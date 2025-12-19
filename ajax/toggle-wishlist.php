// Add to wishlist functionality
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
                updateWishlistButton(wishlistBtn, data.action === 'added');
                
                // Update wishlist count
                updateWishlistCount(data.wishlist_count);
                
                // Show toast message
                const message = data.action === 'added' ? 'Added to wishlist!' : 'Removed from wishlist';
                const type = data.action === 'added' ? 'success' : 'info';
                showToast(message, type);
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