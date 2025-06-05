<?php
require_once 'config.php';
// Restrict access if not logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Doctor');
$id = $_GET['id'] ?? $_POST['id'] ?? null;

// Redirect if ID is missing or invalid
if (!$id || !is_numeric($id)) {
    $_SESSION['flash_error'] = "Invalid appointment ID.";
    header("Location: appointments.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Perform deletion
    try {
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $_SESSION['flash_success'] = "Appointment deleted successfully.";
        header("Location: appointments.php");
        exit;
    } catch (PDOException $e) {
        error_log("Delete failed: " . $e->getMessage());
        $_SESSION['flash_error'] = "An error occurred while deleting the appointment.";
        header("Location: appointments.php");
        exit;
    }
}

// Fetch appointment for optional display (not required but good UX)
$stmt = $pdo->prepare("SELECT a.id, a.appointment_date, a.appointment_time, p.full_name 
                       FROM appointments a 
                       LEFT JOIN patients p ON a.patient_id = p.id 
                       WHERE a.id = ?");
$stmt->execute([$id]);
$appointment = $stmt->fetch();

// Redirect if not found
if (!$appointment) {
    $_SESSION['flash_error'] = "Appointment not found.";
    header("Location: appointments.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Deletion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .confirm-box {
            max-width: 500px;
            margin: 10% auto;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="card shadow confirm-box">
        <div class="card-body">
            <h4 class="text-danger mb-3"><i class="fas fa-trash me-2"></i>Confirm Deletion</h4>
            <p>Are you sure you want to permanently delete the following appointment?</p>
            <ul class="list-unstyled mb-3">
                <li><strong>Patient:</strong> <?= htmlspecialchars($appointment['full_name']) ?></li>
                <li><strong>Date:</strong> <?= $appointment['appointment_date'] ?></li>
                <li><strong>Time:</strong> <?= $appointment['appointment_time'] ?></li>
            </ul>
            <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
                <a href="appointments.php" class="btn btn-secondary">No, Cancel</a>
            </form>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
