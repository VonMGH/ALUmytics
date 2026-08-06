<?php
include '../db/Database.php';
include 'includes/access_control.php';

requireModuleAccess('report-generation');

$conn = Database::getInstance()->getConnection();

// Optional PHPWord support for generating real .docx reports with PLSP header
$phpWordAvailable = false;
$phpWordAutoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($phpWordAutoload && file_exists($phpWordAutoload)) {
    require_once $phpWordAutoload;
    if (class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        $phpWordAvailable = true;
    }
}

// Read filters from query string
$reportCategory = $_GET['category'] ?? 'demographics';
$search = trim($_GET['search'] ?? '');
$filterDepartment = $_GET['department'] ?? 'all';
$filterMajor = $_GET['major'] ?? 'all';
$filterYear = $_GET['year_graduated'] ?? 'all';
$filterGender = $_GET['gender'] ?? 'all';
$filterEmploymentStatus = $_GET['employment_status'] ?? 'all';
$filterMobility = $_GET['mobility'] ?? 'all';
$filterAgeRange = $_GET['age_range'] ?? 'all';
// Employment-category specific filters
$filterIndustry = $_GET['industry'] ?? 'all';
$filterWorkArrangement = $_GET['work_arrangement'] ?? 'all';
$filterCompanyType = $_GET['company_type'] ?? 'all';
$filterJobStatus = $_GET['job_status'] ?? 'all';
// Certification & Awards category filter
$filterCertCategory = $_GET['cert_category'] ?? 'all';
// Certification & Awards count/quantity threshold (Any, >=1, >=5, >=10)
$filterCertCount = $_GET['cert_count'] ?? 'all';
// Geography-specific filters
$filterGeoCountry = $_GET['geo_country'] ?? 'all';
$filterGeoCity = $_GET['geo_city'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build WHERE conditions
$where = [];
$where[] = "u.role = 'alumni'";

// College restriction (coordinators only see their college)
if (isCollegeRestricted() && isset($college_id) && $college_id) {
    $where[] = "e.college_department = (SELECT name FROM colleges WHERE id = " . (int)$college_id . ")";
}

if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $where[] = "(u.full_name LIKE '%{$safe}%' OR u.email LIKE '%{$safe}%' OR e.alumni_id LIKE '%{$safe}%')";
}

if ($filterDepartment !== 'all' && $filterDepartment !== 'none' && $filterDepartment !== '') {
    $safeDept = $conn->real_escape_string($filterDepartment);
    $where[] = "e.college_department = '{$safeDept}'";
}

if ($filterMajor !== 'all' && $filterMajor !== 'none' && $filterMajor !== '') {
    $safeMajor = $conn->real_escape_string($filterMajor);
    $where[] = "e.major_specialization = '{$safeMajor}'";
}

if ($filterYear !== 'all' && $filterYear !== 'none' && $filterYear !== '') {
    $safeYear = $conn->real_escape_string($filterYear);
    $where[] = "e.year_graduated = '{$safeYear}'";
}

if ($filterGender !== 'all' && $filterGender !== 'none' && $filterGender !== '') {
    $safeGender = $conn->real_escape_string($filterGender);
    $where[] = "LOWER(p.sex) = LOWER('{$safeGender}')";
}

// Age range filter based on birthdate (p.dob)
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

if ($filterEmploymentStatus !== 'all' && $filterEmploymentStatus !== 'none' && $filterEmploymentStatus !== '') {
    $safeStatus = $conn->real_escape_string($filterEmploymentStatus);
    $where[] = "emp.employment_status = '{$safeStatus}'";
}

if ($filterMobility !== 'all' && $filterMobility !== 'none' && $filterMobility !== '') {
    $safeMobility = $conn->real_escape_string($filterMobility);
    $where[] = "emp.mobility = '{$safeMobility}'";
}

if ($filterIndustry !== 'all' && $filterIndustry !== 'none' && $filterIndustry !== '') {
    $safeIndustry = $conn->real_escape_string($filterIndustry);
    $where[] = "emp.industry = '{$safeIndustry}'";
}

if ($filterWorkArrangement !== 'all' && $filterWorkArrangement !== 'none' && $filterWorkArrangement !== '') {
    $safeWA = $conn->real_escape_string($filterWorkArrangement);
    $where[] = "emp.work_arrangement = '{$safeWA}'";
}

if ($filterCompanyType !== 'all' && $filterCompanyType !== 'none' && $filterCompanyType !== '') {
    $safeCompanyType = $conn->real_escape_string($filterCompanyType);
    $where[] = "emp.company_type = '{$safeCompanyType}'";
}

if ($filterJobStatus !== 'all' && $filterJobStatus !== 'none' && $filterJobStatus !== '') {
    $safeJobStatus = $conn->real_escape_string($filterJobStatus);
    $where[] = "emp.job_status = '{$safeJobStatus}'";
}

if ($filterCertCategory !== 'all' && $filterCertCategory !== 'none' && $filterCertCategory !== '') {
    $safeCertCat = $conn->real_escape_string($filterCertCategory);
    $where[] = "(EXISTS (SELECT 1 FROM certifications c WHERE c.user_id = u.user_id AND c.category = '{$safeCertCat}')
                 OR EXISTS (SELECT 1 FROM awards a WHERE a.user_id = u.user_id AND a.category = '{$safeCertCat}'))";
}

