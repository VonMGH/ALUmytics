<?php 

include 'includes/Aheader.php'; 

include 'database.php';



$db = Database::getInstance();

$conn = $db->getConnection();



session_start();



// Check if user is logged in

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {

    // User not logged in, redirect to signin page

    echo "<script>alert('Please sign in to access your dashboard.'); window.location.href = 'signin.php';</script>";

    exit();

}



// Get user information from session

$user_id = $_SESSION['user_id'];

$user_email = $_SESSION['email'];



// Verify user is still valid and email is verified

$stmt = $conn->prepare("SELECT email_verified, full_name, onboarded FROM users WHERE user_id = ? AND email = ?");

$stmt->bind_param('is', $user_id, $user_email);

$stmt->execute();

$result = $stmt->get_result();



if ($result->num_rows != 1) {

    // User not found, clear session and redirect

    session_destroy();

    echo "<script>alert('Session expired. Please sign in again.'); window.location.href = 'signin.php';</script>";

    exit();

}



$user_data = $result->fetch_assoc();

if (empty($user_data['onboarded']) || $user_data['onboarded'] == 0) {

    // Not onboarded, redirect to wizard

    echo "<script>alert('Please complete your profile.'); window.location.href = 'update-info-wizard.php';</script>";

    exit();

}



$stmt->close();



// Set the institutional email for the form (can be updated by user)

$institutional_email = $_SESSION['user_email'] ?? $user_email;



// Fetch personal info for prefill

$personal = [

  'first_name' => '',

  'middle_name' => '',

  'last_name' => '',

  'extension' => '',

  'sex' => '',

  'dob' => '',

  'institutional_email' => '',

  'personal_email' => '',

  'phone_number' => '',

  'street_address' => '',

  'province' => '',

  'city' => '',

  'barangay' => '',

  'zip_code' => '',

  'profile_photo' => ''

];

$education = [

  'school_university' => '',

  'campus_branch' => '',

  'college_department' => '',

  'program' => '',

  'major_specialization' => '',

  'alumni_id' => ''

];

$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, extension, sex, dob, institutional_email, personal_email, phone_number, street_address, province, city, barangay, zip_code, profile_photo FROM personal WHERE user_id = ? LIMIT 1");

$stmt->bind_param('i', $user_id);

$stmt->execute();

$stmt->bind_result($personal['first_name'], $personal['middle_name'], $personal['last_name'], $personal['extension'], $personal['sex'], $personal['dob'], $personal['institutional_email'], $personal['personal_email'], $personal['phone_number'], $personal['street_address'], $personal['province'], $personal['city'], $personal['barangay'], $personal['zip_code'], $personal['profile_photo']);

$stmt->fetch();

$stmt->close();

$stmt = $conn->prepare("SELECT school_university, campus_branch, college_department, program, major_specialization, alumni_id FROM education WHERE user_id = ? LIMIT 1");

$stmt->bind_param('i', $user_id);

$stmt->execute();

$stmt->bind_result($education['school_university'], $education['campus_branch'], $education['college_department'], $education['program'], $education['major_specialization'], $education['alumni_id']);

$stmt->fetch();

$stmt->close();





