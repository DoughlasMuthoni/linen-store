<?php
// /linen-closet/products/router.php

$slug = $_GET['slug'] ?? '';

if (!empty($slug)) {
    require_once 'detail.php';
} else {
    require_once 'index.php';
}

// In your main index.php routing, add:
if ($page === 'admin') {
    $adminPage = $action ?: 'dashboard';
    $adminFile = "admin/{$adminPage}.php";
    
    if (file_exists($adminFile)) {
        require_once $adminFile;
    } else {
        // Show admin 404 or redirect to dashboard
        header('Location: ' . SITE_URL . 'admin/dashboard');
        exit();
    }
}
?>