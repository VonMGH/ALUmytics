<?php
// Include database and access control (no output before handling POST)
include '../db/Database.php';
include 'includes/access_control.php';

// Check module access
requireModuleAccess('usermanagement');

$conn = Database::getInstance()->getConnection();

// AJAX endpoint: return full alumni details for modal (read-only)
if (isset($_GET['action']) && $_GET['action'] === 'get_alumni_details' && isset($_GET['user_id'])) {
    header('Content-Type: application/json');

    $requestedId = (int)$_GET['user_id'];

    // Allow users who have alumni-management/view permission or superadmin
    if (!hasPermission('can_manage_alumni') && $role !== 'superadmin') {
        echo json_encode(['error' => 'Access denied.']);
        exit;
    }

    // Fetch core user row (ensure user exists and is alumni)
    $stmt = $conn->prepare("SELECT user_id, full_name, email, role, college_id, created_at FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $requestedId);
    $stmt->execute();
    $userRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$userRow || ($userRow['role'] ?? '') !== 'alumni') {
        echo json_encode(['error' => 'User not found or not an alumni']);
        exit;
    }

    // Fetch personal and education in lightweight queries (avoid heavy subqueries)
    $personal = ['first_name'=>'','middle_name'=>'','last_name'=>'','phone_number'=>'','institutional_email'=>'','personal_email'=>''];
    $pstmt = $conn->prepare("SELECT first_name, middle_name, last_name, phone_number, institutional_email, personal_email FROM personal WHERE user_id = ? LIMIT 1");
    $pstmt->bind_param('i', $requestedId);
    $pstmt->execute();
    $pres = $pstmt->get_result()->fetch_assoc();
    $pstmt->close();
    if ($pres) $personal = array_merge($personal, $pres);

    $education = ['school_university'=>'','campus_branch'=>'','college_department'=>'','program'=>'','major_specialization'=>'','alumni_id'=>''];
    $est = $conn->prepare("SELECT school_university, campus_branch, college_department, program, major_specialization, alumni_id FROM education WHERE user_id = ? LIMIT 1");
    $est->bind_param('i', $requestedId);
    $est->execute();
    $ed = $est->get_result()->fetch_assoc();
    $est->close();
    if ($ed) $education = array_merge($education, $ed);

    // Employment (prefer current job)
    $employment = ['job_title' => '', 'employment_status' => '', 'mobility' => ''];
    $empstmt = $conn->prepare("SELECT job_title, employment_status, mobility FROM employment WHERE user_id = ? ORDER BY FIELD(job_status,'current') DESC, id DESC LIMIT 1");
    $empstmt->bind_param('i', $requestedId);
    $empstmt->execute();
    $er = $empstmt->get_result()->fetch_assoc();
    $empstmt->close();
    if ($er) $employment = array_merge($employment, $er);

    // Do NOT fetch certifications/awards in the initial (lite) response to keep response small/fast.
    // These are loaded lazily by the frontend when the modal is displayed.
    $certs = [];
    $awards = [];

    // Compose response
    $resp = [
        'user' => $userRow,
        'personal' => $personal,
        'education' => $education,
        'employment' => $employment,
        // certifications and awards omitted for fast/lite response
        'certifications' => $certs,
        'awards' => $awards
    ];

    header('Content-Type: application/json');
    echo json_encode($resp);
    exit;
}

    // Lightweight endpoint: get certifications for an alumni (lazy-loaded)
    if (isset($_GET['action']) && $_GET['action'] === 'get_alumni_certs' && isset($_GET['user_id'])) {
        header('Content-Type: application/json');
        $requestedId = (int)$_GET['user_id'];
        if (!hasPermission('can_manage_alumni') && $role !== 'superadmin') {
            echo json_encode(['error' => 'Access denied.']);
            exit;
        }

        $limit = 25;
        $cstmt = $conn->prepare("SELECT id, certification_name, category, issuing_body, certification_date FROM certifications WHERE user_id = ? ORDER BY certification_date DESC LIMIT ?");
        $cstmt->bind_param('ii', $requestedId, $limit);
        $cstmt->execute();
        $cres = $cstmt->get_result();
        $certs = [];
        while ($crow = $cres->fetch_assoc()) $certs[] = $crow;
        $cstmt->close();

        echo json_encode(['certifications' => $certs]);
        exit;
    }

    // Lightweight endpoint: get awards for an alumni (lazy-loaded)
    if (isset($_GET['action']) && $_GET['action'] === 'get_alumni_awards' && isset($_GET['user_id'])) {
        header('Content-Type: application/json');
        $requestedId = (int)$_GET['user_id'];
        if (!hasPermission('can_manage_alumni') && $role !== 'superadmin') {
            echo json_encode(['error' => 'Access denied.']);
            exit;
        }

        $limit = 25;
        $astmt = $conn->prepare("SELECT id, award_name, award_title, category, awarded_by, award_date, award_year, description FROM awards WHERE user_id = ? ORDER BY award_date DESC LIMIT ?");
        $astmt->bind_param('ii', $requestedId, $limit);
        $astmt->execute();
        $ares = $astmt->get_result();
        $awards = [];
        while ($arow = $ares->fetch_assoc()) $awards[] = $arow;
        $astmt->close();

        echo json_encode(['awards' => $awards]);
        exit;
    }

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
            if ($collegeId === null) {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, college_id, onboarded, created_at) VALUES (?, ?, ?, ?, NULL, 1, NOW())");
                $stmt->bind_param("ssss", $fullName, $email, $hashedPassword, $newUserRole);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, college_id, onboarded, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmt->bind_param("ssssi", $fullName, $email, $hashedPassword, $newUserRole, $collegeId);
            }
            
            if ($stmt->execute()) {
                // Set onboarded to 0 for newly created alumni accounts
                $newUserId = $conn->insert_id;
                if ($newUserRole === 'alumni') {
                    $updateStmt = $conn->prepare("UPDATE users SET onboarded = 0 WHERE user_id = ?");
                    $updateStmt->bind_param('i', $newUserId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
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
    $newRole = $_POST['edit_role'] ?? '';
    $collegeId = $_POST['edit_college_id'] ?? '';
    
    // Set college_id to NULL for admin and superadmin roles
    if (in_array($newRole, ['admin', 'superadmin'])) {
        $collegeId = null;
    }
    
    // Get current target user data to check permissions
    $currentUserStmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $currentUserStmt->bind_param("i", $userId);
    $currentUserStmt->execute();
    $currentUserData = $currentUserStmt->get_result()->fetch_assoc();
    
    $errors = [];
    if (empty($fullName)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($newRole)) $errors[] = "Role is required";
    // Allow users to edit their own account; otherwise enforce role management permission
    if ($userId != $_SESSION['user_id'] && !canManageUserRole($currentUserData['role'])) $errors[] = "You don't have permission to edit this user";
    if ($userId != $_SESSION['user_id'] && !canAddUserRole($newRole)) $errors[] = "You don't have permission to assign this role";
    
    if (empty($errors)) {
        // Check if email already exists for other users
        $checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $checkEmail->bind_param("si", $email, $userId);
        $checkEmail->execute();
        if ($checkEmail->get_result()->num_rows > 0) {
            $errors[] = "Email already exists";
        } else {
            // Update user
            if ($collegeId === null) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, college_id = NULL WHERE user_id = ?");
                $stmt->bind_param("sssi", $fullName, $email, $newRole, $userId);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, college_id = ? WHERE user_id = ?");
                $stmt->bind_param("sssii", $fullName, $email, $newRole, $collegeId, $userId);
            }
            
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

    // Fetch all colleges (colleges are kept in sync with departments via sys-departments.php)
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

        $insertStmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, college_id, onboarded, created_at) VALUES (?, ?, ?, 'coordinator', ?, 1, NOW())");
        $insertStmt->bind_param('sssi', $fullName, $emailToUse, $hashedPassword, $collegeId);
        if ($insertStmt->execute()) {
            $createdCount++;
        }
        $insertStmt->close();
    }

    $message = $createdCount > 0
        ? ("Created $createdCount default coordinator" . ($createdCount > 1 ? 's' : '') . " (password: $defaultPassword)")
        : 'All departments already have an account.';
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
    if ($role === 'coordinator') {
        // Include users tied by users.college_id OR alumni whose education.college_department matches the coordinator's college
        $where[] = "(u.college_id = ? OR (u.role = 'alumni' AND EXISTS (SELECT 1 FROM education e2 WHERE e2.user_id = u.user_id AND e2.college_department = (SELECT name FROM colleges WHERE id = ?))))";
        $params[] = $_SESSION['college_id'];
        $params[] = $_SESSION['college_id'];
        $types .= 'ii';
    } else {
        $where[] = "u.college_id = ?";
        $params[] = $_SESSION['college_id'];
        $types .= 'i';
    }
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
    $where[] = "(u.college_id = ? OR (u.role = 'alumni' AND EXISTS (SELECT 1 FROM education e2 WHERE e2.user_id = u.user_id AND e2.college_department = (SELECT name FROM colleges WHERE id = ?))))";
    $params[] = $collegeFilter;
    $params[] = $collegeFilter;
    $types .= 'ii';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Restrict list visibility based on current role
// Note: admins are allowed to view all users (admins will have view-only for other admins by permission checks later)
if ($role === 'coordinator') {
    // Coordinator can view coordinators and alumni (college scoping already applied above), plus self
    $roleFilterClause = "( (u.role = 'coordinator') OR (u.role = 'alumni') OR u.user_id = " . intval($_SESSION['user_id']) . ")";
    if ($whereSql) {
        $whereSql .= ' AND ' . $roleFilterClause;
    } else {
        $whereSql = 'WHERE ' . $roleFilterClause;
    }
}

// Get users data
$sql = "SELECT u.*, COALESCE(c.name, e.college_department) AS college_name, 
        CASE WHEN ll.last_login IS NULL THEN 'Never' 
             ELSE DATE_FORMAT(ll.last_login, '%M %d, %Y at %h:%i %p') 
        END as formatted_last_login
        FROM users u 
        LEFT JOIN colleges c ON u.college_id = c.id 
        LEFT JOIN education e ON u.user_id = e.user_id
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

$role ??= null;
$college_name ??= null;
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/usermanagement.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <h1 class="mb-0 dashboard-title">User Management</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Managing users for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Manage system users, roles, and permissions.</p>
                <?php endif; ?>
            </div>
            <div class="header-actions">
                <?php if (hasPermission('can_manage_users') && !empty($addableRoles)): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Add User
                </button>
                <?php endif; ?>
                <?php if (in_array($role, ['admin', 'superadmin'])): ?>
                <form method="post" action="" class="d-inline-block">
                    <input type="hidden" name="action" value="ensure_default_coordinators">
                    <button type="submit" class="btn btn-outline-success" onclick="return confirm('Ensure one coordinator per college? Default password: ChangeMe123!');">
                        <i class="fas fa-user-shield"></i> Ensure Coordinators per College
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
    <div class="page-alerts">
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
    </div>
    <?php endif; ?>
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

    <div class="filter-panel um-filter-panel">
        <div class="filter-panel-title"><i class="fas fa-search"></i> Search & Filters</div>
        <form method="get" action="" class="um-filter-form">
            <div class="filter-controls um-filter-controls row g-3">
                <div class="col-md-3 filter-dropdown">
                    <label for="search">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Name or email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 filter-dropdown">
                    <label for="role_filter">Role</label>
                    <select class="form-select" id="role_filter" name="role_filter">
                        <option value="">All Roles</option>
                        <?php foreach ($availableRoles as $roleOption): ?>
                            <option value="<?= $roleOption ?>" <?= ($roleFilter == $roleOption) ? 'selected' : '' ?>>
                                <?= getRoleDisplayName($roleOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!isCollegeRestricted()): ?>
                <div class="col-md-2 filter-dropdown">
                    <label for="college_filter">College</label>
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
                <div class="col-md-3 filter-dropdown">
                    <label>&nbsp;</label>
                    <div class="um-filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="usermanagement.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card shadow-sm dashboard-card um-table-card">
        <div class="card-header">
            <h5 class="card-title mb-0">Users (<?= $totalRecords ?> records)</h5>
        </div>
        <div class="card-body">
            <?php if ($users->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover um-table mb-0">
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
                                                    <div class="avatar-initials rounded-circle d-flex align-items-center justify-content-center">
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
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm justify-content-center">
                                            <?php 
                                            // Check if current user can manage this user's role
                                            $canManageThisUser = canManageUserRole($user['role']);
                                            $canEditThisUser = $canManageThisUser && $user['user_id'] != $_SESSION['user_id']; // Can't edit self
                                            $canDeleteThisUser = $canManageThisUser && $user['user_id'] != $_SESSION['user_id'] && $user['role'] != 'superadmin'; // Can't delete self or superadmin
                                            
                                            $hasActions = false; // Track if any actions are available
                                            ?>
                                            
                                            <?php if ($user['role'] === 'alumni' && (hasPermission('can_manage_alumni') || $role === 'superadmin')):
                                                $hasActions = true; ?>
                                                <button class="btn btn-outline-info btn-sm view-user-btn" 
                                                        data-user-id="<?= $user['user_id'] ?>"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($canEditThisUser): 
                                                $hasActions = true; ?>
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
                                            
                                            <?php if ($canManageThisUser && $user['user_id'] != $_SESSION['user_id']): 
                                                $hasActions = true; ?>
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
                                                        title="Archive User">
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if (empty($hasActions)): ?>
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
                <div class="um-empty-state">
                    <i class="fas fa-users fa-3x"></i>
                    <h5>No users found</h5>
                    <p class="mb-0">Try adjusting your search criteria.</p>
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
                    
                    <div class="mb-3" id="college-selection">
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
                    
                    <div class="mb-3" id="edit-college-selection">
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
                    <!-- Personal Information -->
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

                    <!-- Educational Information -->
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

                    <!-- Employment Information -->
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

                    <!-- Certifications -->
                    <div class="card mb-3" id="certifications-card" style="display: none;">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-certificate"></i> Certifications
                        </div>
                        <div class="card-body">
                            <div id="certifications-list"></div>
                        </div>
                    </div>

                    <!-- Awards -->
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

<!-- Archive User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Archive User</h5>
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
                        <label for="delete_confirmation" class="form-label">Type "ARCHIVE" to confirm:</label>
                        <input type="text" class="form-control" id="delete_confirmation" placeholder="Type ARCHIVE to confirm" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete_confirm_btn" disabled>Archive User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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

    // View Alumni Details
    document.querySelectorAll('.view-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            
            // Show loading state per-field (do NOT replace the modal DOM, otherwise element IDs are removed)
            const setLoading = id => {
                const el = document.getElementById(id);
                if (el) el.textContent = 'Loading...';
            };
            ['alumni-full-name','alumni-phone','alumni-email','alumni-school','alumni-campus','alumni-college','alumni-program','alumni-id','alumni-job-title','alumni-employment-status','alumni-mobility'].forEach(setLoading);
            const certsCard = document.getElementById('certifications-card');
            const certsList = document.getElementById('certifications-list');
            if (certsList) { certsList.innerHTML = '<div class="text-muted small">Loading certifications...</div>'; if (certsCard) certsCard.style.display = 'block'; }
            const awardsCard = document.getElementById('awards-card');
            const awardsList = document.getElementById('awards-list');
            if (awardsList) { awardsList.innerHTML = '<div class="text-muted small">Loading awards...</div>'; if (awardsCard) awardsCard.style.display = 'block'; }
            
            // Show the modal
            var viewModal = showModal('viewAlumniModal');
            if (viewModal) viewModal.show();
            
            // Fetch lite alumni details (fast) with timeout and robust parsing
            const fetchWithTimeout = (url, timeout = 8000) => {
                return Promise.race([
                    fetch(url),
                    new Promise((_, reject) => setTimeout(() => reject(new Error('Request timed out')), timeout))
                ]);
            };

            fetchWithTimeout(`usermanagement.php?action=get_alumni_details&user_id=${userId}`, 8000)
                .then(response => {
                    // Try to parse JSON safely; if server returns HTML or error text, show it
                    return response.text().then(text => {
                        try {
                            const json = JSON.parse(text);
                            return json;
                        } catch (e) {
                            throw new Error('Server returned non-JSON response: ' + (text.substring(0, 200)));
                        }
                    });
                })
                .then(data => {
                    if (data.error) throw new Error(data.error);

                    const updateElement = (id, value, defaultValue = 'Not provided') => {
                        const element = document.getElementById(id);
                        if (element) element.textContent = value || defaultValue;
                    };

                    // Personal / main info
                    updateElement('alumni-full-name', data.user?.full_name || `${data.personal.first_name || ''} ${data.personal.last_name || ''}`.trim());
                    updateElement('alumni-phone', data.personal?.phone_number);
                    updateElement('alumni-email', [data.personal?.institutional_email, data.personal?.personal_email].filter(Boolean).join(' / '));

                    // Education
                    updateElement('alumni-school', data.education?.school_university);
                    updateElement('alumni-campus', data.education?.campus_branch);
                    updateElement('alumni-college', data.education?.college_department);
                    updateElement('alumni-program', data.education?.program ? (data.education.program + (data.education.major_specialization ? ' - ' + data.education.major_specialization : '')) : data.education?.major_specialization);
                    updateElement('alumni-id', data.education?.alumni_id);

                    // Employment
                    const employmentStatus = (data.employment?.employment_status || '').toString();
                    const isUnemployed = employmentStatus.toLowerCase() === 'unemployed';

                    // Always show employment status
                    updateElement('alumni-employment-status', employmentStatus);

                    // Only show job title and mobility when not unemployed
                    updateElement('alumni-job-title', isUnemployed ? '' : (data.employment?.job_title || ''));
                    updateElement('alumni-mobility', isUnemployed ? '' : (data.employment?.mobility || ''));

                    // Prepare certs/awards area with a lightweight loader; fetch in background with timeout & robust parsing
                    if (certsList) {
                        certsList.innerHTML = '<div class="text-muted small">Loading certifications...</div>';
                        if (certsCard) certsCard.style.display = 'block';
                        fetchWithTimeout(`usermanagement.php?action=get_alumni_certs&user_id=${userId}`, 8000)
                            .then(r => r.text())
                            .then(text => {
                                try {
                                    const cc = JSON.parse(text);
                                    if (cc.error) throw new Error(cc.error);
                                    if (cc.certifications && cc.certifications.length) {
                                        certsList.innerHTML = cc.certifications.map(cert => `
                                            <div class="border-bottom pb-2 mb-2">
                                                <h6 class="mb-1">${cert.certification_name || 'Untitled'}</h6>
                                                <p class="mb-1 text-muted"><small>
                                                    <strong>Category:</strong> ${cert.category || 'N/A'}<br>
                                                    <strong>Issuing Body:</strong> ${cert.issuing_body || 'N/A'}<br>
                                                    <strong>Date:</strong> ${cert.certification_date ? new Date(cert.certification_date).toLocaleDateString() : 'N/A'}
                                                </small></p>
                                            </div>
                                        `).join('');
                                    } else {
                                        certsList.innerHTML = '<div class="text-muted small">No certifications found.</div>';
                                    }
                                } catch (e) {
                                    certsList.innerHTML = '<div class="text-muted small">Unable to load certifications.</div>';
                                }
                            })
                            .catch(() => { certsList.innerHTML = '<div class="text-muted small">Unable to load certifications.</div>'; });
                    }

                    if (awardsList) {
                        awardsList.innerHTML = '<div class="text-muted small">Loading awards...</div>';
                        if (awardsCard) awardsCard.style.display = 'block';
                        fetchWithTimeout(`usermanagement.php?action=get_alumni_awards&user_id=${userId}`, 8000)
                            .then(r => r.text())
                            .then(text => {
                                try {
                                    const aa = JSON.parse(text);
                                    if (aa.error) throw new Error(aa.error);
                                    if (aa.awards && aa.awards.length) {
                                        awardsList.innerHTML = aa.awards.map(award => `
                                            <div class="border-bottom pb-2 mb-2">
                                                <h6 class="mb-1">${award.award_title || 'Untitled'}</h6>
                                                <p class="mb-1 text-muted"><small>
                                                    <strong>Category:</strong> ${award.category || 'N/A'}<br>
                                                    <strong>Awarded By:</strong> ${award.awarded_by || 'N/A'}<br>
                                                    <strong>Date:</strong> ${award.award_date ? new Date(award.award_date).toLocaleDateString() : 'N/A'}<br>
                                                    ${award.description ? `<strong>Description:</strong> ${award.description}` : ''}
                                                </small></p>
                                            </div>
                                        `).join('');
                                    } else {
                                        awardsList.innerHTML = '<div class="text-muted small">No awards found.</div>';
                                    }
                                } catch (e) {
                                    awardsList.innerHTML = '<div class="text-muted small">Unable to load awards.</div>';
                                }
                            })
                            .catch(() => { awardsList.innerHTML = '<div class="text-muted small">Unable to load awards.</div>'; });
                    }

                })
                .catch(error => {
                    document.getElementById('alumniDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Error loading alumni details: ${error.message}
                        </div>
                    `;
                });
        });
    });
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
    const collegeSelection = document.getElementById('college-selection');
    const collegeSelectInput = document.getElementById('college_id');
    
    if (roleSelect && addUserSubmitBtn) {
        roleSelect.addEventListener('change', function() {
            const selectedRole = this.value;
            const addableRoles = <?= json_encode($addableRoles) ?>;
            
            // Hide/show college selection based on role
            if (selectedRole === 'admin' || selectedRole === 'superadmin') {
                collegeSelection.style.display = 'none';
                collegeSelectInput.value = ''; // Clear selection
                collegeSelectInput.removeAttribute('required');
            } else {
                collegeSelection.style.display = 'block';
                if (selectedRole === 'coordinator') {
                    collegeSelectInput.setAttribute('required', 'required');
                } else {
                    collegeSelectInput.removeAttribute('required');
                }
            }
            
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
    const editRoleSelect = document.getElementById('edit_role');
    const editCollegeSelection = document.getElementById('edit-college-selection');
    const editCollegeSelectInput = document.getElementById('edit_college_id');
    
    if (editRoleSelect) {
        editRoleSelect.addEventListener('change', function() {
            const selectedRole = this.value;
            
            // Hide/show college selection based on role
            if (selectedRole === 'admin' || selectedRole === 'superadmin') {
                editCollegeSelection.style.display = 'none';
                editCollegeSelectInput.value = ''; // Clear selection
                editCollegeSelectInput.removeAttribute('required');
            } else {
                editCollegeSelection.style.display = 'block';
                if (selectedRole === 'coordinator') {
                    editCollegeSelectInput.setAttribute('required', 'required');
                } else {
                    editCollegeSelectInput.removeAttribute('required');
                }
            }
        });
    }
    
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
            
            // Trigger role change event to hide/show college selection appropriately
            if (editRoleSelect) {
                const event = new Event('change');
                editRoleSelect.dispatchEvent(event);
            }

            var editModal = showModal('editUserModal');
            if (editModal) editModal.show();
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

            var resetModal = showModal('resetPasswordModal');
            if (resetModal) resetModal.show();
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

            var deleteModal = showModal('deleteUserModal');
            if (deleteModal) deleteModal.show();
        });
    });

    // Delete confirmation validation
    const deleteConfirmationField = document.getElementById('delete_confirmation');
    const deleteConfirmBtn = document.getElementById('delete_confirm_btn');

    if (deleteConfirmationField && deleteConfirmBtn) {
        deleteConfirmationField.addEventListener('input', function() {
            if (this.value.toUpperCase() === 'ARCHIVE') {
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