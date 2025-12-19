<?php
// /linen-closet/ajax/calculate-shipping.php - PRODUCTION VERSION

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
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null) {
        throw new Exception('Invalid JSON data');
    }
    
    $postalCode = trim($input['postal_code'] ?? '');
    $subtotal = floatval($input['subtotal'] ?? 0);
    
    if (empty($postalCode)) {
        throw new Exception('Please enter a postal code');
    }
    
    // Validate postal code format (Kenyan example: 00100)
    if (!preg_match('/^\d{5}$/', $postalCode)) {
        throw new Exception('Please enter a valid 5-digit postal code');
    }
    
    // Get database connection
    $app = new App();
    $db = $app->getDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if postal code exists in database (if you have a shipping_zones table)
    // For now, use a calculation based on postal code prefix
    
    $prefix = substr($postalCode, 0, 3);
    
    // Shipping zones configuration
    $shippingZones = [
        '001' => ['name' => 'Nairobi CBD', 'rate' => 200, 'free_over' => 3000, 'days' => 1],
        '002' => ['name' => 'Westlands', 'rate' => 250, 'free_over' => 4000, 'days' => 1],
        '005' => ['name' => 'Karen/Langata', 'rate' => 300, 'free_over' => 5000, 'days' => 2],
        '006' => ['name' => 'Mombasa Island', 'rate' => 500, 'free_over' => 8000, 'days' => 3],
        '010' => ['name' => 'Nakuru', 'rate' => 400, 'free_over' => 6000, 'days' => 2],
        '020' => ['name' => 'Kisumu', 'rate' => 450, 'free_over' => 6000, 'days' => 3],
    ];
    
    // Default zone
    $defaultZone = ['name' => 'Other Areas', 'rate' => 600, 'free_over' => 10000, 'days' => 5];
    
    $zone = $shippingZones[$prefix] ?? $defaultZone;
    
    // Calculate shipping
    $shipping = ($subtotal >= $zone['free_over']) ? 0 : $zone['rate'];
    
    echo json_encode([
        'success' => true,
        'area' => $zone['name'],
        'shipping' => $shipping,
        'estimated_days' => $zone['days'],
        'free_over' => $zone['free_over'],
        'message' => $shipping === 0 
            ? 'Free shipping to ' . $zone['name'] 
            : 'Shipping to ' . $zone['name'] . ': Ksh ' . number_format($shipping, 2)
    ]);
    
} catch (Exception $e) {
    error_log("Calculate Shipping Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
?>