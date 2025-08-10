<?php
require_once '../config/db.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    if (!has_permission($conn, 'manage_employees')) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to update settings.'];
        header('Location: ' . url_for('attendance_settings.php'));
        exit();
    }
    require_once __DIR__ . '/../api/helpers.php';
    ensure_schema($conn);

    $office_lat = (float)$_POST['office_lat'];
    $office_lng = (float)$_POST['office_lng'];
    $radius_meters = (int)$_POST['radius_meters'];
    $in_start = $_POST['in_start'];
    $in_end = $_POST['in_end'];
    $out_start = $_POST['out_start'];
    $out_end = $_POST['out_end'];

    $stmt = $conn->prepare("UPDATE attendance_config SET office_lat=?, office_lng=?, radius_meters=?, in_start=?, in_end=?, out_start=?, out_end=? WHERE id=1");
    $stmt->bind_param('ddissss', $office_lat, $office_lng, $radius_meters, $in_start, $in_end, $out_start, $out_end);
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Attendance settings updated.'];
        audit_log($conn, 'attendance_settings_updated', 'Updated geofence/time windows', 'attendance_settings');
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to update settings.'];
    }
    $stmt->close();
    header('Location: ' . url_for('attendance_settings.php'));
    exit();
}

header('Location: ' . url_for('dashboard.php'));
exit();