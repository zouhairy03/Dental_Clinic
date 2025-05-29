<?php
session_start();
require_once 'config.php';

// Only allow logged-in admins
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$message = '';
$showPassword = false;
$passwordViewCountdown = 59;

// Fetch current admin data
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $showPassword = isset($_POST['show_password']) ? (bool)$_POST['show_password'] : false;

    if ($name && $username) {
        // Update query - store password in plaintext
        $sql = "UPDATE admins SET name = ?, username = ?, updated_at = NOW()";
        $params = [$name, $username];

        if ($password !== '') {
            $sql .= ", password_hash = ?";
            $params[] = $password;  // Store as plaintext
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

// Handle password view request
if (isset($_GET['view_password'])) {
    $showPassword = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Settings - DentalCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <style>
        :root {
            --primary: #2cb5a0;
            --secondary: #f0f7fa;
            --accent: #ff7f50;
            --dark: #1a7c6c;
            --light: #e9f5f2;
            --danger: #dc3545;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            background-attachment: fixed;
            padding: 2rem;
            min-height: 100vh;
        }

        .settings-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
        }

        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .info-box {
            background: linear-gradient(to bottom, var(--light), white);
            border-radius: 15px;
            padding: 1.8rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(44, 181, 160, 0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .info-label {
            color: var(--primary);
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 0.3rem;
        }

        .info-value {
            color: #2a2a2a;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .badge-last-login {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 1.2rem;
            font-size: 0.95rem;
            display: inline-block;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.3rem rgba(44, 181, 160, 0.2);
        }

        .breadcrumb {
            background: white;
            border-radius: 12px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .alert-info {
            background: rgba(44, 181, 160, 0.15);
            border: 1px solid var(--primary);
            color: var(--dark);
            border-radius: 12px;
            font-weight: 500;
        }
        
        .password-display {
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
            background: rgba(0, 0, 0, 0.03);
            padding: 0.8rem 1.2rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px dashed rgba(0, 0, 0, 0.08);
        }
        
        .password-toggle {
            cursor: pointer;
            color: var(--primary);
            transition: all 0.3s ease;
            background: rgba(44, 181, 160, 0.1);
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
        }
        
        .password-toggle:hover {
            color: var(--dark);
            background: rgba(44, 181, 160, 0.2);
        }
        
        .view-button {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.6rem 1.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .view-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }
        
        .password-strength {
            height: 8px;
            border-radius: 4px;
            margin-top: 0.8rem;
            background: #e9ecef;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.5s ease;
        }
        
        .password-rules {
            background: rgba(44, 181, 160, 0.08);
            border-radius: 12px;
            padding: 1.2rem;
            margin-top: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .password-rules h6 {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 0.8rem;
        }
        
        .password-rules ul {
            margin-bottom: 0;
            padding-left: 1.2rem;
        }
        
        .password-rules li {
            margin-bottom: 0.6rem;
            color: #4a5568;
        }
        
        .security-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(44, 181, 160, 0.2), rgba(255, 127, 80, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.8rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .security-section {
            background: white;
            border-radius: 18px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            margin-top: 3rem;
        }
        
        .password-visibility-toggle {
            cursor: pointer;
            color: #6c757d;
            transition: color 0.3s;
            background: rgba(0, 0, 0, 0.03);
            padding: 0.5rem 0.8rem;
            border-radius: 0 8px 8px 0;
        }
        
        .password-visibility-toggle:hover {
            color: var(--primary);
            background: rgba(0, 0, 0, 0.05);
        }
        
        .password-view-container {
            position: relative;
            margin: 2rem 0;
        }
        
        .password-view-wrapper {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 18px;
            padding: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .password-view-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 1.5rem;
        }
        
        .password-view-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(44, 181, 160, 0.2), rgba(44, 181, 160, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }
        
        .password-view-title {
            font-weight: 700;
            margin: 0;
            color: var(--dark);
            font-size: 1.6rem;
        }
        
        .password-view-value {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.03);
            border-radius: 12px;
            text-align: center;
            margin-bottom: 2rem;
            border: 2px dashed rgba(0, 0, 0, 0.1);
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.05);
            color: var(--dark);
        }
        
        .password-view-countdown {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 24px;
            padding: 0.8rem 1.5rem;
            width: fit-content;
            margin: 0 auto;
            font-size: 1.1rem;
        }
        
        .password-view-countdown i {
            color: var(--danger);
            font-size: 1.2rem;
        }
        
        .password-view-countdown span {
            font-weight: 600;
            color: var(--danger);
        }
        
        .password-view-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 1rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .password-view-button {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
        }
        
        .btn-secure {
            background: var(--dark);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 1.1rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }
        
        .btn-secure:hover {
            background: #135e4f;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .security-item {
            padding: 1.2rem;
            background: rgba(249, 249, 249, 0.8);
            border-radius: 12px;
            margin-bottom: 1.2rem;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .security-item:hover {
            transform: translateX(5px);
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .security-item i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-right: 15px;
        }
        
        .header-gradient {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
            margin-bottom: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #2a2a2a;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border: none;
            padding: 0.8rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            background: linear-gradient(90deg, #26a592, #e86e40);
        }
        
        .last-login-container {
            background: linear-gradient(135deg, rgba(44, 181, 160, 0.1), rgba(255, 127, 80, 0.05));
            border-radius: 12px;
            padding: 1.2rem;
            margin-top: 0.5rem;
        }
        
        .password-action-container {
            background: linear-gradient(135deg, rgba(44, 181, 160, 0.1), rgba(44, 181, 160, 0.05));
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .update-indicator {
            position: absolute;
            top: -10px;
            right: -10px;
            background: var(--accent);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        }
        
        .settings-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 2rem;
        }
        
        .settings-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
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

        <div class="settings-header">
            <div class="settings-icon">
                <i class="fas fa-user-md"></i>
            </div>
            <div>
                <h1 class="header-gradient">Admin Profile Settings</h1>
                <p class="text-muted">Manage your account details and security preferences</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Form Column -->
            <div class="col-lg-6">
                <div class="settings-card p-4 position-relative">
                    <span class="update-indicator">!</span>
                    <form method="POST" id="settingsForm">
                        <div class="mb-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control py-3" name="name" 
                                   value="<?= htmlspecialchars($admin['name']) ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control py-3" name="username" 
                                   value="<?= htmlspecialchars($admin['username']) ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control py-3" name="password" id="newPassword" placeholder="Enter new password">
                                <span class="input-group-text password-visibility-toggle" onclick="togglePasswordVisibility()">
                                    <i class="bi bi-eye-slash" id="toggleIcon"></i>
                                </span>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter" id="passwordStrength"></div>
                            </div>
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                        
                        <div class="password-rules">
                            <h6><i class="fas fa-shield-alt me-2"></i>Password Requirements</h6>
                            <ul>
                                <li>At least 8 characters</li>
                                <li>Include uppercase and lowercase letters</li>
                                <li>Include at least one number</li>
                                <li>Include a special character (e.g., !@#$%)</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 mt-3">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info Column -->
            <div class="col-lg-6">
                <div class="settings-card p-4 h-100">
                    <div class="info-box">
                        <div class="d-flex flex-column">
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
                                <div class="info-value"><?= date('M j, Y g:i a', strtotime($admin['created_at'])) ?></div>
                            </div>
                            
                            <div>
                                <div class="info-label">Last Updated</div>
                                <div class="info-value">
                                    <?= $admin['updated_at'] ? date('M j, Y g:i a', strtotime($admin['updated_at'])) : 'Never' ?>
                                </div>
                            </div>
                            
                            <div class="last-login-container">
                                <div class="info-label">Last Login</div>
                                <span class="badge-last-login">
                                    <?= $admin['last_login'] ? date('M j, Y g:i a', strtotime($admin['last_login'])) : 'Never' ?>
                                </span>
                            </div>
                            
                            <div class="password-action-container mt-3">
                                <div class="info-label">Password Access</div>
                                <div class="d-flex justify-content-center mt-3">
                                    <a href="?view_password=1" class="view-button">
                                        <i class="fas fa-key me-1"></i> View Password
                                    </a>
                                </div>
                                <small class="text-muted mt-2 d-block text-center">Click to view your password (visible for 60 seconds)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Password View Section -->
        <?php if ($showPassword): ?>
        <div class="security-section" id="passwordViewSection">
            <div class="password-view-container">
                <div class="password-view-wrapper">
                    <div class="password-view-header">
                        <div class="password-view-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <h4 class="password-view-title">Your Password</h4>
                    </div>
                    
                    <div class="password-view-value" id="passwordDisplay">
                        <?= htmlspecialchars($admin['password_hash']) ?>
                    </div>
                    
                    <div class="password-view-countdown">
                        <i class="fas fa-clock"></i>
                        <span>This password will be hidden in</span>
                        <span id="passwordCountdown">60</span>
                        <span>seconds</span>
                    </div>
                    
                    <div class="password-view-button">
                        <button class="btn btn-secure" onclick="location.href='settings.php'">
                            <i class="fas fa-lock me-1"></i> Hide Password
                        </button>
                    </div>
                    
                    <div class="password-view-footer">
                        <i class="fas fa-exclamation-triangle me-1 text-danger"></i>
                        For security reasons, please do not share your password
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Security Section -->
        <div class="security-section">
            <div class="d-flex align-items-center gap-4 mb-4">
                <div class="security-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <h3 class="mb-1">Security Recommendations</h3>
                    <p class="mb-0 text-muted">Best practices to keep your account secure</p>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="security-item d-flex align-items-center">
                        <i class="fas fa-sync-alt"></i>
                        <div>
                            <h5 class="mb-1">Regular Password Updates</h5>
                            <p class="mb-0 small text-muted">Change your password every 60-90 days to maintain security.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="security-item d-flex align-items-center">
                        <i class="fas fa-lock"></i>
                        <div>
                            <h5 class="mb-1">Strong Passwords</h5>
                            <p class="mb-0 small text-muted">Use a combination of letters, numbers, and special characters.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="security-item d-flex align-items-center">
                        <i class="fas fa-user-shield"></i>
                        <div>
                            <h5 class="mb-1">Account Monitoring</h5>
                            <p class="mb-0 small text-muted">Regularly check your login history for suspicious activity.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="security-item d-flex align-items-center">
                        <i class="fas fa-sign-out-alt"></i>
                        <div>
                            <h5 class="mb-1">Logout When Finished</h5>
                            <p class="mb-0 small text-muted">Always log out after your session, especially on shared devices.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script>
        // Password visibility toggle for form field
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('newPassword');
            const icon = document.getElementById('toggleIcon');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        }
        
        // Password strength indicator
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length > 7) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.style.backgroundColor = '#dc3545';
            } else if (strength < 75) {
                strengthBar.style.backgroundColor = '#ffc107';
            } else {
                strengthBar.style.backgroundColor = '#28a745';
            }
        });
        
        // Form submission validation
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            const password = document.getElementById('newPassword').value;
            
            if (password && password.length < 8) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Weak Password',
                    text: 'Password must be at least 8 characters long',
                    confirmButtonColor: '#2cb5a0',
                    confirmButtonText: 'OK'
                });
            }
        });
        
        // Password view countdown
        <?php if ($showPassword): ?>
        let seconds = 60;
        const countdownElement = document.getElementById('passwordCountdown');
        
        const countdown = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                // Redirect to secure page after countdown
                setTimeout(() => {
                    window.location.href = 'settings.php';
                }, 1000);
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>