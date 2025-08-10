<?php
$pageTitle = 'Mailbox & Templates';
include 'includes/header.php';

if (!check_role_access($conn, ['Admin','HR Manager','Finance Manager'])) {
    echo '<div class="p-6 bg-white rounded-lg shadow-md">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Ensure schema exists (lightweight)
$conn->query("CREATE TABLE IF NOT EXISTS mail_templates (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, subject VARCHAR(255) NOT NULL, from_alias VARCHAR(100) NOT NULL DEFAULT 'support', html MEDIUMTEXT NOT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS mail_jobs (id INT AUTO_INCREMENT PRIMARY KEY, template_id INT NOT NULL, uploaded_filename VARCHAR(255), total_recipients INT NOT NULL DEFAULT 0, sent_count INT NOT NULL DEFAULT 0, status ENUM('Queued','Processing','Completed','Failed') DEFAULT 'Queued', created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS mail_job_recipients (id INT AUTO_INCREMENT PRIMARY KEY, job_id INT NOT NULL, name VARCHAR(150), email VARCHAR(180) NOT NULL, status ENUM('Pending','Sent','Failed') DEFAULT 'Pending', error VARCHAR(500) NULL)");

$templates = $conn->query("SELECT * FROM mail_templates ORDER BY created_at DESC");

?>
<div class="container mx-auto">
  <div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-700">Mailbox & Email Templates</h2>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Create Template -->
    <div class="bg-white p-6 rounded-lg shadow">
      <h3 class="text-lg font-semibold mb-4">Create / Save HTML Template</h3>
      <form action="actions/mailbox_action.php" method="POST">
        <input type="hidden" name="action" value="save_template" />
        <div class="mb-3">
          <label class="block text-sm font-medium mb-1">Template Name</label>
          <input class="enhanced-input w-full" name="name" required />
        </div>
        <div class="mb-3">
          <label class="block text-sm font-medium mb-1">Subject</label>
          <input class="enhanced-input w-full" name="subject" required />
        </div>
        <div class="mb-3">
          <label class="block text-sm font-medium mb-1">From Alias</label>
          <select name="from_alias" class="enhanced-input w-full">
            <option value="support">support@zenevo.in</option>
            <option value="info">info@zenevo.in</option>
            <option value="careers">careers@zenevo.in</option>
            <option value="billing">billing@zenevo.in</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium mb-1">HTML</label>
          <textarea name="html" rows="10" class="enhanced-input w-full" placeholder="Paste HTML with [Candidate Name] placeholder" required></textarea>
        </div>
        <div class="text-right">
          <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Save Template</button>
        </div>
      </form>
    </div>

    <!-- Bulk Send -->
    <div class="bg-white p-6 rounded-lg shadow">
      <h3 class="text-lg font-semibold mb-4">Send Emails from CSV</h3>
      <form action="actions/mailbox_action.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="send_bulk" />
        <div class="mb-3">
          <label class="block text-sm font-medium mb-1">Select Template</label>
          <select name="template_id" class="enhanced-input w-full" required>
            <option value="">-- choose --</option>
            <?php while($t = $templates->fetch_assoc()): ?>
              <option value="<?php echo e($t['id']); ?>"><?php echo e($t['name']); ?> (<?php echo e($t['from_alias']); ?>)</option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="block text-sm font-medium mb-1">Upload CSV (Name,Email)</label>
          <input type="file" name="csv" accept=".csv" class="block w-full" required />
        </div>
        <p class="text-xs text-gray-500 mb-4">Tip: Export from Excel as CSV UTF-8. Columns required: Name, Email.</p>
        <div class="text-right">
          <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Send Emails</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Existing Templates -->
  <div class="bg-white p-6 rounded-lg shadow mt-8">
    <h3 class="text-lg font-semibold mb-4">Saved Templates</h3>
    <div class="overflow-x-auto">
      <table class="min-w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Subject</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">From</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Created</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $templates2 = $conn->query("SELECT * FROM mail_templates ORDER BY created_at DESC");
          while($t = $templates2->fetch_assoc()): ?>
          <tr class="border-b">
            <td class="px-4 py-2 text-sm"><?php echo e($t['name']); ?></td>
            <td class="px-4 py-2 text-sm"><?php echo e($t['subject']); ?></td>
            <td class="px-4 py-2 text-sm"><?php echo e($t['from_alias']); ?></td>
            <td class="px-4 py-2 text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($t['created_at'])); ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>