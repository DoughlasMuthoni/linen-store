<?php
// /linen-closet/products/api/best-sellers.php

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
    
    // Fetch best selling products
    // First try to get products with actual sales data
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
            (
                SELECT image_url 
                FROM product_images pi 
                WHERE pi.product_id = p.id 
                LIMIT 1
            ) as image_url,
            COALESCE((
                SELECT SUM(oi.quantity) 
                FROM order_items oi 
                JOIN orders o ON oi.order_id = o.id 
                WHERE oi.product_id = p.id 
                AND o.status IN ('delivered', 'shipped')
            ), 0) as total_sold
        FROM products p
        WHERE p.is_active = 1
        HAVING total_sold > 0
        ORDER BY total_sold DESC, p.created_at DESC
        LIMIT 8
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no products with sales, get featured/new products
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
                (
                    SELECT image_url 
                    FROM product_images pi 
                    WHERE pi.product_id = p.id 
                    LIMIT 1
                ) as image_url,
                0 as total_sold
            FROM products p
            WHERE p.is_active = 1
            ORDER BY p.is_featured DESC, p.created_at DESC
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
    $bestSellers = [];
    
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
            
            $bestSellers[] = [
                'id' => (int)$product['id'],
                'slug' => $product['slug'] ?? '',
                'name' => $product['name'] ?? '',
                'price' => '$' . $price,
                'original_price' => $comparePrice ? '$' . $comparePrice : null,
                'discount_percentage' => $discountPercentage,
                'image_url' => $imageUrl,
                'stock_status' => $stockStatus,
                'total_sold' => (int)($product['total_sold'] ?? 0),
                'is_featured' => (bool)($product['is_featured'] ?? false),
                'description' => mb_substr($product['description'] ?? '', 0, 100) . '...',
                'in_wishlist' => $inWishlist
            ];
        }
    } else {
        // No products in database - use sample data
        $bestSellers = getSampleProducts();
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $bestSellers,
        'count' => count($bestSellers),
        'message' => 'Best sellers fetched successfully'
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    error_log("Best sellers API error: " . $e->getMessage());
    
    // Use sample data on error
    $bestSellers = getSampleProducts();
    
    echo json_encode([
        'success' => true,
        'data' => $bestSellers,
        'count' => count($bestSellers),
        'message' => 'Showing sample products',
        'note' => 'Database error occurred, showing sample data'
    ], JSON_UNESCAPED_SLASHES);
}

exit();

// Function to return sample products
function getSampleProducts() {
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost/linen-closet/';
    
    return [
        [
            'id' => 1,
            'slug' => 'classic-linen-shirt',
            'name' => 'Classic Linen Shirt',
            'price' => '$79.99',
            'original_price' => '$99.99',
            'discount_percentage' => 20,
            'image_url' => $siteUrl . 'assets/images/products/shirt-1.jpg',
            'stock_status' => 'in-stock',
            'total_sold' => 45,
            'is_featured' => true,
            'description' => 'Premium quality linen shirt for everyday comfort...',
            'in_wishlist' => false
        ],
        [
            'id' => 2,
            'slug' => 'linen-blazer',
            'name' => 'Linen Blazer',
            'price' => '$159.99',
            'original_price' => null,
            'discount_percentage' => null,
            'image_url' => $siteUrl . 'assets/images/products/blazer-1.jpg',
            'stock_status' => 'in-stock',
            'total_sold' => 32,
            'is_featured' => true,
            'description' => 'Elegant linen blazer perfect for formal occasions...',
            'in_wishlist' => true
        ],
        [
            'id' => 3,
            'slug' => 'linen-maxi-dress',
            'name' => 'Linen Maxi Dress',
            'price' => '$139.99',
            'original_price' => '$179.99',
            'discount_percentage' => 22,
            'image_url' => $siteUrl . 'assets/images/products/dress-1.jpg',
            'stock_status' => 'in-stock',
            'total_sold' => 28,
            'is_featured' => true,
            'description' => 'Beautiful maxi dress made from breathable linen...',
            'in_wishlist' => false
        ],
        [
            'id' => 4,
            'slug' => 'linen-jumpsuit',
            'name' => 'Linen Jumpsuit',
            'price' => '$129.99',
            'original_price' => null,
            'discount_percentage' => null,
            'image_url' => $siteUrl . 'assets/images/products/jumpsuit-1.jpg',
            'stock_status' => 'in-stock',
            'total_sold' => 19,
            'is_featured' => false,
            'description' => 'Stylish linen jumpsuit for a chic summer look...',
            'in_wishlist' => true
        ],
        [
            'id' => 5,
            'slug' => 'linen-t-shirt',
            'name' => 'Linen T-Shirt',
            'price' => '$49.99',
            'original_price' => '$59.99',
            'discount_percentage' => 17,
            'image_url' => $siteUrl . 'assets/images/products/tshirt-1.jpg',
            'stock_status' => 'in-stock',
            'total_sold' => 67,
            'is_featured' => true,
            'description' => 'Comfortable linen t-shirt for casual everyday wear...',
            'in_wishlist' => false
        ],
        [
            'id' => 6,
            'slug' => 'linen-skirt',
            'name' => 'Linen Skirt',
            'price' => '$89.99',
            'original_price' => null,
            'discount_percentage' => null,
            'image_url' => $siteUrl . 'assets/images/products/skirt-1.jpg',
            'stock_status' => 'in-stock',
            'total_sold' => 23,
            'is_featured' => false,
            'description' => 'Elegant linen skirt perfect for summer outings...',
            'in_wishlist' => false
        ],
        [
            'id' => 7,
            'slug' => 'linen-cardigan',
            'name' => 'Linen Cardigan',
            'price' => '$99.99',
            'original_price' => '$119.99',
            'discount_percentage' => 17,
            'image_url' => $siteUrl . 'assets/images/products/cardigan-1.jpg',
            'stock_status' => 'in-stock',
            'total_sold' => 31,
            'is_featured' => true,
            'description' => 'Lightweight linen cardigan for cool evenings...',
            'in_wishlist' => false
        ],
        [
            'id' => 8,
            'slug' => 'linen-vest',
            'name' => 'Linen Vest',
            'price' => '$59.99',
            'original_price' => null,
            'discount_percentage' => null,
            'image_url' => $siteUrl . 'assets/images/products/vest-1.jpg',
            'stock_status' => 'in-stock',
            'total_sold' => 14,
            'is_featured' => false,
            'description' => 'Versatile linen vest for layering and style...',
            'in_wishlist' => true
        ]
    ];
}
?>