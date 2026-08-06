<?php
include 'includes/Aheader.php';
include 'database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    echo "<script>alert('Please sign in to continue.'); window.location.href = 'signin.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email'];

// Prefill defaults
$personal = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'extension' => '',
    'sex' => '',
    'civil_status' => '',
    'dob' => '',
    'institutional_email' => $user_email,
    'personal_email' => '',
    'phone_number' => '',
    'street_address' => '',
  'country' => '',
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
    'alumni_id' => '',
    'year_graduated' => ''
];

$employment = [
    'employment_status' => '',
    'mobility' => '',
    'job_status' => '',
    'company_name' => '',
    'job_title' => '',
  'company_country' => '',
  'company_province' => '',
  'company_city' => '',
  'industry' => '',
    'it_category' => '',
    'salary_per_month' => '',
    'year_of_employment' => '',
    'company_type' => '',
    'work_arrangement' => ''
];

$company_address = [
    'company_street_address' => '',
    'company_province' => '',
    'company_city' => '',
    'company_barangay' => '',
    'company_zip_code' => ''
];

// Load existing data if available
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, extension, sex, civil_status, dob, institutional_email, personal_email, phone_number, street_address, country, province, city, barangay, zip_code, profile_photo FROM personal WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result(
    $personal['first_name'], $personal['middle_name'], $personal['last_name'], $personal['extension'], $personal['sex'], $personal['civil_status'], $personal['dob'],
    $personal['institutional_email'], $personal['personal_email'], $personal['phone_number'], $personal['street_address'], $personal['country'],
    $personal['province'], $personal['city'], $personal['barangay'], $personal['zip_code'], $personal['profile_photo']
);
$stmt->fetch();
$stmt->close();

// Load academic lookup options
$universitiesRes = $conn->query("SELECT id, name FROM universities ORDER BY name");
$campusesRes = $conn->query("SELECT id, university_id, name FROM campuses ORDER BY name");
$departmentsRes = $conn->query("SELECT id, university_id, campus_id, name FROM departments ORDER BY name");
$programsRes = $conn->query("SELECT id, university_id, campus_id, department_id, name FROM programs ORDER BY name");
$specializationsRes = $conn->query("SELECT id, university_id, campus_id, department_id, program_id, name FROM specializations ORDER BY name");

// If no institutional email saved, default to session email
if (empty($personal['institutional_email'])) {
    $personal['institutional_email'] = $user_email;
}

