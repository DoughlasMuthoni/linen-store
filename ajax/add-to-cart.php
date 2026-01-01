<?php
// /linen-closet/ajax/add-to-cart.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'cart_count' => 0,
    'subtotal' => 0,
    'cart' => [
        'count' => 0,
        'items' => []
    ]
];

try {
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $cart = &$_SESSION['cart'];

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
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

    // Validate product exists and is active
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, name, price, stock_quantity FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('Product not found or inactive');
    }

    // If variant_id is provided, check variant stock and get variant price
    $variantPrice = $product['price']; // Default to product price
    $stockQuantity = $product['stock_quantity']; // Default to product stock

    if ($variantId) {
        // Get variant details
        $variantStmt = $db->prepare("SELECT id, price, stock_quantity, size, color FROM product_variants WHERE id = ? AND product_id = ?");
        $variantStmt->execute([$variantId, $productId]);
        $variant = $variantStmt->fetch();
        
        if ($variant) {
            // Use variant price and stock
            $variantPrice = $variant['price'];
            $stockQuantity = $variant['stock_quantity'];
            
            // Use variant size/color if not provided separately
            if ($size === null && !empty($variant['size'])) {
                $size = $variant['size'];
            }
            if ($color === null && !empty($variant['color'])) {
                $color = $variant['color'];
            }
        }
    }

    // If no variant_id but size/color provided, find the variant
    if (!$variantId && ($size || $color)) {
        $sql = "SELECT id, price, stock_quantity FROM product_variants WHERE product_id = ? AND stock_quantity > 0";
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
            $variantId = $variant['id'];
            $variantPrice = $variant['price'];
            $stockQuantity = $variant['stock_quantity'];
        }
    }

    // Check stock
    if ($quantity > $stockQuantity) {
        throw new Exception('Insufficient stock. Only ' . $stockQuantity . ' items available.');
    }

    // Create a unique key for cart items that includes variant information
    $cartKey = $productId;
    if ($variantId) {
        $cartKey .= '_' . $variantId;
    } elseif ($size || $color || $material) {
        // Create a key based on selected options
        $cartKey .= '_' . md5(($size ?? '') . ($color ?? '') . ($material ?? ''));
    }

    // Add to cart or update quantity
    if (isset($cart[$cartKey])) {
        $cart[$cartKey]['quantity'] += $quantity;
    } else {
        $cart[$cartKey] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'size' => $size,
            'color' => $color,
            'material' => $material,
            'price' => $variantPrice, // Use variant price if available
            'added_at' => time()
        ];
    }

    // Calculate cart totals
    $cartCount = 0;
    $subtotal = 0;

    foreach ($cart as $key => $item) {
        $cartCount += $item['quantity'];
        $subtotal += $item['price'] * $item['quantity'];
    }

    // Build response
    $response['success'] = true;
    $response['message'] = 'Product added to cart';
    $response['cart_count'] = $cartCount;
    $response['subtotal'] = $subtotal;
    
    // Build cart items for response
    $cartItems = [];
    foreach ($cart as $key => $item) {
        // Get product name for display
        $stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch();
        
        $itemName = $product['name'];
        if ($item['size'] || $item['color']) {
            $variantText = trim(($item['size'] ?? '') . ' ' . ($item['color'] ?? ''));
            if ($variantText) {
                $itemName .= ' (' . $variantText . ')';
            }
        }
        
        $cartItems[] = [
            'cart_key' => $key,
            'product_id' => $item['product_id'],
            'variant_id' => $item['variant_id'],
            'name' => $itemName,
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'total' => $item['price'] * $item['quantity'],
            'size' => $item['size'],
            'color' => $item['color'],
            'material' => $item['material']
        ];
    }
    
    $response['cart']['count'] = $cartCount;
    $response['cart']['items'] = $cartItems;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
} catch (PDOException $e) {
    error_log("Database error in add-to-cart.php: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = 'Database error occurred. Please try again.';
}

// Send JSON response
echo json_encode($response);
exit;