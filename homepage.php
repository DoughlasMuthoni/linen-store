<?php
// /linen-closet/homepage.php

// Get database connection (keep your existing PHP code as is)
require_once 'includes/config.php';
require_once 'includes/Database.php';
require_once 'includes/App.php';

$app = new App();
$db = $app->getDB();

// Fetch new arrivals (products added in the last 30 days)
$newArrivals = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            c.name as category_name,
            c.slug as category_slug,
            (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
            (SELECT MIN(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as variant_min_price,
            (SELECT MAX(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as variant_max_price,
            COALESCE((SELECT MIN(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0), p.price) as display_price,
            (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_variant_stock
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 
        AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $newArrivals = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching new arrivals: " . $e->getMessage());
}

// Fetch best sellers (based on order items)
$bestSellers = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            c.name as category_name,
            c.slug as category_slug,
            (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
            (SELECT MIN(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as variant_min_price,
            (SELECT MAX(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as variant_max_price,
            COALESCE((SELECT MIN(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0), p.price) as display_price,
            (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_variant_stock,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as times_ordered
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        ORDER BY times_ordered DESC, p.id DESC
        LIMIT 8
    ");
    $stmt->execute();
    $bestSellers = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching best sellers: " . $e->getMessage());
}

// Fetch featured categories
$categories = [];
try {
   $stmt = $db->prepare("
    SELECT 
        c.id,
        c.name,
        c.slug,
        c.image_url,
        c.description,
        COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
    WHERE c.is_active = 1 
    AND (c.parent_id IS NULL OR c.parent_id = 0)
    GROUP BY c.id
    ORDER BY c.name
    LIMIT 6
");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Fetch sale products
$saleProducts = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            c.name as category_name,
            c.slug as category_slug,
            (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
            (SELECT MIN(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as variant_min_price,
            (SELECT MAX(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as variant_max_price,
            COALESCE((SELECT MIN(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0), p.price) as display_price,
            (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_variant_stock
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 
        AND p.compare_price > p.price
        ORDER BY (p.compare_price - p.price) / p.compare_price DESC
        LIMIT 8
    ");
    $stmt->execute();
    $saleProducts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching sale products: " . $e->getMessage());
}

// Helper function to format price (same as products page)
function formatPrice($price) {
    return 'Ksh ' . number_format($price, 2);
}

// Helper function to get stock status (same as products page)
function getStockStatus($stock) {
    if ($stock <= 0) {
        return ['class' => 'danger', 'text' => 'Out of Stock'];
    } elseif ($stock <= 10) {
        return ['class' => 'warning', 'text' => $stock . ' Left'];
    } else {
        return ['class' => 'success', 'text' => 'In Stock'];
    }
}
// Set page title
$pageTitle = "Premium Linen Collection | Timeless Style & Comfort";
require_once 'includes/header.php';
?>

<!-- SIMPLIFIED AMAZON-STYLE LAYOUT -->
<div class="container-fluid px-0">
    <!-- Hero Banner -->
    <div class="amazon-hero-banner position-relative mb-4">
        <div class="row g-0">
            <div class="col-12">
                <div id="amazonHeroCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <div class="hero-slide bg-primary text-white p-5">
                                <div class="container">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <h1 class="display-4 fw-bold mb-3">Summer Linen Sale</h1>
                                            <p class="lead mb-4">Up to 50% off on premium linen collection</p>
                                            <a href="<?php echo SITE_URL; ?>products?filter=on_sale" class="btn btn-light btn-lg">
                                                Shop Now <i class="fas fa-arrow-right ms-2"></i>
                                            </a>
                                        </div>
                                        <div class="col-md-5 text-center">
                                            <i class="fas fa-couch fa-10x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <div class="hero-slide bg-dark text-white p-5">
                                <div class="container">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <h1 class="display-4 fw-bold mb-3">New Arrivals</h1>
                                            <p class="lead mb-4">Discover our latest linen collection</p>
                                            <a href="#new-arrivals" class="btn btn-light btn-lg">
                                                Explore New Items
                                            </a>
                                        </div>
                                        <div class="col-md-5 text-center">
                                            <i class="fas fa-star fa-10x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#amazonHeroCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#amazonHeroCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Feature Cards -->
    <div class="container mb-4">
        <div class="row g-3">
            <div class="col-md-3 col-6">
                <div class="feature-card text-center p-3 border rounded">
                    <i class="fas fa-bolt fa-2x text-warning mb-3"></i>
                    <h6 class="fw-bold">Today's Deals</h6>
                    <p class="small text-muted">Special offers available</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="feature-card text-center p-3 border rounded">
                    <i class="fas fa-shipping-fast fa-2x text-primary mb-3"></i>
                    <h6 class="fw-bold">Free Shipping</h6>
                    <p class="small text-muted">Orders over Ksh 5,000</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="feature-card text-center p-3 border rounded">
                    <i class="fas fa-award fa-2x text-success mb-3"></i>
                    <h6 class="fw-bold">Best Quality</h6>
                    <p class="small text-muted">Premium linen guaranteed</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="feature-card text-center p-3 border rounded">
                    <i class="fas fa-headset fa-2x text-danger mb-3"></i>
                    <h6 class="fw-bold">24/7 Support</h6>
                    <p class="small text-muted">Dedicated assistance</p>
                </div>
            </div>
        </div>
    </div>

   <div class="container mb-5">
    <div class="section-header bg-danger text-white p-3 rounded-top mb-0">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="fw-bold mb-0">Today's Deals</h3>
            <a href="<?php echo SITE_URL; ?>products?filter=on_sale" class="btn btn-link text-white text-decoration-none p-0">
                See all deals <i class="fas fa-chevron-right ms-1"></i>
            </a>
        </div>
    </div>
    <div class="bg-white p-3 border rounded-bottom">
        <?php if (!empty($saleProducts)): ?>
        <div class="row g-3">
            <?php foreach(array_slice($saleProducts, 0, 6) as $index => $product): 
                $stockStatus = getStockStatus($product['total_variant_stock'] ?? $product['stock_quantity'] ?? 0);
                $hasVariants = !empty($product['variant_min_price']) && !empty($product['variant_max_price']);
                $priceDisplay = $hasVariants && $product['variant_min_price'] != $product['variant_max_price'] 
                    ? formatPrice($product['variant_min_price']) . " - " . formatPrice($product['variant_max_price'])
                    : formatPrice($product['display_price'] ?? $product['price']);
                $discountPercent = isset($product['compare_price']) && $product['compare_price'] > $product['price']
                    ? round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100)
                    : 0;
                $discountAmount = isset($product['compare_price']) && $product['compare_price'] > $product['price']
                    ? $product['compare_price'] - $product['price']
                    : 0;
            ?>
            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
                <div class="product-card border rounded p-2 h-100 d-flex flex-column">
                    <!-- Discount Badge -->
                    <div class="position-absolute top-0 start-0 m-2">
                        <span class="badge bg-danger text-white px-2 py-1 fw-bold">
                            -<?php echo $discountPercent; ?>%
                        </span>
                    </div>
                    
                    <!-- Product Image - Full Coverage -->
                    <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>"
                       class="text-decoration-none">
                        <div class="product-image mb-2 position-relative" 
                             style="height: 180px; overflow: hidden; background: #f8f9fa; border-radius: 4px;">
                            <img src="<?php echo $product['primary_image'] ? SITE_URL . $product['primary_image'] : SITE_URL . 'assets/images/placeholder.jpg'; ?>" 
                                 class="img-fluid w-100 h-100 object-fit-cover"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                        </div>
                    </a>
                    
                    <!-- Product Details -->
                    <div class="product-details flex-grow-1">
                        <!-- Category -->
                        <div class="mb-1">
                            <small class="text-muted">
                                <?php if (!empty($product['category_name'])): ?>
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                <?php else: ?>
                                    Linen Products
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <!-- Product Title -->
                        <h6 class="product-title small fw-bold mb-2">
                            <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" 
                               class="text-decoration-none text-dark text-truncate-2">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h6>
                        
                        <!-- Rating -->
                        <div class="rating mb-2">
                            <span class="text-warning small">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </span>
                            <small class="text-muted ms-1">(<?php echo rand(45, 350); ?>)</small>
                        </div>
                        
                        <!-- Price -->
                        <div class="price mb-2">
                            <div class="d-flex align-items-center flex-wrap">
                                <span class="fw-bold text-dark fs-6"><?php echo $priceDisplay; ?></span>
                                <?php if ($discountPercent > 0): ?>
                                    <small class="text-muted text-decoration-line-through ms-2">
                                        <?php echo formatPrice($product['compare_price']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <?php if ($discountPercent > 0): ?>
                                <div class="save-badge mt-1">
                                    <small class="text-success fw-bold">
                                        Save Ksh <?php echo number_format($discountAmount, 2); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stock Status -->
                        <div class="stock-status mb-2">
                            <?php if ($stockStatus['text'] === 'In Stock'): ?>
                                <span class="badge bg-success small">
                                    <i class="fas fa-check me-1"></i> In Stock
                                </span>
                            <?php elseif ($stockStatus['text'] === 'Out of Stock'): ?>
                                <span class="badge bg-secondary small">
                                    <i class="fas fa-times me-1"></i> Out of Stock
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark small">
                                    <i class="fas fa-exclamation me-1"></i> <?php echo $stockStatus['text']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Delivery Info -->
                        <div class="delivery-info mb-2">
                            <!-- <div class="text-success small">
                                <i class="fas fa-shipping-fast me-1"></i> FREE delivery
                            </div> -->
                            <div class="text-muted small">
                                <i class="fas fa-calendar-alt me-1"></i> 
                                <?php 
                                $deliveryDate = date('D, M j', strtotime('+' . rand(1, 3) . ' days'));
                                echo "Arrives $deliveryDate";
                                ?>
                            </div>
                        </div>
                        
                        <!-- Deal Timer -->
                        <div class="deal-timer small text-danger mb-3">
                            <i class="fas fa-clock me-1"></i> 
                            <span class="fw-bold"><?php echo rand(1, 12); ?>h <?php echo rand(1, 59); ?>m</span> left
                        </div>
                    </div>
                    
                    <!-- Add to Cart Button -->
                    <div class="mt-auto">
                        <button class="btn btn-danger w-100 add-to-cart-btn"
                                data-product-id="<?php echo $product['id']; ?>"
                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                <?php echo $stockStatus['text'] === 'Out of Stock' ? 'disabled' : ''; ?>
                                style="padding: 0.4rem 0.5rem;">
                            <i class="fas fa-cart-plus me-1"></i>
                            <?php echo $stockStatus['text'] === 'Out of Stock' ? 'Out of Stock' : 'Add to Cart'; ?>
                        </button>
                        
                        <!-- Quick Actions -->
                        <!-- <div class="quick-actions mt-2 d-flex justify-content-center gap-2">
                            <button class="btn btn-outline-secondary btn-sm add-to-wishlist" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    title="Add to Wishlist">
                                <i class="far fa-heart"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm quick-view-btn" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    title="Quick View">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div> -->
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="fas fa-tags fa-3x text-muted"></i>
            </div>
            <h5>No Deals Available</h5>
            <p class="text-muted mb-3">Check back soon for special offers!</p>
            <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-primary">
                Browse All Products
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<!-- Best Sellers Section -->
<div class="container mb-5">
    <div class="section-header bg-white p-3 border rounded-top mb-0">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="fw-bold mb-0">Best Sellers</h3>
            <a href="<?php echo SITE_URL; ?>products?sort=best_selling" class="text-primary text-decoration-none">
                See all <i class="fas fa-chevron-right ms-1"></i>
            </a>
        </div>
    </div>
    <div class="bg-white p-3 border rounded-bottom">
        <?php if (!empty($bestSellers)): ?>
        <div class="row g-3">
            <?php foreach(array_slice($bestSellers, 0, 8) as $index => $product): 
                $stockStatus = getStockStatus($product['total_variant_stock'] ?? $product['stock_quantity'] ?? 0);
                $hasVariants = !empty($product['variant_min_price']) && !empty($product['variant_max_price']);
                $priceDisplay = $hasVariants && $product['variant_min_price'] != $product['variant_max_price'] 
                    ? formatPrice($product['variant_min_price']) . " - " . formatPrice($product['variant_max_price'])
                    : formatPrice($product['display_price'] ?? $product['price']);
                $discountPercent = isset($product['compare_price']) && $product['compare_price'] > $product['price']
                    ? round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100)
                    : 0;
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="product-card border rounded p-3 h-100 d-flex flex-column">
                    <!-- Product Image with Badges -->
                    <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>"
                       class="text-decoration-none">
                        <div class="position-relative mb-3" style="height: 220px; overflow: hidden; background: #f8f9fa; border-radius: 4px;">
                            <img src="<?php echo $product['primary_image'] ? SITE_URL . $product['primary_image'] : SITE_URL . 'assets/images/placeholder.jpg'; ?>" 
                                 class="img-fluid w-100 h-100 object-fit-cover"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                            
                            <!-- Best Seller Badge -->
                            <?php if ($index < 3): ?>
                                <span class="position-absolute top-0 start-0 bg-warning text-dark px-2 py-1 fw-bold m-2">
                                    #<?php echo $index + 1; ?> Best Seller
                                </span>
                            <?php else: ?>
                                <span class="position-absolute top-0 start-0 bg-dark text-white px-2 py-1 fw-bold m-2 small">
                                    Top Seller
                                </span>
                            <?php endif; ?>
                            
                            <!-- Discount Badge -->
                            <?php if ($discountPercent > 0): ?>
                                <span class="position-absolute top-0 end-0 bg-danger text-white px-2 py-1 fw-bold m-2">
                                    -<?php echo $discountPercent; ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                    
                    <!-- Product Details -->
                    <div class="product-details flex-grow-1">
                        <!-- Category -->
                        <div class="mb-1">
                            <small class="text-muted">
                                <?php if (!empty($product['category_name'])): ?>
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                <?php else: ?>
                                    Linen Collection
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <!-- Product Title -->
                        <h6 class="product-title fw-bold mb-2">
                            <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" 
                               class="text-decoration-none text-dark text-truncate-2">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h6>
                        
                        <!-- Rating -->
                        <div class="rating mb-2">
                            <span class="text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </span>
                            <small class="text-muted ms-1">(<?php echo rand(120, 850); ?>)</small>
                        </div>
                        
                        <!-- Price -->
                        <div class="price mb-2">
                            <span class="fw-bold fs-5 text-dark"><?php echo $priceDisplay; ?></span>
                            <?php if ($discountPercent > 0): ?>
                                <small class="text-muted text-decoration-line-through ms-2">
                                    <?php echo formatPrice($product['compare_price']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stock Status -->
                        <div class="stock-status mb-3">
                            <?php if ($stockStatus['text'] === 'In Stock'): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i> In Stock
                                </span>
                            <?php elseif ($stockStatus['text'] === 'Out of Stock'): ?>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-times me-1"></i> Out of Stock
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-exclamation me-1"></i> <?php echo $stockStatus['text']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Delivery Info -->
                        <div class="delivery-info mb-3">
                            <!-- <div class="text-success small mb-1">
                                <i class="fas fa-shipping-fast me-1"></i> FREE delivery
                            </div> -->
                            <div class="text-muted small">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo rand(1, 2); ?> day delivery
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add to Cart Button -->
                    <div class="mt-auto">
                        <button class="btn btn-primary w-100 add-to-cart-btn"
                                data-product-id="<?php echo $product['id']; ?>"
                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                <?php echo $stockStatus['text'] === 'Out of Stock' ? 'disabled' : ''; ?>
                                style="padding: 0.5rem 0.75rem;">
                            <i class="fas fa-shopping-cart me-2"></i>
                            <?php echo $stockStatus['text'] === 'Out of Stock' ? 'Out of Stock' : 'Add to Cart'; ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="fas fa-crown fa-3x text-muted"></i>
            </div>
            <h5>No Best Sellers Yet</h5>
            <p class="text-muted mb-3">Products will appear here based on customer purchases</p>
            <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-primary">
                Shop All Products
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

   <!-- Categories Section -->
<div class="container mb-5">
    <div class="section-header bg-white p-3 border rounded-top mb-0">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="fw-bold mb-0">Shop by Category</h3>
            <a href="<?php echo SITE_URL; ?>categories" class="text-primary text-decoration-none">
                All categories <i class="fas fa-chevron-right ms-1"></i>
            </a>
        </div>
    </div>
    <div class="bg-white p-3 border rounded-bottom">
        <?php if (!empty($categories)): ?>
        <div class="row g-3">
            <?php foreach($categories as $category): ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="category-card text-center p-3 h-100 d-flex flex-column">
                    <a href="<?php echo SITE_URL; ?>products/category/<?php echo $category['slug'] ?? $category['id']; ?>" 
                       class="text-decoration-none text-dark flex-grow-1 d-flex flex-column">
                        <!-- Category Image -->
                        <div class="category-image mb-3 position-relative" 
                             style="height: 120px; overflow: hidden; border-radius: 8px; background: #f8f9fa;">
                            <img src="<?php echo $category['image_url'] ? SITE_URL . $category['image_url'] : SITE_URL . 'assets/images/category-placeholder.jpg'; ?>" 
                                 class="img-fluid w-100 h-100 object-fit-cover"
                                 alt="<?php echo htmlspecialchars($category['name']); ?>"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/category-placeholder.jpg';">
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                                 style="background: rgba(0,0,0,0.1);">
                            </div>
                        </div>
                        
                        <!-- Category Name -->
                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($category['name']); ?></h6>
                        
                        <!-- Product Count -->
                        <div class="mb-2">
                            <span class="badge bg-primary text-white">
                                <?php echo $category['product_count']; ?> items
                            </span>
                        </div>
                        
                        <!-- Description (if available) -->
                        <?php if (!empty($category['description'])): ?>
                            <p class="text-muted small mb-3">
                                <?php echo mb_strimwidth(htmlspecialchars($category['description']), 0, 60, '...'); ?>
                            </p>
                        <?php endif; ?>
                        
                        <!-- Shop Button -->
                        <div class="mt-auto">
                            <span class="text-primary small fw-bold">
                                Shop now <i class="fas fa-chevron-right ms-1"></i>
                            </span>
                        </div>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="fas fa-folder-open fa-3x text-muted"></i>
            </div>
            <h5>No Categories Available</h5>
            <p class="text-muted mb-3">Categories will be added soon</p>
            <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-primary">
                Browse All Products
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Add to Cart Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="cartToast" class="toast" role="alert">
        <div class="toast-header">
            <strong class="me-auto">Cart</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            Product added to cart successfully!
        </div>
    </div>
</div>

<style>
/* Amazon-style layout */
.amazon-hero-banner .carousel-item {
    height: 400px;
}

.feature-card {
    transition: transform 0.2s ease;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.product-card {
    transition: all 0.2s ease;
    height: 100%;
}

.product-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #ddd;
}

.product-image {
    background-color: #f8f9fa;
    border-radius: 4px;
}

.category-card {
    transition: transform 0.2s ease;
}

.category-card:hover {
    transform: translateY(-5px);
}

.section-header {
    border-bottom: 2px solid #ddd;
}

@media (max-width: 768px) {
    .amazon-hero-banner .carousel-item {
        height: 300px;
    }
    
    .product-card {
        margin-bottom: 1rem;
    }
}
/* Add to your existing CSS */
/* Best Sellers */
.product-card {
    transition: all 0.3s ease;
    border: 1px solid #e7e7e7 !important;
}

.product-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transform: translateY(-3px);
    border-color: #ddd !important;
}

/* Product Image Effects */
.product-card img {
    transition: transform 0.3s ease;
}

.product-card:hover img {
    transform: scale(1.05);
}

/* Badges */
.position-absolute .badge,
.position-absolute span {
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Stock Status */
.stock-status .badge {
    font-size: 0.8rem;
    padding: 0.3rem 0.6rem;
}

/* Text Truncation */
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.8rem;
    line-height: 1.4;
}

/* Categories */
.category-card {
    transition: all 0.3s ease;
    border: 1px solid #e7e7e7 !important;
}

.category-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transform: translateY(-3px);
    background-color: #f9f9f9;
}

.category-image img {
    transition: transform 0.5s ease;
}

.category-card:hover .category-image img {
    transform: scale(1.1);
}

/* Delivery Info */
.delivery-info {
    line-height: 1.4;
}

/* Add to Cart Button */
.add-to-cart-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .product-card .position-relative {
        height: 180px !important;
    }
    
    .category-image {
        height: 100px !important;
    }
    
    .text-truncate-2 {
        min-height: 2.4rem;
    }
}

@media (max-width: 576px) {
    .product-card .position-relative {
        height: 160px !important;
    }
    
    .category-card {
        margin-bottom: 1rem;
    }
}
    /* Add to your existing CSS */
.product-card {
    transition: all 0.2s ease;
}

.product-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #ddd !important;
}

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.5rem;
    line-height: 1.25;
}

.object-fit-cover {
    object-fit: cover !important;
}

.deal-timer {
    animation: pulse 2s infinite;
}
/* Responsive Product Grid System */
/* Default: 6 products per row on XL, 4 on LG, 3 on MD, 2 on SM, 1 on XS */
.row.g-3 > [class*='col-'] {
    margin-bottom: 0.75rem;
}

/* Extra Large screens (≥1200px) - 6 products per row */
.col-xl-2 {
    flex: 0 0 auto;
    width: 16.66666667%;
}

/* Large screens (992px - 1199px) - 4 products per row */
@media (min-width: 992px) and (max-width: 1199px) {
    .col-lg-4 {
        flex: 0 0 auto;
        width: 25%;
    }
}

/* Medium screens (768px - 991px) - 3 products per row */
@media (min-width: 768px) and (max-width: 991px) {
    .col-md-4 {
        flex: 0 0 auto;
        width: 33.33333333%;
    }
}

/* Small screens (576px - 767px) - 2 products per row */
@media (min-width: 576px) and (max-width: 767px) {
    .col-sm-6 {
        flex: 0 0 auto;
        width: 50%;
    }
}

/* Extra Small screens (<576px) - 2 products per row with smaller gutters */
@media (max-width: 575px) {
    .container, .container-fluid {
        padding-left: 8px;
        padding-right: 8px;
    }
    
    .row.g-3 {
        margin-left: -4px;
        margin-right: -4px;
    }
    
    .row.g-3 > [class*='col-'] {
        padding-left: 4px;
        padding-right: 4px;
        margin-bottom: 8px;
    }
    
    /* 2 products per row on mobile */
    .col-6 {
        flex: 0 0 auto;
        width: 50%;
    }
    
    /* Ensure all product cards take 50% width on mobile */
    .col-xl-2, 
    .col-lg-3, 
    .col-lg-4, 
    .col-md-4, 
    .col-md-6, 
    .col-sm-6 {
        flex: 0 0 auto;
        width: 50%;
    }
    
    /* Adjust section padding for mobile */
    .bg-white.p-3 {
        padding: 0.75rem !important;
    }
    
    .section-header {
        padding: 0.75rem !important;
    }
    
    .product-card {
        padding: 0.5rem !important;
    }
}

/* Product Card Responsive Adjustments */
@media (max-width: 767px) {
    /* Adjust product image height for mobile */
    .product-card .product-image,
    .product-card .position-relative[style*="height"] {
        height: 140px !important;
    }
    
    /* Reduce font sizes for mobile */
    .product-title {
        font-size: 0.85rem;
        margin-bottom: 0.25rem;
    }
    
    .price .fw-bold.fs-5,
    .price .fw-bold.fs-6 {
        font-size: 0.95rem !important;
    }
    
    .price small {
        font-size: 0.8rem;
    }
    
    .rating {
        font-size: 0.8rem;
        margin-bottom: 0.25rem;
    }
    
    .rating small {
        font-size: 0.75rem;
    }
    
    .stock-status .badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
    }
    
    .delivery-info,
    .delivery-info .small,
    .deal-timer {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
    }
    
    /* Adjust button sizes for mobile */
    .add-to-cart-btn {
        padding: 0.35rem 0.5rem !important;
        font-size: 0.85rem;
    }
    
    .add-to-cart-btn i {
        font-size: 0.8rem;
    }
    
    /* Adjust badges for mobile */
    .position-absolute .badge,
    .position-absolute span {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
    }
    
    /* Text truncation for mobile */
    .text-truncate-2 {
        -webkit-line-clamp: 2;
        min-height: 2.2rem;
        line-height: 1.1;
    }
}

/* For very small phones (≤375px) */
@media (max-width: 375px) {
    .container, .container-fluid {
        padding-left: 6px;
        padding-right: 6px;
    }
    
    .row.g-3 {
        margin-left: -3px;
        margin-right: -3px;
    }
    
    .row.g-3 > [class*='col-'] {
        padding-left: 3px;
        padding-right: 3px;
        margin-bottom: 6px;
    }
    
    .product-card .product-image,
    .product-card .position-relative[style*="height"] {
        height: 120px !important;
    }
    
    .product-title {
        font-size: 0.8rem;
    }
    
    .add-to-cart-btn {
        padding: 0.3rem 0.4rem !important;
        font-size: 0.8rem;
    }
    
    .text-truncate-2 {
        min-height: 2rem;
    }
}

/* Categories Section Responsive */
@media (max-width: 767px) {
    .category-card {
        padding: 0.75rem !important;
    }
    
    .category-image {
        height: 80px !important;
        margin-bottom: 0.5rem !important;
    }
    
    .category-card h6 {
        font-size: 0.85rem;
        margin-bottom: 0.25rem;
    }
    
    .category-card .badge {
        font-size: 0.7rem;
        padding: 0.15rem 0.4rem;
    }
    
    .category-card .text-primary.small {
        font-size: 0.75rem;
    }
    
    .category-card p.small {
        font-size: 0.7rem;
        margin-bottom: 0.5rem;
    }
}

/* Section Header Responsive */
@media (max-width: 767px) {
    .section-header h3 {
        font-size: 1.1rem;
    }
    
    .section-header .text-primary,
    .section-header .btn-link {
        font-size: 0.85rem;
    }
}

/* Empty State Responsive */
@media (max-width: 767px) {
    .text-center.py-5 {
        padding-top: 2rem !important;
        padding-bottom: 2rem !important;
    }
    
    .text-center .fa-3x {
        font-size: 2.5rem !important;
    }
    
    .text-center h5 {
        font-size: 1rem;
    }
    
    .text-center p {
        font-size: 0.9rem;
        margin-bottom: 0.75rem;
    }
    
    .text-center .btn {
        padding: 0.4rem 0.75rem;
        font-size: 0.85rem;
    }
}

/* Prevent text overflow on very small screens */
@media (max-width: 320px) {
    .product-title,
    .category-card h6 {
        font-size: 0.75rem;
    }
    
    .add-to-cart-btn {
        font-size: 0.75rem;
        padding: 0.25rem 0.35rem !important;
    }
    
    .price .fw-bold.fs-5,
    .price .fw-bold.fs-6 {
        font-size: 0.85rem !important;
    }
}

/* Ensure images maintain aspect ratio on all screens */
.product-card img,
.category-card img {
    object-fit: cover;
    width: 100%;
    height: 100%;
}

/* Maintain consistent card heights */
.product-card,
.category-card {
    display: flex;
    flex-direction: column;
    height: 100%;
}

/* Ensure product details take available space */
.product-details {
    flex: 1;
}

/* Add subtle hover effects for touch devices */
@media (hover: none) and (pointer: coarse) {
    .product-card:hover,
    .category-card:hover {
        transform: none !important;
    }
    
    .product-card:active,
    .category-card:active {
        background-color: #f9f9f9;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize carousel
    const carousel = new bootstrap.Carousel(document.getElementById('amazonHeroCarousel'), {
        interval: 5000
    });
    
    // Add to cart functionality
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            addToCart(productId);
        });
    });
});

function addToCart(productId) {
    fetch('<?php echo SITE_URL; ?>ajax/add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show toast
            const toast = new bootstrap.Toast(document.getElementById('cartToast'));
            toast.show();
            
            // Update cart count
            updateCartCount(data.cart_count || 0);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
        element.classList.toggle('d-none', count === 0);
    });
}
</script>