<?php
// /linen-closet/cart/index.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

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
$cartCount = count($cart);
$subtotal = 0;
$shipping = 0;
$tax = 0;
$total = 0;

// Fetch cart items with product details
$cartItems = [];
if (!empty($cart)) {
    $productIds = array_keys($cart);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $stmt = $db->prepare("
        SELECT 
            p.*,
            (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
            c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders) AND p.is_active = 1
    ");
    
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as $product) {
        $itemId = $product['id'];
        $cartItem = $cart[$itemId];
        $quantity = $cartItem['quantity'] ?? 1;
        $price = $product['price'];
        $itemTotal = $price * $quantity;
        
        $cartItems[] = [
            'id' => $itemId,
            'product' => $product,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $itemTotal,
            'size' => $cartItem['size'] ?? null,
            'color' => $cartItem['color'] ?? null,
            'material' => $cartItem['material'] ?? null
        ];
        
        $subtotal += $itemTotal;
    }
    
    // Calculate shipping (example: free over 5000, otherwise 300)
    $shipping = ($subtotal >= 5000) ? 0 : 300;
    
    // Calculate tax (example: 16% VAT)
    $tax = $subtotal * 0.16;
    
    $total = $subtotal + $shipping + $tax;
}

$pageTitle = "Shopping Cart (" . $cartCount . " items)";

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
            <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <h1 class="display-5 fw-bold mb-3">Shopping Cart</h1>
            <p class="text-muted lead">
                <?php if ($cartCount > 0): ?>
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
                                <thead class="table-light">
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
                                        <tr class="cart-item" data-product-id="<?php echo $item['id']; ?>">
                                            <td>
                                                <input type="checkbox" 
                                                       class="form-check-input select-item" 
                                                       value="<?php echo $item['id']; ?>"
                                                       checked>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                                        <img src="<?php echo $imageUrl; ?>" 
                                                             class="rounded me-3" 
                                                             style="width: 80px; height: 80px; object-fit: cover;"
                                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                    </a>
                                                    <div>
                                                        <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-dark fw-bold">
                                                            <?php echo htmlspecialchars($product['name']); ?>
                                                        </a>
                                                        <?php if ($item['size']): ?>
                                                            <div class="text-muted small mt-1">
                                                                <span class="me-3">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                                                <?php if ($item['color']): ?>
                                                                    <span>Color: <?php echo htmlspecialchars($item['color']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="text-muted small">
                                                            SKU: <?php echo htmlspecialchars($product['sku']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="price fw-bold">
                                                    Ksh <?php echo number_format($item['price'], 2); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="quantity-selector d-inline-flex align-items-center">
                                                    <button type="button" 
                                                            class="btn btn-outline-dark btn-sm decrease-qty"
                                                            data-product-id="<?php echo $item['id']; ?>">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <input type="number" 
                                                           class="form-control text-center border-dark rounded-0 quantity-input"
                                                           value="<?php echo $item['quantity']; ?>"
                                                           min="1" 
                                                           max="99"
                                                           style="width: 60px;"
                                                           data-product-id="<?php echo $item['id']; ?>"
                                                           data-original-quantity="<?php echo $item['quantity']; ?>">
                                                    <button type="button" 
                                                            class="btn btn-outline-dark btn-sm increase-qty"
                                                            data-product-id="<?php echo $item['id']; ?>">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="item-total fw-bold text-dark">
                                                    Ksh <?php echo number_format($item['total'], 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-sm remove-item"
                                                        data-product-id="<?php echo $item['id']; ?>"
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
                        <div class="card border-0 shadow-sm mb-3 cart-item" data-product-id="<?php echo $item['id']; ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-3">
                                        <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                            <img src="<?php echo $imageUrl; ?>" 
                                                 class="rounded w-100" 
                                                 style="height: 100px; object-fit: cover;"
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        </a>
                                    </div>
                                    <div class="col-9">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-dark fw-bold">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </a>
                                                <?php if ($item['size']): ?>
                                                    <div class="text-muted small mt-1">
                                                        Size: <?php echo htmlspecialchars($item['size']); ?>
                                                        <?php if ($item['color']): ?>
                                                            • Color: <?php echo htmlspecialchars($item['color']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm remove-item"
                                                    data-product-id="<?php echo $item['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="quantity-selector d-flex align-items-center">
                                                <button type="button" 
                                                        class="btn btn-outline-dark btn-sm decrease-qty"
                                                        data-product-id="<?php echo $item['id']; ?>">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" 
                                                       class="form-control text-center border-dark rounded-0 quantity-input"
                                                       value="<?php echo $item['quantity']; ?>"
                                                       min="1" 
                                                       max="99"
                                                       style="width: 50px;"
                                                       data-product-id="<?php echo $item['id']; ?>"
                                                       data-original-quantity="<?php echo $item['quantity']; ?>">
                                                <button type="button" 
                                                        class="btn btn-outline-dark btn-sm increase-qty"
                                                        data-product-id="<?php echo $item['id']; ?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold">
                                                    Ksh <?php echo number_format($item['price'], 2); ?>
                                                </div>
                                                <div class="item-total fw-bold text-dark">
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
                        <input type="checkbox" id="selectAll" class="form-check-input me-2" checked>
                        <label for="selectAll" class="form-check-label fw-medium">Select All</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-dark" id="updateCart">
                            <i class="fas fa-sync-alt me-2"></i> Update Cart
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="clearCart">
                            <i class="fas fa-trash-alt me-2"></i> Clear Cart
                        </button>
                    </div>
                </div>
                
                <!-- Continue Shopping -->
                <div class="mt-4">
                    <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-dark">
                        <i class="fas fa-arrow-left me-2"></i> Continue Shopping
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Empty Cart -->
                <div class="text-center py-5">
                    <div class="py-5">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                        <h3 class="fw-bold mb-3">Your cart is empty</h3>
                        <p class="text-muted mb-4 lead">Looks like you haven't added any products to your cart yet.</p>
                        <a href="<?php echo SITE_URL; ?>products" class="btn btn-dark btn-lg px-5">
                            <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Order Summary -->
        <?php if ($cartCount > 0): ?>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 100px;">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <!-- Summary Items -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span class="fw-bold" id="subtotalAmount">
                                    Ksh <?php echo number_format($subtotal, 2); ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Shipping</span>
                                <span class="fw-bold" id="shippingAmount">
                                    <?php if ($shipping > 0): ?>
                                        Ksh <?php echo number_format($shipping, 2); ?>
                                    <?php else: ?>
                                        <span class="text-success">FREE</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Tax (16% VAT)</span>
                                <span class="fw-bold" id="taxAmount">
                                    Ksh <?php echo number_format($tax, 2); ?>
                                </span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-bold fs-5">Total</span>
                                <span class="fw-bold fs-5 text-dark" id="totalAmount">
                                    Ksh <?php echo number_format($total, 2); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Shipping Estimate -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Shipping Estimate</h6>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       placeholder="Enter postal code"
                                       id="postalCode">
                                <button class="btn btn-outline-dark" type="button" id="calculateShipping">
                                    Calculate
                                </button>
                            </div>
                            <div id="shippingEstimateResult" class="mt-2"></div>
                        </div>
                        
                        <!-- Discount Code -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Discount Code</h6>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       placeholder="Enter discount code"
                                       id="discountCode">
                                <button class="btn btn-outline-dark" type="button" id="applyDiscount">
                                    Apply
                                </button>
                            </div>
                            <div id="discountResult" class="mt-2"></div>
                        </div>
                        
                        <!-- Checkout Button -->
                        <a href="<?php echo SITE_URL; ?>cart/checkout" 
                           class="btn btn-dark btn-lg w-100 py-3 mb-3"
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
                
                <!-- Recommended Products -->
                <?php if (!empty($cartItems)): ?>
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="fw-bold mb-0">
                                <i class="fas fa-fire text-danger me-2"></i> Frequently Bought Together
                            </h6>
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
                                $cartProductIds = array_keys($cart);
                                
                                $recommendedStmt = $db->prepare("
                                    SELECT 
                                        p.*,
                                        (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
                                    FROM products p
                                    WHERE p.category_id IN ($placeholders) 
                                      AND p.is_active = 1
                                      AND p.id NOT IN (" . (!empty($cartProductIds) ? implode(',', array_fill(0, count($cartProductIds), '?')) : '0') . ")
                                    ORDER BY RAND()
                                    LIMIT 3
                                ");
                                
                                $params = array_merge($categoryIds, $cartProductIds);
                                $recommendedStmt->execute($params);
                                $recommendedProducts = $recommendedStmt->fetchAll(PDO::FETCH_ASSOC);
                            }
                            ?>
                            
                            <?php if (!empty($recommendedProducts)): ?>
                                <div class="row g-2">
                                    <?php foreach ($recommendedProducts as $recProduct): ?>
                                        <div class="col-4" data-recommended-id="<?php echo $recProduct['id']; ?>">
                                            <a href="<?php echo SITE_URL; ?>products/detail.php?slug=<?php echo $recProduct['slug']; ?>" 
                                               class="text-decoration-none">
                                                <img src="<?php echo SITE_URL . ($recProduct['primary_image'] ?: 'assets/images/placeholder.jpg'); ?>" 
                                                     class="rounded w-100" 
                                                     style="height: 80px; object-fit: cover;"
                                                     alt="<?php echo htmlspecialchars($recProduct['name']); ?>">
                                                <small class="d-block text-dark mt-1 text-truncate">
                                                    <?php echo htmlspecialchars($recProduct['name']); ?>
                                                </small>
                                                <small class="text-success fw-bold">
                                                    Ksh <?php echo number_format($recProduct['price'], 2); ?>
                                                </small>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="button" class="btn btn-outline-dark btn-sm w-100 mt-3" id="addAllRecommended">
                                    <i class="fas fa-cart-plus me-2"></i> Add All to Cart
                                </button>
                            <?php else: ?>
                                <p class="text-muted small mb-0">No recommendations available at the moment.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
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

<style>
.cart-item {
    transition: background-color 0.3s ease;
}

.cart-item:hover {
    background-color: #f8f9fa;
}

.quantity-selector input[type="number"] {
    -moz-appearance: textfield;
}

.quantity-selector input[type="number"]::-webkit-outer-spin-button,
.quantity-selector input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.sticky-top {
    position: sticky;
    z-index: 100;
}

.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 768px) {
    .display-5 {
        font-size: 2rem;
    }
    
    .cart-item .quantity-selector {
        width: 120px;
    }
}

#checkoutBtn.disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
</style>

<script>

// Production-ready AJAX handler
async function ajaxRequest(url, data = null, method = 'POST') {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin' // Include cookies for sessions
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || `HTTP ${response.status}`);
        }
        
        return result;
        
    } catch (error) {
        console.error('AJAX Error:', error.message, 'URL:', url);
        
        // Show user-friendly error
        showToast(
            error.message.includes('Failed to fetch') 
                ? 'Network error. Please check your connection.' 
                : 'Server error. Please try again.',
            'error'
        );
        
        throw error;
    }
}

// Updated updateCartItem function
// Update cart item
async function updateCartItem(productId, quantity) {
    try {
        const data = await ajaxRequest(
            '<?php echo SITE_URL; ?>ajax/update-cart.php',
            { product_id: productId, quantity: quantity }
        );
        
        if (data.success) {
            updateCartDisplay(data.cart);
            showToast(data.message || 'Cart updated successfully!');
        } else {
            showToast(data.message || 'Failed to update cart', 'error');
        }
    } catch (error) {
        // Error already handled in ajaxRequest
    }
}

// Remove item
async function removeCartItem(productId) {
    if (!confirm('Are you sure you want to remove this item from your cart?')) {
        return;
    }
    
    try {
        const data = await ajaxRequest(
            '<?php echo SITE_URL; ?>ajax/remove-from-cart.php',
            { product_id: productId }
        );
        
        if (data.success) {
            document.querySelectorAll(`.cart-item[data-product-id="${productId}"]`).forEach(el => el.remove());
            updateCartDisplay(data.cart);
            showToast(data.message || 'Item removed from cart');
        } else {
            showToast(data.message || 'Failed to remove item', 'error');
        }
    } catch (error) {
        // Error handled
    }
}

// Calculate shipping
async function calculateShipping() {
    const postalCode = document.getElementById('postalCode').value.trim();
    const subtotal = <?php echo $subtotal; ?>;
    
    if (!postalCode) {
        showToast('Please enter a postal code', 'error');
        return;
    }
    
    try {
        const data = await ajaxRequest(
            '<?php echo SITE_URL; ?>ajax/calculate-shipping.php',
            { postal_code: postalCode, subtotal: subtotal }
        );
        
        const resultDiv = document.getElementById('shippingEstimateResult');
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success p-2 mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    ${data.message}
                    <br><small>Estimated delivery: ${data.estimated_days} business days</small>
                </div>
            `;
            
            // Update order summary with new shipping
            if (data.shipping !== undefined) {
                document.getElementById('shippingAmount').innerHTML = data.shipping === 0 
                    ? '<span class="text-success">FREE</span>' 
                    : 'Ksh ' + data.shipping.toFixed(2);
                
                // Recalculate total
                const newTotal = <?php echo $subtotal; ?> + data.shipping + (<?php echo $subtotal; ?> * 0.16);
                document.getElementById('totalAmount').textContent = 'Ksh ' + newTotal.toFixed(2);
            }
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger p-2 mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${data.message}
                </div>
            `;
        }
    } catch (error) {
        // Error handled
    }
}

