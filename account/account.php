<?php
// /linen-closet/account/account.php

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
    header('Location: ' . SITE_URL . 'auth/login.php?redirect=account/account.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch user data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // User not found in database
        session_destroy();
        header('Location: ' . SITE_URL . 'auth/login.php');
        exit();
    }
    
    // Get order statistics
    $orderStatsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_spent,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders
        FROM orders 
        WHERE user_id = ?
    ");
    $orderStatsStmt->execute([$userId]);
    $orderStats = $orderStatsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent orders
    $recentOrdersStmt = $db->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recentOrdersStmt->execute([$userId]);
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Account page error: " . $e->getMessage());
    $user = [];
    $orderStats = ['total_orders' => 0, 'total_spent' => 0, 'delivered_orders' => 0];
    $recentOrders = [];
}

$pageTitle = "My Account";

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
            <li class="breadcrumb-item active" aria-current="page">My Account</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold mb-2">My Account</h1>
                    <p class="text-muted">
                        Welcome back, <?php echo htmlspecialchars($user['first_name'] ?? 'User'); ?>!
                    </p>
                </div>
                <div>
                    <a href="<?php echo SITE_URL; ?>account/edit.php" class="btn btn-outline-dark">
                        <i class="fas fa-edit me-2"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dashboard Stats -->
    <div class="row mb-5">
        <div class="col-md-3">
            <div class="card border-0 bg-primary text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Orders</h6>
                            <h2 class="fw-bold mb-0"><?php echo $orderStats['total_orders']; ?></h2>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                    </div>
                    <a href="<?php echo SITE_URL; ?>account/orders.php" class="text-white small text-decoration-none d-block mt-3">
                        View all orders <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-success text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Spent</h6>
                            <h2 class="fw-bold mb-0">Ksh <?php echo number_format($orderStats['total_spent'] ?? 0, 2); ?></h2>
                        </div>
                        <i class="fas fa-coins fa-2x opacity-50"></i>
                    </div>
                    <a href="<?php echo SITE_URL; ?>account/orders.php" class="text-white small text-decoration-none d-block mt-3">
                        View spending <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-info text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Delivered</h6>
                            <h2 class="fw-bold mb-0"><?php echo $orderStats['delivered_orders']; ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                    <a href="<?php echo SITE_URL; ?>account/orders.php?status=delivered" class="text-white small text-decoration-none d-block mt-3">
                        View delivered <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-warning text-dark shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Member Since</h6>
                            <h2 class="fw-bold mb-0"><?php echo date('Y', strtotime($user['created_at'])); ?></h2>
                        </div>
                        <i class="fas fa-calendar-alt fa-2x opacity-50"></i>
                    </div>
                    <div class="small mt-3">
                        Joined: <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Left Column - Account Menu -->
        <div class="col-lg-4 mb-4">
            <!-- Profile Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <div class="avatar-circle mx-auto mb-3">
                            <span class="avatar-text">
                                <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1)); ?>
                            </span>
                        </div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                        <div class="d-flex justify-content-center gap-2">
                            <span class="badge bg-dark">Member</span>
                            <?php if ($user['email_verified']): ?>
                                <span class="badge bg-success">Verified</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Unverified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <a href="<?php echo SITE_URL; ?>account/edit.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user me-2"></i> Profile Information
                        </a>
                        <a href="<?php echo SITE_URL; ?>account/addresses.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-map-marker-alt me-2"></i> Addresses
                        </a>
                        <a href="<?php echo SITE_URL; ?>account/orders.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-shopping-cart me-2"></i> My Orders
                        </a>
                        <a href="<?php echo SITE_URL; ?>account/wishlist.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-heart me-2"></i> Wishlist
                        </a>
                        <a href="<?php echo SITE_URL; ?>account/password.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-lock me-2"></i> Change Password
                        </a>
                        <a href="<?php echo SITE_URL; ?>auth/logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-bolt me-2"></i> Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-dark">
                            <i class="fas fa-shopping-bag me-2"></i> Continue Shopping
                        </a>
                        <a href="<?php echo SITE_URL; ?>cart" class="btn btn-outline-dark">
                            <i class="fas fa-shopping-cart me-2"></i> View Cart
                        </a>
                        <a href="<?php echo SITE_URL; ?>account/orders.php?status=pending" class="btn btn-outline-dark">
                            <i class="fas fa-clock me-2"></i> Pending Orders
                        </a>
                        <a href="<?php echo SITE_URL; ?>help/support" class="btn btn-outline-dark">
                            <i class="fas fa-headset me-2"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Account Content -->
        <div class="col-lg-8">
            <!-- Recent Orders -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">
                            <i class="fas fa-history me-2"></i> Recent Orders
                        </h6>
                        <a href="<?php echo SITE_URL; ?>account/orders.php" class="btn btn-sm btn-outline-dark">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recentOrders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Order</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th class="pe-4"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($order['order_number']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td>
                                                <?php echo $order['item_count']; ?> item<?php echo $order['item_count'] !== 1 ? 's' : ''; ?>
                                            </td>
                                            <td>
                                                <span class="fw-bold">Ksh <?php echo number_format($order['total_amount'], 2); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $order['status'] == 'delivered' ? 'success' : 
                                                         ($order['status'] == 'shipped' ? 'primary' : 
                                                         ($order['status'] == 'processing' ? 'info' : 'warning')); 
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td class="pe-4">
                                                <a href="<?php echo SITE_URL; ?>orders/confirmation.php?order_id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-sm btn-outline-dark">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <h6 class="fw-bold mb-2">No orders yet</h6>
                            <p class="text-muted small mb-0">You haven't placed any orders</p>
                            <a href="<?php echo SITE_URL; ?>products" class="btn btn-dark btn-sm mt-3">
                                Start Shopping
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Account Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-info-circle me-2"></i> Account Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">Full Name</label>
                                <p class="mb-0 fw-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">Email Address</label>
                                <p class="mb-0 fw-bold"><?php echo htmlspecialchars($user['email']); ?></p>
                                <?php if (!$user['email_verified']): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-exclamation-circle me-1"></i> Email not verified
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">Phone Number</label>
                                <p class="mb-0 fw-bold">
                                    <?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '<span class="text-muted">Not set</span>'; ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">Address</label>
                                <p class="mb-0">
                                    <?php echo $user['address'] ? nl2br(htmlspecialchars($user['address'])) : '<span class="text-muted">Not set</span>'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Support Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h6 class="fw-bold mb-3">
                        <i class="fas fa-headset me-2"></i> Need Help?
                    </h6>
                    <p class="text-muted small mb-4">
                        Having issues with your account or orders? Our support team is here to help.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="mailto:support@linencloset.com" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-envelope me-2"></i> Email Support
                        </a>
                        <a href="tel:+254700000000" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-phone me-2"></i> Call Support
                        </a>
                        <a href="<?php echo SITE_URL; ?>help/faq" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-question-circle me-2"></i> FAQ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-text {
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
}

.list-group-item {
    border: none;
    padding: 0.75rem 1rem;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.list-group-item i {
    width: 20px;
    text-align: center;
}

.card {
    border-radius: 10px;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add active class to current page link
    const currentPage = window.location.pathname;
    const menuLinks = document.querySelectorAll('.list-group-item');
    
    menuLinks.forEach(link => {
        if (link.href.includes(currentPage)) {
            link.classList.add('active');
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>