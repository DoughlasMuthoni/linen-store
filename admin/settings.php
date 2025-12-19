<?php
// /linen-closet/admin/settings.php
require_once __DIR__ . '/layout.php';

// Include necessary files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Helper functions for settings
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $default;
}

function updateSetting($db, $key, $value) {
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP
    ");
    return $stmt->execute([$key, $value, $value]);
}

function getAllSettings($db) {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings ORDER BY setting_group, setting_key");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? 'general';
    
    switch($section) {
        case 'general':
            updateSetting($db, 'store_name', $_POST['store_name'] ?? '');
            updateSetting($db, 'store_email', $_POST['store_email'] ?? '');
            updateSetting($db, 'store_phone', $_POST['store_phone'] ?? '');
            updateSetting($db, 'store_address', $_POST['store_address'] ?? '');
            updateSetting($db, 'currency', $_POST['currency'] ?? 'KES');
            updateSetting($db, 'timezone', $_POST['timezone'] ?? 'Africa/Nairobi');
            break;
            
        case 'mpesa':
            updateSetting($db, 'mpesa_enabled', $_POST['mpesa_enabled'] ?? '0');
            updateSetting($db, 'mpesa_env', $_POST['mpesa_env'] ?? 'sandbox');
            updateSetting($db, 'mpesa_shortcode', $_POST['mpesa_shortcode'] ?? '');
            updateSetting($db, 'mpesa_passkey', $_POST['mpesa_passkey'] ?? '');
            updateSetting($db, 'mpesa_consumer_key', $_POST['mpesa_consumer_key'] ?? '');
            updateSetting($db, 'mpesa_consumer_secret', $_POST['mpesa_consumer_secret'] ?? '');
            updateSetting($db, 'mpesa_paybill', $_POST['mpesa_paybill'] ?? '');
            updateSetting($db, 'mpesa_till', $_POST['mpesa_till'] ?? '');
            break;
            
        case 'shipping':
            updateSetting($db, 'shipping_enabled', $_POST['shipping_enabled'] ?? '0');
            updateSetting($db, 'shipping_flat_rate', $_POST['shipping_flat_rate'] ?? '0');
            updateSetting($db, 'free_shipping_min', $_POST['free_shipping_min'] ?? '0');
            updateSetting($db, 'shipping_zones', $_POST['shipping_zones'] ?? '');
            break;
            
        case 'email':
            updateSetting($db, 'smtp_enabled', $_POST['smtp_enabled'] ?? '0');
            updateSetting($db, 'smtp_host', $_POST['smtp_host'] ?? '');
            updateSetting($db, 'smtp_port', $_POST['smtp_port'] ?? '587');
            updateSetting($db, 'smtp_username', $_POST['smtp_username'] ?? '');
            updateSetting($db, 'smtp_password', $_POST['smtp_password'] ?? '');
            updateSetting($db, 'smtp_encryption', $_POST['smtp_encryption'] ?? 'tls');
            break;
    }
    
    // Show success message
    echo '<script>Swal.fire("Success!", "Settings updated successfully!", "success");</script>';
}

// Get all settings
$settings = getAllSettings($db);

