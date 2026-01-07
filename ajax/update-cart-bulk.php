<?php
// /linen-closet/ajax/update-cart.php - WITH TAX SETTINGS

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
        
        // Get tax settings from database
        $taxEnabled = '1'; // Default: enabled
        $taxRate = 16.0;   // Default: 16%
        
        try {
            // Include database connection
            if (file_exists(__DIR__ . '/../includes/config.php')) {
                require_once __DIR__ . '/../includes/config.php';
                
                if (file_exists(__DIR__ . '/../includes/Database.php') && 
                    file_exists(__DIR__ . '/../includes/App.php')) {
                    require_once __DIR__ . '/../includes/Database.php';
                    require_once __DIR__ . '/../includes/App.php';
                    
                    $app = new App();
                    $db = $app->getDB();
                    
                    if ($db) {
                        // Get tax settings
                        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('tax_enabled', 'tax_rate')");
                        $stmt->execute();
                        
                        while ($row = $stmt->fetch()) {
                            if ($row['setting_key'] == 'tax_enabled') {
                                $taxEnabled = $row['setting_value'] ?? '1';
                            } elseif ($row['setting_key'] == 'tax_rate') {
                                $taxRate = floatval($row['setting_value'] ?? 16);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Use defaults if database fails
            error_log("Tax settings error: " . $e->getMessage());
        }
        
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
        
        // Calculate tax based on settings
        if ($taxEnabled == '1') {
            $tax = $subtotal * ($taxRate / 100);
        } else {
            $tax = 0;
        }
        
        $total = $subtotal + $shipping + $tax;
        
        $response['success'] = true;
        $response['message'] = 'Cart updated successfully';
        $response['cart'] = [
            'count' => $cartCount,
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'tax_enabled' => $taxEnabled,
            'tax_rate' => $taxRate
        ];
        
        // Include items for debugging if requested
        if (isset($input['debug']) && $input['debug'] == true) {
            $response['cart']['items'] = $_SESSION['cart'];
        }
    } else {
        $response['message'] = 'Item not found in cart';
    }
    
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;