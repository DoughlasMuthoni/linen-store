<?php
// /linen-closet/account/wishlist.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/App.php';

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

// Initialize wishlist if not exists
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

$wishlist = $_SESSION['wishlist'];

// Handle actions
if (isset($_GET['action']) && isset($_GET['product_id'])) {
    $productId = intval($_GET['product_id']);
    $action = $_GET['action'];
    
    if ($action === 'add') {
        if (!in_array($productId, $wishlist)) {
            $wishlist[] = $productId;
            $_SESSION['wishlist'] = $wishlist;
        }
    } elseif ($action === 'remove') {
        $key = array_search($productId, $wishlist);
        if ($key !== false) {
            unset($wishlist[$key]);
            $wishlist = array_values($wishlist); // Reindex array
            $_SESSION['wishlist'] = $wishlist;
        }
    } elseif ($action === 'clear') {
        $_SESSION['wishlist'] = [];
        $wishlist = [];
    }
    
    // Redirect back to avoid form resubmission
    header('Location: ' . SITE_URL . 'account/wishlist.php');
    exit();
}

// Fetch wishlist products
$wishlistProducts = [];
if (!empty($wishlist)) {
    $placeholders = str_repeat('?,', count($wishlist) - 1) . '?';
    
    $stmt = $db->prepare("
        SELECT 
            p.*,
            (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
            c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders) AND p.is_active = 1
    ");
    $stmt->execute($wishlist);
    $wishlistProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <?php if (count($wishlist) > 0): ?>
                            You have <?php echo count($wishlist); ?> item<?php echo count($wishlist) !== 1 ? 's' : ''; ?> in your wishlist
                        <?php else: ?>
                            Your wishlist is empty
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if (count($wishlist) > 0): ?>
                        <a href="?action=clear" class="btn btn-outline-danger" onclick="return confirm('Clear your entire wishlist?')">
                            <i class="fas fa-trash-alt me-2"></i> Clear All
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (count($wishlist) > 0): ?>
        <!-- Wishlist Items -->
        <div class="row">
            <?php foreach ($wishlistProducts as $product): ?>
                <?php
                $imageUrl = SITE_URL . ($product['primary_image'] ?: 'assets/images/placeholder.jpg');
                $productUrl = SITE_URL . 'products/detail.php?slug=' . $product['slug'];
                $inStock = ($product['stock_quantity'] ?? 0) > 0;
                ?>
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="position-relative">
                            <a href="<?php echo $productUrl; ?>" class="text-decoration-none">
                                <img src="<?php echo $imageUrl; ?>" 
                                     class="card-img-top" 
                                     style="height: 200px; object-fit: cover;"
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </a>
                            <button class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2" 
                                    onclick="window.location.href='?action=remove&product_id=<?php echo $product['id']; ?>'">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php if (!$inStock): ?>
                                <span class="badge bg-warning position-absolute top-0 start-0 m-2">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="mb-2">
                                <a href="<?php echo $productUrl; ?>" class="text-decoration-none text-dark">
                                    <h6 class="card-title fw-bold mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                </a>
                                <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                            </div>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="fw-bold fs-5">Ksh <?php echo number_format($product['price'], 2); ?></span>
                                        <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                                            <del class="text-muted small ms-2">Ksh <?php echo number_format($product['compare_price'], 2); ?></del>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($inStock): ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <?php if ($inStock): ?>
                                        <button class="btn btn-dark add-to-cart" 
                                                data-product-id="<?php echo $product['id']; ?>">
                                            <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline-dark" disabled>
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
        
        <!-- Wishlist Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-1">Wishlist Summary</h6>
                                <p class="text-muted small mb-0">
                                    Total items: <?php echo count($wishlist); ?> • 
                                    Total value: Ksh <?php 
                                        $totalValue = array_sum(array_column($wishlistProducts, 'price'));
                                        echo number_format($totalValue, 2); 
                                    ?>
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?php echo SITE_URL; ?>products" class="btn btn-outline-dark">
                                    <i class="fas fa-plus me-2"></i> Add More Items
                                </a>
                                <button class="btn btn-dark" id="addAllToCart">
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
                    <div class="empty-icon mb-4">
                        <i class="fas fa-heart fa-4x text-muted"></i>
                    </div>
                    <h3 class="fw-bold mb-3">Your wishlist is empty</h3>
                    <p class="lead text-muted mb-4">
                        Save items you love for later. They'll appear here!
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="<?php echo SITE_URL; ?>products" class="btn btn-dark btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                        </a>
                        <a href="<?php echo SITE_URL; ?>account" class="btn btn-outline-dark btn-lg">
                            <i class="fas fa-user me-2"></i> Back to Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.card {
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
}

.badge.bg-warning {
    color: #212529;
}

.empty-icon {
    opacity: 0.5;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            addToCart(productId, 1);
        });
    });
    
    // Add all to cart
    document.getElementById('addAllToCart')?.addEventListener('click', function() {
        if (confirm('Add all wishlist items to cart?')) {
            const productIds = <?php echo json_encode($wishlist); ?>;
            
            productIds.forEach(productId => {
                addToCart(productId, 1);
            });
            
            // Show success message
            showToast('All items added to cart!', 'success');
            
            // Redirect to cart after delay
            setTimeout(() => {
                window.location.href = '<?php echo SITE_URL; ?>cart';
            }, 1500);
        }
    });
    
    // Add to cart function
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
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Added to cart!', 'success');
                
                // Update cart count in header
                updateCartCount(data.cart.count);
            } else {
                showToast(data.message || 'Failed to add to cart', 'error');
            }
        } catch (error) {
            console.error('Add to cart error:', error);
            showToast('Something went wrong', 'error');
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
        // Create toast container if it doesn't exist
        if (!document.querySelector('.toast-container')) {
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast
        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        document.querySelector('.toast-container').insertAdjacentHTML('beforeend', toastHTML);
        
        // Show toast
        const toast = new bootstrap.Toast(document.getElementById(toastId));
        toast.show();
        
        // Remove toast after hiding
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function () {
            this.remove();
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>