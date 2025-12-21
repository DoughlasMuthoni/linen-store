<?php
// /linen-closet/includes/NotificationHelper.php

class NotificationHelper {
    
    /**
     * Create a notification directly in the database
     */
    public static function create($db, $userId, $type, $title, $message, $link = null) {
        try {
            $stmt = $db->prepare("
                INSERT INTO notifications 
                (user_id, type, title, message, link, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            
            $result = $stmt->execute([$userId, $type, $title, $message, $link]);
            
            if ($result) {
                error_log("Notification created: $type - $title");
                return true;
            } else {
                error_log("Failed to create notification: " . print_r($stmt->errorInfo(), true));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("NotificationHelper create error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create system notification for all admins
     */
    public static function createSystemNotification($db, $title, $message, $link = null, $type = 'system') {
        try {
            // Get all admin users
            $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' OR is_admin = 1");
            $adminStmt->execute();
            $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($admins)) {
                error_log("No admin users found for system notification");
                return 0;
            }
            
            $count = 0;
            
            foreach ($admins as $adminId) {
                if (self::create($db, $adminId, $type, $title, $message, $link)) {
                    $count++;
                }
            }
            
            error_log("Created $count system notifications for admins");
            return $count;
            
        } catch (Exception $e) {
            error_log("System notification error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create payment notification (as 'payment' type)
     */
    public static function createPaymentNotification($db, $orderId, $orderNumber, $paymentStatus, $paymentMethod, $amount) {
        try {
            error_log("Creating payment notification for Order #$orderNumber");
            
            $title = '';
            $message = '';
            
            switch ($paymentStatus) {
                case 'paid':
                    $title = 'Payment Received';
                    $message = "Payment of Ksh " . number_format($amount, 2) . " received for Order #$orderNumber";
                    break;
                    
                case 'failed':
                    $title = 'Payment Failed';
                    $message = "Payment for Order #$orderNumber failed. Method: " . ucfirst($paymentMethod);
                    break;
                    
                case 'pending':
                    $title = 'Payment Pending';
                    $message = "Payment pending for Order #$orderNumber. Amount: Ksh " . number_format($amount, 2);
                    break;
                    
                default:
                    $title = 'Payment Update';
                    $message = "Payment status updated for Order #$orderNumber to: " . ucfirst($paymentStatus);
                    break;
            }
            
            $link = "/admin/orders/view.php?id=$orderId";
            
            // Create as 'payment' type (not 'system')
            $result = self::createSystemNotification($db, $title, $message, $link, 'payment');
            
            error_log("Payment notification result: $result notifications created");
            return $result;
            
        } catch (Exception $e) {
            error_log("Payment notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create user profile update notification
     */
    public static function createUserProfileNotification($db, $userId, $userEmail, $changes = []) {
        try {
            $title = 'User Profile Updated';
            
            $message = "User $userEmail updated their profile";
            if (!empty($changes)) {
                $changeList = implode(', ', $changes);
                $message .= " ($changeList)";
            }
            
            $link = "/admin/users/edit.php?id=$userId";
            
            return self::createSystemNotification($db, $title, $message, $link, 'system');
            
        } catch (Exception $e) {
            error_log("User profile notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create stock notification
     */
    public static function createStockNotification($db, $productId, $productName, $currentStock, $minStock) {
        try {
            error_log("Checking stock notification for: $productName");
            
            if ($currentStock <= 0) {
                $title = 'Out of Stock Alert';
                $message = "$productName is out of stock";
            } elseif ($currentStock <= $minStock) {
                $title = 'Low Stock Alert';
                $message = "$productName has low stock ($currentStock left, min: $minStock)";
            } else {
                error_log("No stock notification needed for $productName (stock: $currentStock, min: $minStock)");
                return false; // No notification needed
            }
            
            $link = "/admin/products/edit.php?id=$productId";
            
            error_log("Creating stock notification: $title - $message");
            return self::createSystemNotification($db, $title, $message, $link, 'stock');
            
        } catch (Exception $e) {
            error_log("Stock notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create order notification
     */
    public static function createOrderNotification($db, $orderId, $orderNumber, $customerName) {
        try {
            error_log("Creating order notification for Order #$orderNumber");
            
            $title = 'New Order Received';
            $message = "Order #$orderNumber placed by $customerName";
            $link = "/admin/orders.php?order_id=$orderId";
            
            $result = self::createSystemNotification($db, $title, $message, $link, 'order');
            error_log("Order notification result: $result notifications created");
            return $result;
            
        } catch (Exception $e) {
            error_log("Order notification error: " . $e->getMessage());
            return false;
        }
    }
}
?>