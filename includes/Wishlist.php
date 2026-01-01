<?php
// /linen-closet/includes/Wishlist.php

class Wishlist {
    private $db;
    private $userId;
    
    public function __construct($db, $userId = null) {
        $this->db = $db;
        $this->userId = $userId;
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Initialize session wishlist
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
    }
    
    /**
     * Add item to wishlist
     */
    public function addItem($productId) {
        $productId = (int)$productId;
        
        // Add to session
        if (!in_array($productId, $_SESSION['wishlist'])) {
            $_SESSION['wishlist'][] = $productId;
            $_SESSION['wishlist'] = array_values(array_unique($_SESSION['wishlist']));
        }
        
        // Add to database if user is logged in
        if ($this->userId) {
            return $this->addToDb($productId);
        }
        
        return true;
    }
    
    /**
     * Remove item from wishlist
     */
    public function removeItem($productId) {
        $productId = (int)$productId;
        
        // Remove from session
        $key = array_search($productId, $_SESSION['wishlist']);
        if ($key !== false) {
            unset($_SESSION['wishlist'][$key]);
            $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
        }
        
        // Remove from database if user is logged in
        if ($this->userId) {
            return $this->removeFromDb($productId);
        }
        
        return true;
    }
    
    /**
     * Clear wishlist
     */
    public function clearWishlist() {
        // Clear session
        $_SESSION['wishlist'] = [];
        
        // Clear database if user is logged in
        if ($this->userId) {
            return $this->clearDb();
        }
        
        return true;
    }
    
    /**
     * Add to database
     */
    private function addToDb($productId) {
        try {
            // Check if exists
            $stmt = $this->db->prepare("
                SELECT id FROM user_wishlists 
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->execute([$this->userId, $productId]);
            
            if ($stmt->fetch()) {
                // Update to active
                $stmt = $this->db->prepare("
                    UPDATE user_wishlists 
                    SET is_active = 1, updated_at = NOW() 
                    WHERE user_id = ? AND product_id = ?
                ");
            } else {
                // Insert new
                $stmt = $this->db->prepare("
                    INSERT INTO user_wishlists (user_id, product_id, created_at, updated_at) 
                    VALUES (?, ?, NOW(), NOW())
                ");
            }
            
            return $stmt->execute([$this->userId, $productId]);
            
        } catch (Exception $e) {
            error_log("Wishlist add error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove from database (soft delete)
     */
    private function removeFromDb($productId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_wishlists 
                SET is_active = 0, updated_at = NOW() 
                WHERE user_id = ? AND product_id = ?
            ");
            return $stmt->execute([$this->userId, $productId]);
            
        } catch (Exception $e) {
            error_log("Wishlist remove error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear database (soft delete all)
     */
    private function clearDb() {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_wishlists 
                SET is_active = 0, updated_at = NOW() 
                WHERE user_id = ?
            ");
            return $stmt->execute([$this->userId]);
            
        } catch (Exception $e) {
            error_log("Wishlist clear error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get wishlist items
     */
    public function getItems() {
        $items = [];
        
        // Get from database if user is logged in
        if ($this->userId) {
            try {
                $stmt = $this->db->prepare("
                    SELECT product_id 
                    FROM user_wishlists 
                    WHERE user_id = ? AND is_active = 1
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$this->userId]);
                $dbItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Update session with database items
                $_SESSION['wishlist'] = $dbItems;
                $items = $dbItems;
                
            } catch (Exception $e) {
                error_log("Wishlist get items error: " . $e->getMessage());
                // Fallback to session
                $items = $_SESSION['wishlist'] ?? [];
            }
        } else {
            // Use session for non-logged in users
            $items = $_SESSION['wishlist'] ?? [];
        }
        
        // If no items, return empty array
        if (empty($items)) {
            return [];
        }
        
        // Get product details
        $placeholders = str_repeat('?,', count($items) - 1) . '?';
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    c.name as category_name,
                    c.slug as category_slug,
                    (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
                    COALESCE(p.price, 0) as display_price
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id IN ($placeholders) AND p.is_active = 1
                ORDER BY FIELD(p.id, " . implode(',', $items) . ")
            ");
            $stmt->execute($items);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Wishlist products error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get wishlist count
     */
    public function getCount() {
        if ($this->userId) {
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) 
                    FROM user_wishlists 
                    WHERE user_id = ? AND is_active = 1
                ");
                $stmt->execute([$this->userId]);
                return (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                error_log("Wishlist count error: " . $e->getMessage());
            }
        }
        
        // Fallback to session count
        return count($_SESSION['wishlist'] ?? []);
    }
    
    /**
     * Check if product is in wishlist
     */
    public function isInWishlist($productId) {
        $productId = (int)$productId;
        
        if ($this->userId) {
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) 
                    FROM user_wishlists 
                    WHERE user_id = ? AND product_id = ? AND is_active = 1
                ");
                $stmt->execute([$this->userId, $productId]);
                return (int)$stmt->fetchColumn() > 0;
            } catch (Exception $e) {
                error_log("Wishlist check error: " . $e->getMessage());
            }
        }
        
        // Fallback to session check
        return in_array($productId, $_SESSION['wishlist'] ?? []);
    }
}
?>