// Apply discount
async function applyDiscount() {
    const code = document.getElementById('discountCode').value.trim();
    const subtotal = <?php echo $subtotal; ?>;
    
    if (!code) {
        showToast('Please enter a discount code', 'error');
        return;
    }
    
    try {
        const data = await ajaxRequest(
            '<?php echo SITE_URL; ?>ajax/apply-discount.php',
            { code: code, subtotal: subtotal }
        );
        
        const resultDiv = document.getElementById('discountResult');
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success p-2 mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    ${data.message}
                    ${data.discount?.amount ? `<br><small>Discount: Ksh ${data.discount.amount.toFixed(2)}</small>` : ''}
                </div>
            `;
            
            // Update order summary with new totals
            if (data.new_totals) {
                document.getElementById('subtotalAmount').textContent = 'Ksh ' + data.new_totals.subtotal.toFixed(2);
                document.getElementById('shippingAmount').innerHTML = data.new_totals.shipping === 0 
                    ? '<span class="text-success">FREE</span>' 
                    : 'Ksh ' + data.new_totals.shipping.toFixed(2);
                document.getElementById('taxAmount').textContent = 'Ksh ' + data.new_totals.tax.toFixed(2);
                document.getElementById('totalAmount').textContent = 'Ksh ' + data.new_totals.total.toFixed(2);
            }
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger p-2 mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${data.message}
                </div>
            `;
        }
    } catch (error) {
        // Error handled
    }
}
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap toasts
    const toastEl = document.getElementById('cartToast');
    const cartToast = toastEl ? new bootstrap.Toast(toastEl) : null;
    
    // Quantity controls
    document.querySelectorAll('.decrease-qty').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                updateCartItem(productId, input.value);
            }
        });
    });
    
    document.querySelectorAll('.increase-qty').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
            if (parseInt(input.value) < 99) {
                input.value = parseInt(input.value) + 1;
                updateCartItem(productId, input.value);
            }
        });
    });
    
    // Quantity input change
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            const quantity = parseInt(this.value) || 1;
            if (quantity >= 1 && quantity <= 99) {
                updateCartItem(productId, quantity);
            } else {
                this.value = 1;
                updateCartItem(productId, 1);
            }
        });
    });
    
    // Remove item
    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            removeCartItem(productId);
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
    const calculateShippingBtn = document.getElementById('calculateShipping');
    if (calculateShippingBtn) {
        calculateShippingBtn.addEventListener('click', function(e) {
            e.preventDefault();
            calculateShipping();
        });
        
        // Allow pressing Enter in postal code field
        document.getElementById('postalCode')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                calculateShipping();
            }
        });
    }
    
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
    
    // Initialize checkout button state
    updateCheckoutButton();
});

