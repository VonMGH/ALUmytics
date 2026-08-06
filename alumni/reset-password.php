<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'database.php';
session_start();

$conn = Database::getInstance()->getConnection();

$rawToken = $_GET['token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawToken = $_POST['token'] ?? '';
}

$message = '';
$message_type = '';
$tokenValid = false;
$userId = null;
$resetId = null;

if (!empty($rawToken)) {
    $tokenHash = hash('sha256', $rawToken);
    $now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("SELECT id, user_id FROM password_resets WHERE token = ? AND expires_at > ? AND used_at IS NULL LIMIT 1");
    $stmt->bind_param('ss', $tokenHash, $now);
    $stmt->execute();
    $stmt->bind_result($resetId, $userId);
    if ($stmt->fetch()) {
        $tokenValid = true;
    }
    $stmt->close();
}

if (!$tokenValid) {
    $message = 'This password reset link is invalid or has expired.';
    $message_type = 'error';
}

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $message = 'New passwords do not match.';
        $message_type = 'error';
    } elseif (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $message = 'Password must be at least 8 characters, include upper, lower, and number.';
        $message_type = 'error';
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $updateUser = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $updateUser->bind_param('si', $new_hash, $userId);
        if ($updateUser->execute()) {
            $updateUser->close();

            $updateReset = $conn->prepare("UPDATE password_resets SET used_at = ? WHERE id = ?");
            $now = date('Y-m-d H:i:s');
            $updateReset->bind_param('si', $now, $resetId);
            $updateReset->execute();
            $updateReset->close();

            echo "<script>alert('Your password has been reset successfully. Please sign in with your new password.'); window.location.href = 'signin.php';</script>";
            exit();
        } else {
            $updateUser->close();
            $message = 'Something went wrong while updating your password. Please try again.';
            $message_type = 'error';
        }
    }
}

include 'includes/Aheader.php';
?>
<link rel="stylesheet" href="signin.css?v=<?php echo time(); ?>">
<div class="signup-one-container">
  <div class="left-panel" style="order: 2;">
    <div class="quote">“</div>
    <h1 class="welcome-text">Set a New Password</h1>
    <div class="alumytics-text"> Use your secure reset link</div>
  </div>
  <div class="right-panel" style="order: 1;">
    <form method="post" class="signup-one-form">
      <h2><i class="fas fa-key create-account-icon"></i> Create New Password</h2>
      <?php if (!empty($message)): ?>
        <div class="form-error-box" style="display:block;color:<?php echo $message_type === 'success' ? '#27ae60' : '#c0392b'; ?>;margin-bottom:10px;">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>
      <?php if ($tokenValid): ?>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($rawToken); ?>">
        <div class="password-container">
          <input type="password" id="new_password" name="new_password" placeholder="New Password" required autocomplete="new-password">
          <button type="button" class="password-toggle" id="newPasswordToggle" aria-label="Toggle new password visibility">
            <i class="fas fa-eye" id="newEyeIcon"></i>
          </button>
        </div>
        <div class="password-container">
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required autocomplete="new-password">
          <button type="button" class="password-toggle" id="confirmPasswordToggle" aria-label="Toggle confirm password visibility">
            <i class="fas fa-eye" id="confirmEyeIcon"></i>
          </button>
        </div>
        <button type="submit" class="sign-up-button">Reset Password</button>
      <?php else: ?>
        <p class="signin-link"><a href="forgot-password.php">Request a new reset link</a></p>
      <?php endif; ?>
      <p class="signin-link"><a href="signin.php">Back to Sign In</a></p>
    </form>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const newField = document.getElementById("new_password");
  const newToggle = document.getElementById("newPasswordToggle");
  const newEye = document.getElementById("newEyeIcon");

  const confirmField = document.getElementById("confirm_password");
  const confirmToggle = document.getElementById("confirmPasswordToggle");
  const confirmEye = document.getElementById("confirmEyeIcon");

  function setupToggle(field, toggle, eye) {
    if (!field || !toggle || !eye) return;

    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const isPassword = field.type === "password";
      field.type = isPassword ? "text" : "password";
      eye.className = isPassword ? "fas fa-eye-slash" : "fas fa-eye";
      toggle.setAttribute(
        "aria-label",
        isPassword ? "Hide password" : "Show password"
      );
      field.focus();
    });

    toggle.style.display = "flex";
    toggle.style.opacity = "1";
    toggle.style.visibility = "visible";

    toggle.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        toggle.click();
      }
    });
  }

  setupToggle(newField, newToggle, newEye);
  setupToggle(confirmField, confirmToggle, confirmEye);
});
</script>
