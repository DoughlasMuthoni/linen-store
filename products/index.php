<?php
// /linen-closet/products/index.php

// Solution: Use __DIR__ to get current directory and navigate up one level
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';


// Get database connection directly using singleton
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
$orderBy = "p.created_at DESC"; // default
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
        (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_variant_stock
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
$brandsStmt = $pdo->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name");
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
    SELECT id, name, slug, image_url 
    FROM categories 
    WHERE parent_id IS NULL AND is_active = 1 
    ORDER BY name
");
$filterCategories = $categoriesStmt->fetchAll();

$pageTitle = $pageTitle ?? "Shop All Products";

// Include header
require_once __DIR__ . '/../includes/header.php';

// Helper function to format price (like in homepage)
function formatPrice($price) {
    return 'Ksh ' . number_format($price, 2);
}

// Helper function to get stock status (like in homepage)
function getStockStatus($stock) {
    if ($stock <= 0) {
        return ['class' => 'danger', 'text' => 'Out of Stock'];
    } elseif ($stock <= 10) {
        return ['class' => 'warning', 'text' => $stock . ' Left'];
    } else {
        return ['class' => 'success', 'text' => 'In Stock'];
    }
}
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar Filters - Desktop -->
        <div class="col-lg-3 col-xl-2 d-none d-lg-block">
            <div class="sticky-sidebar" style="top: 100px;">
                <!-- Mobile Filter Toggle (hidden on desktop) -->
                <div class="d-lg-none mb-4">
                    <button class="btn btn-dark w-100" data-bs-toggle="offcanvas" data-bs-target="#mobileFiltersOffcanvas">
                        <i class="fas fa-filter me-2"></i> Filters & Sorting
                    </button>
                </div>

                <!-- Desktop Filter Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0"><i class="fas fa-sliders-h me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <!-- Search Filter -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Search</h6>
                            <form method="GET" class="search-filter-form">
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control form-control-sm" 
                                           name="q" 
                                           placeholder="Search products..."
                                           value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                    <button class="btn btn-dark btn-sm" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Category Filter -->
                        <?php if (!empty($filterCategories)): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Categories</h6>
                            <div class="category-filter">
                                <?php foreach ($filterCategories as $cat): ?>
                                    <a href="?category=<?php echo $cat['slug']; ?>" 
                                       class="d-flex align-items-center justify-content-between text-decoration-none text-dark mb-2 py-2 px-3 rounded <?php echo ($categorySlug == $cat['slug']) ? 'bg-light' : ''; ?>">
                                        <div class="d-flex align-items-center">
                                            <?php if ($cat['image_url']): ?>
                                                <img src="<?php echo SITE_URL . $cat['image_url']; ?>" 
                                                     alt="<?php echo htmlspecialchars($cat['name']); ?>" 
                                                     class="rounded me-2" 
                                                     style="width: 30px; height: 30px; object-fit: cover;">
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-chevron-right"></i>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Price Range Filter -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Price Range (Ksh)</h6>
                            <div class="price-slider mb-2" id="priceSlider"></div>
                            <div class="d-flex justify-content-between align-items-center">
                                <input type="number" 
                                       class="form-control form-control-sm" 
                                       id="minPriceInput" 
                                       placeholder="Min" 
                                       style="width: 45%;"
                                       min="0">
                                <span class="mx-2">-</span>
                                <input type="number" 
                                       class="form-control form-control-sm" 
                                       id="maxPriceInput" 
                                       placeholder="Max" 
                                       style="width: 45%;"
                                       min="0">
                            </div>
                            <button class="btn btn-outline-dark btn-sm w-100 mt-2" id="applyPriceFilter">
                                Apply Price
                            </button>
                        </div>

                        <!-- Size Filter -->
                        <?php if (!empty($sizes)): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Size</h6>
                            <div class="size-filter d-flex flex-wrap gap-2">
                                <?php foreach ($sizes as $size): ?>
                                    <button type="button" 
                                            class="btn btn-sm size-btn <?php echo ($filters['size'] === $size) ? 'btn-dark' : 'btn-outline-dark'; ?>"
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
                            <h6 class="fw-bold mb-3">Color</h6>
                            <div class="color-filter d-flex flex-wrap gap-2">
                                <?php foreach ($colors as $color): ?>
                                    <button type="button" 
                                            class="btn color-btn p-0 rounded-circle <?php echo ($filters['color'] === $color['color']) ? 'active' : ''; ?>"
                                            data-color="<?php echo htmlspecialchars($color['color']); ?>"
                                            style="width: 32px; height: 32px; background-color: <?php echo htmlspecialchars($color['color_code']); ?>; border: 2px solid #e0e0e0;"
                                            title="<?php echo htmlspecialchars($color['color']); ?>">
                                        <?php if ($filters['color'] === $color['color']): ?>
                                            <i class="fas fa-check text-white"></i>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Brand Filter -->
                        <?php if (!empty($brands)): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Brands</h6>
                            <div class="brand-filter">
                                <?php foreach ($brands as $brand): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input brand-checkbox" 
                                               type="checkbox" 
                                               value="<?php echo $brand['id']; ?>"
                                               id="brand-<?php echo $brand['id']; ?>"
                                               <?php echo ($filters['brand_id'] == $brand['id']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label d-flex align-items-center" for="brand-<?php echo $brand['id']; ?>">

                                            <?php echo htmlspecialchars($brand['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Stock Status Filter -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Stock Status</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input stock-checkbox" type="checkbox" value="in_stock" id="stock-in-stock">
                                <label class="form-check-label" for="stock-in-stock">
                                    <span class="badge bg-success me-1"><i class="fas fa-check"></i></span> In Stock
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input stock-checkbox" type="checkbox" value="low_stock" id="stock-low-stock">
                                <label class="form-check-label" for="stock-low-stock">
                                    <span class="badge bg-warning me-1"><i class="fas fa-exclamation"></i></span> Low Stock
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input stock-checkbox" type="checkbox" value="out_of_stock" id="stock-out-of-stock">
                                <label class="form-check-label" for="stock-out-of-stock">
                                    <span class="badge bg-danger me-1"><i class="fas fa-times"></i></span> Out of Stock
                                </label>
                            </div>
                        </div>

                        <!-- Clear Filters Button -->
                        <button type="button" class="btn btn-outline-dark w-100 mt-3" id="clear-filters">
                            <i class="fas fa-times me-2"></i> Clear All Filters
                        </button>
                    </div>
                </div>

                <!-- Featured Categories -->
                <?php if (isset($category)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0"><i class="fas fa-tags me-2"></i>Related Categories</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Fetch subcategories if this is a parent category
                        $subcategoriesStmt = $pdo->prepare("
                            SELECT c.* FROM categories c 
                            WHERE c.parent_id = ? AND c.is_active = 1 
                            ORDER BY c.name LIMIT 5
                        ");
                        // $subcategoriesStmt->execute([$category['id']]);
                        $subcategoriesStmt->execute([$category['id'] ?? 0]);
                        $subcategories = $subcategoriesStmt->fetchAll();
                        
                        if (!empty($subcategories)): ?>
                            <?php foreach ($subcategories as $subcat): ?>
                                <a href="?category=<?php echo $categorySlug; ?>&subcategory=<?php echo $subcat['slug']; ?>" 
                                   class="d-block text-decoration-none text-dark mb-2 py-2 px-3 rounded hover-bg-light">
                                    <i class="fas fa-arrow-right text-muted me-2"></i>
                                    <?php echo htmlspecialchars($subcat['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="col-lg-9 col-xl-10">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb bg-light p-3 rounded">
                    <li class="breadcrumb-item">
                        <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <?php if (isset($category)): ?>
                        <li class="breadcrumb-item">
                            <a href="<?php echo SITE_URL; ?>products" class="text-decoration-none">
                                Products
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php echo htmlspecialchars($category['name']); ?>
                            <?php if (isset($category['product_count'])): ?>
                                <span class="badge bg-dark ms-2"><?php echo $category['product_count']; ?></span>
                            <?php endif; ?>
                        </li>
                    <?php elseif (isset($subcategory)): ?>
                        <li class="breadcrumb-item">
                            <a href="<?php echo SITE_URL; ?>products" class="text-decoration-none">
                                Products
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="?category=<?php echo $categorySlug; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($subcategory['parent_name']); ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php echo htmlspecialchars($subcategory['name']); ?>
                        </li>
                    <?php elseif ($searchQuery): ?>
                        <li class="breadcrumb-item">
                            <a href="<?php echo SITE_URL; ?>products" class="text-decoration-none">
                                Products
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            Search: "<?php echo htmlspecialchars($searchQuery); ?>"
                        </li>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page">All Products</li>
                    <?php endif; ?>
                </ol>
            </nav>
            
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-2"><?php echo $pageTitle; ?></h1>
                    <p class="text-muted mb-0">
                        <?php if ($searchQuery): ?>
                            Found <?php echo $totalProducts; ?> product<?php echo $totalProducts != 1 ? 's' : ''; ?> matching your search
                        <?php elseif (isset($category)): ?>
                            <?php echo htmlspecialchars($category['description'] ?? 'Browse our collection'); ?>
                        <?php else: ?>
                            Discover our premium linen collection
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4">
                    <!-- Active Filters Display -->
                    <div class="active-filters mb-3">
                        <?php 
                        $activeFilters = [];
                        if ($searchQuery) $activeFilters[] = "Search: <strong>$searchQuery</strong>";
                        if ($filters['brand_id']) $activeFilters[] = "Brand: <strong>" . (array_column($brands, 'name', 'id')[$filters['brand_id']] ?? '') . "</strong>";
                        if ($filters['size']) $activeFilters[] = "Size: <strong>{$filters['size']}</strong>";
                        if ($filters['color']) $activeFilters[] = "Color: <strong>{$filters['color']}</strong>";
                        if ($filters['min_price'] || $filters['max_price']) {
                            $priceRange = [];
                            if ($filters['min_price']) $priceRange[] = formatPrice($filters['min_price']);
                            if ($filters['max_price']) $priceRange[] = formatPrice($filters['max_price']);
                            $activeFilters[] = "Price: <strong>" . implode(' - ', $priceRange) . "</strong>";
                        }
                        
                        if (!empty($activeFilters)): ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($activeFilters as $filter): ?>
                                    <span class="badge bg-light text-dark border d-flex align-items-center">
                                        <?php echo $filter; ?>
                                        <button type="button" class="btn-close ms-2" style="font-size: 0.7rem;"></button>
                                    </span>
                                <?php endforeach; ?>
                                <a href="?" class="badge bg-dark text-decoration-none">
                                    Clear All <i class="fas fa-times ms-1"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Products Header Controls -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <!-- Results Count -->
                    <div class="d-flex align-items-center">
                        <span class="me-3 text-muted">
                            Showing <?php echo count($products); ?> of <?php echo $totalProducts; ?> products
                        </span>
                        <!-- Mobile Filter Toggle -->
                        <button class="btn btn-outline-dark btn-sm d-lg-none" 
                                data-bs-toggle="offcanvas" 
                                data-bs-target="#mobileFiltersOffcanvas">
                            <i class="fas fa-filter me-2"></i> Filters
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end gap-3">
                        <!-- View Toggle -->
                        <div class="btn-group" role="group">
                            <button type="button" 
                                    class="btn btn-outline-dark <?php echo $filters['view'] === 'grid' ? 'active' : ''; ?>" 
                                    data-view="grid"
                                    title="Grid View">
                                <i class="fas fa-th"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-outline-dark <?php echo $filters['view'] === 'list' ? 'active' : ''; ?>" 
                                    data-view="list"
                                    title="List View">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                        
                        <!-- Sort Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-sort me-2"></i>
                                Sort: 
                                <span class="sort-label fw-medium">
                                    <?php 
                                    $sortOptions = [
                                        'newest' => 'Newest',
                                        'popular' => 'Most Popular',
                                        'best_selling' => 'Best Selling',
                                        'price_low' => 'Price: Low to High',
                                        'price_high' => 'Price: High to Low'
                                    ];
                                    echo $sortOptions[$filters['sort']] ?? 'Newest';
                                    ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">Sort Products</h6></li>
                                <li><a class="dropdown-item sort-option <?php echo $filters['sort'] === 'newest' ? 'active' : ''; ?>" 
                                       href="#" data-sort="newest">
                                    <i class="fas fa-clock me-2"></i> Newest Arrivals
                                </a></li>
                                <li><a class="dropdown-item sort-option <?php echo $filters['sort'] === 'popular' ? 'active' : ''; ?>" 
                                       href="#" data-sort="popular">
                                    <i class="fas fa-fire me-2"></i> Most Popular
                                </a></li>
                                <li><a class="dropdown-item sort-option <?php echo $filters['sort'] === 'best_selling' ? 'active' : ''; ?>" 
                                       href="#" data-sort="best_selling">
                                    <i class="fas fa-star me-2"></i> Best Selling
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item sort-option <?php echo $filters['sort'] === 'price_low' ? 'active' : ''; ?>" 
                                       href="#" data-sort="price_low">
                                    <i class="fas fa-sort-amount-down me-2"></i> Price: Low to High
                                </a></li>
                                <li><a class="dropdown-item sort-option <?php echo $filters['sort'] === 'price_high' ? 'active' : ''; ?>" 
                                       href="#" data-sort="price_high">
                                    <i class="fas fa-sort-amount-up me-2"></i> Price: High to Low
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid/List Container -->
            <div id="products-container" class="<?php echo $filters['view'] === 'list' ? 'products-list-view' : 'row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4'; ?>">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                        // Determine display price
                        $displayPrice = $product['display_price'] ?? $product['price'];
                        $hasVariants = !empty($product['variant_min_price']) && !empty($product['variant_max_price']);
                        
                        if ($hasVariants && $product['variant_min_price'] != $product['variant_max_price']) {
                            $priceDisplay = formatPrice($product['variant_min_price']) . " - " . formatPrice($product['variant_max_price']);
                        } else {
                            $priceDisplay = formatPrice($displayPrice);
                        }
                        
                        // Check stock status (using same function as homepage)
                        $totalStock = $product['total_variant_stock'] ?? $product['stock_quantity'] ?? 0;
                        $stockStatus = getStockStatus($totalStock);
                        
                        // Product URL
                        $productUrl = SITE_URL . 'products/detail.php?slug=' . $product['slug'];
                        $imageUrl = SITE_URL . ($product['primary_image'] ?: 'assets/images/placeholder.jpg');
                        ?>
                        
                        <?php if ($filters['view'] === 'list'): ?>
                            <!-- List View Card -->
                            <div class="product-card card mb-4 border-0 shadow-sm hover-lift">
                                <div class="row g-0">
                                    <div class="col-md-4 position-relative">
                                        <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                            <div class="position-relative overflow-hidden rounded-start" style="height: 250px;">
                                                <img src="<?php echo $imageUrl; ?>" 
                                                     class="img-fluid h-100 w-100 object-fit-cover product-image" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                                                <!-- Quick View Overlay -->
                                                <div class="product-overlay position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-10 d-flex align-items-center justify-content-center opacity-0">
                                                    <button class="btn btn-dark rounded-pill px-4 quick-view-btn" 
                                                            data-product-id="<?php echo $product['id']; ?>">
                                                        Quick View
                                                    </button>
                                                </div>
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
                                        </a>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="card-body h-100 d-flex flex-column p-4">
                                            <!-- Category & Brand -->
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <?php if (!empty($product['brand_name'])): ?>
                                                        <span class="me-3">
                                                            <i class="fas fa-tag me-1"></i>
                                                            <?php echo htmlspecialchars($product['brand_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($product['category_name'])): ?>
                                                        <span>
                                                            <i class="fas fa-folder me-1"></i>
                                                            <?php echo htmlspecialchars($product['category_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            
                                            <!-- Product Title -->
                                            <h5 class="card-title mb-2">
                                                <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-dark fw-bold">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </a>
                                            </h5>
                                            
                                            <!-- Product Description -->
                                            <p class="card-text text-muted mb-3 flex-grow-1">
                                                <?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 150)); ?>...
                                                <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                                    Read more
                                                </a>
                                            </p>
                                            
                                            <!-- Stock & Rating -->
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <span class="badge bg-<?php echo $stockStatus['class']; ?>">
                                                        <i class="fas fa-<?php echo $stockStatus['text'] === 'Out of Stock' ? 'times' : 'check'; ?> me-1"></i>
                                                        <?php echo $stockStatus['text']; ?>
                                                    </span>
                                                </div>
                                                <!-- Add rating here if you have it -->
                                            </div>
                                            
                                            <!-- Price & Actions -->
                                            <div class="mt-auto">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h4 class="text-dark mb-0"><?php echo $priceDisplay; ?></h4>
                                                        <?php if (isset($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                                                            <small class="text-muted text-decoration-line-through">
                                                                <?php echo formatPrice($product['compare_price']); ?>
                                                            </small>
                                                            <span class="badge bg-danger ms-2">
                                                                Save <?php echo round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100); ?>%
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="btn-group">
                                                        <button type="button" 
                                                                class="btn btn-outline-dark btn-sm quick-view-btn"
                                                                data-product-id="<?php echo $product['id']; ?>"
                                                                title="Quick View">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-outline-dark btn-sm add-to-wishlist"
                                                                data-product-id="<?php echo $product['id']; ?>"
                                                                title="Add to Wishlist">
                                                            <i class="far fa-heart"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-dark btn-sm add-to-cart-btn"
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
                                <div class="card h-100 product-card border-0 shadow-sm hover-lift">
                                    <!-- Product Image -->
                                    <div class="position-relative overflow-hidden" style="height: 300px;">
                                        <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                            <img src="<?php echo $imageUrl; ?>" 
                                                 class="card-img-top h-100 w-100 object-fit-cover product-image" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/placeholder.jpg';">
                                            <!-- Quick View Overlay -->
                                            <div class="product-overlay position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-10 d-flex align-items-center justify-content-center opacity-0">
                                                <button class="btn btn-dark rounded-pill px-4 quick-view-btn" 
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
                                        
                                        <!-- Quick Actions -->
                                        <div class="position-absolute top-0 end-0 m-3">
                                            <button type="button" 
                                                    class="btn btn-light btn-sm rounded-circle shadow-sm"
                                                    title="Add to Wishlist">
                                                <i class="far fa-heart"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Card Body -->
                                    <div class="card-body d-flex flex-column p-4">
                                        <!-- Category & Brand -->
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <?php if (!empty($product['brand_name'])): ?>
                                                    <?php echo htmlspecialchars($product['brand_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <!-- Product Title -->
                                        <h6 class="card-title fw-bold mb-2">
                                            <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-dark text-truncate-2">
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
                                                    class="btn btn-dark w-100 add-to-cart-btn"
                                                    data-product-id="<?php echo $product['id']; ?>"
                                                    <?php echo $stockStatus['text'] === 'Out of Stock' ? 'disabled' : ''; ?>>
                                                <i class="fas fa-shopping-cart me-2"></i>
                                                <?php echo $stockStatus['text'] === 'Out of Stock' ? 'Out of Stock' : 'Add to Cart'; ?>
                                            </button>
                                            
                                            <!-- Quick Actions -->
                                            <div class="d-flex justify-content-center gap-2 mt-3">
                                                <button type="button" 
                                                        class="btn btn-outline-dark btn-sm quick-view-btn"
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        title="Quick View">
                                                    <i class="fas fa-eye"></i> Quick View
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-outline-dark btn-sm"
                                                        title="Add to Wishlist">
                                                    <i class="far fa-heart"></i>
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
                            <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                            <h3 class="fw-bold mb-3">No Products Found</h3>
                            <p class="text-muted mb-4 lead">Try adjusting your filters or search criteria</p>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="<?php echo SITE_URL; ?>products" class="btn btn-dark btn-lg">
                                    <i class="fas fa-redo me-2"></i> Clear All Filters
                                </a>
                                <a href="<?php echo SITE_URL; ?>products/new" class="btn btn-outline-dark btn-lg">
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
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        
                        <!-- Previous Page -->
                        <li class="page-item <?php echo $filters['page'] <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $filters['page'] - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $filters['page'] - 2 && $i <= $filters['page'] + 2)): ?>
                                <li class="page-item <?php echo $filters['page'] == $i ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php elseif ($i == $filters['page'] - 3 || $i == $filters['page'] + 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <!-- Next Page -->
                        <li class="page-item <?php echo $filters['page'] >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $filters['page'] + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        
                        <!-- Last Page -->
                        <li class="page-item <?php echo $filters['page'] >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
            <!-- Load More Button (Alternative to pagination) -->
            <?php if ($filters['page'] < $totalPages): ?>
                <div class="text-center mt-5">
                    <button class="btn btn-outline-dark btn-lg px-5 py-3" id="load-more-btn" data-page="<?php echo $filters['page']; ?>">
                        <i class="fas fa-spinner fa-spin d-none me-2"></i>
                        Load More Products
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Mobile Filters Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileFiltersOffcanvas">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold">Filters & Sorting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <!-- Mobile filters content will be loaded via JavaScript -->
        <div id="mobile-filters-content"></div>
    </div>
    <div class="offcanvas-footer border-top p-3">
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-dark" id="apply-mobile-filters">Apply Filters</button>
            <button type="button" class="btn btn-outline-dark" id="clear-mobile-filters">Clear All</button>
        </div>
    </div>
</div>

<!-- Quick View Modal -->
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

<!-- Include noUiSlider CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.css">

<style>
/* Products Page Specific Styles */
.hover-lift {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.hover-lift:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.1) !important;
}

.hover-bg-light:hover {
    background-color: #f8f9fa !important;
}

.object-fit-cover {
    object-fit: cover;
}

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

.sticky-sidebar {
    position: sticky;
    height: calc(100vh - 100px);
    overflow-y: auto;
}

/* Product Card Hover Effects */
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

/* Size & Color Filter Buttons */
.size-btn.active,
.color-btn.active {
    border-color: #000 !important;
    transform: scale(1.1);
    box-shadow: 0 0 0 2px rgba(0,0,0,0.1);
}

.color-btn i {
    font-size: 12px;
}

/* Price Range Slider */
#priceSlider {
    margin: 20px 0;
}

#priceSlider .noUi-connect {
    background: #000;
}

#priceSlider .noUi-handle {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #000;
    background: white;
    cursor: pointer;
}

#priceSlider .noUi-handle:before,
#priceSlider .noUi-handle:after {
    display: none;
}

/* Pagination Active State */
.page-item.active .page-link {
    background-color: #000;
    border-color: #000;
    color: white;
}

.page-link {
    color: #000;
    border: 1px solid #dee2e6;
    padding: 0.5rem 0.75rem;
    margin: 0 2px;
    border-radius: 4px;
}

.page-link:hover {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    color: #000;
}

/* Offcanvas Styles */
.offcanvas-start {
    width: 320px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .sticky-sidebar {
        height: auto;
        position: static;
    }
    
    .products-list-view .product-card {
        flex-direction: column;
    }
    
    .products-list-view .product-img {
        width: 100%;
        height: 250px;
    }
    
    .card-img-top {
        height: 200px;
    }
    
    .display-6 {
        font-size: 1.75rem;
    }
}

@media (max-width: 576px) {
    .row-cols-sm-2 > * {
        flex: 0 0 auto;
        width: 100%;
    }
}
</style>

<!-- Include JavaScript Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.js"></script>

<script>
// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize price slider
    initializePriceSlider();
    
    // Setup event listeners
    setupEventListeners();
    
    // Load mobile filters
    loadMobileFilters();
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
            'max': 5000
        },
        step: 100,
        format: {
            to: value => Math.round(value),
            from: value => value
        }
    });
    
    // Update input fields when slider changes
    priceSlider.noUiSlider.on('update', function(values) {
        const minVal = Math.round(values[0]);
        const maxVal = Math.round(values[1]);
        
        document.getElementById('minPriceInput').value = minVal;
        document.getElementById('maxPriceInput').value = maxVal;
    });
    
    // Update slider when input fields change
    document.getElementById('minPriceInput').addEventListener('change', function() {
        priceSlider.noUiSlider.set([this.value, null]);
    });
    
    document.getElementById('maxPriceInput').addEventListener('change', function() {
        priceSlider.noUiSlider.set([null, this.value]);
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
    
    // Quick view buttons
    document.querySelectorAll('.quick-view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            loadQuickView(productId);
        });
    });
    
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            addToCart(productId, 1);
        });
    });
    
    // Load more button
    document.getElementById('load-more-btn')?.addEventListener('click', loadMoreProducts);
}