$stmt = $conn->prepare("SELECT school_university, campus_branch, college_department, program, major_specialization, alumni_id, year_graduated FROM education WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result(
    $education['school_university'], $education['campus_branch'], $education['college_department'],
    $education['program'], $education['major_specialization'], $education['alumni_id'], $education['year_graduated']
);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT employment_status, mobility, job_status, company_name, job_title, company_country, company_province, company_city, industry, it_category, salary_per_month, year_of_employment, company_type, work_arrangement FROM employment WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result(
  $employment['employment_status'], $employment['mobility'], $employment['job_status'], $employment['company_name'], $employment['job_title'], $employment['company_country'], $employment['company_province'], $employment['company_city'], $employment['industry'], $employment['it_category'],
  $employment['salary_per_month'], $employment['year_of_employment'], $employment['company_type'], $employment['work_arrangement']
);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT company_street_address, company_province, company_city, company_barangay, company_zip_code FROM company_address WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result(
    $company_address['company_street_address'], $company_address['company_province'], $company_address['company_city'],
    $company_address['company_barangay'], $company_address['company_zip_code']
);
$stmt->fetch();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Convert all text inputs to UPPERCASE
    $first_name = strtoupper(trim($_POST['first_name']));
    $middle_name_input = trim($_POST['middle_name'] ?? '');
    $middle_name = strtoupper($middle_name_input);
    $last_name = strtoupper(trim($_POST['last_name']));
    $extension = strtoupper(trim($_POST['extension']));
    $sex = $_POST['sex'];
    $civil_status = strtoupper(trim($_POST['civil_status'] ?? ''));
    $dob = $_POST['dob'];
    $institutional_email = strtolower(trim($_POST['institutional_email'])); // Email stays lowercase
    $personal_email = strtolower(trim($_POST['personal_email'])); // Email stays lowercase
    $phone_number = $_POST['phone_number'];
    $street_address = strtoupper(trim($_POST['street_address']));
    $country = strtoupper(trim($_POST['country']));
    $province = strtoupper(trim($_POST['province']));
    $city = strtoupper(trim($_POST['city']));
    $barangay = strtoupper(trim($_POST['barangay']));
    $zip_code = $_POST['zip_code'];
    $school_university = strtoupper(trim($_POST['school_university']));
    $campus_branch = strtoupper(trim($_POST['campus_branch']));
    $college_department = strtoupper(trim($_POST['college_department']));
    $program = strtoupper(trim($_POST['program']));
    $major_specialization = strtoupper(trim($_POST['major_specialization']));
    $alumni_id_input = trim($_POST['alumni_id'] ?? '');
    $alumni_id = strtoupper($alumni_id_input);
    $year_graduated = $_POST['year_graduated'];
    $employment_status = $_POST['employment_status'];
    $mobility = $_POST['mobility'];
    $job_status = strtoupper(trim($_POST['job_status'] ?? ''));
    $company_name = strtoupper(trim($_POST['company_name']));
    $job_title = strtoupper(trim($_POST['job_title'] ?? ''));
    $industry = strtoupper(trim($_POST['industry']));
    $it_category = $_POST['it_category'] ?? null;
    $salary_per_month = $_POST['salary_per_month'];
    $year_of_employment = $_POST['year_of_employment'];
    $company_type = $_POST['company_type'];
    $company_country = strtoupper(trim($_POST['company_country'] ?? ''));
    $work_arrangement = $_POST['work_arrangement'] ?? null;
    $company_street_address = strtoupper(trim($_POST['company_street_address']));
    // Local (PH dropdown) values
    $company_province = strtoupper(trim($_POST['company_province'] ?? ''));
    $company_city = strtoupper(trim($_POST['company_city'] ?? ''));
    $company_barangay = strtoupper(trim($_POST['company_barangay']));
    // International free-text overrides (if provided)
    $company_province_intl = strtoupper(trim($_POST['company_province_intl'] ?? ''));
    $company_city_intl = strtoupper(trim($_POST['company_city_intl'] ?? ''));
    $company_zip_code = $_POST['company_zip_code'];

    // Normalize mobility and company country to keep employment/local address consistent
    // If mobility is anything other than 'international', treat it as local
    if ($mobility !== 'international') {
        $mobility = 'local';
    } else {
        // For international, prefer free-text province/city if provided
        if ($company_province_intl !== '') {
            $company_province = $company_province_intl;
        }
        if ($company_city_intl !== '') {
            $company_city = $company_city_intl;
        }
    }

    // For local mobility, default country to Philippines when left empty
    if ($mobility === 'local' && $company_country === '') {
        $company_country = 'PHILIPPINES';
    }

    // Normalize optional fields to avoid saving placeholders like N/A
    if (in_array($middle_name, ['N/A', 'NA', 'NONE'])) {
        $middle_name = '';
    }
    if (in_array($alumni_id, ['N/A', 'NA', 'NONE'])) {
        $alumni_id = '';
    }

    // Handle profile photo upload (keep existing if none uploaded)
    $new_profile_photo = $personal['profile_photo'];
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_name = basename($_FILES['profile_photo']['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png'];
        
        if (in_array($ext, $allowed)) {
            // Delete old photo if exists
            if (!empty($personal['profile_photo']) && file_exists($personal['profile_photo'])) {
                @unlink($personal['profile_photo']);
            }
            $new_profile_photo = $upload_dir . uniqid('profile_') . '_' . $file_name;
            move_uploaded_file($file_tmp, $new_profile_photo);
        } else {
            echo "<div class='alert alert-danger'>Invalid file type. Please upload JPEG/PNG files only.</div>";
            exit;
        }
    }

    // Upsert into personal
    $exists = 0;
    $check = $conn->prepare("SELECT 1 FROM personal WHERE user_id = ? LIMIT 1");
    $check->bind_param('i', $user_id);
    $check->execute();
    $check->store_result();
    $exists = $check->num_rows > 0 ? 1 : 0;
    $check->close();

    if ($exists) {
        $stmt = $conn->prepare("UPDATE personal SET first_name = ?, middle_name = ?, last_name = ?, extension = ?, sex = ?, civil_status = ?, dob = ?, institutional_email = ?, personal_email = ?, phone_number = ?, street_address = ?, country = ?, province = ?, city = ?, barangay = ?, zip_code = ?, profile_photo = ? WHERE user_id = ?");
        $stmt->bind_param("sssssssssssssssssi", $first_name, $middle_name, $last_name, $extension, $sex, $civil_status, $dob, $institutional_email, $personal_email, $phone_number, $street_address, $country, $province, $city, $barangay, $zip_code, $new_profile_photo, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO personal (user_id, first_name, middle_name, last_name, extension, sex, civil_status, dob, institutional_email, personal_email, phone_number, street_address, country, province, city, barangay, zip_code, profile_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssssssssssss", $user_id, $first_name, $middle_name, $last_name, $extension, $sex, $civil_status, $dob, $institutional_email, $personal_email, $phone_number, $street_address, $country, $province, $city, $barangay, $zip_code, $new_profile_photo);
        $stmt->execute();
        $stmt->close();
    }

    // Upsert education
    $check = $conn->prepare("SELECT 1 FROM education WHERE user_id = ? LIMIT 1");
    $check->bind_param('i', $user_id);
    $check->execute();
    $check->store_result();
    $exists = $check->num_rows > 0 ? 1 : 0;
    $check->close();
    if ($exists) {
        $stmt = $conn->prepare("UPDATE education SET school_university = ?, campus_branch = ?, college_department = ?, program = ?, major_specialization = ?, alumni_id = ?, year_graduated = ? WHERE user_id = ?");
        $stmt->bind_param("sssssssi", $school_university, $campus_branch, $college_department, $program, $major_specialization, $alumni_id, $year_graduated, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO education (user_id, school_university, campus_branch, college_department, program, major_specialization, alumni_id, year_graduated) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $school_university, $campus_branch, $college_department, $program, $major_specialization, $alumni_id, $year_graduated);
        $stmt->execute();
        $stmt->close();
    }

    // Upsert employment
    $check = $conn->prepare("SELECT 1 FROM employment WHERE user_id = ? LIMIT 1");
    $check->bind_param('i', $user_id);
    $check->execute();
    $check->store_result();
    $exists = $check->num_rows > 0 ? 1 : 0;
    $check->close();
  if ($exists) {
    $stmt = $conn->prepare("UPDATE employment SET employment_status = ?, mobility = ?, job_status = ?, company_name = ?, job_title = ?, company_country = ?, company_province = ?, company_city = ?, industry = ?, it_category = ?, salary_per_month = ?, year_of_employment = ?, company_type = ?, work_arrangement = ? WHERE user_id = ?");
    $stmt->bind_param("ssssssssssssssi", $employment_status, $mobility, $job_status, $company_name, $job_title, $company_country, $company_province, $company_city, $industry, $it_category, $salary_per_month, $year_of_employment, $company_type, $work_arrangement, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
    $stmt = $conn->prepare("INSERT INTO employment (user_id, employment_status, mobility, job_status, company_name, job_title, company_country, company_province, company_city, industry, it_category, salary_per_month, year_of_employment, company_type, work_arrangement) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssssssssss", $user_id, $employment_status, $mobility, $job_status, $company_name, $job_title, $company_country, $company_province, $company_city, $industry, $it_category, $salary_per_month, $year_of_employment, $company_type, $work_arrangement);
        $stmt->execute();
        $stmt->close();
    }

    // Upsert company address
    $check = $conn->prepare("SELECT 1 FROM company_address WHERE user_id = ? LIMIT 1");
    $check->bind_param('i', $user_id);
    $check->execute();
    $check->store_result();
    $exists = $check->num_rows > 0 ? 1 : 0;
    $check->close();
    if ($exists) {
        $stmt = $conn->prepare("UPDATE company_address SET company_street_address = ?, company_province = ?, company_city = ?, company_barangay = ?, company_zip_code = ? WHERE user_id = ?");
        $stmt->bind_param("sssssi", $company_street_address, $company_province, $company_city, $company_barangay, $company_zip_code, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO company_address (user_id, company_street_address, company_province, company_city, company_barangay, company_zip_code) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $company_street_address, $company_province, $company_city, $company_barangay, $company_zip_code);
        $stmt->execute();
        $stmt->close();
    }

    // Mark user as onboarded
    $stmt = $conn->prepare("UPDATE users SET onboarded = 1 WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Profile saved successfully!'); window.location.href = 'index.php';</script>";
    exit();
}
            ?>


<link rel="stylesheet" href="UpdateAccount.css?v=<?php echo time(); ?>">

<div class="signup-container">
<h1 class="alumytics-account-heading">Create your Alumytics Account</h1>
<form class="signup-form" method="post" action="" enctype="multipart/form-data">
    
    <!-- Profile Photo Upload Section -->
    
    <div class="profile-photo-section">
    <div class="avatar-placeholder">
        <img id="profilePreview" src="<?php echo $personal['profile_photo'] ? htmlspecialchars($personal['profile_photo']) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDE1MCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiBmaWxsPSIjZjBmMGYwIi8+CjxjaXJjbGUgY3g9Ijc1IiBjeT0iNjAiIHI9IjIwIiBmaWxsPSIjY2NjIi8+CjxwYXRoIGQ9Ik00NSAxMjBjMC0xNi41NjkgMTMuNDMxLTMwIDMwLTMwczcwIDEzLjQzMSAzMCAzMCIgZmlsbD0iI2NjYyIvPgo8L3N2Zz4='; ?>" alt="Profile Photo" width="150" style="border-radius: 50%; object-fit: cover; cursor: pointer;" 
             onclick="<?php echo $personal['profile_photo'] ? "openImageModal('" . htmlspecialchars($personal['profile_photo']) . "', 'Profile Photo')" : ''; ?>" />
    </div>

    

    <?php if ($personal['profile_photo']): ?>
    <div class="file-actions" style="margin-top: 10px; text-align: center;">
        <button type="button" class="view-btn" onclick="openImageModal('<?php echo htmlspecialchars($personal['profile_photo']); ?>', 'Profile Photo')" style="margin-right: 5px; padding: 5px 10px; background: #000; color: white; border: none; border-radius: 3px; cursor: pointer;">
            <span class="icon">👁</span> View
        </button>
        <a href="<?php echo htmlspecialchars($personal['profile_photo']); ?>" download class="download-btn" style="margin-left: 5px; padding: 5px 10px; background: #000; color: white; text-decoration: none; border-radius: 3px;">
            <span class="icon">⬇</span> Download
        </a>
    </div>
    <?php endif; ?>

    <label for="profile_photo" class="upload-button" style="margin-top: 10px; display: inline-block;">
        <span class="upload-icon">&#8682;</span> Upload New Photo
    </label>

    <input type="file" id="profile_photo" name="profile_photo" accept="image/*" hidden>

    <br>
    <small class="upload-instruction">Recommended 400x400, Max 2MB.</small>
</div>

<script>
    const fileInput = document.getElementById('profile_photo');
    const previewImage = document.getElementById('profilePreview');

    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function (e) {
                previewImage.src = e.target.result;
            }
            reader.readAsDataURL(file);
        } else {
            previewImage.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDE1MCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiBmaWxsPSIjZjBmMGYwIi8+CjxjaXJjbGUgY3g9Ijc1IiBjeT0iNjAiIHI9IjIwIiBmaWxsPSIjY2NjIi8+CjxwYXRoIGQ9Ik00NSAxMjBjMC0xNi41NjkgMTMuNDMxLTMwIDMwLTMwczcwIDEzLjQzMSAzMCAzMCIgZmlsbD0iI2NjYyIvPgo8L3N2Zz4='; // fallback
        }
    });
