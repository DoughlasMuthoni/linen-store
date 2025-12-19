<?php
// /linen-closet/includes/App.php

class App {
    private $db;
    
    public function __construct() {
        // Start session if not already started
        $this->startSession();
        require_once 'config.php';
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Start session with proper configuration
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Session configuration for security
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerate session ID periodically for security
            $this->regenerateSessionId();
        }
    }
    
    /**
     * Regenerate session ID periodically (every 30 minutes)
     */
    private function regenerateSessionId() {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        }
    }
    
    /**
     * Safe session regeneration that handles output buffering
     */
    public function regenerateSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Store session data
            $sessionData = $_SESSION;
            
            // Close current session
            session_write_close();
            
            // Destroy old session
            session_destroy();
            
            // Start new session
            session_start();
            
            // Restore session data
            $_SESSION = $sessionData;
            
            // Update regeneration time
            $_SESSION['last_regeneration'] = time();
            
            return true;
        }
        return false;
    }
    
    // CSRF Token Generation
    public function generateCSRFToken() {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + CSRF_TOKEN_EXPIRY);
        
        $stmt = $this->db->prepare("INSERT INTO csrf_tokens (token, user_id, expires_at) VALUES (?, ?, ?)");
        $userId = $_SESSION['user_id'] ?? null;
        $stmt->execute([$token, $userId, $expires]);
        
        return $token;
    }
    
    // CSRF Token Validation
    public function validateCSRFToken($token) {
        $stmt = $this->db->prepare("SELECT * FROM csrf_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();
        
        if ($tokenData) {
            // Clean up used token
            $this->cleanupCSRFTokens();
            return true;
        }
        return false;
    }
    
    // Clean expired tokens
    private function cleanupCSRFTokens() {
        $stmt = $this->db->prepare("DELETE FROM csrf_tokens WHERE expires_at <= NOW()");
        $stmt->execute();
    }
    
    // Input sanitization
    public function sanitize($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Safe redirect function that handles headers already sent
     */
    public function redirect($url = '') {
        // Clean any output buffer first
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // If URL is relative, prepend SITE_URL
        if (strpos($url, 'http') !== 0 && strpos($url, '/') !== 0) {
            $url = SITE_URL . ltrim($url, '/');
        }
        
        // Check if headers are already sent
        if (headers_sent($filename, $linenum)) {
            // Log the issue for debugging
            error_log("Headers already sent in $filename on line $linenum");
            
            // Use JavaScript redirect as fallback
            echo '<script>';
            echo 'window.location.href = "' . htmlspecialchars($url, ENT_QUOTES) . '";';
            echo '</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        } else {
            // Use PHP header redirect
            header('Location: ' . $url);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
        }
        
        // Ensure no further output
        exit();
    }
    
    // Get database instance
    public function getDB() {
        return $this->db;
    }

    // CSRF Field helper
    public function csrfField() {
        if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
    }

    public function verifyCsrfToken() {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Use hash_equals for timing attack protection
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    
    // Set flash message

    public function setFlashMessage($type, $message) {
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];
    }
    // Get flash message
    public function getFlashMessage() {
        if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            
            // Ensure message has required structure
            if (!isset($message['type']) || !isset($message['message'])) {
                unset($_SESSION['flash_message']);
                return null;
            }
            
            // Handle timestamp (for backward compatibility)
            if (isset($message['timestamp']) && is_numeric($message['timestamp'])) {
                // Auto-expire flash messages after 10 seconds
                if (time() - $message['timestamp'] > 10) {
                    unset($_SESSION['flash_message']);
                    return null;
                }
            }
            
            unset($_SESSION['flash_message']);
            return $message;
        }
        return null;
    }
    
    // Display flash message as HTML
    public function displayFlashMessage() {
        $message = $this->getFlashMessage();
        if ($message) {
            $type = $message['type'];
            $alertClass = $type === 'error' ? 'danger' : $type;
            
            return '<div class="alert alert-' . htmlspecialchars($alertClass) . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($message['message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
        return '';
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    // Check if user is admin
    public function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
    
    // Require login
    public function requireLogin($redirectTo = 'auth/login') {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            $this->setFlashMessage('warning', 'Please log in to access this page.');
            $this->redirect($redirectTo);
        }
    }
    
    // Require admin
    public function requireAdmin($redirectTo = 'account') {
        if (!$this->isAdmin()) {
            $this->setFlashMessage('error', 'Administrator privileges required.');
            $this->redirect($redirectTo);
        }
    }
    
    // Get current user ID
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Safe logout with proper session handling
     */
    public function logout($redirectTo = '') {
        // Store user name for flash message
        $userName = $_SESSION['user_name'] ?? 'User';
        
        // Clear remember token
        if (isset($_COOKIE['remember_token'])) {
            try {
                $stmt = $this->db->prepare("DELETE FROM user_tokens WHERE token = ?");
                $stmt->execute([$_COOKIE['remember_token']]);
            } catch (Exception $e) {
                // Continue on error
            }
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Destroy current session
        $_SESSION = array();
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Set flash message in next session
        $nextUrl = $redirectTo ?: '';
        $params = http_build_query([
            'logout_success' => 1,
            'user' => urlencode($userName)
        ]);
        
        // Redirect with logout success parameters
        $this->redirect($nextUrl . (empty($nextUrl) ? '?' . $params : ''));
    }
    
    /**
     * Initialize logout sequence (for confirmation pages)
     */
    public function initiateLogout($confirm = false) {
        if ($confirm) {
            $this->logout();
        } else {
            // Show confirmation page
            $this->redirect('auth/logout');
        }
    }

    // Add this to your App.php class
    public function generateSlug($string) {
        $slug = strtolower(trim($string));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
    // Add this method to your App class
    public function getCurrentUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            $db = $this->getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        }
        
        return null;
    }
}
?>