<?php
// /linen-closet/ajax/toggle-wishlist.php

// Turn off error display
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        throw new Exception('Invalid request');
    }
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input && !empty($_POST)) {
        $input = $_POST;
    }
    
    if (!$input) {
        throw new Exception('No data received');
    }
    
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    $action = isset($input['action']) ? $input['action'] : 'toggle';
    
    if ($productId <= 0) {
        throw new Exception('Invalid product ID');
    }
    
    // Get user ID from session
    $userId = $_SESSION['user_id'] ?? 0;
    
    // Initialize session wishlist if not exists
    if (!isset($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }
    
    $wishlistItems = &$_SESSION['wishlist'];
    
    // For non-logged in users or when database fails, use session only
    $usingSessionOnly = false;
    
    if ($userId) {
        try {
            // Include files for logged-in users
            require_once __DIR__ . '/../includes/config.php';
            require_once __DIR__ . '/../includes/Database.php';
            require_once __DIR__ . '/../includes/Wishlist.php';
            
            // Initialize database and wishlist
            $db = Database::getInstance()->getConnection();
            $wishlist = new Wishlist($db, $userId);
            
            // Check current status
            $isInWishlist = $wishlist->isInWishlist($productId);
            
            // Perform action
            if ($action === 'toggle') {
                if ($isInWishlist) {
                    $wishlist->removeItem($productId);
                    $newStatus = false;
                    $message = 'Removed from wishlist';
                } else {
                    $wishlist->addItem($productId);
                    $newStatus = true;
                    $message = 'Added to wishlist';
                }
            } 
            elseif ($action === 'add' && !$isInWishlist) {
                $wishlist->addItem($productId);
                $newStatus = true;
                $message = 'Added to wishlist';
            }
            elseif ($action === 'remove' && $isInWishlist) {
                $wishlist->removeItem($productId);
                $newStatus = false;
                $message = 'Removed from wishlist';
            }
            else {
                $newStatus = $isInWishlist;
                $message = 'No change';
            }
            
            $wishlistCount = $wishlist->getCount();
            
        } catch (Exception $dbError) {
            // Database error - fallback to session
            error_log('Wishlist database error: ' . $dbError->getMessage());
            $usingSessionOnly = true;
        }
    } else {
        // User is not logged in - use session only
        $usingSessionOnly = true;
    }
    
    // Fallback to session-only mode
    if ($usingSessionOnly) {
        $isInWishlist = in_array($productId, $wishlistItems);
        
        // Perform action
        if ($action === 'toggle') {
            if ($isInWishlist) {
                // Remove from session wishlist
                $key = array_search($productId, $wishlistItems);
                if ($key !== false) {
                    unset($wishlistItems[$key]);
                    $wishlistItems = array_values($wishlistItems);
                }
                $newStatus = false;
                $message = 'Removed from wishlist (session)';
            } else {
                // Add to session wishlist
                $wishlistItems[] = $productId;
                $wishlistItems = array_values(array_unique($wishlistItems));
                $newStatus = true;
                $message = 'Added to wishlist (session)';
            }
        } 
        elseif ($action === 'add' && !$isInWishlist) {
            $wishlistItems[] = $productId;
            $wishlistItems = array_values(array_unique($wishlistItems));
            $newStatus = true;
            $message = 'Added to wishlist (session)';
        }
        elseif ($action === 'remove' && $isInWishlist) {
            $key = array_search($productId, $wishlistItems);
            if ($key !== false) {
                unset($wishlistItems[$key]);
                $wishlistItems = array_values($wishlistItems);
            }
            $newStatus = false;
            $message = 'Removed from wishlist (session)';
        }
        else {
            $newStatus = $isInWishlist;
            $message = 'No change';
        }
        
        $wishlistCount = count($wishlistItems);
    }
    
    // Clear output and send response
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'in_wishlist' => $newStatus,
        'wishlist_count' => $wishlistCount,
        'message' => $message,
        'session_count' => count($wishlistItems),
        'user_logged_in' => !empty($userId),
        'using_session' => $usingSessionOnly
    ]);
    
} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'user_logged_in' => isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])
    ]);
}

exit;