</script>



  <!-- Basic Information Section -->
  <h3>Basic Information</h3>
  <label class="required-label">First Name</label>
  <input type="text" name="first_name" placeholder="First Name" required value="<?php echo htmlspecialchars(strtoupper($personal['first_name'])); ?>">

  <label class="required-label">Middle Name <span class="optional-note">(optional)</span></label>
  <input type="text" name="middle_name" placeholder="Middle Name (leave blank if none)" value="<?php echo htmlspecialchars(strtoupper($personal['middle_name'])); ?>">

  <label class="required-label">Last Name</label>
  <input type="text" name="last_name" placeholder="Last Name" required value="<?php echo htmlspecialchars(strtoupper($personal['last_name'])); ?>">

  <label class="required-label">Extension</label>
  <input type="text" name="extension" placeholder="Extension . if not applicable, enter N/A'" required value="<?php echo htmlspecialchars(strtoupper($personal['extension'])); ?>">

    <div class="sex-dob-row">
        <div class="sex-selection">
            <label>Sex:</label>
            <label><input type="radio" name="sex" value="male" required <?php echo $personal['sex']==='male' ? 'checked' : ''; ?>> Male</label>
            <label><input type="radio" name="sex" value="female" required <?php echo $personal['sex']==='female' ? 'checked' : ''; ?>> Female</label>
        </div>
        <div class="dob-field">
            <label for="dob">Date of Birth:</label>
            <input type="date" id="dob" name="dob" required value="<?php echo htmlspecialchars($personal['dob']); ?>">
        </div>
    </div>

    <div class="form-group">
      <label for="civil_status">Civil Status</label>
      <select id="civil_status" name="civil_status" data-selected="<?php echo htmlspecialchars($personal['civil_status'] ?? ''); ?>">
        <option value="" disabled selected>Select Civil Status</option>
        <option value="Single" <?php echo ($personal['civil_status']==='Single') ? 'selected' : ''; ?>>Single</option>
        <option value="Married" <?php echo ($personal['civil_status']==='Married') ? 'selected' : ''; ?>>Married</option>
        <option value="Separated" <?php echo ($personal['civil_status']==='Separated') ? 'selected' : ''; ?>>Separated</option>
        <option value="Widowed" <?php echo ($personal['civil_status']==='Widowed') ? 'selected' : ''; ?>>Widowed</option>
        <option value="Other" <?php echo ($personal['civil_status']==='Other') ? 'selected' : ''; ?>>Other</option>
      </select>
    </div>

  <!-- Contact Information Section -->
  <h3>Contact Information</h3>
  <label class="required-label">Institutional Email</label>
  <input type="email" name="institutional_email" placeholder="Institutional Email" required value="<?php echo htmlspecialchars($personal['institutional_email']); ?>">

  <label class="required-label">Personal Email</label>
  <input type="email" name="personal_email" placeholder="Personal Email" required value="<?php echo htmlspecialchars($personal['personal_email']); ?>">

  <label class="required-label">Phone Number</label>
  <input type="tel" name="phone_number" placeholder="Phone Number" required value="<?php echo htmlspecialchars($personal['phone_number']); ?>">

  <label class="required-label">Street Address</label>
  <input type="text" name="street_address" placeholder="Street Address" required value="<?php echo htmlspecialchars(strtoupper($personal['street_address'])); ?>">

    <label class="required-label">Country</label>
    <select id="country" name="country" data-selected="<?php echo htmlspecialchars($personal['country'] ?? ''); ?>">
      <option value="" disabled selected>Select Country</option>
    </select>

    <label class="required-label" for="province">State/Province</label>
    <select id="province" name="province" required data-selected="<?php echo htmlspecialchars($personal['province']); ?>">
        <option value="" disabled selected>Select Province</option>
    </select>

    <label class="required-label" for="city">City/Municipality</label>
    <select id="city" name="city" required data-selected="<?php echo htmlspecialchars($personal['city']); ?>">
        <option value="" disabled selected>Select City/Municipality</option>
    </select>

    <label class="required-label" for="barangay">Barangay</label>
    <select id="barangay" name="barangay" required data-selected="<?php echo htmlspecialchars($personal['barangay']); ?>">
        <option value="" disabled selected>Select Barangay</option>
    </select>

    <label class="required-label" for="zip_code">Zip Code</label>
    <input type="text" id="zip_code" name="zip_code" placeholder="Zip Code" required value="<?php echo htmlspecialchars($personal['zip_code']); ?>">


  <!-- Educational Information Section -->
  <h3>Educational Information</h3>
    <div class="input-row">
  <label class="required-label">School/University</label>
  <select id="school_university" name="school_university" required>
      <option value="" disabled selected>School/University</option>
      <?php if ($universitiesRes): ?>
        <?php while ($row = $universitiesRes->fetch_assoc()): $name = $row['name']; $uid = (int)$row['id']; if (!$name) continue; $isSelected = (strcasecmp($education['school_university'], $name) === 0); ?>
          <option value="<?php echo htmlspecialchars($name); ?>" data-university-id="<?php echo $uid; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($name); ?>
          </option>
        <?php endwhile; ?>
      <?php endif; ?>
  </select>

  <label class="required-label">Campus/Branch</label>
  <select id="campus_branch" name="campus_branch" required>
      <option value="" disabled selected>Campus/Branch</option>
      <?php if ($campusesRes): ?>
        <?php while ($row = $campusesRes->fetch_assoc()): $name = $row['name']; $cid = (int)$row['id']; $cUid = (int)$row['university_id']; if (!$name) continue; $isSelected = (strcasecmp($education['campus_branch'], $name) === 0); ?>
          <option value="<?php echo htmlspecialchars($name); ?>" data-campus-id="<?php echo $cid; ?>" data-university-id="<?php echo $cUid; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($name); ?>
          </option>
        <?php endwhile; ?>
      <?php endif; ?>
  </select>

  <label class="required-label">College/Department</label>
  <select id="college_department" name="college_department" required>
      <option value="" disabled selected>College/Department</option>
      <?php if ($departmentsRes): ?>
        <?php while ($row = $departmentsRes->fetch_assoc()): $name = $row['name']; $dUid = (int)$row['university_id']; $dCid = (int)$row['campus_id']; $dId = (int)$row['id']; if (!$name) continue; $isSelected = (strcasecmp($education['college_department'], $name) === 0); ?>
          <option value="<?php echo htmlspecialchars($name); ?>" data-department-id="<?php echo $dId; ?>" data-university-id="<?php echo $dUid; ?>" data-campus-id="<?php echo $dCid; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($name); ?>
          </option>
        <?php endwhile; ?>
      <?php endif; ?>
  </select>

  <label class="required-label">Program</label>
  <select id="program" name="program" required>
      <option value="" disabled selected>Program</option>
      <?php if ($programsRes): ?>
        <?php while ($row = $programsRes->fetch_assoc()): $name = $row['name']; $pUid = (int)$row['university_id']; $pCid = (int)$row['campus_id']; $pDid = (int)$row['department_id']; $pId = (int)$row['id']; if (!$name) continue; $isSelected = (strcasecmp($education['program'], $name) === 0); ?>
          <option value="<?php echo htmlspecialchars($name); ?>" data-program-id="<?php echo $pId; ?>" data-department-id="<?php echo $pDid; ?>" data-campus-id="<?php echo $pCid; ?>" data-university-id="<?php echo $pUid; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($name); ?>
          </option>
        <?php endwhile; ?>
      <?php endif; ?>
  </select>

  <div class="form-field">
    <label class="required-label">Major/Specialization</label>
    <input
        type="text"
        id="major_specialization"
        name="major_specialization"
        placeholder="Major/Specialization"
        required
        value="<?php echo htmlspecialchars($education['major_specialization']); ?>"
    >
  </div>

  <div class="form-field">
    <label class="required-label">Alumni ID <span class="optional-note">(optional)</span></label>
    <input type="text" name="alumni_id" placeholder="Alumni ID" value="<?php echo htmlspecialchars(strtoupper($education['alumni_id'])); ?>">
  </div>
    </div>

    <div class="education-year-row">
        <label class="required-label">Year Graduated</label>
        <select name="year_graduated" required data-selected="<?php echo htmlspecialchars($education['year_graduated']); ?>">
            <option value="" disabled selected>Year Graduated</option>
            <?php
            $currentYear = date("Y");
            for ($year = $currentYear; $year >= 1950; $year--) {
                $sel = ((string)$education['year_graduated'] === (string)$year) ? ' selected' : '';
                echo "<option value=\"$year\"$sel>$year</option>";
            }
            ?>
        </select>
    </div>

  <!-- Employment Information Section -->
  <h3>Employment Information</h3>
    <div class="employment-info-row">
    <div class="employment-status">
      <label class="required-label" for="employment_status">Employment Status</label>
      <select id="employment_status" name="employment_status" required data-selected="<?php echo htmlspecialchars($employment['employment_status']); ?>">
        <option value="">Select Status</option>
        <option value="employed">Employed</option>
        <option value="unemployed">Unemployed</option>
      </select>
    </div>
        <br>
    <div class="mobility-status">
      <label class="required-label" for="mobility">Mobility</label>
      <select id="mobility" name="mobility" required data-selected="<?php echo htmlspecialchars($employment['mobility']); ?>">
        <option value="">Select Mobility</option>
        <option value="international">International</option>
        <option value="local">Local</option>
      </select>
    </div>
    
    <div class="job-status">
      <label class="required-label" for="job_status">Job Status</label>
      <select id="job_status" name="job_status" data-selected="<?php echo htmlspecialchars($employment['job_status'] ?? ''); ?>">
        <option value="" disabled selected>Select Job Status</option>
        <option value="Permanent" <?php echo ($employment['job_status']==='Permanent') ? 'selected' : ''; ?>>Permanent</option>
        <option value="Temporary" <?php echo ($employment['job_status']==='Temporary') ? 'selected' : ''; ?>>Temporary</option>
        <option value="Contractual" <?php echo ($employment['job_status']==='Contractual') ? 'selected' : ''; ?>>Contractual</option>
        <option value="Job Order/Casual" <?php echo ($employment['job_status']==='Job Order/Casual') ? 'selected' : ''; ?>>Job Order/Casual</option>
        <option value="Self Employed" <?php echo ($employment['job_status']==='Self Employed') ? 'selected' : ''; ?>>Self Employed</option>
      </select>
    </div>
    
    <div class="work-arrangement-status">
      <label for="work_arrangement">Work Arrangement</label>
      <select id="work_arrangement" name="work_arrangement" data-selected="<?php echo htmlspecialchars($employment['work_arrangement']); ?>">
        <option value="">Select Work Arrangement</option>
        <option value="On-site" <?php echo ($employment['work_arrangement']==='On-site') ? 'selected' : ''; ?>>On-site</option>
        <option value="Remote" <?php echo ($employment['work_arrangement']==='Remote') ? 'selected' : ''; ?>>Remote</option>
        <option value="Hybrid" <?php echo ($employment['work_arrangement']==='Hybrid') ? 'selected' : ''; ?>>Hybrid</option>
      </select>
    </div>
    </div>

    <!-- Company Details Section -->
    <div class="company-section">
    <h3>Company Details</h3>

  <div class="company-details-row">
    <div class="form-field">
      <label class="required-label">Company Name</label>
      <input type="text" name="company_name" placeholder="Company Name" required value="<?php echo htmlspecialchars(strtoupper($employment['company_name'])); ?>">
    </div>

    <div class="form-field">
      <label class="required-label">Job Title</label>
      <input type="text" name="job_title" placeholder="Job Title" required value="<?php echo htmlspecialchars(strtoupper($employment['job_title'])); ?>">
    </div>

    <div class="form-field">
      <label class="required-label">Industry</label>
      <!-- Replace text input with select for Industry -->
      <select name="industry" id="employment_industry" required data-selected="<?php echo htmlspecialchars($employment['industry']); ?>">
        <option value="" disabled selected>Select Industry</option>
      </select>
    </div>

    <div class="form-field">
      <label class="required-label">IT / Non-IT Classification</label>
      <select name="it_category" id="it_category" required>
        <option value="" disabled <?php echo $employment['it_category'] === '' ? 'selected' : ''; ?>>Select Classification</option>
        <option value="IT" <?php echo $employment['it_category']==='IT' ? 'selected' : ''; ?>>IT-related</option>
        <option value="NON_IT" <?php echo $employment['it_category']==='NON_IT' ? 'selected' : ''; ?>>Non-IT-related</option>
      </select>
    </div>

    <div class="form-field">
      <label for="salary_per_month">Salary <span class="optional-note">(optional)</span></label>
      <input type="text" id="salary_per_month" name="salary_per_month" placeholder="Salary per Month (Optional)" value="<?php echo htmlspecialchars($employment['salary_per_month']); ?>">
    </div>
  </div>

    <div class="company-type-row">
      <label>Year of Employment</label>
    <select name="year_of_employment" required data-selected="<?php echo htmlspecialchars($employment['year_of_employment']); ?>">
            <option value="" disabled selected>Year of Employment</option>
            <?php
            $currentYear = date("Y");
            for ($year = $currentYear; $year >= 1950; $year--) {
                $sel = ((string)$employment['year_of_employment'] === (string)$year) ? ' selected' : '';
                echo "<option value=\"$year\"$sel>$year</option>";
            }
            ?>
        </select>

        <div class="company-type-options">
            <label>Type of Company:</label>
            <label><input type="radio" name="company_type" value="private" <?php echo $employment['company_type']==='private' ? 'checked' : ''; ?>> Private</label>
            <label><input type="radio" name="company_type" value="public" <?php echo $employment['company_type']==='public' ? 'checked' : ''; ?>> Public</label>
            <label><input type="radio" name="company_type" value="ngo_ingo" <?php echo $employment['company_type']==='ngo_ingo' ? 'checked' : ''; ?>> NGO/INGO</label>
            <label><input type="radio" name="company_type" value="self_employed" <?php echo $employment['company_type']==='self_employed' ? 'checked' : ''; ?>> Self Employed</label>
            <label><input type="radio" name="company_type" value="government" <?php echo $employment['company_type']==='government' ? 'checked' : ''; ?>> Government</label>
        </div>
    </div>
