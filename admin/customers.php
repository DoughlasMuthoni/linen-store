<?php
// /linen-closet/admin/customers.php

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

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_action']) && isset($_POST['selected_customers'])) {
        try {
            // Verify CSRF token
            if (!$app->verifyCsrfToken()) {
                throw new Exception('Invalid form submission. Please try again.');
            }
            
            $action = $_POST['bulk_action'];
            $customerIds = $_POST['selected_customers'];
            
            switch ($action) {
                case 'activate':
                    $placeholders = str_repeat('?,', count($customerIds) - 1) . '?';
                    $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders)");
                    $stmt->execute($customerIds);
                    $success = 'Customers activated successfully';
                    break;
                    
                case 'deactivate':
                    $placeholders = str_repeat('?,', count($customerIds) - 1) . '?';
                    $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders)");
                    $stmt->execute($customerIds);
                    $success = 'Customers deactivated successfully';
                    break;
                    
                case 'delete':
                    $placeholders = str_repeat('?,', count($customerIds) - 1) . '?';
                    $stmt = $db->prepare("DELETE FROM users WHERE id IN ($placeholders) AND is_admin = 0");
                    $stmt->execute($customerIds);
                    $success = 'Customers deleted successfully';
                    break;
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// ====================================================================
// 3. GET FILTER PARAMETERS
// ====================================================================

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ====================================================================
// 4. BUILD QUERY & FETCH CUSTOMERS
// ====================================================================

// Build query
$query = "
    SELECT 
        u.*,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total_amount), 0) as total_spent,
        MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE u.is_admin = 0
";

$params = [];

if ($search) {
    $query .= " AND (
        u.email LIKE ? OR 
        u.first_name LIKE ? OR 
        u.last_name LIKE ? OR 
        u.phone LIKE ?
    )";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($status === 'active') {
    $query .= " AND u.is_active = 1";
} elseif ($status === 'inactive') {
    $query .= " AND u.is_active = 0";
} elseif ($status === 'new') {
    $query .= " AND DATE(u.created_at) = CURDATE()";
}

if ($date_from) {
    $query .= " AND DATE(u.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(u.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

// Get customers
$stmt = $db->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Calculate stats
$stats = [
    'total' => count($customers),
    'active' => count(array_filter($customers, fn($c) => $c['is_active'] == 1)),
    'new_today' => count(array_filter($customers, fn($c) => date('Y-m-d', strtotime($c['created_at'])) === date('Y-m-d'))),
    'total_spent' => array_sum(array_column($customers, 'total_spent')),
];

// ====================================================================
// 5. BUILD CONTENT
// ====================================================================

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Customer Management</h1>
            <p class="text-muted mb-0">Manage and view customer information and order history.</p>
        </div>
        <div>
            <a href="' . SITE_URL . 'admin/customer-add.php" class="btn btn-dark">
                <i class="fas fa-user-plus me-2"></i> Add Customer
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

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Customers
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . $stats['total'] . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Customers
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . $stats['active'] . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                New Today
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ' . $stats['new_today'] . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Revenue
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Ksh' . number_format($stats['total_spent'], 2) . '
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" 
                           class="form-control" 
                           id="search" 
                           name="search" 
                           value="' . htmlspecialchars($search) . '" 
                           placeholder="Search by name, email, phone...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" ' . ($status === 'active' ? 'selected' : '') . '>Active</option>
                        <option value="inactive" ' . ($status === 'inactive' ? 'selected' : '') . '>Inactive</option>
                        <option value="new" ' . ($status === 'new' ? 'selected' : '') . '>New Today</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Joined From</label>
                    <input type="date" 
                           class="form-control" 
                           id="date_from" 
                           name="date_from" 
                           value="' . htmlspecialchars($date_from) . '">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Joined To</label>
                    <input type="date" 
                           class="form-control" 
                           id="date_to" 
                           name="date_to" 
                           value="' . htmlspecialchars($date_to) . '">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Customers (' . count($customers) . ')</h6>
            <div class="d-flex gap-2">
                <form method="POST" id="bulkActionForm" class="d-flex gap-2">
                    ' . $app->csrfField() . '
                    <select name="bulk_action" class="form-control form-control-sm" style="width: 150px;">
                        <option value="">Bulk Actions</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" onclick="submitBulkAction()">Apply</button>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
if (count($customers) > 0) {
    foreach ($customers as $customer) {
        $customerName = htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name']));
        $customerName = $customerName ?: 'Guest User';
        
        $content .= '
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_customers[]" value="' . $customer['id'] . '" class="form-check-input customer-checkbox">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-placeholder bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                         style="width: 40px; height: 40px;">
                                        ' . strtoupper(substr($customer['first_name'] ?? 'G', 0, 1) . substr($customer['last_name'] ?? 'U', 0, 1)) . '
                                    </div>
                                    <div>
                                        <div class="fw-bold">' . $customerName . '</div>
                                        <small class="text-muted">ID: ' . $customer['id'] . '</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <div>' . htmlspecialchars($customer['email']) . '</div>
                                    <small class="text-muted">' . ($customer['phone'] ? htmlspecialchars($customer['phone']) : 'No phone') . '</small>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold">' . $customer['order_count'] . '</div>
                                <small class="text-muted">orders</small>
                            </td>
                            <td>
                                <div class="fw-bold">Ksh' . number_format($customer['total_spent'], 2) . '</div>
                                <small class="text-muted">lifetime</small>
                            </td>
                            <td>
                                ' . ($customer['last_order_date'] ? date('M j, Y', strtotime($customer['last_order_date'])) : 'No orders') . '
                            </td>
                            <td>
                                <span class="badge bg-' . ($customer['is_active'] ? 'success' : 'secondary') . '">
                                    ' . ($customer['is_active'] ? 'Active' : 'Inactive') . '
                                </span>
                            </td>
                            <td>' . date('M j, Y', strtotime($customer['created_at'])) . '</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="' . SITE_URL . 'admin/customer-view.php?id=' . $customer['id'] . '" 
                                       class="btn btn-outline-primary"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="' . SITE_URL . 'admin/customer-edit.php?id=' . $customer['id'] . '" 
                                       class="btn btn-outline-secondary"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="' . SITE_URL . 'admin/customer-orders.php?id=' . $customer['id'] . '" 
                                       class="btn btn-outline-info"
                                       title="View Orders">
                                        <i class="fas fa-shopping-cart"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>';
    }
} else {
    $content .= '
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>';
}
$content .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Bulk actions
document.getElementById("selectAll").addEventListener("change", function() {
    var checkboxes = document.querySelectorAll(".customer-checkbox");
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = this.checked;
    }.bind(this));
});

function submitBulkAction() {
    var form = document.getElementById("bulkActionForm");
    var action = form.bulk_action.value;
    var checkedCustomers = document.querySelectorAll(".customer-checkbox:checked");
    
    if (!action) {
        Swal.fire("Error", "Please select a bulk action", "error");
        return;
    }
    
    if (checkedCustomers.length === 0) {
        Swal.fire("Error", "Please select at least one customer", "error");
        return;
    }
    
    // Add checked customers to form
    checkedCustomers.forEach(function(checkbox) {
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = "selected_customers[]";
        input.value = checkbox.value;
        form.appendChild(input);
    });
    
    // Confirm destructive actions
    if (action === "delete") {
        Swal.fire({
            title: "Are you sure?",
            text: "This will permanently delete " + checkedCustomers.length + " customer(s) and all their data.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, delete them!"
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    } else {
        form.submit();
    }
}
</script>';

// ====================================================================
// 6. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Customer Management');
?>