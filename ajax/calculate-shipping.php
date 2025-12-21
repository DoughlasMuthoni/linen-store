<?php
// /linen-closet/ajax/calculate-shipping.php - FREE SHIPPING VERSION

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
    
    // Shipping zones configuration - ALL FREE
    $shippingZones = [
        '001' => ['name' => 'Nairobi CBD', 'rate' => 0, 'free_over' => 0, 'days' => 1],
        '002' => ['name' => 'Westlands', 'rate' => 0, 'free_over' => 0, 'days' => 1],
        '005' => ['name' => 'Karen/Langata', 'rate' => 0, 'free_over' => 0, 'days' => 2],
        '006' => ['name' => 'Mombasa Island', 'rate' => 0, 'free_over' => 0, 'days' => 3],
        '010' => ['name' => 'Nakuru', 'rate' => 0, 'free_over' => 0, 'days' => 2],
        '020' => ['name' => 'Kisumu', 'rate' => 0, 'free_over' => 0, 'days' => 3],
    ];
    
    // Default zone - ALSO FREE
    $defaultZone = ['name' => 'Other Areas', 'rate' => 0, 'free_over' => 0, 'days' => 5];
    
    $zone = $shippingZones[$prefix] ?? $defaultZone;
    
    // Calculate shipping - ALWAYS FREE
    $shipping = 0; // Always free
    
    echo json_encode([
        'success' => true,
        'area' => $zone['name'],
        'shipping' => $shipping,
        'estimated_days' => $zone['days'],
        'free_over' => $zone['free_over'],
        'message' => '🎉 FREE shipping to ' . $zone['name'] . '!'
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