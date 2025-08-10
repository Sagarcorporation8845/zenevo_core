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
        <!-- Example Dashboard Card 1 -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Total Employees</h3>
            <?php
                // Example of fetching data for the dashboard
                $result = $conn->query("SELECT COUNT(*) as count FROM employees");
                $count = $result->fetch_assoc()['count'];
            ?>
            <p class="text-3xl font-bold text-indigo-600 mt-2"><?php echo e($count); ?></p>
        </div>

        <!-- Example Dashboard Card 2 -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Pending Leave Requests</h3>
             <?php
                $result = $conn->query("SELECT COUNT(*) as count FROM leaves WHERE status = 'Pending'");
                $count = $result->fetch_assoc()['count'];
            ?>
            <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo e($count); ?></p>
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