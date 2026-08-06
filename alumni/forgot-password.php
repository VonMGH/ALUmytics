<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'database.php';
require_once 'email-config.php';
require_once 'mail_helper.php';
session_start();

$conn = Database::getInstance()->getConnection();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        $role = 'alumni';
        $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND role = ? LIMIT 1");
        $stmt->bind_param('ss', $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        // Always show generic message to avoid leaking which emails exist
        $message = 'If this email is registered as an alumni account, a password reset link has been sent.';
        $message_type = 'success';

        if ($row = $result->fetch_assoc()) {
            $userId = (int)$row['user_id'];
            $fullName = $row['full_name'] ?? '';

            try {
                $rawToken = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $rawToken = bin2hex(openssl_random_pseudo_bytes(32));
            }

            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now

            $insert = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $insert->bind_param('iss', $userId, $tokenHash, $expiresAt);
            $insert->execute();
            $insert->close();

            // Build reset link dynamically based on current host and path
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/alumni/forgot-password.php'), '/\\');
            $resetLink = $protocol . '://' . $host . $basePath . '/reset-password.php?token=' . urlencode($rawToken);

            $subject = 'ALUMytics Password Reset Request';
            $body = "Hello " . ($fullName ?: 'Alumni') . ",\n\n" .
                "We received a request to reset the password for your ALUMytics alumni account.\n\n" .
                "To reset your password, please click the link below (or paste it into your browser):\n" .
                $resetLink . "\n\n" .
                "This link will expire in 1 hour. If you did not request a password reset, you can ignore this email.\n\n" .
                "Regards,\nALUMytics Team";

            $headers = [];
            $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
            $headers[] = 'Reply-To: ' . REPLY_TO;
            $headers[] = 'X-Mailer: PHP/' . phpversion();

            send_alumytics_email($email, $subject, $body);
        }

        $stmt->close();
    }
}

include 'includes/Aheader.php';
?>
<link rel="stylesheet" href="signin.css?v=<?php echo time(); ?>">
<div class="signup-one-container">
  <div class="left-panel" style="order: 2;">
    <div class="quote">“</div>
    <h1 class="welcome-text">Forgot Password</h1>
    <div class="alumytics-text"> Request a reset link via email</div>
  </div>
  <div class="right-panel" style="order: 1;">
    <form method="post" class="signup-one-form">
      <h2><i class="fas fa-unlock-alt create-account-icon"></i> Reset Your Password</h2>
      <?php if (!empty($message)): ?>
        <div class="form-error-box" style="display:block;color:<?php echo $message_type === 'success' ? '#27ae60' : '#c0392b'; ?>;margin-bottom:10px;">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>
      <input type="email" name="email" placeholder="Enter your registered email" required autocomplete="email" autofocus>
      <button type="submit" class="sign-up-button">Send Reset Link</button>
      <p class="signin-link"><a href="signin.php">Back to Sign In</a></p>
    </form>
  </div>
</div>
