<?php
// Include database and access control (no output before handling POST)
include '../db/Database.php';
include 'includes/access_control.php';

// Check module access
requireModuleAccess('usermanagement');

$conn = Database::getInstance()->getConnection();

// Handle Add User form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $newUserRole = $_POST['role'] ?? '';
    $collegeId = $_POST['college_id'] ?? '';
    
    // Set college_id to NULL for admin and superadmin roles
    if (in_array($newUserRole, ['admin', 'superadmin'])) {
        $collegeId = null;
    }
    
    // Validation
    $errors = [];
    if (empty($fullName)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($password)) $errors[] = "Password is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if (empty($newUserRole)) $errors[] = "Role is required";
    if (!canAddUserRole($newUserRole)) $errors[] = "You don't have permission to add this role";
    
    if (empty($errors)) {
        // Check if email already exists
        $checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        if ($checkEmail->get_result()->num_rows > 0) {
            $errors[] = "Email already exists";
        } else {
            // Hash password and insert user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, college_id, email_verified, onboarded, created_at) VALUES (?, ?, ?, ?, ?, 1, 1, NOW())");
            $stmt->bind_param("ssssi", $fullName, $email, $hashedPassword, $newUserRole, $collegeId);
            
            if ($stmt->execute()) {
                header('Location: usermanagement.php?success=User added successfully');
                exit;
            } else {
                $errors[] = "Failed to add user";
            }
        }
    }
    
    if (!empty($errors)) {
        header('Location: usermanagement.php?error=' . urlencode(implode(', ', $errors)));
        exit;
    }
}

// Handle Edit User form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $fullName = trim($_POST['edit_full_name'] ?? '');
    $email = trim($_POST['edit_email'] ?? '');
    $role = $_POST['edit_role'] ?? '';
    $collegeId = $_POST['edit_college_id'] ?? '';
    
    // Set college_id to NULL for admin and superadmin roles
    if (in_array($role, ['admin', 'superadmin'])) {
        $collegeId = null;
    }
    
    // Get current user data to check permissions
    $currentUserStmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $currentUserStmt->bind_param("i", $userId);
    $currentUserStmt->execute();
    $currentUserData = $currentUserStmt->get_result()->fetch_assoc();
    
    $errors = [];
    if (empty($fullName)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($role)) $errors[] = "Role is required";
    if (!canManageUserRole($currentUserData['role'])) $errors[] = "You don't have permission to edit this user";
    if (!canAddUserRole($role)) $errors[] = "You don't have permission to assign this role";
    
    if (empty($errors)) {
        // Check if email already exists for other users
        $checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $checkEmail->bind_param("si", $email, $userId);
        $checkEmail->execute();
        if ($checkEmail->get_result()->num_rows > 0) {
            $errors[] = "Email already exists";
        } else {
            // Update user
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, college_id = ? WHERE user_id = ?");
            $stmt->bind_param("sssii", $fullName, $email, $role, $collegeId, $userId);
            
            if ($stmt->execute()) {
                header('Location: usermanagement.php?success=User updated successfully');
                exit;
            } else {
                $errors[] = "Failed to update user";
            }
        }
    }
    
    if (!empty($errors)) {
        header('Location: usermanagement.php?error=' . urlencode(implode(', ', $errors)));
        exit;
    }
}

// Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    
    // Get current user data to check permissions
    $currentUserStmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $currentUserStmt->bind_param("i", $userId);
    $currentUserStmt->execute();
    $currentUserData = $currentUserStmt->get_result()->fetch_assoc();
    
    $errors = [];
    if (empty($newPassword)) $errors[] = "New password is required";
    if (strlen($newPassword) < 6) $errors[] = "Password must be at least 6 characters";
    if (!canManageUserRole($currentUserData['role'])) $errors[] = "You don't have permission to reset this user's password";
    
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        
        if ($stmt->execute()) {
            header('Location: usermanagement.php?success=Password reset successfully');
            exit;
        } else {
            $errors[] = "Failed to reset password";
        }
    }
    
    if (!empty($errors)) {
        header('Location: usermanagement.php?error=' . urlencode(implode(', ', $errors)));
        exit;
    }
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    
    // Get current user data to check permissions
    $currentUserStmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $currentUserStmt->bind_param("i", $userId);
    $currentUserStmt->execute();
    $currentUserData = $currentUserStmt->get_result()->fetch_assoc();
    
    $errors = [];
    if ($userId == $_SESSION['user_id']) $errors[] = "You cannot delete your own account";
    if ($currentUserData['role'] == 'superadmin') $errors[] = "Cannot delete super admin accounts";
    if (!canManageUserRole($currentUserData['role'])) $errors[] = "You don't have permission to delete this user";
    
    if (empty($errors)) {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            header('Location: usermanagement.php?success=User deleted successfully');
            exit;
        } else {
            $errors[] = "Failed to delete user";
        }
    }
    
    if (!empty($errors)) {
        header('Location: usermanagement.php?error=' . urlencode(implode(', ', $errors)));
        exit;
    }
}

// Handle Ensure Default Coordinators (one per college)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ensure_default_coordinators') {
    // Only Admin and Superadmin can generate default coordinators
    if (!in_array($role, ['admin', 'superadmin'])) {
        header('Location: usermanagement.php?error=' . urlencode("You don't have permission to perform this action"));
        exit;
    }

    $createdCount = 0;
    $defaultPassword = 'ChangeMe123!';
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

    // Helper to slugify college names
    $slugify = function($text) {
        $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = strtolower($text);
        $text = preg_replace('~[^-a-z0-9]+~', '', $text);
        return $text ?: 'college';
    };

    // Fetch all colleges
    $collegeRes = $conn->query("SELECT id, name FROM colleges ORDER BY name");
    while ($college = $collegeRes->fetch_assoc()) {
        $collegeId = (int)$college['id'];
        $collegeName = $college['name'];

        // Check if a coordinator exists for this college
        $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = 'coordinator' AND college_id = ?");
        $checkStmt->bind_param('i', $collegeId);
        $checkStmt->execute();
        $cnt = (int)$checkStmt->get_result()->fetch_assoc()['cnt'];
        $checkStmt->close();

        if ($cnt > 0) {
            continue; // Already has a coordinator
        }

        // Generate default coordinator account for this college
        $slug = $slugify($collegeName);
        $baseEmail = "coord.$slug@univ.edu"; // predictable pattern
        $emailToUse = $baseEmail;

        // Ensure unique email if pattern already taken
        $suffix = 1;
        $emailCheck = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        while (true) {
            $emailCheck->bind_param('s', $emailToUse);
            $emailCheck->execute();
            $emailExists = $emailCheck->get_result()->num_rows > 0;
            if (!$emailExists) break;
            $emailToUse = "coord.$slug$" . $suffix . "@univ.edu";
            $suffix++;
        }
        $emailCheck->close();

        $fullName = $collegeName . ' Coordinator';

        $insertStmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, college_id, email_verified, onboarded, created_at) VALUES (?, ?, ?, 'coordinator', ?, 1, 1, NOW())");
        $insertStmt->bind_param('sssi', $fullName, $emailToUse, $hashedPassword, $collegeId);
        if ($insertStmt->execute()) {
            $createdCount++;
        }
        $insertStmt->close();
    }

    $message = $createdCount > 0
        ? ("Created $createdCount default coordinator" . ($createdCount > 1 ? 's' : '') . " (password: $defaultPassword)")
        : 'All colleges already have a coordinator.';
    header('Location: usermanagement.php?success=' . urlencode($message));
    exit;
}

// Get filter values
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role_filter'] ?? '';
$collegeFilter = $_GET['college_filter'] ?? '';
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

