<?php
/*
--------------------------------------------------------------------------------
-- File: /actions/profile_action.php
-- Description: Handles profile-related actions including profile picture upload
--------------------------------------------------------------------------------
*/

session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Set JSON content type
header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'update_profile_picture':
            $profile_picture = $_POST['profile_picture'] ?? '';
            
            if (empty($profile_picture)) {
                throw new Exception('No image data provided');
            }
            
            // Validate base64 image
            $image_data = base64_decode($profile_picture);
            if ($image_data === false) {
                throw new Exception('Invalid image data');
            }
            
            // Check if it's a valid image
            $image_info = getimagesizefromstring($image_data);
            if ($image_info === false) {
                throw new Exception('Invalid image format');
            }
            
            // Check image size (max 5MB)
            if (strlen($image_data) > 5 * 1024 * 1024) {
                throw new Exception('Image size too large (max 5MB)');
            }
            
            // Check image dimensions (max 2048x2048)
            if ($image_info[0] > 2048 || $image_info[1] > 2048) {
                throw new Exception('Image dimensions too large (max 2048x2048)');
            }
            
            // Update profile picture in database
            $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param('si', $profile_picture, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update profile picture: ' . $stmt->error);
            }
            
            $stmt->close();
            
            // Log the action
            $audit_sql = "INSERT INTO audit_logs (user_id, action, details, resource, ip_address, user_agent) 
                         VALUES (?, 'profile_picture_update', 'User updated profile picture', 'profile', ?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            if ($audit_stmt) {
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $audit_stmt->bind_param('iss', $user_id, $ip_address, $user_agent);
                $audit_stmt->execute();
                $audit_stmt->close();
            }
            
            echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully']);
            break;
            
        case 'remove_profile_picture':
            // Remove profile picture
            $sql = "UPDATE users SET profile_picture = NULL WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param('i', $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to remove profile picture: ' . $stmt->error);
            }
            
            $stmt->close();
            
            // Log the action
            $audit_sql = "INSERT INTO audit_logs (user_id, action, details, resource, ip_address, user_agent) 
                         VALUES (?, 'profile_picture_remove', 'User removed profile picture', 'profile', ?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            if ($audit_stmt) {
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $audit_stmt->bind_param('iss', $user_id, $ip_address, $user_agent);
                $audit_stmt->execute();
                $audit_stmt->close();
            }
            
            echo json_encode(['success' => true, 'message' => 'Profile picture removed successfully']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>