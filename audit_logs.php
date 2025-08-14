<?php
require_once 'config/db.php';
require_login();

// Check if user has permission to view audit logs
if (!has_permission($conn, 'view_audit_logs')) {
    $_SESSION['status_error'] = 'You do not have permission to view audit logs.';
    header('Location: ' . url_for('dashboard.php'));
    exit();
}

// Pagination and filtering
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(details LIKE ? OR resource LIKE ? OR ip_address LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if ($action_filter) {
    $where_conditions[] = "action = ?";
    $params[] = $action_filter;
    $param_types .= 's';
}

if ($user_filter) {
    $where_conditions[] = "u.name LIKE ?";
    $params[] = "%$user_filter%";
    $param_types .= 's';
}

if ($date_from) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if ($date_to) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM audit_logs al 
              LEFT JOIN users u ON al.user_id = u.id 
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total_records);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_records / $per_page);

// Get audit logs
$sql = "SELECT al.*, u.name as user_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        $where_clause 
        ORDER BY al.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$audit_logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique actions for filter
$actions_sql = "SELECT DISTINCT action FROM audit_logs ORDER BY action";
$actions_result = $conn->query($actions_sql);
$actions = [];
while ($row = $actions_result->fetch_assoc()) {
    $actions[] = $row['action'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Audit Logs - Zenevo</title>
    <link rel="stylesheet" href="<?php echo url_for('assets/css/app.css'); ?>">
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 p-8">
            <div class="max-w-7xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">Security Audit Logs</h1>
                    <p class="mt-2 text-gray-600">Monitor system security events and user activities</p>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" name="search" value="<?php echo e($search); ?>" 
                                   placeholder="Search logs..." class="w-full enhanced-input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                            <select name="action" class="w-full enhanced-input">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo e($action); ?>" 
                                            <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                        <?php echo e(ucwords(str_replace('_', ' ', $action))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                            <input type="text" name="user" value="<?php echo e($user_filter); ?>" 
                                   placeholder="Filter by user..." class="w-full enhanced-input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" name="date_from" value="<?php echo e($date_from); ?>" 
                                       class="w-full enhanced-input text-sm">
                                <input type="date" name="date_to" value="<?php echo e($date_to); ?>" 
                                       class="w-full enhanced-input text-sm">
                            </div>
                        </div>
                        <div class="md:col-span-2 lg:col-span-4 flex space-x-2">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Apply Filters
                            </button>
                            <a href="<?php echo url_for('audit_logs.php'); ?>" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Clear Filters
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-sm font-medium text-gray-500">Total Records</div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo number_format($total_records); ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-sm font-medium text-gray-500">Current Page</div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo $page; ?> of <?php echo $total_pages; ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-sm font-medium text-gray-500">Records per Page</div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo $per_page; ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-sm font-medium text-gray-500">Showing</div>
                        <div class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $per_page, $total_records)); ?>
                        </div>
                    </div>
                </div>

                <!-- Audit Logs Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Timestamp
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        User
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Action
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Details
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        IP Address
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($audit_logs)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                            No audit logs found matching your criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($audit_logs as $log): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo e($log['user_name'] ?? 'System'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                           <?php echo strpos($log['action'], 'denied') !== false ? 'bg-red-100 text-red-800' : 
                                                                 (strpos($log['action'], 'success') !== false ? 'bg-green-100 text-green-800' : 
                                                                  'bg-blue-100 text-blue-800'); ?>">
                                                    <?php echo e(ucwords(str_replace('_', ' ', $log['action']))); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate" title="<?php echo e($log['details']); ?>">
                                                <?php echo e($log['details']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo e($log['ip_address']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> 
                            of <?php echo number_format($total_records); ?> results
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium 
                                          <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'text-gray-700 bg-white hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>