if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve form data

    $first_name = $_POST['first_name'];

    $middle_name = $_POST['middle_name'];

    $last_name = $_POST['last_name'];

    $extension = $_POST['extension'];

    $sex = $_POST['sex'];

    $dob = $_POST['dob'];

    $institutional_email = $_POST['institutional_email'];

    $personal_email = $_POST['personal_email'];

    $phone_number = $_POST['phone_number'];

    $street_address = $_POST['street_address'];

    $province = $_POST['province'];

    $city = $_POST['city'];

    $barangay = $_POST['barangay'];

    $zip_code = $_POST['zip_code'];

    $school_university = $_POST['school_university'];

    $campus_branch = $_POST['campus_branch'];

    $college_department = $_POST['college_department'];

    $program = $_POST['program'];

    $major_specialization = $_POST['major_specialization'];

    $alumni_id = $_POST['alumni_id'];



    // Handle profile photo upload

    $profile_photo = $personal['profile_photo']; // Keep existing photo if no new one uploaded



    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {

        $upload_dir = 'uploads/';

        // Create directory if it doesn't exist

        if (!is_dir($upload_dir)) {

            mkdir($upload_dir, 0755, true);

        }



        $file_tmp = $_FILES['profile_photo']['tmp_name'];

        $file_name = basename($_FILES['profile_photo']['name']);

        $file_type = mime_content_type($file_tmp);

        $file_size = $_FILES['profile_photo']['size'];

        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];



        if (in_array($file_type, $allowed_types) && $file_size <= 2 * 1024 * 1024) {

            // Delete old photo if it exists

            if ($personal['profile_photo'] && file_exists($personal['profile_photo'])) {

                unlink($personal['profile_photo']);

            }

            $profile_photo = $upload_dir . uniqid('profile_') . '_' . $file_name;

            move_uploaded_file($file_tmp, $profile_photo);

        } else {

            echo "Invalid file type or size. Upload JPEG/PNG under 2MB.";

            exit;

        }

    }



    // Update Personal Information

    $stmt = $conn->prepare("UPDATE personal SET first_name = ?, middle_name = ?, last_name = ?, extension = ?, sex = ?, dob = ?, institutional_email = ?, personal_email = ?, phone_number = ?, street_address = ?, province = ?, city = ?, barangay = ?, zip_code = ?, profile_photo = ? WHERE user_id = ?");

    $types = str_repeat('s', 15) . 'i';
    $stmt->bind_param(
        $types,
        $first_name,
        $middle_name,
        $last_name,
        $extension,
        $sex,
        $dob,
        $institutional_email,
        $personal_email,
        $phone_number,
        $street_address,
        $province,
        $city,
        $barangay,
        $zip_code,
        $profile_photo,
        $user_id
    );



    if ($stmt->execute()) {

        // Update Educational Information

        $stmt_edu = $conn->prepare("UPDATE education SET school_university = ?, campus_branch = ?, college_department = ?, program = ?, major_specialization = ?, alumni_id = ? WHERE user_id = ?");

        $stmt_edu->bind_param("ssssssi", $school_university, $campus_branch, $college_department, $program, $major_specialization, $alumni_id, $user_id);

        $stmt_edu->execute();



        echo "<script>alert('Profile updated successfully!'); window.location.href = 'index.php';</script>";

        exit();

    } else {

        echo "<script>alert('Error updating profile. Please try again.');</script>";

    }

}

?>



<?php include 'includes/Asidebar.php'; ?>

<link rel="stylesheet" href="alumni.css">



