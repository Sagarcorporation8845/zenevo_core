<?php
/*
--------------------------------------------------------------------------------
-- File: /includes/footer.php
-- Description: Reusable footer to close the main content and body tags.
--------------------------------------------------------------------------------
*/
?>
            </main> <!-- Closes the main tag from header.php -->
        </div> <!-- Closes the flex-1 div from header.php -->
    </div> <!-- Closes the main flex container from header.php -->

    <!-- Notification Popup Modal -->
    <div id="notificationPopup" class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div id="popupContent" class="p-6">
                    <!-- Popup content will be loaded here -->
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end">
                    <button onclick="closeNotificationPopup()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Notification system
    let notificationDropdownOpen = false;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Load initial notifications
        loadNotifications();
        
        // Check for popup notifications on first load
        checkPopupNotifications();
        
        // Set up notification bell click handler
        const notificationBell = document.getElementById('notificationBell');
        if (notificationBell) {
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleNotificationDropdown();
            });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.getElementById('notificationBell');
            if (dropdown && bell && !dropdown.contains(e.target) && !bell.contains(e.target)) {
                closeNotificationDropdown();
            }
        });
        
        // Refresh notifications every 30 seconds
        setInterval(loadNotifications, 30000);
    });
    
    function loadNotifications() {
        fetch('<?php echo url_for('actions/notification_action.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_popup_notifications'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBell(data.notifications.length);
                updateNotificationDropdown(data.notifications);
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
    }
    
    function updateNotificationBell(count) {
        const countElement = document.getElementById('notificationCount');
        if (countElement) {
            if (count > 0) {
                countElement.textContent = count;
                countElement.classList.remove('hidden');
            } else {
                countElement.classList.add('hidden');
            }
        }
    }
    
    function updateNotificationDropdown(notifications) {
        const listElement = document.getElementById('notificationList');
        if (!listElement) return;
        
        if (notifications.length === 0) {
            listElement.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500">No new notifications</div>';
            return;
        }
        
        const html = notifications.map(notification => {
            const typeColor = {
                'success': 'text-green-600',
                'warning': 'text-yellow-600',
                'error': 'text-red-600',
                'broadcast': 'text-purple-600',
                'info': 'text-blue-600'
            }[notification.type] || 'text-blue-600';
            
            return `
                <div class="px-4 py-3 hover:bg-gray-50">
                    <div class="flex items-start">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900">${notification.title}</div>
                            <div class="text-sm text-gray-600 mt-1">${notification.message.substring(0, 100)}${notification.message.length > 100 ? '...' : ''}</div>
                            <div class="text-xs ${typeColor} mt-1">${new Date(notification.created_at).toLocaleString()}</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        listElement.innerHTML = html;
    }
    
    function toggleNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (!dropdown) return;
        
        if (notificationDropdownOpen) {
            closeNotificationDropdown();
        } else {
            dropdown.classList.remove('hidden');
            notificationDropdownOpen = true;
        }
    }
    
    function closeNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.add('hidden');
        }
        notificationDropdownOpen = false;
    }
    
    function checkPopupNotifications() {
        fetch('<?php echo url_for('actions/notification_action.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_popup_notifications'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications.length > 0) {
                // Show popup for the first unshown notification
                const notification = data.notifications[0];
                showNotificationPopup(notification);
            }
        })
        .catch(error => console.error('Error checking popup notifications:', error));
    }
    
    function showNotificationPopup(notification) {
        const typeColor = {
            'success': 'text-green-600 bg-green-100',
            'warning': 'text-yellow-600 bg-yellow-100',
            'error': 'text-red-600 bg-red-100',
            'broadcast': 'text-purple-600 bg-purple-100',
            'info': 'text-blue-600 bg-blue-100'
        }[notification.type] || 'text-blue-600 bg-blue-100';
        
        const popupContent = document.getElementById('popupContent');
        if (popupContent) {
            popupContent.innerHTML = `
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">${notification.title}</h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${typeColor}">
                        ${notification.type.charAt(0).toUpperCase() + notification.type.slice(1)}
                    </span>
                </div>
                <p class="text-gray-700 mb-4">${notification.message}</p>
                <p class="text-sm text-gray-500">${new Date(notification.created_at).toLocaleString()}</p>
            `;
            
            const popup = document.getElementById('notificationPopup');
            if (popup) {
                popup.classList.remove('hidden');
            }
            
            // Auto-mark as shown after displaying
            setTimeout(() => {
                markNotificationAsShown(notification.id);
            }, 1000);
        }
    }
    
    function closeNotificationPopup() {
        const popup = document.getElementById('notificationPopup');
        if (popup) {
            popup.classList.add('hidden');
        }
        // Reload notifications to update count
        loadNotifications();
    }
    
    function markNotificationAsShown(notificationId) {
        fetch('<?php echo url_for('actions/notification_action.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_shown&notification_id=${notificationId}`
        })
        .catch(error => console.error('Error marking notification as shown:', error));
    }
    </script>
</body>
</html>