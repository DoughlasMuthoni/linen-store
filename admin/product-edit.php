<?php
// /linen-closet/admin/product-edit.php

// ====================================================================
// 1. INCLUDES & INITIALIZATION
// ====================================================================

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

if (!$app->isLoggedIn() || !$app->isAdmin()) {
    $app->redirect('auth/login');
}

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$productId) {
    $app->redirect('admin/products');
}

// Fetch product data
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, b.name as brand_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    $app->setFlashMessage('error', 'Product not found');
    $app->redirect('admin/products');
}

// Fetch product images with variant associations
$imageStmt = $db->prepare("
    SELECT pi.*, 
           pv.id as variant_id, 
           pv.size, 
           pv.color,
           CONCAT(
               COALESCE(pv.size, ''),
               CASE WHEN pv.size IS NOT NULL AND pv.color IS NOT NULL THEN ' - ' ELSE '' END,
               COALESCE(pv.color, '')
           ) as variant_name
    FROM product_images pi
    LEFT JOIN product_variants pv ON pi.variant_id = pv.id
    WHERE pi.product_id = ? 
    ORDER BY pi.is_primary DESC, pi.sort_order ASC
");
$imageStmt->execute([$productId]);
$productImages = $imageStmt->fetchAll();

// Fetch product variants
$variantStmt = $db->prepare("
    SELECT * FROM product_variants 
    WHERE product_id = ? 
    ORDER BY is_default DESC, size, color
");
$variantStmt->execute([$productId]);
$variants = $variantStmt->fetchAll();

// Get categories and brands for dropdowns
$categories = $db->query("
    SELECT c.*, 
           (SELECT name FROM categories pc WHERE pc.id = c.parent_id) as parent_name
    FROM categories c 
    WHERE c.is_active = 1 
    ORDER BY c.parent_id, c.name
")->fetchAll();

$brands = $db->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();

// ====================================================================
// 2. FORM HANDLING
// ====================================================================

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$app->verifyCsrfToken()) {
            throw new Exception('Invalid form submission. Please try again.');
        }
        
        $productData = array_map([$app, 'sanitize'], $_POST);
        
        // Required fields validation
        if (empty($productData['name'])) {
            throw new Exception('Product name is required');
        }
        
        if (empty($productData['price']) || !is_numeric($productData['price']) || $productData['price'] <= 0) {
            throw new Exception('Valid price is required');
        }
        
        if (empty($productData['category_id'])) {
            throw new Exception('Category is required');
        }
        
        if (empty($productData['sku'])) {
            throw new Exception('SKU is required');
        }
        
        // Check if SKU already exists (excluding current product)
        $skuCheck = $db->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $skuCheck->execute([$productData['sku'], $productId]);
        if ($skuCheck->fetch()) {
            throw new Exception('SKU already exists. Please use a unique SKU.');
        }
        
        // Handle slug
        if (empty($productData['slug'])) {
            $productData['slug'] = $app->generateSlug($productData['name']);
        } else {
            $productData['slug'] = $app->generateSlug($productData['slug']);
        }
        
        // Check if slug already exists (excluding current product)
        $slugCheck = $db->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
        $slugCheck->execute([$productData['slug'], $productId]);
        if ($slugCheck->fetch()) {
            throw new Exception('Product slug already exists. Please use a different slug.');
        }
        
        // Convert boolean values
        $productData['is_featured'] = isset($productData['is_featured']) ? 1 : 0;
        $productData['is_active'] = isset($productData['is_active']) ? 1 : 0;
        
        // Convert numeric values
        $productData['price'] = floatval($productData['price']);
        $productData['compare_price'] = !empty($productData['compare_price']) ? floatval($productData['compare_price']) : null;
        $productData['stock_quantity'] = intval($productData['stock_quantity']);
        $productData['weight'] = !empty($productData['weight']) ? floatval($productData['weight']) : null;
        
        // Start transaction
        $db->beginTransaction();
        
        // Update product
        $updateStmt = $db->prepare("
            UPDATE products SET
                name = ?, slug = ?, description = ?, short_description = ?, 
                price = ?, compare_price = ?, category_id = ?, brand_id = ?,
                sku = ?, stock_quantity = ?, is_featured = ?, is_active = ?,
                care_instructions = ?, materials = ?, weight = ?, dimensions = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $productData['name'],
            $productData['slug'],
            $productData['description'],
            $productData['short_description'],
            $productData['price'],
            $productData['compare_price'],
            $productData['category_id'],
            $productData['brand_id'],
            $productData['sku'],
            $productData['stock_quantity'],
            $productData['is_featured'],
            $productData['is_active'],
            $productData['care_instructions'],
            $productData['materials'],
            $productData['weight'],
            $productData['dimensions'],
            $productId
        ]);
        
        // ============================================
        // FIXED: Handle variants FIRST to get their IDs
        // ============================================
        
        // Temporarily disable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        
        // First, delete all existing variants
        $deleteVariantsStmt = $db->prepare("DELETE FROM product_variants WHERE product_id = ?");
        $deleteVariantsStmt->execute([$productId]);
        
        $newVariants = []; // Store new variant IDs by their form index
        
        // Then insert new variants
        if (isset($_POST['variants']) && is_array($_POST['variants'])) {
            $hasDefault = false;
            foreach ($_POST['variants'] as $index => $variant) {
                if (!empty($variant['size']) || !empty($variant['color'])) {
                    $variantSku = !empty($variant['sku']) ? $variant['sku'] : $productData['sku'] . '-' . substr(uniqid(), -6);
                    $variantPrice = !empty($variant['price']) ? floatval($variant['price']) : $productData['price'];
                    $stockQuantity = intval($variant['stock_quantity'] ?? $productData['stock_quantity']);
                    $isDefault = isset($variant['is_default']) && $variant['is_default'] == '1' ? 1 : 0;
                    
                    // Ensure only one default variant
                    if ($isDefault && $hasDefault) {
                        $isDefault = 0;
                    }
                    if ($isDefault) {
                        $hasDefault = true;
                    }
                    
                    $variantStmt = $db->prepare("
                        INSERT INTO product_variants (
                            product_id, sku, size, color, color_code, price, 
                            compare_price, stock_quantity, is_default
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $variantStmt->execute([
                        $productId,
                        $variantSku,
                        !empty($variant['size']) ? $variant['size'] : null,
                        !empty($variant['color']) ? $variant['color'] : null,
                        !empty($variant['color_code']) ? '#' . ltrim($variant['color_code'], '#') : null,
                        $variantPrice,
                        !empty($variant['compare_price']) ? floatval($variant['compare_price']) : null,
                        $stockQuantity,
                        $isDefault
                    ]);
                    
                    // Store the new variant ID with its form index
                    $newVariants[$index] = $db->lastInsertId();
                }
            }
        }
        
        // Re-enable foreign key checks
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        
        // ============================================
        // Handle image deletions
        // ============================================
        if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $imageId) {
                // Get image path before deletion
                $imgStmt = $db->prepare("SELECT image_url FROM product_images WHERE id = ? AND product_id = ?");
                $imgStmt->execute([$imageId, $productId]);
                $image = $imgStmt->fetch();
                
                if ($image) {
                    // Delete file from server
                    $filePath = SITE_PATH . ltrim($image['image_url'], '/');
                    if (file_exists($filePath) && !str_starts_with($image['image_url'], 'http')) {
                        @unlink($filePath);
                    }
                    
                    // Delete from database
                    $deleteStmt = $db->prepare("DELETE FROM product_images WHERE id = ?");
                    $deleteStmt->execute([$imageId]);
                }
            }
        }
        
        // ============================================
        // FIXED: Update existing images variant associations
        // Now using the new variant IDs from $newVariants array
        // ============================================
        if (isset($_POST['image_variants']) && is_array($_POST['image_variants'])) {
            foreach ($_POST['image_variants'] as $imageId => $variantIndex) {
                // variantIndex is the form index (0, 1, 2, etc.)
                // Convert it to actual variant ID using $newVariants array
                $variantId = null;
                if ($variantIndex !== '' && isset($newVariants[$variantIndex])) {
                    $variantId = $newVariants[$variantIndex];
                }
                
                $updateStmt = $db->prepare("
                    UPDATE product_images 
                    SET variant_id = ? 
                    WHERE id = ? AND product_id = ?
                ");
                $updateStmt->execute([$variantId, $imageId, $productId]);
            }
        }
        
        // ============================================
        // Update image sort order and primary
        // ============================================
        if (isset($_POST['image_order']) && is_array($_POST['image_order'])) {
            // First, set all images as non-primary
            $resetPrimaryStmt = $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
            $resetPrimaryStmt->execute([$productId]);
            
            foreach ($_POST['image_order'] as $index => $imageId) {
                $isPrimary = isset($_POST['primary_image']) && $_POST['primary_image'] == $imageId ? 1 : 0;
                $updateImageStmt = $db->prepare("
                    UPDATE product_images 
                    SET sort_order = ?, is_primary = ? 
                    WHERE id = ? AND product_id = ?
                ");
                $updateImageStmt->execute([$index + 1, $isPrimary, $imageId, $productId]);
            }
        }
        
        // ============================================
        // FIXED: Handle new image uploads with proper variant associations
        // ============================================
        if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
            $uploadDir = SITE_PATH . 'assets/images/products/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $currentImageCount = count($productImages) - count($_POST['delete_images'] ?? []);
            
            for ($i = 0; $i < count($_FILES['new_images']['name']); $i++) {
                if ($_FILES['new_images']['error'][$i] === UPLOAD_ERR_OK) {
                    // Validate file
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $fileType = $_FILES['new_images']['type'][$i];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
                    }
                    
                    if ($_FILES['new_images']['size'][$i] > 5 * 1024 * 1024) {
                        throw new Exception('File size too large. Maximum size is 5MB.');
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION);
                    $filename = 'product-' . $productId . '-' . uniqid() . '.' . $extension;
                    $filePath = $uploadDir . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['new_images']['tmp_name'][$i], $filePath)) {
                        // Get variant ID for this image (variantIndex is the form index)
                        $variantId = null;
                        if (isset($_POST['new_image_variants'][$i]) && $_POST['new_image_variants'][$i] !== '') {
                            $variantIndex = $_POST['new_image_variants'][$i];
                            if (isset($newVariants[$variantIndex])) {
                                $variantId = $newVariants[$variantIndex];
                            }
                        }
                        
                        $isPrimary = ($currentImageCount === 0 && $i === 0) ? 1 : 0;
                        $imageStmt = $db->prepare("
                            INSERT INTO product_images (product_id, variant_id, image_url, is_primary, sort_order)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $imageUrl = 'assets/images/products/' . $filename;
                        $sortOrder = $currentImageCount + $i + 1;
                        $imageStmt->execute([$productId, $variantId, $imageUrl, $isPrimary, $sortOrder]);
                    } else {
                        throw new Exception('Failed to upload image: ' . $_FILES['new_images']['name'][$i]);
                    }
                }
            }
        }
        
        // Commit transaction
        $db->commit();
        
        // Set success message and refresh data
        $app->setFlashMessage('success', 'Product updated successfully!');
        $app->redirect('admin/products/edit?id=' . $productId);
        
    } catch (Exception $e) {
        // Rollback on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

// ====================================================================
// 3. BUILD CONTENT
// ====================================================================

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Edit Product</h1>
            <p class="text-muted mb-0">' . htmlspecialchars($product['name']) . '</p>
        </div>
        <div>
            <a href="' . SITE_URL . 'admin/products" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i> Back to Products
            </a>
            <a href="' . SITE_URL . 'products/' . $product['slug'] . '" 
               class="btn btn-outline-primary" 
               target="_blank">
                <i class="fas fa-eye me-2"></i> View Product
            </a>
        </div>
    </div>

    <!-- Error/Success Messages -->
    ' . ($error ? '
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($error) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>' : '') . '

    <!-- Flash Message -->
    ' . $app->displayFlashMessage() . '

    <!-- Product Form -->
    <form method="POST" enctype="multipart/form-data" id="productForm">
        ' . $app->csrfField() . '
        
        <div class="row">
            <!-- Left Column - Basic Information -->
            <div class="col-lg-8">
                <!-- Basic Information Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="name" class="form-label">Product Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="' . htmlspecialchars($product['name'] ?? '') . '" 
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="slug" class="form-label">Product Slug</label>
                                <div class="input-group">
                                    <span class="input-group-text">' . SITE_URL . 'products/</span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="slug" 
                                           name="slug" 
                                           value="' . htmlspecialchars($product['slug'] ?? '') . '">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="sku" class="form-label">SKU *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="sku" 
                                       name="sku" 
                                       value="' . htmlspecialchars($product['sku'] ?? '') . '" 
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-control select2" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>';
foreach ($categories as $category) {
    $selected = ($product['category_id'] ?? '') == $category['id'] ? 'selected' : '';
    $displayName = $category['parent_name'] ? $category['parent_name'] . ' → ' . $category['name'] : $category['name'];
    $content .= '<option value="' . $category['id'] . '" ' . $selected . '>' . htmlspecialchars($displayName) . '</option>';
}
$content .= '
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="brand_id" class="form-label">Brand</label>
                                <select class="form-control select2" id="brand_id" name="brand_id">
                                    <option value="">No Brand</option>';
foreach ($brands as $brand) {
    $selected = ($product['brand_id'] ?? '') == $brand['id'] ? 'selected' : '';
    $content .= '<option value="' . $brand['id'] . '" ' . $selected . '>' . htmlspecialchars($brand['name']) . '</option>';
}
$content .= '
                                </select>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="short_description" class="form-label">Short Description</label>
                                <textarea class="form-control" 
                                          id="short_description" 
                                          name="short_description" 
                                          rows="2">' . htmlspecialchars($product['short_description'] ?? '') . '</textarea>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="description" class="form-label">Full Description *</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="6" 
                                          required>' . htmlspecialchars($product['description'] ?? '') . '</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pricing Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pricing</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="price" class="form-label">Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">Ksh</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="price" 
                                           name="price" 
                                           value="' . htmlspecialchars($product['price'] ?? '0.00') . '" 
                                           step="0.01" 
                                           min="0" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="compare_price" class="form-label">Compare at Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">Ksh</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="compare_price" 
                                           name="compare_price" 
                                           value="' . htmlspecialchars($product['compare_price'] ?? '') . '" 
                                           step="0.01" 
                                           min="0">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="stock_quantity" 
                                       name="stock_quantity" 
                                       value="' . htmlspecialchars($product['stock_quantity'] ?? '0') . '" 
                                       min="0">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- UPDATED: Images Card with Variant Association -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Product Images</h6>
                        <small class="text-muted">Drag to reorder, select variant for each image</small>
                    </div>
                    <div class="card-body">
                        <!-- Existing Images with Variant Selector -->
                        <div id="existingImages" class="row g-2 mb-4">';
if (count($productImages) > 0) {
    foreach ($productImages as $index => $image) {
        $isPrimary = $image['is_primary'] ? 'checked' : '';
        $variantName = $image['variant_name'] ? htmlspecialchars($image['variant_name']) : 'All Variants';
        
        $content .= '
                            <div class="col-md-4 image-item" data-id="' . $image['id'] . '">
                                <div class="card h-100">
                                    <img src="' . SITE_URL . $image['image_url'] . '" 
                                         class="card-img-top" 
                                         style="height: 150px; object-fit: cover;"
                                         onerror="this.onerror=null; this.src=\'' . SITE_URL . 'assets/images/placeholder.jpg\';">
                                    <div class="card-body p-2">
                                        <!-- Variant Association Dropdown -->
                                        <div class="mb-2">
                                            <label class="form-label small">Associate with Variant:</label>
                                            <select class="form-control form-control-sm image-variant-select" 
                                                    name="image_variants[' . $image['id'] . ']">
                                                <option value="">All Variants (Default)</option>';
        // We need to find which variant index matches this image's variant_id
        $selectedVariantIndex = '';
        foreach ($variants as $variantIndex => $variant) {
            $variantLabel = '';
            if ($variant['size']) $variantLabel .= $variant['size'];
            if ($variant['size'] && $variant['color']) $variantLabel .= ' - ';
            if ($variant['color']) $variantLabel .= $variant['color'];
            if (!$variantLabel) $variantLabel = 'Default Variant';
            
            // Check if this variant matches the image's variant_id
            if ($image['variant_id'] == $variant['id']) {
                $selectedVariantIndex = $variantIndex;
                $selected = 'selected';
            } else {
                $selected = '';
            }
            
            $content .= '<option value="' . $variantIndex . '" ' . $selected . '>' . htmlspecialchars($variantLabel) . '</option>';
        }
        $content .= '
                                            </select>
                                        </div>
                                        
                                        <!-- Primary and Delete Options -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input primary-image-radio" 
                                                       type="radio" 
                                                       name="primary_image" 
                                                       value="' . $image['id'] . '" 
                                                       id="primary_' . $image['id'] . '"
                                                       ' . $isPrimary . '>
                                                <label class="form-check-label small" for="primary_' . $image['id'] . '">
                                                    Primary
                                                </label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input delete-image-checkbox" 
                                                       type="checkbox" 
                                                       name="delete_images[]" 
                                                       value="' . $image['id'] . '" 
                                                       id="delete_' . $image['id'] . '">
                                                <label class="form-check-label small text-danger" for="delete_' . $image['id'] . '">
                                                    Delete
                                                </label>
                                            </div>
                                        </div>
                                        <input type="hidden" name="image_order[]" value="' . $image['id'] . '">
                                        <small class="text-muted d-block mt-1">
                                            ' . $variantName . '
                                        </small>
                                    </div>
                                </div>
                            </div>';
    }
} else {
    $content .= '
                            <div class="col-12 text-center text-muted py-4">
                                <i class="fas fa-images fa-2x mb-2"></i>
                                <p>No images uploaded yet.</p>
                            </div>';
}
$content .= '
                        </div>
                        
                       <!-- In the new image upload section -->
<div class="mb-4">
    <h6 class="fw-bold mb-3">Add More Images</h6>
    <div id="newImagesContainer">
        <div class="new-image-item mb-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Image File *</label>
                    <input class="form-control" 
                           type="file" 
                           name="new_images[]" 
                           accept="image/*" 
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Variant (Optional)</label>
                    <select class="form-control new-image-variant" 
                            name="new_image_variants[]">
                        <option value="">All Variants (Default)</option>';
foreach ($variants as $index => $variant) {
    $variantLabel = '';
    if ($variant['size']) $variantLabel .= $variant['size'];
    if ($variant['size'] && $variant['color']) $variantLabel .= ' - ';
    if ($variant['color']) $variantLabel .= $variant['color'];
    if (!$variantLabel) $variantLabel = 'Default Variant';
    
    $content .= '<option value="' . $index . '">' . htmlspecialchars($variantLabel) . '</option>';
}
$content .= '
                    </select>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeNewImageField(this)">
                <i class="fas fa-times me-1"></i> Remove
            </button>
            <div class="image-preview mt-2"></div>
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addNewImageField()">
        <i class="fas fa-plus me-1"></i> Add Another Image
    </button>
    <small class="text-muted d-block mt-2">
        You can associate images with specific variants. Images without variant association will show for all variants.
    </small>
</div>
                    </div>
                </div>
                
                <!-- Variants Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Product Variants</h6>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addVariant()">
                            <i class="fas fa-plus me-1"></i> Add Variant
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="variantsContainer">';
if (count($variants) > 0) {
    foreach ($variants as $index => $variant) {
        $colorCode = $variant['color_code'] ? str_replace('#', '', $variant['color_code']) : '';
        $content .= '
                            <div class="variant-item card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h6 class="mb-0">Variant ' . ($index + 1) . '</h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariant(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Size</label>
                                            <select class="form-control variant-size" name="variants[' . $index . '][size]">
                                                <option value="">Select Size</option>
                                                <option value="XS" ' . ($variant['size'] === 'XS' ? 'selected' : '') . '>XS</option>
                                                <option value="S" ' . ($variant['size'] === 'S' ? 'selected' : '') . '>S</option>
                                                <option value="M" ' . ($variant['size'] === 'M' ? 'selected' : '') . '>M</option>
                                                <option value="L" ' . ($variant['size'] === 'L' ? 'selected' : '') . '>L</option>
                                                <option value="XL" ' . ($variant['size'] === 'XL' ? 'selected' : '') . '>XL</option>
                                                <option value="XXL" ' . ($variant['size'] === 'XXL' ? 'selected' : '') . '>XXL</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Color</label>
                                            <input type="text" 
                                                   class="form-control variant-color" 
                                                   name="variants[' . $index . '][color]"
                                                   value="' . htmlspecialchars($variant['color'] ?? '') . '"
                                                   placeholder="e.g., Navy Blue">
                                        </div>
                                        
                                        <div class="col-md-12">
                                            <label class="form-label">Color Code (Hex)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">#</span>
                                                <input type="text" 
                                                       class="form-control variant-color-code" 
                                                       name="variants[' . $index . '][color_code]"
                                                       value="' . htmlspecialchars($colorCode) . '"
                                                       placeholder="000000" 
                                                       maxlength="6">
                                                <input type="color" 
                                                       class="form-control form-control-color" 
                                                       style="width: 50px;"
                                                       value="' . ($variant['color_code'] ?: '#000000') . '"
                                                       onchange="updateColorCode(this)">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">SKU</label>
                                            <input type="text" 
                                                   class="form-control variant-sku" 
                                                   name="variants[' . $index . '][sku]"
                                                   value="' . htmlspecialchars($variant['sku']) . '"
                                                   placeholder="Auto-generated">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Price (Ksh)</label>
                                            <input type="number" 
                                                   class="form-control variant-price" 
                                                   name="variants[' . $index . '][price]"
                                                   value="' . htmlspecialchars($variant['price']) . '"
                                                   step="0.01" 
                                                   min="0">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Stock</label>
                                            <input type="number" 
                                                   class="form-control variant-stock" 
                                                   name="variants[' . $index . '][stock_quantity]"
                                                   value="' . htmlspecialchars($variant['stock_quantity']) . '"
                                                   min="0">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-check mt-4 pt-2">
                                                <input class="form-check-input variant-default" 
                                                       type="checkbox" 
                                                       name="variants[' . $index . '][is_default]" 
                                                       value="1"
                                                       ' . ($variant['is_default'] ? 'checked' : '') . '>
                                                <label class="form-check-label">Default Variant</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>';
    }
} else {
    $content .= '
                            <div class="text-center text-muted py-4" id="noVariantsMessage">
                                <i class="fas fa-box-open fa-2x mb-2"></i>
                                <p>No variants added yet. Click "Add Variant" to add size/color options.</p>
                            </div>';
}
$content .= '
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Sidebar -->
            <div class="col-lg-4">
                <!-- Status Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_active" 
                                       name="is_active" 
                                       value="1" 
                                       ' . ($product['is_active'] ? 'checked' : '') . '>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                            <small class="text-muted">Inactive products won\'t be visible to customers</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_featured" 
                                       name="is_featured" 
                                       value="1" 
                                       ' . ($product['is_featured'] ? 'checked' : '') . '>
                                <label class="form-check-label" for="is_featured">Featured</label>
                            </div>
                            <small class="text-muted">Featured products will be highlighted</small>
                        </div>
                    </div>
                </div>
                
                <!-- Product Details Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Product Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="materials" class="form-label">Materials</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="materials" 
                                   name="materials" 
                                   value="' . htmlspecialchars($product['materials'] ?? '') . '"
                                   placeholder="e.g., 100% Organic Linen">
                        </div>
                        
                        <div class="mb-3">
                            <label for="care_instructions" class="form-label">Care Instructions</label>
                            <textarea class="form-control" 
                                      id="care_instructions" 
                                      name="care_instructions" 
                                      rows="3">' . htmlspecialchars($product['care_instructions'] ?? '') . '</textarea>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="weight" class="form-label">Weight (g)</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="weight" 
                                       name="weight" 
                                       value="' . htmlspecialchars($product['weight'] ?? '') . '"
                                       step="0.01" 
                                       min="0">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="dimensions" class="form-label">Dimensions (cm)</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="dimensions" 
                                       name="dimensions" 
                                       value="' . htmlspecialchars($product['dimensions'] ?? '') . '"
                                       placeholder="LxWxH">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Product Stats Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Product Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted d-block">Created</small>
                            <div>' . date('M j, Y', strtotime($product['created_at'])) . '</div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted d-block">Last Updated</small>
                            <div>' . date('M j, Y', strtotime($product['updated_at'])) . '</div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted d-block">Images</small>
                            <div>' . count($productImages) . ' images</div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted d-block">Variants</small>
                            <div>' . count($variants) . ' variants</div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions Card -->
                <div class="card shadow">
                    <div class="card-body">
                        <button type="submit" class="btn btn-dark btn-lg w-100 py-3 mb-3">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                        
                        <div class="d-grid gap-2">
                            <a href="' . SITE_URL . 'products/' . $product['slug'] . '" 
                               class="btn btn-outline-primary" 
                               target="_blank">
                                <i class="fas fa-eye me-2"></i> View Product
                            </a>
                            
                            <a href="' . SITE_URL . 'admin/products/duplicate/' . $product['id'] . '" 
                               class="btn btn-outline-info">
                                <i class="fas fa-copy me-2"></i> Duplicate Product
                            </a>
                            
                            <a href="' . SITE_URL . 'admin/products/delete/' . $product['id'] . '" 
                               class="btn btn-outline-danger confirm-delete">
                                <i class="fas fa-trash me-2"></i> Delete Product
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Variant Template (Hidden) -->
<div id="variantTemplate" class="d-none">
    <div class="variant-item card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h6 class="mb-0">Variant <span class="variant-number">1</span></h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVariant(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Size</label>
                    <select class="form-control variant-size" name="variants[INDEX][size]">
                        <option value="">Select Size</option>
                        <option value="XS">XS</option>
                        <option value="S">S</option>
                        <option value="M">M</option>
                        <option value="L">L</option>
                        <option value="XL">XL</option>
                        <option value="XXL">XXL</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Color</label>
                    <input type="text" 
                           class="form-control variant-color" 
                           name="variants[INDEX][color]"
                           placeholder="e.g., Navy Blue">
                </div>
                
                <div class="col-md-12">
                    <label class="form-label">Color Code (Hex)</label>
                    <div class="input-group">
                        <span class="input-group-text">#</span>
                        <input type="text" 
                               class="form-control variant-color-code" 
                               name="variants[INDEX][color_code]"
                               placeholder="000000" 
                               maxlength="6">
                        <input type="color" 
                               class="form-control form-control-color" 
                               style="width: 50px;"
                               value="#000000"
                               onchange="updateColorCode(this)">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">SKU</label>
                    <input type="text" 
                           class="form-control variant-sku" 
                           name="variants[INDEX][sku]"
                           placeholder="Auto-generated">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Price (Ksh)</label>
                    <input type="number" 
                           class="form-control variant-price" 
                           name="variants[INDEX][price]"
                           step="0.01" 
                           min="0">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Stock</label>
                    <input type="number" 
                           class="form-control variant-stock" 
                           name="variants[INDEX][stock_quantity]"
                           min="0" 
                           value="0">
                </div>
                
                <div class="col-md-6">
                    <div class="form-check mt-4 pt-2">
                        <input class="form-check-input variant-default" 
                               type="checkbox" 
                               name="variants[INDEX][is_default]" 
                               value="1">
                        <label class="form-check-label">Default Variant</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- New Image Field Template -->
<div id="newImageFieldTemplate" class="d-none">
    <div class="new-image-item mb-3">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Image File *</label>
                <input class="form-control" 
                       type="file" 
                       name="new_images[]" 
                       accept="image/*" 
                       required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Variant (Optional)</label>
                <select class="form-control new-image-variant" 
                        name="new_image_variants[]">
                    <option value="">All Variants (Default)</option>';
// Variant options will be populated dynamically in JavaScript
$content .= '
                </select>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeNewImageField(this)">
            <i class="fas fa-times me-1"></i> Remove
        </button>
        <div class="image-preview mt-2"></div>
    </div>
</div>

<script>
let variantCount = ' . count($variants) . ';
let newImageCount = 0;

// Initialize Sortable for image reordering
$(document).ready(function() {
    // Initialize select2
    $(".select2").select2({
        theme: "bootstrap-5",
        width: "100%"
    });
    
    // Make images sortable
    if (typeof Sortable !== "undefined" && document.getElementById("existingImages")) {
        new Sortable(document.getElementById("existingImages"), {
            handle: ".card-img-top",
            animation: 150,
            onUpdate: function() {
                $("#existingImages .image-item").each(function(index) {
                    $(this).find("input[name=\'image_order[]\']").val($(this).data("id"));
                });
            }
        });
    }
    
    // Preview for new images
    document.addEventListener("change", function(e) {
        if (e.target && e.target.type === "file" && e.target.name === "new_images[]") {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                const container = e.target.closest(".new-image-item");
                const previewContainer = container.querySelector(".image-preview");
                
                reader.onload = function(event) {
                    previewContainer.innerHTML = `
                        <div class="col-md-4">
                            <div class="card">
                                <img src="${event.target.result}" 
                                     class="card-img-top" 
                                     style="height: 100px; object-fit: cover;">
                                <div class="card-body p-2">
                                    <small class="d-block text-truncate">${file.name}</small>
                                    <small class="text-muted">${(file.size / 1024).toFixed(1)} KB</small>
                                </div>
                            </div>
                        </div>
                    `;
                };
                
                reader.readAsDataURL(file);
            }
        }
    });
});

