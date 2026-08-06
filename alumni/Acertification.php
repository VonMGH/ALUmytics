<?php
include 'database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please sign in.'); window.location.href = 'signin.php';</script>";
    exit();
}
$user_id = $_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

// Handle add/edit certification POST BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $cert_id = $_POST['cert_id'] ?? '';
    // Normalize text inputs
    $cert_name = strtoupper(trim($_POST['cert_name']));
    $cert_category = trim($_POST['cert_category']);
    $cert_issuer = strtoupper(trim($_POST['cert_issuer']));
    $cert_date = $_POST['cert_date'];
    
    // Handle custom category if "Other" is selected
    if (strtoupper($cert_category) === 'OTHER' && !empty($_POST['custom_category'])) {
        $cert_category = strtoupper(trim($_POST['custom_category']));
    }
    
    $file_path = '';
    if (isset($_FILES['cert_file']) && $_FILES['cert_file']['error'] == 0) {
        $upload_dir = 'uploads/certification/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_tmp = $_FILES['cert_file']['tmp_name'];
        $file_name = basename($_FILES['cert_file']['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['pdf','jpg','jpeg','png'];
        if (in_array($ext, $allowed)) {
            $file_path = $upload_dir . uniqid('cert_') . '_' . $file_name;
            move_uploaded_file($file_tmp, $file_path);
        }
    } else if (!empty($_POST['existing_file'])) {
        $file_path = $_POST['existing_file'];
    }

    // Enforce that a certification file is mandatory
    if ($_POST['action'] === 'add' && empty($file_path)) {
        echo "<script>alert('Please upload a certification file (PDF or image).'); window.history.back();</script>";
        exit();
    }
    if ($_POST['action'] === 'edit' && empty($file_path)) {
        echo "<script>alert('Please upload a certification file (PDF or image).'); window.history.back();</script>";
        exit();
    }
    if ($_POST['action'] === 'add') {
        $stmt = $conn->prepare("INSERT INTO certifications (user_id, certification_name, category, issuing_body, certification_date, certification_file) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssss', $user_id, $cert_name, $cert_category, $cert_issuer, $cert_date, $file_path);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['action'] === 'edit' && $cert_id) {
        $stmt = $conn->prepare("UPDATE certifications SET certification_name=?, category=?, issuing_body=?, certification_date=?, certification_file=? WHERE id=? AND user_id=?");
        $stmt->bind_param('ssssssi', $cert_name, $cert_category, $cert_issuer, $cert_date, $file_path, $cert_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: Acertification.php');
    exit();
}

// Handle delete BEFORE any HTML output
if (isset($_GET['delete'])) {
    $cert_id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT certification_file FROM certifications WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $cert_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $file = $row['certification_file'] ?? null;
    if ($file && file_exists($file)) unlink($file);
    $stmt = $conn->prepare("DELETE FROM certifications WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $cert_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header('Location: Acertification.php');
    exit();
}

// Fetch all certifications for this user
$stmt = $conn->prepare("SELECT * FROM certifications WHERE user_id = ? ORDER BY certification_date DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$certs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$categories = [
    "Technology", "Healthcare", "Business", "Education", "Engineering", "Finance", "Science", "Arts", "Other"
];
include 'includes/Aheader.php';
include 'includes/Asidebar.php';
?>
<link rel="stylesheet" href="alumni.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="Aemployment.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="Acertfication.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<main class="profile-content certification-page">
  <h1 class="profile-heading">Certifications</h1>

  <?php if (empty($certs)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fas fa-certificate"></i></div>
      <h3>No Certifications Yet</h3>
      <p>Start building your certifications portfolio by adding your first certification.</p>
      <button class="add-certification" id="openCertModal">Add Your First Certification</button>
    </div>
  <?php else: ?>
    <div class="certification-list">
      <?php foreach ($certs as $cert): ?>
        <?php
            $certExt = !empty($cert['certification_file'])
                ? strtolower(pathinfo($cert['certification_file'], PATHINFO_EXTENSION))
                : '';
            $certFileLabel = '—';
            if (in_array($certExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $certFileLabel = 'Image Certificate';
            } elseif ($certExt === 'pdf') {
                $certFileLabel = 'PDF Document';
            } elseif ($certExt !== '') {
                $certFileLabel = strtoupper($certExt) . ' File';
            }
            $certFormattedDate = !empty($cert['certification_date'])
                ? date('F j, Y', strtotime($cert['certification_date']))
                : '—';
        ?>
        <div class="certification-entry cert-card">
          <div class="cert-header">
            <div class="cert-title-block">
              <h3><?php echo htmlspecialchars($cert['certification_name']); ?></h3>
              <span class="cert-category-badge"><?php echo htmlspecialchars($cert['category']); ?></span>
            </div>
            <div class="cert-actions">
              <button type="button" class="cert-edit-btn" onclick='openEditModal(<?php echo json_encode($cert); ?>)' aria-label="Edit certification">
                <i class="fas fa-pen-to-square"></i>
                <span class="cert-btn-text">Edit</span>
              </button>
              <a href="Acertification.php?delete=<?php echo $cert['id']; ?>" class="cert-delete-btn" onclick="return confirm('Delete this certification?')" aria-label="Delete certification">
                <i class="fas fa-trash"></i>
                <span class="cert-btn-text">Delete</span>
              </a>
            </div>
          </div>

          <div class="cert-details">
            <div class="cert-detail-bar">
              <span class="cert-detail-accent cert-detail-accent-left" aria-hidden="true"></span>
              <div class="cert-detail-inline">
                <div class="cert-detail-pair">
                  <span class="cert-label">Issuing Body</span>
                  <span class="cert-value"><?php echo htmlspecialchars($cert['issuing_body']); ?></span>
                </div>
                <span class="cert-detail-divider" aria-hidden="true"></span>
                <div class="cert-detail-pair">
                  <span class="cert-label">Date Issued</span>
                  <span class="cert-value"><?php echo htmlspecialchars($cert['certification_date']); ?></span>
                </div>
              </div>
              <span class="cert-detail-accent cert-detail-accent-right" aria-hidden="true"></span>
            </div>

            <div class="cert-solo-meta">
              <div class="cert-solo-item">
                <span class="cert-solo-icon"><i class="fas fa-tag"></i></span>
                <div class="cert-solo-text">
                  <span class="cert-label">Category</span>
                  <span class="cert-value"><?php echo htmlspecialchars($cert['category']); ?></span>
                </div>
              </div>
              <?php if (!empty($cert['industry'])): ?>
              <div class="cert-solo-item">
                <span class="cert-solo-icon"><i class="fas fa-industry"></i></span>
                <div class="cert-solo-text">
                  <span class="cert-label">Industry</span>
                  <span class="cert-value"><?php echo htmlspecialchars($cert['industry']); ?></span>
                </div>
              </div>
              <?php endif; ?>
              <div class="cert-solo-item">
                <span class="cert-solo-icon"><i class="fas fa-calendar-check"></i></span>
                <div class="cert-solo-text">
                  <span class="cert-label">Issued On</span>
                  <span class="cert-value"><?php echo htmlspecialchars($certFormattedDate); ?></span>
                </div>
              </div>
              <?php if (!empty($cert['certification_file'])): ?>
              <div class="cert-solo-item">
                <span class="cert-solo-icon"><i class="fas fa-file-alt"></i></span>
                <div class="cert-solo-text">
                  <span class="cert-label">Attachment</span>
                  <span class="cert-value"><?php echo htmlspecialchars($certFileLabel); ?></span>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($cert['certification_file']): ?>
            <?php if (in_array($certExt, ['jpg','jpeg','png'])): ?>
              <div class="cert-image-container">
                <img src="<?php echo htmlspecialchars($cert['certification_file']); ?>"
                     alt="Certificate Image"
                     class="cert-image"
                     onclick="openImageModal('<?php echo htmlspecialchars($cert['certification_file'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cert['certification_name'], ENT_QUOTES); ?>')" />
                <div class="cert-image-overlay">
                  <div class="cert-image-actions">
                    <button type="button" class="view-btn" onclick="openImageModal('<?php echo htmlspecialchars($cert['certification_file'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cert['certification_name'], ENT_QUOTES); ?>')">
                      <i class="fas fa-eye"></i> View
                    </button>
                    <span class="cert-action-divider" aria-hidden="true"></span>
                    <a href="<?php echo htmlspecialchars($cert['certification_file']); ?>" download class="download-btn">
                      <i class="fas fa-download"></i> Download
                    </a>
                  </div>
                </div>
              </div>
            <?php elseif ($certExt === 'pdf'): ?>
              <div class="cert-pdf-container">
                <div class="pdf-icon">
                  <i class="fas fa-file-pdf"></i>
                </div>
                <div class="pdf-actions">
                  <a href="<?php echo htmlspecialchars($cert['certification_file']); ?>" class="pdf-link" target="_blank">
                    <i class="fas fa-eye"></i> View PDF
                  </a>
                  <a href="<?php echo htmlspecialchars($cert['certification_file']); ?>" class="pdf-link" download>
                    <i class="fas fa-download"></i> Download PDF
                  </a>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="cert-add-wrap">
      <button class="add-certification" id="openCertModal">Add Certification</button>
    </div>
  <?php endif; ?>
</main>

<!-- Image Modal for enlarged view -->
<div id="imageModal" class="image-modal">
  <div class="image-modal-content">
    <span class="image-close-btn" onclick="closeImageModal()">&times;</span>
    <h3 id="imageModalTitle"></h3>
    <img id="modalImage" src="" alt="Certificate" />
  </div>
</div>

<!-- Certification Modal (outside main to avoid overflow clipping) -->
<div id="certModal" class="certification-modal">
  <div class="certification-modal-content">
    <span class="close-btn" id="closeCertModal" aria-label="Close">&times;</span>
    <h2 id="certModalTitle">Add Certification</h2>
    <form id="certForm" action="Acertification.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="cert_id" id="cert_id">
      <input type="hidden" name="existing_file" id="existing_file">
      <input type="hidden" name="action" id="cert_action" value="add">

      <label for="cert-name">Name of Certification *</label>
      <input type="text" id="cert-name" name="cert_name" required>

      <label for="cert-category">Category *</label>
      <select id="cert-category" name="cert_category" required onchange="toggleCustomCategory()">
        <option value="" disabled selected>Select Category</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
        <?php endforeach; ?>
      </select>

      <div id="custom-category-container" class="custom-category-container" style="display: none;">
        <label for="custom-category">Specify Category *</label>
        <input type="text" id="custom-category" name="custom_category" placeholder="Enter your custom category">
      </div>

      <label for="cert-issuer">Issuing Body *</label>
      <input type="text" id="cert-issuer" name="cert_issuer" required>

      <label for="cert-date">Date Issued *</label>
      <input type="date" id="cert-date" name="cert_date" required>

      <label for="cert-file">Upload Certification (PDF/Image) *</label>
      <input type="file" id="cert-file" name="cert_file" accept=".pdf,image/*" required>
      <div id="cert-file-name" class="cert-file-name"></div>
      <div id="cert-file-preview" class="cert-file-preview"></div>

      <div class="cert-form-footer">
        <button type="submit" class="save-button">Save Certification</button>
      </div>
    </form>
  </div>
</div>

<script>
const openCertBtn = document.getElementById("openCertModal");
const closeCertBtn = document.getElementById("closeCertModal");
const certModal = document.getElementById("certModal");
const certForm = document.getElementById("certForm");
const certModalTitle = document.getElementById("certModalTitle");

function openCertModalDisplay() {
  certModal.style.display = "flex";
  document.body.style.overflow = "hidden";
}

function closeCertModalDisplay() {
  certModal.style.display = "none";
  document.body.style.overflow = "";
}

// Function to toggle custom category input
function toggleCustomCategory() {
  const categorySelect = document.getElementById('cert-category');
  const customContainer = document.getElementById('custom-category-container');
  const customInput = document.getElementById('custom-category');
  
  if (categorySelect.value === 'Other') {
    customContainer.style.display = 'block';
    customInput.required = true;
  } else {
    customContainer.style.display = 'none';
    customInput.required = false;
    customInput.value = '';
  }
}

// Image modal functionality
function openImageModal(imageSrc, title) {
  const imageModal = document.getElementById("imageModal");
  const modalImage = document.getElementById("modalImage");
  const imageModalTitle = document.getElementById("imageModalTitle");
  
  modalImage.src = imageSrc;
  imageModalTitle.textContent = title;
  imageModal.style.display = "flex";
  document.body.style.overflow = "hidden";
}

function closeImageModal() {
  const imageModal = document.getElementById("imageModal");
  imageModal.style.display = "none";
  document.body.style.overflow = "";
}

// Close image modal when clicking outside
window.onclick = function(event) {
  const imageModal = document.getElementById("imageModal");
  const certModal = document.getElementById("certModal");
  
  if (event.target === imageModal) {
    closeImageModal();
  }
  if (event.target === certModal) {
    closeCertModalDisplay();
  }
}

if (openCertBtn) {
openCertBtn.onclick = () => {
  certModalTitle.textContent = 'Add Certification';
  certForm.reset();
  document.getElementById('cert_action').value = 'add';
  document.getElementById('cert_id').value = '';
  document.getElementById('existing_file').value = '';
  document.getElementById('custom-category-container').style.display = 'none';
  document.getElementById('custom-category').required = false;
  var fileInput = document.getElementById('cert-file');
  if (fileInput) {
    fileInput.required = true;
    fileInput.value = '';
  }
  var preview = document.getElementById('cert-file-preview');
  if (preview) {
    preview.innerHTML = '';
  }
  var fileNameEl = document.getElementById('cert-file-name');
  if (fileNameEl) {
    fileNameEl.textContent = '';
  }
  openCertModalDisplay();
};
}

if (closeCertBtn) {
closeCertBtn.onclick = () => closeCertModalDisplay();
}

function openEditModal(cert) {
  certModalTitle.textContent = 'Edit Certification';
  certForm.reset();
  document.getElementById('cert_action').value = 'edit';
  document.getElementById('cert_id').value = cert.id;
  document.getElementById('cert-name').value = cert.certification_name;
  document.getElementById('cert-issuer').value = cert.issuing_body;
  document.getElementById('cert-date').value = cert.certification_date;
  document.getElementById('existing_file').value = cert.certification_file;
  var fileInput = document.getElementById('cert-file');
  if (fileInput) {
    fileInput.required = false; // existing file may be kept
    fileInput.value = '';
  }
  var preview = document.getElementById('cert-file-preview');
  if (preview) {
    preview.innerHTML = '';
    if (cert.certification_file) {
      var ext = cert.certification_file.split('.').pop().toLowerCase();
      if (['jpg','jpeg','png'].includes(ext)) {
        preview.innerHTML = '<p>Current File:</p>' +
          '<img src="' + cert.certification_file + '" alt="Current Certification" class="cert-image" style="max-width: 180px; max-height: 180px; width: auto; height: auto; margin-bottom: 8px;">';
      } else if (ext === 'pdf') {
        preview.innerHTML = '<p>Current File:</p>' +
          '<a href="' + cert.certification_file + '" target="_blank" class="pdf-link"><i class="fas fa-eye"></i> View PDF</a>';
      }
    }
  }
  var fileNameEl = document.getElementById('cert-file-name');
  if (fileNameEl) {
    if (cert.certification_file) {
      var parts = cert.certification_file.split(/[\\\/]/);
      var baseName = parts[parts.length - 1];
      fileNameEl.textContent = 'Current file: ' + baseName;
    } else {
      fileNameEl.textContent = '';
    }
  }
  
  // Handle custom category for editing
  const categorySelect = document.getElementById('cert-category');
  const customContainer = document.getElementById('custom-category-container');
  const customInput = document.getElementById('custom-category');
  
  // Check if the category is not in the predefined list
  const predefinedCategories = <?php echo json_encode($categories); ?>;
  var storedCategory = cert.category || '';
  var match = predefinedCategories.find(function(c) {
    return c.toUpperCase() === storedCategory.toUpperCase();
  });
  if (match) {
    categorySelect.value = match;
    customContainer.style.display = 'none';
    customInput.required = false;
    customInput.value = '';
  } else {
    categorySelect.value = 'Other';
    customContainer.style.display = 'block';
    customInput.value = storedCategory;
    customInput.required = true;
  }
  
  openCertModalDisplay();
}

// Form validation
document.getElementById('certForm').addEventListener('submit', function(e) {
  const categorySelect = document.getElementById('cert-category');
  const customInput = document.getElementById('custom-category');
  
  if (categorySelect.value === 'Other' && !customInput.value.trim()) {
    e.preventDefault();
    alert('Please specify a custom category.');
    customInput.focus();
    return false;
  }
});
</script>

<script>
// Auto-uppercase typing for certification text fields
function toUpperField(str) {
  return (str || '').toString().toUpperCase();
}

['cert-name','custom-category','cert-issuer'].forEach(function(id) {
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