// Certification count/quantity threshold: apply only when viewing certification category
if ($reportCategory === 'certification' && $filterCertCount !== 'all' && $filterCertCount !== 'none' && $filterCertCount !== '') {
    $threshold = (int)$filterCertCount;
    if ($threshold > 0) {
        $catFilterCert = '';
        $catFilterAward = '';
        if ($filterCertCategory !== 'all' && $filterCertCategory !== '') {
            $safeCatForCount = $conn->real_escape_string($filterCertCategory);
            $catFilterCert = " AND c2.category = '{$safeCatForCount}'";
            $catFilterAward = " AND a2.category = '{$safeCatForCount}'";
        }
        $where[] = "((SELECT COUNT(*) FROM certifications c2 WHERE c2.user_id = u.user_id{$catFilterCert})
                     + (SELECT COUNT(*) FROM awards a2 WHERE a2.user_id = u.user_id{$catFilterAward})) >= {$threshold}";
    }
}

// Geography-specific filters applied when viewing Geography category
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

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Load filter options
if (isCollegeRestricted() && isset($college_id) && $college_id) {
    $departments = $conn->query("SELECT name AS college_department FROM colleges WHERE id = " . (int)$college_id . " ORDER BY name");
} else {
    $departments = $conn->query("SELECT name AS college_department FROM colleges ORDER BY name");
}

// Major/Specialization options for additional global filter
$majorsGlobal = $conn->query("SELECT DISTINCT major_specialization FROM education WHERE major_specialization IS NOT NULL AND major_specialization <> '' ORDER BY major_specialization");

// Certification & Awards categories (union of Acertification.php and Aawards.php)
$certAwardCategories = [
    'Technology',
    'Healthcare',
    'Business',
    'Education',
    'Engineering',
    'Finance',
    'Science',
    'Arts',
    'Academic Excellence',
    'Professional Achievement',
    'Leadership',
    'Community Service',
    'Sports & Athletics',
    'Arts & Culture',
    'Innovation & Research',
    'Student Organization',
    'Outstanding Performance',
    'Merit Award',
    'Honor Society',
    'Other',
];

// Geography filter options (based on current employment locations)
$geoCountries = $conn->query("SELECT DISTINCT company_country FROM employment WHERE company_country IS NOT NULL AND company_country <> '' ORDER BY company_country");
$geoCities = $conn->query("SELECT DISTINCT company_city FROM employment WHERE company_city IS NOT NULL AND company_city <> '' ORDER BY company_city");

$yearsResult = $conn->query("SELECT DISTINCT year_graduated FROM education WHERE year_graduated IS NOT NULL AND year_graduated <> '' ORDER BY year_graduated DESC");

// Count total records
$countSql = "SELECT COUNT(DISTINCT u.user_id) AS total
             FROM users u
             JOIN education e ON e.user_id = u.user_id
             LEFT JOIN personal p ON p.user_id = u.user_id
             LEFT JOIN employment emp ON emp.user_id = u.user_id AND emp.id = (
                 SELECT MAX(id) FROM employment WHERE user_id = u.user_id
             )
             {$whereSql}";
$totalResult = $conn->query($countSql);
$totalRecords = $totalResult ? (int)$totalResult->fetch_assoc()['total'] : 0;
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Main query for alumni list
$listSql = "SELECT 
                u.user_id,
                u.full_name,
                u.email,
                p.phone_number AS contact_number,
                p.sex,
                e.alumni_id,
                e.college_department,
                e.program,
                e.year_graduated,
                emp.employment_status,
                emp.mobility
            FROM users u
            JOIN education e ON e.user_id = u.user_id
            LEFT JOIN personal p ON p.user_id = u.user_id
            LEFT JOIN employment emp ON emp.user_id = u.user_id AND emp.id = (
                SELECT MAX(id) FROM employment WHERE user_id = u.user_id
            )
            {$whereSql}
            GROUP BY u.user_id
            ORDER BY e.year_graduated DESC, u.full_name ASC
            LIMIT {$perPage} OFFSET {$offset}";

$alumniResult = $conn->query($listSql);

