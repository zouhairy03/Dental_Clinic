<?php
require_once 'config.php';
// Restrict access if not logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Doctor');
// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize inputs
    $id = $_POST['id'] ?? null;
    $patient_id = $_POST['patient_id'] ?? null;
    $appointment_date = $_POST['appointment_date'] ?? null;
    $appointment_time = $_POST['appointment_time'] ?? null;
    $treatment_type = $_POST['treatment_type'] ?? null;
    $status = $_POST['status'] ?? 'scheduled';
    $notes = $_POST['notes'] ?? null;

    // Validate required fields
    if ($id && $patient_id && $appointment_date && $appointment_time) {
        try {
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET patient_id = :patient_id,
                    appointment_date = :appointment_date,
                    appointment_time = :appointment_time,
                    treatment_type = :treatment_type,
                    status = :status,
                    notes = :notes
                WHERE id = :id
            ");

            $stmt->execute([
                ':patient_id' => $patient_id,
                ':appointment_date' => $appointment_date,
                ':appointment_time' => $appointment_time,
                ':treatment_type' => $treatment_type,
                ':status' => $status,
                ':notes' => $notes,
                ':id' => $id
            ]);

            // Redirect back with success message
            $_SESSION['flash_success'] = "Appointment updated successfully.";
            header("Location: appointments.php");
            exit;

        } catch (PDOException $e) {
            error_log("Update failed: " . $e->getMessage());
            $_SESSION['flash_error'] = "An error occurred while updating the appointment.";
        }
    } else {
        $_SESSION['flash_error'] = "Please fill in all required fields.";
    }
} else {
    $_SESSION['flash_error'] = "Invalid request method.";
}

// Redirect back to calendar
header("Location: calendar.php");
exit;
