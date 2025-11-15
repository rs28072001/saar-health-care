<?php
// Error logging configuration
ini_set('log_errors', 1);
ini_set('error_log', 'login_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

session_start();

// Include database connection
require_once 'db_connect.php';

// Auto-login if remember me cookie exists
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    
    // Check if token exists in database
    $stmt = $conn->prepare("SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $token_data = $result->fetch_assoc();
        $user_id = $token_data['user_id'];
        
        // Get user data
        $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? AND is_verified = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        }
    }
    
    // If token is invalid, clear the cookie
    setcookie('remember_me', '', time() - 3600, '/');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    header('Content-Type: application/json');
    
    try {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember_me']);

        // Validation
        if (empty($email) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Email and password are required!']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format!']);
            exit;
        }

        // Check if user exists and is verified
        $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ? AND is_verified = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or account not verified!']);
            exit;
        }

        $user = $result->fetch_assoc();

        // Verify password
        if (!password_verify($password, $user['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or password!']);
            exit;
        }

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        // Handle "Remember Me"
        if ($remember_me) {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Store token in database
            $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user['id'], $token, $expires);
            $stmt->execute();
            
            // Set cookie (30 days)
            setcookie('remember_me', $token, time() + (30 * 24 * 60 * 60), '/');
        }

        echo json_encode([
            'status' => 'success', 
            'message' => 'Login successful! Redirecting...',
            'redirect' => 'dashboard.php'
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'A system error occurred. Please try again later.']);
        exit;
    }
}

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
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
    <title>Saar Health Care - Login</title>
    <meta name="description" content="Login to your Saar Healthcare account. Access personalized diet and nutrition plans from the best dietitians in Faridabad.">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico">
    <link rel="icon" type="image/png" href="assets/images/fav1.png" sizes="32x32">
    <link rel="icon" type="image/png" href="assets/images/fav1.png" sizes="16x16">
    
    <!-- Critical CSS -->
    <style>
        /* Critical styles to prevent layout shift */
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
        }
        .sign-in-page {
            min-height: 100vh;
            display: flex;
            padding: 20px 0;
        }
        .sign-user_card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 30px;
            position: relative;
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
            border-radius: 5px;
            margin-bottom: 20px;
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
        .form-check-label {
            font-size: 14px;
        }
        .forgot-password {
            font-size: 14px;
            text-decoration: none;
        }
    </style>
    
    <!-- Deferred CSS -->
    <link rel="preload" href="assets/css/core/libs.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="assets/css/kivicare.mine209.css?v=1.0.0" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="assets/css/custom.mine209.css?v=1.0.0" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <noscript>
        <link rel="stylesheet" href="assets/css/core/libs.min.css">
        <link rel="stylesheet" href="assets/css/kivicare.mine209.css?v=1.0.0">
        <link rel="stylesheet" href="assets/css/custom.mine209.css?v=1.0.0">
    </noscript>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;600;700&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;1,300;1,400;1,500&display=swap" rel="stylesheet">
</head>
<body class="body-bg">
    <!-- Simplified loader -->
    <div id="loading" class="loader">
        <img src="assets/images/loader.gif" alt="Loading..." width="200">
    </div>

    <main class="main-content">
        <div class="sign-in-page">
            <div class="container">
                <div class="row justify-content-center align-items-center">
                    <div class="col-lg-5 col-md-8 col-sm-10">
                        <div class="sign-user_card position-relative bg-white">
                            <!-- Home Button -->
                             <style>
                                .home-link .btn:hover {
                                    background-color: #9effc6ff !important;
                                    box-shadow: 0 2px 6px rgba(0, 0, 0, 1);
                                    text-decoration: none;
                                    color: #242424ff;
                                }
                             </style>
                            <div class="home-link position-absolute" style="top: 15px; left: 15px;">
                                <a href="/" class="btn btn-success d-flex align-items-center gap-2 fw-semibold"
                                style="padding: 6px 12px; border-radius: 8px; font-size: 14px; background:#1aac59; border:none; ">
                                    <i class="fas fa-home"></i>
                                    Home
                                </a>
                            </div>

                            <!-- Logo -->
                            <div class="logo-img text-center mt-4">
                                <a href="index.php" class="navbar-brand d-inline-block mb-4">
                                    <img src="assets/images/logo.png" class="img-fluid" alt="Saar Healthcare" width="150" loading="lazy">
                                </a>
                            </div>

                            <!-- Display Messages -->
                            <?php if ($error): ?>
                                <div class="alert error"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert success"><?php echo $success; ?></div>
                            <?php endif; ?>

                            <!-- Login Form -->
                            <form id="loginForm" method="post" autocomplete="off">
                                <div class="mb-3">
                                    <input type="email" name="email" id="email" placeholder="Your email id *" class="form-control" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <input type="password" name="password" id="password" placeholder="Password" class="form-control" required>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me">
                                        <label class="form-check-label" for="remember_me">
                                            Remember Me
                                        </label>
                                    </div>
                                    <a href="forgot-password.php" class="forgot-password text-primary text-decoration-none">
                                        Forgot Password?
                                    </a>
                                </div>

                                <button type="submit" id="loginBtn" class="btn btn-primary w-100 py-2">
                                    <span id="btnText">Login</span>
                                    <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 hidden" role="status"></span>
                                </button>
                            </form>

                            <!-- Sign Up Link -->
                            <div class="text-center mt-4">
                                <p class="mb-0">Don't have an account? 
                                    <a href="registration.php" class="text-primary text-decoration-none fw-semibold">Sign Up</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Essential Scripts Only -->
    <script src="assets/js/core/libs.min.js" defer></script>
    <script>
        // Enhanced login functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loader when page is ready
            document.getElementById('loading').classList.add('hidden');
            
            const loginForm = document.getElementById('loginForm');
            
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
                    toggleButtonState(loginBtn, true, 'Logging in...');
                    
                    // Submit form via AJAX
                    fetch('login.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
                            }
                        });
                    })
                    .then(data => {
                        console.log("Login response:", data);
                        
                        if (data.status === 'success') {
                            // Show success message and redirect
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            } else {
                                alert('✅ ' + data.message);
                                window.location.href = 'dashboard.php';
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
                        toggleButtonState(loginBtn, false, 'Login');
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