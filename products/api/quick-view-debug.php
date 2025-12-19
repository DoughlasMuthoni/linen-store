<?php
// /linen-closet/products/api/quick-view-debug.php

// Enable all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

try {
    echo "Starting quick view debug...\n";
    
    // Get product ID
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    echo "Product ID: " . $productId . "\n";
    
    if (!$productId || $productId <= 0) {
        echo "Invalid product ID\n";
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Valid product ID is required',
            'product_id' => $productId
        ]);
        exit;
    }
    
    // Test file paths
    echo "Testing file paths...\n";
    $baseDir = dirname(__DIR__);
    $configPath = $baseDir . '/includes/config.php';
    $databasePath = $baseDir . '/includes/Database.php';
    
    echo "Base dir: " . $baseDir . "\n";
    echo "Config path: " . $configPath . "\n";
    echo "Database path: " . $databasePath . "\n";
    
    // Check if files exist
    if (!file_exists($configPath)) {
        throw new Exception("Config file not found at: " . $configPath);
    }
    if (!file_exists($databasePath)) {
        throw new Exception("Database file not found at: " . $databasePath);
    }
    
    echo "Files exist, loading...\n";
    
    // Load files
    require_once $configPath;
    require_once $databasePath;
    
    echo "Files loaded successfully\n";
    
    // Test database connection
    echo "Testing database connection...\n";
    $db = Database::getInstance()->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "Database connected successfully\n";
    
    // Test a simple query
    echo "Testing simple query...\n";
    $testQuery = $db->query("SELECT 1 as test_value");
    if ($testQuery) {
        $result = $testQuery->fetch(PDO::FETCH_ASSOC);
        echo "Test query result: " . json_encode($result) . "\n";
    } else {
        echo "Test query failed\n";
    }
    
    // Check if products table exists
    echo "Checking products table...\n";
    $tableCheck = $db->query("SHOW TABLES LIKE 'products'");
    if ($tableCheck && $tableCheck->rowCount() > 0) {
        echo "Products table exists\n";
    } else {
        echo "Products table does NOT exist\n";
    }
    
    // Try to get product
    echo "Fetching product with ID: " . $productId . "\n";
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    
    if (!$stmt) {
        $errorInfo = $db->errorInfo();
        throw new Exception("Failed to prepare query: " . json_encode($errorInfo));
    }
    
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo "Product not found\n";
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Product not found',
            'product_id' => $productId
        ]);
        exit;
    }
    
    echo "Product found: " . ($product['name'] ?? 'No name') . "\n";
    
    // Return success response
    $response = [
        'success' => true,
        'debug' => [
            'product_found' => true,
            'product_id' => $productId,
            'product_name' => $product['name'] ?? 'Unknown',
            'steps_completed' => 'All steps completed successfully'
        ],
        'product' => [
            'id' => (int)$product['id'],
            'name' => $product['name'] ?? '',
            'price' => isset($product['price']) ? (float)$product['price'] : 0,
            'slug' => $product['slug'] ?? ''
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Debug error occurred',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

exit;