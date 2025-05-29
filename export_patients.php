<?php
require_once 'config.php';

// Set headers
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=patients_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Output with UTF-8 BOM to support special characters
echo "\xEF\xBB\xBF";

echo <<<HTML
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th {
            background-color: #f2f2f2;
            color: #333;
            border: 1px solid #000;
            padding: 8px;
            font-weight: bold;
        }
        td {
            border: 1px solid #000;
            padding: 8px;
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Phone</th>
            <th>Working Type</th>
            <th>Age</th>
            <th>Created At</th>
        </tr>
HTML;

// Fetch and print patient data
$stmt = $pdo->query("SELECT * FROM patients ORDER BY id ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
    echo "<td>" . htmlspecialchars($row['working_type']) . "</td>";
    echo "<td>{$row['age']}</td>";
    echo "<td>{$row['created_at']}</td>";
    echo "</tr>";
}

echo <<<HTML
    </table>
</body>
</html>
HTML;

exit;
