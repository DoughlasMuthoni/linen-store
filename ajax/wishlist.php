<?php
// /linen-closet/ajax/wishlist.php
// SINGLE WISHLIST API - NO CONFUSION

// Start session FIRST
session_start();

// Set JSON header
header('Content-Type: application/json');

// Initialize wishlist array in session if not exists
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Fallback to POST if JSON not available
if (!$input && isset($_POST['action'])) {
    $input = $_POST;
}

// Handle GET requests for status/check
if (!$input && isset($_GET['action'])) {
    $input = $_GET;
}

// Default response
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    // Get action
    $action = $input['action'] ?? 'status';
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    
    // Reference to wishlist array
    $wishlist = &$_SESSION['wishlist'];
    
    switch ($action) {
        case 'add':
            if ($productId > 0) {
                if (!in_array($productId, $wishlist)) {
                    $wishlist[] = $productId;
                    $response = [
                        'success' => true,
                        'in_wishlist' => true,
                        'wishlist_count' => count($wishlist),
                        'message' => 'Added to wishlist'
                    ];
                } else {
                    $response = [
                        'success' => true,
                        'in_wishlist' => true,
                        'wishlist_count' => count($wishlist),
                        'message' => 'Already in wishlist'
                    ];
                }
            }
            break;
            
        case 'remove':
            if ($productId > 0) {
                $key = array_search($productId, $wishlist);
                if ($key !== false) {
                    unset($wishlist[$key]);
                    $wishlist = array_values($wishlist); // Reindex array
                    $response = [
                        'success' => true,
                        'in_wishlist' => false,
                        'wishlist_count' => count($wishlist),
                        'message' => 'Removed from wishlist'
                    ];
                } else {
                    $response = [
                        'success' => true,
                        'in_wishlist' => false,
                        'wishlist_count' => count($wishlist),
                        'message' => 'Not in wishlist'
                    ];
                }
            }
            break;
            
        case 'toggle':
            if ($productId > 0) {
                $key = array_search($productId, $wishlist);
                if ($key !== false) {
                    // Remove it
                    unset($wishlist[$key]);
                    $wishlist = array_values($wishlist);
                    $response = [
                        'success' => true,
                        'in_wishlist' => false,
                        'wishlist_count' => count($wishlist),
                        'message' => 'Removed from wishlist'
                    ];
                } else {
                    // Add it
                    $wishlist[] = $productId;
                    $response = [
                        'success' => true,
                        'in_wishlist' => true,
                        'wishlist_count' => count($wishlist),
                        'message' => 'Added to wishlist'
                    ];
                }
            }
            break;
            
        case 'clear':
            $_SESSION['wishlist'] = [];
            $response = [
                'success' => true,
                'wishlist_count' => 0,
                'message' => 'Wishlist cleared'
            ];
            break;
            
        case 'count':
            $response = [
                'success' => true,
                'wishlist_count' => count($wishlist),
                'wishlist_items' => $wishlist
            ];
            break;
            
        case 'status':
        default:
            $response = [
                'success' => true,
                'wishlist_count' => count($wishlist),
                'wishlist_items' => $wishlist,
                'session_id' => session_id()
            ];
            break;
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
}

// Output JSON
echo json_encode($response);
exit();