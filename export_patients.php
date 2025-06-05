<?php
require_once 'config.php';
// Restrict access if not logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Doctor');
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
            font-family: Arial, sans-serif;
        }
        th {
            background-color: #2cb5a0;
            color: white;
            border: 1px solid #1a7c6c;
            padding: 12px;
            font-weight: bold;
            text-align: left;
        }
        td {
            border: 1px solid #ddd;
            padding: 10px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f7fa;
        }
        .clinic-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .clinic-name {
            font-size: 24px;
            font-weight: bold;
            color: #2cb5a0;
        }
        .report-title {
            font-size: 18px;
            margin: 10px 0;
            color: #333;
        }
        .report-date {
            color: #666;
            margin-bottom: 20px;
        }
        .summary {
            margin: 15px 0;
            padding: 10px;
            background-color: #f0f7fa;
            border-left: 4px solid #2cb5a0;
        }
    </style>
</head>
<body>
    <div class="clinic-header">
        <div class="clinic-name">DentalCare Clinic</div>
        <div class="report-title">Patient Records Export</div>
        <div class="report-date">Generated on: {date('F j, Y, g:i a')}</div>
    </div>
    
    <div class="summary">
        <strong>Report Summary:</strong> 
        This export contains all patient records with personal information, contact details, and medical identifiers.
    </div>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Phone</th>
            <th>Carte Nationale (CNA)</th>
            <th>Address</th>
            <th>Working Type</th>
            <th>Age</th>
            <th>Age Group</th>
            <th>Created At</th>
        </tr>
HTML;

// Fetch and print patient data
$stmt = $pdo->query("SELECT *, 
    CASE 
        WHEN age < 13 THEN 'Child'
        WHEN age BETWEEN 13 AND 19 THEN 'Teen'
        WHEN age BETWEEN 20 AND 59 THEN 'Adult'
        ELSE 'Senior'
    END AS age_group
    FROM patients ORDER BY id ASC");

$totalPatients = 0;
$totalAge = 0;
$patientsByType = [
    'student' => 0,
    'employed' => 0,
    'self-employed' => 0,
    'unemployed' => 0
];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $totalPatients++;
    $totalAge += $row['age'];
    $patientsByType[$row['working_type']]++;
    
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
    echo "<td>" . htmlspecialchars($row['cna'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($row['address'] ?? 'Not provided') . "</td>";
    echo "<td>" . ucfirst(htmlspecialchars($row['working_type'])) . "</td>";
    echo "<td>{$row['age']}</td>";
    echo "<td>{$row['age_group']}</td>";
    echo "<td>{$row['created_at']}</td>";
    echo "</tr>";
}

// Calculate average age
$averageAge = $totalPatients > 0 ? round($totalAge / $totalPatients, 1) : 0;

echo <<<HTML
    </table>
    
    <div style="margin-top: 30px;">
        <table style="width: 60%; border: none; margin-top: 20px;">
            <tr>
                <td style="border: none; font-weight: bold; background-color: #f0f7fa; padding: 10px;">Total Patients:</td>
                <td style="border: none; text-align: right; padding: 10px;">$totalPatients</td>
            </tr>
            <tr>
                <td style="border: none; font-weight: bold; background-color: #f0f7fa; padding: 10px;">Average Age:</td>
                <td style="border: none; text-align: right; padding: 10px;">$averageAge</td>
            </tr>
            <tr>
                <td style="border: none; font-weight: bold; background-color: #f0f7fa; padding: 10px;">Students:</td>
                <td style="border: none; text-align: right; padding: 10px;">{$patientsByType['student']}</td>
            </tr>
            <tr>
                <td style="border: none; font-weight: bold; background-color: #f0f7fa; padding: 10px;">Employed:</td>
                <td style="border: none; text-align: right; padding: 10px;">{$patientsByType['employed']}</td>
            </tr>
            <tr>
                <td style="border: none; font-weight: bold; background-color: #f0f7fa; padding: 10px;">Self-Employed:</td>
                <td style="border: none; text-align: right; padding: 10px;">{$patientsByType['self-employed']}</td>
            </tr>
            <tr>
                <td style="border: none; font-weight: bold; background-color: #f0f7fa; padding: 10px;">Unemployed:</td>
                <td style="border: none; text-align: right; padding: 10px;">{$patientsByType['unemployed']}</td>
            </tr>
        </table>
    </div>
    
    <div style="margin-top: 30px; font-size: 12px; color: #777; text-align: center;">
        <p>This report was generated on {date('F j, Y, g:i a')} by DentalCare Clinic Management System</p>
        <p>© {date('Y')} DentalCare Clinic. All rights reserved.</p>
    </div>
</body>
</html>
HTML;

exit;