<?php
include 'includes/Aheader.php';
include 'includes/Asidebar.php';
include 'database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please sign in.'); window.location.href = 'signin.php';</script>";
    exit();
}
$user_id = $_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

$email = '';
$phone = '';
$profile_photo = '';
$full_name = '';
$first_name = '';
$last_name = '';

// Fetch user info
$stmt = $conn->prepare("SELECT u.email, p.phone_number, p.profile_photo, u.full_name, p.first_name, p.last_name FROM users u LEFT JOIN personal p ON u.user_id = p.user_id WHERE u.user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $email = (string) ($row['email'] ?? '');
    $phone = (string) ($row['phone_number'] ?? '');
    $profile_photo = (string) ($row['profile_photo'] ?? '');
    $full_name = (string) ($row['full_name'] ?? '');
    $first_name = (string) ($row['first_name'] ?? '');
    $last_name = (string) ($row['last_name'] ?? '');
}
$stmt->close();

$displayName = trim($full_name ?? '');
if ($displayName === '') {
    $displayName = trim(implode(' ', array_filter([$first_name ?? '', $last_name ?? ''])));
}
if ($displayName === '') {
    $displayName = 'Alumni';
}

$avatarInitials = '';
if (!empty($first_name)) {
    $avatarInitials .= strtoupper($first_name[0]);
}
if (!empty($last_name)) {
    $avatarInitials .= strtoupper($last_name[0]);
}
if ($avatarInitials === '' && !empty($full_name)) {
    $parts = preg_split('/\s+/', trim($full_name));
    if (!empty($parts[0])) $avatarInitials .= strtoupper($parts[0][0]);
    if (count($parts) > 1) $avatarInitials .= strtoupper($parts[count($parts) - 1][0]);
}
$avatarInitials = $avatarInitials ?: '?';
$hasProfilePhoto = !empty($profile_photo);

// Handle Account Info Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
    $new_email = trim($_POST['email']);
    $new_phone = trim($_POST['phone']);
    $file_path = $profile_photo;
    $email_changed = false;
    
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_name = basename($_FILES['profile_photo']['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png'];
        if (in_array($ext, $allowed)) {
            $file_path = $upload_dir . uniqid('profile_') . '_' . $file_name;
            move_uploaded_file($file_tmp, $file_path);
        }
    }
    
    if ($new_email !== $email) {
        $email_changed = true;
        $stmt = $conn->prepare("UPDATE users SET email=? WHERE user_id=?");
        $stmt->bind_param('si', $new_email, $user_id);
        $stmt->execute();
        $stmt->close();
        sendAccountChangeEmail($new_email, $full_name, 'email');
        $_SESSION['email'] = $new_email;
    }
    
    $stmt = $conn->prepare("UPDATE personal SET phone_number=?, profile_photo=? WHERE user_id=?");
    $stmt->bind_param('ssi', $new_phone, $file_path, $user_id);
    $stmt->execute();
    $stmt->close();
    
    $phone = $new_phone;
    $profile_photo = $file_path;
    $email = $new_email;
    $msg = 'Account information updated successfully!';
    $msg_type = 'success';
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    $stmt = $conn->prepare("SELECT password_hash, email, full_name FROM users WHERE user_id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $pwResult = $stmt->get_result();
    $pwRow = $pwResult ? $pwResult->fetch_assoc() : null;
    $stmt->close();

    $hash = (string) ($pwRow['password_hash'] ?? '');
    $user_email = (string) ($pwRow['email'] ?? '');
    $user_full_name = (string) ($pwRow['full_name'] ?? '');

    if ($hash === '' || !password_verify($current, $hash)) {
        $pw_msg = 'Current password is incorrect.';
        $pw_msg_type = 'error';
    } elseif ($new !== $confirm) {
        $pw_msg = 'New passwords do not match.';
        $pw_msg_type = 'error';
    } elseif (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $pw_msg = 'Password must be at least 8 characters, include upper, lower, and number.';
        $pw_msg_type = 'error';
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
        $stmt->bind_param('si', $new_hash, $user_id);
        $stmt->execute();
        $stmt->close();
        
        sendAccountChangeEmail($user_email, $user_full_name, 'password');
        $pw_msg = 'Password changed successfully! You will be logged out to test your new password.';
        $pw_msg_type = 'success';
        $logout_after_password_change = true;
    }
}

 

