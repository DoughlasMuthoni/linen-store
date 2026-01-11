<?php
// /linen-closet/admin/save-variant.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

if (!$app->isLoggedIn() || !$app->isAdmin()) {
    $app->redirect('auth/login');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $app->redirect('admin/products');
}

// Verify CSRF token
if (!$app->verifyCsrfToken()) {
    $app->setFlashMessage('error', 'Invalid form submission');
    $app->redirectBack();
}

$productId = $_POST['product_id'] ?? 0;
$variantId = $_POST['variant_id'] ?? 0; // For editing existing variant

// Validate product exists
$productStmt = $db->prepare("SELECT id, sku FROM products WHERE id = ?");
$productStmt->execute([$productId]);
$product = $productStmt->fetch();

if (!$product) {
    $app->setFlashMessage('error', 'Product not found');
    $app->redirect('admin/products');
}

try {
    $db->beginTransaction();
    
    // Get form data
    $sku = trim($_POST['sku'] ?? '');
    $size = trim($_POST['size'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $colorCode = $_POST['color_code'] ?? null;
    $price = floatval($_POST['price'] ?? 0);
    $comparePrice = !empty($_POST['compare_price']) ? floatval($_POST['compare_price']) : null;
    $stockQuantity = intval($_POST['stock_quantity'] ?? 0);
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    
    // Validate required fields
    if (empty($sku)) {
        throw new Exception('SKU is required');
    }
    
    if ($price <= 0) {
        throw new Exception('Valid price is required');
    }
    
    // Check if SKU already exists (excluding current variant if editing)
    $skuCheckSql = "SELECT id FROM product_variants WHERE sku = ?";
    $skuParams = [$sku];
    
    if ($variantId) {
        $skuCheckSql .= " AND id != ?";
        $skuParams[] = $variantId;
    }
    
    $skuCheck = $db->prepare($skuCheckSql);
    $skuCheck->execute($skuParams);
    
    if ($skuCheck->fetch()) {
        throw new Exception('SKU already exists. Please use a unique SKU.');
    }
    
    // If setting as default, unset other defaults for this product
    if ($isDefault) {
        $unsetDefault = $db->prepare("
            UPDATE product_variants 
            SET is_default = 0 
            WHERE product_id = ? AND is_default = 1
        ");
        $unsetDefault->execute([$productId]);
    }
    
    // Process color code
    if ($colorCode && !preg_match('/^#[a-f0-9]{6}$/i', $colorCode)) {
        $colorCode = '#' . ltrim($colorCode, '#');
        if (!preg_match('/^#[a-f0-9]{6}$/i', $colorCode)) {
            $colorCode = null;
        }
    }
    
    // Save or update variant
    if ($variantId) {
        // Update existing variant
        $updateStmt = $db->prepare("
            UPDATE product_variants SET
                sku = ?,
                size = ?,
                color = ?,
                color_code = ?,
                price = ?,
                compare_price = ?,
                stock_quantity = ?,
                is_default = ?,
                updated_at = NOW()
            WHERE id = ? AND product_id = ?
        ");
        
        $updateStmt->execute([
            $sku,
            $size ?: null,
            $color ?: null,
            $colorCode,
            $price,
            $comparePrice,
            $stockQuantity,
            $isDefault,
            $variantId,
            $productId
        ]);
        
        $message = 'Variant updated successfully';
    } else {
        // Insert new variant
        $insertStmt = $db->prepare("
            INSERT INTO product_variants (
                product_id, sku, size, color, color_code, 
                price, compare_price, stock_quantity, is_default, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insertStmt->execute([
            $productId,
            $sku,
            $size ?: null,
            $color ?: null,
            $colorCode,
            $price,
            $comparePrice,
            $stockQuantity,
            $isDefault
        ]);
        
        $message = 'Variant added successfully';
    }
    
    $db->commit();
    $app->setFlashMessage('success', $message);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $app->setFlashMessage('error', $e->getMessage());
}

// Redirect back to variants page
$app->redirect('admin/product-variants.php?product_id=' . $productId);
?>