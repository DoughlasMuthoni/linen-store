<?php
// /linen-closet/admin/orders.php

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
// 2. FORM HANDLING
// ====================================================================

$error = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        // Verify CSRF token
        if (!$app->verifyCsrfToken()) {
            throw new Exception('Invalid form submission. Please try again.');
        }
        
        $orderId = (int)$_POST['order_id'];
        $status = $app->sanitize($_POST['status']);
        $paymentStatus = $app->sanitize($_POST['payment_status'] ?? '');
        
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = ?, payment_status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $paymentStatus, $orderId]);


           // ================================================================
        // ADD NOTIFICATION HERE - When order status is updated
        // ================================================================
        if (class_exists('Notification')) {
            $notification = new Notification($db);
            $orderNumber = $currentOrder['order_number'] ?? 'N/A';
            
            // Create notification for status change
            $notification->create(0, 'order', 'Order Status Updated', 
                'Order #' . $orderNumber . ' status changed from ' . 
                ($currentOrder['status'] ?? 'unknown') . ' to ' . $status . '.', 
                '/admin/orders/view/' . $orderId
            );
            
            // Create notification for payment received
            if ($paymentStatus === 'paid') {
                $notification->create(0, 'payment', 'Payment Received', 
                    'Payment received for order #' . $orderNumber . '.', 
                    '/admin/orders/view/' . $orderId
                );
            }
        }
        
        $app->setFlashMessage('success', 'Order status updated successfully!');
        $app->redirect('admin/orders');
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ====================================================================
// 3. GET FILTER PARAMETERS
// ====================================================================

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ====================================================================
// 4. BUILD QUERY & FETCH ORDERS
// ====================================================================

// Build query
$query = "
    SELECT 
        o.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        COUNT(oi.id) as item_count,
        GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE 1=1
";

$params = [];

