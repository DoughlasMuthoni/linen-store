<?php
// /linen-closet/admin/product-add.php

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
// 2. FETCH DATA FOR DROPDOWNS
// ====================================================================

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
// 3. FORM HANDLING
// ====================================================================

$error = '';
$success = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!$app->verifyCsrfToken()) {
            throw new Exception('Invalid form submission. Please try again.');
        }
        
        // Get and validate data
        $productData = array_map([$app, 'sanitize'], $_POST);
        $formData = $productData; // Store for repopulating form
        
        // Required fields
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
        
        // Check if SKU already exists
        $skuCheck = $db->prepare("SELECT id FROM products WHERE sku = ?");
        $skuCheck->execute([$productData['sku']]);
        if ($skuCheck->fetch()) {
            throw new Exception('SKU already exists. Please use a unique SKU.');
        }
        
        // Handle slug
        if (empty($productData['slug'])) {
            $productData['slug'] = $app->generateSlug($productData['name']);
        } else {
            $productData['slug'] = $app->generateSlug($productData['slug']);
        }
        
        // Check if slug already exists
        $slugCheck = $db->prepare("SELECT id FROM products WHERE slug = ?");
        $slugCheck->execute([$productData['slug']]);
        if ($slugCheck->fetch()) {
            throw new Exception('Product slug already exists. Please use a different slug.');
        }
        
        // Convert boolean values
        $productData['is_featured'] = isset($productData['is_featured']) ? 1 : 0;
        $productData['is_active'] = isset($productData['is_active']) ? 1 : 0;
        
        // Convert numeric values
        $productData['price'] = floatval($productData['price']);
        $productData['compare_price'] = !empty($productData['compare_price']) ? floatval($productData['compare_price']) : null;
        $productData['weight'] = !empty($productData['weight']) ? floatval($productData['weight']) : null;
        
        // Start transaction
        $db->beginTransaction();
        
        // Insert product
        $stmt = $db->prepare("
            INSERT INTO products (
                name, slug, description, short_description, 
                price, compare_price, category_id, brand_id,
                sku, is_featured, is_active,
                care_instructions, materials, weight, dimensions,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $productData['name'],
            $productData['slug'],
            $productData['description'],
            $productData['short_description'],
            $productData['price'],
            $productData['compare_price'],
            $productData['category_id'],
            $productData['brand_id'],
            $productData['sku'],
            $productData['is_featured'],
            $productData['is_active'],
            $productData['care_instructions'],
            $productData['materials'],
            $productData['weight'],
            $productData['dimensions']
        ]);
        
        $productId = $db->lastInsertId();
        
        // Handle image uploads
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $uploadDir = SITE_PATH . 'assets/images/products/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    // Validate file
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $fileType = $_FILES['images']['type'][$i];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
                    }
                    
                    if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) { // 5MB
                        throw new Exception('File size too large. Maximum size is 5MB.');
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                    $filename = 'product-' . $productId . '-' . uniqid() . '.' . $extension;
                    $filePath = $uploadDir . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $filePath)) {
                        // Insert into database
                        $isPrimary = ($i === 0) ? 1 : 0;
                        $imageStmt = $db->prepare("
                            INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
                            VALUES (?, ?, ?, ?)
                        ");
                        $imageUrl = 'assets/images/products/' . $filename;
                        $imageStmt->execute([$productId, $imageUrl, $isPrimary, $i + 1]);
                    } else {
                        throw new Exception('Failed to upload image: ' . $_FILES['images']['name'][$i]);
                    }
                }
            }
        }
        
      // Handle variants
    if (isset($_POST['variants']) && is_array($_POST['variants'])) {
        $hasDefault = false;
        foreach ($_POST['variants'] as $index => $variant) {
            if (!empty($variant['size']) || !empty($variant['color'])) {
                // Generate initial variant SKU
                if (!empty($variant['sku'])) {
                    $variantSku = $variant['sku'];
                } else {
                    $variantSku = $productData['sku'] . '-' . substr(uniqid(), -6);
                }
                
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
                
                // ============================================
                // ADD THIS: SKU UNIQUENESS CHECK
                // ============================================
                $originalSku = $variantSku;
                $counter = 1;
                $skuExists = true;
                
                while ($skuExists) {
                    $checkStmt = $db->prepare("SELECT id FROM product_variants WHERE sku = ?");
                    $checkStmt->execute([$variantSku]);
                    
                    if ($checkStmt->fetch()) {
                        // SKU exists, append counter
                        $variantSku = $originalSku . '-' . $counter;
                        $counter++;
                    } else {
                        $skuExists = false;
                    }
                }
                // ============================================
                
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
            }
        }
    }

