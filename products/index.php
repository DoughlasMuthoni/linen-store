<?php
// /linen-closet/products/index.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Get category from URL if present
$categorySlug = $_GET['category'] ?? null;
$subcategorySlug = $_GET['subcategory'] ?? null;
$searchQuery = $_GET['q'] ?? null;

// Initialize filter variables
$filters = [
    'category_id' => null,
    'brand_id' => $_GET['brand'] ?? null,
    'min_price' => $_GET['min_price'] ?? null,
    'max_price' => $_GET['max_price'] ?? null,
    'size' => $_GET['size'] ?? null,
    'color' => $_GET['color'] ?? null,
    'sort' => $_GET['sort'] ?? 'newest',
    'page' => max(1, $_GET['page'] ?? 1),
    'limit' => $_GET['limit'] ?? 12,
    'view' => $_GET['view'] ?? 'grid'
];

// Get category information if slug is provided
if ($categorySlug) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) as product_count
        FROM categories c 
        WHERE c.slug = ? AND c.is_active = 1
    ");
    $stmt->execute([$categorySlug]);
    $category = $stmt->fetch();
    
    if ($category) {
        $filters['category_id'] = $category['id'];
        $pageTitle = $category['name'] . " Collection";
    }
}

// Get subcategory if provided
if ($subcategorySlug) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               pc.name as parent_name,
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) as product_count
        FROM categories c
        LEFT JOIN categories pc ON c.parent_id = pc.id
        WHERE c.slug = ? AND c.is_active = 1
    ");
    $stmt->execute([$subcategorySlug]);
    $subcategory = $stmt->fetch();
    
    if ($subcategory) {
        $filters['category_id'] = $subcategory['id'];
        $pageTitle = $subcategory['name'] . " - " . $subcategory['parent_name'];
    }
}

// ====================================================================
// FETCH PRODUCTS WITH FILTERS
// ====================================================================

// Build product query
$queryParams = [];
$whereClauses = ["p.is_active = 1"];

// Category filter
if ($filters['category_id']) {
    $whereClauses[] = "p.category_id = ?";
    $queryParams[] = $filters['category_id'];
}

// Brand filter
if ($filters['brand_id']) {
    $whereClauses[] = "p.brand_id = ?";
    $queryParams[] = $filters['brand_id'];
}

// Search query
if ($searchQuery) {
    $whereClauses[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
    $pageTitle = "Search Results for: " . htmlspecialchars($searchQuery);
}

// Price range
if ($filters['min_price'] || $filters['max_price']) {
    $priceClauses = [];
    if ($filters['min_price']) {
        $priceClauses[] = "p.price >= ?";
        $queryParams[] = $filters['min_price'];
    }
    if ($filters['max_price']) {
        $priceClauses[] = "p.price <= ?";
        $queryParams[] = $filters['max_price'];
    }
    if ($priceClauses) {
        $whereClauses[] = "(" . implode(" AND ", $priceClauses) . ")";
    }
}

$whereSQL = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Sorting
$orderBy = "p.created_at DESC";
switch ($filters['sort']) {
    case 'price_low':
        $orderBy = "p.price ASC";
        break;
    case 'price_high':
        $orderBy = "p.price DESC";
        break;
    case 'popular':
        $orderBy = "p.view_count DESC";
        break;
    case 'best_selling':
        $orderBy = "(SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.product_id = p.id) DESC";
        break;
    case 'name_asc':
        $orderBy = "p.name ASC";
        break;
    case 'name_desc':
        $orderBy = "p.name DESC";
        break;
}

// Pagination
$offset = ($filters['page'] - 1) * $filters['limit'];

// Get total count for pagination
$countQuery = "
    SELECT COUNT(DISTINCT p.id) as total
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereSQL
";

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($queryParams);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $filters['limit']);

