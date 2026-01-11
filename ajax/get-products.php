<?php
// /linen-closet/ajax/get-products.php

// Enable debugging temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if it's an AJAX request (optional but good practice)
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Log request for debugging
error_log("AJAX Request to get-products.php: " . $_SERVER['REQUEST_URI']);
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Is AJAX: " . ($is_ajax ? 'Yes' : 'No'));
/**
 * Format price range for products with variants
 */
function formatPriceRange($minPrice, $maxPrice) {
    if ($minPrice == $maxPrice) {
        return 'Ksh ' . number_format($minPrice, 2);
    }
    return 'Ksh ' . number_format($minPrice, 2) . ' - Ksh ' . number_format($maxPrice, 2);
}
try {
    // Define SITE_URL - Use relative path to avoid absolute URL issues
    $site_url = '/linen-closet/';
    
    // Sanitize and validate all parameters
    $params = [
        'category_id' => isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null,
        'brand_id' => isset($_GET['brand_id']) && is_numeric($_GET['brand_id']) ? (int)$_GET['brand_id'] : null,
        'min_price' => isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null,
        'max_price' => isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null,
        'size' => isset($_GET['size']) && preg_match('/^[a-zA-Z0-9\s-]+$/', $_GET['size']) ? trim($_GET['size']) : null,
        'color' => isset($_GET['color']) && preg_match('/^[a-zA-Z0-9\s-]+$/', $_GET['color']) ? trim($_GET['color']) : null,
        'sort' => isset($_GET['sort']) && in_array($_GET['sort'], ['newest', 'popular', 'price_low', 'price_high', 'name']) 
                 ? $_GET['sort'] : 'newest',
        'page' => isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 
                 ? max(1, (int)$_GET['page']) : 1,
        'limit' => isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 
                  ? min(100, max(1, (int)$_GET['limit'])) : 12,
        'view' => isset($_GET['view']) && in_array($_GET['view'], ['grid', 'list']) 
                 ? $_GET['view'] : 'grid',
        'search' => isset($_GET['search']) ? trim(strip_tags($_GET['search'])) : null,
        'q' => isset($_GET['q']) ? trim(strip_tags($_GET['q'])) : null
    ];
    
    // Use 'q' parameter if 'search' is empty
    if (empty($params['search']) && !empty($params['q'])) {
        $params['search'] = $params['q'];
    }
    
        // Debug: Log received parameters
    error_log("Parameters: " . json_encode($params));
    
    // ============================================
    // NEW: Handle POST requests for recently viewed
    // ============================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = json_decode(file_get_contents('php://input'), true);
        
        if (isset($postData['action']) && $postData['action'] === 'get_recently_viewed') {
            $productIds = $postData['product_ids'] ?? [];
            
            if (empty($productIds)) {
                $response = [
                    'success' => false,
                    'message' => 'No product IDs provided',
                    'products' => []
                ];
                
                ob_clean();
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                ob_end_flush();
                exit();
            }
            
            // Convert IDs to comma-separated string for IN clause
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
            
            try {
                require_once __DIR__ . '/../includes/Database.php';
                $db = Database::getInstance();
                $pdo = $db->getConnection();
                
               $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    c.name as category_name,
                    c.slug as category_slug,
                    b.name as brand_name,
                    (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
                    COALESCE((SELECT SUM(pv.stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id), 0) as total_stock,
                    COALESCE((SELECT MIN(pv.price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0), p.price) as min_variant_price,
                    COALESCE((SELECT MAX(pv.price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0), p.price) as max_variant_price,
                    (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) as variant_count
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.id IN ($placeholders) AND p.is_active = 1
                ORDER BY FIELD(p.id, " . $placeholders . ")
                LIMIT 8
            ");
                
                // Execute with IDs twice (for WHERE and ORDER BY FIELD)
                $stmt->execute(array_merge($productIds, $productIds));
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format product data
                $formattedProducts = [];
                foreach ($products as $product) {
                    $formattedProducts[] = [
                        'id' => (int)$product['id'],
                        'name' => $product['name'],
                        'slug' => $product['slug'],
                        'price' => (float)$product['price'],
                        'original_price' => isset($product['compare_price']) ? (float)$product['compare_price'] : null,
                        'price_numeric' => (float)$product['price'],
                        'original_price_numeric' => isset($product['compare_price']) ? (float)$product['compare_price'] : null,
                        'image_url' => $product['primary_image'] ?: 'assets/images/placeholder.jpg',
                        'brand' => $product['brand_name'] ?? '',
                        'brand_id' => (int)($product['brand_id'] ?? 0),
                        'category' => $product['category_name'] ?? '',
                        'category_id' => (int)($product['category_id'] ?? 0),
                        'stock_quantity' => (int)$product['total_stock'],
                        'has_variants' => ($product['variant_count'] > 0),
                        'variant_count' => (int)$product['variant_count'],
                        'price' => $product['variant_count'] > 0 ? formatPriceRange($product['min_variant_price'], $product['max_variant_price']) : 'Ksh ' . number_format($product['price'], 2),
                        'price_numeric' => $product['variant_count'] > 0 ? (float)$product['min_variant_price'] : (float)$product['price'],
                        'original_price' => isset($product['compare_price']) ? 'Ksh ' . number_format($product['compare_price'], 2) : null,
                        'original_price_numeric' => isset($product['compare_price']) ? (float)$product['compare_price'] : null,
                        
                        'description' => $product['description'] ?? '',
                        'short_description' => substr($product['description'] ?? '', 0, 100) . '...',
                        'rating' => (float)($product['rating'] ?? 0),
                        'review_count' => (int)($product['review_count'] ?? 0),
                        'sizes' => [], // You would need to fetch sizes separately
                        'colors' => [], // You would need to fetch colors separately
                        'tags' => [] // You would need to fetch tags separately
                    ];
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Recently viewed products fetched successfully',
                    'products' => $formattedProducts,
                    'count' => count($formattedProducts)
                ];
                
                ob_clean();
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                ob_end_flush();
                exit();
                
            } catch (Exception $e) {
                error_log('Recently viewed error: ' . $e->getMessage());
                
                $response = [
                    'success' => false,
                    'message' => 'Error fetching recently viewed products',
                    'products' => [],
                    'error' => $e->getMessage()
                ];
                
                ob_clean();
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                ob_end_flush();
                exit();
            }
        }
    }
    
    // ============================================
    // END OF NEW POST HANDLING
    // ============================================
    
    // Sample product data - in production, this would come from a database
    $products = [
        [
            'id' => 1,
            'name' => 'Classic Linen Shirt',
            'slug' => 'classic-linen-shirt',
            'price' => '$79.99',
            'original_price' => '$99.99',
            'price_numeric' => 79.99,
            'original_price_numeric' => 99.99,
            'image_url' => 'assets/images/products/shirt-1.jpg',
            'brand' => 'Linen House',
            'brand_id' => 1,
            'category' => 'Shirts',
            'category_id' => 1,
            'stock_status' => 'in-stock',
            'stock_quantity' => 15,
            'description' => 'Premium linen shirt made from 100% organic linen. Perfect for everyday comfort and style.',
            'short_description' => 'Premium 100% organic linen shirt',
            'rating' => 4.5,
            'review_count' => 42,
            'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
            'colors' => ['White', 'Beige', 'Navy'],
            'tags' => ['shirt', 'linen', 'casual', 'summer']
        ],
        [
            'id' => 2,
            'name' => 'Linen Blazer',
            'slug' => 'linen-blazer',
            'price' => '$159.99',
            'original_price' => null,
            'price_numeric' => 159.99,
            'original_price_numeric' => null,
            'image_url' => 'assets/images/products/blazer-1.jpg',
            'brand' => 'Linen Luxe',
            'brand_id' => 2,
            'category' => 'Blazers',
            'category_id' => 2,
            'stock_status' => 'in-stock',
            'stock_quantity' => 8,
            'description' => 'Elegant linen blazer perfect for formal occasions or smart casual wear.',
            'short_description' => 'Elegant linen blazer for formal occasions',
            'rating' => 4.8,
            'review_count' => 28,
            'sizes' => ['S', 'M', 'L', 'XL'],
            'colors' => ['Navy', 'Black', 'Grey'],
            'tags' => ['blazer', 'formal', 'linen', 'business']
        ],
        [
            'id' => 3,
            'name' => 'Linen Maxi Dress',
            'slug' => 'linen-maxi-dress',
            'price' => '$139.99',
            'original_price' => '$179.99',
            'price_numeric' => 139.99,
            'original_price_numeric' => 179.99,
            'image_url' => 'assets/images/products/dress-1.jpg',
            'brand' => 'Linen House',
            'brand_id' => 1,
            'category' => 'Dresses',
            'category_id' => 3,
            'stock_status' => 'in-stock',
            'stock_quantity' => 12,
            'description' => 'Beautiful maxi dress made from breathable linen fabric. Perfect for summer days.',
            'short_description' => 'Breathable linen maxi dress for summer',
            'rating' => 4.6,
            'review_count' => 56,
            'sizes' => ['XS', 'S', 'M', 'L'],
            'colors' => ['White', 'Blue', 'Pink'],
            'tags' => ['dress', 'maxi', 'summer', 'linen', 'casual']
        ],
        [
            'id' => 4,
            'name' => 'Linen Jumpsuit',
            'slug' => 'linen-jumpsuit',
            'price' => '$129.99',
            'original_price' => null,
            'price_numeric' => 129.99,
            'original_price_numeric' => null,
            'image_url' => 'assets/images/products/jumpsuit-1.jpg',
            'brand' => 'Cotton & Linen',
            'brand_id' => 3,
            'category' => 'Jumpsuits',
            'category_id' => 4,
            'stock_status' => 'out-of-stock',
            'stock_quantity' => 0,
            'description' => 'Stylish linen jumpsuit for a chic summer look. Comfortable and fashionable.',
            'short_description' => 'Stylish linen jumpsuit for summer',
            'rating' => 4.3,
            'review_count' => 31,
            'sizes' => ['XS', 'S', 'M'],
            'colors' => ['Beige', 'Black', 'Green'],
            'tags' => ['jumpsuit', 'linen', 'summer', 'casual']
        ],
        [
            'id' => 5,
            'name' => 'Linen Trousers',
            'slug' => 'linen-trousers',
            'price' => '$89.99',
            'original_price' => '$119.99',
            'price_numeric' => 89.99,
            'original_price_numeric' => 119.99,
            'image_url' => 'assets/images/products/trousers-1.jpg',
            'brand' => 'Linen House',
            'brand_id' => 1,
            'category' => 'Trousers',
            'category_id' => 5,
            'stock_status' => 'in-stock',
            'stock_quantity' => 20,
            'description' => 'Comfortable linen trousers perfect for warm weather. Lightweight and breathable.',
            'short_description' => 'Comfortable linen trousers for warm weather',
            'rating' => 4.4,
            'review_count' => 39,
            'sizes' => ['S', 'M', 'L', 'XL'],
            'colors' => ['Beige', 'Grey', 'Navy'],
            'tags' => ['trousers', 'pants', 'linen', 'casual']
        ],
        [
            'id' => 6,
            'name' => 'Linen Shirt Dress',
            'slug' => 'linen-shirt-dress',
            'price' => '$119.99',
            'original_price' => null,
            'price_numeric' => 119.99,
            'original_price_numeric' => null,
            'image_url' => 'assets/images/products/shirt-dress-1.jpg',
            'brand' => 'Linen Luxe',
            'brand_id' => 2,
            'category' => 'Dresses',
            'category_id' => 3,
            'stock_status' => 'in-stock',
            'stock_quantity' => 7,
            'description' => 'Versatile linen shirt dress that can be dressed up or down. Perfect for any occasion.',
            'short_description' => 'Versatile linen shirt dress',
            'rating' => 4.7,
            'review_count' => 47,
            'sizes' => ['XS', 'S', 'M', 'L'],
            'colors' => ['White', 'Blue', 'Striped'],
            'tags' => ['dress', 'shirt-dress', 'linen', 'versatile']
        ]
    ];
    
    // Apply filters to products
    $filteredProducts = $products;
    
    // Filter by category
    if ($params['category_id']) {
        $filteredProducts = array_filter($filteredProducts, function($product) use ($params) {
            return $product['category_id'] == $params['category_id'];
        });
    }
    
    // Filter by brand
    if ($params['brand_id']) {
        $filteredProducts = array_filter($filteredProducts, function($product) use ($params) {
            return $product['brand_id'] == $params['brand_id'];
        });
    }
    
    // Filter by price range
    if ($params['min_price'] !== null || $params['max_price'] !== null) {
        $filteredProducts = array_filter($filteredProducts, function($product) use ($params) {
            $price = $product['price_numeric'];
            $pass = true;
            
            if ($params['min_price'] !== null) {
                $pass = $pass && ($price >= $params['min_price']);
            }
            if ($params['max_price'] !== null) {
                $pass = $pass && ($price <= $params['max_price']);
            }
            
            return $pass;
        });
    }
    
    // Filter by size
    if ($params['size']) {
        $filteredProducts = array_filter($filteredProducts, function($product) use ($params) {
            return in_array($params['size'], $product['sizes']);
        });
    }
    
    // Filter by color
    if ($params['color']) {
        $filteredProducts = array_filter($filteredProducts, function($product) use ($params) {
            return in_array($params['color'], $product['colors']);
        });
    }
    
    // Search by keyword
    if ($params['search']) {
        $searchTerm = strtolower($params['search']);
        $filteredProducts = array_filter($filteredProducts, function($product) use ($searchTerm) {
            $searchable = strtolower($product['name'] . ' ' . $product['description'] . ' ' . $product['brand'] . ' ' . $product['category']);
            return strpos($searchable, $searchTerm) !== false;
        });
    }
    
    // Convert back to indexed array
    $filteredProducts = array_values($filteredProducts);
    
    // Apply sorting
    switch ($params['sort']) {
        case 'popular':
            usort($filteredProducts, function($a, $b) {
                return $b['rating'] * 100 + $b['review_count'] <=> $a['rating'] * 100 + $a['review_count'];
            });
            break;
            
        case 'price_low':
            usort($filteredProducts, function($a, $b) {
                return $a['price_numeric'] <=> $b['price_numeric'];
            });
            break;
            
        case 'price_high':
            usort($filteredProducts, function($a, $b) {
                return $b['price_numeric'] <=> $a['price_numeric'];
            });
            break;
            
        case 'name':
            usort($filteredProducts, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            break;
            
        case 'newest':
        default:
            // Already sorted by ID (newest first)
            usort($filteredProducts, function($a, $b) {
                return $b['id'] <=> $a['id'];
            });
            break;
    }
    
    // Apply pagination
    $total = count($filteredProducts);
    $totalPages = ceil($total / $params['limit']);
    $offset = ($params['page'] - 1) * $params['limit'];
    $pageProducts = array_slice($filteredProducts, $offset, $params['limit']);
    
    // Prepare response data
    $response = [
        'success' => true,
        'data' => $pageProducts,
        'total' => $total,
        'page' => $params['page'],
        'pages' => $totalPages,
        'limit' => $params['limit'],
        'filters_applied' => [
            'category_id' => $params['category_id'],
            'brand_id' => $params['brand_id'],
            'min_price' => $params['min_price'],
            'max_price' => $params['max_price'],
            'size' => $params['size'],
            'color' => $params['color'],
            'sort' => $params['sort'],
            'search' => $params['search']
        ],
        'debug_info' => [
            'products_count' => count($products),
            'filtered_count' => $total,
            'page_count' => count($pageProducts),
            'request_uri' => $_SERVER['REQUEST_URI'],
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'message' => 'Products fetched successfully'
    ];
    
    // Clear any previous output
    ob_clean();
    
    // Send response
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Clean output buffer
    ob_clean();
    
    // Return error response
    http_response_code(500);
    
    $errorResponse = [
        'success' => false,
        'data' => [],
        'message' => 'An error occurred while fetching products',
        'error' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'debug_info' => [
            'request_uri' => $_SERVER['REQUEST_URI'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// End output buffering and flush
ob_end_flush();
exit();