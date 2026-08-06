<?php
// Include database and access control
include '../db/Database.php';
include 'includes/access_control.php';

// Check module access early before any output
requireModuleAccess('backup-restore');

$conn = Database::getInstance()->getConnection();

// Handle backup request
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    echo "-- Database Backup\n";
    echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Table structure
        echo "-- Table structure for `$table`\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        
        $createResult = $conn->query("SHOW CREATE TABLE `$table`");
        $createRow = $createResult->fetch_array();
        echo $createRow[1] . ";\n\n";
        
        // Table data
        echo "-- Dumping data for table `$table`\n";
        $dataResult = $conn->query("SELECT * FROM `$table`");
        
        if ($dataResult->num_rows > 0) {
            $columns = [];
            $fields = $dataResult->fetch_fields();
            foreach ($fields as $field) {
                $columns[] = "`{$field->name}`";
            }
            
            $dataResult->data_seek(0);
            while ($row = $dataResult->fetch_array(MYSQLI_NUM)) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                echo "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
        }
        echo "\n";
    }
    
    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

// Handle restore request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['sql_file'];
        $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        
        if (strtolower($fileExtension) !== 'sql') {
            $error = "Please upload a valid SQL file.";
        } else {
            $sqlContent = file_get_contents($uploadedFile['tmp_name']);
            
            if ($sqlContent === false) {
                $error = "Failed to read the uploaded file.";
            } else {
                // Wrap with FK checks disabled/enabled for safer restore
                $wrappedSql = "SET FOREIGN_KEY_CHECKS=0;\n" . $sqlContent . "\nSET FOREIGN_KEY_CHECKS=1;";
                // Execute SQL statements
                $conn->multi_query($wrappedSql);
                
                // Process all result sets
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->next_result());
                
                if ($conn->error) {
                    $error = "Error restoring database: " . $conn->error;
                } else {
                    $success = "Database restored successfully!";
                }
            }
        }
    } else {
        $error = "Please select a SQL file to upload.";
    }
}

// Include export functions
include 'includes/export_functions.php';

// Handle export PDF
if (isset($_GET['action']) && $_GET['action'] === 'export_pdf') {
    // Get system data
    $totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    $alumniCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'alumni'")->fetch_assoc()['count'];
    $totalColleges = $conn->query("SELECT COUNT(*) as count FROM colleges")->fetch_assoc()['count'];
    $totalLogs = $conn->query("SELECT COUNT(*) as count FROM login_logs")->fetch_assoc()['count'];
    $totalCertifications = $conn->query("SELECT COUNT(*) as count FROM certifications")->fetch_assoc()['count'];
    $totalAwards = $conn->query("SELECT COUNT(*) as count FROM awards")->fetch_assoc()['count'];
    
    // Prepare data for PDF
    $data = [
        ['System Overview', ''],
        ['Total Users', $totalUsers],
        ['Alumni', $alumniCount],
        ['Total Colleges', $totalColleges],
        ['Total Login Records', $totalLogs],
        ['Certifications', $totalCertifications],
        ['Awards', $totalAwards],
        ['', ''], // Empty row for spacing
        ['Users List', ''],
        ['Name', 'Email', 'Role', 'College']
    ];
    
    // Add users data
    $users = $conn->query("SELECT u.full_name, u.email, u.role, c.college_name FROM users u LEFT JOIN colleges c ON u.college_id = c.id ORDER BY u.role, u.full_name");
    while ($user = $users->fetch_assoc()) {
        $data[] = [
            $user['full_name'],
            $user['email'],
            $user['role'],
            $user['college_name'] ?: 'N/A'
        ];
    }
    
    ExportFunctions::exportToPDF($data, 'ALUMytics System Data Export');
}

// Handle export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    // Compute metrics to include in CSV export
    $metrics = [
        'Total Users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0,
        'Alumni' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'alumni'")->fetch_assoc()['count'] ?? 0,
        'Total Colleges' => $conn->query("SELECT COUNT(*) as count FROM colleges")->fetch_assoc()['count'] ?? 0,
        'Login Records' => $conn->query("SELECT COUNT(*) as count FROM login_logs")->fetch_assoc()['count'] ?? 0,
        'Certifications' => $conn->query("SELECT COUNT(*) as count FROM certifications")->fetch_assoc()['count'] ?? 0,
        'Awards' => $conn->query("SELECT COUNT(*) as count FROM awards")->fetch_assoc()['count'] ?? 0,
    ];
    ExportFunctions::exportSystemData($conn, 'csv', ['users', 'colleges'], $metrics);
}

// Get system metrics
// Core user/account metrics
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$alumniCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'alumni'")->fetch_assoc()['count'];
$coordinatorCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'coordinator'")->fetch_assoc()['count'];
$adminCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$superadminCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'superadmin'")->fetch_assoc()['count'];
$activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE archived = 0")->fetch_assoc()['count'];

