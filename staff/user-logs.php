<?php
// Include database and access control
include '../db/Database.php';
include 'includes/header.php'; // This includes access_control.php
include 'includes/sidebar.php';

// Check module access
requireModuleAccess('user-logs');

$conn = Database::getInstance()->getConnection();

// Ensure consistent timezone for display and relative time calculations
date_default_timezone_set('Asia/Manila');

// Get filter values
$search = $_GET['search'] ?? '';
$userFilter = $_GET['user_filter'] ?? '';
$statusFilter = $_GET['status_filter'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE conditions based on role permissions
$where = [];
$params = [];
$types = '';

// Apply college restrictions if user is college-restricted
if (isCollegeRestricted()) {
    $where[] = "u.college_id = ?";
    $params[] = $_SESSION['college_id'];
    $types .= 'i';
}

function formatDisplayTime($timestamp, $format) {
    // Formats absolute date/time consistently in Asia/Manila
    $appTz = new DateTimeZone('Asia/Manila');
    try {
        $dt = new DateTime($timestamp, $appTz);
        return $dt->format($format);
    } catch (Exception $e) {
        return '';
    }
}

function formatRelativeFromSeconds($diffSeconds, $timestamp) {
    if (!is_numeric($diffSeconds)) {
        return formatTimestamp($timestamp);
    }
    if ($diffSeconds < 0) $diffSeconds = 0;
    if ($diffSeconds < 60) return 'Just now';
    if ($diffSeconds < 3600) {
        $mins = floor($diffSeconds / 60);
        return $mins . ' ' . ($mins == 1 ? 'minute' : 'minutes') . ' ago';
    }
    if ($diffSeconds < 86400) {
        $hrs = floor($diffSeconds / 3600);
        return $hrs . ' ' . ($hrs == 1 ? 'hr' : 'hrs') . ' ago';
    }
    // For 1+ days, fall back to the timestamp-based friendly formats
    return formatTimestamp($timestamp);
}

// Apply search filter
if ($search) {
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

// Apply user filter
if ($userFilter) {
    $where[] = "ll.user_id = ?";
    $params[] = $userFilter;
    $types .= 'i';
}

// Apply status filter
if ($statusFilter) {
    if ($statusFilter == 'successful') {
        $where[] = "ll.success = 1";
    } elseif ($statusFilter == 'failed') {
        $where[] = "ll.success = 0";
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get logs data
$sql = "SELECT ll.*, 
               TIMESTAMPDIFF(SECOND, ll.login_time, NOW()) AS diff_seconds,
               u.full_name, u.email, u.role, p.profile_photo
        FROM login_logs ll 
        LEFT JOIN users u ON ll.user_id = u.user_id 
        LEFT JOIN personal p ON u.user_id = p.user_id 
        $whereSql 
        ORDER BY ll.login_time DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($params) {
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$logs = $stmt->get_result();

// Get total count
$countSql = "SELECT COUNT(*) as total FROM login_logs ll LEFT JOIN users u ON ll.user_id = u.user_id $whereSql";
$countStmt = $conn->prepare($countSql);
if ($params && count($params) > 2) {
    $countParams = array_slice($params, 0, -2);
    $countTypes = substr($types, 0, -2);
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get unique users for filter dropdown
$userQuery = isCollegeRestricted() ? 
    "SELECT DISTINCT u.user_id AS id, u.full_name FROM users u WHERE u.college_id = ? ORDER BY u.full_name" :
    "SELECT DISTINCT u.user_id AS id, u.full_name FROM users u ORDER BY u.full_name";
$userStmt = $conn->prepare($userQuery);
if (isCollegeRestricted()) {
    $userStmt->bind_param('i', $_SESSION['college_id']);
}
$userStmt->execute();
$users = $userStmt->get_result();

// Get summary statistics
$baseWhereSql = isCollegeRestricted() ? "WHERE u.college_id = {$_SESSION['college_id']}" : "";
$totalUsers = $conn->query("SELECT COUNT(DISTINCT ll.user_id) as total FROM login_logs ll LEFT JOIN users u ON ll.user_id = u.user_id $baseWhereSql")->fetch_assoc()['total'];
$totalLogins = $conn->query("SELECT COUNT(*) as total FROM login_logs ll LEFT JOIN users u ON ll.user_id = u.user_id $baseWhereSql")->fetch_assoc()['total'];

$failedWhere = $baseWhereSql ? "$baseWhereSql AND ll.success = 0" : "WHERE ll.success = 0";
$failedAttempts = $conn->query("SELECT COUNT(*) as total FROM login_logs ll LEFT JOIN users u ON ll.user_id = u.user_id $failedWhere")->fetch_assoc()['total'];

$todayWhere = $baseWhereSql ? "$baseWhereSql AND DATE(ll.login_time) = CURDATE()" : "WHERE DATE(ll.login_time) = CURDATE()";
$todayLogins = $conn->query("SELECT COUNT(*) as total FROM login_logs ll LEFT JOIN users u ON ll.user_id = u.user_id $todayWhere")->fetch_assoc()['total'];

// Helper functions
function getUserAvatar($profilePhoto, $email, $fullName) {
    if ($profilePhoto && file_exists("../uploads/profile_pictures/$profilePhoto")) {
        return "../uploads/profile_pictures/$profilePhoto";
    }
    // Generate initials
    $names = explode(' ', $fullName);
    $initials = '';
    foreach ($names as $name) {
        $initials .= strtoupper(substr($name, 0, 1));
    }
    return $initials;
}

function formatTimestamp($timestamp) {
    $tz = new DateTimeZone('Asia/Manila');
    try {
        $date = new DateTime($timestamp, $tz);
    } catch (Exception $e) {
        return '';
    }
    $now = new DateTime('now', $tz);
    $diffSeconds = $now->getTimestamp() - $date->getTimestamp();
    if ($diffSeconds < 0) $diffSeconds = 0; // guard future timestamps

    if ($diffSeconds < 60) {
        return 'Just now';
    }
    if ($diffSeconds < 3600) {
        $mins = floor($diffSeconds / 60);
        return $mins . ' ' . ($mins == 1 ? 'minute' : 'minutes') . ' ago';
    }
    if ($diffSeconds < 86400) {
        $hrs = floor($diffSeconds / 3600);
        return $hrs . ' ' . ($hrs == 1 ? 'hr' : 'hrs') . ' ago';
    }
    if ($diffSeconds < 172800) { // < 2 days
        return 'Yesterday at ' . $date->format('H:i');
    }
    return $date->format('M j, Y H:i');
}

$role ??= null;
$college_name ??= null;
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/user-logs.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top">
            <div>
                <h1 class="mb-0 dashboard-title">User Activity Logs</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Viewing logs for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Monitor user login activities, timestamps, and access patterns.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="metrics-container">
        <div class="metric-card">
            <h3>Total Users</h3>
            <div class="metric-value"><?= $totalUsers ?></div>
            <div class="metric-change positive">
                <i class="fas fa-users"></i> Unique users
            </div>
            <div class="icon-container icon-users">
                <i class="fas fa-users"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Total Logins</h3>
            <div class="metric-value"><?= $totalLogins ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-sign-in-alt"></i> All attempts
            </div>
            <div class="icon-container icon-logins">
                <i class="fas fa-sign-in-alt"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Failed Attempts</h3>
            <div class="metric-value"><?= $failedAttempts ?></div>
            <div class="metric-change negative">
                <i class="fas fa-exclamation-triangle"></i> Security alerts
            </div>
            <div class="icon-container icon-failed">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Today's Logins</h3>
            <div class="metric-value"><?= $todayLogins ?></div>
            <div class="metric-change positive">
                <i class="fas fa-calendar-day"></i> Active today
            </div>
            <div class="icon-container icon-today">
                <i class="fas fa-calendar-day"></i>
            </div>
        </div>
    </div>

    <div class="filter-panel ul-filter-panel">
        <div class="filter-panel-title"><i class="fas fa-search"></i> Search & Filters</div>
        <form method="get" action="" class="ul-filter-form">
            <div class="filter-controls ul-filter-controls row g-3">
                <div class="col-md-4 filter-dropdown">
                    <label for="search">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Name or email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3 filter-dropdown">
                    <label for="user_filter">User</label>
                    <select class="form-select" id="user_filter" name="user_filter">
                        <option value="">All Users</option>
                        <?php while($user = $users->fetch_assoc()): ?>
                            <option value="<?= $user['id'] ?>" <?= ($userFilter == $user['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['full_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 filter-dropdown">
                    <label for="status_filter">Status</label>
                    <select class="form-select" id="status_filter" name="status_filter">
                        <option value="">All Status</option>
                        <option value="successful" <?= ($statusFilter == 'successful') ? 'selected' : '' ?>>Successful</option>
                        <option value="failed" <?= ($statusFilter == 'failed') ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-3 filter-dropdown">
                    <label>&nbsp;</label>
                    <div class="ul-filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="user-logs.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card shadow-sm dashboard-card ul-table-card">
        <div class="card-header">
            <h5 class="card-title mb-0">Activity Logs (<?= $totalRecords ?> records)</h5>
        </div>
        <div class="card-body">
            <?php if ($logs->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover ul-table mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?php $avatar = getUserAvatar($log['profile_photo'], $log['email'], $log['full_name']); ?>
                                                <?php if (strpos($avatar, '/') !== false): ?>
                                                    <img src="<?= $avatar ?>" alt="Avatar" class="rounded-circle" width="32" height="32">
                                                <?php else: ?>
                                                    <div class="avatar-initials rounded-circle d-flex align-items-center justify-content-center">
                                                        <?= $avatar ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($log['full_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($log['email']) ?></small>
                                                <div>
                                                    <span class="badge bg-secondary"><?= getRoleDisplayName($log['role']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-sign-in-alt log-action-icon me-2"></i>
                                            <span><?= $log['success'] ? 'Login' : 'Failed Login Attempt' ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $log['success'] ? 'success' : 'danger' ?>">
                                            <i class="fas fa-<?= $log['success'] ? 'check' : 'times' ?>"></i>
                                            <?= $log['success'] ? 'Successful' : 'Failed' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-medium"><?= formatDisplayTime($log['login_time'], 'M j, Y') ?></div>
                                            <small class="text-muted"><?= formatDisplayTime($log['login_time'], 'h:i A') ?></small>
                                            <div class="text-muted small"><?= formatRelativeFromSeconds($log['diff_seconds'], $log['login_time']) ?></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Logs pagination" class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center">
                            <!-- Previous -->
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Page numbers -->
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next -->
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="ul-empty-state">
                    <i class="fas fa-list-alt fa-3x"></i>
                    <h5>No activity logs found</h5>
                    <p class="mb-0">Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit on filter change
    document.querySelectorAll('#user_filter, #status_filter').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });

    // Auto-refresh every 30 seconds if no filters are applied
    const hasFilters = <?= json_encode(!empty($search) || !empty($userFilter) || !empty($statusFilter)) ?>;
    if (!hasFilters) {
        setInterval(function() {
            location.reload();
        }, 30000);
    }
});
</script>
