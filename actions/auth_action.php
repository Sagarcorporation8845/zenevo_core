<?php
/*
--------------------------------------------------------------------------------
-- File: /actions/auth_action.php
-- Description: Handles the server-side logic for authentication (login/logout).
--------------------------------------------------------------------------------
*/

// We need the database connection and helper functions on this page.
require_once '../config/db.php';

// --- Main Logic: Check the requested action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- Login Action ---
    if ($_POST['action'] === 'login') {
        // 1. Get email and password from the form
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Basic validation
        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Email and password are required.';
            header('Location: ' . url_for('login.php'));
            exit();
        }

        // 2. Prepare a secure query to find the user by email
        $sql = "SELECT id, name, password, role_id, is_active FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 3. Verify the password
            // `password_verify` securely checks the submitted password against the stored hash.
            if (password_verify($password, $user['password'])) {

                // Check if the user account is active
                if (!$user['is_active']) {
                    $_SESSION['login_error'] = 'Your account is deactivated. Please contact an administrator.';
                    header('Location: ' . url_for('login.php'));
                    exit();
                }

                // 4. Password is correct. Create the session.
                // Store essential, non-sensitive user data in the session.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role_id'] = $user['role_id'];

                // 5. Redirect to the dashboard
                header('Location: ' . url_for('dashboard.php'));
                exit();

            } else {
                // Password was incorrect
                $_SESSION['login_error'] = 'Invalid email or password.';
                header('Location: ' . url_for('login.php'));
                exit();
            }
        } else {
            // No user found with that email
            $_SESSION['login_error'] = 'Invalid email or password.';
            header('Location: ' . url_for('login.php'));
            exit();
        }
        $stmt->close();
    }
} else {
    // If someone tries to access this file directly, redirect them.
    header('Location: ' . url_for('login.php'));
    exit();
}
?>