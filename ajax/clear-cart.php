<?php
// /linen-closet/ajax/clear-cart.php - PRODUCTION VERSION WITH TAX SETTINGS

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Clear cart
    $_SESSION['cart'] = [];
    
    // Get tax settings from database
    $taxEnabled = '1'; // Default: enabled
    $taxRate = 16.0;   // Default: 16%
    
    try {
        // Include database files
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
                    if ($stmt->execute()) {
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
        }
    } catch (Exception $e) {
        // Use defaults if database fails
        error_log("Tax settings error in clear-cart: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart cleared successfully',
        'cart' => [
            'count' => 0,
            'subtotal' => 0,
            'shipping' => 0,
            'tax' => 0,
            'total' => 0,
            'tax_enabled' => $taxEnabled,
            'tax_rate' => $taxRate
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Clear Cart Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to clear cart'
    ]);
}

ob_end_flush();
?>