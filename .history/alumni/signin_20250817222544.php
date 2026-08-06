<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'database.php';
session_start();

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn = Database::getInstance()->getConnection();
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Only allow alumni role
    $role = 'alumni';
    $stmt = $conn->prepare("SELECT user_id, password_hash FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param('ss', $email, $role);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 1) {
        $stmt->bind_result($userId, $hashedPassword);
        $stmt->fetch();
        if (password_verify($password, $hashedPassword)) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $email;
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $success = 1;
            $logStmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, success) VALUES (?, ?, ?)");
            $logStmt->bind_param('isi', $userId, $ipAddress, $success);
            $logStmt->execute();
            $logStmt->close();
                if ($isAjax) {
                	$onboardedStmt = $conn->prepare("SELECT onboarded FROM users WHERE user_id = ?");
                	$onboardedStmt->bind_param('i', $userId);
                	$onboardedStmt->execute();
                	$onboardedStmt->bind_result($onboarded);
                	$onboardedStmt->fetch();
                	$onboardedStmt->close();
                	if ($onboarded == 0) {
                		header('Content-Type: application/json');
                		echo json_encode(['success' => true, 'redirect' => 'UpdateAccount.php']);
                		exit;
                	} else {
                		header('Content-Type: application/json');
                		echo json_encode(['success' => true, 'redirect' => 'index.php']);
                		exit;
                	}
                }
            $onboardedStmt = $conn->prepare("SELECT onboarded FROM users WHERE user_id = ?");
            $onboardedStmt->bind_param('i', $userId);
            $onboardedStmt->execute();
            $onboardedStmt->bind_result($onboarded);
            $onboardedStmt->fetch();
            $onboardedStmt->close();
            if ($onboarded == 0) {
                echo "<script>alert('Welcome! Please complete your profile.'); window.location.href = 'UpdateAccount.php';</script>";
                exit();
            } else {
                echo "<script>alert('Login successful! Welcome to your dashboard.'); window.location.href = 'index.php';</script>";
                exit();
            }
        } else {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $success = 0;
            $logStmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, success) VALUES (?, ?, ?)");
            $logStmt->bind_param('isi', $userId, $ipAddress, $success);
            $logStmt->execute();
            $logStmt->close();
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Incorrect password. Try again.']);
                exit;
            }
            echo "<script>alert('Incorrect password. Try again.');</script>";
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Email not found or not an alumni account.']);
            exit;
        }
        echo "<script>alert('Email not found or not an alumni account.');</script>";
    }
    $stmt->close();
    $conn->close();
}

include 'includes/Aheader.php';
?>
<link rel="stylesheet" href="signin.css?v=<?php echo time(); ?>">
<script src="js/signin-ajax.js?v=<?php echo time(); ?>"></script>
<div class="signup-one-container">
  <div class="left-panel" style="order: 2;">
    <div class="quote">“</div>
    <h1 class="welcome-text">You are the legacy.</h1>
    <div class="alumytics-text"> Keep shaping it</div>
  </div>
  <div class="right-panel" style="order: 1;">
    <form id="signInForm" class="signup-one-form" method="post" action="">
      <h2><i class="fas fa-sign-in-alt create-account-icon"></i> Alumni Sign In</h2>
      <input type="email" name="email" placeholder="Email Address" required autocomplete="email" autofocus>
      <div class="password-container">
        <input type="password" id="password" name="password" placeholder="Password" required autocomplete="current-password">
        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Toggle password visibility">
          <i class="fas fa-eye" id="eyeIcon"></i>
        </button>
      </div>
      <div class="form-error-box" style="display:none;color:#c0392b;margin-bottom:10px;"></div>
      <button type="submit" class="sign-up-button">Sign In</button>
      <p class="signin-link">Don't have an account? <a href="signup.php">Sign Up</a></p>
    </form>
  </div>
</div>

