<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $patientId = (int)$_GET['id'];
    
    // Fetch patient details
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        http_response_code(404);
        echo json_encode(['error' => 'Patient not found']);
        exit;
    }
    
    // Fetch patient appointments
    $stmt = $pdo->prepare("
        SELECT a.*, d.name AS dentist_name 
        FROM appointments a
        LEFT JOIN dentists d ON a.dentist_id = d.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$patientId]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add status style for better visibility
    foreach ($appointments as &$appointment) {
        $appointment['status_style'] = match ($appointment['status']) {
            'scheduled' => 'color: white; background-color: #2196F3; padding: 2px 8px; border-radius: 4px;',
            'completed' => 'color: white; background-color: #4CAF50; padding: 2px 8px; border-radius: 4px;',
            'cancelled' => 'color: white; background-color: #F44336; padding: 2px 8px; border-radius: 4px;',
            default => ''
        };
    }
    unset($appointment); // Break reference
    
    $patient['appointments'] = $appointments;
    
    header('Content-Type: application/json');
    echo json_encode($patient);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
exit; // Ensure script stops after sending error
?>