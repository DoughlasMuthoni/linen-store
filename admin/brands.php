<?php
// admin/brands.php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();
// Ensure uploads directory exists
$uploads_dir = __DIR__ . '/../uploads/brands/';
if (!is_dir($uploads_dir)) {
    if (!mkdir($uploads_dir, 0777, true)) {
        die('Failed to create uploads directory. Please create it manually: ' . $uploads_dir);
    }
}
if (!$app->isLoggedIn() || !$app->isAdmin()) {
    $app->redirect('auth/login');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $name = $_POST['name'] ?? '';
        $slug = $_POST['slug'] ?? '';
        $description = $_POST['description'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $brand_id = $_POST['brand_id'] ?? 0;
        
        // Generate slug if empty
        if (empty($slug) && !empty($name)) {
            $slug = $app->generateSlug($name);
        }
        
       // Handle logo upload
        $logo_path = null;
        $logo_url = null;

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $upload_dir = __DIR__ . '/../uploads/brands/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = 'brand_' . time() . '_' . uniqid();
            $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $new_file_name = $file_name . '.' . $file_ext;
            $file_destination = $upload_dir . $new_file_name;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_ext, $allowed_types)) {
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $file_destination)) {
                    $logo_path = 'uploads/brands/' . $new_file_name;
                    $logo_url = SITE_URL . $logo_path;
                }
            }
        }
        
        if ($action === 'add') {
            // Add new brand
            $stmt = $db->prepare("
                INSERT INTO brands (name, slug, description, logo, logo_url, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $slug, $description, $logo_path, $logo_url ?? null, $is_active]);
            
            $app->setFlashMessage('success', 'Brand added successfully');
        } else {
            // Update existing brand
            $existing = $db->prepare("SELECT logo FROM brands WHERE id = ?");
            $existing->execute([$brand_id]);
            $existing_brand = $existing->fetch();
            
            $final_logo = $logo_path ?: $existing_brand['logo'];
            
            $stmt = $db->prepare("
                UPDATE brands 
                SET name = ?, slug = ?, description = ?, logo = ?, logo_url = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $slug, $description, $final_logo, $logo_url ?? null, $is_active, $brand_id]);
            
            $app->setFlashMessage('success', 'Brand updated successfully');
        }
        
        $app->redirect('admin/brands');
    } elseif ($action === 'delete') {
        $brand_id = $_POST['brand_id'] ?? 0;
        
        $stmt = $db->prepare("DELETE FROM brands WHERE id = ?");
        $stmt->execute([$brand_id]);
        
        $app->setFlashMessage('success', 'Brand deleted successfully');
        $app->redirect('admin/brands');
    }
}

