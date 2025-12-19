// /linen-closet/assets/js/product-details.js

document.addEventListener('DOMContentLoaded', function() {
    const productDetails = new ProductDetails();
    productDetails.init();
});

class ProductDetails {
    constructor() {
        this.selectedSize = null;
        this.selectedColor = null;
        this.selectedVariantId = document.getElementById('selectedVariantId')?.value || null;
        this.variants = <?php echo json_encode($variants); ?>;
        this.zoomEnabled = false;
    }
    
    init() {
        this.initImageGallery();
        this.initSizeSelection();
        this.initColorSelection();
        this.initQuantityControls();
        this.initAddToCart();
        this.initWishlist();
        this.initBuyNow();
        this.initReviewForm();
        this.initImageZoom();
    }
    
    initImageGallery() {
        const thumbnailBtns = document.querySelectorAll('.thumbnail-btn');
        const mainImage = document.getElementById('mainProductImage');
        
        thumbnailBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Update active state
                thumbnailBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Update main image
                const newImageSrc = btn.dataset.image;
                mainImage.src = newImageSrc;
                mainImage.dataset.zoomImage = newImageSrc;
                
                // Reset zoom
                this.resetZoom();
            });
        });
    }
    
    initImageZoom() {
        const zoomContainer = document.getElementById('productImageZoom');
        const mainImage = document.getElementById('mainProductImage');
        const zoomLens = document.getElementById('zoomLens');
        const zoomedContainer = document.getElementById('zoomedImageContainer');
        
        if (!zoomContainer || !mainImage) return;
        
        // Only enable zoom on desktop
        if (window.innerWidth < 992) return;
        
        mainImage.addEventListener('load', () => {
            this.setupZoom(zoomContainer, mainImage, zoomLens, zoomedContainer);
        });
        
        // Setup if image is already loaded
        if (mainImage.complete) {
            this.setupZoom(zoomContainer, mainImage, zoomLens, zoomedContainer);
        }
    }
    
    setupZoom(container, image, lens, zoomed) {
        const zoomFactor = 2;
        
        container.addEventListener('mousemove', (e) => {
            if (!this.zoomEnabled) return;
            
            const rect = container.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            // Calculate lens position (centered on cursor)
            const lensX = x - lens.offsetWidth / 2;
            const lensY = y - lens.offsetHeight / 2;
            
            // Keep lens within bounds
            const maxX = container.offsetWidth - lens.offsetWidth;
            const maxY = container.offsetHeight - lens.offsetHeight;
            
            const finalX = Math.max(0, Math.min(lensX, maxX));
            const finalY = Math.max(0, Math.min(lensY, maxY));
            
            // Position lens
            lens.style.left = `${finalX}px`;
            lens.style.top = `${finalY}px`;
            
            // Calculate zoomed image position
            const zoomX = (finalX / container.offsetWidth) * 100;
            const zoomY = (finalY / container.offsetHeight) * 100;
            
            // Update zoomed image
            if (zoomed) {
                zoomed.style.backgroundImage = `url('${image.dataset.zoomImage || image.src}')`;
                zoomed.style.backgroundPosition = `${zoomX}% ${zoomY}%`;
                zoomed.style.backgroundSize = `${container.offsetWidth * zoomFactor}px`;
            }
        });
        
        container.addEventListener('mouseenter', () => {
            if (window.innerWidth < 992) return;
            
            lens.classList.remove('d-none');
            if (zoomed) zoomed.classList.remove('d-none');
            this.zoomEnabled = true;
        });
        
        container.addEventListener('mouseleave', () => {
            lens.classList.add('d-none');
            if (zoomed) zoomed.classList.add('d-none');
            this.zoomEnabled = false;
        });
        
        // Position zoomed container
        if (zoomed) {
            zoomed.style.left = `${container.offsetLeft + container.offsetWidth + 20}px`;
            zoomed.style.top = `${container.offsetTop}px`;
        }
    }
    
    resetZoom() {
        this.zoomEnabled = false;
        const lens = document.getElementById('zoomLens');
        const zoomed = document.getElementById('zoomedImageContainer');
        
        if (lens) lens.classList.add('d-none');
        if (zoomed) zoomed.classList.add('d-none');
    }
    
    initSizeSelection() {
        const sizeOptions = document.querySelectorAll('.size-option:not(.disabled)');
        
        sizeOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                // Update active state
                document.querySelectorAll('.size-option').forEach(opt => {
                    opt.classList.remove('active');
                });
                option.classList.add('active');
                
                this.selectedSize = option.dataset.size;
                this.updateSelectedVariant();
            });
        });
        
        // Set initial size
        const activeSize = document.querySelector('.size-option.active:not(.disabled)');
        if (activeSize) {
            this.selectedSize = activeSize.dataset.size;
        }
    }
    
    initColorSelection() {
        const colorOptions = document.querySelectorAll('.color-option');
        
        colorOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                // Update active state
                document.querySelectorAll('.color-option').forEach(opt => {
                    opt.innerHTML = '';
                    opt.classList.remove('active');
                });
                
                option.classList.add('active');
                option.innerHTML = '<i class="fas fa-check position-absolute top-50 start-50 translate-middle text-white"></i>';
                
                this.selectedColor = option.dataset.color;
                this.updateSelectedVariant();
            });
        });
        
        // Set initial color
        const activeColor = document.querySelector('.color-option.active');
        if (activeColor) {
            this.selectedColor = activeColor.dataset.color;
        }
    }
    
    updateSelectedVariant() {
        if (!this.selectedSize && !this.selectedColor) return;
        
        // Find variant matching selected size and color
        let selectedVariant = null;
        
        for (const variant of this.variants) {
            const sizeMatch = !this.selectedSize || variant.size === this.selectedSize;
            const colorMatch = !this.selectedColor || variant.color === this.selectedColor;
            
            if (sizeMatch && colorMatch && variant.stock_quantity > 0) {
                selectedVariant = variant;
                break;
            }
        }
        
        // If no exact match, try to find any variant with selected size
        if (!selectedVariant && this.selectedSize) {
            for (const variant of this.variants) {
                if (variant.size === this.selectedSize && variant.stock_quantity > 0) {
                    selectedVariant = variant;
                    break;
                }
            }
        }
        
        // Update UI
        if (selectedVariant) {
            this.selectedVariantId = selectedVariant.id;
            document.getElementById('selectedVariantId').value = selectedVariant.id;
            
            // Update stock status
            this.updateStockStatus(selectedVariant);
            
            // Update quantity max
            const quantityInput = document.getElementById('quantity');
            quantityInput.max = Math.min(10, selectedVariant.stock_quantity);
            
            if (parseInt(quantityInput.value) > selectedVariant.stock_quantity) {
                quantityInput.value = selectedVariant.stock_quantity;
            }
        }
    }
    
    updateStockStatus(variant) {
        const stockAlert = document.querySelector('.alert');
        const addToCartBtn = document.getElementById('addToCartBtn');
        const buyNowBtn = document.getElementById('buyNowBtn');
        
        if (!stockAlert) return;
        
        if (variant.stock_quantity <= 0) {
            stockAlert.className = 'alert alert-danger mb-0';
            stockAlert.innerHTML = '<i class="fas fa-times-circle me-2"></i> Out of Stock';
            
            if (addToCartBtn) addToCartBtn.disabled = true;
            if (buyNowBtn) buyNowBtn.disabled = true;
        } else if (variant.stock_quantity <= 5) {
            stockAlert.className = 'alert alert-warning mb-0';
            stockAlert.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i> Only ${variant.stock_quantity} left in stock!`;
            
            if (addToCartBtn) addToCartBtn.disabled = false;
            if (buyNowBtn) buyNowBtn.disabled = false;
        } else {
            stockAlert.className = 'alert alert-success mb-0';
            stockAlert.innerHTML = '<i class="fas fa-check-circle me-2"></i> In Stock';
            
            if (addToCartBtn) addToCartBtn.disabled = false;
            if (buyNowBtn) buyNowBtn.disabled = false;
        }
    }
    
    initQuantityControls() {
        const decreaseBtn = document.getElementById('decreaseQty');
        const increaseBtn = document.getElementById('increaseQty');
        const quantityInput = document.getElementById('quantity');
        
        if (decreaseBtn) {
            decreaseBtn.addEventListener('click', () => {
                let currentValue = parseInt(quantityInput.value);
                if (currentValue > 1) {
                    quantityInput.value = currentValue - 1;
                }
            });
        }
        
        if (increaseBtn) {
            increaseBtn.addEventListener('click', () => {
                let currentValue = parseInt(quantityInput.value);
                const maxValue = parseInt(quantityInput.max);
                if (currentValue < maxValue) {
                    quantityInput.value = currentValue + 1;
                }
            });
        }
    }
    
    initAddToCart() {
        const form = document.getElementById('addToCartForm');
        if (!form) return;
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const productId = document.getElementById('productId').value;
            const variantId = document.getElementById('selectedVariantId').value;
            const quantity = document.getElementById('quantity').value;
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            
            if (!variantId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Please select size',
                    text: 'Please select a size before adding to cart.',
                });
                return;
            }
            
            fetch('cart/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    variant_id: variantId,
                    quantity: quantity,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count
                    const cartBadges = document.querySelectorAll('.cart-count');
                    cartBadges.forEach(badge => {
                        badge.textContent = data.cart_count;
                    });
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Added to Cart!',
                        html: `
                            <div class="text-center">
                                <p>${data.message}</p>
                                <div class="mt-3">
                                    <a href="<?php echo SITE_URL; ?>cart" class="btn btn-outline-dark me-2">View Cart</a>
                                    <button class="btn btn-dark" onclick="Swal.close()">Continue Shopping</button>
                                </div>
                            </div>
                        `,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to add item to cart.',
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong. Please try again.',
                });
            });
        });
    }
    
    initBuyNow() {
        const buyNowBtn = document.getElementById('buyNowBtn');
        if (!buyNowBtn) return;
        
        buyNowBtn.addEventListener('click', () => {
            const productId = document.getElementById('productId').value;
            const variantId = document.getElementById('selectedVariantId').value;
            const quantity = document.getElementById('quantity').value;
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            
            if (!variantId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Please select size',
                    text: 'Please select a size before proceeding.',
                });
                return;
            }
            
            // Add to cart first
            fetch('cart/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    variant_id: variantId,
                    quantity: quantity,
                    csrf_token: csrfToken,
                    buy_now: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to checkout
                    window.location.href = '<?php echo SITE_URL; ?>checkout';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to proceed to checkout.',
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong. Please try again.',
                });
            });
        });
    }
    
    initWishlist() {
        const wishlistBtn = document.getElementById('wishlistBtn');
        if (!wishlistBtn) return;
        
        wishlistBtn.addEventListener('click', () => {
            const productId = document.getElementById('productId').value;
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            
            fetch('wishlist/toggle.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button text and icon
                    const icon = wishlistBtn.querySelector('i');
                    if (data.in_wishlist) {
                        icon.className = 'fas text-danger fa-heart me-2';
                        wishlistBtn.innerHTML = '<i class="fas text-danger fa-heart me-2"></i> Remove from Wishlist';
                    } else {
                        icon.className = 'far fa-heart me-2';
                        wishlistBtn.innerHTML = '<i class="far fa-heart me-2"></i> Add to Wishlist';
                    }
                    
                    // Update wishlist count
                    const wishlistBadges = document.querySelectorAll('.wishlist-count');
                    wishlistBadges.forEach(badge => {
                        badge.textContent = data.wishlist_count;
                    });
                    
                    // Show message
                    const message = data.in_wishlist 
                        ? 'Added to your wishlist!' 
                        : 'Removed from your wishlist!';
                    
                    Swal.fire({
                        icon: 'success',
                        title: message,
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to update wishlist.',
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong. Please try again.',
                });
            });
        });
    }
    
    initReviewForm() {
        const reviewForm = document.getElementById('reviewForm');
        const ratingStars = document.querySelectorAll('.rating-star');
        const selectedRatingInput = document.getElementById('selectedRating');
        
        if (!reviewForm) return;
        
        // Star rating selection
        ratingStars.forEach(star => {
            star.addEventListener('click', (e) => {
                const rating = parseInt(star.dataset.rating);
                selectedRatingInput.value = rating;
                
                // Update star display
                ratingStars.forEach((s, index) => {
                    const icon = s.querySelector('i');
                    if (index < rating) {
                        icon.className = 'fas fa-star fa-2x text-warning';
                        s.classList.add('active');
                    } else {
                        icon.className = 'far fa-star fa-2x text-warning';
                        s.classList.remove('active');
                    }
                });
            });
            
            // Hover effect
            star.addEventListener('mouseenter', (e) => {
                const rating = parseInt(star.dataset.rating);
                ratingStars.forEach((s, index) => {
                    const icon = s.querySelector('i');
                    if (index < rating) {
                        icon.className = 'fas fa-star fa-2x text-warning';
                    } else {
                        icon.className = 'far fa-star fa-2x text-warning';
                    }
                });
            });
            
            star.addEventListener('mouseleave', (e) => {
                const currentRating = parseInt(selectedRatingInput.value);
                ratingStars.forEach((s, index) => {
                    const icon = s.querySelector('i');
                    if (index < currentRating) {
                        icon.className = 'fas fa-star fa-2x text-warning';
                    } else {
                        icon.className = 'far fa-star fa-2x text-warning';
                    }
                });
            });
        });
        
        // Form submission
        reviewForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const formData = new FormData(reviewForm);
            const data = Object.fromEntries(formData);
            
            fetch('products/api/submit-review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Review Submitted!',
                        text: 'Thank you for your review. It will be published after approval.',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('writeReviewModal'));
                        modal.hide();
                        
                        // Reset form
                        reviewForm.reset();
                        
                        // Reset stars
                        selectedRatingInput.value = 5;
                        ratingStars.forEach((s, index) => {
                            const icon = s.querySelector('i');
                            if (index < 5) {
                                icon.className = 'fas fa-star fa-2x text-warning';
                                s.classList.add('active');
                            } else {
                                icon.className = 'far fa-star fa-2x text-warning';
                                s.classList.remove('active');
                            }
                        });
                        
                        // Reload page after delay to show new review
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to submit review.',
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong. Please try again.',
                });
            });
        });
    }
}