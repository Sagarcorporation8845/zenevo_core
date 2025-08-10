<?php
$pageTitle = 'DevOps / Collaboration';
include 'includes/header.php';

// Ensure schema
$conn->query("CREATE TABLE IF NOT EXISTS sprints (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS tasks (id INT AUTO_INCREMENT PRIMARY KEY, sprint_id INT NULL, title VARCHAR(200) NOT NULL, description TEXT NULL, status ENUM('Todo','In Progress','Blocked','Done') DEFAULT 'Todo', assignee_employee_id INT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

if (!check_role_access($conn, ['Admin','Team Lead','HR Manager'])) {
    echo '<div class="p-6 bg-white rounded-lg shadow">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit;
}

// Fetch data
$sprints = $conn->query('SELECT * FROM sprints ORDER BY start_date DESC');
$tasks = $conn->query('SELECT t.*, CONCAT(e.first_name, " ", e.last_name) AS assignee
                       FROM tasks t LEFT JOIN employees e ON t.assignee_employee_id=e.id ORDER BY t.created_at DESC LIMIT 100');
?>
<div class="container mx-auto">
  <div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-700">DevOps / Collaboration</h2>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Create Sprint -->
    <div class="bg-white p-6 rounded shadow">
      <h3 class="text-lg font-semibold mb-4">Create Sprint</h3>
      <form action="actions/devops_action.php" method="POST" class="space-y-3">
        <input type="hidden" name="action" value="create_sprint" />
        <input class="enhanced-input w-full" name="name" placeholder="Sprint name" required />
        <div class="grid grid-cols-2 gap-3">
          <input type="date" class="enhanced-input" name="start_date" required />
          <input type="date" class="enhanced-input" name="end_date" required />
        </div>
        <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Save</button>
      </form>
    </div>

    <!-- Create Task -->
    <div class="bg-white p-6 rounded shadow">
      <h3 class="text-lg font-semibold mb-4">Create Task</h3>
      <form action="actions/devops_action.php" method="POST" class="space-y-3">
        <input type="hidden" name="action" value="create_task" />
        <input class="enhanced-input w-full" name="title" placeholder="Task title" required />
        <textarea class="enhanced-input w-full" rows="4" name="description" placeholder="Description"></textarea>
        <select name="sprint_id" class="enhanced-input w-full">
          <option value="">No sprint</option>
          <?php $sp2 = $conn->query('SELECT * FROM sprints ORDER BY start_date DESC'); while($s = $sp2->fetch_assoc()): ?>
          <option value="<?php echo e($s['id']); ?>"><?php echo e($s['name']); ?></option>
          <?php endwhile; ?>
        </select>
        <input class="enhanced-input w-full" name="assignee_employee_id" placeholder="Assignee Employee ID (optional)" />
        <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Add Task</button>
      </form>
    </div>

    <!-- Sprints List -->
    <div class="bg-white p-6 rounded shadow">
      <h3 class="text-lg font-semibold mb-4">Sprints</h3>
      <ul class="space-y-2">
        <?php while($s = $sprints->fetch_assoc()): ?>
          <li class="border rounded p-3"><div class="font-semibold"><?php echo e($s['name']); ?></div>
            <div class="text-xs text-gray-500"><?php echo e($s['start_date']); ?> → <?php echo e($s['end_date']); ?></div>
          </li>
        <?php endwhile; ?>
      </ul>
    </div>
  </div>

  <div class="bg-white p-6 rounded shadow mt-8">
    <h3 class="text-lg font-semibold mb-4">Recent Tasks</h3>
    <div class="overflow-x-auto">
      <table class="min-w-full">
        <thead class="bg-gray-50"><tr>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Title</th>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Status</th>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Assignee</th>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Created</th>
        </tr></thead>
        <tbody>
          <?php while($t = $tasks->fetch_assoc()): ?>
            <tr class="border-b">
              <td class="px-4 py-2 text-sm"><?php echo e($t['title']); ?></td>
              <td class="px-4 py-2 text-sm"><?php echo e($t['status']); ?></td>
              <td class="px-4 py-2 text-sm"><?php echo e($t['assignee'] ?: '-'); ?></td>
              <td class="px-4 py-2 text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($t['created_at'])); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>