<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logout - DentalCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .logout-card {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(44,181,160,0.15);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            max-width: 500px;
            margin: 0 auto;
            padding: 2rem;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
        }

        .btn-logout {
            background: var(--accent);
            border: none;
            padding: 0.8rem 2rem;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255,127,80,0.3);
        }
    </style>
</head>
<body>
    <!-- Confirmation Modal -->
    <div class="modal fade show" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="false" style="display: block;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-primary" id="logoutModalLabel">
                        <i class="fas fa-sign-out-alt me-2"></i>Confirm Logout
                    </h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="display-4 text-primary mb-3">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h5 class="mb-3">Are you sure you want to log out?</h5>
                    <p class="text-muted">You'll need to log in again to access the system</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <form method="POST">
                        <button type="button" class="btn btn-secondary me-3" onclick="window.history.back()">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="confirm_logout" class="btn btn-logout text-white">
                            <i class="fas fa-sign-out-alt me-2"></i>Log Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle back button click
        function goBack() {
            window.history.back();
        }
        
        // Initialize modal
        var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'), {
            backdrop: 'static',
            keyboard: false
        });
        logoutModal.show();
    </script>
</body>
</html>