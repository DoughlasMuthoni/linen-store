<?php
// /linen-closet/admin/customer-edit.php

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
// 2. GET CUSTOMER ID & FETCH DATA
// ====================================================================

// Get customer ID from URL
$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customerId) {
    $app->setFlashMessage('error', 'Customer ID is required');
    $app->redirect('admin/customers');
}

// Fetch customer details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 0");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    $app->setFlashMessage('error', 'Customer not found');
    $app->redirect('admin/customers');
}

// ====================================================================
// 3. FORM HANDLING
// ====================================================================

$error = '';
$success = '';
$formData = [
    'first_name' => $customer['first_name'],
    'last_name' => $customer['last_name'],
    'email' => $customer['email'],
    'phone' => $customer['phone'],
    'address' => $customer['address'],
    'password' => '',
    'confirm_password' => '',
    'is_active' => $customer['is_active']
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
        
        // Check if email already exists (excluding current customer)
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$formData['email'], $customerId]);
        if ($stmt->fetch()) {
            throw new Exception('This email address is already registered to another account');
        }
        
        if (!empty($formData['password'])) {
            if (strlen($formData['password']) < 6) {
                throw new Exception('Password must be at least 6 characters long');
            }
            
            if ($formData['password'] !== $formData['confirm_password']) {
                throw new Exception('Passwords do not match');
            }
        }
        
        // Build update query
        $updateFields = [
            'first_name' => $formData['first_name'],
            'last_name' => $formData['last_name'],
            'email' => $formData['email'],
            'phone' => $formData['phone'],
            'address' => $formData['address'],
            'is_active' => $formData['is_active'],
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $setClause = [];
        $params = [];
        
        foreach ($updateFields as $field => $value) {
            $setClause[] = "$field = ?";
            $params[] = $value;
        }
        
        // Add password update if provided
        if (!empty($formData['password'])) {
            $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
            $setClause[] = "password = ?";
            $params[] = $passwordHash;
        }
        
        // Add customer ID to params
        $params[] = $customerId;
        
        // Update customer
        $query = "UPDATE users SET " . implode(', ', $setClause) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $success = 'Customer updated successfully!';
        
        // Refresh customer data
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ====================================================================
// 4. BUILD CONTENT
// ====================================================================

$customerName = htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name']));
$customerName = $customerName ?: 'Guest User';

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Edit Customer</h1>
            <p class="text-muted mb-0">' . $customerName . '</p>
        </div>
        <div>
            <a href="' . SITE_URL . 'admin/customers" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i> Back to Customers
            </a>
            <a href="' . SITE_URL . 'admin/customer-view.php?id=' . $customerId . '" class="btn btn-outline-primary">
                <i class="fas fa-eye me-2"></i> View Customer
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
                    <h6 class="m-0 font-weight-bold text-primary">Edit Customer Information</h6>
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
                            
                            <!-- Password (Optional) -->
                            <div class="col-md-6">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       value="' . htmlspecialchars($formData['password']) . '"
                                       placeholder="Leave blank to keep current password">
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
                            
                            <!-- Account Information -->
                            <div class="col-12 mt-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Account Information</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted">Customer ID</small>
                                                <div class="fw-bold">#' . $customerId . '</div>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">Account Created</small>
                                                <div>' . date('F j, Y', strtotime($customer['created_at'])) . '</div>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">Last Login</small>
                                                <div>' . ($customer['last_login'] ? date('F j, Y g:i A', strtotime($customer['last_login'])) : 'Never') . '</div>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">Last Updated</small>
                                                <div>' . date('F j, Y', strtotime($customer['updated_at'])) . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-dark me-2">
                                    <i class="fas fa-save me-2"></i> Update Customer
                                </button>
                                <button type="reset" class="btn btn-outline-secondary me-2" onclick="resetForm()">
                                    <i class="fas fa-redo me-2"></i> Reset Changes
                                </button>
                                <a href="' . SITE_URL . 'admin/customer-view.php?id=' . $customerId . '" class="btn btn-outline-primary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
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
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="' . SITE_URL . 'admin/customer-orders.php?id=' . $customerId . '" class="btn btn-outline-primary">
                            <i class="fas fa-shopping-cart me-2"></i> View Orders
                        </a>
                        <button type="button" class="btn btn-outline-warning" onclick="resetPassword()">
                            <i class="fas fa-key me-2"></i> Reset Password
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="toggleAccountStatus()">
                            <i class="fas fa-user-slash me-2"></i> ' . ($customer['is_active'] ? 'Deactivate Account' : 'Activate Account') . '
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="card shadow mt-4 border-danger">
                <div class="card-header py-3 bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">Danger Zone</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">Once you delete a customer account, there is no going back. Please be certain.</p>
                    <button type="button" class="btn btn-outline-danger w-100" onclick="deleteCustomer()">
                        <i class="fas fa-trash me-2"></i> Delete Customer Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Store original form data
const originalFormData = {
    first_name: "' . htmlspecialchars($formData['first_name']) . '",
    last_name: "' . htmlspecialchars($formData['last_name']) . '",
    email: "' . htmlspecialchars($formData['email']) . '",
    phone: "' . htmlspecialchars($formData['phone']) . '",
    address: "' . htmlspecialchars($formData['address']) . '",
    is_active: ' . ($formData['is_active'] ? 'true' : 'false') . '
};

function resetForm() {
    document.getElementById("first_name").value = originalFormData.first_name;
    document.getElementById("last_name").value = originalFormData.last_name;
    document.getElementById("email").value = originalFormData.email;
    document.getElementById("phone").value = originalFormData.phone;
    document.getElementById("address").value = originalFormData.address;
    document.getElementById("password").value = "";
    document.getElementById("confirm_password").value = "";
    document.getElementById("is_active").checked = originalFormData.is_active;
    
    Swal.fire({
        title: "Form Reset",
        text: "All changes have been reset to original values.",
        icon: "info",
        timer: 1500,
        showConfirmButton: false
    });
}

function resetPassword() {
    Swal.fire({
        title: "Reset Password",
        html: "<p>Generate a new random password for this customer?</p>",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Generate",
        cancelButtonText: "Cancel"
    }).then((result) => {
        if (result.isConfirmed) {
            // Generate a random password
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
            let password = "";
            for (let i = 0; i < 10; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            // Set the password fields
            document.getElementById("password").value = password;
            document.getElementById("confirm_password").value = password;
            
            Swal.fire({
                title: "Password Generated!",
                html: "<p>New password: <code>" + password + "</code></p><p class=\"text-muted\">Make sure to save the form to apply changes.</p>",
                icon: "success"
            });
        }
    });
}

function toggleAccountStatus() {
    const isActive = document.getElementById("is_active").checked;
    document.getElementById("is_active").checked = !isActive;
    
    Swal.fire({
        title: "Status Changed",
        text: "Account status has been " + (!isActive ? "activated" : "deactivated"),
        icon: "success",
        timer: 1500,
        showConfirmButton: false
    });
}

function deleteCustomer() {
    Swal.fire({
        title: "Are you sure?",
        html: "<p>This will permanently delete customer <strong>' . $customerName . '</strong> and all their data.</p><p class=\"text-danger\">This action cannot be undone!</p>",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "' . SITE_URL . 'admin/customer-delete.php?id=' . $customerId . '";
        }
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
// 5. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Edit Customer: ' . $customerName);
?>