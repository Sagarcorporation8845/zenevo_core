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

        <!-- Example Dashboard Card 3 -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Revenue this Month</h3>
            <p class="text-3xl font-bold text-green-600 mt-2">$0.00</p>
            <p class="text-xs text-gray-500">Logic to be implemented</p>
        </div>

        <!-- Example Dashboard Card 4 -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700">Overdue Invoices</h3>
            <p class="text-3xl font-bold text-red-600 mt-2">0</p>
            <p class="text-xs text-gray-500">Logic to be implemented</p>
        </div>
    </div>

    <!-- More dashboard widgets can be added here -->

</div>

<?php
// Include the footer to close the HTML structure
include 'includes/footer.php';
?>