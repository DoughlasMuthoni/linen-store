<?php
// /linen-closet/orders/track.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Track Your Order";
$order = null;
$trackingData = null;
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNumber = trim($_POST['order_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($orderNumber) || empty($email)) {
        $error = 'Please enter both order number and email address.';
    } else {
        try {
            // Fetch order with user verification
            $stmt = $db->prepare("
                SELECT o.*, u.email as user_email, 
                       CONCAT(u.first_name, ' ', u.last_name) as customer_name
                FROM orders o
                INNER JOIN users u ON o.user_id = u.id
                WHERE o.order_number = ? AND u.email = ?
            ");
            $stmt->execute([$orderNumber, $email]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                // Fetch order items
                $itemsStmt = $db->prepare("
                    SELECT oi.*, p.name as product_name
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ");
                $itemsStmt->execute([$order['id']]);
                $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Generate tracking data
                $trackingData = generateTrackingData($order);
            } else {
                $error = 'No order found with those details. Please check your order number and email.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred while fetching your order. Please try again.';
            error_log("Track order error: " . $e->getMessage());
        }
    }
} elseif (isset($_GET['order_id'])) {
    // Direct link from confirmation page
    $orderId = intval($_GET['order_id']);
    
    try {
        $stmt = $db->prepare("
            SELECT o.*, u.email as user_email,
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name
            FROM orders o
            INNER JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Fetch order items
            $itemsStmt = $db->prepare("
                SELECT oi.*, p.name as product_name
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $itemsStmt->execute([$order['id']]);
            $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $trackingData = generateTrackingData($order);
        }
    } catch (Exception $e) {
        error_log("Track order by ID error: " . $e->getMessage());
    }
}

// Function to generate tracking data
function generateTrackingData($order) {
    $status = $order['status'];
    $createdAt = new DateTime($order['created_at']);
    $now = new DateTime();
    
    $timeline = [];
    
    // Order placed
    $timeline[] = [
        'status' => 'ordered',
        'title' => 'Order Placed',
        'description' => 'We have received your order.',
        'date' => $createdAt->format('F j, Y'),
        'time' => $createdAt->format('g:i A'),
        'completed' => true,
        'icon' => 'fas fa-shopping-cart'
    ];
    
    // Processing
    $processingDate = clone $createdAt;
    $processingDate->modify('+1 hour');
    $timeline[] = [
        'status' => 'processing',
        'title' => 'Processing',
        'description' => 'We are preparing your order for shipment.',
        'date' => $processingDate->format('F j, Y'),
        'time' => $processingDate->format('g:i A'),
        'completed' => in_array($status, ['processing', 'shipped', 'delivered']),
        'icon' => 'fas fa-cog'
    ];
    
    // Shipping
    $shippingDate = clone $createdAt;
    $shippingDate->modify('+1 day');
    $timeline[] = [
        'status' => 'shipping',
        'title' => 'Shipped',
        'description' => 'Your order is on its way!',
        'date' => $shippingDate->format('F j, Y'),
        'time' => $shippingDate->format('g:i A'),
        'completed' => in_array($status, ['shipped', 'delivered']),
        'icon' => 'fas fa-shipping-fast'
    ];
    
    // Delivery
    $deliveryDate = clone $createdAt;
    $deliveryDate->modify('+3 days');
    $timeline[] = [
        'status' => 'delivery',
        'title' => 'Out for Delivery',
        'description' => 'Your order is out for delivery today.',
        'date' => $deliveryDate->format('F j, Y'),
        'time' => '9:00 AM - 5:00 PM',
        'completed' => $status === 'delivered',
        'icon' => 'fas fa-truck'
    ];
    
    // Delivered
    if ($status === 'delivered') {
        $deliveredDate = clone $createdAt;
        $deliveredDate->modify('+3 days');
        $timeline[] = [
            'status' => 'delivered',
            'title' => 'Delivered',
            'description' => 'Your order has been delivered.',
            'date' => $deliveredDate->format('F j, Y'),
            'time' => $deliveredDate->format('g:i A'),
            'completed' => true,
            'icon' => 'fas fa-check-circle'
        ];
    }
    
    // Generate tracking number
    $trackingNumber = 'TRK' . strtoupper(substr(md5($order['order_number']), 0, 10));
    
    // Estimate delivery date
    $estimatedDelivery = clone $createdAt;
    $estimatedDelivery->modify('+5 days');
    
    return [
        'timeline' => $timeline,
        'tracking_number' => $trackingNumber,
        'carrier' => 'Linen Closet Logistics',
        'estimated_delivery' => $estimatedDelivery->format('F j, Y'),
        'current_status' => $status,
        'last_update' => $order['updated_at']
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                    <i class="fas fa-home me-1"></i> Home
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>orders" class="text-decoration-none">Orders</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Track Order</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <h1 class="display-5 fw-bold mb-3">Track Your Order</h1>
            <p class="lead text-muted">
                Enter your order number and email address to track your order status.
            </p>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Track Order Form -->
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header bg-white py-4 border-0">
                    <h4 class="fw-bold mb-0 text-center">
                        <i class="fas fa-search me-2"></i> Find Your Order
                    </h4>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Order Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-receipt"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       name="order_number"
                                       placeholder="e.g., ORD-20231215-ABC123"
                                       value="<?php echo htmlspecialchars($_POST['order_number'] ?? ''); ?>"
                                       required>
                            </div>
                            <small class="text-muted">You can find your order number in your confirmation email.</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" 
                                       class="form-control" 
                                       name="email"
                                       placeholder="Enter the email used for the order"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark btn-lg">
                                <i class="fas fa-search me-2"></i> Track Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($order && $trackingData): ?>
        <!-- Order Tracking Results -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm mb-5">
                    <div class="card-header bg-white py-4 border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="fw-bold mb-1">Order Tracking</h4>
                                <p class="text-muted mb-0">
                                    Order: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    • Placed on: <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?php 
                                    echo $order['status'] == 'delivered' ? 'success' : 
                                         ($order['status'] == 'shipped' ? 'primary' : 
                                         ($order['status'] == 'processing' ? 'info' : 'warning')); 
                                ?> fs-6 px-3 py-2">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <!-- Tracking Info -->
                        <div class="row mb-5">
                            <div class="col-md-3">
                                <div class="card border h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-barcode fa-2x mb-3 text-dark"></i>
                                        <h6 class="fw-bold mb-1">Tracking Number</h6>
                                        <p class="mb-0">
                                            <code><?php echo $trackingData['tracking_number']; ?></code>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-truck fa-2x mb-3 text-primary"></i>
                                        <h6 class="fw-bold mb-1">Carrier</h6>
                                        <p class="mb-0"><?php echo $trackingData['carrier']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar-check fa-2x mb-3 text-success"></i>
                                        <h6 class="fw-bold mb-1">Estimated Delivery</h6>
                                        <p class="mb-0"><?php echo $trackingData['estimated_delivery']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-map-marker-alt fa-2x mb-3 text-info"></i>
                                        <h6 class="fw-bold mb-1">Delivery Address</h6>
                                        <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tracking Timeline -->
                        <h5 class="fw-bold mb-4">Order Timeline</h5>
                        <div class="tracking-timeline">
                            <?php foreach ($trackingData['timeline'] as $index => $event): ?>
                                <div class="tracking-step <?php echo $event['completed'] ? 'completed' : ''; ?>">
                                    <div class="tracking-icon">
                                        <i class="<?php echo $event['icon']; ?>"></i>
                                    </div>
                                    <div class="tracking-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="fw-bold mb-1"><?php echo $event['title']; ?></h6>
                                            <?php if ($event['completed']): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pending</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-muted mb-1"><?php echo $event['description']; ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo $event['date']; ?> at <?php echo $event['time']; ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Order Items -->
                        <?php if (!empty($orderItems)): ?>
                            <div class="mt-5">
                                <h5 class="fw-bold mb-4">Order Items</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th class="text-center">Quantity</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orderItems as $item): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="me-3">
                                                                <div class="bg-light rounded" style="width: 40px; height: 40px;"></div>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                                <small class="text-muted">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-dark rounded-pill px-3">
                                                            <?php echo $item['quantity']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?php echo $item['quantity'] > 0 ? 'success' : 'warning'; ?>">
                                                            <?php echo $item['quantity'] > 0 ? 'In Stock' : 'Backordered'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Actions -->
                        <div class="mt-4 pt-4 border-top">
                            <div class="d-flex justify-content-between">
                                <a href="<?php echo SITE_URL; ?>orders/confirmation.php?order_id=<?php echo $order['id']; ?>" 
                                   class="btn btn-outline-dark">
                                    <i class="fas fa-receipt me-2"></i> View Order Details
                                </a>
                                <div>
                                    <button class="btn btn-dark me-2" onclick="window.print()">
                                        <i class="fas fa-print me-2"></i> Print Tracking
                                    </button>
                                    <a href="<?php echo SITE_URL; ?>orders" class="btn btn-outline-dark">
                                        <i class="fas fa-list me-2"></i> View All Orders
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.tracking-timeline {
    position: relative;
    padding-left: 40px;
}

.tracking-timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.tracking-step {
    position: relative;
    margin-bottom: 30px;
}

.tracking-step:last-child {
    margin-bottom: 0;
}

.tracking-step.completed::before {
    background: #28a745;
}

.tracking-icon {
    position: absolute;
    left: -40px;
    top: 0;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.tracking-step.completed .tracking-icon {
    background: #28a745;
    border-color: #28a745;
    color: white;
}

.tracking-content {
    padding-left: 20px;
}

@media (max-width: 768px) {
    .tracking-timeline {
        padding-left: 30px;
    }
    
    .tracking-icon {
        left: -30px;
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
    }
    
    .display-5 {
        font-size: 2rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Share tracking info
    document.querySelectorAll('.btn-share').forEach(btn => {
        btn.addEventListener('click', function() {
            const platform = this.dataset.platform;
            const orderNumber = '<?php echo $order['order_number'] ?? ''; ?>';
            const trackingNumber = '<?php echo $trackingData['tracking_number'] ?? ''; ?>';
            
            let url = '';
            let message = `Tracking my Linen Closet order #${orderNumber}. Tracking #: ${trackingNumber}`;
            
            switch(platform) {
                case 'whatsapp':
                    url = `https://wa.me/?text=${encodeURIComponent(message)}`;
                    break;
                case 'email':
                    url = `mailto:?subject=Order Tracking&body=${encodeURIComponent(message)}`;
                    break;
                case 'sms':
                    url = `sms:?body=${encodeURIComponent(message)}`;
                    break;
            }
            
            if (url) {
                window.open(url, '_blank');
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>