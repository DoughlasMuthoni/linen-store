<?php
// /linen-closet/admin/login.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $app->redirect('admin/dashboard');
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $app->sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $db = $app->getDB();
        
        // Check if user exists and is admin
        $stmt = $db->prepare("
            SELECT id, email, password, first_name, last_name, is_admin, is_active 
            FROM users 
            WHERE email = ? AND is_admin = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_active']) {
                // Login successful
                $app->login($user['id'], [
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'is_admin' => $user['is_admin']
                ]);
                
                $app->redirect('admin/dashboard');
            } else {
                $error = 'Your account has been deactivated. Please contact support.';
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$pageTitle = 'Admin Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <!-- Logo -->
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark">
                                <i class="fas fa-tshirt me-2"></i><?php echo SITE_NAME; ?>
                            </h2>
                            <p class="text-muted">Admin Panel</p>
                        </div>
                        
                        <!-- Error/Success Messages -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Login Form -->
                        <form method="POST" action="">
                            <?php echo $app->csrfField(); ?>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" 
                                       class="form-control form-control-lg" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required
                                       placeholder="admin@example.com">
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       id="password" 
                                       name="password" 
                                       required
                                       placeholder="••••••••">
                            </div>
                            
                            <button type="submit" class="btn btn-dark btn-lg w-100 py-3">
                                <i class="fas fa-sign-in-alt me-2"></i> Sign In
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i> Back to Store
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Security Notice -->
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Restricted Access - Authorized Personnel Only
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.admin-login-page {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.admin-login-page .card {
    border-radius: 15px;
    border: none;
}

.admin-login-page .form-control {
    border-radius: 8px;
    padding: 12px 20px;
}

.admin-login-page .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>