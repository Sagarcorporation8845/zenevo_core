<?php
require_once '../config/db.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Like/Unlike notification
    if ($_POST['action'] === 'like_notification') {
        $notification_id = (int)$_POST['notification_id'];
        $user_id = $_SESSION['user_id'];
        
        if (!$notification_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
            exit();
        }
        
        // Check if user already liked this notification
        $check_like = $conn->prepare("SELECT id FROM notification_likes WHERE notification_id = ? AND user_id = ?");
        $check_like->bind_param('ii', $notification_id, $user_id);
        $check_like->execute();
        $like_result = $check_like->get_result();
        
        if ($like_result->num_rows > 0) {
            // Unlike - remove the like
            $unlike = $conn->prepare("DELETE FROM notification_likes WHERE notification_id = ? AND user_id = ?");
            $unlike->bind_param('ii', $notification_id, $user_id);
            $unlike->execute();
            $unlike->close();
            $user_liked = false;
        } else {
            // Like - add the like
            $like = $conn->prepare("INSERT INTO notification_likes (notification_id, user_id) VALUES (?, ?)");
            $like->bind_param('ii', $notification_id, $user_id);
            $like->execute();
            $like->close();
            $user_liked = true;
        }
        $check_like->close();
        
        // Get updated like count
        $count_query = $conn->prepare("SELECT COUNT(*) FROM notification_likes WHERE notification_id = ?");
        $count_query->bind_param('i', $notification_id);
        $count_query->execute();
        $count_query->bind_result($like_count);
        $count_query->fetch();
        $count_query->close();
        
        echo json_encode([
            'success' => true,
            'like_count' => $like_count,
            'user_liked' => $user_liked
        ]);
    }
    
    // Mark notification as shown
    elseif ($_POST['action'] === 'mark_shown') {
        $notification_id = (int)$_POST['notification_id'];
        $user_id = $_SESSION['user_id'];
        
        if (!$notification_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
            exit();
        }
        
        $update = $conn->prepare("UPDATE notifications SET is_shown = 1 WHERE id = ? AND user_id = ?");
        $update->bind_param('ii', $notification_id, $user_id);
        
        if ($update->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update notification']);
        }
        $update->close();
    }
    
    // Mark all notifications as shown
    elseif ($_POST['action'] === 'mark_all_shown') {
        $user_id = $_SESSION['user_id'];
        
        $update_all = $conn->prepare("UPDATE notifications SET is_shown = 1 WHERE user_id = ?");
        $update_all->bind_param('i', $user_id);
        
        if ($update_all->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update notifications']);
        }
        $update_all->close();
    }
    
    // Get notification popup data
    elseif ($_POST['action'] === 'get_popup_notifications') {
        $user_id = $_SESSION['user_id'];
        
        $popup_query = $conn->prepare("SELECT id, title, message, type, created_at 
                                      FROM notifications 
                                      WHERE user_id = ? AND is_shown = 0 
                                      ORDER BY created_at DESC 
                                      LIMIT 5");
        $popup_query->bind_param('i', $user_id);
        $popup_query->execute();
        $result = $popup_query->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $popup_query->close();
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications
        ]);
    }
    
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>