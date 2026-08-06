<?php
include 'includes/access_control.php';

if ($role !== 'superadmin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

global $conn;

// Load universities for selector
$universities = $conn->query('SELECT id, name FROM universities ORDER BY name');
$selectedUniversityId = isset($_GET['university_id']) ? (int)$_GET['university_id'] : 0;

// Handle add / edit / delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectUni = isset($_POST['university_id']) ? (int)$_POST['university_id'] : $selectedUniversityId;

    if ($action === 'add') {
        $universityId = (int)($_POST['university_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($universityId > 0 && $name !== '') {
            $stmt = $conn->prepare('INSERT INTO campuses (university_id, name) VALUES (?, ?)');
            $stmt->bind_param('is', $universityId, $name);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: sys-campuses.php?university_id=' . $redirectUni);
        exit();
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $universityId = (int)($_POST['university_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id > 0 && $universityId > 0 && $name !== '') {
            $stmt = $conn->prepare('UPDATE campuses SET university_id = ?, name = ? WHERE id = ?');
            $stmt->bind_param('isi', $universityId, $name, $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: sys-campuses.php?university_id=' . $redirectUni);
        exit();
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM campuses WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: sys-campuses.php?university_id=' . $redirectUni);
        exit();
    }
}

$campuses = null;
$totalCampuses = 0;
if ($selectedUniversityId > 0) {
    $stmt = $conn->prepare('SELECT id, name, created_at FROM campuses WHERE university_id = ? ORDER BY name');
    $stmt->bind_param('i', $selectedUniversityId);
    $stmt->execute();
    $campuses = $stmt->get_result();
    $totalCampuses = $campuses ? $campuses->num_rows : 0;
    $stmt->close();
}

$role ??= null;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/sys-pages.css">

<div class="content-wrapper">
    <main class="main-content dashboard-page">
        <div class="header dashboard-header">
            <div class="header-top d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <h1 class="mb-0 dashboard-title">Manage Campus / Branch</h1>
                    <p class="dashboard-subtitle mb-0">Select a university and manage its campuses or branches.</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCampusModal">
                        <i class="fas fa-plus"></i> Add Campus/Branch
                    </button>
                </div>
            </div>
        </div>

        <div class="filter-panel sys-filter-panel">
            <div class="filter-panel-title">
                <i class="fas fa-filter"></i> Filter
            </div>
            <form method="get" class="sys-filter-form">
                <div class="filter-dropdown">
                    <label for="university-select" class="form-label">University / School</label>
                    <select id="university-select" name="university_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">Select University/School</option>
                        <?php if ($universities): ?>
                            <?php while ($u = $universities->fetch_assoc()): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($selectedUniversityId == $u['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </form>
        </div>

        <div class="card shadow-sm dashboard-card sys-table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <?php if ($selectedUniversityId > 0): ?>
                        Campuses / Branches (<?= $totalCampuses ?>)
                    <?php else: ?>
                        Campuses / Branches
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($selectedUniversityId === 0): ?>
                    <p class="sys-empty-state mb-0">Select a university to view and manage its campuses.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle sys-table mb-0">
                            <thead>
                                <tr>
                                    <th class="sys-col-index">#</th>
                                    <th>Name</th>
                                    <th class="sys-col-date">Created At</th>
                                    <th class="sys-col-actions text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($campuses && $campuses->num_rows > 0): ?>
                                    <?php $i = 1; while ($row = $campuses->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                            <td class="text-end">
                                                <button
                                                    class="btn btn-sm btn-outline-secondary edit-btn"
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>"
                                                    data-university="<?php echo $selectedUniversityId; ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editCampusModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button
                                                    class="btn btn-sm btn-outline-danger delete-btn"
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>"
                                                    data-university="<?php echo $selectedUniversityId; ?>"
                                                    data-bs-toggle="modal" data-bs-target="#deleteCampusModal">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="sys-empty-state">No campuses defined for this university yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Add Campus Modal -->
<div class="modal fade" id="addCampusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="sys-campuses.php">
                <div class="modal-header">
                    <h5 class="modal-title">Add Campus / Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="add-university" class="form-label">University / School *</label>
                        <select id="add-university" name="university_id" class="form-select" required>
                            <option value="">Select University/School</option>
                            <?php
                            $universitiesAdd = $conn->query('SELECT id, name FROM universities ORDER BY name');
                            if ($universitiesAdd):
                                while ($u = $universitiesAdd->fetch_assoc()): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo ($selectedUniversityId == $u['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['name']); ?>
                                    </option>
                                <?php endwhile;
                            endif;
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="campus-name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="campus-name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Campus Modal -->
<div class="modal fade" id="editCampusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="sys-campuses.php">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Campus / Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="mb-3">
                        <label for="edit-university" class="form-label">University / School *</label>
                        <select id="edit-university" name="university_id" class="form-select" required>
                            <option value="">Select University/School</option>
                            <?php
                            $universitiesEdit = $conn->query('SELECT id, name FROM universities ORDER BY name');
                            if ($universitiesEdit):
                                while ($u = $universitiesEdit->fetch_assoc()): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                <?php endwhile;
                            endif;
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="edit-name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Campus Modal -->
<div class="modal fade" id="deleteCampusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="sys-campuses.php">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Campus / Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete-id">
                    <input type="hidden" name="university_id" id="delete-university-id">
                    <p>Are you sure you want to delete <strong id="delete-name"></strong>?</p>
                    <p class="text-muted mb-0"><small>This will also remove any departments, programs, or specializations linked only to this campus.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function cleanupModalArtifacts() {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal-backdrop').forEach(function (el) {
                el.remove();
            });
        }

        document.querySelectorAll('.modal').forEach(function (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', cleanupModalArtifacts);
        });

        document.querySelectorAll('.edit-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit-id').value = this.dataset.id;
                document.getElementById('edit-name').value = this.dataset.name;
                document.getElementById('edit-university').value = this.dataset.university;
            });
        });

        document.querySelectorAll('.delete-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('delete-id').value = this.dataset.id;
                document.getElementById('delete-name').textContent = this.dataset.name;
                document.getElementById('delete-university-id').value = this.dataset.university;
            });
        });
    });
</script>
