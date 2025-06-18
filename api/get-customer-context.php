<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$customer_id = $input['customer_id'] ?? null;

if (!$customer_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get updated customer statistics
    $stats_query = "SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_trips,
        SUM(CASE WHEN status = 'confirmed' OR status = 'completed' THEN total_amount ELSE 0 END) as total_spent,
        AVG(total_amount) as avg_spending,
        MAX(created_at) as last_booking_date
        FROM bookings WHERE customer_id = ?";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute([$customer_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent package preferences
    $preferences_query = "SELECT p.category, COUNT(*) as count 
                         FROM bookings b 
                         JOIN packages p ON b.package_id = p.id 
                         WHERE b.customer_id = ? 
                         GROUP BY p.category 
                         ORDER BY count DESC 
                         LIMIT 3";
    
    $pref_stmt = $db->prepare($preferences_query);
    $pref_stmt->execute([$customer_id]);
    $preferences = $pref_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'total_bookings' => (int)$stats['total_bookings'],
        'confirmed_bookings' => (int)$stats['confirmed_bookings'],
        'completed_trips' => (int)$stats['completed_trips'],
        'total_spent' => (float)$stats['total_spent'],
        'avg_spending' => (float)$stats['avg_spending'],
        'last_booking_date' => $stats['last_booking_date'],
        'preferences' => $preferences
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
