<?php
include '../db/Database.php';
include 'includes/access_control.php';

requireModuleAccess('report-generation');

$conn = Database::getInstance()->getConnection();

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$stmt = $conn->prepare("SELECT u.user_id, u.full_name, u.email, u.role, u.college_id, c.name AS college_name
                        FROM users u
                        LEFT JOIN colleges c ON u.college_id = c.id
                        WHERE u.user_id = ? AND u.role = 'alumni' LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow) {
    http_response_code(404);
    echo 'Alumni record not found.';
    exit;
}

if (isCollegeRestricted() && isset($college_id) && $college_id) {
    $allowed = false;

    // First, allow if the direct college_id on users matches
    if ((int)$userRow['college_id'] === (int)$college_id) {
        $allowed = true;
    } else {
        // Fallback: allow if alumni belongs to this college via education.college_department
        $eduCheck = $conn->prepare("SELECT 1 FROM education e WHERE e.user_id = ? AND e.college_department = (SELECT name FROM colleges WHERE id = ?) LIMIT 1");
        $eduCheck->bind_param('ii', $userId, $college_id);
        $eduCheck->execute();
        $hasRow = $eduCheck->get_result()->fetch_assoc();
        $eduCheck->close();
        if ($hasRow) {
            $allowed = true;
        }
    }

    if (!$allowed) {
        http_response_code(403);
        echo 'You are not allowed to view this alumni record.';
        exit;
    }
}

$personal = [];
$pstmt = $conn->prepare("SELECT first_name, middle_name, last_name, sex, dob, phone_number, institutional_email, personal_email, civil_status
                        FROM personal WHERE user_id = ? LIMIT 1");
$pstmt->bind_param('i', $userId);
$pstmt->execute();
$pres = $pstmt->get_result()->fetch_assoc();
$pstmt->close();
if ($pres) $personal = $pres;

$education = [];
$est = $conn->prepare("SELECT school_university, campus_branch, college_department, program, major_specialization, alumni_id, year_graduated
                      FROM education WHERE user_id = ? LIMIT 1");
$est->bind_param('i', $userId);
$est->execute();
$ed = $est->get_result()->fetch_assoc();
$est->close();
if ($ed) $education = $ed;

$employment = [];
$empstmt = $conn->prepare("SELECT job_title, employment_status, company_name, company_country, company_province, mobility, year_of_employment
                          FROM employment WHERE user_id = ? ORDER BY FIELD(job_status,'current') DESC, id DESC LIMIT 1");
$empstmt->bind_param('i', $userId);
$empstmt->execute();
$er = $empstmt->get_result()->fetch_assoc();
$empstmt->close();
if ($er) $employment = $er;

$fullName = $userRow['full_name'];
$alumniId = $education['alumni_id'] ?? '';
$yearGrad = $education['year_graduated'] ?? '';
$dept = $education['college_department'] ?? $userRow['college_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Alumni Report - <?= htmlspecialchars($fullName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .report-header { border-bottom: 2px solid #2e7d32; padding-bottom: 8px; margin-bottom: 18px; }
        .label { font-weight: 600; color: #555; }
        .section-title { font-size: 1.05rem; font-weight: 700; color: #2e7d32; margin-top: 18px; margin-bottom: 8px; }
        @media print {
            #printActions,
            .no-print { display: none !important; }
            body { padding: 0.5cm; }
            .card { box-shadow: none !important; border: none !important; }
        }
    </style>
</head>
<body class="bg-light">
<div class="container my-4">
    <div id="printActions" class="d-flex justify-content-end mb-3 no-print">
        <button class="btn btn-success me-2" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="report-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Alumni Details</h2>
                    <div class="text-muted">Generated from ALUMytics Alumni Tracking</div>
                </div>
                <div class="text-end">
                    <?php if ($alumniId): ?>
                        <div><span class="label">Alumni ID:</span> <?= htmlspecialchars($alumniId) ?></div>
                    <?php endif; ?>
                    <?php if ($yearGrad): ?>
                        <div><span class="label">Year Graduated:</span> <?= htmlspecialchars($yearGrad) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="section-title">Personal Information</div>
                    <p class="mb-1"><span class="label">Name:</span> <?= htmlspecialchars($fullName) ?></p>
                    <p class="mb-1"><span class="label">Email:</span> <?= htmlspecialchars($userRow['email']) ?></p>
                    <?php if (!empty($personal['institutional_email'])): ?>
                        <p class="mb-1"><span class="label">Institutional Email:</span> <?= htmlspecialchars($personal['institutional_email']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($personal['personal_email'])): ?>
                        <p class="mb-1"><span class="label">Personal Email:</span> <?= htmlspecialchars($personal['personal_email']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($personal['phone_number'])): ?>
                        <p class="mb-1"><span class="label">Contact Number:</span> <?= htmlspecialchars($personal['phone_number']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($personal['sex'])): ?>
                        <p class="mb-1"><span class="label">Gender:</span> <?= htmlspecialchars(ucfirst(strtolower($personal['sex']))) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($personal['civil_status'])): ?>
                        <p class="mb-1"><span class="label">Civil Status:</span> <?= htmlspecialchars($personal['civil_status']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="section-title">Academic Information</div>
                    <?php if (!empty($education['school_university'])): ?>
                        <p class="mb-1"><span class="label">School/University:</span> <?= htmlspecialchars($education['school_university']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($education['campus_branch'])): ?>
                        <p class="mb-1"><span class="label">Campus/Branch:</span> <?= htmlspecialchars($education['campus_branch']) ?></p>
                    <?php endif; ?>
                    <?php if ($dept): ?>
                        <p class="mb-1"><span class="label">College/Department:</span> <?= htmlspecialchars($dept) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($education['program'])): ?>
                        <p class="mb-1"><span class="label">Program:</span> <?= htmlspecialchars($education['program']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($education['major_specialization'])): ?>
                        <p class="mb-1"><span class="label">Major/Specialization:</span> <?= htmlspecialchars($education['major_specialization']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-title">Employment (Latest)</div>
            <?php if ($employment): ?>
                <div class="row">
                    <div class="col-md-6">
                        <?php if (!empty($employment['job_title'])): ?>
                            <p class="mb-1"><span class="label">Job Title:</span> <?= htmlspecialchars($employment['job_title']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($employment['employment_status'])): ?>
                            <p class="mb-1"><span class="label">Employment Status:</span> <?= htmlspecialchars(ucfirst(str_replace('_',' ', $employment['employment_status']))) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($employment['year_of_employment'])): ?>
                            <p class="mb-1"><span class="label">Year of Employment:</span> <?= htmlspecialchars($employment['year_of_employment']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($employment['company_name'])): ?>
                            <p class="mb-1"><span class="label">Company:</span> <?= htmlspecialchars($employment['company_name']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($employment['company_province']) || !empty($employment['company_country'])): ?>
                            <p class="mb-1"><span class="label">Location:</span>
                                <?= htmlspecialchars($employment['company_province'] ?? '') ?>
                                <?php if (!empty($employment['company_country'])): ?>
                                    (<?= htmlspecialchars($employment['company_country']) ?>)
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($employment['mobility'])): ?>
                            <p class="mb-1"><span class="label">Mobility:</span> <?= htmlspecialchars(ucfirst($employment['mobility'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted">No employment record available.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://kit.fontawesome.com/a2e0e6ad65.js" crossorigin="anonymous"></script>
</body>
</html>
