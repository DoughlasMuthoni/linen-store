<?php
// /linen-closet/homepage.php

// Get database connection
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

<!-- Hero Section with Slider -->
<div class="hero-banner bg-gradient-primary text-white py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">Premium Linen Collection</h1>
                <p class="lead mb-4">Discover comfort and elegance in every thread. Shop our curated collection of premium linens.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#new-arrivals" class="btn btn-light btn-lg px-4">
                        <i class="fas fa-star me-2"></i>Shop New Arrivals
                    </a>
                    <a href="#categories" class="btn btn-outline-light btn-lg px-4">
                        <i class="fas fa-list me-2"></i>Browse Categories
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <i class="fas fa-couch fa-10x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Features Bar -->
<section class="py-4 bg-light border-bottom">
    <div class="container px-0">
        <div class="row g-4">
            <div class="col-md-3 col-sm-6">
                <div class="d-flex align-items-center">
                    <div class="feature-icon bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-truck fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Free Shipping</h6>
                        <small class="text-muted">On orders over Ksh 5,000</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="d-flex align-items-center">
                    <div class="feature-icon bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-shield-alt fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Secure Payment</h6>
                        <small class="text-muted">100% secure checkout</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="d-flex align-items-center">
                    <div class="feature-icon bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-undo fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Easy Returns</h6>
                        <small class="text-muted">30-day return policy</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="d-flex align-items-center">
                    <div class="feature-icon bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-headset fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">24/7 Support</h6>
                        <small class="text-muted">Dedicated customer service</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="py-5 py-lg-7">
    <div class="container px-0">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold mb-3">Shop by Category</h2>
            <p class="text-muted lead">Find your perfect style in our curated collections</p>
        </div>
        <div class="row g-4">
            <?php if (!empty($categories)): ?>
                <?php foreach($categories as $category): ?>
                <div class="col-md-4 col-lg-2">
                    <a href="<?php echo SITE_URL; ?>products/category/<?php echo $category['slug'] ?? $category['id']; ?>" 
                       class="category-card text-decoration-none">
                        <div class="position-relative overflow-hidden rounded-3 mb-3">
                            <div class="category-image-wrapper" style="height: 200px; overflow: hidden;">
                                <img src="<?php echo $category['image_url'] ? SITE_URL . $category['image_url'] : SITE_URL . 'assets/images/category-placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($category['name']); ?>" 
                                     class="img-fluid w-100 h-100 object-fit-cover category-image">
                            </div>
                            <div class="category-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                <div class="text-center text-light">
                                    <h3 class="h5 fw-bold mb-2"><?php echo htmlspecialchars($category['name']); ?></h3>
                                    <span class="badge bg-light text-dark"><?php echo $category['product_count']; ?> items</span>
                                </div>
                            </div>
                        </div>
                        <h4 class="h6 text-center mb-0"><?php echo htmlspecialchars($category['name']); ?></h4>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p class="text-muted">Categories coming soon!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- New Arrivals Section with Carousel -->
