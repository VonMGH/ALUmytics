<?php
// --- Main Logic ---
include '../db/Database.php';
include '../db/LocationCoordinates.php'; // Include new coordinate system
include 'includes/access_control.php'; // Needed for filter builders
$conn = Database::getInstance()->getConnection();

// Optional PHPWord support for generating real .docx dashboard reports with PLSP header
$phpWordAvailable = false;
$phpWordAutoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($phpWordAutoload && file_exists($phpWordAutoload)) {
    require_once $phpWordAutoload;
    if (class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        $phpWordAvailable = true;
    }
}

// Handle server-side Word export for dashboard
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    $includeMetrics = isset($_GET['metrics']) && $_GET['metrics'] === '1';
    $includeCharts  = isset($_GET['charts']) && $_GET['charts'] === '1';

    // Rebuild the same filters used by the dashboard
    $filter_school = $_GET['school-university'] ?? '';
    $filter_campus = $_GET['campus-branch'] ?? '';
    $filter_college = $_GET['college-department'] ?? '';
    $filter_major = (isset($_GET['major-specialization']) && $_GET['major-specialization'] !== 'all') ? $_GET['major-specialization'] : '';
    $filter_year = (isset($_GET['year']) && $_GET['year'] !== 'all') ? $_GET['year'] : '';
    $employment_by = $_GET['employment_by'] ?? 'industry';

    $filters = [
        'school' => $filter_school,
        'campus' => $filter_campus,
        'college' => $filter_college,
        'major' => $filter_major,
        'year' => $filter_year
    ];

    $eduWhere = buildFilterWhereConditions($conn, $filters, 'e');
    if ($filter_year !== '') {
        $eduWhere[] = "e.year_graduated = '" . $conn->real_escape_string($filter_year) . "'";
    }

    // Use the same helpers as the dashboard
    $metrics    = getMetrics($conn, $eduWhere);
    $chartData  = getChartData($conn, $eduWhere, $filter_year, $employment_by);

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
                'ALUMytics Dashboard Report',
                ['bold' => true, 'size' => 16],
                ['spaceBefore' => 160, 'spaceAfter' => 240]
            );

            $section->addText(
                'Generated: ' . date('Y-m-d H:i:s'),
                ['size' => 11],
                ['spaceAfter' => 240]
            );

            // Metrics table
            if ($includeMetrics && is_array($metrics)) {
                $section->addText(
                    'Key Metrics',
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

                $metricRows = [];
                if (isset($metrics['alumniUsers'])) {
                    $metricRows[] = ['Alumni Users', $metrics['alumniUsers']];
                }
                if (isset($metrics['employeeRate'])) {
                    $metricRows[] = ['Employee Rate', $metrics['employeeRate']];
                }
                if (isset($metrics['activeUsers'])) {
                    $metricRows[] = ['Active Users (30 days)', $metrics['activeUsers']];
                }
                if (isset($metrics['avgSalary'])) {
                    $metricRows[] = ['Average Salary', $metrics['avgSalary']];
                }

                foreach ($metricRows as $mr) {
                    $metricsTable->addRow();
                    $metricsTable->addCell()->addText($mr[0]);
                    $metricsTable->addCell()->addText((string)$mr[1]);
                }
            }

            // Employment breakdown table
            if ($includeCharts && is_array($chartData)) {
                $labels = $chartData['employmentLabels'] ?? [];
                $counts = $chartData['employmentCounts'] ?? [];
                if ($labels && $counts && count($labels) === count($counts)) {
                    $section->addText(
                        'Employment by ' . strtoupper($employment_by),
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
                    $empTable->addCell(5000, ['bgColor' => '219653'])->addText('Category', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                    $empTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                    foreach ($labels as $idx => $label) {
                        $count = $counts[$idx] ?? 0;
                        $empTable->addRow();
                        $empTable->addCell()->addText((string)$label);
                        $empTable->addCell()->addText((string)$count);
                    }
                }

                // Geographic distribution table
                $locLabels = $chartData['locationLabels'] ?? [];
                $locCounts = $chartData['locationCounts'] ?? [];
                if ($locLabels && $locCounts && count($locLabels) === count($locCounts)) {
                    $section->addText(
                        'Alumni Location Distribution',
                        ['bold' => true, 'size' => 12],
                        ['spaceBefore' => 240, 'spaceAfter' => 120]
                    );

                    $locTable = $section->addTable([
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'cellMargin' => 50,
                        'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
                    ]);

                    $locTable->addRow();
                    $locTable->addCell(5000, ['bgColor' => '219653'])->addText('Location', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
                    $locTable->addCell(3000, ['bgColor' => '219653'])->addText('Count', ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);

                    foreach ($locLabels as $idx => $loc) {
                        $count = $locCounts[$idx] ?? 0;
                        $locTable->addRow();
                        $locTable->addCell()->addText((string)$loc);
                        $locTable->addCell()->addText((string)$count);
                    }
                }
            }

            $section->addText(
                'Generated by ALUMytics',
                ['size' => 10],
                ['spaceBefore' => 240]
            );

            $fileName = 'dashboard_' . date('Ymd_His') . '.docx';

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

    // Fallback: original plain-text .doc export
    $content  = "ALUMytics Dashboard Report\n";
    $content .= str_repeat('=', 50) . "\n\n";
    $content .= 'Generated: ' . date('Y-m-d H:i:s') . "\n\n";

    if ($includeMetrics && is_array($metrics)) {
        $content .= "METRICS\n";
        $content .= str_repeat('-', 50) . "\n";
        if (isset($metrics['alumniUsers'])) {
            $content .= 'Alumni Users: ' . $metrics['alumniUsers'] . "\n";
        }
        if (isset($metrics['employeeRate'])) {
            $content .= 'Employee Rate: ' . $metrics['employeeRate'] . "\n";
        }
        if (isset($metrics['activeUsers'])) {
            $content .= 'Active Users (30 days): ' . $metrics['activeUsers'] . "\n";
        }
        if (isset($metrics['avgSalary'])) {
            $content .= 'Average Salary: ' . $metrics['avgSalary'] . "\n";
        }
        $content .= "\n";
    }

    if ($includeCharts && is_array($chartData)) {
        // Employment breakdown
        $content .= "EMPLOYMENT BY " . strtoupper($employment_by) . "\n";
        $content .= str_repeat('-', 50) . "\n";
        $labels = $chartData['employmentLabels'] ?? [];
        $counts = $chartData['employmentCounts'] ?? [];
        if ($labels && $counts && count($labels) === count($counts)) {
            $content .= str_pad('Category', 30) . "Count\n";
            $content .= str_repeat('-', 30) . str_repeat('-', 10) . "\n";
            foreach ($labels as $idx => $label) {
                $count = $counts[$idx] ?? 0;
                $content .= str_pad((string)$label, 30) . $count . "\n";
            }
        } else {
            $content .= "No employment breakdown data available for the selected filters.\n";
        }
        $content .= "\n";

        // Geographic distribution table (heatmap data)
        $locLabels = $chartData['locationLabels'] ?? [];
        $locCounts = $chartData['locationCounts'] ?? [];
        if ($locLabels && $locCounts && count($locLabels) === count($locCounts)) {
            $content .= "ALUMNI LOCATION DISTRIBUTION\n";
            $content .= str_repeat('-', 50) . "\n";
            $content .= str_pad('Location', 30) . "Count\n";
            $content .= str_repeat('-', 30) . str_repeat('-', 10) . "\n";
            foreach ($locLabels as $idx => $loc) {
                $count = $locCounts[$idx] ?? 0;
                $content .= str_pad((string)$loc, 30) . $count . "\n";
            }
            $content .= "\n";
        }
    }

    $content .= str_repeat('=', 50) . "\n";
    $content .= "Generated by ALUMytics\n";

    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="dashboard_' . date('Ymd_His') . '.doc"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . strlen($content));

    echo $content;
    exit;
}

include 'includes/header.php';
include 'includes/sidebar.php';

// Load PSGC provinces once (code -> name) with short timeout, cached per request
function getPSGCProvinceMap() {
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

// PSGC province code -> name resolver (uses cached map)
function resolveProvinceName($value) {
    $val = trim((string)$value);
    if ($val === '' ) return $val;
    if (!preg_match('/^\d+$/', $val)) return $val;
    $map = getPSGCProvinceMap();
    return $map[$val] ?? $val;
}

// Helper function for metrics calculation
function getMetrics($conn, $eduWhere) {
    global $userPermissions;
    
    $eduWhereSql = $eduWhere ? 'WHERE ' . implode(' AND ', $eduWhere) : '';
    $join = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : '';
    $where = $eduWhere;

    // Alumni Users
    $alumniUsers = $conn->query("SELECT COUNT(DISTINCT e.user_id) as total FROM education e $eduWhereSql")->fetch_assoc()['total'];
    
    // Alumni Users Last Week (only for comparison purposes)
    $alumniWhere = $eduWhere;
    $alumniWhere[] = "YEARWEEK(users.created_at, 1) = YEARWEEK(DATE_SUB(NOW(), INTERVAL 1 WEEK), 1)";
    $alumniWhereSql = $alumniWhere ? 'WHERE ' . implode(' AND ', $alumniWhere) : '';
    $alumniUsersLastWeek = $conn->query(
        "SELECT COUNT(DISTINCT e.user_id) as total FROM education e JOIN users ON users.user_id = e.user_id $alumniWhereSql"
    )->fetch_assoc()['total'];
    $alumniUsersChange = ($alumniUsersLastWeek && $alumniUsers) ? round((($alumniUsers - $alumniUsersLastWeek) / $alumniUsersLastWeek) * 100) : 0;

    // Employee Rate
    $empWhere = $where;
    $empWhere[] = "employment_status = 'employed'";
    $empWhereSql = $empWhere ? 'WHERE ' . implode(' AND ', $empWhere) : '';
    $employeeRate = $conn->query("SELECT COUNT(DISTINCT employment.user_id) as employed FROM employment $join $empWhereSql")->fetch_assoc()['employed'];
    
    // Employee Rate Last Month
    $empWhereLastMonth = $empWhere;
    $empWhereLastMonth[] = "MONTH(employment.year_of_employment) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))";
    $empWhereLastMonthSql = $empWhereLastMonth ? 'WHERE ' . implode(' AND ', $empWhereLastMonth) : '';
    $employeeRateLastMonth = $conn->query("SELECT COUNT(DISTINCT employment.user_id) as employed FROM employment $join $empWhereLastMonthSql")->fetch_assoc()['employed'];
    $employeeRateChange = ($employeeRateLastMonth && $employeeRate) ? round((($employeeRate - $employeeRateLastMonth) / $employeeRateLastMonth) * 100) : 0;

    // Active Users (only show if user can view user logs)
    $activeUsers = 0;
    $activeUsersChange = 0;
    if ($userPermissions['can_view_user_logs']) {
        $activeJoin = $eduWhere ? "JOIN education e ON e.user_id = login_logs.user_id" : '';
        $activeWhere = $eduWhere;
        $activeWhere[] = "login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $activeWhereSql = $activeWhere ? 'WHERE ' . implode(' AND ', $activeWhere) : '';
        $activeUsers = $conn->query("SELECT COUNT(DISTINCT login_logs.user_id) as active FROM login_logs $activeJoin $activeWhereSql")->fetch_assoc()['active'];
        
        // Active Users Last Day
        $activeWhereLastDay = $activeWhere;
        $activeWhereLastDay[] = "login_time >= DATE_SUB(NOW(), INTERVAL 2 DAY) AND login_time < DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $activeWhereLastDaySql = $activeWhereLastDay ? 'WHERE ' . implode(' AND ', $activeWhereLastDay) : '';
        $activeUsersLastDay = $conn->query("SELECT COUNT(DISTINCT login_logs.user_id) as active FROM login_logs $activeJoin $activeWhereLastDaySql")->fetch_assoc()['active'];
        $activeUsersChange = ($activeUsersLastDay && $activeUsers) ? round((($activeUsers - $activeUsersLastDay) / $activeUsersLastDay) * 100) : 0;
    }

    // Avg. Salary
    $salaryJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : '';
    $salaryWhere = $eduWhere;
    $salaryWhere[] = "salary_per_month IS NOT NULL";
    $salaryWhereSql = $salaryWhere ? 'WHERE ' . implode(' AND ', $salaryWhere) : '';
    $avgSalaryRow = $conn->query("SELECT AVG(salary_per_month) as avg_salary FROM employment $salaryJoin $salaryWhereSql")->fetch_assoc();
    $avgSalary = $avgSalaryRow['avg_salary'] ? number_format($avgSalaryRow['avg_salary'], 2) : '0.00';
    
    // Avg. Salary Last Day (simplified calculation)
    $avgSalaryChange = 0; // Simplified for role-based system

    return compact('alumniUsers', 'alumniUsersChange', 'employeeRate', 'employeeRateChange', 'activeUsers', 'activeUsersChange', 'avgSalary', 'avgSalaryChange');
}

// Helper function for chart data
function getChartData($conn, $eduWhere, $filter_year, $employment_by) {
    $chartJoin = $eduWhere ? "JOIN education e ON e.user_id = employment.user_id" : '';
    $chartWhere = $eduWhere;
    if ($filter_year) {
        // Filter charts by graduation year
        $chartWhere[] = "e.year_graduated = '" . $conn->real_escape_string($filter_year) . "'";
    }
    $chartWhereSql = $chartWhere ? 'WHERE ' . implode(' AND ', $chartWhere) : '';
    
    $employmentByField = [
        'industry' => 'industry',
        'age_group' => 'CASE WHEN YEAR(CURDATE())-YEAR(p.dob) < 25 THEN "Under 25" WHEN YEAR(CURDATE())-YEAR(p.dob) BETWEEN 25 AND 34 THEN "25-34" WHEN YEAR(CURDATE())-YEAR(p.dob) BETWEEN 35 AND 44 THEN "35-44" WHEN YEAR(CURDATE())-YEAR(p.dob) BETWEEN 45 AND 54 THEN "45-54" ELSE "55+" END',
        'gender' => 'p.sex',
        'program' => 'e.program',
        'civil_status' => 'p.civil_status',
        'graduation_year' => 'e.year_graduated'
    ];
    
    $groupField = isset($employmentByField[$employment_by]) ? $employmentByField[$employment_by] : $employmentByField['industry'];
    $extraJoin = '';
    if (in_array($employment_by, ['age_group', 'gender', 'civil_status'])) {
        $extraJoin = 'JOIN personal p ON p.user_id = employment.user_id';
    }
    if ($employment_by === 'graduation_year' || $employment_by === 'program') {
        $chartJoin = 'JOIN education e ON e.user_id = employment.user_id';
    }
    
    // For breakdowns tied directly to alumni attributes (gender, age_group, civil_status, graduation_year, program),
    // count distinct users per group; for pure employment breakdowns (e.g., industry), count employment rows.
    if (in_array($employment_by, ['gender', 'age_group', 'civil_status', 'graduation_year', 'program'], true)) {
        $employmentDataSql = "SELECT $groupField AS group_label, COUNT(DISTINCT employment.user_id) AS count FROM employment $chartJoin $extraJoin $chartWhereSql GROUP BY group_label ORDER BY count DESC";
    } else {
        $employmentDataSql = "SELECT $groupField AS group_label, COUNT(*) AS count FROM employment $chartJoin $extraJoin $chartWhereSql GROUP BY group_label ORDER BY count DESC";
    }
    $employmentData = $conn->query($employmentDataSql);
    $employmentLabels = [];
    $employmentCounts = [];
    if ($employmentData && $employmentData->num_rows > 0) {
        while($row = $employmentData->fetch_assoc()) {
            $employmentLabels[] = $row['group_label'] ? htmlspecialchars($row['group_label']) : 'UNEMPLOYED';
            $employmentCounts[] = (int)$row['count'];
        }
    }
    
    // Industry data
    $industryData = $conn->query("SELECT industry, COUNT(*) as count FROM employment $chartJoin $chartWhereSql GROUP BY industry");
    $industryLabels = [];
    $industryCounts = [];
    if ($industryData && $industryData->num_rows > 0) {
        while($row = $industryData->fetch_assoc()) {
            $industryLabels[] = htmlspecialchars($row['industry']);
            $industryCounts[] = (int)$row['count'];
        }
    }
    
    // Geographic Distribution (improved version) - Group by Province and distinguish current vs past jobs
    $geoJoin = $eduWhere ? "JOIN education e ON emp.user_id = e.user_id" : '';
    $geoWhere = $eduWhere;
    if ($filter_year) {
        // Filter map by graduation year
        $geoWhere[] = "e.year_graduated = '" . $conn->real_escape_string($filter_year) . "'";
    }
    $geoWhereSql = $geoWhere ? 'WHERE ' . implode(' AND ', $geoWhere) : '';

    $geoDistribution = $conn->query("SELECT 
        CASE 
            WHEN emp.mobility = 'international' THEN emp.company_country
            ELSE COALESCE(emp.company_province, ca.company_province)
        END AS location_key,
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

    $locationLabels = [];
    $locationCodes = [];
    $locationCounts = [];
    $currentHeatmapPoints = [];
    $pastHeatmapPoints = [];
    $locationTotals = [];

    if ($geoDistribution && $geoDistribution->num_rows > 0) {
        while($row = $geoDistribution->fetch_assoc()) {
            $key = $row['location_key'] ?: 'Unknown';
            $isInternational = ($row['mobility'] === 'international');
            $displayName = $isInternational ? $key : resolveProvinceName($key);
            $count = (int)$row['count'];
            $jobState = $row['job_state'] === 'past' ? 'past' : 'current';

            // Track totals per location (current + past) for the sidebar table
            if (!isset($locationTotals[$displayName])) {
                $locationTotals[$displayName] = 0;
            }
            $locationTotals[$displayName] += $count;

            $coordinates = LocationCoordinates::getCoordinates(null, $displayName);
            $point = [$coordinates[0], $coordinates[1], $count];
            if ($jobState === 'past') {
                $pastHeatmapPoints[] = $point;
            } else {
                $currentHeatmapPoints[] = $point;
            }
        }

        foreach ($locationTotals as $name => $totalCount) {
            $locationLabels[] = htmlspecialchars($name);
            $locationCodes[] = htmlspecialchars($name);
            $locationCounts[] = $totalCount;
        }
    }
    
    return compact('employmentLabels', 'employmentCounts', 'industryLabels', 'industryCounts', 'locationLabels', 'locationCodes', 'locationCounts', 'currentHeatmapPoints', 'pastHeatmapPoints');
}

// Helper function for recent activities
function getRecentActivities($conn, $limit = 10) {
    global $userPermissions, $eduWhere;
    
    if (!$userPermissions['can_view_user_logs']) {
        return [];
    }
    
    $activities = [];
    // Build education-based restriction reused across subqueries
    $eduFilter = '';
    if (!empty($eduWhere)) {
        $eduFilter = ' AND ' . implode(' AND ', $eduWhere);
    }
    
    $sql = "(
        SELECT users.full_name, login_logs.login_time AS activity_time, 'login' AS activity_type, NULL AS extra
        FROM login_logs
        JOIN users ON users.user_id = login_logs.user_id
        " . (!empty($eduWhere) ? "JOIN education e ON e.user_id = login_logs.user_id" : "") . "
        WHERE login_logs.login_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)" . $eduFilter . "
    ) UNION ALL (
        SELECT users.full_name, certifications.created_at AS activity_time, 'certification' AS activity_type, certifications.certification_name AS extra
        FROM certifications
        JOIN users ON users.user_id = certifications.user_id
        " . (!empty($eduWhere) ? "JOIN education e ON e.user_id = certifications.user_id" : "") . "
        WHERE certifications.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)" . $eduFilter . "
    ) UNION ALL (
        SELECT users.full_name, awards.created_at AS activity_time, 'award' AS activity_type, awards.award_title AS extra
        FROM awards
        JOIN users ON users.user_id = awards.user_id
        " . (!empty($eduWhere) ? "JOIN education e ON e.user_id = awards.user_id" : "") . "
        WHERE awards.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)" . $eduFilter . "
    ) ORDER BY activity_time DESC LIMIT $limit";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $desc = '';
        if ($row['activity_type'] === 'login') $desc = 'Logged in';
        elseif ($row['activity_type'] === 'certification') $desc = 'Added new certification: ' . $row['extra'];
        elseif ($row['activity_type'] === 'award') $desc = 'Received award: ' . $row['extra'];
        $activities[] = [
            'user' => $row['full_name'],
            'type' => $row['activity_type'],
            'time' => $row['activity_time'],
            'desc' => $desc
        ];
    }
    return $activities;
}

