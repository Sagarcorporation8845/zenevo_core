<?php
require_once __DIR__ . '/../config/db.php';

$uid = $_SESSION['user_id'] ?? null;
if (!$uid) { header('Location: ' . url_for('login.php')); exit; }

// Get employee id
$employee_id = null;
if ($stmt = $conn->prepare('SELECT id FROM employees WHERE user_id = ?')) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($employee_id);
    $stmt->fetch();
    $stmt->close();
}

$action = $_POST['action'] ?? '';
if ($action === 'create' && $employee_id) {
    $date = $_POST['date'] ?? date('Y-m-d');
    $flag = $_POST['flag'] ?? 'Miss Punch';
    $reason = $_POST['reason'] ?? null;
    $stmt = $conn->prepare('INSERT INTO attendance_tickets (employee_id, attendance_date, flag, reason) VALUES (?,?,?,?)');
    $stmt->bind_param('isss', $employee_id, $date, $flag, $reason);
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Ticket created'];
}
header('Location: ' . url_for('my_attendance.php?month=' . substr($_POST['date'] ?? date('Y-m-d'),0,7)));
exit;