<section class="py-5 py-lg-7 bg-light">
    <div class="container px-0">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="display-5 fw-bold mb-2">New Arrivals</h2>
                <p class="text-muted">Fresh styles just landed</p>
            </div>
            <?php if (!empty($newArrivals)): ?>
            <a href="<?php echo SITE_URL; ?>products?sort=newest" class="btn btn-outline-primary">
                View All <i class="fas fa-arrow-right ms-2"></i>
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($newArrivals)): ?>
        <div id="newArrivalsCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php 
                $chunks = array_chunk($newArrivals, 4);
                foreach($chunks as $index => $chunk): 
                ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="row g-4">
                        <?php foreach($chunk as $product): 
                            $stockStatus = getStockStatus($product['total_variant_stock'] ?? $product['stock_quantity'] ?? 0);
                            $hasVariants = !empty($product['variant_min_price']) && !empty($product['variant_max_price']);
                            $priceDisplay = $hasVariants && $product['variant_min_price'] != $product['variant_max_price'] 
                                ? formatPrice($product['variant_min_price']) . " - " . formatPrice($product['variant_max_price'])
                                : formatPrice($product['display_price'] ?? $product['price']);
                        ?>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="card product-card h-100 border-0 shadow-sm hover-lift">
                                <!-- Product Image -->
                                <div class="position-relative overflow-hidden" style="height: 300px;">
                                    <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" class="text-decoration-none">
                                        <img src="<?php echo $product['primary_image'] ? SITE_URL . $product['primary_image'] : SITE_URL . 'assets/images/placeholder.jpg'; ?>" 
                                             class="card-img-top h-100 w-100 object-fit-cover product-image" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                                        <!-- Quick View Overlay -->
                                        <div class="product-overlay position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-10 d-flex align-items-center justify-content-center opacity-0">
                                            <button class="btn btn-primary rounded-pill px-4 quick-view-btn" 
                                                    data-product-id="<?php echo $product['id']; ?>">
                                                Quick View
                                            </button>
                                        </div>
                                    </a>
                                    
                                    <!-- Badges -->
                                    <div class="position-absolute top-0 start-0 m-3">
                                        <?php if ($stockStatus['text'] === 'Out of Stock'): ?>
                                            <span class="badge bg-secondary">Out of Stock</span>
                                        <?php endif; ?>
                                        <?php if (isset($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                                            <span class="badge bg-danger">Sale</span>
                                        <?php endif; ?>
                                        <?php if (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                                            <span class="badge bg-success">New</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="card-body d-flex flex-column p-4">
                                    <!-- Category -->
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <?php if (!empty($product['category_name'])): ?>
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <!-- Product Title -->
                                    <h6 class="card-title fw-bold mb-2">
                                        <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" 
                                           class="text-decoration-none text-dark text-truncate-2">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h6>
                                    
                                    <!-- Stock Status -->
                                    <div class="mb-3">
                                        <span class="badge bg-<?php echo $stockStatus['class']; ?>">
                                            <i class="fas fa-<?php echo $stockStatus['text'] === 'Out of Stock' ? 'times' : 'check'; ?> me-1"></i>
                                            <?php echo $stockStatus['text']; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h5 class="text-dark mb-0"><?php echo $priceDisplay; ?></h5>
                                                <?php if (isset($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                                                    <small class="text-muted text-decoration-line-through">
                                                        <?php echo formatPrice($product['compare_price']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Add to Cart Button -->
                                        <button type="button" 
                                                class="btn btn-primary w-100 add-to-cart-btn"
                                                data-product-id="<?php echo $product['id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                <?php echo $stockStatus['text'] === 'Out of Stock' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-shopping-cart me-2"></i>
                                            <?php echo $stockStatus['text'] === 'Out of Stock' ? 'Out of Stock' : 'Add to Cart'; ?>
                                        </button>
                                        
                                     
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($chunks) > 1): ?>
            <!-- Carousel Controls -->
            <button class="carousel-control-prev" type="button" data-bs-target="#newArrivalsCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#newArrivalsCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            
            <!-- Carousel Indicators -->
            <div class="carousel-indicators position-static mt-4">
                <?php for($i = 0; $i < count($chunks); $i++): ?>
                <button type="button" 
                        data-bs-target="#newArrivalsCarousel" 
                        data-bs-slide-to="<?php echo $i; ?>" 
                        class="<?php echo $i === 0 ? 'active' : ''; ?> bg-dark" 
                        style="width: 12px; height: 12px; border-radius: 50%;"></button>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
            <h5>No New Arrivals Yet</h5>
            <p class="text-muted">Check back soon for new products!</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Best Sellers Section with Carousel -->
<section class="py-5 py-lg-7">
    <div class="container px-0">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="display-5 fw-bold mb-2">Best Sellers</h2>
                <p class="text-muted">Most loved by our customers</p>
            </div>
            <?php if (!empty($bestSellers)): ?>
            <a href="<?php echo SITE_URL; ?>products?sort=best_selling" class="btn btn-outline-primary">
                View All <i class="fas fa-arrow-right ms-2"></i>
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($bestSellers)): ?>
        <div id="bestSellersCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php 
                $chunks = array_chunk($bestSellers, 4);
                foreach($chunks as $index => $chunk): 
                ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="row g-4">
                        <?php foreach($chunk as $product): 
                            $stockStatus = getStockStatus($product['total_variant_stock'] ?? $product['stock_quantity'] ?? 0);
                            $hasVariants = !empty($product['variant_min_price']) && !empty($product['variant_max_price']);
                            $priceDisplay = $hasVariants && $product['variant_min_price'] != $product['variant_max_price'] 
                                ? formatPrice($product['variant_min_price']) . " - " . formatPrice($product['variant_max_price'])
                                : formatPrice($product['display_price'] ?? $product['price']);
                        ?>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="card product-card h-100 border-0 shadow-sm hover-lift">
                                <!-- Product Image -->
                                <div class="position-relative overflow-hidden" style="height: 300px;">
                                    <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" class="text-decoration-none">
                                        <img src="<?php echo $product['primary_image'] ? SITE_URL . $product['primary_image'] : SITE_URL . 'assets/images/placeholder.jpg'; ?>" 
                                             class="card-img-top h-100 w-100 object-fit-cover product-image" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                                        <!-- Quick View Overlay -->
                                        <div class="product-overlay position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-10 d-flex align-items-center justify-content-center opacity-0">
                                            <button class="btn btn-primary rounded-pill px-4 quick-view-btn" 
                                                    data-product-id="<?php echo $product['id']; ?>">
                                                Quick View
                                            </button>
                                        </div>
                                    </a>
                                    
                                    <!-- Badges -->
                                    <div class="position-absolute top-0 start-0 m-3">
                                        <?php if ($stockStatus['text'] === 'Out of Stock'): ?>
                                            <span class="badge bg-secondary">Out of Stock</span>
                                        <?php endif; ?>
                                        <?php if (isset($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                                            <span class="badge bg-danger">Sale</span>
                                        <?php endif; ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-fire me-1"></i> Bestseller
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="card-body d-flex flex-column p-4">
                                    <!-- Category -->
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <?php if (!empty($product['category_name'])): ?>
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <!-- Product Title -->
                                    <h6 class="card-title fw-bold mb-2">
                                        <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" 
                                           class="text-decoration-none text-dark text-truncate-2">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h6>
                                    
                                    <!-- Stock Status -->
                                    <div class="mb-3">
                                        <span class="badge bg-<?php echo $stockStatus['class']; ?>">
                                            <i class="fas fa-<?php echo $stockStatus['text'] === 'Out of Stock' ? 'times' : 'check'; ?> me-1"></i>
                                            <?php echo $stockStatus['text']; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h5 class="text-dark mb-0"><?php echo $priceDisplay; ?></h5>
                                                <?php if (isset($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                                                    <small class="text-muted text-decoration-line-through">
                                                        <?php echo formatPrice($product['compare_price']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Add to Cart Button -->
                                        <button type="button" 
                                                class="btn btn-primary w-100 add-to-cart-btn"
                                                data-product-id="<?php echo $product['id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                <?php echo $stockStatus['text'] === 'Out of Stock' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-shopping-cart me-2"></i>
                                            <?php echo $stockStatus['text'] === 'Out of Stock' ? 'Out of Stock' : 'Add to Cart'; ?>
                                        </button>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($chunks) > 1): ?>
            <!-- Carousel Controls -->
            <button class="carousel-control-prev" type="button" data-bs-target="#bestSellersCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#bestSellersCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            
            <!-- Carousel Indicators -->
            <div class="carousel-indicators position-static mt-4">
                <?php for($i = 0; $i < count($chunks); $i++): ?>
                <button type="button" 
                        data-bs-target="#bestSellersCarousel" 
                        data-bs-slide-to="<?php echo $i; ?>" 
                        class="<?php echo $i === 0 ? 'active' : ''; ?> bg-dark" 
                        style="width: 12px; height: 12px; border-radius: 50%;"></button>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-star fa-3x text-muted mb-3"></i>
            <h5>No Products Yet</h5>
            <p class="text-muted">Products will appear here soon!</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Sale Products Section with Carousel -->
<section class="py-5 py-lg-7 bg-light">
    <div class="container px-0">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="display-5 fw-bold mb-2">On Sale</h2>
                <p class="text-muted">Limited time offers</p>
            </div>
            <?php if (!empty($saleProducts)): ?>
            <a href="<?php echo SITE_URL; ?>products?filter=on_sale" class="btn btn-outline-primary">
                View All <i class="fas fa-arrow-right ms-2"></i>
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($saleProducts)): ?>
        <div id="saleProductsCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php 
                $chunks = array_chunk($saleProducts, 4);
                foreach($chunks as $index => $chunk): 
                ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="row g-4">
                        <?php foreach($chunk as $product): 
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
                            <div class="card product-card h-100 border-0 shadow-sm hover-lift">
                                <!-- Product Image -->
                                <div class="position-relative overflow-hidden" style="height: 300px;">
                                    <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" class="text-decoration-none">
                                        <img src="<?php echo $product['primary_image'] ? SITE_URL . $product['primary_image'] : SITE_URL . 'assets/images/placeholder.jpg'; ?>" 
                                             class="card-img-top h-100 w-100 object-fit-cover product-image" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                                        <!-- Quick View Overlay -->
                                        <div class="product-overlay position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-10 d-flex align-items-center justify-content-center opacity-0">
                                            <button class="btn btn-primary rounded-pill px-4 quick-view-btn" 
                                                    data-product-id="<?php echo $product['id']; ?>">
                                                Quick View
                                            </button>
                                        </div>
                                    </a>
                                    
                                    <!-- Badges -->
                                    <div class="position-absolute top-0 start-0 m-3">
                                        <?php if ($discountPercent > 0): ?>
                                            <span class="badge bg-danger">-<?php echo $discountPercent; ?>%</span>
                                        <?php endif; ?>
                                        <?php if ($stockStatus['text'] === 'Out of Stock'): ?>
                                            <span class="badge bg-secondary">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="card-body d-flex flex-column p-4">
                                    <!-- Category -->
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <?php if (!empty($product['category_name'])): ?>
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <!-- Product Title -->
                                    <h6 class="card-title fw-bold mb-2">
                                        <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" 
                                           class="text-decoration-none text-dark text-truncate-2">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h6>
                                    
                                    <!-- Stock Status -->
                                    <div class="mb-3">
                                        <span class="badge bg-<?php echo $stockStatus['class']; ?>">
                                            <i class="fas fa-<?php echo $stockStatus['text'] === 'Out of Stock' ? 'times' : 'check'; ?> me-1"></i>
                                            <?php echo $stockStatus['text']; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h5 class="text-dark mb-0"><?php echo $priceDisplay; ?></h5>
                                                <?php if (isset($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                                                    <small class="text-muted text-decoration-line-through">
                                                        <?php echo formatPrice($product['compare_price']); ?>
                                                    </small>
                                                    <span class="badge bg-danger ms-1">Save <?php echo $discountPercent; ?>%</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Add to Cart Button -->
                                        <button type="button" 
                                                class="btn btn-primary w-100 add-to-cart-btn"
                                                data-product-id="<?php echo $product['id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                <?php echo $stockStatus['text'] === 'Out of Stock' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-shopping-cart me-2"></i>
                                            <?php echo $stockStatus['text'] === 'Out of Stock' ? 'Out of Stock' : 'Add to Cart'; ?>
                                        </button>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($chunks) > 1): ?>
            <!-- Carousel Controls -->
            <button class="carousel-control-prev" type="button" data-bs-target="#saleProductsCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#saleProductsCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            
            <!-- Carousel Indicators -->
            <div class="carousel-indicators position-static mt-4">
                <?php for($i = 0; $i < count($chunks); $i++): ?>
                <button type="button" 
                        data-bs-target="#saleProductsCarousel" 
                        data-bs-slide-to="<?php echo $i; ?>" 
                        class="<?php echo $i === 0 ? 'active' : ''; ?> bg-dark" 
                        style="width: 12px; height: 12px; border-radius: 50%;"></button>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
            <h5>No Sale Items Available</h5>
            <p class="text-muted">Check back for special offers!</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Sale Banner -->
<section class="py-5 py-lg-7 bg-gradient-primary text-white">
    <div class="container px-0">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0">
                <span class="badge bg-light text-primary mb-3 px-3 py-2 rounded-pill">Limited Time</span>
                <h2 class="display-4 fw-bold mb-4">Summer Sale<br>Up to 50% Off</h2>
                <p class="lead mb-5">Refresh your wardrobe with our premium linen collection at amazing prices.</p>
                
                <div class="sale-countdown mb-5">
                    <div class="d-flex align-items-center">
                        <div class="text-center me-4">
                            <div class="countdown-box bg-light text-dark rounded-3 d-flex align-items-center justify-content-center p-3" style="width: 80px; height: 80px;">
                                <div>
                                    <div class="fs-3 fw-bold" id="saleDays">00</div>
                                    <small>Days</small>
                                </div>
                            </div>
                        </div>
                        <div class="text-center me-4">
                            <div class="countdown-box bg-light text-dark rounded-3 d-flex align-items-center justify-content-center p-3" style="width: 80px; height: 80px;">
                                <div>
                                    <div class="fs-3 fw-bold" id="saleHours">00</div>
                                    <small>Hours</small>
                                </div>
                            </div>
                        </div>
                        <div class="text-center me-4">
                            <div class="countdown-box bg-light text-dark rounded-3 d-flex align-items-center justify-content-center p-3" style="width: 80px; height: 80px;">
                                <div>
                                    <div class="fs-3 fw-bold" id="saleMinutes">00</div>
                                    <small>Mins</small>
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="countdown-box bg-light text-dark rounded-3 d-flex align-items-center justify-content-center p-3" style="width: 80px; height: 80px;">
                                <div>
                                    <div class="fs-3 fw-bold" id="saleSeconds">00</div>
                                    <small>Secs</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <a href="<?php echo SITE_URL; ?>products?filter=on_sale" class="btn btn-light btn-lg px-5 py-3">
                    Shop Sale <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <?php if (!empty($saleProducts)): ?>
                        <?php 
                        $saleProductsPreview = array_slice($saleProducts, 0, 4);
                        foreach($saleProductsPreview as $saleProduct): 
                            $discountPercent = isset($saleProduct['compare_price']) && $saleProduct['compare_price'] > $saleProduct['price']
                                ? round((($saleProduct['compare_price'] - $saleProduct['price']) / $saleProduct['compare_price']) * 100)
                                : 0;
                        ?>
                        <div class="col-6">
                            <div class="sale-product-card position-relative overflow-hidden rounded-3">
                                <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $saleProduct['slug']; ?>">
                                    <img src="<?php echo $saleProduct['primary_image'] ? SITE_URL . $saleProduct['primary_image'] : SITE_URL . 'assets/images/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($saleProduct['name']); ?>" 
                                         class="img-fluid w-100"
                                         style="height: 250px; object-fit: cover;">
                                    <div class="sale-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-end p-3">
                                        <div class="text-light">
                                            <small class="d-block mb-1"><?php echo htmlspecialchars($saleProduct['category_name']); ?></small>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($saleProduct['name']); ?></h6>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-bold"><?php echo formatPrice($saleProduct['price']); ?></span>
                                                <?php if ($saleProduct['compare_price'] && $saleProduct['compare_price'] > $saleProduct['price']): ?>
                                                    <span class="text-light text-decoration-line-through ms-2"><?php echo formatPrice($saleProduct['compare_price']); ?></span>
                                                    <span class="badge bg-danger ms-2">
                                                        Save <?php echo $discountPercent; ?>%
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center">
                            <p class="text-light">Sale products coming soon!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="py-5 py-lg-7 bg-light">
    <div class="container px-0">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold mb-3">What Our Customers Say</h2>
            <p class="text-muted lead">Join thousands of satisfied customers</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="testimonial-card bg-white p-4 rounded-3 shadow-sm h-100">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rating-stars text-warning me-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <small class="text-muted">2 days ago</small>
                    </div>
                    <p class="mb-4">"The quality of linen is exceptional! I've purchased multiple items and each one exceeds expectations. Perfect for our Kenyan climate."</p>
                    <div class="d-flex align-items-center">
                        <div class="customer-avatar bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Sarah M.</h6>
                            <small class="text-muted">Nairobi</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="testimonial-card bg-white p-4 rounded-3 shadow-sm h-100">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rating-stars text-warning me-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <small class="text-muted">1 week ago</small>
                    </div>
                    <p class="mb-4">"Best linen products in Kenya! The fabric is breathable and durable. The customer service team was very helpful with my order."</p>
                    <div class="d-flex align-items-center">
                        <div class="customer-avatar bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">James K.</h6>
                            <small class="text-muted">Mombasa</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="testimonial-card bg-white p-4 rounded-3 shadow-sm h-100">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rating-stars text-warning me-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <small class="text-muted">3 days ago</small>
                    </div>
                    <p class="mb-4">"Excellent quality and fast delivery. The linen curtains I bought transformed my living room. Will definitely shop here again!"</p>
                    <div class="d-flex align-items-center">
                        <div class="customer-avatar bg-dark text-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Grace W.</h6>
                            <small class="text-muted">Kisumu</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
<section class="py-5 py-lg-7">
    <div class="container px-0">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="newsletter-card bg-gradient-primary  text-light p-5 rounded-4 text-center">
                    <i class="fas fa-envelope-open-text fa-3x mb-4"></i>
                    <h2 class="display-5 fw-bold mb-3">Stay in the Loop</h2>
                    <p class="lead mb-4">Subscribe to our newsletter for exclusive offers, style tips, and new arrivals.</p>
                    <form id="newsletter-form" class="row g-3 justify-content-center">
                        <div class="col-md-8">
                            <div class="input-group input-group-lg">
                                <input type="email" 
                                       class="form-control" 
                                       placeholder="Enter your email" 
                                       required
                                       style="height: 60px;">
                                <button class="btn btn-light" type="submit" style="height: 60px;">
                                    Subscribe
                                </button>
                            </div>
                            <div class="form-text text-light mt-2">We respect your privacy. Unsubscribe at any time.</div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick View Modal (Same as products page) -->
<div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-body p-0">
                <div id="quickViewContent">
                    <!-- Content loaded via API -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add to Cart Success Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="cartSuccessToast" class="toast align-items-center text-white border-0" role="alert">
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
    --primary-color: #4a64d6ff;
    --primary-light: #eef2ff;
    --secondary-color: #3a0ca3;
    --accent-color: #f72585;
    --dark-color: #1a1a2e;
    --light-color: #f8f9fa;
    --success-color: #4cc9f0;
    --warning-color: #f8961e;
    --danger-color: #f94144;
}  
/* Homepage Specific Styles */
.hero-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.carousel-item {
    transition: transform 0.6s ease-in-out;
}

.hero-image {
    object-fit: cover;
    object-position: center;
}

.feature-icon {
    transition: transform 0.3s ease, background-color 0.3s ease;
}

.feature-icon:hover {
    transform: scale(1.1);
    background-color: #495057 !important;
}

.category-card {
    transition: transform 0.3s ease;
}

.category-card:hover {
    transform: translateY(-10px);
}

.category-image {
    transition: transform 0.5s ease;
}

.category-card:hover .category-image {
    transform: scale(1.1);
}

.category-overlay {
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.category-card:hover .category-overlay {
    opacity: 1;
}

/* Categories Section Styles */
.category-image-wrapper {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
}
.text-gradient-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
}

/* Responsive adjustments for categories */
@media (max-width: 992px) {
    .col-md-4.col-lg-2 {
        flex: 0 0 auto;
        width: 33.33333333%;
    }
}

@media (max-width: 768px) {
    .col-md-4.col-lg-2 {
        flex: 0 0 auto;
        width: 50%;
    }
}

@media (max-width: 576px) {
    .col-md-4.col-lg-2 {
        flex: 0 0 auto;
        width: 50%;
    }
}

.hover-lift {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.hover-lift:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.1) !important;
}

.product-overlay {
    transition: opacity 0.3s ease;
    background: linear-gradient(transparent, rgba(0,0,0,0.2));
}

.product-card:hover .product-overlay {
    opacity: 1 !important;
}

.product-image {
    transition: transform 0.5s ease;
}

.product-card:hover .product-image {
    transform: scale(1.05);
}

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.countdown-box {
    transition: transform 0.3s ease;
}

.countdown-box:hover {
    transform: scale(1.05);
}

.sale-product-card {
    transition: transform 0.3s ease;
}

.sale-product-card:hover {
    transform: scale(1.02);
}

.sale-overlay {
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
}

.testimonial-card {
    transition: transform 0.3s ease;
}

.testimonial-card:hover {
    transform: translateY(-5px);
}

.rating-stars {
    font-size: 0.9rem;
}

.customer-avatar {
    font-size: 1rem;
}

.newsletter-card {
    background: linear-gradient(135deg, #343a40 0%, #212529 100%);
}

/* Product Carousel Styles */
.carousel-control-prev,
.carousel-control-next {
    width: 50px;
    height: 50px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.8;
}

.carousel-control-prev:hover,
.carousel-control-next:hover {
    opacity: 1;
}

.carousel-control-prev {
    left: -25px;
}

.carousel-control-next {
    right: -25px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .hero-content {
        padding: 2rem !important;
    }
    
    .display-3 {
        font-size: 2rem;
    }
    
    .display-4 {
        font-size: 1.75rem;
    }
    
    .display-5 {
        font-size: 1.5rem;
    }
    
    .hero-image {
        height: 50vh !important;
    }
    
    .category-image-wrapper {
        height: 150px !important;
    }
    
    .product-card .product-image {
        height: 200px !important;
    }
    
    .carousel-control-prev,
    .carousel-control-next {
        width: 40px;
        height: 40px;
    }
    
    .carousel-control-prev {
        left: -15px;
    }
    
    .carousel-control-next {
        right: -15px;
    }
}

@media (max-width: 576px) {
    .row-cols-2 > * {
        flex: 0 0 auto;
        width: 50%;
    }
    
    .carousel-control-prev,
    .carousel-control-next {
        width: 35px;
        height: 35px;
    }
    
    .carousel-control-prev {
        left: -10px;
    }
    
    .carousel-control-next {
        right: -10px;
    }
}
</style>

<script>
// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sale countdown
    initializeSaleCountdown();
    
    // Setup event listeners
    setupHomepageEventListeners();
    
    // Initialize carousels
    const heroCarousel = new bootstrap.Carousel(document.getElementById('heroCarousel'), {
        interval: 5000,
        ride: 'carousel'
    });
    
    // Initialize product carousels
    initializeProductCarousels();
});

// Initialize Product Carousels
function initializeProductCarousels() {
    const carousels = [
        'newArrivalsCarousel',
        'bestSellersCarousel', 
        'saleProductsCarousel'
    ];
    
    carousels.forEach(carouselId => {
        const carouselElement = document.getElementById(carouselId);
        if (carouselElement) {
            new bootstrap.Carousel(carouselElement, {
                interval: 6000,
                wrap: true,
                touch: true
            });
        }
    });
}

// Sale Countdown Timer
function initializeSaleCountdown() {
    function updateSaleCountdown() {
        // Set sale end date (7 days from now)
        const countdownDate = new Date();
        countdownDate.setDate(countdownDate.getDate() + 7);
        
        const now = new Date().getTime();
        const distance = countdownDate - now;
        
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        if (document.getElementById('saleDays')) {
            document.getElementById('saleDays').textContent = days.toString().padStart(2, '0');
            document.getElementById('saleHours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('saleMinutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('saleSeconds').textContent = seconds.toString().padStart(2, '0');
        }
        
        // Update hero countdown if exists
        if (document.getElementById('heroCountdown')) {
            document.getElementById('heroCountdown').innerHTML = `
                <div class="countdown-item">
                    <div class="countdown-value">${days.toString().padStart(2, '0')}</div>
                    <div class="countdown-label">Days</div>
                </div>
                <div class="countdown-separator">:</div>
                <div class="countdown-item">
                    <div class="countdown-value">${hours.toString().padStart(2, '0')}</div>
                    <div class="countdown-label">Hours</div>
                </div>
                <div class="countdown-separator">:</div>
                <div class="countdown-item">
                    <div class="countdown-value">${minutes.toString().padStart(2, '0')}</div>
                    <div class="countdown-label">Mins</div>
                </div>
                <div class="countdown-separator">:</div>
                <div class="countdown-item">
                    <div class="countdown-value">${seconds.toString().padStart(2, '0')}</div>
                    <div class="countdown-label">Secs</div>
                </div>
            `;
        }
        
        if (distance < 0) {
            clearInterval(countdownInterval);
            document.querySelectorAll('.countdown-box').forEach(box => {
                box.innerHTML = '<div class="text-center">Sale<br>Ended</div>';
            });
        }
    }
    
    // Update immediately and every second
    updateSaleCountdown();
    const countdownInterval = setInterval(updateSaleCountdown, 1000);
}

// Setup Event Listeners
function setupHomepageEventListeners() {
    // Add to cart buttons (using same function as products page)
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            addToCart(productId, 1, productName);
        });
    });
    
    // Quick view buttons
    document.querySelectorAll('.quick-view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            loadQuickView(productId);
        });
    });
    
    // Add to wishlist buttons
    document.querySelectorAll('.add-to-wishlist').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            addToWishlist(productId);
        });
    });
    
    // Newsletter form
    document.getElementById('newsletter-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const email = this.querySelector('input[type="email"]').value;
        
        // Simple validation
        if (!email || !email.includes('@')) {
            showToast('Please enter a valid email address', 'error');
            return;
        }
        
        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Subscribing...';
        submitBtn.disabled = true;
        
        // Simulate API call (replace with actual API)
        setTimeout(() => {
            showToast('Thank you for subscribing to our newsletter!', 'success');
            this.reset();
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 1500);
    });
}

