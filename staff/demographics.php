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
    $filter_year = (isset($_GET['year']) && $_GET['year'] !== 'all' && $_GET['year'] !== '') ? $_GET['year'] : '';
    
    // Build filters based on role permissions
    $filters = [
        'school' => $filter_school,
        'campus' => $filter_campus,
        'college' => $filter_college,
        'major' => $filter_major
    ];
    $eduWhere = buildFilterWhereConditions($conn, $filters, 'e');
    // Add year_graduated filter if set
    if ($filter_year !== '') {
        $eduWhere[] = "e.year_graduated = '" . $conn->real_escape_string($filter_year) . "'";
    }
    
    // Gender Distribution
    $genderJoin = $eduWhere ? "JOIN education e ON e.user_id = personal.user_id" : "";
    $genderWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    $genderData = $conn->query("SELECT sex, COUNT(*) as count FROM personal $genderJoin $genderWhereSql GROUP BY sex");

    $sexLabels = [];
    $sexCounts = [];
    if ($genderData && $genderData->num_rows > 0) {
        while($row = $genderData->fetch_assoc()) {
            $sex = ucfirst(strtolower(trim($row['sex'])));
            if ($sex && $sex !== '') {
                $sexLabels[] = htmlspecialchars($sex);
                $sexCounts[] = (int)$row['count'];
            }
        }
    }

    // Age Distribution
    $ageJoin = $eduWhere ? "JOIN education e ON e.user_id = personal.user_id" : "";
    $ageWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    $ageQuery = "SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 18 AND 24 THEN '18-24'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 25 AND 29 THEN '25-29'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 30 AND 34 THEN '30-34'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 35 AND 39 THEN '35-39'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 40 AND 44 THEN '40-44'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 45 AND 49 THEN '45-49'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) >= 50 THEN '50+'
            ELSE 'Unknown'
        END as age_group, 
        COUNT(*) as count 
    FROM personal $ageJoin $ageWhereSql 
    GROUP BY age_group 
    ORDER BY age_group";
    
    $ageData = $conn->query($ageQuery);
    $ageLabels = [];
    $ageCounts = [];
    if ($ageData && $ageData->num_rows > 0) {
        while($row = $ageData->fetch_assoc()) {
            if ($row['age_group'] !== 'Unknown') {
                $ageLabels[] = htmlspecialchars($row['age_group']);
                $ageCounts[] = (int)$row['count'];
            }
        }
    }

    // Degree Distribution
    $degreeJoin = $eduWhere ? "" : "";
    $degreeWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    $degreeQuery = "SELECT program, COUNT(*) as count FROM education e $degreeWhereSql GROUP BY program ORDER BY count DESC";
    
    $degreeData = $conn->query($degreeQuery);
    $degreeLabels = [];
    $degreeCounts = [];
    if ($degreeData && $degreeData->num_rows > 0) {
        while($row = $degreeData->fetch_assoc()) {
            $degreeLabels[] = htmlspecialchars($row['program']);
            $degreeCounts[] = (int)$row['count'];
        }
    }

    // Summary metrics for AJAX (same logic as main metrics section)
    $genderJoin = $eduWhere ? "JOIN education e ON e.user_id = personal.user_id" : "";
    $genderWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";

    $totalAlumniRow = $conn->query("SELECT COUNT(DISTINCT personal.user_id) as total FROM personal $genderJoin $genderWhereSql")->fetch_assoc();
    $totalAlumni = isset($totalAlumniRow['total']) ? (int)$totalAlumniRow['total'] : 0;

    $maleWhereSql = $genderWhereSql ? $genderWhereSql . " AND sex = 'male'" : "WHERE sex = 'male'";
    $femaleWhereSql = $genderWhereSql ? $genderWhereSql . " AND sex = 'female'" : "WHERE sex = 'female'";
    $totalMaleRow = $conn->query("SELECT COUNT(*) as total FROM personal $genderJoin $maleWhereSql")->fetch_assoc();
    $totalFemaleRow = $conn->query("SELECT COUNT(*) as total FROM personal $genderJoin $femaleWhereSql")->fetch_assoc();
    $totalMale = isset($totalMaleRow['total']) ? (int)$totalMaleRow['total'] : 0;
    $totalFemale = isset($totalFemaleRow['total']) ? (int)$totalFemaleRow['total'] : 0;

    $avgAgeQuery = "SELECT AVG(TIMESTAMPDIFF(YEAR, dob, CURDATE())) as avg_age FROM personal $genderJoin $genderWhereSql";
    $avgAgeRow = $conn->query($avgAgeQuery)->fetch_assoc();
    $avgAge = !empty($avgAgeRow['avg_age']) ? round($avgAgeRow['avg_age'], 1) : 0;

    header('Content-Type: application/json');
    echo json_encode([
        'gender' => ['labels' => $sexLabels, 'counts' => $sexCounts],
        'age' => ['labels' => $ageLabels, 'counts' => $ageCounts],
        'degree' => ['labels' => $degreeLabels, 'counts' => $degreeCounts],
        'metrics' => [
            'totalAlumni' => $totalAlumni,
            'totalMale' => $totalMale,
            'totalFemale' => $totalFemale,
            'avgAge' => $avgAge
        ]
    ]);
    exit;
}