</div>

    <!-- Company Address Section -->
  <div class="company-address-section">
    <hr>
    <h3>Company Address</h3>
    <label class="required-label">Street Address</label>
    <input type="text" name="company_street_address" placeholder="Street Address" value="<?php echo htmlspecialchars(strtoupper($company_address['company_street_address'])); ?>">
    <label class="required-label">Country</label>
    <select id="company_country" name="company_country" data-selected="<?php echo htmlspecialchars($employment['company_country'] ?? ''); ?>">
      <option value="" disabled selected>Select Country</option>
    </select>
    <div id="company-local-fields" class="company-address-row">
      <label class="required-label">Province</label>
      <select id="company_province" name="company_province" data-selected="<?php echo htmlspecialchars($company_address['company_province']); ?>">
        <option value="" disabled selected>Select Province</option>
      </select>

      <label class="required-label">City/Municipality</label>
      <select id="company_city" name="company_city" data-selected="<?php echo htmlspecialchars($company_address['company_city']); ?>">
        <option value="" disabled selected>Select City/Municipality</option>
      </select>

      <label class="required-label">Barangay</label>
      <select id="company_barangay" name="company_barangay" data-selected="<?php echo htmlspecialchars($company_address['company_barangay']); ?>">
        <option value="" disabled selected>Select Barangay</option>
      </select>
    </div>

    <div id="company-international-fields" class="company-address-row" style="display:none; margin-top:10px;">
      <label for="company_province_intl">State/Province/Region</label>
      <input type="text" id="company_province_intl" name="company_province_intl" placeholder="Enter state/province/region" value="<?php echo htmlspecialchars($employment['company_province'] ?? ''); ?>">

      <label for="company_city_intl">City</label>
      <input type="text" id="company_city_intl" name="company_city_intl" placeholder="Enter city" value="<?php echo htmlspecialchars($employment['company_city'] ?? ''); ?>">
    </div>

    <label class="required-label">Zip Code</label>
    <input type="text" name="company_zip_code" placeholder="Zip Code" value="<?php echo htmlspecialchars($company_address['company_zip_code']); ?>">
  </div>

    <button type="submit" class="save-button">Save Changes</button>
