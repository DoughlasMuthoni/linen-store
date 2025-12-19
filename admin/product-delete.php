<?php
// /linen-closet/admin/product-delete.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    $app->redirect('admin/login');
}

// Get product ID from URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$productId) {
    $app->redirect('admin/products');
}

// Check if product exists
$stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['flash_message'] = 'Product not found';
    $app->redirect('admin/products');
}

try {
    // Get images to delete files
    $imageStmt = $db->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
    $imageStmt->execute([$productId]);
    $images = $imageStmt->fetchAll();
    
    // Delete image files from server
    foreach ($images as $image) {
        $filePath = SITE_PATH . $image['image_url'];
        if (file_exists($filePath) && !str_starts_with($image['image_url'], 'http')) {
            unlink($filePath);
        }
    }
    
    // Delete product (cascade will handle related records)
    $deleteStmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $deleteStmt->execute([$productId]);
    
    $_SESSION['flash_message'] = 'Product "' . htmlspecialchars($product['name']) . '" deleted successfully!';
    
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Error deleting product: ' . $e->getMessage();
}

$app->redirect('admin/products');
?>