<?php
// product-variants.php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

if (!$app->isLoggedIn() || !$app->isAdmin()) {
    $app->redirect('auth/login');
}

$productId = $_GET['product_id'] ?? 0;

// Get product details
$product = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ?
");
$product->execute([$productId]);
$product = $product->fetch();

if (!$product) {
    $app->setFlashMessage('error', 'Product not found');
    $app->redirect('admin/products');
}

// Get variants
$variants = $db->prepare("
    SELECT * FROM product_variants 
    WHERE product_id = ? 
    ORDER BY is_default DESC, size, color
");
$variants->execute([$productId]);
$variants = $variants->fetchAll();

$content = '
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Manage Variants: ' . htmlspecialchars($product['name']) . '</h1>
            <p class="text-muted mb-0">SKU: ' . htmlspecialchars($product['sku']) . ' | Category: ' . htmlspecialchars($product['category_name']) . '</p>
        </div>
        <div>
            <a href="' . SITE_URL . 'admin/products" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i> Back to Products
            </a>
            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                <i class="fas fa-plus me-2"></i> Add Variant
            </button>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Default</th>
                            <th>SKU</th>
                            <th>Size</th>
                            <th>Color</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

$totalStock = 0;
foreach ($variants as $variant) {
    $totalStock += $variant['stock_quantity'];
    
    $stockClass = $variant['stock_quantity'] <= 0 ? 'danger' : 
                 ($variant['stock_quantity'] <= 10 ? 'warning' : 'success');
    
    $content .= '
        <tr>
            <td>
                ' . ($variant['is_default'] ? 
                   '<span class="badge bg-primary"><i class="fas fa-star"></i> Default</span>' : 
                   '<button class="btn btn-sm btn-outline-secondary set-default" data-id="' . $variant['id'] . '">
                        Set Default
                    </button>') . '
            </td>
            <td>' . htmlspecialchars($variant['sku']) . '</td>
            <td>' . htmlspecialchars($variant['size']) . '</td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    ' . ($variant['color_code'] ? 
                       '<span class="color-dot" style="background-color: ' . $variant['color_code'] . '"></span>' : '') . '
                    ' . htmlspecialchars($variant['color']) . '
                </div>
            </td>
            <td>Ksh ' . number_format($variant['price'], 2) . '</td>
            <td>
                <span class="badge bg-' . $stockClass . '">' . $variant['stock_quantity'] . '</span>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary edit-variant" data-id="' . $variant['id'] . '">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-danger delete-variant" data-id="' . $variant['id'] . '">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>';
}

$content .= '
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="5" class="text-end"><strong>Total Stock:</strong></td>
                            <td><span class="badge bg-dark">' . $totalStock . '</span></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Variant Modal -->
<div class="modal fade" id="addVariantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="' . SITE_URL . 'admin/save-variant.php" method="POST">
                ' . $app->csrfField() . '
                <input type="hidden" name="product_id" value="' . $productId . '">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Variant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">SKU *</label>
                        <input type="text" class="form-control" name="sku" required 
                               value="' . $product['sku'] . '-" placeholder="e.g., ' . $product['sku'] . '-RED-L">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Size</label>
                            <input type="text" class="form-control" name="size" placeholder="e.g., L, XL, 42">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Color</label>
                            <input type="text" class="form-control" name="color" placeholder="e.g., Red, Blue">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color Code</label>
                        <input type="color" class="form-control form-control-color" name="color_code" value="#ff0000">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price *</label>
                            <input type="number" class="form-control" name="price" step="0.01" required 
                                   value="' . $product['price'] . '">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Compare Price</label>
                            <input type="number" class="form-control" name="compare_price" step="0.01">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stock Quantity *</label>
                        <input type="number" class="form-control" name="stock_quantity" value="0" min="0" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="is_default">
                        <label class="form-check-label" for="is_default">
                            Set as default variant
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Save Variant</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Variant Modal -->
<div class="modal fade" id="editVariantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo SITE_URL; ?>admin/save-variant.php" method="POST">
                <?php echo $app->csrfField(); ?>
                <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                <input type="hidden" name="variant_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Variant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">SKU *</label>
                        <input type="text" class="form-control" name="sku" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Size</label>
                            <input type="text" class="form-control" name="size" placeholder="e.g., L, XL, 42">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Color</label>
                            <input type="text" class="form-control" name="color" placeholder="e.g., Red, Blue">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color Code</label>
                        <input type="color" class="form-control form-control-color" name="color_code" value="#ff0000">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price *</label>
                            <input type="number" class="form-control" name="price" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Compare Price</label>
                            <input type="number" class="form-control" name="compare_price" step="0.01">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stock Quantity *</label>
                        <input type="number" class="form-control" name="stock_quantity" min="0" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="edit_is_default">
                        <label class="form-check-label" for="edit_is_default">
                            Set as default variant
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Update Variant</button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
.color-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1px solid #ddd;
    display: inline-block;
}
</style>

