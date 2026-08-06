<?php
// Load shared system branding (logo and name) so alumni sidebar matches staff branding
// __DIR__ is alumni/includes, so go two levels up to reach root config/
$brandingConfigFile = __DIR__ . '/../../config/system_branding.json';
$branding = [
    'system_name' => 'Alumytics',
    'logo_path'   => 'alumni/includes/images/logo.png',
];
if (file_exists($brandingConfigFile)) {
    $data = json_decode(file_get_contents($brandingConfigFile), true);
    if (is_array($data)) {
        $branding = array_merge($branding, $data);
    }
}
// Adjust logo path for alumni context: if it's a relative path (like 'includes/logo.png'
// or 'staff/uploads/branding/logo.png'), prefix '../' so it resolves correctly from
// the /alumni/ directory. Keep absolute URLs and already alumni-specific paths as-is.
$logoPath = $branding['logo_path'] ?? 'alumni/includes/images/logo.png';
// If branding stored a root-level uploads path, map it to staff/uploads where logos live
if (strpos($logoPath, 'uploads/branding/') === 0) {
    $logoPath = 'staff/' . $logoPath; // becomes staff/uploads/branding/...
}
if (
    strpos($logoPath, 'alumni/') !== 0 &&           // not already alumni/
    !preg_match('#^https?://#i', $logoPath) &&      // not absolute URL
    (!isset($logoPath[0]) || $logoPath[0] !== '/')  // not root-absolute
) {
    $logoPath = '../' . ltrim($logoPath, '/');
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$navItems = [
    ['file' => 'index.php',                 'href' => 'index.php',                 'icon' => 'fa-user',         'label' => 'Profile'],
    ['file' => 'Aemployment.php',           'href' => 'Aemployment.php',           'icon' => 'fa-briefcase',    'label' => 'Employment'],
    ['file' => 'Acertification.php',       'href' => 'Acertification.php',       'icon' => 'fa-certificate',  'label' => 'Certification'],
    ['file' => 'Aawards.php',              'href' => 'Aawards.php',              'icon' => 'fa-award',        'label' => 'Awards'],
    ['file' => 'Asubmission-history.php',  'href' => 'Asubmission-history.php',  'icon' => 'fa-history',      'label' => 'Submission History'],
];
?>
<aside class="sidebar alumni-sidebar" aria-label="Alumni navigation">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="sidebar-logo-wrap">
                <img
                    src="<?php echo htmlspecialchars($logoPath); ?>"
                    alt="<?php echo htmlspecialchars($branding['system_name']); ?> logo"
                    class="sidebar-logo"
                >
            </div>
            <div class="sidebar-brand-text">
                <span class="sidebar-system-name"><?php echo htmlspecialchars($branding['system_name']); ?></span>
                <span class="sidebar-portal-badge">Alumni Portal</span>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="sidebar-nav-label">Main Menu</span>
        <ul>
            <?php foreach ($navItems as $item): ?>
            <li class="<?php echo $currentPage === $item['file'] ? 'active' : ''; ?>">
                <a href="<?php echo htmlspecialchars($item['href']); ?>">
                    <span class="sidebar-link-icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i></span>
                    <span class="sidebar-link-text"><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <p class="sidebar-footer-text">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($branding['system_name']); ?></p>
    </div>
</aside>
