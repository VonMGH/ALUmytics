<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Fetch first name and profile photo for topbar greeting
$topbarFirstName = 'Alumni';
$topbarProfilePhoto = null;
if (isset($_SESSION['user_id'])) {
    try {
        include_once 'database.php';
        $conn = Database::getInstance()->getConnection();
        if ($conn) {
            $uid = intval($_SESSION['user_id']);
            $stmt = $conn->prepare("SELECT first_name, profile_photo FROM personal WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->bind_result($fn, $pp);
                if ($stmt->fetch()) {
                    if (!empty($fn)) {
                        $topbarFirstName = $fn;
                    }
                    if (!empty($pp)) {
                        $topbarProfilePhoto = $pp;
                    }
                }
                $stmt->close();
            }
        }
    } catch (Throwable $e) { /* ignore and keep default */ }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="includes/images/logo.png">
    <title> Alumni of Alumytics</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="alumni.css?v=<?php echo time(); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="js/sidebar-toggle.js?v=3" defer></script>
</head>
<body>
    <?php
        $currentPath = $_SERVER['PHP_SELF'] ?? '';
        $currentBase = basename($currentPath);
        $isAuthPage = (strpos($currentPath, 'signin.php') !== false) || (strpos($currentPath, 'signup.php') !== false);
        $isVerifyEmailPage = ($currentBase === 'verify-email.php');
    ?>
    <?php if (isset($_SESSION['user_id']) && $currentBase !== 'UpdateAccount.php' && !$isAuthPage && !$isVerifyEmailPage): ?>
    <!-- Top Navigation Bar -->
    <nav class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <span class="topbar-title">Alumni Portal</span>
        </div>
        <div class="topbar-right">
            <div class="topbar-user topbar-user-link" id="topbarUser" onclick="toggleUserMenu(event)">
                <?php if ($topbarProfilePhoto): ?>
                    <img src="<?php echo htmlspecialchars($topbarProfilePhoto); ?>" alt="Profile" class="topbar-user-avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
                <span class="topbar-user-text">
                    <span class="topbar-user-greeting">Welcome, <?php echo htmlspecialchars($topbarFirstName); ?></span>
                    <span class="topbar-user-role">Alumni</span>
                </span>
            </div>
            <div class="topbar-user-menu" id="topbarUserMenu">
                <a href="Aaccount-setting.php">Account Setting</a>
            </div>
            <a href="#" onclick="showLogoutModal()" class="logout-link" title="Logout">
                <i class="fas fa-power-off" style="color: black; margin-left: 11px;"></i>
            </a>
        </div>
    </nav>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    
    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="logout-modal" style="display: none;">
        <div class="logout-modal-overlay" onclick="hideLogoutModal()"></div>
        <div class="logout-modal-content">
            <div class="logout-modal-header">
                <h3>Confirm Logout</h3>
                <button class="logout-modal-close" onclick="hideLogoutModal()">&times;</button>
            </div>
            <div class="logout-modal-body">
                <p>Are you sure you want to log out?</p>
            </div>
            <div class="logout-modal-footer">
                <button class="logout-btn-confirm" onclick="confirmLogout()">Logout</button>
                <button class="logout-btn-cancel" onclick="hideLogoutModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Unsaved Changes Modal -->
    <div id="unsavedModal" class="logout-modal" style="display: none;">
        <div class="logout-modal-overlay" onclick="hideUnsavedModal()"></div>
        <div class="logout-modal-content">
            <div class="logout-modal-header">
                <h3>Unsaved Changes</h3>
                <button class="logout-modal-close" onclick="hideUnsavedModal()">&times;</button>
            </div>
            <div class="logout-modal-body">
                <p>You have unsaved changes. Do you want to leave this page and discard them?</p>
            </div>
            <div class="logout-modal-footer">
                <button class="logout-btn-confirm" onclick="confirmUnsavedLeave()">Leave Page</button>
                <button class="logout-btn-cancel" onclick="hideUnsavedModal()">Stay on Page</button>
            </div>
        </div>
    </div>

    <script>
        // ==================== LOGOUT MODAL ====================
        function showLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }
        
        function hideLogoutModal() {
            document.getElementById('logoutModal').style.display = 'none';
        }
        
        function confirmLogout() {
            window.location.href = 'logout.php';
        }
        
        // Close modal when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideLogoutModal();
                hideUserMenu();
            }
        });

        // ==================== USER DROPDOWN MENU ====================
        function toggleUserMenu(event) {
            event.stopPropagation();
            var menu = document.getElementById('topbarUserMenu');
            if (!menu) return;
            menu.classList.toggle('open');
        }

        function hideUserMenu() {
            var menu = document.getElementById('topbarUserMenu');
            if (!menu) return;
            menu.classList.remove('open');
        }

        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            var user = document.getElementById('topbarUser');
            var menu = document.getElementById('topbarUserMenu');
            if (!user || !menu) return;
            if (!user.contains(event.target) && !menu.contains(event.target)) {
                hideUserMenu();
            }
        });

        // ==================== UNSAVED CHANGES GUARD (MODAL) ====================
        let alumniFormDirty = false;
        let alumniIsSubmitting = false;
        let alumniPendingHref = null;

        // Mark page as dirty when any form field changes
        document.addEventListener('input', function(event) {
            const target = event.target;
            if (!target) return;
            if (target.matches('input, textarea, select')) {
                alumniFormDirty = true;
            }
        }, true);

        // When any form submits, do not warn
        window.addEventListener('load', function() {
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function() {
                    alumniIsSubmitting = true;
                    alumniFormDirty = false;
                });
            });
        });

        function showUnsavedModal() {
            var modal = document.getElementById('unsavedModal');
            if (!modal) return;
            modal.style.display = 'flex';
        }

        function hideUnsavedModal() {
            var modal = document.getElementById('unsavedModal');
            if (!modal) return;
            modal.style.display = 'none';
            alumniPendingHref = null;
        }

        function confirmUnsavedLeave() {
            var href = alumniPendingHref;
            alumniFormDirty = false;
            hideUnsavedModal();
            if (href) {
                window.location.href = href;
            }
        }

        // Intercept internal link clicks when there are unsaved changes
        document.addEventListener('click', function(event) {
            var link = event.target.closest('a');
            if (!link) return;

            // Allow links that explicitly bypass the guard (e.g., logout modal buttons)
            if (link.hasAttribute('data-bypass-unsaved')) {
                return;
            }

            // Only guard normal navigation clicks
            var href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                return;
            }

            if (!alumniFormDirty || alumniIsSubmitting) {
                return;
            }

            event.preventDefault();
            alumniPendingHref = link.href;
            showUnsavedModal();
        }, true);
    </script>
    <?php endif; ?>