// Load Mobile Filters
function loadMobileFilters() {
    const mobileFiltersContent = document.getElementById('mobile-filters-content');
    if (!mobileFiltersContent) return;
    
    // Clone desktop filters for mobile
    const desktopFilters = document.querySelector('.sticky-sidebar').cloneNode(true);
    mobileFiltersContent.appendChild(desktopFilters);
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

// Load Quick View via AJAX
async function loadQuickView(productId) {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>products/api/quick-view.php?id=' + productId, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        
        if (data.success) {
            // Show modal with HTML content
            document.getElementById('quickViewContent').innerHTML = data.html;
            
            // Initialize modal
            const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
            modal.show();
            
            // Reinitialize any event listeners in the loaded content
            initializeQuickViewEvents();
        } else {
            console.error('Quick view error:', data.message);
            showToast('Failed to load product details', 'error');
        }
    } catch (error) {
        console.error('Quick view error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Initialize events for quick view modal content
function initializeQuickViewEvents() {
    // Add to cart in quick view
    const quickViewAddToCart = document.querySelector('#quickViewModal .add-to-cart-btn');
    if (quickViewAddToCart) {
        quickViewAddToCart.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const quantity = document.querySelector('#quickViewModal .quantity-input')?.value || 1;
            addToCart(productId, parseInt(quantity));
        });
    }
    
    // Quantity controls in quick view
    document.querySelectorAll('#quickViewModal .quantity-btn').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.closest('.input-group').querySelector('.quantity-input');
            let value = parseInt(input.value) || 1;
            
            if (this.classList.contains('minus') && value > 1) {
                value--;
            } else if (this.classList.contains('plus')) {
                value++;
            }
            
            input.value = value;
        });
    });
}
// Add to Cart Function
async function addToCart(productId, quantity) {
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
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        
        if (data.success) {
            // Update cart count
            updateCartCount(data.cart_count);
            
            // Show success toast
            showToast('Product added to cart successfully!');
            
            // Close quick view modal if open
            const quickViewModal = bootstrap.Modal.getInstance(document.getElementById('quickViewModal'));
            if (quickViewModal) quickViewModal.hide();
        } else {
            showToast(data.message || 'Failed to add product to cart', 'error');
        }
    } catch (error) {
        console.error('Add to cart error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Update Cart Count
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
        element.classList.remove('d-none');
    });
}

