<?php
session_start();
require_once 'config.php';

// Restrict access if not logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Doctor');

// Appointments today
$today = date('Y-m-d');
$stmtToday = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ?");
$stmtToday->execute([$today]);
$appointmentsToday = $stmtToday->fetchColumn();

// Appointments this week
$thisWeekStart = date('Y-m-d', strtotime('monday this week'));
$thisWeekEnd = date('Y-m-d', strtotime('sunday this week'));
$stmtThisWeek = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?");
$stmtThisWeek->execute([$thisWeekStart, $thisWeekEnd]);
$appointmentsCount = $stmtThisWeek->fetchColumn();

// Last week
$lastWeekStart = date('Y-m-d', strtotime('monday last week'));
$lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));
$stmtLastWeek = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?");
$stmtLastWeek->execute([$lastWeekStart, $lastWeekEnd]);
$lastWeekCount = $stmtLastWeek->fetchColumn();

$change = $lastWeekCount > 0 ? round((($appointmentsCount - $lastWeekCount) / $lastWeekCount) * 100) : ($appointmentsCount > 0 ? 100 : 0);
$changeSign = $change >= 0 ? '+' : '';
$changeColor = $change >= 0 ? 'text-success' : 'text-danger';

// Total patients
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

// Procedures
$procedureCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE treatment_type IS NOT NULL AND treatment_type != ''")->fetchColumn();

// Appointment status counts - Fixed: Initialize all statuses to 0
$statusStmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    GROUP BY status
");
$statusCounts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize all statuses to 0
$statusData = [
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0
];

// Update counts from database
foreach ($statusCounts as $status) {
    $statusData[$status['status']] = $status['count'];
}

// Calculate total appointments for percentages
$totalAppointments = $statusData['scheduled'] + $statusData['completed'] + $statusData['cancelled'];

