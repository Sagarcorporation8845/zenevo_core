<?php
$pageTitle = 'Productivity Dashboard';
include 'includes/header.php';

// Security Check - Managers and above can view team productivity
if (!check_role_access($conn, ['Admin', 'HR Manager', 'Manager', 'Team Lead'])) {
    echo '<div class="p-6 bg-white rounded-lg shadow-md">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

$currentUserId = $_SESSION['user_id'];
$isManager = check_role_access($conn, ['Manager']);
$currentEmployeeId = null;

// Get current employee ID
if ($st = $conn->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1')) {
    $st->bind_param('i', $currentUserId);
    $st->execute();
    $st->bind_result($currentEmployeeId);
    $st->fetch();
    $st->close();
}

// Get team members if user is a manager
$teamMembers = [];
if ($isManager && $currentEmployeeId) {
    $teamQuery = $conn->prepare("SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) as name, e.designation, e.department
                                FROM employee_managers em
                                JOIN employees e ON em.employee_id = e.id
                                JOIN users u ON e.user_id = u.id
                                WHERE em.manager_id = ? AND em.is_active = 1 AND u.is_active = 1
                                ORDER BY e.first_name, e.last_name");
    $teamQuery->bind_param('i', $currentEmployeeId);
    $teamQuery->execute();
    $teamResult = $teamQuery->get_result();
    while ($member = $teamResult->fetch_assoc()) {
        $teamMembers[] = $member;
    }
    $teamQuery->close();
}

// Get productivity metrics
$currentMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));

// Overall statistics
$totalTasks = 0;
$completedTasks = 0;
$pendingTasks = 0;
$overdueTasks = 0;

