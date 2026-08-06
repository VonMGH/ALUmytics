<?php 

include 'includes/Aheader.php'; 

include 'database.php';



$db = Database::getInstance();

$conn = $db->getConnection();



if (session_status() === PHP_SESSION_NONE) { session_start(); }



// Check if user is logged in

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {

    // User not logged in, redirect to signin page

    echo "<script>alert('Please sign in to access your dashboard.'); window.location.href = 'signin.php';</script>";

    exit();

}



// Get user information from session

$user_id = $_SESSION['user_id'];

$user_email = $_SESSION['email'];



// Verify user is still valid

$stmt = $conn->prepare("SELECT full_name, onboarded FROM users WHERE user_id = ? AND email = ?");

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

// Load academic lookup options for dropdowns (match UpdateAccount.php)
$universitiesRes = $conn->query("SELECT id, name FROM universities ORDER BY name");
$campusesRes = $conn->query("SELECT id, university_id, name FROM campuses ORDER BY name");
$departmentsRes = $conn->query("SELECT id, university_id, campus_id, name FROM departments ORDER BY name");
$programsRes = $conn->query("SELECT id, university_id, campus_id, department_id, name FROM programs ORDER BY name");
$specializationsRes = $conn->query("SELECT id, university_id, campus_id, department_id, program_id, name FROM specializations ORDER BY name");



// Set the institutional email for the form (can be updated by user)

$institutional_email = $_SESSION['user_email'] ?? $user_email;



// Fetch personal info for prefill

