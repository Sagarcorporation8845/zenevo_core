<?php
/*
--------------------------------------------------------------------------------
-- File: /logout.php
-- Description: Destroys the user session and logs them out.
--------------------------------------------------------------------------------
*/

// Start the session to access session variables
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// Audit log logout before destroying session (CIA - Availability)
if (isset($_SESSION['user_id']) && isset($_SESSION['user_name'])) {
    audit_log($conn, 'logout', "User: {$_SESSION['user_name']} (ID: {$_SESSION['user_id']})", 'authentication');
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: " . url_for('login.php'));
exit();
?>