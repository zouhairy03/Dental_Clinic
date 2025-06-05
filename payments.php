<?php
require_once 'config.php';


// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $search = $_POST['search'] ?? '';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $sort = $_POST['sort'] ?? 'payment_date';
    $order = $_POST['order'] ?? 'DESC';

    // Validate sort and order
    $allowedSorts = ['id', 'full_name', 'payment_date', 'amount'];
    if (!in_array($sort, $allowedSorts)) {
        $sort = 'payment_date';
    }
    $order = in_array(strtoupper($order), ['ASC', 'DESC']) ? strtoupper($order) : 'DESC';

    // Build export query
    $query = "SELECT p.*, pat.full_name, a.appointment_date, a.appointment_time, a.treatment_type
            FROM payments p
            LEFT JOIN patients pat ON p.patient_id = pat.id
            LEFT JOIN appointments a ON p.appointment_id = a.id
            WHERE p.payment_date BETWEEN :start_date AND :end_date";

    if (!empty($search)) {
        $query .= " AND (pat.full_name LIKE :search OR p.transaction_id LIKE :search OR p.payment_method LIKE :search)";
    }

    $query .= " ORDER BY $sort $order";

    try {
        $paymentsQuery = $pdo->prepare($query);
        $paymentsQuery->bindValue(':start_date', $start_date);
        $paymentsQuery->bindValue(':end_date', $end_date);

        if (!empty($search)) {
            $searchTerm = "%$search%";
            $paymentsQuery->bindValue(':search', $searchTerm);
        }

        $paymentsQuery->execute();
        $paymentsExport = $paymentsQuery->fetchAll();
    } catch (PDOException $e) {
        error_log("Export error: " . $e->getMessage());
        $paymentsExport = [];
    }

    // Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments_export_' . date('Ymd_His') . '.csv"');

    // Create output file pointer
    $output = fopen('php://output', 'w');

    // Output CSV headers
    fputcsv($output, ['ID', 'Patient', 'Payment Date', 'Payment Time', 'Amount (MAD)', 'Method', 'Status', 
                     'Appointment Date', 'Appointment Time', 'Treatment', 'Transaction ID', 'Notes']);

    // Output data rows
    foreach ($paymentsExport as $p) {
        fputcsv($output, [
            $p['id'],
            $p['full_name'],
            $p['payment_date'],
            $p['payment_time'],
            $p['amount'],
            $p['payment_method'],
            $p['status'],
            $p['appointment_date'] ?? '',
            $p['appointment_time'] ?? '',
            $p['treatment_type'] ?? '',
            $p['transaction_id'],
            $p['notes'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}

// Clear success message from session if set
$successMessage = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

// Initialize payments array
$payments = [];

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_payment'])) {
        $stmt = $pdo->prepare("INSERT INTO payments (patient_id, appointment_id, amount, payment_date, payment_time, payment_method, status, transaction_id, notes)
            VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['patient_id'], $_POST['appointment_id'] ?: null, $_POST['amount'],
            $_POST['payment_method'], $_POST['status'], $_POST['transaction_id'], $_POST['notes']
        ]);
        
        // Store success in session to prevent display on page reload
        $_SESSION['success'] = "Payment added successfully";
        header("Location: payments.php");
        exit;
    }
    if (isset($_POST['delete_payment'])) {
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$_POST['payment_id']]);
        
        $_SESSION['success'] = "Payment deleted successfully";
        header("Location: payments.php");
        exit;
    }
    if (isset($_POST['update_payment'])) {
        $stmt = $pdo->prepare("UPDATE payments SET patient_id = ?, appointment_id = ?, amount = ?, payment_method = ?, status = ?, transaction_id = ?, notes = ? WHERE id = ?");
        $stmt->execute([
            $_POST['patient_id'], $_POST['appointment_id'] ?: null, $_POST['amount'],
            $_POST['payment_method'], $_POST['status'], $_POST['transaction_id'], $_POST['notes'], $_POST['payment_id']
        ]);
        
        $_SESSION['success'] = "Payment updated successfully";
        header("Location: payments.php");
        exit;
    }
}

