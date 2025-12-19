// Update markAsRead function
window.markAsRead = function(notificationId) {
    fetch('<?php echo SITE_URL; ?>admin/ajax/notifications.php?action=mark_read&id=' + notificationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update badge count
                const bell = document.getElementById('notificationBell');
                const currentCount = parseInt(bell?.dataset?.unread) || 0;
                const newCount = Math.max(0, currentCount - 1);
                
                // Update badge
                updateNotificationBadge(newCount);
                
                // Mark item as read visually
                const item = document.querySelector('.notification-item[data-id="' + notificationId + '"]');
                if (item) {
                    item.classList.remove('fw-bold');
                    item.classList.add('text-muted');
                    
                    // Remove "New" badge
                    const newBadge = item.querySelector('.badge.bg-danger');
                    if (newBadge) {
                        newBadge.remove();
                    }
                }
            }
        });
};

// Update markAllAsRead function
window.markAllAsRead = function() {
    fetch('<?php echo SITE_URL; ?>admin/ajax/notifications.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update badge to 0
                updateNotificationBadge(0);
                
                // Mark all items as read visually
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('fw-bold');
                    item.classList.add('text-muted');
                    
                    const newBadge = item.querySelector('.badge.bg-danger');
                    if (newBadge) {
                        newBadge.remove();
                    }
                });
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'All notifications marked as read!',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        });
};

// Update checkNewNotifications function
function checkNewNotifications() {
    fetch('<?php echo SITE_URL; ?>admin/ajax/notifications.php?action=check_unread')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.count);
            }
        });
}