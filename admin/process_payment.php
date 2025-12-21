<?php
// /linen-closet/admin/process_payment.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';

$app = new App();
$db = $app->getDB();

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? 0;
    $paymentStatus = $_POST['payment_status'] ?? 'paid';
    
    try {
        // Get order details
        $orderStmt = $db->prepare("
            SELECT o.*, u.email, u.first_name, u.last_name 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE o.id = ?
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit();
        }
        
        // Update payment status
        $updateStmt = $db->prepare("
            UPDATE orders 
            SET payment_status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$paymentStatus, $orderId]);
        
        // Create payment notification
        NotificationHelper::createPaymentNotification(
            $db,
            $orderId,
            $order['order_number'],
            $paymentStatus,
            $order['payment_method'],
            $order['total_amount']
        );
        
        // Also notify the customer if payment is successful
        if ($paymentStatus === 'paid') {
            $customerNotifStmt = $db->prepare("
                INSERT INTO notifications 
                (user_id, type, title, message, link, is_read, created_at) 
                VALUES (?, 'payment', 'Payment Confirmed', 'Your payment for Order #{$order['order_number']} has been confirmed', '/orders/view.php?id=$orderId', 0, NOW())
            ");
            $customerNotifStmt->execute([$order['user_id']]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Payment status updated']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
?>

<!-- HTML form for manual payment processing -->
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-credit-card me-2"></i> Process Payment
                    </h5>
                </div>
                <div class="card-body">
                    <form id="paymentForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Order ID</label>
                            <input type="number" class="form-control" name="order_id" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Payment Status</label>
                            <select class="form-control" name="payment_status" required>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-dark w-100">
                            <i class="fas fa-check me-2"></i> Update Payment Status
                        </button>
                    </form>
                    
                    <div id="result" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const resultDiv = document.getElementById('result');
    
    resultDiv.innerHTML = '<div class="alert alert-info">Processing...</div>';
    
    fetch('<?php echo SITE_URL; ?>admin/process_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
    });
});
</script>