if (isset($_GET['fetch_appointments']) && isset($_GET['patient_id'])) {
    $stmt = $pdo->prepare("SELECT a.id, a.appointment_date, a.appointment_time, a.treatment_type 
                          FROM appointments a 
                          WHERE a.patient_id = ?");
    $stmt->execute([$_GET['patient_id']]);
    $appointments = $stmt->fetchAll();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($appointments);
    exit;
}

// Handle pagination, sorting and search
$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $limit;

// Sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'payment_date';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$order = in_array(strtoupper($order), ['ASC', 'DESC']) ? strtoupper($order) : 'DESC';

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get date range from request or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Build the base query
$query = "SELECT p.*, pat.full_name, a.appointment_date, a.appointment_time, a.treatment_type
        FROM payments p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN appointments a ON p.appointment_id = a.id
        WHERE p.payment_date BETWEEN :start_date AND :end_date";

// Add search condition
if (!empty($search)) {
    $query .= " AND (pat.full_name LIKE :search OR p.transaction_id LIKE :search OR p.payment_method LIKE :search)";
}

// Add sorting
$query .= " ORDER BY $sort $order";

// Add pagination
$query .= " LIMIT :limit OFFSET :offset";

// Get all payments with appointment details
try {
    $paymentsQuery = $pdo->prepare($query);
    
    // Bind parameters
    $paymentsQuery->bindValue(':start_date', $start_date);
    $paymentsQuery->bindValue(':end_date', $end_date);
    $paymentsQuery->bindValue(':limit', $limit, PDO::PARAM_INT);
    $paymentsQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $paymentsQuery->bindValue(':search', $searchTerm);
    }
    
    $paymentsQuery->execute();
    $payments = $paymentsQuery->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching payments: " . $e->getMessage());
    $payments = [];
}

// Get total count for pagination
try {
    $countQuery = "SELECT COUNT(*) 
        FROM payments p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        WHERE p.payment_date BETWEEN :start_date AND :end_date";
    
    if (!empty($search)) {
        $countQuery .= " AND (pat.full_name LIKE :search OR p.transaction_id LIKE :search OR p.payment_method LIKE :search)";
    }
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->bindValue(':start_date', $start_date);
    $countStmt->bindValue(':end_date', $end_date);
    
    if (!empty($search)) {
        $countStmt->bindValue(':search', $searchTerm);
    }
    
    $countStmt->execute();
    $totalPayments = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching payment count: " . $e->getMessage());
    $totalPayments = 0;
}

$patients = $pdo->query("SELECT id, full_name FROM patients ORDER BY full_name")->fetchAll();

// Calculate payment statistics
$totalAmount = 0;
$completedCount = 0;
$pendingCount = 0;
$refundedCount = 0;

foreach ($payments as $p) {
    $totalAmount += $p['amount'];
    if ($p['status'] === 'completed') $completedCount++;
    if ($p['status'] === 'pending') $pendingCount++;
    if ($p['status'] === 'refunded') $refundedCount++;
}

