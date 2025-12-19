<?php
// /linen-closet/ajax/get-wishlist-status.php

session_start();
header('Content-Type: application/json');

// Initialize wishlist if not exists
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Check if specific product is in wishlist
if (isset($_GET['product_id'])) {
    $productId = intval($_GET['product_id']);
    $isInWishlist = in_array($productId, $_SESSION['wishlist']);
    
    echo json_encode([
        'success' => true,
        'in_wishlist' => $isInWishlist,
        'product_id' => $productId,
        'count' => count($_SESSION['wishlist'])
    ]);
} else {
    // Return all wishlist items
    echo json_encode([
        'success' => true,
        'wishlist_items' => $_SESSION['wishlist'],
        'count' => count($_SESSION['wishlist'])
    ]);
}
?>