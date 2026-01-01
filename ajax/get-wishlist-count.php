<?php
// /linen-closet/ajax/get-wishlist-count.php

// Turn off error display
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json');

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $count = 0;
    
    // Include files if they exist
    $configPath = __DIR__ . '/../includes/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
        
        $dbPath = __DIR__ . '/../includes/Database.php';
        $wishlistPath = __DIR__ . '/../includes/Wishlist.php';
        
        if (file_exists($dbPath) && file_exists($wishlistPath)) {
            require_once $dbPath;
            require_once $wishlistPath;
            
            $userId = $_SESSION['user_id'] ?? 0;
            $db = Database::getInstance()->getConnection();
            $wishlist = new Wishlist($db, $userId);
            
            $count = $wishlist->getCount();
        }
    }
    
    // Fallback to session count
    if ($count === 0) {
        $count = count($_SESSION['wishlist'] ?? []);
    }
    
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'count' => count($_SESSION['wishlist'] ?? [])
    ]);
}

exit;