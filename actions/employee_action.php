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
        $role_id = 4; // Default role for new employees is 'Employee'

        // --- 2. Server-side Validation ---
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($designation) || empty($department) || empty($date_of_joining)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'All fields are required.'];
            header('Location: ' . url_for('add_employee.php'));
            exit();
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
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => $message];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to update employee status.'];
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