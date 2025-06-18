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
    
    $token = $input['token'];
    $amount = $input['amount'];
    $booking_id = $input['booking_id'];
    
    // Khalti secret key for Nepal payments
    $khalti_secret = 'test_secret_key_f59e8b7d18b4499ca40f68195a846e9b'; // Replace with your secret key
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://khalti.com/api/v2/payment/verify/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'token' => $token,
            'amount' => $amount
        ]),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Key ' . $khalti_secret,
            'Content-Type: application/json',
        ),
    ));
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($httpcode === 200) {
        $khalti_response = json_decode($response, true);
        
        // Update booking status
        $update_query = "UPDATE bookings SET status = 'confirmed', payment_status = 'paid', payment_method = 'khalti', payment_reference = ? WHERE id = ? AND customer_id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$khalti_response['idx'], $booking_id, $_SESSION['user_id']]);
        
        // Create payment record
        $payment_query = "INSERT INTO payments (booking_id, amount, payment_method, payment_reference, status, created_at) VALUES (?, ?, 'khalti', ?, 'completed', NOW())";
        $payment_stmt = $db->prepare($payment_query);
        $payment_stmt->execute([$booking_id, $amount/100, $khalti_response['idx']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified successfully with TravelNepal',
            'booking_id' => $booking_id
        ]);
    } else {
        throw new Exception('Payment verification failed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
