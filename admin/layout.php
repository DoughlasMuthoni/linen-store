<?php
// /linen-closet/admin/layout.php

// This file will be included in all admin pages
function adminLayout($content, $pageTitle = 'Dashboard') {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/Database.php';
    require_once __DIR__ . '/../includes/App.php';
    
    $app = new App();
    
    // Check if user is admin
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        $app->redirect('admin/login');
    }
    
    // Get admin stats for sidebar
    $db = $app->getDB();
    
    $stats = [
        'total_orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'total_products' => $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn(),
        'total_customers' => $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn(),
        'total_revenue' => $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn(),
    ];
    
    // Get recent orders
    $recentOrders = $db->query("
        SELECT o.*, u.first_name, u.last_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ")->fetchAll();
    
    $currentPage = basename($_SERVER['PHP_SELF']);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?> Admin</title>
        
        <!-- Bootstrap 5 -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
        <!-- DataTables -->
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
        
        <!-- Select2 -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        
        <!-- Admin Custom CSS -->
        <link href="<?php echo SITE_URL; ?>assets/css/admin.css" rel="stylesheet">
        <!-- Sortable.js for drag and drop -->
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
        <!-- Custom styles for this page -->
        <style>
            :root {
                --sidebar-width: 250px;
                --sidebar-bg: #1a1a1a;
                --sidebar-color: #ffffff;
                --header-height: 70px;
                --primary-color: #667eea;
            }
            
            body {
                background-color: #f8f9fa;
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            }
        </style>
    </head>
    <body>
       <!-- Sidebar -->
<div class="admin-sidebar d-flex flex-column" style="width: var(--sidebar-width); background: var(--sidebar-bg); color: var(--sidebar-color); height: 100vh; position: fixed; left: 0; top: 0;">
    <!-- Logo -->
    <div class="sidebar-header p-4 border-bottom flex-shrink-0">
        <h4 class="mb-0">
            <i class="fas fa-tshirt me-2"></i>
            <span class="fw-bold"><?php echo SITE_NAME; ?></span>
            <small class="d-block text-muted mt-1">Admin Panel</small>
        </h4>
    </div>
    
    <!-- User Info -->
    <div class="sidebar-user p-3 border-bottom flex-shrink-0">
        <div class="d-flex align-items-center">
            <div class="user-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                 style="width: 40px; height: 40px;">
                <?php 
                $initials = substr($_SESSION['first_name'] ?? '', 0, 1) . substr($_SESSION['last_name'] ?? '', 0, 1);
                echo strtoupper($initials); 
                ?>
            </div>
            <div>
                <h6 class="mb-0"><?php echo $_SESSION['first_name']; ?></h6>
                <small class="text-muted">Administrator</small>
            </div>
        </div>
    </div>
    
    <!-- Scrollable Navigation -->
    <nav class="sidebar-nav flex-grow-1 overflow-auto" style="min-height: 0;">
        <div class="p-3">
            <ul class="nav flex-column">
                <!-- Dashboard -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/dashboard">
                        <i class="fas fa-tachometer-alt me-3"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <!-- Catalog Management -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center <?php echo in_array($currentPage, ['products.php', 'product-add.php', 'product-edit.php']) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/products">
                        <i class="fas fa-box me-3"></i>
                        <span>Products</span>
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center <?php echo in_array($currentPage, ['categories.php']) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/categories">
                        <i class="fas fa-tags me-3"></i>
                        <span>Categories</span>
                    </a>
                </li>
                
                <!-- Sales & Customers -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center <?php echo in_array($currentPage, ['orders.php', 'order-view.php']) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/orders">
                        <i class="fas fa-shopping-cart me-3"></i>
                        <span>Orders</span>
                        <?php if (isset($stats['total_orders']) && $stats['total_orders'] > 0): ?>
                        <span class="badge bg-danger ms-auto"><?php echo $stats['total_orders']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center <?php echo in_array($currentPage, ['customers.php']) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/customers">
                        <i class="fas fa-users me-3"></i>
                        <span>Customers</span>
                    </a>
                </li>
                
                <!-- Analytics & Reports -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center <?php echo in_array($currentPage, ['reports.php']) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/reports">
                        <i class="fas fa-chart-bar me-3"></i>
                        <span>Reports</span>
                    </a>
                </li>
                
                <!-- Notifications -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center <?php echo $currentPage === 'notifications.php' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/notifications">
                        <i class="fas fa-bell me-3"></i>
                        <span>Notifications</span>
                    </a>
                </li>
                
                <!-- Settings -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center <?php echo in_array($currentPage, ['settings.php']) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/settings">
                        <i class="fas fa-cog me-3"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- Logout Button (Always at bottom) -->
    <div class="p-3 border-top flex-shrink-0">
        <a href="<?php echo SITE_URL; ?>admin/logout" class="btn btn-outline-light w-100">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</div>

<!-- Custom CSS for scrollbar styling -->
<style>
/* Custom scrollbar for sidebar */
.sidebar-nav::-webkit-scrollbar {
    width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Firefox scrollbar */
.sidebar-nav {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) rgba(255, 255, 255, 0.1);
}

/* Ensure smooth scrolling */
.sidebar-nav {
    -webkit-overflow-scrolling: touch;
}

/* Prevent content jump when scrollbar appears */
.sidebar-nav {
    overflow-y: auto;
    overflow-x: hidden;
}

/* Adjust padding for better spacing */
.sidebar-nav > div {
    padding-bottom: 20px;
}

/* Make sure logout button stays at bottom */
.admin-sidebar {
    display: flex;
    flex-direction: column;
}
</style>
        
        <!-- Main Content -->
        <div class="admin-main" style="margin-left: var(--sidebar-width);">
            <!-- Top Header -->
        <header class="admin-header bg-white shadow-sm border-bottom" style="height: var(--header-height);">
    <div class="container-fluid h-100">
        <div class="row align-items-center h-100">
            <!-- Page Title -->
            <div class="col">
                <h5 class="mb-0 fw-bold"><?php echo $pageTitle; ?></h5>
            </div>
            
            <!-- Header Actions -->
            <div class="col-auto">
                <div class="d-flex align-items-center">
                    
                    <?php
                    // Initialize notifications - more robust approach
                    $notifications = [];
                    $unread_count = 0;
                    
                    // Check if Notification file exists
                    $notificationFile = __DIR__ . '/../includes/Notification.php';
                    
                    if (file_exists($notificationFile)) {
                        require_once $notificationFile;
                        
                       // In layout.php
                        if (class_exists('Notification')) {
                            try {
                                $notification = new Notification($db);
                                
                                // Get ALL notifications (no user_id parameter needed)
                                $notifications = $notification->getNotifications(5);
                                $unread_count = $notification->getUnreadCount();
                                
                                error_log("Total notifications: " . count($notifications));
                                error_log("Unread count: " . $unread_count);
                                
                            } catch (Exception $e) {
                                error_log("Notification initialization error: " . $e->getMessage());
                            }
                        }
                    }
                    ?>
                    
                    <!-- Notifications Dropdown - ALWAYS show badge -->
                    <div class="dropdown me-3 notification-wrapper" id="notificationDropdown">
                        <button class="btn btn-link text-dark p-0 position-relative" 
                                type="button" 
                                data-bs-toggle="dropdown"
                                id="notificationBell"
                                data-unread="<?php echo $unread_count; ?>"
                                title="Notifications">
                            <i class="fas fa-bell fa-lg"></i>
                            <!-- ALWAYS show badge, even when count is 0 -->
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill <?php echo $unread_count > 0 ? 'bg-danger' : 'bg-secondary'; ?>" 
                                  id="notificationBadge"
                                  style="transform: translate(-50%, -50%); font-size: 0.65rem; padding: 0.2em 0.5em;">
                                <?php echo $unread_count; ?>
                            </span>
                        </button>
                        
                        <div class="dropdown-menu dropdown-menu-end p-0" style="width: 350px;">
                            <!-- Notifications Header -->
                            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                                <h6 class="mb-0">Notifications</h6>
                                <?php if ($unread_count > 0): ?>
                                <button class="btn btn-sm btn-outline-primary" onclick="markAllAsRead()">
                                    <small>Mark all as read</small>
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Notifications List -->
                            <div class="notification-list" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($notifications)): ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-bell-slash fa-2x mb-2"></i>
                                        <p class="mb-0">No notifications</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($notifications as $notif): 
                                        $icon = class_exists('Notification') ? Notification::getIcon($notif['type']) : 'fas fa-bell';
                                        $color = class_exists('Notification') ? Notification::getColor($notif['type']) : 'primary';
                                        $time_ago = class_exists('Notification') ? Notification::timeAgo($notif['created_at']) : date('M j', strtotime($notif['created_at']));
                                    ?>
                                    <a href="<?php echo $notif['link'] ? SITE_URL . ltrim($notif['link'], '/') : 'javascript:void(0)'; ?>" 
                                       class="dropdown-item notification-item border-bottom <?php echo $notif['is_read'] ? 'text-muted' : 'fw-bold'; ?>" 
                                       data-id="<?php echo $notif['id']; ?>"
                                       onclick="markAsRead(<?php echo $notif['id']; ?>)">
                                        <div class="d-flex">
                                            <!-- Notification Icon -->
                                            <div class="me-3">
                                                <span class="badge bg-<?php echo $color; ?> p-2">
                                                    <i class="<?php echo $icon; ?>"></i>
                                                </span>
                                            </div>
                                            
                                            <!-- Notification Content -->
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($notif['title']); ?></h6>
                                                    <small class="text-muted"><?php echo $time_ago; ?></small>
                                                </div>
                                                <p class="mb-0 small text-truncate"><?php echo htmlspecialchars($notif['message']); ?></p>
                                                <?php if (!$notif['is_read']): ?>
                                                <span class="badge bg-danger rounded-pill">New</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- View All Link -->
                            <div class="border-top">
                                <a class="dropdown-item text-center py-2" href="<?php echo SITE_URL; ?>admin/notifications">
                                    <small>View all notifications</small>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current Date -->
                    <span class="text-muted me-3">
                        <i class="fas fa-calendar-alt me-1"></i>
                        <?php echo date('l, F j, Y'); ?>
                    </span>
                    
                    <!-- Current Time -->
                    <span class="text-muted small">
                        <i class="fas fa-clock me-1"></i>
                        <span id="currentTime"><?php echo date('g:i A'); ?></span>
                    </span>
                </div>
            </div>
        </div>
    </div>
        </header>

