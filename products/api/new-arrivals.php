<?php
// /linen-closet/products/api/new-arrivals.php

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Disable error display, enable logging
ini_set('display_errors', 0);
error_reporting(0);

// Clear output buffer
if (ob_get_length()) ob_end_clean();

// Check if config exists
$configPath = __DIR__ . '/../../includes/config.php';
if (!file_exists($configPath)) {
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'Configuration file not found'
    ]);
    exit();
}

// Include config
require_once $configPath;

// Check if Database class exists
$databasePath = __DIR__ . '/../../includes/Database.php';
if (!file_exists($databasePath)) {
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'Database class not found'
    ]);
    exit();
}

require_once $databasePath;

// Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/linen-closet/');
}

try {
    // Get database instance using singleton pattern
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Check if products table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'products'")->fetch();
    
    if (!$tableCheck) {
        // Table doesn't exist - use sample data
        throw new Exception('Products table not found');
    }
    
    // Fetch new arrivals (products added in the last 30 days)
    $sql = "
        SELECT 
            p.id,
            p.slug,
            p.name,
            p.price,
            p.compare_price,
            p.stock_quantity,
            p.is_featured,
            p.description,
            p.created_at,
            b.name as brand_name,
            c.name as category_name,
            c.slug as category_slug,
            (
                SELECT image_url 
                FROM product_images pi 
                WHERE pi.product_id = p.id 
                ORDER BY pi.is_primary DESC, pi.id ASC
                LIMIT 1
            ) as image_url,
            (
                SELECT COUNT(DISTINCT oi.id) 
                FROM order_items oi 
                JOIN orders o ON oi.order_id = o.id 
                WHERE oi.product_id = p.id 
                AND o.status IN ('delivered', 'shipped')
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ) as recent_orders
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY p.created_at DESC, p.is_featured DESC
        LIMIT 8
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no recent products, get newest products overall
    if (empty($products)) {
        $sql = "
            SELECT 
                p.id,
                p.slug,
                p.name,
                p.price,
                p.compare_price,
                p.stock_quantity,
                p.is_featured,
                p.description,
                p.created_at,
                b.name as brand_name,
                c.name as category_name,
                c.slug as category_slug,
                (
                    SELECT image_url 
                    FROM product_images pi 
                    WHERE pi.product_id = p.id 
                    ORDER BY pi.is_primary DESC, pi.id ASC
                    LIMIT 1
                ) as image_url,
                0 as recent_orders
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1
            ORDER BY p.created_at DESC
            LIMIT 8
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Check if user is logged in for wishlist status
    $userId = $_SESSION['user_id'] ?? null;
    $wishlistIds = [];
    
    if ($userId) {
        try {
            // Check if wishlist table exists
            $wishlistCheck = $pdo->query("SHOW TABLES LIKE 'wishlist'")->fetch();
            if ($wishlistCheck) {
                $wishlistSql = "SELECT product_id FROM wishlist WHERE user_id = ?";
                $wishlistStmt = $pdo->prepare($wishlistSql);
                $wishlistStmt->execute([$userId]);
                $wishlistIds = $wishlistStmt->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Exception $e) {
            // Wishlist table might not exist or have issues
            error_log("Wishlist query error: " . $e->getMessage());
        }
    }
    
    // Format response
    $newArrivals = [];
    
    if (!empty($products)) {
        foreach ($products as $product) {
            // Determine if product is in wishlist
            $inWishlist = in_array($product['id'], $wishlistIds);
            
            // Check stock status
            $stockStatus = ($product['stock_quantity'] > 0) ? 'in-stock' : 'out-of-stock';
            
            // Format price
            $price = $product['price'] ? number_format($product['price'], 2) : '0.00';
            $comparePrice = $product['compare_price'] ? number_format($product['compare_price'], 2) : null;
            
            // Calculate discount percentage if there's a compare price
            $discountPercentage = null;
            if ($comparePrice && $comparePrice > $price) {
                $discountPercentage = round((($comparePrice - $price) / $comparePrice) * 100);
            }
            
            // Get image URL
            $imageUrl = $product['image_url'] ? SITE_URL . $product['image_url'] : SITE_URL . 'assets/images/placeholder.jpg';
            
            // Format date
            $createdDate = date('M j, Y', strtotime($product['created_at']));
            $isNew = (strtotime($product['created_at']) > strtotime('-7 days'));
            
            $newArrivals[] = [
                'id' => (int)$product['id'],
                'slug' => $product['slug'] ?? '',
                'name' => $product['name'] ?? '',
                'price' => '$' . $price,
                'original_price' => $comparePrice ? '$' . $comparePrice : null,
                'discount_percentage' => $discountPercentage,
                'image_url' => $imageUrl,
                'stock_status' => $stockStatus,
                'is_new' => $isNew,
                'created_date' => $createdDate,
                'recent_orders' => (int)($product['recent_orders'] ?? 0),
                'is_featured' => (bool)($product['is_featured'] ?? false),
                'brand' => $product['brand_name'] ?? '',
                'category' => $product['category_name'] ?? '',
                'category_slug' => $product['category_slug'] ?? '',
                'description' => mb_substr($product['description'] ?? '', 0, 100) . '...',
                'in_wishlist' => $inWishlist
            ];
        }
    } else {
        // No products in database - use sample data
        $newArrivals = getSampleNewArrivals();
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $newArrivals,
        'count' => count($newArrivals),
        'message' => 'New arrivals fetched successfully'
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    error_log("New arrivals API error: " . $e->getMessage());
    
    // Use sample data on error
    $newArrivals = getSampleNewArrivals();
    
    echo json_encode([
        'success' => true,
        'data' => $newArrivals,
        'count' => count($newArrivals),
        'message' => 'Showing sample new arrivals',
        'note' => 'Database error occurred, showing sample data'
    ], JSON_UNESCAPED_SLASHES);
}

exit();

// Function to return sample new arrivals
function getSampleNewArrivals() {
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost/linen-closet/';
    
    return [
        [
            'id' => 1,
            'slug' => 'linen-button-shirt',
            'name' => 'Linen Button Shirt',
            'price' => '$89.99',
            'original_price' => '$99.99',
            'discount_percentage' => 10,
            'image_url' => $siteUrl . 'assets/images/products/shirt-1.jpg',
            'stock_status' => 'in-stock',
            'is_new' => true,
            'created_date' => date('M j, Y'),
            'recent_orders' => 12,
            'is_featured' => true,
            'brand' => 'Linen House',
            'category' => 'Shirts',
            'category_slug' => 'shirts',
            'description' => 'Premium linen button shirt with classic fit...',
            'in_wishlist' => false
        ],
        [
            'id' => 2,
            'slug' => 'relaxed-fit-pants',
            'name' => 'Relaxed Fit Pants',
            'price' => '$109.99',
            'original_price' => null,
            'discount_percentage' => null,
            'image_url' => $siteUrl . 'assets/images/products/pants-1.jpg',
            'stock_status' => 'in-stock',
            'is_new' => true,
            'created_date' => date('M j, Y', strtotime('-2 days')),
            'recent_orders' => 8,
            'is_featured' => false,
            'brand' => 'Cotton & Linen',
            'category' => 'Pants',
            'category_slug' => 'pants',
            'description' => 'Comfortable relaxed fit linen pants...',
            'in_wishlist' => true
        ],
        [
            'id' => 3,
            'slug' => 'oversized-linen-blouse',
            'name' => 'Oversized Linen Blouse',
            'price' => '$79.99',
            'original_price' => '$89.99',
            'discount_percentage' => 11,
            'image_url' => $siteUrl . 'assets/images/products/blouse-1.jpg',
            'stock_status' => 'in-stock',
            'is_new' => true,
            'created_date' => date('M j, Y', strtotime('-5 days')),
            'recent_orders' => 15,
            'is_featured' => true,
            'brand' => 'Linen Luxe',
            'category' => 'Blouses',
            'category_slug' => 'blouses',
            'description' => 'Stylish oversized linen blouse for effortless style...',
            'in_wishlist' => false
        ],
        [
            'id' => 4,
            'slug' => 'linen-midi-dress',
            'name' => 'Linen Midi Dress',
            'price' => '$129.99',
            'original_price' => null,
            'discount_percentage' => null,
            'image_url' => $siteUrl . 'assets/images/products/dress-1.jpg',
            'stock_status' => 'in-stock',
            'is_new' => true,
            'created_date' => date('M j, Y', strtotime('-1 day')),
            'recent_orders' => 21,
            'is_featured' => true,
            'brand' => 'Linen House',
            'category' => 'Dresses',
            'category_slug' => 'dresses',
            'description' => 'Elegant linen midi dress perfect for summer...',
            'in_wishlist' => false
        ],
        [
            'id' => 5,
            'slug' => 'cropped-linen-jacket',
            'name' => 'Cropped Linen Jacket',
            'price' => '$149.99',
            'original_price' => '$169.99',
            'discount_percentage' => 12,
            'image_url' => $siteUrl . 'assets/images/products/jacket-1.jpg',
            'stock_status' => 'in-stock',
            'is_new' => false,
            'created_date' => date('M j, Y', strtotime('-15 days')),
            'recent_orders' => 7,
            'is_featured' => false,
            'brand' => 'Linen Luxe',
            'category' => 'Jackets',
            'category_slug' => 'jackets',
            'description' => 'Trendy cropped linen jacket for layering...',
            'in_wishlist' => true
        ],
        [
            'id' => 6,
            'slug' => 'wide-leg-trousers',
            'name' => 'Wide Leg Trousers',
            'price' => '$119.99',
            'original_price' => null,
            'discount_percentage' => null,
            'image_url' => $siteUrl . 'assets/images/products/trousers-1.jpg',
            'stock_status' => 'in-stock',
            'is_new' => false,
            'created_date' => date('M j, Y', strtotime('-10 days')),
            'recent_orders' => 14,
            'is_featured' => true,
            'brand' => 'Cotton & Linen',
            'category' => 'Pants',
            'category_slug' => 'pants',
            'description' => 'Fashionable wide leg linen trousers...',
            'in_wishlist' => false
        ],
        [
            'id' => 7,
            'slug' => 'linen-overshirt',
            'name' => 'Linen Overshirt',
            'price' => '$99.99',
            'original_price' => '$119.99',
            'discount_percentage' => 17,
            'image_url' => $siteUrl . 'assets/images/products/overshirt-1.jpg',
            'stock_status' => 'in-stock',
            'is_new' => false,
            'created_date' => date('M j, Y', strtotime('-20 days')),
            'recent_orders' => 9,
            'is_featured' => false,
            'brand' => 'Linen House',
            'category' => 'Shirts',
            'category_slug' => 'shirts',
            'description' => 'Versatile linen overshirt for casual styling...',
            'in_wishlist' => false
        ],
        [
            'id' => 8,
            'slug' => 'linen-shorts',
            'name' => 'Linen Shorts',
            'price' => '$69.99',
            'original_price' => null,
            'discount_percentage' => null,
            'image_url' => $siteUrl . 'assets/images/products/shorts-1.jpg',
            'stock_status' => 'in-stock',
            'is_new' => true,
            'created_date' => date('M j, Y', strtotime('-3 days')),
            'recent_orders' => 18,
            'is_featured' => true,
            'brand' => 'Cotton & Linen',
            'category' => 'Shorts',
            'category_slug' => 'shorts',
            'description' => 'Comfortable linen shorts for summer days...',
            'in_wishlist' => false
        ]
    ];
}
?>