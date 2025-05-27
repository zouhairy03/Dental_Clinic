<?php
require_once 'config.php';

// Restrict access if not logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Doctor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DentalCare Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
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
            <!-- <li class="nav-item">
                <a class="nav-link" href="dentists.php">
                    <i class="fas fa-user-md me-2"></i>Dentists
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admins.php">
                    <i class="fas fa-users-cog me-2"></i>Admins
                </a>
            </li> -->
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
                <button class="icon-btn position-relative">
                    <i class="fas fa-bell text-secondary"></i>
                    <span class="badge bg-accent position-absolute top-0 start-100 translate-middle">3</span>
                </button>
                <a href="logout.php" class="btn btn-sm btn-outline-danger px-3">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="container-fluid">
            <div class="welcome-card p-4 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary rounded-circle p-3">
                        <i class="fas fa-smile-beam fa-2x text-white"></i>
                    </div>
                    <div>
                        <h3 class="mb-1">Good Morning, Dr. <?= $adminName ?></h3>
                        <p class="text-muted mb-0">You have 5 appointments today</p>
                    </div>
                </div>
            </div>

            <!-- Metrics Grid -->
            <div class="row g-4">
                <div class="col-xxl-3 col-md-6">
                    <div class="metric-card bg-primary text-white p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase small mb-3">Appointments</h6>
                                <h2 class="mb-0">15</h2>
                            </div>
                            <i class="fas fa-calendar-check fa-3x opacity-25"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-xxl-3 col-md-6">
                    <div class="metric-card bg-info text-white p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase small mb-3">Patients</h6>
                                <h2 class="mb-0">234</h2>
                            </div>
                            <i class="fas fa-user-injured fa-3x opacity-25"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-3 col-md-6">
                    <div class="metric-card bg-success text-white p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase small mb-3">Procedures</h6>
                                <h2 class="mb-0">42</h2>
                            </div>
                            <i class="fas fa-tooth fa-3x opacity-25"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-3 col-md-6">
                    <div class="metric-card bg-warning text-white p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase small mb-3">Pending</h6>
                                <h2 class="mb-0">8</h2>
                            </div>
                            <i class="fas fa-file-waveform fa-3x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('mainContent');
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('expanded');
            
            // Smooth animation handling
            if(sidebar.classList.contains('collapsed')) {
                sidebar.style.transform = 'translateX(-100%)';
                content.style.marginLeft = '0';
            } else {
                sidebar.style.transform = 'translateX(0)';
                content.style.marginLeft = '280px';
            }
        }

        // Add staggered animations for metric cards
        document.querySelectorAll('.metric-card').forEach((card, index) => {
            card.style.animation = `cardEntrance 0.6s ease-out ${index * 0.1}s both`;
        });
    </script>
</body>
</html>