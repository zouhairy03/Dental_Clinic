<?php
include 'config.php';

// Restrict access if not logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Doctor');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
    if ($stmt->execute([$id])) {
        header("Location: patients.php?success=deleted");
    } else {
        header("Location: patients.php?error=delete_fail");
    }
} else {
    header("Location: patients.php");
}
exit();
