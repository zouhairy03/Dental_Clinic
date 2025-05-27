<?php
// Database configuration
$host = 'localhost';
$dbname = 'dental_clinic';
$username = 'root';
$password = 'root';
$charset = 'utf8mb4';

// Set DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Create PDO instance
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Log error and exit (don't display sensitive info)
    error_log('Connection failed: ' . $e->getMessage());
    exit('Database connection error. Please try again later.');
}

// Set default timezone
date_default_timezone_set('Africa/Casablanca'); // Example: 'Europe/London'

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
?>