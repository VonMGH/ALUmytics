<?php
include '../db/Database.php';
include 'includes/access_control.php';

requireModuleAccess('report-generation');

$conn = Database::getInstance()->getConnection();

$role ??= null;
$college_id ??= null;
$college_name ??= null;

if (!function_exists('getCityDisplayName')) {
    function getCityDisplayName(string $cityCode): string
    {
        $cityCode = trim($cityCode);
        if ($cityCode === '') {
            return '';
        }

        static $cityNameCache = [];
        if (isset($cityNameCache[$cityCode])) {
            return $cityNameCache[$cityCode];
        }

        // If it's not a numeric PSGC-style code, just return as-is
        if (!preg_match('/^\d+$/', $cityCode)) {
            $cityNameCache[$cityCode] = $cityCode;
            return $cityCode;
        }

        $display = $cityCode;
        $url = 'https://psgc.gitlab.io/api/cities-municipalities/' . rawurlencode($cityCode) . '/';
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
            ],
        ]);
        $json = @file_get_contents($url, false, $context);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['name'])) {
                $display = $data['name'];
            }
        }

        $cityNameCache[$cityCode] = $display;
        return $display;
    }
}

// Report category controls which subfilters are shown (Demographics / Employment / Geography)
$reportCategory = $_GET['category'] ?? 'demographics';

// Shared filters (Demographics / Employment / Geography)
$filterDepartment       = $_GET['department'] ?? 'all';
$filterGender           = $_GET['gender'] ?? 'all';
$filterAgeRange         = $_GET['age_range'] ?? 'all';
$filterEmploymentStatus = $_GET['employment_status'] ?? 'all';
$filterMobility         = $_GET['mobility'] ?? 'all';
// Separate from selectedIndustries used for IT vs Non-IT grouping
$filterIndustryFilter   = $_GET['industry'] ?? 'all';
$filterWorkArrangement  = $_GET['work_arrangement'] ?? 'all';
$filterCompanyType      = $_GET['company_type'] ?? 'all';
$filterGeoCountry       = $_GET['geo_country'] ?? 'all';
$filterGeoCity          = $_GET['geo_city'] ?? 'all';

// Optional PHPWord support for generating real .docx reports with PLSP header
$phpWordAvailable = false;
$phpWordAutoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($phpWordAutoload && file_exists($phpWordAutoload)) {
    require_once $phpWordAutoload;
    if (class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        $phpWordAvailable = true;
    }
}

// Load distinct graduation years (respecting college restriction)
$yearsQuery = "SELECT DISTINCT e.year_graduated AS year FROM education e";
if (isCollegeRestricted() && $college_id) {
    $yearsQuery .= " WHERE e.college_department = (SELECT name FROM colleges WHERE id = " . (int)$college_id . ")";
    $yearsQuery .= " AND e.year_graduated IS NOT NULL AND e.year_graduated <> ''";
} else {
    $yearsQuery .= " WHERE e.year_graduated IS NOT NULL AND e.year_graduated <> ''";
}
$yearsQuery .= " ORDER BY year DESC";

$yearsResult = $conn->query($yearsQuery);
$availableYears = [];
if ($yearsResult) {
    while ($row = $yearsResult->fetch_assoc()) {
        $availableYears[] = $row['year'];
    }
}

// Options for department and geography filters (same as Alumni Tracking)
if (isCollegeRestricted() && $college_id) {
    $departments = $conn->query(
        "SELECT name AS college_department FROM colleges WHERE id = " . (int)$college_id . " ORDER BY name"
    );
} else {
    $departments = $conn->query(
        "SELECT name AS college_department FROM colleges ORDER BY name"
    );
}

$geoCountries = $conn->query(
    "SELECT DISTINCT company_country FROM employment WHERE company_country IS NOT NULL AND company_country <> '' ORDER BY company_country"
);
$geoCities = $conn->query(
    "SELECT DISTINCT company_city FROM employment WHERE company_city IS NOT NULL AND company_city <> '' ORDER BY company_city"
);

// Selected years (multi-select via query string)
$selectedYears = isset($_GET['years']) ? (array)$_GET['years'] : [];
$selectedYears = array_values(array_intersect($selectedYears, $availableYears));
if (empty($selectedYears)) {
    $selectedYears = $availableYears;
}

// Load distinct industries for dynamic IT/non-IT breakdown
$industriesQuery = "SELECT DISTINCT industry FROM employment WHERE industry IS NOT NULL AND industry <> '' ORDER BY industry ASC";
$industriesResult = $conn->query($industriesQuery);
$availableIndustries = [];
if ($industriesResult) {
    while ($row = $industriesResult->fetch_assoc()) {
        $availableIndustries[] = $row['industry'];
    }
}

// Selected industries (multi-select); default to Information Technology (or first available)
$selectedIndustries = isset($_GET['industries']) ? (array)$_GET['industries'] : [];
$selectedIndustries = array_values(array_intersect($selectedIndustries, $availableIndustries));
if (empty($selectedIndustries) && !empty($availableIndustries)) {
    if (in_array('Information Technology', $availableIndustries, true)) {
        $selectedIndustries = ['Information Technology'];
    } else {
        $selectedIndustries = [$availableIndustries[0]];
    }
}

// Dynamic labels for IT vs Non-IT columns based on selected industries
$usingDefaultITOnly = (count($selectedIndustries) === 1 && $selectedIndustries[0] === 'Information Technology');

