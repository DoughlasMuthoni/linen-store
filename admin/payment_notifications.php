<?php
// /linen-closet/admin/payment_notifications.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}

$pageTitle = "Payment Notifications";

// Mark as read if requested
if (isset($_GET['mark_read']) && $_GET['mark_read'] == 'all') {
    $updateStmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE type = 'payment' AND is_read = 0");
    $updateStmt->execute();
    header('Location: payment_notifications.php');
    exit();
}

// Fetch payment notifications
$stmt = $db->prepare("
    SELECT n.*, u.email as user_email 
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.type = 'payment'
    ORDER BY n.created_at DESC
    LIMIT 100
");
$stmt->execute();
$notifications = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>admin/dashboard.php" class="text-decoration-none">
                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Payment Notifications</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold mb-2">Payment Notifications</h1>
                    <p class="text-muted">Monitor payment activities and alerts</p>
                </div>
                <div>
                    <a href="?mark_read=all" class="btn btn-outline-dark">
                        <i class="fas fa-check-double me-2"></i> Mark All as Read
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifications List -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (!empty($notifications)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <a href="<?php echo SITE_URL . $notification['link']; ?>" 
                           class="list-group-item list-group-item-action border-0 py-3 px-4 <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="me-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <?php if (strpos($notification['title'], 'Failed') !== false): ?>
                                            <i class="fas fa-times-circle text-danger me-2"></i>
                                        <?php elseif (strpos($notification['title'], 'Received') !== false || strpos($notification['title'], 'Paid') !== false): ?>
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle text-info me-2"></i>
                                        <?php endif; ?>
                                        
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                        
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary ms-2">New</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    
                                    <small class="text-muted">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        <?php if ($notification['user_email']): ?>
                                            • User: <?php echo htmlspecialchars($notification['user_email']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No payment notifications</h5>
                    <p class="text-muted">Payment notifications will appear here when they occur</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>