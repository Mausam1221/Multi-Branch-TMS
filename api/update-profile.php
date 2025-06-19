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

// Only require email if not a password-only update
if (
    (!isset($input['new_password']) || $input['new_password'] === '') &&
    (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL))
) {
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
    // Only update profile fields if email is present
    if (isset($input['email']) && $input['email'] !== '') {
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
    }

    if (isset($input['new_password']) && $input['new_password'] !== '') {
        // 1. Check current password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$customer_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($input['current_password'], $user['password'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
            exit;
        }
        // 2. Check new password and confirm match
        if ($input['new_password'] !== $input['confirm_password']) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
            exit;
        }
        // 3. Check password length
        if (strlen($input['new_password']) < 6) {
            echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters.']);
            exit;
        }
        // 4. Update password
        $hashed = password_hash($input['new_password'], PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$hashed, $customer_id]);
        // Destroy session and force logout
        session_unset();
        session_destroy();
        echo json_encode(['success' => true, 'logout' => true, 'message' => 'Password changed. Please log in again.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?> 