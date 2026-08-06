<?php
include 'includes/Aheader.php';
include 'includes/Asidebar.php';
include 'database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please sign in.'); window.location.href = 'signin.php';</script>";
    exit();
}
$user_id = $_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

// Fetch user info
$stmt = $conn->prepare("SELECT email, phone_number, profile_photo, full_name FROM users LEFT JOIN personal ON users.user_id = personal.user_id WHERE users.user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($email, $phone, $profile_photo, $full_name);
$stmt->fetch();
$stmt->close();

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
    $stmt->bind_result($hash, $user_email, $user_full_name);
    $stmt->fetch();
    $stmt->close();
    
    if (!password_verify($current, $hash)) {
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

// Handle Account Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $stmt = $conn->prepare("SELECT email, full_name FROM users WHERE user_id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($del_email, $del_name);
    $stmt->fetch();
    $stmt->close();
    
    sendAccountChangeEmail($del_email, $del_name, 'delete');
    
    // Delete user and related data
    $conn->query("DELETE FROM certifications WHERE user_id=$user_id");
    $conn->query("DELETE FROM awards WHERE user_id=$user_id");
    $conn->query("DELETE FROM employment WHERE user_id=$user_id");
    $conn->query("DELETE FROM education WHERE user_id=$user_id");
    $conn->query("DELETE FROM personal WHERE user_id=$user_id");
    $conn->query("DELETE FROM users WHERE user_id=$user_id");
    
    session_destroy();
    echo "<script>alert('Account deleted successfully.'); window.location.href = 'signup.php';</script>";
    exit();
}

function sendAccountChangeEmail($to, $name, $type) {
    $subject = 'Account Change Notification';
    if ($type === 'email') {
        $body = "Hi $name,<br>Your email address was changed. If this wasn't you, please contact support.";
    } elseif ($type === 'password') {
        $body = "Hi $name,<br>Your password was changed. If this wasn't you, please contact support.";
    } elseif ($type === 'delete') {
        $body = "Hi $name,<br>Your account was deleted. If this wasn't you, please contact support.";
    } else {
        $body = "Hi $name,<br>Your account was updated.";
    }
    sendEmail($to, $subject, $body);
}
?>
<link rel="stylesheet" href="aAccount-setting.css">
<link rel="stylesheet" href="alumni.css">
<main class="profile-content">
  <h1 class="profile-heading">Account Settings</h1>

  <!-- Account Information Section -->
  <section class="profile-container">
    <div class="section-header">
      <i class="fas fa-user-circle"></i>
      <h2>Account Information</h2>
    </div>
    
    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?php echo $msg_type ?? 'success'; ?>">
        <i class="fas fa-check-circle"></i>
        <?php echo $msg; ?>
      </div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data" class="account-form">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
      </div>
      
      <div class="form-group">
        <label for="phone">Phone Number</label>
        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
      </div>
      
      <div class="form-group">
        <label for="profile_photo">Profile Photo</label>
        <div class="profile-photo-container">
          <div class="avatar-placeholder">
            <img id="profilePreview" src="<?php echo $profile_photo ? $profile_photo : 'images/avatar-placeholder.png'; ?>" alt="Profile Photo" />
          </div>
          <div class="file-input-wrapper">
            <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
            <label for="profile_photo" class="file-input-label">
              <i class="fas fa-camera"></i>
              Choose Photo
            </label>
          </div>
        </div>
      </div>
      
      <button type="submit" class="save-button" name="update_account">
        <i class="fas fa-save"></i>
        Save Changes
      </button>
    </form>
  </section>

  <!-- Password Change Section -->
  <section class="profile-container">
    <div class="section-header">
      <i class="fas fa-lock"></i>
      <h2>Change Password</h2>
    </div>
    
    <?php if (!empty($pw_msg)): ?>
      <div class="alert alert-<?php echo $pw_msg_type ?? 'success'; ?>">
        <i class="fas fa-<?php echo ($pw_msg_type ?? 'success') === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $pw_msg; ?>
      </div>
    <?php endif; ?>
    
    <form method="post" class="password-form">
      <div class="form-group">
        <label for="current-password">Current Password</label>
        <div class="password-input-wrapper">
          <input type="password" id="current-password" name="current_password" required>
          <i class="fas fa-eye toggle-password"></i>
        </div>
      </div>
      
      <div class="form-group">
        <label for="new-password">New Password</label>
        <div class="password-input-wrapper">
          <input type="password" id="new-password" name="new_password" required>
          <i class="fas fa-eye toggle-password"></i>
        </div>
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
      
      <div class="form-group">
        <label for="confirm-password">Confirm New Password</label>
        <div class="password-input-wrapper">
          <input type="password" id="confirm-password" name="confirm_password" required>
          <i class="fas fa-eye toggle-password"></i>
        </div>
      </div>
      
      <button type="submit" class="save-button" name="change_password">
        <i class="fas fa-key"></i>
        Change Password
      </button>
    </form>
  </section>

  <!-- Danger Zone Section -->
  <section class="profile-container danger-zone">
    <div class="section-header">
      <i class="fas fa-exclamation-triangle"></i>
      <h2>Danger Zone</h2>
    </div>
    
    <div class="danger-warning">
      <p><strong>Warning:</strong> The following actions are irreversible and will permanently delete your account and all associated data.</p>
    </div>
    
    <button class="delete-button" onclick="showDeleteConfirmation()">
      <i class="fas fa-trash-alt"></i>
      Delete Account
    </button>
  </section>
</main>

<!-- Delete Account Confirmation Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2><i class="fas fa-exclamation-triangle"></i> Delete Account</h2>
      <span class="close-btn" onclick="closeDeleteModal()">&times;</span>
    </div>
    
    <div class="modal-body">
      <div class="warning-message">
        <p><strong>Are you absolutely sure you want to delete your account?</strong></p>
        <p>This action will permanently:</p>
        <ul>
          <li>Delete your account and all personal information</li>
          <li>Remove all your certifications and awards</li>
          <li>Delete your employment history</li>
          <li>Remove your education records</li>
          <li>This action cannot be undone</li>
        </ul>
      </div>
      
      <div class="confirmation-input">
        <label for="deleteConfirmation">Type "DELETE" to confirm:</label>
        <input type="text" id="deleteConfirmation" placeholder="Type DELETE to confirm">
      </div>
    </div>
    
    <div class="modal-footer">
      <button id="cancelDelete" onclick="closeDeleteModal()">
        <i class="fas fa-times"></i>
        Cancel
      </button>
      <button id="confirmDelete" onclick="confirmDelete()" disabled>
        <i class="fas fa-trash-alt"></i>
        Delete Account
      </button>
    </div>
  </div>
</div>

<script>
// Profile photo preview
const fileInput = document.getElementById('profile_photo');
const previewImage = document.getElementById('profilePreview');

if (fileInput) {
  fileInput.addEventListener('change', function () {
    const file = this.files[0];
    if (file && file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = function (e) {
        previewImage.src = e.target.result;
      }
      reader.readAsDataURL(file);
    } else {
      previewImage.src = 'images/avatar-placeholder.png';
    }
  });
}

// Password visibility toggle
document.querySelectorAll('.toggle-password').forEach(toggle => {
  toggle.addEventListener('click', function() {
    const input = this.previousElementSibling;
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
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

// Delete account modal
function showDeleteConfirmation() {
  document.getElementById('deleteModal').style.display = 'block';
  document.getElementById('deleteConfirmation').focus();
}

function closeDeleteModal() {
  document.getElementById('deleteModal').style.display = 'none';
  document.getElementById('deleteConfirmation').value = '';
  document.getElementById('confirmDelete').disabled = true;
}

// Confirm deletion input validation
document.getElementById('deleteConfirmation').addEventListener('input', function() {
  const confirmButton = document.getElementById('confirmDelete');
  confirmButton.disabled = this.value !== 'DELETE';
});

function confirmDelete() {
  const confirmation = document.getElementById('deleteConfirmation').value;
  if (confirmation === 'DELETE') {
    // Create and submit the delete form
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="delete_account" value="1">';
    document.body.appendChild(form);
    form.submit();
  }
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('deleteModal');
  if (event.target === modal) {
    closeDeleteModal();
  }
}

// Auto logout after password change
<?php if (isset($logout_after_password_change) && $logout_after_password_change): ?>
setTimeout(function() {
  alert('You will be logged out to test your new password.');
  window.location.href = 'logout.php';
}, 3000);
<?php endif; ?>
</script>
