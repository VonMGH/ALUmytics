<?php
// Handle AJAX requests for live chart updates FIRST, before any output
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    include '../db/Database.php';
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
    $eduWhereSql = $eduWhere ? 'WHERE ' . implode(' AND ', $eduWhere) : '';
    
    // Campus/Branch Analysis
    $campusAnalysisData = $conn->query("SELECT e.campus_branch, COUNT(DISTINCT e.user_id) as alumni_count FROM education e $eduWhereSql GROUP BY e.campus_branch ORDER BY alumni_count DESC");
    
    $campusLabels = [];
    $campusCounts = [];
    if ($campusAnalysisData && $campusAnalysisData->num_rows > 0) {
        while($row = $campusAnalysisData->fetch_assoc()) {
            $campusLabels[] = htmlspecialchars($row['campus_branch']);
            $campusCounts[] = (int)$row['alumni_count'];
        }
    }
    
    // Employment Rates by Campus
    $employmentRatesData = $conn->query("SELECT e.campus_branch, emp.employment_status, COUNT(*) as count 
        FROM education e 
        LEFT JOIN employment emp ON e.user_id = emp.user_id 
        $eduWhereSql 
        GROUP BY e.campus_branch, emp.employment_status 
        ORDER BY e.campus_branch, emp.employment_status");
    
    $employmentRates = [];
    if ($employmentRatesData && $employmentRatesData->num_rows > 0) {
        while($row = $employmentRatesData->fetch_assoc()) {
            $campus = $row['campus_branch'];
            $status = $row['employment_status'] ?: 'Unknown';
            $count = (int)$row['count'];
            
            if (!isset($employmentRates[$campus])) {
                $employmentRates[$campus] = [];
            }
            $employmentRates[$campus][$status] = $count;
        }
    }
    
    // Summary metrics for AJAX (same logic as main metrics section)
    $totalCampusesRow = $conn->query("SELECT COUNT(DISTINCT campus_branch) as total FROM education e $eduWhereSql")->fetch_assoc();
    $totalAlumniRow   = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM education e $eduWhereSql")->fetch_assoc();
    $totalCampuses    = isset($totalCampusesRow['total']) ? (int)$totalCampusesRow['total'] : 0;
    $totalAlumni      = isset($totalAlumniRow['total']) ? (int)$totalAlumniRow['total'] : 0;

    $avgEmploymentRateRow = $conn->query("SELECT 
        COUNT(CASE WHEN emp.employment_status = 'employed' THEN 1 END) * 100.0 / NULLIF(COUNT(*),0) as rate
        FROM education e 
        LEFT JOIN employment emp ON e.user_id = emp.user_id 
        $eduWhereSql")->fetch_assoc();
    $avgEmploymentRate = !empty($avgEmploymentRateRow['rate']) ? round($avgEmploymentRateRow['rate'], 1) : 0;
    $alumniPerCampus   = $totalCampuses > 0 ? round($totalAlumni / $totalCampuses, 0) : 0;

    // Return chart data and metrics as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'campusAnalysis' => ['labels' => $campusLabels, 'counts' => $campusCounts],
        'employmentRates' => $employmentRates,
        'metrics' => [
            'totalCampuses' => $totalCampuses,
            'totalAlumni' => $totalAlumni,
            'avgEmploymentRate' => $avgEmploymentRate,
            'alumniPerCampus' => $alumniPerCampus
        ]
    ]);
    exit;
}

// For normal and export requests, include database and access control first
include '../db/Database.php';
include 'includes/access_control.php';

$conn = Database::getInstance()->getConnection();

// Optional PHPWord support for generating real .docx campus analysis reports with PLSP header
$phpWordAvailable = false;
$phpWordAutoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($phpWordAutoload && file_exists($phpWordAutoload)) {
    require_once $phpWordAutoload;
    if (class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        $phpWordAvailable = true;
    }
}

