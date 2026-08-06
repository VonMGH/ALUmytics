<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'database.php';
session_start();

// Only allow access if logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

// Handle AJAX save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $conn = Database::getInstance()->getConnection();
    $user_id = $_SESSION['user_id'];
    $errors = [];
    // Collect and sanitize all fields
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $extension = trim($_POST['extension'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $institutional_email = trim($_POST['institutional_email'] ?? '');
    $personal_email = trim($_POST['personal_email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $facebook = trim($_POST['facebook'] ?? '');
    $street_address = trim($_POST['street_address'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $school_university = trim($_POST['school_university'] ?? '');
    $campus_branch = trim($_POST['campus_branch'] ?? '');
    $college_department = trim($_POST['college_department'] ?? '');
    $program = trim($_POST['program'] ?? '');
    $major_specialization = trim($_POST['major_specialization'] ?? '');
    $alumni_id = trim($_POST['alumni_id'] ?? '');
    $employment_status = trim($_POST['employment_status'] ?? '');
    $mobility = trim($_POST['mobility'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $company_type = trim($_POST['company_type'] ?? '');
    $year_of_employment = trim($_POST['year_of_employment'] ?? '');
    // Company Address removed from this wizard
    // Handle profile photo upload
    $profile_photo = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_name = basename($_FILES['profile_photo']['name']);
        $file_type = mime_content_type($file_tmp);
        $file_size = $_FILES['profile_photo']['size'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        if (in_array($file_type, $allowed_types) && $file_size <= 2 * 1024 * 1024) {
            $profile_photo = $upload_dir . uniqid('profile_') . '_' . $file_name;
            move_uploaded_file($file_tmp, $profile_photo);
        } else {
            $errors[] = 'Invalid profile photo type or size. Upload JPEG/PNG under 2MB.';
        }
    }
    // Validate required fields (add more as needed)
    if (!$first_name || !$last_name || !$dob || !$sex) $errors[] = 'Personal info required.';
    if (!$institutional_email || !$personal_email || !$phone_number || !$province || !$city || !$barangay) $errors[] = 'Contact info required.';
    if (!$school_university || !$program || !$alumni_id) $errors[] = 'Educational info required.';
    if (!$employment_status || !$mobility) $errors[] = 'Employment info required.';
    // Uniqueness check (alumni_id)
    $stmt = $conn->prepare("SELECT user_id FROM education WHERE alumni_id = ? AND user_id != ?");
    $stmt->bind_param('si', $alumni_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors[] = 'Alumni ID already exists.';
    $stmt->close();
    // Save to personal
    if (empty($errors)) {
        if ($profile_photo) {
            $stmt = $conn->prepare("UPDATE personal SET first_name=?, middle_name=?, last_name=?, extension=?, dob=?, sex=?, institutional_email=?, personal_email=?, phone_number=?, facebook=?, street_address=?, province=?, city=?, barangay=?, zip_code=?, profile_photo=? WHERE user_id=?");
            // 16 string params + 1 int param
            $stmt->bind_param('ssssssssssssssssi', $first_name, $middle_name, $last_name, $extension, $dob, $sex, $institutional_email, $personal_email, $phone_number, $facebook, $street_address, $province, $city, $barangay, $zip_code, $profile_photo, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE personal SET first_name=?, middle_name=?, last_name=?, extension=?, dob=?, sex=?, institutional_email=?, personal_email=?, phone_number=?, facebook=?, street_address=?, province=?, city=?, barangay=?, zip_code=? WHERE user_id=?");
            $stmt->bind_param('sssssssssssssssi', $first_name, $middle_name, $last_name, $extension, $dob, $sex, $institutional_email, $personal_email, $phone_number, $facebook, $street_address, $province, $city, $barangay, $zip_code, $user_id);
        }
        $stmt->execute();
        $stmt->close();
        // Save to education
        $stmt = $conn->prepare("UPDATE education SET school_university=?, campus_branch=?, college_department=?, program=?, major_specialization=?, alumni_id=? WHERE user_id=?");
        $stmt->bind_param('ssssssi', $school_university, $campus_branch, $college_department, $program, $major_specialization, $alumni_id, $user_id);
        $stmt->execute();
        $stmt->close();
        // Save to employment
        $stmt = $conn->prepare("UPDATE employment SET employment_status=?, mobility=?, company_name=?, industry=?, year_of_employment=?, company_type=? WHERE user_id=?");
        $stmt->bind_param('ssssssi', $employment_status, $mobility, $company_name, $industry, $year_of_employment, $company_type, $user_id);
        $stmt->execute();
        $stmt->close();
        // Company address is handled elsewhere; not updated here
        // Handle Certifications (optional, insert new rows)
        if (!empty($_POST['cert_name']) && is_array($_POST['cert_name'])) {
            $certNames = $_POST['cert_name'];
            $certIndustries = $_POST['cert_industry'] ?? [];
            $certDates = $_POST['cert_date'] ?? [];
            foreach ($certNames as $index => $name) {
                if (!$name) continue;
                $industryVal = $certIndustries[$index] ?? '';
                $dateVal = $certDates[$index] ?? '';
                $certTargetDir = $_SERVER['DOCUMENT_ROOT'] . "/alumytics/alumni/uploads/certification/";
                if (!is_dir($certTargetDir)) { @mkdir($certTargetDir, 0755, true); }
                $certTmpPath = $_FILES['cert_file']['tmp_name'][$index] ?? null;
                $certOriginalName = isset($_FILES['cert_file']['name'][$index]) ? basename($_FILES['cert_file']['name'][$index]) : '';
                $certUniqueName = uniqid() . "_" . $certOriginalName;
                $certFilePath = $certOriginalName ? ($certTargetDir . $certUniqueName) : null;
                if ($certTmpPath && $certOriginalName) { @move_uploaded_file($certTmpPath, $certFilePath); }
                $stmt = $conn->prepare("INSERT INTO certifications (user_id, certification_name, industry, certification_date, certification_file) VALUES (?, ?, ?, ?, ?)");
                $dbPath = $certFilePath ?: null;
                $stmt->bind_param('issss', $user_id, $name, $industryVal, $dateVal, $dbPath);
                $stmt->execute();
                $stmt->close();
            }
        }
        // Handle Awards (optional, insert new rows)
        if (!empty($_POST['award_name']) && is_array($_POST['award_name'])) {
            $awardNames = $_POST['award_name'];
            $awardCategories = $_POST['award_category'] ?? [];
            $awardDates = $_POST['award_date'] ?? [];
            foreach ($awardNames as $index => $name) {
                if (!$name) continue;
                $categoryVal = $awardCategories[$index] ?? '';
                $dateVal = $awardDates[$index] ?? '';
                $awardTargetDir = $_SERVER['DOCUMENT_ROOT'] . "/alumytics/alumni/uploads/awards/";
                if (!is_dir($awardTargetDir)) { @mkdir($awardTargetDir, 0755, true); }
                $awardTmpPath = $_FILES['award_file']['tmp_name'][$index] ?? null;
                $awardOriginalName = isset($_FILES['award_file']['name'][$index]) ? basename($_FILES['award_file']['name'][$index]) : '';
                $awardUniqueName = uniqid() . "_" . $awardOriginalName;
                $awardFilePath = $awardOriginalName ? ($awardTargetDir . $awardUniqueName) : null;
                if ($awardTmpPath && $awardOriginalName) { @move_uploaded_file($awardTmpPath, $awardFilePath); }
                $stmt = $conn->prepare("INSERT INTO awards (user_id, award_name, category, award_date, award_file, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $dbPath = $awardFilePath ?: null;
                $stmt->bind_param('issss', $user_id, $name, $categoryVal, $dateVal, $dbPath);
                $stmt->execute();
                $stmt->close();
            }
        }
        // Mark user as onboarded
        $stmt = $conn->prepare("UPDATE users SET onboarded=1 WHERE user_id=?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        if ($isAjax) {
            echo json_encode(['success' => true, 'redirect' => 'index.php']);
            exit;
        } else {
            echo "<script>alert('Profile updated!'); window.location.href = 'index.php';</script>";
            exit;
        }
    } else {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => implode('<br>', $errors)]);
            exit;
        } else {
            echo "<script>alert('" . implode('\\n', $errors) . "');</script>";
        }
    }
}

// Fetch personal info for prefill
$personal = [
  'first_name' => '',
  'middle_name' => '',
  'last_name' => '',
  'extension' => '',
  'dob' => '',
  'sex' => '',
  'institutional_email' => '',
  'personal_email' => '',
  'phone_number' => '',
  'facebook' => '',
  'street_address' => '',
  'province' => '',
  'city' => '',
  'barangay' => '',
  'zip_code' => ''
];
$conn = Database::getInstance()->getConnection();
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, extension, dob, sex, institutional_email, personal_email, phone_number, facebook, street_address, province, city, barangay, zip_code FROM personal WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($personal['first_name'], $personal['middle_name'], $personal['last_name'], $personal['extension'], $personal['dob'], $personal['sex'], $personal['institutional_email'], $personal['personal_email'], $personal['phone_number'], $personal['facebook'], $personal['street_address'], $personal['province'], $personal['city'], $personal['barangay'], $personal['zip_code']);
$stmt->fetch();
$stmt->close();

// Fetch user email for prefill
$user_email = '';
$user_id = $_SESSION['user_id'];
$email_stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
$email_stmt->bind_param('i', $user_id);
$email_stmt->execute();
$email_stmt->bind_result($user_email);
$email_stmt->fetch();
$email_stmt->close();

// Fallback: if institutional_email empty, use users.email
if (!$personal['institutional_email']) {
  $personal['institutional_email'] = $user_email;
}

// Fetch education info for prefill
$education = [
  'school_university' => '',
  'campus_branch' => '',
  'college_department' => '',
  'program' => '',
  'major_specialization' => '',
  'alumni_id' => ''
];
$edu_stmt = $conn->prepare("SELECT school_university, campus_branch, college_department, program, major_specialization, alumni_id FROM education WHERE user_id = ? LIMIT 1");
$edu_stmt->bind_param('i', $user_id);
$edu_stmt->execute();
$edu_stmt->bind_result($education['school_university'], $education['campus_branch'], $education['college_department'], $education['program'], $education['major_specialization'], $education['alumni_id']);
$edu_stmt->fetch();
$edu_stmt->close();

// Fetch employment info for prefill
$employment = [
  'employment_status' => '',
  'mobility' => '',
  'company_name' => '',
  'industry' => '',
  'salary_per_month' => '',
  'year_of_employment' => '',
  'company_type' => ''
];
$emp_stmt = $conn->prepare("SELECT employment_status, mobility, company_name, industry, salary_per_month, year_of_employment, company_type FROM employment WHERE user_id = ? LIMIT 1");
$emp_stmt->bind_param('i', $user_id);
$emp_stmt->execute();
$emp_stmt->bind_result($employment['employment_status'], $employment['mobility'], $employment['company_name'], $employment['industry'], $employment['salary_per_month'], $employment['year_of_employment'], $employment['company_type']);
$emp_stmt->fetch();
$emp_stmt->close();

// Company address not collected in this wizard
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Your Information - ALUMytics</title>
    <link rel="stylesheet" href="signup.css">
    <style>
    .wizard-progress { height: 8px; background: #e0e0e0; border-radius: 4px; margin-bottom: 24px; overflow: hidden; }
    .wizard-progress-bar { height: 100%; background: #2e7d32; width: 0; transition: width 0.3s; }
    .wizard-step { display: none; }
    .wizard-step.active { display: block; }
    .wizard-nav { display: flex; justify-content: space-between; margin-top: 24px; }
    .form-error-box { color: #c0392b; background: #fbeee0; border: 1px solid #e17055; border-radius: 8px; padding: 10px 15px; margin-bottom: 10px; font-size: 15px; display: none; }
    /* Make the form area scrollable */
    .signup-one-container { min-height: 100vh; display: flex; }
    .right-panel { max-height: 100vh; overflow-y: auto; }
    .right-panel > div { padding-bottom: 24px; }
    </style>
</head>
<body>
<div class="signup-one-container">
  <!-- Left Green Panel -->
  <div class="left-panel">
    <div class="quote">“</div>
    <h1 class="welcome-text">You are the legacy.</h1>
    <div class="alumytics-text"> Keep shaping it</div>
    <div style="margin-top: 30px; font-size: 18px; font-weight: 500; text-align: center;">Update your information to unlock the full ALUMytics experience.</div>
  </div>
  <!-- Right Panel with Wizard Form -->
  <div class="right-panel">
    <div style="width:100%;max-width:480px;">
      <div class="wizard-progress"><div class="wizard-progress-bar" id="wizardProgressBar"></div></div>
      <form id="updateWizardForm" autocomplete="off" enctype="multipart/form-data">
        <!-- Step 1: Personal Info -->
        <div class="wizard-step active" data-step="1">
          <h2>Personal Information</h2>
          <small style="display:block;margin-bottom:12px;color:#555;">Please provide your full legal name, date of birth, and sex as they appear on your records.</small>
          <div class="form-error-box"></div>
          <label for="step1_first_name">First Name</label>
          <input type="text" id="step1_first_name" name="first_name" placeholder="First Name" required value="<?php echo htmlspecialchars($personal['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step1_middle_name">Middle Name</label>
          <input type="text" id="step1_middle_name" name="middle_name" placeholder="Middle Name" required value="<?php echo htmlspecialchars($personal['middle_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step1_last_name">Last Name</label>
          <input type="text" id="step1_last_name" name="last_name" placeholder="Last Name" required value="<?php echo htmlspecialchars($personal['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step1_extension">Extension</label>
          <input type="text" id="step1_extension" name="extension" placeholder="Extension (e.g., Jr., Sr.)" value="<?php echo htmlspecialchars($personal['extension'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step1_dob">Date of Birth</label>
          <input type="date" id="step1_dob" name="dob" placeholder="Date of Birth" required value="<?php echo htmlspecialchars($personal['dob'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step1_sex">Sex</label>
          <select id="step1_sex" name="sex" required style="margin-bottom: 22px;">
            <option value="" disabled selected>Select Sex</option>
            <option value="male" <?php if($personal['sex']==='male') echo 'selected'; ?>>Male</option>
            <option value="female" <?php if($personal['sex']==='female') echo 'selected'; ?>>Female</option>
          </select>
          <label for="profile_photo">Profile Photo (optional)</label>
          <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
        </div>
        <!-- Step 2: Contact Info (placeholder) -->
        <div class="wizard-step" data-step="2">
          <h2>Contact Information</h2>
          <small style="display:block;margin-bottom:12px;color:#555;">How can we reach you? Please provide your current contact details and address.</small>
          <div class="form-error-box"></div>
          <label for="step2_institutional_email">Institutional Email Address</label>
          <input type="email" id="step2_institutional_email" name="institutional_email" placeholder="Institutional Email Address" required value="<?php echo htmlspecialchars($personal['institutional_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step2_personal_email">Personal Email Address</label>
          <input type="email" id="step2_personal_email" name="personal_email" placeholder="Personal Email Address" required value="<?php echo htmlspecialchars($personal['personal_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step2_phone_number">Phone Number</label>
          <input type="tel" id="step2_phone_number" name="phone_number" placeholder="Phone Number" required value="<?php echo htmlspecialchars($personal['phone_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step2_facebook">Facebook Link (optional)</label>
          <input type="text" id="step2_facebook" name="facebook" placeholder="Facebook Link (optional)" value="<?php echo htmlspecialchars($personal['facebook'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step2_street_address">Street Address</label>
          <input type="text" id="step2_street_address" name="street_address" placeholder="Street Address" value="<?php echo htmlspecialchars($personal['street_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="province">Province</label>
          <select id="province" name="province" required data-selected="<?php echo htmlspecialchars($personal['province'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <option value="" disabled selected>Select Province</option>
          </select>
          <label for="city">City/Municipality</label>
          <select id="city" name="city" required data-selected="<?php echo htmlspecialchars($personal['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <option value="" disabled selected>Select City/Municipality</option>
          </select>
          <label for="barangay">Barangay</label>
          <select id="barangay" name="barangay" required data-selected="<?php echo htmlspecialchars($personal['barangay'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <option value="" disabled selected>Select Barangay</option>
          </select>
          <label for="step2_zip_code">Zip Code</label>
          <input type="text" id="step2_zip_code" name="zip_code" placeholder="Zip Code" value="<?php echo htmlspecialchars($personal['zip_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <!-- Step 3: Educational Info (placeholder) -->
        <div class="wizard-step" data-step="3">
          <h2>Educational Information</h2>
          <small style="display:block;margin-bottom:12px;color:#555;">Tell us about your academic background. Please use official names and codes if available.</small>
          <div class="form-error-box"></div>
          <label for="step3_school_university">School/University</label>
          <input type="text" id="step3_school_university" name="school_university" placeholder="School/University" required value="<?php echo htmlspecialchars($education['school_university'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step3_campus_branch">Campus/Branch</label>
          <input type="text" id="step3_campus_branch" name="campus_branch" placeholder="Campus/Branch" value="<?php echo htmlspecialchars($education['campus_branch'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step3_college_department">College/Department</label>
          <input type="text" id="step3_college_department" name="college_department" placeholder="College/Department" value="<?php echo htmlspecialchars($education['college_department'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step3_program">Program</label>
          <input type="text" id="step3_program" name="program" placeholder="Program (e.g. Bachelor of Science in Information Technology)" required value="<?php echo htmlspecialchars($education['program'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step3_major_specialization">Major/Specialization</label>
          <input type="text" id="step3_major_specialization" name="major_specialization" placeholder="Major/Specialization (e.g. Web Development)" value="<?php echo htmlspecialchars($education['major_specialization'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="step3_alumni_id">Alumni ID (unique)</label>
          <input type="text" id="step3_alumni_id" name="alumni_id" placeholder="Alumni ID (unique)" required value="<?php echo htmlspecialchars($education['alumni_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <!-- Step 4: Employment Info (placeholder) -->
        <div class="wizard-step" data-step="4">
          <h2>Employment Information</h2>
          <small style="display:block;margin-bottom:12px;color:#555;">Share your current employment status and work details.</small>
          <div class="form-error-box"></div>
          <label for="step4_employment_status">Employment Status</label>
          <select id="step4_employment_status" name="employment_status" required>
            <option value="" disabled selected>Employment Status</option>
            <option value="employed" <?php if($employment['employment_status']==='employed') echo 'selected'; ?>>Employed</option>
            <option value="unemployed" <?php if($employment['employment_status']==='unemployed') echo 'selected'; ?>>Unemployed</option>
            <option value="self_employed" <?php if($employment['employment_status']==='self_employed') echo 'selected'; ?>>Self Employed</option>
            <option value="studying" <?php if($employment['employment_status']==='studying') echo 'selected'; ?>>Studying</option>
          </select>
          <label for="step4_mobility">Mobility</label>
          <select id="step4_mobility" name="mobility" required>
            <option value="" disabled selected>Mobility</option>
            <option value="local" <?php if($employment['mobility']==='local') echo 'selected'; ?>>Local</option>
            <option value="international" <?php if($employment['mobility']==='international') echo 'selected'; ?>>International</option>
          </select>
          <label for="step4_company_name">Company Name</label>
          <input type="text" id="step4_company_name" name="company_name" placeholder="Company Name" value="<?php echo htmlspecialchars($employment['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="industry">Industry</label>
          <select name="industry" id="industry">
            <option value="" disabled selected>Select Industry</option>
            <?php
              $industriesList = [
                'Agriculture',
                'Banking and Finance',
                'Construction',
                'Education',
                'Energy and Utilities',
                'Entertainment',
                'Government',
                'Healthcare',
                'Hospitality',
                'Information Technology',
                'Manufacturing',
                'Marketing and Advertising',
                'Mining',
                'Non-Profit',
                'Pharmaceuticals',
                'Real Estate',
                'Retail',
                'Telecommunications',
                'Transportation and Logistics',
                'Other'
              ];
              foreach ($industriesList as $ind) {
                $sel = ($employment['industry'] === $ind) ? 'selected' : '';
                $safe = htmlspecialchars($ind, ENT_QUOTES, 'UTF-8');
                echo "<option value=\"$safe\" $sel>$safe</option>";
              }
            ?>
          </select>
          <label for="year_of_employment">Year of Employment</label>
          <select id="year_of_employment" name="year_of_employment">
            <option value="" disabled selected>Year of Employment</option>
            <?php $currentYear = date('Y'); for ($year = $currentYear; $year >= 1950; $year--) { $sel = ($employment['year_of_employment']==$year)?'selected':''; echo "<option value=\"$year\" $sel>$year</option>"; } ?>
          </select>
          <label for="step4_company_type">Type of Company</label>
          <select id="step4_company_type" name="company_type">
            <option value="" disabled selected>Type of Company</option>
            <option value="public" <?php if($employment['company_type']==='public') echo 'selected'; ?>>Public</option>
            <option value="private" <?php if($employment['company_type']==='private') echo 'selected'; ?>>Private</option>
            <option value="government" <?php if($employment['company_type']==='government') echo 'selected'; ?>>Government</option>
          </select>
          <!-- Company Address fields removed in this wizard -->
        </div>
        
        <!-- Step 6: Review & Confirm (editable form) -->
        <div class="wizard-step" data-step="6">
          <h2>Review & Confirm</h2>
          <div class="form-error-box"></div>
          <!-- Legacy summary container kept for backwards compatibility with older JS -->
          <div id="reviewInfo" style="display:none"></div>
          <div class="review-form-fields">
            <h3>Personal Information</h3>
            <label for="review_first_name">First Name</label>
            <input type="text" id="review_first_name" name="first_name" required>
            <label for="review_middle_name">Middle Name</label>
            <input type="text" id="review_middle_name" name="middle_name" required>
            <label for="review_last_name">Last Name</label>
            <input type="text" id="review_last_name" name="last_name" required>
            <label for="review_extension">Extension</label>
            <input type="text" id="review_extension" name="extension">
            <label for="review_dob">Date of Birth</label>
            <input type="date" id="review_dob" name="dob" required>
            <label for="review_sex">Sex</label>
            <select id="review_sex" name="sex" required>
              <option value="" disabled>Select Sex</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
            </select>
            <h3>Contact Information</h3>
            <label for="review_institutional_email">Institutional Email</label>
            <input type="email" id="review_institutional_email" name="institutional_email" required>
            <label for="review_personal_email">Personal Email</label>
            <input type="email" id="review_personal_email" name="personal_email" required>
            <label for="review_phone_number">Phone Number</label>
            <input type="tel" id="review_phone_number" name="phone_number" required>
            <label for="review_facebook">Facebook</label>
            <input type="text" id="review_facebook" name="facebook">
            <label for="review_street_address">Street Address</label>
            <input type="text" id="review_street_address" name="street_address">
            <label for="review_province">Province</label>
            <select id="review_province" name="province" required></select>
            <label for="review_city">City</label>
            <select id="review_city" name="city" required></select>
            <label for="review_barangay">Barangay</label>
            <select id="review_barangay" name="barangay" required></select>
            <label for="review_zip_code">Zip Code</label>
            <input type="text" id="review_zip_code" name="zip_code">
            <h3>Educational Information</h3>
            <label for="review_school_university">School/University</label>
            <input type="text" id="review_school_university" name="school_university" required>
            <label for="review_campus_branch">Campus/Branch</label>
            <input type="text" id="review_campus_branch" name="campus_branch">
            <label for="review_college_department">College/Department</label>
            <input type="text" id="review_college_department" name="college_department">
            <label for="review_program">Program</label>
            <input type="text" id="review_program" name="program" required>
            <label for="review_major_specialization">Major/Specialization</label>
            <input type="text" id="review_major_specialization" name="major_specialization">
            <label for="review_alumni_id">Alumni ID</label>
            <input type="text" id="review_alumni_id" name="alumni_id" required>
            <h3>Employment Information</h3>
            <label for="review_employment_status">Employment Status</label>
            <select id="review_employment_status" name="employment_status" required>
              <option value="" disabled>Employment Status</option>
              <option value="employed">Employed</option>
              <option value="unemployed">Unemployed</option>
              <option value="self_employed">Self Employed</option>
              <option value="studying">Studying</option>
            </select>
            <label for="review_mobility">Mobility</label>
            <select id="review_mobility" name="mobility" required>
              <option value="" disabled>Mobility</option>
              <option value="local">Local</option>
              <option value="international">International</option>
            </select>
            <label for="review_company_name">Company Name</label>
            <input type="text" id="review_company_name" name="company_name">
            <label for="review_industry">Industry</label>
            <input type="text" id="review_industry" name="industry">
            <!-- Salary per Month removed -->
            <label for="review_year_of_employment">Year of Employment</label>
            <input type="text" id="review_year_of_employment" name="year_of_employment">
            <label for="review_company_type">Type of Company</label>
            <select id="review_company_type" name="company_type">
              <option value="" disabled>Type of Company</option>
              <option value="public">Public</option>
              <option value="private">Private</option>
              <option value="government">Government</option>
            </select>
            <!-- Company Address fields removed in this wizard -->
            <!-- Awards/Certs can be handled as a repeatable section if needed -->
          </div>
        </div>
        <div class="wizard-nav">
          <button type="button" id="backBtn" style="display:none;">Back</button>
          <button type="button" id="nextBtn">Next</button>
          <button type="submit" id="submitBtn" style="display:none;">Confirm & Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="js/update-info-wizard.js"></script>
<script src="js/address-dropdown.js"></script>
<script>
// Prefill review step fields with values from previous steps on entering review step
function prefillReviewStep() {
  const form = document.getElementById('updateWizardForm');
  const reviewFields = document.querySelector('.review-form-fields');
  if (!reviewFields) return;
  const allInputs = form.querySelectorAll('input, select');
  reviewFields.querySelectorAll('input, select').forEach(field => {
    const name = field.name;
    if (!name) return;
    // Find the first matching field in the earlier steps
    const match = Array.from(allInputs).find(f => f.name === name && !reviewFields.contains(f));
    if (match) {
      if (field.tagName === 'SELECT') {
        Array.from(field.options).forEach(opt => {
          opt.selected = (opt.value === match.value);
        });
      } else {
        field.value = match.value;
      }
    }
  });
  // Special handling for PSGC address dropdowns in review step
  prefillReviewAddressDropdowns();
  prefillReviewCompanyAddressDropdowns();
}

function prefillReviewAddressDropdowns() {
  // Get values from step 2
  const provinceVal = document.getElementById('province')?.value || '';
  const cityVal = document.getElementById('city')?.value || '';
  const barangayVal = document.getElementById('barangay')?.value || '';
  const reviewProvince = document.getElementById('review_province');
  const reviewCity = document.getElementById('review_city');
  const reviewBarangay = document.getElementById('review_barangay');
  if (!reviewProvince || !reviewCity || !reviewBarangay) return;
  // PSGC API
  const psgcAPI = 'https://psgc.gitlab.io/api';
  // Populate provinces
  fetch(`${psgcAPI}/provinces/`)
    .then(res => res.json())
    .then(data => {
      reviewProvince.innerHTML = '<option value="" disabled>Select Province</option>';
      data.forEach(province => {
        const option = document.createElement('option');
        option.value = province.name;
        option.text = province.name;
        option.dataset.code = province.code;
        if (province.name === provinceVal) option.selected = true;
        reviewProvince.appendChild(option);
      });
      // Populate cities if province is selected
      if (provinceVal) {
        const selectedProvince = data.find(p => p.name === provinceVal);
        if (selectedProvince) {
          fetch(`${psgcAPI}/provinces/${selectedProvince.code}/cities-municipalities/`)
            .then(res => res.json())
            .then(cities => {
              reviewCity.innerHTML = '<option value="" disabled>Select City/Municipality</option>';
              cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.name;
                option.text = city.name;
                option.dataset.code = city.code;
                if (city.name === cityVal) option.selected = true;
                reviewCity.appendChild(option);
              });
              // Populate barangays if city is selected
              if (cityVal) {
                const selectedCity = cities.find(c => c.name === cityVal);
                if (selectedCity) {
                  fetch(`${psgcAPI}/cities-municipalities/${selectedCity.code}/barangays/`)
                    .then(res => res.json())
                    .then(brgys => {
                      reviewBarangay.innerHTML = '<option value="" disabled>Select Barangay</option>';
                      brgys.forEach(brgy => {
                        const option = document.createElement('option');
                        option.value = brgy.name;
                        option.text = brgy.name;
                        if (brgy.name === barangayVal) option.selected = true;
                        reviewBarangay.appendChild(option);
                      });
                    });
                }
              }
            });
        }
      }
    });
}

function prefillReviewCompanyAddressDropdowns() {
  const provinceVal = document.getElementById('company_province')?.value || '';
  const cityVal = document.getElementById('company_city')?.value || '';
  const barangayVal = document.getElementById('company_barangay')?.value || '';
  const reviewProvince = document.getElementById('review_company_province');
  const reviewCity = document.getElementById('review_company_city');
  const reviewBarangay = document.getElementById('review_company_barangay');
  if (!reviewProvince || !reviewCity || !reviewBarangay) return;
  const psgcAPI = 'https://psgc.gitlab.io/api';
  fetch(`${psgcAPI}/provinces/`)
    .then(res => res.json())
    .then(data => {
      reviewProvince.innerHTML = '<option value="" disabled>Select Province</option>';
      data.forEach(province => {
        const option = document.createElement('option');
        option.value = province.name;
        option.text = province.name;
        option.dataset.code = province.code;
        if (province.name === provinceVal) option.selected = true;
        reviewProvince.appendChild(option);
      });
      if (provinceVal) {
        const selectedProvince = data.find(p => p.name === provinceVal);
        if (selectedProvince) {
          fetch(`${psgcAPI}/provinces/${selectedProvince.code}/cities-municipalities/`)
            .then(res => res.json())
            .then(cities => {
              reviewCity.innerHTML = '<option value="" disabled>Select City/Municipality</option>';
              cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.name;
                option.text = city.name;
                option.dataset.code = city.code;
                if (city.name === cityVal) option.selected = true;
                reviewCity.appendChild(option);
              });
              if (cityVal) {
                const selectedCity = cities.find(c => c.name === cityVal);
                if (selectedCity) {
                  fetch(`${psgcAPI}/cities-municipalities/${selectedCity.code}/barangays/`)
                    .then(res => res.json())
                    .then(brgys => {
                      reviewBarangay.innerHTML = '<option value="" disabled>Select Barangay</option>';
                      brgys.forEach(brgy => {
                        const option = document.createElement('option');
                        option.value = brgy.name;
                        option.text = brgy.name;
                        if (brgy.name === barangayVal) option.selected = true;
                        reviewBarangay.appendChild(option);
                      });
                    });
                }
              }
            });
        }
      }
    });
}
document.addEventListener('DOMContentLoaded', function () {
  // Prefill review step when shown
  const steps = Array.from(document.querySelectorAll('.wizard-step'));
  const observer = new MutationObserver(() => {
    if (steps[steps.length - 1].classList.contains('active')) {
      prefillReviewStep();
    }
  });
  observer.observe(steps[steps.length - 1], { attributes: true, attributeFilter: ['class'] });
});
</script>
</body>
</html> 