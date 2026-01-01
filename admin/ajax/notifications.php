<?php
// /linen-closet/ajax/notifications.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/Notification.php';

$app = new App();
$db = $app->getDB();
$notification = new Notification($db);

// Check if user is logged in
if (!$app->isLoggedIn()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

// Get action from GET or POST
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'check_unread':
            // Get unread count for current user
            $unread_count = $notification->getUnreadCount();
            
            echo json_encode([
                'success' => true,
                'count' => $unread_count,
                'message' => 'Unread notifications retrieved'
            ]);
            break;
            
        case 'mark_read':
            $id = $_GET['id'] ?? ($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('Notification ID is required');
            }
            
            // Mark specific notification as read
            $stmt = $db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read',
                'notification_id' => $id
            ]);
            break;
            
        case 'mark_all_read':
            // Mark all notifications as read for current user
            $stmt = $db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE is_read = 0
            ");
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
            break;
            
        case 'get_notifications':
            // Get recent notifications for dropdown
            $limit = $_GET['limit'] ?? 10;
            $notifications = $notification->getNotifications($limit);
            
            // Format for JSON response
            $formatted = [];
            foreach ($notifications as $notif) {
                $formatted[] = [
                    'id' => $notif['id'],
                    'title' => $notif['title'],
                    'message' => $notif['message'],
                    'type' => $notif['type'],
                    'is_read' => (bool)$notif['is_read'],
                    'time_ago' => $notification->timeAgo($notif['created_at']),
                    'icon' => $notification->getIcon($notif['type']),
                    'color' => $notification->getColor($notif['type']),
                    'link' => !empty($notif['link']) ? SITE_URL . ltrim($notif['link'], '/') : null
                ];
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $formatted,
                'count' => count($formatted)
            ]);
            break;
            
        case 'create_test':
            // For testing only - create a test notification
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $title = $_POST['title'] ?? 'Test Notification';
            $message = $_POST['message'] ?? 'This is a test notification';
            $type = $_POST['type'] ?? 'system';
            
            $test_id = $notification->create($title, $message, $type, null, null, '/admin/notifications.php');
            
            echo json_encode([
                'success' => true,
                'message' => 'Test notification created',
                'notification_id' => $test_id
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}