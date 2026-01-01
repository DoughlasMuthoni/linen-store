<?php
// /linen-closet/ajax/update-cart.php - SIMPLIFIED VERSION

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }
    
    $cartKey = $input['cart_key'] ?? null;
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
    
    if (!$cartKey) {
        throw new Exception('Cart key is required');
    }
    
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    if ($quantity > 99) {
        $quantity = 99;
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Update item in cart
    if (isset($_SESSION['cart'][$cartKey])) {
        $_SESSION['cart'][$cartKey]['quantity'] = $quantity;
        
        // Calculate cart totals
        $cartCount = 0;
        $subtotal = 0;
        
        foreach ($_SESSION['cart'] as $item) {
            $itemQty = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $cartCount += $itemQty;
            $subtotal += $price * $itemQty;
        }
        
        $shipping = ($subtotal >= 5000) ? 0 : 300;
        $tax = $subtotal * 0.16;
        $total = $subtotal + $shipping + $tax;
        
        $response['success'] = true;
        $response['message'] = 'Cart updated successfully';
        $response['cart'] = [
            'count' => $cartCount,
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'items' => $_SESSION['cart'] // Include items for debugging
        ];
    } else {
        $response['message'] = 'Item not found in cart';
    }
    
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;