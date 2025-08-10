<?php
$pageTitle = 'Finance Settings';
include 'includes/header.php';
if (!has_permission($conn, 'manage_invoices')) {
    echo '<div class="p-6 bg-white rounded shadow">No permission</div>';
    include 'includes/footer.php';
    exit;
}
$fs = $conn->query('SELECT * FROM finance_settings WHERE id=1')->fetch_assoc();
?>
<div class="container mx-auto">
  <h2 class="text-2xl font-semibold text-gray-700 mb-6">Finance Settings</h2>
  <form class="bg-white p-6 rounded shadow" action="actions/finance_action.php" method="POST">
    <input type="hidden" name="action" value="save_settings" />
    <div class="mb-3">
      <label class="block text-sm font-medium mb-1">Reminder days before due date (comma separated)</label>
      <input name="reminder_days_before" value="<?php echo e($fs['reminder_days_before']); ?>" class="enhanced-input w-full" />
    </div>
    <div class="mb-3">
      <label class="block text-sm font-medium mb-1">From Alias</label>
      <select name="from_alias" class="enhanced-input">
        <option value="billing" <?php echo $fs['from_alias']==='billing'?'selected':''; ?>>billing@zenevo.in</option>
        <option value="support" <?php echo $fs['from_alias']==='support'?'selected':''; ?>>support@zenevo.in</option>
      </select>
    </div>
    <div class="flex justify-between items-center">
      <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Save</button>
      <button name="action" value="send_due_reminders_now" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Send Reminders Now</button>
    </div>
  </form>
</div>
<?php include 'includes/footer.php'; ?>