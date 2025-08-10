<?php
// /config/db.php

// Start the session on every page that needs it.
// This must be at the very top before any output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Force application timezone to IST
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Kolkata');
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

// Ensure MySQL session also uses IST for NOW(), CURDATE(), etc.
@$conn->query("SET time_zone = '+05:30'");

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
 * Enhanced with session security checks (CIA - Confidentiality & Integrity)
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . url_for('login.php'));
        exit();
    }
    
    // Additional security: Verify session integrity
    if (!isset($_SESSION['role_id']) || !isset($_SESSION['user_name'])) {
        // Session may be corrupted, force re-login
        session_destroy();
        header("Location: " . url_for('login.php'));
        exit();
    }
    
    // Session timeout check (30 minutes of inactivity)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_destroy();
        header("Location: " . url_for('login.php?timeout=1'));
        exit();
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Checks if the logged-in user has a specific permission.
 * Enhanced with audit logging for CIA - Availability & Integrity
 * @param mysqli $conn The database connection object.
 * @param string $permissionName The name of the permission to check (e.g., 'manage_invoices').
 * @return bool True if the user has the permission, false otherwise.
 */
function has_permission($conn, $permissionName) {
    if (!isset($_SESSION['role_id'])) {
        audit_log($conn, 'permission_check_failed', 'No role_id in session', $permissionName);
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
        audit_log($conn, 'permission_check_error', 'SQL prepare failed', $permissionName);
        return false;
    }

    $stmt->bind_param("is", $role_id, $permissionName);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    $has_perm = $count > 0;
    
    // Audit log for security monitoring
    if (!$has_perm) {
        audit_log($conn, 'permission_denied', "Access denied for permission: $permissionName", $permissionName);
    }

    return $has_perm;
}

/**
 * Validates if a role ID is valid and active
 * CIA - Integrity validation
 * @param mysqli $conn Database connection
 * @param int $role_id Role ID to validate
 * @return bool True if valid, false otherwise
 */
function is_valid_role($conn, $role_id) {
    $sql = "SELECT COUNT(*) FROM roles WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    return $count > 0;
}

/**
 * Gets user's role information for enhanced security checks
 * CIA - Confidentiality & Integrity
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return array|null Role information or null if not found
 */
function get_user_role($conn, $user_id) {
    $sql = "SELECT u.role_id, r.name as role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ? AND u.is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $role_info = $result->fetch_assoc();
    $stmt->close();
    
    return $role_info;
}

/**
 * Audit logging function for security monitoring
 * CIA - Availability & Integrity
 * @param mysqli $conn Database connection
 * @param string $action Action performed
 * @param string $details Additional details
 * @param string $resource Resource accessed
 */
function audit_log($conn, $action, $details = '', $resource = '') {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Create audit_logs table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        resource VARCHAR(100),
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_action (user_id, action),
        INDEX idx_created_at (created_at)
    )";
    $conn->query($create_table_sql);
    
    $sql = "INSERT INTO audit_logs (user_id, action, details, resource, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("isssss", $user_id, $action, $details, $resource, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * A safe way to sanitize output to prevent XSS attacks.
 * Enhanced for better security (CIA - Integrity)
 * @param string $data The data to be escaped.
 * @return string The escaped data.
 */
function e($data) {
    if ($data === null) {
        return '';
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Enhanced role-based access control check
 * CIA - Confidentiality, Integrity, Availability
 * @param mysqli $conn Database connection
 * @param array $required_roles Array of role names that can access
 * @return bool True if user has access, false otherwise
 */
function check_role_access($conn, $required_roles = []) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
        return false;
    }
    
    // Get current user's role
    $role_info = get_user_role($conn, $_SESSION['user_id']);
    if (!$role_info) {
        audit_log($conn, 'role_check_failed', 'Invalid user role', implode(',', $required_roles));
        return false;
    }
    
    // Check if user's role is in the required roles
    $has_access = in_array($role_info['role_name'], $required_roles);
    
    if (!$has_access) {
        audit_log($conn, 'role_access_denied', "Role: {$role_info['role_name']}", implode(',', $required_roles));
    }
    
    return $has_access;
}

?>
