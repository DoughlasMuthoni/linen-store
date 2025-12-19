// /linen-closet/assets/js/homepage.js

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all homepage functionality
    
    // Get SITE_URL from global variable or data attribute
    const SITE_URL = window.SITE_URL || document.body.dataset.siteUrl || 'http://localhost/linen-closet/';
    
    // Set SITE_URL globally for other functions
    window.SITE_URL = SITE_URL;
    
    // 1. Load Products Dynamically
    loadNewArrivals(SITE_URL);
    loadBestSellers(SITE_URL);
    loadTestimonials();
    loadInstagramGrid();
    
    // 2. Countdown Timer
    initCountdownTimer();
    
    // 3. Newsletter Subscription
    initNewsletterForm(SITE_URL);
    
    // 4. Product Quick View (optional)
    initQuickView(SITE_URL);
});

// Improved fetch function with better error handling
async function fetchWithErrorHandling(url, options = {}) {
    try {
        // Add timestamp to prevent caching issues
        const cacheBuster = url.includes('?') ? '&_=' : '?_=';
        const timestamp = Date.now();
        const urlWithCache = url + cacheBuster + timestamp;
        
        console.log('Fetching from:', urlWithCache);
        
        const response = await fetch(urlWithCache, {
            ...options,
            headers: {
                'Accept': 'application/json',
                ...options.headers
            }
        });
        
        // First check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status} ${response.statusText}`);
        }
        
        // Get response text first to check content
        const responseText = await response.text();
        
        // Try to parse as JSON
        try {
            const data = JSON.parse(responseText);
            return data;
        } catch (parseError) {
            console.error('Failed to parse JSON:', parseError);
            console.error('Response received:', responseText.substring(0, 500));
            throw new Error('Server returned invalid JSON. Check console for details.');
        }
        
    } catch (error) {
        console.error('Fetch error for URL:', url, error);
        throw error;
    }
}

// Load New Arrivals with better error handling
async function loadNewArrivals(SITE_URL) {
    const container = document.getElementById('new-arrivals');
    if (!container) {
        console.warn('New arrivals container not found');
        return;
    }
    
    // Show loading state
    container.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-dark" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    try {
        const response = await fetchWithErrorHandling(SITE_URL + 'products/api/new-arrivals.php');
        
        // Check response structure
        let products = [];
        if (response && response.success && response.data) {
            products = response.data;
        } else {
            throw new Error(response.message || 'Invalid response format');
        }
        
        // Check if we need to show "NEW" badges
        products.forEach(product => {
            if (product.is_new) {
                // You could add a visual indicator here
            }
        });
        
        renderProducts(products, container, 'new-arrivals');
        
    } catch (error) {
        console.error('Error loading new arrivals:', error);
        container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <h5 class="d-inline">Unable to load new arrivals</h5>
                    <p class="mb-2">${error.message || 'Please try again later.'}</p>
                    <button onclick="loadSampleNewArrivals()" class="btn btn-sm btn-outline-dark mt-2">
                        Show Sample Products
                    </button>
                </div>
            </div>
        `;
    }
}

// Load Best Sellers with better error handling
async function loadBestSellers(SITE_URL) {
    const container = document.getElementById('best-sellers');
    if (!container) {
        console.warn('Best sellers container not found');
        return;
    }
    
    try {
        const response = await fetchWithErrorHandling(SITE_URL + 'products/api/best-sellers.php');
        
        // Check response structure
        let products = [];
        if (response && response.data && Array.isArray(response.data)) {
            // If response has data property
            products = response.data;
        } else if (Array.isArray(response)) {
            // If response is directly an array
            products = response;
        } else {
            console.warn('Unexpected response structure:', response);
            throw new Error('Invalid response format from server');
        }
        
        renderProducts(products, container, 'best-sellers');
        
    } catch (error) {
        console.error('Error loading best sellers:', error);
        container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <h5 class="d-inline">Unable to load best sellers</h5>
                    <p class="mb-2">${error.message || 'Please try again later.'}</p>
                    <small class="text-muted d-block">Showing sample products instead</small>
                    <button onclick="loadSampleProducts('best-sellers')" class="btn btn-sm btn-outline-dark mt-2">
                        Show Sample Products
                    </button>
                </div>
            </div>
        `;
    }
}

