<?php
// /linen-closet/account/wishlist.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';
require_once __DIR__ . '/../includes/Wishlist.php';

$app = new App();
$db = $app->getDB();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'auth/login.php?redirect=account/wishlist.php');
    exit();
}

$userId = $_SESSION['user_id'];
$pageTitle = "My Wishlist";

// Initialize Wishlist
$wishlist = new Wishlist($db, $userId);

// Debug: Check sync status
if (isset($_GET['debug'])) {
    $debugInfo = $wishlist->debugInfo();
    echo "<pre>Wishlist Debug Info:\n";
    print_r($debugInfo);
    echo "</pre>";
}
// Handle AJAX actions
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        header('Content-Type: application/json');
        
        if ($action === 'remove' && isset($_POST['product_id'])) {
            $productId = intval($_POST['product_id']);
            $success = $wishlist->removeItem($productId);
            $wishlistCount = $wishlist->getCount();
            
            echo json_encode([
                'success' => $success,
                'wishlist_count' => $wishlistCount,
                'message' => $success ? 'Removed from wishlist' : 'Failed to remove'
            ]);
            exit();
            
        } elseif ($action === 'clear') {
            $success = $wishlist->clearWishlist();
            $wishlistCount = $wishlist->getCount();
            
            echo json_encode([
                'success' => $success,
                'wishlist_count' => $wishlistCount,
                'message' => $success ? 'Wishlist cleared' : 'Failed to clear'
            ]);
            exit();
        }
    }
}

// Get wishlist items
$wishlistProducts = $wishlist->getItems();
$wishlistCount = $wishlist->getCount();

