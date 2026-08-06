<?php
// --- Main Logic ---
include '../db/Database.php';
include '../db/LocationCoordinates.php'; // Include new coordinate system
include 'includes/header.php';
include 'includes/sidebar.php';
$conn = Database::getInstance()->getConnection();

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
        $chartWhere[] = "year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
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
    
    $employmentDataSql = "SELECT $groupField as group_label, COUNT(*) as count FROM employment $chartJoin $extraJoin $chartWhereSql GROUP BY group_label ORDER BY count DESC";
    $employmentData = $conn->query($employmentDataSql);
    $employmentLabels = [];
    $employmentCounts = [];
    if ($employmentData && $employmentData->num_rows > 0) {
        while($row = $employmentData->fetch_assoc()) {
            $employmentLabels[] = $row['group_label'] ? htmlspecialchars($row['group_label']) : 'Unknown';
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
    
    // Geographic Distribution (improved version from superadmin) - Group by Province
    $geoJoin = $eduWhere ? "JOIN education e ON emp.user_id = e.user_id" : '';
    $geoWhere = $eduWhere;
    if ($filter_year) {
        $geoWhere[] = "emp.year_of_employment = '" . $conn->real_escape_string($filter_year) . "'";
    }
    $geoWhereSql = $geoWhere ? 'WHERE ' . implode(' AND ', $geoWhere) : '';

    $geoDistribution = $conn->query("SELECT 
        ca.company_province,
        CASE 
            WHEN ca.company_province IN ('Singapore', 'South Korea', 'Hong Kong') THEN 'International'
            ELSE 'Local'
        END as location_type,
        COUNT(*) as count 
    FROM employment emp
    $geoJoin
    LEFT JOIN company_address ca ON emp.user_id = ca.user_id 
    $geoWhereSql 
    GROUP BY ca.company_province, location_type
    ORDER BY count DESC");

    $locationLabels = [];
    $locationCodes = [];
    $locationCounts = [];
    $heatmapPoints = [];

    if ($geoDistribution && $geoDistribution->num_rows > 0) {
        while($row = $geoDistribution->fetch_assoc()) {
            $province = $row['company_province'] ?: 'Unknown';
            $count = (int)$row['count'];
            
            $locationLabels[] = htmlspecialchars($province);
            $locationCodes[] = htmlspecialchars($province);
            $locationCounts[] = $count;

            // Get coordinates using province (not city) for province-level mapping
            $coordinates = LocationCoordinates::getCoordinates(null, $province);
            $heatmapPoints[] = [$coordinates[0], $coordinates[1], $count];
        }
    }
    
    return compact('employmentLabels', 'employmentCounts', 'industryLabels', 'industryCounts', 'locationLabels', 'locationCodes', 'locationCounts', 'heatmapPoints');
}

// Helper function for recent activities
function getRecentActivities($conn, $limit = 10) {
    global $userPermissions, $eduWhere;
    
    if (!$userPermissions['can_view_user_logs']) {
        return [];
    }
    
    $activities = [];
    $whereClause = '';
    if ($userPermissions['college_restricted']) {
        $whereClause = 'WHERE login_logs.login_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
    } else {
        $whereClause = 'WHERE login_logs.login_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
    }
    
    $sql = "(
        SELECT users.full_name, login_logs.login_time AS activity_time, 'login' AS activity_type, NULL AS extra
        FROM login_logs JOIN users ON users.user_id = login_logs.user_id
        $whereClause
    ) UNION ALL (
        SELECT users.full_name, certifications.created_at AS activity_time, 'certification' AS activity_type, certifications.certification_name AS extra
        FROM certifications JOIN users ON users.user_id = certifications.user_id
        WHERE certifications.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ) UNION ALL (
        SELECT users.full_name, awards.created_at AS activity_time, 'award' AS activity_type, awards.award_name AS extra
        FROM awards JOIN users ON users.user_id = awards.user_id
        WHERE awards.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
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
    $profiles = [];
    $sql = "(
        SELECT users.full_name, certifications.created_at AS activity_time, 'certification' AS update_type
        FROM certifications JOIN users ON users.user_id = certifications.user_id
        WHERE certifications.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ) UNION ALL (
        SELECT users.full_name, awards.created_at AS activity_time, 'award' AS update_type
        FROM awards JOIN users ON users.user_id = awards.user_id
        WHERE awards.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ) UNION ALL (
        SELECT users.full_name, users.created_at AS activity_time, 'profile' AS update_type
        FROM users
        WHERE users.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND users.role = 'alumni'
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
$filterOptions = getFilterOptions($conn);
$metrics = getMetrics($conn, $eduWhere);
$chartData = getChartData($conn, $eduWhere, $filter_year, $employment_by);
$recentActivities = getRecentActivities($conn);
$recentlyUpdatedProfiles = getRecentlyUpdatedProfiles($conn);

// Export logic and unused functions removed for cleanup. Ready for refactor.
?>
<link rel="stylesheet" href="css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content">
    <div id="dashboard" class="tab-content active">
        <div class="header">
            <div class="header-top d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0"><?= getRoleDisplayName($role) ?> Dashboard</h1>
                    <?php if (isCollegeRestricted() && $college_name): ?>
                        <p class="text-muted mt-1 mb-0">Viewing data for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                    <?php endif; ?>
                </div>
                <div class="export-dropdown">
                    <button class="export-button" id="exportButton" type="button">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <form method="get" action="">
            <div class="filter-controls">
                <?php $availableFilters = getAvailableFilters(); ?>
                
                <?php if (in_array('school', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="school-university">School/University</label>
                    <select id="school-university" name="school-university">
                        <option value="" <?= ($filter_school == '') ? 'selected' : '' ?>>All</option>
                        <?php while($row = $filterOptions['schools']->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['school_university']) ?>"><?= htmlspecialchars($row['school_university']) ?></option>
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
                                <option value="<?= htmlspecialchars($row['campus_branch']) ?>"><?= htmlspecialchars($row['campus_branch']) ?></option>
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
                            <option value="<?= htmlspecialchars($row['college_department']) ?>"><?= htmlspecialchars($row['college_department']) ?></option>
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
                            <option value="<?= htmlspecialchars($row['major_specialization']) ?>"><?= htmlspecialchars($row['major_specialization']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (in_array('year', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="year">Year</label>
                    <select id="year" name="year">
                        <option value="all" <?= ($filter_year == '') ? 'selected' : '' ?>>All</option>
                        <?php while($row = $filterOptions['years']->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['year']) ?>"><?= htmlspecialchars($row['year']) ?></option>
                        <?php endwhile; ?>
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
        </form>
        </div>

        <br>
        <div class="metrics-container">
            <div class="metric-card">
                <h3>Alumni Users</h3>
                <div class="metric-value"><?php echo $metrics['alumniUsers']; ?></div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i> <?php echo ($metrics['alumniUsersChange'] >= 0 ? '+' : '') . $metrics['alumniUsersChange']; ?>% than last week
                </div>
                <div class="icon-container icon-money">
                    <i class="fas fa-users"></i>
                </div>
            </div>

            <div class="metric-card">
                <h3>Employee Rate</h3>
                <div class="metric-value"><?php echo $metrics['employeeRate']; ?></div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i> <?php echo ($metrics['employeeRateChange'] >= 0 ? '+' : '') . $metrics['employeeRateChange']; ?>% than last month
                </div>
                <div class="icon-container icon-users">
                    <i class="fas fa-users"></i>
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
            <div class="col-md-6 sortable-chart-card">
                <div class="card h-100 shadow-sm">
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
                    <div class="card-body d-flex align-items-center justify-content-center" style="min-height:350px;">
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
            <div class="col-md-6 sortable-chart-card">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">Alumni Location Heatmap</h5>
                            <small class="text-muted">Geographic Distribution</small>
                        </div>
                    </div>
                    <div class="card-body" style="min-height:350px;">
                        <div id="alumniHeatmap" style="height: 350px; width: 100%;"></div>
                        <script id="locationHeatmapPoints" type="application/json">
                            <?php echo json_encode($chartData['heatmapPoints']); ?>
                        </script>
                        <?php if (empty($chartData['heatmapPoints'])): ?>
                            <p class="text-center">No data available for map</p>
                        <?php else: ?>
                        <table class="table table-sm mt-3">
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

        <div class="row g-4 mt-4">
            <div class="row g-4 mt-4">
                <div class="col-md-6" style="width: 100%;">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-0">Just Updated</h5>
                                <small class="text-muted"><?= count($recentlyUpdatedProfiles) ?> profiles recently updated</small>
                            </div>
                        </div>
                        <div class="card-body" style="height: 400px; overflow-y: auto;">
                            <ul class="list-unstyled">
                                <?php foreach ($recentlyUpdatedProfiles as $profile): ?>
                                    <li class="mb-3 pb-2 border-bottom">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <strong class="text-primary"><?= htmlspecialchars($profile['user']) ?></strong>
                                                <p class="mb-1 text-muted small"><?= htmlspecialchars($profile['desc']) ?></p>
                                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($profile['time'])) ?></small>
                                            </div>
                                            <div class="ms-2">
                                                <span class="badge bg-success">Updated</span>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (empty($recentlyUpdatedProfiles)): ?>
                                    <li class="text-center text-muted py-4">
                                        <i class="fas fa-user-edit fa-2x mb-2"></i>
                                        <p class="text-center">No recent profile updates</p>
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
                <div class="col-12" style="width: 100%;">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-0">Recent User Activities</h5>
                                <small class="text-muted"><?= count($recentActivities) ?> recent activities</small>
                            </div>
                        </div>
                        <div class="card-body" style="height: 400px; width: 100%; overflow-y: auto;">
                            <ul class="list-unstyled">
                                <?php foreach ($recentActivities as $activity): ?>
                                    <li class="mb-3 pb-2 border-bottom">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <strong class="text-primary"><?= htmlspecialchars($activity['user']) ?></strong>
                                                <p class="mb-1 text-muted small"><?= htmlspecialchars($activity['desc']) ?></p>
                                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($activity['time'])) ?></small>
                                            </div>
                                            <div class="ms-2">
                                                <?php
                                                $badgeClass = 'bg-secondary';
                                                $icon = 'fa-user';
                                                if ($activity['type'] === 'login') {
                                                    $badgeClass = 'bg-primary';
                                                    $icon = 'fa-sign-in-alt';
                                                } elseif ($activity['type'] === 'certification') {
                                                    $badgeClass = 'bg-info';
                                                    $icon = 'fa-certificate';
                                                } elseif ($activity['type'] === 'award') {
                                                    $badgeClass = 'bg-warning';
                                                    $icon = 'fa-trophy';
                                                }
                                                ?>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <i class="fas <?= $icon ?>"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (empty($recentActivities)): ?>
                                    <li class="text-center text-muted py-4">
                                        <i class="fas fa-history fa-2x mb-2"></i>
                                        <p class="text-center">No recent activities</p>
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

<script src="js/sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/chart-utils.js"></script>
<script src="js/index.js"></script>
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
  var points = JSON.parse(dataScript.textContent);
  if (window.alumniHeatmapMap) {
    window.alumniHeatmapMap.remove();
  }
  if (points && points.length > 0) {
    window.alumniHeatmapMap = L.map('alumniHeatmap').setView([14.5995, 120.9842], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(window.alumniHeatmapMap);
    
    // Add heatmap layer with improved gradient
    if (window.L && window.L.heatLayer) {
      L.heatLayer(points, {
        radius: 25,
        blur: 15,
        maxZoom: 10,
        gradient: {0.2: 'blue', 0.4: 'lime', 0.6: 'orange', 0.8: 'red'}
      }).addTo(window.alumniHeatmapMap);

      // Add count markers
      points.forEach(function(pt) {
        var lat = pt[0], lng = pt[1], count = pt[2];
        var marker = L.marker([lat, lng], {
          icon: L.divIcon({
            className: 'count-marker',
            html: '<div style="background:rgba(33,150,83,0.9);color:#fff;padding:4px 8px;border-radius:12px;font-size:12px;font-weight:bold;">'+count+'</div>',
            iconSize: [30, 20],
            iconAnchor: [15, 10]
          })
        });
        marker.addTo(window.alumniHeatmapMap);
      });
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

  var exportButton = document.getElementById('exportButton');
  var exportForm = document.getElementById('exportForm');
  
  if (exportButton) {
    exportButton.addEventListener('click', function() {
      var modal = new bootstrap.Modal(document.getElementById('exportModal'));
      modal.show();
    });
  }
  
  if (exportForm) {
    exportForm.addEventListener('submit', function(event) {
      event.preventDefault();
      var metricsEl = document.getElementById('exportMetrics');
      var chartsEl = document.getElementById('exportCharts');
      var pdfEl = document.getElementById('exportPDF');
      var excelEl = document.getElementById('exportExcel');
      var csvEl = document.getElementById('exportCSV');
      const metrics = metricsEl && metricsEl.checked;
      const charts = chartsEl && chartsEl.checked;
      const pdf = pdfEl && pdfEl.checked;
      const excel = excelEl && excelEl.checked;
      const csv = csvEl && csvEl.checked;
      
      if (!metrics && !charts) {
          alert('Please select at least one data type to export.');
          return;
      }
      if (!pdf && !excel && !csv) {
          alert('Please select at least one format.');
          return;
      }

      // Use JavaScript libraries for client-side export
      if (window.ExportLibraries) {
        // Debug libraries before export
        if (window.ExportLibraries.debugLibraries) {
          window.ExportLibraries.debugLibraries();
        }
        exportDashboardData(metrics, charts, pdf, excel, csv);
        // Close the modal after export
        const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
        if (modal) {
          modal.hide();
        }
      } else {
        // Fallback to server-side export
        fallbackServerExport(metrics, charts, pdf, excel, csv);
      }
    });
  }

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
    add('major-specialization', 'Major');
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
    add('major-specialization', 'major');
    add('year', 'year');
    add('employment_by', 'by');
    return parts.join('_');
  }

  // Client-side export using reusable ExportLibraries component
  async function exportDashboardData(metrics, charts, pdf, excel, csv) {
    const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
    const suffix = getFilterFilenameSuffix();
    const filename = `dashboard_${timestamp}${suffix ? '_' + suffix : ''}`;
    try {
      const dashboardData = prepareDashboardData(metrics, charts);
      let exportCount = 0;
      if (csv) {
        ExportLibraries.exportToCSV(dashboardData, filename + '.csv');
        exportCount++;
      }
      if (excel) {
        ExportLibraries.exportToExcel(dashboardData, filename + '.xlsx');
        exportCount++;
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
        await ExportLibraries.exportToPDF(
          dashboardData,
          filename + '.pdf',
          { chartImages, config },
          'ALUMytics Dashboard Report' + (titleSuffix ? ' — ' + titleSuffix : '')
        );
        exportCount++;
      }
      if (exportCount > 0) {
        console.log(`Successfully exported ${exportCount} file(s)`);
        // Show success message to user
        const successMsg = `Successfully exported ${exportCount} file(s)`;
        if (typeof bootstrap !== 'undefined') {
          const toast = document.createElement('div');
          toast.className = 'alert alert-success alert-dismissible fade show position-fixed';
          toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
          toast.innerHTML = `
            ${successMsg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          `;
          document.body.appendChild(toast);
          setTimeout(() => {
            if (toast.parentNode) {
              toast.parentNode.removeChild(toast);
            }
          }, 3000);
        }
      }
    } catch (error) {
      console.error('Client-side export error:', error);
      alert('Error with client-side export. Falling back to server-side export.');
      fallbackServerExport(metrics, charts, pdf, excel, csv);
    }
  }

  // Fallback to server-side export
  function fallbackServerExport(metrics, charts, pdf, excel, csv) {
    if (csv) {
      var csvUrl = '?export=csv&metrics=' + (metrics ? '1' : '0') + '&charts=' + (charts ? '1' : '0');
      triggerDownload(csvUrl);
    }
    if (excel) {
      var excelUrl = '?export=excel&metrics=' + (metrics ? '1' : '0') + '&charts=' + (charts ? '1' : '0');
      triggerDownload(excelUrl);
    }
    if (pdf) {
      var pdfUrl = '?export=pdf&metrics=' + (metrics ? '1' : '0') + '&charts=' + (charts ? '1' : '0');
      triggerDownload(pdfUrl);
    }
  }

  // Helper to get description for employment breakdown
  function descForExport(byLabel) {
    const descriptions = {
      'Industry': 'This chart presents the employment distribution of alumni categorized by industry. It displays the number of alumni working across various professional sectors.',
      'Age Group': 'This chart presents the distribution of alumni employment categorized by age group. It provides an overview of how employed alumni are represented across different age ranges.',
      'Gender': 'This chart presents the distribution of alumni employment categorized by gender, showing how employed graduates are represented across gender groups.',
      'Program': 'This chart illustrates the classification of alumni employment according to program, providing an overview of employment representation by course or specialization.',
      'Civil Status': 'This chart presents the distribution of alumni employment categorized by civil status, showing how employed graduates are represented across different civil status groups.',
      'Graduation Year': 'This chart illustrates the classification of alumni employment according to year of graduation, providing an overview of employment representation by batch.'
    };
    return descriptions[byLabel] || null;
  }

  // Prepare dashboard data for export
  function prepareDashboardData(metrics, charts) {
    const data = [];
    // Always include filter context at the top
    const filterRows = getFilterSummaryRows();
    if (filterRows && filterRows.length) {
      data.push(...filterRows);
    }
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
  data.push([`::DESC:: Employment by ${byLabel} — ${descForExport(byLabel) || 'This chart presents employment distribution.'}`]);
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
            // Push descriptions using ::DESC:: prefix so the renderer treats them as paragraph rows
            present.forEach(row => data.push([`::DESC:: ${row[0]} — ${row[1]}`]));
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
});
</script>

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