// Handle DOCX export before rendering the page
if (isset($_GET['export']) && $_GET['export'] === 'docx') {
    $exportSql = "SELECT 
                    u.user_id,
                    u.full_name,
                    u.email,
                    p.phone_number AS contact_number,
                    e.alumni_id,
                    e.college_department,
                    e.year_graduated
                FROM users u
                JOIN education e ON e.user_id = u.user_id
                LEFT JOIN personal p ON p.user_id = u.user_id
                LEFT JOIN employment emp ON emp.user_id = u.user_id AND emp.id = (
                    SELECT MAX(id) FROM employment WHERE user_id = u.user_id
                )
                {$whereSql}
                GROUP BY u.user_id
                ORDER BY e.year_graduated DESC, u.full_name ASC";

    $exportResult = $conn->query($exportSql);

    $categoryLabel = 'Demographics';
    if ($reportCategory === 'employment') {
        $categoryLabel = 'Employment';
    } elseif ($reportCategory === 'certification') {
        $categoryLabel = 'Certification & Awards';
    } elseif ($reportCategory === 'geography') {
        $categoryLabel = 'Geography';
    }

    // Prefer PHPWord DOCX export when available
    if ($phpWordAvailable && $exportResult) {
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

            // Title and meta info with spacing below header
            $section->addText(
                'Alumni Report',
                ['bold' => true, 'size' => 16],
                ['spaceBefore' => 160, 'spaceAfter' => 240]
            );

            $section->addText(
                'Category: ' . $categoryLabel,
                ['size' => 11],
                ['spaceAfter' => 120]
            );

            $section->addText(
                'Generated on ' . date('Y-m-d H:i:s'),
                ['size' => 11],
                ['spaceAfter' => 240]
            );

            // Table of alumni
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 50,
                'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
            ]);

            $headerRow = [
                'Alumni ID',
                'Name',
                'Email',
                'Contact Number',
                'Department',
                'Year Graduated',
            ];

            $table->addRow();
            foreach ($headerRow as $col) {
                $table->addCell(2000, ['bgColor' => '219653'])->addText(
                    $col,
                    ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]
                );
            }

            while ($row = $exportResult->fetch_assoc()) {
                $table->addRow();
                $table->addCell()->addText($row['alumni_id'] ?? '');
                $table->addCell()->addText($row['full_name'] ?? '');
                $table->addCell()->addText($row['email'] ?? '');
                $table->addCell()->addText($row['contact_number'] ?? '');
                $table->addCell()->addText($row['college_department'] ?? '');
                $table->addCell()->addText($row['year_graduated'] ?? '');
            }

            $section->addText(
                'Generated by ALUMytics',
                ['size' => 10],
                ['spaceBefore' => 240]
            );

            // Stream DOCX
            $fileName = 'alumni_report_' . date('Ymd_His') . '.docx';

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
    header('Content-Disposition: attachment; filename="alumni_report_' . date('Ymd_His') . '.doc"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "<html><head><meta charset=\"UTF-8\"><title>Alumni Report - " . htmlspecialchars($categoryLabel, ENT_QUOTES) . "</title></head><body>";
    echo "<h2>Alumni Report</h2>";
    echo "<p><strong>Category:</strong> " . htmlspecialchars($categoryLabel, ENT_QUOTES) . "</p>";
    echo "<p>Generated on " . date('Y-m-d H:i:s') . "</p>";

    echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">";
    echo "<tr>";
    echo "<th>Alumni ID</th>";
    echo "<th>Name</th>";
    echo "<th>Email</th>";
    echo "<th>Contact Number</th>";
    echo "<th>Department</th>";
    echo "<th>Year Graduated</th>";
    echo "</tr>";

    if ($exportResult && $exportResult->num_rows > 0) {
        // Reset pointer in case it was partially consumed above
        $exportResult->data_seek(0);
        while ($row = $exportResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['alumni_id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['full_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['email'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['contact_number'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['college_department'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['year_graduated'] ?? '') . "</td>";
            echo "</tr>";
        }
    }

    echo "</table>";
    echo "</body></html>";
    exit;
}

include 'includes/header.php';
include 'includes/sidebar.php';

$role ??= null;
$college_name ??= null;
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/report-generation.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top d-flex justify-content-between align-items-start flex-wrap">
            <div class="mb-2 mb-md-0">
                <h1 class="mb-0 dashboard-title">Alumni Tracking</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Reporting for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Generate detailed alumni reports by category and export them for analysis.</p>
                <?php endif; ?>
            </div>
            <div class="report-export-actions">
                <button class="btn btn-primary btn-sm" type="button" data-export="docx" disabled>Export to DOCX</button>
                <button class="btn btn-success btn-sm" type="button" data-export="excel" disabled>Export to Excel</button>
                <button class="btn btn-danger btn-sm" type="button" data-export="pdf" disabled>Export to PDF</button>
                <button class="btn btn-info btn-sm" type="button" data-export="csv" disabled>Export to CSV</button>
            </div>
        </div>
    </div>

    <form method="get" action="" class="mt-1">
        <div class="card dashboard-card shadow-sm rg-filter-card">
            <div class="card-body">
                <div class="filter-panel-title mb-3"><i class="fas fa-filter"></i> Report Filters</div>
                <div class="row g-3 align-items-end filter-main-row">
                    <div class="col-md-4 category-col">
                        <label for="reportCategory" class="form-label rg-category-label">Select Report Category</label>
                        <select id="reportCategory" name="category" class="form-select">
                            <option value="demographics" <?= $reportCategory === 'demographics' ? 'selected' : '' ?>>Demographics</option>
                            <option value="employment" <?= $reportCategory === 'employment' ? 'selected' : '' ?>>Employment</option>
                            <option value="certification" <?= $reportCategory === 'certification' ? 'selected' : '' ?>>Certification &amp; Awards</option>
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
                                <?php elseif ($reportCategory === 'certification'): ?>
                                    Certification &amp; Awards Filters
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
                                <div style="min-width: 170px;">
                                    <select class="form-select form-select-sm" name="industry" aria-label="Industry filter">
                                        <option value="all" <?= $filterIndustry === 'all' ? 'selected' : '' ?>>All Industries</option>
                                        <?php
                                        $industries = [
                                            'Agriculture',
                                            'Banking and Finance',
                                            'Construction',
                                            'Education',
                                            'Energy and Utilities',
                                            'Entertainment',
                                            'Government',
                                            'Healthcare',
                                            'Hospitality',
                                            'Information Technology',
                                            'Manufacturing',
                                            'Marketing and Advertising',
                                            'Mining',
                                            'Non-Profit',
                                            'Pharmaceuticals',
                                            'Real Estate',
                                            'Retail',
                                            'Telecommunications',
                                            'Transportation and Logistics',
                                            'Other',
                                        ];
                                        foreach ($industries as $ind): ?>
                                            <option value="<?= htmlspecialchars($ind) ?>" <?= $filterIndustry === $ind ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($ind) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
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
                                <div style="min-width: 160px;">
                                    <select class="form-select form-select-sm" name="job_status" aria-label="Job Status filter">
                                        <option value="all" <?= $filterJobStatus === 'all' ? 'selected' : '' ?>>All Job Status</option>
                                        <option value="permanent" <?= $filterJobStatus === 'permanent' ? 'selected' : '' ?>>Permanent</option>
                                        <option value="contractual" <?= $filterJobStatus === 'contractual' ? 'selected' : '' ?>>Contractual</option>
                                        <option value="temporary" <?= $filterJobStatus === 'temporary' ? 'selected' : '' ?>>Temporary</option>
                                        <option value="job_order_casual" <?= $filterJobStatus === 'job_order_casual' ? 'selected' : '' ?>>Job Order / Casual</option>
                                        <option value="self_employed" <?= $filterJobStatus === 'self_employed' ? 'selected' : '' ?>>Self-Employed</option>
                                    </select>
                                </div>
                            <?php elseif ($reportCategory === 'certification'): ?>
                                <div style="min-width: 220px;">
                                    <select class="form-select form-select-sm" name="cert_category" aria-label="Certification &amp; Awards Category filter">
                                        <option value="all" <?= $filterCertCategory === 'all' ? 'selected' : '' ?>>All Categories</option>
                                        <?php foreach ($certAwardCategories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCertCategory === $cat ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="min-width: 200px;">
                                    <select class="form-select form-select-sm" name="cert_count" aria-label="Certification &amp; Awards Count filter">
                                        <option value="all" <?= $filterCertCount === 'all' ? 'selected' : '' ?>>Any number of Awards & Certification</option>
                                        <option value="1" <?= $filterCertCount === '1' ? 'selected' : '' ?>>At least 1</option>
                                        <option value="5" <?= $filterCertCount === '5' ? 'selected' : '' ?>>At least 5</option>
                                        <option value="10" <?= $filterCertCount === '10' ? 'selected' : '' ?>>At least 10</option>
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
                                                <option value="<?= htmlspecialchars($city) ?>" <?= $filterGeoCity === $city ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($city) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div style="min-width: 160px;">
                                    <select class="form-select form-select-sm" name="department" aria-label="Department filter">
                                        <option value="all" <?= $filterDepartment === 'all' ? 'selected' : '' ?>>All Departments</option>
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
                                    <select class="form-select form-select-sm" name="year_graduated" aria-label="Year Graduated filter">
                                        <option value="all" <?= $filterYear === 'all' ? 'selected' : '' ?>>All Years</option>
                                        <?php if ($yearsResult): ?>
                                            <?php while ($row = $yearsResult->fetch_assoc()): ?>
                                                <?php $yearVal = $row['year_graduated']; ?>
                                                <option value="<?= htmlspecialchars($yearVal) ?>" <?= $filterYear == $yearVal ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($yearVal) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div style="min-width: 120px;">
                                    <select class="form-select form-select-sm" name="gender" aria-label="Gender filter">
                                        <option value="all" <?= $filterGender === 'all' ? 'selected' : '' ?>>All Genders</option>
                                        <option value="none" <?= $filterGender === 'none' ? 'selected' : '' ?>>None</option>
                                        <option value="male" <?= $filterGender === 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= $filterGender === 'female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                                <div style="min-width: 150px;">
                                    <select class="form-select form-select-sm" name="employment_status" aria-label="Employment Status filter">
                                        <option value="all" <?= $filterEmploymentStatus === 'all' ? 'selected' : '' ?>>All Employment Status</option>
                                        <option value="employed" <?= $filterEmploymentStatus === 'employed' ? 'selected' : '' ?>>Employed</option>
                                        <option value="unemployed" <?= $filterEmploymentStatus === 'unemployed' ? 'selected' : '' ?>>Unemployed</option>
                                        <option value="self_employed" <?= $filterEmploymentStatus === 'self_employed' ? 'selected' : '' ?>>Self-Employed</option>
                                    </select>
                                </div>
                                <div style="min-width: 140px;">
                                    <select class="form-select form-select-sm" name="mobility" aria-label="Mobility filter">
                                        <option value="all" <?= $filterMobility === 'all' ? 'selected' : '' ?>>All Mobility</option>
                                        <option value="local" <?= $filterMobility === 'local' ? 'selected' : '' ?>>Local</option>
                                        <option value="international" <?= $filterMobility === 'international' ? 'selected' : '' ?>>International</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card dashboard-card shadow-sm rg-table-card mt-3">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-3 gap-3">
                    <div class="flex-grow-1">
                        <label for="searchUser" class="form-label rg-category-label">Search alumni</label>
                        <input
                            type="text"
                            id="searchUser"
                            name="search"
                            class="form-control"
                            placeholder="Search by name, email, or Alumni ID"
                            value="<?= htmlspecialchars($search) ?>"
                        >
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0">
                        <?php if (!isCollegeRestricted() && $reportCategory !== 'demographics'): ?>
                            <div style="min-width: 180px;">
                                <select class="form-select form-select-sm" name="department" aria-label="Additional Department filter">
                                    <option value="all" <?= $filterDepartment === 'all' ? 'selected' : '' ?>>All Departments</option>
                                    <?php if ($departments): ?>
                                        <?php $departments->data_seek(0); while ($row = $departments->fetch_assoc()): ?>
                                            <?php $dept = $row['college_department']; if (!$dept) continue; ?>
                                            <option value="<?= htmlspecialchars($dept) ?>" <?= $filterDepartment === $dept ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div style="min-width: 180px;">
                                <select class="form-select form-select-sm" name="major" aria-label="Additional Major/Specialization filter">
                                    <option value="all" <?= $filterMajor === 'all' ? 'selected' : '' ?>>All Major/Specialization</option>
                                    <?php if ($majorsGlobal): ?>
                                        <?php while ($row = $majorsGlobal->fetch_assoc()): ?>
                                            <?php $majorVal = $row['major_specialization']; if (!$majorVal) continue; ?>
                                            <option value="<?= htmlspecialchars($majorVal) ?>" <?= $filterMajor === $majorVal ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($majorVal) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div style="min-width: 140px;">
                            <select class="form-select form-select-sm" name="year_graduated" aria-label="Additional Year Graduated filter">
                                <option value="all" <?= $filterYear === 'all' ? 'selected' : '' ?>>All Years</option>
                                <?php if ($yearsResult): ?>
                                    <?php $yearsResult->data_seek(0); while ($row = $yearsResult->fetch_assoc()): ?>
                                        <?php $yearVal = $row['year_graduated']; ?>
                                        <option value="<?= htmlspecialchars($yearVal) ?>" <?= $filterYear == $yearVal ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($yearVal) ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle rg-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Alumni ID</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Contact Number</th>
                                <th scope="col">Department</th>
                                <th scope="col">Year Graduated</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($alumniResult && $alumniResult->num_rows > 0): ?>
                                <?php while ($row = $alumniResult->fetch_assoc()): ?>
                                    <tr
                                        data-gender="<?= htmlspecialchars(strtolower($row['sex'] ?? '')) ?>"
                                        data-employment-status="<?= htmlspecialchars(strtolower($row['employment_status'] ?? '')) ?>"
                                        data-mobility="<?= htmlspecialchars(strtolower($row['mobility'] ?? '')) ?>"
                                    >
                                        <td><?= htmlspecialchars($row['alumni_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                                        <td><?= htmlspecialchars($row['email']) ?></td>
                                        <td><?= htmlspecialchars($row['contact_number'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['college_department'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['year_graduated'] ?? '') ?></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary view-user-btn" data-user-id="<?= (int)$row['user_id'] ?>">View</button>
                                                <a href="alumni-print.php?user_id=<?= (int)$row['user_id'] ?>" target="_blank" class="btn btn-outline-secondary">Print</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No alumni found for the selected filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center rg-table-footer flex-wrap gap-2">
                    <small class="text-muted mb-0">
                        Showing
                        <?php if ($totalRecords === 0): ?>0<?php else: ?>
                            <?= ($offset + 1) ?>
                            to
                            <?= min($offset + $perPage, $totalRecords) ?>
                        <?php endif; ?>
                        of <?= $totalRecords ?> result(s)
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $queryBase = $_GET;
                            unset($queryBase['page']);
                            $baseUrl = 'report-generation.php';
                            $buildUrl = function($pageNum) use ($baseUrl, $queryBase) {
                                $params = array_merge($queryBase, ['page' => $pageNum]);
                                return $baseUrl . '?' . http_build_query($params);
                            };
                            ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $page <= 1 ? '#' : $buildUrl($page - 1) ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $buildUrl($i) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $page >= $totalPages ? '#' : $buildUrl($page + 1) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </form>
</main>
</div>
<!-- View Alumni Details Modal -->
<div class="modal fade" id="viewAlumniModal" tabindex="-1" aria-labelledby="viewAlumniModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAlumniModalLabel">Alumni Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="alumniDetailsContent">
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-user"></i> Personal Information
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="mb-1"><strong>Full Name:</strong></p>
                                    <p id="alumni-full-name" class="text-muted"></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1"><strong>Phone Number:</strong></p>
                                    <p id="alumni-phone" class="text-muted"></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1"><strong>Email:</strong></p>
                                    <p id="alumni-email" class="text-muted"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-graduation-cap"></i> Educational Background
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>School/University:</strong></p>
                                    <p id="alumni-school" class="text-muted"></p>
                                    <p class="mb-1"><strong>Campus/Branch:</strong></p>
                                    <p id="alumni-campus" class="text-muted"></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>College/Department:</strong></p>
                                    <p id="alumni-college" class="text-muted"></p>
                                    <p class="mb-1"><strong>Program/Major:</strong></p>
                                    <p id="alumni-program" class="text-muted"></p>
                                </div>
                            </div>
                            <p class="mb-1"><strong>Alumni ID:</strong></p>
                            <p id="alumni-id" class="text-muted"></p>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-briefcase"></i> Employment Status
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="mb-1"><strong>Job Title:</strong></p>
                                    <p id="alumni-job-title" class="text-muted"></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1"><strong>Employment Status:</strong></p>
                                    <p id="alumni-employment-status" class="text-muted"></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1"><strong>Mobility:</strong></p>
                                    <p id="alumni-mobility" class="text-muted"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3" id="certifications-card" style="display: none;">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-certificate"></i> Certifications
                        </div>
                        <div class="card-body">
                            <div id="certifications-list"></div>
                        </div>
                    </div>
                    <div class="card mb-3" id="awards-card" style="display: none;">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-trophy"></i> Awards
                        </div>
                        <div class="card-body">
                            <div id="awards-list"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
    
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="js/export-libraries.js?v=3"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function cleanupModalArtifacts() {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(function(el) {
            el.remove();
        });
    }

    function showModal(modalId) {
        var modalEl = document.getElementById(modalId);
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
        return bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    document.querySelectorAll('.modal').forEach(function(modalEl) {
        modalEl.addEventListener('hidden.bs.modal', cleanupModalArtifacts);
    });

    // Auto-submit when switching report category so filter set updates immediately
    var categorySelect = document.getElementById('reportCategory');
    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            var form = categorySelect.closest('form');
            if (form) {
                form.submit();
            }
        });
    }

    // For Geography category, convert PSGC numeric city codes in the City dropdown
    var currentCategory = document.getElementById('reportCategory')?.value;
    if (currentCategory === 'geography') {
        var citySelect = document.querySelector('select[name="geo_city"]');
        if (citySelect) {
            var opts = Array.from(citySelect.options);
            var codeOptions = opts.filter(function (opt) {
                return opt.value && /^\d+$/.test(opt.value) && opt.value.length >= 5;
            });
            if (codeOptions.length > 0) {
                var psgcBase = 'https://psgc.gitlab.io/api/cities-municipalities/';
                codeOptions.forEach(function (opt) {
                    var code = opt.value;
                    fetch(psgcBase + code + '/').then(function (resp) {
                        if (!resp.ok) return null;
                        return resp.json();
                    }).then(function (data) {
                        if (data && data.name) {
                            opt.textContent = data.name;
                        }
                    }).catch(function () {
                        // Ignore errors; fallback is the raw code text
                    });
                });
            }
        }
    }

    // Auto-submit when any filter dropdown changes (except pagination links)
    var filterForm = document.querySelector('form[method="get"]');
    if (filterForm) {
        var filterSelects = filterForm.querySelectorAll('select');
        filterSelects.forEach(function(sel) {
            sel.addEventListener('change', function () {
                filterForm.submit();
            });
        });

        var searchInput = document.getElementById('searchUser');
        if (searchInput) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    filterForm.submit();
                }
            });
            searchInput.addEventListener('blur', function () {
                // Submit on blur only if value actually changed
                if (searchInput.value !== '<?= htmlspecialchars($search, ENT_QUOTES) ?>') {
                    filterForm.submit();
                }
            });
        }
    }

    function collectTableData() {
        const rows = Array.from(document.querySelectorAll('table tbody tr'));
        const dataRows = [];
        let genderCounts = { male: 0, female: 0, other: 0 };
        let statusCounts = {};
        let mobilityCounts = {};

        rows.forEach(function(tr) {
            if (tr.querySelector('td') && tr.querySelector('td').textContent.trim() === 'No alumni found for the selected filters.') {
                return;
            }
            const cells = tr.querySelectorAll('td');
            if (cells.length < 6) return;
            const alumniId = cells[0].textContent.trim();
            const name = cells[1].textContent.trim();
            const email = cells[2].textContent.trim();
            const contact = cells[3].textContent.trim();
            const dept = cells[4].textContent.trim();
            const year = cells[5].textContent.trim();
            dataRows.push([alumniId, name, email, contact, dept, year]);

            const g = (tr.dataset.gender || '').toLowerCase();
            if (g === 'male') genderCounts.male++; else if (g === 'female') genderCounts.female++; else genderCounts.other++;

            const st = (tr.dataset.employmentStatus || '').toLowerCase();
            if (st) statusCounts[st] = (statusCounts[st] || 0) + 1;

            const mob = (tr.dataset.mobility || '').toLowerCase();
            if (mob) mobilityCounts[mob] = (mobilityCounts[mob] || 0) + 1;
        });

        return { dataRows, genderCounts, statusCounts, mobilityCounts };
    }

    function buildExportDataForPdf() {
        // For PDF we want a clean table only, with a clear title and category
        const { dataRows } = collectTableData();
        const exportData = [];

        const category = document.getElementById('reportCategory')?.value || 'demographics';
        let categoryLabel = 'Demographics';
        if (category === 'employment') {
            categoryLabel = 'Employment';
        } else if (category === 'certification') {
            categoryLabel = 'Certification & Awards';
        } else if (category === 'geography') {
            categoryLabel = 'Geography';
        }

        // This single-cell row will be rendered as a centered title in the PDF
        exportData.push(['Alumni Report - ' + categoryLabel]);
        // Blank spacer row
        exportData.push(['']);

        exportData.push(['Alumni ID', 'Name', 'Email', 'Contact Number', 'Department', 'Year Graduated']);
        dataRows.forEach(function (r) { exportData.push(r); });

        return exportData;
    }

    function exportAlumniPdf() {
        // Use jsPDF to render a compact black-and-white table for the alumni list
        let JsPDFCtor = null;
        if (window.jspdf && window.jspdf.jsPDF) {
            JsPDFCtor = window.jspdf.jsPDF;
        } else if (window.jsPDF) {
            JsPDFCtor = window.jsPDF;
        }
        if (!JsPDFCtor) {
            alert('PDF library (jsPDF) not loaded.');
            return;
        }

        const { dataRows } = collectTableData();
        if (!dataRows.length) {
            alert('No data available to export.');
            return;
        }

        const category = document.getElementById('reportCategory')?.value || 'demographics';
        let categoryLabel = 'Demographics';
        if (category === 'employment') {
            categoryLabel = 'Employment';
        } else if (category === 'certification') {
            categoryLabel = 'Certification & Awards';
        } else if (category === 'geography') {
            categoryLabel = 'Geography';
        }

        const doc = new JsPDFCtor({ orientation: 'p', unit: 'mm', format: 'a4' });
        const marginLeft = 15;
        const marginTop = 20;
        const marginRight = 15;
        const lineHeight = 5;

        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const tableWidth = pageWidth - marginLeft - marginRight;

        // Column width ratios similar to export-libraries simple table
        const ratios = [0.12, 0.18, 0.25, 0.15, 0.22, 0.08];
        const colWidths = ratios.map(r => r * tableWidth);

        let y = marginTop;

        // Title
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(16);
        doc.text('Alumni Report - ' + categoryLabel, pageWidth / 2, y, { align: 'center' });
        y += 10;

        // Header row
        doc.setFontSize(10);
        doc.setFont('helvetica', 'bold');
        const headerHeight = 9;
        let x = marginLeft;
        // Use shorter labels so headers fit comfortably in their cells
        const headers = ['Alumni ID', 'Name', 'Email', 'Contact No.', 'Department', 'Year'];
        for (let i = 0; i < headers.length; i++) {
            doc.rect(x, y, colWidths[i], headerHeight);
            doc.text(headers[i], x + 2, y + 6);
            x += colWidths[i];
        }
        y += headerHeight;

        // Body rows
        doc.setFont('helvetica', 'normal');
        dataRows.forEach(function (row) {
            if (y > pageHeight - 20) {
                doc.addPage();
                y = marginTop;

                // redraw header on new page
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(11);
                x = marginLeft;
                for (let i = 0; i < headers.length; i++) {
                    doc.rect(x, y, colWidths[i], headerHeight);
                    doc.text(headers[i], x + 2, y + 6);
                    x += colWidths[i];
                }
                y += headerHeight;
                doc.setFont('helvetica', 'normal');
            }

            const linesPerCol = [];
            let maxLines = 1;
            for (let i = 0; i < headers.length; i++) {
                const text = String(row[i] ?? '');
                const lines = doc.splitTextToSize(text, colWidths[i] - 4);
                linesPerCol.push(lines);
                if (lines.length > maxLines) maxLines = lines.length;
            }
            const rowHeight = Math.max(9, maxLines * lineHeight + 2);

            x = marginLeft;
            for (let i = 0; i < headers.length; i++) {
                doc.rect(x, y, colWidths[i], rowHeight);
                let textY = y + 6;
                linesPerCol[i].forEach(function (line) {
                    doc.text(line, x + 2, textY);
                    textY += lineHeight;
                });
                x += colWidths[i];
            }

            y += rowHeight;
        });

        const ts = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
        doc.save('alumni_report_' + ts + '.pdf');
    }

    function buildExportDataWithSummary() {
        // Rich export structure (with summaries) for non-PDF formats
        const category = document.getElementById('reportCategory')?.value || 'demographics';
        const { dataRows, genderCounts, statusCounts, mobilityCounts } = collectTableData();
        const total = dataRows.length;
        const exportData = [];

        exportData.push(['Alumni Report']);
        exportData.push(['Category', category.charAt(0).toUpperCase() + category.slice(1)]);
        exportData.push(['Total Alumni', total]);
        exportData.push(['']);

        if (category === 'demographics') {
            exportData.push(['Demographics Summary']);
            exportData.push(['Metric', 'Count']);
            exportData.push(['Male', genderCounts.male]);
            exportData.push(['Female', genderCounts.female]);
            exportData.push(['Other / Unspecified', genderCounts.other]);
            exportData.push(['']);
        } else if (category === 'employment') {
            exportData.push(['Employment Status Summary']);
            exportData.push(['Status', 'Count']);
            Object.keys(statusCounts).forEach(function (k) {
                exportData.push([k, statusCounts[k]]);
            });
            exportData.push(['']);
            exportData.push(['Mobility Summary']);
            exportData.push(['Mobility', 'Count']);
            Object.keys(mobilityCounts).forEach(function (k) {
                exportData.push([k, mobilityCounts[k]]);
            });
            exportData.push(['']);
        }

        exportData.push(['Alumni List']);
        exportData.push(['Alumni ID', 'Name', 'Email', 'Contact Number', 'Department', 'Year Graduated']);
        dataRows.forEach(function (r) { exportData.push(r); });

        return exportData;
    }

    function doExport(formats) {
        if (!window.ExportLibraries) {
            alert('Export library not loaded.');
            return;
        }

        // For PDF, use custom jsPDF table (black and white) like Certifications & Awards.
        // Other formats still use ExportLibraries with summaries.
        const isPdfOnly = Array.isArray(formats) && formats.length === 1 && formats[0] === 'pdf';
        if (isPdfOnly) {
            exportAlumniPdf();
            return;
        }

        const data = buildExportDataWithSummary();
        const ts = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
        const base = 'alumni_report_' + ts;
        const category = document.getElementById('reportCategory')?.value || 'demographics';
        let categoryLabel = 'Demographics';
        if (category === 'employment') {
            categoryLabel = 'Employment';
        } else if (category === 'certification') {
            categoryLabel = 'Certification & Awards';
        } else if (category === 'geography') {
            categoryLabel = 'Geography';
        }

        window.ExportLibraries.quickExport(data, base, formats, {
            config: window.ExportLibraries.createConfig('standard'),
            simpleTable: false,
            title: 'Alumni Report - ' + categoryLabel
        });
    }

    const docxBtn = document.querySelector('button[data-export="docx"]');
    const excelBtn = document.querySelector('button[data-export="excel"]');
    const pdfBtn = document.querySelector('button[data-export="pdf"]');
    const csvBtn = document.querySelector('button[data-export="csv"]');

    if (docxBtn) {
        docxBtn.disabled = false;
        docxBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var url = new URL(window.location.href);
            url.searchParams.set('export', 'docx');
            window.location.href = url.toString();
        });
    }
    if (pdfBtn) {
        pdfBtn.disabled = false;
        pdfBtn.addEventListener('click', function(e) {
            e.preventDefault();
            doExport(['pdf']);
        });
    }
    if (excelBtn) {
        excelBtn.disabled = false;
        excelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            doExport(['excel']);
        });
    }
    if (csvBtn) {
        csvBtn.disabled = false;
        csvBtn.addEventListener('click', function(e) {
            e.preventDefault();
            doExport(['csv']);
        });
    }
    // View Alumni Details (same behavior as in usermanagement.php)
    document.querySelectorAll('.view-user-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var userId = this.dataset.userId;

            var setLoading = function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.textContent = 'Loading...';
                }
            };

            ['alumni-full-name', 'alumni-phone', 'alumni-email', 'alumni-school', 'alumni-campus', 'alumni-college', 'alumni-program', 'alumni-id', 'alumni-job-title', 'alumni-employment-status', 'alumni-mobility'].forEach(setLoading);

            var certsCard = document.getElementById('certifications-card');
            var certsList = document.getElementById('certifications-list');
            if (certsList) {
                certsList.innerHTML = '<div class="text-muted small">Loading certifications...</div>';
                if (certsCard) {
                    certsCard.style.display = 'block';
                }
            }

            var awardsCard = document.getElementById('awards-card');
            var awardsList = document.getElementById('awards-list');
            if (awardsList) {
                awardsList.innerHTML = '<div class="text-muted small">Loading awards...</div>';
                if (awardsCard) {
                    awardsCard.style.display = 'block';
                }
            }

            var viewModal = showModal('viewAlumniModal');
            if (viewModal) viewModal.show();

            var fetchWithTimeout = function (url, timeout) {
                if (timeout === void 0) { timeout = 8000; }
                return Promise.race([
                    fetch(url),
                    new Promise(function (_, reject) { return setTimeout(function () { return reject(new Error('Request timed out')); }, timeout); })
                ]);
            };

            fetchWithTimeout('usermanagement.php?action=get_alumni_details&user_id=' + userId, 8000)
                .then(function (response) {
                    return response.text().then(function (text) {
                        try {
                            var json = JSON.parse(text);
                            return json;
                        }
                        catch (e) {
                            throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                        }
                    });
                })
                .then(function (data) {
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    var updateElement = function (id, value, defaultValue) {
                        if (defaultValue === void 0) { defaultValue = 'Not provided'; }
                        var element = document.getElementById(id);
                        if (element) {
                            element.textContent = value || defaultValue;
                        }
                    };

                    updateElement('alumni-full-name', (data.user && data.user.full_name) || ((data.personal.first_name || '') + ' ' + (data.personal.last_name || '')).trim());
                    updateElement('alumni-phone', data.personal && data.personal.phone_number);
                    updateElement('alumni-email', [data.personal && data.personal.institutional_email, data.personal && data.personal.personal_email].filter(Boolean).join(' / '));

                    updateElement('alumni-school', data.education && data.education.school_university);
                    updateElement('alumni-campus', data.education && data.education.campus_branch);
                    updateElement('alumni-college', data.education && data.education.college_department);
                    updateElement('alumni-program', data.education && data.education.program ? data.education.program + (data.education.major_specialization ? ' - ' + data.education.major_specialization : '') : data.education && data.education.major_specialization);
                    updateElement('alumni-id', data.education && data.education.alumni_id);

                    updateElement('alumni-job-title', data.employment && data.employment.job_title);
                    updateElement('alumni-employment-status', data.employment && data.employment.employment_status);
                    updateElement('alumni-mobility', data.employment && data.employment.mobility);

                    if (certsList) {
                        certsList.innerHTML = '<div class="text-muted small">Loading certifications...</div>';
                        if (certsCard) {
                            certsCard.style.display = 'block';
                        }
                        fetchWithTimeout('usermanagement.php?action=get_alumni_certs&user_id=' + userId, 8000)
                            .then(function (r) { return r.text(); })
                            .then(function (text) {
                            try {
                                var cc = JSON.parse(text);
                                if (cc.error) {
                                    throw new Error(cc.error);
                                }
                                if (cc.certifications && cc.certifications.length) {
                                    certsList.innerHTML = cc.certifications.map(function (cert) { return '\n                                            <div class="border-bottom pb-2 mb-2">\n                                                <h6 class="mb-1">' + (cert.certification_name || 'Untitled') + '</h6>\n                                                <p class="mb-1 text-muted"><small>\n                                                    <strong>Category:</strong> ' + (cert.category || 'N/A') + '<br>\n                                                    <strong>Issuing Body:</strong> ' + (cert.issuing_body || 'N/A') + '<br>\n                                                    <strong>Date:</strong> ' + (cert.certification_date ? new Date(cert.certification_date).toLocaleDateString() : 'N/A') + '\n                                                </small></p>\n                                            </div>\n                                        '; }).join('');
                                }
                                else {
                                    certsList.innerHTML = '<div class="text-muted small">No certifications found.</div>';
                                }
                            }
                            catch (e) {
                                certsList.innerHTML = '<div class="text-muted small">Unable to load certifications.</div>';
                            }
                        })
                            .catch(function () {
                            certsList.innerHTML = '<div class="text-muted small">Unable to load certifications.</div>';
                        });
                    }

                    if (awardsList) {
                        awardsList.innerHTML = '<div class="text-muted small">Loading awards...</div>';
                        if (awardsCard) {
                            awardsCard.style.display = 'block';
                        }
                        fetchWithTimeout('usermanagement.php?action=get_alumni_awards&user_id=' + userId, 8000)
                            .then(function (r) { return r.text(); })
                            .then(function (text) {
                            try {
                                var aa = JSON.parse(text);
                                if (aa.error) {
                                    throw new Error(aa.error);
                                }
                                if (aa.awards && aa.awards.length) {
                                    awardsList.innerHTML = aa.awards.map(function (award) { return '\n                                            <div class="border-bottom pb-2 mb-2">\n                                                <h6 class="mb-1">' + (award.award_name || 'Untitled') + '</h6>\n                                                <p class="mb-1 text-muted"><small>\n                                                    <strong>Title:</strong> ' + (award.award_title || 'N/A') + '<br>\n                                                    <strong>Awarded By:</strong> ' + (award.awarded_by || 'N/A') + '<br>\n                                                    <strong>Date:</strong> ' + (award.award_date ? new Date(award.award_date).toLocaleDateString() : 'N/A') + '<br>\n                                                    ' + (award.description ? '<strong>Description:</strong> ' + award.description : '') + '\n                                                </small></p>\n                                            </div>\n                                        '; }).join('');
                                }
                                else {
                                    awardsList.innerHTML = '<div class="text-muted small">No awards found.</div>';
                                }
                            }
                            catch (e) {
                                awardsList.innerHTML = '<div class="text-muted small">Unable to load awards.</div>';
                            }
                        })
                            .catch(function () {
                            awardsList.innerHTML = '<div class="text-muted small">Unable to load awards.</div>';
                        });
                    }
                })
                .catch(function (error) {
                    var content = document.getElementById('alumniDetailsContent');
                    if (content) {
                        content.innerHTML = '\n                        <div class="alert alert-danger">\n                            <i class="fas fa-exclamation-triangle"></i>\n                            Error loading alumni details: ' + error.message + '\n                        </div>\n                    ';
                    }
                });
        });
    });
});
</script>
