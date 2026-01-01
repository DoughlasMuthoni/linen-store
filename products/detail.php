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

// Fetch product images with variant associations
$imagesStmt = $db->prepare("
    SELECT 
        pi.*,
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

// Organize images by variant
$variantImages = [];
$generalImages = [];

foreach ($productImages as $image) {
    if ($image['variant_id']) {
        if (!isset($variantImages[$image['variant_id']])) {
            $variantImages[$image['variant_id']] = [];
        }
        $variantImages[$image['variant_id']][] = $image;
    } else {
        $generalImages[] = $image;
    }
}

// Extract unique sizes and colors from variants
$availableSizes = [];
$availableColors = [];

foreach ($variants as $variant) {
    if ($variant['size'] && !in_array($variant['size'], $availableSizes)) {
        $availableSizes[] = $variant['size'];
    }
    if ($variant['color'] && !in_array($variant['color'], $availableColors)) {
        $availableColors[] = $variant['color'];
    }
}

// Fetch product specifications
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
                   <!-- Image Variant Indicator (New) -->
                    <div id="imageVariantInfo" class="mt-2 text-center" style="display: none;">
                        <span class="badge bg-info">
                            <i class="fas fa-tag me-1"></i>
                            <span id="currentImageVariant">Variant info will appear here</span>
                        </span>
                    </div>
                    
                    <!-- Variant Image Indicator -->
                    <div id="variantImageIndicator" class="mt-2 text-center" style="display: none;">
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-image me-1"></i> Showing variant-specific images
                        </span>
                    </div>
                </div>
                
               
                <!-- Image Thumbnails -->
<div class="image-thumbnails d-flex gap-2 flex-wrap" id="imageThumbnails">
    <?php 
    // Show general images by default
    $imagesToShow = !empty($generalImages) ? $generalImages : $productImages;
    foreach ($imagesToShow as $index => $image): 
        if ($index < 8): // Limit to 8 thumbnails 
            // Get variant info for this image
            $variantInfo = '';
            if ($image['variant_id']) {
                // Find the variant for this image
                foreach ($variants as $variant) {
                    if ($variant['id'] == $image['variant_id']) {
                        $variantInfo = htmlspecialchars($variant['size'] ?? '') . 
                                      ($variant['size'] && $variant['color'] ? ' - ' : '') . 
                                      htmlspecialchars($variant['color'] ?? '');
                        break;
                    }
                }
            }
        ?>
            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                 style="width: 80px; height: 80px; cursor: pointer; position: relative;"
                 data-image="<?php echo SITE_URL . $image['image_url']; ?>"
                 data-variant-id="<?php echo $image['variant_id'] ?: '0'; ?>"
                 data-variant-info="<?php echo $variantInfo; ?>">
                <img src="<?php echo SITE_URL . $image['image_url']; ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?> - Image <?php echo $index + 1; ?>" 
                     class="img-fluid rounded border w-100 h-100 object-fit-cover"
                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                <?php if ($image['variant_id']): ?>
                    <div class="variant-badge position-absolute top-0 start-0 bg-info text-white px-1 small">
                        <i class="fas fa-tag"></i>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif;
    endforeach; 
    
    if (count($imagesToShow) === 0): ?>
        <div class="col-12 text-center text-muted py-2">
            <i class="fas fa-image fa-lg"></i>
            <p class="small mt-2">No images available</p>
        </div>
    <?php endif; ?>
</div>
                
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
    <h2 class="text-dark fw-bold display-6" id="productPrice">
        Ksh <?php echo number_format($product['price'], 2); ?>
    </h2>
    
    <!-- Compare Price Section - This will be dynamically updated -->
    <div id="comparePriceContainer">
        <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
            <div class="d-flex align-items-center gap-2" id="comparePriceSection">
                <span class="text-muted text-decoration-line-through fs-5" id="comparePriceText">
                    Ksh <?php echo number_format($product['compare_price'], 2); ?>
                </span>
                <span class="badge bg-danger fs-6" id="discountPercentage">
                    Save <?php echo round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100); ?>%
                </span>
            </div>
        <?php endif; ?>
    </div>
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
                                                   class="d-none variant-option"
                                                   <?php echo !$sizeInStock ? 'disabled' : ''; ?>
                                                   data-type="size">
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
                                                   class="d-none variant-option"
                                                   <?php echo !$colorInStock ? 'disabled' : ''; ?>
                                                   data-type="color">
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
                        
                        <!-- Variant-specific details -->
                        <div id="variantDetails" class="mb-4" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body p-3">
                                    <h6 class="card-title text-success mb-2">
                                        <i class="fas fa-check-circle me-2"></i> Selected Variant
                                    </h6>
                                    <div id="variantInfo" class="row small">
                                        <div class="col-6">
                                            <strong>Size:</strong> <span id="selectedSize">-</span>
                                        </div>
                                        <div class="col-6">
                                            <strong>Color:</strong> <span id="selectedColor">-</span>
                                        </div>
                                        <div class="col-6 mt-1">
                                            <strong>SKU:</strong> <span id="selectedSku">-</span>
                                        </div>
                                        <div class="col-6 mt-1">
                                            <strong>Stock:</strong> <span id="selectedStock">-</span>
                                        </div>
                                        <div class="col-12 mt-1">
                                            <strong>Price:</strong> <span id="selectedPrice" class="fw-bold">-</span>
                                        </div>
                                    </div>
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
                                   max="100">
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
                                             alt="<?php echo htmlspecialchars($related['name']); ?>"
                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
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
</div>

