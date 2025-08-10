<?php
require_once __DIR__ . '/../config/db.php';

// Ensure tickets table exists
$conn->query("CREATE TABLE IF NOT EXISTS attendance_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  attendance_date DATE NOT NULL,
  flag ENUM('Late Entry','Half Day','Early Out','Miss Punch') NOT NULL,
  reason TEXT NULL,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT NULL,
  reviewed_at TIMESTAMP NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;");

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

    // Allow only specific flags
    $allowed = ['Late Entry','Half Day','Early Out','Miss Punch'];
    if (!in_array($flag, $allowed, true)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid ticket type'];
    } else {
        $stmt = $conn->prepare('INSERT INTO attendance_tickets (employee_id, attendance_date, flag, reason) VALUES (?,?,?,?)');
        $stmt->bind_param('isss', $employee_id, $date, $flag, $reason);
        $stmt->execute();
        $stmt->close();
        audit_log($conn, 'attendance_ticket_create', "flag=$flag date=$date");
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Ticket created'];
    }
}
header('Location: ' . url_for('my_attendance.php?month=' . substr($_POST['date'] ?? date('Y-m-d'),0,7)));
exit;