// Helper function for recently updated profiles
function getRecentlyUpdatedProfiles($conn, $limit = 10) {
    global $eduWhere;
    $profiles = [];
    $eduFilter = '';
    if (!empty($eduWhere)) {
        $eduFilter = ' AND ' . implode(' AND ', $eduWhere);
    }
    $sql = "(
        SELECT users.full_name, certifications.created_at AS activity_time, 'certification' AS update_type
        FROM certifications
        JOIN users ON users.user_id = certifications.user_id
        " . (!empty($eduWhere) ? "JOIN education e ON e.user_id = certifications.user_id" : "") . "
        WHERE certifications.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" . $eduFilter . "
    ) UNION ALL (
        SELECT users.full_name, awards.created_at AS activity_time, 'award' AS update_type
        FROM awards
        JOIN users ON users.user_id = awards.user_id
        " . (!empty($eduWhere) ? "JOIN education e ON e.user_id = awards.user_id" : "") . "
        WHERE awards.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" . $eduFilter . "
    ) UNION ALL (
        SELECT users.full_name, users.created_at AS activity_time, 'profile' AS update_type
        FROM users
        " . (!empty($eduWhere) ? "JOIN education e ON e.user_id = users.user_id" : "") . "
        WHERE users.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND users.role = 'alumni'" . $eduFilter . "
    ) ORDER BY activity_time DESC LIMIT $limit";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $desc = '';
            if ($row['update_type'] === 'certification') $desc = 'Added new certification';
            elseif ($row['update_type'] === 'award') $desc = 'Received new award';
            elseif ($row['update_type'] === 'profile') $desc = 'Created new profile';
            $profiles[] = [
                'user' => $row['full_name'],
                'type' => $row['update_type'],
                'time' => $row['activity_time'],
                'desc' => $desc
            ];
        }
    }
    return $profiles;
}