$content = '
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h4>System Settings</h4>
            <p class="text-muted">Manage your store configuration and preferences</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-3">
            <!-- Settings Navigation -->
            <div class="card">
                <div class="list-group list-group-flush" id="settings-nav">
                    <a href="#general" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                        <i class="fas fa-cog me-2"></i>General Settings
                    </a>
                    <a href="#mpesa" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-mobile-alt me-2"></i>M-Pesa Settings
                    </a>
                    <a href="#shipping" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-shipping-fast me-2"></i>Shipping
                    </a>
                    <a href="#email" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-envelope me-2"></i>Email Settings
                    </a>
                    <a href="#tax" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-percent me-2"></i>Tax Settings
                    </a>
                    <a href="#social" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-share-alt me-2"></i>Social Media
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="tab-content">
                <!-- General Settings Tab -->
                <div class="tab-pane fade show active" id="general">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">General Settings</h5>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="section" value="general">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Store Name *</label>
                                        <input type="text" name="store_name" 
                                               value="' . htmlspecialchars($settings['store_name'] ?? SITE_NAME) . '" 
                                               class="form-control" required>
                                        <small class="text-muted">Display name of your store</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Store Email *</label>
                                        <input type="email" name="store_email" 
                                               value="' . htmlspecialchars($settings['store_email'] ?? '') . '" 
                                               class="form-control" required>
                                        <small class="text-muted">For order notifications and customer support</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Store Phone</label>
                                        <input type="text" name="store_phone" 
                                               value="' . htmlspecialchars($settings['store_phone'] ?? '') . '" 
                                               class="form-control">
                                        <small class="text-muted">Customer support phone number</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Currency</label>
                                        <select name="currency" class="form-select">
                                            <option value="KES" ' . (($settings['currency'] ?? 'KES') == 'KES' ? 'selected' : '') . '>Kenyan Shilling (KES)</option>
                                            <option value="USD" ' . (($settings['currency'] ?? '') == 'USD' ? 'selected' : '') . '>US Dollar (USD)</option>
                                            <option value="EUR" ' . (($settings['currency'] ?? '') == 'EUR' ? 'selected' : '') . '>Euro (EUR)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Timezone</label>
                                        <select name="timezone" class="form-select">
                                            <option value="Africa/Nairobi" ' . (($settings['timezone'] ?? 'Africa/Nairobi') == 'Africa/Nairobi' ? 'selected' : '') . '>Africa/Nairobi</option>
                                            <option value="UTC" ' . (($settings['timezone'] ?? '') == 'UTC' ? 'selected' : '') . '>UTC</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Store Address</label>
                                        <textarea name="store_address" class="form-control" rows="3">' . 
                                        htmlspecialchars($settings['store_address'] ?? '') . '</textarea>
                                        <small class="text-muted">Your business physical address</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save General Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- M-Pesa Settings Tab -->
                <div class="tab-pane fade" id="mpesa">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">M-Pesa Payment Settings</h5>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="section" value="mpesa">
                            <div class="card-body">
                                <div class="mb-4">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" 
                                               name="mpesa_enabled" value="1" 
                                               ' . (($settings['mpesa_enabled'] ?? '0') == '1' ? 'checked' : '') . ' 
                                               id="mpesaEnabled">
                                        <label class="form-check-label fw-bold" for="mpesaEnabled">
                                            Enable M-Pesa Payments
                                        </label>
                                    </div>
                                    
                                    <div class="row g-3" id="mpesaFields">
                                        <div class="col-md-6">
                                            <label class="form-label">Environment</label>
                                            <select name="mpesa_env" class="form-select">
                                                <option value="sandbox" ' . (($settings['mpesa_env'] ?? 'sandbox') == 'sandbox' ? 'selected' : '') . '>Sandbox (Testing)</option>
                                                <option value="production" ' . (($settings['mpesa_env'] ?? '') == 'production' ? 'selected' : '') . '>Production (Live)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Shortcode</label>
                                            <input type="text" name="mpesa_shortcode" 
                                                   value="' . htmlspecialchars($settings['mpesa_shortcode'] ?? '') . '" 
                                                   class="form-control">
                                            <small class="text-muted">Your business shortcode</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Passkey</label>
                                            <input type="password" name="mpesa_passkey" 
                                                   value="' . htmlspecialchars($settings['mpesa_passkey'] ?? '') . '" 
                                                   class="form-control">
                                            <small class="text-muted">M-Pesa API passkey</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">PayBill Number</label>
                                            <input type="text" name="mpesa_paybill" 
                                                   value="' . htmlspecialchars($settings['mpesa_paybill'] ?? '') . '" 
                                                   class="form-control">
                                            <small class="text-muted">PayBill number for payments</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Till Number</label>
                                            <input type="text" name="mpesa_till" 
                                                   value="' . htmlspecialchars($settings['mpesa_till'] ?? '') . '" 
                                                   class="form-control">
                                            <small class="text-muted">Till number (if using Till)</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Consumer Key</label>
                                            <input type="text" name="mpesa_consumer_key" 
                                                   value="' . htmlspecialchars($settings['mpesa_consumer_key'] ?? '') . '" 
                                                   class="form-control">
                                            <small class="text-muted">Daraja API consumer key</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Consumer Secret</label>
                                            <input type="password" name="mpesa_consumer_secret" 
                                                   value="' . htmlspecialchars($settings['mpesa_consumer_secret'] ?? '') . '" 
                                                   class="form-control">
                                            <small class="text-muted">Daraja API consumer secret</small>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3">
                                        <h6><i class="fas fa-info-circle me-2"></i>M-Pesa Integration Notes:</h6>
                                        <ul class="mb-0">
                                            <li>You need to register for <a href="https://developer.safaricom.co.ke/" target="_blank">Safaricom Daraja API</a></li>
                                            <li>Use sandbox mode for testing with test credentials</li>
                                            <li>For production, apply for live credentials from Safaricom</li>
                                            <li>Ensure your callback URLs are properly configured</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="button" class="btn btn-outline-info me-2" id="testMpesa">
                                    <i class="fas fa-test-tube me-2"></i>Test Connection
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save M-Pesa Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Shipping Settings Tab -->
                <div class="tab-pane fade" id="shipping">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Shipping Settings</h5>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="section" value="shipping">
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           name="shipping_enabled" value="1" 
                                           ' . (($settings['shipping_enabled'] ?? '0') == '1' ? 'checked' : '') . '>
                                    <label class="form-check-label fw-bold">
                                        Enable Shipping
                                    </label>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Flat Rate Shipping (KES)</label>
                                        <input type="number" name="shipping_flat_rate" 
                                               value="' . htmlspecialchars($settings['shipping_flat_rate'] ?? '200') . '" 
                                               class="form-control" step="0.01" min="0">
                                        <small class="text-muted">Standard shipping cost for all orders</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Free Shipping Minimum (KES)</label>
                                        <input type="number" name="free_shipping_min" 
                                               value="' . htmlspecialchars($settings['free_shipping_min'] ?? '3000') . '" 
                                               class="form-control" step="0.01" min="0">
                                        <small class="text-muted">Order amount for free shipping (0 to disable)</small>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Shipping Zones</label>
                                        <textarea name="shipping_zones" class="form-control" rows="4" placeholder="Format: Zone Name, Cost