// Include database and access control
include '../db/Database.php';
include 'includes/access_control.php';
$conn = Database::getInstance()->getConnection();

// Optional PHPWord support for generating real .docx demographics reports with PLSP header
$phpWordAvailable = false;
$phpWordAutoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($phpWordAutoload && file_exists($phpWordAutoload)) {
    require_once $phpWordAutoload;
    if (class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        $phpWordAvailable = true;
    }
}

// Handle server-side Word export for demographics
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    // Rebuild filters from query string
    $filter_school = $_GET['school-university'] ?? '';
    $filter_campus = $_GET['campus-branch'] ?? '';
    $filter_college = $_GET['college-department'] ?? '';
    $filter_major = (isset($_GET['major-specialization']) && $_GET['major-specialization'] !== 'all') ? $_GET['major-specialization'] : '';
    $filter_year = (isset($_GET['year']) && $_GET['year'] !== 'all' && $_GET['year'] !== '') ? $_GET['year'] : '';

    $filters = [
        'school' => $filter_school,
        'campus' => $filter_campus,
        'college' => $filter_college,
        'major' => $filter_major
    ];
    $eduWhere = buildFilterWhereConditions($conn, $filters, 'e');
    if ($filter_year !== '') {
        $eduWhere[] = "e.year_graduated = '" . $conn->real_escape_string($filter_year) . "'";
    }

    // Totals and metrics (same logic as main page)
    $genderJoin = $eduWhere ? "JOIN education e ON e.user_id = personal.user_id" : "";
    $genderWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";

    $totalAlumni = $conn->query("SELECT COUNT(DISTINCT personal.user_id) as total FROM personal $genderJoin $genderWhereSql")->fetch_assoc()['total'];

    $maleWhereSql = $genderWhereSql ? $genderWhereSql . " AND sex = 'male'" : "WHERE sex = 'male'";
    $femaleWhereSql = $genderWhereSql ? $genderWhereSql . " AND sex = 'female'" : "WHERE sex = 'female'";
    $totalMale = $conn->query("SELECT COUNT(*) as total FROM personal $genderJoin $maleWhereSql")->fetch_assoc()['total'];
    $totalFemale = $conn->query("SELECT COUNT(*) as total FROM personal $genderJoin $femaleWhereSql")->fetch_assoc()['total'];

    $avgAgeQuery = "SELECT AVG(TIMESTAMPDIFF(YEAR, dob, CURDATE())) as avg_age FROM personal $genderJoin $genderWhereSql";
    $avgAge = $conn->query($avgAgeQuery)->fetch_assoc()['avg_age'];
    $avgAge = $avgAge ? round($avgAge, 1) : 0;

    // Gender distribution
    $genderData = $conn->query("SELECT sex, COUNT(*) as count FROM personal $genderJoin $genderWhereSql GROUP BY sex");
    $genderRows = [];
    if ($genderData && $genderData->num_rows > 0) {
        while ($row = $genderData->fetch_assoc()) {
            $sex = ucfirst(strtolower(trim($row['sex'])));
            if ($sex !== '') {
                $genderRows[] = [$sex, (int)$row['count']];
            }
        }
    }

    // Age distribution (same as AJAX block)
    $ageJoin = $genderJoin;
    $ageWhereSql = $genderWhereSql;
    $ageQuery = "SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 18 AND 24 THEN '18-24'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 25 AND 29 THEN '25-29'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 30 AND 34 THEN '30-34'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 35 AND 39 THEN '35-39'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 40 AND 44 THEN '40-44'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 45 AND 49 THEN '45-49'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) >= 50 THEN '50+'
            ELSE 'Unknown'
        END as age_group, 
        COUNT(*) as count 
    FROM personal $ageJoin $ageWhereSql 
    GROUP BY age_group 
    ORDER BY age_group";

    $ageData = $conn->query($ageQuery);
    $ageRows = [];
    if ($ageData && $ageData->num_rows > 0) {
        while ($row = $ageData->fetch_assoc()) {
            if ($row['age_group'] !== 'Unknown') {
                $ageRows[] = [$row['age_group'], (int)$row['count']];
            }
        }
    }

    // Degree distribution
    $degreeWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    $degreeQuery = "SELECT program, COUNT(*) as count FROM education e $degreeWhereSql GROUP BY program ORDER BY count DESC";
    $degreeData = $conn->query($degreeQuery);
    $degreeRows = [];
    if ($degreeData && $degreeData->num_rows > 0) {
        while ($row = $degreeData->fetch_assoc()) {
            $degreeRows[] = [$row['program'], (int)$row['count']];
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

            // Title and summary info
            $section->addText(
                'Demographics Dashboard Report',
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
            $metricsTable->addCell()->addText('Total Alumni');
            $metricsTable->addCell()->addText((string)$totalAlumni);

            $metricsTable->addRow();
            $metricsTable->addCell()->addText('Male Alumni');
            $metricsTable->addCell()->addText((string)$totalMale);

            $metricsTable->addRow();
            $metricsTable->addCell()->addText('Female Alumni');
            $metricsTable->addCell()->addText((string)$totalFemale);

            $metricsTable->addRow();
            $metricsTable->addCell()->addText('Average Age');
            $metricsTable->addCell()->addText($avgAge . ' years');

            // Gender distribution table
            if ($genderRows) {
                $section->addText(
                    'Gender Distribution',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $genderTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $genderTable->addRow();
                $genderTable->addCell(4000, ['bgColor' => '219653'])->addText('Gender', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $genderTable->addCell(4000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($genderRows as [$label, $count]) {
                    $genderTable->addRow();
                    $genderTable->addCell()->addText($label);
                    $genderTable->addCell()->addText((string)$count);
                }
            }

            // Age distribution table
            if ($ageRows) {
                $section->addText(
                    'Age Distribution',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $ageTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $ageTable->addRow();
                $ageTable->addCell(4000, ['bgColor' => '219653'])->addText('Age Group', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $ageTable->addCell(4000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($ageRows as [$label, $count]) {
                    $ageTable->addRow();
                    $ageTable->addCell()->addText($label);
                    $ageTable->addCell()->addText((string)$count);
                }
            }

            // Degree breakdown table
            if ($degreeRows) {
                $section->addText(
                    'Degree Breakdown (Program)',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $degTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $degTable->addRow();
                $degTable->addCell(5000, ['bgColor' => '219653'])->addText('Program', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $degTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($degreeRows as [$label, $count]) {
                    $degTable->addRow();
                    $degTable->addCell()->addText($label);
                    $degTable->addCell()->addText((string)$count);
                }
            }

            $section->addText(
                'Generated by ALUMytics',
                ['size' => 10],
                ['spaceBefore' => 240]
            );

            $fileName = 'demographics_' . date('Ymd_His') . '.docx';

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
    $content  = "Demographics Dashboard Report\n";
    $content .= str_repeat('=', 60) . "\n\n";
    $content .= 'Generated: ' . date('Y-m-d H:i:s') . "\n\n";

    $content .= "SUMMARY METRICS\n";
    $content .= str_repeat('-', 60) . "\n";
    $content .= 'Total Alumni: ' . $totalAlumni . "\n";
    $content .= 'Male Alumni: ' . $totalMale . "\n";
    $content .= 'Female Alumni: ' . $totalFemale . "\n";
    $content .= 'Average Age: ' . $avgAge . " years\n\n";

    if ($genderRows) {
        $content .= "GENDER DISTRIBUTION\n";
        $content .= str_repeat('-', 60) . "\n";
        $content .= str_pad('Gender', 20) . "Count\n";
        $content .= str_repeat('-', 20) . str_repeat('-', 10) . "\n";
        foreach ($genderRows as [$label, $count]) {
            $content .= str_pad($label, 20) . $count . "\n";
        }
        $content .= "\n";
    }

    if ($ageRows) {
        $content .= "AGE DISTRIBUTION\n";
        $content .= str_repeat('-', 60) . "\n";
        $content .= str_pad('Age Group', 20) . "Count\n";
        $content .= str_repeat('-', 20) . str_repeat('-', 10) . "\n";
        foreach ($ageRows as [$label, $count]) {
            $content .= str_pad($label, 20) . $count . "\n";
        }
        $content .= "\n";
    }

    if ($degreeRows) {
        $content .= "DEGREE BREAKDOWN (PROGRAM)\n";
        $content .= str_repeat('-', 60) . "\n";
        $content .= str_pad('Program', 40) . "Count\n";
        $content .= str_repeat('-', 40) . str_repeat('-', 10) . "\n";
        foreach ($degreeRows as [$label, $count]) {
            $content .= str_pad($label, 40) . $count . "\n";
        }
        $content .= "\n";
    }

    $content .= str_repeat('=', 60) . "\n";
    $content .= "Generated by ALUMytics\n";

    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="demographics_' . date('Ymd_His') . '.doc"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . strlen($content));

    echo $content;
    exit;
}

// Include layout
include 'includes/header.php'; // This includes access_control.php
include 'includes/sidebar.php';

// Check module access
requireModuleAccess('demographics');

// Get filter values for initial load
$filter_school = $_GET['school-university'] ?? '';
$filter_campus = $_GET['campus-branch'] ?? '';
$filter_college = $_GET['college-department'] ?? '';
$filter_major = (isset($_GET['major-specialization']) && $_GET['major-specialization'] !== 'all') ? $_GET['major-specialization'] : '';
$filter_year = (isset($_GET['year']) && $_GET['year'] !== 'all' && $_GET['year'] !== '') ? $_GET['year'] : '';

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
    'major' => $filter_major
];
$eduWhere = buildFilterWhereConditions($conn, $filters, 'e');
// Add year_graduated filter if set
if ($filter_year !== '') {
    $eduWhere[] = "e.year_graduated = '" . $conn->real_escape_string($filter_year) . "'";
}
$filterOptions = getFilterOptions($conn);

// Calculate totals for metrics
$genderJoin = $eduWhere ? "JOIN education e ON e.user_id = personal.user_id" : "";
$genderWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";

$totalAlumni = $conn->query("SELECT COUNT(DISTINCT personal.user_id) as total FROM personal $genderJoin $genderWhereSql")->fetch_assoc()['total'];

// Build correct WHERE/AND for sex = 'male' and sex = 'female'
$maleWhereSql = $genderWhereSql ? $genderWhereSql . " AND sex = 'male'" : "WHERE sex = 'male'";
$femaleWhereSql = $genderWhereSql ? $genderWhereSql . " AND sex = 'female'" : "WHERE sex = 'female'";
$totalMale = $conn->query("SELECT COUNT(*) as total FROM personal $genderJoin $maleWhereSql")->fetch_assoc()['total'];
$totalFemale = $conn->query("SELECT COUNT(*) as total FROM personal $genderJoin $femaleWhereSql")->fetch_assoc()['total'];

// Get average age
$avgAgeQuery = "SELECT AVG(TIMESTAMPDIFF(YEAR, dob, CURDATE())) as avg_age FROM personal $genderJoin $genderWhereSql";
$avgAge = $conn->query($avgAgeQuery)->fetch_assoc()['avg_age'];
$avgAge = $avgAge ? round($avgAge, 1) : 0;

$role ??= null;
$college_name ??= null;
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/demographics.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-0 dashboard-title">Demographics Dashboard</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Viewing data for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Comprehensive analysis of alumni demographic patterns and distributions.</p>
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
            <h3>Total Alumni</h3>
            <div class="metric-value"><?= $totalAlumni ?></div>
            <div class="metric-change positive">
                <i class="fas fa-users metric-icon"></i> All registered
            </div>
            <div class="icon-container icon-total">
                <i class="fas fa-users"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Male Alumni</h3>
            <div class="metric-value"><?= $totalMale ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-male metric-icon"></i> <?php
                    $malePercent = ($totalAlumni > 0 && is_numeric($totalMale)) ? round(($totalMale / $totalAlumni) * 100, 1) : 0;
                    echo $malePercent;
                ?>%
            </div>
            <div class="icon-container icon-male">
                <i class="fas fa-male"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Female Alumni</h3>
            <div class="metric-value"><?= $totalFemale ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-female metric-icon"></i> <?php
                    $femalePercent = ($totalAlumni > 0 && is_numeric($totalFemale)) ? round(($totalFemale / $totalAlumni) * 100, 1) : 0;
                    echo $femalePercent;
                ?>%
            </div>
            <div class="icon-container icon-female">
                <i class="fas fa-female"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Average Age</h3>
            <div class="metric-value"><?= $avgAge ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-calendar metric-icon"></i> years old
            </div>
            <div class="icon-container icon-age">
                <i class="fas fa-birthday-cake"></i>
            </div>
        </div>
    </div>

    <div class="row g-4" id="sortable-charts">
        <div class="col-lg-4 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Gender Distribution</h5>
                        <small class="text-muted">Alumni by gender.</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="sexPieChart"></canvas>
                    <div id="sexPieChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="sexPieChart-nodata" class="empty-chart-msg" style="display:none;">
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
                        <h5 class="card-title mb-0">Age Distribution</h5>
                        <small class="text-muted">Alumni by age groups.</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="ageHistogram"></canvas>
                    <div id="ageHistogram-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="ageHistogram-nodata" class="empty-chart-msg" style="display:none;">
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
                        <h5 class="card-title mb-0">Degree Breakdown</h5>
                        <small class="text-muted">Alumni by program.</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="degreeBarChart"></canvas>
                    <div id="degreeBarChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="degreeBarChart-nodata" class="empty-chart-msg" style="display:none;">
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
                <h5 class="modal-title" id="exportModalLabel">Export Demographics Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="metrics" id="exportMetrics" checked>
                        <label class="form-check-label" for="exportMetrics">Summary Metrics</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="charts" id="exportCharts" checked>
                        <label class="form-check-label" for="exportCharts">Chart Data</label>
                    </div>
                    <div class="mb-2 mt-3">Select format(s):</div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="pdf" id="exportPDF">
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
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="exportForm" class="btn btn-primary">Download</button>
            </div>
        </div>
    </div>
</div>

<script src="js/sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/chart-utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Export Libraries dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="js/export-libraries.js?v=3"></script>
<script src="js/export-descriptions.js"></script>

<script>
// Global chart variables
let sexChart, ageChart, degreeChart;

// Create Gender Distribution Chart
function createGenderChart(data) {
    const ctx = document.getElementById('sexPieChart').getContext('2d');
    if (sexChart) sexChart.destroy();
    // Use distinct palette
    const palette = getDistinctPalette(data.labels.length);
    const bg = applyAlpha(palette, 0.85);
    console.debug('createGenderChart palette:', palette, 'bg:', bg);
    sexChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.counts,
                backgroundColor: bg
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
    // Expose globally for export routines
    window.sexChart = sexChart;
}

// Create Age Distribution Chart
function createAgeChart(data) {
    const ctx = document.getElementById('ageHistogram').getContext('2d');
    if (ageChart) ageChart.destroy();
    // Use distinct palette
    const palette = getDistinctPalette(data.labels.length);
    const bg = applyAlpha(palette, 0.85);
    const border = applyAlpha(palette, 1.0);
    console.debug('createAgeChart palette:', palette, 'bg:', bg);
    ageChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Alumni Count',
                data: data.counts,
                backgroundColor: bg,
                borderColor: border,
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
    window.ageChart = ageChart;
}

// Create Degree Distribution Chart (horizontal Top-N + Others)
function createDegreeChart(data) {
    const ctx = document.getElementById('degreeBarChart').getContext('2d');
    if (degreeChart) degreeChart.destroy();

    // Pair labels with counts and keep original order by count DESC (already from SQL)
    const pairs = data.labels.map((label, idx) => ({
        label: label,
        count: data.counts[idx] || 0
    }));

    const maxPrograms = 10; // top 10 programs shown individually
    const top = pairs.slice(0, maxPrograms);
    const rest = pairs.slice(maxPrograms);

    let labels = top.map(p => p.label);
    let counts = top.map(p => p.count);

    // Aggregate the remaining programs into a single "Others" bar
    const othersTotal = rest.reduce((sum, p) => sum + p.count, 0);
    if (othersTotal > 0) {
        labels.push('Others');
        counts.push(othersTotal);
    }

    const palette = getDistinctPalette(labels.length);
    const bg = applyAlpha(palette, 0.85);
    const border = applyAlpha(palette, 1.0);

    // Use shorter display labels on the axis to reduce left-side space,
    // but keep a mapping so tooltips show the full program name.
    const MAX_LABEL_CHARS = 14;
    const displayLabels = labels.map(lbl => {
        const s = String(lbl || '').trim();
        return s.length > MAX_LABEL_CHARS ? s.slice(0, MAX_LABEL_CHARS) + '…' : s;
    });

    degreeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: displayLabels,
            datasets: [{
                label: 'Alumni Count',
                data: counts,
                backgroundColor: bg,
                borderColor: border,
                borderWidth: 1
            }]
        },
        options: {
            // vertical bars (default axis)
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        // Show full program name in tooltip title
                        title: function(items) {
                            const idx = items[0].dataIndex;
                            return labels[idx] || items[0].label || '';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 9 }
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    // Expose globally for export routines
    window.degreeChart = degreeChart;
}

// Update charts with new data
function updateCharts() {
    // Show loading spinners
    document.querySelectorAll('.spinner').forEach(s => s.style.display = 'block');
    document.querySelectorAll('canvas').forEach(c => c.style.display = 'none');
    document.querySelectorAll('[id$="-nodata"]').forEach(n => n.style.display = 'none');
    
    // Get filter values
    const formData = new FormData(document.getElementById('filterForm'));
    const params = new URLSearchParams(formData);
    
    // Make AJAX request
    fetch(`demographics.php?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        // Hide spinners
        document.querySelectorAll('.spinner').forEach(s => s.style.display = 'none');
        document.querySelectorAll('canvas').forEach(c => c.style.display = 'block');
        
        // Update charts
        if (data.gender.labels.length > 0) {
            createGenderChart(data.gender);
        } else {
            document.getElementById('sexPieChart').style.display = 'none';
            document.getElementById('sexPieChart-nodata').style.display = 'block';
        }
        
        if (data.age.labels.length > 0) {
            createAgeChart(data.age);
        } else {
            document.getElementById('ageHistogram').style.display = 'none';
            document.getElementById('ageHistogram-nodata').style.display = 'block';
        }
        
        if (data.degree.labels.length > 0) {
            createDegreeChart(data.degree);
        } else {
            document.getElementById('degreeBarChart').style.display = 'none';
            document.getElementById('degreeBarChart-nodata').style.display = 'block';
        }

        // Update metric cards from AJAX metrics
        if (data.metrics) {
            const m = data.metrics;
            const metricCards = document.querySelectorAll('.metrics-container .metric-card');
            if (metricCards[0]) {
                const val = metricCards[0].querySelector('.metric-value');
                if (val) val.textContent = m.totalAlumni;
            }
            if (metricCards[1]) {
                const val = metricCards[1].querySelector('.metric-value');
                if (val) val.textContent = m.totalMale;
                const change = metricCards[1].querySelector('.metric-change');
                if (change) {
                    const icon = change.querySelector('i');
                    const malePercent = (m.totalAlumni > 0 && m.totalMale >= 0)
                        ? ((m.totalMale / m.totalAlumni) * 100).toFixed(1)
                        : '0.0';
                    change.textContent = '';
                    if (icon) {
                        change.appendChild(icon);
                        icon.style.marginRight = '8px';
                    }
                    change.append(' ' + malePercent + '%');
                }
            }
            if (metricCards[2]) {
                const val = metricCards[2].querySelector('.metric-value');
                if (val) val.textContent = m.totalFemale;
                const change = metricCards[2].querySelector('.metric-change');
                if (change) {
                    const icon = change.querySelector('i');
                    const femalePercent = (m.totalAlumni > 0 && m.totalFemale >= 0)
                        ? ((m.totalFemale / m.totalAlumni) * 100).toFixed(1)
                        : '0.0';
                    change.textContent = '';
                    if (icon) {
                        change.appendChild(icon);
                        icon.style.marginRight = '8px';
                    }
                    change.append(' ' + femalePercent + '%');
                }
            }
            if (metricCards[3]) {
                const val = metricCards[3].querySelector('.metric-value');
                if (val) val.textContent = m.avgAge;
            }
        }
    })
    .catch(error => {
        console.error('Error updating charts:', error);
        document.querySelectorAll('.spinner').forEach(s => s.style.display = 'none');
        document.querySelectorAll('[id$="-nodata"]').forEach(n => n.style.display = 'block');
    });
}

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCharts();
    
    // Add event listeners to filter controls
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', updateCharts);
    });
});


// Helper: Collect metrics data from the DOM
function getMetricsData() {
    return [
        ['Metrics'],
        ['Metric', 'Value'],
        ['Total Alumni', document.querySelector('.metric-card:nth-child(1) .metric-value')?.textContent.trim() || ''],
        ['Male Alumni', document.querySelector('.metric-card:nth-child(2) .metric-value')?.textContent.trim() || ''],
        ['Female Alumni', document.querySelector('.metric-card:nth-child(3) .metric-value')?.textContent.trim() || ''],
        ['Average Age', document.querySelector('.metric-card:nth-child(4) .metric-value')?.textContent.trim() || ''],
        ['']
    ];
}

// Helper: Collect chart data from Chart.js instances
function getChartData() {
    const data = [];
    // Gender Distribution
    if (window.sexChart) {
        data.push(['Gender Distribution']);
        data.push(['__CHART_IMAGE__', 'gender']);
        // Single-column description row (keeps chart rendering intact)
        data.push(['This chart shows how graduates are represented across gender categories.']);
        data.push(['Gender', 'Count']);
        const chartData = window.sexChart.data;
        for (let i = 0; i < chartData.labels.length; i++) {
            data.push([chartData.labels[i], chartData.datasets[0].data[i]]);
        }
        data.push(['']);
    }
    // Age Distribution
    if (window.ageChart) {
        data.push(['Age Distribution']);
        data.push(['__CHART_IMAGE__', 'age']);
        data.push(['This chart shows age group distribution within alumni population.']);
        data.push(['Age Group', 'Count']);
        const chartData = window.ageChart.data;
        for (let i = 0; i < chartData.labels.length; i++) {
            data.push([chartData.labels[i], chartData.datasets[0].data[i]]);
        }
        data.push(['']);
    }
    // Degree Breakdown
    if (window.degreeChart) {
        data.push(['Degree Breakdown']);
        data.push(['__CHART_IMAGE__', 'degree']);
        data.push(['This chart provides of the graduates under each field of study.']);
        data.push(['Program', 'Count']);
        const chartData = window.degreeChart.data;
        for (let i = 0; i < chartData.labels.length; i++) {
            data.push([chartData.labels[i], chartData.datasets[0].data[i]]);
        }
        data.push(['']);
    }
    return data;
}

// Main export handler (async) - triggers exports and reloads page after export finishes
async function handleExport(metrics, charts, pdf, excel, word, csv) {
    const exportData = [];
    if (metrics) exportData.push(...getMetricsData());
    if (charts) exportData.push(...getChartData());
    const title = 'Demographics Dashboard Export';
    const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');

    // Capture chart images as base64 (prefer Chart.js API with canvas fallback)
    let chartImages = {};
    if (charts) {
        const toImg = (chartInstance, canvasId) => {
            try {
                if (chartInstance && typeof chartInstance.toBase64Image === 'function') {
                    return chartInstance.toBase64Image();
                }
                const canvas = document.getElementById(canvasId);
                return canvas ? canvas.toDataURL('image/png', 1.0) : null;
            } catch (e) { return null; }
        };
        chartImages.gender = toImg(window.sexChart, 'sexPieChart');
        chartImages.age = toImg(window.ageChart, 'ageHistogram');
        chartImages.degree = toImg(window.degreeChart, 'degreeBarChart');
    }

    // Collect export promises
    const exportPromises = [];

    if (pdf && window.ExportLibraries) {
        exportPromises.push(window.ExportLibraries.exportToPDF(exportData, `demographics_${timestamp}.pdf`, { chartImages }, title));
    }
    if (excel && window.ExportLibraries) {
        exportPromises.push(window.ExportLibraries.exportToExcel(exportData, `demographics_${timestamp}.xlsx`));
    }
    if (word && window.ExportLibraries && window.ExportLibraries.exportToWord) {
        exportPromises.push(window.ExportLibraries.exportToWord(exportData, `demographics_${timestamp}.docx`, {}, title));
    }
    if (csv && window.ExportLibraries) {
        exportPromises.push(window.ExportLibraries.exportToCSV(exportData, `demographics_${timestamp}.csv`));
    }

    // Wait for all export functions to complete (they resolve immediately after initiating download)
    try {
        await Promise.all(exportPromises);
    } catch (e) {
        // Ignore individual export errors here but log
        console.warn('One or more export functions failed:', e);
    }
    // Hide modal after initiating export
    var modal = getExportModalInstance();
    if (modal) modal.hide();
    cleanupModalArtifacts();
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

document.addEventListener('DOMContentLoaded', function() {
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
        exportForm.addEventListener('submit', async function(event) {
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
            // If Word is selected, use server-side export like staff/index.php
            if (word) {
                const form = document.getElementById('filterForm');
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                const url = 'demographics.php?' + params.toString() +
                    '&export=word' +
                    '&metrics=' + (metrics ? '1' : '0') +
                    '&charts=' + (charts ? '1' : '0');
                window.location.href = url;
                return;
            }
            // Await export completion (handleExport will trigger reload shortly after)
            try {
                await handleExport(metrics, charts, pdf, excel, false, csv);
            } catch (e) {
                console.error('Export failed', e);
            }
            var modal = getExportModalInstance();
            if (modal) modal.hide();
            cleanupModalArtifacts();
        });
    }
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

</script>
