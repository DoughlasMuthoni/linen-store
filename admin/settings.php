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
    // echo '<script>Swal.fire("Success!", "Settings updated successfully!", "success");</script>';
    // Show success message
    echo '<script>alert("Settings updated successfully!");</script>';
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Shipping Settings</h5>
                           <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addZoneModal">
                                <i class="fas fa-plus me-1"></i> Add Shipping Zone
                            </button>
                        </div>
                        <form method="POST" id="shippingSettingsForm">
                            <input type="hidden" name="section" value="shipping">
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           name="shipping_enabled" value="1" 
                                           ' . (($settings['shipping_enabled'] ?? '0') == '1' ? 'checked' : '') . ' 
                                           id="shippingEnabled">
                                    <label class="form-check-label fw-bold" for="shippingEnabled">
                                        Enable Shipping
                                    </label>
                                </div>
                                
                                <!-- Flat Rate Shipping -->
                                <div class="row g-3 mb-4">
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
                                </div>
                                
                                <!-- Shipping Zones Table -->
                                <div class="mb-4">
                                    <h6>Shipping Zones</h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="zonesTable">
                                            <thead>
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th>Zone Name</th>
                                                    <th>Cost (KES)</th>
                                                    <th>Counties</th>
                                                    <th>Towns/Areas</th>
                                                    <th>Delivery Days</th>
                                                    <th>Status</th>
                                                    <th width="120">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>';
                                            
// Fetch existing shipping zones from database
$zonesQuery = $db->query("SELECT * FROM shipping_zones ORDER BY zone_name");
$shippingZones = $zonesQuery->fetchAll();

if (empty($shippingZones)) {
    $content .= '<tr><td colspan="7" class="text-center text-muted py-4">No shipping zones found. Add your first zone.</td></tr>';
} else {
    foreach ($shippingZones as $zone) {
        $content .= '
        <tr data-zone-id="' . $zone['id'] . '">
            <td>' . $zone['id'] . '</td>
            <td>
                <strong>' . htmlspecialchars($zone['zone_name']) . '</strong>
                ' . ($zone['description'] ? '<br><small class="text-muted">' . htmlspecialchars($zone['description']) . '</small>' : '') . '
            </td>
            <td>KES ' . number_format($zone['cost'], 2) . '</td>
           <td>
                <small>' . (strlen($zone['counties']) > 30 ? substr(htmlspecialchars($zone['counties']), 0, 30) . '...' : htmlspecialchars($zone['counties'])) . '</small>
            </td>
            <td>
                <small>' . (strlen($zone['towns_areas']) > 30 ? substr(htmlspecialchars($zone['towns_areas']), 0, 30) . '...' : htmlspecialchars($zone['towns_areas'])) . '</small>
            </td>
            <td>' . $zone['delivery_days'] . ' days</td>
            <td>
                <span class="badge bg-' . ($zone['is_active'] ? 'success' : 'secondary') . '">
                    ' . ($zone['is_active'] ? 'Active' : 'Inactive') . '
                </span>
            </td>
            <td>
               <button type="button" class="btn btn-sm btn-outline-primary edit-zone" 
                    data-id="' . $zone['id'] . '"
                    data-name="' . htmlspecialchars($zone['zone_name']) . '"
                    data-description="' . htmlspecialchars($zone['description'] ?? '') . '"
                    data-cost="' . $zone['cost'] . '"
                    data-countries="' . htmlspecialchars($zone['countries'] ?? '') . '"
                    data-counties="' . htmlspecialchars($zone['counties'] ?? '') . '"
                    data-towns-areas="' . htmlspecialchars($zone['towns_areas'] ?? ($zone['counties'] ?? '')) . '" 
                    data-delivery-days="' . $zone['delivery_days'] . '"
                    data-min-order="' . ($zone['min_order_amount'] ?? '0') . '"
                    data-active="' . $zone['is_active'] . '">
                <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger delete-zone" 
                        data-id="' . $zone['id'] . '"
                        data-name="' . htmlspecialchars($zone['zone_name']) . '">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>';
    }
}

$content .= '
                                            </tbody>
                                        </table>
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
                
               <!-- Inside the Add/Edit Zone Modal -->
