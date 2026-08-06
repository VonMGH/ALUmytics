<?php
include 'includes/Aheader.php';
include 'database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

session_start();

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
    'dob' => '',
    'institutional_email' => $user_email,
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

$employment = [
    'employment_status' => '',
    'mobility' => '',
    'company_name' => '',
    'industry' => '',
    'salary_per_month' => '',
    'year_of_employment' => '',
    'company_type' => ''
];

$company_address = [
    'company_street_address' => '',
    'company_province' => '',
    'company_city' => '',
    'company_barangay' => '',
    'company_zip_code' => ''
];

// Load existing data if available
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, extension, sex, dob, institutional_email, personal_email, phone_number, street_address, province, city, barangay, zip_code, profile_photo FROM personal WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result(
    $personal['first_name'], $personal['middle_name'], $personal['last_name'], $personal['extension'], $personal['sex'], $personal['dob'],
    $personal['institutional_email'], $personal['personal_email'], $personal['phone_number'], $personal['street_address'], $personal['province'],
    $personal['city'], $personal['barangay'], $personal['zip_code'], $personal['profile_photo']
);
$stmt->fetch();
$stmt->close();

// If no institutional email saved, default to session email
if (empty($personal['institutional_email'])) {
    $personal['institutional_email'] = $user_email;
}