// Fetch products with images
$productsQuery = "
    SELECT 
        p.*,
        c.name as category_name,
        c.slug as category_slug,
        b.name as brand_name,
        (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
        (SELECT MIN(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as variant_min_price,
        (SELECT MAX(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as variant_max_price,
        COALESCE((SELECT MIN(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0), p.price) as display_price,
        (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_variant_stock,
        (SELECT AVG(rating) FROM product_reviews pr WHERE pr.product_id = p.id AND pr.is_approved = 1) as avg_rating,
        (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id = p.id AND pr.is_approved = 1) as review_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    $whereSQL
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
";

// Add limit and offset to params
$productParams = $queryParams;
$productParams[] = $filters['limit'];
$productParams[] = $offset;

$productsStmt = $pdo->prepare($productsQuery);
$productsStmt->execute($productParams);
$products = $productsStmt->fetchAll();

// Get all active brands for filter
$brandsStmt = $pdo->query("SELECT id, name, logo_url FROM brands WHERE is_active = 1 ORDER BY name");
$brands = $brandsStmt->fetchAll();

// Get unique sizes and colors from variants
$sizesStmt = $pdo->query("
    SELECT DISTINCT size 
    FROM product_variants 
    WHERE size IS NOT NULL AND stock_quantity > 0
    ORDER BY 
        CASE size 
            WHEN 'XS' THEN 1 
            WHEN 'S' THEN 2 
            WHEN 'M' THEN 3 
            WHEN 'L' THEN 4 
            WHEN 'XL' THEN 5 
            WHEN 'XXL' THEN 6 
            ELSE 7 
        END
");
$sizes = $sizesStmt->fetchAll(PDO::FETCH_COLUMN);

$colorsStmt = $pdo->query("
    SELECT DISTINCT color, color_code 
    FROM product_variants 
    WHERE color IS NOT NULL AND stock_quantity > 0
    ORDER BY color
");
$colors = $colorsStmt->fetchAll();


// Get categories for filter
$categoriesStmt = $pdo->query("
    SELECT id, name, slug, image_url, 
           (SELECT COUNT(*) FROM products WHERE category_id = categories.id AND is_active = 1) as product_count
    FROM categories 
    WHERE parent_id IS NULL AND is_active = 1 
    ORDER BY name
");
$filterCategories = $categoriesStmt->fetchAll();

$pageTitle = $pageTitle ?? "Shop All Products";

// Include header
require_once __DIR__ . '/../includes/header.php';

// Helper functions
function formatPrice($price) {
    return 'Ksh ' . number_format($price, 2);
}

function getStockStatus($stock) {
    if ($stock <= 0) {
        return ['class' => 'danger', 'text' => 'Out of Stock', 'icon' => 'times'];
    } elseif ($stock <= 10) {
        return ['class' => 'warning', 'text' => $stock . ' Left', 'icon' => 'exclamation'];
    } else {
        return ['class' => 'success', 'text' => 'In Stock', 'icon' => 'check'];
    }
}

function renderStars($rating) {
    $stars = '';
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    for ($i = 0; $i < $fullStars; $i++) {
        $stars .= '<i class="fas fa-star text-warning"></i>';
    }
    if ($halfStar) {
        $stars .= '<i class="fas fa-star-half-alt text-warning"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $stars .= '<i class="far fa-star text-warning"></i>';
    }
    return $stars;
}
?>

<!-- Page Loader -->
<!-- <div class="page-loader">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div> -->

<!-- Main Content -->
<div class="container-fluid px-0">
    <!-- Hero Banner -->
    <?php if (!$searchQuery && !isset($category)): ?>
    <div class="hero-banner bg-gradient-primary text-white py-5 mb-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-3">Premium Linen Collection</h1>
                    <p class="lead mb-4">Discover comfort and elegance in every thread. Shop our curated collection of premium linens.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="#featured" class="btn btn-light btn-lg px-4">
                            <i class="fas fa-star me-2"></i>Shop Featured
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
    <?php endif; ?>

    <div class="px-3 px-lg-4 py-4">
        <div class="row">
            <!-- Sidebar Filters -->
            <div class="col-lg-3 d-none d-lg-block">
                <div class="sticky-sidebar" style="top: 100px;">
                    <!-- Mobile Filter Toggle -->
                    <div class="d-lg-none mb-4">
                        <button class="btn btn-dark w-100 d-flex align-items-center justify-content-center py-3" 
                                data-bs-toggle="offcanvas" 
                                data-bs-target="#mobileFiltersOffcanvas">
                            <i class="fas fa-sliders-h me-3 fs-5"></i>
                            <span class="fw-bold">Filters & Sorting</span>
                        </button>
                    </div>

                    <!-- Desktop Filter Card -->
                    <div class="filter-card card border-0 shadow-sm mb-4 overflow-hidden">
                        <div class="card-header bg-gradient-primary text-white py-4">
                            <h5 class="fw-bold mb-0 d-flex align-items-center">
                                <i class="fas fa-filter me-3"></i>Product Filters
                            </h5>
                        </div>
                        
                        <div class="card-body">
                            <!-- Search Filter -->
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3 text-primary d-flex align-items-center">
                                    <i class="fas fa-search me-2"></i>Search
                                </h6>
                                <form method="GET" class="search-filter-form">
                                    <div class="input-group input-group-lg shadow-sm">
                                        <input type="text" 
                                               class="form-control border-primary" 
                                               name="q" 
                                               placeholder="Search products..."
                                               value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Category Filter -->
                            <?php if (!empty($filterCategories)): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3 text-primary d-flex align-items-center">
                                    <i class="fas fa-folder me-2"></i>Categories
                                    <span class="badge bg-primary ms-auto"><?php echo count($filterCategories); ?></span>
                                </h6>
                                <div class="category-filter">
                                    <?php foreach ($filterCategories as $cat): ?>
                                        <a href="?category=<?php echo $cat['slug']; ?>" 
                                           class="category-item d-flex align-items-center justify-content-between text-decoration-none mb-3 p-3 rounded-3 
                                                  <?php echo ($categorySlug == $cat['slug']) ? 'active-category bg-primary text-white' : 'bg-light'; ?>">
                                            <div class="d-flex align-items-center">
                                                <?php if (isset($cat['image_url']) && !empty($cat['image_url'])): ?>
                                                    <div class="category-img rounded-circle me-3 overflow-hidden" 
                                                        style="width: 40px; height: 40px;">
                                                        <img src="<?php echo SITE_URL . $cat['image_url']; ?>" 
                                                            alt="<?php echo htmlspecialchars($cat['name']); ?>" 
                                                            class="w-100 h-100 object-fit-cover">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="category-icon rounded-circle bg-white text-primary d-flex align-items-center justify-content-center me-3" 
                                                        style="width: 40px; height: 40px;">
                                                        <i class="fas fa-box"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($cat['name']); ?></div>
                                                    <small class="text-muted"><?php echo $cat['product_count']; ?> products</small>
                                                </div>
                                            </div>
                                            <i class="fas fa-chevron-right opacity-50"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            
                           <!-- Price Range Filter -->
                           
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3 text-primary d-flex align-items-center">
                                    <i class="fas fa-tag me-2"></i>Price Range (Ksh)
                                </h6>
                                <div class="price-filter bg-light p-3 rounded-3">
                                    <div class="price-slider mb-3" id="priceSlider"></div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <small class="text-muted" id="minPriceDisplay">Ksh <?php echo $filters['min_price'] ?: 0; ?></small>
                                        <small class="text-muted" id="maxPriceDisplay">Ksh <?php echo $filters['max_price'] ?: 5000; ?></small>
                                    </div>
                                    <button class="btn btn-primary w-100 mt-3" id="applyPriceFilter">
                                        <i class="fas fa-check me-2"></i>Apply Price Filter
                                    </button>
                                </div>
                            </div>
                            <!-- Size Filter -->
                            <?php if (!empty($sizes)): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3 text-primary d-flex align-items-center">
                                    <i class="fas fa-ruler me-2"></i>Size
                                </h6>
                                <div class="size-filter d-flex flex-wrap gap-2">
                                    <?php foreach ($sizes as $size): ?>
                                        <button type="button" 
                                                class="btn size-btn btn-outline-primary rounded-pill px-3 
                                                       <?php echo ($filters['size'] === $size) ? 'active bg-primary text-white' : ''; ?>"
                                                data-size="<?php echo htmlspecialchars($size); ?>">
                                            <?php echo htmlspecialchars($size); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Color Filter -->
                            <?php if (!empty($colors)): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3 text-primary d-flex align-items-center">
                                    <i class="fas fa-palette me-2"></i>Color
                                </h6>
                                <div class="color-filter d-flex flex-wrap gap-2">
                                    <?php foreach ($colors as $color): ?>
                                        <button type="button" 
                                                class="btn color-btn p-0 rounded-circle position-relative
                                                       <?php echo ($filters['color'] === $color['color']) ? 'active' : ''; ?>"
                                                data-color="<?php echo htmlspecialchars($color['color']); ?>"
                                                style="width: 36px; height: 36px; background-color: <?php echo htmlspecialchars($color['color_code']); ?>;"
                                                title="<?php echo htmlspecialchars($color['color']); ?>">
                                            <?php if ($filters['color'] === $color['color']): ?>
                                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-check text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Brand Filter -->
                            <?php if (!empty($brands)): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3 text-primary d-flex align-items-center">
                                    <i class="fas fa-copyright me-2"></i>Brands
                                </h6>
                                <div class="brand-filter">
                                    <?php foreach ($brands as $brand): ?>
                                        <div class="form-check brand-check-item mb-2 p-2 rounded-3 hover-bg-light">
                                            <input class="form-check-input brand-checkbox" 
                                                   type="checkbox" 
                                                   value="<?php echo $brand['id']; ?>"
                                                   id="brand-<?php echo $brand['id']; ?>"
                                                   <?php echo ($filters['brand_id'] == $brand['id']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label d-flex align-items-center w-100" 
                                                   for="brand-<?php echo $brand['id']; ?>">
                                                <?php if ($brand['logo_url']): ?>
                                                    <img src="<?php echo SITE_URL . $brand['logo_url']; ?>" 
                                                         alt="<?php echo htmlspecialchars($brand['name']); ?>"
                                                         class="me-3 rounded" 
                                                         style="width: 30px; height: 30px; object-fit: contain;">
                                                <?php endif; ?>
                                                <span class="fw-medium"><?php echo htmlspecialchars($brand['name']); ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Clear Filters Button -->
                            <button type="button" class="btn btn-outline-primary w-100 py-3" id="clear-filters">
                                <i class="fas fa-times me-2"></i> Clear All Filters
                            </button>
                        </div>
                    </div>

                    <!-- Featured Categories -->
                    <?php if (isset($category) && isset($category['image_url']) && $category['image_url']): ?>
                        <div class="card border-0 shadow-sm overflow-hidden">
                            <div class="card-header bg-gradient-primary text-white py-3">
                                <h5 class="fw-bold mb-0 d-flex align-items-center">
                                    <i class="fas fa-fire me-2"></i>Featured in <?php echo htmlspecialchars($category['name']); ?>
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="position-relative">
                                    <img src="<?php echo SITE_URL . $category['image_url']; ?>" 
                                        alt="<?php echo htmlspecialchars($category['name']); ?>" 
                                        class="img-fluid w-100" 
                                        style="height: 200px; object-fit: cover;">
                                    <div class="position-absolute bottom-0 start-0 w-100 p-3" 
                                        style="background: linear-gradient(transparent, rgba(0,0,0,0.7));">
                                        <a href="?category=<?php echo $categorySlug; ?>" 
                                        class="text-white text-decoration-none d-flex align-items-center">
                                            <span class="fw-bold">View All <?php echo isset($category['product_count']) ? $category['product_count'] : 0; ?> Products</span>
                                            <i class="fas fa-arrow-right ms-2"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="col-lg-9">
            
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb bg-white p-3 rounded-3 shadow-sm">
                        <li class="breadcrumb-item">
                            <a href="<?php echo SITE_URL; ?>" class="text-decoration-none text-primary">
                                <i class="fas fa-home me-2"></i>Home
                            </a>
                        </li>
                        <?php if (isset($category)): ?>
                            <li class="breadcrumb-item">
                                <a href="<?php echo SITE_URL; ?>products" class="text-decoration-none text-primary">
                                    <i class="fas fa-store me-1"></i>Shop
                                </a>
                            </li>
                            <li class="breadcrumb-item active text-dark fw-bold" aria-current="page">
                                <?php echo isset($category['name']) ? htmlspecialchars($category['name']) : 'Category'; ?>
                                <?php if (isset($category['product_count'])): ?>
                                    <span class="badge bg-primary ms-2"><?php echo $category['product_count']; ?></span>
                                <?php endif; ?>
                            </li>
                        <?php elseif (isset($subcategory)): ?>
                            <li class="breadcrumb-item">
                                <a href="<?php echo SITE_URL; ?>products" class="text-decoration-none text-primary">
                                    Shop
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="?category=<?php echo $categorySlug; ?>" class="text-decoration-none text-primary">
                                    <?php echo isset($subcategory['parent_name']) ? htmlspecialchars($subcategory['parent_name']) : 'Category'; ?>
                                </a>
                            </li>
                            <li class="breadcrumb-item active text-dark fw-bold" aria-current="page">
                                <?php echo isset($subcategory['name']) ? htmlspecialchars($subcategory['name']) : 'Subcategory'; ?>
                            </li>
                        <?php elseif ($searchQuery): ?>
                            <li class="breadcrumb-item">
                                <a href="<?php echo SITE_URL; ?>products" class="text-decoration-none text-primary">
                                    Shop
                                </a>
                            </li>
                            <li class="breadcrumb-item active text-dark fw-bold" aria-current="page">
                                <i class="fas fa-search me-1"></i>Search: "<?php echo htmlspecialchars($searchQuery); ?>"
                            </li>
                        <?php else: ?>
                            <li class="breadcrumb-item active text-dark fw-bold" aria-current="page">
                                <i class="fas fa-store me-2"></i>All Products
                            </li>
                        <?php endif; ?>
                    </ol>
                </nav>
                
                <!-- Page Header -->
                <div class="row mb-4 align-items-end">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-3">
                            <h1 class="display-5 fw-bold mb-0 text-gradient-primary"><?php echo $pageTitle; ?></h1>
                            <?php if (isset($category) && isset($category['product_count'])): ?>
                                <span class="badge bg-primary ms-3 px-3 py-2 fs-6">
                                    <?php echo $category['product_count']; ?> Products
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="lead text-muted mb-0">
                            <?php if ($searchQuery): ?>
                                Found <span class="fw-bold text-primary"><?php echo $totalProducts; ?></span> 
                                product<?php echo $totalProducts != 1 ? 's' : ''; ?> matching your search
                            <?php elseif (isset($category)): ?>
                                <?php echo htmlspecialchars($category['description'] ?? 'Explore our premium collection of high-quality linens'); ?>
                            <?php else: ?>
                                Discover comfort and elegance in our curated linen collection
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <!-- Active Filters -->
                        <?php 
                        $activeFilters = [];
                        if ($searchQuery) $activeFilters[] = [
                            'label' => "Search: \"$searchQuery\"",
                            'key' => 'q',
                            'value' => $searchQuery
                        ];
                        if ($filters['brand_id']) {
                            $brandName = array_column($brands, 'name', 'id')[$filters['brand_id']] ?? '';
                            $activeFilters[] = [
                                'label' => "Brand: $brandName",
                                'key' => 'brand',
                                'value' => $filters['brand_id']
                            ];
                        }
                        if ($filters['size']) $activeFilters[] = [
                            'label' => "Size: {$filters['size']}",
                            'key' => 'size',
                            'value' => $filters['size']
                        ];
                        if ($filters['color']) $activeFilters[] = [
                            'label' => "Color: {$filters['color']}",
                            'key' => 'color',
                            'value' => $filters['color']
                        ];
                        if ($filters['min_price'] || $filters['max_price']) {
                            $priceRange = [];
                            if ($filters['min_price']) $priceRange[] = formatPrice($filters['min_price']);
                            if ($filters['max_price']) $priceRange[] = formatPrice($filters['max_price']);
                            $activeFilters[] = [
                                'label' => "Price: " . implode(' - ', $priceRange),
                                'key' => 'price',
                                'value' => 'price'
                            ];
                        }
                        
                        if (!empty($activeFilters)): ?>
                            <div class="active-filters">
                                <h6 class="text-muted mb-2">Active Filters:</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($activeFilters as $filter): ?>
                                        <div class="badge bg-primary bg-opacity-10 text-primary border border-primary d-flex align-items-center px-3 py-2">
                                            <i class="fas fa-filter me-2"></i>
                                            <?php echo $filter['label']; ?>
                                            <button type="button" 
                                                    class="btn-close btn-close-primary ms-2" 
                                                    style="font-size: 0.6rem;"
                                                    data-filter-key="<?php echo $filter['key']; ?>"
                                                    data-filter-value="<?php echo $filter['value']; ?>"></button>
                                        </div>
                                    <?php endforeach; ?>
                                    <a href="?" class="badge bg-danger text-white text-decoration-none d-flex align-items-center px-3 py-2">
                                        Clear All <i class="fas fa-times ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Products Controls -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="me-4">
                                <span class="text-muted">
                                    <i class="fas fa-box-open me-2"></i>
                                    Showing <span class="fw-bold text-primary"><?php echo count($products); ?></span> 
                                    of <span class="fw-bold"><?php echo $totalProducts; ?></span> products
                                </span>
                            </div>
                            <!-- Mobile Filter Toggle -->
                            <button class="btn btn-outline-primary d-lg-none d-flex align-items-center" 
                                    data-bs-toggle="offcanvas" 
                                    data-bs-target="#mobileFiltersOffcanvas">
                                <i class="fas fa-sliders-h me-2"></i> Filters
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end gap-3">
                            <!-- View Toggle -->
                            <div class="btn-group shadow-sm" role="group">
                                <button type="button" 
                                        class="btn btn-outline-primary <?php echo $filters['view'] === 'grid' ? 'active bg-primary text-white' : ''; ?>" 
                                        data-view="grid"
                                        title="Grid View">
                                    <i class="fas fa-th"></i>
                                </button>
                                <button type="button" 
                                        class="btn btn-outline-primary <?php echo $filters['view'] === 'list' ? 'active bg-primary text-white' : ''; ?>" 
                                        data-view="list"
                                        title="List View">
                                    <i class="fas fa-list"></i>
                                </button>
                                <button type="button" 
                                        class="btn btn-outline-primary <?php echo $filters['view'] === 'compact' ? 'active bg-primary text-white' : ''; ?>" 
                                        data-view="compact"
                                        title="Compact View">
                                    <i class="fas fa-th-large"></i>
                                </button>
                            </div>
                            
                            <!-- Sort Dropdown -->
                            <div class="dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle d-flex align-items-center" 
                                        type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-sort-amount-down me-2"></i>
                                    <span class="sort-label fw-medium">
                                        <?php 
                                        $sortOptions = [
                                            'newest' => 'Newest',
                                            'popular' => 'Most Popular',
                                            'best_selling' => 'Best Selling',
                                            'price_low' => 'Price: Low to High',
                                            'price_high' => 'Price: High to Low',
                                            'name_asc' => 'Name: A-Z',
                                            'name_desc' => 'Name: Z-A'
                                        ];
                                        echo $sortOptions[$filters['sort']] ?? 'Newest';
                                        ?>
                                    </span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                                    <li><h6 class="dropdown-header text-primary fw-bold">Sort Products</h6></li>
                                    <?php foreach ($sortOptions as $value => $label): ?>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center sort-option 
                                                      <?php echo $filters['sort'] === $value ? 'active bg-primary text-white' : ''; ?>" 
                                               href="#" 
                                               data-sort="<?php echo $value; ?>">
                                                <?php if ($filters['sort'] === $value): ?>
                                                    <i class="fas fa-check me-2"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-sort me-2 opacity-50"></i>
                                                <?php endif; ?>
                                                <?php echo $label; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <!-- Products Per Page -->
                            <div class="dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle d-flex align-items-center" 
                                        type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-list-ol me-2"></i>
                                    <span><?php echo $filters['limit']; ?> per page</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                                    <li><h6 class="dropdown-header text-primary fw-bold">Items Per Page</h6></li>
                                    <?php foreach ([12, 24, 36, 48] as $limit): ?>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center limit-option 
                                                      <?php echo $filters['limit'] == $limit ? 'active bg-primary text-white' : ''; ?>" 
                                               href="#" 
                                               data-limit="<?php echo $limit; ?>">
                                                <?php if ($filters['limit'] == $limit): ?>
                                                    <i class="fas fa-check me-2"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-square me-2 opacity-25"></i>
                                                <?php endif; ?>
                                                <?php echo $limit; ?> Products
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Products Grid/List Container -->
                <div id="products-container" class="w-100 <?php echo $filters['view'] === 'list' ? 'products-list-view' : ($filters['view'] === 'compact' ? 'row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3' : 'row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4'); ?>">
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $index => $product): ?>
                            <?php
                            // Determine display price
                            $displayPrice = $product['display_price'] ?? $product['price'];
                            $hasVariants = !empty($product['variant_min_price']) && !empty($product['variant_max_price']);
                            
                            if ($hasVariants && $product['variant_min_price'] != $product['variant_max_price']) {
                                $priceDisplay = formatPrice($product['variant_min_price']) . " - " . formatPrice($product['variant_max_price']);
                            } else {
                                $priceDisplay = formatPrice($displayPrice);
                            }
                            
                            // Check stock status
                            $totalStock = $product['total_variant_stock'] ?? $product['stock_quantity'] ?? 0;
                            $stockStatus = getStockStatus($totalStock);
                            
                            // Product URL
                            $productUrl = SITE_URL . 'products/detail.php?slug=' . $product['slug'];
                            $imageUrl = SITE_URL . ($product['primary_image'] ?: 'assets/images/placeholder.jpg');
                            
                            // Rating
                            $avgRating = $product['avg_rating'] ?? 0;
                            $reviewCount = $product['review_count'] ?? 0;
                            
                            // Calculate discount percentage if compare price exists
                            $discountPercent = 0;
                            if (isset($product['compare_price']) && $product['compare_price'] > $product['price']) {
                                $discountPercent = round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100);
                            }
                            ?>
                            
                            <?php if ($filters['view'] === 'list'): ?>
                                <!-- List View Card -->
                                <div class="product-card card mb-4 border-0 shadow-sm hover-lift-lg overflow-hidden">
                                    <div class="row g-0">
                                        <div class="col-md-4 position-relative">
                                            <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                                <div class="position-relative overflow-hidden rounded-start" style="height: 280px;">
                                                    <img src="<?php echo $imageUrl; ?>" 
                                                         class="img-fluid h-100 w-100 object-fit-cover product-image" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         loading="lazy"
                                                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                                                    
                                                    <!-- Quick View Overlay -->
                                                    <div class="product-overlay position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-0 d-flex align-items-center justify-content-center transition-all">
                                                        <button class="btn btn-primary rounded-pill px-4 py-2 quick-view-btn shadow-lg" 
                                                                data-product-id="<?php echo $product['id']; ?>">
                                                            <i class="fas fa-eye me-2"></i>Quick View
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Badges -->
                                                    <div class="position-absolute top-0 start-0 m-3">
                                                        <?php if ($stockStatus['text'] === 'Out of Stock'): ?>
                                                            <span class="badge bg-danger px-3 py-2">
                                                                <i class="fas fa-times me-1"></i>Out of Stock
                                                            </span>
                                                        <?php elseif ($discountPercent > 0): ?>
                                                            <span class="badge bg-warning text-dark px-3 py-2">
                                                                <i class="fas fa-bolt me-1"></i>-<?php echo $discountPercent; ?>%
                                                            </span>
                                                        <?php elseif (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                                                            <span class="badge bg-success px-3 py-2">
                                                                <i class="fas fa-star me-1"></i>New
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="card-body h-100 d-flex flex-column p-4">
                                                <!-- Category & Brand -->
                                                <div class="mb-3">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <?php if (!empty($product['brand_name'])): ?>
                                                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                                                <?php echo htmlspecialchars($product['brand_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($product['category_name'])): ?>
                                                            <span class="text-muted">
                                                                <i class="fas fa-folder me-1"></i>
                                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- Product Title -->
                                                <h3 class="card-title mb-2">
                                                    <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-dark fw-bold fs-4">
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </a>
                                                </h3>
                                                
                                                <!-- Rating -->
                                                <?php if ($reviewCount > 0): ?>
                                                <div class="mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-2">
                                                            <?php echo renderStars($avgRating); ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            (<?php echo $reviewCount; ?> review<?php echo $reviewCount != 1 ? 's' : ''; ?>)
                                                        </small>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <!-- Product Description -->
                                                <p class="card-text text-muted mb-4 flex-grow-1">
                                                    <?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 200)); ?>...
                                                    <a href="<?php echo $productUrl; ?>" class="text-primary text-decoration-none">
                                                        Read more
                                                    </a>
                                                </p>
                                                
                                                <!-- Stock Status -->
                                                <div class="mb-4">
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-<?php echo $stockStatus['class']; ?> bg-opacity-10 text-<?php echo $stockStatus['class']; ?> border border-<?php echo $stockStatus['class']; ?> px-3 py-2">
                                                            <i class="fas fa-<?php echo $stockStatus['icon']; ?> me-2"></i>
                                                            <?php echo $stockStatus['text']; ?>
                                                        </span>
                                                        <?php if ($stockStatus['text'] !== 'Out of Stock' && $totalStock > 0): ?>
                                                            <small class="text-muted ms-3">
                                                                <?php echo $totalStock; ?> units available
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- Price & Actions -->
                                                <div class="mt-auto">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="d-flex align-items-center mb-1">
                                                                <h3 class="text-primary fw-bold mb-0"><?php echo $priceDisplay; ?></h3>
                                                                <?php if (isset($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                                                                    <small class="text-muted text-decoration-line-through ms-3 fs-6">
                                                                        <?php echo formatPrice($product['compare_price']); ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if ($hasVariants): ?>
                                                                <small class="text-muted d-block">Multiple options available</small>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="d-flex gap-2">
                                                        
                                                            <button type="button" 
                                                                    class="btn btn-outline-primary btn-lg rounded-pill add-to-wishlist"
                                                                    data-product-id="<?php echo $product['id']; ?>"
                                                                    title="Add to Wishlist">
                                                                <i class="far fa-heart"></i>
                                                            </button>
                                                            <button type="button" 
                                                                    class="btn btn-primary btn-lg rounded-pill px-4 add-to-cart-btn"
                                                                    data-product-id="<?php echo $product['id']; ?>"
                                                                    <?php echo $stockStatus['text'] === 'Out of Stock' ? 'disabled' : ''; ?>>
                                                                <i class="fas fa-shopping-cart me-2"></i>
                                                                Add to Cart
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <!-- Grid View Card -->
                                <div class="col">
                                    <div class="card h-100 product-card border-0 shadow-sm hover-lift overflow-hidden position-relative">
                                        <!-- Product Image -->
                                        <div class="position-relative overflow-hidden" style="height: <?php echo $filters['view'] === 'compact' ? '200px' : '300px'; ?>;">
                                            <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                                <img src="<?php echo $imageUrl; ?>" 
                                                     class="card-img-top h-100 w-100 object-fit-cover product-image transition-all" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     loading="lazy"
                                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                                                <!-- Quick View Overlay -->
                                                <div class="product-overlay position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-0 d-flex align-items-center justify-content-center transition-all">
                                                    <button class="btn btn-primary rounded-pill px-4 py-2 quick-view-btn shadow-lg" 
                                                            data-product-id="<?php echo $product['id']; ?>">
                                                        <i class="fas fa-eye me-2"></i>Quick View
                                                    </button>
                                                </div>
                                            </a>
                                            
                                            <!-- Badges -->
                                            <div class="position-absolute top-0 start-0 m-3">
                                                <?php if ($stockStatus['text'] === 'Out of Stock'): ?>
                                                    <span class="badge bg-danger px-3 py-2">
                                                        <i class="fas fa-times me-1"></i>Out of Stock
                                                    </span>
                                                <?php elseif ($discountPercent > 0): ?>
                                                    <span class="badge bg-warning text-dark px-3 py-2">
                                                        <i class="fas fa-bolt me-1"></i>-<?php echo $discountPercent; ?>%
                                                    </span>
                                                <?php elseif (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                                                    <span class="badge bg-success px-3 py-2">
                                                        <i class="fas fa-star me-1"></i>New
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Quick Actions -->
                                            <div class="position-absolute top-0 end-0 m-3 d-flex flex-column gap-2">
                                                <button type="button" 
                                                        class="btn btn-light btn-sm rounded-circle shadow-sm add-to-wishlist hover-scale"
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        title="Add to Wishlist">
                                                    <i class="far fa-heart"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-light btn-sm rounded-circle shadow-sm compare-btn hover-scale"
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        title="Compare">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Card Body -->
                                        <div class="card-body d-flex flex-column p-<?php echo $filters['view'] === 'compact' ? '3' : '4'; ?>">
                                            <!-- Category & Brand -->
                                            <div class="mb-2">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <?php if (!empty($product['brand_name'])): ?>
                                                        <small class="text-primary fw-medium">
                                                            <i class="fas fa-tag me-1"></i>
                                                            <?php echo htmlspecialchars($product['brand_name']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if ($avgRating > 0): ?>
                                                        <small class="text-warning">
                                                            <?php echo renderStars($avgRating); ?>
                                                            <?php if ($reviewCount > 0): ?>
                                                                <small class="text-muted">(<?php echo $reviewCount; ?>)</small>
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Product Title -->
                                            <h6 class="card-title fw-bold mb-2 <?php echo $filters['view'] === 'compact' ? 'fs-6' : 'fs-5'; ?>">
                                                <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-dark text-truncate-2">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </a>
                                            </h6>
                                            
                                            <!-- Stock Status -->
                                            <div class="mb-3">
                                                <span class="badge bg-<?php echo $stockStatus['class']; ?> bg-opacity-10 text-<?php echo $stockStatus['class']; ?> border border-<?php echo $stockStatus['class']; ?>">
                                                    <i class="fas fa-<?php echo $stockStatus['icon']; ?> me-1"></i>
                                                    <?php echo $stockStatus['text']; ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Price -->
                                            <div class="mt-auto">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div>
                                                        <div class="d-flex align-items-center">
                                                            <h5 class="text-primary fw-bold mb-0 <?php echo $filters['view'] === 'compact' ? 'fs-5' : 'fs-4'; ?>">
                                                                <?php echo $priceDisplay; ?>
                                                            </h5>
                                                            <?php if (isset($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                                                                <small class="text-muted text-decoration-line-through ms-2 <?php echo $filters['view'] === 'compact' ? 'fs-7' : 'fs-6'; ?>">
                                                                    <?php echo formatPrice($product['compare_price']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($hasVariants): ?>
                                                            <small class="text-muted">From <?php echo formatPrice($product['variant_min_price']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- Add to Cart Button -->
                                                <div class="d-grid">
                                                  <button type="button" 
                                                        class="btn btn-primary w-100 add-to-cart-btn rounded-pill"
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        <?php echo $stockStatus['text'] === 'Out of Stock' ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-shopping-cart me-2"></i>
                                                    <?php echo $stockStatus['text'] === 'Out of Stock' ? 'Out of Stock' : 'Add to Cart'; ?>
                                                </button>
                                                </div>
                                                
                                            
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- No Products Found -->
                        <div class="col-12 text-center py-5">
                            <div class="py-5">
                                <div class="empty-state-icon mb-4">
                                    <i class="fas fa-search fa-4x text-primary opacity-25"></i>
                                </div>
                                <h3 class="fw-bold mb-3 text-gradient-primary">No Products Found</h3>
                                <p class="text-muted mb-4 lead">We couldn't find any products matching your criteria</p>
                                <div class="d-flex justify-content-center gap-3">
                                    <a href="<?php echo SITE_URL; ?>products" class="btn btn-primary btn-lg px-4 py-3">
                                        <i class="fas fa-redo me-2"></i> Clear All Filters
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>products/new" class="btn btn-outline-primary btn-lg px-4 py-3">
                                        <i class="fas fa-star me-2"></i> View New Arrivals
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Product pagination" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <!-- First Page -->
                            <li class="page-item <?php echo $filters['page'] <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link rounded-start" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                                   aria-label="First">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            
                            <!-- Previous Page -->
                            <li class="page-item <?php echo $filters['page'] <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $filters['page'] - 1])); ?>"
                                   aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php 
                            $startPage = max(1, $filters['page'] - 2);
                            $endPage = min($totalPages, $filters['page'] + 2);
                            
                            if ($startPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $filters['page'] == $i ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">
                                        <?php echo $totalPages; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Next Page -->
                            <li class="page-item <?php echo $filters['page'] >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $filters['page'] + 1])); ?>"
                                   aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            
                            <!-- Last Page -->
                            <li class="page-item <?php echo $filters['page'] >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link rounded-end" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"
                                   aria-label="Last">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                        <div class="text-center mt-3 text-muted">
                            Page <?php echo $filters['page']; ?> of <?php echo $totalPages; ?>
                        </div>
                    </nav>
                <?php endif; ?>
                
                <!-- Load More Button (Alternative to pagination) -->
                <?php if ($filters['page'] < $totalPages): ?>
                    <div class="text-center mt-5">
                        <button class="btn btn-outline-primary btn-lg px-5 py-3 rounded-pill shadow-sm" 
                                id="load-more-btn" 
                                data-page="<?php echo $filters['page']; ?>">
                            <span class="d-flex align-items-center justify-content-center">
                                <i class="fas fa-spinner fa-spin d-none me-3"></i>
                                Load More Products
                                <i class="fas fa-arrow-down ms-3"></i>
                            </span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Bottom CTA Section -->
                <?php if (!isset($category) && !$searchQuery): ?>
                <div class="row mt-5">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 bg-gradient-primary text-white shadow-lg overflow-hidden">
                            <div class="card-body p-5">
                                <h3 class="fw-bold mb-3">Need Help Choosing?</h3>
                                <p class="mb-4">Our linen experts are here to help you find the perfect products for your needs.</p>
                                <a href="<?php echo SITE_URL; ?>contact" class="btn btn-light btn-lg px-4">
                                    <i class="fas fa-headset me-2"></i>Contact Our Experts
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 bg-light shadow-sm">
                            <div class="card-body p-5">
                                <h3 class="fw-bold mb-3 text-primary">Subscribe for Updates</h3>
                                <p class="mb-4">Get notified about new arrivals, exclusive offers, and styling tips.</p>
                                <form class="d-flex gap-2">
                                    <input type="email" class="form-control form-control-lg" placeholder="Your email address">
                                    <button class="btn btn-primary btn-lg px-4" type="submit">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Filters Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileFiltersOffcanvas">
    <div class="offcanvas-header border-bottom bg-gradient-primary text-white">
        <h5 class="offcanvas-title fw-bold">
            <i class="fas fa-sliders-h me-2"></i>Filters & Sorting
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div id="mobile-filters-content">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
    <div class="offcanvas-footer border-top p-3 bg-light">
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-primary btn-lg py-3" id="apply-mobile-filters">
                <i class="fas fa-check me-2"></i>Apply Filters
            </button>
            <button type="button" class="btn btn-outline-primary btn-lg py-3" id="clear-mobile-filters">
                <i class="fas fa-times me-2"></i>Clear All
            </button>
        </div>
    </div>
</div>

<!-- Quick View Modal -->
<div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-eye me-2"></i>Quick View
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="quickViewContent">
                    <!-- Content loaded via API -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Compare Products Sidebar -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="compareSidebar">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold">
            <i class="fas fa-exchange-alt me-2"></i>Compare Products
            <span class="badge bg-primary ms-2" id="compare-count">0</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div id="compare-products-list">
            <!-- Products will load here -->
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading compare products...</p>
            </div>
        </div>
        <div class="mt-4 d-grid gap-2">
            <button class="btn btn-primary" id="compare-now-btn" disabled>
                <i class="fas fa-chart-bar me-2"></i>Compare Now
            </button>
            <button class="btn btn-outline-danger" id="clear-compare-btn">
                <i class="fas fa-trash me-2"></i>Clear All
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-primary text-white">
            <i class="fas fa-check-circle me-2"></i>
            <strong class="me-auto">Success</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            <span id="toastMessage">Product added to cart!</span>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<button type="button" class="btn btn-primary btn-floating shadow-lg" id="back-to-top">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Floating Wishlist Button -->
<button type="button" class="btn btn-danger btn-floating shadow-lg" id="wishlist-floating-btn">
    <i class="fas fa-heart"></i>
    <span class="wishlist-count badge bg-white text-danger">0</span>
</button>

<!-- Include noUiSlider CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.css">

<style>
/* Custom CSS Variables */
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

/* General Styles */
.text-gradient-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
}

.hover-lift {
    transition: all 0.3s ease;
}

.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
}

.hover-lift-lg:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.2) !important;
}

.hover-scale {
    transition: transform 0.2s ease;
}

.hover-scale:hover {
    transform: scale(1.1);
}

.transition-all {
    transition: all 0.3s ease;
}

/* Product Cards */
.product-card {
    border-radius: 16px !important;
    overflow: hidden;
    position: relative;
}

.product-card .product-overlay {
    transition: all 0.3s ease;
    opacity: 0;
    background: transparent !important;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.product-card .product-image {
    transition: transform 0.8s ease;
}

.product-card:hover .product-image {
    transform: scale(1.05);
}
/* Fix price range slider label overlap */
.price-filter {
    position: relative;
    z-index: 1;
}
/* Price slider styles */
.price-slider {
    margin: 20px 0;
}

.noUi-connect {
    background: var(--primary-color);
}

.noUi-handle {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid var(--primary-color);
    background: white;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.noUi-handle:before,
.noUi-handle:after {
    display: none;
}

.price-display {
    font-size: 0.875rem;
    color: var(--primary-color);
    font-weight: 500;
}
.noUi-tooltip {
    font-size: 0.75rem !important;
    padding: 2px 6px !important;
    bottom: -25px !important;
    background: var(--primary-color) !important;
    color: white !important;
    border-radius: 4px !important;
}

.noUi-horizontal .noUi-tooltip {
    transform: translate(-50%, 0) !important;
}

/* Adjust slider container spacing */
.price-slider {
    margin: 30px 0 15px 0 !important;
    padding: 0 15px !important;
}

/* Ensure tooltips don't overlap with other elements */
.noUi-target {
    margin-top: 10px;
    margin-bottom: 25px;
}
/* Badges */
.badge {
    border-radius: 50px;
    font-weight: 500;
}

/* Category Items */
.category-item {
    transition: all 0.3s ease;
}

.category-item:hover {
    transform: translateX(10px);
}

.active-category {
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
}

/* Reduce Add to Cart button size */
.add-to-cart-btn {
    padding: 0.5rem 1rem !important;
    font-size: 0.875rem !important;
}

/* For larger screens, adjust slightly */
@media (min-width: 768px) {
    .add-to-cart-btn {
        padding: 0.6rem 1.2rem !important;
    }
}

/* Specifically for grid view buttons */
#products-container .add-to-cart-btn {
    padding: 0.4rem 0.8rem !important;
    font-size: 0.8rem !important;
}

/* For list view, keep slightly larger */
.products-list-view .add-to-cart-btn {
    padding: 0.5rem 1rem !important;
    font-size: 0.875rem !important;
}

/* Price Slider */
#priceSlider .noUi-connect {
    background: var(--primary-color);
}

#priceSlider .noUi-handle {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 3px solid var(--primary-color);
    background: white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    cursor: pointer;
}

#priceSlider .noUi-handle:before,
#priceSlider .no-ui-handle:after {
    display: none;
}

/* Color Filter Buttons */
.color-btn {
    border: 3px solid transparent;
    transition: all 0.3s ease;
}

.color-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 0 0 2px var(--primary-color);
}

.color-btn.active {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px var(--primary-color);
}

/* Size Filter Buttons */
.size-btn {
    transition: all 0.3s ease;
    border-width: 2px;
}

.size-btn:hover {
    transform: translateY(-2px);
}

.size-btn.active {
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
}

/* Pagination */
.page-link {
    border: none;
    margin: 0 2px;
    border-radius: 8px !important;
    color: var(--dark-color);
    padding: 0.5rem 1rem;
}

.page-item.active .page-link {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border: none;
}

.page-link:hover {
    background-color: var(--primary-light);
    color: var(--primary-color);
}

/* Loading States */
.page-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    transition: opacity 0.3s ease;
}

.page-loader.fade-out {
    opacity: 0;
    pointer-events: none;
}

/* Hero Banner */
.hero-banner {
    position: relative;
    overflow: hidden;
}

.hero-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,186.7C384,213,480,235,576,213.3C672,192,768,128,864,128C960,128,1056,192,1152,208C1248,224,1344,192,1392,176L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
    background-size: cover;
    background-position: bottom;
}

/* Full width adjustments */
.container-fluid.px-0 {
    overflow-x: hidden;
}

/* Remove padding from main content row */
.row.g-0 {
    margin: 0;
}

/* Adjust sidebar to full height */
.sticky-sidebar {
    padding-right: 1rem;
}

/* Ensure product grid uses full width */
#products-container.w-100 {
    margin-left: -0.5rem;
    margin-right: -0.5rem;
}

/* Adjust card spacing */
.product-card {
    margin-bottom: 1.5rem;
}

/* Text Utilities */
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.text-truncate-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Object Fit */
.object-fit-cover {
    object-fit: cover;
}

.object-fit-contain {
    object-fit: contain;
}

/* Floating Buttons */
.btn-floating {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    z-index: 100;
    border: none;
    transition: all 0.3s ease;
}

#back-to-top {
    bottom: 100px;
    background: var(--primary-color);
    color: white;
}

#back-to-top:hover {
    transform: translateY(-5px);
    background: var(--secondary-color);
}

#wishlist-floating-btn {
    right: 30px;
    bottom: 30px;
    background: var(--danger-color);
    color: white;
}

#wishlist-floating-btn:hover {
    transform: translateY(-5px);
    background: #d32f2f;
}

#wishlist-floating-btn .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 20px;
    height: 20px;
    padding: 2px 6px;
    font-size: 0.75rem;
}

/* Compare Sidebar */
.compare-sidebar .compare-product-item {
    border: 2px solid var(--primary-light);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.compare-sidebar .compare-product-item:hover {
    border-color: var(--primary-color);
    transform: translateX(-5px);
}

.compare-sidebar .remove-compare {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    background: var(--danger-color);
    color: white;
    border: none;
    font-size: 0.8rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.compare-sidebar .compare-product-item:hover .remove-compare {
    opacity: 1;
}

/* Empty State */
.empty-state-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-light), #fff);
    border-radius: 50%;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .display-5 {
        font-size: 2rem;
    }
    
    .hero-banner {
        padding: 3rem 0 !important;
    }
    
    .product-card .card-body {
        padding: 1rem !important;
    }
    
    .btn-floating {
        width: 50px;
        height: 50px;
        font-size: 1rem;
        bottom: 20px;
        right: 20px;
    }
    
    #back-to-top {
        bottom: 80px;
    }
}

@media (max-width: 576px) {
    .row-cols-2 > * {
        flex: 0 0 auto;
        width: 50%;
    }
}

/* Animation for product loading */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.product-card {
    animation: fadeInUp 0.5s ease;
    animation-fill-mode: both;
}

.product-card:nth-child(1) { animation-delay: 0.1s; }
.product-card:nth-child(2) { animation-delay: 0.2s; }
.product-card:nth-child(3) { animation-delay: 0.3s; }
.product-card:nth-child(4) { animation-delay: 0.4s; }
.product-card:nth-child(5) { animation-delay: 0.5s; }
.product-card:nth-child(6) { animation-delay: 0.6s; }
.product-card:nth-child(7) { animation-delay: 0.7s; }
.product-card:nth-child(8) { animation-delay: 0.8s; }

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 10px;
}

::-webkit-scrollbar-track {
    background: var(--light-color);
}

::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 5px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--secondary-color);
}
</style>