// Update cart item via AJAX
async function updateCartItem(productId, quantity) {
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/update-cart.php', {
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
async function removeCartItem(productId) {
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
                product_id: productId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove item from DOM
            document.querySelectorAll(`.cart-item[data-product-id="${productId}"]`).forEach(el => el.remove());
            
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
    // Update cart count in header
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = cartData.count;
        element.classList.toggle('d-none', cartData.count === 0);
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
        document.getElementById('subtotalAmount').textContent = 'Ksh ' + cartData.subtotal.toFixed(2);
        document.getElementById('shippingAmount').innerHTML = cartData.shipping > 0 
            ? 'Ksh ' + cartData.shipping.toFixed(2) 
            : '<span class="text-success">FREE</span>';
        document.getElementById('taxAmount').textContent = 'Ksh ' + cartData.tax.toFixed(2);
        document.getElementById('totalAmount').textContent = 'Ksh ' + cartData.total.toFixed(2);
    }
    
    // Update individual item totals
    document.querySelectorAll('.cart-item').forEach(item => {
        const productId = item.dataset.productId;
        const cartItem = cartData.items.find(i => i.id == productId);
        
        if (cartItem) {
            const itemTotalElement = item.querySelector('.item-total');
            if (itemTotalElement) {
                itemTotalElement.textContent = 'Ksh ' + cartItem.total.toFixed(2);
            }
            
            const quantityInput = item.querySelector('.quantity-input');
            if (quantityInput) {
                quantityInput.value = cartItem.quantity;
                quantityInput.dataset.originalQuantity = cartItem.quantity;
            }
        }
    });
    
    // If cart is empty, reload page
    if (cartData.count === 0) {
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }
}

