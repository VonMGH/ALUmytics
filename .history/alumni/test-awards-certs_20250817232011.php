<?php
// Test script to check if awards and certifications are being inserted correctly
include 'database.php';
session_start();

// Test user ID - change this to a valid user ID in your system
$test_user_id = 1;

$conn = Database::getInstance()->getConnection();

echo "<h2>Awards for User ID: $test_user_id</h2>";
$stmt = $conn->prepare("SELECT * FROM awards WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $test_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Award Name</th><th>Award Title</th><th>Category</th><th>Awarded By</th><th>Award Year</th><th>Award Date</th><th>Description</th><th>File</th><th>Created At</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['award_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['award_title'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['category'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['awarded_by'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['award_year'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['award_date'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['description'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['award_file'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No awards found.";
}

echo "<h2>Certifications for User ID: $test_user_id</h2>";
$stmt = $conn->prepare("SELECT * FROM certifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $test_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Cert Name</th><th>Category</th><th>Issuing Body</th><th>Industry</th><th>Cert Date</th><th>File</th><th>Created At</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['certification_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['category'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['issuing_body'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['industry'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['certification_date'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['certification_file'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No certifications found.";
}
?>

<style>
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; }
</style>
