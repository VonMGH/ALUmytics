<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'database.php'; // Include the Database class

session_start(); // Start session

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Helper to capitalize each word
function capitalizeWords($str) {
    return ucwords(strtolower(trim($str)));
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn = Database::getInstance()->getConnection();

    // Get form data and clean it
    $first_name = capitalizeWords($_POST['first_name'] ?? '');
    $middle_name = capitalizeWords($_POST['middle_name'] ?? '');
    $last_name = capitalizeWords($_POST['last_name'] ?? '');
    $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $alumni_id = trim($_POST['alumni_id']);

    // Validation array to collect errors
    $errors = [];

    // Validate required fields
    if (empty($first_name)) {
        $errors[] = "First Name is required.";
    }
    if (empty($middle_name)) {
        $errors[] = "Middle Name is required.";
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

    // Validate Alumni ID format (assuming it should be numeric and specific length)
    if (empty($alumni_id)) {
        $errors[] = "Alumni ID is required.";
    } elseif (!preg_match('/^[0-9]{7,10}$/', $alumni_id)) {
        $errors[] = "Alumni ID must be 7-10 digits long.";
    }

    // Check if the email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Email already exists. Please use a different one.";
    }
    $stmt->close();

    // Check if Alumni ID already exists
    $stmt = $conn->prepare("SELECT id FROM education WHERE alumni_id = ?");
    $stmt->bind_param('s', $alumni_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Alumni ID already exists. Please verify your Alumni ID.";
    }
    $stmt->close();

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
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $createdAt = date('Y-m-d H:i:s');
        // Insert new user into the database (set as not onboarded by default so they go to UpdateAccount.php)
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, onboarded, created_at) VALUES (?, ?, ?, 0, ?)");
        $stmt->bind_param('ssss', $full_name, $email, $hashedPassword, $createdAt);
        if ($stmt->execute()) {
            $userId = $conn->insert_id;
            // Insert Alumni ID into education table
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
            
            // Set session and redirect to update account
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $email;
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => 'UpdateAccount.php']);
                exit;
            }
            echo "<script>alert('Registration successful! Please complete your profile.'); window.location.href = 'UpdateAccount.php';</script>";
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
<link rel="stylesheet" href="signup.css">
<script src="js/signup-ajax.js"></script>
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
        <input type="text" name="middle_name" placeholder="Middle Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password (min 8 chars, include uppercase, lowercase, number)" required minlength="8">
        <input type="text" name="alumni_id" placeholder="Alumni ID (7-10 digits)" required maxlength="10" pattern="[0-9]{7,10}" title="Alumni ID must be 7-10 digits">
        <div class="form-error-box" style="display:none;color:#c0392b;margin-bottom:10px;"></div>
        <button type="submit" class="sign-up-button">Sign Up</button>
        <p class="signin-link">If you have an account, <a href="signin.php">Sign In!</a></p>
      </form>
    </div>
  </div>
<?php
    // You can include your footer here if needed
}