if ($search) {
    $query .= " AND (o.order_number LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

if ($status) {
    $query .= " AND o.status = ?";
    $params[] = $status;
}

if ($payment_status) {
    $query .= " AND o.payment_status = ?";
    $params[] = $payment_status;
}

if ($date_from) {
    $query .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " GROUP BY o.id ORDER BY o.created_at DESC";

// Get orders
$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Calculate stats
$stats = [
    'total' => count($orders),
    'total_amount' => array_sum(array_column($orders, 'total_amount')),
    'pending' => array_filter($orders, fn($o) => $o['status'] === 'pending'),
    'processing' => array_filter($orders, fn($o) => $o['status'] === 'processing'),
    'shipped' => array_filter($orders, fn($o) => $o['status'] === 'shipped'),
    'delivered' => array_filter($orders, fn($o) => $o['status'] === 'delivered'),
    'cancelled' => array_filter($orders, fn($o) => $o['status'] === 'cancelled'),
];

// ====================================================================
// 5. BUILD CONTENT
// ====================================================================

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Order Management</h1>
            <p class="text-muted mb-0">Manage customer orders and track order status.</p>
        </div>
    </div>

    <!-- Flash Message -->
    ' . $app->displayFlashMessage() . '

    <!-- Error Message -->
    ' . ($error ? '
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($error) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>' : '') . '

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Orders
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . $stats['total'] . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . count($stats['pending']) . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Processing
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . count($stats['processing']) . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cog fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Shipped
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . count($stats['shipped']) . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Delivered
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . count($stats['delivered']) . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-dark shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">
                                Total Revenue
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Ksh' . number_format($stats['total_amount'] ?? 0, 2) . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" 
                           class="form-control" 
                           id="search" 
                           name="search" 
                           value="' . htmlspecialchars($search) . '" 
                           placeholder="Order #, Customer, Email...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Order Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="pending" ' . ($status === 'pending' ? 'selected' : '') . '>Pending</option>
                        <option value="processing" ' . ($status === 'processing' ? 'selected' : '') . '>Processing</option>
                        <option value="shipped" ' . ($status === 'shipped' ? 'selected' : '') . '>Shipped</option>
                        <option value="delivered" ' . ($status === 'delivered' ? 'selected' : '') . '>Delivered</option>
                        <option value="cancelled" ' . ($status === 'cancelled' ? 'selected' : '') . '>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="payment_status" class="form-label">Payment Status</label>
                    <select class="form-control" id="payment_status" name="payment_status">
                        <option value="">All Payments</option>
                        <option value="pending" ' . ($payment_status === 'pending' ? 'selected' : '') . '>Pending</option>
                        <option value="paid" ' . ($payment_status === 'paid' ? 'selected' : '') . '>Paid</option>
                        <option value="failed" ' . ($payment_status === 'failed' ? 'selected' : '') . '>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" 
                           class="form-control" 
                           id="date_from" 
                           name="date_from" 
                           value="' . htmlspecialchars($date_from) . '">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" 
                           class="form-control" 
                           id="date_to" 
                           name="date_to" 
                           value="' . htmlspecialchars($date_to) . '">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Orders (' . count($orders) . ')</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
if (count($orders) > 0) {
    foreach ($orders as $order) {
        $statusColors = [
            'pending' => 'warning',
            'processing' => 'info',
            'shipped' => 'primary',
            'delivered' => 'success',
            'cancelled' => 'danger'
        ];
        
        $paymentColors = [
            'pending' => 'warning',
            'paid' => 'success',
            'failed' => 'danger'
        ];
        
        $customerName = $order['first_name'] ? $order['first_name'] . ' ' . $order['last_name'] : 'Guest';
        
        $content .= '
                        <tr>
                            <td>
                                <a href="' . SITE_URL . 'admin/order-view.php?id=' . $order['id'] . '" class="text-decoration-none fw-bold">
                                    ' . htmlspecialchars($order['order_number']) . '
                                </a>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <div>' . htmlspecialchars($customerName) . '</div>
                                    <small class="text-muted">' . htmlspecialchars($order['email']) . '</small>
                                </div>
                            </td>
                            <td>' . date('M j, Y', strtotime($order['created_at'])) . '</td>
                            <td>' . $order['item_count'] . '</td>
                            <td class="fw-bold">Ksh' . number_format($order['total_amount'], 2) . '</td>
                            <td>
                                <span class="badge bg-' . ($statusColors[$order['status']] ?? 'secondary') . '">
                                    ' . ucfirst($order['status']) . '
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-' . ($paymentColors[$order['payment_status']] ?? 'secondary') . '">
                                    ' . ucfirst($order['payment_status']) . '
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="' . SITE_URL . 'admin/order-view.php?id=' . $order['id'] . '" 
                                       class="btn btn-outline-primary"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-outline-secondary"
                                            onclick="updateOrderStatus(' . $order['id'] . ')"
                                            title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>';
    }
} else {
    $content .= '
                         <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>';
}
$content .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                ' . $app->csrfField() . '
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="order_id" id="update_order_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="update_status" class="form-label">Order Status</label>
                        <select class="form-control" id="update_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="update_payment_status" class="form-label">Payment Status</label>
                        <select class="form-control" id="update_payment_status" name="payment_status" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateOrderStatus(orderId) {
    document.getElementById("update_order_id").value = orderId;
    const modal = new bootstrap.Modal(document.getElementById("updateStatusModal"));
    modal.show();
}

    // Initialize DataTables
$(document).ready(function() {
    $(".data-table").DataTable({
        pageLength: 25,
        order: [[2, "desc"]], // Sort by date descending
        columnDefs: [
            { orderable: false, targets: [7] } // Actions column
        ],
        language: {
            emptyTable: "<div class=\\"text-center py-5\\"><i class=\\"fas fa-shopping-cart fa-3x text-muted mb-3\\"></i><h5>No Orders Found</h5><p class=\\"text-muted\\">Try adjusting your filters or check back later.</p></div>",
            search: "_INPUT_",
            searchPlaceholder: "Search orders..."
        }
    
});
});
</script>';

// ====================================================================
// 6. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Order Management');
?>