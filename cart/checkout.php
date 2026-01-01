<?php
// /linen-closet/cart/checkout.php

// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

// ADD THIS: Include Notification Helper
require_once __DIR__ . '/../includes/NotificationHelper.php';

$app = new App();
$db = $app->getDB();

// Check if user is logged in
if (!$app->isLoggedIn()) {
    // Use session directly if App method doesn't work
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . 'auth/login.php?redirect=cart/checkout');
        exit();
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: ' . SITE_URL . 'cart');
    exit();
}

// Fetch cart items - handle new variant structure
$cart = $_SESSION['cart'];

// Extract product IDs from cart items (handles both old and new structure)
$productIds = [];
foreach ($cart as $cartKey => $item) {
    if (isset($item['product_id'])) {
        $productIds[] = $item['product_id'];
    } elseif (is_numeric($cartKey)) {
        // Old structure: cart key is product ID
        $productIds[] = $cartKey;
    }
}
$productIds = array_unique($productIds);
$placeholders = str_repeat('?,', count($productIds) - 1) . '?';

// FIXED: Include min_stock_level in the query
$stmt = $db->prepare("
    SELECT 
        p.id,
        p.name,
        p.price,
        p.stock_quantity,
        p.min_stock_level,  -- CRITICAL: Add this
        p.sku,
        p.is_active,
        (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
    FROM products p
    WHERE p.id IN ($placeholders) AND p.is_active = 1
");

$stmt->execute($productIds);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
$cartItems = [];

foreach ($products as $product) {
    $productId = $product['id'];
    
    // Find all cart items for this product (including variants)
    foreach ($cart as $cartKey => $cartItemData) {
        $itemProductId = null;
        
        // Determine product ID based on cart structure
        if (isset($cartItemData['product_id'])) {
            $itemProductId = $cartItemData['product_id'];
        } elseif (is_numeric($cartKey)) {
            $itemProductId = $cartKey;
        }
        
        // Skip if not this product
        if ($itemProductId != $productId) {
            continue;
        }
        
        $quantity = $cartItemData['quantity'] ?? 1;
        $price = $cartItemData['price'] ?? $product['price'];
        $itemTotal = $price * $quantity;
        
        $cartItems[] = [
            'cart_key' => $cartKey,
            'product' => $product,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $itemTotal,
            'size' => $cartItemData['size'] ?? null,
            'color' => $cartItemData['color'] ?? null,
            'material' => $cartItemData['material'] ?? null,
            'variant_id' => $cartItemData['variant_id'] ?? null
        ];
        
        $subtotal += $itemTotal;
    }
}

$shipping = ($subtotal >= 5000) ? 0 : 300;
$tax = $subtotal * 0.16;
$total = $subtotal + $shipping + $tax;

// Fetch user data - try App method first, then fallback to database
try {
    if (method_exists($app, 'getCurrentUser') && $app->isLoggedIn()) {
        $user = $app->getCurrentUser();
    } else {
        // Fallback: fetch user from database using session
        $userId = $_SESSION['user_id'] ?? 0;
        $userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {
    $user = [];
}

// ========== Fetch user addresses from user_addresses table ==========
try {
    $addressStmt = $db->prepare("
        SELECT * FROM user_addresses 
        WHERE user_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $addressStmt->execute([$user['id'] ?? 0]);
    $addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $addresses = [];
    error_log("Error fetching addresses: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and process order
    $errors = [];
    
    // Validate shipping address
    $hasSavedAddress = !empty($_POST['shipping_address_id']);
    $hasNewAddress = !empty($_POST['new_shipping_address']['address_line1']);
    
    if (!$hasSavedAddress && !$hasNewAddress) {
        $errors[] = 'Please select or enter a shipping address';
    }
    
    // Validate payment method
    if (empty($_POST['payment_method'])) {
        $errors[] = 'Please select a payment method';
    }
    
    if (empty($errors)) {
        // Create order
        try {
            $db->beginTransaction();
            
            // ========== Determine shipping address ==========
            if (!empty($_POST['shipping_address_id'])) {
                // Use saved address
                $addressId = intval($_POST['shipping_address_id']);
                $addressStmt = $db->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
                $addressStmt->execute([$addressId, $user['id']]);
                $savedAddress = $addressStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($savedAddress) {
                    $shippingAddress = implode(', ', array_filter([
                        $savedAddress['full_name'],
                        $savedAddress['address_line1'],
                        $savedAddress['address_line2'],
                        $savedAddress['city'],
                        $savedAddress['state'],
                        $savedAddress['postal_code'],
                        $savedAddress['country']
                    ]));
                } else {
                    throw new Exception("Selected address not found");
                }
            } else {
                // Use new address
                $addr = $_POST['new_shipping_address'];
                $shippingAddress = implode(', ', array_filter([
                    $addr['full_name'],
                    $addr['address_line1'],
                    $addr['address_line2'],
                    $addr['city'],
                    $addr['state'],
                    $addr['postal_code'],
                    $addr['country']
                ]));
                
                // Save new address to user_addresses table if checkbox is checked
                if (isset($_POST['save_new_address']) && $_POST['save_new_address'] == '1') {
                    $saveAddressStmt = $db->prepare("
                        INSERT INTO user_addresses 
                        (user_id, address_title, full_name, address_line1, address_line2, 
                         city, state, postal_code, country, phone, email)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $addressTitle = 'Address from ' . date('M j, Y');
                    $saveAddressStmt->execute([
                        $user['id'],
                        $addressTitle,
                        $addr['full_name'],
                        $addr['address_line1'],
                        $addr['address_line2'] ?? '',
                        $addr['city'],
                        $addr['state'],
                        $addr['postal_code'],
                        $addr['country'],
                        $addr['phone'] ?? ($user['phone'] ?? ''),
                        $addr['email'] ?? ($user['email'] ?? '')
                    ]);
                }
            }
            
            // Insert order
            $orderStmt = $db->prepare("
                INSERT INTO orders (
                    order_number, 
                    user_id, 
                    total_amount,
                    shipping_address, 
                    billing_address,
                    status, 
                    payment_method, 
                    payment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
            $status = 'pending';
            $payment_status = 'pending';
            
            $orderStmt->execute([
                $orderNumber,
                $user['id'] ?? 0,
                $total,
                $shippingAddress,
                $shippingAddress,
                $status,
                $_POST['payment_method'],
                $payment_status
            ]);
            
            $orderId = $db->lastInsertId();
            NotificationHelper::createPaymentNotification(
                $db,
                $orderId,
                $orderNumber,
                'pending', // initial status
                $_POST['payment_method'],
                $total
            );

          // ========== ADD ORDER NOTIFICATION ==========
        try {
            $customerName = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
            $customerName = trim($customerName) ?: 'Customer';
            
            // Use NotificationHelper to create order notification
            NotificationHelper::createOrderNotification(
                $db,
                $orderId,
                $orderNumber,
                $customerName
            );
            
            // Also create a notification for the customer
            NotificationHelper::create(
                $db,
                $user['id'] ?? 0,
                'order',
                'Order Confirmed',
                'Your order #' . $orderNumber . ' has been received',
                '/orders/view.php?id=' . $orderId
            );
            
        } catch (Exception $e) {
            error_log('Order notification error: ' . $e->getMessage());
        }
        // ========== END ORDER NOTIFICATION ==========
            
            if ($orderId) {
                // Store in session for confirmation page
                $_SESSION['last_order_id'] = $orderId;
                $_SESSION['last_order_number'] = $orderNumber;
                
                // Insert order items
                $orderItemStmt = $db->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, product_name, product_sku,
                        quantity, unit_price, total_price,
                        size, color, material
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
            $itemsInserted = 0;
            foreach ($cartItems as $item) {
                    $product = $item['product'];
                    $orderItemStmt->execute([
                        $orderId,
                        $product['id'],
                        $product['name'],
                        $product['sku'] ?? 'N/A',
                        $item['quantity'],
                        $item['price'],
                        $item['total'],
                        $item['size'] ?? null,
                        $item['color'] ?? null,
                        $item['material'] ?? null
                    ]);
                    $itemsInserted++;
                    
                    // Update product stock
                    $updateStmt = $db->prepare("
                        UPDATE products 
                        SET stock_quantity = stock_quantity - ?, 
                            sold_count = sold_count + ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$item['quantity'], $item['quantity'], $product['id']]);
                                // ========== STOCK NOTIFICATION ==========
                                try {
                                    // Calculate new stock
                                    $newStock = (int)$product['stock_quantity'] - (int)$item['quantity'];
                                    $minStock = (int)$product['min_stock_level'];
                                    
                                    // Create stock notification
                                    NotificationHelper::createStockNotification(
                                        $db,
                                        $product['id'],
                                        $product['name'],
                                        $newStock,
                                        $minStock
                                    );
                                    
                                } catch (Exception $e) {
                                    error_log('Stock notification error: ' . $e->getMessage());
                                }
                                // ========== END STOCK NOTIFICATION ==========
                                }
                
                $db->commit();
                
                // Clear cart
                unset($_SESSION['cart']);
                
                // Redirect to order confirmation
                $redirectUrl = SITE_URL . 'orders/confirmation.php?order_id=' . $orderId . '&order_number=' . urlencode($orderNumber);
                header('Location: ' . $redirectUrl);
                exit();
                
            } else {
                throw new Exception("Failed to get order ID after insertion");
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Order creation failed: " . $e->getMessage());
            $errors[] = 'Failed to create order. Please try again.';
        }
    }
}

$pageTitle = "Checkout";

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --primary-blue: #0d6efd;
        --secondary-blue: #0dcaf0;
        --dark-blue: #052c65;
        --light-blue: #cfe2ff;
        --success-green: #198754;
        --warning-orange: #ffc107;
        --danger-red: #dc3545;
    }
    
    /* Blue theme buttons */
    .btn-primary-blue {
        background-color: var(--primary-blue);
        border-color: var(--primary-blue);
        color: white;
    }
    
    .btn-primary-blue:hover {
        background-color: var(--dark-blue);
        border-color: var(--dark-blue);
        color: white;
    }
    
    .btn-outline-blue {
        color: var(--primary-blue);
        border-color: var(--primary-blue);
    }
    
    .btn-outline-blue:hover {
        background-color: var(--primary-blue);
        color: white;
    }
    
    /* Text colors */
    .text-blue {
        color: var(--primary-blue) !important;
    }
    
    .text-dark-blue {
        color: var(--dark-blue) !important;
    }
    
    /* Background colors */
    .bg-blue-light {
        background-color: var(--light-blue);
    }
    
    .bg-blue {
        background-color: var(--primary-blue) !important;
    }
    
    /* Border colors */
    .border-blue {
        border-color: var(--primary-blue) !important;
    }
    
    /* Checkout progress bar */
    .progress {
        height: 8px;
        background-color: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .progress-bar {
        background-color: var(--primary-blue);
    }
    
    /* Form controls */
    .form-control:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .form-check-input:checked {
        background-color: var(--primary-blue);
        border-color: var(--primary-blue);
    }
    
    /* Card styling */
    .card {
        border: 1px solid #dee2e6;
        transition: all 0.3s ease;
    }
    
    .card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .card-header {
        background-color: white;
        border-bottom: 2px solid var(--primary-blue);
    }
    
    /* Order summary sticky */
    .sticky-top {
        position: -webkit-sticky;
        position: sticky;
        z-index: 1020;
    }
    
    /* Payment method cards */
    .payment-method-card {
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }
    
    .payment-method-card:hover {
        border-color: var(--primary-blue);
        transform: translateY(-2px);
    }
    
    .payment-method-card .form-check-input:checked + .card {
        border-color: var(--primary-blue);
        background-color: var(--light-blue);
    }
    
    .shipping-method-card {
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }
    
    .shipping-method-card:hover {
        border-color: var(--primary-blue);
        transform: translateY(-2px);
    }
    
    /* Animation for price updates */
    .price-update {
        animation: pricePulse 0.5s ease-in-out;
    }
    
    @keyframes pricePulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    /* Form validation */
    .is-invalid {
        border-color: var(--danger-red) !important;
    }
    
    .invalid-feedback {
        display: none;
        color: var(--danger-red);
        font-size: 0.875em;
        margin-top: 0.25rem;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }
        
        .display-5 {
            font-size: 2rem;
        }
        
        .sticky-top {
            position: static;
        }
    }
    
    /* Order items scroll */
    .order-items-list {
        max-height: 300px;
        overflow-y: auto;
        padding-right: 10px;
    }
    
    .order-items-list::-webkit-scrollbar {
        width: 5px;
    }
    
    .order-items-list::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .order-items-list::-webkit-scrollbar-thumb {
        background: var(--primary-blue);
        border-radius: 10px;
    }
</style>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-blue-light p-3 rounded">
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>" class="text-decoration-none text-blue">
                    <i class="fas fa-home me-1"></i> Home
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>cart" class="text-decoration-none text-blue">Cart</a>
            </li>
            <li class="breadcrumb-item active text-blue" aria-current="page">Checkout</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <h1 class="display-5 fw-bold mb-3 text-blue">Checkout</h1>
            <div class="progress mb-3" style="height: 8px;">
                <div class="progress-bar" role="progressbar" style="width: 33.33%;"></div>
            </div>
            <div class="d-flex justify-content-between mt-2">
                <div class="text-center">
                    <div class="rounded-circle bg-blue text-white d-inline-flex align-items-center justify-content-center mb-2" 
                         style="width: 30px; height: 30px;">
                        1
                    </div>
                    <div class="fw-bold text-blue">Shipping</div>
                </div>
                <div class="text-center">
                    <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-2" 
                         style="width: 30px; height: 30px;">
                        2
                    </div>
                    <div class="text-muted">Payment</div>
                </div>
                <div class="text-center">
                    <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-2" 
                         style="width: 30px; height: 30px;">
                        3
                    </div>
                    <div class="text-muted">Confirmation</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
            <h5><i class="fas fa-exclamation-triangle me-2"></i> Please fix the following errors:</h5>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="checkoutForm">
        <div class="row">
            <!-- Left Column - Forms -->
            <div class="col-lg-8 mb-4">
                <!-- Shipping Address -->
                <div class="card border-blue shadow-sm mb-4">
                    <div class="card-header bg-blue text-white py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-shipping-fast me-2"></i> Shipping Address
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="new_shipping_address[full_name]"
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                       required>
                                <div class="invalid-feedback">Full name is required</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" 
                                       class="form-control" 
                                       name="new_shipping_address[phone]"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       required>
                                <div class="invalid-feedback">Phone number is required</div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Address Line 1 <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="new_shipping_address[address_line1]"
                                       placeholder="Street address, P.O. Box, Company name"
                                       required>
                                <div class="invalid-feedback">Address line 1 is required</div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Address Line 2</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="new_shipping_address[address_line2]"
                                       placeholder="Apartment, suite, unit, building, floor, etc.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">City <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="new_shipping_address[city]"
                                       required>
                                <div class="invalid-feedback">City is required</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">State/Province <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="new_shipping_address[state]"
                                       required>
                                <div class="invalid-feedback">State/Province is required</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Postal Code <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="new_shipping_address[postal_code]"
                                       required>
                                <div class="invalid-feedback">Postal code is required</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Country <span class="text-danger">*</span></label>
                                <select class="form-control" name="new_shipping_address[country]" required>
                                    <option value="">Select Country</option>
                                    <option value="Kenya" selected>Kenya</option>
                                    <option value="Uganda">Uganda</option>
                                    <option value="Tanzania">Tanzania</option>
                                    <option value="Rwanda">Rwanda</option>
                                    <option value="Burundi">Burundi</option>
                                </select>
                                <div class="invalid-feedback">Country is required</div>
                            </div>
                        </div>
                        
                        <!-- Shipping Method -->
                        <div class="mt-4">
                            <h6 class="fw-bold mb-3 text-dark-blue">Shipping Method</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card border h-100 shipping-method-card">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="radio" 
                                                       name="shipping_method" 
                                                       value="standard"
                                                       id="shipping-standard"
                                                       checked>
                                                <label class="form-check-label fw-bold" for="shipping-standard">
                                                    Standard Shipping
                                                </label>
                                                <div class="mt-2">
                                                    <p class="mb-1 small">Delivery in 3-5 business days</p>
                                                    <p class="mb-0 fw-bold text-blue">
                                                        <?php if ($subtotal >= 5000): ?>
                                                            <span class="text-success">FREE</span>
                                                        <?php else: ?>
                                                            Ksh 300.00
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border h-100 shipping-method-card">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="radio" 
                                                       name="shipping_method" 
                                                       value="express"
                                                       id="shipping-express">
                                                <label class="form-check-label fw-bold" for="shipping-express">
                                                    Express Shipping
                                                </label>
                                                <div class="mt-2">
                                                    <p class="mb-1 small">Delivery in 1-2 business days</p>
                                                    <p class="mb-0 fw-bold text-blue">Ksh 700.00</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="card border-blue shadow-sm mb-4">
                    <div class="card-header bg-blue text-white py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-credit-card me-2"></i> Payment Method
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card border h-100 payment-method-card">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="radio" 
                                                   name="payment_method" 
                                                   value="mpesa"
                                                   id="payment-mpesa"
                                                   checked>
                                            <label class="form-check-label fw-bold" for="payment-mpesa">
                                                <i class="fas fa-mobile-alt me-2 text-success"></i> M-Pesa
                                            </label>
                                            <div class="mt-2">
                                                <p class="mb-0 small">Pay via M-Pesa mobile money</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border h-100 payment-method-card">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="radio" 
                                                   name="payment_method" 
                                                   value="card"
                                                   id="payment-card">
                                            <label class="form-check-label fw-bold" for="payment-card">
                                                <i class="fas fa-credit-card me-2 text-primary"></i> Credit/Debit Card
                                            </label>
                                            <div class="mt-2">
                                                <p class="mb-0 small">Pay with Visa, MasterCard, or American Express</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border h-100 payment-method-card">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="radio" 
                                                   name="payment_method" 
                                                   value="cash"
                                                   id="payment-cash">
                                            <label class="form-check-label fw-bold" for="payment-cash">
                                                <i class="fas fa-money-bill-wave me-2 text-success"></i> Cash on Delivery
                                            </label>
                                            <div class="mt-2">
                                                <p class="mb-0 small">Pay when you receive your order</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Details (conditional) -->
                        <div id="paymentDetails" class="mt-4" style="display: none;">
                            <div id="mpesaDetails" class="payment-method-details">
                                <h6 class="fw-bold mb-3 text-dark-blue">M-Pesa Payment Details</h6>
                                <div class="alert alert-info">
                                    <p class="mb-2"><strong>How to pay with M-Pesa:</strong></p>
                                    <ol class="mb-0">
                                        <li>Go to M-Pesa on your phone</li>
                                        <li>Select "Lipa Na M-Pesa"</li>
                                        <li>Select "Pay Bill"</li>
                                        <li>Enter Business No: <strong>123456</strong></li>
                                        <li>Enter Account No: <strong id="mpesaAccount"><?php echo $user['id'] ?? 'ORDER' . date('His'); ?></strong></li>
                                        <li>Enter Amount: <strong>Ksh <span id="mpesaAmount"><?php echo number_format($total, 2); ?></span></strong></li>
                                        <li>Enter your M-Pesa PIN</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="d-flex justify-content-between">
                    <a href="<?php echo SITE_URL; ?>cart" class="btn btn-outline-blue btn-lg">
                        <i class="fas fa-arrow-left me-2"></i> Back to Cart
                    </a>
                    <button type="submit" class="btn btn-primary-blue btn-lg px-5" id="placeOrderBtn">
                        Place Order <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
            
            <!-- Right Column - Order Summary -->
            <div class="col-lg-4">
                <div class="card border-blue shadow-sm sticky-top" style="top: 100px;">
                    <div class="card-header bg-blue text-white py-3">
                        <h5 class="fw-bold mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <!-- Order Items -->
                        <div class="order-items mb-4">
                            <h6 class="fw-bold mb-3 text-dark-blue">Items (<?php echo count($cartItems); ?>)</h6>
                            <div class="order-items-list">
                                <?php foreach ($cartItems as $item): ?>
                                    <?php
                                    $product = $item['product'];
                                    $imageUrl = SITE_URL . ($product['primary_image'] ?: 'assets/images/placeholder.jpg');
                                    ?>
                                    <div class="d-flex mb-3 pb-3 border-bottom">
                                        <div class="flex-shrink-0">
                                            <img src="<?php echo $imageUrl; ?>" 
                                                class="rounded border" 
                                                style="width: 60px; height: 60px; object-fit: cover;"
                                                alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="fw-bold mb-1 small text-dark">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </h6>
                                            <?php 
                                            // Build variant description
                                            $variantParts = [];
                                            if (!empty($item['size'])) $variantParts[] = 'Size: ' . htmlspecialchars($item['size']);
                                            if (!empty($item['color'])) $variantParts[] = 'Color: ' . htmlspecialchars($item['color']);
                                            if (!empty($item['material'])) $variantParts[] = 'Material: ' . htmlspecialchars($item['material']);
                                            
                                            if (!empty($variantParts)): ?>
                                                <small class="text-muted d-block">
                                                    <?php echo implode(' • ', $variantParts); ?>
                                                </small>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                                <span class="fw-bold text-blue">
                                                    Ksh <?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?> = 
                                                    Ksh <?php echo number_format($item['total'], 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Order Totals -->
                        <div class="order-totals mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span class="fw-bold text-dark-blue">Ksh <?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Shipping</span>
                                <span class="fw-bold text-dark-blue" id="shippingSummary">
                                    <?php if ($shipping > 0): ?>
                                        Ksh <?php echo number_format($shipping, 2); ?>
                                    <?php else: ?>
                                        <span class="text-success">FREE</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Tax (16% VAT)</span>
                                <span class="fw-bold text-dark-blue">Ksh <?php echo number_format($tax, 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-bold fs-5 text-dark-blue">Total</span>
                                <span class="fw-bold fs-5 text-blue" id="totalSummary">
                                    Ksh <?php echo number_format($total, 2); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Security Badge -->
                        <div class="text-center">
                            <small class="text-muted d-block mb-2">
                                <i class="fas fa-shield-alt text-success me-1"></i>
                                100% Secure Checkout
                            </small>
                            <small class="text-muted d-block mb-3">
                                <i class="fas fa-lock text-success me-1"></i>
                                Your payment information is encrypted
                            </small>
                            <div class="d-flex justify-content-center gap-2">
                                <i class="fab fa-cc-visa fa-2x text-primary"></i>
                                <i class="fab fa-cc-mastercard fa-2x text-danger"></i>
                                <i class="fab fa-cc-paypal fa-2x text-info"></i>
                                <i class="fab fa-cc-apple-pay fa-2x text-dark"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Need Help? -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body text-center">
                        <h6 class="fw-bold mb-3 text-dark-blue">
                            <i class="fas fa-question-circle me-2"></i> Need Help?
                        </h6>
                        <p class="small text-muted mb-3">
                            Have questions about your order or need assistance?
                        </p>
                        <div class="d-grid gap-2">
                            <a href="tel:+254700000000" class="btn btn-outline-blue btn-sm">
                                <i class="fas fa-phone me-2"></i> Call Us
                            </a>
                            <a href="mailto:support@linencloset.com" class="btn btn-outline-blue btn-sm">
                                <i class="fas fa-envelope me-2"></i> Email Us
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize elements
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const checkoutForm = document.getElementById('checkoutForm');
    
    // Payment method selection
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updatePaymentDetails();
            updatePaymentCardStyles();
        });
    });
    
    // Shipping method selection
    document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateShippingCost();
            updateShippingCardStyles();
        });
    });
    
    // Form validation on input
    const formInputs = checkoutForm.querySelectorAll('input[required], select[required]');
    formInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });
    
    // Place order button
    if (placeOrderBtn) {
        placeOrderBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (validateForm()) {
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
                this.disabled = true;
                
                // Add a small delay to show loading state
                setTimeout(() => {
                    checkoutForm.submit();
                }, 500);
            }
        });
    }
    
    // Initialize
    updatePaymentDetails();
    updatePaymentCardStyles();
    updateShippingCardStyles();
    
    // Set M-Pesa account number dynamically
    const mpesaAccount = document.getElementById('mpesaAccount');
    if (mpesaAccount) {
        const userId = '<?php echo $user['id'] ?? ''; ?>';
        const orderId = userId ? userId + Date.now().toString().slice(-4) : 'ORDER' + Date.now().toString().slice(-8);
        mpesaAccount.textContent = orderId;
    }
});

