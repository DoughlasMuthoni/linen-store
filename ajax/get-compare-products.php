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
            p.price as base_price,
            p.sku,
            p.description,
            b.name as brand_name,
            c.name as category_name,
            (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as image_url,
            (SELECT GROUP_CONCAT(DISTINCT pv.size) FROM product_variants pv WHERE pv.product_id = p.id AND pv.size IS NOT NULL AND pv.stock_quantity > 0) as available_sizes,
            (SELECT GROUP_CONCAT(DISTINCT pv.color) FROM product_variants pv WHERE pv.product_id = p.id AND pv.color IS NOT NULL AND pv.stock_quantity > 0) as available_colors,
            (SELECT SUM(pv.stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_stock,
            (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) as variant_count,
            COALESCE((SELECT MIN(pv.price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0), p.price) as min_price,
            COALESCE((SELECT MAX(pv.price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0), p.price) as max_price
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
        // Determine price display
        $hasVariants = $product['variant_count'] > 0;
        $minPrice = $product['min_price'];
        $maxPrice = $product['max_price'];
        
        if ($hasVariants && $minPrice != $maxPrice) {
            $priceDisplay = 'Ksh ' . number_format($minPrice, 2) . ' - Ksh ' . number_format($maxPrice, 2);
        } else {
            $priceDisplay = 'Ksh ' . number_format($minPrice, 2);
        }
        
        // Parse available sizes and colors
        $availableSizes = [];
        $availableColors = [];
        
        if (!empty($product['available_sizes'])) {
            $availableSizes = array_filter(array_map('trim', explode(',', $product['available_sizes'])));
        }
        
        if (!empty($product['available_colors'])) {
            $availableColors = array_filter(array_map('trim', explode(',', $product['available_colors'])));
        }
        
        $formattedProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $priceDisplay,
            'price_numeric' => (float)$minPrice,
            'base_price' => (float)$product['base_price'],
            'min_price' => (float)$minPrice,
            'max_price' => (float)$maxPrice,
            'image' => !empty($product['image_url']) 
                ? (strpos($product['image_url'], 'http') === 0 ? $product['image_url'] : SITE_URL . ltrim($product['image_url'], '/'))
                : SITE_URL . 'assets/images/placeholder.jpg',
            'brand' => $product['brand_name'] ?? 'Unknown Brand',
            'category' => $product['category_name'] ?? 'Uncategorized',
            'sku' => $product['sku'] ?? 'N/A',
            'description' => $product['description'] ?? '',
            'sizes' => $availableSizes,
            'colors' => $availableColors,
            'stock' => (int)$product['total_stock'],
            'has_variants' => $hasVariants,
            'variant_count' => (int)$product['variant_count'],
            'stock_status' => $product['total_stock'] > 0 ? 'In Stock' : 'Out of Stock'
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