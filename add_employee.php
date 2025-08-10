<?php
/*
--------------------------------------------------------------------------------
-- File: /add_employee.php (NEW FILE)
-- Description: Form to add a new employee.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Add New Employee';
include 'includes/header.php';

// Security Check
if (!has_permission($conn, 'manage_employees')) {
    echo '<div class="p-6">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Handle flash messages
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
?>

<div class="container mx-auto max-w-4xl">
    <form action="actions/employee_action.php" method="POST">
        <input type="hidden" name="action" value="create_employee">
        <div class="bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-2 text-gray-800">New Employee Information</h2>
            <p class="text-sm text-gray-600 mb-6">Fill out the form to onboard a new team member.</p>

            <!-- Flash Message Display -->
            <?php if ($flash_message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo e($flash_message['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Personal Information Section -->
            <div class="border-b border-gray-200 pb-6 mb-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Personal Details</h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="first_name" id="first_name" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="last_name" id="last_name" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>
            </div>

            <!-- Job Information Section -->
            <div class="border-b border-gray-200 pb-6 mb-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Job Details</h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="designation" class="block text-sm font-medium text-gray-700">Designation</label>
                        <input type="text" name="designation" id="designation" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                        <input type="text" name="department" id="department" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label for="date_of_joining" class="block text-sm font-medium text-gray-700">Date of Joining</label>
                        <input type="date" name="date_of_joining" id="date_of_joining" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>
            </div>

            <!-- Account Information Section -->
            <div>
                <h3 class="text-lg font-medium leading-6 text-gray-900">Account Credentials</h3>
                <p class="text-sm text-gray-500">The employee will use these to log in.</p>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" name="email" id="email" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" id="password" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label for="role_id" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role_id" id="role_id" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <?php
                            // Fetch roles from database with access control
                            $current_user_role = get_user_role($conn, $_SESSION['user_id']);
                            $roles_sql = "SELECT id, name FROM roles ORDER BY name";
                            $roles_result = $conn->query($roles_sql);
                            while($role = $roles_result->fetch_assoc()):
                                // Only Admin can assign Admin/HR Manager roles
                                $can_assign = true;
                                if (($role['id'] <= 2) && $current_user_role['role_name'] !== 'Admin') {
                                    $can_assign = false;
                                }
                                if ($can_assign):
                            ?>
                                <option value="<?php echo e($role['id']); ?>" <?php echo ($role['id'] == 4) ? 'selected' : ''; ?>>
                                    <?php echo e($role['name']); ?>
                                </option>
                            <?php 
                                endif;
                            endwhile; 
                            ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Default role is Employee. Only Admin can assign Admin/HR Manager roles.</p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 pt-5 border-t border-gray-200">
                <div class="flex justify-end">
                    <a href="employees.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Add Employee
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>