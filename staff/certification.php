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
    $filter_date_from = $_GET['date-from'] ?? '';
    $filter_date_to = $_GET['date-to'] ?? '';
    $filter_category = $_GET['category'] ?? '';
    
    // Build filters based on role permissions
    $filters = [
        'school' => $filter_school,
        'campus' => $filter_campus,
        'college' => $filter_college,
        'major' => $filter_major,
        'year' => $filter_year
    ];
    
    $eduWhere = buildFilterWhereConditions($conn, $filters, 'e');
    
    // Build certification filters
    $certWhere = [];
    if ($eduWhere) {
        $certWhere = array_merge($certWhere, $eduWhere);
    }
    if ($filter_date_from) $certWhere[] = "certifications.certification_date >= '" . $conn->real_escape_string($filter_date_from) . "'";
    if ($filter_date_to) $certWhere[] = "certifications.certification_date <= '" . $conn->real_escape_string($filter_date_to) . "'";
    if ($filter_category) $certWhere[] = "certifications.category = '" . $conn->real_escape_string($filter_category) . "'";
    
    // Build award filters
    $awardWhere = [];
    if ($eduWhere) {
        $awardWhere = array_merge($awardWhere, $eduWhere);
    }
    if ($filter_date_from) $awardWhere[] = "awards.award_date >= '" . $conn->real_escape_string($filter_date_from) . "'";
    if ($filter_date_to) $awardWhere[] = "awards.award_date <= '" . $conn->real_escape_string($filter_date_to) . "'";
    if ($filter_category) $awardWhere[] = "awards.category = '" . $conn->real_escape_string($filter_category) . "'";
    
    // Certification Data by Month
    $certJoin = $eduWhere ? "JOIN education e ON e.user_id = certifications.user_id" : "";
    $certWhereSql = $certWhere ? "WHERE " . implode(' AND ', $certWhere) : "";
    $certificationData = $conn->query("SELECT DATE_FORMAT(certification_date, '%Y-%m') as month, COUNT(*) as count FROM certifications $certJoin $certWhereSql GROUP BY month ORDER BY month");
    
    $certificationLabels = [];
    $certificationCounts = [];
    if ($certificationData && $certificationData->num_rows > 0) {
        while($row = $certificationData->fetch_assoc()) {
            $certificationLabels[] = htmlspecialchars($row['month']);
            $certificationCounts[] = (int)$row['count'];
        }
    }
    
    // Award Data by Month
    $awardJoin = $eduWhere ? "JOIN education e ON e.user_id = awards.user_id" : "";
    $awardWhereSql = $awardWhere ? "WHERE " . implode(' AND ', $awardWhere) : "";
    $awardData = $conn->query("SELECT DATE_FORMAT(award_date, '%Y-%m') as month, COUNT(*) as count FROM awards $awardJoin $awardWhereSql GROUP BY month ORDER BY month");
    
    $awardLabels = [];
    $awardCounts = [];
    if ($awardData && $awardData->num_rows > 0) {
        while($row = $awardData->fetch_assoc()) {
            $awardLabels[] = htmlspecialchars($row['month']);
            $awardCounts[] = (int)$row['count'];
        }
    }
    
    // Top Certification Categories
    $categoryData = $conn->query("SELECT category, COUNT(*) as count FROM certifications $certJoin $certWhereSql GROUP BY category ORDER BY count DESC LIMIT 10");
    
    $categoryLabels = [];
    $categoryCounts = [];
    if ($categoryData && $categoryData->num_rows > 0) {
        while($row = $categoryData->fetch_assoc()) {
            $categoryLabels[] = htmlspecialchars($row['category']);
            $categoryCounts[] = (int)$row['count'];
        }
    }
    
    // Get totals for metrics
    $totalCertifications = $conn->query("SELECT COUNT(*) as total FROM certifications $certJoin $certWhereSql")->fetch_assoc()['total'];
    $totalAwards = $conn->query("SELECT COUNT(*) as total FROM awards $awardJoin $awardWhereSql")->fetch_assoc()['total'];
    
    // Return chart data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'certification' => ['labels' => $certificationLabels, 'counts' => $certificationCounts],
        'award' => ['labels' => $awardLabels, 'counts' => $awardCounts],
        'category' => ['labels' => $categoryLabels, 'counts' => $categoryCounts],
        'totalCertifications' => $totalCertifications,
        'totalAwards' => $totalAwards
    ]);
    exit;
}

