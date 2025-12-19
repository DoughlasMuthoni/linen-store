// /linen-closet/assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    // Set base URL for API calls
    const baseUrl = window.location.origin + '/linen-closet/';
    console.log('Base URL:', baseUrl);
    
    // Cart functions
    window.addToCart = function(productId, quantity = 1, size = null, color = null, material = null) {
        console.log('Adding to cart:', { productId, quantity, size, color, material });
        
        fetch(baseUrl + 'ajax/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity,
                size: size,
                color: color,
                material: material
            })
        })
        .then(response => {
            console.log('Cart response status:', response.status);
            
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Server returned non-JSON:', text.substring(0, 500));
                    throw new Error('Server returned HTML instead of JSON');
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Cart response data:', data);
            if(data.success) {
                updateCartCount(data.cart_count || data.cartCount || 0);
                
                // Show success message
                const toast = new bootstrap.Toast(document.getElementById('cartToast'));
                document.getElementById('toastMessage').textContent = data.message || 'Added to cart!';
                toast.show();
                
                // Optional: Show SweetAlert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message || 'Product added to cart',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            } else {
                throw new Error(data.message || 'Failed to add to cart');
            }
        })
        .catch(error => {
            console.error('Add to cart error:', error);
            
            // Show error message
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to add product to cart',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Error: ' + error.message);
            }
        });
    };
    
    // Quick add to cart (for product cards)
    window.quickAddToCart = function(button) {
        const productId = button.dataset.productId;
        const productName = button.dataset.productName || 'Product';
        
        // Show loading
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        window.addToCart(productId, 1);
        
        // Restore button after 2 seconds
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 2000);
    };
    
    // Remove from cart
    window.removeFromCart = function(productId, key = null) {
        if (!confirm('Remove this item from cart?')) return;
        
        fetch(baseUrl + 'ajax/remove-from-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                product_id: productId,
                cart_key: key
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                updateCartCount(data.cart?.count || 0);
                
                // If on cart page, remove the item from DOM
                if (window.location.pathname.includes('cart')) {
                    const itemElement = document.querySelector(`[data-product-id="${productId}"]`);
                    if (itemElement) {
                        itemElement.remove();
                    }
                    
                    // Update cart totals
                    updateCartTotals(data.cart);
                }
                
                // Show success message
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Removed!',
                        text: 'Item removed from cart',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            }
        });
    };
    
    // Update cart quantity
    window.updateCartQuantity = function(productId, quantity, key = null) {
        fetch(baseUrl + 'ajax/update-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity,
                cart_key: key
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                updateCartCount(data.cart?.count || 0);
                
                // If on cart page, update totals
                if (window.location.pathname.includes('cart')) {
                    updateCartTotals(data.cart);
                }
            }
        });
    };
    
    // Update cart totals display
    function updateCartTotals(cartData) {
        if (!cartData) return;
        
        // Update subtotal
        const subtotalEl = document.getElementById('cartSubtotal');
        if (subtotalEl) {
            subtotalEl.textContent = 'Ksh ' + cartData.subtotal.toFixed(2);
        }
        
        // Update shipping
        const shippingEl = document.getElementById('cartShipping');
        if (shippingEl) {
            shippingEl.textContent = cartData.shipping === 0 ? 'FREE' : 'Ksh ' + cartData.shipping.toFixed(2);
        }
        
        // Update tax
        const taxEl = document.getElementById('cartTax');
        if (taxEl) {
            taxEl.textContent = 'Ksh ' + cartData.tax.toFixed(2);
        }
        
        // Update total
        const totalEl = document.getElementById('cartTotal');
        if (totalEl) {
            totalEl.textContent = 'Ksh ' + cartData.total.toFixed(2);
        }
    }
    

// Replace ALL wishlist code from line 165 to line 289 with this single implementation:

// Add to wishlist functionality (server-side)
document.addEventListener('click', function(e) {
    const wishlistBtn = e.target.closest('.add-to-wishlist');
    if (wishlistBtn) {
        e.preventDefault();
        const productId = wishlistBtn.dataset.productId;
        
        // Show loading state
        const originalHTML = wishlistBtn.innerHTML;
        wishlistBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Make AJAX call
        fetch(baseUrl + 'ajax/wishlist-toggle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                product_id: productId,
                action: 'toggle'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update button appearance
                if (data.action === 'added') {
                    wishlistBtn.innerHTML = '<i class="fas fa-heart text-danger"></i>';
                    wishlistBtn.title = 'Remove from Wishlist';
                    wishlistBtn.classList.add('in-wishlist');
                    showToast('Added to wishlist!', 'success');
                } else {
                    wishlistBtn.innerHTML = '<i class="far fa-heart"></i>';
                    wishlistBtn.title = 'Add to Wishlist';
                    wishlistBtn.classList.remove('in-wishlist');
                    showToast('Removed from wishlist', 'info');
                }
                
                // Update wishlist count if provided
                if (data.wishlist_count !== undefined) {
                    updateWishlistCount(data.wishlist_count);
                }
            } else {
                // Restore original button state
                wishlistBtn.innerHTML = originalHTML;
                showToast(data.message || 'Failed to update wishlist', 'error');
            }
        })
        .catch(error => {
            console.error('Wishlist error:', error);
            wishlistBtn.innerHTML = originalHTML;
            showToast('Something went wrong', 'error');
        });
    }
});

