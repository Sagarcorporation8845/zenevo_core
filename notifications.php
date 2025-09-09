<?php
$pageTitle = 'Notifications';
include 'includes/header.php';

// Get user's notifications
$notifications_query = "SELECT n.*, 
                               CONCAT(u.name) as created_by_name,
                               (SELECT COUNT(*) FROM notification_likes nl WHERE nl.notification_id = n.id) as like_count,
                               (SELECT COUNT(*) FROM notification_likes nl WHERE nl.notification_id = n.id AND nl.user_id = ?) as user_liked
                        FROM notifications n 
                        LEFT JOIN users u ON n.created_by = u.id 
                        WHERE n.user_id = ? 
                        ORDER BY n.created_at DESC 
                        LIMIT 50";
$stmt = $conn->prepare($notifications_query);
$stmt->bind_param('ii', $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Mark notifications as read
$mark_read = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$mark_read->bind_param('i', $_SESSION['user_id']);
$mark_read->execute();
$mark_read->close();
?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-700">Notifications</h2>
        <div class="flex space-x-3">
            <button onclick="markAllAsShown()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                Mark All as Seen
            </button>
        </div>
    </div>

    <div class="space-y-4">
        <?php if ($notifications && $notifications->num_rows > 0): ?>
            <?php while($notification = $notifications->fetch_assoc()): ?>
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 
                    <?php 
                    switch($notification['type']) {
                        case 'success': echo 'border-green-500'; break;
                        case 'warning': echo 'border-yellow-500'; break;
                        case 'error': echo 'border-red-500'; break;
                        case 'broadcast': echo 'border-purple-500'; break;
                        default: echo 'border-blue-500'; break;
                    }
                    ?>
                    <?php echo !$notification['is_shown'] ? 'ring-2 ring-opacity-50 ring-blue-300' : ''; ?>">
                    
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <h3 class="text-lg font-semibold text-gray-900"><?php echo e($notification['title']); ?></h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php 
                                    switch($notification['type']) {
                                        case 'success': echo 'bg-green-100 text-green-800'; break;
                                        case 'warning': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'error': echo 'bg-red-100 text-red-800'; break;
                                        case 'broadcast': echo 'bg-purple-100 text-purple-800'; break;
                                        default: echo 'bg-blue-100 text-blue-800'; break;
                                    }
                                    ?>">
                                    <?php echo ucfirst($notification['type']); ?>
                                </span>
                                <?php if (!$notification['is_shown']): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        New
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-gray-700 mb-3"><?php echo nl2br(e($notification['message'])); ?></p>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <div class="flex items-center space-x-4">
                                    <span><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                                    <?php if ($notification['created_by_name']): ?>
                                        <span>by <?php echo e($notification['created_by_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center space-x-3">
                                    <button onclick="likeNotification(<?php echo $notification['id']; ?>)" 
                                            class="flex items-center space-x-1 hover:text-red-600 transition-colors
                                            <?php echo $notification['user_liked'] ? 'text-red-600' : 'text-gray-500'; ?>">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                        </svg>
                                        <span id="like-count-<?php echo $notification['id']; ?>"><?php echo $notification['like_count']; ?></span>
                                    </button>
                                    
                                    <?php if (!$notification['is_shown']): ?>
                                        <button onclick="markAsShown(<?php echo $notification['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800 font-medium">
                                            Mark as Seen
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-white p-8 rounded-lg shadow-md text-center">
                <div class="text-gray-400 mb-4">
                    <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM9 17H4l5 5v-5zM12 3v9m0 0l3-3m-3 3l-3-3"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No notifications yet</h3>
                <p class="text-gray-500">You'll see notifications here when there are updates for you.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function likeNotification(notificationId) {
    fetch('actions/notification_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=like_notification&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const countElement = document.getElementById(`like-count-${notificationId}`);
            countElement.textContent = data.like_count;
            
            // Toggle heart color
            const button = countElement.parentElement;
            if (data.user_liked) {
                button.classList.add('text-red-600');
                button.classList.remove('text-gray-500');
            } else {
                button.classList.add('text-gray-500');
                button.classList.remove('text-red-600');
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAsShown(notificationId) {
    fetch('actions/notification_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_shown&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllAsShown() {
    fetch('actions/notification_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_shown'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

<?php include 'includes/footer.php'; ?>