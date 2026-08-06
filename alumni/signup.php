<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'database.php'; // Include the Database class
require_once 'email-config.php';
require_once 'mail_helper.php';

session_start(); // Start session

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Helper to capitalize each word
function capitalizeWords($str) {
    return ucwords(strtolower(trim($str)));
}

// Handle form submission
// Check if this is a pre-created account (from staff user management)
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$preCreatedAccount = false;
$userEmail = '';

if ($userId) {
    $conn = Database::getInstance()->getConnection();
    // Verify this is a valid alumni account that hasn't been onboarded
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ? AND role = 'alumni' AND onboarded = 0");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $preCreatedAccount = true;
        $userEmail = $row['email'];
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn = Database::getInstance()->getConnection();

    // Get form data and clean it
    $first_name = capitalizeWords($_POST['first_name'] ?? '');
    $middle_name = capitalizeWords($_POST['middle_name'] ?? '');
    $last_name = capitalizeWords($_POST['last_name'] ?? '');
    $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
    $email = $preCreatedAccount ? $userEmail : trim($_POST['email']);
    $password = $_POST['password'];
    $alumni_id_raw = trim($_POST['alumni_id'] ?? '');
    $alumni_id = strtoupper($alumni_id_raw);

    // Validation array to collect errors
    $errors = [];

    // Validate required fields
    if (empty($first_name)) {
        $errors[] = "First Name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last Name is required.";
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Validate password strength
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }

    // Normalize optional Middle Name and Alumni ID and validate only when provided
    if (in_array(strtoupper($middle_name), ['N/A', 'NA', 'NONE'])) {
        $middle_name = '';
    }

    if (in_array($alumni_id, ['N/A', 'NA', 'NONE'])) {
        $alumni_id = '';
    }

    if ($alumni_id !== '') {
        if (!preg_match('/^[0-9]{7,10}$/', $alumni_id)) {
            $errors[] = "Alumni ID must be 7-10 digits long.";
        }
    }

    // Check if the email already exists
    if (!$preCreatedAccount) {
        // Only check email existence for new registrations
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email already exists. Please use a different one.";
        }
        $stmt->close();
    }

    // Check if Alumni ID already exists (only if provided)
    if ($alumni_id !== '') {
        $stmt = $conn->prepare("SELECT id FROM education WHERE alumni_id = ?");
        $stmt->bind_param('s', $alumni_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Alumni ID already exists. Please verify your Alumni ID.";
        }
        $stmt->close();
    }

    // If there are validation errors, display them
    if (!empty($errors)) {
        $errorMessage = implode("<br>", $errors);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMessage]);
            exit;
        } else {
            echo "<script>alert('Registration failed:\n" . addslashes(strip_tags($errorMessage)) . "');</script>";
        }
    } else {
        // Proceed with registration
        if ($preCreatedAccount) {
            // Update existing user
            $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
            $stmt->bind_param('si', $full_name, $userId);
        } else {
            // Insert new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $createdAt = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, onboarded, created_at) VALUES (?, ?, ?, 'alumni', 0, ?)");
            $stmt->bind_param('ssss', $full_name, $email, $hashedPassword, $createdAt);
        }
        if ($stmt->execute()) {
            $userId = $conn->insert_id;
            // Insert Alumni ID into education table (may be empty if not provided)
            $stmt_edu = $conn->prepare("INSERT INTO education (user_id, alumni_id, school_university, campus_branch, college_department, program, major_specialization) VALUES (?, ?, '', '', '', '', '')");
            $stmt_edu->bind_param('is', $userId, $alumni_id);
            $stmt_edu->execute();
            $stmt_edu->close();
            // Insert personal info
            $sex = null;
            $dob = null;
            $stmt_personal = $conn->prepare("INSERT INTO personal (user_id, first_name, middle_name, last_name, sex, dob, institutional_email) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_personal->bind_param('issssss', $userId, $first_name, $middle_name, $last_name, $sex, $dob, $email);
            $stmt_personal->execute();
            $stmt_personal->close();

            // Generate email verification code
            try {
                $code = random_int(100000, 999999);
            } catch (Exception $e) {
                $code = random_int(100000, 999999);
            }

            $codeHash = hash('sha256', (string)$code);
            $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

            $verStmt = $conn->prepare("INSERT INTO email_verification_codes (user_id, code_hash, expires_at) VALUES (?, ?, ?)");
            $verStmt->bind_param('iss', $userId, $codeHash, $expiresAt);
            $verStmt->execute();
            $verStmt->close();

            // Send verification email
            $subject = 'ALUMytics Email Verification Code';
            $body = "Hello " . $full_name . ",\n\n" .
                "Thank you for creating an ALUMytics account.\n\n" .
                "Your email verification code is: " . $code . "\n\n" .
                "This code will expire in 30 minutes. If you did not sign up, you can ignore this email.\n\n" .
                "Regards,\nALUMytics Team";

            $headers = [];
            $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
            $headers[] = 'Reply-To: ' . REPLY_TO;
            $headers[] = 'X-Mailer: PHP/' . phpversion();

            send_alumytics_email($email, $subject, $body);

            // Store pending verification in session and redirect
            $_SESSION['pending_verification_user_id'] = $userId;

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => 'verify-email.php']);
                exit;
            }
            echo "<script>alert('Registration successful! Please check your email for a verification code.'); window.location.href = 'verify-email.php';</script>";
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again later.']);
                exit;
            }
            echo "<script>alert('Something went wrong. Please try again later.');</script>";
        }
        $stmt->close();
    }
    $conn->close();
    // For AJAX, exit is already called above
}

