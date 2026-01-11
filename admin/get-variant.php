<?php
// /linen-closet/admin/get-variant.php

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

$variantId = $_GET['id'] ?? 0;

if (!$variantId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Variant ID required']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM product_variants WHERE id = ?");
    $stmt->execute([$variantId]);
    $variant = $stmt->fetch();
    
    if (!$variant) {
        throw new Exception('Variant not found');
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'variant' => $variant
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>