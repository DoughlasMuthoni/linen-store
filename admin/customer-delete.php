<?php
// /linen-closet/admin/customer-delete.php

// Include necessary files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Check if user is admin
if (!$app->isLoggedIn() || !$app->isAdmin()) {
    $app->redirect('auth/login');
}

// Get customer ID from URL
$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customerId) {
    $app->setFlashMessage('error', 'Customer ID is required');
    $app->redirect('admin/customers');
}

// Fetch customer details for confirmation
$stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ? AND is_admin = 0");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    $app->setFlashMessage('error', 'Customer not found');
    $app->redirect('admin/customers');
}

// Delete customer (this will cascade delete orders if foreign key constraints are set)
try {
    // First, check if customer has orders
    $orderCount = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $orderCount->execute([$customerId]);
    $hasOrders = $orderCount->fetchColumn() > 0;
    
    if ($hasOrders) {
        // Option 1: Delete customer and all their orders (cascade delete)
        $db->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$customerId]);
    }
    
    // Delete customer
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$customerId]);
    
    $customerName = htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name']));
    $app->setFlashMessage('success', 'Customer "' . $customerName . '" has been deleted successfully');
    
} catch (Exception $e) {
    $app->setFlashMessage('error', 'Error deleting customer: ' . $e->getMessage());
}

$app->redirect('admin/customers');
?>