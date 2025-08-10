<?php
// /config/db.php

// Start the session on every page that needs it.
// This must be at the very top before any output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Database Configuration ---
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root'); // Your database username
define('DB_PASS', '');     // Your database password
define('DB_NAME', 'core'); // Your database name

// --- Create a new MySQLi connection ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- Check for connection errors ---
if ($conn->connect_error) {
    // In a real production environment, you would log this error
    // and show a more user-friendly message.
    die("Connection failed: " . $conn->connect_error);
}

// --- Helper Functions ---

/**
 * Checks if a user is logged in.
 * If not, redirects to the login page.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit();
    }
}

/**
 * Checks if the logged-in user has a specific permission.
 * @param mysqli $conn The database connection object.
 * @param string $permissionName The name of the permission to check (e.g., 'manage_invoices').
 * @return bool True if the user has the permission, false otherwise.
 */
function has_permission($conn, $permissionName) {
    if (!isset($_SESSION['role_id'])) {
        return false;
    }

    $role_id = $_SESSION['role_id'];

    $sql = "SELECT COUNT(*)
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.name = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Handle SQL error
        error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        return false;
    }

    $stmt->bind_param("is", $role_id, $permissionName);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count > 0;
}

/**
 * A safe way to sanitize output to prevent XSS attacks.
 * @param string $data The data to be escaped.
 * @return string The escaped data.
 */
function e($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

?>