<style>
/* Fix notification badge positioning */
#notificationBadge {
    position: absolute !important;
    top: 0 !important;
    left: 100% !important;
    transform: translate(-50%, -50%) !important;
    font-size: 0.65rem !important;
    padding: 0.2em 0.5em !important;
    min-width: 18px;
    height: 18px;
    display: flex !important;
    align-items: center;
    justify-content: center;
    border: 2px solid white !important;
    z-index: 1;
}

/* Ensure bell icon has proper positioning context */
#notificationBell {
    position: relative;
    display: inline-block;
}

/* Hover effect for bell icon */
#notificationBell:hover {
    opacity: 0.8;
}

/* Different badge colors */
#notificationBadge.bg-danger {
    background-color: #e61223ff !important;
}

#notificationBadge.bg-secondary {
    background-color: #e41923ff !important;
    opacity: 0.8;
}
</style>

<script>
// Update the JavaScript to handle badge updates
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    const bell = document.getElementById('notificationBell');
    
    if (!badge || !bell) return;
    
    // Update count
    badge.textContent = count;
    bell.dataset.unread = count;
    
    // Update color based on count
    if (count > 0) {
        badge.classList.remove('bg-secondary');
        badge.classList.add('bg-danger');
    } else {
        badge.classList.remove('bg-danger');
        badge.classList.add('bg-secondary');
    }
}