<!-- Write Review Modal -->
<div class="modal fade" id="writeReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Write a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <form id="reviewForm" onsubmit="event.preventDefault(); submitReview();">
                    <div class="modal-body">
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
:root {
    --primary-color: #4361ee;
    --primary-light: #eef2ff;
    --secondary-color: #3a0ca3;
    --accent-color: #f72585;
    --dark-color: #1a1a2e;
    --light-color: #f8f9fa;
    --success-color: #4cc9f0;
    --warning-color: #f8961e;
    --danger-color: #f94144;
}

/* Product Detail Styles */
.product-images-container .thumbnail {
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
}

.product-images-container .thumbnail.active {
    border-color: #000;
}

.product-images-container .thumbnail:hover {
    border-color: #666;
    transform: scale(1.05);
}

.product-images-container .thumbnail .variant-badge {
    border-radius: 0 0 4px 0;
    font-size: 0.6rem;
    padding: 2px 4px;
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
// Store product data for JavaScript use
const productData = {
    id: <?php echo $product['id']; ?>,
    basePrice: <?php echo $product['price']; ?>,
    baseComparePrice: <?php echo $product['compare_price'] ?: 'null'; ?>,
    variants: <?php echo json_encode($variants); ?>,
    images: <?php echo json_encode($productImages); ?>,
    variantImages: <?php echo json_encode($variantImages); ?>,
    generalImages: <?php echo json_encode($generalImages); ?>
};

// Track current state
let currentVariantId = null;
let selectedSize = null;
let selectedColor = null;

document.addEventListener('DOMContentLoaded', function() {
    // Image thumbnail click handler
    const firstThumbnail = document.querySelector('.thumbnail');
        if (firstThumbnail) {
            const variantId = firstThumbnail.dataset.variantId;
            const variantInfo = firstThumbnail.dataset.variantInfo;
            showImageVariantInfo(variantId, variantInfo);
        }
    // Image thumbnail click handler
    document.querySelectorAll('.thumbnail').forEach(thumb => {
        thumb.addEventListener('click', function() {
            const mainImage = document.getElementById('mainProductImage');
            const imageUrl = this.dataset.image;
            const variantId = this.dataset.variantId;
            const variantInfo = this.dataset.variantInfo;
            
            // Update main image
            mainImage.src = imageUrl;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show variant info if available
            showImageVariantInfo(variantId, variantInfo);
        });
         // Add to cart button
    document.querySelector('.add-to-cart-btn').addEventListener('click', function() {
        addToCartDetail(false);
    });
    
    // Buy now button
    document.querySelector('.buy-now-btn').addEventListener('click', function() {
        addToCartDetail(true);
    });
    });

    // Function to show variant info for the selected image
    function showImageVariantInfo(variantId, variantInfo) {
        const variantInfoElement = document.getElementById('imageVariantInfo');
        const currentImageVariantElement = document.getElementById('currentImageVariant');
        
        if (variantId !== '0' && variantInfo && variantInfo.trim() !== '') {
            // This image belongs to a specific variant
            currentImageVariantElement.textContent = variantInfo;
            
            // Also highlight the corresponding variant selection if available
            highlightCorrespondingVariant(variantId, variantInfo);
            
            // Show the variant info badge
            variantInfoElement.style.display = 'block';
        } else {
            // This is a general image (not variant-specific)
            currentImageVariantElement.textContent = 'General Image - Applies to all variants';
            variantInfoElement.style.display = 'block';
        }
    }

    // Function to highlight the corresponding variant in the selection
    function highlightCorrespondingVariant(variantId, variantInfo) {
        // Find the variant in our data
        const variant = productData.variants.find(v => v.id == variantId);
        
        if (variant) {
            // Try to select the corresponding size and color
            if (variant.size) {
                const sizeRadio = document.querySelector(`input[name="size"][value="${variant.size}"]`);
                if (sizeRadio && !sizeRadio.disabled) {
                    sizeRadio.checked = true;
                    selectedSize = variant.size;
                    
                    // Trigger visual selection
                    const sizeOption = sizeRadio.closest('.size-option');
                    if (sizeOption) {
                        document.querySelectorAll('.size-option .size-btn').forEach(btn => {
                            btn.classList.remove('btn-dark', 'text-white');
                            btn.classList.add('btn-outline-dark');
                        });
                        
                        const sizeBtn = sizeOption.querySelector('.size-btn');
                        if (sizeBtn) {
                            sizeBtn.classList.remove('btn-outline-dark');
                            sizeBtn.classList.add('btn-dark', 'text-white');
                        }
                    }
                }
            }
            
            if (variant.color) {
                const colorRadio = document.querySelector(`input[name="color"][value="${variant.color}"]`);
                if (colorRadio && !colorRadio.disabled) {
                    colorRadio.checked = true;
                    selectedColor = variant.color;
                    
                    // Trigger visual selection
                    const colorOption = colorRadio.closest('.color-option');
                    if (colorOption) {
                        document.querySelectorAll('.color-option .color-btn').forEach(btn => {
                            btn.style.border = '1px solid #dee2e6';
                        });
                        
                        const colorBtn = colorOption.querySelector('.color-btn');
                        if (colorBtn) {
                            colorBtn.style.border = '3px solid #000';
                        }
                    }
                }
            }
            
            // Update variant details
            updateVariantDetails();
        }
    }

    // Initialize with first image's variant info
    // document.addEventListener('DOMContentLoaded', function() { 
    // });

    // Update the updateProductImages function to also update variant info
    function updateProductImages(variantId) {
        const thumbnailsContainer = document.getElementById('imageThumbnails');
        const indicator = document.getElementById('variantImageIndicator');
        const variantInfoElement = document.getElementById('imageVariantInfo');
        
        // Clear existing thumbnails
        thumbnailsContainer.innerHTML = '';
        
        let imagesToShow = [];
        
        if (variantId && productData.variantImages[variantId]) {
            // Show variant-specific images first, then general images
            imagesToShow = [...productData.variantImages[variantId], ...productData.generalImages];
            indicator.style.display = 'block';
            variantInfoElement.style.display = 'none'; // Hide when showing filtered images
        } else {
            // Show only general images
            imagesToShow = productData.generalImages;
            indicator.style.display = 'none';
        }
        
        // If no images, show placeholder
        if (imagesToShow.length === 0) {
            thumbnailsContainer.innerHTML = `
                <div class="col-12 text-center text-muted py-2">
                    <i class="fas fa-image fa-lg"></i>
                    <p class="small mt-2">No images available</p>
                </div>
            `;
            return;
        }
        
        // Create thumbnails
        imagesToShow.forEach((image, index) => {
            if (index < 8) { // Limit to 8 thumbnails
                // Get variant info for this image
                let variantInfo = '';
                if (image.variant_id) {
                    const variant = productData.variants.find(v => v.id == image.variant_id);
                    if (variant) {
                        variantInfo = (variant.size || '') + 
                                    (variant.size && variant.color ? ' - ' : '') + 
                                    (variant.color || '');
                    }
                }
                
                const thumbnail = document.createElement('div');
                thumbnail.className = `thumbnail ${index === 0 ? 'active' : ''}`;
                thumbnail.style.cssText = 'width: 80px; height: 80px; cursor: pointer; position: relative;';
                thumbnail.dataset.image = '<?php echo SITE_URL; ?>' + image.image_url;
                thumbnail.dataset.variantId = image.variant_id || '0';
                thumbnail.dataset.variantInfo = variantInfo;
                
                thumbnail.innerHTML = `
                    <img src="<?php echo SITE_URL; ?>${image.image_url}" 
                        alt="<?php echo htmlspecialchars($product['name']); ?> - Image ${index + 1}" 
                        class="img-fluid rounded border w-100 h-100 object-fit-cover"
                        onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                    ${image.variant_id ? 
                        '<div class="variant-badge position-absolute top-0 start-0 bg-info text-white px-1 small">' +
                        '<i class="fas fa-tag"></i></div>' : ''}
                `;
                
                thumbnail.addEventListener('click', function() {
                    const mainImage = document.getElementById('mainProductImage');
                    mainImage.src = this.dataset.image;
                    
                    document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show variant info
                    showImageVariantInfo(this.dataset.variantId, this.dataset.variantInfo);
                });
                
                thumbnailsContainer.appendChild(thumbnail);
            }
        });
        
        // Update main image to first thumbnail
        if (imagesToShow.length > 0) {
            const mainImage = document.getElementById('mainProductImage');
            mainImage.src = '<?php echo SITE_URL; ?>' + imagesToShow[0].image_url;
            
            // Show variant info for first image
            const firstImage = imagesToShow[0];
            let variantInfo = '';
            if (firstImage.variant_id) {
                const variant = productData.variants.find(v => v.id == firstImage.variant_id);
                if (variant) {
                    variantInfo = (variant.size || '') + 
                                (variant.size && variant.color ? ' - ' : '') + 
                                (variant.color || '');
                }
            }
            
            // Show/hide variant info based on whether we're filtering
            if (!variantId) {
                showImageVariantInfo(firstImage.variant_id || '0', variantInfo);
            }
        }
    }
        
    // Quantity controls
    document.getElementById('decreaseQty').addEventListener('click', function() {
        const qtyInput = document.getElementById('quantity');
        if (parseInt(qtyInput.value) > 1) {
            qtyInput.value = parseInt(qtyInput.value) - 1;
        }
    });
    
    document.getElementById('increaseQty').addEventListener('click', function() {
        const qtyInput = document.getElementById('quantity');
        const maxQty = 100; // Default max
        if (parseInt(qtyInput.value) < maxQty) {
            qtyInput.value = parseInt(qtyInput.value) + 1;
        }
    });
    
    // Variant selection
    document.querySelectorAll('.variant-option').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.dataset.type === 'size') {
                selectedSize = this.value;
            } else if (this.dataset.type === 'color') {
                selectedColor = this.value;
            }
            updateVariantDetails();
        });
    });
    
    // Add to cart button
    document.querySelector('.add-to-cart-btn').addEventListener('click', function() {
        addToCartDetail();
    });
    
    // Buy now button
    document.querySelector('.buy-now-btn').addEventListener('click', function() {
        addToCartDetail(true);
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
    
    // Initialize with default variant if exists
    const defaultVariant = productData.variants.find(v => v.is_default == 1);
    if (defaultVariant) {
        // Check the default variant's options
        if (defaultVariant.size) {
            const sizeRadio = document.querySelector(`input[name="size"][value="${defaultVariant.size}"]`);
            if (sizeRadio && !sizeRadio.disabled) {
                sizeRadio.checked = true;
                selectedSize = defaultVariant.size;
            }
        }
        
        if (defaultVariant.color) {
            const colorRadio = document.querySelector(`input[name="color"][value="${defaultVariant.color}"]`);
            if (colorRadio && !colorRadio.disabled) {
                colorRadio.checked = true;
                selectedColor = defaultVariant.color;
            }
        }
        
        // Update variant details
        setTimeout(updateVariantDetails, 100);
    }
});