<div class="modal fade" id="addZoneModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Shipping Zone</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="zoneForm">
                <div class="modal-body">
                    <input type="hidden" name="zone_id" id="zoneId">
                    <input type="hidden" name="action" id="zoneAction" value="add">
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Zone Name *</label>
                            <input type="text" name="zone_name" id="zoneName" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="zoneDescription" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Shipping Cost (KES) *</label>
                            <input type="number" name="cost" id="zoneCost" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Delivery Days *</label>
                            <input type="number" name="delivery_days" id="zoneDeliveryDays" class="form-control" min="1" value="3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Order Amount (KES)</label>
                            <input type="number" name="min_order_amount" id="zoneMinOrder" class="form-control" step="0.01" min="0">
                            <small class="text-muted">0 = no minimum</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="is_active" id="zoneActive" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Countries (Optional)</label>
                            <textarea name="countries" id="zoneCountries" class="form-control" rows="2" placeholder="Kenya,Tanzania,Uganda"></textarea>
                            <small class="text-muted">Comma-separated list of countries</small>
                        </div>
                        
                        <!-- NEW: Counties Field -->
                        <div class="col-md-12">
                            <label class="form-label">Counties *</label>
                            <textarea name="counties" id="zoneCounties" class="form-control" rows="2" required placeholder="Nairobi, Kiambu, Mombasa, Kisumu"></textarea>
                            <small class="text-muted">Comma-separated list of counties for checkout dropdown</small>
                        </div>
                        
                        <!-- RENAMED: Towns/Areas Field (previously Counties) -->
                        <div class="col-md-12">
                            <label class="form-label">Towns/Areas *</label>
                            <textarea name="towns_areas" id="zoneTownsAreas" class="form-control" rows="3" required placeholder="Nairobi CBD, Westlands, Kilimani
