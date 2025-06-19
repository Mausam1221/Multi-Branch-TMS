<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Script started\n";

// Usage: Run this script from the command line or browser (for dev only)

// --- CONFIGURE THESE ---
$userId = 3; // Damak Branch Admin user ID
$newPassword = 'password123'; // New password
// -----------------------

echo "Before require_once\n";
require_once 'config/database.php';
echo "After require_once\n";

$database = new Database();
echo "Database object created\n";
$db = $database->getConnection();
echo "Database connection obtained\n";

$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
echo "Password hashed\n";

try {
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    echo "Statement prepared\n";
    $result = $stmt->execute([$hashedPassword, $userId]);
    echo "Statement executed\n";
    if ($result) {
        echo "Password reset successful for user ID $userId.\n";
    } else {
        echo "Failed to reset password for user ID $userId.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "Script finished running.\n"; 