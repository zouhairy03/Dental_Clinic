<?php
require_once 'config.php';

// Appointment Summary
$appointmentSummary = $pdo->query("SELECT status, COUNT(*) AS count FROM appointments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$appointmentTrends = $pdo->query("SELECT appointment_date AS date, COUNT(*) AS count FROM appointments GROUP BY appointment_date ORDER BY appointment_date DESC LIMIT 30")->fetchAll();

$treatmentStats = $pdo->query("SELECT treatment_type, COUNT(*) AS count FROM appointments GROUP BY treatment_type ORDER BY count DESC")->fetchAll();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DentalCare Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2cb5a0;
            --secondary: #f0f7fa;
            --accent: #ff7f50;
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
    </style>
</head>
<body>
<div class="container">
    <h1 class="mb-4"><i class="fas fa-chart-pie me-2"></i>Dental Clinic Analytics</h1>

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
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts Grid -->
    <div class="row g-4">
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
</div>

<script>
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
    secondary: 'rgba(108,117,125,0.8)'
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
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
</body>
</html>