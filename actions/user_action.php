<?php
/*
--------------------------------------------------------------------------------
-- File: /actions/user_action.php (NEW FILE)
-- Description: Handles user-specific actions like profile updates.
--------------------------------------------------------------------------------
*/
require_once '../config/db.php';
require_login();

// --- Main Logic: Check the requested action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- Update Profile Details Action ---
    if ($_POST['action'] === 'update_profile') {
        // 1. Get and validate form data
        $name = trim($_POST['name']);
        $user_id = $_SESSION['user_id'];

        if (empty($name)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Name cannot be empty.'];
            header('Location: ' . url_for('profile.php'));
            exit();
        }

        // 2. Update the users table
        $sql = "UPDATE users SET name = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $name, $user_id);

        if ($stmt->execute()) {
            // Update the name in the session as well
            $_SESSION['user_name'] = $name;
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Profile details updated successfully.'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to update profile.'];
        }
        $stmt->close();
        header('Location: ' . url_for('profile.php'));
        exit();
    }

    // --- Change Password Action ---
    if ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $user_id = $_SESSION['user_id'];

        // 1. Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'All password fields are required.', 'form' => 'password'];
            header('Location: ' . url_for('profile.php'));
            exit();
        }
        if ($new_password !== $confirm_password) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'New password and confirmation do not match.', 'form' => 'password'];
            header('Location: ' . url_for('profile.php'));
            exit();
        }

        // 2. Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (password_verify($current_password, $user['password'])) {
            // 3. Current password is correct, update to the new one
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_new_password, $user_id);
            if ($update_stmt->execute()) {
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Password changed successfully.', 'form' => 'password'];
            } else {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to change password.', 'form' => 'password'];
            }
            $update_stmt->close();
        } else {
            // Current password was incorrect
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Incorrect current password.', 'form' => 'password'];
        }
        header('Location: ' . url_for('profile.php'));
        exit();
    }
} else {
    header('Location: ' . url_for('dashboard.php'));
    exit();
}
?>