if ($usingDefaultITOnly) {
    $itColumnTitle = 'Employed in IT Field';
    $nonItColumnTitle = 'Employed in Non-IT Field';
    $itShareTitle = '% of Employed in IT';
    $chartLabelIt = 'IT Related';
    $chartLabelNonIt = 'Non-IT Related';
    $itFootnote = '* Employment counts here refer to alumni whose latest recorded employment status is "employed" or "self_employed", with IT referring to "Information Technology" as the recorded industry.';
} else {
    $itColumnTitle = 'Employed in Selected Industries';
    $nonItColumnTitle = 'Employed in Other Industries';
    $itShareTitle = '% of Employed in Selected Industries';
    $chartLabelIt = 'Selected Industries';
    $chartLabelNonIt = 'Other Industries';
    $itFootnote = '* Employment counts here refer to alumni whose latest recorded employment status is "employed" or "self_employed". "Selected industries" are those chosen in the filters; "Other industries" include all remaining industries.';
}

// Labels for dropdown buttons (years / industries)
$yearsDropdownLabel = 'All years';
if (!empty($selectedYears) && count($selectedYears) < count($availableYears)) {
    $labelYears = $selectedYears;
    sort($labelYears);
    $yearsDropdownLabel = implode(', ', $labelYears);
    if (strlen($yearsDropdownLabel) > 30) {
        $yearsDropdownLabel = substr($yearsDropdownLabel, 0, 27) . '...';
    }
}

$industriesDropdownLabel = 'Default selection';
if (!empty($selectedIndustries)) {
    $labelIndustries = $selectedIndustries;
    sort($labelIndustries);
    $industriesDropdownLabel = implode(', ', $labelIndustries);
    if (strlen($industriesDropdownLabel) > 30) {
        $industriesDropdownLabel = substr($industriesDropdownLabel, 0, 27) . '...';
    }
}

// Human-readable descriptions of current filters for exports
if (!empty($selectedYears) && count($selectedYears) < count($availableYears)) {
    $yearsForDescription = $selectedYears;
    sort($yearsForDescription);
    $yearsFilterDescription = 'years ' . implode(', ', $yearsForDescription);
} else {
    $yearsFilterDescription = 'all available years';
}

if (!empty($selectedIndustries)) {
    $industriesForDescription = $selectedIndustries;
    sort($industriesForDescription);
    $industriesFilterDescription = 'industries ' . implode(', ', $industriesForDescription);
} else {
    $industriesFilterDescription = 'the default industry selection (for example, Information Technology)';
}

// Optional manual override for total graduates per year, provided via query string
$manualTotals = [];
if (isset($_GET['manual_total']) && is_array($_GET['manual_total'])) {
    foreach ($_GET['manual_total'] as $yearKey => $val) {
        if ($val === '' || $val === null) {
            continue;
        }
        if (is_numeric($val) && (int)$val >= 0) {
            $manualTotals[$yearKey] = (int)$val;
        }
    }
}

$yearRows = [];
$chartTotals = [
    'employed_it' => 0,
    'employed_non_it' => 0,
];