// Helper function
function formatPrice($price) {
    return 'Ksh ' . number_format($price, 2);
}

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
                <a href="<?php echo SITE_URL; ?>account/account.php" class="text-decoration-none">My Account</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">My Wishlist</li>
        </ol>
    </nav>
    
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold mb-2">My Wishlist</h1>
                    <p class="text-muted">
                        <?php if ($wishlistCount > 0): ?>
                            You have <span id="wishlist-item-count"><?php echo $wishlistCount; ?></span> item<?php echo $wishlistCount !== 1 ? 's' : ''; ?> in your wishlist
                        <?php else: ?>
                            Your wishlist is empty
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if ($wishlistCount > 0): ?>
                        <button class="btn btn-outline-danger" id="clear-all-wishlist">
                            <i class="fas fa-trash-alt me-2"></i> Clear All
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($wishlistCount > 0): ?>
        <!-- Wishlist Items -->
        <div class="row" id="wishlist-products-container">
            <?php foreach ($wishlistProducts as $product): ?>
                <?php
                $imageUrl = SITE_URL . ($product['primary_image'] ?: 'assets/images/placeholder.jpg');
                $productUrl = SITE_URL . 'products/detail.php?slug=' . $product['slug'];
                $hasVariants = !empty($product['variant_min_price']) && !empty($product['variant_max_price']);
                $priceDisplay = $hasVariants && $product['variant_min_price'] != $product['variant_max_price'] 
                    ? formatPrice($product['variant_min_price']) . " - " . formatPrice($product['variant_max_price'])
                    : formatPrice($product['display_price'] ?? $product['price']);
                
                // FIXED: Proper stock checking - check multiple possible stock fields
                $inStock = false;
                
                // Check product stock quantity
                if (isset($product['stock_quantity']) && $product['stock_quantity'] > 0) {
                    $inStock = true;
                }
                // Check variant stock
                elseif (isset($product['total_variant_stock']) && $product['total_variant_stock'] > 0) {
                    $inStock = true;
                }
                // Check variant_stock (from updated Wishlist class)
                elseif (isset($product['variant_stock']) && $product['variant_stock'] > 0) {
                    $inStock = true;
                }
                // Check total_stock (from updated Wishlist class)
                elseif (isset($product['total_stock']) && $product['total_stock'] > 0) {
                    $inStock = true;
                }
                // Check product_stock (from updated Wishlist class)
                elseif (isset($product['product_stock']) && $product['product_stock'] > 0) {
                    $inStock = true;
                }
                
                // Debug stock info (remove in production)
                // echo "<!-- Product ID: {$product['id']}, Stock: " . ($product['stock_quantity'] ?? 'N/A') . ", Variant Stock: " . ($product['total_variant_stock'] ?? 'N/A') . ", In Stock: " . ($inStock ? 'Yes' : 'No') . " -->";
                ?>
                <div class="col-md-4 col-lg-3 mb-4" id="wishlist-item-<?php echo $product['id']; ?>">
                    <div class="card product-card h-100 border-0 shadow-sm hover-lift overflow-hidden">
                        <!-- Product Image -->
                        <div class="position-relative overflow-hidden" style="height: 200px;">
                            <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                <img src="<?php echo $imageUrl; ?>" 
                                     class="card-img-top h-100 w-100 object-fit-cover product-image" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     loading="lazy">
                            </a>
                            
                            <!-- Remove Button -->
                            <button class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2 remove-wishlist-btn" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    title="Remove from wishlist">
                                <i class="fas fa-times"></i>
                            </button>
                            
                            <?php if (!$inStock): ?>
                                <span class="badge bg-warning position-absolute top-0 start-0 m-2">
                                    <i class="fas fa-exclamation-circle me-1"></i>Out of Stock
                                </span>
                            <?php else: ?>
                                <!-- Show in-stock badge if you want -->
                                <span class="badge bg-success position-absolute top-0 start-0 m-2">
                                    <i class="fas fa-check-circle me-1"></i>In Stock
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="card-body d-flex flex-column p-3">
                            <!-- Category -->
                            <div class="mb-2">
                                <?php if (!empty($product['category_name'])): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo htmlspecialchars($product['category_name']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Product Title -->
                            <h6 class="card-title fw-bold mb-2">
                                <a href="<?php echo $productUrl; ?>" 
                                   class="text-decoration-none text-dark text-truncate-2">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h6>
                            
                            <!-- Stock Info (optional) -->
                            <?php if ($inStock && isset($product['stock_quantity'])): ?>
                                <div class="mb-2">
                                    <small class="text-success">
                                        <i class="fas fa-box me-1"></i>
                                        <?php echo $product['stock_quantity']; ?> available
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Price -->
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h5 class="text-primary fw-bold mb-0"><?php echo $priceDisplay; ?></h5>
                                        <?php if (isset($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                                            <small class="text-muted text-decoration-line-through">
                                                <?php echo formatPrice($product['compare_price']); ?>
                                            </small>
                                            <?php 
                                                $discountPercent = round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100);
                                            ?>
                                            <span class="badge bg-danger ms-2">Save <?php echo $discountPercent; ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Add to Cart Button -->
                                <div class="d-grid gap-2">
                                    <?php if ($inStock): ?>
                                        <button class="btn btn-primary w-100 add-to-cart-btn"
                                                data-product-id="<?php echo $product['id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                            <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary w-100 notify-btn"
                                                data-product-id="<?php echo $product['id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                            <i class="fas fa-bell me-2"></i> Notify When Available
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Wishlist Summary -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-1">Wishlist Summary</h6>
                                <p class="text-muted small mb-0">
                                    Total items: <span id="wishlist-total-count"><?php echo $wishlistCount; ?></span> • 
                                    Total value: Ksh <span id="wishlist-total-value">
                                        <?php 
                                            // Calculate total value properly
                                            $totalValue = 0;
                                            foreach ($wishlistProducts as $product) {
                                                $price = $product['display_price'] ?? $product['price'] ?? 0;
                                                $totalValue += $price;
                                            }
                                            echo number_format($totalValue, 2); 
                                        ?>
                                    </span>
                                </p>
                                <p class="text-muted small mb-0 mt-1">
                                    <?php 
                                        // Count in-stock items
                                        $inStockCount = 0;
                                        foreach ($wishlistProducts as $product) {
                                            // Use same stock check logic
                                            $stock = false;
                                            if (isset($product['stock_quantity']) && $product['stock_quantity'] > 0) $stock = true;
                                            elseif (isset($product['total_variant_stock']) && $product['total_variant_stock'] > 0) $stock = true;
                                            elseif (isset($product['variant_stock']) && $product['variant_stock'] > 0) $stock = true;
                                            elseif (isset($product['total_stock']) && $product['total_stock'] > 0) $stock = true;
                                            elseif (isset($product['product_stock']) && $product['product_stock'] > 0) $stock = true;
                                            
                                            if ($stock) $inStockCount++;
                                        }
                                    ?>
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    <span class="text-success"><?php echo $inStockCount; ?></span> in stock • 
                                    <i class="fas fa-exclamation-circle text-warning me-1"></i>
                                    <span class="text-warning"><?php echo $wishlistCount - $inStockCount; ?></span> out of stock
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-primary">
                                    <i class="fas fa-plus me-2"></i> Add More Items
                                </a>
                                <button class="btn btn-primary" id="add-all-to-cart" 
                                        <?php echo $inStockCount === 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-cart-plus me-2"></i> Add All to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Empty Wishlist -->
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="empty-state-icon mb-4">
                        <i class="fas fa-heart fa-4x text-primary opacity-25"></i>
                    </div>
                    <h3 class="fw-bold mb-3 text-gradient-primary">Your wishlist is empty</h3>
                    <p class="lead text-muted mb-4">
                        Save items you love for later. They'll appear here!
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="<?php echo SITE_URL; ?>products" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                        </a>
                        <a href="<?php echo SITE_URL; ?>account" class="btn btn-outline-primary btn-lg px-5">
                            <i class="fas fa-user me-2"></i> Back to Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<style>
.product-card {
    transition: all 0.3s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
}

.remove-wishlist-btn {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.product-card:hover .remove-wishlist-btn {
    opacity: 1;
}

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto;
    display: flex;
    align-items-center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-light), #fff);
    border-radius: 50%;
}

.fade-out {
    animation: fadeOut 0.3s ease forwards;
}

@keyframes fadeOut {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.9); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Remove from wishlist buttons
    document.querySelectorAll('.remove-wishlist-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            removeFromWishlist(productId, productName, this);
        });
    });
    
    // Clear all wishlist
    document.getElementById('clear-all-wishlist')?.addEventListener('click', function() {
        if (confirm('Clear your entire wishlist?')) {
            clearEntireWishlist();
        }
    });
    
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            addToCart(productId, 1, productName);
        });
    });
    
    // Add all to cart
    document.getElementById('add-all-to-cart')?.addEventListener('click', function() {
        if (confirm('Add all wishlist items to cart?')) {
            const addButtons = document.querySelectorAll('.add-to-cart-btn:not(:disabled)');
            let completed = 0;
            
            addButtons.forEach(btn => {
                const productId = btn.dataset.productId;
                const productName = btn.dataset.productName;
                
                addToCart(productId, 1, productName).then(success => {
                    completed++;
                    
                    if (completed === addButtons.length) {
                        showToast('All available items added to cart!', 'success');
                        
                        // Redirect to cart after delay
                        setTimeout(() => {
                            window.location.href = '<?php echo SITE_URL; ?>cart';
                        }, 1500);
                    }
                });
            });
            
            if (addButtons.length === 0) {
                showToast('No items in stock to add to cart', 'warning');
            }
        }
    });
    
    // Remove from wishlist function
    async function removeFromWishlist(productId, productName, button) {
        const productCard = button.closest('.col-md-4');
        
        // Show loading
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        try {
            const response = await fetch('<?php echo SITE_URL; ?>ajax/wishlist-actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'remove',
                    product_id: productId
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Animate removal
                productCard.classList.add('fade-out');
                
                setTimeout(() => {
                    productCard.remove();
                    
                    // Update counts
                    updateWishlistCounts(data.wishlist_count);
                    
                    // Show message
                    showToast(`"${productName}" removed from wishlist`, 'success');
                    
                    // Check if wishlist is now empty
                    const remainingItems = document.querySelectorAll('#wishlist-products-container .col-md-4').length;
                    if (remainingItems === 0) {
                        // Show empty state without reload
                        showEmptyState();
                    }
                }, 300);
                
            } else {
                button.innerHTML = originalHTML;
                button.disabled = false;
                showToast(data.message || 'Failed to remove', 'error');
            }
            
        } catch (error) {
            console.error('Error:', error);
            button.innerHTML = originalHTML;
            button.disabled = false;
            showToast('Something went wrong', 'error');
        }
    }
    
    // Clear entire wishlist
    async function clearEntireWishlist() {
        const clearButton = document.getElementById('clear-all-wishlist');
        if (!clearButton) return;
        
        const originalHTML = clearButton.innerHTML;
        
        // Show loading
        clearButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Clearing...';
        clearButton.disabled = true;
        
        try {
            const response = await fetch('<?php echo SITE_URL; ?>ajax/wishlist-actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'clear'
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Update counts
                updateWishlistCounts(0);
                
                // Show success message
                showToast('Wishlist cleared', 'success');
                
                // Show empty state
                showEmptyState();
                
            } else {
                clearButton.innerHTML = originalHTML;
                clearButton.disabled = false;
                showToast(data.message || 'Failed to clear wishlist', 'error');
            }
            
        } catch (error) {
            console.error('Clear wishlist error:', error);
            clearButton.innerHTML = originalHTML;
            clearButton.disabled = false;
            showToast('Something went wrong', 'error');
        }
    }
    
    // Show empty state
    function showEmptyState() {
        const container = document.getElementById('wishlist-products-container');
        const summary = document.querySelector('.row.mt-4');
        const clearBtn = document.getElementById('clear-all-wishlist');
        
        if (container) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="text-center py-5">
                        <div class="empty-state-icon mb-4">
                            <i class="fas fa-heart fa-4x text-primary opacity-25"></i>
                        </div>
                        <h3 class="fw-bold mb-3 text-gradient-primary">Your wishlist is empty</h3>
                        <p class="lead text-muted mb-4">
                            Save items you love for later. They'll appear here!
                        </p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="<?php echo SITE_URL; ?>products" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }
        
        if (summary) summary.style.display = 'none';
        if (clearBtn) clearBtn.style.display = 'none';
        
        // Update page title
        const pageTitle = document.querySelector('h1.display-5');
        if (pageTitle) pageTitle.textContent = 'My Wishlist';
        
        const desc = document.querySelector('.text-muted');
        if (desc) desc.textContent = 'Your wishlist is empty';
    }
    
    // Add to cart function
    async function addToCart(productId, quantity, productName = '') {
        const button = document.querySelector(`.add-to-cart-btn[data-product-id="${productId}"]`);
        const originalHTML = button ? button.innerHTML : '';
        
        if (button) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            button.disabled = true;
        }
        
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
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const message = productName 
                    ? `<strong>${productName}</strong> added to cart!` 
                    : 'Added to cart!';
                showToast(message, 'success');
                
                // Update cart count in header
                updateCartCount(data.cart_count || data.cart?.count || 0);
                
                if (button) {
                    button.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
                    setTimeout(() => {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }, 1500);
                }
                
                return true;
            } else {
                showToast(data.message || 'Failed to add to cart', 'error');
                if (button) {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                }
                return false;
            }
        } catch (error) {
            console.error('Add to cart error:', error);
            showToast('Something went wrong', 'error');
            if (button) {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }
            return false;
        }
    }
    
    // Update wishlist counts
    function updateWishlistCounts(count) {
        // Update page count displays
        document.querySelectorAll('#wishlist-item-count, #wishlist-total-count').forEach(el => {
            el.textContent = count;
        });
        
        // Update header wishlist count
        document.querySelectorAll('.wishlist-count').forEach(element => {
            element.textContent = count;
            element.style.display = count > 0 ? 'inline-block' : 'none';
        });
        
        // Update floating wishlist button
        const floatingBtn = document.getElementById('wishlist-floating-btn');
        if (floatingBtn) {
            const badge = floatingBtn.querySelector('.wishlist-count');
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline-block' : 'none';
            }
        }
    }
    
    // Update cart count in header
    function updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count');
        cartCountElements.forEach(element => {
            element.textContent = count;
            element.classList.toggle('d-none', count === 0);
        });
    }
    
    // Show toast notification
    function showToast(message, type = 'success') {
        // Check if toast container exists
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        const toastId = 'toast-' + Date.now();
        const iconClass = type === 'success' ? 'fa-check-circle' : 
                         type === 'error' ? 'fa-exclamation-circle' : 
                         type === 'warning' ? 'fa-exclamation-triangle' : 
                         'fa-info-circle';
        
        const bgColor = type === 'success' ? 'bg-success' : 
                       type === 'error' ? 'bg-danger' : 
                       type === 'warning' ? 'bg-warning' : 
                       'bg-primary';
        
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white border-0" role="alert">
                <div class="toast-header ${bgColor}">
                    <i class="fas ${iconClass} me-2"></i>
                    <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${typeof message === 'string' && message.includes('<') ? 
                      message : 
                      `<span>${message}</span>`}
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        
        // Show toast
        const toastEl = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        
        // Remove toast after hiding
        toastEl.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    }
});
// Notify when available buttons
document.querySelectorAll('.notify-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const productId = this.dataset.productId;
        const productName = this.dataset.productName;
        
        if (confirm(`Notify you when "${productName}" is back in stock?`)) {
            // You can implement a notification system here
            showToast(`We'll notify you when "${productName}" is back in stock!`, 'info');
            
            // Disable button and show loading
            this.innerHTML = '<i class="fas fa-bell me-2"></i> Notifications Set';
            this.disabled = true;
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-success');
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>