<main class="profile-content">

  <h1 class="profile-heading">Profile Information</h1>



  <div class="profile-container">

    <form method="post" enctype="multipart/form-data">

      <!-- Profile Picture Section -->

      <div class="profile-photo-section">

        <div class="avatar-placeholder">

          <img id="profilePreview" src="<?php echo $personal['profile_photo'] ? $personal['profile_photo'] : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDE1MCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiBmaWxsPSIjZjBmMGYwIi8+CjxjaXJjbGUgY3g9Ijc1IiBjeT0iNjAiIHI9IjIwIiBmaWxsPSIjY2NjIi8+CjxwYXRoIGQ9Ik00NSAxMjBjMC0xNi41NjkgMTMuNDMxLTMwIDMwLTMwczcwIDEzLjQzMSAzMCAzMCIgZmlsbD0iI2NjYyIvPgo8L3N2Zz4='; ?>" alt="Profile Photo" style="width:150px;height:150px;border-radius:50%;object-fit:cover;<?php echo !$personal['profile_photo'] ? 'display:none;' : ''; ?>" />

          <div id="avatarInitials" style="width:150px;height:150px;border-radius:50%;background:#2e7d32;display:<?php echo $personal['profile_photo'] ? 'none' : 'flex'; ?>;align-items:center;justify-content:center;font-size:3em;color:#fff;font-weight:bold;">

            <?php

              $initials = '';

              if (!empty($personal['first_name'])) $initials .= strtoupper($personal['first_name'][0]);

              if (!empty($personal['last_name'])) $initials .= strtoupper($personal['last_name'][0]);

              echo $initials ?: '?';

            ?>

          </div>

        </div>



        <label for="profile_photo" class="upload-button" aria-label="Change Profile Photo">Change Profile Photo</label>

        <input type="file" id="profile_photo" name="profile_photo" accept="image/*" hidden>



        <br>

        <small class="upload-instruction">Recommended 400x400, Max 2MB.</small>

      </div>



      



      <!-- Basic Information Section -->

      <h3>Basic Information</h3>

      <div class="form-group">

        <label for="first_name">First Name</label>

        <input type="text" id="first_name" name="first_name" placeholder="First Name" required value="<?php echo htmlspecialchars($personal['first_name']); ?>" aria-label="First Name">

      </div>

      <div class="form-group">

        <label for="middle_name">Middle Name</label>

        <input type="text" id="middle_name" name="middle_name" placeholder="Middle Name" value="<?php echo htmlspecialchars($personal['middle_name']); ?>" aria-label="Middle Name">

      </div>

      <div class="form-group">

        <label for="last_name">Last Name</label>

        <input type="text" id="last_name" name="last_name" placeholder="Last Name" required value="<?php echo htmlspecialchars($personal['last_name']); ?>" aria-label="Last Name">

      </div>

      <div class="form-group">

        <label for="extension">Extension</label>

        <input type="text" id="extension" name="extension" placeholder="Extension" value="<?php echo htmlspecialchars($personal['extension']); ?>" aria-label="Extension">

      </div>



      <div class="sex-dob-row">

        <div class="sex-selection form-group">

          <label>Sex:</label>

          <label><input type="radio" id="sex_male" name="sex" value="male" required <?php if($personal['sex']==='male') echo 'checked'; ?> aria-label="Male"> Male</label>

          <label><input type="radio" id="sex_female" name="sex" value="female" required <?php if($personal['sex']==='female') echo 'checked'; ?> aria-label="Female"> Female</label>

        </div>

        <div class="dob-field form-group">

          <label for="dob">Date of Birth</label>

          <input type="date" id="dob" name="dob" required value="<?php echo htmlspecialchars($personal['dob']); ?>" aria-label="Date of Birth">

        </div>

      </div>



      <!-- Contact Information Section -->

      <h3>Contact Information</h3>

      <div class="form-group">

        <label for="institutional_email">Institutional Email</label>

        <input type="email" id="institutional_email" name="institutional_email" placeholder="Institutional Email" required value="<?php echo htmlspecialchars($personal['institutional_email']); ?>" aria-label="Institutional Email">

      </div>

      <div class="form-group">

        <label for="personal_email">Personal Email</label>

        <input type="email" id="personal_email" name="personal_email" placeholder="Personal Email" value="<?php echo htmlspecialchars($personal['personal_email']); ?>" aria-label="Personal Email">

      </div>

      <div class="form-group">

        <label for="phone_number">Phone Number</label>

        <input type="tel" id="phone_number" name="phone_number" placeholder="Phone Number" required value="<?php echo htmlspecialchars($personal['phone_number']); ?>" aria-label="Phone Number">

      </div>

      <div class="form-group">

        <label for="street_address">Street Address</label>

        <input type="text" id="street_address" name="street_address" placeholder="Street Address" required value="<?php echo htmlspecialchars($personal['street_address']); ?>" aria-label="Street Address">

      </div>

      <div class="form-group">

        <label for="province">Province</label>

        <select id="province" name="province" required aria-label="Province" data-selected="<?php echo htmlspecialchars($personal['province']); ?>">

          <option value="" disabled selected>Select Province</option>

        </select>

      </div>

      <div class="form-group">

        <label for="city">City/Municipality</label>

        <select id="city" name="city" required aria-label="City/Municipality" data-selected="<?php echo htmlspecialchars($personal['city']); ?>">

          <option value="" disabled selected>Select City/Municipality</option>

        </select>

      </div>

      <div class="form-group">

        <label for="barangay">Barangay</label>

        <select id="barangay" name="barangay" required aria-label="Barangay" data-selected="<?php echo htmlspecialchars($personal['barangay']); ?>">

          <option value="" disabled selected>Select Barangay</option>

        </select>

      </div>

      <div class="form-group">

        <label for="zip_code">Zip Code</label>

        <input type="text" id="zip_code" name="zip_code" placeholder="Zip Code" required value="<?php echo htmlspecialchars($personal['zip_code']); ?>" aria-label="Zip Code">

      </div>



      <!-- Educational Information Section -->

      <h3>Educational Information</h3>

      <div class="input-row">

        <div class="form-group">

          <label for="school_university">School/University</label>

          <input type="text" id="school_university" name="school_university" placeholder="School/University" required value="<?php echo htmlspecialchars($education['school_university']); ?>" aria-label="School/University">

        </div>

        <div class="form-group">

          <label for="campus_branch">Campus/Branch</label>

          <input type="text" id="campus_branch" name="campus_branch" placeholder="Campus/Branch" required value="<?php echo htmlspecialchars($education['campus_branch']); ?>" aria-label="Campus/Branch">

        </div>

        <div class="form-group">

          <label for="college_department">College/Department</label>

          <input type="text" id="college_department" name="college_department" placeholder="College/Department" required value="<?php echo htmlspecialchars($education['college_department']); ?>" aria-label="College/Department">

        </div>

        <div class="form-group">

          <label for="program">Program</label>

          <input type="text" id="program" name="program" placeholder="Program" required value="<?php echo htmlspecialchars($education['program']); ?>" aria-label="Program">

        </div>

        <div class="form-group">

          <label for="major_specialization">Major/Specialization</label>

          <input type="text" id="major_specialization" name="major_specialization" placeholder="Major/Specialization" required value="<?php echo htmlspecialchars($education['major_specialization']); ?>" aria-label="Major/Specialization">

        </div>

        <div class="form-group">

          <label for="alumni_id">Alumni ID</label>

          <input type="text" id="alumni_id" name="alumni_id" placeholder="Alumni ID" required value="<?php echo htmlspecialchars($education['alumni_id']); ?>" aria-label="Alumni ID">

        </div>

      </div>



      <!-- Submit Button -->

      <div class="form-actions">

        <button type="submit" class="save-button">Save Changes</button>

      </div>

    </form>

  </div>

