<?php
include_once __DIR__ . '/access_control.php';

global $role, $college_id, $college_name;
$role ??= '';
$college_name ??= null;

// Load system branding (logo and name) shared with Profile Customization
// __DIR__ is staff/includes, so go two levels up to reach root config/
$brandingConfigFile = __DIR__ . '/../../config/system_branding.json';
$branding = [
    'system_name' => 'Alumytics',
    'logo_path'   => 'includes/logo.png',
];
if (file_exists($brandingConfigFile)) {
    $data = json_decode(file_get_contents($brandingConfigFile), true);
    if (is_array($data)) {
        $branding = array_merge($branding, $data);
    }
}
?>
<aside class="sidebar staff-sidebar" aria-label="Staff navigation">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="sidebar-logo-wrap">
                <img
                    src="<?php echo htmlspecialchars($branding['logo_path']); ?>"
                    alt="<?php echo htmlspecialchars($branding['system_name']); ?> logo"
                    class="sidebar-logo"
                >
            </div>
            <div class="sidebar-brand-text">
                <span class="sidebar-system-name"><?php echo htmlspecialchars($branding['system_name']); ?></span>
                <span class="sidebar-portal-badge">Staff Portal</span>
                <?php if (isCollegeRestricted() && $college_name): ?>
                    <span class="sidebar-college-badge"><?= htmlspecialchars($college_name) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="sidebar-nav-label">Main Menu</span>
        <ul>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                <a href="index.php">
                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                    <span class="sidebar-link-text">Dashboard</span>
                </a>
            </li>

            <?php if (canAccessModule('demographics')): ?>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'demographics.php' ? 'active' : '' ?>">
                <a href="demographics.php">
                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                    <span class="sidebar-link-text">Demographics</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (canAccessModule('employment')): ?>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'employment.php' ? 'active' : '' ?>">
                <a href="employment.php">
                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-briefcase"></i></span>
                    <span class="sidebar-link-text">Employment</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (canAccessModule('certification')): ?>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'certification.php' ? 'active' : '' ?>">
                <a href="certification.php">
                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-certificate"></i></span>
                    <span class="sidebar-link-text">Certification</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (canAccessModule('geography')): ?>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'geography.php' ? 'active' : '' ?>">
                <a href="geography.php">
                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-globe"></i></span>
                    <span class="sidebar-link-text">Geography</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (canAccessModule('campus-analysis')): ?>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'campus-analysis.php' ? 'active' : '' ?>">
                <a href="campus-analysis.php">
                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-university"></i></span>
                    <span class="sidebar-link-text">Campus Analysis</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php 
            // Show System Management submenu only if user has access to any of its modules
            $hasSystemManagementAccess = canAccessModule('usermanagement') || canAccessModule('user-logs') || canAccessModule('backup-restore') || canAccessModule('report-generation');
            if ($hasSystemManagementAccess): 
                $currentPage = basename($_SERVER['PHP_SELF']);
                // Include Control Panel (sys-*) pages so System Management stays expanded there
                $systemPages = [
                    'usermanagement.php',
                    'user-logs.php',
                    'backup-restore.php',
                    'report-generation.php',
                    'report-generation-tracer.php',
                    'report-generation-year-comparison.php',
                    'report-generation-cert-awards.php',
                    'sys-universities.php',
                    'sys-campuses.php',
                    'sys-departments.php',
                    'sys-programs.php',
                    'sys-specializations.php',
                    'sys-profile-customization.php'
                ];
                $systemExpandedClass = in_array($currentPage, $systemPages) ? ' expanded' : '';
                $reportGenPages = ['report-generation.php', 'report-generation-tracer.php', 'report-generation-year-comparison.php', 'report-generation-cert-awards.php'];
                $reportGenExpandedClass = in_array($currentPage, $reportGenPages) ? ' expanded' : '';
            ?>
            <li class="submenu-parent system-management-submenu<?= $systemExpandedClass ?>">
                <a href="#" class="submenu-toggle">
                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-user-cog"></i></span>
                    <span class="sidebar-link-text">System Management</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <?php if (canAccessModule('report-generation')): ?>
                    <li class="submenu-parent report-generation-submenu<?= $reportGenExpandedClass ?>">
                        <a href="#" class="submenu-toggle">
                            <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                            <span class="sidebar-link-text">Report Generation</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'report-generation.php' ? 'active' : '' ?>">
                                <a href="report-generation.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                                    <span class="sidebar-link-text">Alumni Tracking</span>
                                </a>
                            </li>
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'report-generation-tracer.php' ? 'active' : '' ?>">
                                <a href="report-generation-tracer.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-chart-bar"></i></span>
                                    <span class="sidebar-link-text">Tracer Study</span>
                                </a>
                            </li>
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'report-generation-year-comparison.php' ? 'active' : '' ?>">
                                <a href="report-generation-year-comparison.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                                    <span class="sidebar-link-text">Year Comparison</span>
                                </a>
                            </li>
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'report-generation-cert-awards.php' ? 'active' : '' ?>">
                                <a href="report-generation-cert-awards.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-award"></i></span>
                                    <span class="sidebar-link-text">Certifications &amp; Awards</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if ($role === 'superadmin'): ?>
                    <li class="submenu-parent<?= in_array(basename($_SERVER['PHP_SELF']), ['sys-universities.php','sys-campuses.php','sys-departments.php','sys-programs.php','sys-specializations.php','sys-profile-customization.php']) ? ' expanded' : '' ?>">
                        <a href="#" class="submenu-toggle">
                            <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-sliders-h"></i></span>
                            <span class="sidebar-link-text">Control Panel</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'sys-universities.php' ? 'active' : '' ?>">
                                <a href="sys-universities.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-school"></i></span>
                                    <span class="sidebar-link-text">Manage University/School</span>
                                </a>
                            </li>
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'sys-campuses.php' ? 'active' : '' ?>">
                                <a href="sys-campuses.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-building"></i></span>
                                    <span class="sidebar-link-text">Manage Campus/Branch</span>
                                </a>
                            </li>
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'sys-departments.php' ? 'active' : '' ?>">
                                <a href="sys-departments.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-university"></i></span>
                                    <span class="sidebar-link-text">Manage College/Department</span>
                                </a>
                            </li>
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'sys-programs.php' ? 'active' : '' ?>">
                                <a href="sys-programs.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-book-open"></i></span>
                                    <span class="sidebar-link-text">Manage Program</span>
                                </a>
                            </li>
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'sys-specializations.php' ? 'active' : '' ?>">
                                <a href="sys-specializations.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                                    <span class="sidebar-link-text">Manage Specialization/Major</span>
                                </a>
                            </li>
                            <li class="<?= basename($_SERVER['PHP_SELF']) == 'sys-profile-customization.php' ? 'active' : '' ?>">
                                <a href="sys-profile-customization.php">
                                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-paint-brush"></i></span>
                                    <span class="sidebar-link-text">Profile Customization</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if (canAccessModule('usermanagement')): ?>
                    <li class="<?= basename($_SERVER['PHP_SELF']) == 'usermanagement.php' ? 'active' : '' ?>">
                        <a href="usermanagement.php">
                            <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                            <span class="sidebar-link-text">User Management</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (canAccessModule('user-logs')): ?>
                    <li class="<?= basename($_SERVER['PHP_SELF']) == 'user-logs.php' ? 'active' : '' ?>">
                        <a href="user-logs.php">
                            <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-list-alt"></i></span>
                            <span class="sidebar-link-text">User Logs</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (canAccessModule('backup-restore')): ?>
                    <li class="<?= basename($_SERVER['PHP_SELF']) == 'backup-restore.php' ? 'active' : '' ?>">
                        <a href="backup-restore.php">
                            <span class="sidebar-link-icon" aria-hidden="true"><i class="fas fa-database"></i></span>
                            <span class="sidebar-link-text">Back Up and Restore</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <p class="sidebar-footer-text">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($branding['system_name']); ?></p>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const submenuParents = document.querySelectorAll('.submenu-parent');
    submenuParents.forEach(function(parent) {
        const toggle = parent.querySelector('.submenu-toggle');
        if (toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                parent.classList.toggle('expanded');
            });
        }
    });

    const currentPage = '<?= basename($_SERVER['PHP_SELF']) ?>';
    submenuParents.forEach(function(parent) {
        const pagesAttr = parent.getAttribute('data-submenu-pages');
        if (!pagesAttr) return;
        const pages = pagesAttr.split(',').map(function(p) { return p.trim(); });
        if (pages.includes(currentPage)) {
            parent.classList.add('expanded');
        }
    });
});
</script>
