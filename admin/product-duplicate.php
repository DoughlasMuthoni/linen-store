<?php
// /linen-closet/admin/product-duplicate.php

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

try {
    $db->beginTransaction();
    
    // Fetch original product
    $stmt = $db->prepare("
        SELECT * FROM products WHERE id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    // Create new SKU and slug
    $newSku = $product['sku'] . '-COPY-' . date('YmdHis');
    $newSlug = $product['slug'] . '-copy-' . date('YmdHis');
    
    // Insert duplicated product
    $insertStmt = $db->prepare("
        INSERT INTO products (
            name, slug, description, short_description, price, compare_price,
            category_id, brand_id, sku, stock_quantity, is_featured, is_active,
            care_instructions, materials, weight, dimensions, rating, review_count
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insertStmt->execute([
        $product['name'] . ' (Copy)',
        $newSlug,
        $product['description'],
        $product['short_description'],
        $product['price'],
        $product['compare_price'],
        $product['category_id'],
        $product['brand_id'],
        $newSku,
        $product['stock_quantity'],
        $product['is_featured'],
        $product['is_active'],
        $product['care_instructions'],
        $product['materials'],
        $product['weight'],
        $product['dimensions'],
        $product['rating'],
        $product['review_count']
    ]);
    
    $newProductId = $db->lastInsertId();
    
    // Duplicate images
    $imageStmt = $db->prepare("
        SELECT * FROM product_images WHERE product_id = ?
    ");
    $imageStmt->execute([$productId]);
    $images = $imageStmt->fetchAll();
    
    foreach ($images as $image) {
        // For simplicity, we're reusing the same image URLs
        // In production, you might want to copy the actual image files
        $imageInsertStmt = $db->prepare("
            INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
            VALUES (?, ?, ?, ?)
        ");
        $imageInsertStmt->execute([
            $newProductId,
            $image['image_url'],
            $image['is_primary'],
            $image['sort_order']
        ]);
    }
    
    // Duplicate variants
    $variantStmt = $db->prepare("
        SELECT * FROM product_variants WHERE product_id = ?
    ");
    $variantStmt->execute([$productId]);
    $variants = $variantStmt->fetchAll();
    
    foreach ($variants as $variant) {
        $newVariantSku = $newSku . '-' . substr(uniqid(), -6);
        $variantInsertStmt = $db->prepare("
            INSERT INTO product_variants (
                product_id, sku, size, color, color_code, price, 
                compare_price, stock_quantity, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $variantInsertStmt->execute([
            $newProductId,
            $newVariantSku,
            $variant['size'],
            $variant['color'],
            $variant['color_code'],
            $variant['price'],
            $variant['compare_price'],
            $variant['stock_quantity'],
            $variant['is_default']
        ]);
    }
    
    $db->commit();
    
    $_SESSION['flash_message'] = 'Product duplicated successfully!';
    $app->redirect('admin/products/edit/' . $newProductId);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
    $app->redirect('admin/products');
}
?>