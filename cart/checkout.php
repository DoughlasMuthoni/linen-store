<?php
// /linen-closet/cart/checkout.php

// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/Shipping.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';
require_once __DIR__ . '/../includes/TaxHelper.php';
require_once __DIR__ . '/../includes/PHPMailerEmail.php';
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

// Include Shipping class
$shippingHelper = new Shipping($db);

// Get shipping zones for dropdown
$shippingZones = $shippingHelper->getZonesForDropdown();

// Get user's selected zone (from session or default)
$selectedZoneId = $_SESSION['selected_zone_id'] ?? ($shippingZones[0]['id'] ?? 0);

// Get user county from session or default
$userCounty = $_SESSION['user']['county'] ?? 'Nairobi';

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

// Calculate INITIAL shipping cost based on county (will be updated by JavaScript)
$shippingInfo = $shippingHelper->calculateShipping($userCounty, $subtotal);

$shipping = $shippingInfo['cost'];
$shippingMessage = $shippingInfo['message'] ?? 'Standard shipping';
$shippingZoneId = $shippingInfo['zone_id'] ?? null;
$deliveryDays = $shippingInfo['delivery_days'] ?? 3;

// Get zone name from selected zone
$shippingZoneName = 'Standard Shipping';
if ($shippingZoneId) {
    foreach ($shippingZones as $zone) {
        if ($zone['id'] == $shippingZoneId) {
            $shippingZoneName = $zone['zone_name'];
            break;
        }
    }
}

// Get tax settings
$taxSettings = TaxHelper::getTaxSettings($db);

