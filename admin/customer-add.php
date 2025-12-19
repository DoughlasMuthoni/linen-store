<?php
// /linen-closet/admin/customer-add.php

// ====================================================================
// 1. INCLUDES & INITIALIZATION
// ====================================================================

// Include the admin layout function FIRST
require_once __DIR__ . '/layout.php';

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

// ====================================================================
// 2. FORM HANDLING
// ====================================================================

$error = '';
$success = '';
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'password' => '',
    'confirm_password' => '',
    'is_active' => 1
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!$app->verifyCsrfToken()) {
            throw new Exception('Invalid form submission. Please try again.');
        }
        
        // Get and sanitize form data
        $formData['first_name'] = $app->sanitize($_POST['first_name'] ?? '');
        $formData['last_name'] = $app->sanitize($_POST['last_name'] ?? '');
        $formData['email'] = $app->sanitize($_POST['email'] ?? '');
        $formData['phone'] = $app->sanitize($_POST['phone'] ?? '');
        $formData['address'] = $app->sanitize($_POST['address'] ?? '');
        $formData['password'] = $_POST['password'] ?? '';
        $formData['confirm_password'] = $_POST['confirm_password'] ?? '';
        $formData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($formData['first_name'])) {
            throw new Exception('First name is required');
        }
        
        if (empty($formData['email'])) {
            throw new Exception('Email address is required');
        }
        
        if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            throw new Exception('This email address is already registered');
        }
        
        if (!empty($formData['password'])) {
            if (strlen($formData['password']) < 6) {
                throw new Exception('Password must be at least 6 characters long');
            }
            
            if ($formData['password'] !== $formData['confirm_password']) {
                throw new Exception('Passwords do not match');
            }
        } else {
            // Generate random password
            $formData['password'] = bin2hex(random_bytes(8));
        }
        
        // Hash password
        $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
        
        // Insert customer
        $stmt = $db->prepare("
            INSERT INTO users 
            (first_name, last_name, email, phone, address, password, is_active, is_admin, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
        ");
        
        $stmt->execute([
            $formData['first_name'],
            $formData['last_name'],
            $formData['email'],
            $formData['phone'],
            $formData['address'],
            $passwordHash,
            $formData['is_active']
        ]);
        
        $customerId = $db->lastInsertId();
        $success = 'Customer added successfully! Customer ID: #' . $customerId;

         // ================================================================
        // ADD NEW CUSTOMER REGISTRATION NOTIFICATION HERE
        // ================================================================
        if (class_exists('Notification')) {
            // Check if file exists and include it
            $notificationFile = __DIR__ . '/../includes/Notification.php';
            if (file_exists($notificationFile)) {
                require_once $notificationFile;
                $notification = new Notification($db);
                $notification->create(0, 'user', 'New Customer Registration', 
                    $formData['first_name'] . ' ' . $formData['last_name'] . ' has been added as a new customer.', 
                    '/admin/customers/view/' . $customerId
                );
            }
        }
        
        // Reset form for new entry
        $formData = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'password' => '',
            'confirm_password' => '',
            'is_active' => 1
        ];
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ====================================================================
// 3. BUILD CONTENT
// ====================================================================

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Add New Customer</h1>
            <p class="text-muted mb-0">Add a new customer to your store.</p>
        </div>
        <div>
            <a href="' . SITE_URL . 'admin/customers" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Customers
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    ' . $app->displayFlashMessage() . '

    <!-- Error/Success Messages -->
    ' . ($error ? '
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($error) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>' : '') . '

    ' . ($success ? '
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($success) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>' : '') . '

    <div class="row">
        <div class="col-lg-8">
            <!-- Customer Form -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Customer Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="customerForm">
                        ' . $app->csrfField() . '
                        
                        <div class="row g-3">
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="first_name" 
                                       name="first_name" 
                                       value="' . htmlspecialchars($formData['first_name']) . '" 
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="last_name" 
                                       name="last_name" 
                                       value="' . htmlspecialchars($formData['last_name']) . '">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="' . htmlspecialchars($formData['email']) . '" 
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       value="' . htmlspecialchars($formData['phone']) . '">
                            </div>
                            
                            <!-- Address -->
                            <div class="col-12">
                                <label for="address" class="form-label">Shipping Address</label>
                                <textarea class="form-control" 
                                          id="address" 
                                          name="address" 
                                          rows="3">' . htmlspecialchars($formData['address']) . '</textarea>
                            </div>
                            
                            <!-- Password -->
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       value="' . htmlspecialchars($formData['password']) . '"
                                       placeholder="Leave blank to generate random password">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       value="' . htmlspecialchars($formData['confirm_password']) . '">
                            </div>
                            
                            <!-- Status -->
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="is_active" 
                                           name="is_active" 
                                           value="1" 
                                           ' . ($formData['is_active'] ? 'checked' : '') . '>
                                    <label class="form-check-label" for="is_active">
                                        Active Account
                                    </label>
                                    <small class="text-muted d-block">Inactive customers cannot log in</small>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-dark me-2">
                                    <i class="fas fa-save me-2"></i> Save Customer
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i> Reset Form
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Instructions Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Instructions</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i> Adding Customers</h6>
                        <ul class="mb-0 ps-3">
                            <li>Fields marked with * are required</li>
                            <li>Email must be unique</li>
                            <li>If password is left blank, a random one will be generated</li>
                            <li>Customer will receive a welcome email with login details</li>
                            <li>Inactive accounts cannot log in to the store</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i> Privacy Note</h6>
                        <p class="mb-0">Customer information is protected. Ensure you have permission to add customer data.</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card shadow mt-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="' . SITE_URL . 'admin/customers" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i> View All Customers
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                            <i class="fas fa-key me-2"></i> Generate Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generatePassword() {
    // Generate a random password
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let password = "";
    for (let i = 0; i < 10; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    
    // Set the password fields
    document.getElementById("password").value = password;
    document.getElementById("confirm_password").value = password;
    
    // Show notification
    Swal.fire({
        title: "Password Generated!",
        text: "A strong password has been generated and filled in both fields.",
        icon: "success",
        timer: 2000,
        showConfirmButton: false
    });
}

// Form validation
document.getElementById("customerForm").addEventListener("submit", function(e) {
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirm_password").value;
    
    if (password && password !== confirmPassword) {
        e.preventDefault();
        Swal.fire({
            title: "Password Mismatch",
            text: "The passwords do not match. Please check and try again.",
            icon: "error",
            confirmButtonText: "OK"
        });
    }
});
</script>';

// ====================================================================
// 4. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Add New Customer');
?>