<?php
// /linen-closet/ajax/remove-from-cart.php - UPDATED WITH TAXHELPER

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
    
    if (!$cartKey) {
        throw new Exception('Cart key is required');
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Remove item from cart
    if (isset($_SESSION['cart'][$cartKey])) {
        unset($_SESSION['cart'][$cartKey]);
        
        // Get database connection and tax settings
        $taxSettings = ['enabled' => '1', 'rate' => 16.0]; // Default values
        
        try {
            // Include required files
            require_once __DIR__ . '/../includes/config.php';
            require_once __DIR__ . '/../includes/Database.php';
            require_once __DIR__ . '/../includes/App.php';
            require_once __DIR__ . '/../includes/TaxHelper.php'; // Include the helper
            
            $app = new App();
            $db = $app->getDB();
            
            if ($db) {
                // Use TaxHelper to get tax settings
                $taxSettings = TaxHelper::getTaxSettings($db);
            }
        } catch (Exception $e) {
            error_log("Tax settings error in remove-from-cart: " . $e->getMessage());
        }
        
        // Count cart items and prepare items array
        $cartCount = 0;
        $subtotal = 0;
        $items = [];
        
        foreach ($_SESSION['cart'] as $key => $item) {
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $itemTotal = $price * $quantity;
            
            $cartCount += $quantity;
            $subtotal += $itemTotal;
            
            $items[] = [
                'cart_key' => $key,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $itemTotal,
                'size' => $item['size'] ?? null,
                'color' => $item['color'] ?? null,
                'material' => $item['material'] ?? null,
                'variant_id' => $item['variant_id'] ?? null
            ];
        }
        
        $shipping = ($subtotal >= 5000) ? 0 : 300;
        
        // Calculate tax using TaxHelper
        $tax = TaxHelper::calculateTax($subtotal, $taxSettings);
        $total = $subtotal + $shipping + $tax;
        
        $response['success'] = true;
        $response['message'] = 'Item removed from cart';
        $response['cart'] = [
            'count' => $cartCount,
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'tax_enabled' => $taxSettings['enabled'],
            'tax_rate' => $taxSettings['rate'],
            'items' => $items // Make sure items array is included
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