// Update payment details based on selection
function updatePaymentDetails() {
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
    const paymentDetails = document.getElementById('paymentDetails');
    const allMethodDetails = document.querySelectorAll('.payment-method-details');
    
    allMethodDetails.forEach(detail => {
        detail.style.display = 'none';
    });
    
    if (paymentMethod === 'mpesa') {
        const mpesaDetails = document.getElementById('mpesaDetails');
        if (mpesaDetails) {
            mpesaDetails.style.display = 'block';
            paymentDetails.style.display = 'block';
        }
    } else {
        paymentDetails.style.display = 'none';
    }
}

// Update payment card styles
function updatePaymentCardStyles() {
    const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
    
    document.querySelectorAll('.payment-method-card').forEach(card => {
        const radio = card.querySelector('input[type="radio"]');
        if (radio && radio.value === selectedMethod) {
            card.classList.add('border-blue');
            card.style.borderWidth = '2px';
            card.style.backgroundColor = '#f8f9fa';
        } else {
            card.classList.remove('border-blue');
            card.style.borderWidth = '1px';
            card.style.backgroundColor = '';
        }
    });
}

// Update shipping card styles
function updateShippingCardStyles() {
    const selectedMethod = document.querySelector('input[name="shipping_method"]:checked')?.value;
    
    document.querySelectorAll('.shipping-method-card').forEach(card => {
        const radio = card.querySelector('input[type="radio"]');
        if (radio && radio.value === selectedMethod) {
            card.classList.add('border-blue');
            card.style.borderWidth = '2px';
            card.style.backgroundColor = '#f8f9fa';
        } else {
            card.classList.remove('border-blue');
            card.style.borderWidth = '1px';
            card.style.backgroundColor = '';
        }
    });
}