// Update entire cart
async function updateCart() {
    const updates = [];
    let hasChanges = false;
    
    document.querySelectorAll('.cart-item').forEach(item => {
        const productId = item.dataset.productId;
        const quantityInput = item.querySelector('.quantity-input');
        const currentQuantity = parseInt(quantityInput?.value) || 1;
        const checkbox = item.querySelector('.select-item');
        const isSelected = checkbox ? checkbox.checked : true;
        
        // Get original quantity from data attribute
        const originalQuantity = parseInt(quantityInput?.dataset.originalQuantity) || currentQuantity;
        
        if (isSelected && currentQuantity !== originalQuantity) {
            updates.push({
                product_id: productId,
                quantity: currentQuantity
            });
            hasChanges = true;
        }
    });
    
    if (!hasChanges) {
        showToast('No changes to update', 'info');
        return;
    }
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/update-cart-bulk.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                updates: updates
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateCartDisplay(data.cart);
            showToast('Cart updated successfully!');
            
            // Update original quantity data attributes
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.dataset.originalQuantity = input.value;
            });
        } else {
            showToast(data.message || 'Failed to update cart', 'error');
        }
    } catch (error) {
        console.error('Update cart bulk error:', error);
        showToast('Something went wrong. Please try again.', 'error');
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
async function calculateShipping() {
    const postalCode = document.getElementById('postalCode').value.trim();
    const subtotal = <?php echo $subtotal; ?>;
    
    if (!postalCode) {
        showToast('Please enter a postal code', 'error');
        return;
    }
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>ajax/calculate-shipping.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                postal_code: postalCode,
                subtotal: subtotal
            })
        });
        
        const data = await response.json();
        const resultDiv = document.getElementById('shippingEstimateResult');
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success p-2 mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    Shipping to ${data.area}: ${data.shipping === 0 ? 'FREE' : 'Ksh ' + data.shipping.toFixed(2)}
                    <br><small>Estimated delivery: ${data.estimated_days} business days</small>
                </div>
            `;
            
            // Update shipping in summary
            if (document.getElementById('shippingAmount')) {
                document.getElementById('shippingAmount').innerHTML = data.shipping === 0 
                    ? '<span class="text-success">FREE</span>' 
                    : 'Ksh ' + data.shipping.toFixed(2);
                
                // Update total
                const subtotal = <?php echo $subtotal; ?>;
                const tax = subtotal * 0.16;
                const newTotal = subtotal + data.shipping + tax;
                document.getElementById('totalAmount').textContent = 'Ksh ' + newTotal.toFixed(2);
            }
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger p-2 mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${data.message || 'Unable to calculate shipping'}
                </div>
            `;
        }
    } catch (error) {
        console.error('Calculate shipping error:', error);
        showToast('Something went wrong. Please try again.', 'error');
    }
}

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