// Add new image field
function addNewImageField() {
    const container = document.getElementById("newImagesContainer");
    const template = document.getElementById("newImageFieldTemplate").innerHTML;
    
    // Get current variants for dropdown
    const variants = getCurrentVariants();
    let variantOptions = \'<option value="">All Variants (Default)</option>\';
    variants.forEach(variant => {
        variantOptions += `<option value="${variant.id}">${variant.label}</option>`;
    });
    
    newImageCount++;
    const fieldHtml = template.replace(\'<option value="">All Variants (Default)</option>\', variantOptions);
    
    container.insertAdjacentHTML("beforeend", fieldHtml);
}

// Remove new image field
function removeNewImageField(button) {
    const item = button.closest(".new-image-item");
    if (item) {
        item.remove();
    }
}

// Get current variants from form
function getCurrentVariants() {
    const variants = [];
    document.querySelectorAll(".variant-item").forEach((item, index) => {
        const size = item.querySelector(".variant-size")?.value || "";
        const color = item.querySelector(".variant-color")?.value || "";
        const variantId = index; // Use index as temporary ID
        
        if (size || color) {
            let label = "";
            if (size) label += size;
            if (size && color) label += " - ";
            if (color) label += color;
            if (!label) label = "Default Variant";
            
            variants.push({
                id: variantId,
                label: label
            });
        }
    });
    return variants;
}

// Add variant function
function addVariant() {
    const container = document.getElementById("variantsContainer");
    const template = document.getElementById("variantTemplate").innerHTML;
    const noVariantsMessage = document.getElementById("noVariantsMessage");
    
    if (noVariantsMessage) {
        noVariantsMessage.remove();
    }
    
    variantCount++;
    const variantHtml = template.replace(/INDEX/g, variantCount - 1);
    
    const div = document.createElement("div");
    div.innerHTML = variantHtml;
    div.querySelector(".variant-number").textContent = variantCount;
    
    // Set default values
    const basePrice = document.getElementById("price");
    const baseSku = document.getElementById("sku");
    
    if (basePrice && basePrice.value) {
        div.querySelector(".variant-price").value = basePrice.value;
    }
    
    if (baseSku && baseSku.value) {
        div.querySelector(".variant-sku").placeholder = baseSku.value + "-SIZE-COLOR";
    }
    
    container.appendChild(div.firstElementChild);
    
    // Update variant dropdowns in image sections
    updateVariantDropdowns();
}

// Remove variant
function removeVariant(button) {
    const variantItem = button.closest(".variant-item");
    if (variantItem) {
        variantItem.remove();
        
        const container = document.getElementById("variantsContainer");
        if (container && container.children.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4" id="noVariantsMessage">
                    <i class="fas fa-box-open fa-2x mb-2"></i>
                    <p>No variants added yet. Click "Add Variant" to add size/color options.</p>
                </div>
            `;
        }
        
        // Update variant dropdowns
        updateVariantDropdowns();
    }
}

// Update all variant dropdowns when variants change
function updateVariantDropdowns() {
    const variants = getCurrentVariants();
    
    // Update existing image variant selects
    document.querySelectorAll(".image-variant-select").forEach(select => {
        const currentValue = select.value;
        select.innerHTML = \'<option value="">All Variants (Default)</option>\';
        
        variants.forEach(variant => {
            const option = document.createElement("option");
            option.value = variant.id;
            option.textContent = variant.label;
            if (currentValue == variant.id) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    });
    
    // Update new image variant selects
    document.querySelectorAll(".new-image-variant").forEach(select => {
        const currentValue = select.value;
        select.innerHTML = \'<option value="">All Variants (Default)</option>\';
        
        variants.forEach(variant => {
            const option = document.createElement("option");
            option.value = variant.id;
            option.textContent = variant.label;
            if (currentValue == variant.id) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    });
}

// Only allow one default variant
document.addEventListener("change", function(e) {
    if (e.target.classList.contains("variant-default") && e.target.checked) {
        document.querySelectorAll(".variant-default").forEach(function(checkbox) {
            if (checkbox !== e.target) {
                checkbox.checked = false;
            }
        });
    }
});

// Update color code
function updateColorCode(colorPicker) {
    const hex = colorPicker.value.substring(1);
    const input = colorPicker.previousElementSibling;
    if (input) {
        input.value = hex.toUpperCase();
    }
}

// Auto-generate variant SKU
document.addEventListener("input", function(e) {
    if (e.target.classList.contains("variant-size") || e.target.classList.contains("variant-color")) {
        const variantItem = e.target.closest(".variant-item");
        const size = variantItem.querySelector(".variant-size").value;
        const color = variantItem.querySelector(".variant-color").value;
        const baseSku = document.getElementById("sku");
        
        if (baseSku && baseSku.value && (size || color)) {
            let sku = baseSku.value;
            if (size) sku += "-" + size;
            if (color) sku += "-" + color.replace(/\\s+/g, "-").toUpperCase();
            const skuInput = variantItem.querySelector(".variant-sku");
            if (!skuInput.value || skuInput.value.startsWith(baseSku.value)) {
                skuInput.value = sku;
            }
        }
    }
});

// Confirm before leaving page if form has changes
let formChanged = false;
const form = document.getElementById("productForm");
if (form) {
    const formInputs = form.querySelectorAll("input, textarea, select");
    
    formInputs.forEach(function(input) {
        input.addEventListener("input", function() {
            formChanged = true;
        });
        
        input.addEventListener("change", function() {
            formChanged = true;
        });
    });
    
    window.addEventListener("beforeunload", function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = "You have unsaved changes. Are you sure you want to leave?";
        }
    });
    
    // Reset form changed flag on submit
    form.addEventListener("submit", function() {
        formChanged = false;
    });
}

// Add confirm delete functionality
document.querySelectorAll(".confirm-delete").forEach(function(link) {
    link.addEventListener("click", function(e) {
        e.preventDefault();
        const url = this.href;
        
        Swal.fire({
            title: "Are you sure?",
            text: "This will permanently delete this product and all its variants/images!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });
});
</script>';

// ====================================================================
// 4. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Edit Product: ' . htmlspecialchars($product['name']));
?>