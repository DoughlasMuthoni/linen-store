<?php
// /linen-closet/ajax/add-to-cart.php - FINAL VERSION

// Turn OFF display errors for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header immediately
header('Content-Type: application/json');

// Initialize response
$response = ['success' => false, 'message' => 'Unknown error', 'cart_count' => 0];

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
    
    $productId = $input['product_id'] ?? null;
    $quantity = intval($input['quantity'] ?? 1);
    $size = $input['size'] ?? null;
    $color = $input['color'] ?? null;
    $material = $input['material'] ?? null;
    $variantId = $input['variant_id'] ?? null;
    
    if (!$productId) {
        throw new Exception('Product ID is required');
    }
    
    // Validate product ID is numeric
    if (!is_numeric($productId)) {
        throw new Exception('Invalid Product ID');
    }
    
    // Initialize cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $cart = &$_SESSION['cart'];
    
    // ============================================
    // TRY TO GET PRODUCT INFO FROM DATABASE
    // ============================================
    $productPrice = 100; // Default price if DB fails
    $variantPrice = 100;
    $stockQuantity = 999; // Default to high stock
    $finalVariantId = $variantId;
    
    try {
        // Try to include database files
        $configFile = __DIR__ . '/../includes/config.php';
        $dbFile = __DIR__ . '/../includes/Database.php';
        
        if (file_exists($configFile) && file_exists($dbFile)) {
            require_once $configFile;
            require_once $dbFile;
            
            $db = Database::getInstance()->getConnection();
            
            // Get product info (NO stock_quantity column!)
            $stmt = $db->prepare("SELECT id, name, price FROM products WHERE id = ? AND is_active = 1");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if ($product) {
                $productPrice = $product['price'];
                $variantPrice = $product['price'];
                
                // ============================================
                // VARIANT HANDLING
                // ============================================
                
                // If variant_id is provided, use it
                if ($finalVariantId) {
                    $variantStmt = $db->prepare("SELECT id, price, stock_quantity FROM product_variants WHERE id = ? AND product_id = ?");
                    $variantStmt->execute([$finalVariantId, $productId]);
                    $variant = $variantStmt->fetch();
                    
                    if ($variant) {
                        $variantPrice = $variant['price'] ?? $productPrice;
                        $stockQuantity = $variant['stock_quantity'] ?? 999;
                    }
                }
                
                // If no variant_id but size/color specified, try to find matching variant
                if (!$finalVariantId && ($size || $color)) {
                    $sql = "SELECT id, price, stock_quantity FROM product_variants WHERE product_id = ?";
                    $params = [$productId];
                    
                    if ($size && $color) {
                        $sql .= " AND size = ? AND color = ?";
                        $params[] = $size;
                        $params[] = $color;
                    } elseif ($size) {
                        $sql .= " AND size = ?";
                        $params[] = $size;
                    } elseif ($color) {
                        $sql .= " AND color = ?";
                        $params[] = $color;
                    }
                    
                    $sql .= " LIMIT 1";
                    $variantStmt = $db->prepare($sql);
                    $variantStmt->execute($params);
                    $variant = $variantStmt->fetch();
                    
                    if ($variant) {
                        $finalVariantId = $variant['id'];
                        $variantPrice = $variant['price'] ?? $productPrice;
                        $stockQuantity = $variant['stock_quantity'] ?? 999;
                    }
                }
                
                // If still no variant, get default variant
                if (!$finalVariantId) {
                    $defaultStmt = $db->prepare("
                        SELECT id, price, stock_quantity 
                        FROM product_variants 
                        WHERE product_id = ? 
                        ORDER BY is_default DESC, id ASC 
                        LIMIT 1
                    ");
                    $defaultStmt->execute([$productId]);
                    $defaultVariant = $defaultStmt->fetch();
                    
                    if ($defaultVariant) {
                        $finalVariantId = $defaultVariant['id'];
                        $variantPrice = $defaultVariant['price'] ?? $productPrice;
                        $stockQuantity = $defaultVariant['stock_quantity'] ?? 999;
                    }
                }
                
                // Check stock (only if stock is limited)
                if ($stockQuantity > 0 && $quantity > $stockQuantity) {
                    throw new Exception('Insufficient stock. Only ' . $stockQuantity . ' items available.');
                }
            }
        }
    } catch (Exception $dbError) {
        // Database error - continue with defaults
        error_log("Cart DB Error: " . $dbError->getMessage());
        // Don't throw - use default values
    }
    
    // ============================================
    // ADD TO CART
    // ============================================
    $cartKey = $productId . '_' . ($finalVariantId ?: 'default');
    if ($size || $color || $material) {
        $cartKey .= '_' . md5(($size ?: '') . ($color ?: '') . ($material ?: ''));
    }
    
    // Check if already in cart (for stock validation)
    if (isset($cart[$cartKey])) {
        $newQuantity = $cart[$cartKey]['quantity'] + $quantity;
        
        // Check stock again
        if ($stockQuantity > 0 && $newQuantity > $stockQuantity) {
            throw new Exception('Cannot add more. Maximum: ' . $stockQuantity);
        }
        
        $cart[$cartKey]['quantity'] = $newQuantity;
    } else {
        $cart[$cartKey] = [
            'product_id' => $productId,
            'variant_id' => $finalVariantId,
            'quantity' => $quantity,
            'size' => $size,
            'color' => $color,
            'material' => $material,
            'price' => $variantPrice,
            'added_at' => time()
        ];
    }
    
    // ============================================
    // CALCULATE TOTALS
    // ============================================
    $cartCount = 0;
    $subtotal = 0;
    
    foreach ($cart as $item) {
        $cartCount += $item['quantity'];
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    $response = [
        'success' => true,
        'message' => 'Product added to cart',
        'cart_count' => $cartCount,
        'subtotal' => $subtotal,
        'cart' => [
            'count' => $cartCount,
            'items' => array_values($cart) // Convert to indexed array
        ]
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'cart_count' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0
    ];
}

// Ensure we only output JSON
$json = json_encode($response);
if ($json === false) {
    // Fallback minimal JSON
    $json = json_encode([
        'success' => false,
        'message' => 'System error',
        'cart_count' => 0
    ]);
}

echo $json;
exit;