<?php
require_once '../config/database.php';

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
$event = $input['event'] ?? null;
$data = $input['data'] ?? [];

if (!$event) {
    http_response_code(400);
    echo json_encode(['error' => 'Event name required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Insert event into analytics table
    $query = "INSERT INTO chatbot_analytics (event_name, event_data, customer_id, session_id, created_at) 
              VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $event,
        json_encode($data),
        $data['customer_id'] ?? null,
        $data['session_id'] ?? null
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
