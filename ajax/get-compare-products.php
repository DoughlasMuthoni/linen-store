<?php
// /ajax/get-compare-products.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

header('Content-Type: application/json');

// Check if it's an AJAX request
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode(['success' => false, 'message' => 'Direct access not allowed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['product_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$productIds = $data['product_ids'];

if (empty($productIds) || !is_array($productIds)) {
    echo json_encode(['success' => true, 'products' => []]);
    exit;
}

// Convert all IDs to integers
$productIds = array_map('intval', $productIds);

try {
    // Prepare placeholders for IN clause
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $query = "
        SELECT 
            p.id,
            p.name,
            p.price,
            p.sku,
            b.name as brand_name,
            c.name as category_name,
            (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as image_url,
            (SELECT GROUP_CONCAT(DISTINCT size) FROM product_variants pv WHERE pv.product_id = p.id AND pv.size IS NOT NULL) as available_sizes,
            (SELECT GROUP_CONCAT(DISTINCT color) FROM product_variants pv WHERE pv.product_id = p.id AND pv.color IS NOT NULL) as available_colors,
            (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_stock
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders) AND p.is_active = 1
        ORDER BY FIELD(p.id, " . $placeholders . ")
    ";
    
    // Duplicate IDs for FIELD ordering
    $params = array_merge($productIds, $productIds);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format products
    $formattedProducts = [];
    foreach ($products as $product) {
        $formattedProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => 'Ksh ' . number_format($product['price'], 2),
            'image' => !empty($product['image_url']) ? SITE_URL . $product['image_url'] : SITE_URL . 'assets/images/placeholder.jpg',
            'brand' => $product['brand_name'] ?? 'Unknown Brand',
            'category' => $product['category_name'] ?? 'Uncategorized',
            'sku' => $product['sku'] ?? 'N/A',
            'sizes' => !empty($product['available_sizes']) ? explode(',', $product['available_sizes']) : [],
            'colors' => !empty($product['available_colors']) ? explode(',', $product['available_colors']) : [],
            'stock' => $product['total_stock'] ?? 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $formattedProducts
    ]);
    
} catch (Exception $e) {
    error_log("Compare products error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}