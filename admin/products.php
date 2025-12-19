<?php
// /linen-closet/admin/products.php

// ====================================================================
// 1. INCLUDES & INITIALIZATION
// ====================================================================

// Include the admin layout function FIRST
require_once __DIR__ . '/layout.php';

// Include necessary files (they're also included in layout.php but keep for clarity)
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
// 2. HANDLE FORM SUBMISSIONS
// ====================================================================

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_action']) && isset($_POST['selected_products'])) {
        $action = $_POST['bulk_action'];
        $productIds = $_POST['selected_products'];
        
        switch ($action) {
            case 'activate':
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                $stmt = $db->prepare("UPDATE products SET is_active = 1 WHERE id IN ($placeholders)");
                $stmt->execute($productIds);
                $app->setFlashMessage('success', 'Products activated successfully');
                break;
                
            case 'deactivate':
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id IN ($placeholders)");
                $stmt->execute($productIds);
                $app->setFlashMessage('success', 'Products deactivated successfully');
                break;
                
            case 'delete':
                $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                $stmt = $db->prepare("DELETE FROM products WHERE id IN ($placeholders)");
                $stmt->execute($productIds);
                $app->setFlashMessage('success', 'Products deleted successfully');
                break;
        }
        // ================================================================
        // ADD NOTIFICATION CHECK AFTER BULK ACTION
        // ================================================================
        if ($action === 'delete' && class_exists('Notification')) {
            $notification = new Notification($db);
            $notification->create(0, 'system', 'Products Deleted', 
                count($productIds) . ' products were deleted via bulk action.', 
                '/admin/products'
            );
        }
        $app->redirect('admin/products');
    }
}

// ====================================================================
// 3. GET SEARCH PARAMETERS & FILTERS
// ====================================================================