<!-- Include JavaScript Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    setupBackToTop();
    // Initialize page loader
    // setTimeout(() => {
    //     document.querySelector('.page-loader')?.classList.add('fade-out');
    //     setTimeout(() => {
    //         document.querySelector('.page-loader')?.style.display = 'none';
    //     }, 300);
    // }, 500);
    
    // REMOVED DUPLICATE WISHLIST EVENT LISTENERS HERE
    
   // Initialize components
    initializePriceSlider();
    setupEventListeners();
    initializeWishlist();
    initializeCompare();
    setupBackToTop();
    loadMobileFilters();
    
    // Load initial wishlist count AND status
    loadWishlistCount();
    checkWishlistStatus();
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Initialize Price Slider
function initializePriceSlider() {
    const priceSlider = document.getElementById('priceSlider');
    if (!priceSlider) return;
    
    const minPrice = <?php echo $filters['min_price'] ?: 0; ?>;
    const maxPrice = <?php echo $filters['max_price'] ?: 5000; ?>;
    
    noUiSlider.create(priceSlider, {
        start: [minPrice, maxPrice],
        connect: true,
        range: {
            'min': 0,
            'max': 10000
        },
        step: 100,
        format: {
            to: value => Math.round(value),
            from: value => value
        }
    });
    
    // Update display labels when slider changes
    priceSlider.noUiSlider.on('update', function(values) {
        const minValue = Math.round(values[0]);
        const maxValue = Math.round(values[1]);
        
        // Update display labels
        document.getElementById('minPriceDisplay').textContent = 'Ksh ' + minValue.toLocaleString();
        document.getElementById('maxPriceDisplay').textContent = 'Ksh ' + maxValue.toLocaleString();
        
        // Store values in hidden inputs or data attributes
        priceSlider.dataset.minPrice = minValue;
        priceSlider.dataset.maxPrice = maxValue;
    });
    
    // Apply price filter button
    document.getElementById('applyPriceFilter')?.addEventListener('click', function() {
        const minValue = priceSlider.dataset.minPrice || 0;
        const maxValue = priceSlider.dataset.maxPrice || 5000;
        
        if (minValue > 0 || maxValue < 10000) {
            const params = new URLSearchParams(window.location.search);
            if (minValue > 0) params.set('min_price', minValue);
            if (maxValue < 10000) params.set('max_price', maxValue);
            params.set('page', 1);
            window.location.href = window.location.pathname + '?' + params.toString();
        }
    });
}

