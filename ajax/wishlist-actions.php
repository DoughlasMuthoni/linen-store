<?php
// /linen-closet/ajax/wishlist-actions.php

// Turn off error display
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        throw new Exception('Invalid request');
    }
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input && !empty($_POST)) {
        $input = $_POST;
    }
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('No action specified');
    }
    
    $action = $input['action'];
    
    // Include files
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/Database.php';
    require_once __DIR__ . '/../includes/Wishlist.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        throw new Exception('Please login to manage wishlist');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Initialize database and wishlist
    $db = Database::getInstance()->getConnection();
    $wishlist = new Wishlist($db, $userId);
    
    if ($action === 'remove' && isset($input['product_id'])) {
        $productId = (int)$input['product_id'];
        
        if ($productId <= 0) {
            throw new Exception('Invalid product ID');
        }
        
        $success = $wishlist->removeItem($productId);
        $wishlistCount = $wishlist->getCount();
        
        ob_end_clean();
        echo json_encode([
            'success' => $success,
            'wishlist_count' => $wishlistCount,
            'message' => $success ? 'Removed from wishlist' : 'Failed to remove'
        ]);
        
    } elseif ($action === 'clear') {
        $success = $wishlist->clearWishlist();
        $wishlistCount = $wishlist->getCount();
        
        ob_end_clean();
        echo json_encode([
            'success' => $success,
            'wishlist_count' => $wishlistCount,
            'message' => 'Wishlist cleared'
        ]);
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;