// Calculate tax based on settings
if ($taxSettings['enabled'] == '1') {
    $tax = $subtotal * ($taxSettings['rate'] / 100);
} else {
    $tax = 0;
}

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
    
    // Validate town/area
    if (empty($_POST['shipping_town_area'])) {
        $errors[] = 'Please select a town/area';
    }
    
    // Validate payment method
    if (empty($_POST['payment_method'])) {
        $errors[] = 'Please select a payment method';
    }
    
    // Validate shipping fields
    if (!isset($_POST['shipping_cost']) || $_POST['shipping_cost'] === '') {
        $errors[] = 'Shipping cost not calculated. Please select a county/town.';
    }
    
    if (empty($_POST['new_shipping_address']['county'])) {
        $errors[] = 'Please select a county';
    }
    
    if (empty($errors)) {
        // Create order
        try {
            $db->beginTransaction();
            
            // DEBUG: Log POST data
            error_log("=== CHECKOUT FORM SUBMISSION ===");
            error_log("Shipping cost from POST: " . ($_POST['shipping_cost'] ?? 'NOT SET'));
            error_log("Shipping zone from POST: " . ($_POST['shipping_zone_id'] ?? 'NOT SET'));
            error_log("County from POST: " . ($_POST['new_shipping_address']['county'] ?? 'NOT SET'));
            error_log("Town/Area from POST: " . ($_POST['shipping_town_area'] ?? 'NOT SET'));
            error_log("=== END DEBUG ===");
            
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
                    
                    // Get county from saved address or new input
                    $county = $savedAddress['county'] ?? ($_POST['new_shipping_address']['county'] ?? 'Nairobi');
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
                
                $county = $addr['county'] ?? 'Nairobi';
                
                // Save new address to user_addresses table if checkbox is checked
                if (isset($_POST['save_new_address']) && $_POST['save_new_address'] == '1') {
                    $saveAddressStmt = $db->prepare("
                        INSERT INTO user_addresses 
                        (user_id, address_title, full_name, address_line1, address_line2, 
                        city, state, postal_code, country, county, phone, email)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                        $county,
                        $addr['phone'] ?? ($user['phone'] ?? ''),
                        $addr['email'] ?? ($user['email'] ?? '')
                    ]);
                }
            }
            
            // CRITICAL: Get shipping info from POST data (JavaScript updated values)
            $shippingZoneId = $_POST['shipping_zone_id'] ?? null;
            $shippingCost = floatval($_POST['shipping_cost'] ?? $shipping); // Use POST value
            $shippingMessage = $_POST['shipping_message'] ?? 'Standard shipping';
            $shippingTownArea = $_POST['shipping_town_area'] ?? '';
            
            // Get county from POST
            $county = $_POST['new_shipping_address']['county'] ?? $userCounty;
            
            // Calculate FINAL tax amount based on current settings
            if ($taxSettings['enabled'] == '1') {
                $finalTaxAmount = $subtotal * ($taxSettings['rate'] / 100);
            } else {
                $finalTaxAmount = 0;
            }
            
            // Calculate FINAL total to ensure consistency
            $finalTotal = $subtotal + $shippingCost + $finalTaxAmount;
            
            // Verify our calculations
            if (abs($total - $finalTotal) > 0.01) {
                error_log("WARNING: Total mismatch, using recalculated total");
                error_log("  Original total: " . $total);
                error_log("  Recalculated total: " . $finalTotal);
                $total = $finalTotal;
            }
            
            // Insert order with shipping zone and tax info
            $orderStmt = $db->prepare("
                INSERT INTO orders (
                    order_number, 
                    user_id, 
                    total_amount,
                    shipping_address, 
                    shipping_county,
                    shipping_town_area,
                    shipping_zone_id,
                    shipping_cost,
                    shipping_message,
                    billing_address,
                    status, 
                    payment_method, 
                    payment_status,
                    tax_amount,
                    tax_rate,
                    tax_enabled,
                    tax_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
            $status = 'pending';
            $payment_status = 'pending';
            
            $orderStmt->execute([
                $orderNumber,
                $user['id'] ?? 0,
                $finalTotal,
                $shippingAddress,
                $county,
                $shippingTownArea,
                $shippingZoneId,
                $shippingCost,
                $shippingMessage,
                $shippingAddress,
                $status,
                $_POST['payment_method'],
                $payment_status,
                $finalTaxAmount,
                $taxSettings['rate'],
                $taxSettings['enabled'],
                $taxSettings['tax_number'] ?? ''
            ]);
            
            $orderId = $db->lastInsertId();
            NotificationHelper::createPaymentNotification(
                $db,
                $orderId,
                $orderNumber,
                'pending',
                $_POST['payment_method'],
                $finalTotal
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

                // ========== SEND ORDER CONFIRMATION EMAIL ==========
                try {
                    error_log("=== STARTING EMAIL PROCESS ===");
                    error_log("Order ID: $orderId, Order Number: $orderNumber");
                    // Include and initialize Email class
                    require_once __DIR__ . '/../includes/Email.php';
                    $emailer = new Email();
                    
                    // Get customer email and name
                    $customerEmail = $user['email'] ?? '';
                    $customerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                    $customerName = !empty($customerName) ? $customerName : ($user['username'] ?? 'Customer');
                    error_log("Customer Email: $customerEmail, Customer Name: $customerName");
                    if (!empty($customerEmail)) {
                        // Build order data for email
                        $orderData = [
                            'order_number' => $orderNumber,
                            'customer_name' => $customerName,
                            'subtotal' => $subtotal,
                            'shipping' => $shippingCost,
                            'tax' => $finalTaxAmount,
                            'total' => $finalTotal,
                            'status' => 'pending',
                            'shipping_address' => $shippingAddress,
                            'order_date' => date('F j, Y'),
                            'items' => []
                        ];
                        
                        // Add order items from cartItems
                        foreach ($cartItems as $item) {
                            $orderData['items'][] = [
                                'product_name' => $item['product']['name'] ?? 'Product',
                                'quantity' => $item['quantity'],
                                'price' => $item['price']
                            ];
                        }
                        
                        // Send confirmation email
                        $emailSent = $emailer->sendOrderConfirmation($orderData, $customerEmail, $customerName);
                        
                        if ($emailSent) {
                            error_log("✅ ORDER #$orderNumber: Email sent to: " . $customerEmail);
                            
                            // Log to email_logs table if it exists
                            try {
                                $logStmt = $db->prepare("
                                    INSERT INTO email_logs (order_id, email_type, recipient, sent_at, status)
                                    VALUES (?, 'order_confirmation', ?, NOW(), 'sent')
                                ");
                                $logStmt->execute([$orderId, $customerEmail]);
                            } catch (Exception $e) {
                                // Table might not exist, that's OK
                                error_log("Note: Could not log email to database: " . $e->getMessage());
                            }
                        } else {
                            error_log("❌ ORDER #$orderNumber: Failed to send email to: " . $customerEmail);
                        }
                    } else {
                        error_log("⚠️ ORDER #$orderNumber: No customer email found, cannot send confirmation");
                    }
                    
                } catch (Exception $e) {
                    // Don't stop checkout if email fails
                    error_log("Email sending error (non-fatal): " . $e->getMessage());
                }
                // ========== END EMAIL SENDING ==========


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
                            
                            <!-- Hidden field for county that will be updated by JavaScript -->
                            <input type="hidden" name="new_shipping_address[county]" id="countyField" 
                                   value="<?php echo htmlspecialchars($userCounty); ?>">
                            
                            <!-- County Selection -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">County <span class="text-danger">*</span></label>
                                <select class="form-control" id="countySelect" required>
                                    <option value="">Select County</option>
                                    <?php
                                    $counties = $shippingHelper->getAllCounties();
                                    foreach ($counties as $county):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($county); ?>"
                                            <?php echo ($userCounty == $county) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($county); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="Other">Other (Specify)</option>
                                </select>
                                <div class="invalid-feedback">Please select a county</div>
                            </div>
                            
                            <!-- Other County Input -->
                            <div class="col-md-6" id="otherCountyDiv" style="display: none;">
                                <label class="form-label fw-bold">Specify County <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="otherCountyInput"
                                       placeholder="Enter your county">
                            </div>
                            
                            <!-- Towns/Areas Selection -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Towns/Areas <span class="text-danger">*</span></label>
                                <select class="form-control" name="shipping_town_area" id="townAreaSelect" required>
                                    <option value="">Select Town/Area</option>
                                    <?php
                                    $townsAreas = $shippingHelper->getAllTownsAreas();
                                    $userTownArea = $_SESSION['user']['town_area'] ?? '';
                                    
                                    foreach ($townsAreas as $townArea):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($townArea); ?>"
                                            <?php echo ($userTownArea == $townArea) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($townArea); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="Other">Other (Specify)</option>
                                </select>
                                <div class="invalid-feedback">Please select a town/area</div>
                            </div>

                            <!-- Other Town/Area Input -->
                            <div class="col-md-6" id="otherTownAreaDiv" style="display: none;">
                                <label class="form-label fw-bold">Specify Town/Area <span class="text-danger">*</span></label>
                                <input type="text" 
                                    class="form-control" 
                                    id="otherTownAreaInput"
                                    placeholder="Enter your specific town/area">
                            </div>
                        </div>
                        
                        <!-- Shipping Information -->
                        <div class="mt-4">
                            <h6 class="fw-bold mb-3 text-dark-blue">Shipping Information</h6>
                            <div class="alert alert-info" id="shippingInfoAlert">
                                <p class="mb-2"><strong>Shipping to:</strong> <span id="currentCounty"><?php echo htmlspecialchars($userCounty); ?></span></p>
                                <p class="mb-0">
                                    <strong>Cost:</strong> 
                                    <span id="currentShippingCost">
                                        <?php if ($shipping > 0): ?>
                                            Ksh <?php echo number_format($shipping, 2); ?>
                                        <?php else: ?>
                                            <span class="text-success">FREE</span>
                                        <?php endif; ?>
                                    </span>
                                    <br>
                                    <small class="text-muted" id="currentShippingMessage">
                                        <?php echo htmlspecialchars($shippingMessage); ?>
                                        <?php if ($deliveryDays): ?> (<?php echo $deliveryDays; ?> business days)<?php endif; ?>
                                    </small>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Hidden shipping fields -->
                        <input type="hidden" name="shipping_zone_id" id="hiddenShippingZoneId" value="<?php echo $shippingZoneId; ?>">
                        <input type="hidden" name="shipping_cost" id="hiddenShippingCost" value="<?php echo $shipping; ?>">
                        <input type="hidden" name="shipping_message" id="hiddenShippingMessage" value="<?php echo htmlspecialchars($shippingMessage); ?>">
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
                                        <li>Enter Amount: <strong>Ksh <span id="mpesaAmount">
                                            <?php 
                                            $mpesaTotal = $subtotal + $shipping;
                                            if ($taxSettings['enabled'] == '1') {
                                                $mpesaTotal += $subtotal * ($taxSettings['rate'] / 100);
                                            }
                                            echo number_format($mpesaTotal, 2); 
                                            ?>
                                        </span></strong></li>
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
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($shippingMessage); ?></small>
                                    <?php else: ?>
                                        <span class="text-success">FREE</span>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($shippingMessage); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                           <?php if ($taxSettings['enabled'] == '1'): ?>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Tax (<?php echo $taxSettings['rate']; ?>% VAT)</span>
                                <span class="fw-bold text-dark-blue">Ksh <?php echo number_format($tax, 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-bold fs-5 text-dark-blue">Total</span>
                                <span class="fw-bold fs-5 text-blue" id="totalSummary">
                                    Ksh <?php 
                                    $displayTotal = $subtotal + $shipping;
                                    if ($taxSettings['enabled'] == '1') {
                                        $displayTotal += $subtotal * ($taxSettings['rate'] / 100);
                                    }
                                    echo number_format($displayTotal, 2); 
                                    ?>
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
// Shipping Zone Selection Handler
document.addEventListener('DOMContentLoaded', function() {
    const countySelect = document.getElementById('countySelect');
    const otherCountyDiv = document.getElementById('otherCountyDiv');
    const otherCountyInput = document.getElementById('otherCountyInput');
    const townAreaSelect = document.getElementById('townAreaSelect');
    const otherTownAreaDiv = document.getElementById('otherTownAreaDiv');
    const otherTownAreaInput = document.getElementById('otherTownAreaInput');
    const countyField = document.getElementById('countyField');
    const subtotal = <?php echo $subtotal; ?>;
    
    // Handle county selection change
    if (countySelect) {
        countySelect.addEventListener('change', function() {
            const county = this.value;
            
            // Show/hide "Other" county input
            if (county === 'Other') {
                otherCountyDiv.style.display = 'block';
                if (otherCountyInput) {
                    otherCountyInput.required = true;
                }
            } else {
                otherCountyDiv.style.display = 'none';
                if (otherCountyInput) {
                    otherCountyInput.required = false;
                    otherCountyInput.value = '';
                }
                
                // Update shipping for selected county
                if (county) {
                    updateShippingByCounty(county);
                    
                    // Update hidden county field
                    if (countyField) {
                        countyField.value = county;
                    }
                }
            }
        });
    }
    
    // Handle "Other" county input
    if (otherCountyInput) {
        otherCountyInput.addEventListener('input', function() {
            const otherCounty = this.value.trim();
            if (otherCounty.length > 2) {
                updateShippingByCounty(otherCounty);
                
                // Update hidden county field
                if (countyField) {
                    countyField.value = otherCounty;
                }
            }
        });
    }
    
    // Handle town/area selection change
    if (townAreaSelect) {
        townAreaSelect.addEventListener('change', function() {
            const townArea = this.value;
            
            // Show/hide "Other" town/area input
            if (townArea === 'Other') {
                otherTownAreaDiv.style.display = 'block';
                if (otherTownAreaInput) {
                    otherTownAreaInput.required = true;
                }
            } else {
                otherTownAreaDiv.style.display = 'none';
                if (otherTownAreaInput) {
                    otherTownAreaInput.required = false;
                    otherTownAreaInput.value = '';
                }
                
                // Update shipping for selected town/area
                if (townArea) {
                    updateShippingByTownArea(townArea);
                }
            }
        });
    }
    
    // Handle "Other" town/area input
    if (otherTownAreaInput) {
        otherTownAreaInput.addEventListener('input', function() {
            const otherTownArea = this.value.trim();
            if (otherTownArea.length > 2) {
                updateShippingByTownArea(otherTownArea);
            }
        });
    }
    
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
    
    // Initialize shipping calculation on page load
    if (countySelect && countySelect.value && countySelect.value !== 'Other') {
        updateShippingByCounty(countySelect.value);
    }
    
    // Initialize
    updatePaymentDetails();
    updatePaymentCardStyles();
    
    // Set M-Pesa account number dynamically
    const mpesaAccount = document.getElementById('mpesaAccount');
    if (mpesaAccount) {
        const userId = '<?php echo $user['id'] ?? ''; ?>';
        const orderId = userId ? userId + Date.now().toString().slice(-4) : 'ORDER' + Date.now().toString().slice(-8);
        mpesaAccount.textContent = orderId;
    }
});

// Update shipping based on county
async function updateShippingByCounty(county) {
    if (!county || county === '' || county === 'Other') return;
    
    const subtotal = <?php echo $subtotal; ?>;
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/calculate-shipping.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                county: county,
                subtotal: subtotal
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateShippingDisplay(county, data, 'county');
        }
    } catch (error) {
        console.error('Update shipping error:', error);
    }
}

// Update shipping based on town/area
async function updateShippingByTownArea(townArea) {
    if (!townArea || townArea === '' || townArea === 'Other') return;
    
    const subtotal = <?php echo $subtotal; ?>;
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/calculate-shipping-by-town.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                town_area: townArea,
                subtotal: subtotal
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateShippingDisplay(townArea, data, 'town');
        }
    } catch (error) {
        console.error('Update shipping error:', error);
        // Fallback to county-based calculation
        const county = document.getElementById('countySelect').value;
        if (county && county !== 'Other') {
            updateShippingByCounty(county);
        }
    }
}

// Update shipping display with data
function updateShippingDisplay(location, data, type = 'county') {
    const shipping = parseFloat(data.cost) || 0;
    const subtotal = <?php echo $subtotal; ?>;
    
    // Get tax settings from PHP
    const taxEnabled = <?php echo $taxSettings['enabled'] == '1' ? 'true' : 'false'; ?>;
    const taxRate = <?php echo $taxSettings['rate']; ?>;
    
    // Calculate tax based on settings
    let tax;
    if (taxEnabled) {
        tax = subtotal * (taxRate / 100);
    } else {
        tax = 0;
    }
    
    const total = subtotal + shipping + tax;
    
    // CRITICAL: Update the hidden form fields
    document.getElementById('hiddenShippingZoneId').value = data.zone_id || '';
    document.getElementById('hiddenShippingCost').value = shipping;
    document.getElementById('hiddenShippingMessage').value = data.message || 'Standard shipping';
    
    // Update shipping info alert
    const shippingInfoAlert = document.getElementById('shippingInfoAlert');
    if (shippingInfoAlert) {
        let locationInfo = '';
        if (type === 'county') {
            locationInfo = `<strong>Shipping to:</strong> <span id="currentLocation">${location}</span>`;
        } else if (type === 'town') {
            locationInfo = `<strong>Shipping to:</strong> <span id="currentLocation">${location}</span>`;
            if (data.zone_name) {
                locationInfo += `<br><small class="text-muted">Zone: ${data.zone_name}</small>`;
            }
        }
        
        shippingInfoAlert.innerHTML = `
            <p class="mb-2">
                ${locationInfo}
            </p>
            <p class="mb-0">
                <strong>Cost:</strong> 
                <span id="currentShippingCost">
                    ${shipping === 0 ? '<span class="text-success">FREE</span>' : 'Ksh ' + shipping.toFixed(2)}
                </span>
                <br>
                <small class="text-muted" id="currentShippingMessage">
                    ${data.message || 'Standard shipping'}
                    ${data.delivery_days ? ' (' + data.delivery_days + ' business days)' : ''}
                </small>
            </p>
        `;
    }
    
    // Update order summary
    const shippingSummary = document.getElementById('shippingSummary');
    const totalSummary = document.getElementById('totalSummary');
    const mpesaAmount = document.getElementById('mpesaAmount');
    
    if (shippingSummary) {
        shippingSummary.innerHTML = shipping === 0 
            ? '<span class="text-success">FREE</span><small class="d-block text-muted">' + (data.message || 'Standard shipping') + '</small>'
            : 'Ksh ' + shipping.toFixed(2) + '<small class="d-block text-muted">' + (data.message || 'Standard shipping') + '</small>';
    }
    
    if (totalSummary) {
        totalSummary.textContent = 'Ksh ' + total.toFixed(2);
        totalSummary.classList.add('price-update');
        setTimeout(() => {
            totalSummary.classList.remove('price-update');
        }, 500);
    }
    
    // Update M-Pesa amount
    if (mpesaAmount) {
        mpesaAmount.textContent = total.toFixed(2);
    }
}

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
        { name: 'new_shipping_address[country]', label: 'Country' },
        { name: 'new_shipping_address[county]', label: 'County' },
        { name: 'shipping_town_area', label: 'Town/Area' }
    ];
    
    requiredFields.forEach(field => {
        const input = document.querySelector(`[name="${field.name}"]`);
        if (!input || !input.value.trim()) {
            errors.push(`${field.label} is required`);
            if (input) input.classList.add('is-invalid');
            isValid = false;
        } else {
            if (input) input.classList.remove('is-invalid');
        }
    });
    
    // Check "Other" county if selected
    const countySelect = document.getElementById('countySelect');
    if (countySelect && countySelect.value === 'Other') {
        const otherCountyInput = document.getElementById('otherCountyInput');
        if (!otherCountyInput || !otherCountyInput.value.trim()) {
            errors.push('Please specify your county');
            if (otherCountyInput) otherCountyInput.classList.add('is-invalid');
            isValid = false;
        } else {
            // Update hidden county field
            const countyField = document.getElementById('countyField');
            if (countyField) {
                countyField.value = otherCountyInput.value.trim();
            }
        }
    }
    
    // Check "Other" town/area if selected
    const townAreaSelect = document.getElementById('townAreaSelect');
    if (townAreaSelect && townAreaSelect.value === 'Other') {
        const otherTownAreaInput = document.getElementById('otherTownAreaInput');
        if (!otherTownAreaInput || !otherTownAreaInput.value.trim()) {
            errors.push('Please specify your town/area');
            if (otherTownAreaInput) otherTownAreaInput.classList.add('is-invalid');
            isValid = false;
        }
    }
    
    // Check payment method
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!paymentMethod) {
        errors.push('Please select a payment method');
        isValid = false;
    }
    
    // Check shipping cost is calculated
    const shippingCost = document.getElementById('hiddenShippingCost').value;
    if (!shippingCost && shippingCost !== '0') {
        errors.push('Please select a county/town to calculate shipping');
        isValid = false;
    }
    
    // Show errors if any
    if (errors.length > 0) {
        showErrorModal(errors);
        return false;
    }
    
    return true;
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

// Validate form on submission
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
    const countySelect = document.getElementById('countySelect');
    const otherCountyInput = document.getElementById('otherCountyInput');
    const townAreaSelect = document.getElementById('townAreaSelect');
    const otherTownAreaInput = document.getElementById('otherTownAreaInput');
    const countyField = document.getElementById('countyField');
    
    // Validate "Other" county
    if (countySelect && countySelect.value === 'Other') {
        if (!otherCountyInput || !otherCountyInput.value.trim()) {
            e.preventDefault();
            alert('Please specify your county');
            if (otherCountyInput) otherCountyInput.focus();
            return false;
        }
        
        // Ensure hidden county field is updated
        if (countyField) {
            countyField.value = otherCountyInput.value.trim();
        }
    }
    
    // Validate "Other" town/area
    if (townAreaSelect && townAreaSelect.value === 'Other') {
        if (!otherTownAreaInput || !otherTownAreaInput.value.trim()) {
            e.preventDefault();
            alert('Please specify your town/area');
            if (otherTownAreaInput) otherTownAreaInput.focus();
            return false;
        }
        
        // Update the town/area select value
        townAreaSelect.value = otherTownAreaInput.value.trim();
    }
    
    return true;
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>