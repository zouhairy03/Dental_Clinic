<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header("Location: patients.php");
    exit();
}

$id = $_GET['id'];

// Fetch patient info
$stmt = $pdo->prepare("SELECT full_name FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    header("Location: patients.php?error=not_found");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            max-width: 600px;
            margin: 80px auto;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow-sm border-0 animate__animated animate__fadeInDown">
        <div class="card-body text-center">
            <h4 class="mb-3 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h4>
            <p class="mb-4">Are you sure you want to delete <strong><?= htmlspecialchars($patient['full_name']) ?></strong>?</p>
            <a href="confirm_delete.php?id=<?= $id ?>" class="btn btn-danger me-2">
                <i class="fas fa-check-circle me-1"></i> Yes, Delete
            </a>
            <a href="patients.php" class="btn btn-secondary">
                <i class="fas fa-times-circle me-1"></i> Cancel
            </a>
        </div>
    </div>
</div>

</body>
</html>
