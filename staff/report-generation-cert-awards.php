<?php
include '../db/Database.php';
include 'includes/access_control.php';

requireModuleAccess('report-generation');

$conn = Database::getInstance()->getConnection();

$role ??= null;
$college_id ??= null;
$college_name ??= null;

// Optional PHPWord support for generating real .docx reports with PLSP header
$phpWordAvailable = false;
$phpWordAutoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($phpWordAutoload && file_exists($phpWordAutoload)) {
    require_once $phpWordAutoload;
    if (class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        $phpWordAvailable = true;
    }
}
// Simple filters similar to report-generation.php (Alumni Tracking)
$filterSchool = $_GET['school_university'] ?? 'all';
$filterDepartment = $_GET['department'] ?? 'all';
$filterYear = $_GET['year_graduated'] ?? 'all';
$filterType = $_GET['cert_type'] ?? 'all'; // all, certification, award

// Load school options
$schoolsResult = $conn->query("SELECT DISTINCT school_university FROM education WHERE school_university IS NOT NULL AND school_university <> '' ORDER BY school_university");

// Load department options (colleges)
if (isCollegeRestricted() && $college_id) {
    $departments = $conn->query("SELECT name AS college_department FROM colleges WHERE id = " . (int)$college_id . " ORDER BY name");
} else {
    $departments = $conn->query("SELECT name AS college_department FROM colleges ORDER BY name");
}

// Load year options
$yearsResult = $conn->query("SELECT DISTINCT year_graduated FROM education WHERE year_graduated IS NOT NULL AND year_graduated <> '' ORDER BY year_graduated DESC");

// Load combined certifications and awards per alumni (respecting college restriction and filters)
$certAwardRows = [];

$whereParts = [];
$whereParts[] = "u.role = 'alumni'";
// School filter (only when not restricted by college)
if ($filterSchool !== 'all' && $filterSchool !== '' && $filterSchool !== 'none') {
    $safeSchool = $conn->real_escape_string($filterSchool);
    $whereParts[] = "e.school_university = '{$safeSchool}'";
}
if (isCollegeRestricted() && $college_id) {
    $whereParts[] = "e.college_department = (SELECT name FROM colleges WHERE id = " . (int)$college_id . ")";
} elseif ($filterDepartment !== 'all' && $filterDepartment !== '' && $filterDepartment !== 'none') {
    $safeDept = $conn->real_escape_string($filterDepartment);
    $whereParts[] = "e.college_department = '{$safeDept}'";
}

if ($filterYear !== 'all' && $filterYear !== '' && $filterYear !== 'none') {
    $safeYear = $conn->real_escape_string($filterYear);
    $whereParts[] = "e.year_graduated = '{$safeYear}'";
}

$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

// Build base queries for certifications and awards, tagging each row with a type
$certSelect = "
            SELECT 
                u.full_name AS name,
                c.certification_name AS title,
                c.certification_file AS file_path,
                'certification' AS type
            FROM certifications c
            JOIN users u ON u.user_id = c.user_id
            JOIN education e ON e.user_id = u.user_id
            {$whereSql}
        ";

$awardSelect = "
            SELECT 
                u.full_name AS name,
                a.award_title AS title,
                a.award_file AS file_path,
                'award' AS type
            FROM awards a
            JOIN users u ON u.user_id = a.user_id
            JOIN education e ON e.user_id = u.user_id
            {$whereSql}
        ";

$unions = [];
if ($filterType === 'all' || $filterType === 'certification') {
    $unions[] = "({$certSelect})";
}
if ($filterType === 'all' || $filterType === 'award') {
    $unions[] = "({$awardSelect})";
}

if (!empty($unions)) {
    $sql = implode("\n        UNION ALL\n        ", $unions) . "\n        ORDER BY name, title";
} else {
    // Safety fallback; should not happen with valid filterType values
    $sql = "SELECT u.full_name AS name, '' AS title, '' AS file_path, '' AS type FROM users u WHERE 1=0";
}

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $certAwardRows[] = $row;
    }
}