function sendAccountChangeEmail($to, $name, $type) {
    // Email notifications disabled - account change recorded in system
    // Previously would send email notification for: $type
}
?>
<link rel="stylesheet" href="alumni.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="index.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="aAccount-setting.css?v=<?php echo time(); ?>">
<main class="profile-content profile-dashboard-page account-settings-page">
  <div class="profile-dashboard-wrap">

    <header class="profile-hero">
      <div class="profile-hero-pattern" aria-hidden="true"></div>
      <div class="profile-hero-deco profile-hero-deco-1" aria-hidden="true"></div>
      <div class="profile-hero-deco profile-hero-deco-2" aria-hidden="true"></div>
      <div class="profile-hero-inner account-hero-inner">
        <div class="profile-hero-main">
          <div class="profile-hero-avatar">
            <div class="avatar-ring">
              <img id="profilePreview"
                class="profile-avatar-img<?php echo $hasProfilePhoto ? '' : ' is-hidden'; ?>"
                src="<?php echo $hasProfilePhoto ? htmlspecialchars($profile_photo) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDE1MCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiBmaWxsPSIjZjBmMGYwIi8+CjxjaXJjbGUgY3g9Ijc1IiBjeT0iNjAiIHI9IjIwIiBmaWxsPSIjY2NjIi8+CjxwYXRoIGQ9Ik00NSAxMjBjMC0xNi41NjkgMTMuNDMxLTMwIDMwLTMwczcwIDEzLjQzMSAzMCAzMCIgZmlsbD0iI2NjYyIvPgo8L3N2Zz4='; ?>"
                alt="Profile Photo">
              <div id="avatarInitials" class="profile-avatar-initials<?php echo $hasProfilePhoto ? ' is-hidden' : ''; ?>"><?php echo htmlspecialchars($avatarInitials); ?></div>
            </div>
            <label for="profile_photo" class="avatar-edit-btn" aria-label="Change Profile Photo"><i class="fas fa-camera"></i></label>
            <input type="file" id="profile_photo" name="profile_photo" form="accountForm" accept="image/*" hidden>
          </div>
          <div class="profile-hero-text">
            <span class="profile-hero-badge"><i class="fas fa-cog"></i> Account Settings</span>
            <h1 class="profile-hero-name"><?php echo htmlspecialchars($displayName); ?></h1>
            <p class="profile-hero-meta">
              <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($email); ?></span>
              <?php if (!empty($phone)): ?>
                <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($phone); ?></span>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <aside class="profile-hero-aside account-hero-aside" aria-label="Account summary">
          <div class="hero-panel">
            <div class="hero-panel-top">
              <div class="account-security-icon" aria-hidden="true">
                <i class="fas fa-shield-alt"></i>
              </div>
              <div class="hero-panel-intro">
                <span class="hero-panel-label">Security</span>
                <p class="hero-panel-desc">Manage your login credentials and profile photo</p>
              </div>
            </div>
            <div class="account-quick-tips">
              <div class="account-tip-tile">
                <span class="hero-stat-icon"><i class="fas fa-envelope"></i></span>
                <span class="hero-stat-label">Login Email</span>
                <span class="hero-stat-value"><?php echo htmlspecialchars($email); ?></span>
              </div>
              <div class="account-tip-tile">
                <span class="hero-stat-icon"><i class="fas fa-mobile-alt"></i></span>
                <span class="hero-stat-label">Phone</span>
                <span class="hero-stat-value"><?php echo htmlspecialchars($phone ?: 'Not set'); ?></span>
              </div>
            </div>
          </div>
        </aside>
      </div>
      <p class="profile-hero-hint">Photo: 400×400 recommended · Max 2MB · JPG or PNG</p>
    </header>

    <form id="accountForm" method="post" enctype="multipart/form-data">
      <section class="profile-section-card">
        <div class="section-header">
          <span class="section-icon"><i class="fas fa-user-circle"></i></span>
          <div>
            <h2>Account Information</h2>
            <p>Update your email, phone number, and profile photo</p>
          </div>
        </div>

        <?php if (!empty($msg)): ?>
          <div class="alert alert-<?php echo $msg_type ?? 'success'; ?>">
            <i class="fas fa-check-circle"></i>
            <?php echo $msg; ?>
          </div>
        <?php endif; ?>

        <div class="form-grid account-form-grid">
          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Email Address" required>
          </div>
          <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Phone Number" required>
          </div>
        </div>

        <div class="save-bar">
          <div class="form-actions">
            <button type="submit" class="save-button" name="update_account">
              <i class="fas fa-save"></i> Save Changes
            </button>
          </div>
        </div>
      </section>
    </form>

    <section class="profile-section-card">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-lock"></i></span>
        <div>
          <h2>Change Password</h2>
          <p>Keep your account secure with a strong password</p>
        </div>
      </div>

      <?php if (!empty($pw_msg)): ?>
        <div class="alert alert-<?php echo $pw_msg_type ?? 'success'; ?>">
          <i class="fas fa-<?php echo ($pw_msg_type ?? 'success') === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
          <?php echo $pw_msg; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="password-form">
        <div class="form-grid password-form-grid">
          <div class="form-group span-full">
            <label for="current-password">Current Password</label>
            <div class="password-input-wrapper">
              <input type="password" id="current-password" name="current_password" placeholder="Enter current password" required>
              <button type="button" class="toggle-password" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
            </div>
          </div>

          <div class="form-group">
            <label for="new-password">New Password</label>
            <div class="password-input-wrapper">
              <input type="password" id="new-password" name="new_password" placeholder="Enter new password" required>
              <button type="button" class="toggle-password" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm-password">Confirm New Password</label>
            <div class="password-input-wrapper">
              <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm new password" required>
              <button type="button" class="toggle-password" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
            </div>
          </div>

          <div class="form-group span-full">
            <div class="password-requirements">
              <p>Password must contain:</p>
              <ul>
                <li id="length-check">At least 8 characters</li>
                <li id="uppercase-check">One uppercase letter</li>
                <li id="lowercase-check">One lowercase letter</li>
                <li id="number-check">One number</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="save-bar">
          <div class="form-actions">
            <button type="submit" class="save-button save-button-secondary" name="change_password">
              <i class="fas fa-key"></i> Change Password
            </button>
          </div>
        </div>
      </form>
    </section>

  </div>
