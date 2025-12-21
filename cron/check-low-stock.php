<?php
// /linen-closet/cron/check-low-stock.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    // Check for low stock items
    $lowStockStmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.name, 
            p.stock_quantity, 
            p.min_stock_level,
            COALESCE((SELECT SUM(pv.stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id), p.stock_quantity) as total_stock
        FROM products p
        WHERE p.is_active = 1 
        AND p.min_stock_level > 0
        HAVING total_stock <= p.min_stock_level AND total_stock > 0
    ");
    
    $lowStockStmt->execute();
    $lowStockItems = $lowStockStmt->fetchAll();
    
    foreach ($lowStockItems as $item) {
        // Check if notification already exists for this item today
        $checkStmt = $pdo->prepare("
            SELECT id FROM notifications 
            WHERE type = 'stock' 
            AND message LIKE ?
            AND DATE(created_at) = CURDATE()
        ");
        $checkStmt->execute(['%' . $item['name'] . '%']);
        
        if (!$checkStmt->fetch()) {
            // Create low stock notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, type, title, message, link, is_read, created_at) 
                VALUES (0, 'stock', ?, ?, ?, 0, NOW())
            ");
            
            $title = 'Low Stock Alert';
            $message = $item['name'] . ' has low stock (' . $item['total_stock'] . ' left)';
            $link = '/admin/products/edit.php?id=' . $item['id'];
            
            $notifStmt->execute([$title, $message, $link]);
            
            echo "Created notification for: " . $item['name'] . "\n";
        }
    }
    
    // Check for out of stock items
    $outOfStockStmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.name,
            COALESCE((SELECT SUM(pv.stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id), p.stock_quantity) as total_stock
        FROM products p
        WHERE p.is_active = 1 
        HAVING total_stock <= 0
    ");
    
    $outOfStockStmt->execute();
    $outOfStockItems = $outOfStockStmt->fetchAll();
    
    foreach ($outOfStockItems as $item) {
        // Check if notification already exists for this item today
        $checkStmt = $pdo->prepare("
            SELECT id FROM notifications 
            WHERE type = 'stock' 
            AND message LIKE ?
            AND DATE(created_at) = CURDATE()
        ");
        $checkStmt->execute(['%' . $item['name'] . ' is out of stock%']);
        
        if (!$checkStmt->fetch()) {
            // Create out of stock notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, type, title, message, link, is_read, created_at) 
                VALUES (0, 'stock', ?, ?, ?, 0, NOW())
            ");
            
            $title = 'Out of Stock Alert';
            $message = $item['name'] . ' is out of stock';
            $link = '/admin/products/edit.php?id=' . $item['id'];
            
            $notifStmt->execute([$title, $message, $link]);
            
            echo "Created out-of-stock notification for: " . $item['name'] . "\n";
        }
    }
    
    echo "Stock check completed. " . count($lowStockItems) . " low stock items, " . count($outOfStockItems) . " out of stock items.\n";
    
} catch (Exception $e) {
    error_log('Stock check error: ' . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}