// Only include HTML if not AJAX
if (!$isAjax) {
    include 'includes/Aheader.php';
?>
<link rel="stylesheet" href="signup.css?v=<?php echo time(); ?>">
<script src="js/signup-ajax.js?v=<?php echo time(); ?>"></script>
<div class="signup-one-container">
    <div class="left-panel">
      <h2 class="welcome-text">Welcome</h2>
      <h3 class="to-text">to</h3>
      <h1 class="alumytics-text">ALUMytics!</h1><div class="quote">“</div>
      <h1>Your journey<br>doesn't end at graduation<br>—it evolves.</h1>
    </div>
    <div class="right-panel">
      <form id="signUpForm" class="signup-one-form" method="post" action="">
        <h2><i class="fas fa-user-graduate create-account-icon"></i> Create Account</h2>
        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="middle_name" placeholder="Middle Name (optional)">
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <div class="password-container">
        <input type="password" id="signup_password" name="password" placeholder="Password" required minlength="8">
          <button type="button" class="password-toggle" id="signupPasswordToggle" aria-label="Toggle password visibility">
            <i class="fas fa-eye" id="signupEyeIcon"></i>
          </button>
        </div>
        <small style="display:block;margin-top:-14px;margin-bottom:8px;font-size:12px;color:#555;">Min 8 characters, with uppercase, lowercase, and a number.</small>
        <input type="text" name="alumni_id" placeholder="Alumni ID (7-10 digits, optional)" maxlength="10" pattern="[0-9]{7,10}" title="Alumni ID must be 7-10 digits if provided">
        <div class="form-error-box" style="display:none;color:#c0392b;margin-bottom:10px;"></div>
        <button type="submit" class="sign-up-button">Sign Up</button>
        <p class="signin-link">If you have an account, <a href="signin.php">Sign In!</a></p>
      </form>
    </div>
  </div>
  <script>
document.addEventListener("DOMContentLoaded", function () {
  const passwordField = document.getElementById("signup_password");
  const passwordToggle = document.getElementById("signupPasswordToggle");
  const eyeIcon = document.getElementById("signupEyeIcon");

  if (!passwordField || !passwordToggle || !eyeIcon) return;

  passwordToggle.addEventListener("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const isPassword = passwordField.type === "password";
    passwordField.type = isPassword ? "text" : "password";
    eyeIcon.className = isPassword ? "fas fa-eye-slash" : "fas fa-eye";
    passwordToggle.setAttribute(
      "aria-label",
      isPassword ? "Hide password" : "Show password"
    );
    passwordField.focus();
  });

  passwordToggle.style.display = "flex";
  passwordToggle.style.opacity = "1";
  passwordToggle.style.visibility = "visible";

  passwordToggle.addEventListener("keydown", function (e) {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      passwordToggle.click();
    }
  });
});
</script>
<?php
    // You can include your footer here if needed
  }
