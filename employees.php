<?php
/*
--------------------------------------------------------------------------------
-- File: /employees.php (UPDATED)
-- Description: Main page for the employee management module with status toggle.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Employee Management';
include 'includes/header.php';

// Security Check
if (!has_permission($conn, 'manage_employees')) {
    echo '<div class="p-6 bg-white rounded-lg shadow-md">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Handle flash messages
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Fetch all employees with their user status, user ID, and role information
$sql = "SELECT 
            e.id, e.first_name, e.last_name, e.designation, e.department, e.date_of_joining, 
            u.id as user_id, u.email, u.is_active, r.name as role_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        ORDER BY e.first_name, e.last_name";
$result = $conn->query($sql);

?>

<div class="container mx-auto">
    <!-- Header with Action Button -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-700">All Employees</h2>
        <a href="add_employee.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
            <svg xmlns="[http://www.w3.org/2000/svg](http://www.w3.org/2000/svg)" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Add New Employee
        </a>
    </div>

    <!-- Flash Message Display -->
    <?php if ($flash_message): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
            <?php echo e($flash_message['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Employees Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Designation</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Role</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date Joined</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 w-10 h-10">
                                            <!-- Placeholder for profile image -->
                                            <div class="w-full h-full rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold">
                                                <?php echo e(strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1))); ?>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-gray-900 whitespace-no-wrap font-semibold"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></p>
                                            <p class="text-gray-600 whitespace-no-wrap text-xs"><?php echo e($row['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo e($row['designation']); ?></p>
                                    <p class="text-gray-600 whitespace-no-wrap text-xs"><?php echo e($row['department']); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        <?php 
                                        switch($row['role_name']) {
                                            case 'Admin': echo 'bg-red-100 text-red-800'; break;
                                            case 'HR Manager': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'Finance Manager': echo 'bg-green-100 text-green-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800'; break;
                                        }
                                        ?>">
                                        <?php echo e($row['role_name']); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo date('M d, Y', strtotime(e($row['date_of_joining']))); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <?php if ($row['is_active']): ?>
                                        <span class="relative inline-block px-3 py-1 font-semibold leading-tight text-green-900 bg-green-200 rounded-full">Active</span>
                                    <?php else: ?>
                                        <span class="relative inline-block px-3 py-1 font-semibold leading-tight text-red-900 bg-red-200 rounded-full">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center">
                                    <div class="flex item-center justify-center space-x-2">
                                        <a href="edit_employee.php?id=<?php echo e($row['id']); ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        <form action="actions/employee_action.php" method="POST" onsubmit="return confirm('Are you sure you want to change this employee\'s status?');">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo e($row['user_id']); ?>">
                                            <input type="hidden" name="current_status" value="<?php echo e($row['is_active']); ?>">
                                            <?php if ($row['is_active']): ?>
                                                <button type="submit" class="text-red-600 hover:text-red-900">Deactivate</button>
                                            <?php else: ?>
                                                <button type="submit" class="text-green-600 hover:text-green-900">Activate</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-gray-500">
                                No employees found. <a href="add_employee.php" class="text-indigo-600 hover:underline">Add one now</a>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>