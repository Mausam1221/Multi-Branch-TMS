<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth->requireRole('customer');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $package_id = $input['package_id'];
    $travel_date = $input['travel_date'];
    $number_of_people = $input['number_of_people'];
    $special_requests = $input['special_requests'] ?? '';
    $customer_id = $_SESSION['user_id'];
    
    // Get package details
    $package_query = "SELECT * FROM packages WHERE id = ? AND status = 'active'";
    $package_stmt = $db->prepare($package_query);
    $package_stmt->execute([$package_id]);
    $package = $package_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$package) {
        throw new Exception('Package not found');
    }
    
    $total_amount = $package['price'] * $number_of_people;
    
    // Create booking
    $booking_query = "INSERT INTO bookings (customer_id, package_id, branch_id, booking_date, travel_date, number_of_people, total_amount, status, special_requests) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'pending', ?)";
    $booking_stmt = $db->prepare($booking_query);
    $booking_stmt->execute([$customer_id, $package_id, $package['branch_id'], $travel_date, $number_of_people, $total_amount, $special_requests]);
    
    $booking_id = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'booking_id' => $booking_id,
        'total_amount' => $total_amount,
        'package_name' => $package['name']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
