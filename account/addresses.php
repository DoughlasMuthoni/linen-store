<?php
// /linen-closet/account/addresses.php

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
    header('Location: ' . SITE_URL . 'auth/login.php?redirect=account/addresses.php');
    exit();
}

$userId = $_SESSION['user_id'];
$pageTitle = "My Addresses";
$success = '';
$errors = [];

// Fetch user data for name and email
try {
    $stmt = $db->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
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

// Fetch addresses from user_addresses table
try {
    $addressStmt = $db->prepare("
        SELECT * FROM user_addresses 
        WHERE user_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $addressStmt->execute([$userId]);
    $addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If table doesn't exist yet, show empty array
    $addresses = [];
    error_log("Addresses table error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new address
    if (isset($_POST['add_address'])) {
        $addressTitle = trim($_POST['address_title'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $addressLine1 = trim($_POST['address_line1'] ?? '');
        $addressLine2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? 'Kenya');
        $phone = trim($_POST['phone'] ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        
        // Validation
        if (empty($addressTitle)) {
            $errors[] = 'Address title is required';
        }
        
        if (empty($fullName)) {
            $errors[] = 'Full name is required';
        }
        
        if (empty($addressLine1)) {
            $errors[] = 'Address line 1 is required';
        }
        
        if (empty($city)) {
            $errors[] = 'City is required';
        }
        
        if (empty($state)) {
            $errors[] = 'State/Province is required';
        }
        
        if (empty($postalCode)) {
            $errors[] = 'Postal code is required';
        }
        
        if (empty($phone)) {
            $errors[] = 'Phone number is required';
        }
        
        if (empty($errors)) {
            try {
                // If setting as default, unset other defaults first
                if ($isDefault) {
                    $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
                }
                
                // Insert new address
                $insertStmt = $db->prepare("
                    INSERT INTO user_addresses 
                    (user_id, address_title, full_name, address_line1, address_line2, 
                     city, state, postal_code, country, phone, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $insertStmt->execute([
                    $userId, $addressTitle, $fullName, $addressLine1, $addressLine2,
                    $city, $state, $postalCode, $country, $phone, $isDefault
                ]);
                
                $success = 'Address added successfully!';
                
                // Refresh addresses list
                $addressStmt->execute([$userId]);
                $addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $errors[] = 'Error adding address: ' . $e->getMessage();
            }
        }
    }
    
    // Update address
    elseif (isset($_POST['update_address'])) {
        $addressId = intval($_POST['address_id']);
        $addressTitle = trim($_POST['address_title'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $addressLine1 = trim($_POST['address_line1'] ?? '');
        $addressLine2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? 'Kenya');
        $phone = trim($_POST['phone'] ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        
        // Validation
        if (empty($addressTitle)) {
            $errors[] = 'Address title is required';
        }
        
        if (empty($fullName)) {
            $errors[] = 'Full name is required';
        }
        
        if (empty($addressLine1)) {
            $errors[] = 'Address line 1 is required';
        }
        
        if (empty($city)) {
            $errors[] = 'City is required';
        }
        
        if (empty($state)) {
            $errors[] = 'State/Province is required';
        }
        
        if (empty($postalCode)) {
            $errors[] = 'Postal code is required';
        }
        
        if (empty($phone)) {
            $errors[] = 'Phone number is required';
        }
        
        if (empty($errors)) {
            try {
                // If setting as default, unset other defaults first
                if ($isDefault) {
                    $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
                }
                
                // Update address
                $updateStmt = $db->prepare("
                    UPDATE user_addresses SET 
                    address_title = ?, full_name = ?, address_line1 = ?, address_line2 = ?,
                    city = ?, state = ?, postal_code = ?, country = ?, phone = ?, is_default = ?
                    WHERE id = ? AND user_id = ?
                ");
                
                $updateStmt->execute([
                    $addressTitle, $fullName, $addressLine1, $addressLine2,
                    $city, $state, $postalCode, $country, $phone, $isDefault,
                    $addressId, $userId
                ]);
                
                $success = 'Address updated successfully!';
                
                // Refresh addresses list
                $addressStmt->execute([$userId]);
                $addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $errors[] = 'Error updating address: ' . $e->getMessage();
            }
        }
    }
}

// Handle delete address
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $addressId = intval($_GET['id']);
    
    try {
        $deleteStmt = $db->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        $deleteStmt->execute([$addressId, $userId]);
        
        $success = 'Address deleted successfully!';
        
        // Refresh addresses list
        $addressStmt->execute([$userId]);
        $addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $errors[] = 'Error deleting address: ' . $e->getMessage();
    }
}

// Handle set default address
if (isset($_GET['set_default']) && isset($_GET['id'])) {
    $addressId = intval($_GET['id']);
    
    try {
        // Unset all defaults first
        $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        
        // Set new default
        $defaultStmt = $db->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
        $defaultStmt->execute([$addressId, $userId]);
        
        $success = 'Default address updated successfully!';
        
        // Refresh addresses list
        $addressStmt->execute([$userId]);
        $addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $errors[] = 'Error setting default address: ' . $e->getMessage();
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
            <li class="breadcrumb-item active" aria-current="page">My Addresses</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold mb-2">My Addresses</h1>
                    <p class="text-muted">Manage your shipping and billing addresses</p>
                </div>
                <div>
                    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                        <i class="fas fa-plus me-2"></i> Add New Address
                    </button>
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
    
    <!-- Addresses Grid -->
    <?php if (!empty($addresses)): ?>
        <div class="row">
            <?php foreach ($addresses as $address): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm h-100 <?php echo $address['is_default'] ? 'border-dark border-2' : ''; ?>">
                        <div class="card-header bg-white py-3 border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0">
                                    <i class="fas fa-<?php echo $address['address_title'] === 'Home' ? 'home' : 
                                                       ($address['address_title'] === 'Office' ? 'briefcase' : 'map-marker-alt'); ?> me-2"></i>
                                    <?php echo htmlspecialchars($address['address_title']); ?>
                                </h5>
                                <?php if ($address['is_default']): ?>
                                    <span class="badge bg-dark">Default</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="mb-4">
                                <address class="mb-0">
                                    <strong><?php echo htmlspecialchars($address['full_name']); ?></strong><br>
                                    <?php echo htmlspecialchars($address['address_line1']); ?><br>
                                    <?php if ($address['address_line2']): ?>
                                        <?php echo htmlspecialchars($address['address_line2']); ?><br>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($address['city']); ?>, 
                                    <?php echo htmlspecialchars($address['state']); ?><br>
                                    <?php echo htmlspecialchars($address['postal_code']); ?><br>
                                    <?php echo htmlspecialchars($address['country']); ?><br>
                                    <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($address['phone']); ?>
                                </address>
                            </div>
                            
                            <div class="mt-auto">
                                <div class="d-flex gap-2">
                                    <?php if (!$address['is_default']): ?>
                                        <a href="?set_default=1&id=<?php echo $address['id']; ?>" 
                                           class="btn btn-outline-dark btn-sm">
                                            <i class="fas fa-star me-1"></i> Set Default
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-dark btn-sm edit-address-btn"
                                            data-address-id="<?php echo $address['id']; ?>"
                                            data-address-title="<?php echo htmlspecialchars($address['address_title']); ?>"
                                            data-full-name="<?php echo htmlspecialchars($address['full_name']); ?>"
                                            data-address-line1="<?php echo htmlspecialchars($address['address_line1']); ?>"
                                            data-address-line2="<?php echo htmlspecialchars($address['address_line2'] ?? ''); ?>"
                                            data-city="<?php echo htmlspecialchars($address['city']); ?>"
                                            data-state="<?php echo htmlspecialchars($address['state']); ?>"
                                            data-postal-code="<?php echo htmlspecialchars($address['postal_code']); ?>"
                                            data-country="<?php echo htmlspecialchars($address['country']); ?>"
                                            data-phone="<?php echo htmlspecialchars($address['phone']); ?>"
                                            data-is-default="<?php echo $address['is_default']; ?>">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </button>
                                    <a href="?delete=1&id=<?php echo $address['id']; ?>" 
                                       class="btn btn-outline-danger btn-sm"
                                       onclick="return confirm('Delete this address?')">
                                        <i class="fas fa-trash me-1"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="empty-icon mb-4">
                        <i class="fas fa-map-marker-alt fa-4x text-muted"></i>
                    </div>
                    <h3 class="fw-bold mb-3">No addresses yet</h3>
                    <p class="lead text-muted mb-4">
                        Add your first address to make checkout faster!
                    </p>
                    <button class="btn btn-dark btn-lg" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                        <i class="fas fa-plus me-2"></i> Add Your First Address
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Shipping Tips -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-info-circle me-2"></i> Address Tips
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Shipping Tips</h6>
                            <div class="alert alert-info small mb-0">
                                <i class="fas fa-lightbulb me-2"></i>
                                For faster delivery, ensure your address includes:
                                <ul class="mb-0 mt-2">
                                    <li>Building/House number</li>
                                    <li>Street name</li>
                                    <li>Nearest landmark</li>
                                    <li>Correct phone number for delivery calls</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">How addresses work</h6>
                            <ul class="small text-muted mb-0">
                                <li class="mb-2">Save multiple addresses for home, office, etc.</li>
                                <li class="mb-2">Set one as default for quick checkout</li>
                                <li class="mb-2">Choose any address at checkout</li>
                                <li>Update addresses anytime</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Address Modal -->
<div class="modal fade" id="addAddressModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-plus me-2"></i> Add New Address
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="add_address" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Address Title <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="address_title"
                                       placeholder="e.g., Home, Office, Parents' House"
                                       required>
                                <div class="form-text">A nickname for this address</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="full_name"
                                       value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address Line 1 <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               name="address_line1"
                               placeholder="Street address, P.O. Box, Company name"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address Line 2</label>
                        <input type="text" 
                               class="form-control" 
                               name="address_line2"
                               placeholder="Apartment, suite, unit, building, floor, etc.">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">City <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="city"
                                       placeholder="e.g., Nairobi"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">State/Province <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="state"
                                       placeholder="e.g., Nairobi County"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Postal Code <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="postal_code"
                                       placeholder="e.g., 00100"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Country <span class="text-danger">*</span></label>
                                <select class="form-control" name="country" required>
                                    <option value="Kenya" selected>Kenya</option>
                                    <option value="Uganda">Uganda</option>
                                    <option value="Tanzania">Tanzania</option>
                                    <option value="Rwanda">Rwanda</option>
                                    <option value="Burundi">Burundi</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" 
                               class="form-control" 
                               name="phone"
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                               placeholder="e.g., 0700 000 000"
                               required>
                        <div class="form-text">For delivery updates and calls</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   name="is_default"
                                   id="isDefaultAdd"
                                   <?php echo empty($addresses) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="isDefaultAdd">
                                Set as default shipping address
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Your default address will be pre-selected at checkout.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="fas fa-save me-2"></i> Save Address
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Address Modal -->
<div class="modal fade" id="editAddressModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-edit me-2"></i> Edit Address
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="update_address" value="1">
                    <input type="hidden" name="address_id" id="editAddressId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Address Title <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="address_title"
                                       id="editAddressTitle"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="full_name"
                                       id="editFullName"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address Line 1 <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               name="address_line1"
                               id="editAddressLine1"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address Line 2</label>
                        <input type="text" 
                               class="form-control" 
                               name="address_line2"
                               id="editAddressLine2">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">City <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="city"
                                       id="editCity"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">State/Province <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="state"
                                       id="editState"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Postal Code <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="postal_code"
                                       id="editPostalCode"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Country <span class="text-danger">*</span></label>
                                <select class="form-control" name="country" id="editCountry" required>
                                    <option value="Kenya">Kenya</option>
                                    <option value="Uganda">Uganda</option>
                                    <option value="Tanzania">Tanzania</option>
                                    <option value="Rwanda">Rwanda</option>
                                    <option value="Burundi">Burundi</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" 
                               class="form-control" 
                               name="phone"
                               id="editPhone"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   name="is_default"
                                   id="editIsDefault">
                            <label class="form-check-label fw-bold" for="editIsDefault">
                                Set as default shipping address
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="fas fa-save me-2"></i> Update Address
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.card.border-dark {
    border-width: 2px !important;
}

address {
    line-height: 1.6;
    font-style: normal;
}

.empty-icon {
    opacity: 0.5;
}

@media (max-width: 768px) {
    .display-5 {
        font-size: 2rem;
    }
    
    .modal-dialog {
        margin: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit address buttons
    document.querySelectorAll('.edit-address-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('editAddressModal'));
            
            // Fill form with address data
            document.getElementById('editAddressId').value = this.dataset.addressId;
            document.getElementById('editAddressTitle').value = this.dataset.addressTitle;
            document.getElementById('editFullName').value = this.dataset.fullName;
            document.getElementById('editAddressLine1').value = this.dataset.addressLine1;
            document.getElementById('editAddressLine2').value = this.dataset.addressLine2;
            document.getElementById('editCity').value = this.dataset.city;
            document.getElementById('editState').value = this.dataset.state;
            document.getElementById('editPostalCode').value = this.dataset.postalCode;
            document.getElementById('editCountry').value = this.dataset.country;
            document.getElementById('editPhone').value = this.dataset.phone;
            document.getElementById('editIsDefault').checked = this.dataset.isDefault === '1';
            
            modal.show();
        });
    });
    
    // Phone number formatting
    document.querySelectorAll('input[name="phone"]').forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 9) {
                value = value.substring(0, 9);
            }
            
            if (value.length > 6) {
                value = value.substring(0, 3) + ' ' + 
                        value.substring(3, 6) + ' ' + 
                        value.substring(6);
            } else if (value.length > 3) {
                value = value.substring(0, 3) + ' ' + value.substring(3);
            }
            
            this.value = value;
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>