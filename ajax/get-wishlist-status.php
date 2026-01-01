<?php
// /linen-closet/ajax/get-wishlist-status.php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json');

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $wishlistItems = $_SESSION['wishlist'] ?? [];
    $wishlistCount = count($wishlistItems);
    
    // Try to get from database if user is logged in
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $configPath = __DIR__ . '/../includes/config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
            
            $dbPath = __DIR__ . '/../includes/Database.php';
            $wishlistPath = __DIR__ . '/../includes/Wishlist.php';
            
            if (file_exists($dbPath) && file_exists($wishlistPath)) {
                require_once $dbPath;
                require_once $wishlistPath;
                
                $userId = $_SESSION['user_id'];
                $db = Database::getInstance()->getConnection();
                $wishlist = new Wishlist($db, $userId);
                
                $wishlistItems = $wishlist->getProductIds();
                $wishlistCount = $wishlist->getCount();
            }
        }
    }
    
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'wishlist_items' => $wishlistItems,
        'count' => $wishlistCount
    ]);
    
} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'wishlist_items' => $_SESSION['wishlist'] ?? [],
        'count' => count($_SESSION['wishlist'] ?? [])
    ]);
}

exit;