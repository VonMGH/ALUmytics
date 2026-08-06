<?php
include 'includes/access_control.php';
if ($role !== 'superadmin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

// Simple config path – adjust if you store this elsewhere
$configFile = __DIR__ . '/../config/system_branding.json';
$uploadDir  = __DIR__ . '/uploads/branding/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// Load existing branding
$branding = [
    'system_name' => 'Alumytics',
    'logo_path'   => 'includes/logo.png',
];
if (file_exists($configFile)) {
    $data = json_decode(file_get_contents($configFile), true);
    if (is_array($data)) {
        $branding = array_merge($branding, $data);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $systemName = trim($_POST['system_name'] ?? $branding['system_name']);

    // Handle logo upload
    $logoPath = $branding['logo_path'];
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['logo']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','gif','webp'];
        if (in_array($ext, $allowed, true)) {
            $fileName = 'logo_' . time() . '.' . $ext;
            $destRel  = 'uploads/branding/' . $fileName;
            $destAbs  = $uploadDir . $fileName;
            if (move_uploaded_file($tmpName, $destAbs)) {
                $logoPath = $destRel;
            }
        }
    }

    $branding['system_name'] = $systemName;
    $branding['logo_path']   = $logoPath;

    if (!is_dir(dirname($configFile))) {
        mkdir(dirname($configFile), 0775, true);
    }
    file_put_contents($configFile, json_encode($branding, JSON_PRETTY_PRINT));

    header('Location: sys-profile-customization.php?updated=1');
    exit;
}

$role ??= null;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/sys-pages.css">
<link rel="stylesheet" href="css/sys-profile-customization.css">

<div class="content-wrapper">
    <main class="main-content dashboard-page">
        <div class="header dashboard-header">
            <div class="header-top">
                <div>
                    <h1 class="mb-0 dashboard-title">Profile Customization</h1>
                    <p class="dashboard-subtitle mb-0">Manage the system name and logo shown in the staff portal.</p>
                </div>
            </div>
        </div>

        <?php if (!empty($_GET['updated'])): ?>
            <div class="alert alert-success sys-branding-alert" role="alert">
                <i class="fas fa-check-circle me-2"></i> Branding settings updated successfully.
            </div>
        <?php endif; ?>

        <div class="sys-branding-layout">
            <div class="card shadow-sm dashboard-card sys-branding-preview-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Current Branding</h5>
                </div>
                <div class="card-body">
                    <div class="sys-branding-preview">
                        <div class="sys-branding-logo-wrap">
                            <img src="<?php echo htmlspecialchars($branding['logo_path']); ?>" alt="Current system logo">
                        </div>
                        <div class="sys-branding-preview-meta">
                            <span class="sys-branding-preview-label">System Name</span>
                            <div class="sys-branding-preview-name"><?php echo htmlspecialchars($branding['system_name']); ?></div>
                            <p class="sys-branding-preview-hint">This logo and name appear in the staff sidebar header and other places where the system brand is shown.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm dashboard-card sys-branding-form-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Update Branding</h5>
                </div>
                <div class="card-body">
                    <form action="sys-profile-customization.php" method="post" enctype="multipart/form-data" class="sys-branding-form">
                        <div class="sys-branding-field">
                            <label for="system_name" class="form-label">System Name</label>
                            <input
                                type="text"
                                class="form-control"
                                id="system_name"
                                name="system_name"
                                value="<?php echo htmlspecialchars($branding['system_name']); ?>"
                                required
                                autocomplete="organization">
                        </div>

                        <div class="sys-branding-field">
                            <label for="logo" class="form-label">System Logo / Icon</label>
                            <div class="sys-branding-file-input">
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                            </div>
                            <p class="sys-branding-field-hint">Recommended: PNG with transparent background, at least 128×128px.</p>
                        </div>

                        <div class="sys-branding-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
