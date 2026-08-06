<?php
// Staff logout: use dedicated staff_session so alumni session remains untouched
session_name('staff_session');
session_start();

// Clear all staff session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the staff session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the staff session
session_destroy();

// Redirect to staff login page with success message
echo "<script>alert('You have been successfully logged out.'); window.location.href = 'staff/login.php';</script>";
exit();
?>