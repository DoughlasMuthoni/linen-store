<?php
// /linen-closet/ajax/add-to-cart.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['product_id'] ?? null;
$quantity = intval($input['quantity'] ?? 1);
$size = $input['size'] ?? null;
$color = $input['color'] ?? null;
$material = $input['material'] ?? null;

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

// Check stock
if ($quantity > $product['stock_quantity']) {
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient stock. Only ' . $product['stock_quantity'] . ' items available.'
    ]);
    exit;
}

// Add to cart or update quantity
if (isset($cart[$productId])) {
    $cart[$productId]['quantity'] += $quantity;
} else {
    $cart[$productId] = [
        'quantity' => $quantity,
        'size' => $size,
        'color' => $color,
        'material' => $material,
        'added_at' => time()
    ];
}

// Calculate cart totals
$cartCount = count($cart);
$subtotal = 0;

foreach ($cart as $id => $item) {
    $stmt = $db->prepare("SELECT price FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    $subtotal += $product['price'] * $item['quantity'];
}

echo json_encode([
    'success' => true,
    'message' => 'Product added to cart',
    'cart_count' => $cartCount,
    'subtotal' => $subtotal,
    'cart' => [
        'count' => $cartCount,
        'items' => array_map(function($id, $item) use ($db) {
            $stmt = $db->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            return [
                'id' => $id,
                'quantity' => $item['quantity'],
                'price' => $product['price'],
                'total' => $product['price'] * $item['quantity']
            ];
        }, array_keys($cart), array_values($cart))
    ]
]);