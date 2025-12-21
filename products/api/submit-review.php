<?php
// Turn off ALL error display
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON headers FIRST
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start output buffering to catch any stray output
ob_start();

try {
    // Include files
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/Database.php';
    
    // Get database connection
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Start session
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please login to submit a review.', 401);
    }
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data received.', 400);
    }
    
    $productId = (int)($input['product_id'] ?? 0);
    $rating = (int)($input['rating'] ?? 0);
    $title = trim($input['title'] ?? '');
    $review = trim($input['review'] ?? '');
    
    // Validate
    if ($productId <= 0) {
        throw new Exception('Invalid product.', 400);
    }
    
    if ($rating < 1 || $rating > 5) {
        throw new Exception('Rating must be between 1 and 5.', 400);
    }
    
    if (empty($review)) {
        throw new Exception('Please write a review.', 400);
    }
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        throw new Exception('Product not found.', 404);
    }
    
    // Check if user already reviewed
    $stmt = $pdo->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$productId, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        throw new Exception('You have already reviewed this product.', 400);
    }
    
    // Insert review
    $stmt = $pdo->prepare("
        INSERT INTO product_reviews 
        (product_id, user_id, rating, title, review, is_approved, created_at)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    
    $stmt->execute([$productId, $_SESSION['user_id'], $rating, $title, $review]);
    $reviewId = $pdo->lastInsertId();

    // Create notification for admin about new review
// Create notification for admin about new review
    try {
        // Get product name and slug for notification
        $productStmt = $pdo->prepare("SELECT name, slug FROM products WHERE id = ?");
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch();
        $productName = $product['name'] ?? 'Product';
        $productSlug = $product['slug'] ?? '';
        
        // Get user info
        $userStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $user = $userStmt->fetch();
        $userName = $user ? ($user['first_name'] . ' ' . $user['last_name']) : 'User';
        
        // Insert notification
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, link, is_read, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $notifStmt->execute([
            $_SESSION['user_id'], // User who wrote the review
            'review',
            'New Product Review',
            $userName . ' reviewed "' . $productName . '" with ' . $rating . ' stars',
            $productSlug ? '/products/detail.php?slug=' . $productSlug . '#reviews' : '/products',
        ]);
        
    } catch (Exception $e) {
        // Don't fail review if notification fails
        error_log('Notification creation error: ' . $e->getMessage());
    }
    
    // Clear output buffer
    ob_end_clean();
    
    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully!',
        'review_id' => $reviewId
    ]);
    
} catch (Exception $e) {
    // Clear any output
    ob_end_clean();
    
    // Set HTTP status code
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    
    // Send error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;