// Add to Cart Function (same as products page)
async function addToCart(productId, quantity, productName = '') {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update cart count in header
            updateCartCount(data.cart_count || data.cart?.count || 0);
            
            // Show success toast
            const message = productName 
                ? `<strong>${productName}</strong> added to cart!` 
                : 'Product added to cart successfully!';
            showToast(message);
        } else {
            showToast(data.message || 'Failed to add product to cart', 'error');
        }
    } catch (error) {
        console.error('Add to cart error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Add to Wishlist
async function addToWishlist(productId) {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/add-to-wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Product added to wishlist!');
        } else {
            showToast(data.message || 'Failed to add to wishlist', 'error');
        }
    } catch (error) {
        console.error('Wishlist error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Load Quick View
// async function loadQuickView(productId) {
//     try {
//         const response = await fetch(`<?php echo SITE_URL; ?>ajax/quick-view.php?id=${productId}`);
//         const data = await response.json();
        
//         if (data.success) {
//             document.getElementById('quickViewContent').innerHTML = data.html;
            
//             // Initialize modal
//             const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
//             modal.show();
            
//             // Re-attach event listeners inside modal
//             setTimeout(() => {
//                 const modalAddToCart = document.querySelector('#quickViewContent .add-to-cart-btn');
//                 if (modalAddToCart) {
//                     modalAddToCart.addEventListener('click', function() {
//                         const modalProductId = this.dataset.productId;
//                         const modalProductName = this.dataset.productName;
//                         addToCart(modalProductId, 1, modalProductName);
                        
//                         // Close modal after adding
//                         setTimeout(() => {
//                             modal.hide();
//                         }, 1000);
//                     });
//                 }
//             }, 100);
//         } else {
//             showToast(data.message || 'Failed to load product details', 'error');
//         }
//     } catch (error) {
//         console.error('Quick view error:', error);
//         showToast('Something went wrong. Please try again.', 'error');
//     }
// }

// Update Cart Count
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
        element.classList.toggle('d-none', count === 0);
    });
}

// Show Toast Notification
function showToast(message, type = 'success') {
    const toastElement = document.getElementById('cartSuccessToast');
    const toastMessage = document.getElementById('toastMessage');
    
    if (!toastElement || !toastMessage) return;
    
    // Parse HTML if message contains HTML
    if (typeof message === 'string' && message.includes('<')) {
        toastMessage.innerHTML = message;
    } else {
        toastMessage.textContent = message;
    }
    
    // Remove all previous classes
    toastElement.className = 'toast align-items-center text-white border-0';
    
    // Add appropriate class
    if (type === 'success') {
        toastElement.classList.add('bg-success');
    } else if (type === 'error') {
        toastElement.classList.add('bg-danger');
    } else {
        toastElement.classList.add('bg-primary');
    }
    
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
}

// Hover effects for product cards
document.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        const overlay = this.querySelector('.product-overlay');
        if (overlay) overlay.style.opacity = '1';
    });
    
    card.addEventListener('mouseleave', function() {
        const overlay = this.querySelector('.product-overlay');
        if (overlay) overlay.style.opacity = '0';
    });
});
</script>