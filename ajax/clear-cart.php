<?php
// /linen-closet/ajax/clear-cart.php - PRODUCTION VERSION

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Clear cart
    $_SESSION['cart'] = [];
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart cleared successfully',
        'cart' => [
            'count' => 0,
            'subtotal' => 0,
            'shipping' => 0,
            'tax' => 0,
            'total' => 0,
            'items' => []
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Clear Cart Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to clear cart'
    ]);
}

ob_end_flush();
?>