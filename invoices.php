<?php
/*
--------------------------------------------------------------------------------
-- File: /invoices.php (UPDATED)
-- Description: Main page for the finance/invoicing module.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Finance & Invoices';
include 'includes/header.php';

// Security Check
if (!has_permission($conn, 'manage_invoices')) {
    echo '<div class="p-6 bg-white rounded-lg shadow-md">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Handle flash messages for success/error notifications
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Fetch all invoices with client and project details
$sql = "SELECT 
            i.id, i.invoice_number, i.issue_date, i.due_date, i.total_amount, i.status,
            p.name as project_name,
            c.name as client_name
        FROM invoices i
        JOIN projects p ON i.project_id = p.id
        JOIN clients c ON p.client_id = c.id
        ORDER BY i.issue_date DESC";
$result = $conn->query($sql);

?>

<div class="container mx-auto">
    <!-- Header with Action Button -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-700">All Invoices</h2>
        <a href="create_invoice.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300">
            + Create New Invoice
        </a>
    </div>

    <!-- Flash Message Display -->
    <?php if ($flash_message): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo e($flash_message['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Invoices Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Invoice #</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Client / Project</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Issue Date</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Due Date</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap font-semibold"><?php echo e($row['invoice_number']); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo e($row['client_name']); ?></p>
                                    <p class="text-gray-600 whitespace-no-wrap text-xs"><?php echo e($row['project_name']); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap font-bold">₹<?php echo number_format((float)$row['total_amount'], 2); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo date('M d, Y', strtotime(e($row['issue_date']))); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo date('M d, Y', strtotime(e($row['due_date']))); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <?php
                                        $status = e($row['status']);
                                        $status_color = 'bg-gray-200 text-gray-800'; // Default
                                        if ($status == 'Paid') $status_color = 'bg-green-200 text-green-800';
                                        if ($status == 'Sent') $status_color = 'bg-blue-200 text-blue-800';
                                        if ($status == 'Overdue') $status_color = 'bg-red-200 text-red-800';
                                        if ($status == 'Draft') $status_color = 'bg-yellow-200 text-yellow-800';
                                    ?>
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight rounded-full <?php echo $status_color; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900">View</a>
                                    <!-- Add Edit/Delete links later -->
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-10 text-gray-500">
                                No invoices found. <a href="create_invoice.php" class="text-indigo-600 hover:underline">Create one now</a>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>