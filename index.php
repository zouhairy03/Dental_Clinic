<?php
session_start();
require_once 'config.php'; // contains $pdo setup and session config

// Security headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize login attempts tracking
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

$error = '';
$login_disabled = false;

// Check if user is temporarily locked out
if ($_SESSION['login_attempts'] >= 5) {
    $lockout_duration = 300; // 5 minutes
    $elapsed_time = time() - $_SESSION['last_attempt_time'];
    
    if ($elapsed_time < $lockout_duration) {
        $remaining_time = $lockout_duration - $elapsed_time;
        $error = "Too many failed attempts. Please try again in $remaining_time seconds.";
        $login_disabled = true;
    } else {
        // Reset attempts if lockout period has passed
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$login_disabled) {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        // Sanitize inputs
      // Sanitize inputs
$username = trim($_POST['username'] ?? '');
$username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

$password = trim($_POST['password'] ?? '');

        if ($username && $password) {
            // Fetch admin by username
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin) {
                // Password verification logic
                $valid_password = false;
                
                // Check if stored password is plaintext (to be migrated to hash)
                if ($admin['password_hash'] === $password) {
                    $valid_password = true;
                    
                    // Migrate to hashed password
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
                    $updateStmt->execute([$new_hash, $admin['id']]);
                } 
                // Check hashed password
                else if (password_verify($password, $admin['password_hash'])) {
                    $valid_password = true;
                }

                if ($valid_password) {
                    // Successful login
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    
                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$admin['id']]);
                    
                    // Audit log
                    $log = sprintf(
                        "Successful login: %s [%s] from %s",
                        $username,
                        date('Y-m-d H:i:s'),
                        $_SERVER['REMOTE_ADDR']
                    );
                    file_put_contents('auth.log', $log.PHP_EOL, FILE_APPEND);
                    
                    // Regenerate CSRF token for next request
                    unset($_SESSION['csrf_token']);
                    
                    header("Location: dashboard.php");
                    exit;
                } else {
                    // Failed login
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    
                    $remaining_attempts = 5 - $_SESSION['login_attempts'];
                    $error = $remaining_attempts > 0 
                        ? "Invalid credentials. $remaining_attempts attempts remaining." 
                        : "Too many failed attempts. Account locked for 5 minutes.";
                    
                    // Audit log
                    $log = sprintf(
                        "Failed login: %s [%s] from %s",
                        $username,
                        date('Y-m-d H:i:s'),
                        $_SERVER['REMOTE_ADDR']
                    );
                    file_put_contents('auth.log', $log.PHP_EOL, FILE_APPEND);
                    
                    // Add delay to slow down brute force attacks
                    sleep(2);
                }
            } else {
                // Admin not found - but don't reveal that
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $error = "Invalid credentials";
                sleep(2); // Same delay as failed login
            }
        } else {
            $error = 'Please fill in both fields.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Access - DentalCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2cb5a0;
            --primary-dark: #229384;
            --secondary: #f0f7fa;
            --accent: #ff7f50;
            --danger: #dc3545;
            --success: #28a745;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0f7fa 0%, #f8f9fa 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(44, 181, 160, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(44, 181, 160, 0.1) 0%, transparent 20%);
            z-index: -1;
        }

        .login-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            min-height: 600px;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .graphic-side {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .graphic-side::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 20%),
                radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 25%);
            transform: rotate(30deg);
        }

        .dental-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .form-side {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .logo i {
            margin-right: 10px;
        }

        .security-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 1.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .security-indicator i {
            margin-right: 8px;
            color: var(--success);
        }

        .form-control {
            border-radius: 12px;
            padding: 1rem 1.2rem;
            border: 2px solid #e9ecef;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 181, 160, 0.25);
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            background: none;
            border: none;
        }

        .btn-login {
            background: var(--primary);
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            height: 55px;
        }

        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 7px 14px rgba(44, 181, 160, 0.3);
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .btn-login:disabled {
            background: #cccccc;
            transform: none;
            box-shadow: none;
            cursor: not-allowed;
        }

        .alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .password-rules {
            background: var(--secondary);
            border-radius: 12px;
            padding: 1.2rem;
            margin-top: 1.5rem;
            border-left: 4px solid var(--primary);
        }

        .password-rules h6 {
            color: var(--primary);
            margin-bottom: 0.8rem;
        }

        .password-rules ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .password-rules li {
            margin-bottom: 0.4rem;
        }

        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .tooth-animation {
            position: absolute;
            opacity: 0.2;
            font-size: 8rem;
            z-index: 0;
        }

        .tooth-1 {
            top: 10%;
            left: 15%;
            animation: float 8s infinite ease-in-out;
        }

        .tooth-2 {
            bottom: 10%;
            right: 15%;
            animation: float 10s infinite ease-in-out;
            animation-delay: 2s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .attempts-warning {
            color: var(--danger);
            font-weight: 500;
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="graphic-side col-md-6 d-none d-md-flex">
                <i class="fas fa-tooth tooth-animation tooth-1"></i>
                <i class="fas fa-tooth tooth-animation tooth-2"></i>
                <div class="text-center position-relative" style="z-index: 1;">
                    <i class="fas fa-tooth dental-icon"></i>
                    <h1 class="mb-3">DentalCare Clinic</h1>
                    <p class="lead">Secure practitioner access to patient management system</p>
                    <div class="mt-5">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-shield-alt fa-2x me-3"></i>
                            <div>
                                <h5>End-to-End Encryption</h5>
                                <p class="mb-0">All data is securely encrypted</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-user-lock fa-2x me-3"></i>
                            <div>
                                <h5>Role-Based Access</h5>
                                <p class="mb-0">Strict permission controls</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-history fa-2x me-3"></i>
                            <div>
                                <h5>Activity Auditing</h5>
                                <p class="mb-0">All actions are logged</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-side col-md-6">
                <div class="auth-header">
                    <div class="logo">
                        <i class="fas fa-tooth"></i>DentalCare
                    </div>
                    <h2>Secure Practitioner Login</h2>
                    <p>Enter your credentials to access the clinic portal</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="mb-4">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" 
                               placeholder="Enter your username" required autofocus
                               <?= $login_disabled ? 'readonly' : '' ?>>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-container">
                            <input type="password" name="password" id="password" class="form-control" 
                                   placeholder="••••••••" required autocomplete="current-password"
                                   <?= $login_disabled ? 'readonly' : '' ?>>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-login text-white" 
                                <?= $login_disabled ? 'disabled' : '' ?>>
                            <i class="fas fa-lock-open me-2"></i>Authenticate Securely
                        </button>
                    </div>
                </form>

                <div class="password-rules">
                    <h6><i class="fas fa-shield-alt me-2"></i>Password Security Requirements</h6>
                    <ul>
                        <li>Minimum 12 characters in length</li>
                        <li>At least one uppercase letter</li>
                        <li>At least one number and special character</li>
                        <li>Changed every 90 days</li>
                    </ul>
                </div>

                <div class="security-indicator">
                    <i class="fas fa-lock"></i>
                    <span>Secure TLS Encrypted Connection</span>
                </div>

                <div class="footer">
                    <p class="mb-1">&copy; <?= date('Y') ?> DentalCare Clinic. All rights reserved.</p>
                    <p class="mb-0">
                        <!-- <a href="forgot-password.php" class="text-decoration-none text-primary">
                            <i class="fas fa-key me-1"></i>Forgot Password?
                        </a>  -->

                        <a href="#" class="text-decoration-none text-primary">
                            <i class="fas fa-headset me-1"></i>Support
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        }
        
        // Add animation to input fields on focus
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>