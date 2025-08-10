<?php
/*
--------------------------------------------------------------------------------
-- File: /reports.php (NEW FILE)
-- Description: Reports and analytics page for HR and Finance managers.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Reports & Analytics';
include 'includes/header.php';

// Security Check
if (!has_permission($conn, 'view_reports')) {
    echo '<div class="p-6 bg-white rounded-lg shadow-md">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Get current date for default filters
$current_month = date('Y-m');
$current_year = date('Y');

// Get filter parameters
$filter_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
$filter_year = isset($_GET['year']) ? $_GET['year'] : $current_year;

// Employee Statistics
$employee_stats_sql = "SELECT 
    COUNT(*) as total_employees,
    SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active_employees,
    SUM(CASE WHEN u.is_active = 0 THEN 1 ELSE 0 END) as inactive_employees
    FROM employees e 
    JOIN users u ON e.user_id = u.id";
$employee_stats = $conn->query($employee_stats_sql)->fetch_assoc();

// Department Statistics
$dept_stats_sql = "SELECT 
    e.department,
    COUNT(*) as employee_count
    FROM employees e 
    JOIN users u ON e.user_id = u.id 
    WHERE u.is_active = 1
    GROUP BY e.department 
    ORDER BY employee_count DESC";
$dept_stats = $conn->query($dept_stats_sql);

// Leave Statistics for current month
$leave_stats_sql = "SELECT 
    COUNT(*) as total_leaves,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_leaves,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_leaves,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_leaves
    FROM leaves 
    WHERE DATE_FORMAT(start_date, '%Y-%m') = ?";
$stmt = $conn->prepare($leave_stats_sql);
$stmt->bind_param("s", $filter_month);
$stmt->execute();
$leave_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Role Distribution
$role_stats_sql = "SELECT 
    r.name as role_name,
    COUNT(*) as user_count
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.is_active = 1
    GROUP BY r.id, r.name 
    ORDER BY user_count DESC";
$role_stats = $conn->query($role_stats_sql);

// Recent Employees (last 30 days)
$recent_employees_sql = "SELECT 
    e.first_name, e.last_name, e.department, e.date_of_joining,
    r.name as role_name
    FROM employees e 
    JOIN users u ON e.user_id = u.id 
    JOIN roles r ON u.role_id = r.id
    WHERE e.date_of_joining >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY e.date_of_joining DESC
    LIMIT 10";
$recent_employees = $conn->query($recent_employees_sql);

// Security Audit Logs (last 50 entries for admins only)
$audit_logs = null;
if (check_role_access($conn, ['Admin'])) {
    $audit_logs_sql = "SELECT 
        al.action, al.details, al.resource, al.ip_address, al.created_at,
        COALESCE(u.name, 'System') as user_name
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 50";
    $audit_logs = $conn->query($audit_logs_sql);
}

?>

<div class="container mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-700">Reports & Analytics</h2>
        <div class="flex space-x-4">
            <input type="month" id="monthFilter" value="<?php echo e($filter_month); ?>" 
                   class="px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                   onchange="updateReports()">
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Total Employees -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Employees</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo e($employee_stats['total_employees']); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Employees -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Active Employees</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo e($employee_stats['active_employees']); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inactive Employees -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Inactive Employees</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo e($employee_stats['inactive_employees']); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Leaves -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Monthly Leaves</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo e($leave_stats['total_leaves']); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Tables Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Department Distribution -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Employees by Department</h3>
                <div class="space-y-3">
                    <?php while($dept = $dept_stats->fetch_assoc()): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-600"><?php echo e($dept['department']); ?></span>
                            <div class="flex items-center">
                                <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                                    <?php 
                                    $percentage = ($dept['employee_count'] / $employee_stats['active_employees']) * 100;
                                    ?>
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo e($percentage); ?>%"></div>
                                </div>
                                <span class="text-sm text-gray-900"><?php echo e($dept['employee_count']); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Role Distribution -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Users by Role</h3>
                <div class="space-y-3">
                    <?php while($role = $role_stats->fetch_assoc()): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-600"><?php echo e($role['role_name']); ?></span>
                            <div class="flex items-center">
                                <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                                    <?php 
                                    $percentage = ($role['user_count'] / $employee_stats['active_employees']) * 100;
                                    ?>
                                    <div class="bg-indigo-600 h-2 rounded-full" style="width: <?php echo e($percentage); ?>%"></div>
                                </div>
                                <span class="text-sm text-gray-900"><?php echo e($role['user_count']); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Status Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Leave Status (<?php echo date('F Y', strtotime($filter_month . '-01')); ?>)</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Pending Leaves</span>
                        <span class="px-3 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                            <?php echo e($leave_stats['pending_leaves']); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Approved Leaves</span>
                        <span class="px-3 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                            <?php echo e($leave_stats['approved_leaves']); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Rejected Leaves</span>
                        <span class="px-3 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                            <?php echo e($leave_stats['rejected_leaves']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Hires -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Hires (Last 30 Days)</h3>
                <div class="space-y-3">
                    <?php if ($recent_employees && $recent_employees->num_rows > 0): ?>
                        <?php while($employee = $recent_employees->fetch_assoc()): ?>
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo e($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500"><?php echo e($employee['department']); ?> • <?php echo e($employee['role_name']); ?></p>
                                </div>
                                <span class="text-xs text-gray-500">
                                    <?php echo date('M d', strtotime($employee['date_of_joining'])); ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">No new hires in the last 30 days.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Audit Logs (Admin Only) -->
    <?php if ($audit_logs): ?>
    <div class="bg-white shadow rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Security Audit Logs (Last 50 Entries)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($log = $audit_logs->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo e($log['user_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                    <?php 
                                    if (strpos($log['action'], 'denied') !== false || strpos($log['action'], 'failed') !== false) {
                                        echo 'bg-red-100 text-red-800';
                                    } elseif (strpos($log['action'], 'created') !== false || strpos($log['action'], 'activated') !== false) {
                                        echo 'bg-green-100 text-green-800';
                                    } else {
                                        echo 'bg-blue-100 text-blue-800';
                                    }
                                    ?>">
                                    <?php echo e($log['action']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate">
                                <?php echo e($log['details']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo e($log['ip_address']); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Export Options -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Export Reports</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-300">
                    Export Employee List
                </button>
                <button class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition duration-300">
                    Export Leave Reports
                </button>
                <button class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-md transition duration-300">
                    Export Department Summary
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function updateReports() {
    const month = document.getElementById('monthFilter').value;
    if (month) {
        window.location.href = '<?php echo url_for('reports.php'); ?>?month=' + month;
    }
}
</script>

<?php include 'includes/footer.php'; ?>