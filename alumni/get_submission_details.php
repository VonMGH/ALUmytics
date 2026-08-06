<?php
include 'database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

if (!$type || !$id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$conn = Database::getInstance()->getConnection();
$details = null;

try {
    switch ($type) {
        case 'employment':
            // Match Aemployment.php: include fallback company address from company_address table
            $stmt = $conn->prepare("SELECT e.*, ca.company_street_address AS ca_street, ca.company_province AS ca_province, ca.company_city AS ca_city, ca.company_barangay AS ca_barangay FROM employment e LEFT JOIN company_address ca ON ca.user_id = e.user_id WHERE e.id = ? AND e.user_id = ?");
            $stmt->bind_param('ii', $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $details = $result->fetch_assoc();
            break;
            
        case 'certification':
            $stmt = $conn->prepare("SELECT * FROM certifications WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $details = $result->fetch_assoc();
            break;
            
        case 'award':
            $stmt = $conn->prepare("SELECT * FROM awards WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $details = $result->fetch_assoc();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid submission type']);
            exit();
    }
    
    if ($details) {
        // Add formatted submission timestamp
        if ($type === 'employment') {
            $submission_timestamp = $details['updated_at'] ?? $details['created_at'] ?? null;
        } else if ($type === 'certification') {
            $submission_timestamp = $details['created_at'] ?? null;
        } else if ($type === 'award') {
            $submission_timestamp = $details['created_at'] ?? null;
        }
        
        if ($submission_timestamp) {
            $details['submission_timestamp'] = date('F d, Y \a\t g:i A', strtotime($submission_timestamp));
        } else {
            $details['submission_timestamp'] = 'Not available';
        }
        
        echo json_encode(['success' => true, 'details' => $details]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Submission not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 