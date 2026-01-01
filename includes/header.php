<?php
// /linen-closet/includes/header.php

/**
 * ====================================================================
 * HEADER TEMPLATE
 * ====================================================================
 * This file should NOT start sessions - session is already started in index.php
 */

// ====================================================================
// 1. CONFIGURATION & APP INITIALIZATION
// ====================================================================

// Include configuration
require_once __DIR__ . '/config.php';

// Check if App class exists before instantiating
if (!class_exists('App')) {
    require_once __DIR__ . '/App.php';
}

$app = new App();

// ====================================================================
// 2. SESSION-BASED VARIABLES & FLASH MESSAGES
// ====================================================================

// Get flash message from session
$flashMessage = $app->getFlashMessage();

// User info (safe access with null coalescing)
$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$firstName = $_SESSION['first_name'] ?? '';
$isLoggedIn = $app->isLoggedIn();
$isAdmin = $app->isAdmin();

// ====================================================================
// 3. DATABASE CONNECTION (FOR CATEGORIES)
// ====================================================================

$db = null;
$mainCategories = [];

// Only try to get categories if Database class exists and we have a connection
if (class_exists('Database')) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Fetch main categories
        if ($db) {
            $stmt = $db->prepare("SELECT name, slug FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY name");
            $stmt->execute();
            $mainCategories = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        // Log error but don't break the page
        error_log("Category fetch error: " . $e->getMessage());
    }
}

// ====================================================================
// 4. PAGE TITLE HANDLING
// ====================================================================

$pageTitle = $pageTitle ?? 'Home';
$fullTitle = htmlspecialchars($pageTitle) . ' | ' . htmlspecialchars(SITE_NAME);

// ====================================================================
// 5. HTML OUTPUT STARTS HERE
// ====================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $fullTitle; ?></title>
    
    <!-- Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars(SITE_NAME); ?> - Timeless Style. Pure Linen.">
    <meta name="author" content="<?php echo htmlspecialchars(SITE_NAME); ?>">
    
    <!-- CSRF Token for JavaScript -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>assets/css/style.css" rel="stylesheet">
   <!-- In header.php or layout file -->