// Update the updateVariantDetails function to handle price changes
function updateVariantDetails() {
    if (selectedSize && selectedColor) {
        // Find the variant that matches both size and color
        const variant = findVariant(selectedSize, selectedColor);
        
        if (variant) {
            currentVariantId = variant.id;
            const variantDetails = document.getElementById('variantDetails');
            const variantInfo = document.getElementById('variantInfo');
            
            // Update variant info display
            document.getElementById('selectedSize').textContent = variant.size || '-';
            document.getElementById('selectedColor').textContent = variant.color || '-';
            document.getElementById('selectedSku').textContent = variant.sku;
            document.getElementById('selectedStock').textContent = variant.stock_quantity + ' available';
            
            // Update price if different from base price
            updatePriceForVariant(variant);
            
            variantDetails.style.display = 'block';
            
            // Update images based on selected variant
            updateProductImages(variant.id);
            
            // Update quantity max
            const qtyInput = document.getElementById('quantity');
            qtyInput.max = Math.min(variant.stock_quantity, 100);
            
            // Update add to cart button
            const addToCartBtn = document.querySelector('.add-to-cart-btn');
            const buyNowBtn = document.querySelector('.buy-now-btn');
            
            if (variant.stock_quantity > 0) {
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i> Add to Cart';
                buyNowBtn.disabled = false;
            } else {
                addToCartBtn.disabled = true;
                addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i> Out of Stock';
                buyNowBtn.disabled = true;
            }
        }
    } else {
        // If no specific variant selected, show general images and reset price
        currentVariantId = null;
        updateProductImages(null);
        
        // Hide variant details
        document.getElementById('variantDetails').style.display = 'none';
        
        // Reset price to base price
        resetPriceToBase();
        
        // Reset quantity max
        const qtyInput = document.getElementById('quantity');
        qtyInput.max = 100;
        
        // Update add to cart button based on total stock
        const totalStock = productData.variants.reduce((sum, v) => sum + v.stock_quantity, 0);
        const addToCartBtn = document.querySelector('.add-to-cart-btn');
        const buyNowBtn = document.querySelector('.buy-now-btn');
        
        if (totalStock > 0) {
            addToCartBtn.disabled = false;
            addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i> Add to Cart';
            buyNowBtn.disabled = false;
        } else {
            addToCartBtn.disabled = true;
            addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i> Out of Stock';
            buyNowBtn.disabled = true;
        }
    }
}

