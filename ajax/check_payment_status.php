<?php
// /linen-closet/ajax/check_payment_status.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

header('Content-Type: application/json');

try {
    $checkoutId = $_GET['checkout_id'] ?? '';
    
    if (empty($checkoutId)) {
        echo json_encode([
            'paid' => false,
            'failed' => false,
            'message' => 'No checkout ID provided'
        ]);
        exit();
    }
    
    $app = new App();
    $db = $app->getDB();
    
    // Check payment attempts table
    $stmt = $db->prepare("
        SELECT pa.*, o.payment_status 
        FROM payment_attempts pa
        LEFT JOIN orders o ON pa.order_id = o.id
        WHERE pa.checkout_id = ? 
        ORDER BY pa.created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$checkoutId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        echo json_encode([
            'paid' => false,
            'failed' => false,
            'message' => 'Payment not found'
        ]);
        exit();
    }
    
    // Check if payment is already marked as paid in orders table
    if ($payment['payment_status'] === 'paid') {
        echo json_encode([
            'paid' => true,
            'failed' => false,
            'message' => 'Payment already confirmed',
            'receipt' => $payment['mpesa_receipt'] ?? 'N/A'
        ]);
        exit();
    }
    
    // Also check payment_attempts status
    if ($payment['status'] === 'success') {
        // Update order to paid
        $updateStmt = $db->prepare("
            UPDATE orders 
            SET payment_status = 'paid', 
                payment_date = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$payment['order_id']]);
        
        echo json_encode([
            'paid' => true,
            'failed' => false,
            'message' => 'Payment successful',
            'receipt' => $payment['mpesa_receipt'] ?? 'N/A'
        ]);
    } elseif ($payment['status'] === 'failed') {
        echo json_encode([
            'paid' => false,
            'failed' => true,
            'message' => 'Payment failed: ' . ($payment['error_message'] ?? 'Unknown error')
        ]);
    } else {
        // Still pending
        echo json_encode([
            'paid' => false,
            'failed' => false,
            'message' => 'Waiting for payment confirmation'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Payment status check error: " . $e->getMessage());
    echo json_encode([
        'paid' => false,
        'failed' => false,
        'message' => 'Error checking payment status'
    ]);
}
?>