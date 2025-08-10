<?php
/*
--------------------------------------------------------------------------------
-- File: /leaves.php (NEW FILE - Replaces placeholder)
-- Description: Main page for the leave management module.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Leave Management';
include 'includes/header.php';

// Handle flash messages
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

$is_manager = has_permission($conn, 'manage_leaves');

// Fetch leave requests
if ($is_manager) {
    // Managers see all leave requests
    $sql = "SELECT l.id, l.start_date, l.end_date, l.reason, l.status, e.first_name, e.last_name
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($sql);
} else {
    // Employees see only their own leave requests
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT l.id, l.start_date, l.end_date, l.reason, l.status, e.first_name, e.last_name
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            WHERE e.user_id = ?
            ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

?>

<div class="container mx-auto">
    <!-- Header with Action Button -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-700">Leave Requests</h2>
        <?php if (!$is_manager): // Only employees see the "Apply for Leave" button here ?>
            <a href="apply_leave.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
                + Apply for Leave
            </a>
        <?php endif; ?>
    </div>

    <!-- Flash Message Display -->
    <?php if ($flash_message): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
            <?php echo e($flash_message['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Leave Requests Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if ($is_manager): ?>
                            <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Employee</th>
                        <?php endif; ?>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Dates</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Reason</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <?php if ($is_manager): ?>
                            <th class="px-5 py-3 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <?php if ($is_manager): ?>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap font-semibold"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></p>
                                    </td>
                                <?php endif; ?>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo date('M d, Y', strtotime(e($row['start_date']))) . ' - ' . date('M d, Y', strtotime(e($row['end_date']))); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-600 whitespace-no-wrap"><?php echo e($row['reason']); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <?php
                                        $status = e($row['status']);
                                        $status_color = 'bg-yellow-200 text-yellow-800'; // Pending
                                        if ($status == 'Approved') $status_color = 'bg-green-200 text-green-800';
                                        if ($status == 'Rejected') $status_color = 'bg-red-200 text-red-800';
                                    ?>
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight rounded-full <?php echo $status_color; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <?php if ($is_manager && $row['status'] == 'Pending'): ?>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center">
                                        <div class="flex item-center justify-center space-x-2">
                                            <form action="actions/leave_action.php" method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="update_leave_status">
                                                <input type="hidden" name="leave_id" value="<?php echo e($row['id']); ?>">
                                                <input type="hidden" name="status" value="Approved">
                                                <button type="submit" class="text-green-600 hover:text-green-900 font-semibold">Approve</button>
                                            </form>
                                            <form action="actions/leave_action.php" method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="update_leave_status">
                                                <input type="hidden" name="leave_id" value="<?php echo e($row['id']); ?>">
                                                <input type="hidden" name="status" value="Rejected">
                                                <button type="submit" class="text-red-600 hover:text-red-900 font-semibold">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                <?php elseif ($is_manager): ?>
                                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center text-gray-500">
                                        Actioned
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $is_manager ? '5' : '3'; ?>" class="text-center py-10 text-gray-500">
                                No leave requests found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>