$personal = [

  'first_name' => '',

  'middle_name' => '',

  'last_name' => '',

  'extension' => '',

  'sex' => '',

  'civil_status' => '',

  'dob' => '',

  'institutional_email' => '',

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

$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, extension, sex, civil_status, dob, institutional_email, personal_email, phone_number, street_address, country, province, city, barangay, zip_code, profile_photo FROM personal WHERE user_id = ? LIMIT 1");

$stmt->bind_param('i', $user_id);

$stmt->execute();

$stmt->bind_result($personal['first_name'], $personal['middle_name'], $personal['last_name'], $personal['extension'], $personal['sex'], $personal['civil_status'], $personal['dob'], $personal['institutional_email'], $personal['personal_email'], $personal['phone_number'], $personal['street_address'], $personal['country'], $personal['province'], $personal['city'], $personal['barangay'], $personal['zip_code'], $personal['profile_photo']);

$stmt->fetch();

$stmt->close();

// Normalize country for dropdown display (DB stores uppercase e.g. PHILIPPINES)
$personal['country_display'] = !empty($personal['country'])
    ? ucwords(strtolower($personal['country']))
    : 'Philippines';

$stmt = $conn->prepare("SELECT school_university, campus_branch, college_department, program, major_specialization, alumni_id, year_graduated FROM education WHERE user_id = ? LIMIT 1");

$stmt->bind_param('i', $user_id);

$stmt->execute();

$stmt->bind_result($education['school_university'], $education['campus_branch'], $education['college_department'], $education['program'], $education['major_specialization'], $education['alumni_id'], $education['year_graduated']);

$stmt->fetch();

$stmt->close();





if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve form data and normalize names to Title Case (not full uppercase)

    $first_name_raw = trim($_POST['first_name'] ?? '');
    $first_name = ucwords(strtolower($first_name_raw));

    $middle_name_raw = trim($_POST['middle_name'] ?? '');
    $middle_name = ucwords(strtolower($middle_name_raw));

    $last_name_raw = trim($_POST['last_name'] ?? '');
    $last_name = ucwords(strtolower($last_name_raw));

    $extension = strtoupper(trim($_POST['extension']));

    $sex = $_POST['sex'];

    $civil_status = strtoupper(trim($_POST['civil_status']));

    $dob = $_POST['dob'];

    // Emails stay lowercase
    $institutional_email = strtolower(trim($_POST['institutional_email']));

    $personal_email = strtolower(trim($_POST['personal_email']));

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

    $alumni_id = strtoupper(trim($_POST['alumni_id']));
    $year_graduated = isset($_POST['year_graduated']) ? intval($_POST['year_graduated']) : null;



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



    // Normalize optional middle name to avoid saving placeholders like N/A
    if (in_array($middle_name, ['N/A', 'NA', 'NONE'])) {
        $middle_name = '';
    }

    // Update Personal Information

    $stmt = $conn->prepare("UPDATE personal SET first_name = ?, middle_name = ?, last_name = ?, extension = ?, sex = ?, civil_status = ?, dob = ?, institutional_email = ?, personal_email = ?, phone_number = ?, street_address = ?, country = ?, province = ?, city = ?, barangay = ?, zip_code = ?, profile_photo = ? WHERE user_id = ?");

    $types = str_repeat('s', 17) . 'i';
    $stmt->bind_param(
        $types,
        $first_name,
        $middle_name,
        $last_name,
        $extension,
        $sex,
        $civil_status,
        $dob,
        $institutional_email,
        $personal_email,
        $phone_number,
        $street_address,
        $country,
        $province,
        $city,
        $barangay,
        $zip_code,
        $profile_photo,
        $user_id
    );



    if ($stmt->execute()) {

        // Also update users.full_name to keep it in sync with personal table
        $full_name_parts = [$first_name];
        if (!empty($middle_name)) {
            $full_name_parts[] = $middle_name;
        }
        $full_name_parts[] = $last_name;
        $full_name = trim(implode(' ', $full_name_parts));

        $stmt_user = $conn->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
        if ($stmt_user) {
            $stmt_user->bind_param('si', $full_name, $user_id);
            $stmt_user->execute();
            $stmt_user->close();
        }

        // Upsert Educational Information
        $edu_check = $conn->prepare("SELECT 1 FROM education WHERE user_id = ? LIMIT 1");
        $edu_check->bind_param('i', $user_id);
        $edu_check->execute();
        $edu_check->store_result();
        $edu_exists = $edu_check->num_rows > 0;
        $edu_check->close();

        if ($edu_exists) {
            $stmt_edu = $conn->prepare("UPDATE education SET school_university = ?, campus_branch = ?, college_department = ?, program = ?, major_specialization = ?, alumni_id = ?, year_graduated = ? WHERE user_id = ?");
            $stmt_edu->bind_param("ssssssii", $school_university, $campus_branch, $college_department, $program, $major_specialization, $alumni_id, $year_graduated, $user_id);
            if (!$stmt_edu->execute()) {
                echo "<script>alert('Failed to update education: " . addslashes($stmt_edu->error) . "');</script>";
            }
            $stmt_edu->close();
        } else {
            $stmt_edu = $conn->prepare("INSERT INTO education (user_id, school_university, campus_branch, college_department, program, major_specialization, alumni_id, year_graduated) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_edu->bind_param("issssssi", $user_id, $school_university, $campus_branch, $college_department, $program, $major_specialization, $alumni_id, $year_graduated);
            if (!$stmt_edu->execute()) {
                echo "<script>alert('Failed to insert education: " . addslashes($stmt_edu->error) . "');</script>";
            }
            $stmt_edu->close();
        }



        echo "<script>alert('Profile updated successfully!'); window.location.href = 'index.php';</script>";

        exit();

    } else {
        echo "<script>alert('Error updating personal info: " . addslashes($stmt->error) . "');</script>";
    }

}

?>



<?php include 'includes/Asidebar.php';

$displayName = trim(implode(' ', array_filter([
    $personal['first_name'],
    $personal['middle_name'],
    $personal['last_name'],
])));
if (!empty($personal['extension']) && strtoupper($personal['extension']) !== 'N/A') {
    $displayName .= ', ' . $personal['extension'];
}
if ($displayName === '') {
    $displayName = 'Alumni';
}

$avatarInitials = '';
if (!empty($personal['first_name'])) {
    $avatarInitials .= strtoupper($personal['first_name'][0]);
}
if (!empty($personal['last_name'])) {
    $avatarInitials .= strtoupper($personal['last_name'][0]);
}
$avatarInitials = $avatarInitials ?: '?';
$hasProfilePhoto = !empty($personal['profile_photo']);

$profileCompletionFields = [
    $personal['first_name'],
    $personal['last_name'],
    $personal['sex'],
    $personal['civil_status'],
    $personal['dob'],
    $personal['institutional_email'],
    $personal['phone_number'],
    $personal['street_address'],
    $personal['country'],
    $personal['province'],
    $personal['city'],
    $personal['barangay'],
    $personal['zip_code'],
    $education['school_university'],
    $education['campus_branch'],
    $education['college_department'],
    $education['program'],
    $education['major_specialization'],
    $education['alumni_id'],
    $education['year_graduated'],
    $hasProfilePhoto ? '1' : '',
];
$profileFilledCount = count(array_filter($profileCompletionFields, static function ($value) {
    return $value !== '' && $value !== null;
}));
$profileCompletionPct = (int) round(($profileFilledCount / count($profileCompletionFields)) * 100);

$heroUniversity = $education['school_university'] ?: 'Not set';
$heroCampus = $education['campus_branch'] ?: 'Not set';
$heroDepartment = $education['college_department'] ?: 'Not set';
$heroYear = $education['year_graduated'] ?: '—';
?>

<link rel="stylesheet" href="alumni.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="index.css?v=<?php echo time(); ?>">

<main class="profile-content profile-dashboard-page">
  <div class="profile-dashboard-wrap">

    <header class="profile-hero">
      <div class="profile-hero-pattern" aria-hidden="true"></div>
      <div class="profile-hero-deco profile-hero-deco-1" aria-hidden="true"></div>
      <div class="profile-hero-deco profile-hero-deco-2" aria-hidden="true"></div>
      <div class="profile-hero-inner">
        <div class="profile-hero-main">
          <div class="profile-hero-avatar">
            <div class="avatar-ring">
              <img id="profilePreview"
                class="profile-avatar-img<?php echo $hasProfilePhoto ? '' : ' is-hidden'; ?>"
                src="<?php echo $hasProfilePhoto ? htmlspecialchars($personal['profile_photo']) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDE1MCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiBmaWxsPSIjZjBmMGYwIi8+CjxjaXJjbGUgY3g9Ijc1IiBjeT0iNjAiIHI9IjIwIiBmaWxsPSIjY2NjIi8+CjxwYXRoIGQ9Ik00NSAxMjBjMC0xNi41NjkgMTMuNDMxLTMwIDMwLTMwczcwIDEzLjQzMSAzMCAzMCIgZmlsbD0iI2NjYyIvPgo8L3N2Zz4='; ?>"
                alt="Profile Photo">
              <div id="avatarInitials" class="profile-avatar-initials<?php echo $hasProfilePhoto ? ' is-hidden' : ''; ?>"><?php echo htmlspecialchars($avatarInitials); ?></div>
            </div>
            <label for="profile_photo" class="avatar-edit-btn" aria-label="Change Profile Photo"><i class="fas fa-camera"></i></label>
            <input type="file" id="profile_photo" name="profile_photo" form="profileForm" accept="image/*" hidden>
          </div>
          <div class="profile-hero-text">
            <span class="profile-hero-badge"><i class="fas fa-graduation-cap"></i> Alumni Profile</span>
            <h1 class="profile-hero-name"><?php echo htmlspecialchars($displayName); ?></h1>
            <p class="profile-hero-meta">
              <?php if (!empty($education['alumni_id'])): ?>
                <span><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($education['alumni_id']); ?></span>
              <?php endif; ?>
              <?php if (!empty($education['program'])): ?>
                <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($education['program']); ?></span>
              <?php endif; ?>
              <?php if (!empty($education['year_graduated'])): ?>
                <span><i class="fas fa-calendar"></i> Class of <?php echo htmlspecialchars($education['year_graduated']); ?></span>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <aside class="profile-hero-aside" aria-label="Profile summary">
          <div class="hero-panel">
            <div class="hero-panel-top">
              <div class="hero-completion-ring" style="--completion: <?php echo $profileCompletionPct; ?>;">
                <span class="hero-completion-value"><?php echo $profileCompletionPct; ?>%</span>
              </div>
              <div class="hero-panel-intro">
                <span class="hero-panel-label">Profile strength</span>
                <p class="hero-panel-desc"><?php echo $profileCompletionPct >= 100 ? 'Your profile is complete' : 'Complete your details below'; ?></p>
              </div>
            </div>
            <div class="hero-progress-track" role="progressbar" aria-valuenow="<?php echo $profileCompletionPct; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completion">
              <div class="hero-progress-fill" style="width: <?php echo $profileCompletionPct; ?>%;"></div>
            </div>
            <div class="hero-stats-grid">
              <div class="hero-stat-tile">
                <span class="hero-stat-icon"><i class="fas fa-university"></i></span>
                <span class="hero-stat-label">University</span>
                <span class="hero-stat-value"><?php echo htmlspecialchars($heroUniversity); ?></span>
              </div>
              <div class="hero-stat-tile">
                <span class="hero-stat-icon"><i class="fas fa-map-marker-alt"></i></span>
                <span class="hero-stat-label">Campus</span>
                <span class="hero-stat-value"><?php echo htmlspecialchars($heroCampus); ?></span>
              </div>
              <div class="hero-stat-tile">
                <span class="hero-stat-icon"><i class="fas fa-building"></i></span>
                <span class="hero-stat-label">Department</span>
                <span class="hero-stat-value"><?php echo htmlspecialchars($heroDepartment); ?></span>
              </div>
              <div class="hero-stat-tile">
                <span class="hero-stat-icon"><i class="fas fa-calendar-check"></i></span>
                <span class="hero-stat-label">Graduated</span>
                <span class="hero-stat-value"><?php echo htmlspecialchars($heroYear); ?></span>
              </div>
            </div>
          </div>
        </aside>
      </div>
      <p class="profile-hero-hint">Photo: 400×400 recommended · Max 2MB</p>
    </header>

    <form id="profileForm" method="post" enctype="multipart/form-data">

      <section class="profile-section-card">
        <div class="section-header">
          <span class="section-icon"><i class="fas fa-user"></i></span>
          <div>
            <h2>Basic Information</h2>
            <p>Your legal name and personal details</p>
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" placeholder="First Name" required value="<?php echo htmlspecialchars($personal['first_name']); ?>" aria-label="First Name">
          </div>
          <div class="form-group">
            <label for="middle_name">Middle Name <span class="optional-tag">(optional)</span></label>
            <input type="text" id="middle_name" name="middle_name" placeholder="Middle Name" value="<?php echo htmlspecialchars($personal['middle_name']); ?>" aria-label="Middle Name">
          </div>
          <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" placeholder="Last Name" required value="<?php echo htmlspecialchars($personal['last_name']); ?>" aria-label="Last Name">
          </div>
          <div class="form-group">
            <label for="extension">Extension</label>
            <input type="text" id="extension" name="extension" placeholder="Jr., Sr., III…" value="<?php echo htmlspecialchars($personal['extension']); ?>" aria-label="Extension">
          </div>
          <div class="demographics-row">
            <div class="form-group sex-field">
              <label>Sex</label>
              <div class="sex-pills">
                <label>
                  <input type="radio" id="sex_male" name="sex" value="male" required <?php if ($personal['sex'] === 'male') echo 'checked'; ?> aria-label="Male">
                  <span class="sex-pill-text"><i class="fas fa-mars"></i> Male</span>
                </label>
                <label>
                  <input type="radio" id="sex_female" name="sex" value="female" required <?php if ($personal['sex'] === 'female') echo 'checked'; ?> aria-label="Female">
                  <span class="sex-pill-text"><i class="fas fa-venus"></i> Female</span>
                </label>
              </div>
            </div>
            <div class="form-group civil-field">
              <label for="civil_status">Civil Status</label>
              <select id="civil_status" name="civil_status" aria-label="Civil Status" data-selected="<?php echo htmlspecialchars($personal['civil_status'] ?? ''); ?>">
                <option value="" disabled selected>Select Civil Status</option>
                <option value="Single" <?php echo (strcasecmp($personal['civil_status'] ?? '', 'Single') === 0) ? 'selected' : ''; ?>>Single</option>
                <option value="Married" <?php echo (strcasecmp($personal['civil_status'] ?? '', 'Married') === 0) ? 'selected' : ''; ?>>Married</option>
                <option value="Separated" <?php echo (strcasecmp($personal['civil_status'] ?? '', 'Separated') === 0) ? 'selected' : ''; ?>>Separated</option>
                <option value="Widowed" <?php echo (strcasecmp($personal['civil_status'] ?? '', 'Widowed') === 0) ? 'selected' : ''; ?>>Widowed</option>
                <option value="Other" <?php echo (strcasecmp($personal['civil_status'] ?? '', 'Other') === 0) ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>
            <div class="form-group dob-field">
              <label for="dob">Date of Birth</label>
              <input type="date" id="dob" name="dob" required value="<?php echo htmlspecialchars($personal['dob']); ?>" aria-label="Date of Birth">
            </div>
          </div>
        </div>
      </section>

      <section class="profile-section-card">
        <div class="section-header">
          <span class="section-icon"><i class="fas fa-address-book"></i></span>
          <div>
            <h2>Contact Information</h2>
            <p>How we can reach you</p>
          </div>
        </div>
        <div class="form-grid">
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
          <div class="form-group span-full">
            <label for="street_address">Street Address</label>
            <input type="text" id="street_address" name="street_address" placeholder="Street Address" required value="<?php echo htmlspecialchars($personal['street_address']); ?>" aria-label="Street Address">
          </div>
          <div class="form-group">
            <label for="country">Country</label>
            <select id="country" name="country" required aria-label="Country" data-selected="<?php echo htmlspecialchars($personal['country_display']); ?>" data-selected-name="<?php echo htmlspecialchars($personal['country_display']); ?>">
              <option value="" disabled selected>Select Country</option>
            </select>
          </div>
          <div class="form-group">
            <label for="province">Province</label>
            <select id="province" name="province" required aria-label="Province" data-selected="<?php echo htmlspecialchars($personal['province']); ?>" data-selected-name="<?php echo htmlspecialchars($personal['province']); ?>">
              <option value="" disabled selected>Select Province</option>
            </select>
          </div>
          <div class="form-group">
            <label for="city">City/Municipality</label>
            <select id="city" name="city" required aria-label="City/Municipality" data-selected="<?php echo htmlspecialchars($personal['city']); ?>" data-selected-name="<?php echo htmlspecialchars($personal['city']); ?>">
              <option value="" disabled selected>Select City/Municipality</option>
            </select>
          </div>
          <div class="form-group">
            <label for="barangay">Barangay</label>
            <select id="barangay" name="barangay" required aria-label="Barangay" data-selected="<?php echo htmlspecialchars($personal['barangay']); ?>" data-selected-name="<?php echo htmlspecialchars($personal['barangay']); ?>">
              <option value="" disabled selected>Select Barangay</option>
            </select>
          </div>
          <div class="form-group">
            <label for="zip_code">Zip Code</label>
            <input type="text" id="zip_code" name="zip_code" placeholder="Zip Code" required value="<?php echo htmlspecialchars($personal['zip_code']); ?>" aria-label="Zip Code">
          </div>
        </div>
      </section>

      <section class="profile-section-card">
        <div class="section-header">
          <span class="section-icon"><i class="fas fa-university"></i></span>
          <div>
            <h2>Educational Information</h2>
            <p>Your academic background and alumni credentials</p>
          </div>
        </div>
        <div class="education-grid">
          <div class="field-wrap">
            <label class="required-label" for="school_university">School/University</label>

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
          </div>

          <div class="field-wrap">
            <label class="required-label" for="campus_branch">Campus/Branch</label>
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
          </div>

          <div class="field-wrap">
            <label class="required-label" for="college_department">College/Department</label>
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
          </div>

          <div class="field-wrap">
            <label class="required-label" for="program">Program</label>
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
          </div>

          <div class="field-wrap">
            <label class="required-label" for="major_specialization">Major/Specialization</label>
        <input
          type="text"
          id="major_specialization"
          name="major_specialization"
          placeholder="Major/Specialization"
          required
          value="<?php echo htmlspecialchars($education['major_specialization']); ?>"
        >
          </div>

          <div class="field-wrap">
            <label class="required-label" for="alumni_id">Alumni ID</label>
        <input type="text" id="alumni_id" name="alumni_id" placeholder="Alumni ID" required value="<?php echo htmlspecialchars(strtoupper($education['alumni_id'])); ?>">
          </div>

          <div class="field-wrap education-year-row">
            <label class="required-label" for="year_graduated">Year Graduated</label>
        <select id="year_graduated" name="year_graduated" required data-selected="<?php echo htmlspecialchars($education['year_graduated']); ?>">

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
        </div>
      </section>

      <div class="save-bar">
        <div class="form-actions">
          <button type="submit" class="save-button"><i class="fas fa-check"></i> Save Changes</button>
        </div>
      </div>

    </form>
  </div>
</main>

<script src="js/address-dropdown.js"></script>
<script src="js/academic-dropdowns.js?v=<?php echo time(); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var profileInput = document.getElementById('profile_photo');
  var profileImg = document.getElementById('profilePreview');
  var avatarInitials = document.getElementById('avatarInitials');

  if (profileInput && profileImg) {
    profileInput.addEventListener('change', function () {
      var file = this.files && this.files[0];
      if (!file) return;
      var blobUrl = (window.URL || window.webkitURL).createObjectURL(file);
      profileImg.src = blobUrl;
      profileImg.classList.remove('is-hidden');
      if (avatarInitials) avatarInitials.classList.add('is-hidden');
    });
  }

  var profileForm = document.getElementById('profileForm');
  if (profileForm) {
    profileForm.addEventListener('submit', function () {
      var countryEl = document.getElementById('country');
      var provinceEl = document.getElementById('province');
      var cityEl = document.getElementById('city');
      var barangayEl = document.getElementById('barangay');
      if (countryEl && countryEl.value.toLowerCase() !== 'philippines') {
        if (provinceEl) provinceEl.value = '';
        if (cityEl) cityEl.value = '';
        if (barangayEl) barangayEl.value = '';
      }
    });
  }

  function toUpperField(str) {
    return (str || '').toString().toUpperCase();
  }

  function toProperCase(str) {
    return str.replace(/\w\S*/g, function (txt) {
      return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
    });
  }

  ['first_name', 'middle_name', 'last_name', 'extension', 'street_address', 'school_university', 'campus_branch', 'college_department', 'program', 'major_specialization', 'alumni_id'].forEach(function (id) {
    var el = document.getElementsByName(id)[0];
    if (el && el.tagName === 'INPUT' && (el.type === 'text' || el.type === 'tel')) {
      el.addEventListener('input', function () {
        var cursorPos = el.selectionStart;
        el.value = toUpperField(el.value);
        if (typeof cursorPos === 'number') {
          el.selectionStart = el.selectionEnd = cursorPos;
        }
      });
    }
  });

  ['first_name', 'middle_name', 'last_name', 'extension', 'school_university', 'major_specialization', 'program'].forEach(function (id) {
    var el = document.getElementsByName(id)[0];
    if (el && el.tagName === 'INPUT' && (el.type === 'text' || el.type === 'tel')) {
      el.addEventListener('blur', function () {
        el.value = toProperCase(el.value);
      });
    }
  });
});
</script>

