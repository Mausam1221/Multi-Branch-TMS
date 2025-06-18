<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
$auth = new Auth((new Database())->getConnection());
$auth->requireRole('customer');

$input = json_decode(file_get_contents('php://input'), true);
$booking_id = $input['booking_id'] ?? null;
$rating = $input['rating'] ?? null;
$review = $input['review'] ?? '';
$customer_id = $_SESSION['user_id'];

if (!$booking_id || !$rating) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    // Prevent duplicate reviews
    $stmt = $db->prepare('SELECT id FROM reviews WHERE booking_id = ? AND customer_id = ?');
    $stmt->execute([$booking_id, $customer_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You have already reviewed this booking.']);
        exit;
    }
    // Get package_id for the booking
    $stmt = $db->prepare('SELECT package_id FROM bookings WHERE id = ? AND customer_id = ?');
    $stmt->execute([$booking_id, $customer_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Booking not found.']);
        exit;
    }
    $package_id = $row['package_id'];
    $stmt = $db->prepare('INSERT INTO reviews (booking_id, package_id, customer_id, rating, review) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$booking_id, $package_id, $customer_id, $rating, $review]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 