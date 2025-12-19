<?php
// /linen-closet/products/api/quick-view.php

// Enable error reporting for debugging (off in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Cache-Control: no-cache, must-revalidate');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Get product ID
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$productId || $productId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Valid product ID is required',
            'product_id' => $productId
        ]);
        exit;
    }
    
    // Include configuration files
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/Database.php';
    require_once __DIR__ . '/../../includes/App.php';
    
    // Initialize app for CSRF token if needed
    $app = new App();
    
    // Get database connection using Singleton pattern
    $db = Database::getInstance()->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get basic product information with additional fields
    $stmt = $db->prepare("
        SELECT 
            p.*,
            c.name as category_name,
            c.slug as category_slug,
            b.name as brand_name,
            b.slug as brand_slug,
            (SELECT COUNT(*) FROM wishlist w WHERE w.product_id = p.id AND w.user_id = :user_id) as in_wishlist
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id AND c.is_active = 1
        LEFT JOIN brands b ON p.brand_id = b.id AND b.is_active = 1
        WHERE p.id = :id AND p.is_active = 1
        LIMIT 1
    ");
    
    if (!$stmt) {
        $errorInfo = $db->errorInfo();
        throw new Exception('Failed to prepare product query: ' . ($errorInfo[2] ?? 'Unknown error'));
    }
    
    // Get user ID from session if available
    session_start();
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    
    $stmt->execute([
        ':id' => $productId,
        ':user_id' => $userId
    ]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Product not found or inactive',
            'product_id' => $productId
        ]);
        exit;
    }
    
    // Get product images
    $images = [];
    $imageStmt = $db->prepare("
        SELECT image_url, is_primary, alt_text 
        FROM product_images 
        WHERE product_id = ? 
        ORDER BY is_primary DESC, sort_order ASC
        LIMIT 5
    ");
    
    $imageStmt->execute([$productId]);
    $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add placeholder if no images
    if (empty($images)) {
        $images[] = [
            'image_url' => 'assets/images/placeholder.jpg',
            'is_primary' => 1,
            'alt_text' => $product['name']
        ];
    }
    
    // Get variants with stock status
    $variants = [];
    $variantStmt = $db->prepare("
        SELECT 
            *,
            CASE 
                WHEN stock_quantity <= 0 THEN 'out_of_stock'
                WHEN stock_quantity <= 5 THEN 'low_stock'
                ELSE 'in_stock'
            END as stock_status
        FROM product_variants 
        WHERE product_id = ?
        ORDER BY 
            CASE 
                WHEN is_default = 1 THEN 0
                ELSE 1
            END,
            size,
            color
    ");
    
    $variantStmt->execute([$productId]);
    $variants = $variantStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total stock and process variants
    $totalStock = 0;
    $availableSizes = [];
    $availableColors = [];
    $variantsBySize = [];
    $defaultVariant = null;
    $hasMultiplePrices = false;
    $minPrice = $product['price'];
    $maxPrice = $product['price'];
    
    foreach ($variants as $variant) {
        $size = $variant['size'] ? trim($variant['size']) : 'One Size';
        $color = $variant['color'] ? trim($variant['color']) : 'Default';
        $colorCode = $variant['color_code'] ?? '#cccccc';
        
        $totalStock += (int)$variant['stock_quantity'];
        
        // Track price range
        $variantPrice = $variant['price'] ?? $product['price'];
        if ($variantPrice < $minPrice) $minPrice = $variantPrice;
        if ($variantPrice > $maxPrice) $maxPrice = $variantPrice;
        if ($variantPrice != $product['price']) {
            $hasMultiplePrices = true;
        }
        
        // Group by size
        if (!isset($variantsBySize[$size])) {
            $variantsBySize[$size] = [];
        }
        $variantsBySize[$size][] = $variant;
        
        // Collect available sizes and colors
        if ($variant['stock_quantity'] > 0) {
            if (!in_array($size, $availableSizes)) {
                $availableSizes[] = $size;
            }
            
            $colorKey = $color . '|' . $colorCode;
            if (!in_array($colorKey, $availableColors)) {
                $availableColors[] = $colorKey;
            }
            
            // Set default variant
            if (!$defaultVariant && $variant['is_default'] == 1) {
                $defaultVariant = $variant;
            }
        }
    }
    
    // If no default variant with stock, use first available
    if (!$defaultVariant && count($variants) > 0) {
        foreach ($variants as $variant) {
            if ($variant['stock_quantity'] > 0) {
                $defaultVariant = $variant;
                break;
            }
        }
    }
    
    // If still no default, use first variant
    if (!$defaultVariant && count($variants) > 0) {
        $defaultVariant = $variants[0];
    }
    
    // Get review statistics
    $reviewCount = 0;
    $avgRating = 0;
    $reviewStats = [
        'five_star' => 0,
        'four_star' => 0,
        'three_star' => 0,
        'two_star' => 0,
        'one_star' => 0
    ];
    
    $reviewStmt = $db->prepare("
        SELECT 
            COUNT(*) as review_count,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM product_reviews 
        WHERE product_id = ? AND is_approved = 1
    ");
    
    $reviewStmt->execute([$productId]);
    $reviewData = $reviewStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reviewData) {
        $reviewCount = (int)$reviewData['review_count'];
        $avgRating = (float)$reviewData['avg_rating'];
        $reviewStats = [
            'five_star' => (int)$reviewData['five_star'],
            'four_star' => (int)$reviewData['four_star'],
            'three_star' => (int)$reviewData['three_star'],
            'two_star' => (int)$reviewData['two_star'],
            'one_star' => (int)$reviewData['one_star']
        ];
    }
    
    // Calculate star breakdown
    $fullStars = floor($avgRating);
    $hasHalfStar = ($avgRating - $fullStars) >= 0.5;
    
    // Get related products (simplified)
    $relatedProducts = [];
    if ($product['category_id']) {
        $relatedStmt = $db->prepare("
            SELECT 
                p.id,
                p.name,
                p.slug,
                p.price,
                p.compare_price,
                (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as image_url
            FROM products p
            WHERE p.category_id = ? 
            AND p.id != ? 
            AND p.is_active = 1
            ORDER BY p.view_count DESC
            LIMIT 4
        ");
        
        $relatedStmt->execute([$product['category_id'], $productId]);
        $relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Determine stock message
    $stockMessage = '';
    if ($totalStock <= 0) {
        $stockMessage = 'Out of Stock';
    } elseif ($totalStock <= 10) {
        $stockMessage = "Only {$totalStock} left in stock";
    } else {
        $stockMessage = 'In Stock';
    }
    
    // Determine price display
    $priceDisplay = '';
    if ($hasMultiplePrices && $minPrice != $maxPrice) {
        $priceDisplay = formatPrice($minPrice) . ' - ' . formatPrice($maxPrice);
    } else {
        $priceDisplay = formatPrice($product['price']);
    }
    
    // Format price helper function
    function formatPrice($price) {
        return 'Ksh ' . number_format($price, 2);
    }
    
    // Prepare HTML response for quick view modal
    $html = '
    <div class="quick-view-content">
        <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
        
        <div class="row g-4">
            <!-- Product Images -->
            <div class="col-md-6">
                <div class="product-image-main mb-3">
                    <div class="position-relative overflow-hidden rounded-3" style="height: 400px; background: #f8f9fa;">
                        <img src="' . SITE_URL . $images[0]['image_url'] . '" 
                             alt="' . htmlspecialchars($product['name']) . '" 
                             class="img-fluid w-100 h-100 object-fit-cover"
                             id="quickViewMainImage"
                             loading="lazy">
                        
                        <!-- Sale Badge -->
                        ' . ($product['compare_price'] && $product['compare_price'] > $product['price'] ? '
                        <div class="position-absolute top-0 start-0 m-3">
                            <span class="badge bg-danger fs-6 py-2 px-3 shadow">
                                <i class="fas fa-tag me-1"></i> Save ' . round((1 - $product['price'] / $product['compare_price']) * 100) . '%
                            </span>
                        </div>' : '') . '
                    </div>
                </div>
                
                <!-- Image Thumbnails -->
                ' . (count($images) > 1 ? '
                <div class="product-thumbnails">
                    <div class="d-flex flex-wrap gap-2">
                        ' . implode('', array_map(function($image, $index) use ($product) {
                            return '
                            <button type="button" 
                                    class="thumbnail-btn btn p-0 border rounded-2 overflow-hidden ' . ($index === 0 ? 'active' : '') . '"
                                    data-image="' . SITE_URL . $image['image_url'] . '"
                                    style="width: 70px; height: 70px;">
                                <img src="' . SITE_URL . $image['image_url'] . '" 
                                     alt="' . htmlspecialchars($product['name']) . '" 
                                     class="img-fluid w-100 h-100 object-fit-cover">
                            </button>';
                        }, $images, array_keys($images))) . '
                    </div>
                </div>' : '') . '
            </div>
            
            <!-- Product Details -->
            <div class="col-md-6">
                <div class="product-details">
                    <!-- Product Header -->
                    <div class="mb-3">
                        ' . ($product['brand_name'] ? '
                        <a href="' . SITE_URL . 'products?brand=' . urlencode($product['brand_slug']) . '" class="text-muted text-decoration-none">
                            <i class="fas fa-tag me-1"></i>' . htmlspecialchars($product['brand_name']) . '
                        </a>' : '') . '
                        <h4 class="fw-bold mb-2 mt-2">' . htmlspecialchars($product['name']) . '</h4>
                        
                        <!-- Rating -->
                        <div class="d-flex align-items-center mb-3">
                            <div class="star-rating me-2">
                                ' . str_repeat('<i class="fas fa-star text-warning"></i>', $fullStars) . '
                                ' . ($hasHalfStar ? '<i class="fas fa-star-half-alt text-warning"></i>' : '') . '
                                ' . str_repeat('<i class="far fa-star text-warning"></i>', 5 - $fullStars - ($hasHalfStar ? 1 : 0)) . '
                            </div>
                            <span class="fw-medium me-2">' . number_format($avgRating, 1) . '</span>
                            <span class="text-muted">(' . $reviewCount . ' reviews)</span>
                        </div>
                    </div>
                    
                    <!-- Price -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="display-6 fw-bold text-dark">
                                ' . $priceDisplay . '
                            </span>
                            
                            ' . ($product['compare_price'] && $product['compare_price'] > $product['price'] ? '
                            <span class="text-muted text-decoration-line-through fs-5">
                                ' . formatPrice($product['compare_price']) . '
                            </span>
                            <span class="badge bg-danger fs-6 py-2">
                                Save ' . formatPrice($product['compare_price'] - $product['price']) . '
                            </span>' : '') . '
                        </div>
                    </div>
                    
                    <!-- Stock Status -->
                    <div class="mb-4">
                        ' . ($totalStock <= 0 ? '
                        <div class="alert alert-danger mb-0 d-flex align-items-center">
                            <i class="fas fa-times-circle me-2"></i>
                            <span>This product is currently out of stock</span>
                        </div>' : ($totalStock <= 10 ? '
                        <div class="alert alert-warning mb-0 d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span>Only ' . $totalStock . ' left in stock - order soon!</span>
                        </div>' : '
                        <div class="alert alert-success mb-0 d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <span>In Stock - Ready to ship</span>
                        </div>')) . '
                    </div>
                    
                    <!-- Short Description -->
                    ' . ($product['short_description'] ? '
                    <div class="mb-4">
                        <p class="text-muted">' . htmlspecialchars($product['short_description']) . '</p>
                    </div>' : '') . '
                    
                    <!-- Size Selection -->
                    ' . (count($availableSizes) > 0 ? '
                    <div class="mb-4">
                        <label class="form-label fw-bold mb-2">Size</label>
                        <div class="size-selection d-flex flex-wrap gap-2">
                            ' . implode('', array_map(function($size) use ($defaultVariant) {
                                $isDefault = ($defaultVariant && trim($defaultVariant['size'] ?? '') === $size);
                                return '
                                <button type="button" 
                                        class="size-option btn btn-outline-dark ' . ($isDefault ? 'active' : '') . '"
                                        data-size="' . htmlspecialchars($size) . '"
                                        style="min-width: 50px;">
                                    ' . htmlspecialchars($size) . '
                                </button>';
                            }, $availableSizes)) . '
                        </div>
                    </div>' : '') . '
                    
                    <!-- Color Selection -->
                    ' . (count($availableColors) > 0 ? '
                    <div class="mb-4">
                        <label class="form-label fw-bold mb-2">Color</label>
                        <div class="color-selection d-flex flex-wrap gap-2">
                            ' . implode('', array_map(function($colorKey) use ($defaultVariant) {
                                list($colorName, $colorCode) = explode('|', $colorKey);
                                $isDefault = ($defaultVariant && trim($defaultVariant['color'] ?? '') === $colorName);
                                return '
                                <button type="button" 
                                        class="color-option btn p-0 rounded-circle border border-2 ' . ($isDefault ? 'active border-dark' : 'border-light') . '"
                                        data-color="' . htmlspecialchars($colorName) . '"
                                        data-color-code="' . htmlspecialchars($colorCode) . '"
                                        style="width: 35px; height: 35px; background-color: ' . htmlspecialchars($colorCode) . ';"
                                        title="' . htmlspecialchars($colorName) . '">
                                    ' . ($isDefault ? '<i class="fas fa-check position-absolute top-50 start-50 translate-middle text-white"></i>' : '') . '
                                </button>';
                            }, $availableColors)) . '
                        </div>
                    </div>' : '') . '
                    
                    <!-- Quantity & Add to Cart -->
                    <div class="mb-5">
                        <form id="quickViewAddToCartForm" class="row g-2">
                            <input type="hidden" id="quickViewProductId" value="' . $product['id'] . '">
                            <input type="hidden" id="quickViewSelectedVariantId" value="' . ($defaultVariant['id'] ?? '') . '">
                            <input type="hidden" name="csrf_token" value="' . $app->getCSRFToken() . '">
                            
                            <div class="col-auto">
                                <div class="input-group" style="width: 120px;">
                                    <button type="button" class="btn btn-outline-dark" id="quickViewDecreaseQty">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" 
                                           id="quickViewQuantity" 
                                           name="quantity" 
                                           value="1" 
                                           min="1" 
                                           max="' . min(10, $totalStock) . '"
                                           class="form-control text-center border-dark">
                                    <button type="button" class="btn btn-outline-dark" id="quickViewIncreaseQty">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col">
                                <button type="submit" 
                                        class="btn btn-dark btn-lg w-100 py-2" 
                                        id="quickViewAddToCartBtn" 
                                        ' . ($totalStock <= 0 ? 'disabled' : '') . '>
                                    <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                </button>
                            </div>
                        </form>
                        
                        <!-- Actions -->
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" 
                                    class="btn btn-outline-dark flex-grow-1" 
                                    id="quickViewWishlistBtn">
                                <i class="' . ($product['in_wishlist'] ? 'fas text-danger' : 'far') . ' fa-heart me-2"></i>
                                ' . ($product['in_wishlist'] ? 'Remove from Wishlist' : 'Add to Wishlist') . '
                            </button>
                            <a href="' . SITE_URL . 'products/detail.php?slug=' . $product['slug'] . '" 
                               class="btn btn-outline-dark">
                                <i class="fas fa-external-link-alt me-2"></i> View Details
                            </a>
                        </div>
                    </div>
                    
                    <!-- Product Features -->
                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="d-flex align-items-center text-muted p-2 bg-light rounded-3">
                                <i class="fas fa-truck me-2 fs-5 text-dark"></i>
                                <div>
                                    <small class="d-block fw-medium">Free Shipping</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center text-muted p-2 bg-light rounded-3">
                                <i class="fas fa-sync-alt me-2 fs-5 text-dark"></i>
                                <div>
                                    <small class="d-block fw-medium">Easy Returns</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Quick View Scripts
    document.addEventListener("DOMContentLoaded", function() {
        const quickViewContent = document.querySelector(".quick-view-content");
        
        // Image thumbnails
        quickViewContent.querySelectorAll(".thumbnail-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                const imageUrl = this.getAttribute("data-image");
                document.getElementById("quickViewMainImage").src = imageUrl;
                
                quickViewContent.querySelectorAll(".thumbnail-btn").forEach(b => b.classList.remove("active"));
                this.classList.add("active");
            });
        });
        
        // Size selection
        quickViewContent.querySelectorAll(".size-option").forEach(btn => {
            btn.addEventListener("click", function() {
                quickViewContent.querySelectorAll(".size-option").forEach(b => b.classList.remove("active"));
                this.classList.add("active");
                updateQuickViewVariant();
            });
        });
        
        // Color selection
        quickViewContent.querySelectorAll(".color-option").forEach(btn => {
            btn.addEventListener("click", function() {
                quickViewContent.querySelectorAll(".color-option").forEach(b => {
                    b.classList.remove("active");
                    b.style.borderColor = "#e0e0e0";
                });
                this.classList.add("active");
                this.style.borderColor = "#000";
                updateQuickViewVariant();
            });
        });
        
        // Quantity controls
        const qtyInput = document.getElementById("quickViewQuantity");
        document.getElementById("quickViewDecreaseQty")?.addEventListener("click", function() {
            if (parseInt(qtyInput.value) > 1) {
                qtyInput.value = parseInt(qtyInput.value) - 1;
            }
        });
        
        document.getElementById("quickViewIncreaseQty")?.addEventListener("click", function() {
            const max = parseInt(qtyInput.max);
            if (parseInt(qtyInput.value) < max) {
                qtyInput.value = parseInt(qtyInput.value) + 1;
            }
        });
        
        // Add to cart form
        document.getElementById("quickViewAddToCartForm")?.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const productId = document.getElementById("quickViewProductId").value;
            const variantId = document.getElementById("quickViewSelectedVariantId").value;
            const quantity = document.getElementById("quickViewQuantity").value;
            
            // Show loading state
            const btn = document.getElementById("quickViewAddToCartBtn");
            const originalText = btn.innerHTML;
            btn.innerHTML = \'<i class="fas fa-spinner fa-spin me-2"></i> Adding...\';
            btn.disabled = true;
            
            // Send AJAX request
            fetch("' . SITE_URL . 'ajax/add-to-cart.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify({
                    product_id: productId,
                    variant_id: variantId,
                    quantity: quantity,
                    csrf_token: document.querySelector(\'input[name="csrf_token"]\').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count
                    updateCartCount(data.cart_count);
                    
                    // Show success message
                    Swal.fire({
                        title: "Success!",
                        text: "Product added to cart",
                        icon: "success",
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById("quickViewModal"));
                        modal.hide();
                    });
                } else {
                    Swal.fire({
                        title: "Error",
                        text: data.message || "Failed to add to cart",
                        icon: "error"
                    });
                }
            })
            .catch(error => {
                console.error("Error:", error);
                Swal.fire({
                    title: "Error",
                    text: "Something went wrong. Please try again.",
                    icon: "error"
                });
            })
            .finally(() => {
                // Restore button
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });
        
        // Wishlist button
        document.getElementById("quickViewWishlistBtn")?.addEventListener("click", function() {
            const productId = document.getElementById("quickViewProductId").value;
            const isInWishlist = this.querySelector("i").classList.contains("fas");
            
            fetch("' . SITE_URL . 'ajax/wishlist-toggle.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify({
                    product_id: productId,
                    csrf_token: document.querySelector(\'input[name="csrf_token"]\').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const icon = this.querySelector("i");
                    const text = this.querySelector("span") || this;
                    
                    if (data.in_wishlist) {
                        icon.classList.remove("far");
                        icon.classList.add("fas", "text-danger");
                        this.innerHTML = \'<i class="fas fa-heart text-danger me-2"></i> Remove from Wishlist\';
                    } else {
                        icon.classList.remove("fas", "text-danger");
                        icon.classList.add("far");
                        this.innerHTML = \'<i class="far fa-heart me-2"></i> Add to Wishlist\';
                    }
                    
                    Swal.fire({
                        title: data.in_wishlist ? "Added to Wishlist!" : "Removed from Wishlist",
                        icon: "success",
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            })
            .catch(error => {
                console.error("Error:", error);
                Swal.fire({
                    title: "Login Required",
                    text: "Please log in to use wishlist",
                    icon: "info"
                });
            });
        });
        
        // Helper function to update variant
        function updateQuickViewVariant() {
            const selectedSize = quickViewContent.querySelector(".size-option.active")?.getAttribute("data-size");
            const selectedColor = quickViewContent.querySelector(".color-option.active")?.getAttribute("data-color");
            
            if (selectedSize || selectedColor) {
                // Find matching variant from the data
                const variants = ' . json_encode($variants) . ';
                let matchedVariant = null;
                
                for (const variant of variants) {
                    const variantSize = variant.size ? variant.size.trim() : \'One Size\';
                    const variantColor = variant.color ? variant.color.trim() : \'Default\';
                    
                    if ((!selectedSize || variantSize === selectedSize) && 
                        (!selectedColor || variantColor === selectedColor)) {
                        matchedVariant = variant;
                        break;
                    }
                }
                
                if (matchedVariant) {
                    document.getElementById("quickViewSelectedVariantId").value = matchedVariant.id;
                    
                    // Update stock status
                    const stockQty = matchedVariant.stock_quantity;
                    const qtyInput = document.getElementById("quickViewQuantity");
                    const addToCartBtn = document.getElementById("quickViewAddToCartBtn");
                    
                    if (stockQty <= 0) {
                        qtyInput.max = 0;
                        addToCartBtn.disabled = true;
                        addToCartBtn.innerHTML = \'<i class="fas fa-times me-2"></i> Out of Stock\';
                    } else {
                        qtyInput.max = Math.min(10, stockQty);
                        addToCartBtn.disabled = false;
                        addToCartBtn.innerHTML = \'<i class="fas fa-shopping-cart me-2"></i> Add to Cart\';
                    }
                }
            }
        }
        
        // Helper function to update cart count
        function updateCartCount(count) {
            const cartCountElements = document.querySelectorAll(".cart-count");
            cartCountElements.forEach(element => {
                element.textContent = count;
                element.classList.remove("d-none");
            });
        }
    });
    </script>';
    
    // Prepare final response
    $response = [
        'success' => true,
        'html' => $html,
        'product' => [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'slug' => $product['slug'],
            'price' => formatPrice($product['price']),
            'price_display' => $priceDisplay,
            'has_multiple_prices' => $hasMultiplePrices,
            'stock' => [
                'total' => $totalStock,
                'message' => $stockMessage,
                'status' => $totalStock > 0 ? ($totalStock <= 10 ? 'low_stock' : 'in_stock') : 'out_of_stock'
            ],
            'url' => SITE_URL . 'products/detail.php?slug=' . $product['slug']
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Quick View PDO Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'code' => 'DATABASE_ERROR'
    ]);
} catch (Exception $e) {
    error_log("Quick View Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'code' => 'SERVER_ERROR'
    ]);
}

exit;