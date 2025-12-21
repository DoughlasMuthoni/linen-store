<?php
// /linen-closet/admin/notifications.php
require_once __DIR__ . '/layout.php';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/Notification.php';

$app = new App();
$db = $app->getDB();
$notification = new Notification($db);
// Helper function to generate proper notification links
function generateNotificationLink($notification) {
    global $SITE_URL; // Make sure SITE_URL is accessible
    
    $link = '';
    $title = 'View';
    
    if ($notification['type'] == 'order') {
        // For order notifications, always point to orders.php
        if (!empty($notification['order_id'])) {
            $link = SITE_URL . 'admin/orders.php?id=' . $notification['order_id'];
            $title = 'View Order #' . $notification['order_id'];
        } else {
            // Try to extract order number from message
            preg_match('/#(\w+)/', $notification['message'], $matches);
            if ($matches) {
                $link = SITE_URL . 'admin/orders.php?search=' . urlencode($matches[1]);
                $title = 'View Order ' . $matches[1];
            } else {
                // Default orders page
                $link = SITE_URL . 'admin/orders.php';
                $title = 'View Orders';
            }
        }
    } elseif (!empty($notification['link'])) {
        $link = SITE_URL . ltrim($notification['link'], '/');
        $title = 'View';
    }
    
    return $link ? 
        '<a href="' . $link . '" class="btn btn-outline-primary" title="' . htmlspecialchars($title) . '">
            <i class="fas fa-eye"></i>
        </a>' : '';
}
// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_all_read':
                // REMOVED: user_id parameter
                $notification->markAllAsRead();
                $app->setFlashMessage('success', 'All notifications marked as read!');
                break;
                
            case 'delete':
                if (isset($_POST['id'])) {
                    // REMOVED: user_id parameter
                    $notification->delete($_POST['id']);
                    $app->setFlashMessage('success', 'Notification deleted!');
                }
                break;
                
            case 'clear_all':
                // Delete all read notifications (for ALL users)
                $stmt = $db->prepare("DELETE FROM notifications WHERE is_read = 1");
                $stmt->execute();
                $app->setFlashMessage('success', 'All read notifications cleared!');
                break;
        }
    }
}

// Get all notifications (no user_id parameter)
$notifications = $notification->getNotifications(100);
$unread_count = $notification->getUnreadCount();

// Get notification statistics for ALL notifications
$stmt = $db->prepare("
    SELECT 
        type,
        COUNT(*) as count,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count
    FROM notifications 
    GROUP BY type
    ORDER BY count DESC
");
$stmt->execute();
$type_stats = $stmt->fetchAll();

// Prepare type stats array for easy access
$type_counts = [];
foreach ($type_stats as $stat) {
    $type_counts[$stat['type']] = [
        'total' => $stat['count'],
        'unread' => $stat['unread_count']
    ];
}

$content = '
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4>Notifications</h4>
                <div>
                    <form method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to clear ALL read notifications?\')">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="btn btn-outline-danger btn-sm me-2">
                            <i class="fas fa-trash me-1"></i>Clear All Read
                        </button>
                    </form>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-check-double me-1"></i>Mark All as Read
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Notifications</h6>
                            <h3 class="mb-0">' . count($notifications) . '</h3>
                        </div>
                        <i class="fas fa-bell fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Unread</h6>
                            <h3 class="mb-0">' . $unread_count . '</h3>
                        </div>
                        <i class="fas fa-exclamation-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Orders</h6>
                            <h3 class="mb-0">' . ($type_counts['order']['total'] ?? 0) . '</h3>
                            <small>(' . ($type_counts['order']['unread'] ?? 0) . ' unread)</small>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Customers</h6>
                            <h3 class="mb-0">' . ($type_counts['user']['total'] ?? 0) . '</h3>
                            <small>(' . ($type_counts['user']['unread'] ?? 0) . ' unread)</small>
                        </div>
                        <i class="fas fa-users fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional stats row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Stock Alerts</h6>
                            <h3 class="mb-0">' . ($type_counts['stock']['total'] ?? 0) . '</h3>
                            <small>(' . ($type_counts['stock']['unread'] ?? 0) . ' unread)</small>
                        </div>
                        <i class="fas fa-boxes fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Payments</h6>
                            <h3 class="mb-0">' . ($type_counts['payment']['total'] ?? 0) . '</h3>
                            <small>(' . ($type_counts['payment']['unread'] ?? 0) . ' unread)</small>
                        </div>
                        <i class="fas fa-credit-card fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">System</h6>
                            <h3 class="mb-0">' . ($type_counts['system']['total'] ?? 0) . '</h3>
                            <small>(' . ($type_counts['system']['unread'] ?? 0) . ' unread)</small>
                        </div>
                        <i class="fas fa-cog fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
    <div class="card bg-purple text-white">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Reviews</h6>
                        <h3 class="mb-0">' . ($type_counts['review']['total'] ?? 0) . '</h3>
                        <small>(' . ($type_counts['review']['unread'] ?? 0) . ' unread)</small>
                    </div>
                    <i class="fas fa-star fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    </div>
    
    <!-- Notifications List -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">All Notifications (' . count($notifications) . ')</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50"></th>
                            <th>Notification</th>
                            <th>Type</th>
                            <th>User</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
                    
if (empty($notifications)) {
    $content .= '
        <tr>
            <td colspan="7" class="text-center py-4 text-muted">
                <i class="fas fa-bell-slash fa-2x mb-2"></i>
                <h5 class="mb-2">No notifications found</h5>
                <p class="mb-0">When notifications are created, they will appear here.</p>
            </td>
        </tr>';
} else {
    foreach ($notifications as $notif) {
        $icon = Notification::getIcon($notif['type']);
        $color = Notification::getColor($notif['type']);
        $time_ago = Notification::timeAgo($notif['created_at']);
        
        // Get user info if available
        $user_name = 'System';
        if ($notif['user_id'] > 0) {
            $user_stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $user_stmt->execute([$notif['user_id']]);
            $user = $user_stmt->fetch();
            if ($user) {
                $user_name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
            }
        }
        
        $content .= '
        <tr class="' . ($notif['is_read'] ? '' : 'table-active') . '">
            <td>
                <span class="badge bg-' . $color . ' p-2">
                    <i class="' . $icon . '"></i>
                </span>
            </td>
            <td>
                <h6 class="mb-1">' . htmlspecialchars($notif['title']) . '</h6>
                <p class="mb-0 small text-muted">' . htmlspecialchars($notif['message']) . '</p>
            </td>
            <td>
                <span class="badge bg-' . $color . '">' . ucfirst($notif['type']) . '</span>
            </td>
            <td>
                <small>' . $user_name . '</small>
                <br>
                <small class="text-muted">ID: ' . $notif['user_id'] . '</small>
            </td>
            <td>
                <small class="text-muted">' . date('M j, Y g:i A', strtotime($notif['created_at'])) . '</small>
                <br>
                <small>' . $time_ago . '</small>
            </td>
            <td>
                ' . ($notif['is_read'] ? 
                    '<span class="badge bg-success">Read</span>' : 
                    '<span class="badge bg-warning">Unread</span>') . '
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    ' . generateNotificationLink($notif) . '
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="' . $notif['id'] . '">
                        <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm(\'Delete this notification?\')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </td>
        </tr>';
    }
}

$content .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.notification-item:hover {
    background-color: #f8f9fa;
}
.table-active {
    background-color: rgba(0,123,255,0.05);
}
.bg-purple {
    background-color: #6f42c1 !important;
}
</style>';

echo adminLayout($content, 'Notifications');