// ============================================
// ALSO UPDATE THE AUTO-CREATE DEFAULT VARIANT
// ============================================
// After product creation, ensure at least one default variant exists
if ($productId) {
    // Check if any variants were added
    $variantCount = $db->prepare("SELECT COUNT(*) FROM product_variants WHERE product_id = ?");
    $variantCount->execute([$productId]);
    
    if ($variantCount->fetchColumn() == 0) {
        // Generate unique SKU for default variant
        $baseSku = $productData['sku'];
        $variantSku = $baseSku . '-DEFAULT';
        $counter = 1;
        
        // Check if SKU already exists and generate a unique one
        $skuExists = true;
        while ($skuExists) {
            $checkStmt = $db->prepare("SELECT id FROM product_variants WHERE sku = ?");
            $checkStmt->execute([$variantSku]);
            
            if ($checkStmt->fetch()) {
                // SKU exists, generate a new one
                $variantSku = $baseSku . '-DEFAULT-' . $counter;
                $counter++;
            } else {
                $skuExists = false;
            }
        }
        
        // Create default variant with unique SKU
        $defaultVariant = $db->prepare("
            INSERT INTO product_variants 
            (product_id, sku, size, color, price, stock_quantity, is_default)
            VALUES (?, ?, 'Default', 'Default', ?, 0, 1)
        ");
        
        $defaultVariant->execute([$productId, $variantSku, $productData['price']]);
    }

        }
        
        // Commit transaction
        $db->commit();
        
        // Set success message and redirect
        $app->setFlashMessage('success', 'Product added successfully!');
        $app->redirect('admin/product-edit.php?id=' . $productId);
        
    } catch (Exception $e) {
        // Rollback on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

// ====================================================================
// 4. BUILD CONTENT
// ====================================================================

$content = '
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Add New Product</h1>
            <p class="text-muted mb-0">Fill in the details below to add a new product to your store.</p>
        </div>
        <div>
            <a href="' . SITE_URL . 'admin/products" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Products
            </a>
        </div>
    </div>

    <!-- Error/Success Messages -->
    ' . ($error ? '
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($error) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>' : '') . '

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
                                       value="' . htmlspecialchars($formData['name'] ?? '') . '" 
                                       required
                                       placeholder="e.g., Organic Linen Shirt">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="slug" class="form-label">Product Slug</label>
                                <div class="input-group">
                                    <span class="input-group-text">' . SITE_URL . 'products/</span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="slug" 
                                           name="slug" 
                                           value="' . htmlspecialchars($formData['slug'] ?? '') . '"
                                           placeholder="auto-generates-from-name">
                                </div>
                                <small class="text-muted">Leave blank to auto-generate from product name</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="sku" class="form-label">SKU *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="sku" 
                                       name="sku" 
                                       value="' . htmlspecialchars($formData['sku'] ?? '') . '" 
                                       required
                                       placeholder="e.g., LN-SHIRT-001">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-control select2" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>';
foreach ($categories as $category) {
    $selected = ($formData['category_id'] ?? '') == $category['id'] ? 'selected' : '';
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
    $selected = ($formData['brand_id'] ?? '') == $brand['id'] ? 'selected' : '';
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
                                          rows="2"
                                          placeholder="Brief description shown in product listings">' . htmlspecialchars($formData['short_description'] ?? '') . '</textarea>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="description" class="form-label">Full Description *</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="6" 
                                          required
                                          placeholder="Detailed product description with features, benefits, etc.">' . htmlspecialchars($formData['description'] ?? '') . '</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
               <!-- Pricing Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Base Pricing</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="price" class="form-label">Base Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">Ksh</span>
                                    <input type="number" 
                                        class="form-control" 
                                        id="price" 
                                        name="price" 
                                        value="' . htmlspecialchars($formData['price'] ?? '') . '" 
                                        step="0.01" 
                                        min="0" 
                                        required
                                        placeholder="0.00">
                                </div>
                                <small class="text-muted">Default price for all variants</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="compare_price" class="form-label">Compare at Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">Ksh</span>
                                    <input type="number" 
                                        class="form-control" 
                                        id="compare_price" 
                                        name="compare_price" 
                                        value="' . htmlspecialchars($formData['compare_price'] ?? '') . '" 
                                        step="0.01" 
                                        min="0"
                                        placeholder="Original price for discount display">
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Stock is managed at the variant level. Add variants below to set specific stock quantities.
                        </div>
                    </div>
                </div>
                
                <!-- Images Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Product Images</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="images" class="form-label">Upload Images *</label>
                            <input class="form-control" 
                                   type="file" 
                                   id="images" 
                                   name="images[]" 
                                   multiple 
                                   accept="image/*"
                                   required>
                            <small class="text-muted">Upload product images (JPG, PNG, GIF, WebP). Max 5MB each. First image will be set as primary.</small>
                        </div>
                        
                        <div id="imagePreview" class="row g-2 mt-3">
                            <!-- Image previews will appear here -->
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
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> If you don\'t add variants, you MUST add stock quantities to variants after creating the product.
                            Stock is no longer managed at the product level.
                        </div>
                        
                        <div id="variantsContainer">
                            <div class="text-center text-muted py-4" id="noVariantsMessage">
                                <i class="fas fa-box-open fa-2x mb-2"></i>
                                <p><strong>No variants added yet.</strong></p>
                                <p class="small">You can add variants now or after creating the product.</p>
                                <p class="small text-danger">Remember: Stock must be added at variant level.</p>
                            </div>
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
                                       ' . (isset($formData['is_active']) ? 'checked' : 'checked') . '>
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
                                       ' . (isset($formData['is_featured']) ? 'checked' : '') . '>
                                <label class="form-check-label" for="is_featured">Featured</label>
                            </div>
                            <small class="text-muted">Featured products will be highlighted on the homepage</small>
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
                                   value="' . htmlspecialchars($formData['materials'] ?? '') . '"
                                   placeholder="e.g., 100% Organic Linen">
                        </div>
                        
                        <div class="mb-3">
                            <label for="care_instructions" class="form-label">Care Instructions</label>
                            <textarea class="form-control" 
                                      id="care_instructions" 
                                      name="care_instructions" 
                                      rows="3"
                                      placeholder="e.g., Machine wash cold, Tumble dry low">' . htmlspecialchars($formData['care_instructions'] ?? '') . '</textarea>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="weight" class="form-label">Weight (g)</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="weight" 
                                       name="weight" 
                                       value="' . htmlspecialchars($formData['weight'] ?? '') . '"
                                       step="0.01" 
                                       min="0"
                                       placeholder="e.g., 250">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="dimensions" class="form-label">Dimensions (cm)</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="dimensions" 
                                       name="dimensions" 
                                       value="' . htmlspecialchars($formData['dimensions'] ?? '') . '"
                                       placeholder="LxWxH e.g., 30x20x5">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions Card -->
                <div class="card shadow">
                    <div class="card-body">
                        <button type="submit" class="btn btn-dark btn-lg w-100 py-3 mb-3">
                            <i class="fas fa-plus-circle me-2"></i> Add Product
                        </button>
                        
                        <div class="d-grid gap-2">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i> Reset Form
                            </button>
                            
                            <a href="' . SITE_URL . 'admin/products" class="btn btn-outline-danger">
                                <i class="fas fa-times me-2"></i> Cancel
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

<script>
let variantCount = 0;

// Initialize select2
$(document).ready(function() {
    $(".select2").select2({
        theme: "bootstrap-5",
        width: "100%"
    });
    
    // Auto-generate slug from name
    $("#name").on("input", function() {
        if (!$("#slug").val()) {
            const name = $(this).val();
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-+|-+$/g, "");
            $("#slug").val(slug);
        }
    });
});
// Form validation
document.getElementById("productForm").addEventListener("submit", function(e) {
    const name = document.getElementById("name").value.trim();
    const price = document.getElementById("price").value;
    const category = document.getElementById("category_id").value;
    const sku = document.getElementById("sku").value.trim();
    const description = document.getElementById("description").value.trim();
    const images = document.getElementById("images").files;
    
    // Basic validation
    if (!name) {
        e.preventDefault();
        Swal.fire("Error", "Product name is required", "error");
        document.getElementById("name").focus();
        return false;
    }
    
    if (!price || parseFloat(price) <= 0) {
        e.preventDefault();
        Swal.fire("Error", "Valid price is required", "error");
        document.getElementById("price").focus();
        return false;
    }
    
    if (!category) {
        e.preventDefault();
        Swal.fire("Error", "Category is required", "error");
        document.getElementById("category_id").focus();
        return false;
    }
    
    if (!sku) {
        e.preventDefault();
        Swal.fire("Error", "SKU is required", "error");
        document.getElementById("sku").focus();
        return false;
    }
    
    if (!description) {
        e.preventDefault();
        Swal.fire("Error", "Description is required", "error");
        document.getElementById("description").focus();
        return false;
    }
    
    if (images.length === 0) {
        e.preventDefault();
        Swal.fire("Error", "At least one product image is required", "error");
        document.getElementById("images").focus();
        return false;
    }
    
    // NEW: Check variant stock
    const variantStocks = document.querySelectorAll(".variant-stock");
    const hasVariants = variantStocks.length > 0;
    
    if (hasVariants) {
        let hasStock = false;
        variantStocks.forEach(input => {
            if (parseInt(input.value) > 0) {
                hasStock = true;
            }
        });
        
        if (!hasStock) {
            e.preventDefault();
            Swal.fire({
                title: "No Stock Warning",
                text: "All variants have 0 stock. This product will be marked as out of stock.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Continue Anyway",
                cancelButtonText: "Add Stock"
            }).then((result) => {
                if (result.isConfirmed) {
                    e.target.submit();
                }
            });
            return false;
        }
    }
    
    return true;
});
// Image preview
document.getElementById("images").addEventListener("change", function(e) {
    const preview = document.getElementById("imagePreview");
    preview.innerHTML = "";
    
    for (let i = 0; i < this.files.length; i++) {
        const file = this.files[i];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const col = document.createElement("div");
            col.className = "col-md-3 mb-2";
            col.innerHTML = `
                <div class="card">
                    <img src="${e.target.result}" 
                         class="card-img-top" 
                         style="height: 150px; object-fit: cover;">
                    <div class="card-body p-2">
                        <small class="d-block text-truncate">${file.name}</small>
                        <small class="text-muted">${(file.size / 1024).toFixed(1)} KB</small>
                        ${i === 0 ? \'<small class="text-primary"><i class="fas fa-star me-1"></i>Primary</small>\' : \'\'}
                    </div>
                </div>
            `;
            preview.appendChild(col);
        }
        
        reader.readAsDataURL(file);
    }
});