// Setup Event Listeners
function setupEventListeners() {
    // View toggle buttons
    document.querySelectorAll('[data-view]').forEach(button => {
        button.addEventListener('click', function() {
            const view = this.dataset.view;
            updateURLParameter('view', view);
        });
    });
    
    // Sort options
    document.querySelectorAll('.sort-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            const sort = this.dataset.sort;
            updateURLParameter('sort', sort);
        });
    });
    
    // Limit options
    document.querySelectorAll('.limit-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            const limit = this.dataset.limit;
            updateURLParameter('limit', limit);
        });
    });
    
    // Size buttons
    document.querySelectorAll('.size-btn').forEach(button => {
        button.addEventListener('click', function() {
            const size = this.dataset.size;
            updateURLParameter('size', size);
        });
    });
    
    // Color buttons
    document.querySelectorAll('.color-btn').forEach(button => {
        button.addEventListener('click', function() {
            const color = this.dataset.color;
            updateURLParameter('color', color);
        });
    });
    
    // Brand checkboxes
    document.querySelectorAll('.brand-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const brandId = this.value;
            updateURLParameter('brand', brandId);
        });
    });
    
    // Apply price filter
    document.getElementById('applyPriceFilter')?.addEventListener('click', function() {
        const minPrice = document.getElementById('minPriceInput').value;
        const maxPrice = document.getElementById('maxPriceInput').value;
        
        if (minPrice || maxPrice) {
            const params = new URLSearchParams(window.location.search);
            if (minPrice) params.set('min_price', minPrice);
            if (maxPrice) params.set('max_price', maxPrice);
            params.set('page', 1);
            window.location.href = window.location.pathname + '?' + params.toString();
        }
    });
    
    // Clear filters
    document.getElementById('clear-filters')?.addEventListener('click', function() {
        window.location.href = '<?php echo SITE_URL; ?>products';
    });
    
    // Clear individual filters
    document.querySelectorAll('.btn-close[data-filter-key]').forEach(button => {
        button.addEventListener('click', function() {
            const key = this.dataset.filterKey;
            const value = this.dataset.filterValue;
            removeFilter(key, value);
        });
    });
    
    // Quick view buttons
    document.querySelectorAll('.quick-view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            loadQuickView(productId);
        });
    });
    
   // Add to cart buttons
