<?php
$current = basename($_SERVER['PHP_SELF']);
if ($current !== 'login.php') {
    require_once __DIR__ . '/access_control.php';
    $topbarStaffName = $userName ?? 'Staff';
    $topbarRoleName = getRoleDisplayName($role ?? '');
} else {
    $topbarStaffName = 'Staff';
    $topbarRoleName = 'Staff';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="includes/logo.png">
    <title>Alumytics</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="preload" href="css/style.css" as="style">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/shell.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <?php if ($current !== 'login.php'): ?>
    <script src="js/sidebar-toggle.js?v=1" defer></script>
    <?php endif; ?>
</head>
<body>
    <?php if ($current !== 'login.php'): ?>
    <!-- Top Navigation Bar -->
    <nav class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <span class="topbar-title">Staff Portal</span>
        </div>
        <div class="topbar-right">
            <div class="topbar-user">
                <i class="fas fa-user-circle"></i>
                <span class="topbar-user-text">
                    <span class="topbar-user-greeting">Welcome, <?php echo htmlspecialchars($topbarStaffName); ?></span>
                    <span class="topbar-user-role"><?php echo htmlspecialchars($topbarRoleName); ?></span>
                </span>
            </div>
            <a href="#" onclick="showStaffLogoutModal(); return false;" class="logout-link" title="Logout">
                <i class="fas fa-power-off"></i>
            </a>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Logout Confirmation Modal -->
    <div id="staffLogoutModal" class="logout-modal" style="display: none;">
        <div class="logout-modal-overlay" onclick="hideStaffLogoutModal()"></div>
        <div class="logout-modal-content">
            <div class="logout-modal-header">
                <h3>Confirm Logout</h3>
                <button class="logout-modal-close" onclick="hideStaffLogoutModal()" type="button">&times;</button>
            </div>
            <div class="logout-modal-body">
                <p>Are you sure you want to log out?</p>
            </div>
            <div class="logout-modal-footer">
                <button class="logout-btn-confirm" type="button" onclick="confirmStaffLogout()">Logout</button>
                <button class="logout-btn-cancel" type="button" onclick="hideStaffLogoutModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function showStaffLogoutModal() {
            var modal = document.getElementById('staffLogoutModal');
            if (modal) modal.style.display = 'flex';
        }

        function hideStaffLogoutModal() {
            var modal = document.getElementById('staffLogoutModal');
            if (modal) modal.style.display = 'none';
        }

        function confirmStaffLogout() {
            window.location.href = '../logout.php';
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideStaffLogoutModal();
            }
        });
    </script>
    <?php endif; ?>

    <div class="wrapper">