// Variant management
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
}

function removeVariant(button) {
    const variantItem = button.closest(".variant-item");
    if (variantItem) {
        variantItem.remove();
        
        // Show message if no variants left
        const container = document.getElementById("variantsContainer");
        if (container && container.children.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4" id="noVariantsMessage">
                    <i class="fas fa-box-open fa-2x mb-2"></i>
                    <p>No variants added yet. Add size/color options if needed.</p>
                    <p class="small">If no variants are added, the product will use the main pricing and stock.</p>
                </div>
            `;
        }
    }
}

function updateColorCode(colorPicker) {
    const hex = colorPicker.value.substring(1); // Remove #
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

// Form validation
document.getElementById("productForm").addEventListener("submit", function(e) {
    const name = document.getElementById("name").value.trim();
    const price = document.getElementById("price").value;
    const category = document.getElementById("category_id").value;
    const sku = document.getElementById("sku").value.trim();
    const description = document.getElementById("description").value.trim();
    const images = document.getElementById("images").files;
    
    if (!name) {
        e.preventDefault();
        Swal.fire("Error", "Product name is required", "error");
        document.getElementById("name").focus();
        return false;
    }
    
    if (!price || parseFloat(price) <= 0) {
        e.preventDefault();
        Swal.fire("Error", "Valid price is required", "error");
        document.getElementById("price").focus();
        return false;
    }
    
    if (!category) {
        e.preventDefault();
        Swal.fire("Error", "Category is required", "error");
        document.getElementById("category_id").focus();
        return false;
    }
    
    if (!sku) {
        e.preventDefault();
        Swal.fire("Error", "SKU is required", "error");
        document.getElementById("sku").focus();
        return false;
    }
    
    if (!description) {
        e.preventDefault();
        Swal.fire("Error", "Description is required", "error");
        document.getElementById("description").focus();
        return false;
    }
    
    if (images.length === 0) {
        e.preventDefault();
        Swal.fire("Error", "At least one product image is required", "error");
        document.getElementById("images").focus();
        return false;
    }
    
    return true;
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
    
   // Reset form button
form.querySelector("button[type=\'reset\']").addEventListener("click", function() {
    if (formChanged) {
        Swal.fire({
            title: "Reset Form?",                                                      
            text: "This will clear all form data.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, reset it!"
        }).then((result) => {
            if (result.isConfirmed) {
                form.reset();
                formChanged = false;
                document.getElementById("imagePreview").innerHTML = "";
                document.getElementById("variantsContainer").innerHTML = `
                    <div class="text-center text-muted py-4" id="noVariantsMessage">
                        <i class="fas fa-box-open fa-2x mb-2"></i>
                        <p><strong>No variants added yet.</strong></p>
                        <p class="small">You can add variants now or after creating the product.</p>
                        <p class="small text-danger">Remember: Stock must be added at variant level.</p>
                    </div>
                `;
                variantCount = 0;
                $(".select2").val(null).trigger("change");
            }
        });
        return false;
    }
});
}
</script>';

// ====================================================================
// 5. OUTPUT THE LAYOUT
// ====================================================================

echo adminLayout($content, 'Add New Product');
?>