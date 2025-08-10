<?php
/*
--------------------------------------------------------------------------------
-- File: /actions/employee_action.php (UPDATED)
-- Description: Handles creating, updating, and deactivating employees.
--------------------------------------------------------------------------------
*/
require_once '../config/db.php';
require_login();

// --- Main Logic: Check the requested action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- Create Employee Action ---
    if ($_POST['action'] === 'create_employee') {
        // Security check
        if (!has_permission($conn, 'manage_employees')) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to perform this action.'];
            header('Location: ' . url_for('employees.php'));
            exit();
        }

        // --- 1. Get and Validate Form Data ---
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $designation = trim($_POST['designation']);
        $department = trim($_POST['department']);
        $date_of_joining = $_POST['date_of_joining'];
        $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 4; // Default role for new employees is 'Employee'

        // --- 2. Server-side Validation ---
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($designation) || empty($department) || empty($date_of_joining)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'All fields are required.'];
            header('Location: ' . url_for('add_employee.php'));
            exit();
        }
        
        // Validate role ID (CIA - Integrity)
        if (!is_valid_role($conn, $role_id)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid role selected.'];
            audit_log($conn, 'invalid_role_attempt', "Attempted to assign invalid role: $role_id", 'employee_creation');
            header('Location: ' . url_for('add_employee.php'));
            exit();
        }
        
        // Additional role assignment security check
        $current_user_role = get_user_role($conn, $_SESSION['user_id']);
        if ($current_user_role && $role_id <= 2) { // Admin or HR Manager roles
            // Only Admin can assign Admin/HR Manager roles
            if ($current_user_role['role_name'] !== 'Admin') {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to assign this role.'];
                audit_log($conn, 'unauthorized_role_assignment', "Attempted to assign role ID: $role_id", 'employee_creation');
                header('Location: ' . url_for('add_employee.php'));
                exit();
            }
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid email format.'];
            header('Location: ' . url_for('add_employee.php'));
            exit();
        }

        // --- 3. Database Transaction ---
        $conn->begin_transaction();
        try {
            // Check if email already exists
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception("An account with this email already exists.");
            }
            $stmt_check->close();

            // A) Insert into `users` table
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $full_name = $first_name . ' ' . $last_name;
            $sql_user = "INSERT INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)";
            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->bind_param("sssi", $full_name, $email, $hashed_password, $role_id);
            $stmt_user->execute();
            $user_id = $conn->insert_id;
            $stmt_user->close();

            // B) Insert into `employees` table
            $sql_employee = "INSERT INTO employees (user_id, first_name, last_name, designation, department, date_of_joining) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_employee = $conn->prepare($sql_employee);
            $stmt_employee->bind_param("isssss", $user_id, $first_name, $last_name, $designation, $department, $date_of_joining);
            $stmt_employee->execute();
            $stmt_employee->close();

            // Commit the transaction
            $conn->commit();
            
            // Audit log successful employee creation (CIA - Availability)
            audit_log($conn, 'employee_created', "Created employee: $full_name (Email: $email, Role ID: $role_id)", 'employee_management');
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Employee added successfully!'];
            header('Location: ' . url_for('employees.php'));
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to add employee: ' . $e->getMessage()];
            header('Location: ' . url_for('add_employee.php'));
            exit();
        }
    }

    // --- Update Employee Action ---
    if ($_POST['action'] === 'update_employee') {
        if (!has_permission($conn, 'manage_employees')) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to perform this action.'];
            header('Location: ' . url_for('employees.php'));
            exit();
        }

        $employee_id = (int)$_POST['employee_id'];
        $user_id = (int)$_POST['user_id'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $designation = trim($_POST['designation']);
        $department = trim($_POST['department']);
        $date_of_joining = $_POST['date_of_joining'];
        $email = trim($_POST['email']);
        $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : null;

        if (!$employee_id || !$user_id || !$first_name || !$last_name || !$designation || !$department || !$date_of_joining || !$email || !$role_id) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'All fields are required.'];
            header('Location: ' . url_for('edit_employee.php?id=' . $employee_id));
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid email format.'];
            header('Location: ' . url_for('edit_employee.php?id=' . $employee_id));
            exit();
        }
        if (!is_valid_role($conn, $role_id)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid role selected.'];
            header('Location: ' . url_for('edit_employee.php?id=' . $employee_id));
            exit();
        }

        // Only Admin can assign Admin/HR roles
        $current_user_role = get_user_role($conn, $_SESSION['user_id']);
        if ($role_id <= 2 && $current_user_role['role_name'] !== 'Admin') {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to assign this role.'];
            header('Location: ' . url_for('edit_employee.php?id=' . $employee_id));
            exit();
        }

        $conn->begin_transaction();
        try {
            // Update users email/name/role
            $full_name = $first_name . ' ' . $last_name;
            $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, role_id = ? WHERE id = ?");
            $stmt_user->bind_param("ssii", $full_name, $email, $role_id, $user_id);
            $stmt_user->execute();
            $stmt_user->close();

            // Update employee details
            $stmt_emp = $conn->prepare("UPDATE employees SET first_name = ?, last_name = ?, designation = ?, department = ?, date_of_joining = ? WHERE id = ?");
            $stmt_emp->bind_param("sssssi", $first_name, $last_name, $designation, $department, $date_of_joining, $employee_id);
            $stmt_emp->execute();
            $stmt_emp->close();

            $conn->commit();
            audit_log($conn, 'employee_updated', "Employee ID: $employee_id updated", 'employee_management');
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Employee updated successfully.'];
            header('Location: ' . url_for('employees.php'));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to update employee.'];
            header('Location: ' . url_for('edit_employee.php?id=' . $employee_id));
            exit();
        }
    }

    // --- Toggle Employee Status Action ---
    if ($_POST['action'] === 'toggle_status') {
        // Security check
        if (!has_permission($conn, 'manage_employees')) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to perform this action.'];
            header('Location: ' . url_for('employees.php'));
            exit();
        }

        $user_id = $_POST['user_id'];
        $current_status = $_POST['current_status'];

        // Determine the new status
        $new_status = ($current_status == 1) ? 0 : 1; // 0 for inactive, 1 for active

        // Prepare the update query
        $sql = "UPDATE users SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $new_status, $user_id);

        if ($stmt->execute()) {
            $message = ($new_status == 1) ? 'Employee account has been activated.' : 'Employee account has been deactivated.';
            
            // Audit log status change (CIA - Availability & Integrity)
            $action_type = ($new_status == 1) ? 'employee_activated' : 'employee_deactivated';
            audit_log($conn, $action_type, "User ID: $user_id status changed to: $new_status", 'employee_management');
            
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => $message];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to update employee status.'];
            audit_log($conn, 'employee_status_update_failed', "Failed to update user ID: $user_id", 'employee_management');
        }
        $stmt->close();
        header('Location: ' . url_for('employees.php'));
        exit();
    }

} else {
    // If accessed directly or without a POST method
    header('Location: ' . url_for('dashboard.php'));
    exit();
}
?>