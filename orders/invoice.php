<?php
// /linen-closet/orders/invoice.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header('Location: ' . SITE_URL . 'cart');
    exit();
}

$orderId = intval($_GET['order_id']);

// Fetch order details
try {
    $stmt = $db->prepare("
        SELECT 
            o.*, 
            u.first_name, 
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) as customer_name,
            u.email, 
            u.phone,
            u.address as user_address
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die("Order not found.");
    }
    
    // Fetch order items
    $itemsStmt = $db->prepare("
        SELECT oi.*, p.name as product_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate subtotal
    $subtotal = array_sum(array_column($orderItems, 'total_price'));
    
    // Calculate shipping and tax (approximate)
    $shipping = $order['total_amount'] - $subtotal - ($subtotal * 0.16);
    $tax = $subtotal * 0.16;
    
} catch (Exception $e) {
    die("Error loading invoice: " . $e->getMessage());
}

// Set PDF headers if requested
if (isset($_GET['download']) && $_GET['download'] == 'pdf') {
    // In a real app, you would generate PDF here
    // For now, we'll just show HTML version
}

$pageTitle = "Invoice #" . $order['order_number'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Invoice Header -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="display-5 fw-bold mb-2">INVOICE</h1>
                    <p class="text-muted mb-0">Order #<?php echo $order['order_number']; ?></p>
                </div>
                <div class="text-end">
                    <div class="d-flex gap-2 mb-3">
                        <button class="btn btn-outline-dark" onclick="window.print()">
                            <i class="fas fa-print me-2"></i> Print Invoice
                        </button>
                        <a href="<?php echo SITE_URL; ?>orders/confirmation.php?order_id=<?php echo $orderId; ?>" 
                           class="btn btn-dark">
                            <i class="fas fa-receipt me-2"></i> View Order
                        </a>
                    </div>
                    <p class="text-muted mb-0">
                        Invoice Date: <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Company and Customer Info -->
    <div class="row mb-5">
        <div class="col-md-6">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Linen Closet</h5>
                    <p class="mb-1">123 Fashion Street</p>
                    <p class="mb-1">Nairobi, Kenya</p>
                    <p class="mb-1">Phone: +254 700 000 000</p>
                    <p class="mb-0">Email: billing@linencloset.com</p>
                    <p class="mb-0">Website: www.linencloset.com</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Bill To</h5>
                    <p class="mb-1"><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                    <p class="mb-1"><?php echo htmlspecialchars($order['email']); ?></p>
                    <p class="mb-1"><?php echo htmlspecialchars($order['phone']); ?></p>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Invoice Details -->
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Description</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end pe-4">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $index => $item): ?>
                            <tr>
                                <td class="ps-4"><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <small class="text-muted">
                                        SKU: <?php echo htmlspecialchars($item['product_sku']); ?>
                                        <?php if ($item['size']): ?>
                                            • Size: <?php echo htmlspecialchars($item['size']); ?>
                                        <?php endif; ?>
                                        <?php if ($item['color']): ?>
                                            • Color: <?php echo htmlspecialchars($item['color']); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-end">Ksh <?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-end pe-4 fw-bold">Ksh <?php echo number_format($item['total_price'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Invoice Totals -->
    <div class="row justify-content-end">
        <div class="col-lg-4">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal:</span>
                        <span class="fw-bold">Ksh <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Shipping:</span>
                        <span class="fw-bold">
                            <?php if ($shipping == 0): ?>
                                <span class="text-success">FREE</span>
                            <?php else: ?>
                                Ksh <?php echo number_format($shipping, 2); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Tax (16% VAT):</span>
                        <span class="fw-bold">Ksh <?php echo number_format($tax, 2); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold fs-5">Total:</span>
                        <span class="fw-bold fs-5">Ksh <?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                    
                    <?php if ($order['payment_status'] == 'paid'): ?>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Payment Status:</strong> Paid on <?php echo date('F j, Y', strtotime($order['updated_at'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Payment Status:</strong> <?php echo ucfirst($order['payment_status']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Invoice Footer -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="fw-bold mb-3">Payment Method</h6>
                            <p class="mb-0">
                                <?php
                                $paymentMethods = [
                                    'mpesa' => 'M-Pesa Mobile Money',
                                    'card' => 'Credit/Debit Card',
                                    'cash' => 'Cash on Delivery',
                                    'paypal' => 'PayPal'
                                ];
                                echo $paymentMethods[$order['payment_method']] ?? ucfirst($order['payment_method']);
                                ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="fw-bold mb-3">Order Status</h6>
                            <p class="mb-0">
                                <span class="badge bg-<?php 
                                    echo $order['status'] == 'delivered' ? 'success' : 
                                         ($order['status'] == 'shipped' ? 'primary' : 
                                         ($order['status'] == 'processing' ? 'info' : 'warning')); 
                                ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="fw-bold mb-3">Terms & Conditions</h6>
                            <p class="small text-muted mb-0">
                                Payment due upon receipt. Late payments subject to 1.5% monthly interest.
                                Returns accepted within 30 days of delivery.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Thank You Note -->
            <div class="text-center mt-5 pt-5 border-top">
                <h5 class="fw-bold mb-3">Thank you for your business!</h5>
                <p class="text-muted">
                    If you have any questions about this invoice, please contact<br>
                    <a href="mailto:billing@linencloset.com">billing@linencloset.com</a> or call +254 700 000 000
                </p>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .container-fluid {
        padding: 0;
    }
    
    .btn, nav, footer {
        display: none !important;
    }
    
    body {
        background: white;
        color: black;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .table {
        border: 1px solid #dee2e6;
    }
    
    .table-dark th {
        background-color: #343a40 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
    }
}

.table th {
    font-weight: 600;
}

.card.bg-light {
    background-color: #f8f9fa !important;
}
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>