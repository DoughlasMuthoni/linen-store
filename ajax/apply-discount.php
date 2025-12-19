<?php
// /linen-closet/ajax/apply-discount.php - PRODUCTION VERSION

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
    
    $code = strtoupper(trim($input['code'] ?? ''));
    $subtotal = floatval($input['subtotal'] ?? 0);
    
    if (empty($code)) {
        throw new Exception('Please enter a discount code');
    }
    
    // Get database connection
    $app = new App();
    $db = $app->getDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check discount code in database
    $stmt = $db->prepare("
        SELECT d.* 
        FROM discounts d 
        WHERE d.code = ? 
          AND d.is_active = 1 
          AND (d.start_date IS NULL OR d.start_date <= NOW()) 
          AND (d.end_date IS NULL OR d.end_date >= NOW())
          AND (d.usage_limit IS NULL OR d.used_count < d.usage_limit)
    ");
    
    $stmt->execute([$code]);
    $discount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$discount) {
        throw new Exception('Invalid or expired discount code');
    }
    
    // Check minimum amount
    if ($subtotal < $discount['min_amount']) {
        throw new Exception('Minimum order amount for this code is Ksh ' . number_format($discount['min_amount'], 2));
    }
    
    // Calculate discount amount
    $discountAmount = 0;
    
    switch ($discount['discount_type']) {
        case 'percentage':
            $discountAmount = $subtotal * ($discount['discount_value'] / 100);
            if ($discount['max_discount'] > 0 && $discountAmount > $discount['max_discount']) {
                $discountAmount = $discount['max_discount'];
            }
            break;
            
        case 'fixed':
            $discountAmount = min($discount['discount_value'], $subtotal);
            break;
            
        case 'shipping':
            // Special handling for shipping discounts
            $discountAmount = 0; // Will be handled in checkout
            break;
            
        default:
            throw new Exception('Invalid discount type');
    }
    
    // Store discount in session
    $_SESSION['applied_discount'] = [
        'id' => $discount['id'],
        'code' => $discount['code'],
        'type' => $discount['discount_type'],
        'value' => $discount['discount_value'],
        'amount' => $discountAmount,
        'max_discount' => $discount['max_discount'] ?? null,
        'min_amount' => $discount['min_amount']
    ];
    
    // Calculate new total
    $newSubtotal = $subtotal - $discountAmount;
    $shipping = ($newSubtotal >= 5000) ? 0 : 300;
    $tax = $newSubtotal * 0.16;
    $newTotal = $newSubtotal + $shipping + $tax;
    
    echo json_encode([
        'success' => true,
        'message' => 'Discount code applied successfully!',
        'discount' => [
            'code' => $discount['code'],
            'type' => $discount['discount_type'],
            'value' => $discount['discount_value'],
            'amount' => $discountAmount
        ],
        'new_totals' => [
            'subtotal' => round($newSubtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => round($tax, 2),
            'total' => round($newTotal, 2)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Apply Discount Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
?>