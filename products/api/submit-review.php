<?php
// /linen-closet/products/api/submit-review.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/App.php';

$app = new App();
$db = $app->getDB();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $app->jsonResponse([
        'success' => false,
        'message' => 'Please login to submit a review.'
    ], 401);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!$app->validateCSRFToken($input['csrf_token'] ?? '')) {
    $app->jsonResponse([
        'success' => false,
        'message' => 'Invalid security token.'
    ], 403);
    exit;
}

$productId = (int)($input['product_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);
$title = $app->sanitize($input['title'] ?? '');
$review = $app->sanitize($input['review'] ?? '');

// Validation
if ($productId <= 0) {
    $app->jsonResponse([
        'success' => false,
        'message' => 'Invalid product.'
    ], 400);
    exit;
}

if ($rating < 1 || $rating > 5) {
    $app->jsonResponse([
        'success' => false,
        'message' => 'Rating must be between 1 and 5.'
    ], 400);
    exit;
}

if (empty($review)) {
    $app->jsonResponse([
        'success' => false,
        'message' => 'Please write a review.'
    ], 400);
    exit;
}

// Check if product exists
$productStmt = $db->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
$productStmt->execute([$productId]);
if (!$productStmt->fetch()) {
    $app->jsonResponse([
        'success' => false,
        'message' => 'Product not found.'
    ], 404);
    exit;
}

// Check if user has already reviewed this product
$existingReviewStmt = $db->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
$existingReviewStmt->execute([$productId, $_SESSION['user_id']]);
if ($existingReviewStmt->fetch()) {
    $app->jsonResponse([
        'success' => false,
        'message' => 'You have already reviewed this product.'
    ], 400);
    exit;
}

// Insert review
$stmt = $db->prepare("
    INSERT INTO product_reviews (product_id, user_id, rating, title, review, is_approved)
    VALUES (?, ?, ?, ?, ?, ?)
");

$isApproved = 1; // Set to 0 if you want to approve reviews manually

try {
    $stmt->execute([$productId, $_SESSION['user_id'], $rating, $title, $review, $isApproved]);
    
    // Update product rating stats
    $updateStmt = $db->prepare("
        UPDATE products 
        SET rating = (
            SELECT AVG(rating) 
            FROM product_reviews 
            WHERE product_id = ? 
            AND is_approved = 1
        ),
        review_count = (
            SELECT COUNT(*) 
            FROM product_reviews 
            WHERE product_id = ? 
            AND is_approved = 1
        )
        WHERE id = ?
    ");
    $updateStmt->execute([$productId, $productId, $productId]);
    
    $app->jsonResponse([
        'success' => true,
        'message' => 'Review submitted successfully.'
    ]);
    
} catch (PDOException $e) {
    error_log('Review submission error: ' . $e->getMessage());
    $app->jsonResponse([
        'success' => false,
        'message' => 'Failed to submit review. Please try again.'
    ], 500);
}
?>