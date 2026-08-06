<?php
include 'database.php';

$conn = Database::getInstance()->getConnection();

echo "<h3>Profile Photo Test</h3>";
echo "<p>Checking for profile photos in database...</p>";

$stmt = $conn->prepare("SELECT user_id, first_name, last_name, profile_photo FROM personal WHERE profile_photo IS NOT NULL AND profile_photo != ''");
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>User ID</th><th>Name</th><th>Photo Path</th><th>File Exists?</th><th>Image Test</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['profile_photo']) . "</td>";
    echo "<td>" . (file_exists($row['profile_photo']) ? 'YES' : 'NO') . "</td>";
    echo "<td><img src='" . htmlspecialchars($row['profile_photo']) . "' width='50' height='50' style='object-fit: cover; border-radius: 50%;' onerror=\"this.style.display='none'; this.nextSibling.style.display='inline';\" /><span style='display:none; color:red;'>❌ Failed to load</span></td>";
    echo "</tr>";
}

echo "</table>";

$stmt->close();
$conn->close();
?>