document.querySelectorAll('.add-to-cart-btn').forEach(button => {
    button.addEventListener('click', async function() {
        const productId = this.dataset.productId;
        const button = this;
        const originalText = button.innerHTML;
        
        // Add loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
        button.disabled = true;
        
        try {
            await addToCart(productId, 1);
        } catch (error) {
            console.error('Add to cart error:', error);
            // You might want to show an error message here
        } finally {
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
});
    
    // Load more button
    document.getElementById('load-more-btn')?.addEventListener('click', loadMoreProducts);
    
    // Wishlist floating button
    document.getElementById('wishlist-floating-btn')?.addEventListener('click', function() {
        window.location.href = '<?php echo SITE_URL; ?>account/wishlist.php';
    });
}

// Initialize Wishlist - ONLY PLACE WHERE WISHLIST EVENT LISTENERS ARE ADDED
function initializeWishlist() {
    // Add wishlist event listeners - THIS IS THE ONLY PLACE
    document.querySelectorAll('.add-to-wishlist').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productId = this.dataset.productId;
            toggleWishlist(productId, this);
        });
    });
    
    // Load initial wishlist count
    loadWishlistCount();
}

// ULTRA SIMPLE COMPARE FEATURE

// Initialize compare on page load
document.addEventListener('DOMContentLoaded', function() {
    initCompare();
});

