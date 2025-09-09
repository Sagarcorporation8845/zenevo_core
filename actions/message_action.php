<?php
require_once '../config/db.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'send_message') {
        $to_user_id = (int)$_POST['to_user_id'];
        $message = trim($_POST['message']);
        $from_user_id = $_SESSION['user_id'];
        
        if (!$to_user_id || empty($message)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Message content and recipient are required.'];
            header('Location: ' . url_for('messages.php'));
            exit();
        }
        
        // Check for @support mention
        $is_support_mention = strpos($message, '@support') !== false ? 1 : 0;
        
        // Handle image upload
        $image_base64 = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $max_size = 2 * 1024 * 1024; // 2MB
                if ($_FILES['image']['size'] <= $max_size) {
                    $image_data = file_get_contents($_FILES['image']['tmp_name']);
                    $image_base64 = base64_encode($image_data);
                } else {
                    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Image size must be less than 2MB.'];
                    header('Location: ' . url_for('messages.php?user=' . $to_user_id));
                    exit();
                }
            } else {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Only JPEG, PNG, and GIF images are allowed.'];
                header('Location: ' . url_for('messages.php?user=' . $to_user_id));
                exit();
            }
        }
        
        // Insert message
        $stmt = $conn->prepare("INSERT INTO messages (from_user_id, to_user_id, message, image_base64, is_support_mention) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iissi', $from_user_id, $to_user_id, $message, $image_base64, $is_support_mention);
        
        if ($stmt->execute()) {
            // Send notification to recipient
            $notify_recipient = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_by) VALUES (?, ?, ?, ?, ?)");
            $notify_title = "New Message";
            $sender_name_query = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $sender_name_query->bind_param('i', $from_user_id);
            $sender_name_query->execute();
            $sender_name_query->bind_result($sender_name);
            $sender_name_query->fetch();
            $sender_name_query->close();
            
            $notify_message = "You have a new message from {$sender_name}";
            $notify_type = "info";
            $notify_recipient->bind_param('isssi', $to_user_id, $notify_title, $notify_message, $notify_type, $from_user_id);
            $notify_recipient->execute();
            $notify_recipient->close();
            
            // If @support mentioned, notify all admins
            if ($is_support_mention) {
                $admin_query = $conn->query("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'Admin' AND u.is_active = 1");
                $notify_admin = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_by) VALUES (?, ?, ?, ?, ?)");
                $admin_title = "Support Request";
                $admin_message = "{$sender_name} mentioned @support in a message: " . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '');
                $admin_type = "warning";
                
                while ($admin = $admin_query->fetch_assoc()) {
                    $notify_admin->bind_param('isssi', $admin['id'], $admin_title, $admin_message, $admin_type, $from_user_id);
                    $notify_admin->execute();
                }
                $notify_admin->close();
            }
            
            audit_log($conn, 'message_sent', "Message sent to user ID: $to_user_id", 'messaging');
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to send message. Please try again.'];
        }
        $stmt->close();
        
        header('Location: ' . url_for('messages.php?user=' . $to_user_id));
        exit();
    }
    
    // Mark message as read
    elseif ($_POST['action'] === 'mark_read') {
        $message_id = (int)$_POST['message_id'];
        $user_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND to_user_id = ?");
        $stmt->bind_param('ii', $message_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        $stmt->close();
        exit();
    }
    
    else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid action.'];
        header('Location: ' . url_for('messages.php'));
        exit();
    }
} else {
    header('Location: ' . url_for('messages.php'));
    exit();
}
?>