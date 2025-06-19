<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Ensure user is logged in and is a customer
$auth->requireRole('customer');

$customer_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    // Validate required fields
    if (empty($input['full_name']) || empty($input['email'])) {
        echo json_encode(['success' => false, 'error' => 'Full name and email are required']);
        exit;
    }

    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        exit;
    }

    // Check if email is already taken by another user
    $email_check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $email_check->execute([$input['email'], $customer_id]);
    if ($email_check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Email is already taken']);
        exit;
    }

    // Update user table
    $user_update = $db->prepare("UPDATE users SET 
        email = ?, 
        full_name = ?, 
        phone = ?, 
        address = ?, 
        date_of_birth = ?, 
        emergency_contact = ?, 
        travel_preferences = ?,
        updated_at = NOW()
        WHERE id = ? AND role = 'customer'");
    
    $user_update->execute([
        $input['email'],
        $input['full_name'],
        $input['phone'] ?? null,
        $input['address'] ?? null,
        empty($input['date_of_birth']) ? null : $input['date_of_birth'],
        $input['emergency_contact'] ?? null,
        $input['travel_preferences'] ?? null,
        $customer_id
    ]);

    // Update session data
    $_SESSION['full_name'] = $input['full_name'];
    $_SESSION['email'] = $input['email'];

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?> 