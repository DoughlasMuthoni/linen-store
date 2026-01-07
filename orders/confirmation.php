<?php
// /linen-closet/orders/confirmation.php

// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/TaxHelper.php';
$app = new App();
$db = $app->getDB();

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header('Location: ' . SITE_URL . 'cart');
    exit();
}

$orderId = intval($_GET['order_id']);

// Fetch order details - FIXED QUERY
try {
    $stmt = $db->prepare("
        SELECT 
            o.*, 
            u.first_name, 
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) as full_name,
            u.email, 
            u.phone 
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        // Try alternative query without the problematic full_name
        error_log("Order not found with first query, trying alternative...");
        
        $stmt = $db->prepare("
            SELECT o.*, u.first_name, u.last_name, u.email, u.phone 
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Create full_name manually
            $order['full_name'] = trim($order['first_name'] . ' ' . $order['last_name']);
        } else {
            header('Location: ' . SITE_URL . 'cart');
            exit();
        }
    }
    
    // Fetch order items
    $itemsStmt = $db->prepare("
        SELECT oi.*, p.slug 
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate subtotal from order items
    $subtotal = 0;
    foreach ($orderItems as $item) {
        $subtotal += $item['total_price'];
    }
    
    // Get tax info from ORDER DATA (not from current settings)
    // The order already has tax_amount stored if tax was enabled during checkout
    if (isset($order['tax_amount']) && isset($order['tax_enabled'])) {
        // Use the tax data stored with the order
        $taxEnabled = $order['tax_enabled'] == '1';
        $taxAmount = floatval($order['tax_amount']);
        $taxRate = floatval($order['tax_rate'] ?? 0);
    } else {
        // Fallback: Get from current tax settings (for old orders that don't have tax data)
        $taxSettings = TaxHelper::getTaxSettings($db);
        $taxEnabled = $taxSettings['enabled'] == '1';
        $taxRate = floatval($taxSettings['rate'] ?? 0);
        
        // Calculate tax based on current settings
        if ($taxEnabled) {
            $taxAmount = $subtotal * ($taxRate / 100);
        } else {
            $taxAmount = 0;
        }
    }
    
   // Calculate shipping cost - FIXED: Use stored shipping cost from order
    // The checkout.php already stores shipping_cost in the order, so use it directly
    if (isset($order['shipping_cost']) && $order['shipping_cost'] !== null) {
        $shippingCost = floatval($order['shipping_cost']);
    } else {
        // Fallback: calculate from total if shipping_cost is not stored
        if (isset($order['total_amount'])) {
            $shippingCost = floatval($order['total_amount']) - $subtotal - $taxAmount;
        } else {
            $shippingCost = 0;
        }
    }
    
} catch (Exception $e) {
    // Log error and show generic message
    error_log("Order confirmation error: " . $e->getMessage());
    
    // Try one more time with simpler query
    try {
        error_log("Trying simpler query...");
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Get user details separately
            $userStmt = $db->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
            $userStmt->execute([$order['user_id']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $order['first_name'] = $user['first_name'];
                $order['last_name'] = $user['last_name'];
                $order['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                $order['email'] = $user['email'];
                $order['phone'] = $user['phone'];
            }
            
            // Get order items
            $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([$orderId]);
            $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate subtotal
            $subtotal = 0;
            foreach ($orderItems as $item) {
                $subtotal += $item['total_price'];
            }
            
            // Get tax info from order
            $taxEnabled = isset($order['tax_enabled']) ? $order['tax_enabled'] == '1' : false;
            $taxAmount = isset($order['tax_amount']) ? floatval($order['tax_amount']) : 0;
            $taxRate = isset($order['tax_rate']) ? floatval($order['tax_rate']) : 0;
            
            // Calculate shipping
            if (isset($order['shipping_cost'])) {
                $shippingCost = floatval($order['shipping_cost']);
            } else {
                $shippingCost = 0;
            }
        } else {
            $order = null;
            $orderItems = [];
            $subtotal = 0;
            $taxAmount = 0;
            $shippingCost = 0;
            $taxEnabled = false;
            $taxRate = 0;
        }
    } catch (Exception $e2) {
        error_log("Even simpler query failed: " . $e2->getMessage());
        $order = null;
        $orderItems = [];
        $subtotal = 0;
        $taxAmount = 0;
        $shippingCost = 0;
        $taxEnabled = false;
        $taxRate = 0;
    }
}

$pageTitle = "Order Confirmation #" . ($order['order_number'] ?? '');

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
                <a href="<?php echo SITE_URL; ?>cart" class="text-decoration-none">Cart</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Order Confirmation</li>
        </ol>
    </nav>
    
    <?php if ($order): ?>
        <!-- Success Header -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="text-center py-4">
                    <div class="checkmark-circle mb-4">
                        <div class="checkmark-wrapper">
                            <div class="checkmark"></div>
                            <div class="checkmark-check"></div>
                        </div>
                    </div>
                    <h1 class="display-5 fw-bold mb-3 text-success">Order Confirmed!</h1>
                    <p class="lead mb-4">
                        Thank you for your order. We've received your order and will process it shortly.
                    </p>
                    <div class="alert alert-success d-inline-block">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>
                            Order Number: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                        </h5>
                    </div>
                
                </div>
            </div>
        </div>
         <!-- ADD THE EMAIL CONFIRMATION MESSAGE RIGHT HERE -->
        <div class="row mb-4">
            <div class="col-lg-8 mx-auto">
                <div class="alert alert-info">
                    <h5 class="alert-heading">
                        <i class="fas fa-envelope me-2"></i> Order Confirmation Email Sent
                    </h5>
                    <p class="mb-2">
                        A confirmation email has been sent to 
                        <strong><?php echo htmlspecialchars($order['email'] ?? $order['customer_email'] ?? 'your email address'); ?></strong>
                    </p>
                    <p class="mb-0 small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        If you don't see it within a few minutes, please check your spam folder.
                    </p>
                </div>
            </div>
        </div>
        <!-- END EMAIL CONFIRMATION MESSAGE -->
        <div class="row">
            <!-- Order Details -->
            <div class="col-lg-8 mb-4">
                <!-- Order Summary Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-shopping-bag me-2"></i> Order Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Image</th>
                                        <th>Product</th>
                                        <th class="text-center">Price</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-center">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                        <?php
                                        // Get product image
                                        $imageStmt = $db->prepare("
                                            SELECT image_url FROM product_images 
                                            WHERE product_id = ? AND is_primary = 1 
                                            LIMIT 1
                                        ");
                                        $imageStmt->execute([$item['product_id']]);
                                        $image = $imageStmt->fetch(PDO::FETCH_ASSOC);
                                        $imageUrl = SITE_URL . ($image['image_url'] ?? 'assets/images/placeholder.jpg');
                                        
                                        $productUrl = SITE_URL . 'products/detail.php?slug=' . ($item['slug'] ?? '');
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                                    <img src="<?php echo $imageUrl; ?>" 
                                                         class="rounded" 
                                                         style="width: 60px; height: 60px; object-fit: cover;"
                                                         alt="Product Image">
                                                </a>
                                            </td>
                                            <td>
                                                <div>
                                                    <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-dark fw-bold">
                                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                                    </a>
                                                    <div class="text-muted small">
                                                        SKU: <?php echo htmlspecialchars($item['product_sku']); ?>
                                                        <?php if ($item['size']): ?>
                                                            <span class="ms-2">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($item['color']): ?>
                                                            <span class="ms-2">Color: <?php echo htmlspecialchars($item['color']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="fw-bold">
                                                    Ksh <?php echo number_format($item['unit_price'], 2); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-dark rounded-pill px-3 py-2">
                                                    <?php echo $item['quantity']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="fw-bold text-dark">
                                                    Ksh <?php echo number_format($item['total_price'], 2); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                        <td class="text-center fw-bold">
                                            Ksh <?php echo number_format($subtotal, 2); ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- Shipping row -->
                                    <tr>
                                        <td colspan="4" class="text-end">Shipping:</td>
                                        <td class="text-center">
                                            <?php if ($shippingCost == 0): ?>
                                                <span class="text-success">FREE</span>
                                            <?php else: ?>
                                                Ksh <?php echo number_format($shippingCost, 2); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- Tax row - ONLY show if tax was enabled AND tax amount > 0 for this order -->
                                    <?php if ($taxEnabled && isset($order['tax_amount']) && floatval($order['tax_amount']) > 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-end">Tax (<?php echo $taxRate; ?>%):</td>
                                            <td class="text-center">
                                                Ksh <?php 
                                                // Use stored tax amount from order if available
                                                echo isset($order['tax_amount']) ? number_format(floatval($order['tax_amount']), 2) : number_format($taxAmount, 2); 
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Total:</td>
                                        <td class="text-center fw-bold fs-5 text-success">
                                            Ksh <?php echo number_format($order['total_amount'], 2); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Order Status Timeline -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-history me-2"></i> Order Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php
                            $statuses = [
                                'pending' => ['icon' => 'fas fa-clock', 'color' => 'warning', 'label' => 'Order Placed'],
                                'processing' => ['icon' => 'fas fa-cog', 'color' => 'info', 'label' => 'Processing'],
                                'shipped' => ['icon' => 'fas fa-shipping-fast', 'color' => 'primary', 'label' => 'Shipped'],
                                'delivered' => ['icon' => 'fas fa-check-circle', 'color' => 'success', 'label' => 'Delivered'],
                                'cancelled' => ['icon' => 'fas fa-times-circle', 'color' => 'danger', 'label' => 'Cancelled']
                            ];
                            
                            $currentStatus = $order['status'];
                            $currentIndex = array_search($currentStatus, array_keys($statuses));
                            
                            foreach ($statuses as $status => $info):
                                $isActive = array_search($status, array_keys($statuses)) <= $currentIndex;
                                $isCurrent = $status === $currentStatus;
                            ?>
                                <div class="timeline-item <?php echo $isActive ? 'active' : ''; ?>">
                                    <div class="timeline-icon bg-<?php echo $isActive ? $info['color'] : 'light'; ?> text-<?php echo $isActive ? 'white' : 'muted'; ?>">
                                        <i class="<?php echo $info['icon']; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="fw-bold mb-1 <?php echo $isActive ? 'text-dark' : 'text-muted'; ?>">
                                            <?php echo $info['label']; ?>
                                            <?php if ($isCurrent): ?>
                                                <span class="badge bg-<?php echo $info['color']; ?> ms-2">Current</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            <?php if ($status === 'pending'): ?>
                                                Order placed on <?php echo date('F d, Y', strtotime($order['created_at'])); ?>
                                            <?php elseif ($status === 'delivered' && $isActive): ?>
                                                Expected delivery: 3-5 business days
                                            <?php else: ?>
                                                <!-- Add actual dates when status changes in your system -->
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Information -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-truck me-2"></i> Shipping Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Shipping Address</h6>
                                <address class="mb-0">
                                    <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                </address>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Shipping Method</h6>
                                <p class="mb-2">
                                    <i class="fas fa-shipping-fast me-2"></i>
                                    <strong>
                                        <?php 
                                        if ($shippingCost == 0) {
                                            echo 'Standard Shipping (FREE)';
                                        } elseif ($shippingCost == 300) {
                                            echo 'Standard Shipping';
                                        } else {
                                            echo 'Express Shipping';
                                        }
                                        ?>
                                    </strong>
                                </p>
                                <p class="text-muted small mb-0">
                                    Estimated delivery: 3-5 business days
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-credit-card me-2"></i> Payment Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Payment Method</h6>
                                <p class="mb-2">
                                    <?php
                                    $paymentMethods = [
                                        'mpesa' => ['icon' => 'fas fa-mobile-alt', 'label' => 'M-Pesa'],
                                        'card' => ['icon' => 'fas fa-credit-card', 'label' => 'Credit/Debit Card'],
                                        'cash' => ['icon' => 'fas fa-money-bill-wave', 'label' => 'Cash on Delivery'],
                                        'paypal' => ['icon' => 'fab fa-paypal', 'label' => 'PayPal']
                                    ];
                                    $method = $order['payment_method'] ?? 'mpesa';
                                    $methodInfo = $paymentMethods[$method] ?? $paymentMethods['mpesa'];
                                    ?>
                                    <i class="<?php echo $methodInfo['icon']; ?> me-2"></i>
                                    <strong><?php echo $methodInfo['label']; ?></strong>
                                </p>
                                <p class="text-muted small mb-0">
                                    Payment Status: 
                                    <span class="badge bg-<?php 
                                        echo $order['payment_status'] == 'paid' ? 'success' : 
                                             ($order['payment_status'] == 'failed' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Order Total</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span>Ksh <?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping:</span>
                                    <span>
                                        <?php if ($shippingCost == 0): ?>
                                            <span class="text-success">FREE</span>
                                        <?php else: ?>
                                            Ksh <?php echo number_format($shippingCost, 2); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php 
                                    // Check if tax was actually applied to this order
                                    $orderTaxEnabled = isset($order['tax_enabled']) ? $order['tax_enabled'] == '1' : $taxEnabled;
                                    $orderTaxAmount = isset($order['tax_amount']) ? floatval($order['tax_amount']) : $taxAmount;
                                    $orderTaxRate = isset($order['tax_rate']) ? floatval($order['tax_rate']) : $taxRate;

                                    if ($orderTaxEnabled && $orderTaxAmount > 0): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Tax (<?php echo $orderTaxRate; ?>% VAT):</span>
                                            <span>Ksh <?php echo number_format($orderTaxAmount, 2); ?></span>
                                        </div>
                                <?php endif; ?>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <strong class="fs-5">Ksh <?php echo number_format($order['total_amount'], 2); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Actions -->
            <div class="col-lg-4">
                <!-- Order Actions Card -->
                <div class="card border-0 shadow-sm sticky-top" style="top: 100px;">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0">Order Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="<?php echo SITE_URL; ?>orders/track.php?order_id=<?php echo $orderId; ?>" 
                               class="btn btn-outline-dark btn-lg">
                                <i class="fas fa-map-marker-alt me-2"></i> Track Order
                            </a>
                            
                            <a href="<?php echo SITE_URL; ?>orders/invoice.php?order_id=<?php echo $orderId; ?>" 
                               class="btn btn-outline-dark btn-lg" target="_blank">
                                <i class="fas fa-file-invoice me-2"></i> View Invoice
                            </a>
                            
                           <?php if ($order['payment_status'] === 'pending' && $order['payment_method'] === 'mpesa'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="fw-bold mb-0">
                <i class="fas fa-mobile-alt me-2 text-success"></i> Complete M-Pesa Payment
            </h5>
        </div>
        <div class="card-body">
            <div id="paymentForm">
                <div class="mb-3">
                    <label class="form-label fw-bold">M-Pesa Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">+254</span>
                        <input type="tel" 
                               class="form-control" 
                               id="mpesaPhone"
                               placeholder="700 000 000"
                               value="<?php echo substr($order['phone'] ?? '', 3); ?>">
                    </div>
                    <div class="form-text">
                        Enter the phone number registered with M-Pesa
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Payment Amount</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">Ksh</span>
                        <input type="text" 
                               class="form-control text-end fw-bold" 
                               value="<?php echo number_format($order['total_amount'], 2); ?>"
                               readonly>
                    </div>
                </div>
                
                <button type="button" class="btn btn-success btn-lg w-100" id="initiateMpesaPayment">
                    <i class="fas fa-mobile-alt me-2"></i> Pay with M-Pesa
                </button>
                
                <div class="alert alert-info mt-3">
                    <h6><i class="fas fa-info-circle me-2"></i> How to pay:</h6>
                    <ol class="mb-0 small">
                        <li>Click "Pay with M-Pesa" button</li>
                        <li>Check your phone for STK Push prompt</li>
                        <li>Enter your M-Pesa PIN</li>
                        <li>Wait for confirmation</li>
                    </ol>
                </div>
                
                <div id="paymentResult" class="mt-3"></div>
            </div>
            
            <div id="paymentStatus" style="display: none;">
                <div class="text-center py-4">
                    <div class="spinner-border text-success mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h6 class="fw-bold">Processing Payment...</h6>
                    <p class="text-muted small mb-3">Please wait while we process your payment</p>
                    <div id="statusMessage"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
                            
                            <a href="<?php echo SITE_URL; ?>products" class="btn btn-dark btn-lg">
                                <i class="fas fa-shopping-bag me-2"></i> Continue Shopping
                            </a>
                            
                            <a href="<?php echo SITE_URL; ?>account/orders.php" class="btn btn-outline-dark">
                                <i class="fas fa-list me-2"></i> View All Orders
                            </a>
                        </div>
                        
                        <!-- Order Support -->
                        <div class="mt-4 pt-4 border-top">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-headset me-2"></i> Need Help?
                            </h6>
                            <div class="d-grid gap-2">
                                <a href="mailto:support@linencloset.com?subject=Order Inquiry: <?php echo $order['order_number']; ?>" 
                                   class="btn btn-outline-dark btn-sm">
                                    <i class="fas fa-envelope me-2"></i> Email Support
                                </a>
                                <a href="tel:+254700000000" class="btn btn-outline-dark btn-sm">
                                    <i class="fas fa-phone me-2"></i> Call: +254 700 000 000
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Updates -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-bell me-2"></i> Get Order Updates
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <p class="small text-muted mb-2">
                                We'll send you updates about your order via email and SMS.
                            </p>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="emailUpdates" checked>
                                <label class="form-check-label small" for="emailUpdates">
                                    Email updates
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="smsUpdates" checked>
                                <label class="form-check-label small" for="smsUpdates">
                                    SMS updates
                                </label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-dark btn-sm w-100" id="saveNotifications">
                            Save Preferences
                        </button>
                    </div>
                </div>
                
                <!-- Share Order -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body text-center">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-share-alt me-2"></i> Share Your Order
                        </h6>
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="btn btn-outline-info btn-sm">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="btn btn-outline-danger btn-sm">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="btn btn-outline-success btn-sm">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        </div>
                        <p class="small text-muted mb-0">
                            Share your purchase with friends and family
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Error State -->
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="error-icon mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x text-warning"></i>
                    </div>
                    <h2 class="fw-bold mb-3">Order Not Found</h2>
                    <p class="lead text-muted mb-4">
                        We couldn't find the order you're looking for. It may have been cancelled or doesn't exist.
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="<?php echo SITE_URL; ?>cart" class="btn btn-dark btn-lg">
                            <i class="fas fa-shopping-cart me-2"></i> Back to Cart
                        </a>
                        <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-dark btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i> Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Checkmark Animation */
.checkmark-circle {
    width: 100px;
    height: 100px;
    margin: 0 auto;
    position: relative;
}

.checkmark-wrapper {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #28a745;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: scaleIn 0.5s ease-out;
}

.checkmark {
    width: 40px;
    height: 40px;
    position: relative;
}

.checkmark-check {
    position: absolute;
    width: 20px;
    height: 40px;
    border-right: 6px solid white;
    border-bottom: 6px solid white;
    transform: rotate(45deg) scale(0);
    transform-origin: 50% 50%;
    animation: checkmark 0.5s ease-out 0.5s forwards;
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: -30px;
    top: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.timeline-item.active .timeline-icon {
    animation: pulse 2s infinite;
}

.timeline-content {
    padding-left: 20px;
}

/* Animations */
@keyframes scaleIn {
    from { transform: scale(0); }
    to { transform: scale(1); }
}

@keyframes checkmark {
    0% { transform: rotate(45deg) scale(0); }
    50% { transform: rotate(45deg) scale(1.2); }
    100% { transform: rotate(45deg) scale(1); }
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(var(--bs-success-rgb), 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(var(--bs-success-rgb), 0); }
    100% { box-shadow: 0 0 0 0 rgba(var(--bs-success-rgb), 0); }
}

.sticky-top {
    position: sticky;
    z-index: 100;
}

@media (max-width: 768px) {
    .timeline {
        padding-left: 20px;
    }
    
    .timeline::before {
        left: 10px;
    }
    
    .timeline-icon {
        left: -20px;
        width: 24px;
        height: 24px;
    }
    
    .display-5 {
        font-size: 2rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Save notification preferences
    document.getElementById('saveNotifications')?.addEventListener('click', function() {
        const emailUpdates = document.getElementById('emailUpdates').checked;
        const smsUpdates = document.getElementById('smsUpdates').checked;
        
        // In a real app, you would send this to your server
        fetch('<?php echo SITE_URL; ?>ajax/save-notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: <?php echo $orderId; ?>,
                email_updates: emailUpdates,
                sms_updates: smsUpdates
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Notification preferences saved!');
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
    
    // Share buttons
    document.querySelectorAll('.btn-outline-primary, .btn-outline-info, .btn-outline-danger, .btn-outline-success').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const platform = this.querySelector('i').className.split('-')[1].split(' ')[0];
            const orderNumber = '<?php echo $order['order_number']; ?>';
            const message = `I just placed an order on Linen Closet! Order #${orderNumber}`;
            
            let url = '';
            switch(platform) {
                case 'facebook':
                    url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}`;
                    break;
                case 'twitter':
                    url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(message)}&url=${encodeURIComponent(window.location.href)}`;
                    break;
                case 'instagram':
                    // Instagram doesn't support direct sharing, so open app
                    showToast('Open Instagram app to share', 'info');
                    return;
                case 'whatsapp':
                    url = `https://wa.me/?text=${encodeURIComponent(message + ' ' + window.location.href)}`;
                    break;
            }
            
            window.open(url, '_blank', 'width=600,height=400');
        });
    });
    
    // M-Pesa Payment Handler
    document.getElementById('initiateMpesaPayment')?.addEventListener('click', function() {
        const phoneInput = document.getElementById('mpesaPhone');
        const phone = phoneInput.value.replace(/\D/g, '');
        
        if (!phone || phone.length !== 9) {
            alert('Please enter a valid 9-digit phone number (without country code)');
            phoneInput.focus();
            return;
        }
        
        // Show processing state
        document.getElementById('paymentForm').style.display = 'none';
        document.getElementById('paymentStatus').style.display = 'block';
        
        // Make payment request
        fetch('<?php echo SITE_URL; ?>ajax/process_mpesa_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: <?php echo $orderId; ?>,
                phone: phone
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statusMessage').innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        ${data.message}
                    </div>
                    <p class="small text-muted">
                        Check your phone now to complete the payment. This page will update automatically.
                    </p>
                `;
                
                // Start polling for payment status
                pollPaymentStatus(data.checkout_id);
            } else {
                document.getElementById('statusMessage').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${data.message}
                    </div>
                `;
                
                // Show retry button
                setTimeout(() => {
                    document.getElementById('paymentForm').style.display = 'block';
                    document.getElementById('paymentStatus').style.display = 'none';
                }, 3000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('statusMessage').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    An error occurred. Please try again.
                </div>
            `;
            
            setTimeout(() => {
                document.getElementById('paymentForm').style.display = 'block';
                document.getElementById('paymentStatus').style.display = 'none';
            }, 3000);
        });
    });

    // Format phone input
    document.getElementById('mpesaPhone')?.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 9 digits
            if (this.value.length > 9) {
                this.value = this.value.substring(0, 9);
            }
        });
    });

// Poll for payment status
function pollPaymentStatus(checkoutId) {
    let attempts = 0;
    const maxAttempts = 30; // Poll for 5 minutes (30 * 10 seconds)
    
    const poll = setInterval(() => {
        attempts++;
        
        if (attempts > maxAttempts) {
            clearInterval(poll);
            document.getElementById('statusMessage').innerHTML += `
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-clock me-2"></i>
                    Payment taking longer than expected. Please check your phone or try again.
                </div>
                <button onclick="location.reload()" class="btn btn-outline-dark btn-sm">
                    Refresh Page
                </button>
            `;
            return;
        }
        
        // Check payment status
        fetch('<?php echo SITE_URL; ?>ajax/check_payment_status.php?checkout_id=' + checkoutId)
        .then(response => response.json())
        .then(data => {
            if (data.paid) {
                clearInterval(poll);
                document.getElementById('statusMessage').innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Payment successful! Receipt: ${data.receipt}
                    </div>
                    <p class="text-muted small">
                        Your order is now being processed. You will receive a confirmation email shortly.
                    </p>
                `;
                
                // Redirect to order details after 3 seconds
                setTimeout(() => {
                    window.location.href = '<?php echo SITE_URL; ?>orders/view.php?id=<?php echo $orderId; ?>';
                }, 3000);
            } else if (data.failed) {
                clearInterval(poll);
                document.getElementById('statusMessage').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        Payment failed: ${data.message}
                    </div>
                    <button onclick="location.reload()" class="btn btn-outline-dark btn-sm">
                        Try Again
                    </button>
                `;
            }
            // If still pending, continue polling
        })
        .catch(error => {
            console.error('Polling error:', error);
        });
    }, 10000); // Poll every 10 seconds
}

// Show toast notification
function showToast(message, type = 'success') {
    // Create toast container if it doesn't exist
    if (!document.querySelector('.toast-container')) {
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toastId = 'toast-' + Date.now();
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    document.querySelector('.toast-container').insertAdjacentHTML('beforeend', toastHTML);
    
    // Show toast
    const toast = new bootstrap.Toast(document.getElementById(toastId));
    toast.show();
    
    // Remove toast after hiding
    document.getElementById(toastId).addEventListener('hidden.bs.toast', function () {
        this.remove();
    });
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>