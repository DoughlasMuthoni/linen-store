<?php
// /linen-closet/includes/Notification.php

class Notification {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Create a new notification
    public function create($user_id, $type, $title, $message, $link = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, link) 
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$user_id, $type, $title, $message, $link]);
        } catch (Exception $e) {
            error_log("Notification create error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get notifications for ALL admins (shows all notifications regardless of user_id)
    public function getNotifications($limit = 10, $unread_only = false) {
        try {
            $sql = "SELECT * FROM notifications WHERE 1=1";
            
            if ($unread_only) {
                $sql .= " AND is_read = 0";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Notification get error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get unread count for ALL admins (counts all unread notifications)
    public function getUnreadCount() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE is_read = 0
            ");
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Notification count error: " . $e->getMessage());
            return 0;
        }
    }
    
    // Mark as read (admin can mark any notification as read)
    public function markAsRead($notification_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications SET is_read = 1 
                WHERE id = ?
            ");
            return $stmt->execute([$notification_id]);
        } catch (Exception $e) {
            error_log("Notification mark as read error: " . $e->getMessage());
            return false;
        }
    }
    
    // Mark all as read (admin can mark ALL notifications as read)
    public function markAllAsRead() {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications SET is_read = 1 
                WHERE is_read = 0
            ");
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Notification mark all as read error: " . $e->getMessage());
            return false;
        }
    }
    
    // Delete notification (admin can delete any notification)
    public function delete($notification_id) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications WHERE id = ?
            ");
            return $stmt->execute([$notification_id]);
        } catch (Exception $e) {
            error_log("Notification delete error: " . $e->getMessage());
            return false;
        }
    }
    
    // Delete old notifications (older than 30 days)
    public function cleanupOldNotifications($days = 30) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            return $stmt->execute([$days]);
        } catch (Exception $e) {
            error_log("Notification cleanup error: " . $e->getMessage());
            return false;
        }
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
            'review' => 'fas fa-star',
            'default' => 'fas fa-bell'
        ];
        return $icons[$type] ?? $icons['default'];
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
            'review' => 'warning',
            'default' => 'secondary'
        ];
        return $colors[$type] ?? $colors['default'];
    }
    
    // Get time ago format
    public static function timeAgo($timestamp) {
        try {
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
        } catch (Exception $e) {
            return 'Recently';
        }
    }
}
?>