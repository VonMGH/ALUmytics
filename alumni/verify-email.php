<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'database.php';
session_start();

if (!isset($_SESSION['pending_verification_user_id'])) {
    echo "<script>alert('Your verification session has expired. Please sign up again.'); window.location.href = 'signup.php';</script>";
    exit();
}

$userId = (int)$_SESSION['pending_verification_user_id'];
$conn = Database::getInstance()->getConnection();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $code)) {
        $message = 'Please enter the 6-digit verification code sent to your email.';
        $message_type = 'error';
    } else {
        $codeHash = hash('sha256', $code);
        $now = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("SELECT id FROM email_verification_codes WHERE user_id = ? AND code_hash = ? AND expires_at > ? AND verified_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('iss', $userId, $codeHash, $now);
        $stmt->execute();
        $stmt->bind_result($codeId);
        if ($stmt->fetch()) {
            $stmt->close();

            // Mark code as verified
            $update = $conn->prepare("UPDATE email_verification_codes SET verified_at = ? WHERE id = ?");
            $now = date('Y-m-d H:i:s');
            $update->bind_param('si', $now, $codeId);
            $update->execute();
            $update->close();

            // Fetch user email
            $userStmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
            $userStmt->bind_param('i', $userId);
            $userStmt->execute();
            $userStmt->bind_result($email);
            $userStmt->fetch();
            $userStmt->close();

            // Finalize login and redirect to UpdateAccount
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $email;
            unset($_SESSION['pending_verification_user_id']);

            echo "<script>alert('Email verified successfully! Please complete your profile.'); window.location.href = 'UpdateAccount.php';</script>";
            exit();
        } else {
            $stmt->close();
            $message = 'Invalid or expired verification code. Please try again.';
            $message_type = 'error';
        }
    }
}

include 'includes/Aheader.php';
?>
<link rel="stylesheet" href="signup.css?v=<?php echo time(); ?>">
<div class="signup-one-container">
  <div class="left-panel">
    <h2 class="welcome-text">Verify Your Email</h2>
    <h3 class="to-text">ALUMytics</h3>
    <div class="alumytics-text">Enter the 6-digit code sent to your email to continue</div>
  </div>
  <div class="right-panel">
    <form method="post" class="signup-one-form">
      <h2><i class="fas fa-envelope-open-text create-account-icon"></i> Email Verification</h2>
      <?php if (!empty($message)): ?>
        <div class="form-error-box" style="display:block;color:<?php echo $message_type === 'success' ? '#27ae60' : '#c0392b'; ?>;margin-bottom:10px;">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>
      <input type="text" name="code" placeholder="Enter 6-digit code" required autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}">
      <button type="submit" class="sign-up-button">Verify Email</button>
    </form>
  </div>
</div>