</form>
</div>

    <script src="js/address-dropdown.js"></script>
    <script src="js/updateAccount.js"></script>
<script src="js/academic-dropdowns.js?v=<?php echo time(); ?>"></script>
<script src="js/profile-photo-preview.js?v=<?php echo time(); ?>"></script>
<script>
// Populate industries for employment and certification selects
document.addEventListener('DOMContentLoaded', function() {
  const industries = [
    'Agriculture', 'Automotive', 'Aviation', 'Banking', 'Biotechnology', 'Business Services',
    'Construction', 'Consumer Goods', 'Customer Support', 'Design', 'Education', 'Energy',
    'Entertainment', 'Environmental', 'Finance', 'Food & Beverage', 'Gaming', 'Government',
    'Healthcare', 'Hospitality', 'Human Resources', 'Insurance', 'Legal', 'Logistics',
    'Manufacturing', 'Marketing', 'Media', 'Mining', 'Non-Profit', 'Oil & Gas', 'Pharmaceutical',
    'Real Estate', 'Research', 'Retail', 'Sales', 'Software', 'Sports', 'Telecommunications',
    'Tourism', 'Transportation', 'Utilities', 'Other'
  ];

  const employmentIndustry = document.getElementById('employment_industry');
  if (employmentIndustry) {
    industries.forEach(function(ind) {
      const opt = document.createElement('option');
      opt.value = ind;
      opt.text = ind;
      employmentIndustry.appendChild(opt);
    });
    const selected = employmentIndustry.getAttribute('data-selected');
    if (selected) employmentIndustry.value = selected;
  }

  // For dynamically adding certification selects, we can remove industry population
  // since we're now using predefined categories instead of dynamic industry lists
  
  document.querySelectorAll('select.cert-category').forEach(function(sel){
    // Categories are already defined in HTML, no population needed
  });

  // Hook add-certifications button to also populate new selects
  const addCertBtn = document.querySelector('.add-cert-button');
  const certContainer = document.getElementById('new-certifications');
  if (addCertBtn && certContainer) {
    let certCounter = 1;
    addCertBtn.addEventListener('click', function() {
      const wrapper = document.createElement('div');
      wrapper.className = 'certification-entry';
      wrapper.innerHTML = `
        <label>Certification Name</label>
        <input type="text" name="cert_name[]" placeholder="Certification Name">
        <label>Category</label>
        <select name="cert_category[]" class="cert-category">
          <option value="" disabled selected>Select Category</option>
          <option value="Professional">Professional</option>
          <option value="Technical">Technical</option>
          <option value="Academic">Academic</option>
          <option value="Industry-Specific">Industry-Specific</option>
          <option value="Safety">Safety</option>
          <option value="Other">Other</option>
        </select>
        <label>Issuing Organization</label>
        <input type="text" name="cert_issuer[]" placeholder="Issuing Organization">
        <label>Issue Date</label>
        <input type="date" name="cert_date[]" placeholder="Issue Date">
        <div class="upload-section">
          <label>Upload Certification</label>
          <input type="file" id="cert-file-${certCounter}" name="cert_file[]" accept=".pdf,image/*" onchange="previewFile(this, 'cert-preview-${certCounter}')">
          <div id="cert-preview-${certCounter}" class="file-preview-container" style="display: none; margin-top: 10px;"></div>
        </div>
      `;
      certContainer.appendChild(wrapper);
      
      // Apply uppercase conversion to newly added text inputs
      const newTextInputs = wrapper.querySelectorAll('input[type="text"]');
      newTextInputs.forEach(function(input) {
        input.addEventListener('input', function(e) {
          const cursorPosition = e.target.selectionStart;
          e.target.value = e.target.value.toUpperCase();
          e.target.setSelectionRange(cursorPosition, cursorPosition);
        });
        input.addEventListener('blur', function(e) {
          e.target.value = e.target.value.toUpperCase();
        });
      });
      
      certCounter++;
    });

  // Toggle company address fields based on Mobility (local vs international)
  (function(){
    var mobEl = document.getElementById('mobility');
    var localFields = document.getElementById('company-local-fields');
    var intlFields = document.getElementById('company-international-fields');
    var provIntl = document.getElementById('company_province_intl');
    var cityIntl = document.getElementById('company_city_intl');
    var provLocal = document.getElementById('company_province');
    var cityLocal = document.getElementById('company_city');
    var brgyLocal = document.getElementById('company_barangay');

    function toggleCompanyAddressByMobility() {
      if (!mobEl || !localFields || !intlFields) return;
      var m = (mobEl.value || '').toLowerCase();
      if (m === 'international') {
        localFields.style.display = 'none';
        intlFields.style.display = 'flex';
        if (provLocal) provLocal.required = false;
        if (cityLocal) cityLocal.required = false;
        if (brgyLocal) brgyLocal.required = false;
        if (provIntl) provIntl.required = false;
        if (cityIntl) cityIntl.required = false;
      } else {
        localFields.style.display = 'flex';
        intlFields.style.display = 'none';
        if (provLocal) provLocal.required = true;
        if (cityLocal) cityLocal.required = true;
        if (brgyLocal) brgyLocal.required = true;
        if (provIntl) provIntl.required = false;
        if (cityIntl) cityIntl.required = false;
      }
    }

    if (mobEl) {
      mobEl.addEventListener('change', toggleCompanyAddressByMobility);
      // Apply on load using stored value
      toggleCompanyAddressByMobility();
    }
  })();
  }

});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Auto-convert all text inputs to UPPERCASE after typing
  // Exclude email, password, date, tel, and file inputs
  const textInputs = document.querySelectorAll('input[type="text"], textarea');
  textInputs.forEach(function(input) {
    // Skip email inputs (they should stay lowercase)
    if (input.type === 'email' || input.name && input.name.includes('email')) {
      return;
    }
    
    // Convert to uppercase on input
    input.addEventListener('input', function(e) {
      const cursorPosition = e.target.selectionStart;
      e.target.value = e.target.value.toUpperCase();
      // Restore cursor position
      e.target.setSelectionRange(cursorPosition, cursorPosition);
    });
    
    // Also convert on blur (when user leaves the field)
    input.addEventListener('blur', function(e) {
      e.target.value = e.target.value.toUpperCase();
    });
  });
  
  // Apply selected values for dropdowns populated by external scripts
  setTimeout(function() {
    var selectedEmploymentStatus = document.getElementById('employment_status').getAttribute('data-selected');
    if (selectedEmploymentStatus) {
      document.getElementById('employment_status').value = selectedEmploymentStatus;
    }
    var selectedMobility = document.getElementById('mobility').getAttribute('data-selected');
    if (selectedMobility) {
      document.getElementById('mobility').value = selectedMobility;
    }
    var industryEl = document.getElementById('employment_industry');
    if (industryEl && industryEl.getAttribute('data-selected')) {
      industryEl.value = industryEl.getAttribute('data-selected');
    }

    // Set company country if present
    var companyCountryEl = document.getElementById('company_country');
    if (companyCountryEl && companyCountryEl.getAttribute('data-selected')) {
      companyCountryEl.value = companyCountryEl.getAttribute('data-selected');
      // Trigger change so address-dropdown can react (e.g., disable/enable province)
      companyCountryEl.dispatchEvent(new Event('change'));
    }
    var yearSelect = document.querySelector('select[name="year_of_employment"]');
    if (yearSelect && yearSelect.getAttribute('data-selected')) {
      yearSelect.value = yearSelect.getAttribute('data-selected');
    }
  }, 400);

  // Toggle visibility/requirements of company-related fields based on employment status
  function updateCompanyFieldsVisibility() {
    var employmentStatusEl = document.getElementById('employment_status');
    if (!employmentStatusEl) return;

    var status = employmentStatusEl.value;
    var companySection = document.querySelector('.company-section');
    var companyAddressSection = document.querySelector('.company-address-section');
    var mobilitySection = document.querySelector('.mobility-status');
    var jobStatusSection = document.querySelector('.job-status');
    var workArrangementSection = document.querySelector('.work-arrangement-status');

    // All inputs/selects that belong to employer/company info
    var companyFields = document.querySelectorAll('[name="company_name"], [name="job_title"], [name="industry"], [name="it_category"], [name="salary_per_month"], [name="year_of_employment"], [name="company_type"], [name="company_street_address"], [name="company_country"], [name="company_province"], [name="company_city"], [name="company_barangay"], [name="company_zip_code"], [name="work_arrangement"], [name="job_status"], [name="mobility"]');

    if (status === 'unemployed') {
      if (companySection) companySection.style.display = 'none';
      if (companyAddressSection) companyAddressSection.style.display = 'none';
      if (mobilitySection) mobilitySection.style.display = 'none';
      if (jobStatusSection) jobStatusSection.style.display = 'none';
      if (workArrangementSection) workArrangementSection.style.display = 'none';

      companyFields.forEach(function(field) {
        field.dataset.originalRequired = field.required ? '1' : (field.dataset.originalRequired || '0');
        field.required = false;
      });
    } else {
      if (companySection) companySection.style.display = '';
      if (companyAddressSection) companyAddressSection.style.display = '';
      if (mobilitySection) mobilitySection.style.display = '';
      if (jobStatusSection) jobStatusSection.style.display = '';
      if (workArrangementSection) workArrangementSection.style.display = '';

      companyFields.forEach(function(field) {
        if (field.dataset.originalRequired === '1' || field.hasAttribute('required')) {
          field.required = true;
        }
      });
    }
  }

  var employmentStatusElInit = document.getElementById('employment_status');
  if (employmentStatusElInit) {
    employmentStatusElInit.addEventListener('change', updateCompanyFieldsVisibility);
    // Apply on initial load (after dropdown values are set)
    setTimeout(updateCompanyFieldsVisibility, 500);
  }

  // Set address dropdowns after options are loaded
  setTimeout(function() {
    ['province','city','barangay'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el && el.getAttribute('data-selected')) {
        el.value = el.getAttribute('data-selected');
      }
    });
    ['company_province','company_city','company_barangay'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el && el.getAttribute('data-selected')) {
        el.value = el.getAttribute('data-selected');
      }
    });
  }, 1000);
});

