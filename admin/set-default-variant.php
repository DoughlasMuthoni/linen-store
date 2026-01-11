<?php
// /linen-closet/admin/set-default-variant.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

if (!$app->isLoggedIn() || !$app->isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$variantId = $input['variant_id'] ?? 0;

if (!$variantId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Variant ID required']);
    exit;
}

try {
    // Get product ID from variant
    $variantStmt = $db->prepare("SELECT product_id FROM product_variants WHERE id = ?");
    $variantStmt->execute([$variantId]);
    $variant = $variantStmt->fetch();
    
    if (!$variant) {
        throw new Exception('Variant not found');
    }
    
    $productId = $variant['product_id'];
    
    // Start transaction
    $db->beginTransaction();
    
    // Unset all other defaults for this product
    $unsetStmt = $db->prepare("
        UPDATE product_variants 
        SET is_default = 0 
        WHERE product_id = ? AND is_default = 1
    ");
    $unsetStmt->execute([$productId]);
    
    // Set this variant as default
    $setStmt = $db->prepare("
        UPDATE product_variants 
        SET is_default = 1 
        WHERE id = ? AND product_id = ?
    ");
    $setStmt->execute([$variantId, $productId]);
    
    $db->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Default variant updated'
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>