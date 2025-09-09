<?php
require_once __DIR__ . '/../config/db.php';

if (!check_role_access($conn, ['Admin','Team Lead','HR Manager','Manager'])) {
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
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Sprint created successfully!'];
        audit_log($conn, 'sprint_created', "Sprint: $name", 'devops');
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'All fields are required for sprint creation.'];
    }
    header('Location: ' . url_for('devops.php'));
    exit;
}

if ($action === 'create_task') {
    $title = trim($_POST['title'] ?? '');
    $description = $_POST['description'] ?? null;
    $sprint_id = $_POST['sprint_id'] ? (int)$_POST['sprint_id'] : null;
    $assignee = $_POST['assignee_employee_id'] ? (int)$_POST['assignee_employee_id'] : null;
    $priority = $_POST['priority'] ?? 'Medium';
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    
    if ($title) {
        // Add columns if they don't exist
        $conn->query("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium'");
        $conn->query("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS deadline DATE NULL");
        
        $stmt = $conn->prepare('INSERT INTO tasks (sprint_id, title, description, assignee_employee_id, priority, deadline, created_by) VALUES (?,?,?,?,?,?,?)');
        $stmt->bind_param('issiiss', $sprint_id, $title, $description, $assignee, $priority, $deadline, $uid);
        
        if ($stmt->execute()) {
            // Send notification to assignee if task is assigned to someone specific
            if ($assignee) {
                $assignee_user_query = $conn->prepare("SELECT u.id FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
                $assignee_user_query->bind_param('i', $assignee);
                $assignee_user_query->execute();
                $assignee_user_query->bind_result($assignee_user_id);
                if ($assignee_user_query->fetch()) {
                    $assignee_user_query->close();
                    
                    $notify = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_by) VALUES (?, ?, ?, ?, ?)");
                    $notify_title = "New Task Assigned";
                    $notify_message = "You have been assigned a new task: {$title}";
                    $notify_type = "info";
                    $notify->bind_param('isssi', $assignee_user_id, $notify_title, $notify_message, $notify_type, $uid);
                    $notify->execute();
                    $notify->close();
                } else {
                    $assignee_user_query->close();
                }
            }
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Task created successfully!'];
            audit_log($conn, 'task_created', "Task: $title", 'devops');
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to create task.'];
        }
        $stmt->close();
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Task title is required.'];
    }
    header('Location: ' . url_for('devops.php'));
    exit;
}

if ($action === 'delete_task') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    if ($task_id) {
        $stmt = $conn->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->bind_param('i', $task_id);
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Task deleted successfully.'];
            audit_log($conn, 'task_deleted', "Task ID: $task_id", 'devops');
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to delete task.'];
        }
        $stmt->close();
    }
    header('Location: ' . url_for('devops.php'));
    exit;
}

echo 'Invalid action';