<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$response = ['success' => false, 'message' => '', 'reviews' => []];

// Get parameters
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if ($product_id <= 0) {
    $response['message'] = 'Invalid product ID';
    echo json_encode($response);
    exit;
}

try {
    // Fetch reviews
    $stmt = $pdo->prepare("
        SELECT 
            pr.*,
            CONCAT(u.first_name, ' ', u.last_name) as full_name,
            u.email,
            u.created_at as member_since
        FROM product_reviews pr
        LEFT JOIN users u ON pr.user_id = u.id
        WHERE pr.product_id = ? AND pr.is_approved = 1
        ORDER BY pr.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$product_id, $limit, $offset]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['reviews'] = $reviews;
    $response['count'] = count($reviews);
    
} catch (Exception $e) {
    error_log('Get reviews error: ' . $e->getMessage());
    $response['message'] = 'Error fetching reviews';
}

echo json_encode($response);
exit;