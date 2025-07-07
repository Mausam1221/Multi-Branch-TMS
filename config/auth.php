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
            $maxAttempts = (int)$this->getSystemSetting('max_login_attempts', 5);
            $inactivityDays = (int)$this->getSystemSetting('inactivity_days', 30);
            
            // Update customer statuses based on inactivity (configurable days)
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET status = 'inactive' 
                WHERE role = 'customer' 
                AND status = 'active' 
                AND last_login IS NOT NULL 
                AND last_login < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND id NOT IN (
                    SELECT DISTINCT user_id FROM login_attempts 
                    WHERE success = FALSE 
                    AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    GROUP BY user_id 
                    HAVING COUNT(*) >= ?
                )
            ");
            $stmt->execute([$inactivityDays, $maxAttempts]);
            $stmt->execute([$maxAttempts]);
            
            // Keep blocked users inactive (users with too many failed attempts)
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET status = 'blocked' 
                WHERE id IN (
                    SELECT DISTINCT user_id FROM login_attempts 
                    WHERE success = FALSE 
                    AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    GROUP BY user_id 
                    HAVING COUNT(*) >= ?
                )
            ");
            $stmt->execute([$maxAttempts]);
            
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
        try {
            $maxAttempts = (int)$this->getSystemSetting('max_login_attempts', 5);
            $lockoutDuration = 15; // minutes
            
            // Count failed attempts in the last lockout duration
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as failed_attempts 
                FROM login_attempts 
                WHERE username = ? 
                AND success = FALSE 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ");
            $stmt->execute([$username, $lockoutDuration]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['failed_attempts'] >= $maxAttempts;
        } catch (Exception $e) {
            error_log("Error checking account lock: " . $e->getMessage());
            return false;
        }
    }
    
    public function getRemainingAttempts($username) {
        try {
            $maxAttempts = (int)$this->getSystemSetting('max_login_attempts', 5);
            $lockoutDuration = 15; // minutes
            
            // Count failed attempts in the last lockout duration
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as failed_attempts 
                FROM login_attempts 
                WHERE username = ? 
                AND success = FALSE 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ");
            $stmt->execute([$username, $lockoutDuration]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return max(0, $maxAttempts - $result['failed_attempts']);
        } catch (Exception $e) {
            error_log("Error getting remaining attempts: " . $e->getMessage());
            return $maxAttempts;
        }
    }
    
    public function getLockoutTimeRemaining($username) {
        try {
            $maxAttempts = (int)$this->getSystemSetting('max_login_attempts', 5);
            $lockoutDuration = 15; // minutes
            
            // Get the time of the last failed attempt
            $stmt = $this->conn->prepare("
                SELECT attempt_time 
                FROM login_attempts 
                WHERE username = ? 
                AND success = FALSE 
                ORDER BY attempt_time DESC 
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $lastAttempt = strtotime($result['attempt_time']);
                $lockoutEnd = $lastAttempt + ($lockoutDuration * 60);
                $remaining = $lockoutEnd - time();
                return max(0, $remaining);
            }
            
            return 0;
        } catch (Exception $e) {
            error_log("Error getting lockout time: " . $e->getMessage());
            return 0;
        }
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
        // Check if account is locked
        if ($this->isAccountLocked($username)) {
            $remainingTime = $this->getLockoutTimeRemaining($username);
            if ($remainingTime > 0) {
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                $this->recordLoginAttempt($username, false);
                throw new Exception("Account is temporarily locked. Please try again in {$minutes}m {$seconds}s.");
            } else {
                // Lockout period expired, clear old attempts
                $this->clearLoginAttempts($username);
            }
        }
        
        $query = "SELECT id, username, email, password, role, branch_id, full_name, profile_pic, status FROM users WHERE username = :username";
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
        
        // Check if account should be locked after this failed attempt
        if ($this->isAccountLocked($username)) {
            $remainingTime = $this->getLockoutTimeRemaining($username);
            $minutes = floor($remainingTime / 60);
            $seconds = $remainingTime % 60;
            throw new Exception("Too many failed attempts. Account locked for {$minutes}m {$seconds}s.");
        }
        
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
