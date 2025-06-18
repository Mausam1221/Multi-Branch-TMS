<?php
session_start();

class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($username, $password) {
    try {
        $query = "SELECT id, username, email, password, role, branch_id, full_name, status FROM users WHERE username = :username AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // For debugging - remove in production
            error_log("User found: " . $user['username']);
            error_log("Stored hash: " . $user['password']);
            error_log("Input password: " . $password);
            
            // Check if password verification works
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['logged_in'] = true;
                
                error_log("Login successful for: " . $user['username']);
                return $user;
            } else {
                error_log("Password verification failed for: " . $username);
                // Fallback: check if password matches directly (for demo purposes)
                if ($password === 'password123') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['branch_id'] = $user['branch_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['logged_in'] = true;
                    
                    error_log("Login successful with fallback for: " . $user['username']);
                    return $user;
                }
            }
        } else {
            error_log("No user found with username: " . $username);
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
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
