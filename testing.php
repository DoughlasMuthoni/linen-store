<?php
// /linen-closet/ajax/add-to-cart.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = &$_SESSION['cart'];

// Get POST data - handle both JSON and form data
$input = [];
if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

// Debug logging
error_log('Received cart data: ' . print_r($input, true));

$productId = $input['product_id'] ?? null;
$variantId = $input['variant_id'] ?? null;
$quantity = intval($input['quantity'] ?? 1);
$size = $input['size'] ?? null;
$color = $input['color'] ?? null;
$material = $input['material'] ?? null;
$variantPrice = $input['price'] ?? null;

if (!$productId) {
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required'
    ]);
    exit;
}

// Validate product exists and is active
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, name, price, stock_quantity FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode([
        'success' => false,
        'message' => 'Product not found or inactive'
    ]);
    exit;
}

// If variant is selected, get variant details
$variant = null;
$finalPrice = $product['price'];
$availableStock = $product['stock_quantity'];

if ($variantId) {
    $stmt = $db->prepare("SELECT * FROM product_variants WHERE id = ? AND product_id = ?");
    $stmt->execute([$variantId, $productId]);
    $variant = $stmt->fetch();
    
    if ($variant) {
        // Use variant price if available
        if ($variant['price'] && $variant['price'] > 0) {
            $finalPrice = $variant['price'];
        }
        
        // Use variant stock if available
        if ($variant['stock_quantity'] > 0) {
            $availableStock = $variant['stock_quantity'];
        }
    }
} elseif ($size || $color) {
    // If no variant ID but size/color is specified, try to find the variant
    $query = "SELECT * FROM product_variants WHERE product_id = ?";
    $params = [$productId];
    
    if ($size) {
        $query .= " AND size = ?";
        $params[] = $size;
    }
    
    if ($color) {
        $query .= " AND color = ?";
        $params[] = $color;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $variant = $stmt->fetch();
    
    if ($variant) {
        $variantId = $variant['id'];
        
        // Use variant price if available
        if ($variant['price'] && $variant['price'] > 0) {
            $finalPrice = $variant['price'];
        }
        
        // Use variant stock if available
        if ($variant['stock_quantity'] > 0) {
            $availableStock = $variant['stock_quantity'];
        }
    }
}

// Check stock
if ($quantity > $availableStock) {
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient stock. Only ' . $availableStock . ' items available.'
    ]);
    exit;
}

// Create a unique cart key based on product + variant combination
$cartKey = 'p_' . $productId;
if ($variantId) {
    $cartKey .= '_v_' . $variantId;
} elseif ($size || $color) {
    // Create a hash for variant combinations without database variant ID
    $variantHash = md5($size . '_' . $color . '_' . $material);
    $cartKey .= '_h_' . $variantHash;
}

// Add to cart or update quantity
if (isset($cart[$cartKey])) {
    $newQuantity = $cart[$cartKey]['quantity'] + $quantity;
    
    // Check if new quantity exceeds stock
    if ($newQuantity > $availableStock) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot add more. Only ' . ($availableStock - $cart[$cartKey]['quantity']) . ' more items available.'
        ]);
        exit;
    }
    
    $cart[$cartKey]['quantity'] = $newQuantity;
} else {
    $cart[$cartKey] = [
        'product_id' => $productId,
        'variant_id' => $variantId,
        'quantity' => $quantity,
        'size' => $size,
        'color' => $color,
        'material' => $material,
        'price' => $finalPrice,
        'added_at' => time(),
        'product_name' => $product['name']
    ];
}

// Calculate cart totals
$cartCount = 0;
$subtotal = 0;
$cartItems = [];

foreach ($cart as $key => $item) {
    $cartCount += $item['quantity'];
    $subtotal += $item['price'] * $item['quantity'];
    
    // Get additional product info
    $stmt = $db->prepare("SELECT name, sku FROM products WHERE id = ?");
    $stmt->execute([$item['product_id']]);
    $productInfo = $stmt->fetch();
    
    // Get variant info if exists
    $variantInfo = null;
    if ($item['variant_id']) {
        $stmt = $db->prepare("SELECT size, color, sku FROM product_variants WHERE id = ?");
        $stmt->execute([$item['variant_id']]);
        $variantInfo = $stmt->fetch();
    }
    
    $cartItems[] = [
        'cart_key' => $key,
        'product_id' => $item['product_id'],
        'product_name' => $productInfo['name'] ?? 'Unknown Product',
        'product_sku' => $productInfo['sku'] ?? '',
        'variant_id' => $item['variant_id'],
        'variant_size' => $item['size'] ?? ($variantInfo['size'] ?? null),
        'variant_color' => $item['color'] ?? ($variantInfo['color'] ?? null),
        'variant_sku' => $variantInfo['sku'] ?? null,
        'quantity' => $item['quantity'],
        'price' => $item['price'],
        'total' => $item['price'] * $item['quantity']
    ];
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Product added to cart successfully',
    'cart_count' => $cartCount,
    'subtotal' => number_format($subtotal, 2),
    'cart_items' => $cartItems,
    'cart_key' => $cartKey // Return the cart key for debugging
]);

// Debug output (remove in production)
error_log('Cart after add: ' . print_r($cart, true));
?>