// Include database and access control for normal/Word requests
include '../db/Database.php';
include 'includes/access_control.php';
$conn = Database::getInstance()->getConnection();

// Optional PHPWord support for generating real .docx certification reports with PLSP header
$phpWordAvailable = false;
$phpWordAutoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($phpWordAutoload && file_exists($phpWordAutoload)) {
    require_once $phpWordAutoload;
    if (class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        $phpWordAvailable = true;
    }
}

// Handle server-side Word export for Certification & Awards
if (isset($_GET['export']) && $_GET['export'] === 'word') {
    // Rebuild filters from query string (same as main page)
    $filter_school = $_GET['school-university'] ?? '';
    $filter_campus = $_GET['campus-branch'] ?? '';
    $filter_college = $_GET['college-department'] ?? '';
    $filter_major = (isset($_GET['major-specialization']) && $_GET['major-specialization'] !== 'all') ? $_GET['major-specialization'] : '';
    $filter_year = (isset($_GET['year']) && $_GET['year'] !== 'all') ? $_GET['year'] : '';
    $filter_date_from = $_GET['date-from'] ?? '';
    $filter_date_to = $_GET['date-to'] ?? '';
    $filter_category = $_GET['category'] ?? '';

    $filters = [
        'school' => $filter_school,
        'campus' => $filter_campus,
        'college' => $filter_college,
        'major' => $filter_major,
        'year' => $filter_year
    ];

    $eduWhere = buildFilterWhereConditions($conn, $filters, 'e');

    // Certification filters
    $certWhere = [];
    if ($eduWhere) $certWhere = array_merge($certWhere, $eduWhere);
    if ($filter_date_from) $certWhere[] = "certifications.certification_date >= '" . $conn->real_escape_string($filter_date_from) . "'";
    if ($filter_date_to)   $certWhere[] = "certifications.certification_date <= '" . $conn->real_escape_string($filter_date_to) . "'";
    if ($filter_category)  $certWhere[] = "certifications.category = '" . $conn->real_escape_string($filter_category) . "'";

    // Award filters
    $awardWhere = [];
    if ($eduWhere) $awardWhere = array_merge($awardWhere, $eduWhere);
    if ($filter_date_from) $awardWhere[] = "awards.award_date >= '" . $conn->real_escape_string($filter_date_from) . "'";
    if ($filter_date_to)   $awardWhere[] = "awards.award_date <= '" . $conn->real_escape_string($filter_date_to) . "'";
    if ($filter_category)  $awardWhere[] = "awards.category = '" . $conn->real_escape_string($filter_category) . "'";

    $certJoin = $eduWhere ? "JOIN education e ON e.user_id = certifications.user_id" : "";
    $certWhereSql = $certWhere ? "WHERE " . implode(' AND ', $certWhere) : "";
    $awardJoin = $eduWhere ? "JOIN education e ON e.user_id = awards.user_id" : "";
    $awardWhereSql = $awardWhere ? "WHERE " . implode(' AND ', $awardWhere) : "";

    // Totals
    $totalCertCount  = $conn->query("SELECT COUNT(*) as total FROM certifications $certJoin $certWhereSql")->fetch_assoc()['total'];
    $totalAwardCount = $conn->query("SELECT COUNT(*) as total FROM awards $awardJoin $awardWhereSql")->fetch_assoc()['total'];

    // Certifications by month
    $certificationData = $conn->query("SELECT DATE_FORMAT(certification_date, '%Y-%m') as month, COUNT(*) as count FROM certifications $certJoin $certWhereSql GROUP BY month ORDER BY month");
    $certMonthRows = [];
    if ($certificationData && $certificationData->num_rows > 0) {
        while ($row = $certificationData->fetch_assoc()) {
            $certMonthRows[] = [$row['month'], (int)$row['count']];
        }
    }

    // Awards by month
    $awardData = $conn->query("SELECT DATE_FORMAT(award_date, '%Y-%m') as month, COUNT(*) as count FROM awards $awardJoin $awardWhereSql GROUP BY month ORDER BY month");
    $awardMonthRows = [];
    if ($awardData && $awardData->num_rows > 0) {
        while ($row = $awardData->fetch_assoc()) {
            $awardMonthRows[] = [$row['month'], (int)$row['count']];
        }
    }

    // Top certification categories
    $categoryData = $conn->query("SELECT category, COUNT(*) as count FROM certifications $certJoin $certWhereSql GROUP BY category ORDER BY count DESC LIMIT 10");
    $categoryRows = [];
    if ($categoryData && $categoryData->num_rows > 0) {
        while ($row = $categoryData->fetch_assoc()) {
            $categoryRows[] = [$row['category'], (int)$row['count']];
        }
    }

    // HTML-based Word export with tables
    $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Certification &amp; Awards Report</title>';
    $html .= '<style>body{font-family:Arial,sans-serif;font-size:11pt;}h1,h2{margin:12px 0;}table{border-collapse:collapse;width:100%;margin-bottom:16px;}th,td{border:1px solid #333;padding:4px 6px;text-align:left;}th{background:#219653;color:#fff;} .small{font-size:10pt;color:#555;}</style>';
    $html .= '</head><body>';

    $html .= '<h1>Certification &amp; Awards Report</h1>';
    $html .= '<p class="small">Generated: ' . date('Y-m-d H:i:s') . '</p>';

    // Summary metrics table
    $html .= '<h2>Summary Metrics</h2>';
    $html .= '<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
    $html .= '<tr><td>Total Certifications</td><td>' . (int)$totalCertCount . '</td></tr>';
    $html .= '<tr><td>Total Awards</td><td>' . (int)$totalAwardCount . '</td></tr>';
    $html .= '</tbody></table>';

    // Certifications by month table
    if ($certMonthRows) {
        $html .= '<h2>Certifications by Month</h2>';
        $html .= '<table><thead><tr><th>Month</th><th>Count</th></tr></thead><tbody>';
        foreach ($certMonthRows as [$label, $count]) {
            $html .= '<tr><td>' . htmlspecialchars($label) . '</td><td>' . (int)$count . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    // Awards by month table
    if ($awardMonthRows) {
        $html .= '<h2>Awards by Month</h2>';
        $html .= '<table><thead><tr><th>Month</th><th>Count</th></tr></thead><tbody>';
        foreach ($awardMonthRows as [$label, $count]) {
            $html .= '<tr><td>' . htmlspecialchars($label) . '</td><td>' . (int)$count . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    // Top certification categories table
    if ($categoryRows) {
        $html .= '<h2>Top Certification Categories</h2>';
        $html .= '<table><thead><tr><th>Category</th><th>Count</th></tr></thead><tbody>';
        foreach ($categoryRows as [$label, $count]) {
            $html .= '<tr><td>' . htmlspecialchars($label) . '</td><td>' . (int)$count . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    $html .= '<p class="small">Generated by ALUMytics</p>';
    $html .= '</body></html>';

    $filename = 'certification_' . date('Ymd_His') . '.doc';
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $html;
    exit;
}

// For normal requests, include layout
include 'includes/header.php'; // This includes access_control.php
include 'includes/sidebar.php';

// Check module access
requireModuleAccess('certification');

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
$filter_date_from = $_GET['date-from'] ?? '';
$filter_date_to = $_GET['date-to'] ?? '';
$filter_category = $_GET['category'] ?? '';

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

// Get additional filter options for certifications
$categoriesQuery = $eduWhere ? 
    "SELECT DISTINCT category FROM certifications JOIN education e ON e.user_id = certifications.user_id WHERE " . implode(' AND ', $eduWhere) . " ORDER BY category" :
    "SELECT DISTINCT category FROM certifications ORDER BY category";
$categories = $conn->query($categoriesQuery);

// Calculate metrics
$certJoin = $eduWhere ? "JOIN education e ON e.user_id = certifications.user_id" : "";
$certWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";
$awardJoin = $eduWhere ? "JOIN education e ON e.user_id = awards.user_id" : "";
$awardWhereSql = $eduWhere ? "WHERE " . implode(' AND ', $eduWhere) : "";

$totalCertCount = $conn->query("SELECT COUNT(*) as total FROM certifications $certJoin $certWhereSql")->fetch_assoc()['total'];
$totalAwardCount = $conn->query("SELECT COUNT(*) as total FROM awards $awardJoin $awardWhereSql")->fetch_assoc()['total'];

$role ??= null;
$college_name ??= null;
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/certification-page.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-0 dashboard-title">Certification & Awards</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Viewing data for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Track alumni achievements, certifications, and awards over time.</p>
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

                <!-- Year filter -->
                <?php if (in_array('year', $availableFilters)): ?>
                <div class="filter-dropdown">
                    <label for="year">Year Graduated</label>
                    <select id="year" name="year" class="filter-select">
                        <option value="">All Years</option>
                        <?php
                        // Fetch distinct years from education table
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
                    <label for="category">Category</label>
                    <select id="category" name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php while($row = $categories->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['category']) ?>"><?= htmlspecialchars($row['category']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            </div>
        </form>
    </div>

    <div class="metrics-container">
        <div class="metric-card">
            <h3>Total Certifications</h3>
            <div class="metric-value" id="totalCertifications"><?= $totalCertCount ?></div>
            <div class="metric-change positive">
                <i class="fas fa-certificate"></i> Certified alumni
            </div>
            <div class="icon-container icon-certificate">
                <i class="fas fa-certificate"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Total Awards</h3>
            <div class="metric-value" id="totalAwards"><?= $totalAwardCount ?></div>
            <div class="metric-change positive">
                <i class="fas fa-trophy"></i> Award recipients
            </div>
            <div class="icon-container icon-award">
                <i class="fas fa-trophy"></i>
            </div>
        </div>
    </div>

    <div class="row g-4" id="sortable-charts">
        <div class="col-lg-6 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Certifications Received</h5>
                        <small class="text-muted">Monthly certification trends</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="certificationChart"></canvas>
                    <div id="certificationChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="certificationChart-nodata" class="empty-chart-msg" style="display:none;">
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
                        <h5 class="card-title mb-0">Awards Received</h5>
                        <small class="text-muted">Monthly award trends</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="awardChart"></canvas>
                    <div id="awardChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="awardChart-nodata" class="empty-chart-msg" style="display:none;">
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
        <div class="col-12 sortable-chart-card">
            <div class="card h-100 shadow-sm dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Top Certification Categories</h5>
                        <small class="text-muted">Most popular certification types</small>
                    </div>
                </div>
                <div class="card-body chart-body">
                    <canvas id="categoryChart"></canvas>
                    <div id="categoryChart-spinner" class="spinner" style="display:none;">
                        <div class="spinner-border dash-spinner" role="status"></div>
                    </div>
                    <div id="categoryChart-nodata" class="empty-chart-msg" style="display:none;">
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
                <h5 class="modal-title" id="exportModalLabel">Export Certification Data</h5>
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
let certificationChart, awardChart, categoryChart;

// Create Certification Chart
function createCertificationChart(data) {
    const ctx = document.getElementById('certificationChart').getContext('2d');
    if (certificationChart) certificationChart.destroy();
    // Use shared palette for certification line
    const palette = getDistinctPalette(data.labels.length || 1);
    const bg = applyAlpha([palette[0]], 0.15)[0];
    const border = palette[0];
    const pointColor = palette[0];
    certificationChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Certifications',
                data: data.counts,
                borderColor: border,
                backgroundColor: bg,
                pointBackgroundColor: pointColor,
                pointBorderColor: border,
                borderWidth: 2,
                tension: 0.4
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
    window.certificationChart = certificationChart;
}

// Create Award Chart
function createAwardChart(data) {
    const ctx = document.getElementById('awardChart').getContext('2d');
    if (awardChart) awardChart.destroy();
    // Use shared palette for award bars
    awardChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Awards',
                data: data.counts,
                backgroundColor: applyAlpha(getDistinctPalette(data.labels.length), 0.85),
                borderColor: getDistinctPalette(data.labels.length),
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
    window.awardChart = awardChart;
}

// Create Category Chart
function createCategoryChart(data) {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    if (categoryChart) categoryChart.destroy();
    // Use shared palette for category pie
    const catPalette = getDistinctPalette(data.labels.length);
    categoryChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.counts,
                backgroundColor: applyAlpha(catPalette, 0.85)
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
    window.categoryChart = categoryChart;
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
    fetch(`certification.php?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        // Hide spinners
        document.querySelectorAll('.spinner').forEach(s => s.style.display = 'none');
        document.querySelectorAll('canvas').forEach(c => c.style.display = 'block');
        
        // Update metrics
        document.getElementById('totalCertifications').textContent = data.totalCertifications;
        document.getElementById('totalAwards').textContent = data.totalAwards;
        
        // Update charts
        if (data.certification.labels.length > 0) {
            createCertificationChart(data.certification);
        } else {
            document.getElementById('certificationChart').style.display = 'none';
            document.getElementById('certificationChart-nodata').style.display = 'block';
        }
        
        if (data.award.labels.length > 0) {
            createAwardChart(data.award);
        } else {
            document.getElementById('awardChart').style.display = 'none';
            document.getElementById('awardChart-nodata').style.display = 'block';
        }
        
        if (data.category.labels.length > 0) {
            createCategoryChart(data.category);
        } else {
            document.getElementById('categoryChart').style.display = 'none';
            document.getElementById('categoryChart-nodata').style.display = 'block';
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

document.addEventListener('DOMContentLoaded', function() {
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
            const formData = new FormData(document.getElementById('filterForm'));
            const params = new URLSearchParams(formData);
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
            // If Word is selected, use server-side export like other modules
            if (word) {
                const url = 'certification.php?' + params.toString() +
                    '&export=word' +
                    '&metrics=' + (metrics ? '1' : '0') +
                    '&charts=' + (charts ? '1' : '0');
                window.location.href = url;
                return;
            }
            // --- New export logic using export-libraries.js ---
            // Gather metrics from PHP variables
            const totalCertifications = <?= json_encode($totalCertCount) ?>;
            const totalAwards = <?= json_encode($totalAwardCount) ?>;

            // Prepare export data sections
            let exportData = [];
            if (metrics) {
                exportData.push(['Certification & Awards Export']);
                exportData.push(['']);
                exportData.push(['Summary Metrics']);
                exportData.push(['Metric', 'Count']);
                exportData.push(['Total Certifications', totalCertifications]);
                exportData.push(['Total Awards', totalAwards]);
                exportData.push(['']);
            }
            if (charts) {
                exportData.push(['Certifications Received']);
                exportData.push(['__CHART_IMAGE__', 'certification']);
                exportData.push(['This chart shows the monthly trend of certifications earned by alumni.']);
                exportData.push(['']);
                exportData.push(['Awards Received']);
                exportData.push(['__CHART_IMAGE__', 'award']);
                exportData.push(['This chart shows the monthly trend of awards received by alumni.']);
                exportData.push(['']);
                exportData.push(['Top Certification Categories']);
                exportData.push(['__CHART_IMAGE__', 'category']);
                exportData.push(['This chart shows the most common categories of certifications obtained by alumni.']);
                exportData.push(['']);
            }

            // Chart image capture (prefer Chart.js API with canvas fallback)
            const chartImages = {};
            const toImg = (chartInstance, canvasId) => {
                try {
                    if (chartInstance && typeof chartInstance.toBase64Image === 'function') {
                        return chartInstance.toBase64Image();
                    }
                    const canvas = document.getElementById(canvasId);
                    return canvas ? canvas.toDataURL('image/png', 1.0) : null;
                } catch (e) { return null; }
            };
            if (charts) {
                chartImages['certification'] = toImg(window.certificationChart, 'certificationChart');
                chartImages['award'] = toImg(window.awardChart, 'awardChart');
                chartImages['category'] = toImg(window.categoryChart, 'categoryChart');
            }

            // Export config
            const config = {
                filename: 'alumytics_certification_export_' + new Date().toISOString().replace(/[-:T]/g, '').slice(0, 15),
                sections: [
                    metrics ? { title: 'Summary Metrics', type: 'table', startRow: 2, endRow: 6 } : null,
                    charts ? { title: 'Certifications Received', type: 'chart', chartKey: 'certification', startRow: metrics ? 8 : 2, endRow: metrics ? 9 : 3 } : null,
                    charts ? { title: 'Awards Received', type: 'chart', chartKey: 'award', startRow: metrics ? 11 : 5, endRow: metrics ? 12 : 6 } : null,
                    charts ? { title: 'Top Certification Categories', type: 'chart', chartKey: 'category', startRow: metrics ? 14 : 8, endRow: metrics ? 15 : 9 } : null,
                ].filter(Boolean)
            };

            // Export using the library directly
            if (pdf && window.exportLibrary) {
                window.exportLibrary.exportToPDF(exportData, config.filename + '.pdf', { chartImages: chartImages }, 'Certification & Awards Export');
            }
            if (excel && window.exportLibrary) {
                window.exportLibrary.exportToExcel(exportData, config.filename + '.xlsx');
            }
            if (csv && window.exportLibrary) {
                window.exportLibrary.exportToCSV(exportData, config.filename + '.csv');
            }
            
            if (!window.exportLibrary) {
                alert('Export library not loaded. Please ensure js/export-libraries.js is included.');
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
