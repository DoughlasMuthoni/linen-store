<?php
// /linen-closet/admin/test_payment_notifications.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';

$app = new App();
$db = $app->getDB();

// Check if user is admin
// session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}

echo "<h2>Testing Payment Notifications</h2>";

// Test 1: Create a test payment notification
echo "<h3>Test 1: Creating Test Payment Notification</h3>";
try {
    $result = NotificationHelper::createPaymentNotification(
        $db,
        1, // order_id
        'ORD-TEST-' . time(), // order_number
        'paid', // payment_status
        'mpesa', // payment_method
        5000.00 // amount
    );
    
    if ($result) {
        echo "<p style='color:green;'>✓ Payment notification created successfully ($result notifications)</p>";
    } else {
        echo "<p style='color:red;'>✗ Failed to create payment notification</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>Stack trace: " . $e->getTraceAsString() . "</pre>";
}

// Test 2: Check current notification counts
echo "<h3>Test 2: Current Notification Statistics</h3>";
$stmt = $db->prepare("
    SELECT 
        type,
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
    FROM notifications 
    GROUP BY type
    ORDER BY type
");
$stmt->execute();
$stats = $stmt->fetchAll();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Type</th><th>Total</th><th>Unread</th></tr>";
foreach ($stats as $stat) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($stat['type']) . "</td>";
    echo "<td>" . $stat['total'] . "</td>";
    echo "<td>" . $stat['unread'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 3: Show ALL recent notifications (not just payment)
echo "<h3>Test 3: Recent Notifications (All Types)</h3>";
$allStmt = $db->prepare("
    SELECT * FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 10
");
$allStmt->execute();
$allNotifs = $allStmt->fetchAll();

if (!empty($allNotifs)) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Type</th><th>Title</th><th>Message</th><th>User ID</th><th>Created</th></tr>";
    foreach ($allNotifs as $notif) {
        echo "<tr>";
        echo "<td>" . $notif['id'] . "</td>";
        echo "<td>" . htmlspecialchars($notif['type']) . "</td>";
        echo "<td>" . htmlspecialchars($notif['title']) . "</td>";
        echo "<td>" . htmlspecialchars($notif['message']) . "</td>";
        echo "<td>" . $notif['user_id'] . "</td>";
        echo "<td>" . $notif['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No notifications found</p>";
}

// Test 4: Check if admin users exist
echo "<h3>Test 4: Checking Admin Users</h3>";
$adminStmt = $db->prepare("SELECT id, email, is_admin FROM users WHERE is_admin = 1");
$adminStmt->execute();
$admins = $adminStmt->fetchAll();

if (!empty($admins)) {
    echo "<p>Found " . count($admins) . " admin users:</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Email</th><th>is_admin</th></tr>";
    foreach ($admins as $admin) {
        echo "<tr>";
        echo "<td>" . $admin['id'] . "</td>";
        echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
        // echo "<td>" . htmlspecialchars($admin['role']) . "</td>";
        echo "<td>" . $admin['is_admin'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>No admin users found! Notifications won't work.</p>";
    
    // Show all users for debugging
    $userStmt = $db->query("SELECT id, email, role, is_admin FROM users LIMIT 10");
    $users = $userStmt->fetchAll();
    
    echo "<p>First 10 users in database:</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Email</th><th>Role</th><th>is_admin</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "<td>" . $user['is_admin'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 5: Check PHP error log
echo "<h3>Test 5: PHP Error Log</h3>";
echo "<p>Check your error log for messages. Look for lines containing 'Notification' or 'payment'</p>";
echo "<p>Error log location: " . ini_get('error_log') . "</p>";
?>