</main>

<script src="js/address-dropdown.js"></script>

<!-- Removed academic-dropdowns.js because campus/college/program are now text fields -->

<script>

document.addEventListener('DOMContentLoaded', function() {

  // Academic selects removed; fields are now plain text inputs

  // Set address dropdowns after options are loaded

  setTimeout(function() {

    var province = <?php echo json_encode($personal['province']); ?>;

    var city = <?php echo json_encode($personal['city']); ?>;

    var barangay = <?php echo json_encode($personal['barangay']); ?>;

    if (province) document.getElementById('province').value = province;

    if (city) document.getElementById('city').value = city;

    if (barangay) document.getElementById('barangay').value = barangay;

  }, 1000);

});

</script>

<script>

// Proper case formatting for names and programs

function toProperCase(str) {

  return str.replace(/\w\S*/g, function(txt){

    return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();

  });

}

['first_name','middle_name','last_name','extension','school_university','major_specialization','program'].forEach(function(id) {

  var el = document.getElementsByName(id)[0];

  if (el) {

    el.addEventListener('blur', function() {

      el.value = toProperCase(el.value);

    });

  }

});

</script>




        <br>

        <small class="upload-instruction">Recommended 400x400, Max 2MB.</small>

      </div>



      


</main>

<script src="js/address-dropdown.js"></script>

<script src="js/academic-dropdowns.js"></script>

<script>

document.addEventListener('DOMContentLoaded', function() {

  // Set dropdowns for academic info

  setTimeout(function() {

    var campus = <?php echo json_encode($education['campus_branch']); ?>;

    var college = <?php echo json_encode($education['college_department']); ?>;

    var program = <?php echo json_encode($education['program']); ?>;

    if (campus) document.getElementById('campus_branch').value = campus;

    if (college) document.getElementById('college_department').value = college;

    if (program) document.getElementById('program').value = program;

  }, 300);

  // Set address dropdowns after options are loaded

  setTimeout(function() {

    var province = <?php echo json_encode($personal['province']); ?>;

    var city = <?php echo json_encode($personal['city']); ?>;

    var barangay = <?php echo json_encode($personal['barangay']); ?>;

    if (province) document.getElementById('province').value = province;

    if (city) document.getElementById('city').value = city;

    if (barangay) document.getElementById('barangay').value = barangay;

  }, 1000);

});

</script>

<script>

// Proper case formatting for names and programs

function toProperCase(str) {

  return str.replace(/\w\S*/g, function(txt){

    return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();

  });

}

['first_name','middle_name','last_name','extension','school_university','major_specialization','program'].forEach(function(id) {

  var el = document.getElementsByName(id)[0];

  if (el) {

    el.addEventListener('blur', function() {

      el.value = toProperCase(el.value);

    });

  }

});

</script>