// Server-side Word export: two-column table with name and stacked certifications/awards (with images)
if (isset($_GET['export']) && $_GET['export'] === 'word' && !empty($certAwardRows)) {
    // Group rows by alumni name so each name appears once with multiple certifications/awards
    $grouped = [];
    foreach ($certAwardRows as $row) {
        $name = $row['name'] ?? '';
        if ($name === '') continue;
        if (!isset($grouped[$name])) {
            $grouped[$name] = [];
        }
        $grouped[$name][] = $row;
    }

    // Prefer PHPWord DOCX export when available
    if ($phpWordAvailable) {
        try {
            $phpWord = new \PhpOffice\PhpWord\PhpWord();

            $section = $phpWord->addSection([
                'marginTop' => 720,
                'marginRight' => 1440,
                'marginBottom' => 720,
                'marginLeft' => 1440,
            ]);

            // PLSP header image (right-aligned, fixed width, keep aspect ratio)
            $headerImagePath = realpath(__DIR__ . '/../PLSP - Export Design.jpg');
            if ($headerImagePath && file_exists($headerImagePath)) {
                $header = $section->addHeader();
                $header->addImage(
                    $headerImagePath,
                    [
                        'width' => 450,
                        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT,
                    ]
                );
            }

            // Title and intro with spacing below header
            $section->addText(
                'Certifications and Awards',
                ['bold' => true, 'size' => 16],
                ['spaceBefore' => 160, 'spaceAfter' => 240]
            );

            $section->addText(
                'Generated on ' . date('Y-m-d H:i:s'),
                ['size' => 11],
                ['spaceAfter' => 120]
            );

            $section->addText(
                'This report lists alumni certifications and awards grouped by alumni name.',
                ['size' => 11],
                ['spaceAfter' => 240]
            );

            // Table: Name + stacked certifications/awards
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 50,
                'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
            ]);

            $table->addRow();
            // Use the same green header color as tracer and year comparison reports
            $table->addCell(2500, ['bgColor' => '219653'])->addText(
                'Name',
                ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]
            );
            $table->addCell(6500, ['bgColor' => '219653'])->addText(
                'Certification / Training',
                ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]
            );

            foreach ($grouped as $name => $items) {
                $table->addRow();
                $table->addCell()->addText($name, ['size' => 10]);

                $cell = $table->addCell();

                foreach ($items as $item) {
                    $title = $item['title'] ?? '';
                    $filePath = $item['file_path'] ?? '';
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg','jpeg','png']);

                    if ($title !== '') {
                        $cell->addText($title, ['bold' => true, 'size' => 10]);
                    }

                    // Try to embed image from filesystem if available
                    if ($filePath && $isImage) {
                        $fsPath = realpath(__DIR__ . '/../alumni/' . ltrim($filePath, '/'));
                        if ($fsPath && file_exists($fsPath)) {
                            $cell->addImage($fsPath, [
                                'width' => 180,
                                'height' => 110,
                                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                            ]);
                        }
                    } elseif ($filePath) {
                        $cell->addText(
                            'Attachment: ' . basename($filePath),
                            ['size' => 9, 'color' => '555555']
                        );
                    }

                    // Small spacer between items
                    $cell->addText('', ['size' => 1], ['spaceAfter' => 40]);
                }
            }

            $section->addText(
                'Generated by ALUMytics',
                ['size' => 10],
                ['spaceBefore' => 240]
            );

            // Stream DOCX
            $fileName = 'certifications_awards_' . date('Ymd_His') . '.docx';

            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');

            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
            exit;
        } catch (Throwable $e) {
            // Fall through to HTML .doc export on any error
        }
    }

    // Fallback: original HTML .doc export
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="certifications_awards_' . date('Ymd_His') . '.doc"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html><head><meta charset="UTF-8"><title>Certifications and Awards Report</title></head><body>';
    echo '<h2>Certifications and Awards</h2>';
    echo '<p>Generated on ' . date('Y-m-d H:i:s') . '</p>';

    echo '<table border="1" cellspacing="0" cellpadding="4" style="width:100%; border-collapse:collapse; font-size:11px;">';
    // Match the green header styling used in other reports
    echo '<tr style="background-color:#219653; color:#ffffff;">';
    echo '<th style="width:25%;">NAME</th>';
    echo '<th style="width:75%;">CERTIFICATION/TRAINING</th>';
    echo '</tr>';

    // Build base URL for absolute image paths (needed so Word can load images)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Derive project root from the current script path (this file is under /<root>/staff/...)
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    // Go up one level from /staff to reach the web root of the project
    $projectRoot = rtrim(dirname($scriptDir), '/');
    $baseUrl = rtrim($scheme . $host . $projectRoot, '/');

    foreach ($grouped as $name => $items) {
        echo '<tr>';
        echo '<td style="vertical-align:top;">' . htmlspecialchars($name) . '</td>';
        echo '<td style="vertical-align:top;">';

        foreach ($items as $item) {
            $title = $item['title'] ?? '';
            $filePath = $item['file_path'] ?? '';
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg','jpeg','png']);
            // For Word export, use absolute URLs so the images load correctly
            $displayPath = $filePath ? $baseUrl . '/alumni/' . ltrim($filePath, '/') : '';

            if ($title !== '') {
                echo '<div style="margin-bottom:4px; font-weight:bold; text-align:center;">' . htmlspecialchars($title) . '</div>';
            }

            if ($displayPath && $isImage) {
                // Use fixed width/height attributes so Word renders a small centered image similar to the sample layout
                echo '<div style="margin-bottom:8px; text-align:center;"><img src="' . htmlspecialchars($displayPath) . '" width="180" height="110" alt="Certification or Award Image"></div>';
            } elseif ($displayPath) {
                echo '<div style="margin-bottom:8px; font-size:10px; color:#555555;">Attachment: ' . htmlspecialchars(basename($displayPath)) . '</div>';
            }

            echo '<div style="height:4px;"></div>';
        }

        echo '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '<p>Generated by ALUMytics</p>';
    echo '</body></html>';
    exit;
}