function initCompare() {
    console.log('Initializing compare feature...');
    
    // Load existing products
    let compareProducts = [];
    try {
        const stored = localStorage.getItem('compareProducts');
        if (stored) {
            compareProducts = JSON.parse(stored);
        }
    } catch (e) {
        console.error('Error loading compare products:', e);
        localStorage.removeItem('compareProducts');
    }
    
    console.log('Loaded products:', compareProducts);
    
    // Update count
    updateCompareCount(compareProducts.length);
    
    // Render products
    renderSimpleCompare(compareProducts);
    
    // Setup compare button clicks
    setupCompareButtons();
    
    // Setup sidebar buttons
    document.getElementById('compare-now-btn')?.addEventListener('click', function() {
        if (compareProducts.length >= 2) {
            const ids = compareProducts.map(p => p.id);
            window.location.href = `<?php echo SITE_URL; ?>products/compare.php?ids=${encodeURIComponent(JSON.stringify(ids))}`;
        } else {
            alert('Please select at least 2 products to compare');
        }
    });
    
    document.getElementById('clear-compare-btn')?.addEventListener('click', function() {
        if (confirm('Clear all products from comparison?')) {
            localStorage.removeItem('compareProducts');
            compareProducts = [];
            updateCompareCount(0);
            renderSimpleCompare([]);
            
            // Reset all compare buttons
            document.querySelectorAll('.compare-btn').forEach(btn => {
                btn.querySelector('i').className = 'fas fa-exchange-alt';
            });
            
            alert('All products removed from comparison');
        }
    });
}

