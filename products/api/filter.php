<?php
// /linen-closet/products/api/filter.php

$rootPath = dirname(__DIR__, 2); // Go up two levels from /products/api/
require_once $rootPath . '/linen-closet/includes/autoload.php';
require_once $rootPath . '/includes/config.php';
require_once $rootPath . '/includes/Database.php';
require_once $rootPath . '/includes/App.php';

$app = new App();
$db = $app->getDB();

// Get filter parameters
$filters = [
    'category_id' => $_GET['category_id'] ?? null,
    'brand_id' => $_GET['brand_id'] ?? null,
    'min_price' => $_GET['min_price'] ?? null,
    'max_price' => $_GET['max_price'] ?? null,
    'size' => $_GET['size'] ?? null,
    'color' => $_GET['color'] ?? null,
    'sort' => $_GET['sort'] ?? 'newest',
    'page' => (int)($_GET['page'] ?? 1),
    'limit' => (int)($_GET['limit'] ?? 12)
];

// Build base query
$sql = "SELECT 
            p.*,
            pi.image_url,
            b.name as brand_name,
            GROUP_CONCAT(DISTINCT pv.size) as available_sizes,
            GROUP_CONCAT(DISTINCT pv.color) as available_colors,
            MIN(pv.price) as min_variant_price,
            MAX(pv.price) as max_variant_price,
            SUM(pv.stock_quantity) as total_stock
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN product_variants pv ON p.id = pv.product_id
        WHERE p.is_active = 1";
        
$params = [];
$conditions = [];

// Apply filters
if ($filters['category_id']) {
    // Get all subcategories if this is a parent category
    $stmt = $db->prepare("SELECT id FROM categories WHERE parent_id = ? OR id = ?");
    $stmt->execute([$filters['category_id'], $filters['category_id']]);
    $categoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
    $conditions[] = "p.category_id IN ($placeholders)";
    $params = array_merge($params, $categoryIds);
}

if ($filters['brand_id']) {
    $conditions[] = "p.brand_id = ?";
    $params[] = $filters['brand_id'];
}

if ($filters['min_price'] !== null) {
    $conditions[] = "p.price >= ?";
    $params[] = $filters['min_price'];
}

if ($filters['max_price'] !== null) {
    $conditions[] = "p.price <= ?";
    $params[] = $filters['max_price'];
}

if ($filters['size']) {
    $conditions[] = "EXISTS (
        SELECT 1 FROM product_variants pv2 
        WHERE pv2.product_id = p.id 
        AND pv2.size = ? 
        AND pv2.stock_quantity > 0
    )";
    $params[] = $filters['size'];
}

if ($filters['color']) {
    $conditions[] = "EXISTS (
        SELECT 1 FROM product_variants pv3 
        WHERE pv3.product_id = p.id 
        AND pv3.color = ?
        AND pv3.stock_quantity > 0
    )";
    $params[] = $filters['color'];
}

// Add conditions to query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Group by product
$sql .= " GROUP BY p.id";

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM ($sql) as filtered_products";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalResult = $countStmt->fetch();
$totalProducts = $totalResult['total'];

// Apply sorting
switch ($filters['sort']) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

// Apply pagination
$offset = ($filters['page'] - 1) * $filters['limit'];
$sql .= " LIMIT ? OFFSET ?";
$params[] = $filters['limit'];
$params[] = $offset;

// Execute query
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Add wishlist status for logged-in users
if (isset($_SESSION['user_id'])) {
    foreach ($products as &$product) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as in_wishlist 
            FROM wishlist 
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $product['id']]);
        $wishlist = $stmt->fetch();
        $product['in_wishlist'] = $wishlist['in_wishlist'] > 0;
    }
}

// Prepare response
$response = [
    'success' => true,
    'products' => $products,
    'total' => $totalProducts,
    'page' => $filters['page'],
    'limit' => $filters['limit'],
    'totalPages' => ceil($totalProducts / $filters['limit']),
    'filters' => $filters
];

header('Content-Type: application/json');
echo json_encode($response);
?>