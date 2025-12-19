<?php
// /linen-closet/includes/Notification.php
class Notification {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Create a new notification
    public function create($user_id, $type, $title, $message, $link = null) {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, link) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $type, $title, $message, $link]);
    }
    
    // Get notifications for a user (user_id = 0 for admin)
    public function getNotifications($user_id, $limit = 10, $unread_only = false) {
        $sql = "
            SELECT * FROM notifications 
            WHERE user_id = ? 
        ";
        
        if ($unread_only) {
            $sql .= " AND is_read = 0 ";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    }
    
    // Get unread count
    public function getUnreadCount($user_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }
    
    // Mark as read
    public function markAsRead($notification_id, $user_id = null) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $params = [$notification_id];
        
        if ($user_id !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    // Mark all as read
    public function markAllAsRead($user_id) {
        $stmt = $this->db->prepare("
            UPDATE notifications SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        return $stmt->execute([$user_id]);
    }
    
    // Delete notification
    public function delete($notification_id, $user_id = null) {
        $sql = "DELETE FROM notifications WHERE id = ?";
        $params = [$notification_id];
        
        if ($user_id !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    // Delete old notifications (older than 30 days)
    public function cleanupOldNotifications($days = 30) {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        return $stmt->execute([$days]);
    }
    
    // Get notification icon based on type
    public static function getIcon($type) {
        $icons = [
            'order' => 'fas fa-shopping-cart',
            'stock' => 'fas fa-exclamation-triangle',
            'user' => 'fas fa-user-plus',
            'system' => 'fas fa-cog',
            'payment' => 'fas fa-credit-card',
            'shipping' => 'fas fa-truck',
            'review' => 'fas fa-star'
        ];
        return $icons[$type] ?? 'fas fa-bell';
    }
    
    // Get notification color based on type
    public static function getColor($type) {
        $colors = [
            'order' => 'primary',
            'stock' => 'warning',
            'user' => 'success',
            'system' => 'info',
            'payment' => 'success',
            'shipping' => 'info',
            'review' => 'warning'
        ];
        return $colors[$type] ?? 'secondary';
    }
    
    // Get time ago format
    public static function timeAgo($timestamp) {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
}
?>