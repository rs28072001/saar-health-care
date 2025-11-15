<?php
// Error logging configuration
ini_set('log_errors', 1);
ini_set('error_log', 'sign_up_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

session_start();

// Include database connection
require_once 'db_connect.php';

// Function to clean input data
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Function to generate OTP
function generateOTP($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Function to send OTP email using PHPMailer
function sendOTPEmail($email, $otp, $name) {
    try {
        // Use correct path for PHPMailer
        require_once __DIR__ . '/smtp/class.phpmailer.php';
        require_once __DIR__ . '/smtp/class.smtp.php';

        $mail = new PHPMailer(); 
        $mail->IsSMTP(); 
        $mail->SMTPAuth = true; 
        $mail->SMTPSecure = 'tls'; 
        $mail->Host = "smtp.hostinger.com";
        $mail->Port = 587; 
        $mail->IsHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Username = "supports@saarhealthcare.com";
        $mail->Password = "A;SlDkFj@123";
        $mail->SetFrom("supports@saarhealthcare.com", "Saar Healthcare");
        $mail->Subject = "Your OTP for Registration - Saar Healthcare";
        
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #25d366; text-align: center;'>Saar Healthcare - Registration OTP</h2>
            <p>Hi <strong>$name</strong>,</p>
            <p>Your OTP for registration is:</p>
            <div style='text-align: center; margin: 20px 0;'>
                <span style='font-size: 32px; font-weight: bold; color: #25d366; letter-spacing: 5px;'>$otp</span>
            </div>
            <p>This OTP is valid for 10 minutes.</p>
            <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                If you didn't request this OTP, please ignore this email.
            </p>
            <br>
            <p>Best regards,<br><strong>Saar Healthcare Team</strong></p>
        </div>
        ";
        
        $mail->Body = $message;
        $mail->AddAddress($email);
        $mail->SMTPOptions = array('ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ));

        if ($mail->Send()) {
            error_log("OTP sent successfully to: $email");
            return true;
        } else {
            error_log("Mail error for $email: " . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log("PHPMailer exception for $email: " . $e->getMessage());
        return false;
    }
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'])) {
    header('Content-Type: application/json');
    
    try {
        $full_name = clean_input($_POST['full_name']);
        $contact_no = clean_input($_POST['contact_no']);
        $address = clean_input($_POST['address']);
        $email = clean_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation
        if (empty($full_name) || empty($contact_no) || empty($address) || empty($email) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required!']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format!']);
            exit;
        }

        if (!preg_match('/^[0-9]{10}$/', $contact_no)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 10-digit contact number.']);
            exit;
        }

        if ($password !== $confirm_password) {
            echo json_encode(['status' => 'error', 'message' => 'Passwords do not match!']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters long!']);
            exit;
        }

        if (!isset($_POST['termsCheck'])) {
            echo json_encode(['status' => 'error', 'message' => 'Please agree to the Terms & Conditions and Privacy Policy.']);
            exit;
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already registered!']);
            exit;
        }

        // Generate OTP
        $otp = generateOTP();
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Store in session
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_time'] = time();
        $_SESSION['user_data'] = [
            'full_name' => $full_name,
            'email' => $email,
            'contact_no' => $contact_no,
            'address' => $address
        ];
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Start transaction
        $conn->begin_transaction();

        try {
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, contact_no, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $contact_no, $address);
            $stmt->execute();
            $user_id = $stmt->insert_id;

            // Store OTP in database
            $stmt = $conn->prepare("INSERT INTO otp_verifications (user_id, otp, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $otp, $otp_expiry);
            $stmt->execute();

            // Send OTP email
            if (sendOTPEmail($email, $otp, $full_name)) {
                $conn->commit();
                $_SESSION['verification_email'] = $email;
                echo json_encode(['status' => 'success', 'message' => 'Registration successful! Please check your email for OTP verification.']);
                exit;
            } else {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP email. Please try again.']);
                exit;
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'A system error occurred. Please try again later.']);
        exit;
    }
}

// If not an AJAX request, show the normal HTML page
$error = '';
$success = '';
?>

<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saar Health Care - Registration</title>
    <meta name="description" content="Register for Saar Healthcare services. Get personalized diet and nutrition plans from the best dietitians in Faridabad.">
    
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
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #fd7e14; }
        .strength-strong { color: #198754; }
    </style>
    
    <!-- Deferred CSS -->
    <link rel="preload" href="assets/css/core/libs.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="assets/css/kivicare.mine209.css?v=1.0.0" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="assets/css/custom.mine209.css?v=1.0.0" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="assets/css/core/libs.min.css">
        <link rel="stylesheet" href="assets/css/kivicare.mine209.css?v=1.0.0">
        <link rel="stylesheet" href="assets/css/custom.mine209.css?v=1.0.0">
    </noscript>
    
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
                                <div class="text-center">
                                    <a href="verify_otp.php" class="btn btn-success">Verify OTP</a>
                                </div>
                            <?php else: ?>

                            <!-- Registration Form -->
                            <form id="registerForm" method="post" autocomplete="off">
                                <div class="mb-3">
                                    <input type="text" name="full_name" id="full_name" placeholder="Full Name" class="form-control" 
                                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <input type="tel" name="contact_no" id="contact_no" placeholder="Contact No." class="form-control" 
                                           value="<?php echo isset($_POST['contact_no']) ? htmlspecialchars($_POST['contact_no']) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <input type="text" name="address" id="address" placeholder="Address" class="form-control" 
                                           value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <input type="email" name="email" id="email" placeholder="Your email id *" class="form-control" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>

                                <!-- Password Fields -->
                                <div class="mb-3">
                                    <input type="password" name="password" id="password" placeholder="Password" class="form-control" required>
                                    <div id="password-strength" class="password-strength"></div>
                                </div>
                                <div class="mb-3">
                                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" class="form-control" required>
                                    <div id="password-match" class="password-strength"></div>
                                </div>

                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" name="termsCheck" id="termsCheck" 
                                           <?php echo isset($_POST['termsCheck']) ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="termsCheck">
                                        I agree to the 
                                        <a href="terms-and-conditions.php" target="_blank" class="text-primary text-decoration-none">Terms & Conditions</a>
                                        and
                                        <a href="privacy-policy.php" target="_blank" class="text-primary text-decoration-none">Privacy Policy</a>.
                                    </label>
                                </div>

                                <button type="submit" id="registerBtn" class="btn btn-primary w-100 py-2">
                                    <span id="btnText">Register</span>
                                    <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 hidden" role="status"></span>
                                </button>
                            </form>
                            <?php endif; ?>

                            <!-- OTP Modal -->
                            <div id="otpModal" class="modal fade" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title text-success">Enter OTP</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <p class="text-muted">We've sent a 6-digit OTP to your email</p>
                                            <input type="text" id="otpInput" class="form-control form-control-lg text-center mb-3" 
                                                   placeholder="Enter OTP" maxlength="6" style="letter-spacing: 8px; font-weight: bold;">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <button id="verifyOtpBtn" class="btn btn-success px-4">
                                                    Verify OTP
                                                </button>
                                                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                            <p id="otpMessage" class="small text-muted mt-3"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sign In Link -->
                            <div class="text-center mt-4">
                                <p class="mb-0">Already have an account? 
                                    <a href="login.php" class="text-primary text-decoration-none fw-semibold">Sign In</a>
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
        // Enhanced registration functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loader when page is ready
            document.getElementById('loading').classList.add('hidden');
            
            const registerForm = document.getElementById('registerForm');
            
            // Only initialize form functionality if form exists (not in success state)
            if (registerForm) {
                const verifyOtpBtn = document.getElementById('verifyOtpBtn');
                const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
                
                // Password strength indicator
                document.getElementById('password').addEventListener('input', function() {
                    const password = this.value;
                    const strengthText = document.getElementById('password-strength');
                    
                    if (password.length === 0) {
                        strengthText.textContent = '';
                        strengthText.className = 'password-strength';
                        return;
                    }
                    
                    let strength = 0;
                    if (password.length >= 6) strength++;
                    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                    if (password.match(/\d/)) strength++;
                    if (password.match(/[^a-zA-Z\d]/)) strength++;
                    
                    if (strength <= 1) {
                        strengthText.textContent = 'Weak password';
                        strengthText.className = 'password-strength strength-weak';
                    } else if (strength <= 3) {
                        strengthText.textContent = 'Medium strength password';
                        strengthText.className = 'password-strength strength-medium';
                    } else {
                        strengthText.textContent = 'Strong password';
                        strengthText.className = 'password-strength strength-strong';
                    }
                });
                
                // Password match indicator
                document.getElementById('confirm_password').addEventListener('input', function() {
                    const password = document.getElementById('password').value;
                    const confirmPassword = this.value;
                    const matchText = document.getElementById('password-match');
                    
                    if (confirmPassword.length === 0) {
                        matchText.textContent = '';
                        matchText.className = 'password-strength';
                        return;
                    }
                    
                    if (password === confirmPassword) {
                        matchText.textContent = '✓ Passwords match';
                        matchText.className = 'password-strength strength-strong';
                    } else {
                        matchText.textContent = '✗ Passwords do not match';
                        matchText.className = 'password-strength strength-weak';
                    }
                });
                
                // Form validation
                function validateForm() {
                    const name = document.getElementById('full_name');
                    const contact = document.getElementById('contact_no');
                    const address = document.getElementById('address');
                    const email = document.getElementById('email');
                    const password = document.getElementById('password');
                    const confirmPassword = document.getElementById('confirm_password');
                    
                    // Check if elements exist before accessing their values
                    if (!name || !contact || !address || !email || !password || !confirmPassword) {
                        console.error('Form elements not found');
                        return false;
                    }
                    
                    const nameValue = name.value.trim();
                    const contactValue = contact.value.trim();
                    const addressValue = address.value.trim();
                    const emailValue = email.value.trim();
                    const passwordValue = password.value;
                    const confirmPasswordValue = confirmPassword.value;
                    
                    if (!nameValue || !contactValue || !addressValue || !emailValue || !passwordValue || !confirmPasswordValue) {
                        alert("Please fill all fields.");
                        return false;
                    }
                    
                    if (!/^\d{10}$/.test(contactValue)) {
                        alert("Please enter a valid 10-digit contact number.");
                        return false;
                    }
                    
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
                        alert("Please enter a valid email address.");
                        return false;
                    }
                    
                    if (passwordValue.length < 6) {
                        alert("Password must be at least 6 characters long.");
                        return false;
                    }
                    
                    if (passwordValue !== confirmPasswordValue) {
                        alert("Passwords do not match.");
                        return false;
                    }
                    
                    if (!document.getElementById('termsCheck').checked) {
                        alert("Please agree to the Terms & Conditions and Privacy Policy.");
                        return false;
                    }
                    
                    return true;
                }
                
                // Form submission handler
                registerForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (!validateForm()) return;
                    
                    const formData = new FormData(this);
                    const registerBtn = document.getElementById('registerBtn');
                    
                    // Show loading state
                    toggleButtonState(registerBtn, true, 'Sending OTP...');
                    
                    // Submit form via AJAX with proper headers
                    fetch('registration.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => {
                        console.log("Response status:", response.status);
                        return response.text().then(text => {
                            console.log("Raw response:", text);
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
                            }
                        });
                    })
                    .then(data => {
                        console.log("Parsed response:", data);
                        
                        if (data.status === 'success') {
                            // Show OTP modal
                            document.getElementById('otpMessage').textContent = 'OTP sent to ' + document.getElementById('email').value;
                            otpModal.show();
                            document.getElementById('otpInput').value = '';
                            document.getElementById('otpInput').focus();
                        } else {
                            // Show error message
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(err => {
                        console.error("Fetch error:", err);
                        alert('Registration failed: ' + err.message);
                    })
                    .finally(() => {
                        toggleButtonState(registerBtn, false, 'Register');
                    });
                });
                
                // OTP verification handler
                document.getElementById('verifyOtpBtn').addEventListener('click', function() {
                    const otp = document.getElementById('otpInput').value.trim();
                    const verifyBtn = this;
                    
                    if (!otp || otp.length !== 6) {
                        alert('Please enter a valid 6-digit OTP');
                        return;
                    }
                    
                    // Show loading state
                    verifyBtn.disabled = true;
                    verifyBtn.textContent = 'Verifying...';
                    
                    // Verify OTP via AJAX
                    fetch('verify_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ otp: otp })
                    })
                    .then(response => {
                        console.log("OTP Response status:", response.status);
                        return response.text().then(text => {
                            console.log("OTP Raw response:", text);
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
                            }
                        });
                    })
                    .then(data => {
                        console.log("OTP verification response:", data);
                        
                        if (data.status === 'success') {
                            alert('✅ ' + data.message);
                            otpModal.hide();
                            // Redirect to login page after 2 seconds
                            setTimeout(() => {
                                window.location.href = 'login.php';
                            }, 2000);
                        } else {
                            alert('❌ ' + data.message);
                            document.getElementById('otpInput').value = '';
                            document.getElementById('otpInput').focus();
                        }
                    })
                    .catch(err => {
                        console.error("OTP verification error:", err);
                        alert('Verification failed: ' + err.message);
                    })
                    .finally(() => {
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = 'Verify OTP';
                    });
                });

                // OTP input validation - numbers only
                const otpInput = document.getElementById('otpInput');
                if (otpInput) {
                    otpInput.addEventListener('input', function(e) {
                        this.value = this.value.replace(/[^0-9]/g, '');
                    });
                    
                    // Auto-submit OTP when 6 digits are entered
                    otpInput.addEventListener('input', function(e) {
                        if (this.value.length === 6) {
                            document.getElementById('verifyOtpBtn').click();
                        }
                    });
                }
                
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