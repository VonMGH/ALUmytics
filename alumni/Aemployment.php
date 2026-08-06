<?php
include 'database.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please sign in.'); window.location.href = 'signin.php';</script>";
    exit();
}
$user_id = $_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

// Handle add/edit employment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employment_id = $_POST['employment_id'] ?? '';
    // Apply uppercase normalization for text fields
    $job_title = strtoupper(trim($_POST['job_title']));
    $company = strtoupper(trim($_POST['company']));
    $company_name = strtoupper(trim($_POST['company'] ?? ''));
    $industry = strtoupper(trim($_POST['industry']));
    $it_category = $_POST['it_category'] ?? null;
    $mobility = $_POST['mobility'];
    $employment_status = $_POST['employment_status'] ?? null; // employed | unemployed | self_employed | studying
    $job_status = $_POST['job_status'] ?? null; // Permanent | Temporary | Contractual | Job Order/Casual | Self Employed
    $work_arrangement = $_POST['work_arrangement'] ?? null; // On-site | Remote | Hybrid
    $salary_per_month = isset($_POST['salary_per_month']) && $_POST['salary_per_month'] !== '' ? (float)$_POST['salary_per_month'] : null;
    $year_of_employment = isset($_POST['year_of_employment']) && $_POST['year_of_employment'] !== '' ? (int)$_POST['year_of_employment'] : null;
    $company_type = $_POST['company_type'] ?? null; // private|public|ngo_ingo|self_employed|government
    $company_country = strtoupper(trim($_POST['company_country'] ?? ''));
    // Local (PH dropdown) values
    $company_province = strtoupper(trim($_POST['company_province'] ?? ''));
    $company_city = strtoupper(trim($_POST['company_city'] ?? ''));
    $company_barangay = strtoupper(trim($_POST['company_barangay'] ?? ''));
    // International free-text overrides (from separate inputs)
    $company_province_intl = strtoupper(trim($_POST['company_province_intl'] ?? ''));
    $company_city_intl = strtoupper(trim($_POST['company_city_intl'] ?? ''));
    $company_address = strtoupper(trim($_POST['company_address']));
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Normalize address fields based on mobility selection
    if ($mobility === 'international') {
        // For international, prefer free-text province/city if provided
        if ($company_province_intl !== '') {
            $company_province = $company_province_intl;
        }
        if ($company_city_intl !== '') {
            $company_city = $company_city_intl;
        }
        // Barangay not used for international addresses
        $company_barangay = '';
    } else {
        if (empty($company_country)) {
            $company_country = 'Philippines';
        }
    }

    if ($employment_id) {
        // Update existing
        $stmt = $conn->prepare("UPDATE employment SET job_title=?, company=?, company_name=?, industry=?, it_category=?, employment_status=?, job_status=?, mobility=?, work_arrangement=?, salary_per_month=?, year_of_employment=?, company_type=?, company_country=?, company_province=?, company_city=?, company_barangay=?, company_address=?, start_date=?, end_date=? WHERE id=? AND user_id=?");
        // 19 string-like fields + 2 integer IDs
        $stmt->bind_param('sssssssssssssssssssii', $job_title, $company, $company_name, $industry, $it_category, $employment_status, $job_status, $mobility, $work_arrangement, $salary_per_month, $year_of_employment, $company_type, $company_country, $company_province, $company_city, $company_barangay, $company_address, $start_date, $end_date, $employment_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO employment (user_id, job_title, company, company_name, industry, it_category, employment_status, job_status, mobility, work_arrangement, salary_per_month, year_of_employment, company_type, company_country, company_province, company_city, company_barangay, company_address, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssssssssssssssssss', $user_id, $job_title, $company, $company_name, $industry, $it_category, $employment_status, $job_status, $mobility, $work_arrangement, $salary_per_month, $year_of_employment, $company_type, $company_country, $company_province, $company_city, $company_barangay, $company_address, $start_date, $end_date);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: Aemployment.php?saved=1');
    exit();
}
include 'includes/Aheader.php';
include 'includes/Asidebar.php';
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    echo "<script>alert('Saved successfully');</script>";
}
// Fetch all employment records for this user, with fallback address from company_address entered during signup
// Fetch all employment records for this user, with fallback address from company_address entered during signup
$stmt = $conn->prepare("SELECT e.*, ca.company_street_address AS ca_street, ca.company_province AS ca_province, ca.company_city AS ca_city, ca.company_barangay AS ca_barangay FROM employment e LEFT JOIN company_address ca ON ca.user_id = e.user_id WHERE e.user_id = ? ORDER BY e.start_date DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$employment_result = $stmt->get_result();
$employment_records = $employment_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Derive a default employment_status for this user (initially set via UpdateAccount.php)
$default_employment_status = null;
foreach ($employment_records as $er) {
    if (!empty($er['employment_status'])) {
        $default_employment_status = $er['employment_status'];
        break;
    }
}

// Fetch signup company address for prefill in Add modal
$signup_address = [
    'company_street_address' => '',
    'company_province' => '',
    'company_city' => '',
    'company_barangay' => ''
];
$stmt = $conn->prepare("SELECT company_street_address, company_province, company_city, company_barangay FROM company_address WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $signup_address = $row;
}
$stmt->close();$company_names = [];
$result = $conn->query("SELECT DISTINCT TRIM(COALESCE(NULLIF(company,''), NULLIF(company_name,''))) AS name FROM employment WHERE TRIM(COALESCE(NULLIF(company,''), NULLIF(company_name,''))) IS NOT NULL AND TRIM(COALESCE(NULLIF(company,''), NULLIF(company_name,''))) <> '' ORDER BY name ASC");
if ($result) {
    while ($r = $result->fetch_assoc()) {
        if (!empty($r['name'])) { $company_names[] = $r['name']; }
    }
}
?>

<link rel="stylesheet" href="alumni.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="Aemployment.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="Acertfication.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<main class="profile-content employment-page">
    <h1 class="profile-heading">Employment History</h1>

    <?php if (empty($employment_records)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-briefcase"></i></div>
        <h3>No Employment Records Yet</h3>
        <p>Add your current or past employment to keep your alumni profile up to date.</p>
        <button class="add-employment" onclick="openAddModal()">Add Your First Employment</button>
    </div>
    <?php else: ?>
    <div class="certification-list employment-list">
      <?php foreach ($employment_records as $emp): ?>
        <?php
            $isPast = (!empty($emp['end_date']) && strtotime($emp['end_date']) <= strtotime('today'));
            $statusClass = $isPast ? 'past' : 'current';
            $label = $isPast ? 'Past Job' : 'Current Job';
            $contract = isset($emp['job_status']) && $emp['job_status'] !== '' ? ' — ' . htmlspecialchars($emp['job_status']) : '';
            $mobilityLabel = $emp['mobility'] === 'international' ? 'International' : 'Local';
            $street = $emp['company_address'] ?: ($emp['ca_street'] ?? '');
            $isIntl = ($emp['mobility'] === 'international');
            $brgy = $emp['company_barangay'] ?: ($emp['ca_barangay'] ?? '');
            $city = $emp['company_city'] ?: ($emp['ca_city'] ?? '');
            $prov = $emp['company_province'] ?: ($emp['ca_province'] ?? '');
            $country = $emp['company_country'] ?? '';
        ?>
        <div class="certification-entry employment-card">
            <div class="cert-header emp-header">
                <div class="emp-title-block">
                    <h3><?php echo htmlspecialchars($emp['job_title']); ?></h3>
                    <span class="employment-status <?php echo $statusClass; ?>">
                        <?php echo $label . $contract; ?>
                    </span>
                </div>
                <button type="button" class="edit-btn emp-edit-btn" aria-label="Edit employment" onclick='openEditModal(<?php echo json_encode($emp); ?>)'>
                    <i class="fas fa-pen-to-square"></i>
                    <span class="edit-btn-text">Edit</span>
                </button>
            </div>

            <div class="emp-details">
                <div class="emp-meta-grid">
                    <div class="emp-meta-item">
                        <span class="emp-meta-icon" aria-hidden="true"><i class="fas fa-building"></i></span>
                        <div class="emp-meta-text">
                            <span class="emp-label">Company</span>
                            <span class="emp-value"><?php echo htmlspecialchars(!empty($emp['company']) ? $emp['company'] : ($emp['company_name'] ?? '—')); ?></span>
                        </div>
                    </div>
                    <div class="emp-meta-item">
                        <span class="emp-meta-icon" aria-hidden="true"><i class="fas fa-industry"></i></span>
                        <div class="emp-meta-text">
                            <span class="emp-label">Industry</span>
                            <span class="emp-value"><?php echo htmlspecialchars($emp['industry'] ?: '—'); ?></span>
                        </div>
                    </div>
                    <div class="emp-meta-item">
                        <span class="emp-meta-icon" aria-hidden="true"><i class="fas fa-globe"></i></span>
                        <div class="emp-meta-text">
                            <span class="emp-label">Mobility</span>
                            <span class="emp-value"><?php echo htmlspecialchars($mobilityLabel); ?></span>
                        </div>
                    </div>
                    <div class="emp-meta-item">
                        <span class="emp-meta-icon" aria-hidden="true"><i class="fas fa-house-laptop"></i></span>
                        <div class="emp-meta-text">
                            <span class="emp-label">Work Arrangement</span>
                            <span class="emp-value"><?php echo htmlspecialchars($emp['work_arrangement'] ?? '—'); ?></span>
                        </div>
                    </div>
                    <div class="emp-meta-row">
                        <div class="emp-meta-item emp-meta-address">
                            <span class="emp-meta-icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
                            <div class="emp-meta-text">
                                <span class="emp-label">Company Address</span>
                                <span class="emp-value address-display"
                                      data-street="<?php echo htmlspecialchars($street); ?>"
                                      data-brgy="<?php echo htmlspecialchars($brgy); ?>"
                                      data-city="<?php echo htmlspecialchars($city); ?>"
                                      data-prov="<?php echo htmlspecialchars($prov); ?>"
                                      data-country="<?php echo htmlspecialchars($country); ?>">
                                    <?php if ($isIntl): ?>
                                        <?php if (!empty($city)): ?><?php echo htmlspecialchars($city); ?><?php endif; ?>
                                        <?php if (!empty($prov)): ?><?php echo !empty($city) ? ', ' : ''; ?><?php echo htmlspecialchars($prov); ?><?php endif; ?>
                                        <?php if (!empty($country)): ?><?php echo (!empty($city) || !empty($prov)) ? ', ' : ''; ?><?php echo htmlspecialchars($country); ?><?php endif; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($street); ?>
                                        <?php if (!empty($brgy)): ?>, <?php echo htmlspecialchars($brgy); ?><?php endif; ?>
                                        <?php if (!empty($city)): ?>, <?php echo htmlspecialchars($city); ?><?php endif; ?>
                                        <?php if (!empty($prov)): ?>, <?php echo htmlspecialchars($prov); ?><?php endif; ?>
                                        <?php if (!empty($country)): ?>, <?php echo htmlspecialchars($country); ?><?php endif; ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="emp-meta-item">
                            <span class="emp-meta-icon" aria-hidden="true"><i class="fas fa-calendar-plus"></i></span>
                            <div class="emp-meta-text">
                                <span class="emp-label">Start Date</span>
                                <span class="emp-value"><?php echo !empty($emp['start_date']) ? htmlspecialchars($emp['start_date']) : '—'; ?></span>
                            </div>
                        </div>
                        <div class="emp-meta-item">
                            <span class="emp-meta-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                            <div class="emp-meta-text">
                                <span class="emp-label">End Date</span>
                                <span class="emp-value"><?php echo !empty($emp['end_date']) ? htmlspecialchars($emp['end_date']) : 'Present'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="emp-add-wrap">
        <button class="add-employment" onclick="openAddModal()">Add Employment</button>
    </div>
    <?php endif; ?>
</main>

<!-- Modal outside main so overflow-x:hidden on .profile-content does not clip it -->
<div id="employmentModal" class="employment-modal">
        <div class="employment-modal-content">
            <span class="close-btn" onclick="closeModal()" aria-label="Close">&times;</span>
            <h2 id="modalTitle">Add Employment</h2>
            <form id="employmentForm" method="post">
                <input type="hidden" id="employment_id" name="employment_id">
                <label for="job_title">Job Title</label>
                <input type="text" id="job_title" name="job_title" required>
                <label for="company">Company</label>
                <input type="text" id="company" name="company" required>
                <label for="employment_status">Employment Status</label>
                <select id="employment_status" name="employment_status" required>
                  <option value="" disabled selected>Select Employment Status</option>
                  <option value="employed">Employed</option>
                  <option value="unemployed">Unemployed</option>
                  <option value="self_employed">Self-employed</option>
                </select>
                <label for="industry">Industry</label>
                <select id="industry" name="industry" required>
                  <option value="" disabled selected>Select Industry</option>
                </select>
                <label for="it_category">IT / Non-IT Classification</label>
                <select id="it_category" name="it_category">
                  <option value="" disabled selected>Select Classification</option>
                  <option value="IT">IT-related</option>
                  <option value="NON_IT">Non-IT-related</option>
                </select>
                <label for="job_status">Job Status</label>
                <select id="job_status" name="job_status">
                  <option value="" disabled selected>Select Job Status</option>
                  <option value="Permanent">Permanent</option>
                  <option value="Temporary">Temporary</option>
                  <option value="Contractual">Contractual</option>
                  <option value="Job Order/Casual">Job Order/Casual</option>
                  <option value="Self Employed">Self Employed</option>
                </select>
                <label for="mobility">Mobility</label>
                <select id="mobility" name="mobility" required onchange="toggleAddressFields()">
                  <option value="" disabled selected>Select Mobility</option>
                  <option value="local">Local</option>
                  <option value="international">International</option>
                </select>
                <label for="work_arrangement">Work Arrangement</label>
                <select id="work_arrangement" name="work_arrangement" required>
                  <option value="" disabled selected>Select Work Arrangement</option>
                  <option value="On-site">On-site</option>
                  <option value="Remote">Remote</option>
                  <option value="Hybrid">Hybrid</option>
                </select>
                <label for="salary_per_month">Salary per Month (Optional)</label>
                <input type="text" id="salary_per_month" name="salary_per_month" placeholder="Salary per Month (Optional)">
                <label for="year_of_employment">Year of Employment</label>
                <select id="year_of_employment" name="year_of_employment">
                  <option value="" disabled selected>Year of Employment</option>
                  <?php $currentYear = date('Y'); for ($y=$currentYear; $y>=1950; $y--) { echo '<option value="'.$y.'">'.$y.'</option>'; } ?>
                </select>
                <div class="company-type-options">
                  <label>Type of Company:</label>
                  <label><input type="radio" name="company_type" value="private"> Private</label>
                  <label><input type="radio" name="company_type" value="public"> Public</label>
                  <label><input type="radio" name="company_type" value="ngo_ingo"> NGO/INGO</label>
                  <label><input type="radio" name="company_type" value="self_employed"> Self Employed</label>
                  <label><input type="radio" name="company_type" value="government"> Government</label>
                </div>
                <div id="international-fields" style="display: none;">
                    <label for="company_country">Company Country</label>
                    <select id="company_country" name="company_country">
                        <option value="" disabled selected>Select Country</option>
                        <option value="Singapore">Singapore</option>
                        <option value="United States">United States</option>
                        <option value="Canada">Canada</option>
                        <option value="Australia">Australia</option>
                        <option value="United Kingdom">United Kingdom</option>
                        <option value="Japan">Japan</option>
                        <option value="South Korea">South Korea</option>
                        <option value="Hong Kong">Hong Kong</option>
                        <option value="Malaysia">Malaysia</option>
                        <option value="Thailand">Thailand</option>
                        <option value="Vietnam">Vietnam</option>
                        <option value="Indonesia">Indonesia</option>
                        <option value="Germany">Germany</option>
                        <option value="France">France</option>
                        <option value="Netherlands">Netherlands</option>
                        <option value="Switzerland">Switzerland</option>
                        <option value="New Zealand">New Zealand</option>
                        <option value="UAE">United Arab Emirates</option>
                        <option value="Qatar">Qatar</option>
                        <option value="Other">Other</option>
                    </select>
                    <label for="international_province">State/Province/Region</label>
                    <input type="text" id="international_province" name="company_province_intl" placeholder="Enter state/province/region">
                    <label for="international_city">City</label>
                    <input type="text" id="international_city" name="company_city_intl" placeholder="Enter city">
                </div>
                <div id="local-fields">
                    <label for="company_province">Company Province</label>
                    <select id="company_province" name="company_province">
                      <option value="" disabled selected>Select Province</option>
                    </select>
                    <label for="company_city">Company City/Municipality</label>
                    <select id="company_city" name="company_city">
                      <option value="" disabled selected>Select City/Municipality</option>
                    </select>
                    <label for="company_barangay">Company Barangay</label>
                    <select id="company_barangay" name="company_barangay">
                      <option value="" disabled selected>Select Barangay</option>
                    </select>
                </div>
                <label for="company_address">Company Address (Street)</label>
                <input type="text" id="company_address" name="company_address">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" required>
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date">
                <div class="form-footer emp-form-footer">
                    <button type="submit" class="save-button">Save</button>
                    <span id="status-display" class="employment-status current emp-status-pill">Current Job</span>
                </div>
            </form>
        </div>
    </div>

<script src="js/address-dropdown.js"></script>
<script>
const signupCompanyAddress = <?php echo json_encode($signup_address); ?>;
const defaultEmploymentStatus = <?php echo json_encode($default_employment_status); ?>;
// Industry dropdown options
const industries = [
    "Agriculture",
    "Banking and Finance",
    "Construction",
    "Education",
    "Energy and Utilities",
    "Entertainment",
    "Government",
    "Healthcare",
    "Hospitality",
    "Information Technology",
    "Manufacturing",
    "Marketing and Advertising",
    "Mining",
    "Non-Profit",
    "Pharmaceuticals",
    "Real Estate",
    "Retail",
    "Telecommunications",
    "Transportation and Logistics",
    "Other"
];
function populateIndustryDropdown() {
    const select = document.getElementById('industry');
    select.innerHTML = '<option value="" disabled selected>Select Industry</option>';
    industries.forEach(ind => {
        const opt = document.createElement('option');
        opt.value = ind;
        opt.textContent = ind;
        select.appendChild(opt);
    });
}
// Modal logic
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Employment';
    document.getElementById('employmentForm').reset();
    document.getElementById('employment_id').value = '';
    populateIndustryDropdown();
    var itc = document.getElementById('it_category'); if (itc) itc.value = '';
    var es = document.getElementById('employment_status');
    if (es) {
        es.value = defaultEmploymentStatus || '';
    }
    // Ensure Mobility and Work Arrangement have no default selection
    var wa = document.getElementById('work_arrangement');
    if (wa) wa.value = '';

    var mob = document.getElementById('mobility');
    if (mob) mob.value = '';

    // Clear all company address-related fields so old employment address is not reused
    var companyAddressEl = document.getElementById('company_address');
    if (companyAddressEl) companyAddressEl.value = '';

    var companyCountryEl = document.getElementById('company_country');
    if (companyCountryEl) {
        companyCountryEl.value = '';
        companyCountryEl.removeAttribute('data-selected');
    }

    ['company_province','company_city','company_barangay'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.removeAttribute('data-selected');
            el.removeAttribute('data-selected-name');
            // Reset options to default placeholder
            if (id === 'company_province') {
                el.innerHTML = '<option value="" disabled selected>Select Province</option>';
            } else if (id === 'company_city') {
                el.innerHTML = '<option value="" disabled selected>Select City/Municipality</option>';
            } else if (id === 'company_barangay') {
                el.innerHTML = '<option value="" disabled selected>Select Barangay</option>';
            }
            el.value = '';
        }
    });

    // Clear international address text fields
    var intlProv = document.getElementById('international_province');
    if (intlProv) intlProv.value = '';
    var intlCity = document.getElementById('international_city');
    if (intlCity) intlCity.value = '';

    // Apply default local/international visibility and required flags
    if (typeof toggleAddressFields === 'function') {
        toggleAddressFields();
    }

    document.getElementById('employmentModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    updateJobStatus();
}
function openEditModal(emp) {
    document.getElementById('modalTitle').textContent = 'Edit Employment';
    document.getElementById('employment_id').value = emp.id;
    document.getElementById('job_title').value = emp.job_title;
    document.getElementById('company').value = (emp.company && emp.company.trim()) ? emp.company : (emp.company_name || '');
    populateIndustryDropdown();
    // Try to select saved industry in dropdown, even if it doesn't exactly match predefined options
    (function () {
        const select = document.getElementById('industry');
        if (!select) return;

        const saved = (emp.industry || '').toString().trim();
        if (!saved) return;

        // First try exact match on value
        let matched = false;
        for (const opt of select.options) {
            if (opt.value === saved) {
                opt.selected = true;
                matched = true;
                break;
            }
        }

        // Then try case-insensitive match on value or text
        if (!matched) {
            const lowerSaved = saved.toLowerCase();
            for (const opt of select.options) {
                if (opt.value.toLowerCase() === lowerSaved || opt.text.toLowerCase() === lowerSaved) {
                    opt.selected = true;
                    matched = true;
                    break;
                }
            }
        }

        // If still no match, append a new option for the saved value and select it
        if (!matched) {
            const pretty = saved.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
            const opt = document.createElement('option');
            opt.value = saved;
            opt.textContent = pretty || saved;
            opt.selected = true;
            select.appendChild(opt);
        }
    })();
    var itc = document.getElementById('it_category'); if (itc) itc.value = emp.it_category || '';
    var es = document.getElementById('employment_status'); if (es) es.value = emp.employment_status || '';
    var js = document.getElementById('job_status'); if (js) js.value = emp.job_status || '';
    document.getElementById('mobility').value = emp.mobility;
    var wa = document.getElementById('work_arrangement');
    if (wa) wa.value = emp.work_arrangement || '';
    var sal = document.getElementById('salary_per_month'); if (sal) sal.value = emp.salary_per_month || '';
    var yoe = document.getElementById('year_of_employment'); if (yoe) yoe.value = emp.year_of_employment || '';
    if (emp.company_type) {
        var ct = document.querySelector('input[name="company_type"][value="'+emp.company_type+'"]');
        if (ct) ct.checked = true;
    }
    toggleAddressFields();
    
    // For international, do not fallback to signup company_address to avoid showing PH PSGC
    let province, city, barangay;
    if (emp.mobility === 'international') {
        province = emp.company_province || '';
        city = emp.company_city || '';
        barangay = emp.company_barangay || '';
    } else {
        // Use fallback values from company_address table if employment fields are empty
        province = emp.company_province || emp.ca_province || '';
        city = emp.company_city || emp.ca_city || '';
        barangay = emp.company_barangay || emp.ca_barangay || '';
    }
    const street = emp.company_address || emp.ca_street || '';

    // Country handling
    const country = emp.company_country || '';
    const countryEl = document.getElementById('company_country');
    if (countryEl) {
        // Default Philippines only for local with no country stored
        countryEl.setAttribute('data-selected', (emp.mobility === 'local' && !country) ? 'Philippines' : (country || ''));
        if (emp.mobility === 'international') {
            // Remove any default and select the saved country by value or label
            countryEl.removeAttribute('data-selected');
            let matched = false;
            for (const opt of countryEl.options) {
                if (opt.value === country || opt.text === country) {
                    opt.selected = true;
                    matched = true;
                    break;
                }
            }
            if (!matched && country) {
                const opt = document.createElement('option');
                opt.value = country;
                opt.text = country;
                opt.selected = true;
                countryEl.appendChild(opt);
            }
        }
    }

    if (emp.mobility === 'international') {
        // free-text fields for international
        // If old local PSGC codes are present, drop them in the edit UI
        const intlProv = (/^\d+$/.test(province || '')) ? '' : (province || '');
        const intlCity = (/^\d+$/.test(city || '')) ? '' : (city || '');
        document.getElementById('international_province').value = intlProv;
        document.getElementById('international_city').value = intlCity;
        // Also clear local dropdowns so they don't show stale codes
        ['company_province','company_city','company_barangay'].forEach(function(id){
          var el = document.getElementById(id);
          if (el) {
            el.removeAttribute('data-selected');
            el.removeAttribute('data-selected-name');
            el.innerHTML = '<option value="" disabled selected>' + (id==='company_province'?'Select Province': id==='company_city'?'Select City/Municipality':'Select Barangay') + '</option>';
            el.value = '';
          }
        });
    } else {
        // Local: set data-selected (codes) or data-selected-name (names) and let address-dropdown.js do the rest
        const provEl = document.getElementById('company_province');
        const cityEl = document.getElementById('company_city');
        const brgyEl = document.getElementById('company_barangay');

        if (provEl) {
            if (province && /^\d+$/.test(province)) provEl.setAttribute('data-selected', province);
            else if (province) provEl.setAttribute('data-selected-name', province);
        }
        if (cityEl) {
            if (city && /^\d+$/.test(city)) cityEl.setAttribute('data-selected', city);
            else if (city) cityEl.setAttribute('data-selected-name', city);
        }
        if (brgyEl) {
            if (barangay && /^\d+$/.test(barangay)) brgyEl.setAttribute('data-selected', barangay);
            else if (barangay) brgyEl.setAttribute('data-selected-name', barangay);
        }

        // Make sure Philippines is selected so the shared script loads PSGC data
        if (countryEl) {
            try {
                countryEl.value = 'Philippines';
                countryEl.dispatchEvent(new Event('change'));
            } catch (e) {}
        }
    }

    document.getElementById('company_address').value = street;
    document.getElementById('start_date').value = emp.start_date;
    document.getElementById('end_date').value = emp.end_date;
    document.getElementById('employmentModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    updateJobStatus();
}
function closeModal() {
    document.getElementById('employmentModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Toggle address fields based on mobility selection
function toggleAddressFields() {
    const mobility = document.getElementById('mobility').value;
    const localFields = document.getElementById('local-fields');
    const internationalFields = document.getElementById('international-fields');
    const companyAddressEl = document.getElementById('company_address');
    
    if (mobility === 'international') {
        localFields.style.display = 'none';
        internationalFields.style.display = 'block';
        // Make international fields required and local fields optional
        document.getElementById('company_country').required = true;
        document.getElementById('international_province').required = false;
        document.getElementById('international_city').required = false;
        document.getElementById('company_province').required = false;
        document.getElementById('company_city').required = false;
        document.getElementById('company_barangay').required = false;
        // Street address is optional for international employment
        if (companyAddressEl) companyAddressEl.required = false;
        // Clear local dropdown values so old values don't persist on submit
        ['company_province','company_city','company_barangay'].forEach(function(id){
          var el = document.getElementById(id);
          if (el) {
            el.removeAttribute('data-selected');
            el.removeAttribute('data-selected-name');
            el.innerHTML = '<option value="" disabled selected>' + (id==='company_province'?'Select Province': id==='company_city'?'Select City/Municipality':'Select Barangay') + '</option>';
            el.value = '';
          }
        });
    } else {
        localFields.style.display = 'block';
        internationalFields.style.display = 'none';
        // Make local fields required and international fields optional
        document.getElementById('company_country').required = false;
        document.getElementById('international_province').required = false;
        document.getElementById('international_city').required = false;
        document.getElementById('company_province').required = true;
        document.getElementById('company_city').required = true;
        document.getElementById('company_barangay').required = true;
        // Street address is required for local employment
        if (companyAddressEl) companyAddressEl.required = true;

        // Ensure country is Philippines so address-dropdown.js enables PSGC dropdowns
        const companyCountryEl = document.getElementById('company_country');
        if (companyCountryEl) {
            // If not yet populated by address-dropdown.js, set attribute so it picks up on init
            companyCountryEl.setAttribute('data-selected', 'Philippines');
            // If already populated, set value and trigger change
            try {
                companyCountryEl.value = 'Philippines';
                companyCountryEl.dispatchEvent(new Event('change'));
            } catch (e) {}
        }
    }
}

// Update job status indicator based on end date
function updateJobStatus() {
    const endDateEl = document.getElementById('end_date');
    const statusDisplay = document.getElementById('status-display');
    if (!endDateEl || !statusDisplay) return;
    const endDate = endDateEl.value;
    let isPast = false;
    if (endDate) {
        const end = new Date(endDate);
        const today = new Date();
        // Normalize to date-only comparison
        end.setHours(0,0,0,0);
        today.setHours(0,0,0,0);
        isPast = end.getTime() <= today.getTime();
    }
    statusDisplay.textContent = isPast ? 'Past Job' : 'Current Job';
    statusDisplay.className = 'employment-status emp-status-pill ' + (isPast ? 'past' : 'current');
}
document.addEventListener('DOMContentLoaded', function() {
    populateIndustryDropdown();
    // Hook date change to update status indicator
    var sd = document.getElementById('start_date');
    var ed = document.getElementById('end_date');
    if (sd) sd.addEventListener('change', updateJobStatus);
    if (ed) ed.addEventListener('change', updateJobStatus);
    // Ensure Local mode uses PSGC dropdowns by default via shared script
    // Also convert codes to names in the list view for existing records
    const psgcAPI = 'https://psgc.gitlab.io/api';
    async function convertAddressCodesToNames() {
        const addressDisplays = document.querySelectorAll('.address-display');
        for (const display of addressDisplays) {
            const street = display.dataset.street;
            const brgy = display.dataset.brgy;
            const city = display.dataset.city;
            const prov = display.dataset.prov;
            const country = display.dataset.country;
            if (prov && /^\d+$/.test(prov)) {
                try {
                    const provResponse = await fetch(`${psgcAPI}/provinces/`);
                    const provinces = await provResponse.json();
                    const province = provinces.find(p => p.code === prov);
                    const provName = province ? province.name : prov;
                    let cityName = city;
                    let brgyName = brgy;
                    if (city && /^\d+$/.test(city) && province) {
                        const cityResponse = await fetch(`${psgcAPI}/provinces/${prov}/cities-municipalities/`);
                        const cities = await cityResponse.json();
                        const cityObj = cities.find(c => c.code === city);
                        cityName = cityObj ? cityObj.name : city;
                        if (brgy && /^\d+$/.test(brgy) && cityObj) {
                            const brgyResponse = await fetch(`${psgcAPI}/cities-municipalities/${city}/barangays/`);
                            const barangays = await brgyResponse.json();
                            const brgyObj = barangays.find(b => b.code === brgy);
                            brgyName = brgyObj ? brgyObj.name : brgy;
                        }
                    }
                    let addressParts = [street];
                    if (brgyName) addressParts.push(brgyName);
                    if (cityName) addressParts.push(cityName);
                    if (provName) addressParts.push(provName);
                    if (country) addressParts.push(country);
                    display.textContent = addressParts.filter(p => p).join(', ');
                } catch (error) {
                    console.error('Error converting address codes:', error);
                }
            }
        }
    }
    convertAddressCodesToNames();
});
</script>

<script>
// Auto-uppercase typing for key text fields in the employment modal
function toUpperField(str) {
    return (str || '').toString().toUpperCase();
}

['job_title','company','company_address','international_province','international_city'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', function() {
            var cursorPos = el.selectionStart;
            el.value = toUpperField(el.value);
            if (typeof cursorPos === 'number') {
                el.selectionStart = el.selectionEnd = cursorPos;
            }
        });
    }
});
</script>