// Function to update price for selected variant
function updatePriceForVariant(variant) {
    const priceElement = document.getElementById('productPrice');
    const comparePriceContainer = document.getElementById('comparePriceContainer');
    
    // Convert price to number if it's a string
    const variantPrice = parseFloat(variant.price);
    
    // Update main price with animation
    priceElement.classList.add('price-change');
    setTimeout(() => {
        priceElement.innerHTML = `Ksh <span class="text-success">${variantPrice.toFixed(2)}</span>`;
        priceElement.classList.remove('price-change');
    }, 150);
    
    // Update selected price in variant details
    const selectedPriceElement = document.getElementById('selectedPrice');
    if (selectedPriceElement) {
        selectedPriceElement.innerHTML = `Ksh <span class="fw-bold">${variantPrice.toFixed(2)}</span>`;
    }
    
    // Handle compare price
    updateComparePriceForVariant(variant);
}

// Function to update compare price
function updateComparePriceForVariant(variant) {
    const comparePriceContainer = document.getElementById('comparePriceContainer');
    
    // Convert prices to numbers
    const variantPrice = parseFloat(variant.price);
    const variantComparePrice = variant.compare_price ? parseFloat(variant.compare_price) : null;
    const baseComparePrice = productData.baseComparePrice ? parseFloat(productData.baseComparePrice) : null;
    
    // Check if variant has compare price
    if (variantComparePrice && variantComparePrice > variantPrice) {
        // Show variant-specific compare price
        comparePriceContainer.innerHTML = `
            <div class="d-flex align-items-center gap-2" id="comparePriceSection">
                <span class="text-muted text-decoration-line-through fs-5" id="comparePriceText">
                    Ksh ${variantComparePrice.toFixed(2)}
                </span>
                <span class="badge bg-danger fs-6" id="discountPercentage">
                    Save ${Math.round(((variantComparePrice - variantPrice) / variantComparePrice) * 100)}%
                </span>
            </div>
        `;
    } else if (baseComparePrice && baseComparePrice > productData.basePrice) {
        // Show original product compare price if variant doesn't have one
        comparePriceContainer.innerHTML = `
            <div class="d-flex align-items-center gap-2" id="comparePriceSection">
                <span class="text-muted text-decoration-line-through fs-5" id="comparePriceText">
                    Ksh ${baseComparePrice.toFixed(2)}
                </span>
                <span class="badge bg-danger fs-6" id="discountPercentage">
                    Save ${Math.round(((baseComparePrice - productData.basePrice) / baseComparePrice) * 100)}%
                </span>
            </div>
        `;
    } else {
        // No compare price at all
        comparePriceContainer.innerHTML = '';
    }
}
// Function to reset price to base product price
function resetPriceToBase() {
    const priceElement = document.getElementById('productPrice');
    const comparePriceContainer = document.getElementById('comparePriceContainer');
    
    // Reset to base price with animation
    priceElement.classList.add('price-change');
    setTimeout(() => {
        priceElement.textContent = `Ksh ${productData.basePrice.toFixed(2)}`;
        priceElement.classList.remove('price-change');
    }, 150);
    
    // Reset selected price in variant details
    const selectedPriceElement = document.getElementById('selectedPrice');
    if (selectedPriceElement) {
        selectedPriceElement.textContent = `Ksh ${productData.basePrice.toFixed(2)}`;
    }
    
    // Reset compare price to original
    if (productData.baseComparePrice && parseFloat(productData.baseComparePrice) > productData.basePrice) {
        comparePriceContainer.innerHTML = `
            <div class="d-flex align-items-center gap-2" id="comparePriceSection">
                <span class="text-muted text-decoration-line-through fs-5" id="comparePriceText">
                    Ksh ${parseFloat(productData.baseComparePrice).toFixed(2)}
                </span>
                <span class="badge bg-danger fs-6" id="discountPercentage">
                    Save ${Math.round(((parseFloat(productData.baseComparePrice) - productData.basePrice) / parseFloat(productData.baseComparePrice)) * 100)}%
                </span>
            </div>
        `;
    } else {
        comparePriceContainer.innerHTML = '';
    }
}
// Update the highlightCorrespondingVariant function to also update price
function highlightCorrespondingVariant(variantId, variantInfo) {
    // Find the variant in our data
    const variant = productData.variants.find(v => v.id == variantId);
    
    if (variant) {
        // Try to select the corresponding size and color
        if (variant.size) {
            const sizeRadio = document.querySelector(`input[name="size"][value="${variant.size}"]`);
            if (sizeRadio && !sizeRadio.disabled) {
                sizeRadio.checked = true;
                selectedSize = variant.size;
                
                // Trigger visual selection
                const sizeOption = sizeRadio.closest('.size-option');
                if (sizeOption) {
                    document.querySelectorAll('.size-option .size-btn').forEach(btn => {
                        btn.classList.remove('btn-dark', 'text-white');
                        btn.classList.add('btn-outline-dark');
                    });
                    
                    const sizeBtn = sizeOption.querySelector('.size-btn');
                    if (sizeBtn) {
                        sizeBtn.classList.remove('btn-outline-dark');
                        sizeBtn.classList.add('btn-dark', 'text-white');
                    }
                }
            }
        }
        
        if (variant.color) {
            const colorRadio = document.querySelector(`input[name="color"][value="${variant.color}"]`);
            if (colorRadio && !colorRadio.disabled) {
                colorRadio.checked = true;
                selectedColor = variant.color;
                
                // Trigger visual selection
                const colorOption = colorRadio.closest('.color-option');
                if (colorOption) {
                    document.querySelectorAll('.color-option .color-btn').forEach(btn => {
                        btn.style.border = '1px solid #dee2e6';
                    });
                    
                    const colorBtn = colorOption.querySelector('.color-btn');
                    if (colorBtn) {
                        colorBtn.style.border = '3px solid #000';
                    }
                }
            }
        }
        
        // Update price for this variant
        updatePriceForVariant(variant);
        
        // Show variant details
        const variantDetails = document.getElementById('variantDetails');
        const variantInfoElement = document.getElementById('variantInfo');
        
        if (variantDetails && variantInfoElement) {
            document.getElementById('selectedSize').textContent = variant.size || '-';
            document.getElementById('selectedColor').textContent = variant.color || '-';
            document.getElementById('selectedSku').textContent = variant.sku;
            document.getElementById('selectedStock').textContent = variant.stock_quantity + ' available';
            document.getElementById('selectedPrice').innerHTML = `Ksh <span class="fw-bold">${variant.price.toFixed(2)}</span>`;
            
            variantDetails.style.display = 'block';
        }
        
        // Update images
        updateProductImages(variant.id);
    }
}

