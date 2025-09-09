<?php
require_once '../config/db.php';
require_login();

// Security check - only Admin and HR can manage resources
if (!check_role_access($conn, ['Admin', 'HR Manager'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Access denied. Insufficient permissions.'];
    header('Location: ' . url_for('dashboard.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Assign Manager to Employee
    if ($_POST['action'] === 'assign_manager') {
        $employee_id = (int)$_POST['employee_id'];
        $manager_id = (int)$_POST['manager_id'];
        $assigned_by = $_SESSION['user_id'];
        
        if (!$employee_id || !$manager_id) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please select both employee and manager.'];
            header('Location: ' . url_for('resource_management.php'));
            exit();
        }
        
        // Validate that manager_id is actually a manager
        $manager_check = $conn->prepare("SELECT COUNT(*) FROM employees e 
                                        JOIN users u ON e.user_id = u.id 
                                        JOIN roles r ON u.role_id = r.id 
                                        WHERE e.id = ? AND r.name = 'Manager'");
        $manager_check->bind_param('i', $manager_id);
        $manager_check->execute();
        $manager_check->bind_result($is_manager);
        $manager_check->fetch();
        $manager_check->close();
        
        if (!$is_manager) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Selected user is not a manager.'];
            header('Location: ' . url_for('resource_management.php'));
            exit();
        }
        
        // Check if employee already has an active manager
        $existing_check = $conn->prepare("SELECT manager_id, CONCAT(m.first_name, ' ', m.last_name) as manager_name 
                                         FROM employee_managers em 
                                         JOIN employees m ON em.manager_id = m.id 
                                         WHERE em.employee_id = ? AND em.is_active = 1");
        $existing_check->bind_param('i', $employee_id);
        $existing_check->execute();
        $existing_result = $existing_check->get_result();
        
        if ($existing_result->num_rows > 0) {
            $existing = $existing_result->fetch_assoc();
            $existing_check->close();
            
            // If trying to assign the same manager, show message
            if ($existing['manager_id'] == $manager_id) {
                $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Employee is already assigned to this manager.'];
                header('Location: ' . url_for('resource_management.php'));
                exit();
            }
            
            // Deactivate previous assignment
            $deactivate = $conn->prepare("UPDATE employee_managers SET is_active = 0 WHERE employee_id = ? AND is_active = 1");
            $deactivate->bind_param('i', $employee_id);
            $deactivate->execute();
            $deactivate->close();
            
            // Send notification to previous manager
            $prev_manager_user = $conn->prepare("SELECT u.id FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
            $prev_manager_user->bind_param('i', $existing['manager_id']);
            $prev_manager_user->execute();
            $prev_manager_user->bind_result($prev_manager_user_id);
            $prev_manager_user->fetch();
            $prev_manager_user->close();
            
            // Get employee name for notification
            $emp_name_query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM employees WHERE id = ?");
            $emp_name_query->bind_param('i', $employee_id);
            $emp_name_query->execute();
            $emp_name_query->bind_result($employee_name);
            $emp_name_query->fetch();
            $emp_name_query->close();
            
            if ($prev_manager_user_id) {
                $notify_prev = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_by) VALUES (?, ?, ?, ?, ?)");
                $title = "Team Member Reassigned";
                $message = "Employee {$employee_name} has been reassigned to a different manager.";
                $type = "warning";
                $notify_prev->bind_param('isssi', $prev_manager_user_id, $title, $message, $type, $assigned_by);
                $notify_prev->execute();
                $notify_prev->close();
            }
        } else {
            $existing_check->close();
        }
        
        // Create new assignment
        $assign = $conn->prepare("INSERT INTO employee_managers (employee_id, manager_id, assigned_by) VALUES (?, ?, ?)");
        $assign->bind_param('iii', $employee_id, $manager_id, $assigned_by);
        
        if ($assign->execute()) {
            // Send notification to new manager
            $new_manager_user = $conn->prepare("SELECT u.id FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
            $new_manager_user->bind_param('i', $manager_id);
            $new_manager_user->execute();
            $new_manager_user->bind_result($new_manager_user_id);
            $new_manager_user->fetch();
            $new_manager_user->close();
            
            // Get employee name if not already fetched
            if (!isset($employee_name)) {
                $emp_name_query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM employees WHERE id = ?");
                $emp_name_query->bind_param('i', $employee_id);
                $emp_name_query->execute();
                $emp_name_query->bind_result($employee_name);
                $emp_name_query->fetch();
                $emp_name_query->close();
            }
            
            if ($new_manager_user_id) {
                $notify_new = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_by) VALUES (?, ?, ?, ?, ?)");
                $title = "New Team Member Assigned";
                $message = "Employee {$employee_name} has been assigned to your team.";
                $type = "success";
                $notify_new->bind_param('isssi', $new_manager_user_id, $title, $message, $type, $assigned_by);
                $notify_new->execute();
                $notify_new->close();
            }
            
            // Notify the employee
            $emp_user_query = $conn->prepare("SELECT u.id FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
            $emp_user_query->bind_param('i', $employee_id);
            $emp_user_query->execute();
            $emp_user_query->bind_result($emp_user_id);
            $emp_user_query->fetch();
            $emp_user_query->close();
            
            if ($emp_user_id) {
                $manager_name_query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM employees WHERE id = ?");
                $manager_name_query->bind_param('i', $manager_id);
                $manager_name_query->execute();
                $manager_name_query->bind_result($manager_name);
                $manager_name_query->fetch();
                $manager_name_query->close();
                
                $notify_emp = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_by) VALUES (?, ?, ?, ?, ?)");
                $title = "Manager Assigned";
                $message = "You have been assigned to {$manager_name} as your manager.";
                $type = "info";
                $notify_emp->bind_param('isssi', $emp_user_id, $title, $message, $type, $assigned_by);
                $notify_emp->execute();
                $notify_emp->close();
            }
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Manager assigned successfully!'];
            audit_log($conn, 'manager_assigned', "Employee ID: $employee_id assigned to Manager ID: $manager_id", 'resource_management');
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to assign manager. Please try again.'];
        }
        $assign->close();
    }
    
    // Remove Manager Assignment
    elseif ($_POST['action'] === 'remove_manager') {
        $employee_id = (int)$_POST['employee_id'];
        
        if (!$employee_id) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid employee selection.'];
            header('Location: ' . url_for('resource_management.php'));
            exit();
        }
        
        // Get current manager info for notification
        $current_manager = $conn->prepare("SELECT em.manager_id, CONCAT(m.first_name, ' ', m.last_name) as manager_name, u.id as manager_user_id
                                          FROM employee_managers em 
                                          JOIN employees m ON em.manager_id = m.id 
                                          JOIN users u ON m.user_id = u.id
                                          WHERE em.employee_id = ? AND em.is_active = 1");
        $current_manager->bind_param('i', $employee_id);
        $current_manager->execute();
        $manager_result = $current_manager->get_result();
        
        if ($manager_result->num_rows > 0) {
            $manager_info = $manager_result->fetch_assoc();
            $current_manager->close();
            
            // Deactivate assignment
            $remove = $conn->prepare("UPDATE employee_managers SET is_active = 0 WHERE employee_id = ? AND is_active = 1");
            $remove->bind_param('i', $employee_id);
            
            if ($remove->execute()) {
                // Get employee info
                $emp_info = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, u.id as user_id 
                                          FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
                $emp_info->bind_param('i', $employee_id);
                $emp_info->execute();
                $emp_result = $emp_info->get_result();
                $employee_info = $emp_result->fetch_assoc();
                $emp_info->close();
                
                // Notify manager
                $notify_manager = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_by) VALUES (?, ?, ?, ?, ?)");
                $title = "Team Member Removed";
                $message = "Employee {$employee_info['name']} has been removed from your team.";
                $type = "warning";
                $notify_manager->bind_param('isssi', $manager_info['manager_user_id'], $title, $message, $type, $_SESSION['user_id']);
                $notify_manager->execute();
                $notify_manager->close();
                
                // Notify employee
                $notify_employee = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_by) VALUES (?, ?, ?, ?, ?)");
                $title = "Manager Assignment Removed";
                $message = "Your manager assignment has been removed. You will be notified when a new manager is assigned.";
                $type = "info";
                $notify_employee->bind_param('isssi', $employee_info['user_id'], $title, $message, $type, $_SESSION['user_id']);
                $notify_employee->execute();
                $notify_employee->close();
                
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Manager assignment removed successfully.'];
                audit_log($conn, 'manager_removed', "Employee ID: $employee_id manager assignment removed", 'resource_management');
            } else {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to remove manager assignment.'];
            }
            $remove->close();
        } else {
            $current_manager->close();
            $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'No active manager assignment found for this employee.'];
        }
    }
    
    // Send Broadcast Message
    elseif ($_POST['action'] === 'send_broadcast') {
        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        $type = $_POST['type'];
        $created_by = $_SESSION['user_id'];
        
        if (empty($title) || empty($message)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Title and message are required.'];
            header('Location: ' . url_for('resource_management.php'));
            exit();
        }
        
        // Get all active users
        $users = $conn->query("SELECT id FROM users WHERE is_active = 1");
        $notification_count = 0;
        
        $broadcast_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_by) VALUES (?, ?, ?, ?, ?)");
        
        while ($user = $users->fetch_assoc()) {
            $broadcast_stmt->bind_param('isssi', $user['id'], $title, $message, $type, $created_by);
            if ($broadcast_stmt->execute()) {
                $notification_count++;
            }
        }
        $broadcast_stmt->close();
        
        if ($notification_count > 0) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Broadcast message sent to {$notification_count} users successfully!"];
            audit_log($conn, 'broadcast_sent', "Broadcast message sent to {$notification_count} users: {$title}", 'resource_management');
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to send broadcast message.'];
        }
    }
    
    header('Location: ' . url_for('resource_management.php'));
    exit();
} else {
    header('Location: ' . url_for('dashboard.php'));
    exit();
}
?>