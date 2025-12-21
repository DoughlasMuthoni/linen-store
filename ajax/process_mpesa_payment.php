<?php
// /linen-closet/ajax/process_mpesa_payment.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/MpesaPayment.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['order_id']) || !isset($input['phone'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request data'
        ]);
        exit();
    }
    
    $orderId = intval($input['order_id']);
    $phone = trim($input['phone']);
    
    // Get order details
    $app = new App();
    $db = $app->getDB();
    
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND payment_status = 'pending'");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'Order not found or already paid'
        ]);
        exit();
    }
    
    // Initialize M-Pesa
    $mpesa = new MpesaPayment($db);
    
    // Format phone number (add 254 if needed)
    if (strlen($phone) === 9) {
        $phone = '254' . $phone;
    } elseif (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    }
    
    // Initiate STK Push
    $result = $mpesa->initiateSTKPush(
        $phone,
        $order['total_amount'],
        $orderId,
        $order['order_number']
    );
    
    if ($result['success']) {
        // Update order with checkout ID
        $updateStmt = $db->prepare("
            UPDATE orders 
            SET checkout_id = ?, 
                payment_method = 'mpesa',
                payment_status = 'processing',
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$result['checkout_id'], $orderId]);
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'checkout_id' => $result['checkout_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("M-Pesa payment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage()
    ]);
}
?>