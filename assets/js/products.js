/**
 * Minimal Product Catalog - Only handles interactive features
 * Doesn't interfere with PHP rendering
 */
class ProductCatalog {
    constructor() {
        // Only initialize if we're on products page
        const container = document.getElementById('products-container');
        if (!container) return;
        
        console.log('ProductCatalog: Enhancing existing products');
        
        // Store current products count
        this.currentProductCount = container.children.length;
        
        // Initialize only interactive features
        this.initEventListeners();
        this.initPriceSlider();
        this.initViewToggle();
        
        // Check localStorage for saved view preference
        this.checkSavedView();
    }
    
    initEventListeners() {
        console.log('Initializing event listeners');
        
        // Size filter - Update URL and reload page
        document.querySelectorAll('.size-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const size = e.target.dataset.size;
                this.updateFilterURL('size', size);
            });
        });
        
        // Color filter - Update URL and reload page
        document.querySelectorAll('.color-option').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const color = e.target.dataset.color;
                this.updateFilterURL('color', color);
            });
        });
        
        // Brand filter - Update URL and reload page
        document.querySelectorAll('.brand-checkbox').forEach(cb => {
            cb.addEventListener('change', (e) => {
                const brandId = e.target.checked ? e.target.value : null;
                this.updateFilterURL('brand_id', brandId);
            });
        });
        
        // Sort options - Update URL and reload page
        document.querySelectorAll('.sort-option').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const sort = e.target.dataset.sort;
                this.updateFilterURL('sort', sort);
            });
        });
        
        // Clear filters - Redirect to base products page
        document.getElementById('clear-filters')?.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = `${window.SITE_URL || ''}products`;
        });
        
        // Load more button - Handle via URL parameter
        document.getElementById('load-more')?.addEventListener('click', (e) => {
            e.preventDefault();
            const currentPage = parseInt(new URLSearchParams(window.location.search).get('page')) || 1;
            this.updateFilterURL('page', currentPage + 1);
        });
        
        // Mobile filter toggle
        document.getElementById('mobile-filter-toggle')?.addEventListener('click', () => {
            this.showMobileFilters();
        });
        
        // Apply mobile filters
        document.getElementById('apply-mobile-filters')?.addEventListener('click', () => {
            this.applyMobileFilters();
        });
        
        // Quick view buttons (delegated)
        document.addEventListener('click', (e) => {
            const quickViewBtn = e.target.closest('.quick-view-btn');
            if (quickViewBtn) {
                e.preventDefault();
                const productId = quickViewBtn.dataset.productId;
                this.showQuickView(productId);
            }
        });
        
        // Add to cart buttons (delegated)
        document.addEventListener('click', (e) => {
            const addToCartBtn = e.target.closest('.add-to-cart-btn');
            if (addToCartBtn) {
                e.preventDefault();
                const productId = addToCartBtn.dataset.productId;
                this.addToCart(productId, 1);
            }
        });
        
        // REMOVED WISHLIST BUTTON HANDLING - Now handled in products/index.php
        
        // Pagination links - Prevent default and update URL
        document.querySelectorAll('.pagination .page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = e.target.closest('a').getAttribute('href').match(/page=(\d+)/);
                if (page) {
                    this.updateFilterURL('page', page[1]);
                }
            });
        });
    }
    
    initPriceSlider() {
        const slider = document.querySelector('.price-slider');
        if (!slider) return;
        
        // Check if already initialized
        if (slider.noUiSlider) {
            slider.noUiSlider.destroy();
        }
        
        // Get current price values from URL or defaults
        const urlParams = new URLSearchParams(window.location.search);
        const minPrice = urlParams.get('min_price') ? parseInt(urlParams.get('min_price')) : 0;
        const maxPrice = urlParams.get('max_price') ? parseInt(urlParams.get('max_price')) : 500;
        
        noUiSlider.create(slider, {
            start: [minPrice, maxPrice],
            connect: true,
            range: {
                'min': 0,
                'max': 500
            },
            step: 10
        });
        
        // Update display values
        slider.noUiSlider.on('update', (values) => {
            const minValue = parseInt(values[0]);
            const maxValue = parseInt(values[1]);
            
            const priceMinEl = document.getElementById('price-min');
            const priceMaxEl = document.getElementById('price-max');
            const minPriceValueEl = document.getElementById('min-price-value');
            const maxPriceValueEl = document.getElementById('max-price-value');
            
            if (priceMinEl) priceMinEl.textContent = `$${minValue}`;
            if (priceMaxEl) priceMaxEl.textContent = `$${maxValue}`;
            if (minPriceValueEl) minPriceValueEl.value = minValue;
            if (maxPriceValueEl) maxPriceValueEl.value = maxValue;
        });
        
        // On change, update URL and reload page
        slider.noUiSlider.on('change', (values) => {
            const minPrice = parseInt(values[0]);
            const maxPrice = parseInt(values[1]);
            
            // Only update if values changed significantly
            const currentMin = parseInt(new URLSearchParams(window.location.search).get('min_price')) || 0;
            const currentMax = parseInt(new URLSearchParams(window.location.search).get('max_price')) || 500;
            
            if (minPrice !== currentMin || maxPrice !== currentMax) {
                const params = new URLSearchParams(window.location.search);
                
                if (minPrice > 0) {
                    params.set('min_price', minPrice);
                } else {
                    params.delete('min_price');
                }
                
                if (maxPrice < 500) {
                    params.set('max_price', maxPrice);
                } else {
                    params.delete('max_price');
                }
                
                params.set('page', 1); // Reset to page 1
                window.location.href = `${window.location.pathname}?${params.toString()}`;
            }
        });
    }
    
    initViewToggle() {
        document.querySelectorAll('.view-toggle button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const view = e.target.dataset.view || e.target.closest('[data-view]').dataset.view;
                this.setView(view, true); // true = update URL
            });
        });
    }
    
    checkSavedView() {
        // Check localStorage for saved view preference
        const savedView = localStorage.getItem('product_view_preference');
        if (savedView) {
            this.setView(savedView, false); // false = don't update URL
        }
    }
    
    setView(view, updateURL = false) {
        // Update UI buttons
        document.querySelectorAll('.view-toggle button').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.view === view) {
                btn.classList.add('active');
            }
        });
        
        // Update container class (for CSS)
        const container = document.getElementById('products-container');
        if (container) {
            if (view === 'list') {
                container.classList.add('products-list-view');
                container.classList.remove('products-grid-view');
            } else {
                container.classList.add('products-grid-view');
                container.classList.remove('products-list-view');
            }
        }
        
        // Save to localStorage
        localStorage.setItem('product_view_preference', view);
        
        // Update URL if requested
        if (updateURL) {
            this.updateFilterURL('view', view);
        }
    }
    
    updateFilterURL(key, value) {
        const params = new URLSearchParams(window.location.search);
        
        if (value && value !== 'null' && value !== 'undefined') {
            params.set(key, value);
        } else {
            params.delete(key);
        }
        
        // Always reset to page 1 when changing filters (except for page parameter)
        if (key !== 'page') {
            params.set('page', 1);
        }
        
        // Reload page with new filters
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    }
    
    showMobileFilters() {
        // Clone filters to modal
        const sidebarFilters = document.querySelector('.card-body')?.cloneNode(true);
        const mobileFilters = document.querySelector('.mobile-filters');
        if (sidebarFilters && mobileFilters) {
            mobileFilters.innerHTML = '';
            mobileFilters.appendChild(sidebarFilters);
            
            // Reinitialize price slider in modal
            setTimeout(() => {
                const modalSlider = mobileFilters.querySelector('.price-slider');
                if (modalSlider && !modalSlider.noUiSlider) {
                    const urlParams = new URLSearchParams(window.location.search);
                    const minPrice = urlParams.get('min_price') ? parseInt(urlParams.get('min_price')) : 0;
                    const maxPrice = urlParams.get('max_price') ? parseInt(urlParams.get('max_price')) : 500;
                    
                    noUiSlider.create(modalSlider, {
                        start: [minPrice, maxPrice],
                        connect: true,
                        range: { 'min': 0, 'max': 500 },
                        step: 10
                    });
                }
            }, 100);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('mobileFilterModal'));
            modal.show();
        }
    }
    
    applyMobileFilters() {
        // Collect filter values from modal and update URL
        const modal = document.getElementById('mobileFilterModal');
        const params = new URLSearchParams(window.location.search);
        
        // Get values from modal (simplified - you'd need to collect all filter values)
        const modalSlider = modal.querySelector('.price-slider');
        if (modalSlider && modalSlider.noUiSlider) {
            const values = modalSlider.noUiSlider.get();
            const minPrice = parseInt(values[0]);
            const maxPrice = parseInt(values[1]);
            
            if (minPrice > 0) {
                params.set('min_price', minPrice);
            } else {
                params.delete('min_price');
            }
            
            if (maxPrice < 500) {
                params.set('max_price', maxPrice);
            } else {
                params.delete('max_price');
            }
        }
        
        params.set('page', 1);
        
        // Close modal
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
        
        // Reload page with new filters
        setTimeout(() => {
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        }, 300);
    }
    
    showQuickView(productId) {
        console.log('Quick view for product:', productId);
        // For now, just redirect to product page
        // In a real implementation, you would show a modal with product details
        window.location.href = `${window.SITE_URL || ''}products/${productId}`;
    }
    
    async addToCart(productId, quantity) {
        try {
            const response = await fetch(`${window.SITE_URL || ''}ajax/add-to-cart.php`, {
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
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Added to Cart!',
                    text: 'Product has been added to your shopping cart.',
                    showConfirmButton: false,
                    timer: 1500,
                    toast: true,
                    position: 'bottom-end'
                });
                
                // Update cart count in header
                this.updateCartCount(data.cart_count || 0);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: data.message || 'Failed to add product to cart',
                    toast: true,
                    position: 'bottom-end'
                });
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Something went wrong. Please try again.',
                toast: true,
                position: 'bottom-end'
            });
        }
    }
    
    updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count');
        cartCountElements.forEach(el => {
            el.textContent = count;
            el.classList.add('animate__animated', 'animate__bounceIn');
            
            // Remove animation class after animation completes
            setTimeout(() => {
                el.classList.remove('animate__animated', 'animate__bounceIn');
            }, 1000);
        });
    }
}

// Initialize product catalog when DOM is loaded
let productCatalog;

// After initializing ProductCatalog
document.addEventListener('DOMContentLoaded', function() {
    // Initialize ProductCatalog
    if (document.getElementById('products-container')) {
        window.productCatalog = new ProductCatalog();
    }
});