// Apply search filter
if ($search) {
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

// Apply role filter
if ($roleFilter) {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

// Apply college filter (for non-restricted users)
if ($collegeFilter && !isCollegeRestricted()) {
    $where[] = "u.college_id = ?";
    $params[] = $collegeFilter;
    $types .= 'i';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get users data
$sql = "SELECT u.*, c.name AS college_name, 
        CASE WHEN ll.last_login IS NULL THEN 'Never' 
             ELSE DATE_FORMAT(ll.last_login, '%M %d, %Y at %h:%i %p') 
        END as formatted_last_login
        FROM users u 
        LEFT JOIN colleges c ON u.college_id = c.id 
        LEFT JOIN (
            SELECT user_id, MAX(login_time) as last_login
            FROM login_logs
            GROUP BY user_id
        ) ll ON u.user_id = ll.user_id
        $whereSql 
        ORDER BY u.created_at DESC 
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
$users = $stmt->get_result();

// Get total count
$countSql = "SELECT COUNT(*) as total FROM users u LEFT JOIN colleges c ON u.college_id = c.id $whereSql";
$countStmt = $conn->prepare($countSql);
if ($params && count($params) > 2) {
    $countParams = array_slice($params, 0, -2);
    $countTypes = substr($types, 0, -2);
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Summary statistics (use same filters/params; avoid raw $whereSql with placeholders)
// Total users equals $totalRecords from the filtered count above
$totalUsers = (int)$totalRecords;

// Prepare reusable count params (without LIMIT/OFFSET)
$countWhereSql = $whereSql;
// Recompute count params/types from $params/$types which include limit/offset at the end
$countParams = $params ? array_slice($params, 0, -2) : [];
$countTypes = $params ? substr($types, 0, -2) : '';

// Alumni count
$alumniWhereSql = $countWhereSql ? ($countWhereSql . " AND u.role = 'alumni'") : "WHERE u.role = 'alumni'";
$alumniSql = "SELECT COUNT(*) as total FROM users u LEFT JOIN colleges c ON u.college_id = c.id $alumniWhereSql";
$alumniStmt = $conn->prepare($alumniSql);
if (!empty($countParams)) {
    $alumniStmt->bind_param($countTypes, ...$countParams);
}
$alumniStmt->execute();
$alumniCount = (int)$alumniStmt->get_result()->fetch_assoc()['total'];
$alumniStmt->close();

// Colleges count
$collegesSql = "SELECT COUNT(DISTINCT u.college_id) as total FROM users u LEFT JOIN colleges c ON u.college_id = c.id $countWhereSql";
$collegesStmt = $conn->prepare($collegesSql);
if (!empty($countParams)) {
    $collegesStmt->bind_param($countTypes, ...$countParams);
}
$collegesStmt->execute();
$collegesCount = (int)$collegesStmt->get_result()->fetch_assoc()['total'];
$collegesStmt->close();

// Get filter options
$roles = ['alumni', 'coordinator', 'admin', 'superadmin'];
$availableRoles = [];
foreach ($roles as $roleOption) {
    if (canAccessRole($roleOption)) {
        $availableRoles[] = $roleOption;
    }
}

// Get roles that can be added by current user
$addableRoles = [];
foreach ($roles as $roleOption) {
    if (canAddUserRole($roleOption)) {
        $addableRoles[] = $roleOption;
    }
}

// Get colleges for filter and add user form
$collegeQuery = isCollegeRestricted() ? 
    "SELECT * FROM colleges WHERE id = ?" : 
    "SELECT * FROM colleges ORDER BY name";
$collegeStmt = $conn->prepare($collegeQuery);
if (isCollegeRestricted()) {
    $collegeStmt->bind_param('i', $_SESSION['college_id']);
}
$collegeStmt->execute();
$colleges = $collegeStmt->get_result();

// Summary statistics are computed above using prepared statements

// Helper function to get user avatar
function getUserAvatar($email, $fullName) {
    $avatarPath = '../uploads/profile_pictures/' . md5($email) . '.jpg';
    if (file_exists($avatarPath)) {
        return $avatarPath;
    }
    // Generate initials
    $names = explode(' ', $fullName);
    $initials = '';
    foreach ($names as $name) {
        $initials .= strtoupper(substr($name, 0, 1));
    }
    return $initials;
}
// Include layout after PHP processing to allow header redirects during POST handling
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.btn-group-sm .btn {
    margin: 0 1px;
}

.action-column {
    min-width: 120px;
}

.user-avatar img, .avatar-initials {
    transition: transform 0.2s ease;
}

.user-avatar:hover img, .user-avatar:hover .avatar-initials {
    transform: scale(1.1);
}

.modal-header {
    border-bottom: 2px solid #dee2e6;
}

.alert {
    border: none;
    border-radius: 8px;
}

.alert-warning {
    background: linear-gradient(45deg, #fff3cd, #ffeaa7);
    border-left: 4px solid #ffc107;
}

.alert-danger {
    background: linear-gradient(45deg, #f8d7da, #fab1a0);
    border-left: 4px solid #dc3545;
}

.alert-info {
    background: linear-gradient(45deg, #d1ecf1, #74b9ff);
    border-left: 4px solid #0dcaf0;
}

.form-control.is-valid {
    border-color: #28a745;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.85-.85c.32-.32.77-.32 1.09 0l.85.85c.32.32.32.77 0 1.09l-.85.85c-.32.32-.77.32-1.09 0l-.85-.85c-.32-.32-.32-.77 0-1.09zm2.8-3.8c.32-.32.77-.32 1.09 0l.85.85c.32.32.32.77 0 1.09l-.85.85c-.32.32-.77.32-1.09 0l-.85-.85c-.32-.32-.32-.77 0-1.09l.85-.85z'/%3e%3c/svg%3e");
}

.form-control.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 5.8 4.4 4.4m0-4.4-4.4 4.4'/%3e%3c/svg%3e");
}
</style>

<div class="content-wrapper">
<main class="main-content">
    <div class="header">
        <div class="header-top">
            <div>
                <h1>User Management</h1>
                <?php if (isCollegeRestricted() && $college_name): ?>
                    <p class="text-muted">Managing users for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php endif; ?>
                <p class="text-muted">Manage system users, roles, and permissions.</p>
            </div>
            <div class="header-right">
                <?php if (hasPermission('can_manage_users') && !empty($addableRoles)): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Add User
                </button>
                <?php endif; ?>
                <?php if (in_array($role, ['admin', 'superadmin'])): ?>
                <form method="post" action="" class="d-inline-block ms-2">
                    <input type="hidden" name="action" value="ensure_default_coordinators">
                    <button type="submit" class="btn btn-outline-success" onclick="return confirm('Ensure one coordinator per college? Default password: ChangeMe123!');">
                        <i class="fas fa-user-shield"></i> Ensure Coordinators per College
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="metrics-container">
        <div class="metric-card">
            <h3>Total Users</h3>
            <div class="metric-value"><?= $totalUsers ?></div>
            <div class="metric-change positive">
                <i class="fas fa-users"></i> All users
            </div>
            <div class="icon-container icon-users">
                <i class="fas fa-users"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Alumni Count</h3>
            <div class="metric-value"><?= $alumniCount ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-user-graduate"></i> Alumni users
            </div>
            <div class="icon-container icon-alumni">
                <i class="fas fa-user-graduate"></i>
            </div>
        </div>
        
        <div class="metric-card">
            <h3>Colleges</h3>
            <div class="metric-value"><?= $collegesCount ?></div>
            <div class="metric-change neutral">
                <i class="fas fa-university"></i> Represented
            </div>
            <div class="icon-container icon-colleges">
                <i class="fas fa-university"></i>
            </div>
        </div>
    </div>

    <br>
    <!-- Search and Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Name or email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label for="role_filter" class="form-label">Role</label>
                    <select class="form-select" id="role_filter" name="role_filter">
                        <option value="">All Roles</option>
                        <?php foreach ($availableRoles as $role): ?>
                            <option value="<?= $role ?>" <?= ($roleFilter == $role) ? 'selected' : '' ?>>
                                <?= getRoleDisplayName($role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!isCollegeRestricted()): ?>
                <div class="col-md-2">
                    <label for="college_filter" class="form-label">College</label>
                    <select class="form-select" id="college_filter" name="college_filter">
                        <option value="">All Colleges</option>
                        <?php $colleges->data_seek(0); while($college = $colleges->fetch_assoc()): ?>
                            <option value="<?= $college['id'] ?>" <?= ($collegeFilter == $college['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($college['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="usermanagement.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="card-title mb-0">Users (<?= $totalRecords ?> records)</h5>
        </div>
        <div class="card-body">
            <?php if ($users->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>College</th>
                                <th>Last Active</th>
                                <th class="action-column text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?php $avatar = getUserAvatar($user['email'], $user['full_name']); ?>
                                                <?php if (strpos($avatar, '/') !== false): ?>
                                                    <img src="<?= $avatar ?>" alt="Avatar" class="rounded-circle" width="40" height="40">
                                                <?php else: ?>
                                                    <div class="avatar-initials rounded-circle d-flex align-items-center justify-content-center" 
                                                         style="width:40px;height:40px;background:#28a745;color:white;font-weight:bold;">
                                                        <?= $avatar ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= getRoleDisplayName($user['role']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($user['college_name'] ?: 'Not assigned') ?></td>
                                    <td>
                                        <small class="text-muted"><?= $user['formatted_last_login'] ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php 
                                            // Check if current user can manage this user's role
                                            $canManageThisUser = canManageUserRole($user['role']);
                                            $canEditThisUser = $canManageThisUser && $user['user_id'] != $_SESSION['user_id']; // Can't edit self
                                            $canDeleteThisUser = $canManageThisUser && $user['user_id'] != $_SESSION['user_id'] && $user['role'] != 'superadmin'; // Can't delete self or superadmin
                                            ?>
                                            
                                            <?php if ($canEditThisUser): ?>
                                                <button class="btn btn-outline-primary btn-sm edit-user-btn" 
                                                        data-user-id="<?= $user['user_id'] ?>"
                                                        data-user-name="<?= htmlspecialchars($user['full_name']) ?>"
                                                        data-user-email="<?= htmlspecialchars($user['email']) ?>"
                                                        data-user-role="<?= $user['role'] ?>"
                                                        data-user-college="<?= $user['college_id'] ?>"
                                                        title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($canManageThisUser && $user['user_id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-outline-warning btn-sm reset-password-btn" 
                                                        data-user-id="<?= $user['user_id'] ?>"
                                                        data-user-name="<?= htmlspecialchars($user['full_name']) ?>"
                                                        title="Reset Password">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($canDeleteThisUser): ?>
                                                <button class="btn btn-outline-danger btn-sm delete-user-btn" 
                                                        data-user-id="<?= $user['user_id'] ?>"
                                                        data-user-name="<?= htmlspecialchars($user['full_name']) ?>"
                                                        data-user-role="<?= getRoleDisplayName($user['role']) ?>"
                                                        title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if (empty(array_filter([$canEditThisUser, $canDeleteThisUser]))): ?>
                                                <span class="text-muted small">No actions</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="User pagination" class="mt-3">
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
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No users found</h5>
                    <p class="text-muted">Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <?php if (empty($addableRoles)): ?>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>No Permission</strong><br>
                    You don't have permission to add any user roles. Contact your system administrator if you need additional permissions.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
            <?php else: ?>
            <form id="addUserForm" method="post" action="">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Role Permissions:</strong><br>
                        <?php
                        switch ($role) {
                            case 'superadmin':
                                echo "As a Super Administrator, you can create all types of users.";
                                break;
                            case 'admin':
                                echo "As an Administrator, you can create Alumni Coordinators and Alumni users.";
                                break;
                            case 'coordinator':
                                echo "As an Alumni Coordinator, you can only create Alumni users.";
                                break;
                            default:
                                echo "Contact your administrator for user creation permissions.";
                        }
                        ?>
                    </div>
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="6">
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role *</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <?php foreach ($addableRoles as $roleOption): ?>
                                <option value="<?= $roleOption ?>"><?= getRoleDisplayName($roleOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($addableRoles)): ?>
                            <small class="form-text text-muted text-danger">You don't have permission to add any user roles.</small>
                        <?php else: ?>
                            <small class="form-text text-muted">
                                <?php
                                $roleDescriptions = [
                                    'alumni' => 'Can view and update their own profile',
                                    'coordinator' => 'Can manage alumni users within their college',
                                    'admin' => 'Can manage coordinators and alumni across all colleges',
                                    'superadmin' => 'Full system access and user management'
                                ];
                                echo "Available roles: ";
                                $descriptions = [];
                                foreach ($addableRoles as $roleOption) {
                                    $descriptions[] = getRoleDisplayName($roleOption) . " - " . $roleDescriptions[$roleOption];
                                }
                                echo implode('; ', $descriptions);
                                ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="college_id" class="form-label">College</label>
                        <select class="form-select" id="college_id" name="college_id">
                            <option value="">Select College</option>
                            <?php $colleges->data_seek(0); while($college = $colleges->fetch_assoc()): ?>
                                <option value="<?= $college['id'] ?>"><?= htmlspecialchars($college['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUserForm" method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="edit_full_name" name="edit_full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="edit_email" name="edit_email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role *</label>
                        <select class="form-select" id="edit_role" name="edit_role" required>
                            <option value="">Select Role</option>
                            <?php foreach ($addableRoles as $roleOption): ?>
                                <option value="<?= $roleOption ?>"><?= getRoleDisplayName($roleOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_college_id" class="form-label">College</label>
                        <select class="form-select" id="edit_college_id" name="edit_college_id">
                            <option value="">Select College</option>
                            <?php $colleges->data_seek(0); while($college = $colleges->fetch_assoc()): ?>
                                <option value="<?= $college['id'] ?>"><?= htmlspecialchars($college['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="resetPasswordForm" method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This will reset the password for user <span id="reset_user_name_display"></span>.
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" required minlength="6">
                        <small class="form-text text-muted">Must match the new password</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteUserForm" method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action cannot be undone!
                    </div>
                    
                    <p>Are you sure you want to delete the following user?</p>
                    
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title" id="delete_user_name_display"></h6>
                            <p class="card-text">
                                <strong>Role:</strong> <span id="delete_user_role_display"></span><br>
                                <strong>Email:</strong> <span id="delete_user_email_display"></span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label for="delete_confirmation" class="form-label">Type "DELETE" to confirm:</label>
                        <input type="text" class="form-control" id="delete_confirmation" placeholder="Type DELETE to confirm" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete_confirm_btn" disabled>Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit on filter change
    document.querySelectorAll('#role_filter, #college_filter').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });

    // Reset forms when modals are closed
    document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('addUserForm').reset();
    });

    document.getElementById('editUserModal')?.addEventListener('hidden.bs.modal', function() {
        document.getElementById('editUserForm').reset();
    });

    document.getElementById('resetPasswordModal')?.addEventListener('hidden.bs.modal', function() {
        document.getElementById('resetPasswordForm').reset();
    });

    document.getElementById('deleteUserModal')?.addEventListener('hidden.bs.modal', function() {
        document.getElementById('deleteUserForm').reset();
        document.getElementById('delete_confirmation').value = '';
        document.getElementById('delete_confirm_btn').disabled = true;
    });

    // Role selection feedback for add user
    const roleSelect = document.getElementById('role');
    const addUserSubmitBtn = document.querySelector('#addUserForm button[type="submit"]');
    
    if (roleSelect && addUserSubmitBtn) {
        roleSelect.addEventListener('change', function() {
            const selectedRole = this.value;
            const addableRoles = <?= json_encode($addableRoles) ?>;
            
            if (selectedRole && !addableRoles.includes(selectedRole)) {
                addUserSubmitBtn.disabled = true;
                addUserSubmitBtn.textContent = 'Permission Denied';
                addUserSubmitBtn.classList.remove('btn-primary');
                addUserSubmitBtn.classList.add('btn-danger');
            } else {
                addUserSubmitBtn.disabled = false;
                addUserSubmitBtn.innerHTML = '<i class="fas fa-plus"></i> Add User';
                addUserSubmitBtn.classList.remove('btn-danger');
                addUserSubmitBtn.classList.add('btn-primary');
            }
        });
    }

    // Edit User Modal
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            const userEmail = this.dataset.userEmail;
            const userRole = this.dataset.userRole;
            const userCollege = this.dataset.userCollege;

            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_full_name').value = userName;
            document.getElementById('edit_email').value = userEmail;
            document.getElementById('edit_role').value = userRole;
            document.getElementById('edit_college_id').value = userCollege || '';

            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        });
    });

    // Reset Password Modal
    document.querySelectorAll('.reset-password-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;

            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_user_name_display').textContent = userName;
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';

            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        });
    });

    // Password confirmation validation
    const newPasswordField = document.getElementById('new_password');
    const confirmPasswordField = document.getElementById('confirm_password');
    const resetPasswordSubmitBtn = document.querySelector('#resetPasswordForm button[type="submit"]');

    function validatePasswordMatch() {
        if (newPasswordField && confirmPasswordField && resetPasswordSubmitBtn) {
            const newPassword = newPasswordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            if (newPassword && confirmPassword) {
                if (newPassword === confirmPassword) {
                    confirmPasswordField.classList.remove('is-invalid');
                    confirmPasswordField.classList.add('is-valid');
                    resetPasswordSubmitBtn.disabled = false;
                } else {
                    confirmPasswordField.classList.remove('is-valid');
                    confirmPasswordField.classList.add('is-invalid');
                    resetPasswordSubmitBtn.disabled = true;
                }
            } else {
                confirmPasswordField.classList.remove('is-valid', 'is-invalid');
                resetPasswordSubmitBtn.disabled = true;
            }
        }
    }

    if (newPasswordField && confirmPasswordField) {
        newPasswordField.addEventListener('input', validatePasswordMatch);
        confirmPasswordField.addEventListener('input', validatePasswordMatch);
    }

    // Delete User Modal
    document.querySelectorAll('.delete-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            const userRole = this.dataset.userRole;
            const userEmail = btn.closest('tr').querySelector('td:nth-child(1) small').textContent;

            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name_display').textContent = userName;
            document.getElementById('delete_user_role_display').textContent = userRole;
            document.getElementById('delete_user_email_display').textContent = userEmail;
            document.getElementById('delete_confirmation').value = '';
            document.getElementById('delete_confirm_btn').disabled = true;

            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        });
    });

    // Delete confirmation validation
    const deleteConfirmationField = document.getElementById('delete_confirmation');
    const deleteConfirmBtn = document.getElementById('delete_confirm_btn');

    if (deleteConfirmationField && deleteConfirmBtn) {
        deleteConfirmationField.addEventListener('input', function() {
            if (this.value.toUpperCase() === 'DELETE') {
                deleteConfirmBtn.disabled = false;
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                deleteConfirmBtn.disabled = true;
                this.classList.remove('is-valid');
                if (this.value.length > 0) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            }
        });
    }
});
</script>