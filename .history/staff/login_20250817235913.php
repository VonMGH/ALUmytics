<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db/Database.php';
session_start();

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$toastData = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn = Database::getInstance()->getConnection();
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $college_id = isset($_POST['college_id']) ? $_POST['college_id'] : '';

    if (!$role || ($role === 'coordinator' && !$college_id)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Role and (if coordinator) college are required.']);
            exit;
        }
        $toastData = [
            'message' => 'Role and (if coordinator) college are required.',
            'type' => 'error'
        ];
    } else {
        if ($role === 'coordinator') {
            $stmt = $conn->prepare("SELECT user_id, password_hash, college_id FROM users WHERE email = ? AND role = ?");
            $stmt->bind_param('ss', $email, $role);
        } else {
            $stmt = $conn->prepare("SELECT user_id, password_hash FROM users WHERE email = ? AND role = ?");
            $stmt->bind_param('ss', $email, $role);
        }
        $stmt->execute();
        if ($role === 'coordinator') {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($userId, $hashedPassword, $db_college_id);
                $stmt->fetch();
                if ($db_college_id != $college_id) {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Selected college does not match your assigned college.']);
                        exit;
                    }
                    $toastData = [
                        'message' => 'Selected college does not match your assigned college.',
                        'type' => 'error'
                    ];
                }
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'No coordinator found with these credentials.']);
                    exit;
                }
                $toastData = [
                    'message' => 'No coordinator found with these credentials.',
                    'type' => 'error'
                ];
            }
        } else {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($userId, $hashedPassword);
                $stmt->fetch();
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'No user found with these credentials and role.']);
                    exit;
                }
                $toastData = [
                    'message' => 'No user found with these credentials and role.',
                    'type' => 'error'
                ];
            }
        }
        if (!isset($toastData)) { // Only proceed if no error so far
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
                    $onboardedStmt->bind_result($onboarded);
                    $onboardedStmt->fetch();
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
                $toastData = [
                    'message' => 'Incorrect password. Try again.',
                    'type' => 'error'
                ];
            }
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
$conn->close();
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
    color: var(--primary-color);
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.login-header i {
    font-size: 2.5rem;
    color: var(--primary-color);
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
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
    background-color: white;
}

.form-control::placeholder {
    color: #6c757d;
    font-style: italic;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, #1b5e20 100%) !important;
    border: none !important;
    border-radius: 10px !important;
    padding: 0.875rem 1.5rem !important;
    font-weight: 600 !important;
    font-size: 1rem !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3) !important;
}

.btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4) !important;
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
        <label for="role" class="form-label fw-bold">Role</label>
        <select name="role" id="role" class="form-select" required onchange="handleRoleChange()">
          <option value="">Select Role</option>
          <option value="coordinator">Coordinator</option>
          <option value="admin">Admin</option>
          <option value="superadmin">Super Admin</option>
        </select>
      </div>
      <div class="mb-3" id="collegeDiv" style="display:none;">
        <label for="college_id" class="form-label fw-bold">College (for Coordinators)</label>
        <select name="college_id" id="college_id" class="form-select">
          <option value="">Select College</option>
          <?php foreach ($colleges as $college): ?>
            <option value="<?= htmlspecialchars($college['id']) ?>"><?= htmlspecialchars($college['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label fw-bold">Email</label>
        <input type="email" name="email" id="email" class="form-control" placeholder="e.g., user@example.com" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label fw-bold">Password</label>
        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Sign In</button>
    </form>
  </div>
</div>
<script>
function handleRoleChange() {
    var role = document.getElementById('role').value;
    var collegeDiv = document.getElementById('collegeDiv');
    if (role === 'coordinator') {
        collegeDiv.style.display = 'block';
        document.getElementById('college_id').required = true;
    } else {
        collegeDiv.style.display = 'none';
        document.getElementById('college_id').required = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('role').addEventListener('change', handleRoleChange);
    handleRoleChange();
});
</script>
<?php include 'includes/footer.php'; ?> 