if (!empty($selectedYears)) {
    $safeYears = array_map(function ($y) use ($conn) {
        return "'" . $conn->real_escape_string($y) . "'";
    }, $selectedYears);
    $yearsInSql = implode(',', $safeYears);

    $where = [];
    $where[] = "u.role = 'alumni'";
    $where[] = "e.year_graduated IN ($yearsInSql)";

    if (isCollegeRestricted() && $college_id) {
        $where[] = "e.college_department = (SELECT name FROM colleges WHERE id = " . (int)$college_id . ")";
    }

    // Demographics filters
    if ($filterDepartment !== 'all' && $filterDepartment !== 'none' && $filterDepartment !== '') {
        $safeDept = $conn->real_escape_string($filterDepartment);
        $where[] = "e.college_department = '{$safeDept}'";
    }

    if ($filterGender !== 'all' && $filterGender !== 'none' && $filterGender !== '') {
        $safeGender = $conn->real_escape_string($filterGender);
        $where[] = "LOWER(p.sex) = LOWER('{$safeGender}')";
    }

    if ($filterAgeRange !== 'all' && $filterAgeRange !== 'none' && $filterAgeRange !== '') {
        switch ($filterAgeRange) {
            case '18-25':
                $where[] = "TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) BETWEEN 18 AND 25";
                break;
            case '26-35':
                $where[] = "TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) BETWEEN 26 AND 35";
                break;
            case '36-45':
                $where[] = "TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) BETWEEN 36 AND 45";
                break;
            case '46_plus':
                $where[] = "TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) >= 46";
                break;
        }
    }

    // Employment filters
    if ($filterEmploymentStatus !== 'all' && $filterEmploymentStatus !== 'none' && $filterEmploymentStatus !== '') {
        $safeStatus = $conn->real_escape_string($filterEmploymentStatus);
        $where[] = "emp.employment_status = '{$safeStatus}'";
    }

    if ($filterMobility !== 'all' && $filterMobility !== 'none' && $filterMobility !== '') {
        $safeMobility = $conn->real_escape_string($filterMobility);
        $where[] = "emp.mobility = '{$safeMobility}'";
    }

    // Note: explicit "Industries" employment filter has been removed from the UI
    // to avoid redundancy with the IT/Non-IT grouping below, so
    // $filterIndustryFilter is no longer applied here.

    if ($filterWorkArrangement !== 'all' && $filterWorkArrangement !== 'none' && $filterWorkArrangement !== '') {
        $safeWA = $conn->real_escape_string($filterWorkArrangement);
        $where[] = "emp.work_arrangement = '{$safeWA}'";
    }

    if ($filterCompanyType !== 'all' && $filterCompanyType !== 'none' && $filterCompanyType !== '') {
        $safeCompanyType = $conn->real_escape_string($filterCompanyType);
        $where[] = "emp.company_type = '{$safeCompanyType}'";
    }

    // Geography filters (applied when viewing Geography category)
    if ($reportCategory === 'geography') {
        if ($filterGeoCountry !== 'all' && $filterGeoCountry !== 'none' && $filterGeoCountry !== '') {
            $safeCountry = $conn->real_escape_string($filterGeoCountry);
            $where[] = "emp.company_country = '{$safeCountry}'";
        }
        if ($filterGeoCity !== 'all' && $filterGeoCity !== 'none' && $filterGeoCity !== '') {
            $safeCity = $conn->real_escape_string($filterGeoCity);
            $where[] = "emp.company_city = '{$safeCity}'";
        }
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // Build dynamic industry filters for IT vs Non-IT
    $industryInSql = '';
    if (!empty($selectedIndustries)) {
        $safeIndustries = array_map(function ($ind) use ($conn) {
            return "'" . $conn->real_escape_string($ind) . "'";
        }, $selectedIndustries);
        $industryInSql = implode(',', $safeIndustries);
    }

    // Per-year employment summary (employed overall, in-field vs other), where
    // in-field = employed AND (industry in selected industries OR it_category = 'IT')
    // other   = employed AND NOT in-field
    // Also count traced graduates (any alumni with at least one employment record)
    $sql = "SELECT 
                e.year_graduated AS year,
                COUNT(DISTINCT u.user_id) AS total_graduates,
                COUNT(DISTINCT CASE WHEN emp.id IS NOT NULL THEN u.user_id END) AS traced_graduates,
                COUNT(DISTINCT CASE WHEN emp.id IS NOT NULL AND emp.employment_status IN ('employed','self_employed') THEN u.user_id END) AS employed_graduates,
                COUNT(DISTINCT CASE WHEN emp.id IS NOT NULL AND emp.employment_status IN ('employed','self_employed') AND (" . (!empty($industryInSql) ? "emp.industry IN ($industryInSql) OR " : "") . "emp.it_category = 'IT') THEN u.user_id END) AS employed_it,
                COUNT(DISTINCT CASE WHEN emp.id IS NOT NULL AND emp.employment_status IN ('employed','self_employed') AND (emp.it_category IS NULL OR emp.it_category <> 'IT') AND (emp.industry IS NULL" . (!empty($industryInSql) ? " OR emp.industry NOT IN ($industryInSql)" : "") . ") THEN u.user_id END) AS employed_non_it
            FROM users u
            JOIN education e ON e.user_id = u.user_id
            LEFT JOIN personal p ON p.user_id = u.user_id
            LEFT JOIN employment emp ON emp.user_id = u.user_id AND emp.id = (
                SELECT MAX(id) FROM employment WHERE user_id = u.user_id
            )
            $whereSql
            GROUP BY e.year_graduated
            ORDER BY e.year_graduated DESC";

    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $year = $row['year'];

            // Base values from DB
            $dbTotal = (int)$row['total_graduates'];
            $traced = (int)$row['traced_graduates'];
            $employed = (int)$row['employed_graduates'];
            $employedIt = (int)$row['employed_it'];
            $employedNonIt = (int)$row['employed_non_it'];

            // If a manual total was provided for this year, prefer it over the DB total
            $manualKey = (string)$year;
            if (array_key_exists($manualKey, $manualTotals)) {
                $total = max(0, (int)$manualTotals[$manualKey]);
            } else {
                $total = $dbTotal;
            }

            $untraced = max(0, $total - $traced);

            $employmentRate = $total > 0 ? round(($employed / $total) * 100, 2) : 0;
            $itShare = $employed > 0 ? round(($employedIt / $employed) * 100, 2) : 0;
            $nonItShare = $employed > 0 ? round(($employedNonIt / $employed) * 100, 2) : 0;
            $pctTraced = $total > 0 ? round(($traced / $total) * 100, 2) : 0;
            $pctUntraced = $total > 0 ? round(($untraced / $total) * 100, 2) : 0;

            $yearRows[] = [
                'year' => $year,
                'total' => $total,
                'traced' => $traced,
                'untraced' => $untraced,
                'employed' => $employed,
                'employed_it' => $employedIt,
                'employed_non_it' => $employedNonIt,
                'employment_rate' => $employmentRate,
                'it_share' => $itShare,
                'non_it_share' => $nonItShare,
                'pct_traced' => $pctTraced,
                'pct_untraced' => $pctUntraced,
            ];

            $chartTotals['employed_it'] += $employedIt;
            $chartTotals['employed_non_it'] += $employedNonIt;
        }
    }
}

