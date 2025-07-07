<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/database.php';
require_once 'config/auth.php';

// Test database connection
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Failed to connect to database. Please check your database configuration.");
}

$auth = new Auth($db);

$error_message = '';
$debug_info = '';
$register_error = '';
$register_success = '';

// Show session timeout message if redirected
$timeout_message = '';
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $timeout_message = 'Session expired due to inactivity. Please log in again.';
}

// Registration logic FIRST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reg_username'])) {
    $reg_full_name = trim($_POST['reg_full_name']);
    $reg_email = trim($_POST['reg_email']);
    $reg_username = trim($_POST['reg_username']);
    $reg_password = $_POST['reg_password'];
    $reg_confirm_password = $_POST['reg_confirm_password'];
    $reg_phone = trim($_POST['reg_phone']);

    // Validate input
    if (empty($reg_full_name) || empty($reg_email) || empty($reg_username) || empty($reg_password) || empty($reg_confirm_password)) {
        $register_error = 'Please fill in all required fields.';
    } elseif (!filter_var($reg_email, FILTER_VALIDATE_EMAIL)) {
        $register_error = 'Please enter a valid email address.';
    } elseif ($reg_password !== $reg_confirm_password) {
        $register_error = 'Passwords do not match.';
    } elseif (strlen($reg_password) < 6) {
        $register_error = 'Password must be at least 6 characters.';
    } else {
        // Check for duplicate username or email
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$reg_username, $reg_email]);
        if ($stmt->fetch()) {
            $register_error = 'Username or email already exists.';
        } else {
            // Hash password
            $hashed_password = password_hash($reg_password, PASSWORD_DEFAULT);
            // Insert new customer
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role, full_name, phone, status, last_login, created_at, updated_at) VALUES (?, ?, ?, 'customer', ?, ?, 'active', NOW(), NOW(), NOW())");
            $result = $stmt->execute([$reg_username, $reg_email, $hashed_password, $reg_full_name, $reg_phone]);
            if ($result) {
                $_SESSION['register_success'] = 'Account created successfully! You can now log in.';
                header('Location: index.php');
                exit();
            } else {
                $register_error = 'Registration failed. Please try again.';
            }
        }
    }
}

