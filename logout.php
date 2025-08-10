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

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

require_once __DIR__ . '/config/db.php';
// Redirect to the login page
header("Location: " . url_for('login.php'));
exit();
?>