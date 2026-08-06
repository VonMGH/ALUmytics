<?php
// Handle AJAX requests for live chart updates FIRST, before any output
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    include '../db/Database.php';
    include '../db/LocationCoordinates.php'; // Include new coordinate system
    include 'includes/access_control.php'; // Ensure filter functions are available for AJAX
    $conn = Database::getInstance()->getConnection();
    
    // Get filter values from GET for AJAX
    $filter_school = $_GET['school-university'] ?? '';
    $filter_campus = $_GET['campus-branch'] ?? '';
    $filter_college = $_GET['college-department'] ?? '';
    $filter_major = (isset($_GET['major-specialization']) && $_GET['major-specialization'] !== 'all') ? $_GET['major-specialization'] : '';
    $filter_year = (isset($_GET['year']) && $_GET['year'] !== 'all') ? $_GET['year'] : '';
    
    // Build filters based on role permissions
    $filters = [
        'school' => $filter_school,
        'campus' => $filter_campus,
        'college' => $filter_college,
        'major' => $filter_major,
        'year' => $filter_year
    ];
    
    $eduWhere = buildFilterWhereConditions($conn, $filters, 'e');
    
    // PSGC cached map helpers (code -> province name) - matches index.php approach
    function geo_getPSGCProvinceMap() {
        static $map = null;
        if ($map !== null) return $map;
        $map = [];

        // Persistent cache file with TTL
        $cacheDir = realpath(__DIR__ . '/../db');
        if ($cacheDir === false) { $cacheDir = __DIR__ . '/../db'; }
        $cacheDir .= DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'psgc_provinces.json';
        $ttl = 7 * 24 * 60 * 60; // 7 days

        $cachedData = null;
        $cacheFresh = false;
        if (is_file($cacheFile)) {
            $mtime = @filemtime($cacheFile);
            $cacheFresh = $mtime && (time() - $mtime < $ttl);
            $raw = @file_get_contents($cacheFile);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) { $cachedData = $decoded; }
            }
            if ($cacheFresh && is_array($cachedData)) {
                $map = $cachedData;
                return $map; // Fresh cache hit
            }
        }

        // Cache miss or stale: try fetching once with short timeout
        $url = 'https://psgc.gitlab.io/api/provinces/';
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 2],
            'https' => ['method' => 'GET', 'timeout' => 2],
        ]);
        try {
            $resp = @file_get_contents($url, false, $context);
            if ($resp) {
                $json = json_decode($resp, true);
                if (is_array($json)) {
                    $tmp = [];
                    foreach ($json as $prov) {
                        if (!empty($prov['code']) && !empty($prov['name'])) {
                            $tmp[$prov['code']] = $prov['name'];
                        }
                    }
                    if (!empty($tmp)) {
                        $map = $tmp;
                        @file_put_contents($cacheFile, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        return $map;
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Fallback: return stale cache if available, else empty map
        if (is_array($cachedData)) { return $cachedData; }
        return $map;
    }
    function geo_resolveProvinceName($value) {
        $val = trim((string)$value);
        if ($val === '') return $val;
        // Only resolve if it's all digits (PSGC code)
        if (!preg_match('/^\d+$/', $val)) return $val;
        $map = geo_getPSGCProvinceMap();
        return $map[$val] ?? $val;
    }

    // Geography data queries
    $geoJoin = ($eduWhere || $filter_year) ? "JOIN education e ON emp.user_id = e.user_id" : '';
    $geoWhere = $eduWhere ? $eduWhere : [];
    if ($filter_year) {
        $geoWhere[] = "TRIM(e.year_graduated) = '" . $conn->real_escape_string(trim($filter_year)) . "'";
    }
    $geoWhereSql = $geoWhere ? 'WHERE ' . implode(' AND ', $geoWhere) : '';

    // Build safe prefix for additional conditions in AJAX block
    $wherePrefixAjax = $geoWhere ? ($geoWhereSql . ' AND ') : 'WHERE ';

    // Geographic distribution: country for international, province for local,
    // and distinguish current vs past jobs (same logic as dashboard heatmap)
    $geoDistribution = $conn->query("SELECT 
        CASE WHEN emp.mobility = 'international' THEN emp.company_country
             ELSE COALESCE(emp.company_province, ca.company_province) END AS location_key,
        emp.mobility,
        CASE 
            WHEN emp.end_date IS NOT NULL AND emp.end_date <> '' 
                 AND STR_TO_DATE(emp.end_date, '%Y-%m-%d') <= CURDATE() THEN 'past'
            ELSE 'current'
        END AS job_state,
        COUNT(*) as count 
    FROM employment emp
    $geoJoin
    LEFT JOIN company_address ca ON emp.user_id = ca.user_id 
    $geoWhereSql 
    GROUP BY location_key, emp.mobility, job_state
    ORDER BY count DESC");

    $currentHeatmapPoints = [];
    $pastHeatmapPoints = [];
    $localTotals = [];
    $internationalTotals = [];

    if ($geoDistribution && $geoDistribution->num_rows > 0) {
        while($row = $geoDistribution->fetch_assoc()) {
            $key = $row['location_key'] ?: 'Unknown';
            $isIntl = ($row['mobility'] === 'international');
            $jobState = ($row['job_state'] === 'past') ? 'past' : 'current';
            $display = $isIntl ? $key : geo_resolveProvinceName($key);
            $count = (int)$row['count'];

            $coordinates = LocationCoordinates::getCoordinates(null, $display);
            $point = [$coordinates[0], $coordinates[1], $count];
            if ($jobState === 'past') {
                $pastHeatmapPoints[] = $point;
            } else {
                $currentHeatmapPoints[] = $point;
            }

            // Accumulate totals for topCities charts (local vs international)
            // Skip "Unknown" entries for the charts (unemployed)
            if ($key !== 'Unknown') {
                if ($isIntl) {
                    if (!isset($internationalTotals[$display])) {
                        $internationalTotals[$display] = 0;
                    }
                    $internationalTotals[$display] += $count;
                } else {
                    if (!isset($localTotals[$display])) {
                        $localTotals[$display] = 0;
                    }
                    $localTotals[$display] += $count;
                }
            }
        }
    }

    // Build sorted top local and international locations (top 10 each)
    $localProvinces = [];
    $localData = [];
    if (!empty($localTotals)) {
        arsort($localTotals);
        $localSlice = array_slice($localTotals, 0, 10, true);
        foreach ($localSlice as $name => $total) {
            $localProvinces[] = $name;
            $localData[] = (int)$total;
        }
    }

    $internationalProvinces = [];
    $internationalData = [];
    if (!empty($internationalTotals)) {
        arsort($internationalTotals);
        $intlSlice = array_slice($internationalTotals, 0, 10, true);
        foreach ($intlSlice as $name => $total) {
            $internationalProvinces[] = $name;
            $internationalData[] = (int)$total;
        }
    }

    // Compute metrics for infoboxes (AJAX)
    $ajaxLocal = $conn->query("SELECT COUNT(*) as total FROM employment emp $geoJoin LEFT JOIN company_address ca ON emp.user_id = ca.user_id "
        . $wherePrefixAjax . "emp.mobility <> 'international'")->fetch_assoc()['total'];
    $ajaxInternational = $conn->query("SELECT COUNT(*) as total FROM employment emp $geoJoin LEFT JOIN company_address ca ON emp.user_id = ca.user_id "
        . $wherePrefixAjax . "emp.mobility = 'international'")->fetch_assoc()['total'];
    $ajaxLocations = $conn->query("SELECT COUNT(DISTINCT CASE WHEN emp.mobility='international' THEN emp.company_country ELSE COALESCE(emp.company_province, ca.company_province) END) as total FROM employment emp $geoJoin LEFT JOIN company_address ca ON emp.user_id = ca.user_id "
        . ($geoWhere ? $geoWhereSql : '') . "")->fetch_assoc()['total'];
    $ajaxTotal = $ajaxLocal + $ajaxInternational;
    $ajaxMobility = $ajaxTotal > 0 ? round(($ajaxInternational / $ajaxTotal) * 100, 1) : 0;

    // Return chart data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'heatmapPoints' => [
            'current' => $currentHeatmapPoints,
            'past'    => $pastHeatmapPoints,
        ],
        'topCities' => [
            'local' => ['labels' => $localProvinces, 'data' => $localData],
            'international' => ['labels' => $internationalProvinces, 'data' => $internationalData]
        ],
        'metrics' => [
            'local' => (int)$ajaxLocal,
            'international' => (int)$ajaxInternational,
            'locations' => (int)$ajaxLocations,
            'mobilityRate' => $ajaxMobility
        ]
    ]);
    exit;
}

// For normal/ export requests, include database and access control first
include '../db/Database.php';
include '../db/LocationCoordinates.php'; // Include new coordinate system
include 'includes/access_control.php';

$conn = Database::getInstance()->getConnection();

// Optional PHPWord support for generating real .docx geography reports with PLSP header
$phpWordAvailable = false;
$phpWordAutoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($phpWordAutoload && file_exists($phpWordAutoload)) {
    require_once $phpWordAutoload;
    if (class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        $phpWordAvailable = true;
    }
}

// Handle export requests BEFORE any HTML output
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $exportMetrics = isset($_GET['metrics']) && $_GET['metrics'] == '1';
    $exportCharts = isset($_GET['charts']) && $_GET['charts'] == '1';
    $filename = 'alumytics_geography_export_' . date('Ymd_His');

    // Get filter values from GET
    $filter_school = $_GET['school-university'] ?? '';
    $filter_campus = $_GET['campus-branch'] ?? '';
    $filter_college = $_GET['college-department'] ?? '';
    $filter_major = (isset($_GET['major-specialization']) && $_GET['major-specialization'] !== 'all') ? $_GET['major-specialization'] : '';
    $filter_year = (isset($_GET['year']) && $_GET['year'] !== 'all') ? $_GET['year'] : '';

    // Build filters based on role permissions
    $filters = [
        'school' => $filter_school,
        'campus' => $filter_campus,
        'college' => $filter_college,
        'major' => $filter_major,
        'year' => $filter_year
    ];
    
    $eduWhere = buildFilterWhereConditions($conn, $filters, 'e');

    // Geography-specific joins/where for export
    $geoJoin = ($eduWhere || $filter_year) ? "JOIN education e ON emp.user_id = e.user_id" : '';
    $geoWhere = $eduWhere ? $eduWhere : [];
    if ($filter_year) {
        $geoWhere[] = "TRIM(e.year_graduated) = '" . $conn->real_escape_string(trim($filter_year)) . "'";
    }
    $geoWhereSql = $geoWhere ? 'WHERE ' . implode(' AND ', $geoWhere) : '';

    if ($type === 'word') {
        // Summary metrics for export
        $totalLocal = $conn->query("SELECT COUNT(*) as total FROM employment emp $geoJoin LEFT JOIN company_address ca ON emp.user_id = ca.user_id $geoWhereSql AND ca.company_province NOT IN ('Singapore', 'South Korea', 'Hong Kong')")->fetch_assoc()['total'];
        $totalInternational = $conn->query("SELECT COUNT(*) as total FROM employment emp $geoJoin LEFT JOIN company_address ca ON emp.user_id = ca.user_id $geoWhereSql AND ca.company_province IN ('Singapore', 'South Korea', 'Hong Kong')")->fetch_assoc()['total'];
        $totalLocations = $conn->query("SELECT COUNT(DISTINCT CASE WHEN emp.mobility='international' THEN emp.company_country ELSE COALESCE(emp.company_province, ca.company_province) END) as total FROM employment emp $geoJoin LEFT JOIN company_address ca ON emp.user_id = ca.user_id $geoWhereSql")->fetch_assoc()['total'];
        $totalAll = $totalLocal + $totalInternational;
        $mobilityRate = $totalAll > 0 ? round(($totalInternational / $totalAll) * 100, 1) : 0;

        // Top local vs international locations (similar to AJAX block)
        $geoDistribution = $conn->query("SELECT 
                CASE WHEN emp.mobility = 'international' THEN emp.company_country
                     ELSE COALESCE(emp.company_province, ca.company_province) END AS location_key,
                emp.mobility,
                COUNT(*) as count 
            FROM employment emp
            $geoJoin
            LEFT JOIN company_address ca ON emp.user_id = ca.user_id 
            $geoWhereSql 
            GROUP BY location_key, emp.mobility
            ORDER BY count DESC");

        $localRows = [];
        $internationalRows = [];
        if ($geoDistribution && $geoDistribution->num_rows > 0) {
            while ($row = $geoDistribution->fetch_assoc()) {
                $key = $row['location_key'] ?: 'Unknown';
                $isIntl = ($row['mobility'] === 'international');
                $label = $key;
                $count = (int)$row['count'];
                // Skip "Unknown" entries for exports (unemployed)
                if ($key !== 'Unknown') {
                    if ($isIntl) {
                        $internationalRows[] = [$label, $count];
                    } else {
                        $localRows[] = [$label, $count];
                    }
                }
            }
        }

        // Prefer PHPWord DOCX export when available
        if ($phpWordAvailable) {
            try {
                $phpWord = new \PhpOffice\PhpWord\PhpWord();

                $section = $phpWord->addSection([
                    'marginTop' => 720,
                    'marginRight' => 1440,
                    'marginBottom' => 720,
                    'marginLeft' => 1440,
                ]);

                // PLSP header image (right-aligned, fixed width, keep aspect ratio)
                $headerImagePath = realpath(__DIR__ . '/../PLSP - Export Design.jpg');
                if ($headerImagePath && file_exists($headerImagePath)) {
                    $header = $section->addHeader();
                    $header->addImage(
                        $headerImagePath,
                        [
                            'width' => 450,
                            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT,
                        ]
                    );
                }

                // Title and generated timestamp
                $section->addText(
                    'Geographic Distribution Report',
                    ['bold' => true, 'size' => 16],
                    ['spaceBefore' => 160, 'spaceAfter' => 240]
                );

                $section->addText(
                    'Generated: ' . date('Y-m-d H:i:s'),
                    ['size' => 11],
                    ['spaceAfter' => 240]
                );

                // Summary metrics table
                $section->addText(
                    'Summary Metrics',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 120, 'spaceAfter' => 120]
                );

                $metricsTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $metricsTable->addRow();
                $metricsTable->addCell(4000, ['bgColor' => '219653'])->addText('Metric', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $metricsTable->addCell(4000, ['bgColor' => '219653'])->addText('Value', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                $metricsTable->addRow();
                $metricsTable->addCell()->addText('Local Employment');
                $metricsTable->addCell()->addText((string)$totalLocal);

                $metricsTable->addRow();
                $metricsTable->addCell()->addText('International Employment');
                $metricsTable->addCell()->addText((string)$totalInternational);

                $metricsTable->addRow();
                $metricsTable->addCell()->addText('Total Locations');
                $metricsTable->addCell()->addText((string)$totalLocations);

                $metricsTable->addRow();
                $metricsTable->addCell()->addText('Mobility Rate (International %)');
                $metricsTable->addCell()->addText((string)$mobilityRate . '%');

                // Local distribution table
                if ($localRows) {
                    $section->addText(
                        'Local Alumni Distribution',
                        ['bold' => true, 'size' => 12],
                        ['spaceBefore' => 240, 'spaceAfter' => 120]
                    );

                    $localTable = $section->addTable([
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'cellMargin' => 50,
                        'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                    ]);

                    $localTable->addRow();
                    $localTable->addCell(5000, ['bgColor' => '219653'])->addText('Location', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                    $localTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                    foreach ($localRows as [$label, $count]) {
                        $localTable->addRow();
                        $localTable->addCell()->addText($label);
                        $localTable->addCell()->addText((string)$count);
                    }
                }

                // International distribution table
                if ($internationalRows) {
                    $section->addText(
                        'International Alumni Distribution',
                        ['bold' => true, 'size' => 12],
                        ['spaceBefore' => 240, 'spaceAfter' => 120]
                    );

                    $intlTable = $section->addTable([
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'cellMargin' => 50,
                        'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                    ]);

                    $intlTable->addRow();
                    $intlTable->addCell(5000, ['bgColor' => '219653'])->addText('Location', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                    $intlTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                    foreach ($internationalRows as [$label, $count]) {
                        $intlTable->addRow();
                        $intlTable->addCell()->addText($label);
                        $intlTable->addCell()->addText((string)$count);
                    }
                }

                $section->addText(
                    'Generated by ALUMytics',
                    ['size' => 10],
                    ['spaceBefore' => 240]
                );

                $fileName = 'geography_' . date('Ymd_His') . '.docx';

                if (function_exists('ob_get_level')) {
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                }

                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                header('Cache-Control: max-age=0');

                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
                $writer->save('php://output');
                exit;
            } catch (Throwable $e) {
                // Fall through to plain-text .doc export if anything goes wrong
            }
        }

        // Fallback: original plain-text Word content
        $content  = "Geographic Distribution Report\n";
        $content .= str_repeat('=', 70) . "\n\n";
        $content .= 'Generated: ' . date('Y-m-d H:i:s') . "\n\n";

        $content .= "SUMMARY METRICS\n";
        $content .= str_repeat('-', 70) . "\n";
        $content .= 'Local Employment: ' . $totalLocal . "\n";
        $content .= 'International Employment: ' . $totalInternational . "\n";
        $content .= 'Total Locations: ' . $totalLocations . "\n";
        $content .= 'Mobility Rate (International %): ' . $mobilityRate . "%\n\n";

        if ($localRows) {
            $content .= "LOCAL ALUMNI DISTRIBUTION\n";
            $content .= str_repeat('-', 70) . "\n";
            $content .= str_pad('Location', 40) . "Count\n";
            $content .= str_repeat('-', 40) . str_repeat('-', 10) . "\n";
            foreach ($localRows as [$label, $count]) {
                $content .= str_pad($label, 40) . $count . "\n";
            }
            $content .= "\n";
        }

        if ($internationalRows) {
            $content .= "INTERNATIONAL ALUMNI DISTRIBUTION\n";
            $content .= str_repeat('-', 70) . "\n";
            $content .= str_pad('Location', 40) . "Count\n";
            $content .= str_repeat('-', 40) . str_repeat('-', 10) . "\n";
            foreach ($internationalRows as [$label, $count]) {
                $content .= str_pad($label, 40) . $count . "\n";
            }
            $content .= "\n";
        }

        $content .= str_repeat('=', 70) . "\n";
        $content .= "Generated by ALUMytics\n";

        header('Content-Type: application/msword');
        header('Content-Disposition: attachment; filename="geography_' . date('Ymd_His') . '.doc"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    } elseif ($type === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        
        if ($exportCharts) {
            fputcsv($out, ['Geographic Distribution']);
            fputcsv($out, ['Province', 'City', 'Type', 'Count']);
            // Add geographic data rows here
        }
        fclose($out);
        exit;
    } elseif ($type === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        echo "<table border='1'>";
        if ($exportCharts) {
            echo "<tr><th colspan='4'>Geographic Distribution</th></tr>";
            echo "<tr><td>Province</td><td>City</td><td>Type</td><td>Count</td></tr>";
        }
        echo "</table>";
        exit;
    } elseif ($type === 'pdf') {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>ALUMytics Geography Export</title><style>body{font-family:Arial,sans-serif;padding:32px;}h1{margin-bottom:32px;}h2{margin-top:32px;}table{margin-bottom:32px;border-collapse:collapse;width:100%;}th,td{border:1px solid #333;padding:8px;}th{background:#222;color:#fff;}@media print {button#printBtn { display: none; }}</style></head><body>';
        echo '<button id="printBtn" onclick="window.print()" style="position:fixed;top:24px;right:24px;padding:10px 18px;font-size:16px;z-index:999;">Print or Save as PDF</button>';
        echo '<h1>ALUMytics Geography Export</h1>';
        echo '<script>window.onload = function(){ setTimeout(function(){ window.print(); }, 500); };</script>';
        echo '</body></html>';
        exit;
    }
    exit;
}

// After handling any exports, include layout and continue normal page render
include 'includes/header.php'; // This includes access_control.php
include 'includes/sidebar.php';

// Check module access
requireModuleAccess('geography');

// Get filter values for initial load
$filter_school = $_GET['school-university'] ?? '';
$filter_campus = $_GET['campus-branch'] ?? '';
$filter_college = $_GET['college-department'] ?? '';
$filter_major = (isset($_GET['major-specialization']) && $_GET['major-specialization'] !== 'all') ? $_GET['major-specialization'] : '';
$filter_year = (isset($_GET['year']) && $_GET['year'] !== 'all') ? $_GET['year'] : '';

// For admin and superadmin, always default these filters to "All"
if (in_array($role, ['admin', 'superadmin']) && empty($_GET)) {
    $filter_school = '';
    $filter_campus = '';
    $filter_college = '';
    $filter_major = '';
}

// Build filters based on role permissions
$filters = [
    'school' => $filter_school,
    'campus' => $filter_campus,
    'college' => $filter_college,
    'major' => $filter_major,
    'year' => $filter_year
];

$eduWhere = buildFilterWhereConditions($conn, $filters, 'e');
$filterOptions = getFilterOptions($conn);

// Calculate metrics
$geoJoin = ($eduWhere || $filter_year) ? "JOIN education e ON emp.user_id = e.user_id" : '';
$geoWhere = $eduWhere ? $eduWhere : [];
if ($filter_year) {
    $geoWhere[] = "TRIM(e.year_graduated) = '" . $conn->real_escape_string(trim($filter_year)) . "'";
}
$geoWhereSql = $geoWhere ? 'WHERE ' . implode(' AND ', $geoWhere) : '';

$totalLocal = $conn->query("SELECT COUNT(*) as total FROM employment emp $geoJoin LEFT JOIN company_address ca ON emp.user_id = ca.user_id $geoWhereSql AND ca.company_province NOT IN ('Singapore', 'South Korea', 'Hong Kong')")->fetch_assoc()['total'];
$totalInternational = $conn->query("SELECT COUNT(*) as total FROM employment emp $geoJoin LEFT JOIN company_address ca ON emp.user_id = ca.user_id $geoWhereSql AND ca.company_province IN ('Singapore', 'South Korea', 'Hong Kong')")->fetch_assoc()['total'];
$totalLocations = $conn->query("SELECT COUNT(DISTINCT ca.company_province) as total FROM employment emp $geoJoin LEFT JOIN company_address ca ON emp.user_id = ca.user_id $geoWhereSql")->fetch_assoc()['total'];

$role ??= null;
$college_name ??= null;
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/geography.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-0 dashboard-title">Geographic Distribution</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Viewing data for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Alumni employment locations and mobility patterns worldwide.</p>
                <?php endif; ?>
            </div>
            <div class="export-dropdown">
                <button class="export-button" id="exportButton" type="button">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        <form method="get" action="" id="filterForm">
            <div class="filter-panel">
                <div class="filter-panel-title"><i class="fas fa-filter"></i> Filters</div>
            <div class="filter-controls">
                <?php $availableFilters = getAvailableFilters(); ?>
                
                <?php if (in_array('school', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="school-university">School/University</label>
                    <select id="school-university" name="school-university" class="filter-select" <?= (isCollegeRestricted()) ? 'disabled' : '' ?>>
                        <?php if (isCollegeRestricted()): ?>
                            <?php $filterOptions['schools']->data_seek(0); while($row = $filterOptions['schools']->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['school_university']) ?>" selected><?= htmlspecialchars($row['school_university']) ?></option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" <?= ($filter_school == '') ? 'selected' : '' ?>>All</option>
                            <?php while($row = $filterOptions['schools']->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['school_university']) ?>"><?= htmlspecialchars($row['school_university']) ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('campus', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="campus-branch">Campus/Branch</label>
                    <select id="campus-branch" name="campus-branch" class="filter-select" <?= (isCollegeRestricted()) ? 'disabled' : '' ?>>
                        <?php if (isCollegeRestricted()): ?>
                            <?php $filterOptions['campuses']->data_seek(0); while($row = $filterOptions['campuses']->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['campus_branch']) ?>" selected><?= htmlspecialchars($row['campus_branch']) ?></option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" <?= ($filter_campus == '') ? 'selected' : '' ?>>All</option>
                            <?php while($row = $filterOptions['campuses']->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['campus_branch']) ?>"><?= htmlspecialchars($row['campus_branch']) ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('college', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="college-department">College/Department</label>
                    <select id="college-department" name="college-department" class="filter-select" <?= (isCollegeRestricted()) ? 'disabled' : '' ?>>
                        <?php if (isCollegeRestricted()): ?>
                            <?php $filterOptions['colleges']->data_seek(0); while($row = $filterOptions['colleges']->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['college_department']) ?>" selected><?= htmlspecialchars($row['college_department']) ?></option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" <?= ($filter_college == '') ? 'selected' : '' ?>>All</option>
                            <?php while($row = $filterOptions['colleges']->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['college_department']) ?>"><?= htmlspecialchars($row['college_department']) ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('major', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="major-specialization">Program</label>
                    <select id="major-specialization" name="major-specialization" class="filter-select">
                        <option value="all" <?= ($filter_major == '') ? 'selected' : '' ?>>All</option>
                        <?php while($row = $filterOptions['majors']->fetch_assoc()): ?>
                            <?php $program = htmlspecialchars($row['program']); ?>
                            <option value="<?= $program ?>"><?= $program ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('year', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="year">Graduation Year</label>
                    <select id="year" name="year" class="filter-select">
                        <option value="all" <?= ($filter_year == '' || $filter_year == 'all') ? 'selected' : '' ?>>All</option>
                        <?php
                        $yearsResult = $conn->query("SELECT DISTINCT year_graduated FROM education WHERE year_graduated IS NOT NULL AND year_graduated <> '' ORDER BY year_graduated DESC");
                        if ($yearsResult) {
                            while ($row = $yearsResult->fetch_assoc()) {
                                $yearVal = htmlspecialchars($row['year_graduated']);
                                $selected = ($filter_year == $row['year_graduated']) ? 'selected' : '';
                                echo "<option value=\"$yearVal\" $selected>$yearVal</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            </div>
        </form>
    </div>

    <div class="metrics-container">
        <div class="metric-card">
            <h3>Local Employment</h3>
            <div class="metric-value" id="metricLocal"><?= $totalLocal ?></div>
            <div class="metric-change positive">
                <i class="fas fa-map-marker-alt"></i> Philippines
            </div>
            <div class="icon-container icon-local">
                <i class="fas fa-map-marker-alt"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>International</h3>
            <div class="metric-value" id="metricInternational"><?= $totalInternational ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-globe"></i> Overseas
            </div>
            <div class="icon-container icon-international">
                <i class="fas fa-globe"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Total Locations</h3>
            <div class="metric-value" id="metricLocations"><?= $totalLocations ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-map"></i> Provinces
            </div>
            <div class="icon-container icon-locations">
                <i class="fas fa-map"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Mobility Rate</h3>
            <div class="metric-value" id="metricMobility">
                <?php 
                $total = $totalLocal + $totalInternational;
                $mobilityRate = $total > 0 ? round(($totalInternational / $total) * 100, 1) : 0;
                echo $mobilityRate;
                ?>%
            </div>
            <div class="metric-change neutral">
                <i class="fas fa-plane"></i> International
            </div>
            <div class="icon-container icon-mobility">
                <i class="fas fa-plane"></i>
            </div>
        </div>
    </div>

    <div class="row g-4" id="sortable-charts">
        <div class="col-lg-8 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Alumni Location Heatmap</h5>
                        <small class="text-muted">Geographic distribution of alumni employment</small>
                    </div>
                </div>
                <div class="card-body heatmap-body">
                    <div id="alumniHeatmap"></div>
                    <div id="alumniHeatmap-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="alumniHeatmap-nodata" class="empty-chart-msg" style="display:none;">
                        <p class="mb-0">No data available</p>
                    </div>
                </div>
                <div class="card-footer text-muted">
                    <i class="far fa-clock"></i> Just updated.
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Top Employment Cities</h5>
                        <small class="text-muted">Most popular work locations</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="topCitiesChart"></canvas>
                    <div id="topCitiesChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="topCitiesChart-nodata" class="empty-chart-msg" style="display:none;">
                        <p class="mb-0">No data available</p>
                    </div>
                </div>
                <div class="card-footer text-muted">
                    <i class="far fa-clock"></i> Just updated.
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm dashboard-card geo-table-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Local Alumni Distribution</h5>
                    <small class="text-muted">Philippines-based employment</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm geo-table mb-0" id="localDistributionTable">
                            <thead>
                                <tr>
                                    <th>City</th>
                                    <th>Count</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm dashboard-card geo-table-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">International Alumni Distribution</h5>
                    <small class="text-muted">Overseas employment</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm geo-table mb-0" id="internationalDistributionTable">
                            <thead>
                                <tr>
                                    <th>City</th>
                                    <th>Count</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Geographic Distribution</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="mb-3">
                        <label class="form-label">Select data to export:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="metrics" id="exportMetrics" checked>
                            <label class="form-check-label" for="exportMetrics">Metrics</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="charts" id="exportCharts" checked>
                            <label class="form-check-label" for="exportCharts">Charts</label>
                        </div>
                        <hr/>
                        <label class="form-label">Select export format:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="pdf" id="exportPDF" checked>
                            <label class="form-check-label" for="exportPDF">PDF</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="excel" id="exportExcel">
                            <label class="form-check-label" for="exportExcel">Excel</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="word" id="exportWord">
                            <label class="form-check-label" for="exportWord">Word</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="csv" id="exportCSV">
                            <label class="form-check-label" for="exportCSV">CSV</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="exportForm" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/chart-utils.js"></script>
<script src="js/index.js"></script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Enhanced Export Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="js/export-libraries.js"></script>

<script>
// Global variables
let alumniHeatmapMap, topCitiesChart;

// Create Heatmap with separate layers for current vs past jobs (green vs red)
function createHeatmap(heatmapData) {
    var currentPoints = (heatmapData && heatmapData.current) ? heatmapData.current : [];
    var pastPoints = (heatmapData && heatmapData.past) ? heatmapData.past : [];

    // Clear existing map
    if (alumniHeatmapMap) {
        alumniHeatmapMap.remove();
    }

    if ((currentPoints && currentPoints.length > 0) || (pastPoints && pastPoints.length > 0)) {
        alumniHeatmapMap = L.map('alumniHeatmap').setView([14.5995, 120.9842], 4);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(alumniHeatmapMap);

        if (window.L && window.L.heatLayer) {
            // Current jobs heat layer (green shades)
            if (currentPoints.length > 0) {
                L.heatLayer(currentPoints, {
                    radius: 25,
                    blur: 15,
                    maxZoom: 10,
                    gradient: {0.2: 'lime', 0.6: 'green', 1.0: 'darkgreen'}
                }).addTo(alumniHeatmapMap);
            }

            // Past jobs heat layer (red/orange shades)
            if (pastPoints.length > 0) {
                L.heatLayer(pastPoints, {
                    radius: 25,
                    blur: 15,
                    maxZoom: 10,
                    gradient: {0.2: 'orange', 0.6: 'red', 1.0: 'darkred'}
                }).addTo(alumniHeatmapMap);
            }

            // Add count markers: green for current, red for past
            function addMarkers(points, color) {
                points.forEach(function(pt) {
                    var lat = pt[0], lng = pt[1], count = pt[2];
                    var marker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'count-marker',
                            html: '<div style="background:' + color + ';color:#fff;padding:4px 8px;border-radius:12px;font-size:12px;font-weight:bold;">'+count+'</div>',
                            iconSize: [30, 20],
                            iconAnchor: [15, 10]
                        })
                    });
                    marker.addTo(alumniHeatmapMap);
                });
            }

            addMarkers(currentPoints, 'rgba(33,150,83,0.9)');   // green for current jobs
            addMarkers(pastPoints, 'rgba(211,47,47,0.9)');      // red for past jobs

            // Legend (bottom-right) matching dashboard style
            var legend = L.control({position: 'bottomright'});
            legend.onAdd = function () {
                var div = L.DomUtil.create('div', 'info legend');
                div.innerHTML = '<div style="background:rgba(33,150,83,0.9);width:12px;height:12px;display:inline-block;margin-right:6px;border-radius:2px;"></div>Current Job ' +
                                '<br><div style="background:rgba(211,47,47,0.9);width:12px;height:12px;display:inline-block;margin-right:6px;border-radius:2px;"></div>Past Job';
                return div;
            };
            legend.addTo(alumniHeatmapMap);
        }
    }
}

// Create Top Cities Chart
function createTopCitiesChart(data) {
    const ctx = document.getElementById('topCitiesChart').getContext('2d');
    if (topCitiesChart) topCitiesChart.destroy();
    
    const allLabels = [...data.local.labels, ...data.international.labels];
    const localData = [...data.local.data, ...new Array(data.international.labels.length).fill(0)];
    const internationalData = [...new Array(data.local.labels.length).fill(0), ...data.international.data];
    
    topCitiesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: allLabels,
            datasets: (function(){
                const palette = getDistinctPalette(2);
                const bg = applyAlpha(palette, 0.85);
                return [{
                    label: 'Local',
                    data: localData,
                    backgroundColor: bg[0],
                    borderColor: palette[0],
                    borderWidth: 1
                }, {
                    label: 'International',
                    data: internationalData,
                    backgroundColor: bg[1],
                    borderColor: palette[1],
                    borderWidth: 1
                }];
            })()
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    // Expose globally for export routines
    window.topCitiesChart = topCitiesChart;
}

// Update tables
function updateTables(data) {
    // Update local table
    const localTableBody = document.querySelector('#localDistributionTable tbody');
    localTableBody.innerHTML = '';
    const localTotal = data.local.data.reduce((a, b) => a + b, 0);
    
    data.local.labels.forEach((label, index) => {
        const count = data.local.data[index];
        const percentage = localTotal > 0 ? ((count / localTotal) * 100).toFixed(1) : 0;
        const row = `<tr><td>${label}</td><td>${count}</td><td>${percentage}%</td></tr>`;
        localTableBody.innerHTML += row;
    });
    
    // Update international table
    const intTableBody = document.querySelector('#internationalDistributionTable tbody');
    intTableBody.innerHTML = '';
    const intTotal = data.international.data.reduce((a, b) => a + b, 0);
    
    data.international.labels.forEach((label, index) => {
        const count = data.international.data[index];
        const percentage = intTotal > 0 ? ((count / intTotal) * 100).toFixed(1) : 0;
        const row = `<tr><td>${label}</td><td>${count}</td><td>${percentage}%</td></tr>`;
        intTableBody.innerHTML += row;
    });
}

// Update geography data
function updateGeographyData() {
    // Show loading spinners
    document.querySelectorAll('.spinner').forEach(s => s.style.display = 'flex');
    document.getElementById('alumniHeatmap').style.display = 'none';
    document.getElementById('topCitiesChart').style.display = 'none';
    document.querySelectorAll('[id$="-nodata"]').forEach(n => n.style.display = 'none');
    
    // Get filter values
    const formData = new FormData(document.getElementById('filterForm'));
    const params = new URLSearchParams(formData);
    
    // Make AJAX request
    fetch(`geography.php?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        // Hide spinners
        document.querySelectorAll('.spinner').forEach(s => s.style.display = 'none');
        document.getElementById('alumniHeatmap').style.display = 'block';
        document.getElementById('topCitiesChart').style.display = 'block';
        
        // Update heatmap (expects { current: [...], past: [...] })
        if (data.heatmapPoints && (data.heatmapPoints.current.length > 0 || data.heatmapPoints.past.length > 0)) {
            createHeatmap(data.heatmapPoints);
        } else {
            document.getElementById('alumniHeatmap').style.display = 'none';
            document.getElementById('alumniHeatmap-nodata').style.display = 'block';
        }
        
        // Update top cities chart
        if (data.topCities.local.labels.length > 0 || data.topCities.international.labels.length > 0) {
            createTopCitiesChart(data.topCities);
            updateTables(data.topCities);
        } else {
            document.getElementById('topCitiesChart').style.display = 'none';
            document.getElementById('topCitiesChart-nodata').style.display = 'block';
        }

        // Update metrics infoboxes
        if (data.metrics) {
            var elLocal = document.getElementById('metricLocal');
            var elIntl = document.getElementById('metricInternational');
            var elLocs = document.getElementById('metricLocations');
            if (elLocal) elLocal.textContent = data.metrics.local;
            if (elIntl) elIntl.textContent = data.metrics.international;
            if (elLocs) elLocs.textContent = data.metrics.locations;
            // Mobility rate value is displayed as text with % sign in the first div
            var mobilityDiv = document.querySelector('.metric-card:nth-child(4) .metric-value');
            if (mobilityDiv && typeof data.metrics.mobilityRate !== 'undefined') {
                mobilityDiv.textContent = data.metrics.mobilityRate + '%';
            }
        }
    })
    .catch(error => {
        console.error('Error updating geography data:', error);
        document.querySelectorAll('.spinner').forEach(s => s.style.display = 'none');
        document.querySelectorAll('[id$="-nodata"]').forEach(n => n.style.display = 'block');
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateGeographyData();
    
    // Add event listeners to filter controls
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', updateGeographyData);
    });
});

// Export functionality
document.addEventListener('DOMContentLoaded', function() {
    // Function to capture chart images for PDF export
    function captureChartImages() {
        const chartImages = {};
        
        // Capture top cities chart (prefer Chart.js API with canvas fallback)
        if (topCitiesChart) {
            try {
                if (typeof topCitiesChart.toBase64Image === 'function') {
                    chartImages.topCities = topCitiesChart.toBase64Image();
                } else if (topCitiesChart.canvas) {
                    chartImages.topCities = topCitiesChart.canvas.toDataURL('image/png', 1.0);
                }
            } catch (error) {
                console.warn('Failed to capture top cities chart:', error);
            }
        }
        
        // Do not include alumni heatmap in export
        
        return chartImages;
    }
    
    // Function to generate export data
    function generateExportData(includeMetrics = true, includeCharts = true) {
        const data = [];
        
        // Title
        data.push(['GEOGRAPHIC DISTRIBUTION ANALYSIS']);
        data.push(['']);
        
        if (includeMetrics) {
            // Metrics section
            data.push(['LOCATION METRICS']);
            data.push(['Metric', 'Value']);
            data.push(['Local Employment', '<?= $totalLocal ?>']);
            data.push(['International Employment', '<?= $totalInternational ?>']);
            data.push(['Total Locations', '<?= $totalLocations ?>']);
            data.push(['Mobility Rate', '<?= ($totalLocal + $totalInternational) > 0 ? round(($totalInternational / ($totalLocal + $totalInternational)) * 100, 1) : 0 ?>%']);
            data.push(['']);
        }
        
        if (includeCharts) {
            // Chart markers for PDF
            data.push(['TOP EMPLOYMENT CITIES']);
            data.push(['__CHART_IMAGE__', 'topCities']);
            data.push(['This chart shows the main cities where alumni are employed.']);
            data.push(['']);
            // Do not include alumni heatmap in export
            
            // Add table data from current view
            const localTable = document.querySelector('#localDistributionTable tbody');
            if (localTable && localTable.children.length > 0) {
                data.push(['LOCAL DISTRIBUTION TABLE']);
                data.push(['City', 'Count']);
                Array.from(localTable.children).forEach(row => {
                    const cells = Array.from(row.cells);
                    if (cells.length >= 2) {
                        data.push([cells[0].textContent, cells[1].textContent]);
                    }
                });
                data.push(['']);
            }
            
            const internationalTable = document.querySelector('#internationalDistributionTable tbody');
            if (internationalTable && internationalTable.children.length > 0) {
                data.push(['INTERNATIONAL DISTRIBUTION TABLE']);
                data.push(['City', 'Count']);
                Array.from(internationalTable.children).forEach(row => {
                    const cells = Array.from(row.cells);
                    if (cells.length >= 2) {
                        data.push([cells[0].textContent, cells[1].textContent]);
                    }
                });
                data.push(['']);
            }
        }
        
        return data;
    }
    
    function cleanupModalArtifacts() {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(function(el) {
            el.remove();
        });
    }

    function getExportModalInstance() {
        var modalEl = document.getElementById('exportModal');
        if (!modalEl) return null;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            return bootstrap.Modal.getOrCreateInstance(modalEl);
        }
        return null;
    }

    function openExportModal() {
        var modal = getExportModalInstance();
        if (modal) modal.show();
    }

    var exportModalEl = document.getElementById('exportModal');
    if (exportModalEl) {
        exportModalEl.addEventListener('hidden.bs.modal', cleanupModalArtifacts);
    }

    var exportButton = document.getElementById('exportButton');
    if (exportButton) {
        exportButton.addEventListener('click', openExportModal);
    }
    
    var exportForm = document.getElementById('exportForm');
    if (exportForm) {
        exportForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            var metricsEl = document.getElementById('exportMetrics');
            var chartsEl = document.getElementById('exportCharts');
            var pdfEl = document.getElementById('exportPDF');
            var excelEl = document.getElementById('exportExcel');
            var wordEl = document.getElementById('exportWord');
            var csvEl = document.getElementById('exportCSV');
            const metrics = metricsEl && metricsEl.checked;
            const charts = chartsEl && chartsEl.checked;
            const pdf = pdfEl && pdfEl.checked;
            const excel = excelEl && excelEl.checked;
            const word = wordEl && wordEl.checked;
            const csv = csvEl && csvEl.checked;
            
            if (!metrics && !charts) {
                alert('Please select at least one data type to export.');
                return;
            }
            
            if (!pdf && !excel && !word && !csv) {
                alert('Please select at least one format.');
                return;
            }
            
            if (word) {
                const formData = new FormData(document.getElementById('filterForm'));
                const params = new URLSearchParams(formData);
                const url = 'geography.php?' + params.toString() +
                    '&export=word' +
                    '&metrics=' + (metrics ? '1' : '0') +
                    '&charts=' + (charts ? '1' : '0');
                window.location.href = url;
                return;
            }
            
            const exportData = generateExportData(metrics, charts);
            const chartImages = charts ? captureChartImages() : {};
            const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
            
            if (window.exportLibrary) {
                try {
                    if (pdf) {
                        window.exportLibrary.exportToPDF(
                            exportData, 
                            `alumytics_geography_${timestamp}.pdf`,
                            {
                                chartImages: chartImages,
                                config: window.exportLibrary.createConfig('standard')
                            },
                            'ALUMytics Geographic Distribution Report'
                        );
                    }
                    
                    if (excel) {
                        window.exportLibrary.exportToExcel(
                            exportData, 
                            `alumytics_geography_${timestamp}.xlsx`,
                            { sheetName: 'Geographic Distribution' }
                        );
                    }
                    
                    if (csv) {
                        window.exportLibrary.exportToCSV(
                            exportData, 
                            `alumytics_geography_${timestamp}.csv`
                        );
                    }
                    
                    var modal = getExportModalInstance();
                    if (modal) modal.hide();
                    cleanupModalArtifacts();
                    
                } catch (error) {
                    console.error('Export error:', error);
                    alert('Export failed. Please try again.');
                }
            } else {
                alert('Export library not loaded. Please refresh the page and try again.');
            }
        });
    }
});
</script>