// Get all brands
$stmt = $db->prepare("
    SELECT b.*, 
           (SELECT COUNT(*) FROM products WHERE brand_id = b.id) as product_count
    FROM brands b 
    ORDER BY b.name ASC
");
$stmt->execute();
$brands = $stmt->fetchAll();

// Start content
$content = '
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Brand Management</h1>
        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addBrandModal">
            <i class="fas fa-plus me-2"></i> Add New Brand
        </button>
    </div>

    <!-- Flash Messages -->
    <div class="mb-4">';

// Add success message
if ($app->getFlashMessage("success")) {
    $content .= '
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            ' . $app->getFlashMessage("success") . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
}

// Add error message  
if ($app->getFlashMessage("error")) {
    $content .= '
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ' . $app->getFlashMessage("error") . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
}

// Continue with rest of content
$content .= '
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Logo</th>
                            <th>Brand Name</th>
                            <th>Slug</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

if (empty($brands)) {
    $content .= '
        <tr>
            <td colspan="7" class="text-center py-4">
                <div class="text-muted">
                    <i class="fas fa-tag fa-2x mb-3"></i>
                    <p>No brands found. Add your first brand!</p>
                </div>
            </td>
        </tr>';
} else {
    foreach ($brands as $brand) {
        $status_badge = $brand['is_active'] ? 
            '<span class="badge bg-success">Active</span>' : 
            '<span class="badge bg-secondary">Inactive</span>';
        
        $logo_html = $brand['logo'] ? 
            '<img src="' . SITE_URL . $brand['logo'] . '" alt="' . htmlspecialchars($brand['name']) . '" style="width: 40px; height: 40px; object-fit: contain; border-radius: 4px;">' : 
            '<div class="bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border-radius: 4px;">
                <i class="fas fa-tag text-muted"></i>
            </div>';
        
        $content .= '
        <tr>
            <td>' . $brand['id'] . '</td>
            <td>' . $logo_html . '</td>
            <td>
                <strong>' . htmlspecialchars($brand['name']) . '</strong>
                ' . ($brand['description'] ? '<br><small class="text-muted">' . htmlspecialchars(substr($brand['description'], 0, 50)) . '...</small>' : '') . '
            </td>
            <td><code>' . htmlspecialchars($brand['slug']) . '</code></td>
            <td><span class="badge bg-info">' . $brand['product_count'] . '</span></td>
            <td>' . $status_badge . '</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary edit-brand" data-brand=\'' . json_encode($brand) . '\'>
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-danger delete-brand" data-id="' . $brand['id'] . '" data-name="' . htmlspecialchars($brand['name']) . '">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
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
</div>

<!-- Add Brand Modal -->
<div class="modal fade" id="addBrandModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="' . SITE_URL . 'admin/brands" method="POST" enctype="multipart/form-data">
                ' . $app->csrfField() . '
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Brand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Brand Name *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Slug</label>
                                <input type="text" class="form-control" name="slug" placeholder="auto-generates-from-name">
                                <small class="text-muted">URL-friendly version of the name</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Brand description..."></textarea>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Brand Logo</label>
                                <div class="border rounded p-3 text-center mb-2" style="height: 150px;">
                                    <img id="logoPreview" src="' . SITE_URL . 'admin/assets/img/placeholder.png" class="img-fluid h-100" style="object-fit: contain;">
                                </div>
                                <input type="file" class="form-control" name="logo" id="logoInput" accept="image/*">
                                <small class="text-muted">Recommended: 300x300px, PNG or JPG</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Save Brand</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Brand Modal -->
<div class="modal fade" id="editBrandModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="' . SITE_URL . 'admin/brands" method="POST" enctype="multipart/form-data">
                ' . $app->csrfField() . '
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="brand_id" id="editBrandId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Brand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Brand Name *</label>
                                <input type="text" class="form-control" name="name" id="editName" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Slug</label>
                                <input type="text" class="form-control" name="slug" id="editSlug">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
                                <label class="form-check-label" for="editIsActive">Active</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Brand Logo</label>
                                <div class="border rounded p-3 text-center mb-2" style="height: 150px;">
                                    <img id="editLogoPreview" src="' . SITE_URL . 'admin/assets/img/placeholder.png" class="img-fluid h-100" style="object-fit: contain;">
                                </div>
                                <input type="file" class="form-control" name="logo" id="editLogoInput" accept="image/*">
                                <small class="text-muted">Leave empty to keep current logo</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Update Brand</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteBrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="' . SITE_URL . 'admin/brands" method="POST">
                ' . $app->csrfField() . '
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="brand_id" id="deleteBrandId">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Brand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteBrandName"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. Products linked to this brand will have their brand set to NULL.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Brand</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Logo preview for add modal
document.getElementById(\'logoInput\').addEventListener(\'change\', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(\'logoPreview\').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});

// Edit brand
document.querySelectorAll(\'.edit-brand\').forEach(btn => {
    btn.addEventListener(\'click\', function() {
        const brand = JSON.parse(this.dataset.brand);
        
        // Populate form
        document.getElementById(\'editBrandId\').value = brand.id;
        document.getElementById(\'editName\').value = brand.name;
        document.getElementById(\'editSlug\').value = brand.slug;
        document.getElementById(\'editDescription\').value = brand.description || \'\';
        document.getElementById(\'editIsActive\').checked = brand.is_active == 1;
        
        // Set logo preview
        if (brand.logo) {
            document.getElementById(\'editLogoPreview\').src = \'' . SITE_URL . '\' + brand.logo;
        } else {
            document.getElementById(\'editLogoPreview\').src = \'' . SITE_URL . 'admin/assets/img/placeholder.png\';
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById(\'editBrandModal\'));
        modal.show();
    });
});

// Delete brand
document.querySelectorAll(\'.delete-brand\').forEach(btn => {
    btn.addEventListener(\'click\', function() {
        const brandId = this.dataset.id;
        const brandName = this.dataset.name;
        
        document.getElementById(\'deleteBrandId\').value = brandId;
        document.getElementById(\'deleteBrandName\').textContent = brandName;
        
        const modal = new bootstrap.Modal(document.getElementById(\'deleteBrandModal\'));
        modal.show();
    });
});

// Auto-generate slug from name
document.querySelector(\'#addBrandModal input[name="name"]\').addEventListener(\'blur\', function() {
    const nameInput = this.value.trim();
    const slugInput = document.querySelector(\'#addBrandModal input[name="slug"]\');
    
    if (nameInput && !slugInput.value) {
        // Simple slug generation
        const slug = nameInput.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, \'\')
            .replace(/\s+/g, \'-\')
            .replace(/--+/g, \'-\')
            .trim();
        slugInput.value = slug;
    }
});

// Same for edit modal
document.querySelector(\'#editBrandModal input[name="name"]\').addEventListener(\'blur\', function() {
    const nameInput = this.value.trim();
    const slugInput = document.querySelector(\'#editBrandModal input[name="slug"]\');
    
    if (nameInput && (!slugInput.value || slugInput.value === \'auto-generates-from-name\')) {
        const slug = nameInput.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, \'\')
            .replace(/\s+/g, \'-\')
            .replace(/--+/g, \'-\')
            .trim();
        slugInput.value = slug;
    }
});

// Edit logo preview
document.getElementById(\'editLogoInput\').addEventListener(\'change\', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(\'editLogoPreview\').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});
</script>';

echo adminLayout($content, 'Brand Management');
?>