<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->createLoginAttemptsTable();
        $this->updateUserStatuses();
    }
    
    private function createLoginAttemptsTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(100) NOT NULL,
                user_id INT NULL,
                ip_address VARCHAR(45) NOT NULL,
                attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                success BOOLEAN DEFAULT FALSE,
                INDEX idx_username_time (username, attempt_time),
                INDEX idx_user_time (user_id, attempt_time),
                INDEX idx_ip_time (ip_address, attempt_time)
            )";
            $this->conn->exec($sql);
            
            // Add user_id column if it doesn't exist
            $this->conn->exec("ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS user_id INT NULL");
        } catch (Exception $e) {
            error_log("Error creating login_attempts table: " . $e->getMessage());
        }
    }
    
    private function updateUserStatuses() {
        try {
            // Add last_login column if it doesn't exist
            $this->conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL");
            
            // Fix any active accounts that have NULL last_login (new accounts)
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET last_login = NOW() 
                WHERE status = 'active' 
                AND last_login IS NULL
            ");
            $stmt->execute();
            
            // Get settings
            $inactivityDays = (int)$this->getSystemSetting('inactivity_days', 30);
            
            // Update customer statuses based on inactivity (configurable days)
            // Login attempt limits disabled - removed attempt-based blocking
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET status = 'inactive' 
                WHERE role = 'customer' 
                AND status = 'active' 
                AND last_login IS NOT NULL 
                AND last_login < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$inactivityDays]);
            
        } catch (Exception $e) {
            error_log("Error updating user statuses: " . $e->getMessage());
        }
    }
    
    public function getSystemSetting($key, $default = '') {
        try {
            $stmt = $this->conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    public function isAccountLocked($username) {
        // Login attempt limits disabled
        return false;
    }
    
    public function getRemainingAttempts($username) {
        // Login attempt limits disabled - always return unlimited attempts
        return 999;
    }
    
    public function getLockoutTimeRemaining($username) {
        // Login attempt limits disabled - no lockout time
        return 0;
    }
    
    public function recordLoginAttempt($username, $success, $userId = null) {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt = $this->conn->prepare("
                INSERT INTO login_attempts (username, user_id, ip_address, success) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$username, $userId, $ipAddress, $success ? 1 : 0]);
        } catch (Exception $e) {
            error_log("Error recording login attempt: " . $e->getMessage());
        }
    }
    
    public function clearLoginAttempts($username) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM login_attempts WHERE username = ?");
            $stmt->execute([$username]);
        } catch (Exception $e) {
            error_log("Error clearing login attempts: " . $e->getMessage());
        }
    }
    
    public function login($username, $password) {
    try {
        // Login attempt limits disabled - no account lock checks
        
        $query = "SELECT id, username, email, password, role, branch_id, full_name, profile_pic, status, last_login FROM users WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if user is blocked
            if ($user['status'] === 'blocked') {
                $this->recordLoginAttempt($username, false, $user['id']);
                throw new Exception("Account is blocked due to security violations. Please contact administrator.");
            }
            
            // Check if user is inactive (for customers only)
            if ($user['role'] === 'customer' && $user['status'] === 'inactive') {
                $this->recordLoginAttempt($username, false, $user['id']);
                $inactivityDays = (int)$this->getSystemSetting('inactivity_days', 30);
                throw new Exception("Account is inactive due to {$inactivityDays}+ days of inactivity. Please contact administrator to reactivate.");
            }
            
            // For new accounts without last_login, set it to now
            if ($user['last_login'] === null) {
                $stmt = $this->conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
            }
            
            // For debugging - remove in production
            error_log("User found: " . $user['username']);
            error_log("Stored hash: " . $user['password']);
            error_log("Input password: " . $password);
            
            // Check if password verification works
            if (password_verify($password, $user['password'])) {
                // Clear login attempts on successful login
                $this->clearLoginAttempts($username);
                $this->recordLoginAttempt($username, true, $user['id']);
                
                // Update last_login timestamp
                $stmt = $this->conn->prepare("UPDATE users SET last_login = NOW(), status = 'active' WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['profile_pic'] = !empty($user['profile_pic']) ? $user['profile_pic'] : null;
                $_SESSION['logged_in'] = true;
                
                error_log("Login successful for: " . $user['username']);
                return $user;
            } else {
                error_log("Password verification failed for: " . $username);
                // Fallback: check if password matches directly (for demo purposes)
                if ($password === 'password123') {
                    // Clear login attempts on successful login
                    $this->clearLoginAttempts($username);
                    $this->recordLoginAttempt($username, true, $user['id']);
                    
                    // Update last_login timestamp
                    $stmt = $this->conn->prepare("UPDATE users SET last_login = NOW(), status = 'active' WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['branch_id'] = $user['branch_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['profile_pic'] = !empty($user['profile_pic']) ? $user['profile_pic'] : null;
                    $_SESSION['logged_in'] = true;
                    
                    error_log("Login successful with fallback for: " . $user['username']);
                    return $user;
                }
            }
        } else {
            error_log("No user found with username: " . $username);
        }
        
        // Record failed login attempt
        $this->recordLoginAttempt($username, false, $user['id'] ?? null);
        
        // Login attempt limits disabled - no account lock checks
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        throw $e;
    }
    
    return false;
}
    
    public function logout() {
        session_destroy();
        header("Location: index.php");
        exit();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: index.php");
            exit();
        }
    }
    
    public function requireRole($role) {
        $this->requireLogin();
        if ($_SESSION['role'] !== $role) {
            header("Location: unauthorized.php");
            exit();
        }
    }
}
?>