</main>

<script>
// Profile photo preview (matches index.php hero avatar behavior)
const fileInput = document.getElementById('profile_photo');
const previewImage = document.getElementById('profilePreview');
const avatarInitials = document.getElementById('avatarInitials');

if (fileInput && previewImage) {
  fileInput.addEventListener('change', function () {
    const file = this.files && this.files[0];
    if (!file || !file.type.startsWith('image/')) return;
    const blobUrl = (window.URL || window.webkitURL).createObjectURL(file);
    previewImage.src = blobUrl;
    previewImage.classList.remove('is-hidden');
    if (avatarInitials) avatarInitials.classList.add('is-hidden');
  });
}

// Sync topbar avatar with latest profile photo (after save or page load)
document.addEventListener('DOMContentLoaded', function() {
  var topbarAvatar = document.querySelector('.topbar-user-avatar');
  if (!topbarAvatar) return;

  var latestPhoto = <?php echo json_encode($profile_photo ?? ''); ?>;
  if (!latestPhoto) return;

  // Add cache-busting query so browser fetches the new image immediately
  var baseUrl = latestPhoto.split('?')[0];
  topbarAvatar.src = baseUrl + '?v=' + Date.now();
});

// Password visibility toggle
document.querySelectorAll('.toggle-password').forEach(toggle => {
  toggle.addEventListener('click', function() {
    const wrapper = this.closest('.password-input-wrapper');
    const input = wrapper ? wrapper.querySelector('input') : null;
    const icon = this.querySelector('i');
    if (!input) return;
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    if (icon) {
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    }
  });
});

// Password validation
const newPasswordInput = document.getElementById('new-password');
const confirmPasswordInput = document.getElementById('confirm-password');

function validatePassword() {
  const password = newPasswordInput.value;
  const confirm = confirmPasswordInput.value;
  
  // Update requirement checks
  document.getElementById('length-check').classList.toggle('met', password.length >= 8);
  document.getElementById('uppercase-check').classList.toggle('met', /[A-Z]/.test(password));
  document.getElementById('lowercase-check').classList.toggle('met', /[a-z]/.test(password));
  document.getElementById('number-check').classList.toggle('met', /[0-9]/.test(password));
  
  // Check if passwords match
  if (confirm && password !== confirm) {
    confirmPasswordInput.setCustomValidity('Passwords do not match');
  } else {
    confirmPasswordInput.setCustomValidity('');
  }
}

if (newPasswordInput) {
  newPasswordInput.addEventListener('input', validatePassword);
}

if (confirmPasswordInput) {
  confirmPasswordInput.addEventListener('input', validatePassword);
}

 

// Auto logout after password change
<?php if (isset($logout_after_password_change) && $logout_after_password_change): ?>
setTimeout(function() {
  alert('You will be logged out to test your new password.');
  window.location.href = 'logout.php';
}, 3000);
<?php endif; ?>
</script>
