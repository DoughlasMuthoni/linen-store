<?php
// /linen-closet/admin/categories.php

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
// 2. INITIALIZE VARIABLES & UPLOAD CONFIG
// ====================================================================

$error = '';
$success = '';

// Upload configuration
$upload_dir = __DIR__ . '/../uploads/categories/';
$max_file_size = 5 * 1024 * 1024; // 5MB
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ====================================================================
// 3. FORM HANDLING
// ====================================================================

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $name = $app->sanitize($_POST['name'] ?? '');
            $slug = $app->sanitize($_POST['slug'] ?? '');
            $description = $app->sanitize($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                throw new Exception('Category name is required');
            }
            
            if (empty($slug)) {
                $slug = $app->generateSlug($name);
            } else {
                $slug = $app->generateSlug($slug);
            }
            
            // Handle file upload
            $image_url = '';
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image_file'];
                
                // Validate file
                if ($file['size'] > $max_file_size) {
                    throw new Exception('File size exceeds 5MB limit');
                }
                
                $file_type = mime_content_type($file['tmp_name']);
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception('Invalid file type. Allowed: JPEG, PNG, GIF, WebP');
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = $slug . '-' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $image_url = 'uploads/categories/' . $filename;
                } else {
                    throw new Exception('Failed to upload file');
                }
            } elseif (!empty($_POST['image_url'])) {
                // Use existing image URL from text input
                $image_url = $app->sanitize($_POST['image_url']);
            }
            
            if ($action === 'add') {
                // Add new category
                $stmt = $db->prepare("
                    INSERT INTO categories (name, slug, description, image_url, parent_id, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $slug, $description, $image_url, $parent_id, $is_active]);
                $success = 'Category added successfully!';
            } else {
                // Edit existing category
                $category_id = (int)$_POST['category_id'];
                
                // Check if trying to set parent as itself
                if ($parent_id === $category_id) {
                    throw new Exception('Category cannot be its own parent');
                }
                
                // If new file uploaded, delete old file
                if (!empty($image_url) && isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                    $old_image_path = __DIR__ . '/../' . $_POST['current_image'];
                    if (file_exists($old_image_path) && $image_url !== $_POST['current_image']) {
                        unlink($old_image_path);
                    }
                }
                
                $stmt = $db->prepare("
                    UPDATE categories 
                    SET name = ?, slug = ?, description = ?, image_url = ?, parent_id = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $slug, $description, $image_url, $parent_id, $is_active, $category_id]);
                $success = 'Category updated successfully!';
            }
        } elseif ($action === 'delete') {
            $category_id = (int)$_POST['category_id'];
            
            // Get category image before deleting
            $stmt = $db->prepare("SELECT image_url FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $category = $stmt->fetch();
            
            // Check if category has products
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $checkStmt->execute([$category_id]);
            $productCount = $checkStmt->fetchColumn();
            
            if ($productCount > 0) {
                throw new Exception('Cannot delete category that has products. Move or delete the products first.');
            }
            
            // Check if category has subcategories
            $subCheckStmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
            $subCheckStmt->execute([$category_id]);
            $subCount = $subCheckStmt->fetchColumn();
            
            if ($subCount > 0) {
                throw new Exception('Cannot delete category that has subcategories. Delete the subcategories first.');
            }
            
            $deleteStmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $deleteStmt->execute([$category_id]);
            
            // Delete associated image file
            if ($category && !empty($category['image_url']) && file_exists(__DIR__ . '/../' . $category['image_url'])) {
                unlink(__DIR__ . '/../' . $category['image_url']);
            }
            
            $success = 'Category deleted successfully!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ====================================================================
// 4. FETCH DATA
// ====================================================================

// Get all categories with hierarchy
$categories = $db->query("
    SELECT 
        c1.*,
        c2.name as parent_name,
        (SELECT COUNT(*) FROM products p WHERE p.category_id = c1.id) as product_count,
        (SELECT COUNT(*) FROM categories c3 WHERE c3.parent_id = c1.id) as subcategory_count
    FROM categories c1
    LEFT JOIN categories c2 ON c1.parent_id = c2.id
    ORDER BY c1.parent_id IS NULL DESC, c1.parent_id, c1.name
")->fetchAll();

// Get parent categories for dropdown
$parentCategories = $db->query("
    SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name
")->fetchAll();

// ====================================================================
// 5. BUILD CONTENT
// ====================================================================

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Category Management</h1>
            <p class="text-muted mb-0">Organize your products into categories and subcategories.</p>
        </div>
        <div>
            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i> Add Category
            </button>
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

    <!-- Categories Table -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Categories (' . count($categories) . ')</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Image</th>
                            <th>Slug</th>
                            <th>Parent</th>
                            <th>Products</th>
                            <th>Subcategories</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
if (count($categories) > 0) {
    foreach ($categories as $category) {
        $indent = $category['parent_id'] ? 'ps-4' : '';
        $statusClass = $category['is_active'] ? 'success' : 'secondary';
        $statusText = $category['is_active'] ? 'Active' : 'Inactive';
        
        // Fix the image URL - add SITE_URL if it\'s a relative path
        $imageUrl = $category['image_url'];
        if ($imageUrl && !preg_match('/^https?:\/\//', $imageUrl) && !preg_match('/^\//', $imageUrl)) {
            $imageUrl = SITE_URL . $imageUrl;
        }
        
        $content .= '
                        <tr>
                            <td class="' . $indent . '">
                                <div class="fw-bold">' . htmlspecialchars($category['name']) . '</div>
                                <small class="text-muted">' . htmlspecialchars($category['description'] ?? '') . '</small>
                            </td>
                            <td>
                                ' . ($category['image_url'] ? 
                                    '<img src="' . $imageUrl . '" 
                                        class="rounded" 
                                        style="width: 40px; height: 40px; object-fit: cover;" 
                                        alt="' . htmlspecialchars($category['name']) . '"
                                        onerror="this.onerror=null; this.src=\'' . SITE_URL . 'assets/images/placeholder.jpg\';">' : 
                                    '<span class="text-muted">No image</span>') . '
                            </td>
                            <td>' . htmlspecialchars($category['slug']) . '</td>
                            <td>' . ($category['parent_name'] ? htmlspecialchars($category['parent_name']) : '<em class="text-muted">None</em>') . '</td>
                            <td>
                                <span class="badge bg-info">' . $category['product_count'] . '</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">' . $category['subcategory_count'] . '</span>
                            </td>
                            <td>
                                <span class="badge bg-' . $statusClass . '">' . $statusText . '</span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" 
                                            class="btn btn-outline-secondary"
                                            onclick="editCategory(' . $category['id'] . ')"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-danger confirm-delete-category"
                                            data-id="' . $category['id'] . '"
                                            data-name="' . htmlspecialchars($category['name']) . '"
                                            data-products="' . $category['product_count'] . '"
                                            data-subcategories="' . $category['subcategory_count'] . '"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>';
    }
} else {
    $content .= '
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                <h5>No Categories Found</h5>
                                <p class="text-muted">Start by adding your first category.</p>
                                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-plus me-2"></i> Add First Category
                                </button>
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                ' . $app->csrfField() . '
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="name" 
                               name="name" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" 
                               class="form-control" 
                               id="slug" 
                               name="slug"
                               placeholder="Auto-generated from name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category Image</label>
                        <div class="card border">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small">Upload New Image</label>
                                        <input type="file" 
                                               class="form-control form-control-sm" 
                                               id="image_file" 
                                               name="image_file"
                                               accept="image/*">
                                        <small class="text-muted d-block mt-1">Max size: 5MB, Allowed: JPG, PNG, GIF, WebP</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">OR Use Existing URL</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="image_url" 
                                                   name="image_url"
                                                   placeholder="e.g., uploads/categories/image.jpg">
                                            <button type="button" class="btn btn-outline-secondary" onclick="browseMedia(\'image_url\')">
                                                <i class="fas fa-image"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 text-center" id="image_preview_container" style="display: none;">
                                    <small class="text-muted">Preview:</small>
                                    <img src="" id="image_preview" class="img-fluid rounded mt-1" style="max-height: 150px; max-width: 200px; object-fit: cover;">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Parent Category</label>
                        <select class="form-control" id="parent_id" name="parent_id">
                            <option value="">None (Main Category)</option>';
foreach ($parentCategories as $parent) {
    $content .= '<option value="' . $parent['id'] . '">' . htmlspecialchars($parent['name']) . '</option>';
}
$content .= '
                        </select>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               value="1" 
                               checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                ' . $app->csrfField() . '
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="category_id" id="edit_category_id">
                <input type="hidden" name="current_image" id="edit_current_image">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="edit_name" 
                               name="name" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_slug" class="form-label">Slug</label>
                        <input type="text" 
                               class="form-control" 
                               id="edit_slug" 
                               name="slug">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="edit_description" 
                                  name="description" 
                                  rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category Image</label>
                        <div class="card border">
                            <div class="card-body">
                                <div id="edit_current_image_container">
                                    <!-- Current image will be shown here -->
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small">Upload New Image</label>
                                        <input type="file" 
                                               class="form-control form-control-sm" 
                                               id="edit_image_file" 
                                               name="image_file"
                                               accept="image/*">
                                        <small class="text-muted d-block mt-1">Max size: 5MB, Allowed: JPG, PNG, GIF, WebP</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">OR Use Existing URL</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="edit_image_url" 
                                                   name="image_url"
                                                   placeholder="e.g., uploads/categories/image.jpg">
                                            <button type="button" class="btn btn-outline-secondary" onclick="browseMedia(\'edit_image_url\')">
                                                <i class="fas fa-image"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 text-center" id="edit_image_preview_container" style="display: none;">
                                    <small class="text-muted">Preview:</small>
                                    <img src="" id="edit_image_preview" class="img-fluid rounded mt-1" style="max-height: 150px; max-width: 200px; object-fit: cover;">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_parent_id" class="form-label">Parent Category</label>
                        <select class="form-control" id="edit_parent_id" name="parent_id">
                            <option value="">None (Main Category)</option>';
foreach ($parentCategories as $parent) {
    $content .= '<option value="' . $parent['id'] . '">' . htmlspecialchars($parent['name']) . '</option>';
}
$content .= '
                        </select>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="edit_is_active" 
                               name="is_active" 
                               value="1">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Form -->
<form method="POST" id="deleteCategoryForm" class="d-none">
    ' . $app->csrfField() . '
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="category_id" id="delete_category_id">
</form>

<!-- Set global JavaScript variables -->
<script>
// Pass PHP variables to JavaScript
window.SITE_URL = "' . SITE_URL . '";
console.log("SITE_URL set to:", window.SITE_URL);
</script>

<style>
/* Additional styles for image upload */
.image-upload-container {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.3s;
}

.image-upload-container:hover {
    border-color: #6c757d;
}

.image-upload-container.dragover {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

.current-image {
    max-width: 200px;
    max-height: 150px;
    object-fit: cover;
}
</style>

<!-- Include external JavaScript file -->
<script src="' . SITE_URL . 'assets/js/categories.js"></script>

<!-- Additional JavaScript for image upload preview -->
<script>
// File upload preview for add form
document.getElementById("image_file").addEventListener("change", function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById("image_preview");
            preview.src = e.target.result;
            document.getElementById("image_preview_container").style.display = "block";
        };
        reader.readAsDataURL(file);
        // Clear URL input
        document.getElementById("image_url").value = "";
    }
});

// File upload preview for edit form
document.getElementById("edit_image_file").addEventListener("change", function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById("edit_image_preview");
            preview.src = e.target.result;
            document.getElementById("edit_image_preview_container").style.display = "block";
        };
        reader.readAsDataURL(file);
        // Clear URL input
        document.getElementById("edit_image_url").value = "";
    }
});

// Preview for URL input
document.getElementById("image_url").addEventListener("input", function() {
    if (this.value) {
        const preview = document.getElementById("image_preview");
        preview.src = window.SITE_URL + this.value;
        document.getElementById("image_preview_container").style.display = "block";
    }
});

document.getElementById("edit_image_url").addEventListener("input", function() {
    if (this.value) {
        const preview = document.getElementById("edit_image_preview");
        preview.src = window.SITE_URL + this.value;
        document.getElementById("edit_image_preview_container").style.display = "block";
    }
});

// Auto-generate slug from name
document.getElementById("name").addEventListener("input", function() {
    if (!document.getElementById("slug").value) {
        const slug = this.value.toLowerCase()
            .replace(/[^\\w\\s-]/g, "")
            .replace(/\\s+/g, "-");
        document.getElementById("slug").value = slug;
    }
});
</script>';

// ====================================================================
// 6. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Category Management');