// Image Modal Functions
function openImageModal(imageSrc, title) {
  const modal = document.getElementById('imageModal');
  const modalImage = document.getElementById('modalImage');
  const modalTitle = document.getElementById('imageModalTitle');
  const downloadLink = document.getElementById('downloadImageLink');
  
  modalImage.src = imageSrc;
  modalTitle.textContent = title;
  downloadLink.href = imageSrc;
  modal.style.display = 'block';
}

function closeImageModal() {
  document.getElementById('imageModal').style.display = 'none';
}

// File Preview Function
function previewFile(input, previewId) {
  const file = input.files[0];
  const preview = document.getElementById(previewId);
  
  if (!file) {
    preview.style.display = 'none';
    return;
  }
  
  const ext = file.name.split('.').pop().toLowerCase();
  const isImage = ['jpg', 'jpeg', 'png'].includes(ext);
  const isPDF = ext === 'pdf';
  
  if (isImage || isPDF) {
    let previewHTML = '';
    
    if (isImage) {
      const reader = new FileReader();
      reader.onload = function(e) {
        previewHTML = `
          <div class="file-preview-item">
            <div class="preview-image-container">
              <img src="${e.target.result}" alt="Preview" class="preview-image" onclick="openImageModal('${e.target.result}', '${file.name}')" />
              <div class="preview-overlay">
                <button type="button" class="preview-view-btn" onclick="openImageModal('${e.target.result}', '${file.name}')">
                  <i class="fas fa-eye"></i> View
                </button>
              </div>
            </div>
            <p class="file-name">${file.name}</p>
            <button type="button" class="remove-preview-btn" onclick="removeFilePreview('${previewId}', '${input.id}')">
              <i class="fas fa-times"></i> Remove
            </button>
          </div>
        `;
        preview.innerHTML = previewHTML;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    } else if (isPDF) {
      previewHTML = `
        <div class="file-preview-item">
          <div class="pdf-preview-container">
            <div class="pdf-icon">
              <i class="fas fa-file-pdf"></i>
            </div>
            <p class="file-name">${file.name}</p>
            <button type="button" class="remove-preview-btn" onclick="removeFilePreview('${previewId}', '${input.id}')">
              <i class="fas fa-times"></i> Remove
            </button>
          </div>
        </div>
      `;
      preview.innerHTML = previewHTML;
      preview.style.display = 'block';
    }
  } else {
    alert('Please upload only PDF, JPG, JPEG, or PNG files.');
    input.value = '';
    preview.style.display = 'none';
  }
}

// Remove file preview
function removeFilePreview(previewId, inputId) {
  const preview = document.getElementById(previewId);
  const input = document.getElementById(inputId);
  
  preview.style.display = 'none';
  preview.innerHTML = '';
  input.value = '';
}

// Close image modal when clicking outside
window.onclick = function(event) {
  const imageModal = document.getElementById('imageModal');
  if (event.target === imageModal) {
    imageModal.style.display = 'none';
  }
};
</script>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9);">
  <div class="image-modal-content" style="margin: auto; display: block; width: 80%; max-width: 700px; margin-top: 50px;">
    <span class="image-close-btn" onclick="closeImageModal()" style="position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
    <h3 id="imageModalTitle" style="color: white; text-align: center; margin-bottom: 20px;"></h3>
    <img id="modalImage" style="width: 100%; height: auto; border-radius: 10px;">
    <div style="text-align: center; margin-top: 20px;">
      <a id="downloadImageLink" download class="download-btn" style="padding: 10px 20px; background: #000; color: white; text-decoration: none; border-radius: 5px;">
        Download Image
      </a>
    </div>
  </div>
</div>

