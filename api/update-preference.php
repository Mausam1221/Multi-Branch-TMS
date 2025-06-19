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

if (!$input || !isset($input['setting']) || !isset($input['value'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $setting = $input['setting'];
    $value = $input['value'] ? 1 : 0; // Always store as 1 or 0

    // Validate setting name
    $allowed_settings = [
        'email_notifications',
        'sms_notifications', 
        'marketing_emails',
        'language',
        'dark_mode',
        'newsletter_subscription'
    ];

    if (!in_array($setting, $allowed_settings)) {
        echo json_encode(['success' => false, 'error' => 'Invalid setting']);
        exit;
    }

    // Check if preference record exists
    $check_query = $db->prepare("SELECT id FROM customer_preferences WHERE user_id = ?");
    $check_query->execute([$customer_id]);
    $existing = $check_query->fetch();

    if ($existing) {
        // Update existing preference
        $update_query = $db->prepare("UPDATE customer_preferences SET $setting = ?, updated_at = NOW() WHERE user_id = ?");
        $update_query->execute([$value, $customer_id]);
    } else {
        // Create new preference record
        $insert_query = $db->prepare("INSERT INTO customer_preferences (user_id, $setting, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $insert_query->execute([$customer_id, $value]);
    }

    echo json_encode(['success' => true, 'message' => 'Preference updated successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?> 