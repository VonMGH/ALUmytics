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
    
    // Industry Distribution
    $industryJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : "";
    $industryWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $industryWhereSql = $industryWhereSql ? $industryWhereSql . " AND year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }
    $industryData = $conn->query("SELECT industry, COUNT(*) as count FROM employment $industryJoin $industryWhereSql GROUP BY industry ORDER BY count DESC");

    $industryLabels = [];
    $industryCounts = [];
    if ($industryData && $industryData->num_rows > 0) {
        while($row = $industryData->fetch_assoc()) {
            $industryLabels[] = $row['industry'] ? htmlspecialchars($row['industry']) : 'UNEMPLOYED';
            $industryCounts[] = (int)$row['count'];
        }
    }

    // Employment Status
    $statusJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : "";
    $statusWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $statusWhereSql = $statusWhereSql ? $statusWhereSql . " AND year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }
    // Count distinct users per employment_status so multiple jobs for the same alumnus are only counted once
    $statusData = $conn->query("SELECT employment_status, COUNT(DISTINCT employment.user_id) AS count FROM employment $statusJoin $statusWhereSql GROUP BY employment_status ORDER BY count DESC");

    $statusLabels = [];
    $statusCounts = [];
    if ($statusData && $statusData->num_rows > 0) {
        while($row = $statusData->fetch_assoc()) {
            $statusLabels[] = htmlspecialchars(ucfirst($row['employment_status']));
            $statusCounts[] = (int)$row['count'];
        }
    }

    // Work Arrangement
    $waJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : "";
    $waWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $waWhereSql = $waWhereSql ? $waWhereSql . " AND year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }
    $waData = $conn->query("SELECT work_arrangement, COUNT(*) as count FROM employment $waJoin $waWhereSql GROUP BY work_arrangement ORDER BY count DESC");

    $waLabels = [];
    $waCounts = [];
    if ($waData && $waData->num_rows > 0) {
        while($row = $waData->fetch_assoc()) {
            if ($row['work_arrangement'] === null || $row['work_arrangement'] === '') continue;
            $waLabels[] = htmlspecialchars($row['work_arrangement']);
            $waCounts[] = (int)$row['count'];
        }
    }

    // Mobility (Local vs International) — use employment.mobility directly
    $mobilityJoin = $eduWhere ? "JOIN education e ON e.user_id = emp.user_id" : "";
    $mobilityWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $mobilityWhereSql = $mobilityWhereSql ? $mobilityWhereSql . " AND emp.year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE emp.year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }
    // limit to known values
    if ($mobilityWhereSql) {
        $mobilityWhereSql .= " AND emp.mobility IN ('local','international')";
    } else {
        $mobilityWhereSql = "WHERE emp.mobility IN ('local','international')";
    }
    $mobilityQuery = "SELECT 
        CASE WHEN emp.mobility = 'international' THEN 'International' ELSE 'Local' END AS mobility_type,
        COUNT(*) AS count
    FROM employment emp
    $mobilityJoin
    $mobilityWhereSql
    GROUP BY mobility_type
    ORDER BY count DESC";
    
    $mobilityData = $conn->query($mobilityQuery);
    $mobilityLabels = [];
    $mobilityCounts = [];
    if ($mobilityData && $mobilityData->num_rows > 0) {
        while($row = $mobilityData->fetch_assoc()) {
            $mobilityLabels[] = htmlspecialchars($row['mobility_type']);
            $mobilityCounts[] = (int)$row['count'];
        }
    }

    // Company Type
    $companyJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : "";
    $companyWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $companyWhereSql = $companyWhereSql ? $companyWhereSql . " AND year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }
    $companyData = $conn->query("SELECT company_type, COUNT(*) as count FROM employment $companyJoin $companyWhereSql GROUP BY company_type ORDER BY count DESC");

    $companyLabels = [];
    $companyCounts = [];
    if ($companyData && $companyData->num_rows > 0) {
        while($row = $companyData->fetch_assoc()) {
            $companyLabels[] = htmlspecialchars(ucfirst($row['company_type']));
            $companyCounts[] = (int)$row['count'];
        }
    }

    // Job Status
    $jobStatusJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : "";
    $jobStatusWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $jobStatusWhereSql = $jobStatusWhereSql ? $jobStatusWhereSql . " AND year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }
    // Filter out NULL or empty job_status values
    if ($jobStatusWhereSql) {
        $jobStatusWhereSql .= " AND employment.job_status IS NOT NULL AND employment.job_status != ''";
    } else {
        $jobStatusWhereSql = "WHERE employment.job_status IS NOT NULL AND employment.job_status != ''";
    }
    $jobStatusData = $conn->query("SELECT job_status, COUNT(*) as count FROM employment $jobStatusJoin $jobStatusWhereSql GROUP BY job_status ORDER BY count DESC");

    $jobStatusLabels = [];
    $jobStatusCounts = [];
    if ($jobStatusData && $jobStatusData->num_rows > 0) {
        while($row = $jobStatusData->fetch_assoc()) {
            $jobStatusLabels[] = htmlspecialchars($row['job_status']);
            $jobStatusCounts[] = (int)$row['count'];
        }
    }

    // Summary metrics for AJAX (same logic as main metrics section)
    $empJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : "";
    $empWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $empWhereSql = $empWhereSql ? $empWhereSql . " AND year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }

    if ($empWhereSql) {
        $employedWhereAjax     = $empWhereSql . " AND employment_status = 'employed'";
        $unemployedWhereAjax   = $empWhereSql . " AND employment_status = 'unemployed'";
        $selfEmployedWhereAjax = $empWhereSql . " AND employment_status = 'self_employed'";
    } else {
        $employedWhereAjax     = "WHERE employment_status = 'employed'";
        $unemployedWhereAjax   = "WHERE employment_status = 'unemployed'";
        $selfEmployedWhereAjax = "WHERE employment_status = 'self_employed'";
    }

    // Summary metrics: count distinct users, not raw employment rows
    $totalEmployedRowAjax     = $conn->query("SELECT COUNT(DISTINCT employment.user_id) AS total FROM employment $empJoin $employedWhereAjax")->fetch_assoc();
    $totalUnemployedRowAjax   = $conn->query("SELECT COUNT(DISTINCT employment.user_id) AS total FROM employment $empJoin $unemployedWhereAjax")->fetch_assoc();
    $totalSelfEmployedRowAjax = $conn->query("SELECT COUNT(DISTINCT employment.user_id) AS total FROM employment $empJoin $selfEmployedWhereAjax")->fetch_assoc();

    $totalEmployedAjax     = isset($totalEmployedRowAjax['total']) ? (int)$totalEmployedRowAjax['total'] : 0;
    $totalUnemployedAjax   = isset($totalUnemployedRowAjax['total']) ? (int)$totalUnemployedRowAjax['total'] : 0;
    $totalSelfEmployedAjax = isset($totalSelfEmployedRowAjax['total']) ? (int)$totalSelfEmployedRowAjax['total'] : 0;

    // Return chart data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'industry' => ['labels' => $industryLabels, 'counts' => $industryCounts],
        'status' => ['labels' => $statusLabels, 'counts' => $statusCounts],
        'work_arrangement' => ['labels' => $waLabels, 'counts' => $waCounts],
        'mobility' => ['labels' => $mobilityLabels, 'counts' => $mobilityCounts],
        'company' => ['labels' => $companyLabels, 'counts' => $companyCounts],
        'job_status' => ['labels' => $jobStatusLabels, 'counts' => $jobStatusCounts],
        'metrics' => [
            'employed' => $totalEmployedAjax,
            'unemployed' => $totalUnemployedAjax,
            'selfEmployed' => $totalSelfEmployedAjax
        ]
    ]);
    exit;
}

