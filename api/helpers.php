<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

function json_response($data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function ensure_schema(mysqli $conn) {
    // Auth tokens table
    $conn->query("CREATE TABLE IF NOT EXISTS auth_tokens (
        token VARCHAR(64) PRIMARY KEY,
        user_id INT NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1");

    // Attendance config single-row table
    $conn->query("CREATE TABLE IF NOT EXISTS attendance_config (
        id INT PRIMARY KEY DEFAULT 1,
        office_lat DECIMAL(10,7) NOT NULL DEFAULT 0,
        office_lng DECIMAL(10,7) NOT NULL DEFAULT 0,
        radius_meters INT NOT NULL DEFAULT 50,
        in_start TIME NOT NULL DEFAULT '09:30:00',
        in_end TIME NOT NULL DEFAULT '09:45:00',
        out_start TIME NOT NULL DEFAULT '17:30:00',
        out_end TIME NOT NULL DEFAULT '17:45:00',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1");

    // Ensure default row exists
    $conn->query("INSERT IGNORE INTO attendance_config (id) VALUES (1)");

    // Extend attendance table for geofence and selfies
    $conn->query("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS in_photo_base64 LONGTEXT NULL");
    $conn->query("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS out_photo_base64 LONGTEXT NULL");
    $conn->query("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS in_lat DECIMAL(10,7) NULL");
    $conn->query("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS in_lng DECIMAL(10,7) NULL");
    $conn->query("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS out_lat DECIMAL(10,7) NULL");
    $conn->query("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS out_lng DECIMAL(10,7) NULL");
}

function generate_token(): string {
    return bin2hex(random_bytes(32));
}

function get_bearer_token(): ?string {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            return trim($matches[1]);
        }
    }
    // Also support token in request for easier testing
    if (isset($_GET['token'])) return $_GET['token'];
    if (isset($_POST['token'])) return $_POST['token'];
    return null;
}

function authenticate_user(mysqli $conn) {
    $token = get_bearer_token();
    if (!$token) {
        json_response(['success' => false, 'error' => 'No token provided'], 401);
    }
    $stmt = $conn->prepare("SELECT user_id FROM auth_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if (!$row) {
        json_response(['success' => false, 'error' => 'Invalid or expired token'], 401);
    }
    $user_id = (int)$row['user_id'];
    // Load user minimal profile
    $stmt = $conn->prepare("SELECT id, name, email, role_id, is_active FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user || !$user['is_active']) {
        json_response(['success' => false, 'error' => 'User disabled or not found'], 403);
    }
    return $user;
}

function get_config(mysqli $conn): array {
    $res = $conn->query("SELECT * FROM attendance_config WHERE id = 1");
    return $res->fetch_assoc();
}

function haversine_distance_m(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371000.0; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

function get_employee_id(mysqli $conn, int $user_id): ?int {
    $stmt = $conn->prepare("SELECT id FROM employees WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : null;
}