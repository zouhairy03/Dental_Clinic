<?php
require_once 'config.php';
// session_start();

// Restrict access if not logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Doctor');
// Calendar configuration
$currentMonth = $_GET['month'] ?? date('Y-m');
$search = $_GET['search'] ?? '';
$date = new DateTime($currentMonth . '-01');
$monthStart = $date->format('Y-m-01');
$monthEnd = $date->format('Y-m-t');
$prevMonth = (clone $date)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $date)->modify('+1 month')->format('Y-m');

// Fetch appointments
$stmt = $pdo->prepare("SELECT a.*, p.full_name AS patient_name, p.phone AS patient_phone
                      FROM appointments a 
                      LEFT JOIN patients p ON a.patient_id = p.id 
                      WHERE a.appointment_date BETWEEN ? AND ? 
                      AND p.full_name LIKE ?
                      ORDER BY appointment_date, appointment_time");
$stmt->execute([$monthStart, $monthEnd, "%$search%"]);
$appointments = $stmt->fetchAll();

// Group appointments by date
$appointmentsByDate = [];
foreach ($appointments as $appt) {
    $appointmentsByDate[$appt['appointment_date']][] = $appt;
}

// Calendar calculations
$firstWeekday = $date->format('N');
$totalDays = $date->format('t');
$weeks = ceil(($totalDays + $firstWeekday - 1) / 7);
$today = date('Y-m-d');

// Get patients
$patients = $pdo->query("SELECT id, full_name FROM patients ORDER BY full_name")->fetchAll();

// Check for notifications
$notification = '';
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dental Calendar - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2cb5a0;
            --primary-dark: #1f8e7d;
            --secondary: #f0f7fa;
            --scheduled: #ff7f50;
            --completed: #2cb5a0;
            --cancelled: #dc3545;
            --light-bg: #f8f9fa;
            --dark-bg: #e3f2fd;
            --card-bg: rgba(255, 255, 255, 0.95);
            --border-radius: 12px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --success: #28a745;
            --error: #dc3545;
        }

        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: url('data:image/svg+xml,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="%232cb5a033" d="M44.6,-58.1C56.3,-49.6,62.6,-33.3,66.1,-16.8C69.6,-0.3,70.4,16.5,63.9,29.1C57.4,41.7,43.7,50.2,29.9,56.9C16.1,63.6,2.2,68.5,-12.6,67.7C-27.4,66.8,-42.9,60.2,-55.4,50.3C-67.9,40.4,-77.3,27.2,-79.9,12.6C-82.5,-2.1,-78.3,-18.2,-69.3,-31.1C-60.3,-44,-46.5,-53.7,-32.3,-61.3C-18.1,-68.9,-3.5,-74.4,12.1,-71.3C27.7,-68.2,55.4,-56.5,62.7,-42.5C70,-28.5,57,-12.3,53.9,2.1C50.8,16.5,57.6,33,55.9,47.8C54.2,62.6,44,75.7,31.8,81.8C19.6,87.9,5.3,87.1,-8.2,84.1C-21.7,81.2,-35.3,76.1,-45.6,67.3C-55.9,58.4,-62.8,45.8,-68.9,33.3C-75,20.8,-80.3,8.4,-79.8,-3.7C-79.3,-15.8,-73,-31.6,-63.3,-44.5C-53.6,-57.4,-40.5,-67.4,-26.6,-74.3C-12.7,-81.1,2,-84.8,16.4,-83.3C30.8,-81.8,45.1,-75,56.8,-65.3C68.5,-55.5,77.7,-42.7,81.2,-28.6C84.7,-14.5,82.5,0.9,76.5,13.4C70.5,25.8,60.7,35.3,49.9,44.3C39.1,53.3,27.3,61.8,14.1,64.3C0.9,66.8,-13.6,63.4,-25.4,57.5C-37.2,51.6,-46.3,43.3,-54.3,34.1C-62.3,24.9,-69.2,14.8,-71.7,3.3C-74.3,-8.3,-72.5,-21.3,-66.3,-32.2C-60.1,-43.1,-49.5,-51.9,-37.8,-60.3C-26.1,-68.7,-13,-76.6,1.1,-78.6C15.2,-80.6,30.5,-76.7,44.6,-58.1Z"/></svg>'),
                        linear-gradient(160deg, #f8f9fa 0%, #e3f2fd 100%);
            background-size: cover;
            padding: 2rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            background: var(--card-bg);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .calendar-header {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .calendar-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .calendar-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-right: auto;
        }

        .calendar-title i {
            font-size: 1.5rem;
        }

        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .weekday-header {
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            color: white;
            background: var(--primary);
        }

        .weekend {
            background: var(--primary-dark);
        }

        .calendar-day {
            min-height: 150px;
            padding: 1rem;
            border-right: 1px solid #eaeaea;
            border-bottom: 1px solid #eaeaea;
            position: relative;
            background: white;
            transition: all 0.2s ease;
        }

        .calendar-day:hover {
            background: var(--secondary);
            z-index: 2;
        }

        .today {
            background: rgba(44, 181, 160, 0.1);
            position: relative;
        }

        .today::before {
            content: '';
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: 50%;
        }

        .non-month-day {
            background: #f8f9fa;
            color: #adb5bd;
        }

        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .day-number {
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
        }

        .appointment-count {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 26px;
            height: 26px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .events {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .event-item {
            background: white;
            border-radius: 8px;
            padding: 0.75rem;
            border-left: 4px solid var(--scheduled);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .event-item:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
        }

        .event-item.completed {
            border-left-color: var(--completed);
        }

        .event-item.cancelled {
            border-left-color: var(--cancelled);
        }

        .event-time {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.25rem;
        }

        .event-patient {
            font-size: 0.9rem;
            font-weight: 500;
            color: #343a40;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .empty-day {
            color: #adb5bd;
            font-style: italic;
            font-size: 0.9rem;
            text-align: center;
            padding: 1rem 0;
        }

        .search-container {
            position: relative;
            margin-top: 1.5rem;
        }

        .search-container .form-control {
            border-radius: 8px;
            padding-left: 2.5rem;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .clear-search {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
        }

        .legend {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            margin-top: 1.5rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .status-indicator {
            width: 14px;
            height: 14px;
            border-radius: 50%;
        }

        .scheduled { background: var(--scheduled); }
        .completed { background: var(--completed); }
        .cancelled { background: var(--cancelled); }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--box-shadow);
        }

        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 1.2rem 1.5rem;
        }

        .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-close {
            filter: invert(1);
        }

        .status-badge {
            padding: 0.35rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge::before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .badge-scheduled {
            background: rgba(255, 127, 80, 0.15);
            color: var(--scheduled);
        }

        .badge-scheduled::before {
            background: var(--scheduled);
        }

        .badge-completed {
            background: rgba(44, 181, 160, 0.15);
            color: var(--completed);
        }

        .badge-completed::before {
            background: var(--completed);
        }

        .badge-cancelled {
            background: rgba(220, 53, 69, 0.15);
            color: var(--cancelled);
        }

        .badge-cancelled::before {
            background: var(--cancelled);
        }

        .patient-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--light-bg);
            border-radius: var(--border-radius);
        }

        .patient-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .patient-details {
            flex: 1;
        }

        .patient-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .patient-phone {
            font-size: 0.9rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .detail-value {
            font-size: 1rem;
            color: #212529;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        
        .notification {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            background: white;
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease-out, fadeOut 0.5s ease-in 3.5s forwards;
            position: relative;
            overflow: hidden;
        }
        
        .notification::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 6px;
        }
        
        .notification-success {
            border-left: 4px solid var(--success);
        }
        
        .notification-success .notification-icon {
            color: var(--success);
        }
        
        .notification-error {
            border-left: 4px solid var(--error);
        }
        
        .notification-error .notification-icon {
            color: var(--error);
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .notification-message {
            font-size: 0.9rem;
            color: #495057;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        @media (max-width: 992px) {
            .calendar-grid {
                grid-template-columns: repeat(1, 1fr);
            }
            
            .calendar-day {
                min-height: auto;
            }
            
            .weekday-header {
                display: none;
            }
            
            .calendar-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .calendar-controls {
                gap: 0.5rem;
            }
            
            .btn {
                padding: 0.5rem;
            }
            
            .legend {
                flex-wrap: wrap;
                gap: 0.75rem;
            }
            
            .notification-container {
                left: 20px;
                right: 20px;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="notification-container" id="notificationContainer">
        <?php if ($notification): ?>
        <div class="notification notification-<?= $notification['type'] ?>">
            <div class="notification-icon">
                <i class="fas <?= $notification['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> fa-2x"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title"><?= $notification['title'] ?></div>
                <div class="notification-message"><?= $notification['message'] ?></div>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-container">
    <nav class="breadcrumb px-3 py-2 mb-4">
    <ol class="breadcrumb m-0">
    <style>
        .breadcrumb {
            background: rgba(255, 255, 255, 0.9);
            padding: 1rem;
            border-radius: 12px;
            /* box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); */
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
        <a href="dashboard.php">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
    </li>
    <li class="breadcrumb-item active">
        <i class="fas fa-calendar-check me-2"></i>Appointments
    </li>
</ol>

</nav>

<style>
.breadcrumb {
    background: rgba(255, 255, 255, 0.9);
    padding: 1rem;
    /* border-radius: 12px; */
    /* box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); */
}

.breadcrumb-item a {
    color: var(--primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.breadcrumb-item a:hover {
    color: #1f8e7d;
}

.breadcrumb-item.active {
    font-weight: bold;
    color: #6c757d;
}
</style>

        <div class="card calendar-header">
            <div class="calendar-controls">
                <div class="calendar-title">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?= $date->format('F Y') ?></span>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="?month=<?= $prevMonth ?>&search=<?= urlencode($search) ?>" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="?month=<?= date('Y-m') ?>" 
                       class="btn btn-outline-primary">
                        Today
                    </a>
                    <a href="?month=<?= $nextMonth ?>&search=<?= urlencode($search) ?>" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus me-2"></i>New Appointment
                    </button>
                </div>
            </div>

            <style>
.search-icon {
    position: absolute;
    top: 50%;
    left: 12px;
    transform: translateY(-50%);
    color: #888;
    pointer-events: none;
}

.form-control.ps-4 {
    padding-left: 2.2rem !important; /* Ensures space for icon */
}

.clear-search {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #888;
    cursor: pointer;
}
</style>

<div class="search-container">
    <form>
        <input type="hidden" name="month" value="<?= $currentMonth ?>">
        <div class="position-relative">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="search"  value="<?= htmlspecialchars($search) ?>"
                   class="form-control ps-4" placeholder="Search patients...">
            <?php if($search): ?>
                <button class="clear-search" type="button" onclick="clearSearch()">
                    <i class="fas fa-times"></i>
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

            <div class="legend">
                <div class="legend-item">
                    <div class="status-indicator scheduled"></div>
                    <span>Scheduled</span>
                </div>
                <div class="legend-item">
                    <div class="status-indicator completed"></div>
                    <span>Completed</span>
                </div>
                <div class="legend-item">
                    <div class="status-indicator cancelled"></div>
                    <span>Cancelled</span>
                </div>
            </div>
        </div>

        <div class="calendar-grid">
            <div class="weekday-header">Mon</div>
            <div class="weekday-header">Tue</div>
            <div class="weekday-header">Wed</div>
            <div class="weekday-header">Thu</div>
            <div class="weekday-header">Fri</div>
            <div class="weekday-header weekend">Sat</div>
            <div class="weekday-header weekend">Sun</div>

            <?php
            $startDay = (clone $date)->modify('-' . ($firstWeekday - 1) . ' days');
            
            for ($i = 0; $i < $weeks * 7; $i++) {
                $currentDate = $startDay->format('Y-m-d');
                $isCurrentMonth = $startDay->format('Y-m') === $date->format('Y-m');
                $isToday = $currentDate === $today;
                $isWeekend = in_array($startDay->format('D'), ['Sat', 'Sun']);
                $apptCount = isset($appointmentsByDate[$currentDate]) ? count($appointmentsByDate[$currentDate]) : 0;
                $hasAppointments = $apptCount > 0;
                ?>
                <div class="calendar-day 
                    <?= !$isCurrentMonth ? 'non-month-day' : '' ?> 
                    <?= $isToday ? 'today' : '' ?>
                    <?= $isWeekend ? 'weekend' : '' ?>">
                    
                    <div class="day-header">
                        <div class="day-number"><?= $startDay->format('j') ?></div>
                        <?php if($isCurrentMonth && !$hasAppointments): ?>
                            <div class="add-appointment-btn" 
                                 data-date="<?= $currentDate ?>"
                                 data-bs-toggle="modal" 
                                 data-bs-target="#addModal"
                                 title="Add appointment">
                                <i class="fas fa-plus"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($hasAppointments): ?>
                        <div class="appointment-count"><?= $apptCount ?></div>
                    <?php endif; ?>
                    
                    <div class="events">
                        <?php if ($isCurrentMonth && $hasAppointments): ?>
                            <?php foreach ($appointmentsByDate[$currentDate] as $appt): ?>
                                <div class="event-item <?= $appt['status'] ?>" 
                                     data-bs-toggle="modal" 
                                     data-bs-target="#detailModal<?= $appt['id'] ?>">
                                    <div class="event-time">
                                        <?= date('g:i A', strtotime($appt['appointment_time'])) ?>
                                    </div>
                                    <div class="event-patient">
                                        <?= htmlspecialchars($appt['patient_name']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif($isCurrentMonth): ?>
                            <div class="empty-day">No appointments</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                $startDay->modify('+1 day');
            }
            ?>
        </div>
    </div>

    <?php foreach ($appointments as $appt): ?>
    <div class="modal fade" id="detailModal<?= $appt['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-day"></i>
                        <span>Appointment Details</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-end mb-3">
                        <span class="status-badge badge-<?= $appt['status'] ?>">
                            <?= ucfirst($appt['status']) ?>
                        </span>
                    </div>
                    
                    <div class="patient-info">
                        <div class="patient-avatar">
                            <?= strtoupper(substr($appt['patient_name'], 0, 1)) ?>
                        </div>
                        <div class="patient-details">
                            <div class="patient-name"><?= htmlspecialchars($appt['patient_name']) ?></div>
                            <div class="patient-phone">
                                <i class="fas fa-phone"></i>
                                <span><?= $appt['patient_phone'] ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Date & Time</div>
                        <div class="detail-value">
                            <?= date('F j, Y', strtotime($appt['appointment_date'])) ?> 
                            at <?= date('g:i A', strtotime($appt['appointment_time'])) ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Treatment</div>
                        <div class="detail-value"><?= htmlspecialchars($appt['treatment_type']) ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Notes</div>
                        <div class="detail-value"><?= nl2br(htmlspecialchars($appt['notes'] ?: 'No notes')) ?></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="action-buttons">
                        <button type="button" class="btn btn-primary btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editModal"
                            data-id="<?= $appt['id'] ?>"
                            data-patient-id="<?= $appt['patient_id'] ?>"
                            data-date="<?= $appt['appointment_date'] ?>"
                            data-time="<?= $appt['appointment_time'] ?>"
                            data-treatment="<?= htmlspecialchars($appt['treatment_type']) ?>"
                            data-status="<?= $appt['status'] ?>"
                            data-notes="<?= htmlspecialchars($appt['notes']) ?>"
                            data-bs-dismiss="modal">
                            <i class="fas fa-edit me-2"></i>Edit
                        </button>
                        
                        <a href="delete_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash me-2"></i>Delete
                        </a>
                        
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form action="add_appointment.php" method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>New Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date *</label>
                            <input type="date" name="appointment_date" class="form-control" required id="addDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time *</label>
                            <input type="time" name="appointment_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Treatment</label>
                            <input type="text" name="treatment_type" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form action="update_appointment.php" method="POST" class="modal-content">
                <input type="hidden" name="id" id="editAppointmentId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-select" required id="editPatient">
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date *</label>
                            <input type="date" name="appointment_date" class="form-control" required id="editDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time *</label>
                            <input type="time" name="appointment_time" class="form-control" required id="editTime">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Treatment</label>
                            <input type="text" name="treatment_type" class="form-control" id="editTreatment">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" id="editStatus">
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" id="editNotes"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize edit modal data
            document.querySelectorAll('[data-bs-target="#editModal"]').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const patientId = this.dataset.patientId;
                    const date = this.dataset.date;
                    const time = this.dataset.time;
                    const treatment = this.dataset.treatment;
                    const status = this.dataset.status;
                    const notes = this.dataset.notes;

                    document.getElementById('editAppointmentId').value = id;
                    document.getElementById('editPatient').value = patientId;
                    document.getElementById('editDate').value = date;
                    document.getElementById('editTime').value = time;
                    document.getElementById('editTreatment').value = treatment;
                    document.getElementById('editStatus').value = status;
                    document.getElementById('editNotes').value = notes;
                });
            });
            
            // Set date for add modal when clicking on day
            document.querySelectorAll('.add-appointment-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const date = this.dataset.date;
                    document.getElementById('addDate').value = date;
                });
            });
            
            // Auto-close notifications after 4 seconds
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.style.animation = 'fadeOut 0.5s ease-in forwards';
                    setTimeout(() => notification.remove(), 500);
                }, 3500);
            });
        });
        
        function clearSearch() {
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>