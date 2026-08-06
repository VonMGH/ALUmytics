<?php
include 'includes/access_control.php';

if ($role !== 'superadmin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

global $conn;
$universities = $conn->query('SELECT id, name FROM universities ORDER BY name');
$selectedUniversityId = isset($_GET['university_id']) ? (int)$_GET['university_id'] : 0;
$selectedCampusId = isset($_GET['campus_id']) ? (int)$_GET['campus_id'] : 0;
$selectedDepartmentId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Handle add / edit / delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectUni = isset($_POST['university_id']) ? (int)$_POST['university_id'] : $selectedUniversityId;
    $redirectCampus = isset($_POST['campus_id']) ? (int)$_POST['campus_id'] : $selectedCampusId;
    $redirectDept = isset($_POST['department_id']) ? (int)$_POST['department_id'] : $selectedDepartmentId;

    if ($action === 'add') {
        $universityId = (int)($_POST['university_id'] ?? 0);
        $campusId = (int)($_POST['campus_id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($universityId > 0 && $campusId > 0 && $departmentId > 0 && $name !== '') {
            $stmt = $conn->prepare('INSERT INTO programs (university_id, campus_id, department_id, name) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiis', $universityId, $campusId, $departmentId, $name);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: sys-programs.php?university_id=' . $redirectUni . '&campus_id=' . $redirectCampus . '&department_id=' . $redirectDept);
        exit();
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $universityId = (int)($_POST['university_id'] ?? 0);
        $campusId = (int)($_POST['campus_id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id > 0 && $universityId > 0 && $campusId > 0 && $departmentId > 0 && $name !== '') {
            $stmt = $conn->prepare('UPDATE programs SET university_id = ?, campus_id = ?, department_id = ?, name = ? WHERE id = ?');
            $stmt->bind_param('iiisi', $universityId, $campusId, $departmentId, $name, $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: sys-programs.php?university_id=' . $redirectUni . '&campus_id=' . $redirectCampus . '&department_id=' . $redirectDept);
        exit();
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM programs WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: sys-programs.php?university_id=' . $redirectUni . '&campus_id=' . $redirectCampus . '&department_id=' . $redirectDept);
        exit();
    }
}

$campuses = null;
if ($selectedUniversityId > 0) {
    $stmt = $conn->prepare('SELECT id, name FROM campuses WHERE university_id = ? ORDER BY name');
    $stmt->bind_param('i', $selectedUniversityId);
    $stmt->execute();
    $campuses = $stmt->get_result();
    $stmt->close();
}

$departments = null;
if ($selectedUniversityId > 0 && $selectedCampusId > 0) {
    $stmt = $conn->prepare('SELECT id, name FROM departments WHERE university_id = ? AND campus_id = ? ORDER BY name');
    $stmt->bind_param('ii', $selectedUniversityId, $selectedCampusId);
    $stmt->execute();
    $departments = $stmt->get_result();
    $stmt->close();
}

$programs = null;
$totalPrograms = 0;
if ($selectedUniversityId > 0 && $selectedCampusId > 0 && $selectedDepartmentId > 0) {
    $stmt = $conn->prepare('SELECT id, name, created_at FROM programs WHERE university_id = ? AND campus_id = ? AND department_id = ? ORDER BY name');
    $stmt->bind_param('iii', $selectedUniversityId, $selectedCampusId, $selectedDepartmentId);
    $stmt->execute();
    $programs = $stmt->get_result();
    $totalPrograms = $programs ? $programs->num_rows : 0;
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
                    <h1 class="mb-0 dashboard-title">Manage Program</h1>
                    <p class="dashboard-subtitle mb-0">Select a university, campus, and department, then manage its programs.</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                        <i class="fas fa-plus"></i> Add Program
                    </button>
                </div>
            </div>
        </div>

        <div class="filter-panel sys-filter-panel">
            <div class="filter-panel-title">
                <i class="fas fa-filter"></i> Filter
            </div>
            <form method="get" class="sys-filter-form sys-filter-form-row sys-filter-form-row-3">
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
                <div class="filter-dropdown">
                    <label for="campus-select" class="form-label">Campus / Branch</label>
                    <select id="campus-select" name="campus_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">Select Campus/Branch</option>
                        <?php if ($campuses): ?>
                            <?php while ($c = $campuses->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($selectedCampusId == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="filter-dropdown">
                    <label for="department-select" class="form-label">College / Department</label>
                    <select id="department-select" name="department_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">Select College/Department</option>
                        <?php if ($departments): ?>
                            <?php while ($d = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo ($selectedDepartmentId == $d['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
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
                    <?php if ($selectedUniversityId > 0 && $selectedCampusId > 0 && $selectedDepartmentId > 0): ?>
                        Programs (<?= $totalPrograms ?>)
                    <?php else: ?>
                        Programs
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($selectedUniversityId === 0 || $selectedCampusId === 0 || $selectedDepartmentId === 0): ?>
                    <p class="sys-empty-state mb-0">Select a university, campus, and department to view and manage programs.</p>
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
                                <?php if ($programs && $programs->num_rows > 0): ?>
                                    <?php $i = 1; while ($row = $programs->fetch_assoc()): ?>
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
                                                    data-campus="<?php echo $selectedCampusId; ?>"
                                                    data-department="<?php echo $selectedDepartmentId; ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editProgramModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button
                                                    class="btn btn-sm btn-outline-danger delete-btn"
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>"
                                                    data-university="<?php echo $selectedUniversityId; ?>"
                                                    data-campus="<?php echo $selectedCampusId; ?>"
                                                    data-department="<?php echo $selectedDepartmentId; ?>"
                                                    data-bs-toggle="modal" data-bs-target="#deleteProgramModal">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="sys-empty-state">No programs defined for this department yet.</td>
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

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="sys-programs.php">
                <div class="modal-header">
                    <h5 class="modal-title">Add Program</h5>
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
                        <label for="add-campus" class="form-label">Campus / Branch *</label>
                        <select id="add-campus" name="campus_id" class="form-select" required>
                            <option value="">Select Campus/Branch</option>
                            <?php
                            if ($selectedUniversityId > 0) {
                                $campusesAddStmt = $conn->prepare('SELECT id, name FROM campuses WHERE university_id = ? ORDER BY name');
                                $campusesAddStmt->bind_param('i', $selectedUniversityId);
                                $campusesAddStmt->execute();
                                $campusesAdd = $campusesAddStmt->get_result();
                                while ($c = $campusesAdd->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($selectedCampusId == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endwhile;
                                $campusesAddStmt->close();
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add-department" class="form-label">College / Department *</label>
                        <select id="add-department" name="department_id" class="form-select" required>
                            <option value="">Select College/Department</option>
                            <?php
                            if ($selectedUniversityId > 0 && $selectedCampusId > 0) {
                                $departmentsAddStmt = $conn->prepare('SELECT id, name FROM departments WHERE university_id = ? AND campus_id = ? ORDER BY name');
                                $departmentsAddStmt->bind_param('ii', $selectedUniversityId, $selectedCampusId);
                                $departmentsAddStmt->execute();
                                $departmentsAdd = $departmentsAddStmt->get_result();
                                while ($d = $departmentsAdd->fetch_assoc()): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo ($selectedDepartmentId == $d['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['name']); ?>
                                    </option>
                                <?php endwhile;
                                $departmentsAddStmt->close();
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="program-name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="program-name" name="name" required>
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

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="sys-programs.php">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Program</h5>
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
                        <label for="edit-campus" class="form-label">Campus / Branch *</label>
                        <select id="edit-campus" name="campus_id" class="form-select" required>
                            <option value="">Select Campus/Branch</option>
                            <?php
                            $campusesAll = $conn->query('SELECT id, name FROM campuses ORDER BY name');
                            if ($campusesAll):
                                while ($c = $campusesAll->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endwhile;
                            endif;
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-department" class="form-label">College / Department *</label>
                        <select id="edit-department" name="department_id" class="form-select" required>
                            <option value="">Select College/Department</option>
                            <?php
                            $departmentsAll = $conn->query('SELECT id, name FROM departments ORDER BY name');
                            if ($departmentsAll):
                                while ($d = $departmentsAll->fetch_assoc()): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
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

<!-- Delete Program Modal -->
<div class="modal fade" id="deleteProgramModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="sys-programs.php">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete-id">
                    <input type="hidden" name="university_id" id="delete-university-id">
                    <input type="hidden" name="campus_id" id="delete-campus-id">
                    <input type="hidden" name="department_id" id="delete-department-id">
                    <p>Are you sure you want to delete <strong id="delete-name"></strong>?</p>
                    <p class="text-muted mb-0"><small>This may affect specializations linked to this program.</small></p>
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
                document.getElementById('edit-campus').value = this.dataset.campus;
                document.getElementById('edit-department').value = this.dataset.department;
            });
        });

        document.querySelectorAll('.delete-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('delete-id').value = this.dataset.id;
                document.getElementById('delete-name').textContent = this.dataset.name;
                document.getElementById('delete-university-id').value = this.dataset.university;
                document.getElementById('delete-campus-id').value = this.dataset.campus;
                document.getElementById('delete-department-id').value = this.dataset.department;
            });
        });
    });
</script>
