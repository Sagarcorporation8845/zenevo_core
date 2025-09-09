<?php
$pageTitle = 'Messages';
include 'includes/header.php';

// Create messages table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `from_user_id` INT NOT NULL,
  `to_user_id` INT NULL,
  `message` TEXT NOT NULL,
  `image_base64` LONGTEXT NULL,
  `is_support_mention` TINYINT(1) DEFAULT 0,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_from_user` (`from_user_id`),
  KEY `idx_to_user` (`to_user_id`),
  KEY `idx_support` (`is_support_mention`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$currentUserId = $_SESSION['user_id'];
$currentEmployeeId = null;
$isManager = check_role_access($conn, ['Manager']);

// Get current employee ID
if ($st = $conn->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1')) {
    $st->bind_param('i', $currentUserId);
    $st->execute();
    $st->bind_result($currentEmployeeId);
    $st->fetch();
    $st->close();
}

// Get conversations list
$conversations = [];

if ($isManager && $currentEmployeeId) {
    // Manager sees conversations with their team members
    $convQuery = $conn->prepare("SELECT DISTINCT 
                                    CASE 
                                        WHEN m.from_user_id = ? THEN m.to_user_id 
                                        ELSE m.from_user_id 
                                    END as other_user_id,
                                    u.name as other_user_name,
                                    e.designation,
                                    MAX(m.created_at) as last_message_time,
                                    COUNT(CASE WHEN m.to_user_id = ? AND m.is_read = 0 THEN 1 END) as unread_count
                                FROM messages m
                                JOIN users u ON (CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END) = u.id
                                LEFT JOIN employees e ON u.id = e.user_id
                                LEFT JOIN employee_managers em ON e.id = em.employee_id
                                WHERE (m.from_user_id = ? OR m.to_user_id = ?) 
                                AND (em.manager_id = ? AND em.is_active = 1)
                                GROUP BY other_user_id, other_user_name, e.designation
                                ORDER BY last_message_time DESC");
    $convQuery->bind_param('iiiiii', $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentEmployeeId);
    $convQuery->execute();
    $conversations = $convQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $convQuery->close();
} else {
    // Regular employees see conversations with their manager and support
    $convQuery = $conn->prepare("SELECT DISTINCT 
                                    CASE 
                                        WHEN m.from_user_id = ? THEN m.to_user_id 
                                        ELSE m.from_user_id 
                                    END as other_user_id,
                                    u.name as other_user_name,
                                    e.designation,
                                    MAX(m.created_at) as last_message_time,
                                    COUNT(CASE WHEN m.to_user_id = ? AND m.is_read = 0 THEN 1 END) as unread_count
                                FROM messages m
                                JOIN users u ON (CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END) = u.id
                                LEFT JOIN employees e ON u.id = e.user_id
                                WHERE (m.from_user_id = ? OR m.to_user_id = ?) 
                                GROUP BY other_user_id, other_user_name, e.designation
                                ORDER BY last_message_time DESC");
    $convQuery->bind_param('iiiii', $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId);
    $convQuery->execute();
    $conversations = $convQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $convQuery->close();
}

// Get selected conversation messages
$selectedUserId = isset($_GET['user']) ? (int)$_GET['user'] : null;
$messages = [];
$selectedUserName = '';

if ($selectedUserId) {
    // Get selected user info
    $userQuery = $conn->prepare("SELECT u.name, e.designation FROM users u LEFT JOIN employees e ON u.id = e.user_id WHERE u.id = ?");
    $userQuery->bind_param('i', $selectedUserId);
    $userQuery->execute();
    $userQuery->bind_result($selectedUserName, $selectedUserDesignation);
    $userQuery->fetch();
    $userQuery->close();
    
    // Get messages for this conversation
    $msgQuery = $conn->prepare("SELECT m.*, u.name as sender_name 
                               FROM messages m 
                               JOIN users u ON m.from_user_id = u.id 
                               WHERE (m.from_user_id = ? AND m.to_user_id = ?) 
                               OR (m.from_user_id = ? AND m.to_user_id = ?) 
                               ORDER BY m.created_at ASC");
    $msgQuery->bind_param('iiii', $currentUserId, $selectedUserId, $selectedUserId, $currentUserId);
    $msgQuery->execute();
    $messages = $msgQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $msgQuery->close();
    
    // Mark messages as read
    $markRead = $conn->prepare("UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0");
    $markRead->bind_param('ii', $selectedUserId, $currentUserId);
    $markRead->execute();
    $markRead->close();
}
?>

<div class="container mx-auto h-full">
    <div class="flex h-full bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Conversations Sidebar -->
        <div class="w-1/3 border-r border-gray-200 flex flex-col">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Messages</h2>
                <p class="text-sm text-gray-600">
                    <?php echo $isManager ? 'Team conversations' : 'Your conversations'; ?>
                </p>
            </div>
            
            <div class="flex-1 overflow-y-auto">
                <?php if (!empty($conversations)): ?>
                    <?php foreach ($conversations as $conv): ?>
                        <a href="?user=<?php echo $conv['other_user_id']; ?>" 
                           class="block p-4 border-b border-gray-100 hover:bg-gray-50 transition-colors
                           <?php echo $selectedUserId == $conv['other_user_id'] ? 'bg-blue-50 border-blue-200' : ''; ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0 w-10 h-10">
                                        <div class="w-full h-full rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold">
                                            <?php echo e(strtoupper(substr($conv['other_user_name'], 0, 2))); ?>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            <?php echo e($conv['other_user_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 truncate">
                                            <?php echo e($conv['designation'] ?? 'Employee'); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end">
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full">
                                            <?php echo $conv['unread_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-xs text-gray-500 mt-1">
                                        <?php echo date('M j', strtotime($conv['last_message_time'])); ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.959 8.959 0 01-4.906-1.476L3 21l2.476-5.094A8.959 8.959 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                        <p class="text-sm">No conversations yet</p>
                        <p class="text-xs mt-1">Start a conversation to see it here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="flex-1 flex flex-col">
            <?php if ($selectedUserId): ?>
                <!-- Chat Header -->
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0 w-10 h-10">
                            <div class="w-full h-full rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold">
                                <?php echo e(strtoupper(substr($selectedUserName, 0, 2))); ?>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900"><?php echo e($selectedUserName); ?></h3>
                            <p class="text-sm text-gray-500"><?php echo e($selectedUserDesignation ?? 'Employee'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Messages -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messagesContainer">
                    <?php foreach ($messages as $message): ?>
                        <div class="flex <?php echo $message['from_user_id'] == $currentUserId ? 'justify-end' : 'justify-start'; ?>">
                            <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg
                                <?php echo $message['from_user_id'] == $currentUserId 
                                    ? 'bg-blue-500 text-white' 
                                    : 'bg-gray-200 text-gray-900'; ?>">
                                
                                <?php if ($message['image_base64']): ?>
                                    <img src="data:image/jpeg;base64,<?php echo $message['image_base64']; ?>" 
                                         class="max-w-full h-auto rounded mb-2" alt="Shared image">
                                <?php endif; ?>
                                
                                <p class="text-sm"><?php echo nl2br(e($message['message'])); ?></p>
                                
                                <?php if ($message['is_support_mention']): ?>
                                    <div class="mt-1 text-xs <?php echo $message['from_user_id'] == $currentUserId ? 'text-blue-100' : 'text-gray-500'; ?>">
                                        <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                        Support notified
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-xs mt-1 <?php echo $message['from_user_id'] == $currentUserId ? 'text-blue-100' : 'text-gray-500'; ?>">
                                    <?php echo date('M j, g:i A', strtotime($message['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Message Input -->
                <div class="p-4 border-t border-gray-200 bg-gray-50">
                    <form action="actions/message_action.php" method="POST" enctype="multipart/form-data" id="messageForm" class="space-y-3">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="to_user_id" value="<?php echo $selectedUserId; ?>">
                        
                        <div class="flex space-x-3">
                            <div class="flex-1">
                                <textarea name="message" id="messageInput" rows="3" 
                                         class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" 
                                         placeholder="Type your message... (Use @support to notify admin)" required></textarea>
                            </div>
                            <div class="flex flex-col space-y-2">
                                <label class="flex items-center justify-center w-10 h-10 bg-gray-200 hover:bg-gray-300 rounded-md cursor-pointer transition-colors">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <input type="file" name="image" accept="image/*" class="hidden" id="imageInput">
                                </label>
                                <button type="submit" class="w-10 h-10 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition-colors flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div id="imagePreview" class="hidden">
                            <div class="relative inline-block">
                                <img id="previewImg" class="max-w-32 h-auto rounded border" alt="Preview">
                                <button type="button" onclick="removeImage()" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs hover:bg-red-600">
                                    ×
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- No Conversation Selected -->
                <div class="flex-1 flex items-center justify-center bg-gray-50">
                    <div class="text-center">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.959 8.959 0 01-4.906-1.476L3 21l2.476-5.094A8.959 8.959 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Select a conversation</h3>
                        <p class="text-gray-500">Choose a conversation from the sidebar to start messaging</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Image preview functionality
document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
});

function removeImage() {
    document.getElementById('imageInput').value = '';
    document.getElementById('imagePreview').classList.add('hidden');
}

// Auto-scroll to bottom of messages
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Scroll to bottom on page load
document.addEventListener('DOMContentLoaded', scrollToBottom);

// Handle form submission
document.getElementById('messageForm')?.addEventListener('submit', function(e) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput.value.trim() && !document.getElementById('imageInput').files[0]) {
        e.preventDefault();
        alert('Please enter a message or select an image to send.');
    }
});

// Auto-resize textarea
document.getElementById('messageInput')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Keyboard shortcuts
document.getElementById('messageInput')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('messageForm').submit();
    }
});
</script>

<?php include 'includes/footer.php'; ?>