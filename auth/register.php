<?php
// /linen-closet/auth/register.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if ($app->isLoggedIn()) {
    $app->redirect('/'); // Redirect to home if already logged in
}

$pageTitle = "Register";

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!$app->verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        try {
            // Sanitize inputs
            $first_name = $app->sanitize($_POST['first_name'] ?? '');
            $last_name = $app->sanitize($_POST['last_name'] ?? '');
            $email = $app->sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $phone = $app->sanitize($_POST['phone'] ?? '');
            $address = $app->sanitize($_POST['address'] ?? '');
            $agree_terms = isset($_POST['agree_terms']);
            
            // Validate inputs
            $errors = [];
            
            if (empty($first_name)) {
                $errors[] = 'First name is required';
            } elseif (strlen($first_name) < 2) {
                $errors[] = 'First name must be at least 2 characters';
            }
            
            if (empty($last_name)) {
                $errors[] = 'Last name is required';
            } elseif (strlen($last_name) < 2) {
                $errors[] = 'Last name must be at least 2 characters';
            }
            
            if (empty($email)) {
                $errors[] = 'Email address is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address';
            }
            
            if (empty($password)) {
                $errors[] = 'Password is required';
            } elseif (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters';
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $errors[] = 'Password must contain at least one uppercase letter';
            } elseif (!preg_match('/[a-z]/', $password)) {
                $errors[] = 'Password must contain at least one lowercase letter';
            } elseif (!preg_match('/[0-9]/', $password)) {
                $errors[] = 'Password must contain at least one number';
            }
            
            if ($password !== $confirm_password) {
                $errors[] = 'Passwords do not match';
            }
            
            if (!$agree_terms) {
                $errors[] = 'You must agree to the Terms of Service and Privacy Policy';
            }
            
            // If there are errors, throw exception
            if (!empty($errors)) {
                throw new Exception(implode('<br>', $errors));
            }
            
            // Get database connection
            $db = $app->getDB();
            
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                throw new Exception('This email is already registered. Please use a different email or <a href="' . SITE_URL . 'auth/login" class="text-decoration-none">login here</a>.');
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));
            
            // Insert user into database
            $stmt = $db->prepare("
                INSERT INTO users (
                    first_name, 
                    last_name, 
                    email, 
                    password, 
                    phone, 
                    address,
                    verification_token,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $hashed_password,
                $phone,
                $address,
                $verification_token
            ]);
            
            $user_id = $db->lastInsertId();
            
            // ================================================================
            // CREATE NOTIFICATION FOR NEW CUSTOMER REGISTRATION
            // ================================================================
            if (class_exists('Notification')) {
                $notification = new Notification($db);
                $notification->create(0, 'user', 'New Customer Registration', 
                    $first_name . ' ' . $last_name . ' has registered as a new customer.', 
                    '/admin/customers/view/' . $user_id
                );
            }
            // ================================================================
            
            // Send welcome email (simulated for now)
            // In production, implement actual email sending
            $verification_link = SITE_URL . "auth/verify?token=" . $verification_token;
            
            // Log the email (for development)
            error_log("Registration email to: $email");
            error_log("Verification link: $verification_link");
            
            // Regenerate CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Auto-login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['is_admin'] = false;
            
            // Set success flash message
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Account created successfully! Welcome to Linen Closet.'
            ];
            
            // ================================================================
            // REDIRECT TO HOME PAGE INSTEAD OF ACCOUNT PAGE
            // ================================================================
            $app->redirect(''); // Redirect to home page
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <!-- Flash Messages from previous page -->
            <?php if (isset($_SESSION['flash_message']) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['flash_message']['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>
            
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <!-- Logo/Brand -->
                    <div class="text-center mb-4">
                        <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                            <h3 class="fw-bold text-dark mb-0">Linen Closet</h3>
                            <small class="text-muted">Timeless Style. Pure Linen.</small>
                        </a>
                    </div>
                    
                    <h2 class="text-center mb-1">Create Your Account</h2>
                    <p class="text-center text-muted mb-4">Join our community of linen lovers</p>
                    
                    <!-- Error Message -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Registration Form -->
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                        <?php echo $app->csrfField(); ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control form-control-lg" 
                                    id="first_name" 
                                    name="first_name"
                                    value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                                    required
                                    placeholder="Enter your first name"
                                >
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control form-control-lg" 
                                    id="last_name" 
                                    name="last_name"
                                    value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                                    required
                                    placeholder="Enter your last name"
                                >
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input 
                                type="email" 
                                class="form-control form-control-lg" 
                                id="email" 
                                name="email"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                required
                                placeholder="Enter your email address"
                            >
                            <div class="form-text">We'll never share your email with anyone else.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number (Optional)</label>
                                <input 
                                    type="tel" 
                                    class="form-control form-control-lg" 
                                    id="phone" 
                                    name="phone"
                                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                    placeholder="e.g., 555-123-4567"
                                >
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address (Optional)</label>
                            <textarea 
                                class="form-control form-control-lg" 
                                id="address" 
                                name="address"
                                rows="2"
                                placeholder="Enter your shipping address"
                            ><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input 
                                        type="password" 
                                        class="form-control form-control-lg" 
                                        id="password" 
                                        name="password"
                                        required
                                        placeholder="Create a password"
                                    >
                                    <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    Must be at least 8 characters with uppercase, lowercase, and number
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input 
                                        type="password" 
                                        class="form-control form-control-lg" 
                                        id="confirm_password" 
                                        name="confirm_password"
                                        required
                                        placeholder="Confirm your password"
                                    >
                                    <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="passwordMatch" class="form-text"></div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input 
                                    type="checkbox" 
                                    class="form-check-input" 
                                    id="agree_terms" 
                                    name="agree_terms"
                                    <?php echo isset($_POST['agree_terms']) ? 'checked' : ''; ?>
                                    required
                                >
                                <label class="form-check-label" for="agree_terms">
                                    I agree to the 
                                    <a href="<?php echo SITE_URL; ?>terms" class="text-decoration-none" target="_blank">Terms of Service</a> 
                                    and 
                                    <a href="<?php echo SITE_URL; ?>privacy" class="text-decoration-none" target="_blank">Privacy Policy</a>
                                    <span class="text-danger">*</span>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-dark btn-lg w-100 py-3 mb-3">
                            <i class="fas fa-user-plus me-2"></i> Create Account
                        </button>
                        
                        <!-- Password Strength Meter -->
                        <div class="mb-4">
                            <div class="progress" style="height: 5px;">
                                <div id="passwordStrength" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small id="strengthText" class="text-muted"></small>
                        </div>
                        
                        <!-- Divider -->
                        <div class="position-relative my-4">
                            <hr>
                            <div class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">
                                OR
                            </div>
                        </div>
                    </form>
                    
                    <!-- Alternative Actions -->
                    <div class="text-center">
                        <p class="mb-3">
                            Already have an account? 
                            <a href="<?php echo SITE_URL; ?>auth/login" class="text-decoration-none fw-bold">
                                Sign In
                            </a>
                        </p>
                        <p class="mb-0">
                            <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                                <i class="fas fa-home me-1"></i> Back to Homepage
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
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const confirmInput = document.getElementById('confirm_password');
    const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
    confirmInput.setAttribute('type', type);
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});