// Update the showImageVariantInfo function to update price
function showImageVariantInfo(variantId, variantInfo) {
    const variantInfoElement = document.getElementById('imageVariantInfo');
    const currentImageVariantElement = document.getElementById('currentImageVariant');
    
    if (variantId !== '0' && variantInfo && variantInfo.trim() !== '') {
        // This image belongs to a specific variant
        currentImageVariantElement.textContent = variantInfo;
        
        // Also highlight the corresponding variant selection and update price
        highlightCorrespondingVariant(variantId, variantInfo);
        
        // Show the variant info badge
        variantInfoElement.style.display = 'block';
    } else {
        // This is a general image (not variant-specific)
        currentImageVariantElement.textContent = 'General Image - Applies to all variants';
        variantInfoElement.style.display = 'block';
        
        // Reset to base price if clicking on general image
        resetPriceToBase();
        
        // Hide variant details
        const variantDetails = document.getElementById('variantDetails');
        if (variantDetails) {
            variantDetails.style.display = 'none';
        }
        
        // Clear selections
        selectedSize = null;
        selectedColor = null;
        
        // Reset visual selections
        document.querySelectorAll('.size-option .size-btn').forEach(btn => {
            btn.classList.remove('btn-dark', 'text-white');
            btn.classList.add('btn-outline-dark');
        });
        
        document.querySelectorAll('.color-option .color-btn').forEach(btn => {
            btn.style.border = '1px solid #dee2e6';
        });
        
        // Uncheck radio buttons
        document.querySelectorAll('input[name="size"]:checked').forEach(radio => {
            radio.checked = false;
        });
        
        document.querySelectorAll('input[name="color"]:checked').forEach(radio => {
            radio.checked = false;
        });
    }
}
// Find variant by size and color
function findVariant(size, color) {
    return productData.variants.find(v => 
        v.size === size && 
        v.color === color && 
        v.stock_quantity > 0
    );
}

