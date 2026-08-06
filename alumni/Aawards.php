<?php
include 'database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please sign in.'); window.location.href = 'signin.php';</script>";
    exit();
}
$user_id = $_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

// Handle add/edit award POST BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $award_id = $_POST['award_id'] ?? '';
    $award_title = strtoupper(trim($_POST['award_title']));
    $award_category = trim($_POST['award_category']);
    $awarded_by = strtoupper(trim($_POST['awarded_by']));
    $award_date = $_POST['award_date'];
    $description = strtoupper(trim($_POST['description'] ?? ''));

    if (strtoupper($award_category) === 'OTHER' && !empty($_POST['custom_award_category'])) {
        $award_category = strtoupper(trim($_POST['custom_award_category']));
    }

    if (empty($award_title) || empty($award_category) || empty($awarded_by) || empty($award_date)) {
        echo "<script>alert('All fields are required.'); window.history.back();</script>";
        exit();
    }

    $file_path = '';
    if (isset($_FILES['award_file']) && $_FILES['award_file']['error'] == 0) {
        $upload_dir = 'uploads/awards/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_tmp = $_FILES['award_file']['tmp_name'];
        $file_name = basename($_FILES['award_file']['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['pdf','jpg','jpeg','png'];
        if (in_array($ext, $allowed)) {
            $file_path = $upload_dir . uniqid('award_') . '_' . $file_name;
            move_uploaded_file($file_tmp, $file_path);
        }
    } else if (!empty($_POST['existing_file'])) {
        $file_path = $_POST['existing_file'];
    }

    if ($_POST['action'] === 'add' && empty($file_path)) {
        echo "<script>alert('Please upload an award certificate or image.'); window.history.back();</script>";
        exit();
    }
    if ($_POST['action'] === 'edit' && empty($file_path)) {
        echo "<script>alert('Please upload an award certificate or image.'); window.history.back();</script>";
        exit();
    }

    if ($_POST['action'] === 'add') {
        $stmt = $conn->prepare("INSERT INTO awards (user_id, award_title, category, awarded_by, award_date, description, award_file) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssss', $user_id, $award_title, $award_category, $awarded_by, $award_date, $description, $file_path);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['action'] === 'edit' && $award_id) {
        $stmt = $conn->prepare("UPDATE awards SET award_title=?, category=?, awarded_by=?, award_date=?, description=?, award_file=? WHERE id=? AND user_id=?");
        $stmt->bind_param('ssssssii', $award_title, $award_category, $awarded_by, $award_date, $description, $file_path, $award_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: Aawards.php');
    exit();
}

if (isset($_GET['delete'])) {
    $award_id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT award_file FROM awards WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $award_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $file = $row['award_file'] ?? null;
    if ($file && file_exists($file)) unlink($file);
    $stmt = $conn->prepare("DELETE FROM awards WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $award_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header('Location: Aawards.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM awards WHERE user_id = ? ORDER BY award_date DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$awards = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$categories = [
    "Academic Excellence", "Professional Achievement", "Leadership", "Community Service",
    "Sports & Athletics", "Arts & Culture", "Innovation & Research", "Student Organization",
    "Outstanding Performance", "Merit Award", "Honor Society", "Other"
];

include 'includes/Aheader.php';
include 'includes/Asidebar.php';
?>
<link rel="stylesheet" href="alumni.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="Aemployment.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="Acertfication.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<main class="profile-content certification-page awards-page">
  <h1 class="profile-heading">Awards & Recognition</h1>

  <?php if (empty($awards)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fas fa-trophy"></i></div>
      <h3>No Awards Yet</h3>
      <p>Start building your awards portfolio by adding your first achievement.</p>
      <button class="add-certification" id="openAwardModalBtn">Add Your First Award</button>
    </div>
  <?php else: ?>
    <div class="certification-list">
      <?php foreach ($awards as $award): ?>
        <?php
            $awardExt = !empty($award['award_file'])
                ? strtolower(pathinfo($award['award_file'], PATHINFO_EXTENSION))
                : '';
            $awardFileLabel = '—';
            if (in_array($awardExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $awardFileLabel = 'Image Certificate';
            } elseif ($awardExt === 'pdf') {
                $awardFileLabel = 'PDF Document';
            } elseif ($awardExt !== '') {
                $awardFileLabel = strtoupper($awardExt) . ' File';
            }
            $awardFormattedDate = !empty($award['award_date'])
                ? date('F j, Y', strtotime($award['award_date']))
                : '—';
        ?>
        <div class="certification-entry cert-card">
          <div class="cert-header">
            <div class="cert-title-block">
              <h3><?php echo htmlspecialchars($award['award_title']); ?></h3>
              <span class="cert-category-badge"><?php echo htmlspecialchars($award['category']); ?></span>
            </div>
            <div class="cert-actions">
              <button type="button" class="cert-edit-btn" onclick='openEditModal(<?php echo json_encode($award); ?>)' aria-label="Edit award">
                <i class="fas fa-pen-to-square"></i>
                <span class="cert-btn-text">Edit</span>
              </button>
              <a href="Aawards.php?delete=<?php echo $award['id']; ?>" class="cert-delete-btn" onclick="return confirm('Delete this award?')" aria-label="Delete award">
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
                  <span class="cert-label">Awarded By</span>
                  <span class="cert-value"><?php echo htmlspecialchars($award['awarded_by']); ?></span>
                </div>
                <span class="cert-detail-divider" aria-hidden="true"></span>
                <div class="cert-detail-pair">
                  <span class="cert-label">Date Received</span>
                  <span class="cert-value"><?php echo !empty($award['award_date']) ? htmlspecialchars($award['award_date']) : '—'; ?></span>
                </div>
              </div>
              <span class="cert-detail-accent cert-detail-accent-right" aria-hidden="true"></span>
            </div>

            <div class="cert-solo-meta">
              <div class="cert-solo-item">
                <span class="cert-solo-icon"><i class="fas fa-tag"></i></span>
                <div class="cert-solo-text">
                  <span class="cert-label">Category</span>
                  <span class="cert-value"><?php echo htmlspecialchars($award['category']); ?></span>
                </div>
              </div>
              <div class="cert-solo-item">
                <span class="cert-solo-icon"><i class="fas fa-calendar-check"></i></span>
                <div class="cert-solo-text">
                  <span class="cert-label">Received On</span>
                  <span class="cert-value"><?php echo htmlspecialchars($awardFormattedDate); ?></span>
                </div>
              </div>
              <?php if (!empty($award['award_file'])): ?>
              <div class="cert-solo-item">
                <span class="cert-solo-icon"><i class="fas fa-file-alt"></i></span>
                <div class="cert-solo-text">
                  <span class="cert-label">Attachment</span>
                  <span class="cert-value"><?php echo htmlspecialchars($awardFileLabel); ?></span>
                </div>
              </div>
              <?php endif; ?>
              <?php if (!empty($award['description'])): ?>
              <div class="cert-solo-item span-full">
                <span class="cert-solo-icon"><i class="fas fa-align-left"></i></span>
                <div class="cert-solo-text">
                  <span class="cert-label">Description</span>
                  <span class="cert-value"><?php echo htmlspecialchars($award['description']); ?></span>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <?php if (!empty($award['description'])): ?>
            <div class="cert-detail-row awards-description-row">
              <span class="cert-label">Description</span>
              <span class="cert-value"><?php echo htmlspecialchars($award['description']); ?></span>
            </div>
            <?php endif; ?>
          </div>

          <?php if ($award['award_file']): ?>
            <?php if (in_array($awardExt, ['jpg','jpeg','png'])): ?>
              <div class="cert-image-container">
                <img src="<?php echo htmlspecialchars($award['award_file']); ?>"
                     alt="Award Certificate"
                     class="cert-image"
                     onclick="openImageModal('<?php echo htmlspecialchars($award['award_file'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($award['award_title'], ENT_QUOTES); ?>')" />
                <div class="cert-image-overlay">
                  <div class="cert-image-actions">
                    <button type="button" class="view-btn" onclick="openImageModal('<?php echo htmlspecialchars($award['award_file'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($award['award_title'], ENT_QUOTES); ?>')">
                      <i class="fas fa-eye"></i> View
                    </button>
                    <span class="cert-action-divider" aria-hidden="true"></span>
                    <a href="<?php echo htmlspecialchars($award['award_file']); ?>" download class="download-btn">
                      <i class="fas fa-download"></i> Download
                    </a>
                  </div>
                </div>
              </div>
            <?php elseif ($awardExt === 'pdf'): ?>
              <div class="cert-pdf-container">
                <div class="pdf-icon">
                  <i class="fas fa-file-pdf"></i>
                </div>
                <div class="pdf-actions">
                  <a href="<?php echo htmlspecialchars($award['award_file']); ?>" class="pdf-link" target="_blank">
                    <i class="fas fa-eye"></i> View PDF
                  </a>
                  <a href="<?php echo htmlspecialchars($award['award_file']); ?>" class="pdf-link" download>
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
      <button class="add-certification" id="openAwardModalBtn">Add Award</button>
    </div>
  <?php endif; ?>
</main>

<!-- Image Modal for enlarged view -->
<div id="imageModal" class="image-modal">
  <div class="image-modal-content">
    <span class="image-close-btn" onclick="closeImageModal()">&times;</span>
    <h3 id="imageModalTitle"></h3>
    <img id="modalImage" src="" alt="Award Certificate" />
  </div>
</div>

<!-- Award Modal (outside main to avoid overflow clipping) -->
<div id="awardModal" class="certification-modal">
  <div class="certification-modal-content">
    <span class="close-btn" id="closeAwardModalBtn" aria-label="Close">&times;</span>
    <h2 id="awardModalTitle">Add Award</h2>
    <form id="awardForm" action="Aawards.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="award_id" id="award_id">
      <input type="hidden" name="existing_file" id="existing_file">
      <input type="hidden" name="action" id="award_action" value="add">

      <label for="award-title">Award Title *</label>
      <input type="text" id="award-title" name="award_title" required placeholder="Enter award title">

      <label for="award-category">Category *</label>
      <select id="award-category" name="award_category" required onchange="toggleCustomAwardCategory()">
        <option value="" disabled selected>Select Category</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
        <?php endforeach; ?>
      </select>

      <div id="custom-award-category-container" class="custom-category-container" style="display:none;">
        <label for="custom-award-category">Specify Category *</label>
        <input type="text" id="custom-award-category" name="custom_award_category" placeholder="Enter your custom category">
      </div>

      <label for="awarded-by">Awarded By *</label>
      <input type="text" id="awarded-by" name="awarded_by" required placeholder="Organization/Institution">

      <label for="award-date">Date Received *</label>
      <input type="date" id="award-date" name="award_date" required>

      <label for="award-description">Description (Optional)</label>
      <input type="text" id="award-description" name="description" placeholder="Brief description of the award">

      <label for="award-file">Upload Certificate/Image *</label>
      <input type="file" id="award-file" name="award_file" accept=".pdf,image/*" required>
      <div id="award-file-name" class="cert-file-name"></div>
      <div id="award-file-preview" class="cert-file-preview"></div>

      <div class="cert-form-footer">
        <button type="submit" class="save-button">Save Award</button>
      </div>
    </form>
  </div>
</div>

<script>
const openAwardBtn = document.getElementById("openAwardModalBtn");
const closeAwardBtn = document.getElementById("closeAwardModalBtn");
const awardModal = document.getElementById("awardModal");
const awardForm = document.getElementById("awardForm");
const awardModalTitle = document.getElementById("awardModalTitle");

function openAwardModalDisplay() {
  awardModal.style.display = "flex";
  document.body.style.overflow = "hidden";
}

function closeAwardModalDisplay() {
  awardModal.style.display = "none";
  document.body.style.overflow = "";
}

function toggleCustomAwardCategory() {
  const select = document.getElementById('award-category');
  const container = document.getElementById('custom-award-category-container');
  const input = document.getElementById('custom-award-category');
  if (!select || !container || !input) return;
  if (select.value === 'Other') {
    container.style.display = 'block';
    input.required = true;
  } else {
    container.style.display = 'none';
    input.required = false;
    input.value = '';
  }
}

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
  document.getElementById("imageModal").style.display = "none";
  document.body.style.overflow = "";
}

window.onclick = function(event) {
  const imageModal = document.getElementById("imageModal");
  const awardModalEl = document.getElementById("awardModal");

  if (event.target === imageModal) {
    closeImageModal();
  }
  if (event.target === awardModalEl) {
    closeAwardModalDisplay();
  }
};

if (openAwardBtn) {
  openAwardBtn.onclick = () => {
    awardModalTitle.textContent = 'Add Award';
    awardForm.reset();
    document.getElementById('award_action').value = 'add';
    document.getElementById('award_id').value = '';
    document.getElementById('existing_file').value = '';
    document.getElementById('custom-award-category-container').style.display = 'none';
    var customCat = document.getElementById('custom-award-category');
    if (customCat) {
      customCat.required = false;
      customCat.value = '';
    }
    var fileInput = document.getElementById('award-file');
    if (fileInput) {
      fileInput.required = true;
      fileInput.value = '';
    }
    var fileNameEl = document.getElementById('award-file-name');
    if (fileNameEl) fileNameEl.textContent = '';
    var preview = document.getElementById('award-file-preview');
    if (preview) preview.innerHTML = '';
    openAwardModalDisplay();
  };
}

if (closeAwardBtn) {
  closeAwardBtn.onclick = () => closeAwardModalDisplay();
}

function openEditModal(award) {
  awardModalTitle.textContent = 'Edit Award';
  awardForm.reset();
  document.getElementById('award_action').value = 'edit';
  document.getElementById('award_id').value = award.id;
  document.getElementById('award-title').value = award.award_title;
  document.getElementById('awarded-by').value = award.awarded_by;
  document.getElementById('award-date').value = award.award_date || '';
  var descEl = document.getElementById('award-description');
  if (descEl) descEl.value = award.description || '';
  document.getElementById('existing_file').value = award.award_file;
  var fileInput = document.getElementById('award-file');
  if (fileInput) {
    fileInput.required = false;
    fileInput.value = '';
  }
  var fileNameEl = document.getElementById('award-file-name');
  if (fileNameEl) {
    if (award.award_file) {
      var parts = award.award_file.split(/[\\\/]/);
      fileNameEl.textContent = 'Current file: ' + parts[parts.length - 1];
    } else {
      fileNameEl.textContent = '';
    }
  }
  var preview = document.getElementById('award-file-preview');
  if (preview) {
    preview.innerHTML = '';
    if (award.award_file) {
      var ext = award.award_file.split('.').pop().toLowerCase();
      if (['jpg','jpeg','png'].includes(ext)) {
        preview.innerHTML = '<p>Current File:</p>' +
          '<img src="' + award.award_file + '" alt="Current Award" class="cert-image" style="max-width: 180px; max-height: 180px; width: auto; height: auto; margin-bottom: 8px;">';
      } else if (ext === 'pdf') {
        preview.innerHTML = '<p>Current File:</p>' +
          '<a href="' + award.award_file + '" target="_blank" class="pdf-link"><i class="fas fa-eye"></i> View PDF</a>';
      }
    }
  }

  const awardCategorySelect = document.getElementById('award-category');
  const customAwardContainer = document.getElementById('custom-award-category-container');
  const customAwardInput = document.getElementById('custom-award-category');
  const predefinedAwardCategories = <?php echo json_encode($categories); ?>;
  var storedAwardCategory = award.category || '';
  var match = predefinedAwardCategories.find(function(c) {
    return c.toUpperCase() === storedAwardCategory.toUpperCase();
  });
  if (match) {
    awardCategorySelect.value = match;
    customAwardContainer.style.display = 'none';
    customAwardInput.required = false;
    customAwardInput.value = '';
  } else {
    awardCategorySelect.value = 'Other';
    customAwardContainer.style.display = 'block';
    customAwardInput.value = storedAwardCategory;
    customAwardInput.required = true;
  }

  openAwardModalDisplay();
}

document.getElementById('awardForm').addEventListener('submit', function(e) {
  const categorySelect = document.getElementById('award-category');
  const customInput = document.getElementById('custom-award-category');
  if (categorySelect.value === 'Other' && customInput && !customInput.value.trim()) {
    e.preventDefault();
    alert('Please specify a custom category.');
    customInput.focus();
    return false;
  }
});
</script>

<script>
function toUpperField(str) {
  return (str || '').toString().toUpperCase();
}

['award-title','awarded-by','custom-award-category','award-description'].forEach(function(id) {
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
