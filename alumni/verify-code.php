<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'database.php';
session_start();

if (!isset($_SESSION['pending_user_id'])) {
    echo "<script>alert('Your session has expired. Please sign in again.'); window.location.href = 'signin.php';</script>";
    exit();
}

$userId = (int)$_SESSION['pending_user_id'];
$conn = Database::getInstance()->getConnection();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $code)) {
        $message = 'Please enter the 6-digit code sent to your email.';
        $message_type = 'error';
    } else {
        $codeHash = hash('sha256', $code);
        $now = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("SELECT id FROM login_verification_codes WHERE user_id = ? AND code_hash = ? AND expires_at > ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('iss', $userId, $codeHash, $now);
        $stmt->execute();
        $stmt->bind_result($codeId);
        if ($stmt->fetch()) {
            $stmt->close();

            // Mark code as used
            $update = $conn->prepare("UPDATE login_verification_codes SET used_at = ? WHERE id = ?");
            $now = date('Y-m-d H:i:s');
            $update->bind_param('si', $now, $codeId);
            $update->execute();
            $update->close();

            // Fetch user email and onboarded flag
            $userStmt = $conn->prepare("SELECT email, onboarded FROM users WHERE user_id = ?");
            $userStmt->bind_param('i', $userId);
            $userStmt->execute();
            $userStmt->bind_result($email, $onboarded);
            $userStmt->fetch();
            $userStmt->close();

            // Finalize login
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $email;
            unset($_SESSION['pending_user_id']);

            // Log successful login for staff User Logs
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $success = 1;
            $logStmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, success) VALUES (?, ?, ?)");
            if ($logStmt) {
                $logStmt->bind_param('isi', $userId, $ipAddress, $success);
                $logStmt->execute();
                $logStmt->close();
            }

            // Redirect based on onboarded
            if ((int)$onboarded === 0) {
                echo "<script>alert('Welcome! Please complete your profile.'); window.location.href = 'UpdateAccount.php';</script>";
                exit();
            } else {
                echo "<script>alert('Login successful! Welcome to your dashboard.'); window.location.href = 'index.php';</script>";
                exit();
            }
        } else {
            $stmt->close();
            $message = 'Invalid or expired code. Please try again.';
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
    <h1 class="welcome-text">Enter Verification Code</h1>
    <div class="alumytics-text"> Check your email for the 6-digit code</div>
  </div>
  <div class="right-panel" style="order: 1;">
    <form method="post" class="signup-one-form">
      <h2><i class="fas fa-shield-alt create-account-icon"></i> Verify Your Login</h2>
      <?php if (!empty($message)): ?>
        <div class="form-error-box" style="display:block;color:<?php echo $message_type === 'success' ? '#27ae60' : '#c0392b'; ?>;margin-bottom:10px;">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>
      <input type="text" name="code" placeholder="Enter 6-digit code" required autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}">
      <button type="submit" class="sign-up-button">Verify</button>
      <p class="signin-link"><a href="signin.php">Back to Sign In</a></p>
    </form>
  </div>
</div>