if ($isManager && !empty($teamMembers)) {
    // Manager sees team statistics
    $teamIds = array_column($teamMembers, 'id');
    $placeholders = str_repeat('?,', count($teamIds) - 1) . '?';
    
    $stmt = $conn->prepare("SELECT 
                               COUNT(*) as total,
                               SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as completed,
                               SUM(CASE WHEN status IN ('Todo', 'In Progress', 'Blocked') THEN 1 ELSE 0 END) as pending,
                               SUM(CASE WHEN deadline < CURDATE() AND status != 'Done' THEN 1 ELSE 0 END) as overdue
                            FROM tasks 
                            WHERE assignee_employee_id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($teamIds)), ...$teamIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    $totalTasks = $stats['total'];
    $completedTasks = $stats['completed'];
    $pendingTasks = $stats['pending'];
    $overdueTasks = $stats['overdue'];
} else {
    // Admin/HR sees all statistics
    $result = $conn->query("SELECT 
                               COUNT(*) as total,
                               SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as completed,
                               SUM(CASE WHEN status IN ('Todo', 'In Progress', 'Blocked') THEN 1 ELSE 0 END) as pending,
                               SUM(CASE WHEN deadline < CURDATE() AND status != 'Done' THEN 1 ELSE 0 END) as overdue
                            FROM tasks");
    if ($result) {
        $stats = $result->fetch_assoc();
        $totalTasks = $stats['total'];
        $completedTasks = $stats['completed'];
        $pendingTasks = $stats['pending'];
        $overdueTasks = $stats['overdue'];
    }
}

// Individual employee performance (for managers)
$employeeStats = [];
if ($isManager && !empty($teamMembers)) {
    foreach ($teamMembers as $member) {
        $empId = $member['id'];
        $stmt = $conn->prepare("SELECT 
                                   COUNT(*) as total,
                                   SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as completed,
                                   SUM(CASE WHEN status IN ('Todo', 'In Progress', 'Blocked') THEN 1 ELSE 0 END) as pending
                                FROM tasks 
                                WHERE assignee_employee_id = ?");
        $stmt->bind_param('i', $empId);
        $stmt->execute();
        $result = $stmt->get_result();
        $empStats = $result->fetch_assoc();
        $stmt->close();
        
        $member['stats'] = $empStats;
        $member['completion_rate'] = $empStats['total'] > 0 ? round(($empStats['completed'] / $empStats['total']) * 100, 1) : 0;
        $employeeStats[] = $member;
    }
}

// Monthly trend data
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    
    if ($isManager && !empty($teamMembers)) {
        $placeholders = str_repeat('?,', count($teamIds) - 1) . '?';
        $stmt = $conn->prepare("SELECT COUNT(*) as completed 
                               FROM tasks 
                               WHERE assignee_employee_id IN ($placeholders) 
                               AND status = 'Done' 
                               AND DATE_FORMAT(created_at, '%Y-%m') = ?");
        $params = array_merge($teamIds, [$month]);
        $types = str_repeat('i', count($teamIds)) . 's';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $completed = $result->fetch_assoc()['completed'];
        $stmt->close();
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as completed 
                               FROM tasks 
                               WHERE status = 'Done' 
                               AND DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->bind_param('s', $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $completed = $result->fetch_assoc()['completed'];
        $stmt->close();
    }
    
    $monthlyData[] = [
        'month' => $monthName,
        'completed' => $completed
    ];
}
?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-semibold text-gray-700">Productivity Dashboard</h2>
            <p class="text-gray-600 mt-1">
                <?php 
                if ($isManager) {
                    echo "Team performance metrics and analytics";
                } else {
                    echo "Organization-wide productivity insights";
                }
                ?>
            </p>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-gray-500">Total Tasks</h3>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalTasks; ?></p>
                </div>
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-gray-500">Completed</h3>
                    <p class="text-2xl font-bold text-green-600"><?php echo $completedTasks; ?></p>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php echo $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0; ?>% completion rate
                    </p>
                </div>
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-gray-500">In Progress</h3>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $pendingTasks; ?></p>
                    <p class="text-xs text-gray-500 mt-1">Active tasks</p>
                </div>
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-gray-500">Overdue</h3>
                    <p class="text-2xl font-bold text-red-600"><?php echo $overdueTasks; ?></p>
                    <p class="text-xs text-gray-500 mt-1">Past deadline</p>
                </div>
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Monthly Trend Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Task Completion Trend</h3>
            <div class="h-64 flex items-end justify-between space-x-2">
                <?php 
                $maxCompleted = max(array_column($monthlyData, 'completed'));
                $maxCompleted = $maxCompleted > 0 ? $maxCompleted : 1;
                foreach ($monthlyData as $data): 
                    $height = $maxCompleted > 0 ? ($data['completed'] / $maxCompleted) * 100 : 0;
                ?>
                <div class="flex-1 flex flex-col items-center">
                    <div class="bg-blue-500 rounded-t w-full transition-all duration-500 hover:bg-blue-600" 
                         style="height: <?php echo $height; ?>%" 
                         title="<?php echo $data['completed']; ?> tasks completed"></div>
                    <div class="text-xs text-gray-600 mt-2 transform -rotate-45 origin-left">
                        <?php echo $data['month']; ?>
                    </div>
                    <div class="text-xs font-semibold text-gray-900 mt-1">
                        <?php echo $data['completed']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Team Performance (for managers) -->
        <?php if ($isManager && !empty($employeeStats)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Team Performance</h3>
            <div class="space-y-4">
                <?php foreach ($employeeStats as $employee): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0 w-10 h-10">
                            <div class="w-full h-full rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold">
                                <?php echo e(strtoupper(substr($employee['name'], 0, 2))); ?>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?php echo e($employee['name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo e($employee['designation']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold text-gray-900">
                            <?php echo $employee['stats']['completed']; ?>/<?php echo $employee['stats']['total']; ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo $employee['completion_rate']; ?>% complete
                        </div>
                        <div class="w-20 bg-gray-200 rounded-full h-2 mt-1">
                            <div class="bg-green-600 h-2 rounded-full transition-all duration-300" 
                                 style="width: <?php echo $employee['completion_rate']; ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- Department Breakdown (for admins) -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Task Status Distribution</h3>
            <div class="space-y-4">
                <?php
                $statuses = ['Todo', 'In Progress', 'Blocked', 'Done'];
                $statusColors = [
                    'Todo' => 'bg-gray-500',
                    'In Progress' => 'bg-yellow-500', 
                    'Blocked' => 'bg-red-500',
                    'Done' => 'bg-green-500'
                ];
                
                foreach ($statuses as $status):
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE status = ?");
                    $stmt->bind_param('s', $status);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['count'];
                    $stmt->close();
                    
                    $percentage = $totalTasks > 0 ? ($count / $totalTasks) * 100 : 0;
                ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-4 h-4 rounded-full <?php echo $statusColors[$status]; ?>"></div>
                        <span class="text-sm font-medium text-gray-700"><?php echo $status; ?></span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-32 bg-gray-200 rounded-full h-2">
                            <div class="<?php echo $statusColors[$status]; ?> h-2 rounded-full transition-all duration-300" 
                                 style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 w-8"><?php echo $count; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.chart-bar {
    transition: all 0.3s ease;
}
.chart-bar:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<?php include 'includes/footer.php'; ?>