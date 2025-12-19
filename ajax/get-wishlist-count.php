<?php
// /linen-closet/ajax/get-wishlist-count.php

session_start();
header('Content-Type: application/json');

// Initialize wishlist if not exists
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

$count = count($_SESSION['wishlist']);

echo json_encode([
    'success' => true,
    'count' => $count,
    'wishlist_items' => $_SESSION['wishlist'] // Optional: send item IDs too
]);
?>