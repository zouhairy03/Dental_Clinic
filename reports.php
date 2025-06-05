<?php
require_once 'config.php';

// Get date range from request or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Calculate previous period dates
$prev_start_date = date('Y-m-d', strtotime($start_date . ' -1 month'));
$prev_end_date = date('Y-m-d', strtotime($end_date . ' -1 month'));

// Appointment Summary with date filter
$appointmentSummaryQuery = $pdo->prepare("
    SELECT status, COUNT(*) AS count 
    FROM appointments 
    WHERE appointment_date BETWEEN :start_date AND :end_date
    GROUP BY status
");
$appointmentSummaryQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$appointmentSummary = $appointmentSummaryQuery->fetchAll(PDO::FETCH_KEY_PAIR);

// Previous period appointment summary
$prevAppointmentSummaryQuery = $pdo->prepare("
    SELECT status, COUNT(*) AS count 
    FROM appointments 
    WHERE appointment_date BETWEEN :start_date AND :end_date
    GROUP BY status
");
$prevAppointmentSummaryQuery->execute(['start_date' => $prev_start_date, 'end_date' => $prev_end_date]);
$prevAppointmentSummary = $prevAppointmentSummaryQuery->fetchAll(PDO::FETCH_KEY_PAIR);

// Appointment Trends with date filter
$appointmentTrendsQuery = $pdo->prepare("
    SELECT appointment_date AS date, COUNT(*) AS count 
    FROM appointments 
    WHERE appointment_date BETWEEN :start_date AND :end_date
    GROUP BY appointment_date 
    ORDER BY appointment_date DESC
");
$appointmentTrendsQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$appointmentTrends = $appointmentTrendsQuery->fetchAll();

// Treatment Stats with date filter
$treatmentStatsQuery = $pdo->prepare("
    SELECT treatment_type, COUNT(*) AS count 
    FROM appointments 
    WHERE appointment_date BETWEEN :start_date AND :end_date
    GROUP BY treatment_type 
    ORDER BY count DESC
");
$treatmentStatsQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$treatmentStats = $treatmentStatsQuery->fetchAll();

// Patient by working type
$workingTypeStats = $pdo->query("SELECT working_type, COUNT(*) AS count FROM patients GROUP BY working_type")->fetchAll();

// Patient by age group
$ageGroups = [
    '0-18' => [0, 18],
    '19-35' => [19, 35],
    '36-60' => [36, 60],
    '60+' => [61, 200]
];
$ageGroupCounts = [];
foreach ($ageGroups as $label => [$min, $max]) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE age BETWEEN ? AND ?");
    $stmt->execute([$min, $max]);
    $ageGroupCounts[$label] = $stmt->fetchColumn();
}

// Top Dentists with date filter
$topDentistsQuery = $pdo->prepare("
    SELECT d.name, COUNT(a.id) AS appointment_count 
    FROM dentists d
    LEFT JOIN appointments a ON d.id = a.dentist_id
    WHERE a.appointment_date BETWEEN :start_date AND :end_date
    GROUP BY d.id
    ORDER BY appointment_count DESC
    LIMIT 5
");
$topDentistsQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$topDentists = $topDentistsQuery->fetchAll();

// Frequent Patients with date filter
$topPatientsQuery = $pdo->prepare("
    SELECT p.full_name, COUNT(a.id) AS appointment_count 
    FROM patients p
    LEFT JOIN appointments a ON p.id = a.patient_id
    WHERE a.appointment_date BETWEEN :start_date AND :end_date
    GROUP BY p.id
    ORDER BY appointment_count DESC
    LIMIT 5
");
$topPatientsQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$topPatients = $topPatientsQuery->fetchAll();

// Hourly Appointments with date filter
$hourlyAppointmentsQuery = $pdo->prepare("
    SELECT 
        HOUR(appointment_time) AS hour,
        COUNT(*) AS count
    FROM appointments
    WHERE appointment_date BETWEEN :start_date AND :end_date
    GROUP BY HOUR(appointment_time)
    ORDER BY hour
");
$hourlyAppointmentsQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$hourlyAppointments = $hourlyAppointmentsQuery->fetchAll();

// ========== FINANCIAL QUERIES ========== //

// Payment Status Summary
$paymentStatusQuery = $pdo->prepare("
    SELECT status, COUNT(*) AS count 
    FROM payments 
    WHERE payment_date BETWEEN :start_date AND :end_date
    GROUP BY status
");
$paymentStatusQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$paymentStatus = $paymentStatusQuery->fetchAll(PDO::FETCH_KEY_PAIR);

// Previous period payment status
$prevPaymentStatusQuery = $pdo->prepare("
    SELECT status, COUNT(*) AS count 
    FROM payments 
    WHERE payment_date BETWEEN :start_date AND :end_date
    GROUP BY status
");
$prevPaymentStatusQuery->execute(['start_date' => $prev_start_date, 'end_date' => $prev_end_date]);
$prevPaymentStatus = $prevPaymentStatusQuery->fetchAll(PDO::FETCH_KEY_PAIR);

// Payment Method Distribution
$paymentMethodsQuery = $pdo->prepare("
    SELECT payment_method, COUNT(*) AS count 
    FROM payments 
    WHERE payment_date BETWEEN :start_date AND :end_date
    GROUP BY payment_method
");
$paymentMethodsQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$paymentMethods = $paymentMethodsQuery->fetchAll();

// Daily Revenue Trend
$revenueTrendQuery = $pdo->prepare("
    SELECT payment_date AS date, SUM(amount) AS total 
    FROM payments 
    WHERE status = 'completed' AND payment_date BETWEEN :start_date AND :end_date
    GROUP BY payment_date 
    ORDER BY payment_date
");
$revenueTrendQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$revenueTrend = $revenueTrendQuery->fetchAll();

// Previous period revenue
$prevRevenueQuery = $pdo->prepare("
    SELECT SUM(amount) AS total 
    FROM payments 
    WHERE status = 'completed' AND payment_date BETWEEN :start_date AND :end_date
");
$prevRevenueQuery->execute(['start_date' => $prev_start_date, 'end_date' => $prev_end_date]);
$prevRevenue = $prevRevenueQuery->fetchColumn() ?: 0;

// Invoice Status Summary
$invoiceStatusQuery = $pdo->prepare("
    SELECT status, COUNT(*) AS count 
    FROM invoices 
    WHERE invoice_date BETWEEN :start_date AND :end_date
    GROUP BY status
");
$invoiceStatusQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$invoiceStatus = $invoiceStatusQuery->fetchAll(PDO::FETCH_KEY_PAIR);

// Previous period invoices
$prevInvoiceStatusQuery = $pdo->prepare("
    SELECT status, COUNT(*) AS count 
    FROM invoices 
    WHERE invoice_date BETWEEN :start_date AND :end_date
    GROUP BY status
");
$prevInvoiceStatusQuery->execute(['start_date' => $prev_start_date, 'end_date' => $prev_end_date]);
$prevInvoiceStatus = $prevInvoiceStatusQuery->fetchAll(PDO::FETCH_KEY_PAIR);

// Total Revenue Calculation
$totalRevenueQuery = $pdo->prepare("
    SELECT SUM(amount) AS total 
    FROM payments 
    WHERE status = 'completed' AND payment_date BETWEEN :start_date AND :end_date
");
$totalRevenueQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$totalRevenue = $totalRevenueQuery->fetchColumn() ?: 0;

// Total Invoices
$totalInvoicesQuery = $pdo->prepare("
    SELECT COUNT(*) 
    FROM invoices 
    WHERE invoice_date BETWEEN :start_date AND :end_date
");
$totalInvoicesQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$totalInvoices = $totalInvoicesQuery->fetchColumn();

// Previous period invoices
$prevTotalInvoicesQuery = $pdo->prepare("
    SELECT COUNT(*) 
    FROM invoices 
    WHERE invoice_date BETWEEN :start_date AND :end_date
");
$prevTotalInvoicesQuery->execute(['start_date' => $prev_start_date, 'end_date' => $prev_end_date]);
$prevTotalInvoices = $prevTotalInvoicesQuery->fetchColumn();

// Total Payments
$totalPaymentsQuery = $pdo->prepare("
    SELECT COUNT(*) 
    FROM payments 
    WHERE payment_date BETWEEN :start_date AND :end_date
");
$totalPaymentsQuery->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$totalPayments = $totalPaymentsQuery->fetchColumn();

// Previous period payments
$prevTotalPaymentsQuery = $pdo->prepare("
    SELECT COUNT(*) 
    FROM payments 
    WHERE payment_date BETWEEN :start_date AND :end_date
");
$prevTotalPaymentsQuery->execute(['start_date' => $prev_start_date, 'end_date' => $prev_end_date]);
$prevTotalPayments = $prevTotalPaymentsQuery->fetchColumn();

// Calculate percentage changes
function calculateChange($current, $previous) {
    if ($previous == 0) return $current == 0 ? 0 : 100;
    return round((($current - $previous) / $previous) * 100);
}

// Appointment status changes
$appointmentChanges = [];
foreach (['scheduled', 'completed', 'cancelled'] as $status) {
    $current = $appointmentSummary[$status] ?? 0;
    $previous = $prevAppointmentSummary[$status] ?? 0;
    $appointmentChanges[$status] = calculateChange($current, $previous);
}

// Financial changes
$revenueChange = calculateChange($totalRevenue, $prevRevenue);
$invoicesChange = calculateChange($totalInvoices, $prevTotalInvoices);
$paymentsChange = calculateChange($totalPayments, $prevTotalPayments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DentalCare Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2cb5a0;
            --secondary: #f0f7fa;
            --accent: #ff7f50;
            --revenue: #28a745;
            --invoices: #17a2b8;
            --payments: #6f42c1;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: url('data:image/svg+xml,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="%232cb5a033" d="M44.6,-58.1C56.3,-49.6,62.6,-33.3,66.1,-16.8C69.6,-0.3,70.4,16.5,63.9,29.1C57.4,41.7,43.7,50.2,29.9,56.9C16.1,63.6,2.2,68.5,-12.6,67.7C-27.4,66.8,-42.9,60.2,-55.4,50.3C-67.9,40.4,-77.3,27.2,-79.9,12.6C-82.5,-2.1,-78.3,-18.2,-69.3,-31.1C-60.3,-44,-46.5,-53.7,-32.3,-61.3C-18.1,-68.9,-3.5,-74.4,12.1,-71.3C27.7,-68.2,55.4,-56.5,62.7,-42.5C70,-28.5,57,-12.3,53.9,2.1C50.8,16.5,57.6,33,55.9,47.8C54.2,62.6,44,75.7,31.8,81.8C19.6,87.9,5.3,87.1,-8.2,84.1C-21.7,81.2,-35.3,76.1,-45.6,67.3C-55.9,58.4,-62.8,45.8,-68.9,33.3C-75,20.8,-80.3,8.4,-79.8,-3.7C-79.3,-15.8,-73,-31.6,-63.3,-44.5C-53.6,-57.4,-40.5,-67.4,-26.6,-74.3C-12.7,-81.1,2,-84.8,16.4,-83.3C30.8,-81.8,45.1,-75,56.8,-65.3C68.5,-55.5,77.7,-42.7,81.2,-28.6C84.7,-14.5,82.5,0.9,76.5,13.4C70.5,25.8,60.7,35.3,49.9,44.3C39.1,53.3,27.3,61.8,14.1,64.3C0.9,66.8,-13.6,63.4,-25.4,57.5C-37.2,51.6,-46.3,43.3,-54.3,34.1C-62.3,24.9,-69.2,14.8,-71.7,3.3C-74.3,-8.3,-72.5,-21.3,-66.3,-32.2C-60.1,-43.1,-49.5,-51.9,-37.8,-60.3C-26.1,-68.7,-13,-76.6,1.1,-78.6C15.2,-80.6,30.5,-76.7,44.6,-58.1Z"/></svg>'),
                        linear-gradient(160deg, #f8f9fa 0%, #e3f2fd 100%);
            background-size: cover;
            padding: 2rem;
        }

        .dashboard-card {
            background: rgba(255,255,255,0.9);
            border-radius: 20px;
            border: 1px solid rgba(44,181,160,0.15);
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-container {
            padding: 1.5rem;
            height: 400px;
        }

        h1 {
            color: var(--primary);
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(44,181,160,0.1);
        }
        .breadcrumb {
            background: rgba(255,255,255,0.9);
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .breadcrumb-item a:hover {
            color: #1f8e7d;
        }
        
        .date-filter-card {
            background: rgba(255,255,255,0.9);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .date-filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .date-range-inputs {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .date-input-group {
            flex: 1;
        }
        
        .btn-apply {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 0.5rem 1.5rem;
        }
        
        .btn-apply:hover {
            background-color: #1f8e7d;
            border-color: #1f8e7d;
        }
        
        .filter-summary {
            background: rgba(44,181,160,0.1);
            border-left: 4px solid var(--primary);
            padding: 0.75rem 1rem;
            border-radius: 0 8px 8px 0;
            margin-top: 1rem;
        }
        
        .section-title {
            position: relative;
            padding-bottom: 10px;
            margin: 2rem 0 1.5rem;
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
        
        .change-indicator {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .change-positive {
            color: #28a745;
        }
        
        .change-negative {
            color: #dc3545;
        }
        
        .change-neutral {
            color: #6c757d;
        }

    </style>
</head>
<body>
<nav class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><i class="fas fa-home me-2"></i><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active"><i class="fas fa-file-waveform  me-2"></i>Reports</li>
    </ol>
</nav>

<div class="container">
    <h1 class="mb-4"><i class="fas fa-chart-pie me-2"></i>Dental Clinic Analytics</h1>
    
    <!-- Date Filter Card -->
    <div class="date-filter-card">
        <div class="date-filter-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Reports</h5>
            <button class="btn btn-sm btn-outline-secondary" id="reset-dates">
                <i class="fas fa-sync me-1"></i> Reset
            </button>
        </div>
        
        <form method="GET" id="date-filter-form">
            <div class="date-range-inputs">
                <div class="date-input-group">
                    <label class="form-label small text-muted">Start Date</label>
                    <input type="text" class="form-control date-picker" id="start_date" name="start_date" 
                           value="<?= htmlspecialchars($start_date) ?>" placeholder="Select start date">
                </div>
                
                <div class="date-input-group">
                    <label class="form-label small text-muted">End Date</label>
                    <input type="text" class="form-control date-picker" id="end_date" name="end_date" 
                           value="<?= htmlspecialchars($end_date) ?>" placeholder="Select end date">
                </div>
                
                <div class="date-input-group align-self-end">
                    <button type="submit" class="btn btn-apply w-100">
                        <i class="fas fa-check me-1"></i> Apply
                    </button>
                </div>
            </div>
        </form>
        
        <div class="filter-summary">
            <i class="fas fa-info-circle me-2"></i>
            Showing data from <strong><?= date('M j, Y', strtotime($start_date)) ?></strong> to <strong><?= date('M j, Y', strtotime($end_date)) ?></strong>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <?php 
        $statusConfig = [
            'scheduled' => ['color' => 'var(--accent)', 'icon' => 'fa-calendar-check'],
            'completed' => ['color' => 'var(--primary)', 'icon' => 'fa-check-circle'],
            'cancelled' => ['color' => '#6c757d', 'icon' => 'fa-times-circle']
        ];
        
        foreach ($statusConfig as $status => $config): 
            $count = $appointmentSummary[$status] ?? 0;
            $change = $appointmentChanges[$status];
            $changeClass = $change > 0 ? 'change-positive' : ($change < 0 ? 'change-negative' : 'change-neutral');
            $arrowIcon = $change > 0 ? 'fa-arrow-up' : ($change < 0 ? 'fa-arrow-down' : 'fa-minus');
        ?>
        <div class="col-md-4">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center">
                    <div class="metric-icon me-3" style="background: <?= $config['color'] ?>">
                        <i class="fas <?= $config['icon'] ?> fa-2x text-white"></i>
                    </div>
                    <div>
                        <div class="text-muted text-uppercase small"><?= ucfirst($status) ?></div>
                        <div class="h2 mb-0" style="color: <?= $config['color'] ?>"><?= $count ?></div>
                        <div class="change-indicator <?= $changeClass ?>">
                            <i class="fas <?= $arrowIcon ?>"></i>
                            <span><?= abs($change) ?>% from last month</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Financial Summary Cards -->
    <h3 class="section-title">Financial Summary</h3>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center">
                    <div class="metric-icon me-3" style="background: var(--revenue)">
                        <i class="fas fa-money-bill-wave fa-2x text-white"></i>
                    </div>
                    <div>
                        <div class="text-muted text-uppercase small">Total Revenue</div>
                        <div class="h2 mb-0" style="color: var(--revenue)"><?= number_format($totalRevenue, 2) ?> MAD</div>
                        <?php
                        $changeClass = $revenueChange > 0 ? 'change-positive' : ($revenueChange < 0 ? 'change-negative' : 'change-neutral');
                        $arrowIcon = $revenueChange > 0 ? 'fa-arrow-up' : ($revenueChange < 0 ? 'fa-arrow-down' : 'fa-minus');
                        ?>
                        <div class="change-indicator <?= $changeClass ?>">
                            <i class="fas <?= $arrowIcon ?>"></i>
                            <span><?= abs($revenueChange) ?>% from last month</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center">
                    <div class="metric-icon me-3" style="background: var(--invoices)">
                        <i class="fas fa-file-invoice fa-2x text-white"></i>
                    </div>
                    <div>
                        <div class="text-muted text-uppercase small">Total Invoices</div>
                        <div class="h2 mb-0" style="color: var(--invoices)"><?= $totalInvoices ?></div>
                        <?php
                        $changeClass = $invoicesChange > 0 ? 'change-positive' : ($invoicesChange < 0 ? 'change-negative' : 'change-neutral');
                        $arrowIcon = $invoicesChange > 0 ? 'fa-arrow-up' : ($invoicesChange < 0 ? 'fa-arrow-down' : 'fa-minus');
                        ?>
                        <div class="change-indicator <?= $changeClass ?>">
                            <i class="fas <?= $arrowIcon ?>"></i>
                            <span><?= abs($invoicesChange) ?>% from last month</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-card p-3">
                <div class="d-flex align-items-center">
                    <div class="metric-icon me-3" style="background: var(--payments)">
                        <i class="fas fa-credit-card fa-2x text-white"></i>
                    </div>
                    <div>
                        <div class="text-muted text-uppercase small">Total Payments</div>
                        <div class="h2 mb-0" style="color: var(--payments)"><?= $totalPayments ?></div>
                        <?php
                        $changeClass = $paymentsChange > 0 ? 'change-positive' : ($paymentsChange < 0 ? 'change-negative' : 'change-neutral');
                        $arrowIcon = $paymentsChange > 0 ? 'fa-arrow-up' : ($paymentsChange < 0 ? 'fa-arrow-down' : 'fa-minus');
                        ?>
                        <div class="change-indicator <?= $changeClass ?>">
                            <i class="fas <?= $arrowIcon ?>"></i>
                            <span><?= abs($paymentsChange) ?>% from last month</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Grid -->
    <h3 class="section-title">Appointments & Patients</h3>
    <div class="row g-4 mb-5">
        <!-- Appointments Trend -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Appointments Trend</h5>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Treatment Distribution -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-teeth-open me-2"></i>Treatment Types</h5>
                </div>
                <div class="chart-container">
                    <canvas id="treatmentChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Patient Demographics -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Employment Status</h5>
                </div>
                <div class="chart-container">
                    <canvas id="employmentChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Age Groups -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Age Distribution</h5>
                </div>
                <div class="chart-container">
                    <canvas id="ageChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Financial Charts -->
    <h3 class="section-title">Financial Analytics</h3>
    <div class="row g-4 mb-5">
        <!-- Revenue Trend -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Revenue Trend</h5>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Payment Status -->
        <div class="col-lg-3">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-money-check me-2"></i>Payment Status</h5>
                </div>
                <div class="chart-container">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Invoice Status -->
        <div class="col-lg-3">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Invoice Status</h5>
                </div>
                <div class="chart-container">
                    <canvas id="invoiceStatusChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Payment Methods -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Methods</h5>
                </div>
                <div class="chart-container">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lists -->
    <div class="row g-4">
        <!-- Top Dentists -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-user-md me-2"></i>Top Dentists</h5>
                </div>
                <div class="p-3">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topDentists as $dentist): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= $dentist['name'] ?>
                            <span class="badge bg-primary rounded-pill"><?= $dentist['appointment_count'] ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Frequent Patients -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Frequent Patients</h5>
                </div>
                <div class="p-3">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topPatients as $patient): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= $patient['full_name'] ?>
                            <span class="badge bg-success rounded-pill"><?= $patient['appointment_count'] ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Hourly Distribution -->
        <div class="col-12">
            <div class="dashboard-card">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Appointments by Time of Day</h5>
                </div>
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Initialize date pickers
flatpickr('.date-picker', {
    dateFormat: "Y-m-d",
    allowInput: true,
    maxDate: "today"
});

// Reset dates button
document.getElementById('reset-dates').addEventListener('click', function() {
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    document.getElementById('date-filter-form').submit();
});

// Chart Configuration
const chartConfig = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top', labels: { font: { family: 'Poppins', size: 14 } } },
        tooltip: { 
            bodyFont: { family: 'Poppins', size: 14 },
            titleFont: { family: 'Poppins', size: 14 }
        }
    },
    scales: {
        x: { 
            grid: { display: false },
            ticks: { font: { family: 'Poppins', size: 12 } }
        },
        y: { 
            grid: { color: '#f0f0f0' },
            ticks: { font: { family: 'Poppins', size: 12 } }
        }
    }
};

// Color Palette
const colors = {
    primary: 'rgba(44,181,160,0.8)',
    accent: 'rgba(255,127,80,0.8)',
    secondary: 'rgba(108,117,125,0.8)',
    revenue: 'rgba(40,167,69,0.8)',
    invoices: 'rgba(23,162,184,0.8)',
    payments: 'rgba(111,66,193,0.8)'
};

// Appointments Trend Chart
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($appointmentTrends, 'date')) ?>,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode(array_column($appointmentTrends, 'count')) ?>,
            borderColor: colors.primary,
            backgroundColor: 'rgba(44,181,160,0.1)',
            fill: true,
            tension: 0.4,
            borderWidth: 2
        }]
    },
    options: chartConfig
});

// Treatment Distribution Chart
new Chart(document.getElementById('treatmentChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($treatmentStats, 'treatment_type')) ?>,
        datasets: [{
            label: 'Procedures',
            data: <?= json_encode(array_column($treatmentStats, 'count')) ?>,
            backgroundColor: [colors.primary, colors.accent, colors.secondary],
            borderRadius: 8
        }]
    },
    options: chartConfig
});

// Employment Status Chart
new Chart(document.getElementById('employmentChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($workingTypeStats, 'working_type')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($workingTypeStats, 'count')) ?>,
            backgroundColor: [colors.primary, colors.accent, colors.secondary, '#20c997'],
            borderWidth: 0
        }]
    },
    options: {
        ...chartConfig,
        cutout: '60%',
        plugins: {
            legend: { position: 'right' }
        }
    }
});

// Age Distribution Chart
new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($ageGroupCounts)) ?>,
        datasets: [{
            label: 'Patients',
            data: <?= json_encode(array_values($ageGroupCounts)) ?>,
            backgroundColor: colors.accent,
            borderRadius: 8
        }]
    },
    options: chartConfig
});

// Hourly Distribution Chart
const hourlyLabels = Array.from({length: 24}, (_, i) => `${i}:00`);
const hourlyData = Array(24).fill(0);

<?php foreach ($hourlyAppointments as $hour): ?>
hourlyData[<?= $hour['hour'] ?>] = <?= $hour['count'] ?>;
<?php endforeach; ?>

new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
        labels: hourlyLabels,
        datasets: [{
            label: 'Appointments',
            data: hourlyData,
            backgroundColor: colors.primary,
            borderColor: colors.primary,
            borderWidth: 1
        }]
    },
    options: {
        ...chartConfig,
        scales: {
            ...chartConfig.scales,
            x: {
                ...chartConfig.scales.x,
                title: {
                    display: true,
                    text: 'Hour of Day'
                }
            },
            y: {
                ...chartConfig.scales.y,
                title: {
                    display: true,
                    text: 'Number of Appointments'
                }
            }
        }
    }
});

// ========== FINANCIAL CHARTS ========== //

// Revenue Trend Chart
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($revenueTrend, 'date')) ?>,
        datasets: [{
            label: 'Revenue (MAD)',
            data: <?= json_encode(array_column($revenueTrend, 'total')) ?>,
            borderColor: colors.revenue,
            backgroundColor: 'rgba(40,167,69,0.1)',
            fill: true,
            tension: 0.4,
            borderWidth: 2
        }]
    },
    options: {
        ...chartConfig,
        scales: {
            ...chartConfig.scales,
            y: {
                ...chartConfig.scales.y,
                title: {
                    display: true,
                    text: 'Amount (MAD)'
                }
            }
        }
    }
});

