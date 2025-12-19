<?php
// /linen-closet/auth/logout.php

// Include necessary files
require_once dirname(dirname(__FILE__)) . '/includes/config.php';
require_once dirname(dirname(__FILE__)) . '/includes/App.php';

$app = new App();

// If user is not logged in, redirect to home
if (!$app->isLoggedIn()) {
    $app->redirect('');
}

// Check if this is a logout action (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    // PROCESS LOGOUT
    
    // Store user info BEFORE destroying session
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_email = $_SESSION['user_email'] ?? '';
    
    // Clear remember token
    if (isset($_COOKIE['remember_token'])) {
        // Try to delete from database
        try {
            $db = $app->getDB();
            $stmt = $db->prepare("DELETE FROM user_tokens WHERE token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);
        } catch (Exception $e) {
            // Continue even if cleanup fails
        }
        
        // Clear the cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Destroy session data
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
    
    // IMPORTANT: Do NOT call session_start() again here
    // The redirect will happen without a session, and the next page will start a new one
    
    // Use App::redirect() with flash message in URL or query param
    $redirect_url = SITE_URL . '?logout_success=1&user=' . urlencode($user_name);
    
    // Use JavaScript redirect to avoid session_start() issues
    echo '<script>window.location.href = "' . $redirect_url . '";</script>';
    exit();
}

// SHOW CONFIRMATION PAGE
$pageTitle = "Confirm Logout";
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5 text-center">
                    <!-- Logo/Brand -->
                    <div class="text-center mb-4">
                        <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                            <h3 class="fw-bold text-dark mb-0">Linen Closet</h3>
                            <small class="text-muted">Timeless Style. Pure Linen.</small>
                        </a>
                    </div>
                    
                    <div class="mb-4">
                        <i class="fas fa-sign-out-alt fa-3x text-warning"></i>
                    </div>
                    
                    <h2 class="mb-3">Logout</h2>
                    <p class="text-muted mb-4">
                        Are you sure you want to log out?<br>
                        You can sign back in anytime.
                    </p>
                    
                    <form method="POST" action="">
                        <?php echo $app->csrfField(); ?>
                        
                        <div class="d-grid gap-3">
                            <button type="submit" name="confirm_logout" value="1" 
                                    class="btn btn-warning btn-lg py-3">
                                <i class="fas fa-sign-out-alt me-2"></i> Yes, Logout
                            </button>
                            
                            <a href="<?php echo isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : SITE_URL . 'account'; ?>" 
                               class="btn btn-outline-secondary btn-lg py-3">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-muted small">
                        <p class="mb-0">
                            Logged in as: 
                            <strong><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Unknown'); ?></strong>
                        </p>
                        <p class="mb-0">
                            Account: 
                            <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Unknown'); ?></strong>
                        </p>
                    </div>
                    
                    <div class="mt-4">
                        <a href="<?php echo SITE_URL; ?>account" class="text-decoration-none">
                            <i class="fas fa-user me-1"></i> Back to Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Optional: Add confirmation for logout button
document.querySelector('form').addEventListener('submit', function(e) {
    const logoutButton = document.querySelector('button[name="confirm_logout"]');
    
    if (e.submitter === logoutButton) {
        if (!confirm('Are you sure you want to log out?')) {
            e.preventDefault();
            return false;
        }
    }
});
</script>