// Check for unauthorized access
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    echo '<div class="alert alert-danger m-4"><i class="fas fa-exclamation-triangle"></i> You do not have permission to access that module.</div>';
}

// Get filter values from GET
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
$employment_by = $_GET['employment_by'] ?? 'industry';

// Build filters based on role permissions
$filters = [
    'school' => $filter_school,
    'campus' => $filter_campus,
    'college' => $filter_college,
    'major' => $filter_major,
    'year' => $filter_year
];

$eduWhere = buildFilterWhereConditions($conn, $filters, 'e');
$__localEduWhereYearApplied = false;
if ($filter_year !== '') {
    $eduWhere[] = "e.year_graduated = '" . $conn->real_escape_string($filter_year) . "'";
    $__localEduWhereYearApplied = true;
}
$filterOptions = getFilterOptions($conn);
$metrics = getMetrics($conn, $eduWhere);
$chartData = getChartData($conn, $eduWhere, $filter_year, $employment_by);
$recentActivities = getRecentActivities($conn);
$recentlyUpdatedProfiles = getRecentlyUpdatedProfiles($conn);

$role ??= null;
$college_name ??= null;

// Export logic and unused functions removed for cleanup. Ready for refactor.
?>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div id="dashboard" class="tab-content active">
        <div class="header dashboard-header">
            <div class="header-top d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0 dashboard-title"><?= getRoleDisplayName($role) ?> Dashboard</h1>
                    <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                        <p class="dashboard-subtitle mb-0">Viewing data for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                    <?php else: ?>
                        <p class="dashboard-subtitle mb-0">Overview of alumni metrics, employment trends, and recent activity.</p>
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
                    <select id="school-university" name="school-university">
                        <option value="" <?= ($filter_school == '') ? 'selected' : '' ?>>All</option>
                        <?php while($row = $filterOptions['schools']->fetch_assoc()): ?>
                            <?php
                                $value = htmlspecialchars($row['school_university']);
                                $selected = ($filter_school === $row['school_university']) ? 'selected' : '';
                            ?>
                            <option value="<?= $value ?>" <?= $selected ?>><?= $value ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('campus', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="campus-branch">Campus/Branch</label>
                    <select id="campus-branch" name="campus-branch" <?= (isCollegeRestricted()) ? 'disabled' : '' ?>>
                        <?php if (isCollegeRestricted()): ?>
                            <?php while($row = $filterOptions['campuses']->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['campus_branch']) ?>" selected><?= htmlspecialchars($row['campus_branch']) ?></option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" <?= ($filter_campus == '') ? 'selected' : '' ?>>All</option>
                            <?php while($row = $filterOptions['campuses']->fetch_assoc()): ?>
                                <?php
                                    $value = htmlspecialchars($row['campus_branch']);
                                    $selected = ($filter_campus === $row['campus_branch']) ? 'selected' : '';
                                ?>
                                <option value="<?= $value ?>" <?= $selected ?>><?= $value ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('college', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="college-department">College/Department</label>
                    <select id="college-department" name="college-department">
                        <option value="" <?= ($filter_college == '') ? 'selected' : '' ?>>All</option>
                        <?php while($row = $filterOptions['colleges']->fetch_assoc()): ?>
                            <?php
                                $value = htmlspecialchars($row['college_department']);
                                $selected = ($filter_college === $row['college_department']) ? 'selected' : '';
                            ?>
                            <option value="<?= $value ?>" <?= $selected ?>><?= $value ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('major', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="major-specialization">Program</label>
                    <select id="major-specialization" name="major-specialization">
                        <option value="all" <?= ($filter_major == '') ? 'selected' : '' ?>>All</option>
                        <?php while($row = $filterOptions['majors']->fetch_assoc()): ?>
                            <?php
                                $value = htmlspecialchars($row['program']);
                                $selected = ($filter_major === $row['program']) ? 'selected' : '';
                            ?>
                            <option value="<?= $value ?>" <?= $selected ?>><?= $value ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('year', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="year">Graduation Year</label>
                    <select id="year" name="year">
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
                
                <div class="filter-dropdown">
                    <label for="employment_by">Employment by:</label>
                    <select id="employment_by" name="employment_by">
                        <option value="industry" <?= ($employment_by == 'industry') ? 'selected' : '' ?>>Industry</option>
                        <option value="age_group" <?= ($employment_by == 'age_group') ? 'selected' : '' ?>>Age Group</option>
                        <option value="gender" <?= ($employment_by == 'gender') ? 'selected' : '' ?>>Gender</option>
                        <option value="program" <?= ($employment_by == 'program') ? 'selected' : '' ?>>Program</option>
                        <option value="civil_status" <?= ($employment_by == 'civil_status') ? 'selected' : '' ?>>Civil Status</option>
                        <option value="graduation_year" <?= ($employment_by == 'graduation_year') ? 'selected' : '' ?>>Graduation Year</option>
                    </select>
                </div>
            </div>
            </div>
        </form>
        </div>
        <script>
        (function(){
          try {
            document.querySelectorAll('.filter-controls select').forEach(function(el){
              el.addEventListener('change', function(){
                if (el && el.form) { el.form.submit(); }
              });
            });
          } catch(e) {}
        })();
        </script>

        <div class="metrics-container">
            <div class="metric-card">
                <h3>Alumni Users</h3>
                <div class="metric-value"><?php echo $metrics['alumniUsers']; ?></div>
                <div class="metric-change <?php echo $metrics['alumniUsersChange'] >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $metrics['alumniUsersChange'] >= 0 ? 'up' : 'down'; ?>"></i> <?php echo ($metrics['alumniUsersChange'] >= 0 ? '+' : '') . $metrics['alumniUsersChange']; ?>% than last week
                </div>
                <div class="icon-container icon-money">
                    <i class="fas fa-users"></i>
                </div>
            </div>

            <div class="metric-card">
                <h3>Employee Rate</h3>
                <div class="metric-value"><?php echo $metrics['employeeRate']; ?></div>
                <div class="metric-change <?php echo $metrics['employeeRateChange'] >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $metrics['employeeRateChange'] >= 0 ? 'up' : 'down'; ?>"></i> <?php echo ($metrics['employeeRateChange'] >= 0 ? '+' : '') . $metrics['employeeRateChange']; ?>% than last month
                </div>
                <div class="icon-container icon-users">
                    <i class="fas fa-briefcase"></i>
                </div>
            </div>

            <?php if (hasPermission('can_view_user_logs')): ?>
            <div class="metric-card">
                <h3>Active Users</h3>
                <div class="metric-value"><?php echo $metrics['activeUsers']; ?></div>
                <div class="metric-change <?php echo $metrics['activeUsersChange'] >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $metrics['activeUsersChange'] >= 0 ? 'up' : 'down'; ?>"></i> <?php echo ($metrics['activeUsersChange'] >= 0 ? '+' : '') . $metrics['activeUsersChange']; ?>% than yesterday
                </div>
                <div class="icon-container icon-views">
                    <i class="far fa-clock"></i>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="row g-4" id="sortable-charts">
            <div class="col-lg-6 sortable-chart-card">
                <div class="card h-100 shadow-sm dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">Employment by
                                <span id="employmentByLabel">
                                    <?php
                                    $labels = [
                                        'industry' => 'Industry',
                                        'age_group' => 'Age Group',
                                        'gender' => 'Gender',
                                        'program' => 'Program',
                                        'civil_status' => 'Civil Status',
                                        'graduation_year' => 'Graduation Year',
                                    ];
                                    echo isset($labels[$employment_by]) ? $labels[$employment_by] : 'Industry';
                                    ?>
                                </span>
                            </h5>
                            <small class="text-muted">Number of Alumni grouped by selected category.</small>
                        </div>
                    </div>
                    <div class="card-body chart-body">
                        <canvas id="employmentByChart"></canvas>
                        <script id="employmentByChartData" type="application/json">
                            <?php echo json_encode([
                                'labels' => $chartData['employmentLabels'],
                                'counts' => $chartData['employmentCounts'],
                                'chartType' => in_array($employment_by, ['industry', 'age_group', 'program']) ? 'bar' : ($employment_by === 'graduation_year' ? 'line' : 'pie'),
                            ]); ?>
                        </script>
                        <?php if (empty($chartData['employmentLabels'])): ?>

                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted">
                        <i class="far fa-clock"></i> Just Updated.
                    </div>
                </div>
            </div>
            <div class="col-lg-6 sortable-chart-card">
                <div class="card h-100 shadow-sm dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">Alumni Location Heatmap</h5>
                            <small class="text-muted">Geographic distribution of current and past employment</small>
                        </div>
                    </div>
                    <div class="card-body heatmap-body">
                        <div id="alumniHeatmap"></div>
                        <script id="locationHeatmapPoints" type="application/json">
                            <?php echo json_encode([
                                'current' => $chartData['currentHeatmapPoints'] ?? [],
                                'past'    => $chartData['pastHeatmapPoints'] ?? []
                            ]); ?>
                        </script>
                        <?php
                        $hasHeatData = !empty($chartData['currentHeatmapPoints']) || !empty($chartData['pastHeatmapPoints']);
                        if (!$hasHeatData): ?>
                            <div class="empty-state">
                                <i class="fas fa-map-marked-alt fa-2x"></i>
                                <p>No location data available for the selected filters.</p>
                            </div>
                        <?php else: ?>
                        <table class="table table-sm location-table">
                            <thead><tr><th>Province/Location</th><th>Count</th></tr></thead>
                            <tbody>
                            <?php foreach ($chartData['locationLabels'] as $i => $locationName): ?>
                                <tr><td><?= htmlspecialchars($locationName) ?></td><td><?= $chartData['locationCounts'][$i] ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted">
                        <i class="far fa-clock"></i> Just updated.
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">Just Updated</h5>
                            <small class="text-muted"><?= count($recentlyUpdatedProfiles) ?> profiles recently updated</small>
                        </div>
                    </div>
                    <div class="card-body activity-body">
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($recentlyUpdatedProfiles as $profile): ?>
                                <li class="activity-item">
                                    <div class="activity-icon icon-profile">
                                        <i class="fas fa-user-edit"></i>
                                    </div>
                                    <div class="activity-content flex-grow-1">
                                        <strong><?= htmlspecialchars($profile['user']) ?></strong>
                                        <p class="mb-0"><?= htmlspecialchars($profile['desc']) ?></p>
                                        <span class="activity-time"><?= date('M d, Y H:i', strtotime($profile['time'])) ?></span>
                                    </div>
                                    <span class="activity-badge dash-badge">Updated</span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($recentlyUpdatedProfiles)): ?>
                                <li class="empty-state">
                                    <i class="fas fa-user-edit fa-2x"></i>
                                    <p>No recent profile updates</p>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="card-footer text-muted">
                        <i class="far fa-clock"></i> Last 7 days
                    </div>
                </div>
            </div>
            <?php if (hasPermission('can_view_user_logs')): ?>
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">Recent User Activities</h5>
                            <small class="text-muted"><?= count($recentActivities) ?> recent activities</small>
                        </div>
                    </div>
                    <div class="card-body activity-body">
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($recentActivities as $activity): ?>
                                <?php
                                $iconClass = 'icon-login';
                                $faIcon = 'fa-sign-in-alt';
                                if ($activity['type'] === 'certification') {
                                    $iconClass = 'icon-cert';
                                    $faIcon = 'fa-certificate';
                                } elseif ($activity['type'] === 'award') {
                                    $iconClass = 'icon-award';
                                    $faIcon = 'fa-trophy';
                                }
                                ?>
                                <li class="activity-item">
                                    <div class="activity-icon <?= $iconClass ?>">
                                        <i class="fas <?= $faIcon ?>"></i>
                                    </div>
                                    <div class="activity-content flex-grow-1">
                                        <strong><?= htmlspecialchars($activity['user']) ?></strong>
                                        <p class="mb-0"><?= htmlspecialchars($activity['desc']) ?></p>
                                        <span class="activity-time"><?= date('M d, Y H:i', strtotime($activity['time'])) ?></span>
                                    </div>
                                    <span class="activity-badge dash-badge">
                                        <i class="fas <?= $faIcon ?>"></i>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($recentActivities)): ?>
                                <li class="empty-state">
                                    <i class="fas fa-history fa-2x"></i>
                                    <p>No recent activities</p>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="card-footer text-muted">
                        <i class="far fa-clock"></i> Last 24 hours
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exportModalLabel">Export Dashboard Data</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="exportForm">
          <div class="mb-2">Select data to export:</div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="metrics" id="exportMetrics" checked>
            <label class="form-check-label" for="exportMetrics">Metrics</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="charts" id="exportCharts" checked>
            <label class="form-check-label" for="exportCharts">Charts</label>
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
<script src="js/chart-utils.js?v=2"></script>
<script src="js/index.js?v=4"></script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<!-- Export Libraries dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="js/export-descriptions.js"></script>
<script src="js/export-libraries.js"></script>
<script>
function renderAlumniHeatmap() {
  if (!document.getElementById('dashboard')) return;
  var dataScript = document.getElementById('locationHeatmapPoints');
  var mapDiv = document.getElementById('alumniHeatmap');
  if (!dataScript || !mapDiv) return;
  var heatData = JSON.parse(dataScript.textContent || '{}');
  var currentPoints = heatData.current || [];
  var pastPoints = heatData.past || [];
  if (window.alumniHeatmapMap) {
    window.alumniHeatmapMap.remove();
  }
  if ((currentPoints && currentPoints.length > 0) || (pastPoints && pastPoints.length > 0)) {
    window.alumniHeatmapMap = L.map('alumniHeatmap').setView([14.5995, 120.9842], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(window.alumniHeatmapMap);
    
    // Add separate heatmap layers for current vs past jobs
    if (window.L && window.L.heatLayer) {
      if (currentPoints.length > 0) {
        L.heatLayer(currentPoints, {
          radius: 25,
          blur: 15,
          maxZoom: 10,
          gradient: {0.2: 'lime', 0.6: 'green', 1.0: 'darkgreen'}
        }).addTo(window.alumniHeatmapMap);
      }
      if (pastPoints.length > 0) {
        L.heatLayer(pastPoints, {
          radius: 25,
          blur: 15,
          maxZoom: 10,
          gradient: {0.2: 'orange', 0.6: 'red', 1.0: 'darkred'}
        }).addTo(window.alumniHeatmapMap);
      }

      // Add count markers (current = green, past = red)
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
          marker.addTo(window.alumniHeatmapMap);
        });
      }
      addMarkers(currentPoints, 'rgba(33,150,83,0.9)');   // green for current jobs
      addMarkers(pastPoints, 'rgba(211,47,47,0.9)');      // red for past jobs

      // Simple legend
      var legend = L.control({position: 'bottomright'});
      legend.onAdd = function () {
        var div = L.DomUtil.create('div', 'info legend');
        div.innerHTML = '<div style="background:rgba(33,150,83,0.9);width:12px;height:12px;display:inline-block;margin-right:6px;border-radius:2px;"></div>Current Job ' +
                        '<br><div style="background:rgba(211,47,47,0.9);width:12px;height:12px;display:inline-block;margin-right:6px;border-radius:2px;"></div>Past Job';
        return div;
      };
      legend.addTo(window.alumniHeatmapMap);
    }
  }
}
// Call on page load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', renderAlumniHeatmap);
} else {
  renderAlumniHeatmap();
}

