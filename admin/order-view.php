<?php
// /linen-closet/admin/order-view.php

// ====================================================================
// 1. INCLUDES & INITIALIZATION
// ====================================================================

// Include the admin layout function FIRST
require_once __DIR__ . '/layout.php';

// Include necessary files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Check if user is admin
if (!$app->isLoggedIn() || !$app->isAdmin()) {
    $app->redirect('auth/login');
}

// ====================================================================
// 2. GET ORDER ID & VERIFICATION
// ====================================================================

// Get order ID from URL
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    $app->setFlashMessage('error', 'Order ID is required');
    $app->redirect('admin/orders');
}

// ====================================================================
// 3. FORM HANDLING
// ====================================================================

$error = '';
$success = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        // Verify CSRF token
        if (!$app->verifyCsrfToken()) {
            throw new Exception('Invalid form submission. Please try again.');
        }
        
        $status = $app->sanitize($_POST['status']);
        $paymentStatus = $app->sanitize($_POST['payment_status']);
        
        $updateStmt = $db->prepare("
            UPDATE orders 
            SET status = ?, payment_status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$status, $paymentStatus, $orderId]);
        
        $success = 'Order status updated successfully!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ====================================================================
// 4. FETCH ORDER DETAILS
// ====================================================================

// Fetch order details
$stmt = $db->prepare("
    SELECT 
        o.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.address
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    $app->setFlashMessage('error', 'Order not found');
    $app->redirect('admin/orders');
}

// Fetch order items
$itemsStmt = $db->prepare("
    SELECT 
        oi.*,
        p.name as product_name,
        p.slug as product_slug,
        p.sku as product_sku,
        pi.image_url
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

// ====================================================================
// 5. BUILD CONTENT
// ====================================================================

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Order Details</h1>
            <p class="text-muted mb-0">' . htmlspecialchars($order['order_number']) . '</p>
        </div>
        <div>
            <a href="' . SITE_URL . 'admin/orders" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i> Back to Orders
            </a>
            <button type="button" class="btn btn-dark" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Print
            </button>
        </div>
    </div>

    <!-- Flash Message -->
    ' . $app->displayFlashMessage() . '

    <!-- Error/Success Messages -->
    ' . ($error ? '
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($error) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>' : '') . '

    ' . ($success ? '
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($success) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>' : '') . '

    <div class="row">
        <!-- Order Information -->
        <div class="col-lg-8">
            <!-- Order Status Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Order Status</h6>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        ' . $app->csrfField() . '
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="col-md-6">
                            <label class="form-label">Order Status</label>
                            <select class="form-control" name="status">';
$statusOptions = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
foreach ($statusOptions as $statusOption) {
    $selected = $order['status'] === $statusOption ? 'selected' : '';
    $content .= '<option value="' . $statusOption . '" ' . $selected . '>' . ucfirst($statusOption) . '</option>';
}
$content .= '
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Payment Status</label>
                            <select class="form-control" name="payment_status">';
$paymentOptions = ['pending', 'paid', 'failed'];
foreach ($paymentOptions as $paymentOption) {
    $selected = $order['payment_status'] === $paymentOption ? 'selected' : '';
    $content .= '<option value="' . $paymentOption . '" ' . $selected . '>' . ucfirst($paymentOption) . '</option>';
}
$content .= '
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-dark">
                                <i class="fas fa-save me-2"></i> Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Order Items Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Order Items (' . count($orderItems) . ')</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>';
foreach ($orderItems as $item) {
    $content .= '
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="' . SITE_URL . ($item['image_url'] ?: 'assets/images/placeholder.jpg') . '" 
                                                 class="rounded me-3" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                            <div>
                                                <div class="fw-bold">' . htmlspecialchars($item['product_name']) . '</div>
                                                <a href="' . SITE_URL . 'products/' . $item['product_slug'] . '" 
                                                   class="text-decoration-none"
                                                   target="_blank">
                                                    <small>View Product</small>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>' . htmlspecialchars($item['product_sku']) . '</td>
                                    <td>Ksh ' . number_format($item['unit_price'], 2) . '</td>
                                    <td>' . $item['quantity'] . '</td>
                                    <td class="fw-bold">Ksh' . number_format($item['total_price'], 2) . '</td>
                                </tr>';
}
$content .= '
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold">Subtotal</td>
                                    <td class="fw-bold">Ksh' . number_format($order['total_amount'], 2) . '</td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold">Shipping</td>
                                    <td class="fw-bold">Ksh 0.00</td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold">Total</td>
                                    <td class="fw-bold h5">Ksh ' . number_format($order['total_amount'], 2) . '</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Details Sidebar -->
        <div class="col-lg-4">
            <!-- Order Summary Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Order Summary</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Order Number</small>
                        <div class="fw-bold">' . htmlspecialchars($order['order_number']) . '</div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Order Date</small>
                        <div>' . date('F j, Y g:i A', strtotime($order['created_at'])) . '</div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Last Updated</small>
                        <div>' . date('F j, Y g:i A', strtotime($order['updated_at'])) . '</div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Payment Method</small>
                        <div>' . ($order['payment_method'] ? htmlspecialchars($order['payment_method']) : 'Not specified') . '</div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Order Status</small>
                        <div>
                            <span class="badge bg-' . ($order['status'] === 'pending' ? 'warning' : ($order['status'] === 'processing' ? 'info' : ($order['status'] === 'shipped' ? 'primary' : ($order['status'] === 'delivered' ? 'success' : 'danger')))) . '">
                                ' . ucfirst($order['status']) . '
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Payment Status</small>
                        <div>
                            <span class="badge bg-' . ($order['payment_status'] === 'pending' ? 'warning' : ($order['payment_status'] === 'paid' ? 'success' : 'danger')) . '">
                                ' . ucfirst($order['payment_status']) . '
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Customer Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Customer Name</small>
                        <div class="fw-bold">' . ($order['first_name'] ? htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) : 'Guest') . '</div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Email</small>
                        <div>' . ($order['email'] ? htmlspecialchars($order['email']) : 'N/A') . '</div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Phone</small>
                        <div>' . ($order['phone'] ? htmlspecialchars($order['phone']) : 'N/A') . '</div>
                    </div>
                </div>
            </div>
            
            <!-- Shipping Address Card -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Shipping Address</h6>
                </div>
                <div class="card-body">
                    ' . ($order['shipping_address'] ? nl2br(htmlspecialchars($order['shipping_address'])) : 'No shipping address provided') . '
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .admin-sidebar, .admin-header, .btn {
        display: none !important;
    }
    
    .admin-main {
        margin-left: 0 !important;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
}
</style>';

// ====================================================================
// 6. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Order: ' . htmlspecialchars($order['order_number']));