// Simple pagination for display (exports still use full $certAwardRows)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$perPage = 5;
$totalRows = count($certAwardRows);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pageRows = array_slice($certAwardRows, $offset, $perPage);

// Split paginated rows into certifications and awards for separate tables
$pageCertRows = [];
$pageAwardRows = [];
foreach ($pageRows as $row) {
    $type = $row['type'] ?? '';
    if ($type === 'certification') {
        $pageCertRows[] = $row;
    } elseif ($type === 'award') {
        $pageAwardRows[] = $row;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/report-generation.css">
<link rel="stylesheet" href="css/report-generation-tracer.css">
<link rel="stylesheet" href="css/report-generation-cert-awards.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="content-wrapper">
<main class="main-content dashboard-page">
    <div class="header dashboard-header">
        <div class="header-top d-flex justify-content-between align-items-start flex-wrap">
            <div class="mb-2 mb-md-0">
                <h1 class="mb-0 dashboard-title">Certifications &amp; Awards Report</h1>
                <?php if (isCollegeRestricted() && !empty($college_name)): ?>
                    <p class="dashboard-subtitle mb-0">Reporting for: <strong><?= htmlspecialchars($college_name) ?></strong></p>
                <?php else: ?>
                    <p class="dashboard-subtitle mb-0">Summary of alumni certifications and awards in table form for export.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card dashboard-card shadow-sm ca-filter-card mt-1">
        <div class="card-body">
            <div class="filter-panel-title mb-3"><i class="fas fa-filter"></i> Report Filters</div>
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3 col-lg-3 filter-dropdown">
                    <label for="filterSchool" class="form-label">School / University</label>
                    <select id="filterSchool" name="school_university" class="form-select form-select-sm">
                        <option value="all" <?= $filterSchool === 'all' ? 'selected' : '' ?>>All Schools</option>
                        <?php if ($schoolsResult): ?>
                            <?php $schoolsResult->data_seek(0); while ($row = $schoolsResult->fetch_assoc()): ?>
                                <?php $school = $row['school_university']; if (!$school) continue; ?>
                                <option value="<?= htmlspecialchars($school) ?>" <?= $filterSchool === $school ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($school) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3 col-lg-3 filter-dropdown">
                    <label for="filterDepartment" class="form-label">Department</label>
                    <select id="filterDepartment" name="department" class="form-select form-select-sm">
                        <option value="all" <?= $filterDepartment === 'all' ? 'selected' : '' ?>>All Departments</option>
                        <?php if ($departments): ?>
                            <?php $departments->data_seek(0); while ($row = $departments->fetch_assoc()): ?>
                                <?php $dept = $row['college_department']; if (!$dept) continue; ?>
                                <option value="<?= htmlspecialchars($dept) ?>" <?= $filterDepartment === $dept ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3 col-lg-3 filter-dropdown">
                    <label for="filterYear" class="form-label">Year Graduated</label>
                    <select id="filterYear" name="year_graduated" class="form-select form-select-sm">
                        <option value="all" <?= $filterYear === 'all' ? 'selected' : '' ?>>All Years</option>
                        <?php if ($yearsResult): ?>
                            <?php $yearsResult->data_seek(0); while ($row = $yearsResult->fetch_assoc()): ?>
                                <?php $yearVal = $row['year_graduated']; if (!$yearVal) continue; ?>
                                <option value="<?= htmlspecialchars($yearVal) ?>" <?= $filterYear == $yearVal ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($yearVal) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3 col-lg-3 filter-dropdown">
                    <label for="filterType" class="form-label">Show</label>
                    <select id="filterType" name="cert_type" class="form-select form-select-sm">
                        <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Certifications and Awards</option>
                        <option value="certification" <?= $filterType === 'certification' ? 'selected' : '' ?>>Certifications only</option>
                        <option value="award" <?= $filterType === 'award' ? 'selected' : '' ?>>Awards only</option>
                    </select>
                </div>
                <div class="col-md-auto ms-md-auto ca-filter-actions">
                    <button type="submit" class="btn btn-success btn-sm px-4">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card dashboard-card shadow-sm ca-table-card mt-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">Certifications and Awards</h5>
                </div>
                <div class="btn-group btn-group-sm rt-export-actions" role="group" aria-label="Export certifications and awards table">
                    <button type="button" class="btn btn-outline-primary" id="exportCertAwardsPdfBtn">PDF</button>
                    <button type="button" class="btn btn-outline-success" id="exportCertAwardsExcelBtn">Excel</button>
                    <button type="button" class="btn btn-outline-info" id="exportCertAwardsCsvBtn">CSV</button>
                    <button type="button" class="btn btn-outline-secondary" id="exportCertAwardsWordBtn">Word</button>
                </div>
            </div>

            <?php
                $showCertTable = ($filterType === 'all' || $filterType === 'certification');
                $showAwardTable = ($filterType === 'all' || $filterType === 'award');
            ?>

            <?php if ($showCertTable): ?>
                <h6 class="ca-section-title">Certifications</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-striped align-middle ca-table mb-0">
                        <thead>
                            <tr>
                                <th class="ca-name-col">Name</th>
                                <th class="ca-detail-col">Certification and Attached Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pageCertRows)): ?>
                                <tr>
                                    <td colspan="2" class="text-muted text-center">No certifications available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pageCertRows as $row): ?>
                                    <?php
                                        $name = $row['name'] ?? '';
                                        $title = $row['title'] ?? '';
                                        $filePath = $row['file_path'] ?? '';
                                        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                                        $isImage = in_array($ext, ['jpg','jpeg','png']);
                                        $displayPath = $filePath ? '../alumni/' . ltrim($filePath, '/') : '';
                                    ?>
                                    <tr>
                                        <td class="ca-name-col"><?= htmlspecialchars($name) ?></td>
                                        <td class="ca-detail-col">
                                            <div class="mb-1 fw-semibold"><?= htmlspecialchars($title) ?></div>
                                            <?php if ($displayPath && $isImage): ?>
                                                <img src="<?= htmlspecialchars($displayPath) ?>" alt="Certification Image" class="img-fluid border ca-attachment-img">
                                            <?php elseif ($displayPath): ?>
                                                <span class="text-muted">Attachment: <?= htmlspecialchars(basename($displayPath)) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No attachment provided.</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($showAwardTable): ?>
                <h6 class="ca-section-title mt-3">Awards</h6>
                <div class="table-responsive">
                    <table class="table table-striped align-middle ca-table mb-0">
                        <thead>
                            <tr>
                                <th class="ca-name-col">Name</th>
                                <th class="ca-detail-col">Award and Attached Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pageAwardRows)): ?>
                                <tr>
                                    <td colspan="2" class="text-muted text-center">No awards available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pageAwardRows as $row): ?>
                                    <?php
                                        $name = $row['name'] ?? '';
                                        $title = $row['title'] ?? '';
                                        $filePath = $row['file_path'] ?? '';
                                        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                                        $isImage = in_array($ext, ['jpg','jpeg','png']);
                                        $displayPath = $filePath ? '../alumni/' . ltrim($filePath, '/') : '';
                                    ?>
                                    <tr>
                                        <td class="ca-name-col"><?= htmlspecialchars($name) ?></td>
                                        <td class="ca-detail-col">
                                            <div class="mb-1 fw-semibold"><?= htmlspecialchars($title) ?></div>
                                            <?php if ($displayPath && $isImage): ?>
                                                <img src="<?= htmlspecialchars($displayPath) ?>" alt="Award Image" class="img-fluid border ca-attachment-img">
                                            <?php elseif ($displayPath): ?>
                                                <span class="text-muted">Attachment: <?= htmlspecialchars(basename($displayPath)) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No attachment provided.</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Certifications and Awards pagination" class="ca-table-footer">
                    <ul class="pagination pagination-sm justify-content-end mb-0">
                        <?php
                        $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
                        $query = $_GET;
                        for ($p = 1; $p <= $totalPages; $p++):
                            $query['page'] = $p;
                            $url = $baseUrl . '?' . http_build_query($query);
                            $active = $p === $page ? ' active' : '';
                        ?>
                            <li class="page-item<?= $active ?>">
                                <a class="page-link" href="<?= htmlspecialchars($url) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="js/export-libraries.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const certAwardData = <?php echo json_encode($certAwardRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    function buildExportData() {
        const rows = [];
        // Title + header row only; description sentence removed to avoid being cut in PDF
        rows.push(['CERTIFICATIONS AND AWARDS']);
        rows.push(['']);
        rows.push(['Name', 'Certification / Award']);
        certAwardData.forEach(function (item) {
            rows.push([
                item.name || '',
                item.title || ''
            ]);
        });
        return rows;
    }

    function exportToPdf() {
        (function () {
            // Use jsPDF to render a compact two-column table (Name / Certification) without images
            let JsPDFCtor = null;
            if (window.jspdf && window.jspdf.jsPDF) {
                JsPDFCtor = window.jspdf.jsPDF;
            } else if (window.jsPDF) {
                JsPDFCtor = window.jsPDF;
            }
            if (!JsPDFCtor) {
                alert('PDF library (jsPDF) not loaded.');
                return;
            }

            const doc = new JsPDFCtor({ orientation: 'p', unit: 'mm', format: 'a4' });
            const marginLeft = 15;
            const marginTop = 20;
            const marginRight = 15;
            const lineHeight = 5;

            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const tableWidth = pageWidth - marginLeft - marginRight;
            const col1Width = tableWidth * 0.35; // Name
            const col2Width = tableWidth * 0.65; // Certification

            let y = marginTop;

            // Title
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(16);
            doc.text('Certifications and Awards Report', pageWidth / 2, y, { align: 'center' });
            y += 10;

            // Table header
            doc.setFontSize(11);
            doc.setFont('helvetica', 'bold');
            const headerHeight = 9;
            doc.rect(marginLeft, y, col1Width, headerHeight);
            doc.rect(marginLeft + col1Width, y, col2Width, headerHeight);
            // Add a bit more vertical padding so text is not on the border line
            doc.text('Name', marginLeft + 2, y + 6);
            doc.text('Certification / Award', marginLeft + col1Width + 2, y + 6);
            y += headerHeight;

            // Rows
            doc.setFont('helvetica', 'normal');
            const rows = certAwardData || [];
            rows.forEach(function (item) {
                const name = String(item.name || '');
                const title = String(item.title || '');

                // New page if needed
                if (y > pageHeight - 20) {
                    doc.addPage();
                    y = marginTop;
                    // redraw header
                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(11);
                    doc.rect(marginLeft, y, col1Width, headerHeight);
                    doc.rect(marginLeft + col1Width, y, col2Width, headerHeight);
                    doc.text('Name', marginLeft + 2, y + 6);
                    doc.text('Certification / Award', marginLeft + col1Width + 2, y + 6);
                    y += headerHeight;
                    doc.setFont('helvetica', 'normal');
                }

                // Measure wrapped text to compute row height
                const nameLines = doc.splitTextToSize(name, col1Width - 4);
                const titleLines = doc.splitTextToSize(title, col2Width - 4);
                const maxLines = Math.max(nameLines.length, titleLines.length) || 1;
                const rowHeight = Math.max(9, maxLines * lineHeight + 2); // +2 for vertical padding

                // Draw cell borders
                doc.rect(marginLeft, y, col1Width, rowHeight);
                doc.rect(marginLeft + col1Width, y, col2Width, rowHeight);

                // Draw text
                let textY = y + 6; // small top padding
                nameLines.forEach(function (line) {
                    doc.text(line, marginLeft + 2, textY);
                    textY += lineHeight;
                });

                textY = y + 6;
                titleLines.forEach(function (line) {
                    doc.text(line, marginLeft + col1Width + 2, textY);
                    textY += lineHeight;
                });

                y += rowHeight;
            });

            const ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            const filename = 'certifications_awards_' + ts + '.pdf';
            doc.save(filename);
        })();
    }

    function exportToExcel() {
        if (!window.ExportLibraries) {
            alert('Export library not loaded.');
            return;
        }
        const data = buildExportData();
        const ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        const filename = 'certifications_awards_' + ts + '.xlsx';
        window.ExportLibraries.exportToExcel(data, filename, { sheetName: 'CertificationsAwards' });
    }

    function exportToCsv() {
        if (!window.ExportLibraries) {
            alert('Export library not loaded.');
            return;
        }
        const data = buildExportData();
        const ts = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        const filename = 'certifications_awards_' + ts + '.csv';
        window.ExportLibraries.exportToCSV(data, filename, {});
    }

    function exportToWord() {
        // Use server-side HTML Word export so layout (including images) matches the on-screen table
        const url = new URL(window.location.href);
        url.searchParams.set('export', 'word');
        window.location.href = url.toString();
    }

    const pdfBtn = document.getElementById('exportCertAwardsPdfBtn');
    const excelBtn = document.getElementById('exportCertAwardsExcelBtn');
    const csvBtn = document.getElementById('exportCertAwardsCsvBtn');
    const wordBtn = document.getElementById('exportCertAwardsWordBtn');

    if (pdfBtn) pdfBtn.addEventListener('click', function (e) { e.preventDefault(); exportToPdf(); });
    if (excelBtn) excelBtn.addEventListener('click', function (e) { e.preventDefault(); exportToExcel(); });
    if (csvBtn) csvBtn.addEventListener('click', function (e) { e.preventDefault(); exportToCsv(); });
    if (wordBtn) wordBtn.addEventListener('click', function (e) { e.preventDefault(); exportToWord(); });
});
</script>