// Include database and access control
include '../db/Database.php';
include 'includes/access_control.php';
$conn = Database::getInstance()->getConnection();

// Optional PHPWord support for generating real .docx employment reports with PLSP header
$phpWordAvailable = false;
$phpWordAutoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($phpWordAutoload && file_exists($phpWordAutoload)) {
    require_once $phpWordAutoload;
    if (class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        $phpWordAvailable = true;
    }
}

// Handle server-side Word export for employment analytics
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    // Rebuild filters from query string (same as main page)
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

    // Employment metrics
    $empJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : "";
    $empWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $empWhereSql = $empWhereSql ? $empWhereSql . " AND year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }

    if ($empWhereSql) {
        $employedWhere      = $empWhereSql . " AND employment_status = 'employed'";
        $unemployedWhere    = $empWhereSql . " AND employment_status = 'unemployed'";
        $selfEmployedWhere  = $empWhereSql . " AND employment_status = 'self_employed'";
    } else {
        $employedWhere      = "WHERE employment_status = 'employed'";
        $unemployedWhere    = "WHERE employment_status = 'unemployed'";
        $selfEmployedWhere  = "WHERE employment_status = 'self_employed'";
    }

    // Summary metrics for export: distinct users per status
    $totalEmployed      = $conn->query("SELECT COUNT(DISTINCT employment.user_id) AS total FROM employment $empJoin $employedWhere")->fetch_assoc()['total'];
    $totalUnemployed    = $conn->query("SELECT COUNT(DISTINCT employment.user_id) AS total FROM employment $empJoin $unemployedWhere")->fetch_assoc()['total'];
    $totalSelfEmployed  = $conn->query("SELECT COUNT(DISTINCT employment.user_id) AS total FROM employment $empJoin $selfEmployedWhere")->fetch_assoc()['total'];

    // Industry distribution
    $industryJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : "";
    $industryWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $industryWhereSql = $industryWhereSql ? $industryWhereSql . " AND year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }
    $industryData = $conn->query("SELECT industry, COUNT(*) as count FROM employment $industryJoin $industryWhereSql GROUP BY industry ORDER BY count DESC");
    $industryRows = [];
    if ($industryData && $industryData->num_rows > 0) {
        while ($row = $industryData->fetch_assoc()) {
            $industryRows[] = [$row['industry'] ? $row['industry'] : 'UNEMPLOYED', (int)$row['count']];
        }
    }

    // Employment status distribution
    $statusJoin = $industryJoin;
    $statusWhereSql = $industryWhereSql;
    $statusData = $conn->query("SELECT employment_status, COUNT(*) as count FROM employment $statusJoin $statusWhereSql GROUP BY employment_status ORDER BY count DESC");
    $statusRows = [];
    if ($statusData && $statusData->num_rows > 0) {
        while ($row = $statusData->fetch_assoc()) {
            $statusRows[] = [ucfirst($row['employment_status']), (int)$row['count']];
        }
    }

    // Mobility distribution
    $mobilityJoin = $eduWhere ? "JOIN education e ON e.user_id = emp.user_id" : "";
    $mobilityWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
    if ($filter_year) {
        $mobilityWhereSql = $mobilityWhereSql ? $mobilityWhereSql . " AND emp.year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE emp.year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }
    if ($mobilityWhereSql) {
        $mobilityWhereSql .= " AND emp.mobility IN ('local','international')";
    } else {
        $mobilityWhereSql = "WHERE emp.mobility IN ('local','international')";
    }
    $mobilityQuery = "SELECT 
        CASE WHEN emp.mobility = 'international' THEN 'International' ELSE 'Local' END AS mobility_type,
        COUNT(*) AS count
    FROM employment emp
    $mobilityJoin
    $mobilityWhereSql
    GROUP BY mobility_type
    ORDER BY count DESC";
    $mobilityData = $conn->query($mobilityQuery);
    $mobilityRows = [];
    if ($mobilityData && $mobilityData->num_rows > 0) {
        while ($row = $mobilityData->fetch_assoc()) {
            $mobilityRows[] = [$row['mobility_type'], (int)$row['count']];
        }
    }

    // Company type distribution
    $companyJoin = $industryJoin;
    $companyWhereSql = $industryWhereSql;
    $companyData = $conn->query("SELECT company_type, COUNT(*) as count FROM employment $companyJoin $companyWhereSql GROUP BY company_type ORDER BY count DESC");
    $companyRows = [];
    if ($companyData && $companyData->num_rows > 0) {
        while ($row = $companyData->fetch_assoc()) {
            $companyRows[] = [$row['company_type'], (int)$row['count']];
        }
    }

    // Job status distribution
    $jobStatusJoin = $industryJoin;
    $jobStatusWhereSql = $industryWhereSql;
    if ($jobStatusWhereSql) {
        $jobStatusWhereSql .= " AND employment.job_status IS NOT NULL AND employment.job_status != ''";
    } else {
        $jobStatusWhereSql = "WHERE employment.job_status IS NOT NULL AND employment.job_status != ''";
    }
    $jobStatusData = $conn->query("SELECT job_status, COUNT(*) as count FROM employment $jobStatusJoin $jobStatusWhereSql GROUP BY job_status ORDER BY count DESC");
    $jobStatusRows = [];
    if ($jobStatusData && $jobStatusData->num_rows > 0) {
        while ($row = $jobStatusData->fetch_assoc()) {
            $jobStatusRows[] = [$row['job_status'], (int)$row['count']];
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
                'Employment Analytics Report',
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
            $metricsTable->addCell()->addText('Employed');
            $metricsTable->addCell()->addText((string)$totalEmployed);

            $metricsTable->addRow();
            $metricsTable->addCell()->addText('Unemployed');
            $metricsTable->addCell()->addText((string)$totalUnemployed);

            $metricsTable->addRow();
            $metricsTable->addCell()->addText('Self-Employed');
            $metricsTable->addCell()->addText((string)$totalSelfEmployed);

            // Industry distribution table
            if ($industryRows) {
                $section->addText(
                    'Industry Distribution',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $indTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $indTable->addRow();
                $indTable->addCell(5000, ['bgColor' => '219653'])->addText('Industry', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $indTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($industryRows as [$label, $count]) {
                    $indTable->addRow();
                    $indTable->addCell()->addText($label);
                    $indTable->addCell()->addText((string)$count);
                }
            }

            // Employment status table
            if ($statusRows) {
                $section->addText(
                    'Employment Status',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $statusTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $statusTable->addRow();
                $statusTable->addCell(5000, ['bgColor' => '219653'])->addText('Status', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $statusTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($statusRows as [$label, $count]) {
                    $statusTable->addRow();
                    $statusTable->addCell()->addText($label);
                    $statusTable->addCell()->addText((string)$count);
                }
            }

            // Mobility table
            if ($mobilityRows) {
                $section->addText(
                    'Mobility (Local vs International)',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $mobTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $mobTable->addRow();
                $mobTable->addCell(5000, ['bgColor' => '219653'])->addText('Type', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $mobTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($mobilityRows as [$label, $count]) {
                    $mobTable->addRow();
                    $mobTable->addCell()->addText($label);
                    $mobTable->addCell()->addText((string)$count);
                }
            }

            // Company type table
            if ($companyRows) {
                $section->addText(
                    'Company Type',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $compTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $compTable->addRow();
                $compTable->addCell(5000, ['bgColor' => '219653'])->addText('Company Type', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $compTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($companyRows as [$label, $count]) {
                    $compTable->addRow();
                    $compTable->addCell()->addText($label);
                    $compTable->addCell()->addText((string)$count);
                }
            }

            // Job status table
            if ($jobStatusRows) {
                $section->addText(
                    'Job Status',
                    ['bold' => true, 'size' => 12],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                );

                $jobTable = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '000000',
                    'cellMargin' => 50,
                    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                ]);

                $jobTable->addRow();
                $jobTable->addCell(5000, ['bgColor' => '219653'])->addText('Job Status', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                $jobTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                foreach ($jobStatusRows as [$label, $count]) {
                    $jobTable->addRow();
                    $jobTable->addCell()->addText($label);
                    $jobTable->addCell()->addText((string)$count);
                }
            }

            $section->addText(
                'Generated by ALUMytics',
                ['size' => 10],
                ['spaceBefore' => 240]
            );

            $fileName = 'employment_' . date('Ymd_His') . '.docx';

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
            // Fall through to plain-text .doc export on any error
        }
    }

    // Fallback: original plain-text Word content
    $content  = "Employment Analytics Report\n";
    $content .= str_repeat('=', 70) . "\n\n";
    $content .= 'Generated: ' . date('Y-m-d H:i:s') . "\n\n";

    $content .= "SUMMARY METRICS\n";
    $content .= str_repeat('-', 70) . "\n";
    $content .= 'Employed: ' . $totalEmployed . "\n";
    $content .= 'Unemployed: ' . $totalUnemployed . "\n";
    $content .= 'Self-Employed: ' . $totalSelfEmployed . "\n\n";

    if ($industryRows) {
        $content .= "INDUSTRY DISTRIBUTION\n";
        $content .= str_repeat('-', 70) . "\n";
        $content .= str_pad('Industry', 40) . "Count\n";
        $content .= str_repeat('-', 40) . str_repeat('-', 10) . "\n";
        foreach ($industryRows as [$label, $count]) {
            $content .= str_pad($label, 40) . $count . "\n";
        }
        $content .= "\n";
    }

    if ($statusRows) {
        $content .= "EMPLOYMENT STATUS\n";
        $content .= str_repeat('-', 70) . "\n";
        $content .= str_pad('Status', 30) . "Count\n";
        $content .= str_repeat('-', 30) . str_repeat('-', 10) . "\n";
        foreach ($statusRows as [$label, $count]) {
            $content .= str_pad($label, 30) . $count . "\n";
        }
        $content .= "\n";
    }

    if ($mobilityRows) {
        $content .= "MOBILITY (LOCAL VS INTERNATIONAL)\n";
        $content .= str_repeat('-', 70) . "\n";
        $content .= str_pad('Type', 30) . "Count\n";
        $content .= str_repeat('-', 30) . str_repeat('-', 10) . "\n";
        foreach ($mobilityRows as [$label, $count]) {
            $content .= str_pad($label, 30) . $count . "\n";
        }
        $content .= "\n";
    }

    if ($companyRows) {
        $content .= "COMPANY TYPE\n";
        $content .= str_repeat('-', 70) . "\n";
        $content .= str_pad('Company Type', 40) . "Count\n";
        $content .= str_repeat('-', 40) . str_repeat('-', 10) . "\n";
        foreach ($companyRows as [$label, $count]) {
            $content .= str_pad($label, 40) . $count . "\n";
        }
        $content .= "\n";
    }

    if ($jobStatusRows) {
        $content .= "JOB STATUS\n";
        $content .= str_repeat('-', 70) . "\n";
        $content .= str_pad('Job Status', 40) . "Count\n";
        $content .= str_repeat('-', 40) . str_repeat('-', 10) . "\n";
        foreach ($jobStatusRows as [$label, $count]) {
            $content .= str_pad($label, 40) . $count . "\n";
        }
        $content .= "\n";
    }

    $content .= str_repeat('=', 70) . "\n";
    $content .= "Generated by ALUMytics\n";

    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="employment_' . date('Ymd_His') . '.doc"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . strlen($content));

    echo $content;
    exit;
}

// Handle other export requests (legacy) – currently unused
if (isset($_GET['export'])) {
    exit;
}

// For normal requests, include layout
include 'includes/header.php'; // This includes access_control.php
include 'includes/sidebar.php';

// Check module access
requireModuleAccess('employment');

// Reuse existing $conn instance
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

// Calculate employment metrics
$empJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : "";
$empWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
if ($filter_year) {
    $empWhereSql = $empWhereSql ? $empWhereSql . " AND year_of_employment = '" . $conn->real_escape_string($filter_year) . "'" : "WHERE year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
}


// Fix: Add WHERE or AND depending on $empWhereSql
if ($empWhereSql) {
    $employedWhere = $empWhereSql . " AND employment_status = 'employed'";
    $unemployedWhere = $empWhereSql . " AND employment_status = 'unemployed'";
    $selfEmployedWhere = $empWhereSql . " AND employment_status = 'self_employed'";
} else {
    $employedWhere = "WHERE employment_status = 'employed'";
    $unemployedWhere = "WHERE employment_status = 'unemployed'";
    $selfEmployedWhere = "WHERE employment_status = 'self_employed'";
}
// Main-page summary metrics: distinct users per employment status
$totalEmployed = $conn->query("SELECT COUNT(DISTINCT employment.user_id) AS total FROM employment $empJoin $employedWhere")->fetch_assoc()['total'];
$totalUnemployed = $conn->query("SELECT COUNT(DISTINCT employment.user_id) AS total FROM employment $empJoin $unemployedWhere")->fetch_assoc()['total'];
$totalSelfEmployed = $conn->query("SELECT COUNT(DISTINCT employment.user_id) AS total FROM employment $empJoin $selfEmployedWhere")->fetch_assoc()['total'];

$role ??= null;
$college_name ??= null;

?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/employment-page.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-0 dashboard-title">Employment Analytics</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Viewing data for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Comprehensive analysis of alumni employment patterns, industry distribution, and career progression.</p>
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
            <h3>Employed</h3>
            <div class="metric-value"><?= $totalEmployed ?></div>
            <div class="metric-change positive">
                <i class="fas fa-briefcase"></i> Currently working
            </div>
            <div class="icon-container icon-briefcase">
                <i class="fas fa-briefcase"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Unemployed</h3>
            <div class="metric-value"><?= $totalUnemployed ?></div>
            <div class="metric-change negative">
                <i class="fas fa-user-times"></i> Seeking employment
            </div>
            <div class="icon-container icon-unemployed">
                <i class="fas fa-user-times"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Self-Employed</h3>
            <div class="metric-value"><?= $totalSelfEmployed ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-user-tie"></i> Entrepreneurs
            </div>
            <div class="icon-container icon-entrepreneur">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
    </div>

    <div class="row g-4 charts-section" id="sortable-charts">
        <div class="col-lg-6 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Industry Distribution</h5>
                        <small class="text-muted">Alumni employment by industry sector.</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="industryChart"></canvas>
                    <div id="industryChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="industryChart-nodata" class="empty-chart-msg" style="display:none;">
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
                        <h5 class="card-title mb-0">Employment Status</h5>
                        <small class="text-muted">Current employment status distribution.</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="statusChart"></canvas>
                    <div id="statusChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="statusChart-nodata" class="empty-chart-msg" style="display:none;">
                        <p class="mb-0">No data available</p>
                    </div>
                </div>
                <div class="card-footer text-muted">
                    <i class="far fa-clock"></i> Just updated.
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2 charts-section" id="sortable-charts-wa">
        <div class="col-lg-6 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Work Arrangement</h5>
                        <small class="text-muted">On-site vs Remote vs Hybrid.</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="workArrangementChart"></canvas>
                    <div id="workArrangementChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="workArrangementChart-nodata" class="empty-chart-msg" style="display:none;">
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
                        <h5 class="card-title mb-0">Company Type</h5>
                        <small class="text-muted">Distribution by company sector.</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="companyChart"></canvas>
                    <div id="companyChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="companyChart-nodata" class="empty-chart-msg" style="display:none;">
                        <p class="mb-0">No data available</p>
                    </div>
                </div>
                <div class="card-footer text-muted">
                    <i class="far fa-clock"></i> Just updated.
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2 charts-section" id="sortable-charts-2">
        <div class="col-lg-6 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Mobility</h5>
                        <small class="text-muted">Local vs International employment.</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="mobilityChart"></canvas>
                    <div id="mobilityChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="mobilityChart-nodata" class="empty-chart-msg" style="display:none;">
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
                        <h5 class="card-title mb-0">Job Status</h5>
                        <small class="text-muted">Permanent, Temporary, Contractual, etc.</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="jobStatusChart"></canvas>
                    <div id="jobStatusChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="jobStatusChart-nodata" class="empty-chart-msg" style="display:none;">
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
                <h5 class="modal-title" id="exportModalLabel">Export Employment Data</h5>
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
<script src="js/export-libraries.js"></script>

<script>
// Global chart variables
let industryChart, statusChart, workArrangementChart, mobilityChart, companyChart, jobStatusChart;


// Create Industry Distribution Chart
function createIndustryChart(data) {
    const ctx = document.getElementById('industryChart').getContext('2d');
    if (industryChart) industryChart.destroy();
    // Use distinct palette (fallback to green shades)
    let palette, bgColors, borderColors;
    if (typeof getDistinctPalette === 'function' && typeof applyAlpha === 'function') {
        palette = getDistinctPalette(data.labels.length);
        bgColors = applyAlpha(palette, 0.85);
        borderColors = palette;
        console.debug('Industry chart palette:', palette, 'bgColors:', bgColors);
    } else {
        const greenShades = ['#388e3c', '#43a047', '#4caf50', '#66bb6a', '#81c784', '#a5d6a7', '#c8e6c9', '#e8f5e9'];
        palette = greenShades.slice(0, data.labels.length);
        bgColors = palette;
        borderColors = palette.map((c, i) => palette[(i+1) % palette.length]);
    }
    industryChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Alumni Count',
                data: data.counts,
                backgroundColor: bgColors,
                borderColor: borderColors,
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
    window.industryChart = industryChart;
}

// Create Employment Status Chart
function createStatusChart(data) {
    const ctx = document.getElementById('statusChart').getContext('2d');
    if (statusChart) statusChart.destroy();
    // Use distinct palette for status (fallback to green shades)
    let paletteS, bgS;
    if (typeof getDistinctPalette === 'function' && typeof applyAlpha === 'function') {
        paletteS = getDistinctPalette(data.labels.length);
        bgS = applyAlpha(paletteS, 0.85);
        console.debug('Status chart palette:', paletteS, 'bgS:', bgS);
    } else {
        const greenShades = ['#388e3c', '#43a047', '#66bb6a', '#a5d6a7'];
        paletteS = greenShades.slice(0, data.labels.length);
        bgS = paletteS;
    }
    statusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.counts,
                backgroundColor: bgS
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
    window.statusChart = statusChart;
}

// Create Work Arrangement Chart
function createWorkArrangementChart(data) {
    const ctx = document.getElementById('workArrangementChart').getContext('2d');
    if (workArrangementChart) workArrangementChart.destroy();
    let paletteW, bgW;
    if (typeof getDistinctPalette === 'function' && typeof applyAlpha === 'function') {
        paletteW = getDistinctPalette(data.labels.length);
        bgW = applyAlpha(paletteW, 0.85);
        console.debug('Work Arrangement chart palette:', paletteW, 'bgW:', bgW);
    } else {
        const greenShades = ['#388e3c', '#66bb6a', '#a5d6a7'];
        paletteW = greenShades.slice(0, data.labels.length);
        bgW = paletteW;
    }
    workArrangementChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.counts,
                backgroundColor: bgW
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
    window.workArrangementChart = workArrangementChart;
}

// Create Mobility Chart
function createMobilityChart(data) {
    const ctx = document.getElementById('mobilityChart').getContext('2d');
    if (mobilityChart) mobilityChart.destroy();
    // Use distinct palette for mobility (fallback to two green shades)
    let paletteM, bgM;
    if (typeof getDistinctPalette === 'function' && typeof applyAlpha === 'function') {
        paletteM = getDistinctPalette(data.labels.length);
        bgM = applyAlpha(paletteM, 0.85);
        console.debug('Mobility chart palette:', paletteM, 'bgM:', bgM);
    } else {
        const greenShades = ['#388e3c', '#a5d6a7'];
        paletteM = greenShades.slice(0, data.labels.length);
        bgM = paletteM;
    }
    mobilityChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.counts,
                backgroundColor: bgM
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
    window.mobilityChart = mobilityChart;
}

// Create Company Type Chart
function createCompanyChart(data) {
    const ctx = document.getElementById('companyChart').getContext('2d');
    if (companyChart) companyChart.destroy();
    // Use distinct palette for company types (fallback to green shades)
    let paletteC, bgC, borderC;
    if (typeof getDistinctPalette === 'function' && typeof applyAlpha === 'function') {
        paletteC = getDistinctPalette(data.labels.length);
        bgC = applyAlpha(paletteC, 0.85);
        borderC = paletteC;
        console.debug('Company chart palette:', paletteC, 'bgC:', bgC);
    } else {
        const greenShades = ['#388e3c', '#43a047', '#4caf50', '#66bb6a', '#81c784', '#a5d6a7', '#c8e6c9', '#e8f5e9'];
        paletteC = greenShades.slice(0, data.labels.length);
        bgC = paletteC;
        borderC = paletteC.map((c, i) => paletteC[(i+1) % paletteC.length]);
    }
    companyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Alumni Count',
                data: data.counts,
                backgroundColor: bgC,
                borderColor: borderC,
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
    window.companyChart = companyChart;
}

// Create Job Status Chart
function createJobStatusChart(data) {
    const ctx = document.getElementById('jobStatusChart').getContext('2d');
    if (jobStatusChart) jobStatusChart.destroy();
    // Use distinct palette for job status (fallback to green shades)
    let paletteJ, bgJ;
    if (typeof getDistinctPalette === 'function' && typeof applyAlpha === 'function') {
        paletteJ = getDistinctPalette(data.labels.length);
        bgJ = applyAlpha(paletteJ, 0.85);
        console.debug('Job Status chart palette:', paletteJ, 'bgJ:', bgJ);
    } else {
        const greenShades = ['#388e3c', '#43a047', '#66bb6a', '#81c784', '#a5d6a7'];
        paletteJ = greenShades.slice(0, data.labels.length);
        bgJ = paletteJ;
    }
    jobStatusChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.counts,
                backgroundColor: bgJ
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
    window.jobStatusChart = jobStatusChart;
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
    fetch(`employment.php?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        // Hide spinners
        document.querySelectorAll('.spinner').forEach(s => s.style.display = 'none');
        document.querySelectorAll('canvas').forEach(c => c.style.display = 'block');
        
        // Update charts
        if (data.industry.labels.length > 0) {
            createIndustryChart(data.industry);
        } else {
            document.getElementById('industryChart').style.display = 'none';
            document.getElementById('industryChart-nodata').style.display = 'block';
        }
        
        if (data.status.labels.length > 0) {
            createStatusChart(data.status);
        } else {
            document.getElementById('statusChart').style.display = 'none';
            document.getElementById('statusChart-nodata').style.display = 'block';
        }
        
        if (data.work_arrangement.labels.length > 0) {
            createWorkArrangementChart(data.work_arrangement);
        } else {
            document.getElementById('workArrangementChart').style.display = 'none';
            document.getElementById('workArrangementChart-nodata').style.display = 'block';
        }
        
        if (data.mobility.labels.length > 0) {
            createMobilityChart(data.mobility);
        } else {
            document.getElementById('mobilityChart').style.display = 'none';
            document.getElementById('mobilityChart-nodata').style.display = 'block';
        }
        
        if (data.company.labels.length > 0) {
            createCompanyChart(data.company);
        } else {
            document.getElementById('companyChart').style.display = 'none';
            document.getElementById('companyChart-nodata').style.display = 'block';
        }
        
        if (data.job_status.labels.length > 0) {
            createJobStatusChart(data.job_status);
        } else {
            document.getElementById('jobStatusChart').style.display = 'none';
            document.getElementById('jobStatusChart-nodata').style.display = 'block';
        }

        // Update metric cards from AJAX metrics
        if (data.metrics) {
            const m = data.metrics;
            const metricCards = document.querySelectorAll('.metrics-container .metric-card');
            if (metricCards[0]) {
                const val = metricCards[0].querySelector('.metric-value');
                if (val) val.textContent = m.employed;
            }
            if (metricCards[1]) {
                const val = metricCards[1].querySelector('.metric-value');
                if (val) val.textContent = m.unemployed;
            }
            if (metricCards[2]) {
                const val = metricCards[2].querySelector('.metric-value');
                if (val) val.textContent = m.selfEmployed;
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

// --- Export helpers ---
function getMetricsData() {
    // Collect metrics from DOM
    const employed = document.querySelector('.metric-card:nth-child(1) .metric-value')?.textContent.trim() || '';
    const unemployed = document.querySelector('.metric-card:nth-child(2) .metric-value')?.textContent.trim() || '';
    const selfEmployed = document.querySelector('.metric-card:nth-child(3) .metric-value')?.textContent.trim() || '';
    return [
        ['Summary Metrics'],
        ['Metric', 'Count'],
        ['Employed', employed],
        ['Unemployed', unemployed],
        ['Self-Employed', selfEmployed],
        ['']
    ];
}

// Build filter summary from current form selections for inclusion in export
function getFilterSummaryRows() {
    const form = document.getElementById('filterForm');
    if (!form) return [];
    const fields = [
        { id: 'school-university', label: 'School/University' },
        { id: 'campus-branch', label: 'Campus/Branch' },
        { id: 'college-department', label: 'College/Department' },
        { id: 'major-specialization', label: 'Program' },
        { id: 'year', label: 'Year' }
    ];
    const rows = [];
    let hasAny = false;
    rows.push(['Applied Filters']);
    rows.push(['Filter', 'Value']);
    fields.forEach(f => {
        const el = form.querySelector(`#${f.id}`);
        if (!el) return;
        const selected = el.options && el.selectedIndex >= 0 ? el.options[el.selectedIndex].text.trim() : (el.value || '').trim();
        const valueText = selected || 'All';
        if (valueText && valueText.toLowerCase() !== 'all') {
            hasAny = true;
        }
        rows.push([f.label, valueText || 'All']);
    });
    rows.push(['']);
    // If no specific filters applied (all are All/empty), still return the section to show context
    return rows;
}

// Create a concise title suffix based on active (non-All) filters
function getFilterTitleSuffix() {
    const form = document.getElementById('filterForm');
    if (!form) return '';
    const parts = [];
    const add = (id, label) => {
        const el = form.querySelector(`#${id}`);
        if (!el) return;
        const txt = el.options && el.selectedIndex >= 0 ? el.options[el.selectedIndex].text.trim() : (el.value || '').trim();
        if (txt && txt.toLowerCase() !== 'all') parts.push(`${label}: ${txt}`);
    };
    add('school-university', 'School');
    add('campus-branch', 'Campus');
    add('college-department', 'College');
    add('major-specialization', 'Major');
    add('year', 'Year');
    return parts.join('; ');
}

// Create a filename suffix from active filters
function getFilterFilenameSuffix() {
    const form = document.getElementById('filterForm');
    if (!form) return '';
    const slug = s => (s || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 50);
    const parts = [];
    const add = (id, key) => {
        const el = form.querySelector(`#${id}`);
        if (!el) return;
        const txt = el.options && el.selectedIndex >= 0 ? el.options[el.selectedIndex].text.trim() : (el.value || '').trim();
        if (txt && txt.toLowerCase() !== 'all') parts.push(`${key}-${slug(txt)}`);
    };
    add('school-university', 'school');
    add('campus-branch', 'campus');
    add('college-department', 'college');
    add('major-specialization', 'major');
    add('year', 'year');
    return parts.join('_');
}

function getChartData() {
    const data = [];
    // Industry Distribution
    if (window.industryChart && window.industryChart.data && Array.isArray(window.industryChart.data.labels)) {
        data.push(['Industry Distribution']);
        data.push(['Industry', 'Count']);
        const chart = window.industryChart;
        chart.data.labels.forEach((label, i) => {
            data.push([label, chart.data.datasets[0].data[i]]);
        });
        data.push(['__CHART_IMAGE__', 'industry']);
        // Description for Industry Distribution
    data.push(['This chart shows alumni employment by field of work or industry.']);
        data.push(['']);
    }
    // Employment Status
    if (window.statusChart && window.statusChart.data && Array.isArray(window.statusChart.data.labels)) {
        data.push(['Employment Status']);
        data.push(['Status', 'Count']);
        const chart = window.statusChart;
        chart.data.labels.forEach((label, i) => {
            data.push([label, chart.data.datasets[0].data[i]]);
        });
        data.push(['__CHART_IMAGE__', 'status']);
        // Description for Employment Status
    data.push(['This chart shows alumni grouped by their current employment condition.']);
        data.push(['']);
    }
    // Mobility
    if (window.mobilityChart && window.mobilityChart.data && Array.isArray(window.mobilityChart.data.labels)) {
        data.push(['Mobility']);
        data.push(['Type', 'Count']);
        const chart = window.mobilityChart;
        chart.data.labels.forEach((label, i) => {
            data.push([label, chart.data.datasets[0].data[i]]);
        });
        data.push(['__CHART_IMAGE__', 'mobility']);
        // Description for Mobility
    data.push(['This chart shows whether alumni are employed locally or abroad.']);
        data.push(['']);
    }
    // Company Type
    if (window.companyChart && window.companyChart.data && Array.isArray(window.companyChart.data.labels)) {
        data.push(['Company Type']);
        data.push(['Type', 'Count']);
        const chart = window.companyChart;
        chart.data.labels.forEach((label, i) => {
            data.push([label, chart.data.datasets[0].data[i]]);
        });
        data.push(['__CHART_IMAGE__', 'company']);
        // Description for Company Type
    data.push(['This chart shows alumni employment across various company types.']);
        data.push(['']);
    }
    // Job Status
    if (window.jobStatusChart && window.jobStatusChart.data && Array.isArray(window.jobStatusChart.data.labels)) {
        data.push(['Job Status']);
        data.push(['Status', 'Count']);
        const chart = window.jobStatusChart;
        chart.data.labels.forEach((label, i) => {
            data.push([label, chart.data.datasets[0].data[i]]);
        });
        data.push(['__CHART_IMAGE__', 'jobStatus']);
        // Description for Job Status
    data.push(['This chart shows the distribution of employment types (Permanent, Temporary, Contractual, etc.).']);
        data.push(['']);
    }
    return data;
}

function handleExport(metrics, charts, pdf, excel, word, csv) {
    const exportData = [];
    if (metrics) exportData.push(...getMetricsData());
    if (charts) exportData.push(...getChartData());
    const titleSuffix = getFilterTitleSuffix();
    const title = 'Employment Analytics Export' + (titleSuffix ? ' — ' + titleSuffix : '');
    const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
    const filenameSuffix = getFilterFilenameSuffix();
    const baseFilename = `employment_export_${timestamp}${filenameSuffix ? '_' + filenameSuffix : ''}`;

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
        chartImages['industry'] = toImg(window.industryChart, 'industryChart');
        chartImages['status'] = toImg(window.statusChart, 'statusChart');
        chartImages['mobility'] = toImg(window.mobilityChart, 'mobilityChart');
        chartImages['company'] = toImg(window.companyChart, 'companyChart');
        chartImages['jobStatus'] = toImg(window.jobStatusChart, 'jobStatusChart');
    }

    if (pdf && window.ExportLibraries) {
        ExportLibraries.exportToPDF(exportData, `${baseFilename}.pdf`, { chartImages }, title);
    }
    if (excel && window.ExportLibraries) {
        ExportLibraries.exportToExcel(exportData, `${baseFilename}.xlsx`);
    }
    // Note: Word export is handled server-side via ?export=word, so we don't call ExportLibraries.exportToWord here
    if (csv && window.ExportLibraries) {
        ExportLibraries.exportToCSV(exportData, `${baseFilename}.csv`);
    }
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

// Modal and form logic
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
                const form = document.getElementById('filterForm');
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                const url = 'employment.php?' + params.toString() +
                    '&export=word' +
                    '&metrics=' + (metrics ? '1' : '0') +
                    '&charts=' + (charts ? '1' : '0');
                window.location.href = url;
                return;
            }
            handleExport(metrics, charts, pdf, excel, false, csv);
            var modal = getExportModalInstance();
            if (modal) modal.hide();
            cleanupModalArtifacts();
        });
    }
});

// ...existing code for SortableJS initialization...
</script>
