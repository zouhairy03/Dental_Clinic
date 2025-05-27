<?php
require_once 'config.php';

// Set headers to trigger download as .xls file
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=patients_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Output starts here
echo "<table border='1'>";
echo "<tr>
    <th>ID</th>
    <th>Full Name</th>
    <th>Phone</th>
    <th>Working Type</th>
    <th>Age</th>
    <th>Created At</th>
</tr>";

// Fetch all patient data
$stmt = $pdo->query("SELECT * FROM patients ORDER BY id ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
    echo "<td>" . htmlspecialchars($row['working_type']) . "</td>";
    echo "<td>" . $row['age'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";
exit;
