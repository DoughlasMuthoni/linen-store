<?php
// /linen-closet/admin/customer-orders.php

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
// 2. GET CUSTOMER ID & FETCH DATA
// ====================================================================

// Get customer ID from URL
$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customerId) {
    $app->setFlashMessage('error', 'Customer ID is required');
    $app->redirect('admin/customers');
}

// Fetch customer details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 0");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    $app->setFlashMessage('error', 'Customer not found');
    $app->redirect('admin/customers');
}

// ====================================================================
// 3. GET FILTER PARAMETERS
// ====================================================================

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ====================================================================
// 4. FETCH CUSTOMER ORDERS
// ====================================================================

// Build query
$query = "
    SELECT 
        o.*,
        COUNT(oi.id) as item_count,
        GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ?
";

$params = [$customerId];

if ($search) {
    $query .= " AND (o.order_number LIKE ? OR p.name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($status) {
    $query .= " AND o.status = ?";
    $params[] = $status;
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
    'pending' => count(array_filter($orders, fn($o) => $o['status'] === 'pending')),
    'completed' => count(array_filter($orders, fn($o) => $o['status'] === 'delivered')),
];

// ====================================================================
// 5. BUILD CONTENT
// ====================================================================

$customerName = htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name']));
$customerName = $customerName ?: 'Guest User';

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Customer Order History</h1>
            <p class="text-muted mb-0">' . $customerName . '</p>
        </div>
        <div>
            <a href="' . SITE_URL . 'admin/customers" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i> Back to Customers
            </a>
            <a href="' . SITE_URL . 'admin/customer-view.php?id=' . $customerId . '" class="btn btn-outline-primary">
                <i class="fas fa-user me-2"></i> View Customer
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    ' . $app->displayFlashMessage() . '

    <!-- Customer Info Banner -->
    <div class="card shadow mb-4 bg-light">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="avatar-placeholder bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                             style="width: 60px; height: 60px; font-size: 20px;">
                            ' . strtoupper(substr($customer['first_name'] ?? 'G', 0, 1) . substr($customer['last_name'] ?? 'U', 0, 1)) . '
                        </div>
                        <div>
                            <h4 class="mb-1">' . $customerName . '</h4>
                            <div class="text-muted">
                                <i class="fas fa-envelope me-1"></i>' . htmlspecialchars($customer['email']) . ' 
                                <span class="mx-2">•</span>
                                <i class="fas fa-phone me-1"></i>' . ($customer['phone'] ? htmlspecialchars($customer['phone']) : 'No phone') . '
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="h5 mb-0">$' . number_format($stats['total_amount'], 2) . '</div>
                    <small class="text-muted">Total Spent</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
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

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Orders
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . $stats['pending'] . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Completed Orders
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . $stats['completed'] . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Average Order Value
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                $' . number_format($stats['total'] > 0 ? $stats['total_amount'] / $stats['total'] : 0, 2) . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
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
                <input type="hidden" name="id" value="' . $customerId . '">
                
                <div class="col-md-3">
                    <label for="search" class="form-label">Search Orders</label>
                    <input type="text" 
                           class="form-control" 
                           id="search" 
                           name="search" 
                           value="' . htmlspecialchars($search) . '" 
                           placeholder="Order #, Product name...">
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
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Filter Orders
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Order History (' . count($orders) . ' orders)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
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
        
        $content .= '
                        <tr>
                            <td>
                                <a href="' . SITE_URL . 'admin/order-view.php?id=' . $order['id'] . '" class="text-decoration-none fw-bold">
                                    ' . htmlspecialchars($order['order_number']) . '
                                </a>
                            </td>
                            <td>' . date('M j, Y', strtotime($order['created_at'])) . '</td>
                            <td>' . $order['item_count'] . ' item(s)</td>
                            <td class="fw-bold">$' . number_format($order['total_amount'], 2) . '</td>
                            <td>
                                <span class="badge bg-' . ($paymentColors[$order['payment_status']] ?? 'secondary') . '">
                                    ' . ucfirst($order['payment_status']) . '
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-' . ($statusColors[$order['status']] ?? 'secondary') . '">
                                    ' . ucfirst($order['status']) . '
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
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <h5>No Orders Found</h5>
                                <p class="text-muted">' . $customerName . ' hasn\'t placed any orders yet.</p>
                                <a href="' . SITE_URL . 'products" class="btn btn-dark">
                                    <i class="fas fa-store me-2"></i> Browse Products
                                </a>
                            </td>
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
            <form method="POST" action="' . SITE_URL . 'admin/orders">
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
        order: [[1, "desc"]], // Sort by date descending
        columnDefs: [
            { orderable: false, targets: [6] } // Actions column
        ],
        language: {
            emptyTable: \'<div class="text-center py-5"><i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i><h5>No Orders Found</h5><p class="text-muted">' . addslashes($customerName) . ' hasn\\\'t placed any orders yet.</p></div>\',
            search: "_INPUT_",
            searchPlaceholder: "Search orders..."
        }
    });
});
</script>';

// ====================================================================
// 6. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Orders: ' . $customerName);
?>