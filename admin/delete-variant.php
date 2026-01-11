<?php
// /linen-closet/admin/delete-variant.php

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
    // Get product ID from variant first
    $variantStmt = $db->prepare("SELECT product_id, is_default FROM product_variants WHERE id = ?");
    $variantStmt->execute([$variantId]);
    $variant = $variantStmt->fetch();
    
    if (!$variant) {
        throw new Exception('Variant not found');
    }
    
    $productId = $variant['product_id'];
    $isDefault = $variant['is_default'];
    
    // Check if this is the last variant
    $countStmt = $db->prepare("SELECT COUNT(*) FROM product_variants WHERE product_id = ?");
    $countStmt->execute([$productId]);
    $variantCount = $countStmt->fetchColumn();
    
    if ($variantCount <= 1) {
        throw new Exception('Cannot delete the last variant. Products must have at least one variant.');
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Delete the variant
    $deleteStmt = $db->prepare("DELETE FROM product_variants WHERE id = ?");
    $deleteStmt->execute([$variantId]);
    
    // If we deleted the default variant, set another one as default
    if ($isDefault) {
        $newDefaultStmt = $db->prepare("
            UPDATE product_variants 
            SET is_default = 1 
            WHERE product_id = ? 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $newDefaultStmt->execute([$productId]);
    }
    
    $db->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Variant deleted successfully',
        'product_id' => $productId
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