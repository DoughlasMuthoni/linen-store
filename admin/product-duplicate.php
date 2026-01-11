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
    
    // Insert duplicated product (NO STOCK_QUANTITY)
    $insertStmt = $db->prepare("
        INSERT INTO products (
            name, slug, description, short_description, price, compare_price,
            category_id, brand_id, sku, is_featured, is_active,
            care_instructions, materials, weight, dimensions, rating, review_count,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
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
    
    // Fetch variants FIRST (we need them for image associations)
    $variantStmt = $db->prepare("
        SELECT * FROM product_variants WHERE product_id = ?
    ");
    $variantStmt->execute([$productId]);
    $variants = $variantStmt->fetchAll();
    
    // Duplicate variants with unique SKUs
    $variantMap = []; // Map old variant IDs to new ones
    
    foreach ($variants as $variant) {
        // Generate base variant SKU
        $baseVariantSku = $newSku;
        if ($variant['size']) $baseVariantSku .= '-' . $variant['size'];
        if ($variant['color']) $baseVariantSku .= '-' . strtoupper(str_replace(' ', '-', $variant['color']));
        if (!$variant['size'] && !$variant['color']) {
            $baseVariantSku .= '-DEFAULT';
        }
        
        // Ensure unique SKU
        $newVariantSku = $baseVariantSku;
        $counter = 1;
        $skuExists = true;
        
        while ($skuExists) {
            $checkStmt = $db->prepare("SELECT id FROM product_variants WHERE sku = ?");
            $checkStmt->execute([$newVariantSku]);
            
            if ($checkStmt->fetch()) {
                // SKU exists, append counter
                $newVariantSku = $baseVariantSku . '-' . $counter;
                $counter++;
            } else {
                $skuExists = false;
            }
        }
        
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
        
        $newVariantId = $db->lastInsertId();
        $variantMap[$variant['id']] = $newVariantId;
    }
    
    // Duplicate images with variant associations
    $imageStmt = $db->prepare("
        SELECT * FROM product_images WHERE product_id = ?
    ");
    $imageStmt->execute([$productId]);
    $images = $imageStmt->fetchAll();
    
    foreach ($images as $image) {
        $newVariantId = null;
        if ($image['variant_id'] && isset($variantMap[$image['variant_id']])) {
            $newVariantId = $variantMap[$image['variant_id']];
        }
        
        $imageInsertStmt = $db->prepare("
            INSERT INTO product_images (product_id, variant_id, image_url, is_primary, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        $imageInsertStmt->execute([
            $newProductId,
            $newVariantId,
            $image['image_url'],
            $image['is_primary'],
            $image['sort_order']
        ]);
    }
    
    $db->commit();
    
    $app->setFlashMessage('success', 'Product duplicated successfully!');
    $app->redirect('admin/product-edit.php?id=' . $newProductId);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $app->setFlashMessage('error', 'Error: ' . $e->getMessage());
    $app->redirect('admin/products');
}
?>