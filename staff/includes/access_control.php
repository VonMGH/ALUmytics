<?php
// Use a dedicated session for the staff portal so alumni logout doesn't clear staff state
if (session_status() === PHP_SESSION_NONE) {
    session_name('staff_session');
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once __DIR__ . '/../../db/Database.php';
$conn = Database::getInstance()->getConnection();

// Get user information including role and college assignment
$stmt = $conn->prepare("SELECT u.user_id, u.full_name, u.email, u.role, u.college_id, c.name as college_name 
                       FROM users u 
                       LEFT JOIN colleges c ON u.college_id = c.id 
                       WHERE u.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$userId = $user['user_id'];
$userName = $user['full_name'];
$userEmail = $user['email'];

global $role, $college_id, $college_name;
$role = $user['role'];
$college_id = $user['college_id'] ?? null;
$college_name = $user['college_name'] ?? null;

// Define role-based permissions
$permissions = [
    'superadmin' => [
        'can_view_all_data' => true,
        'can_manage_users' => true,
        'can_manage_admins' => true,
        'can_manage_coordinators' => true,
        'can_manage_alumni' => true,
        'can_backup_restore' => true,
        'can_view_user_logs' => true,
        'college_restricted' => false,
        'available_filters' => ['school', 'campus', 'college', 'major', 'year'],
        'dashboard_modules' => ['demographics', 'employment', 'certification', 'geography', 'campus-analysis', 'user-logs', 'usermanagement', 'backup-restore', 'report-generation']
    ],
    'admin' => [
        'can_view_all_data' => true,
        'can_manage_users' => true,
        'can_manage_admins' => false,
        'can_manage_coordinators' => true,
        'can_manage_alumni' => true,
        'can_backup_restore' => false,
        'can_view_user_logs' => true,
        'college_restricted' => false,
        'available_filters' => ['school', 'campus', 'college', 'major', 'year'],
        'dashboard_modules' => ['demographics', 'employment', 'certification', 'geography', 'campus-analysis', 'user-logs', 'usermanagement', 'report-generation']
    ],
    'coordinator' => [
        'can_view_all_data' => false,
        'can_manage_users' => true,
        'can_manage_admins' => false,
        'can_manage_coordinators' => false,
        'can_manage_alumni' => true,
        'can_backup_restore' => false,
        'can_view_user_logs' => false,
        'college_restricted' => true,
        'available_filters' => ['school', 'major', 'year'],
        'dashboard_modules' => ['demographics', 'employment', 'certification', 'geography', 'campus-analysis', 'usermanagement', 'report-generation']
    ]
];

// Get current user permissions
$userPermissions = $permissions[$role] ?? $permissions['coordinator'];

// Helper functions for permission checking
function hasPermission($permission) {
    global $userPermissions;
    return $userPermissions[$permission] ?? false;
}

function canAccessModule($module) {
    global $userPermissions;
    return in_array($module, $userPermissions['dashboard_modules'] ?? []);
}

function isCollegeRestricted() {
    global $userPermissions;
    return $userPermissions['college_restricted'] ?? true;
}

function getAvailableFilters() {
    global $userPermissions;
    return $userPermissions['available_filters'] ?? [];
}

function canManageUserRole($targetRole) {
    global $role, $userPermissions;
    
    if (!$userPermissions['can_manage_users']) {
        return false;
    }
    // Do not allow managing 'alumni' via this role-management helper; alumni are handled separately
    if ($targetRole === 'alumni') return false;

    // Superadmin can manage any non-alumni roles
    if ($role === 'superadmin') return true;

    // Admin: may view other admins (no actions) but can manage coordinators
    if ($role === 'admin') {
        return $targetRole === 'coordinator';
    }

    // Coordinator: no cross-role management via this helper (coordinators typically cannot create other coordinators/admins)
    if ($role === 'coordinator') {
        return false;
    }

    return false;
}

// Function to check if user can add a specific role
function canAddUserRole($targetRole) {
    global $role;
    
    switch ($role) {
        case 'superadmin':
            // Super Admin can add all types of users
            return in_array($targetRole, ['alumni', 'coordinator', 'admin', 'superadmin']);
            
        case 'admin':
            // Admin can add Alumni Coordinators and Alumni, but not other Admins or Super Admins
            return in_array($targetRole, ['alumni', 'coordinator']);
            
        case 'coordinator':
            // Alumni Coordinator can only add Alumni (cannot add another Alumni Coordinator)
            return $targetRole === 'alumni';
            
        case 'alumni':
            // Alumni cannot add any users
            return false;
            
        default:
            return false;
    }
}

// Add canAccessRole function for usermanagement.php
function canAccessRole($targetRole) {
    global $role, $userPermissions;
    // Superadmin can access all roles
    if ($role === 'superadmin') return true;
    // Admin can access admins only (view)
    if ($role === 'admin' && $targetRole === 'admin') return true;
    // Coordinator can access coordinator and alumni (further college filtering applied in queries)
    if ($role === 'coordinator' && in_array($targetRole, ['alumni', 'coordinator'])) return true;
    // Alumni can only access alumni
    if ($role === 'alumni' && $targetRole === 'alumni') return true;
    return false;
}

// Apply college restrictions to queries
function applyCollegeRestrictions($baseQuery, $tableAlias = 'e') {
    global $college_id, $userPermissions;
    
    if ($userPermissions['college_restricted'] && $college_id) {
        // Add college restriction to WHERE clause
        if (strpos(strtolower($baseQuery), 'where') !== false) {
            $baseQuery .= " AND {$tableAlias}.college_department = (SELECT name FROM colleges WHERE id = {$college_id})";
        } else {
            $baseQuery .= " WHERE {$tableAlias}.college_department = (SELECT name FROM colleges WHERE id = {$college_id})";
        }
    }
    
    return $baseQuery;
}

// Get filter options based on role permissions
function getFilterOptions($conn) {
    global $userPermissions, $college_id;
    
    $options = [];
    $availableFilters = $userPermissions['available_filters'];
    
    $notBlank = function($column) {
        return "$column IS NOT NULL AND TRIM($column) <> ''";
    };
    
    if (in_array('school', $availableFilters)) {
        if ($userPermissions['college_restricted'] && $college_id) {
            $options['schools'] = $conn->query(
                "SELECT DISTINCT e.school_university 
                 FROM education e 
                 WHERE " . $notBlank('e.school_university') . " 
                   AND e.college_department = (SELECT name FROM colleges WHERE id = {$college_id}) 
                 ORDER BY e.school_university"
            );
        } else {
            $options['schools'] = $conn->query(
                "SELECT DISTINCT school_university 
                 FROM education 
                 WHERE " . $notBlank('school_university') . " 
                 ORDER BY school_university"
            );
        }
    }
    
    if (in_array('campus', $availableFilters)) {
        if ($userPermissions['college_restricted'] && $college_id) {
            $options['campuses'] = $conn->query(
                "SELECT DISTINCT e.campus_branch 
                 FROM education e 
                 WHERE " . $notBlank('e.campus_branch') . " 
                   AND e.college_department = (SELECT name FROM colleges WHERE id = {$college_id}) 
                 ORDER BY e.campus_branch"
            );
        } else {
            $options['campuses'] = $conn->query(
                "SELECT DISTINCT campus_branch 
                 FROM education 
                 WHERE " . $notBlank('campus_branch') . " 
                 ORDER BY campus_branch"
            );
        }
    }
    
    if (in_array('college', $availableFilters)) {
        if ($userPermissions['college_restricted'] && $college_id) {
            $options['colleges'] = $conn->query(
                "SELECT DISTINCT e.college_department 
                 FROM education e 
                 WHERE " . $notBlank('e.college_department') . " 
                   AND e.college_department = (SELECT name FROM colleges WHERE id = {$college_id}) 
                 ORDER BY e.college_department"
            );
        } else {
            $options['colleges'] = $conn->query(
                "SELECT DISTINCT college_department 
                 FROM education 
                 WHERE " . $notBlank('college_department') . " 
                 ORDER BY college_department"
            );
        }
    }
    
    if (in_array('major', $availableFilters)) {
        // Treat 'major' filter as Program filter, using education.program values
        if ($userPermissions['college_restricted'] && $college_id) {
            $options['majors'] = $conn->query(
                "SELECT DISTINCT e.program 
                 FROM education e 
                 WHERE " . $notBlank('e.program') . " 
                   AND e.college_department = (SELECT name FROM colleges WHERE id = {$college_id}) 
                 ORDER BY e.program"
            );
        } else {
            $options['majors'] = $conn->query(
                "SELECT DISTINCT program 
                 FROM education 
                 WHERE " . $notBlank('program') . " 
                 ORDER BY program"
            );
        }
    }
    
    if (in_array('year', $availableFilters)) {
        if ($userPermissions['college_restricted'] && $college_id) {
            $options['years'] = $conn->query(
                "SELECT DISTINCT emp.year_of_employment AS year \n"
                . "FROM employment emp \n"
                . "JOIN education e ON e.user_id = emp.user_id \n"
                . "WHERE emp.year_of_employment IS NOT NULL AND emp.year_of_employment != '' \n"
                . "AND e.college_department = (SELECT name FROM colleges WHERE id = {$college_id}) \n"
                . "ORDER BY year DESC"
            );
        } else {
            $options['years'] = $conn->query("SELECT DISTINCT year_of_employment AS year FROM employment WHERE year_of_employment IS NOT NULL AND year_of_employment != '' ORDER BY year DESC");
        }
    }
    
    return $options;
}

// Build filter WHERE conditions based on role restrictions
function buildFilterWhereConditions($conn, $filters, $tablePrefix = 'e') {
    global $userPermissions, $college_id;
    
    $where = [];
    $availableFilters = $userPermissions['available_filters'];
    
    // Apply college restriction for coordinators
    if ($userPermissions['college_restricted'] && $college_id) {
        $where[] = "{$tablePrefix}.college_department = (SELECT name FROM colleges WHERE id = {$college_id})";
    }
    
    // Apply user-selected filters
    if (in_array('school', $availableFilters) && !empty($filters['school'])) {
        $where[] = "{$tablePrefix}.school_university = '" . $conn->real_escape_string($filters['school']) . "'";
    }
    
    if (in_array('campus', $availableFilters) && !empty($filters['campus'])) {
        $where[] = "{$tablePrefix}.campus_branch = '" . $conn->real_escape_string($filters['campus']) . "'";
    }
    
    if (in_array('college', $availableFilters) && !empty($filters['college'])) {
        $where[] = "{$tablePrefix}.college_department = '" . $conn->real_escape_string($filters['college']) . "'";
    }
    
    if (in_array('major', $availableFilters) && !empty($filters['major'])) {
        // Apply Program-based filter
        $where[] = "{$tablePrefix}.program = '" . $conn->real_escape_string($filters['major']) . "'";
    }
    
    // Note: Year filter is based on employment.year_of_employment and handled in page-level queries.
    return $where;
}

// Check module access and redirect if unauthorized
function requireModuleAccess($module) {
    if (!canAccessModule($module)) {
        header("Location: index.php?error=unauthorized");
        exit();
    }
}

// Display role-appropriate page title
function getRoleDisplayName($role) {
    switch ($role) {
        case 'superadmin':
            return 'Super Administrator';
        case 'admin':
            return 'Administrator';
        case 'coordinator':
            return 'Alumni Coordinator';
        default:
            return ucfirst($role);
    }
}

$stmt->close();
?> 