// Update product images based on variant
function updateProductImages(variantId) {
    const thumbnailsContainer = document.getElementById('imageThumbnails');
    const indicator = document.getElementById('variantImageIndicator');
    
    // Clear existing thumbnails
    thumbnailsContainer.innerHTML = '';
    
    let imagesToShow = [];
    
    if (variantId && productData.variantImages[variantId]) {
        // Show variant-specific images first, then general images
        imagesToShow = [...productData.variantImages[variantId], ...productData.generalImages];
        indicator.style.display = 'block';
    } else {
        // Show only general images
        imagesToShow = productData.generalImages;
        indicator.style.display = 'none';
    }
    
    // If no images, show placeholder
    if (imagesToShow.length === 0) {
        thumbnailsContainer.innerHTML = `
            <div class="col-12 text-center text-muted py-2">
                <i class="fas fa-image fa-lg"></i>
                <p class="small mt-2">No images available</p>
            </div>
        `;
        return;
    }
    
    // Create thumbnails
    imagesToShow.forEach((image, index) => {
        if (index < 8) { // Limit to 8 thumbnails
            const thumbnail = document.createElement('div');
            thumbnail.className = `thumbnail ${index === 0 ? 'active' : ''}`;
            thumbnail.style.cssText = 'width: 80px; height: 80px; cursor: pointer; position: relative;';
            thumbnail.dataset.image = '<?php echo SITE_URL; ?>' + image.image_url;
            thumbnail.dataset.variantId = image.variant_id || 'general';
            
            thumbnail.innerHTML = `
                <img src="<?php echo SITE_URL; ?>${image.image_url}" 
                     alt="<?php echo htmlspecialchars($product['name']); ?> - Image ${index + 1}" 
                     class="img-fluid rounded border w-100 h-100 object-fit-cover"
                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                ${image.variant_id ? 
                    '<div class="variant-badge position-absolute top-0 start-0 bg-info text-white px-1 small">' +
                    '<i class="fas fa-tag"></i></div>' : ''}
            `;
            
            thumbnail.addEventListener('click', function() {
                const mainImage = document.getElementById('mainProductImage');
                mainImage.src = this.dataset.image;
                
                document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
            
            thumbnailsContainer.appendChild(thumbnail);
        }
    });
    
    // Update main image to first thumbnail
    if (imagesToShow.length > 0) {
        const mainImage = document.getElementById('mainProductImage');
        mainImage.src = '<?php echo SITE_URL; ?>' + imagesToShow[0].image_url;
    }
}
// Create a handler function that prepares the data properly
async function handleAddToCart(buyNow = false) {
    try {
        const quantity = parseInt(document.getElementById('quantity').value) || 1;
        const size = selectedSize;
        const color = selectedColor;
        
        console.log('DEBUG - Current state:', {
            productId: productData.id,
            quantity: quantity,
            selectedSize: selectedSize,
            selectedColor: selectedColor,
            size: size,
            color: color
        });
        
        // Validate variant selection if variants exist
        if (productData.variants && productData.variants.length > 0) {
            if (!size || !color) {
                showToast('Please select both size and color', 'error');
                return;
            }
            
            // Find the selected variant
            const variant = findVariant(size, color);
            if (!variant) {
                showToast('Selected variant is not available', 'error');
                return;
            }
            
            // Check stock
            if (variant.stock_quantity <= 0) {
                showToast('This variant is out of stock', 'error');
                return;
            }
            
            if (quantity > variant.stock_quantity) {
                showToast(`Only ${variant.stock_quantity} items available`, 'error');
                return;
            }
            
            console.log('DEBUG - Found variant:', variant);
        }
        
        // Prepare cart data
        const cartData = {
            product_id: productData.id,
            quantity: quantity
        };
        
        // Add variant data if selected
        if (size && color) {
            const variant = findVariant(size, color);
            if (variant && variant.id) {
                cartData.variant_id = variant.id;
                cartData.size = size;
                cartData.color = color;
                
                // Add variant-specific price if different
                if (variant.price && variant.price !== productData.basePrice) {
                    cartData.price = variant.price;
                }
            }
        }
        
        console.log('DEBUG - Final cart data:', cartData);
        
        // Show loading state
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
        this.disabled = true;
        
        // Use a unique function name to avoid conflict with main.js
        await addProductToCartDetail(cartData, buyNow);
        
    } catch (error) {
        console.error('Handle add to cart error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    } finally {
        // Reset button state
        const addToCartBtn = document.querySelector('.add-to-cart-btn');
        const buyNowBtn = document.querySelector('.buy-now-btn');
        
        if (addToCartBtn) {
            addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Add to Cart';
            addToCartBtn.disabled = false;
        }
        
        if (buyNowBtn) {
            buyNowBtn.innerHTML = '<i class="fas fa-bolt me-2"></i>Buy Now';
            buyNowBtn.disabled = false;
        }
    }
}

async function addToCartDetail(buyNow = false) {
    try {
        const productId = productData.id;
        const quantity = parseInt(document.getElementById('quantity').value) || 1;
        const size = selectedSize;
        const color = selectedColor;
        
        console.log('Add to cart details:', {
            productId: productId,
            quantity: quantity,
            size: size,
            color: color
        });
        
        // Validate product ID
        if (!productId) {
            showToast('Product ID is required', 'error');
            return;
        }
        
        // For products with variants, validate selection
        if (productData.variants && productData.variants.length > 0) {
            if (!size || !color) {
                showToast('Please select both size and color', 'error');
                return;
            }
            
            // Find the selected variant
            const variant = findVariant(size, color);
            if (!variant) {
                showToast('Selected variant is not available', 'error');
                return;
            }
            
            // Check stock
            if (variant.stock_quantity <= 0) {
                showToast('This variant is out of stock', 'error');
                return;
            }
            
            if (quantity > variant.stock_quantity) {
                showToast(`Only ${variant.stock_quantity} items available`, 'error');
                return;
            }
        }
        
        // Prepare cart data
        const cartData = {
            product_id: productId,
            quantity: quantity,
            size: size,
            color: color
        };
        
        // Add variant ID if available
        if (currentVariantId) {
            cartData.variant_id = currentVariantId;
        }
        
        // Show loading state
        const addToCartBtn = document.querySelector('.add-to-cart-btn');
        const buyNowBtn = document.querySelector('.buy-now-btn');
        
        const originalText = addToCartBtn.innerHTML;
        const originalBuyNowText = buyNowBtn.innerHTML;
        
        addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
        addToCartBtn.disabled = true;
        buyNowBtn.disabled = true;
        
        // Send request to server
        const response = await fetch('<?php echo SITE_URL; ?>ajax/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(cartData)
        });
        
        // First check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text.substring(0, 200));
            throw new Error('Server returned non-JSON response');
        }
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Product added to cart successfully!');
            
            // Update cart count
            if (data.cart_count !== undefined) {
                updateCartCount(data.cart_count);
            }
            
            if (buyNow) {
                setTimeout(() => {
                    window.location.href = '<?php echo SITE_URL; ?>cart/checkout';
                }, 1000);
            }
        } else {
            throw new Error(data.message || 'Failed to add to cart');
        }
        
    } catch (error) {
        console.error('Add to cart error:', error);
        showToast(error.message || 'Something went wrong. Please try again.', 'error');
    } finally {
        // Reset button state
        const addToCartBtn = document.querySelector('.add-to-cart-btn');
        const buyNowBtn = document.querySelector('.buy-now-btn');
        
        if (addToCartBtn) {
            addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Add to Cart';
            addToCartBtn.disabled = false;
        }
        
        if (buyNowBtn) {
            buyNowBtn.innerHTML = '<i class="fas fa-bolt me-2"></i>Buy Now';
            buyNowBtn.disabled = false;
        }
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
                product_id: productData.id,
                rating: rating.value,
                title: title.trim(),
                review: review.trim()
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
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
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>