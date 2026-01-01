<?php
// /linen-closet/admin/ajax/shipping_zones.php
// SIMPLIFIED VERSION - Minimal includes

// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);

// Start output buffering
ob_start();

// Set JSON header FIRST
header('Content-Type: application/json');

// Debug: Log the POST data
error_log('Shipping zones AJAX called: ' . print_r($_POST, true));

try {
    // Get POST data
    $action = $_POST['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('No action specified');
    }
    
    // Simple database connection
    $host = 'localhost';
    $dbname = 'linen_closet';
    $username = 'root';
    $password = 'mwalatvc';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'add':
            $zoneName = $_POST['zone_name'] ?? '';
            $cost = $_POST['cost'] ?? 0;
            $counties = $_POST['counties'] ?? '';
            $townsAreas = $_POST['towns_areas'] ?? ''; // Make sure this matches form field name
            
            // Debug
            error_log("Add zone - Towns areas received: " . substr($townsAreas, 0, 100));
            
            if (empty($zoneName) || empty($counties) || empty($townsAreas)) {
                throw new Exception('Zone name, counties, and towns/areas are required');
            }
            
            // First check if table has towns_areas column
            $checkColumn = $pdo->query("SHOW COLUMNS FROM shipping_zones LIKE 'towns_areas'")->fetch();
            
            if ($checkColumn) {
                // Column exists, use it
                $stmt = $pdo->prepare("
                    INSERT INTO shipping_zones 
                    (zone_name, description, cost, countries, counties, towns_areas, delivery_days, min_order_amount, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $zoneName,
                    $_POST['description'] ?? '',
                    (float)$cost,
                    $_POST['countries'] ?? '',
                    $counties,
                    $townsAreas,
                    (int)($_POST['delivery_days'] ?? 3),
                    (float)($_POST['min_order_amount'] ?? 0),
                    isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0
                ]);
            } else {
                // Column doesn't exist, use counties for towns_areas
                $stmt = $pdo->prepare("
                    INSERT INTO shipping_zones 
                    (zone_name, description, cost, countries, counties, delivery_days, min_order_amount, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $zoneName,
                    $_POST['description'] ?? '',
                    (float)$cost,
                    $_POST['countries'] ?? '',
                    $counties, // Use counties for towns_areas
                    (int)($_POST['delivery_days'] ?? 3),
                    (float)($_POST['min_order_amount'] ?? 0),
                    isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0
                ]);
                
                // Add the towns_areas column if it doesn't exist
                $pdo->exec("ALTER TABLE shipping_zones ADD COLUMN towns_areas TEXT NULL AFTER counties");
                
                // Update the new column with counties data
                $pdo->exec("UPDATE shipping_zones SET towns_areas = counties WHERE towns_areas IS NULL");
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Zone added successfully',
                'zone_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'edit':
            $zoneId = $_POST['zone_id'] ?? 0;
            $zoneName = $_POST['zone_name'] ?? '';
            $cost = $_POST['cost'] ?? 0;
            $counties = $_POST['counties'] ?? '';
            $townsAreas = $_POST['towns_areas'] ?? ''; // Make sure this matches form field name
            
            if ($zoneId <= 0) {
                throw new Exception('Invalid zone ID');
            }
            
            if (empty($zoneName) || empty($counties) || empty($townsAreas)) {
                throw new Exception('Zone name, counties, and towns/areas are required');
            }
            
            // Check if column exists
            $checkColumn = $pdo->query("SHOW COLUMNS FROM shipping_zones LIKE 'towns_areas'")->fetch();
            
            if ($checkColumn) {
                // Column exists
                $stmt = $pdo->prepare("
                    UPDATE shipping_zones SET
                    zone_name = ?,
                    description = ?,
                    cost = ?,
                    countries = ?,
                    counties = ?,
                    towns_areas = ?,
                    delivery_days = ?,
                    min_order_amount = ?,
                    is_active = ?,
                    updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $zoneName,
                    $_POST['description'] ?? '',
                    (float)$cost,
                    $_POST['countries'] ?? '',
                    $counties,
                    $townsAreas,
                    (int)($_POST['delivery_days'] ?? 3),
                    (float)($_POST['min_order_amount'] ?? 0),
                    isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0,
                    $zoneId
                ]);
            } else {
                // Column doesn't exist yet
                $pdo->exec("ALTER TABLE shipping_zones ADD COLUMN towns_areas TEXT NULL AFTER counties");
                
                $stmt = $pdo->prepare("
                    UPDATE shipping_zones SET
                    zone_name = ?,
                    description = ?,
                    cost = ?,
                    countries = ?,
                    counties = ?,
                    towns_areas = ?,
                    delivery_days = ?,
                    min_order_amount = ?,
                    is_active = ?,
                    updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $zoneName,
                    $_POST['description'] ?? '',
                    (float)$cost,
                    $_POST['countries'] ?? '',
                    $counties,
                    $townsAreas,
                    (int)($_POST['delivery_days'] ?? 3),
                    (float)($_POST['min_order_amount'] ?? 0),
                    isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0,
                    $zoneId
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Zone updated successfully'
            ]);
            break;
            
        case 'delete':
            $zoneId = $_POST['zone_id'] ?? 0;
            
            if ($zoneId <= 0) {
                throw new Exception('Invalid zone ID');
            }
            
            $stmt = $pdo->prepare("DELETE FROM shipping_zones WHERE id = ?");
            $stmt->execute([$zoneId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Zone deleted successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (PDOException $e) {
    // Database error
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // General error
    error_log('General error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
}

// Ensure no extra output
ob_end_flush();
exit;