<!-- <script src="<?php echo SITE_URL; ?>assets/js/wishlist.js"></script> -->
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>assets/favicon.ico">
    
    <style>
       :root {
        --primary-color: #4361ee;
        --primary-light: #eef2ff;
        --secondary-color: #3a0ca3;
        --accent-color: #f72585;
        --dark-color: #1a1a2e;
        --light-color: #f8f9fa;
        --success-color: #4cc9f0;
        --warning-color: #f8961e;
        --danger-color: #f94144;
    }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            transition: opacity var(--transition-speed) ease;
        }
        
        .navbar-brand:hover {
            opacity: 0.8;
        }
        
        .navbar {
            box-shadow: var(--box-shadow);
            padding: 0.75rem 0;
            background: white !important;
        }
        
        .nav-link {
            font-weight: 500;
            color: var(--primary-color) !important;
            padding: 0.5rem 1rem !important;
            border-radius: var(--border-radius);
            transition: all var(--transition-speed) ease;
        }
        
        .nav-link:hover, .nav-link:focus {
            background-color: rgba(33, 37, 41, 0.05);
            transform: translateY(-1px);
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            border-radius: var(--border-radius);
            padding: 0.5rem;
            min-width: 220px;
            margin-top: 0.5rem;
        }
        
        .dropdown-item {
            padding: 0.65rem 1rem;
            border-radius: var(--border-radius);
            margin: 0.15rem 0;
            transition: all var(--transition-speed) ease;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            background-color: rgba(33, 37, 41, 0.05);
        }
        
        .search-input {
            border-radius: 25px;
            padding-left: 2.5rem;
            border: 1px solid #e1e5eb;
            transition: all var(--transition-speed) ease;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(33, 37, 41, 0.1);
        }
        
        .search-btn {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--accent-color);
            cursor: pointer;
        }
        
        .badge-notification {
            font-size: 0.7rem;
            padding: 0.25rem 0.4rem;
            min-width: 18px;
        }
        
        .btn-login {
            background: var(--primary-color);
            color: white;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all var(--transition-speed) ease;
            border: none;
        }
        
        .btn-login:hover {
            background:var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
             color: white;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .navbar-toggler {
            border: none;
            padding: 0.25rem 0.5rem;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        .mobile-search {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin: 0.5rem 0;
        }
        
        /* Flash message styles */
        .flash-message-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @media (max-width: 991.98px) {
            .navbar-collapse {
                padding: 1rem 0;
            }
            
            .nav-item {
                margin-bottom: 0.25rem;
            }
            
            .flash-message-container {
                top: 60px;
                left: 20px;
                right: 20px;
                max-width: none;
            }
        }

        
    </style>
</head>
<body>
    <?php if ($flashMessage): ?>
    <!-- Flash Message Toast -->
    <div class="flash-message-container">
        <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show shadow-sm" role="alert">
            <?php if ($flashMessage['type'] === 'success'): ?>
                <i class="fas fa-check-circle me-2"></i>
            <?php elseif ($flashMessage['type'] === 'error'): ?>
                <i class="fas fa-exclamation-circle me-2"></i>
            <?php elseif ($flashMessage['type'] === 'warning'): ?>
                <i class="fas fa-exclamation-triangle me-2"></i>
            <?php else: ?>
                <i class="fas fa-info-circle me-2"></i>
            <?php endif; ?>
            <?php echo htmlspecialchars($flashMessage['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-tshirt me-2"></i><?php echo htmlspecialchars(SITE_NAME); ?>
            </a>
            
            <!-- Desktop Search -->
            <div class="d-none d-lg-flex align-items-center flex-grow-1 mx-4 position-relative">
                <form action="<?php echo SITE_URL; ?>products/search" method="GET" class="w-100">
                    <button type="submit" class="search-btn" aria-label="Search">
                        <i class="fas fa-search"></i>
                    </button>
                    <input type="search" 
                           name="q" 
                           class="form-control search-input" 
                           placeholder="Search products, categories..." 
                           aria-label="Search"
                           value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                </form>
            </div>
            
            <!-- Right Actions -->
            <div class="d-flex align-items-center">
                <!-- Desktop Actions -->
                <div class="d-none d-lg-flex align-items-center">
                    
                    <!-- Wishlist -->
<a href="<?php echo SITE_URL; ?>account/wishlist.php" class="nav-link position-relative me-2" 
   data-bs-toggle="tooltip" title="Wishlist" aria-label="Wishlist">
    <i class="fas fa-heart fa-lg"></i>
    <?php
    $wishlistCount = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
    if ($wishlistCount > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge badge-notification bg-danger wishlist-count">
            <?php echo $wishlistCount; ?>
        </span>
    <?php else: ?>
        <span class="position-absolute top-0 start-100 translate-middle badge badge-notification bg-danger wishlist-count d-none">
            0
        </span>
    <?php endif; ?>
</a>
                    
                    <!-- In your header/navigation -->

                    <!-- Cart -->
                    <a href="<?php echo SITE_URL; ?>cart" class="nav-link position-relative me-3" 
                       data-bs-toggle="tooltip" title="Cart" aria-label="Shopping Cart">
                        <i class="fas fa-shopping-bag fa-lg"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge badge-notification bg-danger cart-count">
                            0
                        </span>
                    </a>
                    
                    <?php if($isLoggedIn): ?>
                        <!-- User Dropdown -->
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" 
                               id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                               aria-label="User menu">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($firstName, 0, 1)); ?>
                                </div>
                                <span class="d-none d-xl-inline"><?php echo htmlspecialchars($firstName); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <div class="dropdown-header px-3 py-2">
                                        <div class="fw-bold"><?php echo htmlspecialchars($userName); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($userEmail); ?></small>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>account/account.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>account/orders.php"><i class="fas fa-box me-2"></i>My Orders</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>account/wishlist.php"><i class="fas fa-heart me-2"></i>Wishlist 
    <?php if ($wishlistCount > 0): ?>
        <span class="badge bg-danger float-end"><?php echo $wishlistCount; ?></span>
    <?php endif; ?></li>
                                <?php if($isAdmin): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-success" href="<?php echo SITE_URL; ?>admin/dashboard"><i class="fas fa-shield-alt me-2"></i>Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>auth/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Login Button -->
                        <a href="<?php echo SITE_URL; ?>auth/login" class="btn btn-login" aria-label="Login">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Mobile Toggle -->
                <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" 
                        data-bs-target="#navbarContent" aria-controls="navbarContent" 
                        aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            
            <!-- Mobile Content -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- Mobile Search -->
                <div class="mobile-search d-lg-none">
                    <form action="<?php echo SITE_URL; ?>products/search" method="GET" class="d-flex">
                        <input type="search" 
                               name="q" 
                               class="form-control me-2" 
                               placeholder="Search..." 
                               aria-label="Search"
                               value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                        <button type="submit" class="btn btn-dark" aria-label="Search">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Navigation Links -->
                <ul class="navbar-nav me-auto mb-3 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pageTitle === 'Home') ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>" aria-current="page">
                            <i class="fas fa-home me-2"></i>Home
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-store me-2"></i>Shop
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>products/new"><i class="fas fa-star me-2"></i>New Arrivals</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>products/bestsellers"><i class="fas fa-fire me-2"></i>Best Sellers</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if(!empty($mainCategories)): ?>
                                <?php foreach ($mainCategories as $category): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>products?category=<?php echo urlencode($category['slug']); ?>">
                                            <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($category['name']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><span class="dropdown-item text-muted">No categories available</span></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pageTitle === 'About') ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>about_us.php">
                            <i class="fas fa-info-circle me-2"></i>About
                        </a>
                    </li>
                    
                    <!-- Mobile-only menu items -->
                    <li class="nav-item d-lg-none">
                       <!-- Mobile-only menu items -->
   <a class="nav-link <?php echo ($pageTitle === 'Wishlist') ? 'active' : ''; ?>" 
       href="<?php echo SITE_URL; ?>account/wishlist.php">
        <i class="fas fa-heart me-2"></i>Wishlist
        <?php if ($wishlistCount > 0): ?>
            <span class="badge bg-danger float-end wishlist-count"><?php echo $wishlistCount; ?></span>
        <?php else: ?>
            <span class="badge bg-danger float-end wishlist-count d-none">0</span>
        <?php endif; ?>
    </a>
                    </li>
                    
                    <li class="nav-item d-lg-none">
                        <a class="nav-link <?php echo ($pageTitle === 'Cart') ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>cart">
                            <i class="fas fa-shopping-bag me-2"></i>Cart
                            <span class="badge bg-danger float-end cart-count">0</span>
                        </a>
                    </li>
                </ul>
                
                <!-- Mobile Auth -->
                <div class="d-lg-none">
                    <?php if($isLoggedIn): ?>
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Welcome, <?php echo htmlspecialchars($firstName); ?></h6>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo SITE_URL; ?>account" class="btn btn-outline-dark">
                                        <i class="fas fa-user me-2"></i>My Account
                                    </a>
                                    <?php if($isAdmin): ?>
                                        <a href="<?php echo SITE_URL; ?>admin/dashboard" class="btn btn-success">
                                            <i class="fas fa-shield-alt me-2"></i>Admin Panel
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?php echo SITE_URL; ?>auth/logout" class="btn btn-danger">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <a href="<?php echo SITE_URL; ?>auth/login" class="btn btn-dark">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a>
                            <a href="<?php echo SITE_URL; ?>auth/register" class="btn btn-outline-dark">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="container-fluid px-0">