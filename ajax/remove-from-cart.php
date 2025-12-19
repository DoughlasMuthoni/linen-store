<?php
// /linen-closet/ajax/remove-from-cart.php - PRODUCTION VERSION

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
    
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    
    if ($productId <= 0) {
        throw new Exception('Invalid product ID');
    }
    
    // Initialize cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Get database connection
    $app = new App();
    $db = $app->getDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Remove item from cart
    $removed = false;
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
        $removed = true;
    }
    
    if ($removed) {
        // Get updated cart summary
        $cartSummary = getCartSummary($_SESSION['cart'], $db);
        
        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart',
            'cart' => $cartSummary
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Item not found in cart'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Remove from Cart Error: " . $e->getMessage());
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