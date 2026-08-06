<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db/Database.php';
// Use a dedicated session for staff so it doesn't conflict with alumni session
if (session_status() === PHP_SESSION_NONE) {
    session_name('staff_session');
    session_start();
}

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$toastData = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn = Database::getInstance()->getConnection();
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Debug logging
    error_log("Login attempt - Email: " . $email);
    
    // Query to get user details including role
    $stmt = $conn->prepare("SELECT user_id, password_hash, role, college_id FROM users WHERE email = ? AND (role = 'admin' OR role = 'coordinator' OR role = 'superadmin')");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $userId = $user['user_id'];
        $hashedPassword = $user['password_hash'];
        $userRole = $user['role'];
        $userCollegeId = $user['college_id'];

        if (password_verify($password, $hashedPassword)) {
            // Debug logging
            error_log("Password verified - Role: " . $userRole);

            // Verify staff role
            if (!in_array($userRole, ['admin', 'coordinator', 'superadmin'])) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Invalid access level for staff portal']);
                    exit;
                }
                $toastData = [
                    'message' => 'Invalid access level for staff portal',
                    'type' => 'error'
                ];
                return;
            }
            
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $userRole;
                // Always set college_id in session, will be null for non-coordinators
                $_SESSION['college_id'] = $userCollegeId;
            
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
                $onboardedResult = $onboardedStmt->get_result();
                $onboardedData = $onboardedResult->fetch_assoc();
                $onboarded = $onboardedData['onboarded'];
                $onboardedStmt->close();
                if ($onboarded == 0) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => '../alumni/UpdateAccount.php']);
                    exit;
                } else {
                    // All staff roles redirect to unified dashboard
                    $redirectUrl = 'index.php';
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
                    exit;
                }
            }
            $onboardedStmt = $conn->prepare("SELECT onboarded FROM users WHERE user_id = ?");
            $onboardedStmt->bind_param('i', $userId);
            $onboardedStmt->execute();
            $onboardedResult = $onboardedStmt->get_result();
            $onboardedData = $onboardedResult->fetch_assoc();
            $onboarded = $onboardedData['onboarded'];
            $onboardedStmt->close();
            if ($onboarded == 0) {
                // Redirect instantly for non-onboarded users
                header('Location: ../alumni/UpdateAccount.php');
                exit;
            } else {
                // All staff roles redirect to unified dashboard instantly
                header('Location: index.php');
                exit;
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Incorrect password. Try again.']);
                exit;
            }
            $toastData = [
                'message' => 'Incorrect password. Try again.',
                'type' => 'error'
            ];
        }
    } else {
        // First check if this email exists as an alumni account
        $checkAlumni = $conn->prepare("SELECT 1 FROM users WHERE email = ? AND role = 'alumni' LIMIT 1");
        $checkAlumni->bind_param('s', $email);
        $checkAlumni->execute();
        $checkAlumni->store_result();
        
        if ($checkAlumni->num_rows == 1) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'This is an alumni account. Please use the alumni portal to login.']);
                exit;
            }
            $toastData = [
                'message' => 'This is an alumni account. Please use the alumni portal to login.',
                'type' => 'error'
            ];
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'No staff account found with these credentials.']);
                exit;
            }
            $toastData = [
                'message' => 'No staff account found with these credentials.',
                'type' => 'error'
            ];
        }
        $checkAlumni->close();
    }
    $stmt->close();
    // Do NOT call $conn->close() here
}

// Fetch colleges for dropdown
$colleges = [];
$conn = Database::getInstance()->getConnection();
$result = $conn->query("SELECT id, name FROM colleges ORDER BY name ASC");
while ($row = $result->fetch_assoc()) {
    $colleges[] = $row;
}
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/footer_fixed.css">
<style>
.login-container {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.login-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    padding: 2.5rem;
    max-width: 450px;
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

.login-header {
    text-align: center;
    margin-bottom: 2rem;
}

.login-header h2 {
    color: #222222;
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.login-header i {
    font-size: 2.5rem;
    color: #222222;
    margin-bottom: 1rem;
    display: block;
}

.form-label.fw-bold {
    font-weight: 700 !important;
    color: var(--text-color);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-control, .form-select {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
    background-color: #f8f9fa;
}

.form-control:focus, .form-select:focus {
    border-color: #333333;
    box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.18);
    background-color: white;
}

.form-control::placeholder {
    color: #6c757d;
    font-style: italic;
}

.btn-primary {
    background: linear-gradient(135deg, #333333 0%, #111111 100%) !important;
    border: none !important;
    border-radius: 10px !important;
    padding: 0.875rem 1.5rem !important;
    font-weight: 600 !important;
    font-size: 1rem !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
}

.btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4) !important;
}

@media (max-width: 576px) {
    .login-container {
        padding: 1rem;
    }
    
    .login-card {
        padding: 2rem;
    }
    
    .login-header h2 {
        font-size: 1.5rem;
    }
}
</style>

<div class="login-container">
  <div class="login-card">
    <div class="login-header">
      <i class="fas fa-sign-in-alt"></i>
      <h2>Staff Sign In</h2>
    </div>
    <form id="signInForm" method="post" action="">
      <div class="mb-3">
        <label for="email" class="form-label fw-bold">Email</label>
        <input type="email" name="email" id="email" class="form-control" placeholder="e.g., user@example.com" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label fw-bold">Password</label>
        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
      </div>
      <div id="loginError" class="alert alert-danger" style="display: none;"></div>
      <button type="submit" class="btn btn-primary w-100">Sign In</button>
    </form>

    <script>
    document.getElementById('signInForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch(this.action || window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                const errorDiv = document.getElementById('loginError');
                errorDiv.textContent = data.error;
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.getElementById('loginError');
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = 'block';
        });
    });
    </script>
  </div>
</div>

<?php include 'includes/footer.php'; ?> 