// Handle server-side Word export BEFORE any HTML output
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    // Rebuild filters from query string
    $filter_school = $_GET['school-university'] ?? '';
    $filter_campus = $_GET['campus-branch'] ?? '';
    $filter_college = $_GET['college-department'] ?? '';
    $filter_major = (isset($_GET['major-specialization']) && $_GET['major-specialization'] !== 'all') ? $_GET['major-specialization'] : '';
    $filter_year = (isset($_GET['year']) && $_GET['year'] !== 'all') ? $_GET['year'] : '';

    $filters = [
        'school' => $filter_school,
        'campus' => $filter_campus,
        'college' => $filter_college,
        'major' => $filter_major,
        'year' => $filter_year
    ];

    $eduWhere = buildFilterWhereConditions($conn, $filters, 'e');
    $eduWhereSql = $eduWhere ? 'WHERE ' . implode(' AND ', $eduWhere) : '';

    // Metrics
    $totalCampuses = $conn->query("SELECT COUNT(DISTINCT campus_branch) as total FROM education e $eduWhereSql")->fetch_assoc()['total'];
    $totalAlumni   = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM education e $eduWhereSql")->fetch_assoc()['total'];
    $avgEmploymentRate = $conn->query("SELECT 
        COUNT(CASE WHEN emp.employment_status = 'employed' THEN 1 END) * 100.0 / COUNT(*) as rate
        FROM education e 
        LEFT JOIN employment emp ON e.user_id = emp.user_id 
        $eduWhereSql")->fetch_assoc()['rate'];
    $avgEmploymentRate = $avgEmploymentRate ? round($avgEmploymentRate, 1) : 0;
    $alumniPerCampus = $totalCampuses > 0 ? round($totalAlumni / $totalCampuses, 0) : 0;

    // Campus analysis data (alumni per campus)
    $campusAnalysisData = $conn->query("SELECT e.campus_branch, COUNT(DISTINCT e.user_id) as alumni_count FROM education e $eduWhereSql GROUP BY e.campus_branch ORDER BY alumni_count DESC");
    $campusRows = [];
    if ($campusAnalysisData && $campusAnalysisData->num_rows > 0) {
        while ($row = $campusAnalysisData->fetch_assoc()) {
            $campusRows[] = [$row['campus_branch'], (int)$row['alumni_count']];
        }
    }

    // Employment rates by campus
    $employmentRatesData = $conn->query("SELECT e.campus_branch, emp.employment_status, COUNT(*) as count 
        FROM education e 
        LEFT JOIN employment emp ON e.user_id = emp.user_id 
        $eduWhereSql 
        GROUP BY e.campus_branch, emp.employment_status 
        ORDER BY e.campus_branch, emp.employment_status");
    $employmentByCampus = [];
    if ($employmentRatesData && $employmentRatesData->num_rows > 0) {
        while ($row = $employmentRatesData->fetch_assoc()) {
            $campus = $row['campus_branch'];
            $status = $row['employment_status'] ?: 'Unknown';
            $count  = (int)$row['count'];
            if (!isset($employmentByCampus[$campus])) {
                $employmentByCampus[$campus] = [
                    'employed'      => 0,
                    'unemployed'    => 0,
                    'self_employed' => 0,
                    'Unknown'       => 0
                ];
            }
            if (!isset($employmentByCampus[$campus][$status])) {
                $employmentByCampus[$campus][$status] = 0;
            }
            $employmentByCampus[$campus][$status] += $count;
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
                'Campus Analysis Report',
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
            $metricsTable->addCell()->addText('Total Campuses');
            $metricsTable->addCell()->addText((string)$totalCampuses);

            $metricsTable->addRow();
            $metricsTable->addCell()->addText('Total Alumni');
            $metricsTable->addCell()->addText((string)$totalAlumni);

            $metricsTable->addRow();
            $metricsTable->addCell()->addText('Avg Employment Rate');
            $metricsTable->addCell()->addText((string)$avgEmploymentRate . '%');

            $metricsTable->addRow();
            $metricsTable->addCell()->addText('Alumni per Campus');
            $metricsTable->addCell()->addText((string)$alumniPerCampus);

            // Alumni per campus table
            if ($campusRows) {
                $section->addText(
                    'Alumni per Campus',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $campusTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $campusTable->addRow();
                $campusTable->addCell(5000, ['bgColor' => '219653'])->addText('Campus/Branch', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $campusTable->addCell(3000, ['bgColor' => '219653'])->addText('Alumni Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($campusRows as [$label, $count]) {
                    $campusTable->addRow();
                    $campusTable->addCell()->addText($label);
                    $campusTable->addCell()->addText((string)$count);
                }
            }

            // Employment rates by campus table
            if ($employmentByCampus) {
                $section->addText(
                    'Employment Rates by Campus',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $empTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $empTable->addRow();
                $empTable->addCell(4000, ['bgColor' => '219653'])->addText('Campus/Branch', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $empTable->addCell(2000, ['bgColor' => '219653'])->addText('Employed', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $empTable->addCell(2000, ['bgColor' => '219653'])->addText('Unemployed', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $empTable->addCell(2000, ['bgColor' => '219653'])->addText('Self-Employed', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($employmentByCampus as $campus => $stats) {
                    $empTable->addRow();
                    $empTable->addCell()->addText($campus);
                    $empTable->addCell()->addText((string)($stats['employed'] ?? 0));
                    $empTable->addCell()->addText((string)($stats['unemployed'] ?? 0));
                    $empTable->addCell()->addText((string)($stats['self_employed'] ?? 0));
                }
            }

            $section->addText(
                'Generated by ALUMytics',
                ['size' => 10],
                ['spaceBefore' => 240]
            );

            $fileName = 'campus_analysis_' . date('Ymd_His') . '.docx';

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
    $content  = "Campus Analysis Report\n";
    $content .= str_repeat('=', 70) . "\n\n";
    $content .= 'Generated: ' . date('Y-m-d H:i:s') . "\n\n";

    $content .= "SUMMARY METRICS\n";
    $content .= str_repeat('-', 70) . "\n";
    $content .= 'Total Campuses: ' . $totalCampuses . "\n";
    $content .= 'Total Alumni: ' . $totalAlumni . "\n";
    $content .= 'Avg Employment Rate: ' . $avgEmploymentRate . "%\n";
    $content .= 'Alumni per Campus: ' . $alumniPerCampus . "\n\n";

    if ($campusRows) {
        $content .= "ALUMNI PER CAMPUS\n";
        $content .= str_repeat('-', 70) . "\n";
        $content .= str_pad('Campus/Branch', 40) . "Alumni Count\n";
        $content .= str_repeat('-', 40) . str_repeat('-', 15) . "\n";
        foreach ($campusRows as [$label, $count]) {
            $content .= str_pad($label, 40) . $count . "\n";
        }
        $content .= "\n";
    }

    if ($employmentByCampus) {
        $content .= "EMPLOYMENT RATES BY CAMPUS\n";
        $content .= str_repeat('-', 70) . "\n";
        $content .= str_pad('Campus/Branch', 30) . str_pad('Employed', 12) . str_pad('Unemployed', 12) . str_pad('Self-Employed', 16) . "\n";
        $content .= str_repeat('-', 30) . str_repeat('-', 12) . str_repeat('-', 12) . str_repeat('-', 16) . "\n";
        foreach ($employmentByCampus as $campus => $stats) {
            $content .= str_pad($campus, 30)
                . str_pad((string)($stats['employed'] ?? 0), 12)
                . str_pad((string)($stats['unemployed'] ?? 0), 12)
                . str_pad((string)($stats['self_employed'] ?? 0), 16)
                . "\n";
        }
        $content .= "\n";
    }

    $content .= str_repeat('=', 70) . "\n";
    $content .= "Generated by ALUMytics\n";

    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="campus_analysis_' . date('Ymd_His') . '.doc"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . strlen($content));

    echo $content;
    exit;
}

// After handling exports, include layout and continue normal page render
include 'includes/header.php'; // This includes access_control.php
include 'includes/sidebar.php';

// Check module access
requireModuleAccess('campus-analysis');

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
$eduWhereSql = $eduWhere ? 'WHERE ' . implode(' AND ', $eduWhere) : '';
$totalCampuses = $conn->query("SELECT COUNT(DISTINCT campus_branch) as total FROM education e $eduWhereSql")->fetch_assoc()['total'];
$totalAlumni = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM education e $eduWhereSql")->fetch_assoc()['total'];
$avgEmploymentRate = $conn->query("SELECT 
    COUNT(CASE WHEN emp.employment_status = 'employed' THEN 1 END) * 100.0 / COUNT(*) as rate
    FROM education e 
    LEFT JOIN employment emp ON e.user_id = emp.user_id 
    $eduWhereSql")->fetch_assoc()['rate'];
$avgEmploymentRate = $avgEmploymentRate ? round($avgEmploymentRate, 1) : 0;

$role ??= null;
$college_name ??= null;
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/campus-analysis.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-0 dashboard-title">Campus Analysis</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Viewing data for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Compare alumni performance and outcomes across different campuses and branches.</p>
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
                
                <?php if (in_array('year', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="year">Year</label>
                    <select id="year" name="year" class="filter-select">
                        <option value="all" <?= ($filter_year == '') ? 'selected' : '' ?>>All</option>
                        <?php while($row = $filterOptions['years']->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['year']) ?>"><?= htmlspecialchars($row['year']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            </div>
        </form>
    </div>

    <div class="metrics-container">
        <div class="metric-card">
            <h3>Total Campuses</h3>
            <div class="metric-value" id="metricTotalCampuses"><?= $totalCampuses ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-university"></i> Branches
            </div>
            <div class="icon-container icon-campus">
                <i class="fas fa-university"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Total Alumni</h3>
            <div class="metric-value" id="metricTotalAlumni"><?= $totalAlumni ?></div>
            <div class="metric-change positive">
                <i class="fas fa-user-graduate"></i> Graduates
            </div>
            <div class="icon-container icon-alumni">
                <i class="fas fa-user-graduate"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Avg Employment Rate</h3>
            <div class="metric-value" id="metricAvgEmployment"><?= $avgEmploymentRate ?>%</div>
            <div class="metric-change positive">
                <i class="fas fa-briefcase"></i> Across campuses
            </div>
            <div class="icon-container icon-employment">
                <i class="fas fa-briefcase"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Alumni per Campus</h3>
            <div class="metric-value" id="metricAlumniPerCampus"><?= $totalCampuses > 0 ? round($totalAlumni / $totalCampuses, 0) : 0 ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-calculator"></i> Average
            </div>
            <div class="icon-container icon-average">
                <i class="fas fa-calculator"></i>
            </div>
        </div>
    </div>

    <div class="row g-4" id="sortable-charts">
        <div class="col-lg-6 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Campus Alumni Analysis</h5>
                        <small class="text-muted">Number of alumni per campus/branch</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="campusAnalysisChart"></canvas>
                    <div id="campusAnalysisChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="campusAnalysisChart-nodata" class="empty-chart-msg" style="display:none;">
                        <p class="mb-0">No data available</p>
                    </div>
                </div>
                <div class="card-footer text-muted">
                    <i class="far fa-clock"></i> Just updated.
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Employment Rates by Campus</h5>
                        <small class="text-muted">Comparison across campuses/branches</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="employmentRatesChart"></canvas>
                    <div id="employmentRatesChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="employmentRatesChart-nodata" class="empty-chart-msg" style="display:none;">
                        <p class="mb-0">No data available</p>
                    </div>
                </div>
                <div class="card-footer text-muted">
                    <i class="far fa-clock"></i> Just updated.
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
                <h5 class="modal-title" id="exportModalLabel">Export Campus Analysis</h5>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Enhanced Export Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="js/export-libraries.js"></script>
<script src="js/chart-utils.js"></script>

<script>
// Global chart variables
let campusAnalysisChart, employmentRatesChart;

// Create Campus Analysis Chart
function createCampusAnalysisChart(data) {
    const ctx = document.getElementById('campusAnalysisChart').getContext('2d');
    if (campusAnalysisChart) campusAnalysisChart.destroy();
    
    // Give each campus its own color
    const labelCount = (data.labels && data.labels.length) ? data.labels.length : data.counts.length;
    const colors = getDistinctPalette(labelCount);
    const backgroundColors = applyAlpha(colors, 0.8);

    campusAnalysisChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Alumni Count',
                data: data.counts,
                // Chart.js accepts an array for per-bar colors
                backgroundColor: backgroundColors,
                borderColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    // Expose globally for export routines
    window.campusAnalysisChart = campusAnalysisChart;
}

// Create Employment Rates Chart
function createEmploymentRatesChart(data) {
    const ctx = document.getElementById('employmentRatesChart').getContext('2d');
    if (employmentRatesChart) employmentRatesChart.destroy();
    
    const campuses = Object.keys(data);
    const statuses = ['employed', 'unemployed', 'self_employed'];
    const colors = getDistinctPalette(statuses.length);
    const backgroundColors = applyAlpha(colors, 0.8);
    
    const datasets = statuses.map((status, index) => ({
        label: status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' '),
        data: campuses.map(campus => data[campus] ? (data[campus][status] || 0) : 0),
        backgroundColor: backgroundColors[index],
        borderColor: colors[index],
        borderWidth: 1
    }));
    
    employmentRatesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: campuses,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
            }
        }
    });
    // Expose globally for export routines
    window.employmentRatesChart = employmentRatesChart;
}


// Update campus data
function updateCampusData() {
    // Show loading spinners
    document.querySelectorAll('.spinner').forEach(s => s.style.display = 'flex');
    document.querySelectorAll('canvas').forEach(c => c.style.display = 'none');
    document.querySelectorAll('[id$="-nodata"]').forEach(n => n.style.display = 'none');
    
    // Get filter values
    const formData = new FormData(document.getElementById('filterForm'));
    const params = new URLSearchParams(formData);
    
    // Make AJAX request
    fetch(`campus-analysis.php?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        // Hide spinners
        document.querySelectorAll('.spinner').forEach(s => s.style.display = 'none');
        document.querySelectorAll('canvas').forEach(c => c.style.display = 'block');
        
        // Update charts
        if (data.campusAnalysis.labels.length > 0) {
            createCampusAnalysisChart(data.campusAnalysis);
        } else {
            document.getElementById('campusAnalysisChart').style.display = 'none';
            document.getElementById('campusAnalysisChart-nodata').style.display = 'block';
        }
        
        if (Object.keys(data.employmentRates).length > 0) {
            createEmploymentRatesChart(data.employmentRates);
        } else {
            document.getElementById('employmentRatesChart').style.display = 'none';
            document.getElementById('employmentRatesChart-nodata').style.display = 'block';
        }

        // Update summary metric cards from AJAX metrics
        if (data.metrics) {
            const m = data.metrics;
            const elCampuses = document.getElementById('metricTotalCampuses');
            const elAlumni = document.getElementById('metricTotalAlumni');
            const elEmployment = document.getElementById('metricAvgEmployment');
            const elPerCampus = document.getElementById('metricAlumniPerCampus');
            if (elCampuses) elCampuses.textContent = m.totalCampuses;
            if (elAlumni) elAlumni.textContent = m.totalAlumni;
            if (elEmployment) elEmployment.textContent = m.avgEmploymentRate + '%';
            if (elPerCampus) elPerCampus.textContent = m.alumniPerCampus;
        }

        // Salary comparison chart removed
    })
    .catch(error => {
        console.error('Error updating campus data:', error);
        document.querySelectorAll('.spinner').forEach(s => s.style.display = 'none');
        document.querySelectorAll('[id$="-nodata"]').forEach(n => n.style.display = 'block');
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCampusData();
    
    // Add event listeners to filter controls
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', updateCampusData);
    });
});

// Initialize SortableJS for chart cards
if (typeof Sortable !== "undefined") {
  new Sortable(document.getElementById("sortable-charts"), {
    animation: 200,
    handle: ".card-header",
    draggable: ".sortable-chart-card",
    ghostClass: "sortable-ghost",
  });
} else if (window.Sortable) {
  new window.Sortable(document.getElementById("sortable-charts"), {
    animation: 200,
    handle: ".card-header",
    draggable: ".sortable-chart-card",
    ghostClass: "sortable-ghost",
  });
} else {
  console.warn("SortableJS is not loaded. Chart cards will not be draggable.");
}

// Export functionality
document.addEventListener('DOMContentLoaded', function() {
    // Function to capture chart images for PDF export
    function captureChartImages() {
        const chartImages = {};
        
        // Capture campus analysis chart
        if (campusAnalysisChart) {
            try {
                if (typeof campusAnalysisChart.toBase64Image === 'function') {
                    chartImages.campusAnalysis = campusAnalysisChart.toBase64Image();
                } else if (campusAnalysisChart.canvas) {
                    chartImages.campusAnalysis = campusAnalysisChart.canvas.toDataURL('image/png', 1.0);
                }
            } catch (error) {
                console.warn('Failed to capture campus analysis chart:', error);
            }
        }
        
        // Capture employment rates chart
        if (employmentRatesChart) {
            try {
                if (typeof employmentRatesChart.toBase64Image === 'function') {
                    chartImages.employmentRates = employmentRatesChart.toBase64Image();
                } else if (employmentRatesChart.canvas) {
                    chartImages.employmentRates = employmentRatesChart.canvas.toDataURL('image/png', 1.0);
                }
            } catch (error) {
                console.warn('Failed to capture employment rates chart:', error);
            }
        }
        
        // Salary comparison chart removed
        
        return chartImages;
    }
    
    // Function to generate export data
    function generateExportData(includeMetrics = true, includeCharts = true) {
        const data = [];
        
        // Title
        data.push(['CAMPUS ANALYSIS REPORT']);
        data.push(['']);
        
        if (includeMetrics) {
            // Metrics section
            data.push(['CAMPUS METRICS']);
            data.push(['Metric', 'Value']);
            data.push(['Total Campuses', '<?= $totalCampuses ?>']);
            data.push(['Total Alumni', '<?= $totalAlumni ?>']);
            data.push(['Avg Employment Rate', '<?= $avgEmploymentRate ?>%']);
            data.push(['Alumni per Campus', '<?= $totalCampuses > 0 ? round($totalAlumni / $totalCampuses, 0) : 0 ?>']);
            data.push(['']);
        }
        
        if (includeCharts) {
            // Chart markers for PDF
            data.push(['CAMPUS ALUMNI ANALYSIS']);
            data.push(['__CHART_IMAGE__', 'campusAnalysis']);
            data.push(['This chart shows distribution of alumni across campuses or branches.']);
            data.push(['']);
            
            data.push(['EMPLOYMENT RATES BY CAMPUS']);
            data.push(['__CHART_IMAGE__', 'employmentRates']);
            data.push(['This chart shows how employment rates vary across campuses.']);
            data.push(['']);
            
            // Salary comparison removed from exports
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
            
            // If Word is selected, use server-side export handler
            if (word) {
                const formData = new FormData(document.getElementById('filterForm'));
                const params = new URLSearchParams(formData);
                const url = 'campus-analysis.php?' + params.toString() +
                    '&export=word' +
                    '&metrics=' + (metrics ? '1' : '0') +
                    '&charts=' + (charts ? '1' : '0');
                window.location.href = url;
                return;
            }
            
            // Generate export data and capture charts
            const exportData = generateExportData(metrics, charts);
            const chartImages = charts ? captureChartImages() : {};
            const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
            
            // Export using the new library
            if (window.exportLibrary) {
                try {
                    if (pdf) {
                        window.exportLibrary.exportToPDF(
                            exportData, 
                            `alumytics_campus_analysis_${timestamp}.pdf`,
                            { chartImages: chartImages, config: window.exportLibrary.createConfig('standard') },
                            'ALUMytics Campus Analysis Report'
                        );
                    }
                    
                    if (excel) {
                        window.exportLibrary.exportToExcel(
                            exportData, 
                            `alumytics_campus_analysis_${timestamp}.xlsx`,
                            { sheetName: 'Campus Analysis' }
                        );
                    }
                    
                    if (csv) {
                        window.exportLibrary.exportToCSV(
                            exportData, 
                            `alumytics_campus_analysis_${timestamp}.csv`
                        );
                    }
                    
                    // Close modal
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