// Show Toast Notification
function showToast(message, type = 'success') {
    const toastElement = document.getElementById('cartSuccessToast');
    const toastMessage = document.getElementById('toastMessage');
    
    if (!toastElement || !toastMessage) return;
    
    // Update message and styling
    toastMessage.textContent = message;
    toastElement.className = 'toast align-items-center text-bg-' + type + ' border-0';
    
    // Show toast
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
}

// Load More Products (AJAX)
async function loadMoreProducts() {
    const loadMoreBtn = document.getElementById('load-more-btn');
    if (!loadMoreBtn) return;
    
    const currentPage = parseInt(loadMoreBtn.dataset.page) || 1;
    const nextPage = currentPage + 1;
    
    // Show loading state
    loadMoreBtn.disabled = true;
    loadMoreBtn.querySelector('.fa-spinner').classList.remove('d-none');
    
    try {
        // Get current URL parameters
        const params = new URLSearchParams(window.location.search);
        params.set('page', nextPage);
        params.set('ajax', '1');
        
        // Fetch next page
        const response = await fetch(`<?php echo SITE_URL; ?>products?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const html = await response.text();
        
        // Parse the response and extract products
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newProducts = doc.querySelectorAll('#products-container > .col, #products-container > .product-card');
        
        // Append new products to container
        const container = document.getElementById('products-container');
        newProducts.forEach(product => {
            container.appendChild(product);
        });
        
        // Update load more button
        loadMoreBtn.dataset.page = nextPage;
        
        // Hide button if no more pages
        const hasMore = doc.querySelector('#load-more-btn');
        if (!hasMore) {
            loadMoreBtn.remove();
        }
    } catch (error) {
        console.error('Load more error:', error);
        showToast('Failed to load more products', 'error');
    } finally {
        // Reset button state
        loadMoreBtn.disabled = false;
        loadMoreBtn.querySelector('.fa-spinner').classList.add('d-none');
    }
}

// Apply Mobile Filters
document.getElementById('apply-mobile-filters')?.addEventListener('click', function() {
    // Collect filter values from mobile offcanvas
    const params = new URLSearchParams(window.location.search);
    
    // Get selected filters from mobile view
    const mobileFilters = document.getElementById('mobile-filters-content');
    
    // Price
    const minPriceInput = mobileFilters.querySelector('#minPriceInput');
    const maxPriceInput = mobileFilters.querySelector('#maxPriceInput');
    if (minPriceInput?.value) params.set('min_price', minPriceInput.value);
    if (maxPriceInput?.value) params.set('max_price', maxPriceInput.value);
    
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
    const checkedBrands = Array.from(mobileFilters.querySelectorAll('.brand-checkbox:checked'))
        .map(cb => cb.value);
    if (checkedBrands.length > 0) {
        params.set('brand', checkedBrands[0]);
    } else {
        params.delete('brand');
    }
    
    // Reset to page 1
    params.set('page', 1);
    
    // Close offcanvas and apply filters
    const offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('mobileFiltersOffcanvas'));
    offcanvas.hide();
    
    // Navigate to filtered page
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
</script>

<?php
// Include footer at the end
require_once __DIR__ . '/../includes/footer.php';
?>