// Update markAsRead function to use the new update function
window.markAsRead = function(notificationId) {
    fetch('<?php echo SITE_URL; ?>admin/ajax/notifications.php?action=mark_read&id=' + notificationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update badge count
                const bell = document.getElementById('notificationBell');
                const currentCount = parseInt(bell?.dataset?.unread) || 0;
                const newCount = Math.max(0, currentCount - 1);
                updateNotificationBadge(newCount);
                
                // Mark item as read visually
                const item = document.querySelector('.notification-item[data-id="' + notificationId + '"]');
                if (item) {
                    item.classList.remove('fw-bold');
                    item.classList.add('text-muted');
                    
                    // Remove "New" badge
                    const newBadge = item.querySelector('.badge.bg-danger');
                    if (newBadge) {
                        newBadge.remove();
                    }
                }
            }
        });
};

window.markAllAsRead = function() {
    fetch('<?php echo SITE_URL; ?>admin/ajax/notifications.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update badge to 0
                updateNotificationBadge(0);
                
                // Mark all items as read visually
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('fw-bold');
                    item.classList.add('text-muted');
                    
                    const newBadge = item.querySelector('.badge.bg-danger');
                    if (newBadge) {
                        newBadge.remove();
                    }
                });
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'All notifications marked as read!',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        });
};

// Check for new notifications periodically
function checkNewNotifications() {
    fetch('<?php echo SITE_URL; ?>admin/ajax/notifications.php?action=check_unread')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.count);
            }
        });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Initial badge setup
    const initialCount = <?php echo $unread_count; ?>;
    updateNotificationBadge(initialCount);
    
    // Auto-refresh every 30 seconds
    setInterval(checkNewNotifications, 30000);
});
</script>
<!-- Script for live time update -->
<script>
// Update time every minute
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    document.getElementById('currentTime').textContent = timeString;
}

// Initial update
updateTime();

// Update every minute
setInterval(updateTime, 60000);
</script>
            
            <!-- Page Content -->
            <main class="admin-content p-4">
                <?php echo $content; ?>
            </main>
        </div>
        
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        
        <!-- DataTables -->
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
        
        <!-- Select2 -->
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
        <!-- SweetAlert -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        
        <!-- Admin Custom JS -->
        <script src="<?php echo SITE_URL; ?>assets/js/admin.js"></script>
        
        <!-- Page-specific scripts -->
        <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('.data-table').DataTable({
                pageLength: 25,
                responsive: true
            });
            
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5'
            });
            
            // Confirm before delete
            $('.confirm-delete').on('click', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This action cannot be undone!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>