$stmt = $conn->prepare("SELECT school_university, campus_branch, college_department, program, major_specialization, alumni_id FROM education WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result(
    $education['school_university'], $education['campus_branch'], $education['college_department'],
    $education['program'], $education['major_specialization'], $education['alumni_id']
);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT employment_status, mobility, company_name, industry, salary_per_month, year_of_employment, company_type FROM employment WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result(
    $employment['employment_status'], $employment['mobility'], $employment['company_name'], $employment['industry'],
    $employment['salary_per_month'], $employment['year_of_employment'], $employment['company_type']
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
    $employment_status = $_POST['employment_status'];
    $mobility = $_POST['mobility'];
    $company_name = $_POST['company_name'];
    $industry = $_POST['industry'];
    $salary_per_month = $_POST['salary_per_month'];
    $year_of_employment = $_POST['year_of_employment'];
    $company_type = $_POST['company_type'];
    $company_street_address = $_POST['company_street_address'];
    $company_province = $_POST['company_province'];
    $company_city = $_POST['company_city'];
    $company_barangay = $_POST['company_barangay'];
    $company_zip_code = $_POST['company_zip_code'];

    // Handle profile photo upload (keep existing if none uploaded)
    $new_profile_photo = $personal['profile_photo'];
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
            // Delete old photo if exists
            if (!empty($personal['profile_photo']) && file_exists($personal['profile_photo'])) {
                @unlink($personal['profile_photo']);
            }
            $new_profile_photo = $upload_dir . uniqid('profile_') . '_' . $file_name;
            move_uploaded_file($file_tmp, $new_profile_photo);
        } else {
            echo "Invalid file type or size. Upload JPEG/PNG under 2MB.";
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
        $stmt = $conn->prepare("UPDATE personal SET first_name = ?, middle_name = ?, last_name = ?, extension = ?, sex = ?, dob = ?, institutional_email = ?, personal_email = ?, phone_number = ?, street_address = ?, province = ?, city = ?, barangay = ?, zip_code = ?, profile_photo = ? WHERE user_id = ?");
        $stmt->bind_param("sssssssssssssssi", $first_name, $middle_name, $last_name, $extension, $sex, $dob, $institutional_email, $personal_email, $phone_number, $street_address, $province, $city, $barangay, $zip_code, $new_profile_photo, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO personal (user_id, first_name, middle_name, last_name, extension, sex, dob, institutional_email, personal_email, phone_number, street_address, province, city, barangay, zip_code, profile_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssssssssss", $user_id, $first_name, $middle_name, $last_name, $extension, $sex, $dob, $institutional_email, $personal_email, $phone_number, $street_address, $province, $city, $barangay, $zip_code, $new_profile_photo);
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
        $stmt = $conn->prepare("UPDATE education SET school_university = ?, campus_branch = ?, college_department = ?, program = ?, major_specialization = ?, alumni_id = ? WHERE user_id = ?");
        $stmt->bind_param("ssssssi", $school_university, $campus_branch, $college_department, $program, $major_specialization, $alumni_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO education (user_id, school_university, campus_branch, college_department, program, major_specialization, alumni_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $user_id, $school_university, $campus_branch, $college_department, $program, $major_specialization, $alumni_id);
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
        $stmt = $conn->prepare("UPDATE employment SET employment_status = ?, mobility = ?, company_name = ?, industry = ?, salary_per_month = ?, year_of_employment = ?, company_type = ? WHERE user_id = ?");
        $stmt->bind_param("sssssssi", $employment_status, $mobility, $company_name, $industry, $salary_per_month, $year_of_employment, $company_type, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO employment (user_id, employment_status, mobility, company_name, industry, salary_per_month, year_of_employment, company_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $employment_status, $mobility, $company_name, $industry, $salary_per_month, $year_of_employment, $company_type);
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

    // Insert certifications (new entries only)
    if (!empty($_POST['cert_name']) && is_array($_POST['cert_name'])) {
        $certNames = $_POST['cert_name'];
        $certIndustries = isset($_POST['cert_industry']) && is_array($_POST['cert_industry']) ? $_POST['cert_industry'] : [];
        $certDates = isset($_POST['cert_date']) && is_array($_POST['cert_date']) ? $_POST['cert_date'] : [];
        foreach ($certNames as $index => $name) {
            $name = trim($name);
            if ($name === '') { continue; }
            $industryVal = isset($certIndustries[$index]) ? $certIndustries[$index] : '';
            $dateVal = isset($certDates[$index]) ? $certDates[$index] : null;
            $certTargetDir = __DIR__ . "/uploads/certification/";
            if (!is_dir($certTargetDir)) { mkdir($certTargetDir, 0755, true); }
            $filePathSaved = null;
            if (isset($_FILES['cert_file']['tmp_name'][$index]) && is_uploaded_file($_FILES['cert_file']['tmp_name'][$index])) {
                $certTmpPath = $_FILES['cert_file']['tmp_name'][$index];
                $certOriginalName = basename($_FILES['cert_file']['name'][$index]);
                $certUniqueName = uniqid() . "_" . $certOriginalName;
                $filePathSaved = $certTargetDir . $certUniqueName;
                move_uploaded_file($certTmpPath, $filePathSaved);
            }
            $stmt = $conn->prepare("INSERT INTO certifications (user_id, certification_name, industry, certification_date, certification_file) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $name, $industryVal, $dateVal, $filePathSaved);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Insert awards (new entries only)
    if (!empty($_POST['award_name']) && is_array($_POST['award_name'])) {
        $awardNames = $_POST['award_name'];
        $awardCategories = isset($_POST['award_category']) && is_array($_POST['award_category']) ? $_POST['award_category'] : [];
        $awardDates = isset($_POST['award_date']) && is_array($_POST['award_date']) ? $_POST['award_date'] : [];
        foreach ($awardNames as $index => $name) {
            $name = trim($name);
            if ($name === '') { continue; }
            $categoryVal = isset($awardCategories[$index]) ? $awardCategories[$index] : '';
            $dateVal = isset($awardDates[$index]) ? $awardDates[$index] : null;
            $awardTargetDir = __DIR__ . "/uploads/awards/";
            if (!is_dir($awardTargetDir)) { mkdir($awardTargetDir, 0755, true); }
            $filePathSaved = null;
            if (isset($_FILES['award_file']['tmp_name'][$index]) && is_uploaded_file($_FILES['award_file']['tmp_name'][$index])) {
                $awardTmpPath = $_FILES['award_file']['tmp_name'][$index];
                $awardOriginalName = basename($_FILES['award_file']['name'][$index]);
                $awardUniqueName = uniqid() . "_" . $awardOriginalName;
                $filePathSaved = $awardTargetDir . $awardUniqueName;
                move_uploaded_file($awardTmpPath, $filePathSaved);
            }
            $stmt = $conn->prepare("INSERT INTO awards (user_id, award_name, category, award_date, award_file, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issss", $user_id, $name, $categoryVal, $dateVal, $filePathSaved);
            $stmt->execute();
            $stmt->close();
        }
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



<link rel="stylesheet" href="UpdateAccount.css">

<div class="signup-container">
<h1 class="alumytics-account-heading">Create your Alumytics Account</h1>
<form class="signup-form" method="post" action="" enctype="multipart/form-data">
    
    <!-- Profile Photo Upload Section -->
    
    <div class="profile-photo-section">
    <div class="avatar-placeholder">
        <img id="profilePreview" src="<?php echo $personal['profile_photo'] ? htmlspecialchars($personal['profile_photo']) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDE1MCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiBmaWxsPSIjZjBmMGYwIi8+CjxjaXJjbGUgY3g9Ijc1IiBjeT0iNjAiIHI9IjIwIiBmaWxsPSIjY2NjIi8+CjxwYXRoIGQ9Ik00NSAxMjBjMC0xNi41NjkgMTMuNDMxLTMwIDMwLTMwczcwIDEzLjQzMSAzMCAzMCIgZmlsbD0iI2NjYyIvPgo8L3N2Zz4='; ?>" alt="Profile Photo" width="150" style="border-radius: 50%; object-fit: cover;" />
    </div>

    <label for="profile_photo" class="upload-button">
        <span class="upload-icon">&#8682;</span> Upload Photo
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
    <input type="text" name="first_name" placeholder="First Name" required value="<?php echo htmlspecialchars($personal['first_name']); ?>">
    <input type="text" name="middle_name" placeholder="Middle Name" value="<?php echo htmlspecialchars($personal['middle_name']); ?>">
    <input type="text" name="last_name" placeholder="Last Name" required value="<?php echo htmlspecialchars($personal['last_name']); ?>">
    <input type="text" name="extension" placeholder="Extension" value="<?php echo htmlspecialchars($personal['extension']); ?>">

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

    <!-- Contact Information Section -->
    <h3>Contact Information</h3>
    <input type="email" name="institutional_email" placeholder="Institutional Email" required value="<?php echo htmlspecialchars($personal['institutional_email']); ?>">
    <input type="email" name="personal_email" placeholder="Personal Email" value="<?php echo htmlspecialchars($personal['personal_email']); ?>">
    <input type="tel" name="phone_number" placeholder="Phone Number" required value="<?php echo htmlspecialchars($personal['phone_number']); ?>">
    <input type="text" name="street_address" placeholder="Street Address" required value="<?php echo htmlspecialchars($personal['street_address']); ?>">

    <select id="province" name="province" required data-selected="<?php echo htmlspecialchars($personal['province']); ?>">
        <option value="" disabled selected>Select Province</option>
    </select>
    <select id="city" name="city" required data-selected="<?php echo htmlspecialchars($personal['city']); ?>">
        <option value="" disabled selected>Select City/Municipality</option>
    </select>
    <select id="barangay" name="barangay" required data-selected="<?php echo htmlspecialchars($personal['barangay']); ?>">
        <option value="" disabled selected>Select Barangay</option>
    </select>

    <input type="text" name="zip_code" placeholder="Zip Code" required value="<?php echo htmlspecialchars($personal['zip_code']); ?>">

    <!-- Educational Information Section -->
    <h3>EDUCATIONAL INFORMATION</h3>
    <div class="input-row">
        <input type="text" name="school_university" placeholder="School/University" required value="<?php echo htmlspecialchars($education['school_university']); ?>">
        <input type="text" name="campus_branch" placeholder="Campus/Branch" required value="<?php echo htmlspecialchars($education['campus_branch']); ?>">
        <input type="text" name="college_department" placeholder="College/Department" required value="<?php echo htmlspecialchars($education['college_department']); ?>">
        <input type="text" name="program" placeholder="Program" required value="<?php echo htmlspecialchars($education['program']); ?>">
        <input type="text" name="major_specialization" placeholder="Major/Specialization" required value="<?php echo htmlspecialchars($education['major_specialization']); ?>">
        <input type="text" name="alumni_id" placeholder="Alumni ID" required value="<?php echo htmlspecialchars($education['alumni_id']); ?>">
    </div>

    <!-- Employment Information Section -->
    <h3>EMPLOYMENT INFORMATION</h3>
    <div class="employment-info-row">
        <div class="employment-status">
            <label for="employment_status">Employment Status:</label>
            <select id="employment_status" name="employment_status" required data-selected="<?php echo htmlspecialchars($employment['employment_status']); ?>">
                <option value="">Select Status</option>
                <option value="employed">Employed</option>
                <option value="unemployed">Unemployed</option>
                <option value="self_employed">Self Employed</option>
                <option value="studying">Studying</option>
            </select>
        </div>
        <br>
        <div class="mobility-status">
            <label for="mobility">Mobility:</label>
            <select id="mobility" name="mobility" required data-selected="<?php echo htmlspecialchars($employment['mobility']); ?>">
                <option value="">Select Mobility</option>
                <option value="international">International</option>
                <option value="local">Local</option>
            </select>
        </div>
    </div>

    <!-- Company Details Section -->
    <div class="company-section">
    <h3>Company Details</h3>

    <div class="company-details-row">
        <input type="text" name="company_name" placeholder="Company Name" value="<?php echo htmlspecialchars($employment['company_name']); ?>">

        <!-- Replace text input with select for Industry -->
        <select name="industry" id="employment_industry" data-selected="<?php echo htmlspecialchars($employment['industry']); ?>">
            <option value="" disabled selected>Select Industry</option>
        </select>

        <input type="text" name="salary_per_month" placeholder="Salary per Month (Optional)" value="<?php echo htmlspecialchars($employment['salary_per_month']); ?>">
    </div>

    <div class="company-type-row">
        <select name="year_of_employment" data-selected="<?php echo htmlspecialchars($employment['year_of_employment']); ?>">
            <option value="" disabled selected>Year of Employment</option>
            <?php
            $currentYear = date("Y");
            for ($year = $currentYear; $year >= 1950; $year--) {
                echo "<option value=\"$year\">$year</option>";
            }
            ?>
        </select>

        <div class="company-type-options">
            <label>Type of Company:</label>
            <label><input type="radio" name="company_type" value="public" <?php echo $employment['company_type']==='public' ? 'checked' : ''; ?>> Public</label>
            <label><input type="radio" name="company_type" value="private" <?php echo $employment['company_type']==='private' ? 'checked' : ''; ?>> Private</label>
            <label><input type="radio" name="company_type" value="government" <?php echo $employment['company_type']==='government' ? 'checked' : ''; ?>> Government</label>
        </div>
    </div>
</div>

    <!-- Company Address Section -->
    <div class="company-address-section">
        <hr>
        <h3>Company Address</h3>
        <input type="text" name="company_street_address" placeholder="Street Address" value="<?php echo htmlspecialchars($company_address['company_street_address']); ?>">
        <div class="company-address-row">
            <select id="company_province" name="company_province" data-selected="<?php echo htmlspecialchars($company_address['company_province']); ?>">
                <option value="" disabled selected>Select Province</option>
            </select>
            <select id="company_city" name="company_city" data-selected="<?php echo htmlspecialchars($company_address['company_city']); ?>">
                <option value="" disabled selected>Select City/Municipality</option>
            </select>
            <select id="company_barangay" name="company_barangay" data-selected="<?php echo htmlspecialchars($company_address['company_barangay']); ?>">
                <option value="" disabled selected>Select Barangay</option>
            </select>
        </div>
        <input type="text" name="company_zip_code" placeholder="Zip Code" value="<?php echo htmlspecialchars($company_address['company_zip_code']); ?>">
    </div>

<div class="certification-section">
    <hr>
    <h3>CERTIFICATION</h3>
    
    <!-- Initial Certification Entry -->
    <div class="certification-entry">
        <input type="text" id="certification-name" name="cert_name[]" placeholder="Name of Certification">
        <select name="cert_industry[]" class="cert-industry">
            <option value="" disabled selected>Select Industry</option>
            <!-- Example industry options -->
            <option value="Technology">Technology</option>
            <option value="Healthcare">Healthcare</option>
        </select>
        <input type="date" id="issue-date" name="cert_date[]" placeholder="Issued Date">
    </div>

    <!-- Upload Section for Certifications -->
    <div class="upload-section">
        <label for="upload-cert">Upload Certification</label>
        <input type="file" id="upload-cert" name="cert_file[]">
    </div>
    
    <!-- Button to Add More Certifications -->
    <button type="button" class="add-cert-button">+ Add Certifications</button>

    <!-- Container for Dynamic Certifications -->
    <div id="new-certifications"></div>
</div>

<hr>

<div class="awards-section">
    <h3>AWARDS</h3>
    
    <!-- Initial Award Entry -->
    <div class="awards-entry">
        <input type="text" id="award-name" name="award_name[]" placeholder="Award Title">
        <select name="award_category[]" class="award-category-select">
            <option value="" disabled selected>Select Category</option>
            <option value="Academic Excellence">Academic Excellence</option>
            <option value="Leadership">Leadership</option>
            <option value="Best Performance">Best Performance</option>
            <option value="Innovation">Innovation</option>
            <option value="Community Service">Community Service</option>
            <option value="Professional Achievement">Professional Achievement</option>
            <option value="Other">Other</option>
        </select>
        <input type="text" id="awarded-by" name="awarded_by[]" placeholder="Awarded By (Organization)">
        <input type="number" id="award-year" name="award_year[]" placeholder="Year" min="1900" max="2099">
        <input type="date" id="award-date" name="award_date[]" placeholder="Date Received">
        <textarea name="award_description[]" placeholder="Description (optional)" rows="2"></textarea>
    </div>

    <!-- Upload Section for Awards -->
    <div class="upload-section">
        <label for="upload-award">Upload Award Certificate</label>
        <input type="file" id="upload-award" name="award_file[]">
    </div>
    
    <!-- Button to Add More Awards -->
    <button type="button" id="add-award-button" class="add-cert-button">+ Add Awards</button>

    <!-- Container for Dynamic Awards -->
    <div id="new-awards"></div>
</div>


    <button type="submit" class="save-button">Save Changes</button>
</form>
</div>

<script src="js/address-dropdown.js?v=<?php echo time(); ?>"></script>
<script src="js/academic-dropdowns.js?v=<?php echo time(); ?>"></script>
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

  // For dynamically adding certification selects, we attach population on demand too
  function populateIndustrySelect(selectEl) {
    if (!selectEl) return;
    // Avoid duplicate population
    if (selectEl.getAttribute('data-populated') === '1') return;
    industries.forEach(function(ind) {
      const opt = document.createElement('option');
      opt.value = ind;
      opt.text = ind;
      selectEl.appendChild(opt);
    });
    selectEl.setAttribute('data-populated', '1');
  }

  document.querySelectorAll('select.cert-industry').forEach(function(sel){
    populateIndustrySelect(sel);
  });

  // Hook add-certifications button to also populate new selects
  const addCertBtn = document.querySelector('.add-cert-button');
  const certContainer = document.getElementById('new-certifications');
  if (addCertBtn && certContainer) {
    addCertBtn.addEventListener('click', function() {
      const wrapper = document.createElement('div');
      wrapper.className = 'certification-entry';
      wrapper.innerHTML = `
        <input type="text" name="cert_name[]" placeholder="Name of Certification">
        <select name="cert_industry[]" class="cert-industry">
          <option value="" disabled selected>Select Industry</option>
        </select>
        <input type="date" name="cert_date[]" placeholder="Issued Date">
        <div class="upload-section">
          <label>Upload Certification</label>
          <input type="file" name="cert_file[]">
        </div>
      `;
      certContainer.appendChild(wrapper);
      populateIndustrySelect(wrapper.querySelector('select.cert-industry'));
    });
  }

  // Awards add dynamic entries with category options preserved
  const addAwardBtn = document.getElementById('add-award-button');
  const awardContainer = document.getElementById('new-awards');
  if (addAwardBtn && awardContainer) {
    addAwardBtn.addEventListener('click', function(){
      const wrapper = document.createElement('div');
      wrapper.className = 'awards-entry';
      wrapper.innerHTML = `
        <input type="text" name="award_name[]" placeholder="Name of Award">
        <select name="award_category[]" class="award-category-select">
          <option value="" disabled selected>Select Category</option>
          <option value="Best Performance">Best Performance</option>
          <option value="Excellence">Excellence</option>
        </select>
        <input type="date" name="award_date[]" placeholder="Date Received">
        <div class="upload-section">
          <label>Upload Award Certificate</label>
          <input type="file" name="award_file[]">
        </div>
      `;
      awardContainer.appendChild(wrapper);
    });
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
    var selectedIndustry = document.getElementById('industry').getAttribute('data-selected');
    if (selectedIndustry) {
      document.getElementById('industry').value = selectedIndustry;
    }
    var yearSelect = document.querySelector('select[name="year_of_employment"]');
    if (yearSelect && yearSelect.getAttribute('data-selected')) {
      yearSelect.value = yearSelect.getAttribute('data-selected');
    }
  }, 400);

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
</script>