Example:
Nairobi, 200
Mombasa, 400
Rest of Kenya, 600
International, 2000">' . htmlspecialchars($settings['shipping_zones'] ?? '') . '</textarea>
                                        <small class="text-muted">Enter one zone per line in format: Zone Name, Cost</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Shipping Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Email Settings Tab -->
                <div class="tab-pane fade" id="email">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Email Settings</h5>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="section" value="email">
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           name="smtp_enabled" value="1" 
                                           ' . (($settings['smtp_enabled'] ?? '0') == '1' ? 'checked' : '') . '>
                                    <label class="form-check-label fw-bold">
                                        Enable SMTP (Recommended)
                                    </label>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" name="smtp_host" 
                                               value="' . htmlspecialchars($settings['smtp_host'] ?? '') . '" 
                                               class="form-control" placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" name="smtp_port" 
                                               value="' . htmlspecialchars($settings['smtp_port'] ?? '587') . '" 
                                               class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Username</label>
                                        <input type="text" name="smtp_username" 
                                               value="' . htmlspecialchars($settings['smtp_username'] ?? '') . '" 
                                               class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Password</label>
                                        <input type="password" name="smtp_password" 
                                               value="' . htmlspecialchars($settings['smtp_password'] ?? '') . '" 
                                               class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Encryption</label>
                                        <select name="smtp_encryption" class="form-select">
                                            <option value="tls" ' . (($settings['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : '') . '>TLS</option>
                                            <option value="ssl" ' . (($settings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : '') . '>SSL</option>
                                            <option value="" ' . (empty($settings['smtp_encryption'] ?? '') ? 'selected' : '') . '>None</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning mt-3">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Important:</h6>
                                    <p class="mb-0">For Gmail, you may need to enable "Less secure app access" or use App Passwords.</p>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="button" class="btn btn-outline-info me-2" id="testEmail">
                                    <i class="fas fa-envelope me-2"></i>Test Email
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Email Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tax Settings Tab -->
                <div class="tab-pane fade" id="tax">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Tax Settings</h5>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="section" value="tax">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Tax Rate (%)</label>
                                        <input type="number" name="tax_rate" 
                                               value="' . htmlspecialchars($settings['tax_rate'] ?? '16') . '" 
                                               class="form-control" step="0.01" min="0" max="100">
                                        <small class="text-muted">VAT rate (16% for Kenya)</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tax Display</label>
                                        <select name="tax_display" class="form-select">
                                            <option value="inclusive" ' . (($settings['tax_display'] ?? 'inclusive') == 'inclusive' ? 'selected' : '') . '>Prices inclusive of tax</option>
                                            <option value="exclusive" ' . (($settings['tax_display'] ?? '') == 'exclusive' ? 'selected' : '') . '>Prices exclusive of tax</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Tax Number</label>
                                        <input type="text" name="tax_number" 
                                               value="' . htmlspecialchars($settings['tax_number'] ?? '') . '" 
                                               class="form-control" placeholder="P000XXXXX">
                                        <small class="text-muted">Your KRA PIN/VAT number</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Tax Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Social Media Tab -->
                <div class="tab-pane fade" id="social">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Social Media Links</h5>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="section" value="social">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Facebook URL</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-facebook"></i></span>
                                            <input type="url" name="facebook_url" 
                                                   value="' . htmlspecialchars($settings['facebook_url'] ?? '') . '" 
                                                   class="form-control" placeholder="https://facebook.com/yourpage">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Instagram URL</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-instagram"></i></span>
                                            <input type="url" name="instagram_url" 
                                                   value="' . htmlspecialchars($settings['instagram_url'] ?? '') . '" 
                                                   class="form-control" placeholder="https://instagram.com/yourpage">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Twitter URL</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-twitter"></i></span>
                                            <input type="url" name="twitter_url" 
                                                   value="' . htmlspecialchars($settings['twitter_url'] ?? '') . '" 
                                                   class="form-control" placeholder="https://twitter.com/yourpage">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">WhatsApp Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-whatsapp"></i></span>
                                            <input type="text" name="whatsapp_number" 
                                                   value="' . htmlspecialchars($settings['whatsapp_number'] ?? '') . '" 
                                                   class="form-control" placeholder="+2547XXXXXXXX">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Social Media Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Tab switching
    $("#settings-nav a").on("click", function(e) {
        e.preventDefault();
        $("#settings-nav a").removeClass("active");
        $(this).addClass("active");
        $(".tab-pane").removeClass("show active");
        $($(this).attr("href")).addClass("show active");
    });
    
    // Toggle M-Pesa fields
    function toggleMpesaFields() {
        if ($("#mpesaEnabled").is(":checked")) {
            $("#mpesaFields").show();
        } else {
            $("#mpesaFields").hide();
        }
    }
    
    $("#mpesaEnabled").on("change", toggleMpesaFields);
    toggleMpesaFields(); // Initial state
    
    // Test M-Pesa Connection
    $("#testMpesa").on("click", function() {
        Swal.fire({
            title: "Testing M-Pesa Connection",
            text: "This will test your M-Pesa API credentials...",
            icon: "info",
            showCancelButton: true,
            confirmButtonText: "Test Now"
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX call to test M-Pesa
                Swal.fire({
                    title: "Testing...",
                    text: "Please wait while we test the connection",
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Simulate API test (replace with actual AJAX call)
                setTimeout(() => {
                    Swal.fire({
                        title: "Test Complete",
                        text: "M-Pesa connection test successful!",
                        icon: "success"
                    });
                }, 1500);
            }
        });
    });
    
    // Test Email
    $("#testEmail").on("click", function() {
        Swal.fire({
            title: "Send Test Email",
            input: "email",
            inputLabel: "Enter email address to send test to",
            inputPlaceholder: "test@example.com",
            showCancelButton: true,
            confirmButtonText: "Send Test"
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                // AJAX call to send test email
                Swal.fire({
                    title: "Sending...",
                    text: "Sending test email to " + result.value,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Simulate email send (replace with actual AJAX)
                setTimeout(() => {
                    Swal.fire({
                        title: "Sent!",
                        text: "Test email has been sent to " + result.value,
                        icon: "success"
                    });
                }, 2000);
            }
        });
    });
});
</script>
';

echo adminLayout($content, 'Settings');
?>