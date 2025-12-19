<?php
// /linen-closet/ajax/add-multiple-to-cart.php - PRODUCTION VERSION

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
    
    // Get database connection
    $app = new App();
    $db = $app->getDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Validate product IDs
    $validProductIds = array_map('intval', array_filter($productIds, 'is_numeric'));
    $validProductIds = array_unique($validProductIds);
    
    if (empty($validProductIds)) {
        throw new Exception('No valid product IDs provided');
    }
    
    // Check which products exist and are in stock
    $placeholders = str_repeat('?,', count($validProductIds) - 1) . '?';
    $stmt = $db->prepare("
        SELECT id, stock_quantity 
        FROM products 
        WHERE id IN ($placeholders) 
          AND is_active = 1 
          AND stock_quantity > 0
    ");
    
    $stmt->execute($validProductIds);
    $availableProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $addedCount = 0;
    $addedProducts = [];
    
    foreach ($availableProducts as $product) {
        $productId = $product['id'];
        $maxQuantity = min($quantity, $product['stock_quantity']);
        
        if ($maxQuantity > 0) {
            // Add to cart or update quantity
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]['quantity'] += $maxQuantity;
            } else {
                $_SESSION['cart'][$productId] = [
                    'quantity' => $maxQuantity,
                    'added_at' => time()
                ];
            }
            
            $addedCount++;
            $addedProducts[] = $productId;
        }
    }
    
    if ($addedCount === 0) {
        throw new Exception('None of the selected products are available');
    }
    
    // Get updated cart summary
    $cartSummary = getCartSummary($_SESSION['cart'], $db);
    
    echo json_encode([
        'success' => true,
        'message' => "Added {$addedCount} product(s) to cart",
        'added_count' => $addedCount,
        'added_products' => $addedProducts,
        'cart' => $cartSummary
    ]);
    
} catch (Exception $e) {
    error_log("Add Multiple to Cart Error: " . $e->getMessage());
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
            $itemQuantity = $item['quantity'] ?? 1;
            $itemTotal = $price * $itemQuantity;
            $subtotal += $itemTotal;
            
            $items[] = [
                'id' => $id,
                'quantity' => $itemQuantity,
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