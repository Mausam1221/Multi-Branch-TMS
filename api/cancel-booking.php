<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
$auth = new Auth((new Database())->getConnection());
$auth->requireRole('customer');

$input = json_decode(file_get_contents('php://input'), true);
$booking_id = $input['booking_id'] ?? null;
$customer_id = $_SESSION['user_id'];

if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'No booking ID provided']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND customer_id = ?");
    $stmt->execute([$booking_id, $customer_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 