<?php
// /linen-closet/admin/api/category.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/App.php';

$app = new App();
$db = $app->getDB();

header('Content-Type: application/json');

// Check if user is logged in
if (!$app->isLoggedIn() || !$app->isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$categoryId = $_GET['id'] ?? 0;

if (!$categoryId) {
    echo json_encode(['success' => false, 'error' => 'Category ID is required']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT id, name, slug, description, image_url, parent_id, is_active 
        FROM categories 
        WHERE id = ?
    ");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
    
    if ($category) {
        echo json_encode([
            'success' => true,
            'category' => $category
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Category not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>