// /linen-closet/assets/js/main.js

// Get base URL dynamically (move outside DOMContentLoaded so it's available globally)
const baseUrl = (function() {
    if (window.location.hostname === 'shop.waterliftsolar.africa') {
        return window.location.origin + '/';
    }
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        return window.location.origin + '/linen-closet/';
    }
    return window.location.origin + '/';
})();

console.log('Base URL:', baseUrl);

// Global function to handle add to cart - FIXED VERSION
window.addToCart = function(productId, quantity = 1, options = {}, event = null) {
    console.log('Adding product:', productId, quantity, options);
    
    // Find the button that was clicked
    const addButton = event?.target || document.querySelector(`[data-product-id="${productId}"]`);
    const originalText = addButton?.innerHTML;
    
    // Show loading state
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        addButton.disabled = true;
    }
    
    // Prepare data
    const data = {
        product_id: productId,
        quantity: quantity
    };
    
    // Add options if provided
    if (options.size) data.size = options.size;
    if (options.color) data.color = options.color;
    if (options.material) data.material = options.material;
    if (options.variant_id) data.variant_id = options.variant_id;
    
    // Send to server
    fetch(baseUrl + 'ajax/add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // UPDATE THE CART COUNT EVERYWHERE
            const newCount = data.cart_count || data.cart?.count || 0;
            
            // Update cart count (this includes mobile badge)
            updateCartCount(newCount);
            
            // Show success message
            showSuccessToast('Added to cart!');
            
            // Dispatch custom event for other listeners
            window.dispatchEvent(new CustomEvent('cartUpdated', { 
                detail: { count: newCount }
            }));
            
            // Also call the mobile badge function directly for safety
            const mobileBadge = document.querySelector('.mobile-cart-badge');
            if (mobileBadge) {
                mobileBadge.textContent = newCount;
                mobileBadge.style.display = newCount > 0 ? 'flex' : 'none';
            }
            
        } else {
            showErrorToast(data.message || 'Failed to add to cart');
        }
    })
    .catch(error => {
        console.error('Add to cart error:', error);
        showErrorToast('Network error. Please try again.');
    })
    .finally(() => {
        // Restore button
        if (addButton && originalText) {
            addButton.innerHTML = originalText;
            addButton.disabled = false;
        }
    });
};

document.addEventListener('DOMContentLoaded', function() {
    
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

    // Toast notification functions
    function showSuccessToast(message) {
        showToast(message, 'success');
    }

    function showErrorToast(message) {
        showToast(message, 'danger');
    }

    function showToast(message, type = 'success') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('globalToastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'globalToastContainer';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast
        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-${type} text-white">
                    <strong class="me-auto">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                        ${type === 'success' ? 'Success' : 'Error'}
                    </strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        
        // Show the toast
        const toastEl = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        
        // Remove after hide
        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    }
    
    // Update cart count in UI
    function updateCartCount(count) {
        console.log('Updating cart count to:', count);
        
        // Update desktop badges
        const cartBadges = document.querySelectorAll('.cart-count, .cart-badge, .badge[data-cart-count]');
        cartBadges.forEach(badge => {
            badge.textContent = count;
            badge.classList.toggle('d-none', count === 0);
        });
        
        // UPDATE MOBILE BADGE
        updateMobileCartBadge(count);
        
        // Update cart icon animation
        const cartIcons = document.querySelectorAll('.cart-icon, .fa-shopping-cart, .fa-shopping-bag');
        cartIcons.forEach(icon => {
            icon.classList.add('animate__animated', 'animate__bounce');
            setTimeout(() => {
                icon.classList.remove('animate__animated', 'animate__bounce');
            }, 1000);
        });
        
        // Store in sessionStorage
        sessionStorage.setItem('cartCount', count);
    }
    
    // Update mobile cart badge specifically
    function updateMobileCartBadge(count) {
        console.log('Updating mobile badge to:', count);
        
        // Method 1: By ID (if you added id="mobileCartBadge")
        const mobileBadgeById = document.getElementById('mobileCartBadge');
        if (mobileBadgeById) {
            mobileBadgeById.textContent = count;
            mobileBadgeById.style.display = count > 0 ? 'flex' : 'none';
        }
        
        // Method 2: By class (backup)
        const mobileBadgeByClass = document.querySelector('.mobile-cart-badge');
        if (mobileBadgeByClass && !mobileBadgeById) {
            mobileBadgeByClass.textContent = count;
            mobileBadgeByClass.style.display = count > 0 ? 'flex' : 'none';
        }
    }
    
    // Initialize cart count on page load
    function initializeCartCount() {
        // First check sessionStorage for faster display
        const storedCount = sessionStorage.getItem('cartCount');
        if (storedCount !== null) {
            updateCartCount(parseInt(storedCount));
        }
        
        // Then fetch from server for accuracy
        fetch(baseUrl + 'ajax/get-cart-count.php')
            .then(response => {
                if (!response.ok) {
                    console.warn('Cart count API failed, using stored value');
                    return;
                }
                return response.json();
            })
            .then(data => {
                if (data && data.count !== undefined) {
                    const serverCount = parseInt(data.count);
                    // Only update if different from stored
                    if (serverCount !== parseInt(storedCount || 0)) {
                        updateCartCount(serverCount);
                    }
                }
            })
            .catch(error => {
                console.error('Failed to load cart count:', error);
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
    
    // Add event delegation for add-to-cart buttons
    document.addEventListener('click', function(e) {
        const addBtn = e.target.closest('[onclick*="addToCart"], .btn-add-to-cart');
        if (addBtn && addBtn.onclick && addBtn.onclick.toString().includes('addToCart')) {
            // Let the onclick handler run, but ensure it passes the event
            return;
        }
    });
    
    // Poll for cart updates every 5 seconds (optional, for multi-tab support)
    setInterval(initializeCartCount, 5000);
    
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
document.addEventListener('DOMContentLoaded', ensureToastContainer);