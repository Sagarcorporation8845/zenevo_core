<?php
/*
--------------------------------------------------------------------------------
-- File: /dashboard.php
-- Description: The main landing page after a user logs in.
--------------------------------------------------------------------------------
*/

// Set the page title for the header
$pageTitle = 'Dashboard';

// Include the header, which handles session and login checks
include 'includes/header.php';

// The $conn variable is now available from header.php
?>

<!-- The main content for the dashboard -->
<div class="container mx-auto">

    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Welcome to your Dashboard</h2>

    <?php
    // Finance widgets visibility: Admins or users with manage_invoices permission
    $can_view_finance = has_permission($conn, 'manage_invoices') || check_role_access($conn, ['Admin','Finance']);
    $revenue_this_month = 0.0;
    $overdue_count = 0;
    if ($can_view_finance) {
        $startOfMonth = date('Y-m-01');
        $endOfMonth = date('Y-m-t');
        // Revenue: sum of PAID invoices in current month (Zoho-like logic approximation)
        if ($stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE status IN ('Paid') AND issue_date BETWEEN ? AND ?")) {
            $stmt->bind_param('ss', $startOfMonth, $endOfMonth);
            $stmt->execute();
            $stmt->bind_result($revenue_this_month);
            $stmt->fetch();
            $stmt->close();
        }
        // Overdue: invoices past due and not paid/cancelled/void
        if ($stmt = $conn->prepare("SELECT COUNT(*) FROM invoices WHERE due_date < CURDATE() AND status NOT IN ('Paid','Cancelled','Void')")) {
            $stmt->execute();
            $stmt->bind_result($overdue_count);
            $stmt->fetch();
            $stmt->close();
        }
    }
    ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Tasks Completed This Month -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Tasks Completed</h3>
            <?php
                $currentMonth = date('Y-m');
                $currentUserId = $_SESSION['user_id'];
                $currentEmployeeId = null;
                
                // Get current employee ID
                if ($st = $conn->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1')) {
                    $st->bind_param('i', $currentUserId);
                    $st->execute();
                    $st->bind_result($currentEmployeeId);
                    $st->fetch();
                    $st->close();
                }
                
                $completedTasks = 0;
                if ($currentEmployeeId && $stmt = $conn->prepare("SELECT COUNT(*) FROM tasks WHERE assignee_employee_id = ? AND status = 'Done' AND DATE_FORMAT(created_at, '%Y-%m') = ?")) {
                    $stmt->bind_param('is', $currentEmployeeId, $currentMonth);
                    $stmt->execute();
                    $stmt->bind_result($completedTasks);
                    $stmt->fetch();
                    $stmt->close();
                }
            ?>
            <p class="text-3xl font-bold text-green-600 mt-2"><?php echo e($completedTasks); ?></p>
            <p class="text-xs text-gray-500">This month</p>
        </div>

        <!-- Pending Tasks -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Pending Tasks</h3>
            <?php
                $pendingTasks = 0;
                if ($currentEmployeeId && $stmt = $conn->prepare("SELECT COUNT(*) FROM tasks WHERE assignee_employee_id = ? AND status IN ('Todo', 'In Progress', 'Blocked')")) {
                    $stmt->bind_param('i', $currentEmployeeId);
                    $stmt->execute();
                    $stmt->bind_result($pendingTasks);
                    $stmt->fetch();
                    $stmt->close();
                }
            ?>
            <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo e($pendingTasks); ?></p>
            <p class="text-xs text-gray-500">Active tasks</p>
        </div>

        <!-- Present Days This Month -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Present Days</h3>
            <?php
                $presentDays = 0;
                if ($currentEmployeeId && $stmt = $conn->prepare("SELECT COUNT(DISTINCT date) FROM attendance WHERE employee_id = ? AND clock_in_time IS NOT NULL AND DATE_FORMAT(date, '%Y-%m') = ?")) {
                    $stmt->bind_param('is', $currentEmployeeId, $currentMonth);
                    $stmt->execute();
                    $stmt->bind_result($presentDays);
                    $stmt->fetch();
                    $stmt->close();
                }
            ?>
            <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo e($presentDays); ?></p>
            <p class="text-xs text-gray-500">This month</p>
        </div>

        <!-- Notifications -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Notifications</h3>
            <?php
                $unreadNotifications = 0;
                if ($stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0")) {
                    $stmt->bind_param('i', $currentUserId);
                    $stmt->execute();
                    $stmt->bind_result($unreadNotifications);
                    $stmt->fetch();
                    $stmt->close();
                }
            ?>
            <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo e($unreadNotifications); ?></p>
            <p class="text-xs text-gray-500">Unread messages</p>
        </div>

        <?php if ($can_view_finance): ?>
        <!-- Revenue this Month (Finance/Admin only) -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Revenue this Month</h3>
            <p class="text-3xl font-bold text-green-600 mt-2">₹<?php echo number_format((float)$revenue_this_month, 2); ?></p>
            <p class="text-xs text-gray-500">Sum of paid invoices this month</p>
        </div>

        <!-- Overdue Invoices (Finance/Admin only) -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Overdue Invoices</h3>
            <p class="text-3xl font-bold text-red-600 mt-2"><?php echo (int)$overdue_count; ?></p>
            <p class="text-xs text-gray-500">Due date passed and unpaid</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- More dashboard widgets can be added here -->

</div>

<?php
// Include the footer to close the HTML structure
include 'includes/footer.php';
?>