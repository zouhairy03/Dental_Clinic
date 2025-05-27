<?php
require_once 'config.php';

// Calendar configuration
$currentMonth = $_GET['month'] ?? date('Y-m');
$search = $_GET['search'] ?? '';
$date = new DateTime($currentMonth . '-01');
$monthStart = $date->format('Y-m-01');
$monthEnd = $date->format('Y-m-t');
$prevMonth = (clone $date)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $date)->modify('+2 month')->format('Y-m');

// Fetch appointments
$stmt = $pdo->prepare("SELECT a.*, p.full_name AS patient_name 
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dental Calendar - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --scheduled: #ff7f50;
            --completed: #2cb5a0;
            --cancelled: #dc3545;
            --secondary: #f0f7fa;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(160deg, #f8f9fa 0%, #e3f2fd 100%);
            min-height: 100vh;
        }

        .calendar-header {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .calendar-grid {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .calendar-day {
            min-height: 150px;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem;
            transition: all 0.2s ease;
            position: relative;
        }

        .calendar-day:hover {
            background: var(--secondary);
            transform: scale(1.02);
            z-index: 1;
        }

        .day-number {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .event-item {
            background: white;
            border-radius: 6px;
            padding: 0.5rem;
            margin: 0.2rem 0;
            font-size: 0.85em;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .event-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
            flex-shrink: 0;
        }

        .scheduled { background: var(--scheduled); }
        .completed { background: var(--completed); }
        .cancelled { background: var(--cancelled); }

        .non-month-day {
            background: #f8f9fa;
            color: #adb5bd;
        }

        .today {
            background: #e3f2fd;
            font-weight: 600;
        }

        .weekday-header {
            background: var(--secondary);
            font-weight: 500;
            padding: 1rem;
            text-align: center;
        }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <!-- Header Section -->
        <div class="calendar-header p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0 text-primary">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?= $date->format('F Y') ?>
                </h2>
                <div class="d-flex gap-2">
                    <a href="?month=<?= $prevMonth ?>&search=<?= urlencode($search) ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="?month=<?= $nextMonth ?>&search=<?= urlencode($search) ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus me-2"></i>New
                    </button>
                </div>
            </div>

            <!-- Search Form -->
            <form>
                <input type="hidden" name="month" value="<?= $currentMonth ?>">
                <div class="input-group">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           class="form-control" placeholder="Search patients...">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-grid">
            <!-- Weekday Headers -->
            <div class="row row-cols-7 g-0">
                <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day): ?>
                    <div class="col weekday-header">
                        <small><?= $day ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Calendar Days -->
            <div class="row row-cols-7 g-0">
                <?php
                $startDay = (clone $date)->modify('-' . ($firstWeekday - 1) . ' days');
                
                for ($i = 0; $i < $weeks * 7; $i++) {
                    $currentDate = $startDay->format('Y-m-d');
                    $isCurrentMonth = $startDay->format('Y-m') === $date->format('Y-m');
                    $isToday = $currentDate === $today;
                    ?>
                    <div class="col calendar-day <?= !$isCurrentMonth ? 'non-month-day' : '' ?> <?= $isToday ? 'today' : '' ?>">
                        <div class="day-number"><?= $startDay->format('j') ?></div>
                        <div class="events">
                            <?php if ($isCurrentMonth && isset($appointmentsByDate[$currentDate])): ?>
                                <?php foreach ($appointmentsByDate[$currentDate] as $appt): ?>
                                    <div class="event-item" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#detailModal<?= $appt['id'] ?>">
                                        <div class="d-flex align-items-center">
                                            <div class="status-indicator <?= $appt['status'] ?>"></div>
                                            <div>
                                                <div class="text-dark fw-medium">
                                                    <?= $appt['appointment_time'] ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars($appt['patient_name']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    $startDay->modify('+1 day');
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Detail Modals -->
    <?php foreach ($appointments as $appt): ?>
    <div class="modal fade" id="detailModal<?= $appt['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-day me-2"></i>
                        <?= $appt['appointment_date'] ?> at <?= $appt['appointment_time'] ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="status-indicator <?= $appt['status'] ?> me-2"></div>
                        <span class="text-uppercase fw-medium text-<?= $appt['status'] ?>">
                            <?= ucfirst($appt['status']) ?>
                        </span>
                    </div>
                    <dl class="row">
                        <dt class="col-sm-3">Patient</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($appt['patient_name']) ?></dd>

                        <dt class="col-sm-3">Treatment</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($appt['treatment_type']) ?></dd>

                        <dt class="col-sm-3">Notes</dt>
                        <dd class="col-sm-9"><?= nl2br(htmlspecialchars($appt['notes'])) ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form action="add_appointment.php" method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>New Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Patient</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="appointment_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time</label>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>