// Update wishlist count in header
function updateWishlistCount(count) {
    const wishlistCountElements = document.querySelectorAll('.wishlist-count');
    wishlistCountElements.forEach(element => {
        element.textContent = count;
        element.classList.toggle('d-none', count === 0);
    });
}

// Initialize wishlist state for buttons on page load
function initializeWishlistButtons() {
    fetch(baseUrl + 'ajax/get-wishlist-status.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update wishlist count
                updateWishlistCount(data.count);
                
                // Update individual buttons if we have product_id
                if (data.product_id !== undefined) {
                    const btn = document.querySelector(`.add-to-wishlist[data-product-id="${data.product_id}"]`);
                    if (btn) {
                        updateWishlistButton(btn, data.in_wishlist);
                    }
                } else if (data.wishlist_items) {
                    // Update all buttons on page
                    document.querySelectorAll('.add-to-wishlist').forEach(btn => {
                        const productId = btn.dataset.productId;
                        const isInWishlist = data.wishlist_items.includes(parseInt(productId));
                        updateWishlistButton(btn, isInWishlist);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Failed to load wishlist status:', error);
        });
}

// Helper function to update button appearance
function updateWishlistButton(button, isInWishlist) {
    if (isInWishlist) {
        button.innerHTML = '<i class="fas fa-heart text-danger"></i>';
        button.title = 'Remove from Wishlist';
        button.classList.add('in-wishlist');
    } else {
        button.innerHTML = '<i class="far fa-heart"></i>';
        button.title = 'Add to Wishlist';
        button.classList.remove('in-wishlist');
    }
}

        // Add this to your DOMContentLoaded initialization
        initializeWishlistButtons();
    // Update cart count in UI
    function updateCartCount(count) {
        const cartBadges = document.querySelectorAll('.cart-count, .cart-badge, .badge[data-cart-count]');
        cartBadges.forEach(badge => {
            badge.textContent = count;
            badge.classList.toggle('d-none', count === 0);
        });
        
        // Update cart icon animation
        const cartIcons = document.querySelectorAll('.cart-icon, .fa-shopping-cart');
        cartIcons.forEach(icon => {
            icon.classList.add('animate__animated', 'animate__bounce');
            setTimeout(() => {
                icon.classList.remove('animate__animated', 'animate__bounce');
            }, 1000);
        });
    }
    
    // Initialize cart count on page load
    function initializeCartCount() {
        fetch(baseUrl + 'ajax/get-cart-count.php')
            .then(response => {
                if (!response.ok) return { count: 0 };
                return response.json();
            })
            .then(data => {
                if(data.count !== undefined) {
                    updateCartCount(data.count);
                } else {
                    updateCartCount(0);
                }
            })
            .catch(error => {
                console.error('Failed to load cart count:', error);
                updateCartCount(0);
            });
    }
    
    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if(!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    });
    
    // Initialize tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            document.body.classList.toggle('mobile-menu-open');
        });
    }
    
    // Search functionality
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="q"]');
            if (!searchInput.value.trim()) {
                e.preventDefault();
                searchInput.focus();
            }
        });
    }
    
    // Back to top button
    const backToTopButton = document.getElementById('backToTop');
    if (backToTopButton) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });
        
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Image lazy loading
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img.lazy').forEach(img => imageObserver.observe(img));
    }
    
    // Initialize cart count
    initializeCartCount();
    
    // Make functions available globally
    window.updateCartCount = updateCartCount;
    window.updateCartTotals = updateCartTotals;
    
    console.log('Main.js initialized successfully');
});

// Toast container HTML (add this to your main layout if not exists)
function ensureToastContainer() {
    if (!document.getElementById('cartToast')) {
        const toastHTML = `
            <div class="toast-container position-fixed bottom-0 end-0 p-3">
                <div id="cartToast" class="toast align-items-center text-bg-success border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check-circle me-2"></i>
                            <span id="toastMessage">Product added to cart!</span>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', toastHTML);
    }
}

// Call this on page load
ensureToastContainer();