// Load sample products when API fails
function loadSampleProducts(type) {
    const container = document.getElementById(type);
    if (!container) return;
    
    const sampleProducts = [
        {
            id: 1,
            slug: 'classic-linen-shirt',
            name: 'Classic Linen Shirt',
            price: '79.99',
            compare_price: '99.99',
            image_url: 'assets/images/products/shirt-1.jpg',
            in_wishlist: false
        },
        {
            id: 2,
            slug: 'linen-blazer',
            name: 'Linen Blazer',
            price: '159.99',
            compare_price: null,
            image_url: 'assets/images/products/blazer-1.jpg',
            in_wishlist: true
        },
        {
            id: 3,
            slug: 'linen-maxi-dress',
            name: 'Linen Maxi Dress',
            price: '139.99',
            compare_price: '179.99',
            image_url: 'assets/images/products/dress-1.jpg',
            in_wishlist: false
        },
        {
            id: 4,
            slug: 'linen-jumpsuit',
            name: 'Linen Jumpsuit',
            price: '129.99',
            compare_price: null,
            image_url: 'assets/images/products/jumpsuit-1.jpg',
            in_wishlist: true
        }
    ];
    
    renderProducts(sampleProducts, container, type);
}

// Common product rendering function
function renderProducts(products, container, type = 'products') {
    if (!products || !Array.isArray(products) || products.length === 0) {
        container.innerHTML = '<div class="col-12 text-center py-5"><p class="text-muted">No products found.</p></div>';
        return;
    }
    
    // Ensure SITE_URL is available
    const siteUrl = window.SITE_URL || '';
    
    const html = products.map(product => `
        <div class="col-md-3 col-6 mb-4">
            <div class="product-card-home h-100 position-relative">
                ${product.compare_price ? `
                    <div class="product-badge">
                        <span class="badge bg-danger">Sale</span>
                    </div>
                ` : ''}
                <a href="${siteUrl}products/${product.slug || 'product'}" class="text-decoration-none">
                    <div class="product-img-container position-relative" style="padding-top: 100%; overflow: hidden;">
                        <img src="${product.image_url ? (product.image_url.startsWith('http') ? product.image_url : siteUrl + product.image_url) : siteUrl + 'assets/images/placeholder.jpg'}" 
                             alt="${product.name || 'Product'}" 
                             class="img-fluid product-img-home position-absolute top-0 start-0 w-100 h-100"
                             style="object-fit: cover;"
                             onerror="this.onerror=null; this.src='${siteUrl}assets/images/placeholder.jpg';">
                    </div>
                    <div class="p-3">
                        <h6 class="product-title mb-2 text-dark" style="min-height: 3rem;">${product.name || 'Product'}</h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="price fs-5 fw-bold text-dark">$${product.price || '0.00'}</span>
                                ${product.original_price ? `
                                    <span class="old-price text-muted ms-2"><del>${product.original_price}</del></span>
                                ` : ''}
                            </div>
                            <div class="d-flex gap-1">
                                ${type === 'best-sellers' || product.in_wishlist !== undefined ? `
                                <button type="button" 
                                        class="btn btn-outline-dark btn-sm rounded-circle wishlist-btn"
                                        onclick="toggleWishlist(${product.id}); event.preventDefault();"
                                        data-id="${product.id}"
                                        title="${product.in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'}">
                                    <i class="${product.in_wishlist ? 'fas text-danger' : 'far'} fa-heart"></i>
                                </button>
                                ` : ''}
                                <button type="button" 
                                        class="btn btn-outline-dark btn-sm rounded-circle"
                                        onclick="addToCart(${product.id}, 1); event.preventDefault();"
                                        title="Add to cart">
                                    <i class="fas fa-shopping-bag"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = html;
}

// Load Testimonials (unchanged)
function loadTestimonials() {
    const testimonials = [
        {
            name: "Sarah Johnson",
            role: "Fashion Blogger",
            text: "The linen quality is exceptional! These pieces have become staples in my wardrobe.",
            rating: 5,
            image: "assets/images/testimonial-1.jpg"
        },
        {
            name: "Michael Chen",
            role: "Regular Customer",
            text: "Best online shopping experience. The fit and fabric are perfect every time.",
            rating: 5,
            image: "assets/images/testimonial-2.jpg"
        },
        {
            name: "Emma Wilson",
            role: "Interior Designer",
            text: "Timeless pieces that get better with every wash. Highly recommended!",
            rating: 5,
            image: "assets/images/testimonial-3.jpg"
        }
    ];
    
    const container = document.getElementById('testimonials-slider');
    if (!container) return;
    
    const siteUrl = window.SITE_URL || '';
    
    container.innerHTML = testimonials.map(testimonial => `
        <div class="col-md-4">
            <div class="testimonial-card h-100">
                <div class="d-flex align-items-center mb-4">
                    <img src="${siteUrl + testimonial.image}" 
                         alt="${testimonial.name}" 
                         class="rounded-circle me-3"
                         style="width: 60px; height: 60px; object-fit: cover;"
                         onerror="this.onerror=null; this.src='${siteUrl}assets/images/placeholder.jpg';">
                    <div>
                        <h5 class="mb-1">${testimonial.name}</h5>
                        <small class="text-muted">${testimonial.role}</small>
                    </div>
                </div>
                <div class="star-rating mb-3 text-warning">
                    ${'<i class="fas fa-star"></i>'.repeat(testimonial.rating)}
                </div>
                <p class="mb-0">"${testimonial.text}"</p>
            </div>
        </div>
    `).join('');
}

// Load Instagram Grid (unchanged)
function loadInstagramGrid() {
    const instaPosts = Array(6).fill().map((_, i) => ({
        id: i + 1,
        image: `assets/images/insta-${i + 1}.jpg`,
        likes: Math.floor(Math.random() * 1000) + 500,
        comments: Math.floor(Math.random() * 100) + 50
    }));
    
    const container = document.getElementById('instagram-grid');
    if (!container) return;
    
    const siteUrl = window.SITE_URL || '';
    
    container.innerHTML = instaPosts.map(post => `
        <div class="col-md-4 col-6">
            <div class="insta-post position-relative">
                <img src="${siteUrl + post.image}" 
                     alt="Instagram Post" 
                     class="img-fluid w-100"
                     style="height: 200px; object-fit: cover;"
                     onerror="this.onerror=null; this.src='${siteUrl}assets/images/placeholder.jpg';">
                <div class="insta-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                    <div class="text-center text-light">
                        <div class="mb-2">
                            <i class="fas fa-heart me-2"></i> ${post.likes.toLocaleString()}
                        </div>
                        <div>
                            <i class="fas fa-comment me-2"></i> ${post.comments}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// Countdown Timer (unchanged)
function initCountdownTimer() {
    // Set sale end date (7 days from now)
    const saleEnd = new Date();
    saleEnd.setDate(saleEnd.getDate() + 7);
    
    function updateCountdown() {
        const now = new Date();
        const timeLeft = saleEnd - now;
        
        if (timeLeft <= 0) {
            document.getElementById('days').textContent = '00';
            document.getElementById('hours').textContent = '00';
            document.getElementById('minutes').textContent = '00';
            document.getElementById('seconds').textContent = '00';
            return;
        }
        
        const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
        const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
        
        document.getElementById('days').textContent = days.toString().padStart(2, '0');
        document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
        document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
        document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
    }
    
    updateCountdown();
    setInterval(updateCountdown, 1000);
}

// Newsletter Form with error handling
function initNewsletterForm(SITE_URL) {
    const form = document.getElementById('newsletter-form');
    if (!form) return;
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = this.querySelector('input[type="email"]').value;
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Validate email
        if (!email || !email.includes('@')) {
            alert('Please enter a valid email address');
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
        
        try {
            // For now, just show success message
            // In production, you would make an API call here
            
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // Show success
            Swal.fire({
                icon: 'success',
                title: 'Subscribed!',
                text: 'Thank you for subscribing to our newsletter.',
                showConfirmButton: false,
                timer: 2000
            });
            
            form.reset();
        } catch (error) {
            console.error('Subscription error:', error);
            Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
        } finally {
            // Reset button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Quick View Functionality
function initQuickView(SITE_URL) {
    document.addEventListener('click', function(e) {
        if (e.target.closest('.quick-view-btn')) {
            const productId = e.target.closest('.quick-view-btn').dataset.id;
            showQuickView(productId, SITE_URL);
        }
    });
}

function showQuickView(productId, SITE_URL) {
    // For now, just redirect to product page
    window.location.href = SITE_URL + 'products/' + productId;
}

// Stub functions for cart and wishlist (to be implemented)
function addToCart(productId, quantity) {
    console.log('Add to cart:', productId, quantity);
    // Implement cart functionality
    Swal.fire({
        icon: 'success',
        title: 'Added to Cart!',
        text: 'Product has been added to your shopping cart.',
        showConfirmButton: false,
        timer: 1500
    });
}

function toggleWishlist(productId) {
    console.log('Toggle wishlist:', productId);
    // Implement wishlist functionality
    const btn = document.querySelector(`.wishlist-btn[data-id="${productId}"]`);
    if (btn) {
        const icon = btn.querySelector('i');
        const isInWishlist = icon.classList.contains('fas');
        
        if (isInWishlist) {
            icon.classList.remove('fas', 'text-danger');
            icon.classList.add('far');
        } else {
            icon.classList.remove('far');
            icon.classList.add('fas', 'text-danger');
        }
        
        Swal.fire({
            icon: 'success',
            title: isInWishlist ? 'Removed!' : 'Added!',
            text: isInWishlist ? 'Removed from wishlist' : 'Added to wishlist',
            showConfirmButton: false,
            timer: 1500
        });
    }
}

// Make functions available globally
window.loadNewArrivals = loadNewArrivals;
window.loadBestSellers = loadBestSellers;
window.loadSampleProducts = loadSampleProducts;
window.addToCart = addToCart;
window.toggleWishlist = toggleWishlist;