// Get search parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$query = "
    SELECT 
        p.*,
        c.name as category_name,
        b.name as brand_name,
        (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
        (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE 1=1
";

$params = [];

if ($search) {
    $query .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category) {
    $query .= " AND p.category_id = ?";
    $params[] = $category;
}

if ($status === 'active') {
    $query .= " AND p.is_active = 1";
} elseif ($status === 'inactive') {
    $query .= " AND p.is_active = 0";
} elseif ($status === 'low_stock') {
    $query .= " AND (p.stock_quantity <= 10 OR (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) <= 10)";
} elseif ($status === 'out_of_stock') {
    $query .= " AND (p.stock_quantity = 0 OR (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) = 0)";
}

$query .= " ORDER BY p.created_at DESC";

// Get categories for filter
$categories = $db->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

// Get products
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// ====================================================================
// 4. BUILD CONTENT
// ====================================================================

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Product Management</h1>
            <p class="text-muted mb-0">Manage your store products, inventory, and pricing.</p>
        </div>
        <div>
            <a href="' . SITE_URL . 'admin/product-add.php" class="btn btn-dark">
                <i class="fas fa-plus me-2"></i> Add New Product
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    ' . ($app->displayFlashMessage()) . '

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" 
                           class="form-control" 
                           id="search" 
                           name="search" 
                           value="' . htmlspecialchars($search) . '" 
                           placeholder="Search by name, SKU, or description...">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-control" id="category" name="category">
                        <option value="">All Categories</option>';
foreach ($categories as $cat) {
    $selected = $category == $cat['id'] ? 'selected' : '';
    $content .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
}
$content .= '
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" ' . ($status === 'active' ? 'selected' : '') . '>Active</option>
                        <option value="inactive" ' . ($status === 'inactive' ? 'selected' : '') . '>Inactive</option>
                        <option value="low_stock" ' . ($status === 'low_stock' ? 'selected' : '') . '>Low Stock</option>
                        <option value="out_of_stock" ' . ($status === 'out_of_stock' ? 'selected' : '') . '>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Products (' . count($products) . ')</h6>
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
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
if (count($products) > 0) {
    foreach ($products as $product) {
        $stockClass = '';
        $stockText = $product['total_stock'] ?: $product['stock_quantity'];
        
        if ($stockText <= 0) {
            $stockClass = 'danger';
            $stockText = 'Out of Stock';
        } elseif ($stockText <= 10) {
            $stockClass = 'warning';
            $stockText = $stockText . ' (Low)';
        } else {
            $stockClass = 'success';
        }
        
        $content .= '
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_products[]" value="' . $product['id'] . '" class="form-check-input product-checkbox">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="' . SITE_URL . ($product['primary_image'] ?: 'assets/images/placeholder.jpg') . '" 
                                         class="rounded me-3" 
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                    <div>
                                        <div class="fw-bold">' . htmlspecialchars($product['name']) . '</div>
                                        <small class="text-muted">' . htmlspecialchars($product['brand_name'] ?? 'No Brand') . '</small>
                                    </div>
                                </div>
                            </td>
                            <td>' . htmlspecialchars($product['sku']) . '</td>
                            <td>' . htmlspecialchars($product['category_name'] ?? 'Uncategorized') . '</td>
                            <td>
                                <div class="fw-bold">Ksh' . number_format($product['price'], 2) . '</div>';
        if ($product['compare_price']) {
            $content .= '<small class="text-muted text-decoration-line-through">Ksh' . number_format($product['compare_price'], 2) . '</small>';
        }
        $content .= '
                            </td>
                            <td>
                                <span class="badge bg-' . $stockClass . '">' . $stockText . '</span>
                            </td>
                            <td>
                                <span class="badge bg-' . ($product['is_active'] ? 'success' : 'secondary') . '">
                                    ' . ($product['is_active'] ? 'Active' : 'Inactive') . '
                                </span>
                            </td>
                            <td>' . date('M j, Y', strtotime($product['created_at'])) . '</td>
                        
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="' . SITE_URL . 'products/detail.php?slug=' . $product['slug'] . '" 
                                    class="btn btn-outline-primary" 
                                    target="_blank"
                                    title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="' . SITE_URL . 'admin/product-edit.php?id=' . $product['id'] . '" 
                                    class="btn btn-outline-secondary"
                                    title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="' . SITE_URL . 'admin/product-duplicate.php?id=' . $product['id'] . '" 
                                    class="btn btn-outline-info"
                                    title="Duplicate">
                                        <i class="fas fa-copy"></i>
                                    </a>
                                    <a href="' . SITE_URL . 'admin/product-delete.php?id=' . $product['id'] . '" 
                                    class="btn btn-outline-danger confirm-delete"
                                    title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>';
    }
} else {
    $content .= '
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5>No Products Found</h5>
                                <p class="text-muted">Try adjusting your filters or add a new product.</p>
                                <a href="' . SITE_URL . 'admin/products/add" class="btn btn-dark">
                                    <i class="fas fa-plus me-2"></i> Add Your First Product
                                </a>
                            </td>
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
    var checkboxes = document.querySelectorAll(".product-checkbox");
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = this.checked;
    }.bind(this));
});

function submitBulkAction() {
    var form = document.getElementById("bulkActionForm");
    var action = form.bulk_action.value;
    var checkedProducts = document.querySelectorAll(".product-checkbox:checked");
    
    if (!action) {
        Swal.fire("Error", "Please select a bulk action", "error");
        return;
    }
    
    if (checkedProducts.length === 0) {
        Swal.fire("Error", "Please select at least one product", "error");
        return;
    }
    
    // Add checked products to form
    checkedProducts.forEach(function(checkbox) {
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = "selected_products[]";
        input.value = checkbox.value;
        form.appendChild(input);
    });
    
    // Confirm destructive actions
    if (action === "delete") {
        Swal.fire({
            title: "Are you sure?",
            text: "This will permanently delete " + checkedProducts.length + " product(s).",
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
// 5. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Products Management');
?>