function setupCompareButtons() {
    document.querySelectorAll('.compare-btn').forEach(button => {
        // Remove any existing listeners
        button.onclick = null;
        
        // Add new listener
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            const productId = this.dataset.productId;
            const productCard = this.closest('.product-card, .card');
            
            // Get product info
            const productName = productCard.querySelector('.card-title, h5, h6')?.textContent?.trim() || 'Product';
            const productPrice = productCard.querySelector('.text-primary, .text-dark')?.textContent?.trim() || 'Ksh 0.00';
            const productImage = productCard.querySelector('img')?.src || '<?php echo SITE_URL; ?>assets/images/placeholder.jpg';
            
            // Load current products
            let compareProducts = [];
            try {
                const stored = localStorage.getItem('compareProducts');
                if (stored) {
                    compareProducts = JSON.parse(stored);
                }
            } catch (e) {
                compareProducts = [];
            }
            
            // Check if product already exists
            const existingIndex = compareProducts.findIndex(p => p.id == productId);
            
            if (existingIndex > -1) {
                // Remove product
                compareProducts.splice(existingIndex, 1);
                this.querySelector('i').className = 'fas fa-exchange-alt';
                alert('Removed from compare');
            } else {
                // Add product (max 4)
                if (compareProducts.length >= 4) {
                    alert('Maximum 4 products can be compared');
                    return;
                }
                
                compareProducts.push({
                    id: productId,
                    name: productName,
                    price: productPrice,
                    image: productImage
                });
                
                this.querySelector('i').className = 'fas fa-exchange-alt text-primary';
                alert('Added to compare');
            }
            
            // Save to localStorage
            localStorage.setItem('compareProducts', JSON.stringify(compareProducts));
            
            // Update UI
            updateCompareCount(compareProducts.length);
            renderSimpleCompare(compareProducts);
            
            // Open sidebar if not already open
            if (compareProducts.length > 0) {
                const sidebar = document.getElementById('compareSidebar');
                if (sidebar && !sidebar.classList.contains('show')) {
                    const bsSidebar = new bootstrap.Offcanvas(sidebar);
                    bsSidebar.show();
                }
            }
        });
    });
}

function renderSimpleCompare(products) {
    const container = document.getElementById('compare-products-list');
    if (!container) {
        console.error('Compare container not found!');
        return;
    }
    
    if (!products || products.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-2">No products added to compare</p>
                <small class="text-muted">Add products by clicking the compare button</small>
            </div>
        `;
        return;
    }
    
    let html = '';
    products.forEach(product => {
        html += `
            <div class="compare-item border rounded p-3 mb-3">
                <div class="d-flex align-items-center">
                    <img src="${product.image}" 
                         alt="${product.name}" 
                         class="rounded me-3" 
                         style="width: 60px; height: 60px; object-fit: cover;">
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">${product.name}</h6>
                        <p class="text-primary mb-0">${product.price}</p>
                    </div>
                    <button class="btn btn-sm btn-danger" 
                            onclick="removeFromCompare(${product.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateCompareCount(count) {
    const countElement = document.getElementById('compare-count');
    if (countElement) {
        countElement.textContent = count;
    }
    
    const compareBtn = document.getElementById('compare-now-btn');
    if (compareBtn) {
        compareBtn.disabled = count < 2;
    }
}

// Global function for remove buttons
window.removeFromCompare = function(productId) {
    let compareProducts = [];
    try {
        const stored = localStorage.getItem('compareProducts');
        if (stored) {
            compareProducts = JSON.parse(stored);
        }
    } catch (e) {
        compareProducts = [];
    }
    
    compareProducts = compareProducts.filter(p => p.id != productId);
    localStorage.setItem('compareProducts', JSON.stringify(compareProducts));
    
    // Update compare button icon
    const compareBtn = document.querySelector(`.compare-btn[data-product-id="${productId}"]`);
    if (compareBtn) {
        compareBtn.querySelector('i').className = 'fas fa-exchange-alt';
    }
    
    updateCompareCount(compareProducts.length);
    renderSimpleCompare(compareProducts);
    
    alert('Product removed from comparison');
};

// Setup Back to Top Button
function setupBackToTop() {
    const backToTopBtn = document.getElementById('back-to-top');
    if (!backToTopBtn) return;
    
    // Function to check scroll position
    function checkScrollPosition() {
        // Use modern scrollY or fallback to pageYOffset
        const scrollPosition = window.scrollY || window.pageYOffset;
        
        if (scrollPosition > 300) {
            backToTopBtn.style.opacity = '1';
            backToTopBtn.style.visibility = 'visible';
            backToTopBtn.style.transform = 'translateY(0)';
        } else {
            backToTopBtn.style.opacity = '0';
            backToTopBtn.style.visibility = 'hidden';
            backToTopBtn.style.transform = 'translateY(20px)';
        }
    }
    
    // Check initial position
    checkScrollPosition();
    
    // Throttle scroll event for better performance
    let scrollTimeout;
    window.addEventListener('scroll', function() {
        if (scrollTimeout) {
            window.cancelAnimationFrame(scrollTimeout);
        }
        
        scrollTimeout = window.requestAnimationFrame(checkScrollPosition);
    });
    
    // Smooth scroll to top
    backToTopBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Disable button during scroll
        backToTopBtn.disabled = true;
        backToTopBtn.style.cursor = 'wait';
        
        // Smooth scroll to top
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
        
        // Re-enable button after scroll completes
        setTimeout(() => {
            backToTopBtn.disabled = false;
            backToTopBtn.style.cursor = 'pointer';
        }, 1000);
    });
    
    // Add keyboard support
    backToTopBtn.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.click();
        }
    });
}
// Update URL Parameter
function updateURLParameter(key, value) {
    const params = new URLSearchParams(window.location.search);
    if (value) {
        params.set(key, value);
    } else {
        params.delete(key);
    }
    params.set('page', 1); // Reset to first page on filter change
    window.location.href = window.location.pathname + '?' + params.toString();
}

// Remove Filter
function removeFilter(key, value) {
    const params = new URLSearchParams(window.location.search);
    params.delete(key);
    params.set('page', 1);
    window.location.href = window.location.pathname + '?' + params.toString();
}

// Load Mobile Filters
function loadMobileFilters() {
    const mobileFiltersContent = document.getElementById('mobile-filters-content');
    if (!mobileFiltersContent) return;
    
    // Clone desktop filters for mobile
    const desktopFilters = document.querySelector('.sticky-sidebar').cloneNode(true);
    mobileFiltersContent.appendChild(desktopFilters);
}