// Academic structure
$totalColleges = $conn->query("SELECT COUNT(*) as count FROM colleges")->fetch_assoc()['count'];
$totalDepartments = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
$totalPrograms = $conn->query("SELECT COUNT(*) as count FROM programs")->fetch_assoc()['count'];
$totalUniversities = $conn->query("SELECT COUNT(*) as count FROM universities")->fetch_assoc()['count'];
$totalCampuses = $conn->query("SELECT COUNT(*) as count FROM campuses")->fetch_assoc()['count'];

// Activity and achievements
$totalLogins = $conn->query("SELECT COUNT(*) as count FROM login_logs")->fetch_assoc()['count'];
$totalCertifications = $conn->query("SELECT COUNT(*) as count FROM certifications")->fetch_assoc()['count'];
$totalAwards = $conn->query("SELECT COUNT(*) as count FROM awards")->fetch_assoc()['count'];

// Employment overview (latest employment records)
$totalEmploymentRecords = $conn->query("SELECT COUNT(*) as count FROM employment")->fetch_assoc()['count'];
$employedCount = $conn->query("SELECT COUNT(*) as count FROM employment WHERE employment_status = 'employed'")->fetch_assoc()['count'];
$unemployedCount = $conn->query("SELECT COUNT(*) as count FROM employment WHERE employment_status = 'unemployed'")->fetch_assoc()['count'];
$selfEmployedCount = $conn->query("SELECT COUNT(*) as count FROM employment WHERE employment_status = 'self_employed'")->fetch_assoc()['count'];
$studyingCount = $conn->query("SELECT COUNT(*) as count FROM employment WHERE employment_status = 'studying'")->fetch_assoc()['count'];
// After handling any non-HTML actions above, include layout and render page
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content">
    <div class="header">
        <div class="header-top">
            <div>
                <h1 style="color:#000;">Backup & Restore</h1>
                <?php if (isCollegeRestricted() && $college_name): ?>
                    <p class="text-muted">System management for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php endif; ?>
                <p class="text-muted">Manage system backups, data restoration, and export operations.</p>
            </div>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <!-- Action Cards -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-download fa-3x text-primary"></i>
                    </div>
                    <h5 class="card-title">Backup Database</h5>
                    <p class="card-text text-muted">
                        Create a complete backup of all system data including users, settings, and records.
                    </p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#backupModal">
                        <i class="fas fa-download"></i> Backup Now
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-upload fa-3x text-warning"></i>
                    </div>
                    <h5 class="card-title">Restore Database</h5>
                    <p class="card-text text-muted">
                        Restore system data from a previously created backup file.
                    </p>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#restoreModal">
                        <i class="fas fa-upload"></i> Restore Now
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-file-export fa-3x text-success"></i>
                    </div>
                    <h5 class="card-title">Export Data</h5>
                    <p class="card-text text-muted">
                        Export system data in CSV format for reporting and analysis.
                    </p>
                    <div class="btn-group">

                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportCsvModal">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</div>

