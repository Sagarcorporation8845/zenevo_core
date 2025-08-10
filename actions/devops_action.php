<?php
require_once __DIR__ . '/../config/db.php';

if (!check_role_access($conn, ['Admin','Team Lead','HR Manager'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$action = $_POST['action'] ?? '';
$uid = $_SESSION['user_id'] ?? null;

if ($action === 'create_sprint') {
    $name = trim($_POST['name'] ?? '');
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    if ($name && $start && $end) {
        $stmt = $conn->prepare('INSERT INTO sprints (name, start_date, end_date, created_by) VALUES (?,?,?,?)');
        $stmt->bind_param('sssi', $name, $start, $end, $uid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Sprint created'];
    }
    header('Location: ' . url_for('devops.php'));
    exit;
}

if ($action === 'create_task') {
    $title = trim($_POST['title'] ?? '');
    $description = $_POST['description'] ?? null;
    $sprint_id = $_POST['sprint_id'] ? (int)$_POST['sprint_id'] : null;
    $assignee = $_POST['assignee_employee_id'] ? (int)$_POST['assignee_employee_id'] : null;
    if ($title) {
        $stmt = $conn->prepare('INSERT INTO tasks (sprint_id, title, description, assignee_employee_id, created_by) VALUES (?,?,?,?,?)');
        $stmt->bind_param('issii', $sprint_id, $title, $description, $assignee, $uid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Task added'];
    }
    header('Location: ' . url_for('devops.php'));
    exit;
}

echo 'Invalid action';