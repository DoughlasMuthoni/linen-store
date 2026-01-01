<?php
// /linen-closet/ajax/update-cart.php - UPDATED FOR VARIANTS

// Disable error display in production, log instead
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

try {
    // Include files
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/Database.php';
    require_once __DIR__ . '/../includes/App.php';
    
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
    
    // Accept either cart_key (new) or product_id (backward compatibility)
    $cartKey = $input['cart_key'] ?? null;
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
    
    if (!$cartKey && $productId <= 0) {
        throw new Exception('Either cart_key or product_id is required');
    }
    
    if ($quantity < 1) {
        throw new Exception('Quantity must be at least 1');
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
    
    // If using cart_key (new system)
    if ($cartKey) {
        if (!isset($_SESSION['cart'][$cartKey])) {
            throw new Exception('Cart item not found');
        }
        
        $cartItem = $_SESSION['cart'][$cartKey];
        $productId = $cartItem['product_id'];
        $variantId = $cartItem['variant_id'] ?? null;
        
        // Check stock
        if ($variantId) {
            // Check variant stock
            $stmt = $db->prepare("SELECT stock_quantity FROM product_variants WHERE id = ? AND product_id = ?");
            $stmt->execute([$variantId, $productId]);
            $variant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$variant) {
                throw new Exception('Variant not found');
            }
            
            if ($quantity > $variant['stock_quantity']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Only ' . $variant['stock_quantity'] . ' items available for this variant',
                    'max_quantity' => $variant['stock_quantity']
                ]);
                exit;
            }
        } else {
            // Check product stock
            $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? AND is_active = 1");
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
        }
        
        // Update quantity
        $_SESSION['cart'][$cartKey]['quantity'] = $quantity;
        
    } else {
        // Backward compatibility: using product_id only (old system)
        if ($productId <= 0) {
            throw new Exception('Invalid product ID');
        }
        
        // Check if product exists in cart (old format)
        $found = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if (isset($item['product_id']) && $item['product_id'] == $productId && !isset($item['variant_id'])) {
                // Found simple product without variant
                $cartKey = $key;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            // Try to find by product_id only (very old format)
            if (isset($_SESSION['cart'][$productId])) {
                $cartKey = $productId;
                $found = true;
            }
        }
        
        if (!$found) {
            throw new Exception('Product not in cart');
        }
        
        // Check product stock
        $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? AND is_active = 1");
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
        
        // Update quantity
        $_SESSION['cart'][$cartKey]['quantity'] = $quantity;
    }
    
    // Get updated cart summary
    $cartSummary = getCartSummary($_SESSION['cart'], $db);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart updated successfully',
        'cart' => $cartSummary
    ]);
    
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
 * Get cart summary for new cart structure
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
    
    $cartCount = 0;
    $subtotal = 0;
    $items = [];
    
    // Get all unique product IDs from cart
    $productIds = [];
    foreach ($cart as $cartKey => $item) {
        if (isset($item['product_id'])) {
            $productIds[] = $item['product_id'];
        }
    }
    $productIds = array_unique($productIds);
    
    if (empty($productIds)) {
        return [
            'count' => 0,
            'subtotal' => 0,
            'shipping' => 0,
            'tax' => 0,
            'total' => 0,
            'items' => []
        ];
    }
    
    // Get product prices and names
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    $stmt = $db->prepare("
        SELECT 
            id, 
            name, 
            price,
            sku
        FROM products 
        WHERE id IN ($placeholders) AND is_active = 1
    ");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create product lookup
    $productLookup = [];
    foreach ($products as $product) {
        $productLookup[$product['id']] = $product;
    }
    
    // Calculate totals
    foreach ($cart as $cartKey => $item) {
        $productId = $item['product_id'] ?? null;
        $quantity = $item['quantity'] ?? 1;
        
        if ($productId && isset($productLookup[$productId])) {
            $product = $productLookup[$productId];
            $price = $item['price'] ?? $product['price'];
            $itemTotal = $price * $quantity;
            
            // Build item display name
            $itemName = $product['name'];
            $variantText = '';
            
            if (!empty($item['size']) || !empty($item['color']) || !empty($item['material'])) {
                $variantParts = [];
                if (!empty($item['size'])) $variantParts[] = 'Size: ' . htmlspecialchars($item['size']);
                if (!empty($item['color'])) $variantParts[] = 'Color: ' . htmlspecialchars($item['color']);
                if (!empty($item['material'])) $variantParts[] = 'Material: ' . htmlspecialchars($item['material']);
                
                if (!empty($variantParts)) {
                    $variantText = implode(', ', $variantParts);
                    $itemName .= ' (' . $variantText . ')';
                }
            }
            
            $cartCount += $quantity;
            $subtotal += $itemTotal;
            
            $items[] = [
                'cart_key' => $cartKey,
                'id' => $productId,
                'name' => $itemName,
                'variant_text' => $variantText,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $itemTotal,
                'size' => $item['size'] ?? null,
                'color' => $item['color'] ?? null,
                'material' => $item['material'] ?? null,
                'variant_id' => $item['variant_id'] ?? null
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