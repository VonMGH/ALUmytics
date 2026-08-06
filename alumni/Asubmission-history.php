<?php
include 'includes/Aheader.php';
include 'includes/Asidebar.php';
include 'database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please sign in.'); window.location.href = 'signin.php';</script>";
    exit();
}
$user_id = $_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

// Fetch all submissions with consistent timestamp handling
$submissions = [];

// Employment - use created_at/updated_at as submission timestamp
$res = $conn->query("SELECT id, job_title AS title, start_date AS date, 'employment' AS type,
                     COALESCE(updated_at, created_at) AS submission_timestamp,
                     company_name, industry, mobility, company_address, end_date, employment_status
                     FROM employment WHERE user_id=$user_id");
while ($row = $res->fetch_assoc()) {
    $row['timestamp'] = strtotime($row['submission_timestamp']);
    $row['display_date'] = $row['submission_timestamp'] ? date('F d, Y \a\t g:i A', strtotime($row['submission_timestamp'])) : '';
    $submissions[] = $row;
}

// Certifications - use created_at if available, otherwise certification_date
$res = $conn->query("SELECT id, certification_name AS title, certification_date AS date, 'certification' AS type,
                     COALESCE(created_at, CONCAT(certification_date, ' 00:00:00')) AS submission_timestamp,
                     category, issuing_body, industry, certification_file
                     FROM certifications WHERE user_id=$user_id");
while ($row = $res->fetch_assoc()) {
    $row['timestamp'] = strtotime($row['submission_timestamp']);
    $row['display_date'] = $row['submission_timestamp'] ? date('F d, Y \a\t g:i A', strtotime($row['submission_timestamp'])) : '';
    $submissions[] = $row;
}

// Awards - use created_at if available, otherwise award_date or award_year
$res = $conn->query("SELECT id, award_title AS title, COALESCE(award_date, CONCAT(award_year, '-01-01')) AS date, 'award' AS type,
                     COALESCE(created_at, CONCAT(COALESCE(award_date, CONCAT(award_year, '-01-01')), ' 00:00:00')) AS submission_timestamp,
                     category, awarded_by, award_year, description, award_file
                     FROM awards WHERE user_id=$user_id");
while ($row = $res->fetch_assoc()) {
    $row['timestamp'] = strtotime($row['submission_timestamp']);
    $row['display_date'] = $row['submission_timestamp'] ? date('F d, Y \a\t g:i A', strtotime($row['submission_timestamp'])) : '';
    $submissions[] = $row;
}

// Sort all by timestamp descending
usort($submissions, function($a, $b) { return $b['timestamp'] <=> $a['timestamp']; });
?>
<link rel="stylesheet" href="alumni.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="Acertfication.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="Asubmission-history.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<main class="profile-content submission-history-page">
  <h1 class="profile-heading">Submission History</h1>
  <div class="search-filter-container">
    <select id="filterSelect" aria-label="Filter by submission type">
      <option value="all">All Types</option>
      <option value="employment">Employment</option>
      <option value="certification">Certification</option>
      <option value="award">Award</option>
    </select>
  </div>

  <?php if (empty($submissions)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fas fa-clock-rotate-left"></i></div>
    <h3>No Submissions Yet</h3>
    <p>Your employment, certification, and award submissions will appear here.</p>
  </div>
  <?php else: ?>
  <p class="table-scroll-hint"><i class="fas fa-arrows-left-right"></i> Swipe sideways to see all columns</p>
  <div class="table-scroll-wrap">
    <table id="submissionTable">
      <thead>
        <tr>
          <th>Type</th>
          <th>Title</th>
          <th>Submitted On</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $sub): ?>
          <tr data-type="<?php echo $sub['type']; ?>" class="submission-row <?php echo $sub['type']; ?>-row">
            <td>
              <span class="type-badge <?php echo $sub['type']; ?>-badge">
                <?php echo ucfirst($sub['type']); ?>
              </span>
            </td>
            <td class="submission-title"><?php echo htmlspecialchars($sub['title']); ?></td>
            <td class="submission-date"><?php echo $sub['display_date'] ? $sub['display_date'] : 'Date not available'; ?></td>
            <td><button type="button" class="view-btn" onclick="openSubmissionModal('<?php echo $sub['type']; ?>', <?php echo $sub['id']; ?>)">View Details</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</main>

<!-- Submission Details Modal -->
<div id="submissionModal" class="submission-modal">
  <div class="submission-modal-content">
    <span class="close-btn" id="closeModalBtn" aria-label="Close">&times;</span>
    <h2>Submission Details</h2>
    <div id="modalDetails">Loading...</div>
  </div>
</div>

<!-- File Preview Modal -->
<div id="filePreviewModal" class="image-modal">
  <div class="image-modal-content">
    <span class="image-close-btn" id="closeFileModalBtn" aria-label="Close">&times;</span>
    <h3 id="filePreviewTitle">File Preview</h3>
    <div id="filePreviewContent">Loading preview...</div>
  </div>
</div>

<script>
const filterSelect = document.getElementById("filterSelect");
const tableRows = document.querySelectorAll("#submissionTable tbody tr");

function filterTable() {
  const selectedType = filterSelect.value;
  tableRows.forEach(row => {
    const type = row.getAttribute("data-type");
    const matchesFilter = selectedType === "all" || type === selectedType;
    row.style.display = matchesFilter ? "" : "none";
  });
}

filterSelect.addEventListener("change", filterTable);

// Modal logic
const submissionModal = document.getElementById("submissionModal");
const closeModalBtn = document.getElementById("closeModalBtn");

function openSubmissionModal(type, id) {
  document.getElementById('modalDetails').innerHTML = '<div class="loading">Loading submission details...</div>';
  submissionModal.style.display = "flex";
  document.body.style.overflow = "hidden";
  
  // Fetch submission details via AJAX
  fetch(`get_submission_details.php?type=${type}&id=${id}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displaySubmissionDetails(data.details, type);
      } else {
        document.getElementById('modalDetails').innerHTML = '<div class="error">Error loading submission details.</div>';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      document.getElementById('modalDetails').innerHTML = '<div class="error">Error loading submission details.</div>';
    });
}

function displaySubmissionDetails(details, type) {
  let html = '<div class="submission-details">';
  
  if (type === 'employment') {
    const isIntl = (details.mobility === 'international');

    const street = (details.company_address || details.ca_street || '').trim();
    const barangayCode = (details.company_barangay || details.ca_barangay || '').trim();
    const cityCode = (details.company_city || details.ca_city || '').trim();
    const provinceCode = (details.company_province || details.ca_province || '').trim();
    let country = (details.company_country || '').trim();

    if (!country && !isIntl) {
      country = 'Philippines';
    }

    const addressPartsInitial = [];
    if (street) addressPartsInitial.push(street);
    if (barangayCode) addressPartsInitial.push(barangayCode);
    if (cityCode) addressPartsInitial.push(cityCode);
    if (provinceCode) addressPartsInitial.push(provinceCode);
    if (country) addressPartsInitial.push(country);
    const initialAddressText = addressPartsInitial.length ? addressPartsInitial.join(', ') : 'N/A';

    html += `
      <div class="detail-section">
        <h3>${details.job_title || 'N/A'}</h3>
        <div class="detail-grid">
          <div class="detail-item">
            <strong>Company:</strong> ${details.company_name || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>Industry:</strong> ${details.industry || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>Mobility:</strong> ${details.mobility || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>Start Date:</strong> ${details.start_date || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>End Date:</strong> ${details.end_date || 'Current'}
          </div>
          <div class="detail-item">
            <strong>Status:</strong> ${details.employment_status || 'N/A'}
          </div>
          <div class="detail-item full-width">
            <strong>Company Address:</strong>
            <span class="address-display"
                  data-street="${street}"
                  data-brgy="${barangayCode}"
                  data-city="${cityCode}"
                  data-prov="${provinceCode}"
                  data-country="${country}">
              ${initialAddressText}
            </span>
          </div>
          <div class="detail-item full-width">
            <strong>Submitted On:</strong> ${details.submission_timestamp || 'Not available'}
          </div>
        </div>
      </div>
    `;
  } else if (type === 'certification') {
    html += `
      <div class="detail-section">
        <h3>${details.certification_name || 'N/A'}</h3>
        <div class="detail-grid">
          <div class="detail-item">
            <strong>Category:</strong> ${details.category || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>Issuing Body:</strong> ${details.issuing_body || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>Certification Date:</strong> ${details.certification_date || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>File:</strong> ${details.certification_file ? `<a href="${details.certification_file}" class="file-link" data-file-type="certification">View Certificate</a>` : 'No file uploaded'}
          </div>
          <div class="detail-item full-width">
            <strong>Submitted On:</strong> ${details.submission_timestamp || 'Not available'}
          </div>
        </div>
      </div>
    `;
  } else if (type === 'award') {
    html += `
      <div class="detail-section">
        <h3>${details.award_title || 'N/A'}</h3>
        <div class="detail-grid">
          <div class="detail-item">
            <strong>Category:</strong> ${details.category || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>Awarded By:</strong> ${details.awarded_by || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>Award Date:</strong> ${details.award_date || 'N/A'}
          </div>
          <div class="detail-item">
            <strong>File:</strong> ${details.award_file ? `<a href="${details.award_file}" class="file-link" data-file-type="award">View Award</a>` : 'No file uploaded'}
          </div>
          <div class="detail-item full-width">
            <strong>Submitted On:</strong> ${details.submission_timestamp || 'Not available'}
          </div>
        </div>
      </div>
    `;
  }
  
  html += '</div>';
  document.getElementById('modalDetails').innerHTML = html;

  if (type === 'employment') {
    convertAddressCodesToNamesInContainer(document.getElementById('modalDetails'));
  }
}

const psgcAPI = 'https://psgc.gitlab.io/api';
async function convertAddressCodesToNamesInContainer(container) {
  if (!container) return;
  const displays = container.querySelectorAll('.address-display');
  for (const display of displays) {
    const street = display.dataset.street || '';
    const brgy = display.dataset.brgy || '';
    const city = display.dataset.city || '';
    const prov = display.dataset.prov || '';
    const country = display.dataset.country || '';

    if (!prov || !/^\d+$/.test(prov)) {
      continue;
    }

    try {
      const provResponse = await fetch(`${psgcAPI}/provinces/`);
      const provinces = await provResponse.json();
      const provObj = provinces.find(p => p.code === prov);
      const provName = provObj ? provObj.name : prov;

      let cityName = city;
      let brgyName = brgy;

      if (city && /^\d+$/.test(city) && provObj) {
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

      const parts = [];
      if (street) parts.push(street);
      if (brgyName) parts.push(brgyName);
      if (cityName) parts.push(cityName);
      if (provName) parts.push(provName);
      if (country) parts.push(country);
      display.textContent = parts.filter(Boolean).join(', ');
    } catch (e) {
      console.error('Error converting address codes in submission modal:', e);
    }
  }
}

closeModalBtn.addEventListener("click", () => {
  submissionModal.style.display = "none";
  document.body.style.overflow = "";
});
// Secondary modal logic for file preview
const filePreviewModal = document.getElementById('filePreviewModal');
const closeFileModalBtn = document.getElementById('closeFileModalBtn');
const filePreviewContent = document.getElementById('filePreviewContent');

function openFilePreview(url){
  if (!url) return;
  // normalize slashes and encode spaces
  let norm = url.replace(/\\/g, '/');
  const safeUrl = encodeURI(norm);
  const ext = (safeUrl.split('.').pop() || '').toLowerCase();
  let previewHtml = '';
  if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
    previewHtml = `<img src="${safeUrl}" alt="File">`;
  } else if (ext === 'pdf') {
    previewHtml = `<iframe src="${safeUrl}"></iframe>`;
  } else {
    previewHtml = `<a href="${safeUrl}" target="_blank">Open File in new tab</a>`;
  }
  // Add download button similar to Acertification image modal
  if (ext === 'pdf' || ['jpg','jpeg','png','gif','webp'].includes(ext)) {
    previewHtml += `<div class="file-preview-actions"><a href="${safeUrl}" download class="file-download-btn">Download</a></div>`;
  }
  filePreviewContent.innerHTML = previewHtml;
  filePreviewModal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

// Delegate clicks on file links inside the submission modal
submissionModal.addEventListener('click', function(e){
  const link = e.target.closest('.file-link');
  if (link) {
    e.preventDefault();
    let url = link.getAttribute('href') || '';
    const type = (link.getAttribute('data-file-type') || '').toLowerCase();
    const isHttp = /^https?:\/\//i.test(url);
    if (!isHttp && url && !url.startsWith('uploads/')) {
      if (type === 'certification') url = 'uploads/certification/' + url;
      else if (type === 'award') url = 'uploads/awards/' + url;
    }
    // normalize slashes and encode spaces before preview
    const norm = url.replace(/\\/g, '/');
    openFilePreview(norm);
  }
});

if (closeFileModalBtn) closeFileModalBtn.addEventListener('click', () => {
  filePreviewModal.style.display = 'none';
  if (submissionModal.style.display !== 'flex') document.body.style.overflow = '';
});
window.addEventListener('click', (e) => {
  if (e.target === filePreviewModal) {
    filePreviewModal.style.display = 'none';
    if (submissionModal.style.display !== 'flex') document.body.style.overflow = '';
  }
});
window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    filePreviewModal.style.display = 'none';
    if (submissionModal.style.display !== 'flex') document.body.style.overflow = '';
  }
});
window.addEventListener("click", (e) => {
  if (e.target === submissionModal) {
    submissionModal.style.display = "none";
    document.body.style.overflow = "";
  }
});
</script>
