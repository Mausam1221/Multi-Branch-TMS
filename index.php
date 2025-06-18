<?php
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Basic validation
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password';
    } else {
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
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-left {
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            padding: 60px 40px;
            text-align: center;
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
        
        .login-right {
            padding: 60px 40px;
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="row g-0">
                <div class="col-lg-6">
                    <div class="login-left">
                        <div class="travel-icon">
                            <i class="fas fa-plane"></i>
                        </div>
                        <h1>TravelNepal</h1>
                        <p>Your Gateway to Amazing Adventures in Nepal</p>
                        <p>Discover the beauty of Nepal with our premium travel packages and exceptional service.</p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="login-right">
                        <form method="POST" class="login-form">
                            <h2><i class="fas fa-sign-in-alt me-2"></i>Welcome Back</h2>
                            
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($debug_info && isset($_GET['debug'])): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i><?php echo $debug_info; ?>
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
                            
                            <button type="submit" class="btn btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>
                        
                        <!-- <div class="demo-credentials">
                            <h6><i class="fas fa-info-circle me-2"></i>Demo Credentials</h6>
                            <div class="credential-item">
                                <strong>Main Admin:</strong> mainadmin / password123
                            </div>
                            <div class="credential-item">
                                <strong>Branch Admin:</strong> kathmandu_admin / password123
                            </div>
                            <div class="credential-item">
                                <strong>Customer:</strong> customer1 / password123
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <strong>Troubleshooting:</strong> If login fails, ensure the database is set up correctly and try adding <code>?debug=1</code> to the URL for debug information.
                                </small>
                            </div>
                        </div> -->
                    </div>
                </div>
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
        });
    </script>
</body>
</html>
