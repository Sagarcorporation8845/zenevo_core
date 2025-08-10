<?php
// ... existing code ...
$pageTitle = 'Edit Employee';
include 'includes/header.php';

if (!has_permission($conn, 'manage_employees')) {
    echo '<div class="p-6 bg-white rounded-lg shadow-md">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($employee_id <= 0) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid employee selected.'];
    header('Location: ' . url_for('employees.php'));
    exit();
}

$sql = "SELECT e.id, e.user_id, e.first_name, e.last_name, e.designation, e.department, e.date_of_joining, u.email, u.role_id
        FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Employee not found.'];
    header('Location: ' . url_for('employees.php'));
    exit();
}
?>

<div class="container mx-auto max-w-4xl">
    <form action="actions/employee_action.php" method="POST">
        <input type="hidden" name="action" value="update_employee">
        <input type="hidden" name="employee_id" value="<?php echo e($employee['id']); ?>">
        <input type="hidden" name="user_id" value="<?php echo e($employee['user_id']); ?>">
        <div class="bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-2 text-gray-800">Edit Employee</h2>

            <div class="border-b border-gray-200 pb-6 mb-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Personal Details</h3>
                <div class="mt-4 form-grid form-grid-2">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" name="first_name" id="first_name" required class="w-full enhanced-input" value="<?php echo e($employee['first_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" name="last_name" id="last_name" required class="w-full enhanced-input" value="<?php echo e($employee['last_name']); ?>">
                    </div>
                </div>
            </div>

            <div class="border-b border-gray-200 pb-6 mb-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Job Details</h3>
                <div class="mt-4 form-grid form-grid-3">
                    <div class="form-group">
                        <label for="designation">Designation</label>
                        <input type="text" name="designation" id="designation" required class="w-full enhanced-input" value="<?php echo e($employee['designation']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" name="department" id="department" required class="w-full enhanced-input" value="<?php echo e($employee['department']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_of_joining">Date of Joining</label>
                        <input type="date" name="date_of_joining" id="date_of_joining" required class="w-full enhanced-input" value="<?php echo e($employee['date_of_joining']); ?>">
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-medium leading-6 text-gray-900">Account Credentials</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" required class="w-full enhanced-input" value="<?php echo e($employee['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="role_id">Role</label>
                        <select name="role_id" id="role_id" required class="w-full enhanced-input">
                            <?php
                            $current_user_role = get_user_role($conn, $_SESSION['user_id']);
                            $roles_sql = "SELECT id, name FROM roles ORDER BY name";
                            $roles_result = $conn->query($roles_sql);
                            while($role = $roles_result->fetch_assoc()):
                                $can_assign = true;
                                if (($role['id'] <= 2) && $current_user_role['role_name'] !== 'Admin') {
                                    $can_assign = false;
                                }
                                if ($can_assign):
                            ?>
                                <option value="<?php echo e($role['id']); ?>" <?php echo ($role['id'] == $employee['role_id']) ? 'selected' : ''; ?>>
                                    <?php echo e($role['name']); ?>
                                </option>
                            <?php 
                                endif;
                            endwhile; 
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-gray-200">
                <div class="flex justify-between">
                    <a href="employees.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>