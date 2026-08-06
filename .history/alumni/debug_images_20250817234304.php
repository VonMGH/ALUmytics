<?php
include 'database.php';

echo "<h2>Debug Profile Photos</h2>";
echo "<p>Checking profile photo paths in database...</p>";

$stmt = $conn->prepare("SELECT user_id, first_name, last_name, profile_photo FROM personal WHERE profile_photo IS NOT NULL AND profile_photo != ''");
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1'>";
echo "<tr><th>User ID</th><th>Name</th><th>Profile Photo Path</th><th>File Exists?</th><th>Image Preview</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['profile_photo']) . "</td>";
    echo "<td>" . (file_exists($row['profile_photo']) ? 'YES' : 'NO') . "</td>";
    echo "<td>";
    if ($row['profile_photo']) {
        echo "<img src='" . htmlspecialchars($row['profile_photo']) . "' width='50' height='50' style='object-fit:cover;' onerror=\"this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIGZpbGw9IiNkZGQiPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5YPC90ZXh0Pjwvc3ZnPg=='\" />";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Directory Contents:</h3>";
$upload_dir = 'uploads/';
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && !is_dir($upload_dir . $file)) {
            echo "<li>" . htmlspecialchars($file) . " (size: " . filesize($upload_dir . $file) . " bytes)</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p>Upload directory doesn't exist!</p>";
}

echo "<h3>Current Working Directory:</h3>";
echo "<p>" . getcwd() . "</p>";

$stmt->close();
$conn->close();
?>