// Toggle Wishlist - SIMPLE VERSION
// Toggle Wishlist - UPDATED WITH LOGIN HANDLING
async function toggleWishlist(productId, buttonElement) {
    const icon = buttonElement.querySelector('i');
    const isCurrentlyInWishlist = icon.classList.contains('fas') && icon.classList.contains('text-danger');
    
    // Store original state
    const originalHTML = buttonElement.innerHTML;
    const originalTitle = buttonElement.title;
    
    // Show loading
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    buttonElement.disabled = true;
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/toggle-wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                product_id: productId,
                action: isCurrentlyInWishlist ? 'remove' : 'add'
            })
        });
        
        // Check response
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Update button
            if (data.in_wishlist) {
                buttonElement.innerHTML = '<i class="fas fa-heart text-danger"></i>';
                buttonElement.title = 'Remove from Wishlist';
                showToast(data.message, 'success');
            } else {
                buttonElement.innerHTML = '<i class="far fa-heart"></i>';
                buttonElement.title = 'Add to Wishlist';
                showToast(data.message, 'info');
            }
            
            // Update wishlist count
            updateWishlistCount(data.wishlist_count);
            
        } else {
            // Check if it's a login error
            if (data.message.includes('login') || data.message.includes('Login') || 
                (!data.user_logged_in && data.message.includes('database'))) {
                
                // Store product ID for after login
                sessionStorage.setItem('wishlist_product_id', productId);
                sessionStorage.setItem('wishlist_action', isCurrentlyInWishlist ? 'remove' : 'add');
                
                // Ask user to login
                if (confirm('Please login to use wishlist. Go to login page?')) {
                    const currentUrl = encodeURIComponent(window.location.href);
                    window.location.href = `<?php echo SITE_URL; ?>auth/login?redirect=${currentUrl}&wishlist=1`;
                } else {
                    buttonElement.innerHTML = originalHTML;
                    buttonElement.title = originalTitle;
                }
            } else {
                buttonElement.innerHTML = originalHTML;
                buttonElement.title = originalTitle;
                showToast(data.message || 'Failed to update wishlist', 'error');
            }
        }
        
    } catch (error) {
        console.error('Wishlist error:', error);
        buttonElement.innerHTML = originalHTML;
        buttonElement.title = originalTitle;
        
        // Check for network or server errors
        if (error.message.includes('HTTP')) {
            showToast('Server error. Please try again.', 'error');
        } else {
            showToast('Connection error. Please check your internet.', 'error');
        }
    } finally {
        buttonElement.disabled = false;
    }
}

// Update wishlist count
function updateWishlistCount(count) {
    // Update all wishlist count elements
    document.querySelectorAll('.wishlist-count').forEach(element => {
        element.textContent = count;
        element.style.display = count > 0 ? 'inline-block' : 'none';
    });
    
    // Update floating button
    const floatingBtn = document.getElementById('wishlist-floating-btn');
    if (floatingBtn) {
        const badge = floatingBtn.querySelector('.wishlist-count');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline-block' : 'none';
        }
    }
}

// Load initial wishlist count
async function loadWishlistCount() {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/get-wishlist-count.php');
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                updateWishlistCount(data.count);
            }
        }
    } catch (error) {
        console.log('Could not load wishlist count');
    }
}

// Call on page load
document.addEventListener('DOMContentLoaded', function() {
    loadWishlistCount();
});
// Check wishlist status for all products on page
async function checkWishlistStatus() {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/get-wishlist-status.php');
        if (response.ok) {
            const data = await response.json();
            
            if (data.success && data.wishlist_items) {
                // Convert to Set for faster lookup
                const wishlistSet = new Set(data.wishlist_items.map(id => id.toString()));
                
                // Update all wishlist buttons on page
                document.querySelectorAll('.add-to-wishlist').forEach(button => {
                    const productId = button.dataset.productId;
                    const icon = button.querySelector('i');
                    
                    if (productId && wishlistSet.has(productId)) {
                        // Product is in wishlist
                        if (icon) {
                            icon.className = 'fas fa-heart text-danger';
                        }
                        button.title = 'Remove from Wishlist';
                        button.dataset.inWishlist = 'true';
                    } else {
                        // Product is not in wishlist
                        if (icon) {
                            icon.className = 'far fa-heart';
                        }
                        button.title = 'Add to Wishlist';
                        button.dataset.inWishlist = 'false';
                    }
                });
            }
        }
    } catch (error) {
        console.log('Could not load wishlist status:', error.message);
    }
}
// Load wishlist count function
async function loadWishlistCount() {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/get-wishlist-count.php');
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.count !== undefined) {
                updateWishlistCount(data.count);
            }
        }
    } catch (error) {
        console.error('Error loading wishlist count:', error);
    }
}

// Load Quick View
async function loadQuickView(productId) {
    try {
        const response = await fetch(`<?php echo SITE_URL; ?>products/api/quick-view.php?id=${productId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('quickViewContent').innerHTML = data.html;
            const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
            modal.show();
        } else {
            showToast(data.message || 'Failed to load product', 'error');
        }
    } catch (error) {
        console.error('Quick view error:', error);
        showToast('Something went wrong', 'error');
    }
}

// Add to Cart
async function addToCart(productId, quantity = 1) {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update cart count
            document.querySelectorAll('.cart-count').forEach(element => {
                element.textContent = data.cart_count;
                element.classList.remove('d-none');
            });
            
            showToast('Product added to cart successfully!', 'success');
            return true;
        } else {
            showToast(data.message || 'Failed to add to cart', 'error');
            return false;
        }
    } catch (error) {
        console.error('Add to cart error:', error);
        showToast('Something went wrong', 'error');
        return false;
    }
}

// Load More Products
async function loadMoreProducts() {
    const loadMoreBtn = document.getElementById('load-more-btn');
    if (!loadMoreBtn) return;
    
    const currentPage = parseInt(loadMoreBtn.dataset.page) || 1;
    const nextPage = currentPage + 1;
    
    // Show loading state
    loadMoreBtn.disabled = true;
    const spinner = loadMoreBtn.querySelector('.fa-spinner');
    if (spinner) spinner.classList.remove('d-none');
    
    try {
        const params = new URLSearchParams(window.location.search);
        params.set('page', nextPage);
        params.set('ajax', '1');
        
        const response = await fetch(`<?php echo SITE_URL; ?>products?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const html = await response.text();
        
        // Parse response
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newProducts = doc.querySelectorAll('#products-container > .col, #products-container > .product-card');
        const container = document.getElementById('products-container');
        
        newProducts.forEach(product => {
            product.style.animation = 'fadeInUp 0.5s ease';
            container.appendChild(product);
        });
        
        // Update button
        loadMoreBtn.dataset.page = nextPage;
        
        // Check if more pages exist
        const hasMore = doc.querySelector('#load-more-btn');
        if (!hasMore) {
            loadMoreBtn.style.display = 'none';
        }
        
        // Reinitialize event listeners for new products
        initializeWishlist();
        initializeCompare();
        
    } catch (error) {
        console.error('Load more error:', error);
        showToast('Failed to load more products', 'error');
    } finally {
        loadMoreBtn.disabled = false;
        const spinner = loadMoreBtn.querySelector('.fa-spinner');
        if (spinner) spinner.classList.add('d-none');
    }
}

// Show Toast Notification
function showToast(message, type = 'success') {
    const toastEl = document.getElementById('liveToast');
    const toastBody = document.getElementById('toastMessage');
    const toastHeader = toastEl.querySelector('.toast-header');
    
    // Update toast content
    toastBody.textContent = message;
    
    // Update toast color based on type
    const colors = {
        success: 'bg-success',
        error: 'bg-danger',
        warning: 'bg-warning',
        info: 'bg-info'
    };
    
    toastHeader.className = `toast-header text-white ${colors[type] || 'bg-primary'}`;
    toastHeader.querySelector('i').className = 
        type === 'success' ? 'fas fa-check-circle me-2' :
        type === 'error' ? 'fas fa-exclamation-circle me-2' :
        type === 'warning' ? 'fas fa-exclamation-triangle me-2' :
        'fas fa-info-circle me-2';
    
    // Show toast
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
}

// Apply Mobile Filters
document.getElementById('apply-mobile-filters')?.addEventListener('click', function() {
    const params = new URLSearchParams(window.location.search);
    
    // Get values from mobile filters
    const mobileFilters = document.getElementById('mobile-filters-content');
    
    // Price
    const minPrice = mobileFilters.querySelector('#minPriceInput')?.value;
    const maxPrice = mobileFilters.querySelector('#maxPriceInput')?.value;
    if (minPrice) params.set('min_price', minPrice);
    if (maxPrice) params.set('max_price', maxPrice);
    
    // Size
    const activeSize = mobileFilters.querySelector('.size-btn.active');
    if (activeSize) {
        params.set('size', activeSize.dataset.size);
    } else {
        params.delete('size');
    }
    
    // Color
    const activeColor = mobileFilters.querySelector('.color-btn.active');
    if (activeColor) {
        params.set('color', activeColor.dataset.color);
    } else {
        params.delete('color');
    }
    
    // Brand
    const checkedBrand = mobileFilters.querySelector('.brand-checkbox:checked');
    if (checkedBrand) {
        params.set('brand', checkedBrand.value);
    } else {
        params.delete('brand');
    }
    
    params.set('page', 1);
    
    const offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('mobileFiltersOffcanvas'));
    offcanvas.hide();
    
    setTimeout(() => {
        window.location.href = window.location.pathname + '?' + params.toString();
    }, 300);
});

// Clear Mobile Filters
document.getElementById('clear-mobile-filters')?.addEventListener('click', function() {
    const offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('mobileFiltersOffcanvas'));
    offcanvas.hide();
    setTimeout(() => {
        window.location.href = '<?php echo SITE_URL; ?>products';
    }, 300);
});

// Product Image Lazy Loading
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                const src = img.dataset.src;
                if (src) {
                    img.src = src;
                    img.classList.add('loaded');
                }
                imageObserver.unobserve(img);
            }
        });
    });
    
    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>