<!-- Backup Confirmation Modal -->
<div class="modal fade" id="backupModal" tabindex="-1" aria-labelledby="backupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="backupModalLabel">Confirm Database Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Information:</strong> This will create a complete backup of all system data.
                </div>
                <p>Are you sure you want to create a database backup? This will download a SQL file containing all system data.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBackup">
                    <i class="fas fa-download"></i> Create Backup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restoreModalLabel">Confirm Database Restore</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This will replace all current data with the backup data. This action cannot be undone.
                    </div>
                    
                    <input type="hidden" name="action" value="restore">
                    
                    <div class="mb-3">
                        <label for="sql_file" class="form-label">Select SQL Backup File</label>
                        <input type="file" class="form-control" id="sql_file" name="sql_file" accept=".sql" required>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmRestore" required>
                        <label class="form-check-label" for="confirmRestore">
                            I understand that this will replace all current data and cannot be undone.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-upload"></i> Restore Database
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export PDF Confirmation Modal -->
<div class="modal fade" id="exportPdfModal" tabindex="-1" aria-labelledby="exportPdfModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportPdfModalLabel">Export System Data as PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This will generate a PDF report containing system overview, user lists, and other key information.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmExportPdf">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export CSV Confirmation Modal -->
<div class="modal fade" id="exportCsvModal" tabindex="-1" aria-labelledby="exportCsvModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportCsvModalLabel">Export System Data as CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This will generate a CSV file containing users, colleges, login logs, certifications, and awards data.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmExportCsv">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Server-side system metrics made available to client-side exports
window.SYSTEM_METRICS = <?php echo json_encode([
    // Core users
    'Total Users' => (int)$totalUsers,
    'Active Users' => (int)$activeUsers,
    'Alumni' => (int)$alumniCount,
    'Coordinators' => (int)$coordinatorCount,
    'Admins' => (int)$adminCount,
    'Superadmins' => (int)$superadminCount,

    // Academic structure
    'Total Colleges' => (int)$totalColleges,
    'Total Departments' => (int)$totalDepartments,
    'Total Programs' => (int)$totalPrograms,
    'Total Universities' => (int)$totalUniversities,
    'Total Campuses' => (int)$totalCampuses,

    // Activity & achievements
    'Login Records' => (int)$totalLogins,
    'Certifications' => (int)$totalCertifications,
    'Awards' => (int)$totalAwards,

    // Employment overview (counts of employment records by status)
    'Employment Records' => (int)$totalEmploymentRecords,
    'Employed (records)' => (int)$employedCount,
    'Unemployed (records)' => (int)$unemployedCount,
    'Self-employed (records)' => (int)$selfEmployedCount,
    'Studying (records)' => (int)$studyingCount,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Export Libraries dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="js/export-libraries.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Debug export library availability
    if (window.exportLibrary && window.exportLibrary.debugLibraries) {
        window.exportLibrary.debugLibraries();
    }

    // Backup confirmation
    document.getElementById('confirmBackup').addEventListener('click', function() {
        window.location.href = 'backup-restore.php?action=backup';
    });

    // Export PDF confirmation - Use JavaScript libraries
    document.getElementById('confirmExportPdf').addEventListener('click', function() {
        try {
            if (window.ExportLibraries) {
                exportSystemDataPDF();
            } else {
                // Fallback to server-side export
                window.open('backup-restore.php?action=export_pdf', '_blank');
            }
        } catch (error) {
            console.error('Export error:', error);
            // Fallback to server-side export
            window.open('backup-restore.php?action=export_pdf', '_blank');
        }
    });

    // Export CSV confirmation - Use JavaScript libraries
    document.getElementById('confirmExportCsv').addEventListener('click', function() {
        try {
            if (window.ExportLibraries) {
                exportSystemDataCSV();
            } else {
                // Fallback to server-side export
                window.location.href = 'backup-restore.php?action=export_csv';
            }
        } catch (error) {
            console.error('Export error:', error);
            // Fallback to server-side export
            window.location.href = 'backup-restore.php?action=export_csv';
        }
    });

    // Client-side PDF export
    function exportSystemDataPDF() {
        const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
        const filename = `system_data_${timestamp}.pdf`;
        
        // Prepare system data for export
        const systemData = prepareSystemData();
        
        window.ExportLibraries.exportToPDF(
            systemData, 
            filename, 
            null, 
            'ALUMytics System Data Report'
        );
    }

    // Client-side CSV export
    function exportSystemDataCSV() {
        const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
        const filename = `system_data_${timestamp}.csv`;
        
        // Prepare system data for export
        const systemData = prepareSystemData();
        
        window.ExportLibraries.exportToCSV(systemData, filename);
    }

    // Prepare system data for export
    function prepareSystemData() {
        const data = [];
        
        // Add system overview
        data.push(['SYSTEM OVERVIEW']);
        data.push(['Metric', 'Value']);
        
        // Get system metrics from the page
        const systemMetrics = getSystemMetrics();
        Object.entries(systemMetrics).forEach(([key, value]) => {
            data.push([key, value]);
        });
        
        data.push([]); // Empty row for separation
        
        // Add user data if available
        const userData = getUserData();
        if (userData.length > 0) {
            data.push(['USER DATA']);
            data.push(['ID', 'Name', 'Email', 'Role', 'Status']);
            userData.forEach(user => {
                data.push([user.id, user.name, user.email, user.role, user.status]);
            });
        }
        
        return data;
    }

    // Get system metrics from the page or server-provided fallback
    function getSystemMetrics() {
        const metrics = {};
        const cards = document.querySelectorAll('.metric-card');

        // Primary: read any visible metric cards on the current page
        if (cards.length > 0) {
            cards.forEach(card => {
                const title = card.querySelector('h3')?.textContent?.trim();
                const value = card.querySelector('.metric-value')?.textContent?.trim();
                if (title && value) metrics[title] = value;
            });
            return metrics;
        }

        // Fallback: use server-side metrics exposed via window.SYSTEM_METRICS
        if (window.SYSTEM_METRICS) {
            return window.SYSTEM_METRICS;
        }

        return metrics;
    }

    // Get user data from the page (if available)
    function getUserData() {
        const userData = [];
        
        // This would need to be customized based on your specific user data display
        // For now, return empty array as fallback
        return userData;
    }
});
</script>