Kiambu Town, Thika, Ruiru
Mombasa Island, Nyali, Bamburi
Kisumu CBD, Milimani, Kondele"></textarea>
                            <small class="text-muted">One per line or comma-separated. Specific towns/areas served by this zone.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Zone</button>
                </div>
            </form>
        </div>
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
// Load jQuery if not already loaded
if (typeof jQuery === \'undefined\') {
    var script = document.createElement(\'script\');
    script.src = \'https://code.jquery.com/jquery-3.7.0.min.js\';
    script.onload = function() {
        // jQuery loaded, now run our code
        initMyScript();
    };
    document.head.appendChild(script);
} else {
    // jQuery already loaded
    initMyScript();
}

function initMyScript() {
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
                        didOpen: function() {
                            Swal.showLoading();
                        }
                    });
                    
                    // Simulate API test (replace with actual AJAX call)
                    setTimeout(function() {
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
                        didOpen: function() {
                            Swal.showLoading();
                        }
                    });
                    
                    // Simulate email send (replace with actual AJAX)
                    setTimeout(function() {
                        Swal.fire({
                            title: "Sent!",
                            text: "Test email has been sent to " + result.value,
                            icon: "success"
                        });
                    }, 2000);
                }
            });
        });
        
        // Shipping Zones Management
        const zoneModal = new bootstrap.Modal(document.getElementById(\'addZoneModal\'));
        
        // Open Add Zone Modal when button is clicked
        $("button[data-bs-target=\'#addZoneModal\']").on("click", function() {
            document.getElementById(\'addZoneModal\').querySelector(\'.modal-title\').textContent = \'Add Shipping Zone\';
            document.getElementById(\'zoneForm\').reset();
            document.getElementById(\'zoneId\').value = \'\';
            document.getElementById(\'zoneAction\').value = \'add\';
            // Set default values
            document.getElementById(\'zoneDeliveryDays\').value = \'3\';
            document.getElementById(\'zoneActive\').value = \'1\';
            zoneModal.show();
        });
        
   // Edit Zone
        $(document).on("click", ".edit-zone", function() {
            const btn = $(this);
            document.getElementById(\'addZoneModal\').querySelector(\'.modal-title\').textContent = \'Edit Shipping Zone\';
            document.getElementById(\'zoneId\').value = btn.data(\'id\');
            document.getElementById(\'zoneName\').value = btn.data(\'name\');
            document.getElementById(\'zoneDescription\').value = btn.data(\'description\');
            document.getElementById(\'zoneCost\').value = btn.data(\'cost\');
            document.getElementById(\'zoneCountries\').value = btn.data(\'countries\');
            document.getElementById(\'zoneCounties\').value = btn.data(\'counties\');
            
            // Try multiple ways to get towns_areas data since jQuery handles data attributes differently
            let townsAreas = btn.data(\'towns-areas\') || btn.data(\'townsAreas\') || btn.data(\'towns_areas\') || \'\';
            document.getElementById(\'zoneTownsAreas\').value = townsAreas;
            
            document.getElementById(\'zoneDeliveryDays\').value = btn.data(\'delivery-days\');
            document.getElementById(\'zoneMinOrder\').value = btn.data(\'min-order\');
            document.getElementById(\'zoneActive\').value = btn.data(\'active\');
            document.getElementById(\'zoneAction\').value = \'edit\';
            zoneModal.show();
        });
        
        // Delete Zone
        $(document).on("click", ".delete-zone", function() {
            const btn = $(this);
            Swal.fire({
                title: \'Delete Zone?\',
                html: \'Are you sure you want to delete <strong>\' + btn.data(\'name\') + \'</strong>?\',
                icon: \'warning\',
                showCancelButton: true,
                confirmButtonText: \'Delete\',
                cancelButtonText: \'Cancel\'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: \'Deleting...\',
                        text: \'Please wait\',
                        allowOutsideClick: false,
                        didOpen: function() {
                            Swal.showLoading();
                        }
                    });
                    
                    // AJAX call to delete zone
                    $.ajax({
                        url: "ajax/shipping_zones.php",
                        method: "POST",
                        data: {
                            action: "delete",
                            zone_id: btn.data(\'id\')
                        },
                        dataType: "json",
                        success: function(data) {
                            if (data.success) {
                                Swal.fire({
                                    title: \'Deleted!\',
                                    text: \'Shipping zone has been deleted.\',
                                    icon: \'success\',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(function() {
                                    // Remove row from table
                                    $("tr[data-zone-id=\'" + btn.data(\'id\') + "\']").remove();
                                    
                                    // If no rows left, show empty message
                                    if ($("#zonesTable tbody tr").length === 0) {
                                        $("#zonesTable tbody").html(
                                            \'<tr><td colspan="7" class="text-center text-muted py-4">No shipping zones found. Add your first zone.</td></tr>\'
                                        );
                                    }
                                });
                            } else {
                                Swal.fire(\'Error!\', data.message || \'Failed to delete zone.\', \'error\');
                            }
                        },
                        error: function() {
                            Swal.fire(\'Error!\', \'Network error occurred.\', \'error\');
                        }
                    });
                }
            });
        });
        
        // Save Zone Form
document.getElementById(\'zoneForm\').addEventListener(\'submit\', function(e) {
    e.preventDefault();
    
    // Validate required fields before submitting
    const zoneName = document.getElementById(\'zoneName\').value.trim();
    const zoneCost = document.getElementById(\'zoneCost\').value.trim();
    const zoneCounties = document.getElementById(\'zoneCounties\').value.trim();
    const zoneTownsAreas = document.getElementById(\'zoneTownsAreas\').value.trim();
    
    if (!zoneName || !zoneCost || !zoneCounties || !zoneTownsAreas) {
        Swal.fire(\'Validation Error!\', \'Please fill all required fields: Zone Name, Cost, Counties, and Towns/Areas.\', \'error\');
        return;
    }
    
    // Show loading
    Swal.fire({
        title: \'Saving...\',
        text: \'Please wait\',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData(this);
    
    // Use fetch for FormData support
    fetch(\'ajax/shipping_zones.php\', {
        method: \'POST\',
        body: formData
    })
    .then(function(response) {
        // First, check if the response is JSON
        const contentType = response.headers.get(\'content-type\');
        if (!contentType || !contentType.includes(\'application/json\')) {
            // Not JSON, try to get text for debugging
            return response.text().then(function(text) {
                throw new Error(\'Invalid JSON response: \' + text.substring(0, 200));
            });
        }
        
        // Check if response is OK (status 200-299)
        if (!response.ok) {
            return response.json().then(function(data) {
                throw new Error(data.message || \'Server error: \' + response.status);
            });
        }
        
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            Swal.fire({
                title: \'Success!\',
                text: data.message || \'Zone saved successfully.\',
                icon: \'success\',
                timer: 1500,
                showConfirmButton: false
            }).then(function() {
                zoneModal.hide();
                // Refresh page to show updated zones
                location.reload();
            });
        } else {
            Swal.fire({
                title: \'Error!\',
                html: data.message ? data.message.replace(/\\n/g, \'<br>\') : \'Failed to save zone.\',
                icon: \'error\'
            });
        }
    })
    .catch(function(error) {
        console.error(\'Error details:\', error);
        
        // More detailed error messages
        let errorMessage = \'Network error occurred.\';
        let errorTitle = \'Error!\';
        
        if (error.message.includes(\'Invalid JSON response\')) {
            errorTitle = \'Server Configuration Error\';
            errorMessage = \'The server returned an invalid response. This usually means:<br><br>\' +
                          \'1. PHP errors are being displayed<br>\' +
                          \'2. Database connection failed<br>\' +
                          \'3. Missing or incorrect PHP includes<br><br>\' +
                          \'<small>Check your server error logs for details.</small>\';
        } else if (error.message.includes(\'Server error\')) {
            errorTitle = \'Server Error\';
            errorMessage = error.message.replace(\'Server error: \', \'HTTP \') + \'<br><br>Please try again or contact support.\';
        } else if (error.message) {
            errorMessage = error.message;
        }
        
        Swal.fire({
            title: errorTitle,
            html: errorMessage,
            icon: \'error\',
            width: 600
        });
    }).finally(function() {
        // Re-enable form if needed
        const submitBtn = document.querySelector(\'#zoneForm button[type="submit"]\');
        if (submitBtn) submitBtn.disabled = false;
    });
});
    });
}
</script>
';

echo adminLayout($content, 'Settings');
?>