// Update shipping cost
function updateShippingCost() {
    const shippingMethod = document.querySelector('input[name="shipping_method"]:checked')?.value;
    const subtotal = <?php echo $subtotal; ?>;
    
    let shippingCost = 0;
    
    if (shippingMethod === 'standard') {
        shippingCost = (subtotal >= 5000) ? 0 : 300;
    } else if (shippingMethod === 'express') {
        shippingCost = 700;
    }
    
    // Update display
    const shippingSummary = document.getElementById('shippingSummary');
    const totalSummary = document.getElementById('totalSummary');
    const mpesaAmount = document.getElementById('mpesaAmount');
    
    const tax = subtotal * 0.16;
    const total = subtotal + shippingCost + tax;
    
    if (shippingSummary) {
        shippingSummary.innerHTML = shippingCost === 0 
            ? '<span class="text-success">FREE</span>'
            : 'Ksh ' + shippingCost.toFixed(2);
    }
    
    if (totalSummary) {
        totalSummary.textContent = 'Ksh ' + total.toFixed(2);
        totalSummary.classList.add('price-update');
        setTimeout(() => {
            totalSummary.classList.remove('price-update');
        }, 500);
    }
    
    if (mpesaAmount) {
        mpesaAmount.textContent = total.toFixed(2);
    }
}

