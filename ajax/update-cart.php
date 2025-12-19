<?php
// /linen-closet/ajax/update-cart.php - PRODUCTION VERSION

// Disable error display in production, log instead
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

try {
    // Include files with existence check
    $configPath = __DIR__ . '/../includes/config.php';
    $databasePath = __DIR__ . '/../includes/Database.php';
    $appPath = __DIR__ . '/../includes/App.php';
    
    if (!file_exists($configPath) || !file_exists($databasePath) || !file_exists($appPath)) {
        throw new Exception('Required include files not found');
    }
    
    require_once $configPath;
    require_once $databasePath;
    require_once $appPath;
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null) {
        throw new Exception('Invalid JSON data');
    }
    
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
    
    if ($productId <= 0 || $quantity < 1) {
        throw new Exception('Invalid product ID or quantity');
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
    
    // Check product exists and has stock
    $stmt = $db->prepare("SELECT id, price, stock_quantity FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Product not found or inactive');
    }
    
    if ($quantity > $product['stock_quantity']) {
        echo json_encode([
            'success' => false,
            'message' => 'Only ' . $product['stock_quantity'] . ' items available',
            'max_quantity' => $product['stock_quantity']
        ]);
        exit;
    }
    
    // Update cart
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] = $quantity;
        
        // Get updated cart summary
        $cartSummary = getCartSummary($_SESSION['cart'], $db);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cart updated successfully',
            'cart' => $cartSummary
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Product not in cart'
        ]);
    }
    
} catch (PDOException $e) {
    // Database error
    error_log("Cart Update PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    // General error
    error_log("Cart Update Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get cart summary
 */
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
    
    // Create price lookup
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

// Clean output
ob_end_flush();
?>