// Handle Word export for Year Comparison summary table
if (isset($_GET['export']) && $_GET['export'] === 'word' && !empty($yearRows)) {
    // Compute totals for the TOTAL row (respecting manual totals)
    $sumTotal = 0;
    $sumEmployed = 0;
    $sumIt = 0;
    $sumNonIt = 0;
    $sumTraced = 0;
    $sumUntraced = 0;
    foreach ($yearRows as $r) {
        $sumTotal += (int)$r['total'];
        $sumEmployed += (int)$r['employed'];
        $sumIt += (int)$r['employed_it'];
        $sumNonIt += (int)$r['employed_non_it'];
        $sumTraced += (int)$r['traced'];
        $sumUntraced += (int)$r['untraced'];
    }
    $overallEmploymentRate = $sumTotal > 0 ? round(($sumEmployed / $sumTotal) * 100, 2) : 0;
    $overallItShare = $sumEmployed > 0 ? round(($sumIt / $sumEmployed) * 100, 2) : 0;
    $overallNonItShare = $sumEmployed > 0 ? round(($sumNonIt / $sumEmployed) * 100, 2) : 0;
    $overallPctTraced = $sumTotal > 0 ? round(($sumTraced / $sumTotal) * 100, 2) : 0;
    $overallPctUntraced = $sumTotal > 0 ? round(($sumUntraced / $sumTotal) * 100, 2) : 0;

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

            // Title and intro (add a bit of space below the header)
            $section->addText(
                'Year Comparison Report',
                ['bold' => true, 'size' => 16],
                ['spaceBefore' => 160, 'spaceAfter' => 240]
            );

            $section->addText(
                'Generated on ' . date('Y-m-d H:i:s'),
                ['size' => 11],
                ['spaceAfter' => 120]
            );

            $section->addText(
                'This report is based on ' . $yearsFilterDescription . ' and ' . $industriesFilterDescription . '.',
                ['size' => 11],
                ['spaceAfter' => 240]
            );

            $section->addText(
                'Year Comparison Summary (Employed Graduates)',
                ['bold' => true, 'size' => 12],
                ['spaceBefore' => 120, 'spaceAfter' => 120]
            );

            // Table
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 50,
                'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
            ]);

            $headerRow = [
                'Year Graduated',
                'Total Graduates',
                'Traced Graduates',
                'Untraced Graduates',
                '% Traced',
                '% Untraced',
                'Employed Graduates',
                $itColumnTitle,
                $nonItColumnTitle,
                'Employment Rate',
                $itShareTitle,
            ];

            $table->addRow();
            foreach ($headerRow as $col) {
                $table->addCell(1600, ['bgColor' => '219653'])->addText(
                    $col,
                    ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]
                );
            }

            foreach ($yearRows as $row) {
                $table->addRow();
                $table->addCell()->addText($row['year']);
                $table->addCell()->addText($row['total']);
                $table->addCell()->addText($row['traced']);
                $table->addCell()->addText($row['untraced']);
                $table->addCell()->addText(number_format($row['pct_traced'], 2) . '%');
                $table->addCell()->addText(number_format($row['pct_untraced'], 2) . '%');
                $table->addCell()->addText($row['employed']);
                $table->addCell()->addText($row['employed_it']);
                $table->addCell()->addText($row['employed_non_it']);
                $table->addCell()->addText(number_format($row['employment_rate'], 2) . '%');
                $table->addCell()->addText(number_format($row['it_share'], 2) . '%');
            }

            // TOTAL row
            $table->addRow();
            $table->addCell()->addText('TOTAL', ['bold' => true]);
            $table->addCell()->addText($sumTotal, ['bold' => true]);
            $table->addCell()->addText($sumTraced, ['bold' => true]);
            $table->addCell()->addText($sumUntraced, ['bold' => true]);
            $table->addCell()->addText(number_format($overallPctTraced, 2) . '%', ['bold' => true]);
            $table->addCell()->addText(number_format($overallPctUntraced, 2) . '%', ['bold' => true]);
            $table->addCell()->addText($sumEmployed, ['bold' => true]);
            $table->addCell()->addText($sumIt, ['bold' => true]);
            $table->addCell()->addText($sumNonIt, ['bold' => true]);
            $table->addCell()->addText(number_format($overallEmploymentRate, 2) . '%', ['bold' => true]);
            $table->addCell()->addText(number_format($overallItShare, 2) . '%', ['bold' => true]);

            // Footnote and footer
            $section->addText(
                $itFootnote,
                ['italic' => true, 'size' => 10],
                ['spaceBefore' => 240, 'spaceAfter' => 120]
            );

            $section->addText(
                'Generated by ALUMytics',
                ['size' => 10],
                ['spaceBefore' => 120]
            );

            // Stream DOCX
            $fileName = 'year_comparison_' . date('Ymd_His') . '.docx';

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
            // Fall through to HTML .doc export on any error
        }
    }

    // Fallback: original HTML .doc export
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="year_comparison_' . date('Ymd_His') . '.doc"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html><head><meta charset="UTF-8"><title>Year Comparison Report</title></head><body>';
    echo '<h2>Year Comparison Report</h2>';
    echo '<p>Generated on ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p>This report is based on ' . htmlspecialchars($yearsFilterDescription) . ' and ' . htmlspecialchars($industriesFilterDescription) . '.</p>';

    echo '<h3>Year Comparison Summary (Employed Graduates)</h3>';
    echo '<table border="1" cellspacing="0" cellpadding="4" style="width:100%; border-collapse:collapse; font-size:11px;">';
    echo '<tr>';
    echo '<th>Year Graduated</th>';
    echo '<th>Total Graduates</th>';
    echo '<th>Traced Graduates</th>';
    echo '<th>Untraced Graduates</th>';
    echo '<th>% Traced</th>';
    echo '<th>% Untraced</th>';
    echo '<th>Employed Graduates</th>';
    echo '<th>' . htmlspecialchars($itColumnTitle) . '</th>';
    echo '<th>' . htmlspecialchars($nonItColumnTitle) . '</th>';
    echo '<th>Employment Rate</th>';
    echo '<th>' . htmlspecialchars($itShareTitle) . '</th>';
    echo '</tr>';

    foreach ($yearRows as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['year']) . '</td>';
        echo '<td>' . (int)$row['total'] . '</td>';
        echo '<td>' . (int)$row['traced'] . '</td>';
        echo '<td>' . (int)$row['untraced'] . '</td>';
        echo '<td>' . number_format($row['pct_traced'], 2) . '%</td>';
        echo '<td>' . number_format($row['pct_untraced'], 2) . '%</td>';
        echo '<td>' . (int)$row['employed'] . '</td>';
        echo '<td>' . (int)$row['employed_it'] . '</td>';
        echo '<td>' . (int)$row['employed_non_it'] . '</td>';
        echo '<td>' . number_format($row['employment_rate'], 2) . '%</td>';
        echo '<td>' . number_format($row['it_share'], 2) . '%</td>';
        echo '</tr>';
    }

    echo '<tr>';
    echo '<td><strong>TOTAL</strong></td>';
    echo '<td><strong>' . $sumTotal . '</strong></td>';
    echo '<td><strong>' . $sumTraced . '</strong></td>';
    echo '<td><strong>' . $sumUntraced . '</strong></td>';
    echo '<td><strong>' . number_format($overallPctTraced, 2) . '%</strong></td>';
    echo '<td><strong>' . number_format($overallPctUntraced, 2) . '%</strong></td>';
    echo '<td><strong>' . $sumEmployed . '</strong></td>';
    echo '<td><strong>' . $sumIt . '</strong></td>';
    echo '<td><strong>' . $sumNonIt . '</strong></td>';
    echo '<td><strong>' . number_format($overallEmploymentRate, 2) . '%</strong></td>';
    echo '<td><strong>' . number_format($overallItShare, 2) . '%</strong></td>';
    echo '</tr>';

    echo '</table>';
    echo '<p><em>' . htmlspecialchars($itFootnote) . '</em></p>';
    echo '<p>Generated by ALUMytics</p>';
    echo '</body></html>';
    exit;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/report-generation.css">
