<?php
// /linen-closet/account/orders.php

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

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'auth/login.php?redirect=account/orders');
    exit();
}

$userId = $_SESSION['user_id'];
$pageTitle = "My Orders";

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total orders count
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$countStmt->execute([$userId]);
$totalOrders = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalOrders / $limit);

// Fetch orders with pagination
try {
    $stmt = $db->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
               (SELECT SUM(total_price) FROM order_items oi WHERE oi.order_id = o.id) as items_total
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order items for each order
    foreach ($orders as &$order) {
        $itemsStmt = $db->prepare("
            SELECT oi.*, p.name as product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            LIMIT 3
        ");
        $itemsStmt->execute([$order['id']]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Orders page error: " . $e->getMessage());
    $orders = [];
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
                <a href="<?php echo SITE_URL; ?>account/account.php" class="text-decoration-none">My Account</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">My Orders</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold mb-2">My Orders</h1>
                    <p class="text-muted">
                        <?php if ($totalOrders > 0): ?>
                            You have <?php echo $totalOrders; ?> order<?php echo $totalOrders !== 1 ? 's' : ''; ?>
                        <?php else: ?>
                            You haven't placed any orders yet
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <a href="<?php echo SITE_URL; ?>products" class="btn btn-dark">
                        <i class="fas fa-shopping-bag me-2"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($totalOrders > 0): ?>
        <!-- Orders List -->
        <div class="row">
            <div class="col-12">
                <!-- Orders Filter -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search orders..." id="searchOrders">
                                    <button class="btn btn-outline-dark" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-end gap-2">
                                    <select class="form-select w-auto" id="filterStatus">
                                        <option value="">All Status</option>
                                        <option value="pending">Pending</option>
                                        <option value="processing">Processing</option>
                                        <option value="shipped">Shipped</option>
                                        <option value="delivered">Delivered</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                    <select class="form-select w-auto" id="sortOrders">
                                        <option value="newest">Newest First</option>
                                        <option value="oldest">Oldest First</option>
                                        <option value="total_high">Total: High to Low</option>
                                        <option value="total_low">Total: Low to High</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Orders Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Order</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th class="pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr class="order-row" 
                                            data-order-id="<?php echo $order['id']; ?>"
                                            data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                                            data-status="<?php echo $order['status']; ?>"
                                            data-total="<?php echo $order['total_amount']; ?>"
                                            data-date="<?php echo strtotime($order['created_at']); ?>">
                                            <td class="ps-4">
                                                <div class="fw-bold">
                                                    <a href="<?php echo SITE_URL; ?>orders/confirmation.php?order_id=<?php echo $order['id']; ?>" 
                                                       class="text-decoration-none text-dark">
                                                        <?php echo htmlspecialchars($order['order_number']); ?>
                                                    </a>
                                                </div>
                                                <small class="text-muted">
                                                    Payment: 
                                                    <span class="badge bg-<?php 
                                                        echo $order['payment_status'] == 'paid' ? 'success' : 
                                                             ($order['payment_status'] == 'failed' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo ucfirst($order['payment_status']); ?>
                                                    </span>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                                <br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <?php if (!empty($order['items'])): ?>
                                                            <div class="d-flex" style="margin-left: -8px;">
                                                                <?php foreach ($order['items'] as $index => $item): ?>
                                                                    <?php if ($index < 2): ?>
                                                                        <div class="rounded-circle bg-light border" 
                                                                             style="width: 30px; height: 30px; margin-left: -8px;"></div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                                <?php if (count($order['items']) > 2): ?>
                                                                    <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center" 
                                                                         style="width: 30px; height: 30px; margin-left: -8px; font-size: 0.8rem;">
                                                                        +<?php echo count($order['items']) - 2; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold"><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] !== 1 ? 's' : ''; ?></span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php if (!empty($order['items'])): ?>
                                                                <?php echo htmlspecialchars($order['items'][0]['product_name']); ?>
                                                                <?php if (count($order['items']) > 1): ?>
                                                                    + <?php echo count($order['items']) - 1; ?> more
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold">Ksh <?php echo number_format($order['total_amount'], 2); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge status-badge bg-<?php 
                                                    echo $order['status'] == 'delivered' ? 'success' : 
                                                         ($order['status'] == 'shipped' ? 'primary' : 
                                                         ($order['status'] == 'processing' ? 'info' : 
                                                         ($order['status'] == 'cancelled' ? 'danger' : 'warning'))); 
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td class="pe-4">
                                                <div class="d-flex gap-2">
                                                    <a href="<?php echo SITE_URL; ?>orders/confirmation.php?order_id=<?php echo $order['id']; ?>" 
                                                       class="btn btn-sm btn-outline-dark" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo SITE_URL; ?>orders/track.php?order_id=<?php echo $order['id']; ?>" 
                                                       class="btn btn-sm btn-outline-dark" title="Track Order">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                    </a>
                                                    <a href="<?php echo SITE_URL; ?>orders/invoice.php?order_id=<?php echo $order['id']; ?>" 
                                                       class="btn btn-sm btn-outline-dark" title="View Invoice" target="_blank">
                                                        <i class="fas fa-file-invoice"></i>
                                                    </a>
                                                    <?php if ($order['status'] == 'pending' && $order['payment_status'] == 'pending'): ?>
                                                        <button class="btn btn-sm btn-success pay-now-btn" 
                                                                data-order-id="<?php echo $order['id']; ?>"
                                                                title="Pay Now">
                                                            <i class="fas fa-credit-card"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Order Statistics -->
        <div class="row mt-5">
            <div class="col-12">
                <h4 class="fw-bold mb-4">Order Statistics</h4>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card border-0 bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Total Orders</h6>
                                        <h2 class="fw-bold mb-0"><?php echo $totalOrders; ?></h2>
                                    </div>
                                    <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Delivered</h6>
                                        <h2 class="fw-bold mb-0">
                                            <?php
                                            $deliveredStmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status = 'delivered'");
                                            $deliveredStmt->execute([$userId]);
                                            echo $deliveredStmt->fetch()['count'];
                                            ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">In Progress</h6>
                                        <h2 class="fw-bold mb-0">
                                            <?php
                                            $progressStmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status IN ('pending', 'processing', 'shipped')");
                                            $progressStmt->execute([$userId]);
                                            echo $progressStmt->fetch()['count'];
                                            ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-sync-alt fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Total Spent</h6>
                                        <h2 class="fw-bold mb-0">
                                            Ksh <?php
                                            $totalStmt = $db->prepare("SELECT SUM(total_amount) as total FROM orders WHERE user_id = ?");
                                            $totalStmt->execute([$userId]);
                                            $totalSpent = $totalStmt->fetch()['total'] ?? 0;
                                            echo number_format($totalSpent, 2);
                                            ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-coins fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Empty State -->
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="empty-icon mb-4">
                        <i class="fas fa-shopping-cart fa-4x text-muted"></i>
                    </div>
                    <h3 class="fw-bold mb-3">No Orders Yet</h3>
                    <p class="lead text-muted mb-4">
                        You haven't placed any orders yet. Start shopping to see your orders here.
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="<?php echo SITE_URL; ?>products" class="btn btn-dark btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                        </a>
                        <a href="<?php echo SITE_URL; ?>account" class="btn btn-outline-dark btn-lg">
                            <i class="fas fa-user me-2"></i> My Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.order-row:hover {
    background-color: #f8f9fa;
}

.status-badge {
    font-size: 0.8rem;
    padding: 0.25rem 0.75rem;
}

.page-item.active .page-link {
    background-color: #212529;
    border-color: #212529;
}

.page-link {
    color: #212529;
}

.page-link:hover {
    color: #212529;
    background-color: #f8f9fa;
}

.card.bg-primary, .card.bg-success, .card.bg-info, .card.bg-warning {
    border-radius: 10px;
    transition: transform 0.3s ease;
}

.card.bg-primary:hover, .card.bg-success:hover, .card.bg-info:hover, .card.bg-warning:hover {
    transform: translateY(-5px);
}

@media (max-width: 768px) {
    .display-5 {
        font-size: 2rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search orders
    const searchInput = document.getElementById('searchOrders');
    const orderRows = document.querySelectorAll('.order-row');
    
    searchInput?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        orderRows.forEach(row => {
            const orderNumber = row.dataset.orderNumber.toLowerCase();
            const text = row.textContent.toLowerCase();
            
            if (orderNumber.includes(searchTerm) || text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Filter by status
    const filterStatus = document.getElementById('filterStatus');
    filterStatus?.addEventListener('change', function() {
        const status = this.value;
        
        orderRows.forEach(row => {
            if (!status || row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Sort orders
    const sortSelect = document.getElementById('sortOrders');
    sortSelect?.addEventListener('change', function() {
        const sortBy = this.value;
        const tbody = document.querySelector('tbody');
        const rows = Array.from(orderRows);
        
        rows.sort((a, b) => {
            switch(sortBy) {
                case 'newest':
                    return b.dataset.date - a.dataset.date;
                case 'oldest':
                    return a.dataset.date - b.dataset.date;
                case 'total_high':
                    return b.dataset.total - a.dataset.total;
                case 'total_low':
                    return a.dataset.total - b.dataset.total;
                default:
                    return 0;
            }
        });
        
        // Reorder rows in table
        rows.forEach(row => tbody.appendChild(row));
    });
    
    // Pay now buttons
    document.querySelectorAll('.pay-now-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            
            if (confirm('Process payment for this order?')) {
                // In a real app, you would initiate payment here
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                this.disabled = true;
                
                setTimeout(() => {
                    alert('Payment initiated! Check your phone for M-Pesa prompt.');
                    this.innerHTML = '<i class="fas fa-credit-card"></i>';
                    this.disabled = false;
                }, 2000);
            }
        });
    });
    
    // Export orders (basic CSV)
    document.getElementById('exportOrders')?.addEventListener('click', function() {
        const rows = [];
        const headers = ['Order Number', 'Date', 'Items', 'Total', 'Status', 'Payment Status'];
        rows.push(headers.join(','));
        
        orderRows.forEach(row => {
            if (row.style.display !== 'none') {
                const cells = row.querySelectorAll('td');
                const rowData = [
                    row.dataset.orderNumber,
                    cells[1].textContent.replace('\n', ' ').trim(),
                    cells[2].textContent.replace('\n', ' ').trim(),
                    cells[3].textContent.trim(),
                    cells[4].textContent.trim(),
                    row.querySelector('.text-muted .badge')?.textContent.trim() || ''
                ];
                rows.push(rowData.join(','));
            }
        });
        
        const csvContent = rows.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'orders_export.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>