<?php
include 'includes/access_control.php';

if ($role !== 'superadmin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

global $conn;

// Handle add / edit / delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $short = trim($_POST['short_name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare('INSERT INTO universities (name, short_name) VALUES (?, ?)');
            $stmt->bind_param('ss', $name, $short);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: sys-universities.php');
        exit();
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $short = trim($_POST['short_name'] ?? '');
        if ($id > 0 && $name !== '') {
            $stmt = $conn->prepare('UPDATE universities SET name = ?, short_name = ? WHERE id = ?');
            $stmt->bind_param('ssi', $name, $short, $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: sys-universities.php');
        exit();
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM universities WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: sys-universities.php');
        exit();
    }
}

$result = $conn->query('SELECT id, name, short_name, created_at FROM universities ORDER BY name');
$totalUniversities = $result ? $result->num_rows : 0;

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
                    <h1 class="mb-0 dashboard-title">Manage University / School</h1>
                    <p class="dashboard-subtitle mb-0">Add or update universities and schools available in the system.</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUniversityModal">
                        <i class="fas fa-plus"></i> Add University/School
                    </button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm dashboard-card sys-table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Universities / Schools (<?= $totalUniversities ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle sys-table mb-0">
                        <thead>
                            <tr>
                                <th class="sys-col-index">#</th>
                                <th>Name</th>
                                <th class="sys-col-short">Short Name</th>
                                <th class="sys-col-date">Created At</th>
                                <th class="sys-col-actions text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['short_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                        <td class="text-end">
                                            <button 
                                                class="btn btn-sm btn-outline-secondary edit-btn"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>"
                                                data-short="<?php echo htmlspecialchars($row['short_name'] ?? '', ENT_QUOTES); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editUniversityModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button 
                                                class="btn btn-sm btn-outline-danger delete-btn"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteUniversityModal">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="sys-empty-state">No universities/schools defined yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addUniversityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="sys-universities.php">
                <div class="modal-header">
                    <h5 class="modal-title">Add University / School</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="uni-name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="uni-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="uni-short" class="form-label">Short Name</label>
                        <input type="text" class="form-control" id="uni-short" name="short_name">
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

<!-- Edit Modal -->
<div class="modal fade" id="editUniversityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="sys-universities.php">
                <div class="modal-header">
                    <h5 class="modal-title">Edit University / School</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="edit-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-short" class="form-label">Short Name</label>
                        <input type="text" class="form-control" id="edit-short" name="short_name">
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

<!-- Delete Modal -->
<div class="modal fade" id="deleteUniversityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="sys-universities.php">
                <div class="modal-header">
                    <h5 class="modal-title">Delete University / School</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete-id">
                    <p>Are you sure you want to delete <strong id="delete-name"></strong>?</p>
                    <p class="text-muted mb-0"><small>This will also remove any campuses, departments, programs, or specializations linked to this university.</small></p>
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
    document.addEventListener('DOMContentLoaded', function() {
        function cleanupModalArtifacts() {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal-backdrop').forEach(function(el) {
                el.remove();
            });
        }

        document.querySelectorAll('.modal').forEach(function(modalEl) {
            modalEl.addEventListener('hidden.bs.modal', cleanupModalArtifacts);
        });

        document.querySelectorAll('.edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('edit-id').value = this.dataset.id;
                document.getElementById('edit-name').value = this.dataset.name;
                document.getElementById('edit-short').value = this.dataset.short;
            });
        });

        document.querySelectorAll('.delete-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('delete-id').value = this.dataset.id;
                document.getElementById('delete-name').textContent = this.dataset.name;
            });
        });
    });
</script>
