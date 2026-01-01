// Mark notification as read
function markAsRead(notificationId) {
    fetch('../admin/ajax/notifications.php?action=mark_read&id=' + notificationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update badge count
                let badge = document.getElementById('notificationBadge');
                let bell = document.getElementById('notificationBell');
                let currentCount = parseInt(bell.dataset.unread) || 0;
                
                if (currentCount > 1) {
                    let newCount = currentCount - 1;
                    badge.textContent = newCount > 9 ? '9+' : newCount;
                    bell.dataset.unread = newCount;
                } else {
                    badge.remove();
                    bell.dataset.unread = 0;
                }
                
                // Mark item as read visually
                let item = document.querySelector('.notification-item[data-id="' + notificationId + '"]');
                if (item) {
                    item.classList.remove('fw-bold');
                    item.classList.add('text-muted');
                    
                    // Remove "New" badge
                    let newBadge = item.querySelector('.badge.bg-danger');
                    if (newBadge) {
                        newBadge.remove();
                    }
                }
            }
        });
}

// Mark all as read
function markAllAsRead() {
    fetch('../admin/ajax/notifications.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove badge
                let badge = document.getElementById('notificationBadge');
                if (badge) {
                    badge.remove();
                }
                
                // Update bell data
                let bell = document.getElementById('notificationBell');
                bell.dataset.unread = 0;
                
                // Mark all items as read visually
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('fw-bold');
                    item.classList.add('text-muted');
                    
                    let newBadge = item.querySelector('.badge.bg-danger');
                    if (newBadge) {
                        newBadge.remove();
                    }
                });
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'All notifications marked as read!',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        });
}

// Check for new notifications periodically
// function checkNewNotifications() {
//     fetch('../admin/ajax/notifications.php?action=check_unread')
//         .then(response => response.json())
//         .then(data => {
//             if (data.success && data.count > 0) {
//                 let bell = document.getElementById('notificationBell');
//                 let currentCount = parseInt(bell.dataset.unread) || 0;
                
//                 if (data.count !== currentCount) {
//                     updateNotificationCount(data.count);
                    
//                     // Show desktop notification if browser supports it
//                     if (data.count > currentCount && "Notification" in window) {
//                         if (Notification.permission === "granted") {
//                             new Notification("New Notification", {
//                                 body: "You have " + data.count + " unread notifications",
//                                 icon: "../assets/images/logo.png"
//                             });
//                         }
//                     }
//                 }
//             }
//         });
// }

// Update notification count in UI
function updateNotificationCount(count) {
    let bell = document.getElementById('notificationBell');
    let badge = document.getElementById('notificationBadge');
    
    bell.dataset.unread = count;
    
    if (count > 0) {
        if (badge) {
            badge.textContent = count > 9 ? '9+' : count;
        } else {
            let newBadge = document.createElement('span');
            newBadge.id = 'notificationBadge';
            newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
            newBadge.textContent = count > 9 ? '9+' : count;
            bell.appendChild(newBadge);
        }
    } else if (badge) {
        badge.remove();
    }
}

// Auto-refresh notifications every 30 seconds
setInterval(checkNewNotifications, 30000);

// Request notification permission on page load
document.addEventListener('DOMContentLoaded', function() {
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }
});