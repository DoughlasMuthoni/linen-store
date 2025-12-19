<?php
// Include necessary files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();

// Check if user is already logged in
if ($app->isLoggedIn()) {
    // Check if this was an admin access attempt
    if (isset($_SESSION['admin_access_requested']) && $_SESSION['admin_access_requested']) {
        unset($_SESSION['admin_access_requested']);
        if ($app->isAdmin()) {
            $redirect = 'admin/dashboard';
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => 'Access denied. Administrator privileges required.'
            ];
            $redirect = '';
        }
    } else {
        $redirect = $_SESSION['redirect_url'] ?? '';
        unset($_SESSION['redirect_url']);
    }
    
    echo '<script>window.location.href = "' . SITE_URL . $redirect . '";</script>';
    exit();
}

$pageTitle = "Login";

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$app->verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        try {
            $email = $app->sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                throw new Exception('Please fill in all fields');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address');
            }
            
            $db = $app->getDB();
            
            $stmt = $db->prepare("
                SELECT id, first_name, last_name, email, password, is_active, is_admin 
                FROM users 
                WHERE email = ? 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Invalid email or password');
            }
            
            if (!$user['is_active']) {
                throw new Exception('Your account has been deactivated. Please contact support.');
            }
            
            if (!password_verify($password, $user['password'])) {
                throw new Exception('Invalid email or password');
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            
            session_regenerate_id(true);
            
            // Remember me
            if (isset($_POST['remember'])) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60);
                
                $stmt = $db->prepare("
                    INSERT INTO user_tokens (user_id, token, expires_at) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    token = VALUES(token),
                    expires_at = VALUES(expires_at)
                ");
                $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', $expires)]);
                
                setcookie('remember_token', $token, $expires, '/', '', false, true);
            }
            
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Welcome back, ' . $user['first_name'] . '!'
            ];
            
            if (isset($_SESSION['admin_access_requested']) && $_SESSION['admin_access_requested']) {
                unset($_SESSION['admin_access_requested']);
                $redirect = $app->isAdmin() ? 'admin/dashboard' : '';
            } else {
                $redirect = $_SESSION['redirect_url'] ?? '';
                unset($_SESSION['redirect_url']);
            }
            
            echo '<script>window.location.href = "' . SITE_URL . $redirect . '";</script>';
            exit();
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<style>
    :root {
        --primary-color: #212529;
        --secondary-color: #f8f9fa;
        --accent-color: #6c757d;
    }
    
    /* Override body styling to allow content to flow naturally */
    .auth-page {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: calc(100vh - 120px); /* Account for header/footer */
        display: flex;
        align-items: center;
        padding: 2rem 0;
        margin-top: 0;
    }
    
    .login-container {
        max-width: 420px;
        margin: 0 auto;
        width: 100%;
    }
    
    .login-card {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
        background: white;
    }
    
    .login-header {
        background: var(--primary-color);
        color: white;
        padding: 2rem;
        text-align: center;
    }
    
    .login-body {
        padding: 2.5rem;
    }
    
    .form-control-lg {
        padding: 0.85rem 1rem;
        border-radius: 8px;
        border: 1px solid #e1e5eb;
        transition: all 0.3s ease;
    }
    
    .form-control-lg:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(33, 37, 41, 0.25);
    }
    
    .btn-login {
        background: var(--primary-color);
        border: none;
        padding: 0.85rem;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-login:hover {
        background: #000;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .divider {
        display: flex;
        align-items: center;
        text-align: center;
        color: #6c757d;
        margin: 1.5rem 0;
    }
    
    .divider::before,
    .divider::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid #dee2e6;
    }
    
    .divider span {
        padding: 0 1rem;
        font-size: 0.875rem;
    }
    
    .social-login {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .social-btn {
        padding: 0.75rem;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        background: white;
        color: #333;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .social-btn:hover {
        background: #f8f9fa;
        border-color: #adb5bd;
        transform: translateY(-1px);
    }
    
    .demo-accounts {
        background: #f8f9fa;
        border-left: 4px solid var(--primary-color);
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1.5rem;
    }
    
    .eye-btn {
        border-color: #dee2e6;
        color: #6c757d;
    }
    
    .eye-btn:hover {
        background: #f8f9fa;
        border-color: #adb5bd;
    }
    
    .remember-me {
        color: #495057;
        font-size: 0.95rem;
    }
    
    /* Ensure proper stacking */
    .auth-wrapper {
        position: relative;
        z-index: 1;
    }
</style>

<!-- Login Content Only - No HTML/BODY tags -->
<div class="auth-page">
    <div class="container auth-wrapper">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h3 class="mb-2"><i class="fas fa-tshirt"></i> <?php echo SITE_NAME; ?></h3>
                    <p class="mb-0 text-light opacity-75">Timeless Style. Pure Linen.</p>
                </div>
                
                <div class="login-body">
                    <?php if (isset($_SESSION['admin_access_requested']) && $_SESSION['admin_access_requested']): ?>
                        <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                            <i class="fas fa-shield-alt me-2"></i>
                            <strong>Admin Access Required</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['flash_message']) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                        <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show mb-3" role="alert">
                            <?php echo htmlspecialchars($_SESSION['flash_message']['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['flash_message']); ?>
                    <?php endif; ?>
                    
                    <h4 class="mb-1 text-center">Welcome Back</h4>
                    <p class="text-center text-muted mb-4">Sign in to your account</p>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" 
                                       class="form-control form-control-lg" 
                                       id="email" 
                                       name="email"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       required
                                       placeholder="you@example.com">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="password" class="form-label">Password</label>
                                <a href="<?php echo SITE_URL; ?>auth/forgot-password" class="text-decoration-none small">
                                    Forgot Password?
                                </a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       id="password" 
                                       name="password"
                                       required
                                       placeholder="Enter your password">
                                <button type="button" class="btn eye-btn" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input type="checkbox" 
                                       class="form-check-input" 
                                       id="remember" 
                                       name="remember"
                                       <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                                <label class="form-check-label remember-me" for="remember">
                                    Remember me for 30 days
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-dark btn-login w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i> Sign In
                        </button>
                        
                        <div class="divider">
                            <span>Or continue with</span>
                        </div>
                        
                        <div class="social-login">
                            <button type="button" class="social-btn">
                                <i class="fab fa-google me-2"></i> Google
                            </button>
                            <button type="button" class="social-btn">
                                <i class="fab fa-facebook-f me-2"></i> Facebook
                            </button>
                        </div>
                        
                        <div class="demo-accounts">
                            <small class="text-muted d-block mb-2">
                                <i class="fas fa-info-circle me-1"></i> Demo Accounts
                            </small>
                            <div class="row small">
                                <div class="col-6">
                                    <div class="fw-medium">Admin</div>
                                    <div>admin@linencloset.com</div>
                                </div>
                                <div class="col-6">
                                    <div class="fw-medium">User</div>
                                    <div>user@example.com</div>
                                </div>
                            </div>
                            <div class="mt-2 text-muted">Password: password123</div>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-2">
                            Don't have an account? 
                            <a href="<?php echo SITE_URL; ?>auth/register" class="text-decoration-none fw-medium">
                                Create Account
                            </a>
                        </p>
                        <p class="mb-0">
                            <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i> Back to Home
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    const icon = this.querySelector('i');
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    if (!email || !password) {
        e.preventDefault();
        alert('Please fill in all fields');
        return false;
    }
    
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        return false;
    }
});
</script>