<?php
// /linen-closet/auth/logout-action.php
// Simple logout without confirmation

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once dirname(dirname(__FILE__)) . '/includes/config.php';
require_once dirname(dirname(__FILE__)) . '/includes/App.php';

$app = new App();

// Store user info
$user_name = $_SESSION['user_name'] ?? 'User';

// Clear remember token
if (isset($_COOKIE['remember_token'])) {
    try {
        $db = $app->getDB();
        $stmt = $db->prepare("DELETE FROM user_tokens WHERE token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
    } catch (Exception $e) {
        // Continue on error
    }
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Destroy session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Start new session
session_start();

// Set success message
$_SESSION['flash_message'] = [
    'type' => 'success',
    'message' => "Goodbye, $user_name. You have been logged out successfully."
];

// Regenerate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Redirect to home using App::redirect()
$app->redirect('');