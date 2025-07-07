<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$username = trim($_POST['username'] ?? '');

if (empty($username)) {
    echo json_encode(['error' => 'Username is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    $remainingAttempts = $auth->getRemainingAttempts($username);
    $maxAttempts = (int)$auth->getSystemSetting('max_login_attempts', 5);
    
    echo json_encode([
        'success' => true,
        'remaining_attempts' => $remainingAttempts,
        'max_attempts' => $maxAttempts
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error checking attempts',
        'remaining_attempts' => 5, // Default to max attempts on error
        'max_attempts' => 5
    ]);
}
?> 