// Calculate total pages for pagination
$totalPages = ceil($totalPayments / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - DentalCare Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary: #2cb5a0;
            --secondary: #f0f7fa;
            --accent: #ff7f50;
            --completed: #1cc88a;
            --pending: #f6c23e;
            --refunded: #36b9cc;
            --failed: #e74a3b;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: url('data:image/svg+xml,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="%232cb5a033" d="M44.6,-58.1C56.3,-49.6,62.6,-33.3,66.1,-16.8C69.6,-0.3,70.4,16.5,63.9,29.1C57.4,41.7,43.7,50.2,29.9,56.9C16.1,63.6,2.2,68.5,-12.6,67.7C-27.4,66.8,-42.9,60.2,-55.4,50.3C-67.9,40.4,-77.3,27.2,-79.9,12.6C-82.5,-2.1,-78.3,-18.2,-69.3,-31.1C-60.3,-44,-46.5,-53.7,-32.3,-61.3C-18.1,-68.9,-3.5,-74.4,12.1,-71.3C27.7,-68.2,55.4,-56.5,62.7,-42.5C70,-28.5,57,-12.3,53.9,2.1C50.8,16.5,57.6,33,55.9,47.8C54.2,62.6,44,75.7,31.8,81.8C19.6,87.9,5.3,87.1,-8.2,84.1C-21.7,81.2,-35.3,76.1,-45.6,67.3C-55.9,58.4,-62.8,45.8,-68.9,33.3C-75,20.8,-80.3,8.4,-79.8,-3.7C-79.3,-15.8,-73,-31.6,-63.3,-44.5C-53.6,-57.4,-40.5,-67.4,-26.6,-74.3C-12.7,-81.1,2,-84.8,16.4,-83.3C30.8,-81.8,45.1,-75,56.8,-65.3C68.5,-55.5,77.7,-42.7,81.2,-28.6C84.7,-14.5,82.5,0.9,76.5,13.4C70.5,25.8,60.7,35.3,49.9,44.3C39.1,53.3,27.3,61.8,14.1,64.3C0.9,66.8,-13.6,63.4,-25.4,57.5C-37.2,51.6,-46.3,43.3,-54.3,34.1C-62.3,24.9,-69.2,14.8,-71.7,3.3C-74.3,-8.3,-72.5,-21.3,-66.3,-32.2C-60.1,-43.1,-49.5,-51.9,-37.8,-60.3C-26.1,-68.7,-13,-76.6,1.1,-78.6C15.2,-80.6,30.5,-76.7,44.6,-58.1Z"/></svg>'),
                        linear-gradient(160deg, #f8f9fa 0%, #e3f2fd 100%);
            background-size: cover;
            padding: 2rem;
        }

        .breadcrumb {
            background: rgba(255,255,255,0.9);
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .app-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .app-header {
            background: linear-gradient(120deg, rgba(44, 181, 160, 0.1) 0%, rgba(76, 201, 240, 0.1) 100%);
            padding: 25px 30px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .app-header h2 {
            font-weight: 600;
            margin: 0;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .app-content {
            padding: 30px;
        }

        .table {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            background: white;
        }

        .table thead th {
            background: var(--secondary);
            border-bottom: 2px solid var(--primary);
            font-weight: 600;
            color: #2a2a2a;
        }

        .table-hover tbody tr {
            transition: all 0.2s ease;
        }

        .table-hover tbody tr:hover {
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .btn-success {
            background: var(--primary);
            border: none;
            padding: 0.6rem 1.5rem;
            transition: all 0.2s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44,181,160,0.3);
        }

        .btn-outline-secondary {
            border-color: var(--primary);
            color: var(--primary);
        }

        .badge {
            padding: 0.5em 0.8em;
            border-radius: 20px;
            font-weight: 500;
        }

        .badge-completed {
            background-color: rgba(28, 200, 138, 0.15);
            color: #15803d;
        }

        .badge-pending {
            background-color: rgba(246, 194, 62, 0.15);
            color: #b45309;
        }

        .badge-refunded {
            background-color: rgba(54, 185, 204, 0.15);
            color: #0e7490;
        }

        .badge-failed {
            background-color: rgba(231, 74, 59, 0.15);
            color: #b91c1c;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
        }

        .modal-header {
            background: var(--secondary);
            border-bottom: 2px solid var(--primary);
        }

        .summary-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
            padding: 1.5rem;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .payment-method {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            background: rgba(44, 181, 160, 0.1);
            display: inline-block;
        }
        
        .section-title {
            position: relative;
            padding-bottom: 10px;
            margin: 0 0 1.5rem;
            border-bottom: 2px solid rgba(44,181,160,0.2);
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100px;
            height: 2px;
            background: var(--primary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
        }
        
        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            background: rgba(44, 181, 160, 0.08);
            border: 1px solid rgba(44, 181, 160, 0.15);
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .action-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        
        .btn-view {
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }
        
        .btn-edit {
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }
        
        .btn-delete {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }
        
        .btn-print {
            background: rgba(114, 9, 183, 0.08);
            border: 1px solid rgba(114, 9, 183, 0.15);
            color: #7209b7;
        }
        
        .payment-detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }
        
        .payment-detail-row:last-child {
            border-bottom: none;
        }
        
        .payment-label {
            width: 40%;
            font-weight: 500;
            color: #4a5568;
        }
        
        .payment-value {
            width: 60%;
            font-weight: 600;
            color: #2a2a2a;
        }
        
        .stat-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem;
            border-radius: 16px;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            background: rgba(44, 181, 160, 0.15);
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 5px 0;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.95rem;
            color: #718096;
            margin: 0;
        }
        
        .alert {
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        
        .alert-success {
            background: rgba(74, 222, 128, 0.2);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: #15803d;
        }
        
        .no-data {
            text-align: center;
            padding: 40px 0;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.08);
        }
        
        .clinic-info {
            background: linear-gradient(135deg, var(--primary), #3aafa9);
            color: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 25px;
        }
        
        .clinic-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .clinic-details {
            text-align: center;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        
        .signature-container {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
        }
        
        .signature-box {
            width: 45%;
            position: relative;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            height: 1px;
            margin-bottom: 25px;
        }
        
        .signature-label {
            text-align: center;
            font-size: 0.9rem;
            position: absolute;
            bottom: 0;
            width: 100%;
        }
        
        .doctor-stamp {
            position: relative;
            margin-top: 20px;
            text-align: center;
        }
        
        .stamp-placeholder {
            display: block;
            width: 100px;
            height: 100px;
            border: 2px dashed #ccc;
            border-radius: 50%;
            margin: 0 auto;
            position: relative;
        }
        
        .stamp-placeholder-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            text-align: center;
            color: #999;
            font-size: 0.8rem;
            line-height: 1.2;
        }
        
        .teeth-icon {
            display: inline-block;
            margin-left: 10px;
            font-size: 1.2rem;
        }
        
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        
        .page-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .page-link {
            color: var(--primary);
        }
        
        .search-container {
            display: flex;
            margin-bottom: 20px;
        }
        
        .sort-icon {
            margin-left: 5px;
            color: var(--primary);
        }
        
        .date-filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .date-filter-group {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn-reset {
            background: #6c757d;
            border: none;
            color: white;
        }
        
        .btn-reset:hover {
            background: #5a6268;
            color: white;
        }
        
        .appointment-details {
            background: rgba(44, 181, 160, 0.05);
            padding: 10px;
            border-radius: 8px;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        
        .appointment-label {
            font-weight: 600;
            color: var(--primary);
        }
        
        .export-btn {
            background: #1a936f;
            border: none;
            padding: 0.6rem 1.5rem;
            transition: all 0.2s ease;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 147, 111, 0.3);
            background: #16825f;
        }
        
        @media print {
            body * { 
                visibility: hidden; 
            }
            #paymentModal .modal-content, 
            #paymentModal .modal-content * {
                visibility: visible;
            }
            #paymentModal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: auto;
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
                background: white;
                z-index: 9999;
            }
            .no-print {
                display: none !important;
            }
            .modal-backdrop {
                display: none !important;
            }
            .signature-container {
                margin-top: 30px;
            }
            .signature-line {
                border-top: 2px solid #000 !important;
                width: 100% !important;
            }
            .doctor-stamp .print-stamp-text {
                display: block;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 100%;
                text-align: center;
                font-weight: bold;
                color: #000;
                font-size: 0.9rem;
                line-height: 1.2;
            }
            
            .signature-label {
                color: #000;
                font-size: 1rem;
            }
            .print-stamp-text {
                display: block !important;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 100%;
                text-align: center;
                font-weight: bold;
                color: #000;
                font-size: 0.9rem;
                line-height: 1.2;
            }
            
            .payment-detail-row {
                padding: 8px 0;
            }
            
            .clinic-info {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .clinic-name {
                font-size: 1.3rem;
                margin-bottom: 5px;
            }
            
            .clinic-details {
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Breadcrumb -->
    <nav class="mb-4">
    <ol class="breadcrumb">
    <style>
        .breadcrumb {
            background: rgba(255, 255, 255, 0.9);
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .breadcrumb-item a {
            color: #2cb5a0; /* var(--primary) */
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .breadcrumb-item a:hover {
            color: #1f8e7d;
        }
    </style>

    <li class="breadcrumb-item">
        <i class="fas fa-home me-2"></i>
        <a href="dashboard.php">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">
        <i class="fas fa-credit-card me-2"></i>Payments
    </li>
</ol>

    </nav>

    <div class="app-container">
        <div class="app-header">
            <h2><i class="fas fa-credit-card me-2"></i> Payments Management</h2>
        </div>
        
        <div class="app-content">
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value"><?= number_format($totalAmount, 2) ?> MAD</div>
                    <div class="stat-label">Total Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?= $completedCount ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?= $pendingCount ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="stat-value"><?= $refundedCount ?></div>
                    <div class="stat-label">Refunded</div>
                </div>
            </div>
            
            <!-- Header and controls -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="section-title"><i class="fas fa-list me-2"></i>Payment Records</h3>
                <div>
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="fas fa-plus-circle me-2"></i> New Payment
                    </button>
                    <!-- Export form -->
                    <form id="exportForm" method="POST" style="display: inline;">
                        <input type="hidden" name="export" value="1">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
                        <button type="submit" class="btn export-btn">
                            <i class="fas fa-file-excel me-2"></i> Export to Excel
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Search Form -->
            <form method="GET" id="filtersForm" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search Payments</label>
                        <div class="input-group">
                            <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" 
                                   class="form-control" placeholder="Search by patient, transaction..." 
                                   onkeyup="liveSearch()">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="text" class="form-control date-picker" id="start_date" name="start_date" 
                               value="<?= htmlspecialchars($start_date) ?>" placeholder="Select start date">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="text" class="form-control date-picker" id="end_date" name="end_date" 
                               value="<?= htmlspecialchars($end_date) ?>" placeholder="Select end date">
                    </div>
                    
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                    <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
                </div>
                
                <div class="filter-buttons mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                    <button type="button" class="btn btn-reset" onclick="resetFilters()">
                        <i class="fas fa-times me-2"></i> Reset Filters
                    </button>
                </div>
            </form>
            
            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'id', 'order' => $sort === 'id' && $order === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                                    # 
                                    <?php if ($sort === 'id'): ?>
                                        <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'full_name', 'order' => $sort === 'full_name' && $order === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                                    <i class="fas fa-user me-1"></i> Patient
                                    <?php if ($sort === 'full_name'): ?>
                                        <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'payment_date', 'order' => $sort === 'payment_date' && $order === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                                    <i class="fas fa-calendar me-1"></i> Payment Date
                                    <?php if ($sort === 'payment_date'): ?>
                                        <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'amount', 'order' => $sort === 'amount' && $order === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                                    <i class="fas fa-money-bill me-1"></i> Amount
                                    <?php if ($sort === 'amount'): ?>
                                        <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th><i class="fas fa-credit-card me-1"></i> Method</th>
                            <th><i class="fas fa-tag me-1"></i> Status</th>
                            <th><i class="fas fa-calendar-check me-1"></i> Appointment</th>
                            <th><i class="fas fa-cog me-1"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTableBody">
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $i => $p): 
                                $statusClass = [
                                    'completed' => 'badge-completed',
                                    'pending' => 'badge-pending',
                                    'refunded' => 'badge-refunded',
                                    'failed' => 'badge-failed'
                                ];
                                $class = $statusClass[$p['status']] ?? '';
                            ?>
                                <tr data-payment='<?= htmlspecialchars(json_encode($p)) ?>'>
                                    <td><?= $offset + $i + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                            <div class="ms-3">
                                                <strong><?= htmlspecialchars($p['full_name']) ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?= $p['payment_date'] ?></div>
                                        <div class="text-muted small"><?= $p['payment_time'] ?></div>
                                    </td>
                                    <td class="fw-bold text-success"><?= number_format($p['amount'], 2) ?> MAD</td>
                                    <td>
                                        <span class="payment-method text-capitalize">
                                            <?= $p['payment_method'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $class ?>"><?= ucfirst($p['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($p['appointment_date']): ?>
                                            <div class="appointment-details">
                                                <div class="appointment-label">Date:</div>
                                                <div><?= $p['appointment_date'] ?> at <?= $p['appointment_time'] ?></div>
                                                <div class="appointment-label mt-2">Treatment:</div>
                                                <div><?= $p['treatment_type'] ? $p['treatment_type'] : 'N/A' ?></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted small">No appointment</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-view action-btn" data-bs-toggle="modal" data-bs-target="#paymentModal" onclick="loadPaymentDetails(this)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-edit action-btn" data-bs-toggle="modal" data-bs-target="#editPaymentModal" onclick="loadEditPayment(this)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-delete action-btn" onclick="showDeleteConfirmation(<?= $p['id'] ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button class="btn-print action-btn" onclick="printPayment(this)" title="Print">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    <i class="fas fa-file-invoice"></i>
                                    <h5>No payments found</h5>
                                    <p>No payments match your current filters</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination with Export Info -->
            <div class="pagination-container">
                <div class="page-info">
                    Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalPayments) ?> of <?= $totalPayments ?> records
                </div>
                
                <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a></li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="add_payment" value="1">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-user me-2"></i> Patient</label>
                                <select name="patient_id" id="patientSelect" class="form-select" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $pt): ?>
                                        <option value="<?= $pt['id'] ?>"><?= htmlspecialchars($pt['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-calendar-check me-2"></i> Appointment</label>
                                <select name="appointment_id" id="appointmentSelect" class="form-select">
                                    <option value="">Select patient first</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-money-bill-wave me-2"></i> Amount (MAD)</label>
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-credit-card me-2"></i> Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="cash">Cash</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="check">Check</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-tag me-2"></i> Status</label>
                                <select name="status" class="form-select">
                                    <option value="completed">Completed</option>
                                    <option value="pending">Pending</option>
                                    <option value="refunded">Refunded</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-receipt me-2"></i> Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control" placeholder="Enter transaction ID">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><i class="fas fa-sticky-note me-2"></i> Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="update_payment" value="1">
                    <input type="hidden" name="payment_id" id="editPaymentId">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-user me-2"></i> Patient</label>
                                <select name="patient_id" id="editPatientSelect" class="form-select" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $pt): ?>
                                        <option value="<?= $pt['id'] ?>"><?= htmlspecialchars($pt['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-calendar-check me-2"></i> Appointment</label>
                                <select name="appointment_id" id="editAppointmentSelect" class="form-select">
                                    <option value="">Select appointment</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-money-bill-wave me-2"></i> Amount (MAD)</label>
                                <input type="number" step="0.01" name="amount" id="editAmount" class="form-control" placeholder="Enter amount" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-credit-card me-2"></i> Payment Method</label>
                                <select name="payment_method" id="editPaymentMethod" class="form-select">
                                    <option value="cash">Cash</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="check">Check</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-tag me-2"></i> Status</label>
                                <select name="status" id="editStatus" class="form-select">
                                    <option value="completed">Completed</option>
                                    <option value="pending">Pending</option>
                                    <option value="refunded">Refunded</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-receipt me-2"></i> Transaction ID</label>
                                <input type="text" name="transaction_id" id="editTransactionId" class="form-control" placeholder="Enter transaction ID">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><i class="fas fa-sticky-note me-2"></i> Notes</label>
                                <textarea name="notes" id="editNotes" class="form-control" rows="3" placeholder="Additional notes"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content payment-details-modal">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i> Payment Details</h5>
                    <button type="button" class="btn-close no-print" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="clinic-info">
                        <div class="clinic-name">
                           <i class="fas fa-tooth teeth-icon"></i>  Dr. Asmaa Fahmi
                        </div>
                        <div class="clinic-details">Chirurgien Dentiste – Orthodontiste</div>
                        <div class="clinic-details">Hay Salama 1, Rue 99, N° 3 Bis – Casablanca, Morocco</div>
                        <div class="clinic-details">Phone: +212 5 22 55 22 35 | Email: doctorfahmiasmaa@gmail.com</div>
                    </div>
                    
                    <div class="payment-detail-row">
                        <div class="payment-label">Payment ID:</div>
                        <div class="payment-value" id="modalPaymentId">-</div>
                    </div>
                    <div class="payment-detail-row">
                        <div class="payment-label">Patient:</div>
                        <div class="payment-value" id="modalPatientName">-</div>
                    </div>
                    <div class="payment-detail-row">
                        <div class="payment-label">Date:</div>
                        <div class="payment-value" id="modalPaymentDate">-</div>
                    </div>
                    <div class="payment-detail-row">
                        <div class="payment-label">Time:</div>
                        <div class="payment-value" id="modalPaymentTime">-</div>
                    </div>
                    <div class="payment-detail-row">
                        <div class="payment-label">Amount:</div>
                        <div class="payment-value" id="modalAmount">-</div>
                    </div>
                    <div class="payment-detail-row">
                        <div class="payment-label">Method:</div>
                        <div class="payment-value" id="modalPaymentMethod">-</div>
                    </div>
                    <div class="payment-detail-row">
                        <div class="payment-label">Status:</div>
                        <div class="payment-value" id="modalStatus">-</div>
                    </div>
                    <div class="payment-detail-row">
                        <div class="payment-label">Appointment:</div>
                        <div class="payment-value" id="modalAppointmentDetails">-</div>
                    </div>
                    <div class="payment-detail-row">
                        <div class="payment-label">Transaction ID:</div>
                        <div class="payment-value" id="modalTransactionId">-</div>
                    </div>
                    <div class="payment-detail-row">
                        <div class="payment-label">Notes:</div>
                        <div class="payment-value" id="modalNotes">-</div>
                    </div>
                    
                    <!-- Signature Section -->
                    <div class="signature-container">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-label">Patient Signature</div>
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-label">Dr. Asmaa Fahmi</div>
                            <div class="doctor-stamp">
                                <div class="stamp-placeholder">
                                    <div class="stamp-placeholder-text">Space for<br>Official Stamp</div>
                                </div>
                                <div class="print-stamp-text" style="display: none;">
                                    DR. ASMAA FAHMI<br>OFFICIAL STAMP
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printPayment(this)">
                        <i class="fas fa-print me-2"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this payment record? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="delete_payment" value="1">
                        <input type="hidden" name="payment_id" id="deletePaymentId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        $(document).ready(function() {
            // Initialize date pickers
            flatpickr('.date-picker', {
                dateFormat: "Y-m-d",
                allowInput: true,
                maxDate: "today"
            });
            
            // Fetch appointments when patient changes
            $('#patientSelect').change(function () {
                const patientId = $(this).val();
                if (!patientId) {
                    $('#appointmentSelect').html('<option value="">Select patient first</option>');
                    return;
                }
                
                $('#appointmentSelect').html('<option value="">Loading appointments...</option>');
                
                $.ajax({
                    url: 'payments.php?fetch_appointments=1&patient_id=' + patientId,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (data.length > 0) {
                            let options = '<option value="">Select appointment</option>';
                            data.forEach(function(appointment) {
                                options += `<option value="${appointment.id}">${appointment.appointment_date} ${appointment.appointment_time} - ${appointment.treatment_type}</option>`;
                            });
                            $('#appointmentSelect').html(options);
                        } else {
                            $('#appointmentSelect').html('<option value="">No appointments found</option>');
                        }
                    },
                    error: function() {
                        $('#appointmentSelect').html('<option value="">Error loading appointments</option>');
                    }
                });
            });
        });
        
        // Function to load payment details
        function loadPaymentDetails(button) {
            // Get the parent row
            const row = $(button).closest('tr');
            // Get the payment data from the row's data attribute
            const paymentData = JSON.parse(row.attr('data-payment'));
            
            // Update modal with payment details
            $('#modalPaymentId').text(paymentData.id || 'N/A');
            $('#modalPatientName').text(paymentData.full_name || 'N/A');
            $('#modalPaymentDate').text(paymentData.payment_date || 'N/A');
            $('#modalPaymentTime').text(paymentData.payment_time || 'N/A');
            $('#modalAmount').text(paymentData.amount ? parseFloat(paymentData.amount).toFixed(2) + ' MAD' : 'N/A');
            $('#modalPaymentMethod').text(paymentData.payment_method ? paymentData.payment_method.charAt(0).toUpperCase() + paymentData.payment_method.slice(1) : 'N/A');
            
            // Create status badge
            const statusClass = getStatusClass(paymentData.status);
            $('#modalStatus').html(`<span class="badge ${statusClass}">${paymentData.status ? paymentData.status.charAt(0).toUpperCase() + paymentData.status.slice(1) : 'N/A'}</span>`);
            
            // Update appointment details
            if (paymentData.appointment_date) {
                $('#modalAppointmentDetails').html(`
                    <div><strong>Date:</strong> ${paymentData.appointment_date} at ${paymentData.appointment_time}</div>
                    <div><strong>Treatment:</strong> ${paymentData.treatment_type || 'N/A'}</div>
                `);
            } else {
                $('#modalAppointmentDetails').text('No appointment');
            }
            
            $('#modalTransactionId').text(paymentData.transaction_id || 'N/A');
            $('#modalNotes').text(paymentData.notes || 'No notes available');
        }
        
        // Function to print payment receipt
        function printPayment(button) {
            // Load payment details into modal
            loadPaymentDetails(button);
            
            // Show the modal
            $('#paymentModal').modal('show');
            
            // After a short delay, print
            setTimeout(() => {
                window.print();
            }, 500);
        }
        
        // Function to load payment data for editing
        function loadEditPayment(button) {
            // Get the parent row
            const row = $(button).closest('tr');
            // Get the payment data from the row's data attribute
            const paymentData = JSON.parse(row.attr('data-payment'));
            
            // Update edit modal with payment data
            $('#editPaymentId').val(paymentData.id);
            $('#editPatientSelect').val(paymentData.patient_id);
            $('#editAmount').val(paymentData.amount);
            $('#editPaymentMethod').val(paymentData.payment_method);
            $('#editStatus').val(paymentData.status);
            $('#editTransactionId').val(paymentData.transaction_id);
            $('#editNotes').val(paymentData.notes || '');
            
            // Load appointments for this patient
            const patientId = paymentData.patient_id;
            if (patientId) {
                $('#editAppointmentSelect').html('<option value="">Loading appointments...</option>');
                
                $.ajax({
                    url: 'payments.php?fetch_appointments=1&patient_id=' + patientId,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        let options = '<option value="">Select appointment</option>';
                        if (data.length > 0) {
                            data.forEach(function(appointment) {
                                const selected = appointment.id == paymentData.appointment_id ? 'selected' : '';
                                options += `<option value="${appointment.id}" ${selected}>${appointment.appointment_date} ${appointment.appointment_time} - ${appointment.treatment_type}</option>`;
                            });
                        } else {
                            options = '<option value="">No appointments found</option>';
                        }
                        $('#editAppointmentSelect').html(options);
                    },
                    error: function() {
                        $('#editAppointmentSelect').html('<option value="">Error loading appointments</option>');
                    }
                });
            }
        }
        
        function getStatusClass(status) {
            const statusClasses = {
                'completed': 'badge-completed',
                'pending': 'badge-pending',
                'refunded': 'badge-refunded',
                'failed': 'badge-failed'
            };
            return statusClasses[status] || '';
        }
        
        function showDeleteConfirmation(paymentId) {
            $('#deletePaymentId').val(paymentId);
            $('#deleteConfirmationModal').modal('show');
        }
        
        // Live search function
        function liveSearch() {
            const searchTerm = $('#searchInput').val().toLowerCase();
            const rows = $('#paymentsTableBody tr');
            
            rows.each(function() {
                const row = $(this);
                const patientName = row.find('td:eq(1) strong').text().toLowerCase();
                const paymentDate = row.find('td:eq(2) .fw-medium').text().toLowerCase();
                const amount = row.find('td:eq(3)').text().toLowerCase();
                const method = row.find('td:eq(4)').text().toLowerCase();
                const status = row.find('td:eq(5) span').text().toLowerCase();
                const appointment = row.find('td:eq(6)').text().toLowerCase();
                
                if (patientName.includes(searchTerm) || 
                    paymentDate.includes(searchTerm) || 
                    amount.includes(searchTerm) || 
                    method.includes(searchTerm) || 
                    status.includes(searchTerm) ||
                    appointment.includes(searchTerm)) {
                    row.show();
                } else {
                    row.hide();
                }
            });
        }
        
        // Reset filters function
        function resetFilters() {
            $('#searchInput').val('');
            $('#start_date').val('');
            $('#end_date').val('');
            $('#filtersForm').submit();
        }
    </script>
</body>
</html>