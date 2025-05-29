<?php
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

// Appointment status counts
$statusStmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    GROUP BY status
");
$statusCounts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

// Format status counts for easier access
$statusData = [
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0
];
foreach ($statusCounts as $status) {
    $statusData[$status['status']] = $status['count'];
}

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

// notification
$now = new DateTime();
$now->setTime((int)$now->format('H'), (int)$now->format('i'), 0);
$inFiveMinutes = clone $now;
$inFiveMinutes->modify('+5 minutes');

$stmt = $pdo->prepare("
    SELECT a.id, a.appointment_time, a.appointment_date, p.full_name 
    FROM appointments a 
    JOIN patients p ON p.id = a.patient_id 
    WHERE a.status = 'scheduled' 
      AND a.appointment_date = CURDATE()
      AND a.appointment_time = ?
");
$stmt->execute([$inFiveMinutes->format('H:i:s')]);
$notifications = $stmt->fetchAll();
$notifCount = count($notifications);

date_default_timezone_set('Africa/Casablanca');

$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
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
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: url('data:image/svg+xml,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="%232cb5a033" d="M44.6,-58.1C56.3,-49.6,62.6,-33.3,66.1,-16.8C69.6,-0.3,70.4,16.5,63.9,29.1C57.4,41.7,43.7,50.2,29.9,56.9C16.1,63.6,2.2,68.5,-12.6,67.7C-27.4,66.8,-42.9,60.2,-55.4,50.3C-67.9,40.4,-77.3,27.2,-79.9,12.6C-82.5,-2.1,-78.3,-18.2,-69.3,-31.1C-60.3,-44,-46.5,-53.7,-32.3,-61.3C-18.1,-68.9,-3.5,-74.4,12.1,-71.3C27.7,-68.2,55.4,-56.5,62.7,-42.5C70,-28.5,57,-12.3,53.9,2.1C50.8,16.5,57.6,33,55.9,47.8C54.2,62.6,44,75.7,31.8,81.8C19.6,87.9,5.3,87.1,-8.2,84.1C-21.7,81.2,-35.3,76.1,-45.6,67.3C-55.9,58.4,-62.8,45.8,-68.9,33.3C-75,20.8,-80.3,8.4,-79.8,-3.7C-79.3,-15.8,-73,-31.6,-63.3,-44.5C-53.6,-57.4,-40.5,-67.4,-26.6,-74.3C-12.7,-81.1,2,-84.8,16.4,-83.3C30.8,-81.8,45.1,-75,56.8,-65.3C68.5,-55.5,77.7,-42.7,81.2,-28.6C84.7,-14.5,82.5,0.9,76.5,13.4C70.5,25.8,60.7,35.3,49.9,44.3C39.1,53.3,27.3,61.8,14.1,64.3C0.9,66.8,-13.6,63.4,-25.4,57.5C-37.2,51.6,-46.3,43.3,-54.3,34.1C-62.3,24.9,-69.2,14.8,-71.7,3.3C-74.3,-8.3,-72.5,-21.3,-66.3,-32.2C-60.1,-43.1,-49.5,-51.9,-37.8,-60.3C-26.1,-68.7,-13,-76.6,1.1,-78.6C15.2,-80.6,30.5,-76.7,44.6,-58.1Z"/></svg>'),
                        linear-gradient(160deg, #f8f9fa 0%, #e3f2fd 100%);
            background-size: cover;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(160deg, var(--secondary) 0%, white 100%);
            box-shadow: 4px 0 15px rgba(0,0,0,0.05);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 2px solid rgba(44,181,160,0.1);
            background: rgba(255,255,255,0.9);
        }

        .nav-link {
            color: #3a3a3a !important;
            padding: 12px 25px !important;
            margin: 8px 15px;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        .nav-link:hover {
            background: var(--primary) !important;
            color: white !important;
            transform: translateX(8px);
            box-shadow: 2px 4px 12px rgba(44,181,160,0.2);
        }

        .nav-link.active {
            background: var(--primary);
            color: white !important;
            font-weight: 500;
        }

        .content {
            margin-left: 280px;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 2rem;
        }

        .topbar {
            padding: 1rem 2rem;
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 999;
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
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(44,181,160,0.15);
            border-radius: 20px;
            backdrop-filter: blur(8px);
            animation: cardEntrance 0.6s ease-out;
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
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        
        /* Dashboard grid layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 18px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(44, 181, 160, 0.1);
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar position-fixed h-100">
        <div class="sidebar-header">
            <h4 class="mb-0 text-primary fw-bold"><i class="fas fa-tooth me-2"></i>DentalCare</h4>
            <small class="text-muted">Administration Panel</small>
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
    </div>

    <!-- Main Content -->
    <div id="mainContent" class="content">
        <!-- Topbar -->
        <div class="topbar d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-bars sidebar-toggle me-3 fs-5" onclick="toggleSidebar()"></i>
                <h5 class="mb-0 text-muted">Welcome back, <span class="text-primary">Dr. <?= $adminName ?></span></h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="icon-btn position-relative" id="notifBell">
                    <i class="fas fa-bell text-secondary"></i>
                    <?php if ($notifCount > 0): ?>
                        <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
                            <?= $notifCount ?>
                        </span>
                    <?php endif; ?>
                </button>
                <a href="logout.php" class="btn btn-sm btn-outline-danger px-3">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="container-fluid">
            <!-- Welcome Card -->
            <div class="welcome-card p-4 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary rounded-circle p-3">
                        <i class="fas fa-smile-beam fa-2x text-white"></i>
                    </div>
                    <div>
        <h3 class="mb-1"><?= $greeting ?>, Dr. <?= $adminName ?></h3>
        <p class="text-muted mb-0">You have <?= $appointmentsToday ?> appointment(s) today</p>
    </div>
                </div>
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
                            <div class="stat-label">Appointments This Week</div>
                            <div class="<?= $changeColor ?>">
                                <i class="fas <?= $change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> me-1"></i>
                                <?= $changeSign . abs($change) ?>% from last week
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="stat-icon bg-green-light">
                                <i class="fas fa-user-injured fa-2x"></i>
                            </div>
                            <div class="stat-number"><?= $totalPatients ?></div>
                            <div class="stat-label">Total Patients</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="stat-icon bg-purple-light">
                                <i class="fas fa-tooth fa-2x"></i>
                            </div>
                            <div class="stat-number"><?= $procedureCount ?></div>
                            <div class="stat-label">Procedures</div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="stat-icon bg-yellow-light">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <div class="stat-number"><?= $statusData['scheduled'] ?></div>
                            <div class="stat-label">Pending Appointments</div>
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
                                <?= round(($statusData['completed'] / ($statusData['completed'] + $statusData['scheduled'] + $statusData['cancelled'])) * 100) ?>%
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
                                <?= round(($statusData['scheduled'] / ($statusData['completed'] + $statusData['scheduled'] + $statusData['cancelled'])) * 100) ?>%
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
                                <?= round(($statusData['cancelled'] / ($statusData['completed'] + $statusData['scheduled'] + $statusData['cancelled'])) * 100) ?>%
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

    <!-- Notification Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="margin-bottom: 450px;">
        <?php foreach ($notifications as $note): ?>
        <div class="toast align-items-center text-white bg-primary border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>Your next appointment is</strong><br>
                    <strong><?= htmlspecialchars($note['full_name']) ?></strong><br>
                    <?= date('H:i', strtotime($note['appointment_time'])) ?> - <?= $note['appointment_date'] ?><br>
                    <a href="appointments.php" class="text-white text-decoration-underline">View Appointment</a>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

  <audio id="alertSound" src="https://www.soundjay.com/button/sounds/beep-07.mp3" preload="auto"></audio>
  <footer class="bg-light py-3 mt-auto">
  <div class="container">
    <p class="text-center text-muted mb-0" style="text-align: center;">
      &copy; 2025 Dental Clinic. All rights reserved.
    </p>
  </div>
</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
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
            
            // Notification sound
            const toastElList = [].slice.call(document.querySelectorAll('.toast'));
            const alertSound = document.getElementById('alertSound');
            
            if (toastElList.length > 0) {
                toastElList.forEach(toastEl => {
                    const bsToast = new bootstrap.Toast(toastEl, { delay: 10000 });
                    bsToast.show();
                });
                
                // Play notification sound
                alertSound.play().catch(() => {
                    console.log('Auto-play blocked. Interaction required.');
                });
            }
        });
    </script>
</body>
</html>