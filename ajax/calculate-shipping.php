<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/Shipping.php';

$app = new App();
$db = $app->getDB();
$shippingHelper = new Shipping($db);

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $county = $data['county'] ?? 'Nairobi';
    $subtotal = (float)($data['subtotal'] ?? 0);
    
    $shippingInfo = $shippingHelper->calculateShipping($county, $subtotal);
    
    echo json_encode([
        'success' => true,
        'county' => $county,
        'cost' => $shippingInfo['cost'],
        'message' => $shippingInfo['message'],
        'zone_id' => $shippingInfo['zone_id'] ?? null,
        'delivery_days' => $shippingInfo['delivery_days'] ?? 3
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating shipping: ' . $e->getMessage(),
        'cost' => 300,
        'county' => $county ?? 'Nairobi'
    ]);
}