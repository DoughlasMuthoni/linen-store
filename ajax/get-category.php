<?php
// /linen-closet/admin/ajax/get-category.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get absolute paths
$rootDir = dirname(dirname(dirname(__FILE__)));

// Start session BEFORE including App.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include files
require_once $rootDir . '/includes/config.php';
require_once $rootDir . '/includes/Database.php';
require_once $rootDir . '/includes/App.php';

$app = new App();
$db = $app->getDB();

// Check authentication WITHOUT redirecting
$isAuthenticated = false;
$isAdmin = false;

// Check session directly
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $isAuthenticated = true;
    $isAdmin = ($_SESSION['user_role'] === 'admin');
}

// Debug - you can comment this out after testing
// error_log("AJAX Auth Check: user_id=" . ($_SESSION['user_id'] ?? 'none') . ", role=" . ($_SESSION['user_role'] ?? 'none'));

if (!$isAuthenticated || !$isAdmin) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required. Please log in as admin.',
        'debug' => [
            'isAuthenticated' => $isAuthenticated,
            'isAdmin' => $isAdmin,
            'session_id' => session_id()
        ]
    ]);
    exit();
}

// Validate input
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid category ID required']);
    exit();
}

$categoryId = (int)$_GET['id'];

try {
    // Fetch category with parent info
    $stmt = $db->prepare("
        SELECT c.*, 
               p.name as parent_name
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE c.id = ?
    ");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($category) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'category' => $category
        ]);
    } else {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Category not found'
        ]);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>