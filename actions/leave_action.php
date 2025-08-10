<?php
/*
--------------------------------------------------------------------------------
-- File: /actions/leave_action.php (NEW FILE)
-- Description: Handles all logic related to leave requests.
--------------------------------------------------------------------------------
*/
require_once '../config/db.php';
require_login();

// --- Main Logic: Check the requested action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- Apply for Leave Action ---
    if ($_POST['action'] === 'apply_for_leave') {
        // 1. Get and validate form data
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = trim($_POST['reason']);
        $user_id = $_SESSION['user_id']; // The logged-in user is applying for leave

        if (empty($start_date) || empty($end_date) || empty($reason)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'All fields are required.'];
            header('Location: ' . url_for('apply_leave.php'));
            exit();
        }

        // 2. Get the employee_id from the user_id
        $stmt_emp = $conn->prepare("SELECT id FROM employees WHERE user_id = ?");
        $stmt_emp->bind_param("i", $user_id);
        $stmt_emp->execute();
        $result_emp = $stmt_emp->get_result();
        if ($result_emp->num_rows === 0) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Could not find employee record for your user account.'];
            header('Location: ' . url_for('apply_leave.php'));
            exit();
        }
        $employee = $result_emp->fetch_assoc();
        $employee_id = $employee['id'];
        $stmt_emp->close();

        // 3. Insert into the database
        $sql = "INSERT INTO leaves (employee_id, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $employee_id, $start_date, $end_date, $reason);

        if ($stmt->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Leave request submitted successfully.'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to submit leave request. Please try again.'];
        }
        $stmt->close();
        header('Location: ' . url_for('leaves.php'));
        exit();
    }

    // --- Update Leave Status Action (for Managers) ---
    if ($_POST['action'] === 'update_leave_status') {
        // Security check
        if (!has_permission($conn, 'manage_leaves')) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to perform this action.'];
            header('Location: ' . url_for('leaves.php'));
            exit();
        }

        $leave_id = $_POST['leave_id'];
        $new_status = $_POST['status']; // 'Approved' or 'Rejected'

        if (empty($leave_id) || !in_array($new_status, ['Approved', 'Rejected'])) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid request.'];
            header('Location: ' . url_for('leaves.php'));
            exit();
        }

        $sql = "UPDATE leaves SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $leave_id);

        if ($stmt->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Leave request has been ' . strtolower($new_status) . '.'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to update leave status.'];
        }
        $stmt->close();
        header('Location: ' . url_for('leaves.php'));
        exit();
    }
} else {
    header('Location: ' . url_for('dashboard.php'));
    exit();
}
?>