<?php
// /linen-closet/account/edit.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'auth/login.php?redirect=account/edit.php');
    exit();
}

$userId = $_SESSION['user_id'];
$pageTitle = "Edit Profile";
$success = '';
$errors = [];

// Fetch user data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header('Location: ' . SITE_URL . 'auth/login.php');
        exit();
    }
} catch (Exception $e) {
    die("Error loading user data: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    if (empty($firstName)) {
        $errors[] = 'First name is required';
    }
    
    if (empty($lastName)) {
        $errors[] = 'Last name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } else {
        // Check if email is already taken by another user
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $userId]);
        if ($checkStmt->fetch()) {
            $errors[] = 'Email is already taken';
        }
    }
    
    if (empty($errors)) {
        try {
            // Update user
            $updateStmt = $db->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $updateStmt->execute([$firstName, $lastName, $email, $phone, $address, $userId]);
            
            // Update session data if needed
            $_SESSION['user_email'] = $email;
            
            $success = 'Profile updated successfully!';
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $errors[] = 'Error updating profile: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                    <i class="fas fa-home me-1"></i> Home
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>account/account.php" class="text-decoration-none">My Account</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Edit Profile</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold mb-2">Edit Profile</h1>
                    <p class="text-muted">Update your personal information</p>
                </div>
                <div>
                    <a href="<?php echo SITE_URL; ?>account/account.php" class="btn btn-outline-dark">
                        <i class="fas fa-arrow-left me-2"></i> Back to Account
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Message -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <h5><i class="fas fa-exclamation-triangle me-2"></i> Please fix the following errors:</h5>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="first_name"
                                           value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="last_name"
                                           value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" 
                                   class="form-control" 
                                   name="email"
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                   required>
                            <div class="form-text">
                                <?php if ($user['email_verified']): ?>
                                    <span class="text-success">
                                        <i class="fas fa-check-circle me-1"></i> Email verified
                                    </span>
                                <?php else: ?>
                                    <span class="text-warning">
                                        <i class="fas fa-exclamation-circle me-1"></i> Email not verified
                                    </span>
                                    <a href="<?php echo SITE_URL; ?>account/verify-email.php" class="ms-2">Verify now</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+254</span>
                                <input type="tel" 
                                       class="form-control" 
                                       name="phone"
                                       value="<?php echo htmlspecialchars(substr($user['phone'] ?? '', 3)); ?>"
                                       placeholder="700 000 000">
                            </div>
                            <div class="form-text">
                                Enter phone number without country code
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Address</label>
                            <textarea class="form-control" 
                                      name="address" 
                                      rows="3"
                                      placeholder="Enter your full address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Account Status -->
                        <div class="card border mb-4">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Account Status</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-user-check me-3 text-success"></i>
                                            <div>
                                                <small class="text-muted">Member Since</small>
                                                <div class="fw-bold"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-calendar-alt me-3 text-info"></i>
                                            <div>
                                                <small class="text-muted">Last Updated</small>
                                                <div class="fw-bold"><?php echo date('F j, Y', strtotime($user['updated_at'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo SITE_URL; ?>account/password.php" class="btn btn-outline-dark">
                                <i class="fas fa-lock me-2"></i> Change Password
                            </a>
                            <div>
                                <a href="<?php echo SITE_URL; ?>account" class="btn btn-outline-dark me-2">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-dark">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="card border-danger mt-4">
                <div class="card-header bg-danger text-white">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i> Danger Zone
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-1">Delete Account</h6>
                            <p class="text-muted small mb-0">
                                Permanently delete your account and all associated data.
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> Delete Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold">Are you sure you want to delete your account?</p>
                <p class="text-muted small mb-0">
                    This action cannot be undone. All your data including orders, addresses, and preferences will be permanently deleted.
                </p>
                
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="confirmDelete">
                    <label class="form-check-label" for="confirmDelete">
                        I understand that this action is irreversible
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="deleteOrders">
                    <label class="form-check-label" for="deleteOrders">
                        Also delete all my order history
                    </label>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                    Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.card.border-danger {
    border-width: 2px;
}

.input-group-text {
    min-width: 60px;
}

@media (max-width: 768px) {
    .display-5 {
        font-size: 2rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Phone number formatting
    const phoneInput = document.querySelector('input[name="phone"]');
    phoneInput?.addEventListener('input', function() {
        // Remove non-numeric characters
        this.value = this.value.replace(/\D/g, '');
        
        // Format as XXX XXX XXX
        if (this.value.length > 9) {
            this.value = this.value.substring(0, 9);
        }
        
        if (this.value.length > 6) {
            this.value = this.value.substring(0, 3) + ' ' + 
                         this.value.substring(3, 6) + ' ' + 
                         this.value.substring(6);
        } else if (this.value.length > 3) {
            this.value = this.value.substring(0, 3) + ' ' + this.value.substring(3);
        }
    });
    
    // Delete account confirmation
    const confirmDeleteCheckbox = document.getElementById('confirmDelete');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    
    confirmDeleteCheckbox?.addEventListener('change', function() {
        confirmDeleteBtn.disabled = !this.checked;
    });
    
    confirmDeleteBtn?.addEventListener('click', function() {
        if (confirm('Are you absolutely sure? This cannot be undone!')) {
            // In a real app, you would send delete request here
            // For now, just show a message
            alert('Account deletion requested. In a real app, this would delete your account.');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal'));
            modal.hide();
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>