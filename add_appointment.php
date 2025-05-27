<?php
require_once 'config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'] ?? null;
    $appointment_date = $_POST['appointment_date'] ?? null;
    $appointment_time = $_POST['appointment_time'] ?? null;
    $treatment_type = trim($_POST['treatment_type'] ?? '');
    $status = $_POST['status'] ?? 'scheduled';
    $notes = trim($_POST['notes'] ?? '');

    // Validate required fields
    if ($patient_id && $appointment_date && $appointment_time) {
        try {
            $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, appointment_date, appointment_time, treatment_type, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patient_id,
                $appointment_date,
                $appointment_time,
                $treatment_type,
                $status,
                $notes
            ]);

            // Redirect back to appointments.php
            header("Location: appointments.php?success=1");
            exit;
        } catch (PDOException $e) {
            error_log("Insert Appointment Error: " . $e->getMessage());
            header("Location: appointments.php?error=1");
            exit;
        }
    } else {
        header("Location: appointments.php?error=1");
        exit;
    }
} else {
    // Invalid access
    header("Location: appointments.php");
    exit;
}
