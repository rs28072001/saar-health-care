<?php
// Error logging configuration
ini_set('log_errors', 1);
ini_set('error_log', 'admin_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

session_start();

// Include database connection
require_once 'db_connect.php';

// Redirect if admin is already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

// Handle admin login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    header('Content-Type: application/json');
    
    try {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Validation
        if (empty($email) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Email and password are required!']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format!']);
            exit;
        }

        // Check if admin exists
        $stmt = $conn->prepare("SELECT id, full_name, email, password FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid admin credentials!']);
            exit;
        }

        $admin = $result->fetch_assoc();

        // Verify password
        if (!password_verify($password, $admin['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or password!']);
            exit;
        }

        // Set admin session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_email'] = $admin['email'];

        echo json_encode([
            'status' => 'success', 
            'message' => 'Admin login successful! Redirecting...',
            'redirect' => 'admin_dashboard.php'
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Admin login error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'A system error occurred. Please try again later.']);
        exit;
    }
}

$error = '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['success']);
?>

<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saar Health Care - Admin Login</title>
    <meta name="description" content="Admin login for Saar Healthcare management system.">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico">
    <link rel="icon" type="image/png" href="assets/images/fav1.png" sizes="32x32">
    <link rel="icon" type="image/png" href="assets/images/fav1.png" sizes="16x16">
    
    <!-- Critical CSS -->
    <style>
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .admin-login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .admin-login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            position: relative;
        }
        .admin-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .admin-header h2 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .admin-header p {
            color: #666;
            margin: 0;
        }
        .loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .hidden {
            display: none !important;
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
        }
    </style>
    
    <!-- Deferred CSS -->
    <link rel="preload" href="assets/css/core/libs.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="assets/css/kivicare.mine209.css?v=1.0.0" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="assets/css/core/libs.min.css">
        <link rel="stylesheet" href="assets/css/kivicare.mine209.css?v=1.0.0">
    </noscript>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Loader -->
    <div id="loading" class="loader">
        <img src="assets/images/loader.gif" alt="Loading..." width="200">
    </div>

    <main class="main-content">
        <div class="admin-login-page">
            <div class="admin-login-card">
                <!-- Back Link -->
                <div class="back-link">
                    <a href="index.php" style="padding: 6px;box-shadow: 1px 3px 17px 5px #dadada;border-radius: 30px;" class="text-decoration-none d-flex align-items-center text-dark">
                        <i class="fas fa-arrow-left me-2"></i>
                        <span>🏠 Home </span>
                    </a>
                </div>

                <!-- Admin Header -->
                <div class="admin-header">
                    <img src="assets/images/logo.png" alt="Saar Healthcare" width="120" class="mb-3">
                    <h2>Admin Portal</h2>
                    <p>Access administrative functions</p>
                </div>

                <!-- Display Messages -->
                <?php if ($error): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Admin Login Form -->
                <form id="adminLoginForm" method="post" autocomplete="off">
                    <div class="mb-3">
                        <label for="email" class="form-label">Admin Email</label>
                        <input type="email" name="email" id="email" class="form-control" 
                               placeholder="Enter admin email" required>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Enter password" required>
                    </div>

                    <button type="submit" id="loginBtn" style="color: white;" class="btn btn-admin w-100 py-3">
                        <span id="btnText">Admin Login</span>
                        <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 hidden" role="status"></span>
                    </button>
                </form>

                <!-- User Login Link -->
                <div class="text-center mt-4">
                    <p class="mb-0">
                        <a href="login.php" class="text-decoration-none">User Login</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Essential Scripts -->
    <script src="assets/js/core/libs.min.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loader when page is ready
            document.getElementById('loading').classList.add('hidden');
            
            const loginForm = document.getElementById('adminLoginForm');
            
            if (loginForm) {
                // Form validation
                function validateForm() {
                    const email = document.getElementById('email').value.trim();
                    const password = document.getElementById('password').value;
                    
                    if (!email || !password) {
                        alert("Please fill all fields.");
                        return false;
                    }
                    
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        alert("Please enter a valid email address.");
                        return false;
                    }
                    
                    return true;
                }
                
                // Form submission handler
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (!validateForm()) return;
                    
                    const formData = new FormData(this);
                    const loginBtn = document.getElementById('loginBtn');
                    
                    // Show loading state
                    toggleButtonState(loginBtn, true, 'Authenticating...');
                    
                    // Submit form via AJAX
                    fetch('admin_login.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            } else {
                                window.location.href = 'admin_dashboard.php';
                            }
                        } else {
                            alert('❌ ' + data.message);
                        }
                    })
                    .catch(err => {
                        console.error("Login error:", err);
                        alert('Login failed. Please try again.');
                    })
                    .finally(() => {
                        toggleButtonState(loginBtn, false, 'Admin Login');
                    });
                });
                
                // Helper function to toggle button state
                function toggleButtonState(button, isLoading, text) {
                    const btnText = button.querySelector('#btnText');
                    const spinner = button.querySelector('#btnSpinner');
                    
                    if (isLoading) {
                        button.disabled = true;
                        if (spinner) spinner.classList.remove('hidden');
                        if (btnText) btnText.textContent = text;
                    } else {
                        button.disabled = false;
                        if (spinner) spinner.classList.add('hidden');
                        if (btnText) btnText.textContent = text;
                    }
                }
            }
        });
    </script>
</body>
</html>