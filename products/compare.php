<?php
// /products/compare.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Get product IDs from URL or localStorage
$productIds = [];
if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    $productIds = json_decode($_GET['ids'], true);
} elseif (isset($_COOKIE['compare_products'])) {
    $productIds = json_decode($_COOKIE['compare_products'], true);
}

// If no products to compare, redirect to products page
if (empty($productIds) || !is_array($productIds)) {
    header('Location: ' . SITE_URL . 'products');
    exit;
}

// Limit to 4 products maximum
$productIds = array_slice($productIds, 0, 4);

// Fetch products to compare
if (!empty($productIds)) {
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    $query = "
        SELECT 
            p.*,
            b.name as brand_name,
            b.logo_url as brand_logo,
            c.name as category_name,
            (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
            (SELECT GROUP_CONCAT(DISTINCT size) FROM product_variants pv WHERE pv.product_id = p.id AND pv.size IS NOT NULL) as available_sizes,
            (SELECT GROUP_CONCAT(DISTINCT color) FROM product_variants pv WHERE pv.product_id = p.id AND pv.color IS NOT NULL) as available_colors,
            (SELECT MIN(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as min_variant_price,
            (SELECT MAX(price) FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) as max_variant_price,
            (SELECT SUM(stock_quantity) FROM product_variants pv WHERE pv.product_id = p.id) as total_stock,
            (SELECT AVG(rating) FROM product_reviews pr WHERE pr.product_id = p.id AND pr.is_approved = 1) as avg_rating,
            (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id = p.id AND pr.is_approved = 1) as review_count
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders) AND p.is_active = 1
        ORDER BY FIELD(p.id, " . $placeholders . ")
    ";
    
    $params = array_merge($productIds, $productIds);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $products = [];
}

$pageTitle = "Compare Products";
require_once __DIR__ . '/../includes/header.php';

function formatPrice($price) {
    return 'Ksh ' . number_format($price, 2);
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
                <a href="<?php echo SITE_URL; ?>products" class="text-decoration-none">Products</a>
            </li>
            <li class="breadcrumb-item active">Compare Products</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h1 class="display-5 fw-bold text-gradient-primary mb-2">Compare Products</h1>
            <p class="text-muted mb-0">Side-by-side comparison of <?php echo count($products); ?> product<?php echo count($products) != 1 ? 's' : ''; ?></p>
        </div>
        <div class="col-md-4 text-end">
            <div class="d-flex gap-2 justify-content-end">
                <button class="btn btn-danger" id="clear-all-compare">
                    <i class="fas fa-trash me-2"></i>Clear All
                </button>
                <a href="<?php echo SITE_URL; ?>products" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add More Products
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <!-- Empty State -->
        <div class="text-center py-5 my-5">
            <div class="empty-state-icon mb-4">
                <i class="fas fa-exchange-alt fa-4x text-primary opacity-25"></i>
            </div>
            <h3 class="fw-bold mb-3 text-gradient-primary">No Products to Compare</h3>
            <p class="text-muted mb-4 lead">Add products to compare from the product listings page</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?php echo SITE_URL; ?>products" class="btn btn-primary btn-lg px-4 py-3">
                    <i class="fas fa-shopping-bag me-2"></i>Browse Products
                </a>
                <a href="<?php echo SITE_URL; ?>products/new" class="btn btn-outline-primary btn-lg px-4 py-3">
                    <i class="fas fa-star me-2"></i>View New Arrivals
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Compare Table -->
        <div class="card border-0 shadow-lg overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover compare-table mb-0">
                        <thead class="bg-gradient-primary text-white">
                            <tr>
                                <th style="width: 200px;" class="py-4">
                                    <h5 class="fw-bold mb-0">Features</h5>
                                </th>
                                <?php foreach ($products as $index => $product): ?>
                                <th class="text-center py-4" style="min-width: 300px;">
                                    <div class="position-relative p-3">
                                        <!-- Remove Button -->
                                        <button class="btn btn-sm btn-danger position-absolute top-0 end-0 remove-compare-item rounded-circle" 
                                                data-product-id="<?php echo $product['id']; ?>"
                                                style="width: 30px; height: 30px; transform: translate(50%, -50%);">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        
                                        <!-- Product Image -->
                                        <div class="mb-3">
                                            <img src="<?php echo SITE_URL . ($product['primary_image'] ?: 'assets/images/placeholder.jpg'); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="img-fluid rounded shadow-sm" 
                                                 style="height: 200px; width: 100%; object-fit: cover;">
                                        </div>
                                        
                                        <!-- Product Name -->
                                        <h5 class="fw-bold mb-2">
                                            <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" 
                                               class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h5>
                                        
                                        <!-- Rating -->
                                        <?php if ($product['avg_rating'] > 0): ?>
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <div class="me-2">
                                                    <?php echo renderStars($product['avg_rating']); ?>
                                                </div>
                                                <small class="text-muted">
                                                    (<?php echo $product['review_count']; ?>)
                                                </small>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Price -->
                                        <div class="text-primary fw-bold fs-4 mb-3">
                                            <?php 
                                            if ($product['min_variant_price'] && $product['max_variant_price'] 
                                                && $product['min_variant_price'] != $product['max_variant_price']) {
                                                echo formatPrice($product['min_variant_price']) . ' - ' . formatPrice($product['max_variant_price']);
                                            } else {
                                                echo formatPrice($product['price']);
                                            }
                                            ?>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="d-grid gap-2">
                                            <?php 
                                            $stock = $product['total_stock'] ?? 0;
                                            $isOutOfStock = $stock <= 0;
                                            ?>
                                            <button class="btn btn-primary w-100 add-to-cart-btn <?php echo $isOutOfStock ? 'disabled' : ''; ?>" 
                                                    data-product-id="<?php echo $product['id']; ?>"
                                                    <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                                <i class="fas fa-shopping-cart me-2"></i>
                                                <?php echo $isOutOfStock ? 'Out of Stock' : 'Add to Cart'; ?>
                                            </button>
                                            <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" 
                                               class="btn btn-outline-primary w-100">
                                                <i class="fas fa-eye me-2"></i>View Details
                                            </a>
                                        </div>
                                    </div>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Basic Information -->
                            <tr class="bg-light">
                                <td class="fw-bold py-3">Product Information</td>
                                <td colspan="<?php echo count($products); ?>" class="text-center text-muted py-3">Basic Details</td>
                            </tr>
                            
                            <tr>
                                <td class="fw-bold">Brand</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <?php if (!empty($product['brand_logo'])): ?>
                                            <img src="<?php echo SITE_URL . $product['brand_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($product['brand_name']); ?>"
                                                 class="me-2" 
                                                 style="width: 24px; height: 24px; object-fit: contain;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($product['brand_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <tr>
                                <td class="fw-bold">Category</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <span class="badge bg-primary bg-opacity-10 text-primary">
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <tr>
                                <td class="fw-bold">SKU</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <code class="text-muted"><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></code>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Pricing -->
                            <tr class="bg-light">
                                <td class="fw-bold py-3">Pricing</td>
                                <td colspan="<?php echo count($products); ?>" class="text-center text-muted py-3">Cost & Value</td>
                            </tr>
                            
                            <tr>
                                <td class="fw-bold">Price</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <div class="fw-bold text-primary fs-5">
                                        <?php echo formatPrice($product['price']); ?>
                                    </div>
                                    <?php if ($product['min_variant_price'] && $product['max_variant_price']): ?>
                                        <small class="text-muted d-block">
                                            Variants: <?php echo formatPrice($product['min_variant_price']); ?> - <?php echo formatPrice($product['max_variant_price']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <?php if (isset($product['compare_price']) && $product['compare_price'] > 0): ?>
                            <tr>
                                <td class="fw-bold">Regular Price</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <?php if (isset($product['compare_price']) && $product['compare_price'] > 0): ?>
                                        <span class="text-muted text-decoration-line-through">
                                            <?php echo formatPrice($product['compare_price']); ?>
                                        </span>
                                        <?php 
                                        $discount = round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100);
                                        if ($discount > 0): ?>
                                            <span class="badge bg-danger ms-2">Save <?php echo $discount; ?>%</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endif; ?>
                            
                            <!-- Specifications -->
                            <tr class="bg-light">
                                <td class="fw-bold py-3">Specifications</td>
                                <td colspan="<?php echo count($products); ?>" class="text-center text-muted py-3">Product Details</td>
                            </tr>
                            
                            <?php if (array_filter(array_column($products, 'available_sizes'))): ?>
                            <tr>
                                <td class="fw-bold">Available Sizes</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <?php if (!empty($product['available_sizes'])): ?>
                                        <div class="d-flex flex-wrap gap-1 justify-content-center">
                                            <?php 
                                            $sizes = explode(',', $product['available_sizes']);
                                            foreach ($sizes as $size): 
                                                $trimmedSize = trim($size);
                                                if (!empty($trimmedSize)): ?>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-3 py-1">
                                                        <?php echo htmlspecialchars($trimmedSize); ?>
                                                    </span>
                                                <?php endif;
                                            endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if (array_filter(array_column($products, 'available_colors'))): ?>
                            <tr>
                                <td class="fw-bold">Available Colors</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <?php if (!empty($product['available_colors'])): ?>
                                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                                            <?php 
                                            $colors = explode(',', $product['available_colors']);
                                            foreach ($colors as $color): 
                                                $trimmedColor = trim($color);
                                                if (!empty($trimmedColor)): ?>
                                                    <span class="badge px-3 py-1 border" 
                                                          style="background-color: #f8f9fa; color: #495057;">
                                                        <?php echo htmlspecialchars($trimmedColor); ?>
                                                    </span>
                                                <?php endif;
                                            endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endif; ?>
                            
                            <!-- Stock & Availability -->
                            <tr class="bg-light">
                                <td class="fw-bold py-3">Availability</td>
                                <td colspan="<?php echo count($products); ?>" class="text-center text-muted py-3">Stock Status</td>
                            </tr>
                            
                            <tr>
                                <td class="fw-bold">Stock Status</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <?php 
                                    $stock = $product['total_stock'] ?? 0;
                                    if ($stock <= 0): ?>
                                        <span class="badge bg-danger px-3 py-2">
                                            <i class="fas fa-times me-1"></i>Out of Stock
                                        </span>
                                    <?php elseif ($stock <= 10): ?>
                                        <span class="badge bg-warning text-dark px-3 py-2">
                                            <i class="fas fa-exclamation me-1"></i>Low Stock (<?php echo $stock; ?> left)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success px-3 py-2">
                                            <i class="fas fa-check me-1"></i>In Stock (<?php echo $stock; ?> available)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Product Description -->
                            <tr class="bg-light">
                                <td class="fw-bold py-3">Description</td>
                                <td colspan="<?php echo count($products); ?>" class="text-center text-muted py-3">Product Details</td>
                            </tr>
                            
                            <tr>
                                <td class="fw-bold align-top">Overview</td>
                                <?php foreach ($products as $product): ?>
                                <td>
                                    <div class="text-truncate-4">
                                        <?php echo htmlspecialchars($product['description'] ?? 'No description available'); ?>
                                    </div>
                                    <?php if (!empty($product['description']) && strlen($product['description']) > 150): ?>
                                        <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $product['slug']; ?>" 
                                           class="text-primary text-decoration-none small">
                                            Read more...
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Additional Info -->
                            <?php if (isset($products[0]['material']) || isset($products[0]['care_instructions'])): ?>
                            <tr class="bg-light">
                                <td class="fw-bold py-3">Additional Information</td>
                                <td colspan="<?php echo count($products); ?>" class="text-center text-muted py-3">Extra Details</td>
                            </tr>
                            
                            <?php if (isset($products[0]['material'])): ?>
                            <tr>
                                <td class="fw-bold">Material</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <?php echo !empty($product['material']) ? htmlspecialchars($product['material']) : '<span class="text-muted">-</span>'; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if (isset($products[0]['care_instructions'])): ?>
                            <tr>
                                <td class="fw-bold">Care Instructions</td>
                                <?php foreach ($products as $product): ?>
                                <td class="text-center">
                                    <?php echo !empty($product['care_instructions']) ? htmlspecialchars($product['care_instructions']) : '<span class="text-muted">-</span>'; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Comparison Summary -->
        <div class="row mt-4">
            <div class="col-md-6 mb-3">
                <div class="card border-0 bg-light shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-primary mb-3">
                            <i class="fas fa-chart-bar me-2"></i>Comparison Summary
                        </h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Comparing <?php echo count($products); ?> product<?php echo count($products) != 1 ? 's' : ''; ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-tag text-primary me-2"></i>
                                Price range: 
                                <?php 
                                $prices = array_column($products, 'price');
                                echo formatPrice(min($prices)) . ' - ' . formatPrice(max($prices));
                                ?>
                            </li>
                            <li>
                                <i class="fas fa-layer-group text-warning me-2"></i>
                                <?php 
                                $categories = array_unique(array_column($products, 'category_name'));
                                echo count($categories) . ' categor' . (count($categories) != 1 ? 'ies' : 'y');
                                ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card border-0 bg-primary bg-opacity-10 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-primary mb-3">
                            <i class="fas fa-lightbulb me-2"></i>Tips
                        </h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <small><i class="fas fa-info-circle text-primary me-2"></i>
                                Click on product names to view full details
                                </small>
                            </li>
                            <li class="mb-2">
                                <small><i class="fas fa-info-circle text-primary me-2"></i>
                                Add more products (up to 4) from the products page
                                </small>
                            </li>
                            <li>
                                <small><i class="fas fa-info-circle text-primary me-2"></i>
                                Use the "Add to Cart" button to purchase directly
                                </small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.compare-table th, .compare-table td {
    vertical-align: middle;
    border-color: #e0e0e0 !important;
}

.compare-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.compare-table tbody tr:hover {
    background-color: #f0f7ff;
}

.text-truncate-4 {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.remove-compare-item {
    transition: all 0.3s ease;
}

.remove-compare-item:hover {
    transform: translate(50%, -50%) scale(1.1) !important;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), #fff);
    border-radius: 50%;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table-responsive {
        border: 0;
    }
    
    .compare-table {
        display: block;
    }
    
    .compare-table thead {
        display: none;
    }
    
    .compare-table tbody, .compare-table tr, .compare-table td {
        display: block;
        width: 100%;
    }
    
    .compare-table tr {
        margin-bottom: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        overflow: hidden;
    }
    
    .compare-table td {
        text-align: right;
        padding-left: 50%;
        position: relative;
        border-top: 1px solid #dee2e6;
    }
    
    .compare-table td:before {
        content: attr(data-label);
        position: absolute;
        left: 0;
        width: 45%;
        padding-left: 1rem;
        font-weight: bold;
        text-align: left;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make table responsive on mobile
    makeTableResponsive();
    
    // Remove product from compare
    document.querySelectorAll('.remove-compare-item').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            removeFromCompare(productId);
            
            // Remove this column from the table
            const th = this.closest('th');
            const index = Array.from(th.parentNode.children).indexOf(th);
            
            // Remove column from all rows
            document.querySelectorAll('.compare-table tr').forEach(row => {
                const cell = row.children[index];
                if (cell) cell.remove();
            });
            
            // Update colspan for section headers
            updateColspans();
            
            // Show message if no products left
            if (document.querySelectorAll('.compare-table th').length <= 1) {
                setTimeout(() => {
                    window.location.href = '<?php echo SITE_URL; ?>products';
                }, 1000);
            }
            
            showToast('Product removed from comparison', 'info');
        });
    });
    
    // Clear all compare
    document.getElementById('clear-all-compare')?.addEventListener('click', function() {
        if (confirm('Are you sure you want to clear all products from comparison?')) {
            localStorage.removeItem('compareProducts');
            document.cookie = 'compare_products=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            window.location.href = '<?php echo SITE_URL; ?>products';
        }
    });
    
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', async function() {
            const productId = this.dataset.productId;
            const button = this;
            const originalText = button.innerHTML;
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            button.disabled = true;
            
            try {
                const response = await fetch('<?php echo SITE_URL; ?>ajax/add-to-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: 1
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update cart count
                    updateCartCount(data.cart_count);
                    
                    // Show success message
                    showToast('Product added to cart!', 'success');
                    
                    // Restore button with success state
                    button.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-success');
                    
                    // Revert after 2 seconds
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.classList.remove('btn-success');
                        button.classList.add('btn-primary');
                        button.disabled = false;
                    }, 2000);
                } else {
                    showToast(data.message || 'Failed to add to cart', 'error');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            } catch (error) {
                console.error('Add to cart error:', error);
                showToast('Something went wrong', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        });
    });
    
    // Make table responsive
    function makeTableResponsive() {
        if (window.innerWidth < 768) {
            const table = document.querySelector('.compare-table');
            if (!table) return;
            
            // Get headers
            const headers = [];
            table.querySelectorAll('thead th').forEach((th, index) => {
                if (index > 0) { // Skip first header (Features column)
                    const productName = th.querySelector('h5')?.textContent || `Product ${index}`;
                    headers.push(productName);
                }
            });
            
            // Add data-label attribute to all cells
            table.querySelectorAll('tbody td').forEach((td, index) => {
                const rowIndex = Math.floor(index / headers.length);
                const headerIndex = index % headers.length;
                if (headerIndex < headers.length) {
                    td.setAttribute('data-label', headers[headerIndex]);
                }
            });
        }
    }
    
    // Update colspan for section headers
    function updateColspans() {
        const productCount = document.querySelectorAll('.compare-table th').length - 1;
        document.querySelectorAll('.compare-table td[colspan]').forEach(td => {
            td.setAttribute('colspan', productCount);
        });
    }
    
    // Update cart count
    function updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count');
        cartCountElements.forEach(element => {
            element.textContent = count;
            element.classList.remove('d-none');
        });
    }
    
    // Remove from compare (also update localStorage)
    function removeFromCompare(productId) {
        // Update localStorage
        let compareProducts = JSON.parse(localStorage.getItem('compareProducts') || '[]');
        compareProducts = compareProducts.filter(id => id != productId);
        localStorage.setItem('compareProducts', JSON.stringify(compareProducts));
        
        // Update cookie
        document.cookie = `compare_products=${JSON.stringify(compareProducts)}; path=/`;
    }
    
    // Show toast notification
    function showToast(message, type = 'success') {
        // Create toast if it doesn't exist
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        
        const toast = new bootstrap.Toast(document.getElementById(toastId));
        toast.show();
        
        // Remove toast after hiding
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function () {
            this.remove();
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', makeTableResponsive);
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>