// Payment Status Chart
const paymentStatusData = {
    labels: ['Completed', 'Pending', 'Failed', 'Refunded'],
    datasets: [{
        data: [
            <?= $paymentStatus['completed'] ?? 0 ?>,
            <?= $paymentStatus['pending'] ?? 0 ?>,
            <?= $paymentStatus['failed'] ?? 0 ?>,
            <?= $paymentStatus['refunded'] ?? 0 ?>
        ],
        backgroundColor: [
            '#28a745',
            '#ffc107',
            '#dc3545',
            '#6c757d'
        ],
        borderWidth: 0
    }]
};

new Chart(document.getElementById('paymentStatusChart'), {
    type: 'doughnut',
    data: paymentStatusData,
    options: {
        ...chartConfig,
        cutout: '60%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Invoice Status Chart
const invoiceStatusData = {
    labels: ['Paid', 'Pending', 'Overdue', 'Partial', 'Cancelled'],
    datasets: [{
        data: [
            <?= $invoiceStatus['paid'] ?? 0 ?>,
            <?= $invoiceStatus['pending'] ?? 0 ?>,
            <?= $invoiceStatus['overdue'] ?? 0 ?>,
            <?= $invoiceStatus['partial'] ?? 0 ?>,
            <?= $invoiceStatus['cancelled'] ?? 0 ?>
        ],
        backgroundColor: [
            '#28a745',
            '#ffc107',
            '#dc3545',
            '#17a2b8',
            '#6c757d'
        ],
        borderWidth: 0
    }]
};

new Chart(document.getElementById('invoiceStatusChart'), {
    type: 'doughnut',
    data: invoiceStatusData,
    options: {
        ...chartConfig,
        cutout: '60%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Payment Methods Chart
const paymentMethodData = {
    labels: <?= json_encode(array_column($paymentMethods, 'payment_method')) ?>,
    datasets: [{
        data: <?= json_encode(array_column($paymentMethods, 'count')) ?>,
        backgroundColor: [
            colors.primary,
            colors.accent,
            colors.revenue,
            colors.payments,
            colors.secondary
        ],
        borderWidth: 0
    }]
};

new Chart(document.getElementById('paymentMethodChart'), {
    type: 'pie',
    data: paymentMethodData,
    options: {
        ...chartConfig,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
</body>
</html>