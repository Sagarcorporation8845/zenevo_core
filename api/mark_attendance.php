<?php
require_once __DIR__ . '/helpers.php';
ensure_schema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST required'], 405);
}

$user = authenticate_user($conn);
$config = get_config($conn);

$type = strtolower($_POST['type'] ?? ''); // 'in' or 'out'
$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$photo = $_POST['photo_base64'] ?? '';

if (!in_array($type, ['in','out'], true)) {
    json_response(['success' => false, 'error' => 'type must be in|out'], 400);
}
if ($lat === null || $lng === null) {
    json_response(['success' => false, 'error' => 'lat and lng are required'], 400);
}
if (!$photo) {
    json_response(['success' => false, 'error' => 'photo_base64 is required'], 400);
}

$distance_m = haversine_distance_m($lat, $lng, (float)$config['office_lat'], (float)$config['office_lng']);
if ($distance_m > (int)$config['radius_meters']) {
    json_response(['success' => false, 'error' => 'Please reach office in time', 'distance_m' => round($distance_m, 2)], 403);
}

$now = new DateTime('now');
$time = $now->format('H:i:s');
if ($type === 'in' && !($time >= $config['in_start'] && $time <= $config['in_end'])) {
    json_response(['success' => false, 'error' => 'Not within check-in window'], 403);
}
if ($type === 'out' && !($time >= $config['out_start'] && $time <= $config['out_end'])) {
    json_response(['success' => false, 'error' => 'Not within check-out window'], 403);
}

$employee_id = get_employee_id($conn, (int)$user['id']);
if (!$employee_id) {
    json_response(['success' => false, 'error' => 'Employee profile not found'], 404);
}

$today = $now->format('Y-m-d');

// Upsert attendance row for today
$stmt = $conn->prepare("SELECT id, clock_in_time, clock_out_time FROM attendance WHERE employee_id = ? AND date = ? LIMIT 1");
$stmt->bind_param('is', $employee_id, $today);
$stmt->execute();
$att = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($type === 'in') {
    if ($att && !empty($att['clock_in_time'])) {
        json_response(['success' => false, 'error' => 'Already clocked in'], 409);
    }
    if ($att) {
        $stmt = $conn->prepare("UPDATE attendance SET clock_in_time = NOW(), in_photo_base64 = ?, in_lat = ?, in_lng = ? WHERE id = ?");
        $stmt->bind_param('sdsi', $photo, $lat, $lng, $att['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, clock_in_time, date, in_photo_base64, in_lat, in_lng) VALUES (?, NOW(), ?, ?, ?, ?)");
        $stmt->bind_param('issdd', $employee_id, $today, $photo, $lat, $lng);
    }
    $stmt->execute();
    $stmt->close();
    audit_log($conn, 'attendance_in', 'Marked IN via API', 'attendance_api');
    json_response(['success' => true, 'message' => 'Attendance marked', 'type' => 'in']);
}

if ($type === 'out') {
    if (!$att || empty($att['clock_in_time'])) {
        json_response(['success' => false, 'error' => 'Cannot clock out before clocking in'], 409);
    }
    if (!empty($att['clock_out_time'])) {
        json_response(['success' => false, 'error' => 'Already clocked out'], 409);
    }
    $stmt = $conn->prepare("UPDATE attendance SET clock_out_time = NOW(), out_photo_base64 = ?, out_lat = ?, out_lng = ? WHERE id = ?");
    $stmt->bind_param('sdsi', $photo, $lat, $lng, $att['id']);
    $stmt->execute();
    $stmt->close();
    audit_log($conn, 'attendance_out', 'Marked OUT via API', 'attendance_api');
    json_response(['success' => true, 'message' => 'Attendance marked', 'type' => 'out']);
}