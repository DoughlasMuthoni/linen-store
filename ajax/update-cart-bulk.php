<?php
// /linen-closet/ajax/update-cart-bulk.php - PRODUCTION VERSION

// Disable error display, enable logging
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    // Include files
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/Database.php';
    require_once __DIR__ . '/../includes/App.php';
    
    // Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null) {
        throw new Exception('Invalid JSON data');
    }
    
    $updates = $input['updates'] ?? [];
    
    if (empty($updates)) {
        throw new Exception('No updates provided');
    }
    
    // Initialize cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Get database
    $app = new App();
    $db = $app->getDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get product IDs for stock check
    $productIds = [];
    foreach ($updates as $update) {
        if (!empty($update['product_id'])) {
            $productIds[] = (int)$update['product_id'];
        }
    }
    
    $productIds = array_unique($productIds);
    
    // Get stock quantities if we have products
    $stockQuantities = [];
    if (!empty($productIds)) {
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        $stmt = $db->prepare("SELECT id, stock_quantity FROM products WHERE id IN ($placeholders) AND is_active = 1");
        $stmt->execute($productIds);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stockQuantities[$row['id']] = $row['stock_quantity'];
        }
    }
    
    // Apply updates
    foreach ($updates as $update) {
        $productId = isset($update['product_id']) ? (int)$update['product_id'] : 0;
        $quantity = isset($update['quantity']) ? (int)$update['quantity'] : 1;
        
        if ($productId <= 0) {
            continue;
        }
        
        if ($quantity < 1) {
            // Remove item
            unset($_SESSION['cart'][$productId]);
            continue;
        }
        
        // Check stock if available
        if (isset($stockQuantities[$productId]) && $quantity > $stockQuantities[$productId]) {
            $quantity = $stockQuantities[$productId];
        }
        
        // Update cart
        $_SESSION['cart'][$productId]['quantity'] = $quantity;
    }
    
    // Get updated cart summary
    $cartSummary = getCartSummary($_SESSION['cart'], $db);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart updated successfully',
        'cart' => $cartSummary
    ]);
    
} catch (Exception $e) {
    error_log("Bulk Cart Update Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getCartSummary($cart, $db) {
    if (empty($cart)) {
        return [
            'count' => 0,
            'subtotal' => 0,
            'shipping' => 0,
            'tax' => 0,
            'total' => 0,
            'items' => []
        ];
    }
    
    $cartCount = count($cart);
    $subtotal = 0;
    $items = [];
    
    $productIds = array_keys($cart);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $stmt = $db->prepare("SELECT id, price FROM products WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $prices = [];
    foreach ($products as $product) {
        $prices[$product['id']] = $product['price'];
    }
    
    foreach ($cart as $id => $item) {
        if (isset($prices[$id])) {
            $price = $prices[$id];
            $quantity = $item['quantity'] ?? 1;
            $itemTotal = $price * $quantity;
            $subtotal += $itemTotal;
            
            $items[] = [
                'id' => $id,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $itemTotal
            ];
        }
    }
    
    $shipping = ($subtotal >= 5000) ? 0 : 300;
    $tax = $subtotal * 0.16;
    $total = $subtotal + $shipping + $tax;
    
    return [
        'count' => $cartCount,
        'subtotal' => round($subtotal, 2),
        'shipping' => round($shipping, 2),
        'tax' => round($tax, 2),
        'total' => round($total, 2),
        'items' => $items
    ];
}

ob_end_flush();
?>