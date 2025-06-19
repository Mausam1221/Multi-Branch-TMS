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

// Try to get JSON input
$input = json_decode(file_get_contents('php://input'), true);
// If not JSON, use $_POST (for FormData)
if (!$input) {
    $input = $_POST;
}

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Validation
$errors = [];

// Email: required, valid format
if (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email is required.';
}
// Email: unique
$email_check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$email_check->execute([$input['email'], $customer_id]);
if ($email_check->fetch()) {
    $errors[] = 'Email is already taken.';
}
// Phone: if present, must be valid
if (!empty($input['phone']) && !preg_match('/^[+\\d][\\d\s-]{7,}$/', $input['phone'])) {
    $errors[] = 'Phone number is invalid.';
}
// Emergency contact: if present, must be valid
if (!empty($input['emergency_contact']) && !preg_match('/^[+\\d][\\d\s-]{7,}$/', $input['emergency_contact'])) {
    $errors[] = 'Emergency contact is invalid.';
}
// Date of birth: if present, must not be in the future
if (!empty($input['date_of_birth']) && strtotime($input['date_of_birth']) > time()) {
    $errors[] = 'Date of birth cannot be in the future.';
}
if (!empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

try {
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