<?php
// /linen-closet/cart/index.php - UPDATED FOR VARIANTS

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/Shipping.php';
$app = new App();
$db = $app->getDB();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = $_SESSION['cart'];
$cartCount = 0;
$subtotal = 0;
$shipping = 0;
$tax = 0;
$total = 0;

// Fetch cart items with product details
$cartItems = [];
if (!empty($cart)) {
    // Get unique product IDs from cart items
    $productIds = [];
    foreach ($cart as $cartKey => $item) {
        if (isset($item['product_id'])) {
            $productIds[] = $item['product_id'];
        }
    }
    $productIds = array_unique($productIds);
    
    if (!empty($productIds)) {
               // Get product details for all cart products
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        
        // Debug: Check what we're passing
        // echo "Product IDs: " . print_r($productIds, true);
        // echo "Placeholders: " . $placeholders;
        
        $stmt = $db->prepare("
            SELECT 
                p.*,
                (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
                c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id IN ($placeholders) AND p.is_active = 1
        ");
        
        // FIX: Ensure we pass the correct number of parameters
        $stmt->execute(array_values($productIds));  // Use array_values() to ensure proper array format
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize products by ID for easy lookup
        $productsById = [];
        foreach ($products as $product) {
            $productsById[$product['id']] = $product;
        }
        
        // Build cart items array
        foreach ($cart as $cartKey => $cartItem) {
            $productId = $cartItem['product_id'] ?? null;
            
            if ($productId && isset($productsById[$productId])) {
                $product = $productsById[$productId];
                $quantity = $cartItem['quantity'] ?? 1;
                $price = $cartItem['price'] ?? $product['price'];
                $itemTotal = $price * $quantity;
                
                // Build variant description
                $variantDescription = '';
                $variantParts = [];
                if (!empty($cartItem['size'])) {
                    $variantParts[] = 'Size: ' . htmlspecialchars($cartItem['size']);
                }
                if (!empty($cartItem['color'])) {
                    $variantParts[] = 'Color: ' . htmlspecialchars($cartItem['color']);
                }
                if (!empty($cartItem['material'])) {
                    $variantParts[] = 'Material: ' . htmlspecialchars($cartItem['material']);
                }
                if (!empty($variantParts)) {
                    $variantDescription = implode(' • ', $variantParts);
                }
                
                $cartItems[] = [
                    'cart_key' => $cartKey,
                    'id' => $productId,
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $itemTotal,
                    'size' => $cartItem['size'] ?? null,
                    'color' => $cartItem['color'] ?? null,
                    'material' => $cartItem['material'] ?? null,
                    'variant_description' => $variantDescription,
                    'variant_id' => $cartItem['variant_id'] ?? null
                ];
                
                $cartCount += $quantity;
                $subtotal += $itemTotal;
            }
        }
        
       
       
        $shippingHelper = new Shipping($db);

        // Get shipping cost (default to Nairobi or get from session)
        $userCounty = $_SESSION['user']['county'] ?? 'Nairobi';
        $shippingInfo = $shippingHelper->calculateShipping($userCounty, $subtotal);

        $shipping = $shippingInfo['cost'];
        $shippingMessage = $shippingInfo['message'] ?? 'Standard shipping'; // Add default message
        $tax = $subtotal * 0.16;
        $total = $subtotal + $shipping + $tax;
    }
}

$pageTitle = "Shopping Cart (" . $cartCount . " items)";

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --primary-blue: #0d6efd;
        --secondary-blue: #0dcaf0;
        --dark-blue: #052c65;
        --light-blue: #cfe2ff;
    }
    
    .btn-primary-blue {
        background-color: var(--primary-blue);
        border-color: var(--primary-blue);
        color: white;
    }
    
    .btn-primary-blue:hover {
        background-color: var(--dark-blue);
        border-color: var(--dark-blue);
        color: white;
    }
    
    .btn-outline-blue {
        color: var(--primary-blue);
        border-color: var(--primary-blue);
    }
    
    .btn-outline-blue:hover {
        background-color: var(--primary-blue);
        color: white;
    }
    
    .text-blue {
        color: var(--primary-blue) !important;
    }
    
    .bg-blue-light {
        background-color: var(--light-blue);
    }
    
    .border-blue {
        border-color: var(--primary-blue) !important;
    }
    
    .quantity-selector .btn {
        color: var(--primary-blue);
        border-color: var(--primary-blue);
    }
    
    .quantity-selector .btn:hover {
        background-color: var(--primary-blue);
        color: white;
    }
    
    .quantity-selector .form-control {
        border-color: var(--primary-blue);
    }
    
    .recommended-product {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid #e0e0e0;
    }
    
    .recommended-product:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(13, 110, 253, 0.1);
        border-color: var(--primary-blue);
    }
    
    .add-recommended-btn {
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .recommended-product:hover .add-recommended-btn {
        opacity: 1;
    }
    
    .card-header.bg-blue {
        background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        color: white;
    }
    
    .badge-blue {
        background-color: var(--primary-blue);
        color: white;
    }
</style>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-blue-light p-3 rounded">
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>" class="text-decoration-none text-blue">
                    <i class="fas fa-home me-1"></i> Home
                </a>
            </li>
            <li class="breadcrumb-item active text-blue" aria-current="page">Shopping Cart</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <h1 class="display-5 fw-bold mb-3 text-blue">Shopping Cart</h1>
            <p class="text-muted lead">
                <?php if ($cartCount > 0): ?>
                    <span class="badge bg-primary rounded-pill"><?php echo $cartCount; ?></span>
                    You have <?php echo $cartCount; ?> item<?php echo $cartCount !== 1 ? 's' : ''; ?> in your cart
                <?php else: ?>
                    Your cart is empty
                <?php endif; ?>
            </p>
        </div>
    </div>
    
    <div class="row">
        <!-- Cart Items -->
        <div class="col-lg-8 mb-4">
            <?php if ($cartCount > 0): ?>
                <!-- Cart Table (Desktop) -->
                <div class="card border-0 shadow-sm d-none d-lg-block">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-blue-light">
                                    <tr>
                                        <th style="width: 50px;"></th>
                                        <th>Product</th>
                                        <th class="text-center">Price</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-center">Total</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="cartItemsBody">
                                    <?php foreach ($cartItems as $item): ?>
                                        <?php
                                        $product = $item['product'];
                                        $imageUrl = SITE_URL . ($product['primary_image'] ?: 'assets/images/placeholder.jpg');
                                        $productUrl = SITE_URL . 'products/detail.php?slug=' . $product['slug'];
                                        ?>
                                        <tr class="cart-item" data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>">
                                            <td>
                                                <input type="checkbox" 
                                                       class="form-check-input select-item" 
                                                       value="<?php echo htmlspecialchars($item['cart_key']); ?>"
                                                       style="border-color: var(--primary-blue);"
                                                       checked>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                                        <img src="<?php echo $imageUrl; ?>" 
                                                             class="rounded me-3" 
                                                             style="width: 80px; height: 80px; object-fit: cover; border: 2px solid var(--light-blue);"
                                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                    </a>
                                                    <div>
                                                        <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-blue fw-bold">
                                                            <?php echo htmlspecialchars($product['name']); ?>
                                                        </a>
                                                        <?php if (!empty($item['variant_description'])): ?>
                                                            <div class="text-muted small mt-1">
                                                                <?php echo $item['variant_description']; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="text-muted small">
                                                            SKU: <?php echo htmlspecialchars($product['sku']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="price fw-bold text-blue">
                                                    Ksh <?php echo number_format($item['price'], 2); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="quantity-selector d-inline-flex align-items-center">
                                                    <button type="button" 
                                                            class="btn btn-outline-blue btn-sm decrease-qty"
                                                            data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <input type="number" 
                                                           class="form-control text-center border-blue rounded-0 quantity-input"
                                                           value="<?php echo $item['quantity']; ?>"
                                                           min="1" 
                                                           max="99"
                                                           style="width: 60px;"
                                                           data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>"
                                                           data-original-quantity="<?php echo $item['quantity']; ?>">
                                                    <button type="button" 
                                                            class="btn btn-outline-blue btn-sm increase-qty"
                                                            data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="item-total fw-bold text-blue">
                                                    Ksh <?php echo number_format($item['total'], 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-sm remove-item"
                                                        data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>"
                                                        title="Remove item">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Cart Items (Mobile) -->
                <div class="d-block d-lg-none">
                    <?php foreach ($cartItems as $item): ?>
                        <?php
                        $product = $item['product'];
                        $imageUrl = SITE_URL . ($product['primary_image'] ?: 'assets/images/placeholder.jpg');
                        $productUrl = SITE_URL . 'products/detail.php?slug=' . $product['slug'];
                        ?>
                        <div class="card border-0 shadow-sm mb-3 cart-item" data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-3">
                                        <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                            <img src="<?php echo $imageUrl; ?>" 
                                                 class="rounded w-100" 
                                                 style="height: 100px; object-fit: cover; border: 2px solid var(--light-blue);"
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        </a>
                                    </div>
                                    <div class="col-9">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-blue fw-bold">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </a>
                                                <?php if (!empty($item['variant_description'])): ?>
                                                    <div class="text-muted small mt-1">
                                                        <?php echo $item['variant_description']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm remove-item"
                                                    data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="quantity-selector d-flex align-items-center">
                                                <button type="button" 
                                                        class="btn btn-outline-blue btn-sm decrease-qty"
                                                        data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" 
                                                       class="form-control text-center border-blue rounded-0 quantity-input"
                                                       value="<?php echo $item['quantity']; ?>"
                                                       min="1" 
                                                       max="99"
                                                       style="width: 50px;"
                                                       data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>"
                                                       data-original-quantity="<?php echo $item['quantity']; ?>">
                                                <button type="button" 
                                                        class="btn btn-outline-blue btn-sm increase-qty"
                                                        data-cart-key="<?php echo htmlspecialchars($item['cart_key']); ?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-blue">
                                                    Ksh <?php echo number_format($item['price'], 2); ?>
                                                </div>
                                                <div class="item-total fw-bold text-blue">
                                                    Ksh <?php echo number_format($item['total'], 2); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Cart Actions -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="d-flex align-items-center">
                        <input type="checkbox" id="selectAll" class="form-check-input me-2" style="border-color: var(--primary-blue);" checked>
                        <label for="selectAll" class="form-check-label fw-medium text-blue">Select All</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-blue" id="updateCart">
                            <i class="fas fa-sync-alt me-2"></i> Update Cart
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="clearCart">
                            <i class="fas fa-trash-alt me-2"></i> Clear Cart
                        </button>
                    </div>
                </div>
                
                <!-- Continue Shopping -->
                <div class="mt-4">
                    <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-blue">
                        <i class="fas fa-arrow-left me-2"></i> Continue Shopping
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Empty Cart -->
                <div class="text-center py-5">
                    <div class="py-5">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                        <h3 class="fw-bold mb-3 text-blue">Your cart is empty</h3>
                        <p class="text-muted mb-4 lead">Looks like you haven't added any products to your cart yet.</p>
                        <a href="<?php echo SITE_URL; ?>products" class="btn btn-primary-blue btn-lg px-5">
                            <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Order Summary -->
        <?php if ($cartCount > 0): ?>
            <div class="col-lg-4">
                <div class="card border-blue shadow-sm" style="position: sticky; top: 100px;">
                    <div class="card-header bg-blue text-white py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-receipt me-2"></i> Order Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Summary Items -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span class="fw-bold text-blue" id="subtotalAmount">
                                    Ksh <?php echo number_format($subtotal, 2); ?>
                                </span>
                            </div>
                           <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Shipping</span>
                                <span class="fw-bold text-blue" id="shippingAmount">
                                    <?php if ($shipping > 0): ?>
                                        Ksh <?php echo number_format($shipping, 2); ?>
                                        <small class="d-block text-muted"><?php echo $shippingMessage; ?></small>
                                    <?php else: ?>
                                        <span class="text-success">FREE</span>
                                        <small class="d-block text-muted"><?php echo $shippingMessage; ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Tax (16% VAT)</span>
                                <span class="fw-bold text-blue" id="taxAmount">
                                    Ksh <?php echo number_format($tax, 2); ?>
                                </span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-bold fs-5 text-blue">Total</span>
                                <span class="fw-bold fs-5 text-blue" id="totalAmount">
                                    Ksh <?php echo number_format($total, 2); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Shipping Estimate -->
                        <!-- <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-blue">
                                <i class="fas fa-truck me-2"></i> Shipping Estimate
                            </h6>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control border-blue" 
                                       placeholder="Enter postal code"
                                       id="postalCode">
                                <button class="btn btn-outline-blue" type="button" id="calculateShipping">
                                    Calculate
                                </button>
                            </div>
                            <div id="shippingEstimateResult" class="mt-2"></div>
                        </div> -->
                        
                        <!-- Discount Code -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-blue">
                                <i class="fas fa-tag me-2"></i> Discount Code
                            </h6>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control border-blue" 
                                       placeholder="Enter discount code"
                                       id="discountCode">
                                <button class="btn btn-outline-blue" type="button" id="applyDiscount">
                                    Apply
                                </button>
                            </div>
                            <div id="discountResult" class="mt-2"></div>
                        </div>
                        
                        <!-- Checkout Button -->
                        <a href="<?php echo SITE_URL; ?>cart/checkout" 
                           class="btn btn-primary-blue btn-lg w-100 py-3 mb-3"
                           id="checkoutBtn">
                            <i class="fas fa-lock me-2"></i> Proceed to Checkout
                        </a>
                        
                        <!-- Payment Methods -->
                        <div class="text-center mb-3">
                            <small class="text-muted">Secure payment by</small>
                            <div class="d-flex justify-content-center gap-2 mt-2">
                                <i class="fab fa-cc-visa fa-2x text-primary"></i>
                                <i class="fab fa-cc-mastercard fa-2x text-danger"></i>
                                <i class="fab fa-cc-paypal fa-2x text-info"></i>
                                <i class="fab fa-cc-apple-pay fa-2x text-dark"></i>
                            </div>
                        </div>
                        
                        <!-- Security Badge -->
                        <div class="text-center">
                            <small class="text-muted d-block mb-2">
                                <i class="fas fa-shield-alt text-success me-1"></i>
                                100% Secure Checkout
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-lock text-success me-1"></i>
                                Your payment information is encrypted
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Frequently Bought Together Section -->
    <?php if (!empty($cartItems)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-2 text-blue">
                            <i class="fas fa-fire text-warning me-2"></i> Frequently Bought Together
                        </h5>
                        <small class="text-muted">Customers who bought these items also bought these products</small>
                    </div>
                    <div class="card-body">
                        <?php
                        // Fetch recommended products based on cart items
                        $categoryIds = [];
                        foreach ($cartItems as $item) {
                            if (!empty($item['product']['category_id'])) {
                                $categoryIds[] = $item['product']['category_id'];
                            }
                        }
                        $categoryIds = array_unique($categoryIds);
                        
                        $recommendedProducts = [];
                        if (!empty($categoryIds)) {
                            $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
                            $cartProductIds = array_column($cartItems, 'id');
                            
                            $recommendedStmt = $db->prepare("
                                SELECT 
                                    p.*,
                                    (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
                                FROM products p
                                WHERE p.category_id IN ($placeholders) 
                                  AND p.is_active = 1
                                  AND p.id NOT IN (" . (!empty($cartProductIds) ? implode(',', array_fill(0, count($cartProductIds), '?')) : '0') . ")
                                ORDER BY RAND()
                                LIMIT 4
                            ");
                            
                            $params = array_merge(array_values($categoryIds), array_values($cartProductIds));
                            $recommendedStmt->execute($params);
                            $recommendedProducts = $recommendedStmt->fetchAll(PDO::FETCH_ASSOC);
                        }
                        ?>
                        
                        <?php if (!empty($recommendedProducts)): ?>
                            <div class="row g-4">
                                <?php foreach ($recommendedProducts as $recProduct): ?>
                                    <div class="col-lg-3 col-md-6 col-sm-6 col-6" data-recommended-id="<?php echo $recProduct['id']; ?>">
                                        <div class="recommended-product card h-100 border-1">
                                            <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $recProduct['slug']; ?>" 
                                               class="text-decoration-none">
                                                <div class="position-relative overflow-hidden" style="height: 180px;">
                                                    <img src="<?php echo SITE_URL . ($recProduct['primary_image'] ?: 'assets/images/placeholder.jpg'); ?>" 
                                                         class="w-100 h-100 object-fit-cover"
                                                         alt="<?php echo htmlspecialchars($recProduct['name']); ?>">
                                                    <button type="button" 
                                                            class="btn btn-primary-blue btn-sm position-absolute top-0 end-0 m-2 p-2 rounded-circle add-recommended-btn"
                                                            data-product-id="<?php echo $recProduct['id']; ?>"
                                                            title="Add to cart">
                                                        <i class="fas fa-cart-plus"></i>
                                                    </button>
                                                    <?php if (isset($recProduct['compare_price']) && $recProduct['compare_price'] > $recProduct['price']): ?>
                                                        <span class="position-absolute top-0 start-0 m-2 badge bg-danger">
                                                            Save <?php echo number_format((($recProduct['compare_price'] - $recProduct['price']) / $recProduct['compare_price']) * 100, 0); ?>%
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-body">
                                                    <h6 class="card-title text-dark mb-2 text-truncate" style="font-size: 0.9rem;">
                                                        <?php echo htmlspecialchars($recProduct['name']); ?>
                                                    </h6>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="text-blue fw-bold fs-5">
                                                                Ksh <?php echo number_format($recProduct['price'], 2); ?>
                                                            </div>
                                                            <?php if (isset($recProduct['compare_price']) && $recProduct['compare_price'] > $recProduct['price']): ?>
                                                                <div class="text-muted text-decoration-line-through small">
                                                                    Ksh <?php echo number_format($recProduct['compare_price'], 2); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-warning small">
                                                            <i class="fas fa-star"></i>
                                                            <i class="fas fa-star"></i>
                                                            <i class="fas fa-star"></i>
                                                            <i class="fas fa-star"></i>
                                                            <i class="fas fa-star-half-alt"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Add All Button -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="d-flex justify-content-center">
                                        <button type="button" class="btn btn-primary-blue px-5" id="addAllRecommended">
                                            <i class="fas fa-cart-plus me-2"></i> Add All to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No recommendations available at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="cartToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i>
                <span id="toastMessage">Cart updated successfully!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
// Update cart item via AJAX
async function updateCartItem(cartKey, quantity) {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/update-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart_key: cartKey,
                quantity: quantity
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateCartDisplay(data.cart);
            showToast('Cart updated successfully!');
        } else {
            showToast(data.message || 'Failed to update cart', 'error');
        }
    } catch (error) {
        console.error('Update cart error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Remove cart item via AJAX
async function removeCartItem(cartKey) {
    if (!confirm('Are you sure you want to remove this item from your cart?')) {
        return;
    }
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/remove-from-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart_key: cartKey
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove item from DOM
            document.querySelectorAll(`.cart-item[data-cart-key="${cartKey}"]`).forEach(el => el.remove());
            
            updateCartDisplay(data.cart);
            showToast('Item removed from cart');
        } else {
            showToast(data.message || 'Failed to remove item', 'error');
        }
    } catch (error) {
        console.error('Remove cart error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Update cart display
function updateCartDisplay(cartData) {
    console.log('Cart data received:', cartData); // For debugging
    
    // Update cart count in header
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = cartData.count || 0;
        element.classList.toggle('d-none', (cartData.count || 0) === 0);
    });
    
    // Update page title
    const pageTitle = document.querySelector('h1.display-5');
    if (pageTitle && cartData.count > 0) {
        pageTitle.textContent = `Shopping Cart (${cartData.count} item${cartData.count !== 1 ? 's' : ''})`;
    }
    
    // Update "You have X items" text
    const cartStatusText = document.querySelector('.lead.text-muted');
    if (cartStatusText) {
        if (cartData.count > 0) {
            cartStatusText.textContent = `You have ${cartData.count} item${cartData.count !== 1 ? 's' : ''} in your cart`;
        } else {
            cartStatusText.textContent = 'Your cart is empty';
        }
    }
    
    // Update order summary
    if (document.getElementById('subtotalAmount')) {
        document.getElementById('subtotalAmount').textContent = 'Ksh ' + (cartData.subtotal || 0).toFixed(2);
        document.getElementById('shippingAmount').innerHTML = cartData.shipping > 0 
            ? 'Ksh ' + (cartData.shipping || 0).toFixed(2) 
            : '<span class="text-success">FREE</span>';
        document.getElementById('taxAmount').textContent = 'Ksh ' + (cartData.tax || 0).toFixed(2);
        document.getElementById('totalAmount').textContent = 'Ksh ' + (cartData.total || 0).toFixed(2);
    }
    
    // Update individual item totals in the table
    updateIndividualItemTotals(cartData);
    
    // If cart is empty, reload page after delay
    if (cartData.count === 0) {
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }
}

// New function to update individual item totals
function updateIndividualItemTotals(cartData) {
    // Only update if we have items data
    if (!cartData.items || !Array.isArray(cartData.items)) {
        console.log('No items data to update');
        return;
    }
    
    console.log('Updating individual items:', cartData.items);
    
    // Update each item in the table
    cartData.items.forEach(cartItem => {
        const cartKey = cartItem.cart_key;
        if (!cartKey) return;
        
        // Find the item row by cart_key
        const itemRow = document.querySelector(`.cart-item[data-cart-key="${cartKey}"]`);
        if (!itemRow) {
            console.log('Item row not found for cart key:', cartKey);
            return;
        }
        
        // Ensure price is a number
        const itemPrice = parseFloat(cartItem.price) || 0;
        const itemTotal = parseFloat(cartItem.total) || 0;
        const itemQuantity = parseInt(cartItem.quantity) || 1;
        
        // Update the item total display
        const itemTotalElement = itemRow.querySelector('.item-total');
        if (itemTotalElement) {
            itemTotalElement.textContent = 'Ksh ' + itemTotal.toFixed(2);
        }
        
        // Update the price display
        const priceElement = itemRow.querySelector('.price');
        if (priceElement) {
            priceElement.textContent = 'Ksh ' + itemPrice.toFixed(2);
        }
        
        // Update the quantity input (but only if it hasn't been manually changed)
        const quantityInput = itemRow.querySelector('.quantity-input');
        if (quantityInput) {
            const currentValue = parseInt(quantityInput.value) || 1;
            const originalValue = parseInt(quantityInput.dataset.originalQuantity) || 1;
            
            // Only update if the user hasn't changed it locally
            if (currentValue === originalValue) {
                quantityInput.value = itemQuantity;
                quantityInput.dataset.originalQuantity = itemQuantity;
            }
        }
    });
}
// Update cart item via AJAX
async function updateCartItem(cartKey, quantity) {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/update-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart_key: cartKey,
                quantity: quantity
            })
        });
        
        // Check if response is OK
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Server error:', errorText);
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }
        
        const data = await response.json();
        console.log('Update cart response:', data);
        
        if (data.success) {
            // Update the specific item's total immediately
            const itemRow = document.querySelector(`.cart-item[data-cart-key="${cartKey}"]`);
            if (itemRow && data.cart && data.cart.items) {
                const cartItem = data.cart.items.find(item => item.cart_key === cartKey);
                if (cartItem) {
                    const itemTotal = parseFloat(cartItem.total) || 0;
                    const itemTotalElement = itemRow.querySelector('.item-total');
                    if (itemTotalElement) {
                        itemTotalElement.textContent = 'Ksh ' + itemTotal.toFixed(2);
                    }
                    
                    // Update original quantity
                    const quantityInput = itemRow.querySelector('.quantity-input');
                    if (quantityInput) {
                        quantityInput.dataset.originalQuantity = quantity;
                    }
                }
            }
            
            // Update the full cart display
            updateCartDisplay(data.cart);
            showToast('Cart updated successfully!');
        } else {
            showToast(data.message || 'Failed to update cart', 'error');
        }
    } catch (error) {
        console.error('Update cart error:', error);
        showToast(error.message || 'Something went wrong. Please try again.', 'error');
    }
}
// Clear cart
async function clearCart() {
    if (!confirm('Are you sure you want to clear your entire cart? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/clear-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Cart cleared successfully!');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Failed to clear cart', 'error');
        }
    } catch (error) {
        console.error('Clear cart error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Calculate shipping
// async function calculateShipping() {
//     const postalCode = document.getElementById('postalCode').value.trim();
//     const subtotal = <?php echo $subtotal; ?>;
    
//     if (!postalCode) {
//         showToast('Please enter a postal code', 'error');
//         return;
//     }
    
//     try {
//         const response = await fetch('<?php echo SITE_URL; ?>ajax/calculate-shipping.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//             },
//             body: JSON.stringify({
//                 postal_code: postalCode,
//                 subtotal: subtotal
//             })
//         });
        
//         const data = await response.json();
//         const resultDiv = document.getElementById('shippingEstimateResult');
        
//         if (data.success) {
//             resultDiv.innerHTML = `
//                 <div class="alert alert-success p-2 mb-0">
//                     <i class="fas fa-check-circle me-2"></i>
//                     Shipping to ${data.area}: ${data.shipping === 0 ? 'FREE' : 'Ksh ' + data.shipping.toFixed(2)}
//                     <br><small>Estimated delivery: ${data.estimated_days} business days</small>
//                 </div>
//             `;
            
//             // Update shipping in summary
//             if (document.getElementById('shippingAmount')) {
//                 document.getElementById('shippingAmount').innerHTML = data.shipping === 0 
//                     ? '<span class="text-success">FREE</span>' 
//                     : 'Ksh ' + data.shipping.toFixed(2);
                
//                 // Update total
//                 const subtotal = <?php echo $subtotal; ?>;
//                 const tax = subtotal * 0.16;
//                 const newTotal = subtotal + data.shipping + tax;
//                 document.getElementById('totalAmount').textContent = 'Ksh ' + newTotal.toFixed(2);
//             }
//         } else {
//             resultDiv.innerHTML = `
//                 <div class="alert alert-danger p-2 mb-0">
//                     <i class="fas fa-exclamation-circle me-2"></i>
//                     ${data.message || 'Unable to calculate shipping'}
//                 </div>
//             `;
//         }
//     } catch (error) {
//         console.error('Calculate shipping error:', error);
//         showToast('Something went wrong. Please try again.', 'error');
//     }
// }

// Apply discount
async function applyDiscount() {
    const code = document.getElementById('discountCode').value.trim();
    
    if (!code) {
        showToast('Please enter a discount code', 'error');
        return;
    }
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/apply-discount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                code: code,
                subtotal: <?php echo $subtotal; ?>
            })
        });
        
        const data = await response.json();
        const resultDiv = document.getElementById('discountResult');
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success p-2 mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    ${data.message || 'Discount applied!'}
                    ${data.discount_amount ? `<br><small>Discount: Ksh ${data.discount_amount.toFixed(2)}</small>` : ''}
                </div>
            `;
            
            // Update totals if provided
            if (data.new_total !== undefined && document.getElementById('totalAmount')) {
                document.getElementById('totalAmount').textContent = 'Ksh ' + data.new_total.toFixed(2);
            }
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger p-2 mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${data.message || 'Invalid discount code'}
                </div>
            `;
        }
    } catch (error) {
        console.error('Apply discount error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Update checkout button state
function updateCheckoutButton() {
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (!checkoutBtn) return;
    
    const selectedItems = document.querySelectorAll('.select-item:checked').length;
    const totalItems = document.querySelectorAll('.select-item').length;
    
    if (selectedItems === 0) {
        checkoutBtn.disabled = true;
        checkoutBtn.classList.add('disabled');
        checkoutBtn.setAttribute('aria-disabled', 'true');
    } else {
        checkoutBtn.disabled = false;
        checkoutBtn.classList.remove('disabled');
        checkoutBtn.removeAttribute('aria-disabled');
        
        // Update href to include selected items
        const selectedIds = Array.from(document.querySelectorAll('.select-item:checked'))
            .map(cb => cb.value)
            .join(',');
        
        if (selectedIds && selectedItems < totalItems) {
            checkoutBtn.href = `<?php echo SITE_URL; ?>cart/checkout?items=${encodeURIComponent(selectedIds)}`;
        } else {
            checkoutBtn.href = `<?php echo SITE_URL; ?>cart/checkout`;
        }
    }
}

// Add all recommended products
async function addAllRecommended() {
    // Get product IDs from data attributes on recommended products
    const productIds = [];
    document.querySelectorAll('[data-recommended-id]').forEach(el => {
        productIds.push(el.dataset.recommendedId);
    });
    
    if (productIds.length === 0) {
        showToast('No recommended products available', 'info');
        return;
    }
    
    if (!confirm(`Add ${productIds.length} recommended products to your cart?`)) {
        return;
    }
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/add-multiple-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_ids: productIds,
                quantity: 1
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(`${productIds.length} products added to cart!`);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to add products', 'error');
        }
    } catch (error) {
        console.error('Add multiple products error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Show toast notification
function showToast(message, type = 'success') {
    const toastElement = document.getElementById('cartToast');
    const toastMessage = document.getElementById('toastMessage');
    
    if (!toastElement || !toastMessage) return;
    
    toastMessage.textContent = message;
    
    // Remove all previous classes
    toastElement.className = 'toast align-items-center text-white border-0';
    
    // Add appropriate class
    if (type === 'success') {
        toastElement.classList.add('bg-success');
    } else if (type === 'error') {
        toastElement.classList.add('bg-danger');
    } else if (type === 'info') {
        toastElement.classList.add('bg-info');
    } else {
        toastElement.classList.add('bg-primary');
    }
    
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap toasts
    const toastEl = document.getElementById('cartToast');
    const cartToast = toastEl ? new bootstrap.Toast(toastEl) : null;
    
    // Quantity controls
    document.querySelectorAll('.decrease-qty').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cartKey = this.dataset.cartKey;
            const input = document.querySelector(`.quantity-input[data-cart-key="${cartKey}"]`);
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                updateCartItem(cartKey, input.value);
            }
        });
    });
    
    document.querySelectorAll('.increase-qty').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cartKey = this.dataset.cartKey;
            const input = document.querySelector(`.quantity-input[data-cart-key="${cartKey}"]`);
            if (parseInt(input.value) < 99) {
                input.value = parseInt(input.value) + 1;
                updateCartItem(cartKey, input.value);
            }
        });
    });
    
    // Quantity input change
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function(e) {
            e.preventDefault();
            const cartKey = this.dataset.cartKey;
            const quantity = parseInt(this.value) || 1;
            if (quantity >= 1 && quantity <= 99) {
                updateCartItem(cartKey, quantity);
            } else {
                this.value = 1;
                updateCartItem(cartKey, 1);
            }
        });
    });
    
    // Remove item
    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cartKey = this.dataset.cartKey;
            removeCartItem(cartKey);
        });
    });
    
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.select-item');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateCheckoutButton();
        });
    }
    
    // Individual item checkbox
    document.querySelectorAll('.select-item').forEach(cb => {
        cb.addEventListener('change', function() {
            // Update "Select All" checkbox state
            const allChecked = document.querySelectorAll('.select-item:checked').length === document.querySelectorAll('.select-item').length;
            const someChecked = document.querySelectorAll('.select-item:checked').length > 0;
            
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            }
            
            updateCheckoutButton();
        });
    });
    
    // Update cart button
    const updateCartBtn = document.getElementById('updateCart');
    if (updateCartBtn) {
        updateCartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            updateCart();
        });
    }
    
    // Clear cart button
    const clearCartBtn = document.getElementById('clearCart');
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearCart();
        });
    }
    
    // Calculate shipping
    // const calculateShippingBtn = document.getElementById('calculateShipping');
    // if (calculateShippingBtn) {
    //     calculateShippingBtn.addEventListener('click', function(e) {
    //         e.preventDefault();
    //         calculateShipping();
    //     });
        
    //     // Allow pressing Enter in postal code field
    //     document.getElementById('postalCode')?.addEventListener('keypress', function(e) {
    //         if (e.key === 'Enter') {
    //             e.preventDefault();
    //             calculateShipping();
    //         }
    //     });
    // }
    
    // Apply discount
    const applyDiscountBtn = document.getElementById('applyDiscount');
    if (applyDiscountBtn) {
        applyDiscountBtn.addEventListener('click', function(e) {
            e.preventDefault();
            applyDiscount();
        });
        
        // Allow pressing Enter in discount field
        document.getElementById('discountCode')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyDiscount();
            }
        });
    }
    
    // Add all recommended products
    const addAllRecommendedBtn = document.getElementById('addAllRecommended');
    if (addAllRecommendedBtn) {
        addAllRecommendedBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addAllRecommended();
        });
    }
    
    // Add recommended product button
    document.querySelectorAll('.add-recommended-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productId = this.dataset.productId;
            if (productId) {
                addRecommendedProduct(productId);
            }
        });
    });
    
    // Initialize checkout button state
    updateCheckoutButton();
});

// Add single recommended product
async function addRecommendedProduct(productId) {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: 1
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Product added to cart!');
            if (data.cart_count !== undefined) {
                updateCartDisplay(data.cart);
            }
        } else {
            showToast(data.message || 'Failed to add product', 'error');
        }
    } catch (error) {
        console.error('Add recommended product error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

// Initialize original quantity data attributes
document.querySelectorAll('.quantity-input').forEach(input => {
    if (!input.dataset.originalQuantity) {
        input.dataset.originalQuantity = input.value;
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>