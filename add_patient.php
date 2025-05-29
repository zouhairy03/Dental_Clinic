<?php
require_once 'config.php';

// Ensure form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $cna = trim($_POST['cna'] ?? ''); // Added CNA field
    $address = trim($_POST['address'] ?? '');
    $working_type = $_POST['working_type'] ?? '';
    $age = (int)($_POST['age'] ?? 0);

    // Basic validation
    if ($full_name && $phone && $working_type && $age > 0) {
        try {
            // Updated to include CNA and address fields
            $stmt = $pdo->prepare("
                INSERT INTO patients 
                (full_name, phone, cna, address, working_type, age) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $full_name, 
                $phone, 
                $cna, // CNA parameter
                $address,
                $working_type, 
                $age
            ]);

            // Redirect back to patients.php with success
            header("Location: patients.php?success=1");
            exit;
        } catch (PDOException $e) {
            // More detailed error logging
            error_log("Add patient error: " . $e->getMessage());
            
            // Redirect with specific error code
            header("Location: patients.php?error=db");
            exit;
        }
    } else {
        // Redirect with validation error code
        header("Location: patients.php?error=validation");
        exit;
    }
} else {
    // Reject direct access
    header("Location: patients.php");
    exit;
}