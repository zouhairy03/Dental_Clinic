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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout Confirmation - DentalCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e6f7ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .container {
            max-width: 480px;
            width: 100%;
            z-index: 2;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            text-align: center;
            padding: 45px 35px;
            position: relative;
            transition: all 0.4s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 35px;
            color: #2a6b7f;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .logo i {
            color: #2cb5a0;
            margin-right: 12px;
            font-size: 2.2rem;
        }

        .icon-container {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #e0f7f4 0%, #d2f0ed 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: #2cb5a0;
            font-size: 3.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(44, 181, 160, 0.15);
        }

        .icon-container::before {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border-radius: 50%;
            border: 2px dashed rgba(44, 181, 160, 0.3);
            animation: rotate 20s linear infinite;
        }

        .title {
            font-size: 1.9rem;
            font-weight: 600;
            color: #1a3c48;
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .message {
            color: #5a6b78;
            font-size: 1.08rem;
            line-height: 1.6;
            margin-bottom: 35px;
            max-width: 350px;
            margin-left: auto;
            margin-right: auto;
        }

        .actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 1.05rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            outline: none;
            min-width: 150px;
        }

        .btn i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .btn-cancel {
            background: white;
            color: #5a6b78;
            border: 1px solid #e0e7ed;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .btn-cancel:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
        }

        .btn-logout {
            background: linear-gradient(135deg, #ff7f50 0%, #ff6347 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(255, 127, 80, 0.3);
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #ff7340 0%, #ff5530 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 127, 80, 0.4);
        }

        /* Loading state */
        .loading-state .icon-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #f0f7fa 100%);
        }

        .loading-state .icon-container i {
            color: #2cb5a0;
            animation: pulse 1.5s infinite;
        }

        .loading-state .title {
            color: #2cb5a0;
        }

        .loading-state .actions {
            display: none;
        }

        .loading-message {
            display: none;
            font-size: 1.2rem;
            color: #2a6b7f;
            margin-top: 30px;
            font-weight: 500;
        }

        .loading-state .loading-message {
            display: block;
            animation: fadeIn 0.5s forwards;
        }

        .countdown {
            font-size: 1.1rem;
            font-weight: 600;
            color: #ff7f50;
            margin-top: 10px;
        }

        /* Animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Background elements */
        .bg-element {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(44, 181, 160, 0.08) 0%, rgba(255, 127, 80, 0.08) 100%);
            z-index: 1;
        }

        .bg-1 {
            width: 300px;
            height: 300px;
            top: -100px;
            left: -100px;
        }

        .bg-2 {
            width: 200px;
            height: 200px;
            bottom: -80px;
            right: -80px;
        }

        .bg-3 {
            width: 150px;
            height: 150px;
            top: 40%;
            right: 10%;
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .card {
                padding: 35px 25px;
            }
            
            .actions {
                flex-direction: column;
                gap: 12px;
            }
            
            .btn {
                width: 100%;
            }
            
            .title {
                font-size: 1.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-element bg-1"></div>
    <div class="bg-element bg-2"></div>
    <div class="bg-element bg-3"></div>
    
    <div class="container">
        <div class="card" id="logoutCard">
            <div class="logo">
                <i class="fas fa-tooth"></i>
                <span>DentalCare</span>
            </div>
            
            <div class="icon-container">
                <i class="fas fa-sign-out-alt" id="logoutIcon"></i>
            </div>
            
            <h2 class="title" id="logoutTitle">Confirm Logout</h2>
            
            <p class="message" id="logoutMessage">Are you sure you want to log out? You'll need to sign in again to access your account.</p>
            
            <div class="actions">
                <button class="btn btn-cancel" onclick="window.history.back()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-logout" id="logoutButton">
                    <i class="fas fa-sign-out-alt"></i> Log Out
                </button>
            </div>
            
            <div class="loading-message" id="loadingMessage">
                <div>See you again soon!</div>
                <div class="countdown" id="countdown">Redirecting in 5 seconds...</div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('logoutButton').addEventListener('click', function() {
            const card = document.getElementById('logoutCard');
            const title = document.getElementById('logoutTitle');
            const icon = document.getElementById('logoutIcon');
            const message = document.getElementById('logoutMessage');
            const loadingMessage = document.getElementById('loadingMessage');
            const countdown = document.getElementById('countdown');
            
            // Apply loading state styles
            card.classList.add('loading-state');
            title.textContent = "Logging you out...";
            message.textContent = "Please wait while we securely end your session";
            icon.className = "fas fa-spinner fa-spin";
            
            // Countdown timer
            let seconds = 5;
            countdown.textContent = `Redirecting in ${seconds} seconds...`;
            
            const timer = setInterval(() => {
                seconds--;
                countdown.textContent = `Redirecting in ${seconds} second${seconds !== 1 ? 's' : ''}...`;
                
                if (seconds <= 0) {
                    clearInterval(timer);
                    // Submit the form
                    document.querySelector('form').submit();
                }
            }, 1000);
        });
        
        // Create the form programmatically
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'confirm_logout';
        input.value = '1';
        
        form.appendChild(input);
        document.body.appendChild(form);
    </script>
</body>
</html>