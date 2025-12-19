<?php
// /linen-closet/index.php

/**
 * ====================================================================
 * MAIN APPLICATION ENTRY POINT
 * ====================================================================
 * This file handles routing, session management, and page rendering.
 */

// ====================================================================
// 1. INITIALIZATION & OUTPUT BUFFERING
// ====================================================================

// Start output buffering immediately to prevent header issues
ob_start();

// ====================================================================
// 2. SESSION MANAGEMENT
// ====================================================================

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    // Session configuration for security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
    
    // Initialize session variables if not set
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// ====================================================================
// 3. CONFIGURATION & CORE INCLUDES
// ====================================================================

// Include configuration first
require_once 'includes/config.php';
require_once 'includes/Database.php';
require_once 'includes/App.php';

$app = new App();

// ====================================================================
// 4. HANDLE LOGOUT SUCCESS (from URL parameters)
// ====================================================================

// Check for logout success in URL parameters
if (isset($_GET['logout_success']) && isset($_GET['user'])) {
    $userName = urldecode($_GET['user']);
    $app->setFlashMessage('success', "Goodbye, {$userName}. You have been logged out successfully.");
    
    // Clean the URL by redirecting without parameters
    $app->redirect('');
}

// ====================================================================
// 5. URL PARSING & ROUTING
// ====================================================================

// Parse URL for routing
$url = $_GET['url'] ?? 'home';
$url = rtrim($url, '/');
$urlParts = explode('/', $url);

// Simple routing
$page = $urlParts[0];
$action = $urlParts[1] ?? '';

// Define accessible pages
$publicPages = ['home', 'products', 'auth', 'cart'];
$protectedPages = ['checkout', 'orders', 'wishlist'];

// ====================================================================
// 6. ROUTE HANDLING
// ====================================================================

try {
    // Handle homepage separately
    if ($page === 'home' || $page === '') {
        $pageTitle = "Timeless Style. Pure Linen.";
        require_once 'includes/header.php';
        require_once 'homepage.php';
        require_once 'includes/footer.php';
        
        // Flush output buffer and exit cleanly
        ob_end_flush();
        exit();
    }

    // ===== ADMIN ROUTING =====
    if ($page === 'admin') {
        // Admin pages routing
        $adminPage = $action ?: 'dashboard';
        $adminFile = "admin/{$adminPage}.php";
        
        // Admin authentication check
        if (!$app->isLoggedIn()) {
            // Store redirect URL and redirect to main login
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            $_SESSION['admin_access_requested'] = true; // Flag for admin access attempt
            error_log("Admin access requested but not logged in. Redirecting to auth/login");
            $app->redirect('auth/login');
        }
        
        if (!$app->isAdmin()) {
            // User is logged in but not admin
            error_log("User logged in but not admin. Redirecting to account");
            $app->setFlashMessage('error', 'Access denied. Administrator privileges required.');
            $app->redirect('account');
        }
        
        // User is logged in AND is admin
        if (file_exists($adminFile)) {
            // Load admin page
            error_log("Loading admin file: $adminFile");
            require_once $adminFile;
            
            // Flush output buffer and exit
            ob_end_flush();
            exit();
        } else {
            // Admin 404 - redirect to dashboard
            error_log("Admin file not found: $adminFile. Redirecting to dashboard");
            $app->redirect('admin/dashboard');
        }
    }
    // ===== END ADMIN ROUTING =====

    // Handle routing for other pages
    if (in_array($page, $publicPages)) {
        // Public pages
        $action = $action ?: 'index';
        $filePath = "{$page}/{$action}.php";
        
        error_log("Public page requested: $filePath");
        
        if (file_exists($filePath)) {
            // Set page title if not set
            $pageTitle = $pageTitle ?? ucfirst($page);
            require_once 'includes/header.php';
            require_once $filePath;
            require_once 'includes/footer.php';
        } else {
            // If specific action file doesn't exist, try index.php in that folder
            $indexPath = "{$page}/index.php";
            if (file_exists($indexPath)) {
                $pageTitle = $pageTitle ?? ucfirst($page);
                require_once 'includes/header.php';
                require_once $indexPath;
                require_once 'includes/footer.php';
            } else {
                // Show 404
                $pageTitle = "Page Not Found";
                error_log("Page not found: $filePath");
                require_once 'includes/header.php';
                require_once 'includes/404.php';
                require_once 'includes/footer.php';
            }
        }
    } elseif (in_array($page, $protectedPages) && $app->isLoggedIn()) {
        // Protected pages for logged-in users
        $action = $action ?: 'index';
        $filePath = "{$page}/{$action}.php";
        
        error_log("Protected page requested by logged-in user: $filePath");
        
        if (file_exists($filePath)) {
            $pageTitle = $pageTitle ?? ucfirst($page);
            require_once 'includes/header.php';
            require_once $filePath;
            require_once 'includes/footer.php';
        } else {
            // Try index.php
            $indexPath = "{$page}/index.php";
            if (file_exists($indexPath)) {
                $pageTitle = $pageTitle ?? ucfirst($page);
                require_once 'includes/header.php';
                require_once $indexPath;
                require_once 'includes/footer.php';
            } else {
                // Redirect to login
                error_log("Protected page not found: $filePath");
                $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
                $app->redirect('auth/login');
            }
        }
    } elseif (in_array($page, $protectedPages) && !$app->isLoggedIn()) {
        // Redirect to login for protected pages
        error_log("Protected page requested but not logged in: $page");
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        $app->redirect('auth/login');
    } else {
        // Show 404 for unknown pages
        $pageTitle = "Page Not Found";
        error_log("Unknown page requested: $page");
        require_once 'includes/header.php';
        require_once 'includes/404.php';
        require_once 'includes/footer.php';
    }

} catch (Exception $e) {
    // Handle any unexpected errors
    error_log("Routing error: " . $e->getMessage());
    
    // Clear output buffer on error
    ob_end_clean();
    
    // Show error page
    $pageTitle = "Application Error";
    require_once 'includes/header.php';
    require_once 'includes/500.php';
    require_once 'includes/footer.php';
}

// ====================================================================
// 7. FINAL OUTPUT
// ====================================================================

// Flush the output buffer
ob_end_flush();
?>