// Load JavaScript export libraries
document.addEventListener('DOMContentLoaded', function() {
  console.debug('Export init: DOMContentLoaded fired');
  // Initialize export libraries
  if (typeof window.ExportLibraries === 'undefined') {
    console.log('Loading export libraries...');
    // Load the export libraries script if not already loaded
    const script = document.createElement('script');
    script.src = 'js/export-libraries.js';
    script.onload = function() {
      console.log('Export libraries loaded successfully');
    };
    document.head.appendChild(script);
  }

  function triggerDownload(url) {
    var iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);
    setTimeout(function() { document.body.removeChild(iframe); }, 2000);
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
    try {
      var modal = getExportModalInstance();
      if (modal) {
        modal.show();
        return;
      }
      var modalEl = document.getElementById('exportModal');
      if (modalEl && modalEl.classList) {
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
      } else {
        alert('Export modal not found on this page.');
      }
    } catch (e) {
      console.error('Failed to open export modal', e);
      alert('Unable to open export modal. Check console for details.');
    }
  }

  var exportButton = document.getElementById('exportButton');
  var exportForm = document.getElementById('exportForm');
  var exportModalEl = document.getElementById('exportModal');
  console.debug('Export init: exportButton element=', exportButton, 'exportForm element=', exportForm);
  
  if (exportModalEl) {
    exportModalEl.addEventListener('hidden.bs.modal', cleanupModalArtifacts);
  }

  if (exportButton) {
    exportButton.addEventListener('click', function() {
      openExportModal();
    });
  }

  window.openExportModal = openExportModal;
  
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

      // For Word exports, always use the server-side handler (like report-generation.php)
      if (word) {
        var wordUrl = '?export=word&metrics=' + (metrics ? '1' : '0') + '&charts=' + (charts ? '1' : '0');
        console.log('Redirecting to server-side Word export:', wordUrl);
        window.location.href = wordUrl;
        return;
      }

      // Use JavaScript libraries for client-side export (PDF / Excel / CSV)
      if (window.ExportLibraries) {
        // Debug libraries before export
        if (window.ExportLibraries.debugLibraries) {
          window.ExportLibraries.debugLibraries();
        }
        try {
          await exportDashboardData(metrics, charts, pdf, excel, false, csv);
        } catch (e) {
          console.error('Export failed', e);
        }
        // Close the modal after initiating export
        const modal = getExportModalInstance();
        if (modal) {
          modal.hide();
        }
        cleanupModalArtifacts();
      } else {
        // Fallback to server-side export (no client-side libraries)
        fallbackServerExport(metrics, charts, pdf, excel, false, csv);
      }
    });
  }

});
  // --- Helpers to capture filter context for export ---
  function getFilterSummaryRows() {
    const form = document.getElementById('filterForm');
    if (!form) return [];
    const fields = [
      { id: 'school-university', label: 'School/University' },
      { id: 'campus-branch', label: 'Campus/Branch' },
      { id: 'college-department', label: 'College/Department' },
      { id: 'major-specialization', label: 'Program' },
      { id: 'year', label: 'Year' },
      { id: 'employment_by', label: 'Employment by' }
    ];
    const rows = [];
    rows.push(['Applied Filters']);
    rows.push(['Filter', 'Value']);
    fields.forEach(f => {
      const el = form.querySelector(`#${f.id}`);
      if (!el) return;
      const text = el.options && el.selectedIndex >= 0 ? el.options[el.selectedIndex].text.trim() : (el.value || '').trim();
      rows.push([f.label, text || 'All']);
    });
    rows.push([]);
    return rows;
  }

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
    add('major-specialization', 'Program');
    add('year', 'Year');
    add('employment_by', 'Employment by');
    return parts.join('; ');
  }

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
    add('major-specialization', 'program');
    add('year', 'year');
    add('employment_by', 'by');
    return parts.join('_');
  }

  // Client-side export using reusable ExportLibraries component
  async function exportDashboardData(metrics, charts, pdf, excel, word, csv) {
    const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
    const suffix = getFilterFilenameSuffix();
    const filename = `dashboard_${timestamp}${suffix ? '_' + suffix : ''}`;
    try {
      const dashboardData = prepareDashboardData(metrics, charts);
      const exportPromises = [];
      if (csv) {
        if (ExportLibraries.exportToCSV) exportPromises.push(ExportLibraries.exportToCSV(dashboardData, filename + '.csv'));
      }
      if (excel) {
        if (ExportLibraries.exportToExcel) exportPromises.push(ExportLibraries.exportToExcel(dashboardData, filename + '.xlsx'));
      }
      if (word) {
        const titleSuffix = getFilterTitleSuffix();
        console.log('Exporting to Word with filename:', filename + '.docx');
        if (ExportLibraries.exportToWord) {
          exportPromises.push(Promise.resolve(ExportLibraries.exportToWord(
            dashboardData,
            filename + '.docx',
            {},
            'ALUMytics Dashboard Report' + (titleSuffix ? ' — ' + titleSuffix : '')
          )));
        } else {
          console.error('ExportLibraries.exportToWord not available');
        }
      }
      if (pdf) {
        // Enhanced PDF export: use new config and chartImages object
        let chartImages = {};
        if (charts) {
          // Helper to capture Chart.js instance image with canvas fallback
          const getChartImage = (canvasId, chartInstance) => {
            try {
              if (chartInstance && typeof chartInstance.toBase64Image === 'function') {
                return chartInstance.toBase64Image();
              }
              const canvas = document.getElementById(canvasId);
              return canvas ? canvas.toDataURL('image/png', 1.0) : null;
            } catch (e) {
              return null;
            }
          };

          // Employment by chart
          chartImages['employment'] = getChartImage('employmentByChart', window.employmentByChartInstance);
          // Add more charts here if needed in the future
        }
        // Use the new config system (choose 'standard', 'landscape', etc.)
        const config = ExportLibraries.createConfig('standard');
        const titleSuffix = getFilterTitleSuffix();
        if (ExportLibraries.exportToPDF) exportPromises.push(ExportLibraries.exportToPDF(
          dashboardData,
          filename + '.pdf',
          { chartImages, config },
          'ALUMytics Dashboard Report' + (titleSuffix ? ' — ' + titleSuffix : '')
        ));
      }
      
      // Await all export promises and then reload the page shortly after downloads start
      try {
        if (exportPromises.length) {
          await Promise.all(exportPromises);
          // small delay to allow browser to start downloads
          setTimeout(() => { try { window.location.reload(); } catch (e) { console.warn(e); } }, 1500);
        }
      } catch (err) {
        console.warn('One or more exports failed', err);
      }
    } catch (error) {
      console.error('Client-side export error:', error);
      alert('Error with client-side export. Falling back to server-side export.');
      fallbackServerExport(metrics, charts, pdf, excel, word, csv);
    }
  }

  // Fallback to server-side export
  function fallbackServerExport(metrics, charts, pdf, excel, word, csv) {
    if (csv) {
      var csvUrl = '?export=csv&metrics=' + (metrics ? '1' : '0') + '&charts=' + (charts ? '1' : '0');
      window.location.href = csvUrl;
    }
    if (excel) {
      var excelUrl = '?export=excel&metrics=' + (metrics ? '1' : '0') + '&charts=' + (charts ? '1' : '0');
      window.location.href = excelUrl;
    }
    if (word) {
      var wordUrl = '?export=word&metrics=' + (metrics ? '1' : '0') + '&charts=' + (charts ? '1' : '0');
      console.log('Triggering server-side Word export:', wordUrl);
      window.location.href = wordUrl;
    }
    if (pdf) {
      var pdfUrl = '?export=pdf&metrics=' + (metrics ? '1' : '0') + '&charts=' + (charts ? '1' : '0');
      window.location.href = pdfUrl;
    }
  }

  // Helper to get description for employment breakdown
  function descForExport(byLabel) {
    const descriptions = {
        'Industry': 'This shows employment distribution of alumni.',
        'Age Group': 'This shows how employed alumni are distributed across different age groups.',
        'Gender': 'This chart shows employment distribution of alumni based on gender.',
        'Program': 'This chart shows how alumni employment is distributed by academic program.',
        'Civil Status': 'This chart shows the employment breakdown of alumni by civil status.',
        'Graduation Year': 'This chart shows the employment distribution of alumni by graduation year.'
    };
    return descriptions[byLabel] || null;
  }

  // Prepare dashboard data for export
  function prepareDashboardData(metrics, charts) {
    const data = [];
    if (metrics) {
      // Only export Alumni Users and Employee Rate
      const metricsData = getMetricsData();
      data.push(['METRICS']);
      data.push(['Metric', 'Value']);
      if (metricsData['Alumni Users']) {
        data.push(['Alumni Users', metricsData['Alumni Users']]);
      }
      if (metricsData['Employee Rate']) {
        data.push(['Employee Rate', metricsData['Employee Rate']]);
      }
      data.push([]);
    }
    if (charts) {
      // Employment by industry chart
      const chartData = getChartData();
      // Employment by industry (bar chart)
      if (chartData['employmentByChart'] && Object.keys(chartData['employmentByChart']).length > 0) {
        const bySelect = document.getElementById('employment_by');
        const byLabel = bySelect && bySelect.options && bySelect.selectedIndex >= 0 ? bySelect.options[bySelect.selectedIndex].text.trim() : 'Industry';
        data.push([`EMPLOYMENT BY ${byLabel.toUpperCase()}`]);
        // Insert chart marker row for PDF export
        data.push(['__CHART_IMAGE__', 'employment']);
  // Description for Employment by selected breakdown (use ::DESC:: prefix)
  data.push([descForExport(byLabel) || 'This shows employment distribution of alumni.']);
        data.push([byLabel, 'Count']);
        Object.entries(chartData['employmentByChart']).forEach(([industry, count]) => {
          data.push([industry, count]);
        });
        data.push([]);
      }
      // Alumni heatmap table (location table)
      if (chartData['Alumni Heatmap Table'] && Array.isArray(chartData['Alumni Heatmap Table'])) {
        data.push(['ALUMNI HEATMAP TABLE']);
        data.push(['Province/Location', 'Count']);
        chartData['Alumni Heatmap Table'].forEach(row => {
          data.push(row);
        });
        data.push([]);
      }
      // Append textual descriptions for charts present on the dashboard export
      try {
        if (window.ExportDescriptions && window.ExportDescriptions.dashboard) {
          const descMap = window.ExportDescriptions.dashboard;
          const present = [];
          // Employment by industry
          if (chartData['employmentByChart'] && Object.keys(chartData['employmentByChart']).length > 0) {
            if (descMap['Employment by Industry']) {
              present.push(['Employment by Industry', descMap['Employment by Industry']]);
            }
          }
          // Alumni heatmap
          if (chartData['Alumni Heatmap Table'] && Array.isArray(chartData['Alumni Heatmap Table']) && chartData['Alumni Heatmap Table'].length > 0) {
            if (descMap['Alumni Location Heat Map']) {
              present.push(['Alumni Location Heat Map', descMap['Alumni Location Heat Map']]);
            }
          }
          if (present.length) {
            // Push only the description text (no title)
            present.forEach(row => data.push([row[1]]));
            data.push([]);
          }
        }
      } catch (e) {
        console.warn('Failed to append export descriptions', e);
      }

      return data;
  }

  // Get metrics data from the page
  function getMetricsData() {
    const metrics = {};
    const metricCards = document.querySelectorAll('.metric-card');
    metricCards.forEach(card => {
      // Try .card-title, fallback to h3
      let title = card.querySelector('.card-title')?.textContent?.trim();
      if (!title) {
        title = card.querySelector('h3')?.textContent?.trim();
      }
      const value = card.querySelector('.metric-value')?.textContent?.trim();
      if (title && value) {
        metrics[title] = value;
      }
    });
    return metrics;
  }

  // Get chart data from the page
  function getChartData() {
    const chartData = {};
    // Employment by industry (from Chart.js bar chart)
    const employmentByChart = {};
    if (window.Chart && window.Chart.instances) {
      Object.values(window.Chart.instances).forEach(chart => {
        if (chart.canvas && chart.canvas.id === 'employmentByChart' && chart.data && chart.data.labels && chart.data.datasets) {
          chart.data.labels.forEach((label, index) => {
            const value = chart.data.datasets[0]?.data[index] || 0;
            employmentByChart[label] = value;
          });
        }
      });
    }
    chartData['employmentByChart'] = employmentByChart;
    // Alumni heatmap table (parse the table in the DOM)
    const heatmapTable = document.querySelector('.card-body table.table');
    if (heatmapTable) {
      const rows = heatmapTable.querySelectorAll('tbody tr');
      const tableRows = [];
      rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 2) {
          const location = cells[0]?.textContent?.trim();
          const count = cells[1]?.textContent?.trim();
          if (location && count) {
            tableRows.push([location, count]);
          }
        }
      });
      chartData['Alumni Heatmap Table'] = tableRows;
    }
    return chartData;
  }

  // Test PDF export function
  function testPDFExport() {
    console.log("Testing PDF export...");
    
    if (window.ExportLibraries) {
      // Debug libraries
      window.ExportLibraries.debugLibraries();
      
      // Test with simple data
      const testData = [
        ['Test', 'Value'],
        ['Item 1', '100'],
        ['Item 2', '200'],
        ['Item 3', '300']
      ];
      
      try {
        window.ExportLibraries.exportToPDF(
          testData,
          'test_export.pdf',
          null,
          'Test PDF Export'
        );
        console.log("PDF test export initiated");
      } catch (error) {
        console.error("PDF test export failed:", error);
        alert("PDF test failed: " + error.message);
      }
    } else {
      console.error("ExportLibraries not available");
      alert("ExportLibraries not available");
    }
  }
};
</script>