<link rel="stylesheet" href="css/report-generation-tracer.css">
<link rel="stylesheet" href="css/report-generation-year-comparison.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top d-flex justify-content-between align-items-start flex-wrap">
            <div class="mb-2 mb-md-0">
                <h1 class="mb-0 dashboard-title">Year Comparison Report</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Reporting for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Compare multiple graduation years based on employed graduates (IT field vs non-IT), with table and chart views plus export options.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form method="get" class="mt-1">
        <div class="card dashboard-card shadow-sm rg-filter-card rt-filter-card">
            <div class="card-body">
                <div class="filter-panel-title mb-3"><i class="fas fa-filter"></i> Report Filters</div>
                <div class="row g-3 align-items-end filter-main-row">
                    <div class="col-md-4 category-col">
                        <label for="ycReportCategory" class="form-label rg-category-label">Select Report Category</label>
                        <select id="ycReportCategory" name="category" class="form-select">
                            <option value="demographics" <?= $reportCategory === 'demographics' ? 'selected' : '' ?>>Demographics</option>
                            <option value="employment" <?= $reportCategory === 'employment' ? 'selected' : '' ?>>Employment</option>
                            <option value="geography" <?= $reportCategory === 'geography' ? 'selected' : '' ?>>Geography</option>
                        </select>
                        <small class="text-muted">Category affects how this list will be summarized and exported later.</small>
                    </div>
                    <div class="col-md-8 subfilters-col">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <label class="form-label mb-0 me-2 rg-subfilters-label">
                                <?php if ($reportCategory === 'demographics'): ?>
                                    Demographics Filters
                                <?php elseif ($reportCategory === 'employment'): ?>
                                    Employment Filters
                                <?php elseif ($reportCategory === 'geography'): ?>
                                    Geography Filters
                                <?php else: ?>
                                    Global Filters
                                <?php endif; ?>
                            </label>
                            <div class="d-flex flex-wrap gap-2 flex-grow-1">
                                <?php if ($reportCategory === 'demographics'): ?>
                                    <div style="min-width: 180px;">
                                        <select class="form-select form-select-sm" name="department" aria-label="Degree filter">
                                            <option value="all" <?= $filterDepartment === 'all' ? 'selected' : '' ?>>All Degrees</option>
                                            <?php if ($departments): ?>
                                                <?php while ($row = $departments->fetch_assoc()): ?>
                                                    <?php $dept = $row['college_department']; if (!$dept) continue; ?>
                                                    <option value="<?= htmlspecialchars($dept) ?>" <?= $filterDepartment === $dept ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($dept) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div style="min-width: 140px;">
                                        <select class="form-select form-select-sm" name="gender" aria-label="Gender filter">
                                            <option value="all" <?= $filterGender === 'all' ? 'selected' : '' ?>>All Genders</option>
                                            <option value="male" <?= $filterGender === 'male' ? 'selected' : '' ?>>Male</option>
                                            <option value="female" <?= $filterGender === 'female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    </div>
                                    <div style="min-width: 140px;">
                                        <select class="form-select form-select-sm" name="age_range" aria-label="Age range filter">
                                            <option value="all" <?= $filterAgeRange === 'all' ? 'selected' : '' ?>>All Range</option>
                                            <option value="18-25" <?= $filterAgeRange === '18-25' ? 'selected' : '' ?>>18-25</option>
                                            <option value="26-35" <?= $filterAgeRange === '26-35' ? 'selected' : '' ?>>26-35</option>
                                            <option value="36-45" <?= $filterAgeRange === '36-45' ? 'selected' : '' ?>>36-45</option>
                                            <option value="46_plus" <?= $filterAgeRange === '46_plus' ? 'selected' : '' ?>>46+</option>
                                        </select>
                                    </div>
                                <?php elseif ($reportCategory === 'employment'): ?>
                                    <div style="min-width: 150px;">
                                        <select class="form-select form-select-sm" name="employment_status" aria-label="Employment Status filter">
                                            <option value="all" <?= $filterEmploymentStatus === 'all' ? 'selected' : '' ?>>All Employment Status</option>
                                            <option value="employed" <?= $filterEmploymentStatus === 'employed' ? 'selected' : '' ?>>Employed</option>
                                            <option value="unemployed" <?= $filterEmploymentStatus === 'unemployed' ? 'selected' : '' ?>>Unemployed</option>
                                            <option value="self_employed" <?= $filterEmploymentStatus === 'self_employed' ? 'selected' : '' ?>>Self-Employed</option>
                                        </select>
                                    </div>
                                    <div style="min-width: 150px;">
                                        <select class="form-select form-select-sm" name="work_arrangement" aria-label="Work Arrangement filter">
                                            <option value="all" <?= $filterWorkArrangement === 'all' ? 'selected' : '' ?>>All Work Arrangements</option>
                                            <option value="onsite" <?= $filterWorkArrangement === 'onsite' ? 'selected' : '' ?>>Onsite</option>
                                            <option value="remote" <?= $filterWorkArrangement === 'remote' ? 'selected' : '' ?>>Remote</option>
                                            <option value="hybrid" <?= $filterWorkArrangement === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                        </select>
                                    </div>
                                    <div style="min-width: 160px;">
                                        <select class="form-select form-select-sm" name="company_type" aria-label="Company Type filter">
                                            <option value="all" <?= $filterCompanyType === 'all' ? 'selected' : '' ?>>All Company Types</option>
                                            <option value="public" <?= $filterCompanyType === 'public' ? 'selected' : '' ?>>Public</option>
                                            <option value="private" <?= $filterCompanyType === 'private' ? 'selected' : '' ?>>Private</option>
                                            <option value="ngo" <?= $filterCompanyType === 'ngo' ? 'selected' : '' ?>>NGO</option>
                                            <option value="ingo" <?= $filterCompanyType === 'ingo' ? 'selected' : '' ?>>INGO</option>
                                            <option value="self_employed" <?= $filterCompanyType === 'self_employed' ? 'selected' : '' ?>>Self-Employed</option>
                                            <option value="government" <?= $filterCompanyType === 'government' ? 'selected' : '' ?>>Government</option>
                                        </select>
                                    </div>
                                    <div style="min-width: 140px;">
                                        <select class="form-select form-select-sm" name="mobility" aria-label="Mobility filter">
                                            <option value="all" <?= $filterMobility === 'all' ? 'selected' : '' ?>>All Mobility</option>
                                            <option value="local" <?= $filterMobility === 'local' ? 'selected' : '' ?>>Local</option>
                                            <option value="international" <?= $filterMobility === 'international' ? 'selected' : '' ?>>International</option>
                                        </select>
                                    </div>
                                <?php elseif ($reportCategory === 'geography'): ?>
                                    <div style="min-width: 140px;">
                                        <select class="form-select form-select-sm" name="mobility" aria-label="Mobility filter (Geography)">
                                            <option value="all" <?= $filterMobility === 'all' ? 'selected' : '' ?>>All Mobility</option>
                                            <option value="local" <?= $filterMobility === 'local' ? 'selected' : '' ?>>Local</option>
                                            <option value="international" <?= $filterMobility === 'international' ? 'selected' : '' ?>>International</option>
                                        </select>
                                    </div>
                                    <div style="min-width: 200px;">
                                        <select class="form-select form-select-sm" name="geo_country" aria-label="Country filter">
                                            <option value="all" <?= $filterGeoCountry === 'all' ? 'selected' : '' ?>>All Countries</option>
                                            <?php if ($geoCountries): ?>
                                                <?php while ($row = $geoCountries->fetch_assoc()): ?>
                                                    <?php $country = $row['company_country']; if (!$country) continue; ?>
                                                    <option value="<?= htmlspecialchars($country) ?>" <?= $filterGeoCountry === $country ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($country) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div style="min-width: 200px;">
                                        <select class="form-select form-select-sm" name="geo_city" aria-label="City filter (local)">
                                            <option value="all" <?= $filterGeoCity === 'all' ? 'selected' : '' ?>>All Cities</option>
                                            <?php if ($geoCities): ?>
                                                <?php while ($row = $geoCities->fetch_assoc()): ?>
                                                    <?php $city = $row['company_city']; if (!$city) continue; ?>
                                                    <?php $cityLabel = getCityDisplayName($city); ?>
                                                    <option value="<?= htmlspecialchars($city) ?>" <?= $filterGeoCity === $city ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($cityLabel) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end rt-filter-actions">
                    <button type="submit" class="btn btn-success px-4">Apply Filters</button>
                </div>
            </div>
        </div>
        <div class="card dashboard-card shadow-sm rt-options-card">
            <div class="card-body">
                <div class="row g-4 align-items-start">
                    <div class="col-md-7">
                        <h6 class="rt-section-title">Select Graduation Years</h6>
                        <div class="dropdown w-100">
                            <button class="btn btn-outline-success btn-sm w-100 text-start dropdown-toggle" type="button" id="ycYearsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= htmlspecialchars($yearsDropdownLabel) ?>
                            </button>
                            <div class="dropdown-menu w-100 p-2 rt-dropdown-menu" aria-labelledby="ycYearsDropdown">
                                <?php foreach ($availableYears as $year): ?>
                                    <?php $isSelected = in_array($year, $selectedYears, true); ?>
                                    <div class="form-check mb-1">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="yc_year_<?= htmlspecialchars($year) ?>"
                                            name="years[]"
                                            value="<?= htmlspecialchars($year) ?>"
                                            <?= $isSelected ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label small" for="yc_year_<?= htmlspecialchars($year) ?>">
                                            <?= htmlspecialchars($year) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">Use the dropdown to pick one or more graduation years to compare.</small>
                    </div>
                    <div class="col-md-5">
                        <h6 class="rt-section-title">Select Industries Grouped as "In-Field"</h6>
                        <div class="dropdown w-100 mb-2">
                            <button class="btn btn-outline-secondary btn-sm w-100 text-start dropdown-toggle" type="button" id="ycIndustriesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= htmlspecialchars($industriesDropdownLabel) ?>
                            </button>
                            <div class="dropdown-menu w-100 p-2 rt-dropdown-menu" aria-labelledby="ycIndustriesDropdown">
                                <?php foreach ($availableIndustries as $industry): ?>
                                    <?php $indSelected = in_array($industry, $selectedIndustries, true); ?>
                                    <div class="form-check mb-1">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="yc_industry_<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $industry)) ?>"
                                            name="industries[]"
                                            value="<?= htmlspecialchars($industry) ?>"
                                            <?= $indSelected ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label small" for="yc_industry_<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $industry)) ?>">
                                            <?= htmlspecialchars($industry) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted d-block">Selected industries are grouped together as your "in-field" or focus group; all other industries are treated as "other".</small>
                    </div>
                </div>
                <div class="d-flex justify-content-end rt-filter-actions">
                    <button type="submit" class="btn btn-success px-4">Apply Filters</button>
                </div>
            </div>
        </div>
    </form>

    <form method="get" class="mt-3">
        <?php if (!empty($selectedYears)): ?>
            <?php foreach ($selectedYears as $year): ?>
                <input type="hidden" name="years[]" value="<?= htmlspecialchars($year) ?>">
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($selectedIndustries)): ?>
            <?php foreach ($selectedIndustries as $industry): ?>
                <input type="hidden" name="industries[]" value="<?= htmlspecialchars($industry) ?>">
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="card dashboard-card shadow-sm rt-summary-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-0">Year Comparison Summary (Employed Graduates)</h5>
                    </div>
                    <div class="btn-group btn-group-sm rt-export-actions" role="group" aria-label="Export year comparison summary table">
                        <button type="button" class="btn btn-outline-primary" id="exportYearCompPdfBtn">PDF</button>
                        <button type="button" class="btn btn-outline-success" id="exportYearCompExcelBtn">Excel</button>
                        <button type="button" class="btn btn-outline-info" id="exportYearCompCsvBtn">CSV</button>
                        <button type="button" class="btn btn-outline-secondary" id="exportYearCompWordBtn">Word</button>
                    </div>
                </div>
                <?php if (empty($yearRows)): ?>
                    <p class="rt-empty-msg mb-0">No data available for the selected years.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle rg-table mb-0">
                            <thead>
                                <tr>
                                    <th>Year Graduated</th>
                                    <th>Total Graduates</th>
                                    <th>Traced Graduates</th>
                                    <th>Untraced Graduates</th>
                                    <th>% Traced</th>
                                    <th>% Untraced</th>
                                    <th>Employed Graduates</th>
                                    <th><?= htmlspecialchars($itColumnTitle) ?></th>
                                    <th><?= htmlspecialchars($nonItColumnTitle) ?></th>
                                    <th>Employment Rate</th>
                                    <th><?= htmlspecialchars($itShareTitle) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($yearRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['year']) ?></td>
                                        <td class="text-center rt-manual-total-cell">
                                            <input
                                                type="number"
                                                name="manual_total[<?= htmlspecialchars($row['year']) ?>]"
                                                class="form-control form-control-sm text-center d-inline-block rt-manual-total-input"
                                                min="0"
                                                value="<?= htmlspecialchars($row['total']) ?>"
                                            >
                                        </td>
                                        <td><?= $row['traced'] ?></td>
                                        <td><?= $row['untraced'] ?></td>
                                        <td><?= number_format($row['pct_traced'], 2) ?>%</td>
                                        <td><?= number_format($row['pct_untraced'], 2) ?>%</td>
                                        <td><?= $row['employed'] ?></td>
                                        <td><?= $row['employed_it'] ?></td>
                                        <td><?= $row['employed_non_it'] ?></td>
                                        <td><?= number_format($row['employment_rate'], 2) ?>%</td>
                                        <td><?= number_format($row['it_share'], 2) ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center rt-summary-footer flex-wrap gap-2">
                        <p class="text-muted small mb-0"><?= htmlspecialchars($itFootnote) ?></p>
                        <button type="submit" class="btn btn-success btn-sm">Recalculate totals</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if (!empty($yearRows)): ?>
    <div class="card dashboard-card shadow-sm rt-chart-card mt-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">Summary of Employed Graduates (In-Field vs Other)</h5>
                    <small class="text-muted">Combined view for selected years showing employed graduates in your selected industries versus all other industries.</small>
                </div>
            </div>
            <div class="yc-chart-body">
                <canvas id="yearCompChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="js/export-libraries.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var categorySelect = document.getElementById('ycReportCategory');
    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            var form = categorySelect.closest('form');
            if (form) {
                form.submit();
            }
        });
    }
});
</script>

