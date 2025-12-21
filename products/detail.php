<?php
// /linen-closet/products/detail.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

$app = new App();
$db = $app->getDB();

// Get product slug from URL
$slug = $_GET['slug'] ?? null;

if (!$slug) {
    $app->redirect('products');
}

// Fetch product details
$stmt = $db->prepare("
    SELECT 
        p.*,
        c.name as category_name,
        c.slug as category_slug,
        b.name as brand_name,
        b.description as brand_description,
        (SELECT AVG(rating) FROM product_reviews pr WHERE pr.product_id = p.id AND pr.is_approved = 1) as avg_rating,
        (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id = p.id AND pr.is_approved = 1) as review_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.slug = ? AND p.is_active = 1
");

$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    $app->redirect('products', ['error' => 'Product not found']);
}

// Fetch product images
$imagesStmt = $db->prepare("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, sort_order ASC
");
$imagesStmt->execute([$product['id']]);
$productImages = $imagesStmt->fetchAll();

// Fetch product variants
$variantsStmt = $db->prepare("
    SELECT * FROM product_variants 
    WHERE product_id = ? AND stock_quantity > 0
    ORDER BY size, color, price
");
$variantsStmt->execute([$product['id']]);
$variants = $variantsStmt->fetchAll();

// Extract unique sizes, colors, and materials from variants
$availableSizes = [];
$availableColors = [];
$availableMaterials = [];

foreach ($variants as $variant) {
    if ($variant['size'] && !in_array($variant['size'], $availableSizes)) {
        $availableSizes[] = $variant['size'];
    }
    if ($variant['color'] && !in_array($variant['color'], $availableColors)) {
        $availableColors[] = $variant['color'];
    }
    // if ($variant['material'] && !in_array($variant['material'], $availableMaterials)) {
    //     $availableMaterials[] = $variant['material'];
    // }
}

// Fetch product specifications
// Build specifications from product fields
$specifications = [];
if (!empty($product['materials'])) {
    $specifications[] = ['spec_name' => 'Materials', 'spec_value' => $product['materials']];
}
if (!empty($product['weight'])) {
    $specifications[] = ['spec_name' => 'Weight', 'spec_value' => $product['weight'] . ' grams'];
}
if (!empty($product['dimensions'])) {
    $specifications[] = ['spec_name' => 'Dimensions', 'spec_value' => $product['dimensions'] . ' cm'];
}
if (!empty($product['care_instructions'])) {
    $specifications[] = ['spec_name' => 'Care Instructions', 'spec_value' => $product['care_instructions']];
}

// Fetch related products
$relatedStmt = $db->prepare("
    SELECT 
        p.*,
        (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
        (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_stock
    FROM products p
    WHERE p.category_id = ? 
      AND p.id != ? 
      AND p.is_active = 1
    ORDER BY RAND()
    LIMIT 8
");
$relatedStmt->execute([$product['category_id'], $product['id']]);
$relatedProducts = $relatedStmt->fetchAll();

// Fetch product reviews
// Fetch product reviews
$reviewsStmt = $db->prepare("
    SELECT 
        pr.*,
        CONCAT(u.first_name, ' ', u.last_name) as full_name,
        u.email,
        u.created_at as member_since
    FROM product_reviews pr
    LEFT JOIN users u ON pr.user_id = u.id
    WHERE pr.product_id = ? AND pr.is_approved = 1
    ORDER BY pr.created_at DESC
    LIMIT 10
");
$reviewsStmt->execute([$product['id']]);
$reviews = $reviewsStmt->fetchAll();

// Update view count
$updateViewStmt = $db->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?");
$updateViewStmt->execute([$product['id']]);

$pageTitle = $product['name'] . " | " . SITE_NAME;

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
                <a href="<?php echo SITE_URL; ?>products" class="text-decoration-none">
                    Products
                </a>
            </li>
            <?php if ($product['category_slug']): ?>
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>products?category=<?php echo $product['category_slug']; ?>" 
                       class="text-decoration-none">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo htmlspecialchars($product['name']); ?>
            </li>
        </ol>
    </nav>

    <!-- Product Detail Section -->
    <div class="row g-4">
        <!-- Product Images -->
        <div class="col-lg-6">
            <div class="product-images-container">
                <!-- Main Image -->
                <div class="main-image mb-3">
                    <div class="rounded border overflow-hidden" style="height: 500px;">
                        <img id="mainProductImage" 
                             src="<?php echo SITE_URL . ($productImages[0]['image_url'] ?? 'assets/images/placeholder.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="img-fluid w-100 h-100 object-fit-cover"
                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                    </div>
                </div>
                
                <!-- Image Thumbnails -->
                <?php if (count($productImages) > 1): ?>
                    <div class="image-thumbnails d-flex gap-2">
                        <?php foreach ($productImages as $index => $image): ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                 style="width: 80px; height: 80px; cursor: pointer;"
                                 data-image="<?php echo SITE_URL . $image['image_url']; ?>">
                                <img src="<?php echo SITE_URL . $image['image_url']; ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?> - Image <?php echo $index + 1; ?>" 
                                     class="img-fluid rounded border w-100 h-100 object-fit-cover">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Product Actions -->
                <div class="d-flex gap-2 mt-4">
                    <button type="button" class="btn btn-outline-dark btn-lg flex-fill">
                        <i class="far fa-heart me-2"></i> Add to Wishlist
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-lg flex-fill">
                        <i class="fas fa-share-alt me-2"></i> Share
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-lg flex-fill">
                        <i class="fas fa-flag me-2"></i> Report
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Product Info -->
        <div class="col-lg-6">
            <div class="product-info">
                <!-- Brand & Category -->
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if ($product['brand_name']): ?>
                        <span class="badge bg-dark">
                            <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($product['brand_name']); ?>
                        </span>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>products?category=<?php echo $product['category_slug']; ?>" 
                       class="badge bg-light text-dark text-decoration-none">
                        <i class="fas fa-folder me-1"></i> <?php echo htmlspecialchars($product['category_name']); ?>
                    </a>
                    <?php if (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-star me-1"></i> New Arrival
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Product Title -->
                <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <!-- SKU -->
                <div class="mb-3">
                    <small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                </div>
                
                <!-- Rating -->
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rating">
                        <?php
                        $avgRating = $product['avg_rating'] ?? 0;
                        $fullStars = floor($avgRating);
                        $hasHalfStar = ($avgRating - $fullStars) >= 0.5;
                        $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                        ?>
                        
                        <?php for ($i = 0; $i < $fullStars; $i++): ?>
                            <i class="fas fa-star text-warning"></i>
                        <?php endfor; ?>
                        
                        <?php if ($hasHalfStar): ?>
                            <i class="fas fa-star-half-alt text-warning"></i>
                        <?php endif; ?>
                        
                        <?php for ($i = 0; $i < $emptyStars; $i++): ?>
                            <i class="far fa-star text-warning"></i>
                        <?php endfor; ?>
                        
                        <span class="ms-2 fw-bold"><?php echo number_format($avgRating, 1); ?></span>
                    </div>
                    <a href="#reviews" class="text-decoration-none">
                        <span class="text-muted">(<?php echo $product['review_count'] ?? 0; ?> reviews)</span>
                    </a>
                    <span class="text-muted">|</span>
                    <span class="text-muted">
                        <i class="fas fa-eye me-1"></i> <?php echo number_format($product['view_count'] ?? 0); ?> views
                    </span>
                    <?php if ($product['sold_count'] > 0): ?>
                        <span class="text-muted">|</span>
                        <span class="text-muted">
                            <i class="fas fa-shopping-bag me-1"></i> <?php echo number_format($product['sold_count']); ?> sold
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Price -->
                <div class="mb-4">
                    <h2 class="text-dark fw-bold display-6">
                        Ksh <?php echo number_format($product['price'], 2); ?>
                    </h2>
                    <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted text-decoration-line-through fs-5">
                                Ksh <?php echo number_format($product['compare_price'], 2); ?>
                            </span>
                            <span class="badge bg-danger fs-6">
                                Save <?php echo round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100); ?>%
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Variant Selection Form -->
                <form id="addToCartForm" class="mb-4">
                    <?php if (!empty($variants)): ?>
                        <!-- Size Selection -->
                        <?php if (!empty($availableSizes)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Size <span class="text-danger">*</span></label>
                                <div class="size-selection d-flex flex-wrap gap-2">
                                    <?php foreach ($availableSizes as $size): ?>
                                        <?php
                                        // Find variants with this size and stock > 0
                                        $sizeVariants = array_filter($variants, function($v) use ($size) {
                                            return $v['size'] === $size && $v['stock_quantity'] > 0;
                                        });
                                        $sizeInStock = !empty($sizeVariants);
                                        ?>
                                        <label class="size-option position-relative <?php echo !$sizeInStock ? 'disabled' : ''; ?>">
                                            <input type="radio" 
                                                   name="size" 
                                                   value="<?php echo htmlspecialchars($size); ?>" 
                                                   class="d-none"
                                                   <?php echo !$sizeInStock ? 'disabled' : ''; ?>
                                                   required>
                                            <div class="size-btn btn <?php echo $sizeInStock ? 'btn-outline-dark' : 'btn-outline-secondary'; ?>">
                                                <?php echo htmlspecialchars($size); ?>
                                                <?php if (!$sizeInStock): ?>
                                                    <span class="position-absolute top-0 end-0 small text-danger">×</span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Color Selection -->
                        <?php if (!empty($availableColors)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Color <span class="text-danger">*</span></label>
                                <div class="color-selection d-flex flex-wrap gap-2">
                                    <?php foreach ($availableColors as $color): ?>
                                        <?php
                                        $colorVariants = array_filter($variants, function($v) use ($color) {
                                            return $v['color'] === $color && $v['stock_quantity'] > 0;
                                        });
                                        $colorInStock = !empty($colorVariants);
                                        ?>
                                        <label class="color-option position-relative <?php echo !$colorInStock ? 'disabled' : ''; ?>">
                                            <input type="radio" 
                                                   name="color" 
                                                   value="<?php echo htmlspecialchars($color); ?>" 
                                                   class="d-none"
                                                   <?php echo !$colorInStock ? 'disabled' : ''; ?>
                                                   required>
                                            <div class="color-btn rounded-circle border <?php echo !$colorInStock ? 'opacity-50' : ''; ?>"
                                                 style="width: 40px; height: 40px; background-color: <?php 
                                                 // Try to find color code
                                                 $colorVariant = current($colorVariants);
                                                 echo htmlspecialchars($colorVariant['color_code'] ?? '#ccc'); ?>;">
                                                <?php if (!$colorInStock): ?>
                                                    <span class="position-absolute top-50 start-50 translate-middle text-danger">×</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="d-block text-center mt-1"><?php echo htmlspecialchars($color); ?></small>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Material Selection -->
                        <?php if (!empty($availableMaterials)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Material</label>
                                <select name="material" class="form-select">
                                    <option value="">Select Material</option>
                                    <?php foreach ($availableMaterials as $material): ?>
                                        <option value="<?php echo htmlspecialchars($material); ?>">
                                            <?php echo htmlspecialchars($material); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Variant-specific details will be shown here -->
                        <div id="variantDetails" class="mb-4" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h6 class="card-title text-success">
                                        <i class="fas fa-check-circle me-2"></i> Variant Selected
                                    </h6>
                                    <div id="variantInfo"></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Quantity -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Quantity</label>
                        <div class="quantity-selector d-flex align-items-center" style="max-width: 150px;">
                            <button type="button" class="btn btn-outline-dark" id="decreaseQty">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" 
                                   name="quantity" 
                                   id="quantity" 
                                   class="form-control text-center border-dark rounded-0" 
                                   value="1" 
                                   min="1" 
                                   max="<?php echo $product['stock_quantity'] ?? 10; ?>">
                            <button type="button" class="btn btn-outline-dark" id="increaseQty">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <small class="text-muted mt-1 d-block">
                            <?php 
                            $totalStock = array_sum(array_column($variants, 'stock_quantity')) ?: $product['stock_quantity'];
                            echo $totalStock . ' items available';
                            ?>
                        </small>
                    </div>
                    
                    <!-- Add to Cart & Buy Now -->
                    <div class="d-flex gap-3 mb-4">
                        <button type="button" 
                                class="btn btn-dark btn-lg flex-fill py-3 add-to-cart-btn"
                                data-product-id="<?php echo $product['id']; ?>"
                                <?php echo ($totalStock <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-shopping-cart me-2"></i>
                            <?php echo ($totalStock <= 0) ? 'Out of Stock' : 'Add to Cart'; ?>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-dark btn-lg flex-fill py-3 buy-now-btn"
                                data-product-id="<?php echo $product['id']; ?>"
                                <?php echo ($totalStock <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-bolt me-2"></i> Buy Now
                        </button>
                    </div>
                    
                    <!-- Product Actions -->
                    <div class="d-flex gap-2 mb-4">
                        <button type="button" class="btn btn-outline-dark">
                            <i class="fas fa-sync-alt me-2"></i> Compare
                        </button>
                        <button type="button" class="btn btn-outline-dark">
                            <i class="fas fa-truck me-2"></i> Shipping Info
                        </button>
                        <button type="button" class="btn btn-outline-dark">
                            <i class="fas fa-shield-alt me-2"></i> Warranty
                        </button>
                    </div>
                </form>
                
                <!-- Product Highlights -->
                <div class="product-highlights mb-4">
                    <h5 class="fw-bold mb-3">Product Highlights</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-light rounded-circle p-2 me-3">
                                    <i class="fas fa-shipping-fast text-dark"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">Free Shipping</h6>
                                    <p class="mb-0 text-muted small">On orders over Ksh 5,000</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-light rounded-circle p-2 me-3">
                                    <i class="fas fa-undo-alt text-dark"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">30-Day Returns</h6>
                                    <p class="mb-0 text-muted small">Easy return policy</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-light rounded-circle p-2 me-3">
                                    <i class="fas fa-shield-alt text-dark"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">1-Year Warranty</h6>
                                    <p class="mb-0 text-muted small">Manufacturer warranty</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-light rounded-circle p-2 me-3">
                                    <i class="fas fa-headset text-dark"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">24/7 Support</h6>
                                    <p class="mb-0 text-muted small">Customer service</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Share Product -->
                <div class="share-product mb-4">
                    <h6 class="fw-bold mb-3">Share this product:</h6>
                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-outline-primary">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="btn btn-outline-info">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="btn btn-outline-danger">
                            <i class="fab fa-pinterest-p"></i>
                        </a>
                        <a href="#" class="btn btn-outline-success">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="#" class="btn btn-outline-secondary">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Tabs -->
    <div class="row mt-5">
        <div class="col-12">
            <nav>
                <div class="nav nav-tabs border-bottom" id="productTab" role="tablist">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button">
                        <i class="fas fa-file-alt me-2"></i> Description
                    </button>
                    <button class="nav-link" id="specifications-tab" data-bs-toggle="tab" data-bs-target="#specifications" type="button">
                        <i class="fas fa-list-alt me-2"></i> Specifications
                    </button>
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                        <i class="fas fa-star me-2"></i> Reviews
                        <span class="badge bg-dark ms-2"><?php echo $product['review_count'] ?? 0; ?></span>
                    </button>
                    <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button">
                        <i class="fas fa-truck me-2"></i> Shipping & Returns
                    </button>
                    <button class="nav-link" id="faq-tab" data-bs-toggle="tab" data-bs-target="#faq" type="button">
                        <i class="fas fa-question-circle me-2"></i> FAQ
                    </button>
                </div>
            </nav>
            
            <div class="tab-content p-4 border border-top-0 rounded-bottom" id="productTabContent">
                <!-- Description Tab -->
                <div class="tab-pane fade show active" id="description" role="tabpanel">
                    <h4 class="fw-bold mb-4">Product Description</h4>
                    <div class="product-description">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?>
                    </div>
                    
                    <?php if (!empty($product['features'])): ?>
                        <div class="mt-5">
                            <h5 class="fw-bold mb-4">Key Features</h5>
                            <div class="row">
                                <?php 
                                $features = explode("\n", $product['features']);
                                foreach ($features as $feature): 
                                    if (trim($feature)): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="d-flex">
                                                <i class="fas fa-check-circle text-success me-3 mt-1"></i>
                                                <span><?php echo htmlspecialchars(trim($feature)); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Specifications Tab -->
                <div class="tab-pane fade" id="specifications" role="tabpanel">
                    <h4 class="fw-bold mb-4">Product Specifications</h4>
                    <?php if (!empty($specifications)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tbody>
                                    <?php foreach ($specifications as $spec): ?>
                                        <tr>
                                            <th style="width: 30%;" class="bg-light"><?php echo htmlspecialchars($spec['spec_name']); ?></th>
                                            <td><?php echo htmlspecialchars($spec['spec_value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No specifications available for this product.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviews" role="tabpanel">
                    <h4 class="fw-bold mb-4">Customer Reviews</h4>
                    
                    <!-- Review Summary -->
                    <div class="row mb-5">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <h2 class="display-4 fw-bold text-dark mb-0">
                                        <?php echo number_format($product['avg_rating'] ?? 0, 1); ?>
                                    </h2>
                                    <div class="rating mb-2">
                                        <?php 
                                        $avgRating = $product['avg_rating'] ?? 0;
                                        for ($i = 1; $i <= 5; $i++): 
                                            if ($i <= floor($avgRating)): ?>
                                                <i class="fas fa-star text-warning"></i>
                                            <?php elseif ($i <= $avgRating): ?>
                                                <i class="fas fa-star-half-alt text-warning"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-warning"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="text-muted mb-0">
                                        Based on <?php echo $product['review_count'] ?? 0; ?> reviews
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="rating-bars">
                                <?php 
                                $ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                                foreach ($reviews as $review) {
                                    if (isset($ratingCounts[$review['rating']])) {
                                        $ratingCounts[$review['rating']]++;
                                    }
                                }
                                
                                for ($rating = 5; $rating >= 1; $rating--): 
                                    $count = $ratingCounts[$rating];
                                    $percentage = $product['review_count'] > 0 ? ($count / $product['review_count']) * 100 : 0;
                                ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted me-2" style="width: 20px;"><?php echo $rating; ?>★</span>
                                        <div class="progress flex-grow-1 me-3" style="height: 10px;">
                                            <div class="progress-bar bg-warning" 
                                                 style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <span class="text-muted small" style="width: 40px;"><?php echo $count; ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    
                    <!-- Reviews List -->
<div class="reviews-list">
    <?php if (!empty($reviews)): ?>
        <?php foreach ($reviews as $review): ?>
            <div class="review-item card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 50px; height: 50px;">
                                <?php 
                                $initials = 'U';
                                if (!empty($review['full_name'])) {
                                    $nameParts = explode(' ', trim($review['full_name']));
                                    if (count($nameParts) >= 2) {
                                        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                                    } else {
                                        $initials = strtoupper(substr($review['full_name'], 0, 2));
                                    }
                                }
                                echo htmlspecialchars($initials);
                                ?>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">
                                    <?php echo htmlspecialchars($review['full_name'] ?? 'Anonymous User'); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                    <?php if (!empty($review['member_since'])): ?>
                                        • Member since <?php echo date('Y', strtotime($review['member_since'])); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <div class="rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($review['title'])): ?>
                        <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($review['title']); ?></h6>
                    <?php endif; ?>
                    
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review'] ?? '')); ?></p>
                    
                    <!-- Note: If you add images column to reviews table, uncomment this -->
                    <?php /* if (!empty($review['images'])): ?>
                        <div class="review-images mt-3">
                            <?php 
                            $images = json_decode($review['images'], true);
                            if (is_array($images) && !empty($images)):
                                foreach ($images as $image): 
                                    if (!empty($image)): ?>
                                        <img src="<?php echo SITE_URL . $image; ?>" 
                                             class="rounded me-2" 
                                             style="width: 80px; height: 80px; object-fit: cover;"
                                             alt="Review image"
                                             onerror="this.style.display='none';">
                                    <?php endif;
                                endforeach; 
                            endif; ?>
                        </div>
                    <?php endif; */ ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
            <h5 class="fw-bold mb-3">No Reviews Yet</h5>
            <p class="text-muted mb-4">Be the first to review this product!</p>
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#writeReviewModal">
                <i class="fas fa-pen me-2"></i> Write a Review
            </button>
        </div>
    <?php endif; ?>
</div>
                    
                    <!-- Write Review Button -->
                    <div class="text-center mt-4">
                        <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#writeReviewModal">
                            <i class="fas fa-pen me-2"></i> Write a Review
                        </button>
                    </div>
                </div>
                
                <!-- Shipping Tab -->
                <div class="tab-pane fade" id="shipping" role="tabpanel">
                    <h4 class="fw-bold mb-4">Shipping & Returns</h4>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">
                                        <i class="fas fa-shipping-fast text-dark me-2"></i> Shipping Information
                                    </h5>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Free shipping on orders over Ksh 5,000
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Standard delivery: 3-5 business days
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Express delivery: 1-2 business days
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Tracking number provided
                                        </li>
                                        <li>
                                            <i class="fas fa-check text-success me-2"></i>
                                            Shipping to major cities only
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">
                                        <i class="fas fa-undo-alt text-dark me-2"></i> Return Policy
                                    </h5>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            30-day return policy
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Items must be in original condition
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Original tags must be attached
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Free returns for defective items
                                        </li>
                                        <li>
                                            <i class="fas fa-check text-success me-2"></i>
                                            Refund processed within 7-10 business days
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- FAQ Tab -->
                <div class="tab-pane fade" id="faq" role="tabpanel">
                    <h4 class="fw-bold mb-4">Frequently Asked Questions</h4>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What is the material of this product?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    This product is made from high-quality materials as specified in the product description. For detailed material information, please check the specifications tab.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How do I care for this product?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Please refer to the care instructions included with the product. Generally, we recommend gentle washing and air drying to maintain quality.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Are the colors accurate in the photos?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We make every effort to display colors accurately. However, monitor settings may vary, and actual colors may differ slightly from what you see on screen.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    What if I receive a damaged item?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    If you receive a damaged item, please contact our customer service within 48 hours of delivery with photos of the damage. We will arrange a replacement or refund.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 shadow-sm mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    Do you offer international shipping?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Currently, we only ship within Kenya. We are working to expand our shipping options in the future.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold">Related Products</h2>
                    <a href="<?php echo SITE_URL; ?>products?category=<?php echo $product['category_slug']; ?>" 
                       class="btn btn-outline-dark">
                        View All <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
                
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                    <?php foreach ($relatedProducts as $related): ?>
                        <?php
                        $relatedImage = SITE_URL . ($related['primary_image'] ?: 'assets/images/placeholder.jpg');
                        $relatedStock = $related['total_stock'] ?? $related['stock_quantity'] ?? 0;
                        $relatedUrl = SITE_URL . 'products/detail.php?slug=' . $related['slug'];
                        ?>
                        <div class="col">
                            <div class="card h-100 product-card border-0 shadow-sm hover-lift">
                                <div class="position-relative overflow-hidden" style="height: 200px;">
                                    <a href="<?php echo $relatedUrl; ?>" class="text-decoration-none">
                                        <img src="<?php echo $relatedImage; ?>" 
                                             class="card-img-top h-100 w-100 object-fit-cover" 
                                             alt="<?php echo htmlspecialchars($related['name']); ?>">
                                        <?php if ($relatedStock <= 0): ?>
                                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center">
                                                <span class="badge bg-secondary">Out of Stock</span>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <button type="button" class="btn btn-light btn-sm rounded-circle shadow-sm">
                                            <i class="far fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title fw-bold">
                                        <a href="<?php echo $relatedUrl; ?>" class="text-decoration-none text-dark text-truncate-2">
                                            <?php echo htmlspecialchars($related['name']); ?>
                                        </a>
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <h5 class="text-dark mb-0">Ksh <?php echo number_format($related['price'], 2); ?></h5>
                                        <?php if ($relatedStock > 0): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-dark add-to-cart-btn"
                                                    data-product-id="<?php echo $related['id']; ?>">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    
   <!-- More Reviews -->
    <div class="row mt-5">
        <div class="col-12">
            <h2 class="fw-bold mb-4">More Reviews</h2>
            <div id="reviewsList">
                <!-- Reviews will be loaded here -->
            </div>
            <div class="text-center mt-4">
                <a href="#reviews" class="btn btn-outline-dark" onclick="loadAllReviews()">
                    <i class="fas fa-comments me-2"></i> View All Reviews
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Write Review Modal -->

<!-- Write Review Modal -->
<div class="modal fade" id="writeReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Write a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Wrap everything in a form -->
                <form id="reviewForm" onsubmit="event.preventDefault(); submitReview();">
                    <div class="modal-body">
                        <!-- ... your existing modal body content ... -->
                        
                        <div class="mb-4 text-center">
                            <h6 class="fw-bold mb-3">How would you rate this product?</h6>
                            <div class="rating-input d-flex justify-content-center">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="star-label mx-1" style="cursor: pointer;">
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" class="d-none">
                                        <i class="far fa-star fa-2x text-warning"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Review Title</label>
                            <input type="text" name="title" class="form-control" placeholder="Summarize your experience" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Your Review</label>
                            <textarea name="review" class="form-control" rows="5" 
                                placeholder="Share your thoughts about this product..." required></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark" id="submitReview">Submit Review</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="modal-body">
                    <div class="text-center py-5">
                        <i class="fas fa-user-lock fa-3x text-muted mb-3"></i>
                        <h5 class="fw-bold mb-3">Login Required</h5>
                        <p class="text-muted mb-4">Please login to submit a review.</p>
                        <a href="<?php echo SITE_URL; ?>auth/login" class="btn btn-dark">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add to Cart Success Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="cartSuccessToast" class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i>
                <span id="toastMessage">Product added to cart successfully!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<style>
/* Product Detail Styles */
.product-images-container .thumbnail {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.product-images-container .thumbnail.active {
    border-color: #000;
}

.product-images-container .thumbnail:hover {
    border-color: #666;
    transform: scale(1.05);
}

.quantity-selector input[type="number"] {
    -moz-appearance: textfield;
}

.quantity-selector input[type="number"]::-webkit-outer-spin-button,
.quantity-selector input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.size-selection .size-option.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.size-selection .size-btn {
    min-width: 60px;
    transition: all 0.3s ease;
}

.size-selection input:checked + .size-btn {
    background-color: #000;
    color: white;
    border-color: #000;
}

.color-selection .color-option {
    cursor: pointer;
}

.color-selection .color-option.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.color-selection input:checked + .color-btn {
    border: 3px solid #000 !important;
    transform: scale(1.1);
}

.rating-input .star-label:hover i,
.rating-input .star-label:hover ~ .star-label i {
    color: #ffc107 !important;
}

.rating-input input:checked ~ .star-label i {
    color: #ffc107 !important;
}

.tab-content {
    min-height: 300px;
}

.nav-tabs .nav-link {
    color: #666;
    font-weight: 500;
    border: none;
    padding: 1rem 1.5rem;
}

.nav-tabs .nav-link.active {
    color: #000;
    border-bottom: 3px solid #000;
    background: none;
}

.nav-tabs .nav-link:hover {
    color: #000;
}

.hover-lift {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important;
}

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.object-fit-cover {
    object-fit: cover;
}

/* Responsive */
@media (max-width: 768px) {
    .main-image {
        height: 300px !important;
    }
    
    .display-5 {
        font-size: 2rem;
    }
    
    .display-6 {
        font-size: 1.75rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image thumbnail click handler
    document.querySelectorAll('.thumbnail').forEach(thumb => {
        thumb.addEventListener('click', function() {
            const mainImage = document.getElementById('mainProductImage');
            const imageUrl = this.dataset.image;
            
            // Update main image
            mainImage.src = imageUrl;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Quantity controls
    document.getElementById('decreaseQty').addEventListener('click', function() {
        const qtyInput = document.getElementById('quantity');
        if (parseInt(qtyInput.value) > 1) {
            qtyInput.value = parseInt(qtyInput.value) - 1;
        }
    });
    
    document.getElementById('increaseQty').addEventListener('click', function() {
        const qtyInput = document.getElementById('quantity');
        const maxQty = parseInt(qtyInput.max);
        if (parseInt(qtyInput.value) < maxQty) {
            qtyInput.value = parseInt(qtyInput.value) + 1;
        }
    });
    
    // Size selection
    document.querySelectorAll('.size-selection input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateVariantDetails();
        });
    });
    
    // Color selection
    document.querySelectorAll('.color-selection input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateVariantDetails();
        });
    });
    
    // Add to cart button
    document.querySelector('.add-to-cart-btn').addEventListener('click', function() {
        const productId = this.dataset.productId;
        const quantity = document.getElementById('quantity').value;
        const size = document.querySelector('input[name="size"]:checked')?.value;
        const color = document.querySelector('input[name="color"]:checked')?.value;
        const material = document.querySelector('select[name="material"]')?.value;
        
        addToCart(productId, quantity, size, color, material);
    });
    
    // Buy now button
    document.querySelector('.buy-now-btn').addEventListener('click', function() {
        const productId = this.dataset.productId;
        const quantity = document.getElementById('quantity').value;
        const size = document.querySelector('input[name="size"]:checked')?.value;
        const color = document.querySelector('input[name="color"]:checked')?.value;
        const material = document.querySelector('select[name="material"]')?.value;
        
        addToCart(productId, quantity, size, color, material, true);
    });
    
    // Rating stars
    document.querySelectorAll('.rating-input .star-label').forEach((label, index) => {
        label.addEventListener('click', function() {
            const rating = index + 1;
            const stars = document.querySelectorAll('.rating-input .star-label i');
            
            stars.forEach((star, i) => {
                if (i <= index) {
                    star.classList.remove('far');
                    star.classList.add('fas');
                } else {
                    star.classList.remove('fas');
                    star.classList.add('far');
                }
            });
            
            this.querySelector('input').checked = true;
        });
    });
    
    // Submit review
    document.getElementById('submitReview')?.addEventListener('click', submitReview);
    
    // Load recently viewed products
    loadRecentlyViewed();
});

// Update variant details based on selection
function updateVariantDetails() {
    const size = document.querySelector('input[name="size"]:checked')?.value;
    const color = document.querySelector('input[name="color"]:checked')?.value;
    
    if (size && color) {
        // In a real app, you would fetch variant details from the server
        const variantDetails = document.getElementById('variantDetails');
        const variantInfo = document.getElementById('variantInfo');
        
        // For now, just show a message
        variantInfo.innerHTML = `
            <p class="mb-1"><strong>Size:</strong> ${size}</p>
            <p class="mb-1"><strong>Color:</strong> ${color}</p>
            <p class="mb-0"><strong>Availability:</strong> In Stock</p>
        `;
        
        variantDetails.style.display = 'block';
    }
}

// Add to cart function
async function addToCart(productId, quantity, size, color, material, buyNow = false) {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity,
                size: size,
                color: color,
                material: material
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update cart count
            updateCartCount(data.cart_count);
            
            // Show success toast
            showToast('Product added to cart successfully!');
            
            if (buyNow) {
                // Redirect to checkout
                setTimeout(() => {
                    window.location.href = '<?php echo SITE_URL; ?>cart/checkout';
                }, 1000);
            }
        } else {
            showToast(data.message || 'Failed to add product to cart', 'error');
        }
    } catch (error) {
        console.error('Add to cart error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Update cart count
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
        element.classList.remove('d-none');
    });
}

// Show toast notification
function showToast(message, type = 'success') {
    const toastElement = document.getElementById('cartSuccessToast');
    const toastMessage = document.getElementById('toastMessage');
    
    if (!toastElement || !toastMessage) return;
    
    toastMessage.textContent = message;
    toastElement.className = 'toast align-items-center text-bg-' + type + ' border-0';
    
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
}

// Submit review

async function submitReview() {
    const form = document.getElementById('reviewForm');
    const submitBtn = document.getElementById('submitReview');
    
    // Get form data
    const rating = document.querySelector('input[name="rating"]:checked');
    const title = document.querySelector('input[name="title"]').value;
    const review = document.querySelector('textarea[name="review"]').value;
    
    // Validation
    if (!rating) {
        showToast('Please select a rating', 'error');
        return;
    }
    
    if (!title || title.trim() === '') {
        showToast('Please enter a review title', 'error');
        return;
    }
    
    if (!review || review.trim() === '') {
        showToast('Please write your review', 'error');
        return;
    }
    
    // Show loading state
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>products/api/submit-review.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: <?php echo $product['id']; ?>,
                rating: rating.value,
                title: title.trim(),
                review: review.trim()
            })
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('API response:', data);
        
        if (data.success) {
            showToast(data.message, 'success');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('writeReviewModal'));
            if (modal) {
                modal.hide();
            }
            
            // Reset form
            form.reset();
            
            // Reset stars
            document.querySelectorAll('.rating-input .star-label i').forEach(star => {
                star.classList.remove('fas');
                star.classList.add('far');
            });
            
            // Reload page after 1.5 seconds to show new review
            setTimeout(() => {
                location.reload();
            }, 1500);
            
        } else {
            showToast(data.message, 'error');
        }
        
    } catch (error) {
        console.error('Submit review error:', error);
        showToast('Failed to submit review: ' + error.message, 'error');
    } finally {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Load reviews for the product
async function loadProductReviews() {
    const container = document.getElementById('reviewsList');
    if (!container) return;
    
    const productId = <?php echo $product['id']; ?>;
    
    // Show loading
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-dark" role="status"></div><p class="mt-2 text-muted">Loading reviews...</p></div>';
    
    try {
        const response = await fetch(`<?php echo SITE_URL; ?>ajax/get-reviews.php?product_id=${productId}&limit=4`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.reviews && data.reviews.length > 0) {
            displayReviews(data.reviews, container);
        } else {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <h5 class="fw-bold mb-3">No Reviews Yet</h5>
                    <p class="text-muted">Be the first to review this product!</p>
                    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#writeReviewModal">
                        <i class="fas fa-pen me-2"></i> Write First Review
                    </button>
                </div>
            `;
        }
        
    } catch (error) {
        console.error('Error loading reviews:', error);
        container.innerHTML = '<p class="text-muted">Could not load reviews.</p>';
    }
}

// Display reviews
function displayReviews(reviews, container) {
    if (!reviews || reviews.length === 0) {
        container.innerHTML = '<p class="text-muted">No reviews found.</p>';
        return;
    }
    
    let html = '<div class="row g-4">';
    
    reviews.forEach(review => {
        const date = new Date(review.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Get user initials
        let initials = 'U';
        if (review.full_name) {
            const nameParts = review.full_name.split(' ');
            if (nameParts.length >= 2) {
                initials = (nameParts[0].charAt(0) + nameParts[1].charAt(0)).toUpperCase();
            } else {
                initials = nameParts[0].substring(0, 2).toUpperCase();
            }
        }
        
        html += `
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 40px; height: 40px;">
                                ${initials}
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold">${review.full_name || 'Anonymous'}</h6>
                                <small class="text-muted">${date}</small>
                            </div>
                        </div>
                        
                        <div class="rating mb-3">
                            ${Array.from({length: 5}, (_, i) => 
                                `<i class="fas fa-star ${i < review.rating ? 'text-warning' : 'text-muted'}"></i>`
                            ).join('')}
                        </div>
                        
                        ${review.title ? `<h6 class="fw-bold mb-2">${review.title}</h6>` : ''}
                        
                        <p class="mb-0 text-truncate-3" style="max-height: 4.5em; overflow: hidden;">
                            ${review.review}
                        </p>
                        
                        <div class="mt-3 pt-3 border-top">
                            <small class="text-muted">
                                <i class="fas fa-thumbs-up me-1"></i> Helpful? 
                                <a href="#" class="text-decoration-none ms-2">Yes</a> • 
                                <a href="#" class="text-decoration-none">No</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Load all reviews (scrolls to reviews tab)
function loadAllReviews() {
    // Scroll to reviews section
    const reviewsTab = document.getElementById('reviews-tab');
    if (reviewsTab) {
        reviewsTab.click();
        window.scrollTo({
            top: document.getElementById('reviews').offsetTop - 100,
            behavior: 'smooth'
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load product reviews
    loadProductReviews();
    
    // Keep recently viewed functionality in localStorage for future use
    // But don't display it
    const currentProductId = <?php echo $product['id']; ?>;
    let recentlyViewed = JSON.parse(localStorage.getItem('recentlyViewed') || '[]');
    recentlyViewed = recentlyViewed.filter(id => id !== currentProductId);
    recentlyViewed.unshift(currentProductId);
    recentlyViewed = recentlyViewed.slice(0, 8);
    localStorage.setItem('recentlyViewed', JSON.stringify(recentlyViewed));
});
// Display recently viewed products
function displayRecentlyViewedProducts(products, container) {
    if (!products || products.length === 0) {
        container.innerHTML = '<p class="text-muted">No recently viewed products.</p>';
        return;
    }
    
    let html = '<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">';
    
    // Limit to 4 products
    products.slice(0, 4).forEach(product => {
        const imageUrl = product.image_url 
            ? '<?php echo SITE_URL; ?>' + product.image_url 
            : '<?php echo SITE_URL; ?>assets/images/placeholder.jpg';
        
        const productUrl = '<?php echo SITE_URL; ?>products/detail.php?slug=' + product.slug;
        const inStock = product.stock_quantity > 0;
        const hasDiscount = product.original_price_numeric && product.original_price_numeric > product.price_numeric;
        const discountPercent = hasDiscount 
            ? Math.round(((product.original_price_numeric - product.price_numeric) / product.original_price_numeric) * 100)
            : 0;
        
        html += `
            <div class="col">
                <div class="card h-100 product-card border-0 shadow-sm hover-lift">
                    <div class="position-relative overflow-hidden" style="height: 200px;">
                        <a href="${productUrl}" class="text-decoration-none">
                            <img src="${imageUrl}" 
                                 class="card-img-top h-100 w-100 object-fit-cover" 
                                 alt="${product.name}"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                            ${!inStock ? `
                                <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center">
                                    <span class="badge bg-secondary">Out of Stock</span>
                                </div>
                            ` : ''}
                        </a>
                        
                        <!-- Badges -->
                        <div class="position-absolute top-0 start-0 m-2">
                            ${!inStock ? `<span class="badge bg-secondary">Out of Stock</span>` : ''}
                            ${hasDiscount ? `<span class="badge bg-danger">-${discountPercent}%</span>` : ''}
                            ${product.rating >= 4.5 ? `<span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i> ${product.rating.toFixed(1)}</span>` : ''}
                        </div>
                        
                        <div class="position-absolute top-0 end-0 m-2">
                            <button type="button" class="btn btn-light btn-sm rounded-circle shadow-sm add-to-wishlist" 
                                    data-product-id="${product.id}">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body d-flex flex-column">
                        <div class="mb-2">
                            <small class="text-muted">${product.category || 'Uncategorized'}</small>
                        </div>
                        
                        <h6 class="card-title fw-bold mb-2">
                            <a href="${productUrl}" class="text-decoration-none text-dark text-truncate-2">
                                ${product.name}
                            </a>
                        </h6>
                        
                        ${product.rating > 0 ? `
                            <div class="rating mb-2">
                                ${Array.from({length: 5}, (_, i) => {
                                    const starClass = i < Math.floor(product.rating) ? 'fas fa-star text-warning' : 
                                                     i < product.rating ? 'fas fa-star-half-alt text-warning' : 'far fa-star text-muted';
                                    return `<i class="${starClass}"></i>`;
                                }).join('')}
                                <small class="text-muted ms-1">(${product.review_count || 0})</small>
                            </div>
                        ` : ''}
                        
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="text-dark mb-0">Ksh ${product.price_numeric.toFixed(2)}</h5>
                                    ${hasDiscount ? `
                                        <small class="text-muted text-decoration-line-through">
                                            Ksh ${product.original_price_numeric.toFixed(2)}
                                        </small>
                                    ` : ''}
                                </div>
                                ${inStock ? `
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-dark add-to-cart-btn"
                                            data-product-id="${product.id}">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                ` : `
                                    <span class="badge bg-secondary">Out of Stock</span>
                                `}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    
    // Add event listeners to new buttons
    addRecentlyViewedEventListeners();
}

// Add event listeners to recently viewed products
function addRecentlyViewedEventListeners() {
    // Add to cart buttons
    document.querySelectorAll('#recentlyViewed .add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            addToCart(productId, 1);
        });
    });
    
    // Add to wishlist buttons
    document.querySelectorAll('#recentlyViewed .add-to-wishlist').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            addToWishlist(productId);
        });
    });
}

// Save current product to recently viewed - UPDATED VERSION
function updateRecentlyViewed() {
    const currentProductId = <?php echo $product['id']; ?>;
    let recentlyViewed = JSON.parse(localStorage.getItem('recentlyViewed') || '[]');
    
    // Remove if already exists
    recentlyViewed = recentlyViewed.filter(id => id !== currentProductId);
    
    // Add to beginning
    recentlyViewed.unshift(currentProductId);
    
    // Keep only last 8 products (to have more for display filtering)
    recentlyViewed = recentlyViewed.slice(0, 8);
    
    // Save back to localStorage
    localStorage.setItem('recentlyViewed', JSON.stringify(recentlyViewed));
    
    // Update the display if container exists
    const container = document.getElementById('recentlyViewed');
    if (container && container.querySelector('.spinner-border')) {
        // If still loading, wait a bit then reload
        setTimeout(() => {
            loadRecentlyViewed();
        }, 500);
    }
    
    console.log('Recently updated:', recentlyViewed);
    return recentlyViewed;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Update recently viewed
    updateRecentlyViewed();
    
    // Load recently viewed products after a short delay
    setTimeout(() => {
        loadRecentlyViewed();
    }, 100);
});

// Helper functions (you should have these in your file)
function addToCart(productId, quantity) {
    // Your existing addToCart function
    console.log('Add to cart:', productId, quantity);
    // Implement your add to cart logic here
    showToast('Product added to cart!', 'success');
}

function addToWishlist(productId) {
    console.log('Add to wishlist:', productId);
    showToast('Product added to wishlist!', 'success');
    // Implement your wishlist logic here
}

function showToast(message, type = 'success') {
    // Your existing toast function
    console.log('Toast:', type, message);
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>