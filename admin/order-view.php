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
require_once __DIR__ . '/../includes/TaxHelper.php'; // ADDED

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

// Fetch order details - UPDATED TO INCLUDE TAX COLUMNS
$stmt = $db->prepare("
    SELECT 
        o.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.address,
        COALESCE(o.shipping_cost, 0) as shipping_cost,
        COALESCE(o.shipping_message, 'Standard shipping') as shipping_message,
        COALESCE(o.shipping_county, '') as shipping_county,
        COALESCE(o.shipping_town_area, '') as shipping_town_area,
        COALESCE(o.tax_enabled, 0) as tax_enabled,
        COALESCE(o.tax_rate, 0) as tax_rate,
        COALESCE(o.tax_amount, 0) as tax_amount
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

// Debug: Check tax data in order
error_log("Order tax data:");
error_log("  tax_enabled: " . ($order['tax_enabled'] ?? 'NULL'));
error_log("  tax_rate: " . ($order['tax_rate'] ?? 'NULL'));
error_log("  tax_amount: " . ($order['tax_amount'] ?? 'NULL'));

// Fetch order items with variant information
$itemsStmt = $db->prepare("
    SELECT 
        oi.*,
        pv.sku as variant_sku,
        pv.size as variant_size,
        pv.color as variant_color,
        pv.price as variant_price,
        pi.image_url,
        p.slug as product_slug
    FROM order_items oi
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

// ====================================================================
// 5. BUILD CONTENT
// ====================================================================

// Calculate items subtotal
$itemsSubtotal = 0;
foreach ($orderItems as $item) {
    $itemsSubtotal += $item['total_price'];
}

$shippingCost = (float)($order['shipping_cost'] ?? 0);

// Get tax information - Use stored order data first, then fallback to settings
if (isset($order['tax_enabled']) && $order['tax_enabled'] == '1' && isset($order['tax_rate'])) {
    // Use tax data stored with the order
    $taxEnabled = true;
    $taxRate = (float)$order['tax_rate'];
    $taxAmount = isset($order['tax_amount']) ? (float)$order['tax_amount'] : ($itemsSubtotal * ($taxRate / 100));
} else {
    // Fallback to current tax settings
    $taxSettings = TaxHelper::getTaxSettings($db);
    $taxEnabled = $taxSettings['enabled'] == '1';
    $taxRate = (float)$taxSettings['rate'];
    $taxAmount = $taxEnabled ? ($itemsSubtotal * ($taxRate / 100)) : 0;
}

$grandTotal = $itemsSubtotal + $shippingCost + $taxAmount;

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
    // Build variant information string
    $variantInfo = '';
    $variantBadges = '';
    
    if (!empty($item['size']) || !empty($item['color']) || !empty($item['material'])) {
        $variantParts = [];
        $badgeParts = [];
        
        if (!empty($item['size'])) {
            $variantParts[] = 'Size: ' . htmlspecialchars($item['size']);
            $badgeParts[] = '<span class="badge bg-info variant-badge me-1">Size: ' . htmlspecialchars($item['size']) . '</span>';
        }
        if (!empty($item['color'])) {
            $variantParts[] = 'Color: ' . htmlspecialchars($item['color']);
            $badgeParts[] = '<span class="badge bg-warning variant-badge me-1">Color: ' . htmlspecialchars($item['color']) . '</span>';
        }
        if (!empty($item['material'])) {
            $variantParts[] = 'Material: ' . htmlspecialchars($item['material']);
            $badgeParts[] = '<span class="badge bg-secondary variant-badge me-1">Material: ' . htmlspecialchars($item['material']) . '</span>';
        }
        if (!empty($item['variant_id'])) {
            $variantParts[] = 'Variant ID: ' . $item['variant_id'];
            $badgeParts[] = '<span class="badge bg-dark variant-badge me-1">Variant ID: ' . $item['variant_id'] . '</span>';
        }
        
        $variantInfo = implode('<br>', $variantParts);
        $variantBadges = implode('', $badgeParts);
    }
    
    $content .= '
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-start">
                                            <img src="' . SITE_URL . ($item['image_url'] ?: 'assets/images/placeholder.jpg') . '" 
                                                 class="rounded me-3" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                            <div>
                                                <div class="fw-bold">' . htmlspecialchars($item['product_name']) . '</div>';
    
    // Add variant badges
    if (!empty($variantBadges)) {
        $content .= '                                                <div class="mt-1">' . $variantBadges . '</div>';
    }
    
    // Add variant details in tooltip for mobile
    if (!empty($variantInfo)) {
        $content .= '                                                <div class="mt-1">
                                                    <small class="text-muted d-none d-md-block">' . $variantInfo . '</small>
                                                    <small class="text-muted d-md-none" 
                                                           data-bs-toggle="tooltip" 
                                                           title="' . htmlspecialchars(str_replace('<br>', ' | ', $variantInfo)) . '">
                                                        <i class="fas fa-info-circle text-info"></i> Has variants
                                                    </small>
                                                </div>';
    }
    
    $content .= '                                                <a href="' . SITE_URL . 'products/detail.php?slug=' . $item['product_slug'] . '" 
                                                   class="text-decoration-none d-block mt-1"
                                                   target="_blank">
                                                    <small><i class="fas fa-external-link-alt me-1"></i>View Product</small>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>' . htmlspecialchars($item['product_sku']) . '</div>
                                        ' . (!empty($item['variant_id']) ? '<small class="text-muted">Variant: ' . $item['variant_id'] . '</small>' : '') . '
                                    </td>
                                    <td>Ksh ' . number_format($item['unit_price'], 2) . '</td>
                                    <td>
                                        <div class="fw-bold">' . $item['quantity'] . '</div>
                                        ' . (!empty($item['size']) ? '<small class="text-muted d-block">Per ' . htmlspecialchars($item['size']) . '</small>' : '') . '
                                    </td>
                                    <td class="fw-bold">Ksh ' . number_format($item['total_price'], 2) . '</td>
                                </tr>';
}

$content .= '
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold">Items Subtotal</td>
                                    <td class="fw-bold">Ksh ' . number_format($itemsSubtotal, 2) . '</td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold">Shipping (' . htmlspecialchars($order['shipping_message'] ?? 'Standard shipping') . ')</td>
                                    <td class="fw-bold">Ksh ' . number_format($shippingCost, 2) . '</td>
                                </tr>
                                ' . ($taxEnabled ? '
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold">Tax (' . number_format($taxRate, 1) . '% VAT)</td>
                                    <td class="fw-bold">Ksh ' . number_format($taxAmount, 2) . '</td>
                                </tr>' : '') . '
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold h5">Total</td>
                                    <td class="fw-bold h5">Ksh ' . number_format($grandTotal, 2) . '</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Variant Legend -->
                    ' . (count(array_filter($orderItems, function($item) {
                        return !empty($item['size']) || !empty($item['color']) || !empty($item['material']);
                    })) > 0 ? '
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-bold mb-2">Variant Legend</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info variant-badge">Size Variant</span>
                            <span class="badge bg-warning variant-badge">Color Variant</span>
                            <span class="badge bg-secondary variant-badge">Material Variant</span>
                            ' . (count(array_filter($orderItems, function($item) {
                                return !empty($item['variant_id']);
                            })) > 0 ? '<span class="badge bg-dark variant-badge">Variant ID</span>' : '') . '
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i>
                            These badges help identify the exact product variant to ship.
                        </small>
                    </div>' : '') . '
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
                        <div>
                            <span class="badge bg-' . ($order['payment_method'] === 'mpesa' ? 'success' : ($order['payment_method'] === 'card' ? 'primary' : 'secondary')) . '">
                                ' . ($order['payment_method'] ? ucfirst(htmlspecialchars($order['payment_method'])) : 'Not specified') . '
                            </span>
                        </div>
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
                    
                    <!-- Tax Information -->
                    ' . ($taxEnabled ? '
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted d-block mb-2">Tax Information</small>
                        <div class="small">
                            <i class="fas fa-percentage text-success me-1"></i>
                            Tax applied: ' . number_format($taxRate, 1) . '% VAT
                            <br>
                            <i class="fas fa-money-bill-wave text-success me-1"></i>
                            Tax amount: Ksh ' . number_format($taxAmount, 2) . '
                        </div>
                    </div>' : '') . '
                    
                    <!-- Variant Summary -->
                    ' . (count(array_filter($orderItems, function($item) {
                        return !empty($item['size']) || !empty($item['color']) || !empty($item['material']);
                    })) > 0 ? '
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted d-block mb-2">Variant Summary</small>
                        <div class="small">
                            <i class="fas fa-tags text-primary me-1"></i>
                            This order contains ' . count(array_filter($orderItems, function($item) {
                                return !empty($item['size']) || !empty($item['color']) || !empty($item['material']);
                            })) . ' variant item(s)
                        </div>
                    </div>' : '') . '
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
.variant-badge {
    font-size: 0.7rem;
    padding: 3px 8px;
    margin-right: 4px;
    margin-bottom: 4px;
    display: inline-block;
}

.order-items img {
    border: 2px solid #f8f9fa;
}

.order-items img:hover {
    border-color: #0d6efd;
}

.table td {
    vertical-align: middle;
}

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
    
    .variant-badge {
        border: 1px solid #ccc !important;
        color: #000 !important;
        background-color: transparent !important;
        font-weight: bold;
    }
    
    [data-bs-toggle="tooltip"] {
        display: none !important;
    }
}
</style>

<script>
// Initialize tooltips
document.addEventListener("DOMContentLoaded", function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Highlight variant items
    const variantItems = document.querySelectorAll(\'tr\');
    variantItems.forEach(row => {
        const hasVariants = row.querySelector(\'.variant-badge\');
        if (hasVariants) {
            row.classList.add(\'table-info\');
            row.style.backgroundColor = \'rgba(13, 110, 253, 0.05)\';
        }
    });
});
</script>';

// ====================================================================
// 6. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Order: ' . htmlspecialchars($order['order_number']));