<?php if (!empty($yearRows)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const yearData = <?php echo json_encode($yearRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const chartTotals = <?php echo json_encode($chartTotals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const chartLabelIt = <?php echo json_encode($chartLabelIt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const chartLabelNonIt = <?php echo json_encode($chartLabelNonIt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const yearsFilterDescription = <?php echo json_encode($yearsFilterDescription, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const industriesFilterDescription = <?php echo json_encode($industriesFilterDescription, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    const labels = [chartLabelIt, chartLabelNonIt];
    const itCount = Number(chartTotals.employed_it) || 0;
    const nonItCount = Number(chartTotals.employed_non_it) || 0;

    const ctx = document.getElementById('yearCompChart');
    if (ctx && window.Chart) {
        const data = {
            labels: labels,
            datasets: [{
                data: [itCount, nonItCount],
                backgroundColor: [
                    'rgba(33, 150, 83, 0.85)',
                    'rgba(255, 152, 0, 0.85)'
                ],
                borderColor: [
                    'rgba(33, 150, 83, 1)',
                    'rgba(255, 152, 0, 1)'
                ],
                borderWidth: 1,
            }],
        };

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = itCount + nonItCount;
                            const pct = total > 0 ? ((value / total) * 100).toFixed(2) : '0.00';
                            return `${label}: ${value} (${pct}%)`;
                        }
                    }
                }
            }
        };

        window.yearCompChartInstance = new Chart(ctx, {
            type: 'pie',
            data: data,
            options: options,
        });
    }

    function buildExportData() {
        const rows = [];
        rows.push(['YEAR COMPARISON SUMMARY - EMPLOYED GRADUATES']);
        rows.push([`This report is based on ${yearsFilterDescription} and ${industriesFilterDescription}.`]);
        rows.push(['']);
        rows.push([
            'Year Graduated',
            'Total Graduates',
            'Employed Graduates',
            <?= json_encode($itColumnTitle); ?>,
            <?= json_encode($nonItColumnTitle); ?>,
            'Employment Rate',
            <?= json_encode($itShareTitle); ?>,
        ]);

        let sumTotal = 0;
        let sumEmployed = 0;
        let sumIt = 0;
        let sumNonIt = 0;

        yearData.forEach(function (r) {
            rows.push([
                r.year,
                r.total,
                r.employed,
                r.employed_it,
                r.employed_non_it,
                r.employment_rate,
                r.it_share,
            ]);

            sumTotal += Number(r.total) || 0;
            sumEmployed += Number(r.employed) || 0;
            sumIt += Number(r.employed_it) || 0;
            sumNonIt += Number(r.employed_non_it) || 0;
        });

        const overallEmploymentRate = sumTotal > 0 ? ((sumEmployed / sumTotal) * 100).toFixed(2) : '0.00';
        const overallItShare = sumEmployed > 0 ? ((sumIt / sumEmployed) * 100).toFixed(2) : '0.00';

        rows.push([
            'TOTAL',
            sumTotal,
            sumEmployed,
            sumIt,
            sumNonIt,
            overallEmploymentRate,
            overallItShare,
        ]);

        return rows;
    }

    function getChartImage() {
        if (!window.yearCompChartInstance) return null;
        try {
            if (typeof window.yearCompChartInstance.toBase64Image === 'function') {
                return window.yearCompChartInstance.toBase64Image();
            }
        } catch (e) {
            console.warn('Unable to capture year comparison chart image', e);
        }
        const canvas = document.getElementById('yearCompChart');
        if (canvas && canvas.toDataURL) {
            return canvas.toDataURL('image/png');
        }
        return null;
    }

    function exportToPdf() {
        if (!window.ExportLibraries) {
            alert('Export library not loaded.');
            return;
        }
        const data = buildExportData();
        const chartImg = getChartImage();

        // Start with the rows used for Excel/CSV (title, description, header, data, TOTAL)
        const sections = [...data];

        // Append a chart section after the table
        const selectedYears = yearData.map(r => r.year).join(', ');
        sections.push(['']);
        sections.push(['Year Comparison Summary (Chart)']);
        sections.push([`Years covered in this chart: ${selectedYears}.`]);
        sections.push(['This chart shows employed graduates in IT-related vs non-IT (or selected vs other industries) across the selected years.']);
        if (chartImg) {
            sections.push(['__CHART_IMAGE__', 'yearcomp_chart']);
        }

        const ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        const filename = 'year_comparison_' + ts + '.pdf';

        const chartImages = chartImg ? { yearcomp_chart: chartImg } : {};
        const options = {
            // Use simpleTable so the multi-column header + rows render as a grid, plus landscape layout
            simpleTable: true,
            config: window.ExportLibraries.createConfig('landscape'),
            chartImages: window.ExportLibraries.validateChartImages(chartImages),
        };

        window.ExportLibraries.exportToPDF(sections, filename, options, 'Year Comparison Report');
    }

    function exportToExcel() {
        if (!window.ExportLibraries) {
            alert('Export library not loaded.');
            return;
        }
        const data = buildExportData();
        const ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        const filename = 'year_comparison_' + ts + '.xlsx';
        window.ExportLibraries.exportToExcel(data, filename, { sheetName: 'Year Comparison' });
    }

    function exportToCsv() {
        if (!window.ExportLibraries) {
            alert('Export library not loaded.');
            return;
        }
        const data = buildExportData();
        const ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        const filename = 'year_comparison_' + ts + '.csv';
        window.ExportLibraries.exportToCSV(data, filename, {});
    }

    function exportToWordTable() {
        // Table-focused Word export: use server-side HTML table (?export=word)
        const url = new URL(window.location.href);
        url.searchParams.set('export', 'word');
        window.location.href = url.toString();
    }

    function exportToWordChart() {
        if (!window.ExportLibraries) {
            alert('Export library not loaded.');
            return;
        }

        const rows = [];
        const yearsList = yearData.map(r => r.year).join(', ');
        rows.push(['Year Comparison Summary (Chart View)']);
        rows.push(['']);
        rows.push([`Years Covered: ${yearsList}`]);
        rows.push(['This chart shows employed graduates in IT-related vs non-IT fields across the selected years.']);
        rows.push(['']);

        const totalEmployed = yearData.reduce((acc, r) => acc + (Number(r.employed) || 0), 0);
        const totalIt = chartTotals.employed_it || 0;
        const totalNonIt = chartTotals.employed_non_it || 0;
        const pctIt = totalEmployed > 0 ? ((totalIt / totalEmployed) * 100).toFixed(2) : '0.00';
        const pctNonIt = totalEmployed > 0 ? ((totalNonIt / totalEmployed) * 100).toFixed(2) : '0.00';

        rows.push([`Total Employed (selected years): ${totalEmployed}`]);
        rows.push([`IT-related employed: ${totalIt} (${pctIt}%)`]);
        rows.push([`Non-IT employed: ${totalNonIt} (${pctNonIt}%)`]);
        rows.push(['']);
        rows.push(['Per-Year Employed Graduates']);

        yearData.forEach(function (r) {
            const line = `Year ${r.year}: Total Employed = ${r.employed}, IT = ${r.employed_it}, Non-IT = ${r.employed_non_it}`;
            rows.push([line]);
        });

        const ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        const filename = 'year_comparison_chart_' + ts + '.doc';
        window.ExportLibraries.exportToWord(rows, filename, {}, 'Year Comparison Report - Chart View');
    }

    const pdfBtn = document.getElementById('exportYearCompPdfBtn');
    const excelBtn = document.getElementById('exportYearCompExcelBtn');
    const csvBtn = document.getElementById('exportYearCompCsvBtn');
    const wordBtn = document.getElementById('exportYearCompWordBtn');

    // Table exports
    if (pdfBtn) pdfBtn.addEventListener('click', function (e) { e.preventDefault(); exportToPdf(); });
    if (excelBtn) excelBtn.addEventListener('click', function (e) { e.preventDefault(); exportToExcel(); });
    if (csvBtn) csvBtn.addEventListener('click', function (e) { e.preventDefault(); exportToCsv(); });
    if (wordBtn) wordBtn.addEventListener('click', function (e) { e.preventDefault(); exportToWordTable(); });
});
</script>
<?php endif; ?>