// Patient demographics
$demographicsStmt = $pdo->query("
    SELECT working_type, COUNT(*) as count 
    FROM patients 
    GROUP BY working_type
");
$demographics = $demographicsStmt->fetchAll(PDO::FETCH_ASSOC);

// Top treatments
$treatmentsStmt = $pdo->query("
    SELECT treatment_type, COUNT(*) as count 
    FROM appointments 
    WHERE treatment_type IS NOT NULL AND treatment_type != ''
    GROUP BY treatment_type 
    ORDER BY count DESC 
    LIMIT 5
");
$topTreatments = $treatmentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent appointments
$recentAppointmentsStmt = $pdo->query("
    SELECT a.id, a.appointment_date, a.appointment_time, p.full_name, a.treatment_type, a.status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5
");
$recentAppointments = $recentAppointmentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Upcoming appointments (next 3 days)
$upcomingStart = date('Y-m-d');
$upcomingEnd = date('Y-m-d', strtotime('+3 days'));
$upcomingStmt = $pdo->prepare("
    SELECT a.id, a.appointment_date, a.appointment_time, p.full_name, a.treatment_type
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.appointment_date BETWEEN ? AND ?
    AND a.status = 'scheduled'
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 5
");
$upcomingStmt->execute([$upcomingStart, $upcomingEnd]);
$upcomingAppointments = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

// Notification system: Appointments in the next 5 minutes
$now = new DateTime();
$inFiveMinutes = clone $now;
$inFiveMinutes->modify('+5 minutes');

$stmt = $pdo->prepare("
    SELECT a.id, a.appointment_time, a.appointment_date, p.full_name 
    FROM appointments a 
    JOIN patients p ON p.id = a.patient_id 
    WHERE a.status = 'scheduled' 
      AND a.appointment_date = CURDATE()
      AND a.appointment_time BETWEEN ? AND ?
");
$stmt->execute([
    $now->format('H:i:s'),
    $inFiveMinutes->format('H:i:s')
]);
$notifications = $stmt->fetchAll();
$notifCount = count($notifications);

// Calculate minutes for each notification
$currentTime = new DateTime();
foreach ($notifications as &$notification) {
    $appointmentTime = new DateTime($notification['appointment_date'] . ' ' . $notification['appointment_time']);
    $diff = $appointmentTime->getTimestamp() - $currentTime->getTimestamp();
    $minutes = ceil($diff / 60);
    $notification['minutes'] = max(0, $minutes);
}

// Payment statistics
$totalRevenue = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?? 0;
$pendingPayments = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'pending'")->fetchColumn() ?? 0;
$overdueInvoices = $pdo->query("SELECT SUM(total_amount) FROM invoices WHERE status = 'overdue'")->fetchColumn() ?? 0;

// Recent payments
$recentPayments = $pdo->query("
    SELECT p.id, p.payment_date, p.amount, p.status, p.payment_method, pt.full_name
    FROM payments p
    JOIN patients pt ON p.patient_id = pt.id
    ORDER BY p.payment_date DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Recent invoices
$recentInvoices = $pdo->query("
    SELECT i.id, i.invoice_date, i.due_date, i.total_amount, i.status, pt.full_name
    FROM invoices i
    JOIN patients pt ON i.patient_id = pt.id
    ORDER BY i.invoice_date DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

date_default_timezone_set('Africa/Casablanca');

$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// Calculate percentage change helper function
function calculateChange($current, $previous) {
    if ($previous == 0) return $current == 0 ? 0 : 100;
    return round((($current - $previous) / $previous) * 100);
}

// Get current month dates
$currentMonthStart = date('Y-m-01');
$currentMonthEnd = date('Y-m-t');

// Get previous month dates
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

// Current month appointments
$appointmentsCount = $pdo->query("
    SELECT COUNT(*) 
    FROM appointments 
    WHERE appointment_date BETWEEN '$currentMonthStart' AND '$currentMonthEnd'
")->fetchColumn();

// Last month appointments
$lastMonthAppointmentsCount = $pdo->query("
    SELECT COUNT(*) 
    FROM appointments 
    WHERE appointment_date BETWEEN '$lastMonthStart' AND '$lastMonthEnd'
")->fetchColumn();

// Current month procedures
$procedureCount = $pdo->query("
    SELECT COUNT(*) 
    FROM appointments 
    WHERE appointment_date BETWEEN '$currentMonthStart' AND '$currentMonthEnd'
    AND treatment_type IS NOT NULL
")->fetchColumn();

// Last month procedures
$lastMonthProcedures = $pdo->query("
    SELECT COUNT(*) 
    FROM appointments 
    WHERE appointment_date BETWEEN '$lastMonthStart' AND '$lastMonthEnd'
    AND treatment_type IS NOT NULL
")->fetchColumn();

// Last month pending appointments
$lastMonthPending = $pdo->query("
    SELECT COUNT(*) 
    FROM appointments 
    WHERE status = 'scheduled'
    AND appointment_date BETWEEN '$lastMonthStart' AND '$lastMonthEnd'
")->fetchColumn();

// Total patients
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

// Last month total patients
$lastMonthPatients = $pdo->query("
    SELECT COUNT(*) 
    FROM patients 
    WHERE created_at BETWEEN '$lastMonthStart' AND '$lastMonthEnd'
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DentalCare Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #2cb5a0;
            --primary-light: #e6f7f4;
            --secondary: #f0f7fa;
            --accent: #ff7f50;
            --student: #4e73df;
            --employed: #1cc88a;
            --self-employed: #36b9cc;
            --unemployed: #f6c23e;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            color: #333;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path fill="%232cb5a0" fill-opacity="0.03" d="M20,20 C40,0 60,0 80,20 C100,40 100,60 80,80 C60,100 40,100 20,80 C0,60 0,40 20,20 Z" /></svg>');
            background-size: 200px;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a3c4a 0%, #0d2029 100%);
            color: white;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }

        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 12px 25px !important;
            margin: 8px 15px;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--primary) !important;
            color: white !important;
            transform: translateX(8px);
            box-shadow: 2px 4px 12px rgba(44,181,160,0.3);
        }

        .content {
            margin-left: 280px;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 2rem;
            background-color: #f8f9fa;
        }

        .topbar {
            padding: 1rem 2rem;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid #eaeaea;
        }

        .icon-btn {
            transition: all 0.2s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .icon-btn:hover {
            transform: scale(1.1);
            background: rgba(44,181,160,0.1);
        }

        .sidebar-toggle {
            color: var(--primary);
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .welcome-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            animation: cardEntrance 0.6s ease-out;
            border: none;
        }

        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .metric-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            background: white;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: none;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08);
        }
        
        .dashboard-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid rgba(44, 181, 160, 0.15);
        }
        
        .dashboard-card-title {
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--primary);
            margin: 0;
        }
        
        .chart-container {
            height: 250px;
            position: relative;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .bg-primary-light {
            background-color: rgba(44, 181, 160, 0.15);
            color: var(--primary);
        }
        
        .bg-purple-light {
            background-color: rgba(78, 115, 223, 0.15);
            color: var(--student);
        }
        
        .bg-green-light {
            background-color: rgba(28, 200, 138, 0.15);
            color: var(--employed);
        }
        
        .bg-blue-light {
            background-color: rgba(105, 192, 255, 0.15);
            color: var(--self-employed);
        }
        
        .bg-yellow-light {
            background-color: rgba(246, 194, 62, 0.15);
            color: var(--unemployed);
        }
        
        .appointment-badge {
            padding: 0.3em 0.6em;
            border-radius: 10px;
            font-size: 0.85em;
        }
        
        .badge-scheduled {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .recent-appointments {
            max-height: 350px;
            overflow-y: auto;
        }
        
        .appointment-item {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.8rem;
            background: #f8f9fa;
            transition: all 0.2s ease;
            border-left: 3px solid var(--primary);
        }
        
        .appointment-item:hover {
            background: #e9f9f6;
            transform: translateX(5px);
        }
        
        .demographic-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
        }
        
        .demographic-badge {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.8rem;
        }
        
        .treatment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.8rem;
            border-radius: 10px;
            margin-bottom: 0.8rem;
            background: #f8f9fa;
        }
        
        .treatment-progress {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }
        
        .treatment-progress-bar {
            height: 100%;
            border-radius: 4px;
        }
        
        .status-overview-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.8rem;
            border-radius: 10px;
            background: var(--primary-light);
        }
        
        .status-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .status-info {
            flex-grow: 1;
        }
        
        .status-title {
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        
        .status-count {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .status-percentage {
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .payment-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .total-revenue {
            color: var(--success);
        }
        
        .pending-payments {
            color: var(--warning);
        }
        
        .overdue-invoices {
            color: var(--danger);
        }
        
        .payment-method-badge {
            padding: 0.3em 0.6em;
            border-radius: 10px;
            font-size: 0.85em;
        }
        
        .badge-cash {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-credit {
            background: #cce5ff;
            color: #004085;
        }
        
        .badge-transfer {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .invoice-status-badge {
            padding: 0.3em 0.6em;
            border-radius: 10px;
            font-size: 0.85em;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .online-indicator {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(40, 167, 69, 0.15);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            color: var(--success);
            font-weight: 500;
        }

        .online-indicator::before {
            content: "";
            width: 10px;
            height: 10px;
            background: var(--success);
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        
        .moroccan-pattern {
            position: absolute;
            top: 0;
            right: 0;
            opacity: 0.03;
            width: 300px;
            height: 300px;
            z-index: -1;
        }
        
        .mad-currency {
            font-weight: 600;
            color: #1a3c4a;
        }
        
        .moroccan-theme {
            background: linear-gradient(135deg, #1a3c4a 0%, #0d2029 100%);
            color: white;
        }
        
        .moroccan-theme .dashboard-card-title {
            color: #2cb5a0;
        }
        
        .moroccan-accent {
            background: #ff7f50;
        }
        
        .moroccan-decor {
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M50,10 A40,40 0 1,1 50,90 A40,40 0 1,1 50,10 Z" fill="none" stroke="%232cb5a0" stroke-width="2"/><circle cx="50" cy="50" r="20" fill="%23ff7f50" /></svg>');
            opacity: 0.1;
            z-index: -1;
        }
        
        .logo-text {
            font-weight: 700;
            letter-spacing: 1px;
            color: white;
        }
        
        .sidebar .logo-text {
            color: #ff7f50;
        }
        
        .greeting-card {
            background: linear-gradient(135deg, #2cb5a0 0%, #1a3c4a 100%);
            color: white;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }
        
        .greeting-card::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        
        .greeting-card::after {
            content: "";
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        
        .notification-bell {
            position: relative;
            cursor: pointer;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .notification-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
        }
        
        .notification-toast {
            background: #fff;
            border-left: 4px solid #2cb5a0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 16px;
            margin-bottom: 10px;
            max-width: 350px;
            display: flex;
            align-items: center;
            animation: slideIn 0.5s ease-out;
        }
        
        .notification-icon {
            background: rgba(44, 181, 160, 0.15);
            color: #2cb5a0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .notification-content h5 {
            margin: 0 0 5px 0;
            font-size: 1rem;
            color: #333;
        }
        
        .notification-content p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            margin-left: 10px;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .notification-toast.emergency {
            border-left-color: #dc3545;
        }
        
        .notification-toast.emergency .notification-icon {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar position-fixed h-100">
        <div class="sidebar-header">
            <h4 class="mb-0 logo-text"><i class="fas fa-tooth me-2"></i>DentalCare</h4>
            <small class="text-white-50">Administration Panel</small>
        </div>
        <ul class="nav flex-column p-3 mt-2">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-chart-pie me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="appointments.php">
                    <i class="fas fa-calendar-check me-2"></i>Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="patients.php">
                    <i class="fas fa-user-injured me-2"></i>Patients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="payments.php">
                    <i class="fas fa-credit-card me-2"></i>Payments & Invoices
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-file-waveform me-2"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cogs me-2"></i>Settings
                </a>
            </li>
        </ul>
        <div class="position-absolute bottom-0 w-100 p-3 text-center text-white-50">
            <small>&copy; 2025 DentalCare. All rights reserved.</small>
        </div>
    </div>

    <!-- Main Content -->
    <div id="mainContent" class="content">
        <!-- Topbar -->
        <div class="topbar d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-bars sidebar-toggle me-3 fs-5" onclick="toggleSidebar()"></i>
                <h5 class="mb-0">Welcome back, <span class="text-primary">Dr. <?= $adminName ?></span></h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="online-indicator">
                    Online
                </div>
                <div class="notification-bell" id="notifBell">
                    <i class="fas fa-bell fa-lg text-secondary"></i>
                    <?php if ($notifCount > 0): ?>
                        <span class="notification-badge"><?= $notifCount ?></span>
                    <?php endif; ?>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-danger px-3">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
        <!-- Dashboard Content -->
        <div class="container-fluid">
            <!-- Welcome Card -->
            <div class="greeting-card p-4 mb-4">
                <div class="d-flex align-items-center gap-3 position-relative" style="z-index: 2;">
                    <div class="bg-white rounded-circle p-3">
                        <i class="fas fa-smile-beam fa-2x text-primary"></i>
                    </div>
                    <div>
                        <h3 class="mb-1 text-white"><?= $greeting ?>, Dr. <?= $adminName ?></h3>
                        <p class="text-white mb-0">You have <?= $appointmentsToday ?> appointment(s) today</p>
                    </div>
                </div>
                <div class="moroccan-decor"></div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Key Metrics Card -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-chart-line me-2"></i>Key Metrics</h5>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="stat-icon bg-primary-light">
                                <i class="fas fa-calendar-check fa-2x"></i>
                            </div>
                            <div class="stat-number"><?= $appointmentsCount ?></div>
                            <div class="stat-label">Appointments This Month</div>
                            <?php
                            $appointmentChange = calculateChange($appointmentsCount, $lastMonthAppointmentsCount);
                            $appointmentChangeSign = $appointmentChange >= 0 ? '+' : '';
                            $appointmentChangeColor = $appointmentChange >= 0 ? 'text-success' : 'text-danger';
                            ?>
                            <div class="<?= $appointmentChangeColor ?>">
                                <i class="fas <?= $appointmentChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> me-1"></i>
                                <?= $appointmentChangeSign . abs($appointmentChange) ?>% from last month
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="stat-icon bg-green-light">
                                <i class="fas fa-user-injured fa-2x"></i>
                            </div>
                            <div class="stat-number"><?= $totalPatients ?></div>
                            <div class="stat-label">Total Patients</div>
                            <?php
                            $patientChange = calculateChange($totalPatients, $lastMonthPatients);
                            $patientChangeSign = $patientChange >= 0 ? '+' : '';
                            $patientChangeColor = $patientChange >= 0 ? 'text-success' : 'text-danger';
                            ?>
                            <div class="<?= $patientChangeColor ?>">
                                <i class="fas <?= $patientChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> me-1"></i>
                                <?= $patientChangeSign . abs($patientChange) ?>% from last month
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="stat-icon bg-purple-light">
                                <i class="fas fa-tooth fa-2x"></i>
                            </div>
                            <div class="stat-number"><?= $procedureCount ?></div>
                            <div class="stat-label">Procedures This Month</div>
                            <?php
                            $procedureChange = calculateChange($procedureCount, $lastMonthProcedures);
                            $procedureChangeSign = $procedureChange >= 0 ? '+' : '';
                            $procedureChangeColor = $procedureChange >= 0 ? 'text-success' : 'text-danger';
                            ?>
                            <div class="<?= $procedureChangeColor ?>">
                                <i class="fas <?= $procedureChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> me-1"></i>
                                <?= $procedureChangeSign . abs($procedureChange) ?>% from last month
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="stat-icon bg-yellow-light">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <div class="stat-number"><?= $statusData['scheduled'] ?></div>
                            <div class="stat-label">Pending Appointments</div>
                            <?php
                            $pendingChange = calculateChange($statusData['scheduled'], $lastMonthPending);
                            $pendingChangeSign = $pendingChange >= 0 ? '+' : '';
                            $pendingChangeColor = $pendingChange >= 0 ? 'text-success' : 'text-danger';
                            ?>
                            <div class="<?= $pendingChangeColor ?>">
                                <i class="fas <?= $pendingChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> me-1"></i>
                                <?= $pendingChangeSign . abs($pendingChange) ?>% from last month
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Financial Overview -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-money-bill-wave me-2"></i>Financial Overview (MAD)</h5>
                    </div>
                    <div class="payment-stats">
                        <div class="stat-card">
                            <div class="stat-value total-revenue"><?= number_format($totalRevenue, 2) ?> <span class="mad-currency">MAD</span></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value pending-payments"><?= number_format($pendingPayments, 2) ?> <span class="mad-currency">MAD</span></div>
                            <div class="stat-label">Pending Payments</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value overdue-invoices"><?= number_format($overdueInvoices, 2) ?> <span class="mad-currency">MAD</span></div>
                            <div class="stat-label">Overdue Invoices</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><i class="fas fa-credit-card me-2"></i> Recent Payments (MAD)</h6>
                            <a href="payments.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="recent-appointments">
                            <?php foreach ($recentPayments as $payment): ?>
                                <div class="appointment-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong><?= htmlspecialchars($payment['full_name']) ?></strong>
                                        <span class="text-muted"><?= date('M d', strtotime($payment['payment_date'])) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold"><?= number_format($payment['amount'], 2) ?> <span class="mad-currency">MAD</span></span>
                                        <span class="payment-method-badge 
                                            <?= $payment['payment_method'] == 'cash' ? 'badge-cash' : 
                                               ($payment['payment_method'] == 'credit_card' ? 'badge-credit' : 'badge-transfer') ?>">
                                            <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                        </span>
                                    </div>
                                    <div class="mt-2 text-muted">
                                        <i class="fas fa-circle me-1 <?= $payment['status'] == 'completed' ? 'text-success' : 'text-warning' ?>"></i> 
                                        <?= ucfirst($payment['status']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Appointments Overview -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-calendar-alt me-2"></i>Appointments Overview</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="appointmentsChart"></canvas>
                    </div>
                </div>
                
                <!-- Patient Demographics -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-users me-2"></i>Patient Demographics</h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="demographicsChart"></canvas>
                    </div>
                </div>
                
                <!-- Appointment Status Overview -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-clipboard-list me-2"></i>Appointment Status</h5>
                    </div>
                    <div class="status-overview-card">
                        <div class="status-item">
                            <div class="status-icon bg-success text-white">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="status-info">
                                <div class="status-title">Completed</div>
                                <div class="status-count"><?= $statusData['completed'] ?></div>
                            </div>
                            <div class="status-percentage text-success">
                                <?= $totalAppointments > 0 ? round(($statusData['completed'] / $totalAppointments) * 100) : 0 ?>%
                            </div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-icon bg-info text-white">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="status-info">
                                <div class="status-title">Scheduled</div>
                                <div class="status-count"><?= $statusData['scheduled'] ?></div>
                            </div>
                            <div class="status-percentage text-info">
                                <?= $totalAppointments > 0 ? round(($statusData['scheduled'] / $totalAppointments) * 100) : 0 ?>%
                            </div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-icon bg-warning text-white">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="status-info">
                                <div class="status-title">Cancelled</div>
                                <div class="status-count"><?= $statusData['cancelled'] ?></div>
                            </div>
                            <div class="status-percentage text-warning">
                                <?= $totalAppointments > 0 ? round(($statusData['cancelled'] / $totalAppointments) * 100) : 0 ?>%
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Appointments -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-history me-2"></i>Recent Appointments</h5>
                    </div>
                    <div class="recent-appointments">
                        <?php foreach ($recentAppointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong><?= htmlspecialchars($appointment['full_name']) ?></strong>
                                    <span class="text-muted"><?= date('M d', strtotime($appointment['appointment_date'])) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="fas fa-clock me-1 text-muted"></i>
                                        <?= date('h:i A', strtotime($appointment['appointment_time'])) ?>
                                    </span>
                                    <span class="badge 
                                        <?= $appointment['status'] == 'scheduled' ? 'badge-scheduled' : 
                                           ($appointment['status'] == 'completed' ? 'badge-completed' : 'badge-cancelled') ?>">
                                        <?= ucfirst($appointment['status']) ?>
                                    </span>
                                </div>
                                <div class="mt-2 text-muted">
                                    <i class="fas fa-teeth me-1"></i> <?= $appointment['treatment_type'] ? htmlspecialchars($appointment['treatment_type']) : 'General Checkup' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Recent Invoices -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-file-invoice me-2"></i>Recent Invoices (MAD)</h5>
                    </div>
                    <div class="recent-appointments">
                        <?php foreach ($recentInvoices as $invoice): ?>
                            <div class="appointment-item">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong><?= htmlspecialchars($invoice['full_name']) ?></strong>
                                    <span class="text-muted"><?= date('M d', strtotime($invoice['invoice_date'])) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold"><?= number_format($invoice['total_amount'], 2) ?> <span class="mad-currency">MAD</span></span>
                                    <span class="invoice-status-badge 
                                        <?= $invoice['status'] == 'paid' ? 'badge-paid' : 
                                           ($invoice['status'] == 'pending' ? 'badge-pending' : 'badge-overdue') ?>">
                                        <?= ucfirst($invoice['status']) ?>
                                    </span>
                                </div>
                                <div class="mt-2 text-muted">
                                    <i class="fas fa-calendar-day me-1"></i> Due: <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Upcoming Appointments -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-calendar-plus me-2"></i>Upcoming Appointments</h5>
                    </div>
                    <div class="recent-appointments">
                        <?php if (count($upcomingAppointments) > 0): ?>
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <div class="appointment-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong><?= htmlspecialchars($appointment['full_name']) ?></strong>
                                        <span class="text-muted"><?= date('M d', strtotime($appointment['appointment_date'])) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-clock me-1 text-muted"></i>
                                            <?= date('h:i A', strtotime($appointment['appointment_time'])) ?>
                                        </span>
                                        <span class="badge badge-scheduled">
                                            Scheduled
                                        </span>
                                    </div>
                                    <div class="mt-2 text-muted">
                                        <i class="fas fa-teeth me-1"></i> <?= $appointment['treatment_type'] ? htmlspecialchars($appointment['treatment_type']) : 'General Checkup' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">
                                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                <p>No upcoming appointments</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Top Treatments -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-stethoscope me-2"></i>Top Treatments</h5>
                    </div>
                    <div>
                        <?php foreach ($topTreatments as $treatment): ?>
                            <div class="treatment-item">
                                <div>
                                    <div class="fw-medium"><?= htmlspecialchars($treatment['treatment_type']) ?></div>
                                    <div class="text-muted small"><?= $treatment['count'] ?> procedures</div>
                                </div>
                                <div class="w-50">
                                    <div class="treatment-progress">
                                        <div class="treatment-progress-bar bg-primary" 
                                            style="width: <?= ($treatment['count'] / max(10, $procedureCount)) * 100 ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Patient Demographics Breakdown -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h5 class="dashboard-card-title"><i class="fas fa-chart-pie me-2"></i>Patient Types</h5>
                    </div>
                    <div>
                        <?php foreach ($demographics as $demo): ?>
                            <div class="demographic-item">
                                <div class="demographic-badge 
                                    <?= $demo['working_type'] == 'student' ? 'bg-purple-light' : 
                                       ($demo['working_type'] == 'employed' ? 'bg-green-light' : 
                                       ($demo['working_type'] == 'self-employed' ? 'bg-blue-light' : 'bg-yellow-light')) ?>">
                                    <i class="fas 
                                        <?= $demo['working_type'] == 'student' ? 'fa-user-graduate' : 
                                           ($demo['working_type'] == 'employed' ? 'fa-briefcase' : 
                                           ($demo['working_type'] == 'self-employed' ? 'fa-user-tie' : 'fa-user-slash')) ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-medium"><?= ucfirst($demo['working_type']) ?></div>
                                    <div class="text-muted small"><?= $demo['count'] ?> patients</div>
                                </div>
                                <div class="fw-bold"><?= round(($demo['count'] / $totalPatients) * 100) ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div class="notification-container" id="notificationContainer">
        <?php foreach ($notifications as $note): ?>
        <div class="notification-toast <?= $note['minutes'] == 0 ? 'emergency' : '' ?>">
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="notification-content">
                <h5>Upcoming Appointment</h5>
                <p><strong><?= htmlspecialchars($note['full_name']) ?></strong> 
                    <?= $note['minutes'] > 0 ? 'in ' . $note['minutes'] . ' minutes' : 'now' ?>
                </p>
                <p><?= date('h:i A', strtotime($note['appointment_time'])) ?> - Today</p>
            </div>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Audio Element for Notification Sound -->
    <audio id="notificationSound">
        <source src="https://assets.mixkit.co/sfx/preview/mixkit-alarm-digital-clock-beep-989.mp3" type="audio/mpeg">
    </audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('mainContent');
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('expanded');
            
            if(sidebar.classList.contains('collapsed')) {
                sidebar.style.transform = 'translateX(-100%)';
                content.style.marginLeft = '0';
            } else {
                sidebar.style.transform = 'translateX(0)';
                content.style.marginLeft = '280px';
            }
        }

        // Charts initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Appointments Chart
            const appointmentsCtx = document.getElementById('appointmentsChart').getContext('2d');
            const appointmentsChart = new Chart(appointmentsCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Appointments',
                        data: [12, 19, 8, 15, 14, 10, 7],
                        borderColor: '#2cb5a0',
                        backgroundColor: 'rgba(44, 181, 160, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Demographics Chart
            const demographicsCtx = document.getElementById('demographicsChart').getContext('2d');
            const demographicsChart = new Chart(demographicsCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Students', 'Employed', 'Self-Employed', 'Unemployed'],
                    datasets: [{
                        data: [
                            <?= $demographics[0]['count'] ?? 0 ?>,
                            <?= $demographics[1]['count'] ?? 0 ?>,
                            <?= $demographics[2]['count'] ?? 0 ?>,
                            <?= $demographics[3]['count'] ?? 0 ?>
                        ],
                        backgroundColor: [
                            'rgba(78, 115, 223, 0.8)',
                            'rgba(28, 200, 138, 0.8)',
                            'rgba(54, 185, 204, 0.8)',
                            'rgba(246, 194, 62, 0.8)'
                        ],
                        borderColor: [
                            'rgba(78, 115, 223, 1)',
                            'rgba(28, 200, 138, 1)',
                            'rgba(54, 185, 204, 1)',
                            'rgba(246, 194, 62, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    cutout: '70%'
                }
            });
            
            // Notification functionality
            const notificationContainer = document.getElementById('notificationContainer');
            const notificationSound = document.getElementById('notificationSound');
            
            // Close notification buttons
            document.querySelectorAll('.notification-close').forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.notification-toast').remove();
                    updateNotificationCount();
                });
            });
            
            // Function to update notification count
            function updateNotificationCount() {
                const badge = document.querySelector('.notification-badge');
                const count = document.querySelectorAll('.notification-toast').length;
                
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count;
                    } else {
                        badge.remove();
                    }
                } else if (count > 0) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = count;
                    document.getElementById('notifBell').appendChild(newBadge);
                }
            }
            
            // Play notification sound if there are notifications
            if (<?= $notifCount ?> > 0) {
                notificationSound.play();
            }
        });
    </script>
</body>
</html>