// Validate individual field
function validateField(field) {
    const errorElement = field.parentNode.querySelector('.invalid-feedback') || 
                         field.parentNode.parentNode.querySelector('.invalid-feedback');
    
    if (!field.value.trim()) {
        field.classList.add('is-invalid');
        if (errorElement) {
            errorElement.style.display = 'block';
        }
        return false;
    } else {
        field.classList.remove('is-invalid');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
        return true;
    }
}

// Validate entire form
function validateForm() {
    let isValid = true;
    const errors = [];
    
    // Check required fields
    const requiredFields = [
        { name: 'new_shipping_address[full_name]', label: 'Full Name' },
        { name: 'new_shipping_address[phone]', label: 'Phone Number' },
        { name: 'new_shipping_address[address_line1]', label: 'Address Line 1' },
        { name: 'new_shipping_address[city]', label: 'City' },
        { name: 'new_shipping_address[state]', label: 'State/Province' },
        { name: 'new_shipping_address[postal_code]', label: 'Postal Code' },
        { name: 'new_shipping_address[country]', label: 'Country' }
    ];
    
    requiredFields.forEach(field => {
        const input = document.querySelector(`[name="${field.name}"]`);
        if (!input || !input.value.trim()) {
            errors.push(`${field.label} is required`);
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    // Check payment method
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!paymentMethod) {
        errors.push('Please select a payment method');
        isValid = false;
    }
    
    // Show errors if any
    if (errors.length > 0) {
        showErrorModal(errors);
        return false;
    }
    
    return isValid;
}

// Show error modal
function showErrorModal(errors) {
    // Create modal HTML
    const modalHTML = `
        <div class="modal fade" id="errorModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header border-0 bg-blue text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i> Please fix the following errors:
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                ${errors.map(error => `<li>${error}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-primary-blue" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('errorModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('errorModal'));
    modal.show();
}

// Add smooth scrolling for order items
function scrollToElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>