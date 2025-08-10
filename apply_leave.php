<?php
/*
--------------------------------------------------------------------------------
-- File: /apply_leave.php (NEW FILE)
-- Description: Form for employees to apply for leave.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Apply for Leave';
include 'includes/header.php';

// Handle flash messages
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
?>

<div class="container mx-auto max-w-2xl">
    <form action="actions/leave_action.php" method="POST">
        <input type="hidden" name="action" value="apply_for_leave">
        <div class="bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-2 text-gray-800">New Leave Request</h2>
            <p class="text-sm text-gray-600 mb-6">Submit your request for time off.</p>

            <!-- Flash Message Display -->
            <?php if ($flash_message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo e($flash_message['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Leave Dates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                    <input type="date" name="start_date" id="start_date" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                    <input type="date" name="end_date" id="end_date" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
            </div>

            <!-- Reason for Leave -->
            <div class="mb-6">
                <label for="reason" class="block text-sm font-medium text-gray-700">Reason for Leave</label>
                <textarea id="reason" name="reason" rows="4" required class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border border-gray-300 rounded-md"></textarea>
            </div>


            <!-- Form Actions -->
            <div class="pt-5 border-t border-gray-200">
                <div class="flex justify-end">
                    <a href="leaves.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Submit Request
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>