// Password strength checker
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('strengthText');
    
    let strength = 0;
    let text = '';
    let color = '';
    
    if (password.length >= 8) strength += 25;
    if (/[A-Z]/.test(password)) strength += 25;
    if (/[a-z]/.test(password)) strength += 25;
    if (/[0-9]/.test(password)) strength += 25;
    
    strengthBar.style.width = strength + '%';
    
    if (strength === 0) {
        text = 'Very Weak';
        color = 'danger';
    } else if (strength <= 50) {
        text = 'Weak';
        color = 'warning';
    } else if (strength <= 75) {
        text = 'Good';
        color = 'info';
    } else {
        text = 'Strong';
        color = 'success';
    }
    
    strengthBar.className = `progress-bar bg-${color}`;
    strengthText.textContent = `Password Strength: ${text}`;
    strengthText.className = `text-${color}`;
});

// Password match checker
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    const matchText = document.getElementById('passwordMatch');
    
    if (confirmPassword === '') {
        matchText.textContent = '';
        matchText.className = 'form-text';
    } else if (password === confirmPassword) {
        matchText.textContent = '✓ Passwords match';
        matchText.className = 'form-text text-success';
    } else {
        matchText.textContent = '✗ Passwords do not match';
        matchText.className = 'form-text text-danger';
    }
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const firstName = document.getElementById('first_name').value;
    const lastName = document.getElementById('last_name').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const agreeTerms = document.getElementById('agree_terms').checked;
    
    // Required fields
    if (!firstName || !lastName || !email || !password || !confirmPassword) {
        e.preventDefault();
        alert('Please fill in all required fields (marked with *)');
        return false;
    }
    
    // Email validation
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        return false;
    }
    
    // Password validation
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
        return false;
    }
    
    if (!/[A-Z]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one uppercase letter');
        return false;
    }
    
    if (!/[a-z]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one lowercase letter');
        return false;
    }
    
    if (!/[0-9]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one number');
        return false;
    }
    
    // Password match
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match');
        return false;
    }
    
    // Terms agreement
    if (!agreeTerms) {
        e.preventDefault();
        alert('You must agree to the Terms of Service and Privacy Policy');
        return false;
    }
});
</script>