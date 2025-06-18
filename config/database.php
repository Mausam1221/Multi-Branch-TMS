<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'travel_management_system';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;dbname=" . $this->db_name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // Test the connection
            $this->conn->query("SELECT 1");
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            
            // For debugging - remove in production
            if (isset($_GET['debug'])) {
                echo "Connection error: " . $exception->getMessage();
            }
            
            // Try to provide helpful error messages
            if (strpos($exception->getMessage(), 'Unknown database') !== false) {
                die("Database 'travel_management_system' does not exist. Please create the database first.");
            } elseif (strpos($exception->getMessage(), 'Access denied') !== false) {
                die("Database access denied. Please check your database credentials.");
            } else {
                die("Database connection failed. Please check your database configuration.");
            }
        }
        return $this->conn;
    }
}
?>