// Login logic SECOND
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['reg_username'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Basic validation
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password';
    } else {
        try {
            $user = $auth->login($username, $password);
            if ($user) {
                // Clear any previous error messages
                $error_message = '';
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'main_admin':
                        header("Location: dashboards/main-admin.php");
                        exit();
                    case 'branch_admin':
                        header("Location: dashboards/branch-admin.php");
                        exit();
                    case 'customer':
                        header("Location: dashboards/customer.php");
                        exit();
                    default:
                        $error_message = 'Invalid user role';
                }
            } else {
                $error_message = 'Invalid username or password. Please check your credentials and try again.';
                
                // Add debug information in development
                if (isset($_GET['debug'])) {
                    $debug_info = "Debug: Attempted login with username: " . htmlspecialchars($username);
                }
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            
            // Check if this is a lockout message and show remaining attempts
            if (strpos($error_message, 'Account is temporarily locked') !== false || 
                strpos($error_message, 'Too many failed attempts') !== false) {
                // This is a lockout message, no need to show remaining attempts
            } else {
                // Show remaining attempts for regular failed login
                $remainingAttempts = $auth->getRemainingAttempts($username);
                if ($remainingAttempts > 0) {
                    $error_message .= " Remaining attempts: {$remainingAttempts}";
                }
            }
            
            // Add debug information in development
            if (isset($_GET['debug'])) {
                $debug_info = "Debug: Attempted login with username: " . htmlspecialchars($username);
            }
        }
    }
}
?> 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelNepal - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            width: 900px;
            max-width: 98vw;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: row;
            position: relative;
            min-height: 500px;
        }
        
        .login-left {
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            flex: 1 1 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-width: 0;
            min-height: 300px;
        }
        
        .login-right {
            flex: 1 1 0;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
            min-height: 300px;
            background: transparent;
            position: relative;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="plane" patternUnits="userSpaceOnUse" width="20" height="20"><path d="M10 2l8 8-8 8-8-8z" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23plane)"/></svg>');
            opacity: 0.3;
        }
        
        .login-left h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .login-left p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .travel-icon {
            font-size: 4rem;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .login-form h2 {
            color: #333;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .demo-credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .demo-credentials h6 {
            color: #495057;
            margin-bottom: 15px;
        }
        
        .credential-item {
            background: white;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        
        @media (max-width: 768px) {
            .login-left {
                padding: 40px 20px;
            }
            
            .login-right {
                padding: 40px 20px;
            }
            
            .login-left h1 {
                font-size: 2rem;
            }
            
            .travel-icon {
                font-size: 3rem;
            }
        }
        
        .login-form {
            display: block;
        }
        .login-form.hide {
            display: none;
        }
        .login-form.show {
            display: block;
        }
        .fade {
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .fade.show {
            opacity: 1;
            pointer-events: auto;
        }
        .fade.hide {
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card" id="loginCard">
            <div class="login-left">
                <div class="travel-icon">
                    <i class="fas fa-plane"></i>
                </div>
                <h1>TravelNepal</h1>
                <p>Your Gateway to Amazing Adventures in Nepal</p>
                <p>Discover the beauty of Nepal with our premium travel packages and exceptional service.</p>
            </div>
            <div class="login-right">
                <form method="POST" class="login-form show" id="loginForm">
                    <h2><i class="fas fa-sign-in-alt me-2"></i>Welcome Back</h2>
                    <?php if (isset($_SESSION['register_success'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['register_success']; unset($_SESSION['register_success']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                            <?php if (strpos($error_message, 'Account is temporarily locked') !== false || strpos($error_message, 'Too many failed attempts') !== false): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        This is a security measure to protect your account. The lockout will automatically expire.
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($debug_info && isset($_GET['debug'])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i><?php echo $debug_info; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($timeout_message): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-clock me-2"></i><?php echo $timeout_message; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                        <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                    </div>
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    </div>
                    <div id="attemptsInfo" class="text-muted small" style="display: none;">
                        <i class="fas fa-shield-alt me-1"></i>
                        <span id="attemptsText"></span>
                    </div>
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                    <div class="text-center mt-3">
                        <a href="#" id="showRegister" class="text-primary" style="cursor:pointer;">Create Account</a>
                    </div>
                </form>
                <form method="POST" class="login-form hide" id="registerForm" autocomplete="off">
                    <h2><i class="fas fa-user-plus me-2"></i>Create Account</h2>
                    <?php if ($register_error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $register_error; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-floating">
                        <input type="text" class="form-control" id="reg_full_name" name="reg_full_name" placeholder="Full Name" required autocomplete="off">
                        <label for="reg_full_name"><i class="fas fa-user me-2"></i>Full Name</label>
                    </div>
                    <div class="form-floating">
                        <input type="email" class="form-control" id="reg_email" name="reg_email" placeholder="Email" required autocomplete="off">
                        <label for="reg_email"><i class="fas fa-envelope me-2"></i>Email</label>
                    </div>
                    <div class="form-floating">
                        <input type="text" class="form-control" id="reg_username" name="reg_username" placeholder="Username" required autocomplete="off">
                        <label for="reg_username"><i class="fas fa-user me-2"></i>Username</label>
                    </div>
                    <div class="form-floating">
                        <input type="password" class="form-control" id="reg_password" name="reg_password" placeholder="Password" required autocomplete="off">
                        <label for="reg_password"><i class="fas fa-lock me-2"></i>Password</label>
                    </div>
                    <div class="form-floating">
                        <input type="password" class="form-control" id="reg_confirm_password" name="reg_confirm_password" placeholder="Confirm Password" required autocomplete="off">
                        <label for="reg_confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                    </div>
                    <div class="form-floating">
                        <input type="tel" class="form-control" id="reg_phone" name="reg_phone" placeholder="Phone (optional)" autocomplete="off">
                        <label for="reg_phone"><i class="fas fa-phone me-2"></i>Phone (optional)</label>
                    </div>
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-user-plus me-2"></i>Register
                    </button>
                    <div class="text-center mt-3">
                        <a href="#" id="showLogin" class="text-primary" style="cursor:pointer;">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Toggle between login and register forms and expand/collapse card
            document.getElementById('showRegister').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('loginForm').classList.remove('show');
                document.getElementById('loginForm').classList.add('hide');
                document.getElementById('registerForm').classList.remove('hide');
                document.getElementById('registerForm').classList.add('show');
            });
            document.getElementById('showLogin').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('registerForm').classList.remove('show');
                document.getElementById('registerForm').classList.add('hide');
                document.getElementById('loginForm').classList.remove('hide');
                document.getElementById('loginForm').classList.add('show');
            });

            // Check remaining login attempts when username is entered
            let attemptsCheckTimeout;
            document.getElementById('username').addEventListener('input', function() {
                const username = this.value.trim();
                const attemptsInfo = document.getElementById('attemptsInfo');
                const attemptsText = document.getElementById('attemptsText');
                
                // Clear previous timeout
                clearTimeout(attemptsCheckTimeout);
                
                if (username.length > 2) {
                    // Add a small delay to avoid too many requests
                    attemptsCheckTimeout = setTimeout(() => {
                        fetch('check_attempts.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'username=' + encodeURIComponent(username)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.remaining_attempts !== undefined) {
                                if (data.remaining_attempts === 0) {
                                    attemptsText.textContent = 'Account is locked. Please try again later.';
                                    attemptsInfo.style.display = 'block';
                                    attemptsInfo.className = 'text-danger small';
                                } else if (data.remaining_attempts < 5) {
                                    attemptsText.textContent = `${data.remaining_attempts} login attempts remaining`;
                                    attemptsInfo.style.display = 'block';
                                    attemptsInfo.className = 'text-warning small';
                                } else {
                                    attemptsInfo.style.display = 'none';
                                }
                            }
                        })
                        .catch(error => {
                            console.log('Could not check attempts:', error);
                        });
                    }, 500);
                } else {
                    attemptsInfo.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
