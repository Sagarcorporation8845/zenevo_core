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
 * Returns the base URL path where the app is hosted (e.g., "/core" or "").
 * Computed from the filesystem paths of DOCUMENT_ROOT and the app root.
 */
function base_url_path() {
    static $cachedBasePath = null;
    if ($cachedBasePath !== null) {
        return $cachedBasePath;
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
    $documentRoot = $documentRoot ? rtrim(str_replace('\\', '/', $documentRoot), '/') : '';

    // App root is one level up from this file: /config -> app root
    $appRoot = realpath(__DIR__ . '/..');
    $appRoot = $appRoot ? rtrim(str_replace('\\', '/', $appRoot), '/') : '';

    $base = '';
    if ($documentRoot && $appRoot && strpos($appRoot, $documentRoot) === 0) {
        $base = substr($appRoot, strlen($documentRoot));
    }

    $base = '/' . ltrim($base, '/');
    $cachedBasePath = rtrim($base, '/');
    return $cachedBasePath;
}

/**
 * Prefixes a relative path with the app's base URL path.
 * Example: url_for('dashboard.php') -> '/core/dashboard.php' if app is under /core
 */
function url_for($path) {
    $base = base_url_path();
    return ($base ? $base : '') . '/' . ltrim($path, '/');
}

/**
 * Checks if a user is logged in.
 * If not, redirects to the login page.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . url_for('login.php'));
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
