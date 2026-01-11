<?php
// /linen-closet/ajax/add-multiple-to-cart.php - UPDATED VERSION

// Turn OFF display errors for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Initialize response
$response = [
    'success' => false, 
    'message' => 'Unknown error', 
    'cart_count' => 0,
    'added_count' => 0,
    'added_products' => [],
    'cart' => null
];

try {
    // Get POST data
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception('No input received');
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    $productIds = $input['product_ids'] ?? [];
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
    
    if (empty($productIds)) {
        throw new Exception('No products specified');
    }
    
    if ($quantity < 1) {
        throw new Exception('Invalid quantity');
    }
    
    // Initialize cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $cart = &$_SESSION['cart'];
    
    // ============================================
    // TRY TO GET DATABASE CONNECTION
    // ============================================
    $db = null;
    try {
        // Try to include database files
        $configFile = __DIR__ . '/../includes/config.php';
        $dbFile = __DIR__ . '/../includes/Database.php';
        
        if (file_exists($configFile) && file_exists($dbFile)) {
            require_once $configFile;
            require_once $dbFile;
            
            $db = Database::getInstance()->getConnection();
        }
    } catch (Exception $dbError) {
        // Database error - continue without database validation
        error_log("Database error in add-multiple-to-cart: " . $dbError->getMessage());
        // We'll add products anyway, but without stock validation
    }
    
    // Validate product IDs
    $validProductIds = array_map('intval', array_filter($productIds, 'is_numeric'));
    $validProductIds = array_unique($validProductIds);
    
    if (empty($validProductIds)) {
        throw new Exception('No valid product IDs provided');
    }
    
    // ============================================
    // FETCH PRODUCT INFO FROM DATABASE (IF AVAILABLE)
    // ============================================
    $productInfo = [];
    $productPrices = [];
    
    if ($db) {
        try {
            $placeholders = str_repeat('?,', count($validProductIds) - 1) . '?';
            $stmt = $db->prepare("
                SELECT 
                    p.id,
                    p.name,
                    p.price,
                    p.sku,
                    COALESCE(SUM(pv.stock_quantity), 0) as total_stock,
                    COUNT(pv.id) as variant_count
                FROM products p
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                WHERE p.id IN ($placeholders) 
                  AND p.is_active = 1
                GROUP BY p.id
            ");
            
            $stmt->execute($validProductIds);
            $productInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create lookup arrays
            foreach ($productInfo as $product) {
                $productPrices[$product['id']] = $product['price'] ?? 100;
            }
        } catch (Exception $e) {
            error_log("Product info fetch error: " . $e->getMessage());
        }
    }
    
    // ============================================
    // ADD PRODUCTS TO CART
    // ============================================
    $addedCount = 0;
    $addedProducts = [];
    
    foreach ($validProductIds as $productId) {
        // Check if product exists in database info
        $productData = null;
        foreach ($productInfo as $p) {
            if ($p['id'] == $productId) {
                $productData = $p;
                break;
            }
        }
        
        $hasVariants = $productData['variant_count'] ?? 0 > 0;
        $totalStock = $productData['total_stock'] ?? 999; // Default high stock if no DB
        $productPrice = $productData['price'] ?? ($productPrices[$productId] ?? 100);
        
        // Determine max quantity (with stock validation if DB available)
        $maxQuantity = $quantity;
        if ($db && $productData && $totalStock > 0) {
            $maxQuantity = min($quantity, $totalStock);
        }
        
        if ($maxQuantity > 0) {
            // Create cart key
            $cartKey = $productId . ($hasVariants ? '_var' : '_simple');
            
            // Check if already in cart
            if (isset($cart[$cartKey])) {
                // Update existing quantity
                $newQuantity = $cart[$cartKey]['quantity'] + $maxQuantity;
                
                // Check stock again if we have DB info
                if ($db && $productData && $totalStock > 0 && $newQuantity > $totalStock) {
                    // Can't add more than available stock
                    $maxQuantity = max(0, $totalStock - $cart[$cartKey]['quantity']);
                    if ($maxQuantity <= 0) {
                        // Skip this product - already at max stock
                        continue;
                    }
                    $newQuantity = $cart[$cartKey]['quantity'] + $maxQuantity;
                }
                
                $cart[$cartKey]['quantity'] = $newQuantity;
            } else {
                // Add new item
                $cart[$cartKey] = [
                    'product_id' => $productId,
                    'quantity' => $maxQuantity,
                    'price' => $productPrice,
                    'name' => $productData['name'] ?? 'Product ' . $productId,
                    'sku' => $productData['sku'] ?? '',
                    'has_variants' => $hasVariants,
                    'added_at' => time()
                ];
            }
            
            $addedCount++;
            $addedProducts[] = [
                'id' => $productId,
                'name' => $productData['name'] ?? 'Product ' . $productId,
                'quantity' => $maxQuantity,
                'has_variants' => $hasVariants,
                'price' => $productPrice
            ];
        }
    }
    
    if ($addedCount === 0) {
        throw new Exception('None of the selected products could be added to cart');
    }
    
    // ============================================
    // CALCULATE UPDATED CART TOTALS
    // ============================================
    $cartCount = 0;
    $subtotal = 0;
    
    foreach ($cart as $item) {
        $itemQuantity = $item['quantity'] ?? 1;
        $itemPrice = $item['price'] ?? 0;
        
        $cartCount += $itemQuantity;
        $subtotal += $itemPrice * $itemQuantity;
    }
    
    // Calculate shipping and tax
    $shipping = ($subtotal >= 5000) ? 0 : 300;
    $taxRate = 16.0; // Default tax rate
    $tax = $subtotal * ($taxRate / 100);
    $total = $subtotal + $shipping + $tax;
    
    // Prepare cart summary
    $cartSummary = [
        'count' => $cartCount,
        'subtotal' => round($subtotal, 2),
        'shipping' => round($shipping, 2),
        'tax' => round($tax, 2),
        'total' => round($total, 2),
        'items' => array_values($cart), // Convert to indexed array
        'tax_rate' => $taxRate
    ];
    
    $response = [
        'success' => true,
        'message' => "Successfully added {$addedCount} product(s) to cart",
        'cart_count' => $cartCount,
        'added_count' => $addedCount,
        'added_products' => $addedProducts,
        'cart' => $cartSummary
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'cart_count' => isset($_SESSION['cart']) ? $this->calculateCartCount($_SESSION['cart']) : 0,
        'added_count' => 0,
        'added_products' => [],
        'cart' => null
    ];
    
    error_log("Add Multiple to Cart Error: " . $e->getMessage());
}

// Helper function to calculate cart count
function calculateCartCount($cart) {
    $count = 0;
    if (is_array($cart)) {
        foreach ($cart as $item) {
            $count += $item['quantity'] ?? 1;
        }
    }
    return $count;
}

// Ensure we only output JSON
$json = json_encode($response);
if ($json === false) {
    // Fallback minimal JSON
    $json = json_encode([
        'success' => false,
        'message' => 'System error',
        'cart_count' => 0,
        'added_count' => 0,
        'added_products' => []
    ]);
}

echo $json;
exit;