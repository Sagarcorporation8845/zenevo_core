<?php
require_once __DIR__ . '/helpers.php';
ensure_schema($conn);

$user = authenticate_user($conn);
$config = get_config($conn);

$lat = isset($_REQUEST['lat']) ? (float)$_REQUEST['lat'] : null;
$lng = isset($_REQUEST['lng']) ? (float)$_REQUEST['lng'] : null;
$type = isset($_REQUEST['type']) ? strtolower(trim($_REQUEST['type'])) : null; // optional 'in'|'out'

if ($lat === null || $lng === null) {
    json_response(['success' => false, 'error' => 'lat and lng are required'], 400);
}

$distance_m = haversine_distance_m($lat, $lng, (float)$config['office_lat'], (float)$config['office_lng']);
$geofence_ok = $distance_m <= (int)$config['radius_meters'];

$now = new DateTime('now');
$today = $now->format('Y-m-d');
$time = $now->format('H:i:s');

$employee_id = get_employee_id($conn, (int)$user['id']);
$attendance = null;
if ($employee_id) {
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ? LIMIT 1");
    $stmt->bind_param('is', $employee_id, $today);
    $stmt->execute();
    $attendance = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

function within(string $start, string $end, string $timeNow): bool {
    return ($timeNow >= $start && $timeNow <= $end);
}

$in_window = within($config['in_start'], $config['in_end'], $time);
$out_window = within($config['out_start'], $config['out_end'], $time);

$already_in = $attendance && !empty($attendance['clock_in_time']);
$already_out = $attendance && !empty($attendance['clock_out_time']);

$can_mark_in = $geofence_ok && $in_window && !$already_in;
$can_mark_out = $geofence_ok && $out_window && $already_in && !$already_out;

$response = [
    'success' => true,
    'geofence' => [
        'ok' => $geofence_ok,
        'distance_m' => round($distance_m, 2),
        'radius_m' => (int)$config['radius_meters']
    ],
    'time' => [
        'server' => $now->format(DateTime::ATOM),
        'in_window' => $in_window,
        'out_window' => $out_window,
        'in_start' => $config['in_start'],
        'in_end' => $config['in_end'],
        'out_start' => $config['out_start'],
        'out_end' => $config['out_end']
    ],
    'status' => [
        'already_in' => $already_in,
        'already_out' => $already_out,
        'can_mark_in' => $can_mark_in,
        'can_mark_out' => $can_mark_out
    ]
];

if ($type === 'in') {
    $response['allowed'] = $can_mark_in;
    $response['message'] = $geofence_ok ? ($in_window ? ($already_in ? 'Already clocked in' : 'You can mark attendance') : 'Not in check-in time window') : 'Please reach office location';
} elseif ($type === 'out') {
    $response['allowed'] = $can_mark_out;
    $response['message'] = $geofence_ok ? ($out_window ? ($already_out ? 'Already clocked out' : 'You can mark check-out') : 'Not in check-out time window') : 'Please reach office location';
}

json_response($response);