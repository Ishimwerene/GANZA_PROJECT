<?php
// Start session and check login
session_start();

// Hardcoded login credentials
$valid_username = 'admin';
$valid_password = '123';

// Check if user is already logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Check if login form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username === $valid_username && $password === $valid_password) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            // Redirect to your existing dashboard
            header('Location: traffic_monitoring_dashboard.php');
            exit;
        } else {
            $login_error = "Invalid username or password!";
        }
    }
    
    // Show login page if not logged in
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Traffic Monitoring System - Login</title>
        <link rel="shortcut icon" href="icon_image/image4.ico">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .login-container {
                background: white;
                border-radius: 15px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.3);
                padding: 40px;
                width: 100%;
                max-width:1000px;
                transform: translateY(-20px);
            }
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .login-header h2 {
                color: #2c3e50;
                margin-bottom: 10px;
                font-weight: 700;
            }
            .login-header p {
                color: #7f8c8d;
                font-size: 0.95rem;
            }
            .form-control {
                border-radius: 10px;
                padding: 15px;
                margin-bottom: 20px;
                border: 2px solid #ecf0f1;
                transition: all 0.3s;
            }
            .form-control:focus {
                border-color: #3498db;
                box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            }
            .btn-login {
                background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
                border: none;
                color: white;
                padding: 15px;
                border-radius: 10px;
                width: 100%;
                font-weight: 600;
                font-size: 1.1rem;
                transition: all 0.3s;
            }
            .btn-login:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
            .alert {
                border-radius: 10px;
                border: none;
            }
            .system-info {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 20px;
                margin-top: 25px;
                text-align: center;
                border-left: 4px solid #3498db;
            }
            .system-info small {
                color: #7f8c8d;
            }
            .logo {
                font-size: 3rem;
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <div class="logo">🚦</div>
                <h2>Traffic Monitoring System</h2>
                <p>Please login to access the dashboard</p>
            </div>
            
            <?php if (isset($login_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $login_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <input type="text" class="form-control" name="username" placeholder="Username" value="admin" required>
                </div>
                <div class="mb-3">
                    <input type="password" class="form-control" name="password" placeholder="Password" value="123" required>
                </div>
                <button type="submit" class="btn btn-login">Login to Dashboard</button>
            </form>
            
            <div class="system-info">
                <small>
                    <strong>Default Login Credentials:</strong><br>
                    👤 Username: <code>admin</code><br>
                    🔑 Password: <code>123</code>
                </small>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// If user is already logged in, redirect to dashboard
header('Location: traffic_monitoring_dashboard.php');
exit;
?>