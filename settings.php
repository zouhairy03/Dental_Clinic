<?php
require_once 'config.php';

// Only allow logged-in admins
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$message = '';

// Fetch current admin data
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($name && $username) {
        // Update query
        $sql = "UPDATE admins SET name = ?, username = ?, updated_at = NOW()";
        $params = [$name, $username];

        if ($password !== '') {
            $sql .= ", password_hash = ?";
            $params[] = $password; // Plaintext as requested
        }

        $sql .= " WHERE id = ?";
        $params[] = $admin_id;

        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);

        $_SESSION['admin_name'] = $name;
        $message = 'Settings updated successfully.';
        // Refresh admin data
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();
    } else {
        $message = 'Name and username are required.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinic Settings - DentalCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

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

        .settings-card {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(44,181,160,0.15);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .settings-card:hover {
            transform: translateY(-3px);
        }

        .info-box {
            background: rgba(255,255,255,0.9);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(44,181,160,0.1);
        }

        .info-label {
            color: var(--primary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .info-value {
            color: #2a2a2a;
            font-weight: 600;
            font-size: 1rem;
        }

        .badge-last-login {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-size: 0.9rem;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(44,181,160,0.25);
        }

        .breadcrumb {
            background: rgba(255,255,255,0.9);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .alert-info {
            background: rgba(44,181,160,0.15);
            border: 1px solid var(--primary);
            color: var(--primary);
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="dashboard.php" class="text-primary"><i class="fas fa-home me-2"></i>Dashboard</a>
                </li>
                <li class="breadcrumb-item active"><i class="fas fa-cogs me-2"></i>Clinic Settings</li>
            </ol>
        </nav>

        <h3 class="text-primary mb-4"><i class="fas fa-user-md me-2"></i>Doctor Profile</h3>

        <?php if ($message): ?>
            <div class="alert alert-info mb-4"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Form Column -->
            <div class="col-lg-6">
                <div class="settings-card p-4">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label info-label">Full Name</label>
                            <input type="text" class="form-control py-2" name="name" 
                                   value="<?= htmlspecialchars($admin['name']) ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label info-label">Username</label>
                            <input type="text" class="form-control py-2" name="username" 
                                   value="<?= htmlspecialchars($admin['username']) ?>" required>
                        </div>
                        
                        <div class="mb-4">
    <label class="form-label info-label">New Password</label>
    <div class="input-group">
        <input type="password" class="form-control py-2" name="password" id="newPassword">
        <span class="input-group-text" onclick="togglePasswordVisibility()">
            <i class="bi bi-eye-slash" id="toggleIcon"></i>
        </span>
    </div>
    <small class="text-muted">Leave blank to keep current password</small>
</div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info Column -->
            <div class="col-lg-6">
                <div class="settings-card p-4 h-100">
                    <div class="info-box h-100">
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($admin['name']) ?></div>
                            </div>
                            
                            <div>
                                <div class="info-label">Username</div>
                                <div class="info-value"><?= htmlspecialchars($admin['username']) ?></div>
                            </div>
                            
                            <div>
                                <div class="info-label">Account Created</div>
                                <div class="info-value"><?= $admin['created_at'] ?></div>
                            </div>
                            
                            <div>
                                <div class="info-label">Last Updated</div>
                                <div class="info-value"><?= $admin['updated_at'] ?? 'Never' ?></div>
                            </div>
                            
                            <div>
                                <div class="info-label" style="margin-bottom: 10px;">Last Login</div>

                                <span class="badge-last-login"><?= $admin['last_login'] ?? 'Never' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('newPassword');
    const icon = document.getElementById('toggleIcon');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    icon.classList.toggle('bi-eye');
    icon.classList.toggle('bi-eye-slash');
}
</script>

</body>
</html>