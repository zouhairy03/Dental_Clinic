<?php
require_once 'config.php';

// Ensure form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $working_type = $_POST['working_type'] ?? '';
    $age = (int)($_POST['age'] ?? 0);

    // Basic validation
    if ($full_name && $phone && $working_type && $age > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO patients (full_name, phone, working_type, age) VALUES (?, ?, ?, ?)");
            $stmt->execute([$full_name, $phone, $working_type, $age]);

            // Redirect back to patients.php with success
            header("Location: patients.php?success=1");
            exit;
        } catch (PDOException $e) {
            error_log("Add patient error: " . $e->getMessage());
            header("Location: patients.php?error=1");
            exit;
        }
    } else {
        // Redirect back if validation fails
        header("Location: patients.php?error=1");
        exit;
    }
} else {
    // Reject direct access
    header("Location: patients.php");
    exit;
}