<script>
// JavaScript for variant management

// Set default variant
document.querySelectorAll(\'.set-default\').forEach(btn => {
    btn.addEventListener(\'click\', function() {
        const variantId = this.dataset.id;
        Swal.fire({
            title: \'Set as Default?\',
            text: \'This variant will be shown as default on the product page\',
            icon: \'question\',
            showCancelButton: true
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("' . SITE_URL . 'admin/set-default-variant.php", {
                    method: \'POST\',
                    headers: {\'Content-Type\': \'application/json\'},
                    body: JSON.stringify({variant_id: variantId})
                }).then(response => response.json())
                  .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        Swal.fire(\'Error\', data.message, \'error\');
                    }
                });
            }
        });
    });
});

// Edit variant
document.querySelectorAll(\'.edit-variant\').forEach(btn => {
    btn.addEventListener(\'click\', function() {
        const variantId = this.dataset.id;
        
        // Load variant data via AJAX
        fetch(`' . SITE_URL . 'admin/get-variant.php?id=${variantId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(\'Network response was not ok\');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Populate edit modal
                    populateEditModal(data.variant);
                    
                    // Show edit modal
                    const modal = new bootstrap.Modal(document.getElementById(\'editVariantModal\'));
                    modal.show();
                } else {
                    Swal.fire(\'Error\', data.message, \'error\');
                }
            })
            .catch(error => {
                console.error(\'Error:\', error);
                Swal.fire(\'Error\', \'Failed to load variant data\', \'error\');
            });
    });
});

// Delete variant
document.querySelectorAll(\'.delete-variant\').forEach(btn => {
    btn.addEventListener(\'click\', function() {
        const variantId = this.dataset.id;
        
        Swal.fire({
            title: \'Delete Variant?\',
            text: \'This action cannot be undone. Stock data will be lost.\',
            icon: \'warning\',
            showCancelButton: true,
            confirmButtonColor: \'#d33\',
            confirmButtonText: \'Yes, delete it!\'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("' . SITE_URL . 'admin/delete-variant.php", {
                    method: \'POST\',
                    headers: {\'Content-Type\': \'application/json\'},
                    body: JSON.stringify({variant_id: variantId})
                }).then(response => response.json())
                  .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        Swal.fire(\'Error\', data.message, \'error\');
                    }
                });
            }
        });
    });
});

// Function to populate edit modal
function populateEditModal(variant) {
    document.querySelector(\'#editVariantModal input[name="variant_id"]\').value = variant.id;
    document.querySelector(\'#editVariantModal input[name="sku"]\').value = variant.sku;
    document.querySelector(\'#editVariantModal input[name="size"]\').value = variant.size || \'\';
    document.querySelector(\'#editVariantModal input[name="color"]\').value = variant.color || \'\';
    document.querySelector(\'#editVariantModal input[name="color_code"]\').value = variant.color_code || \'#ff0000\';
    document.querySelector(\'#editVariantModal input[name="price"]\').value = variant.price;
    document.querySelector(\'#editVariantModal input[name="compare_price"]\').value = variant.compare_price || \'\';
    document.querySelector(\'#editVariantModal input[name="stock_quantity"]\').value = variant.stock_quantity;
    document.querySelector(\'#editVariantModal input[name="is_default"]\').checked = variant.is_default == 1;
}

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>';

echo adminLayout($content, 'Manage Variants');
?>