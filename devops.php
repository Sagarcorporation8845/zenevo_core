<?php
$pageTitle = 'DevOps / Collaboration';
include 'includes/header.php';

// Ensure schema
$conn->query("CREATE TABLE IF NOT EXISTS sprints (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS tasks (id INT AUTO_INCREMENT PRIMARY KEY, sprint_id INT NULL, title VARCHAR(200) NOT NULL, description TEXT NULL, status ENUM('Todo','In Progress','Blocked','Done') DEFAULT 'Todo', assignee_employee_id INT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$roleViewAll = check_role_access($conn, ['Admin','Team Lead','HR Manager']);
$currentUserId = $_SESSION['user_id'];
$currentEmployeeId = null;
if ($st = $conn->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1')) {
  $st->bind_param('i', $currentUserId);
  $st->execute();
  $st->bind_result($currentEmployeeId);
  $st->fetch();
  $st->close();
}

// Fetch data
$sprints = $conn->query('SELECT * FROM sprints ORDER BY start_date DESC');

if ($roleViewAll) {
  $tasks = $conn->query('SELECT t.*, CONCAT(e.first_name, " ", e.last_name) AS assignee
                         FROM tasks t LEFT JOIN employees e ON t.assignee_employee_id=e.id ORDER BY t.created_at DESC LIMIT 200');
} else {
  // Employees see tasks assigned to them or unassigned (broadcast)
  $ts = $conn->prepare('SELECT t.*, CONCAT(e.first_name, " ", e.last_name) AS assignee
                        FROM tasks t LEFT JOIN employees e ON t.assignee_employee_id=e.id
                        WHERE t.assignee_employee_id = ? OR t.assignee_employee_id IS NULL
                        ORDER BY t.created_at DESC LIMIT 200');
  $ts->bind_param('i', $currentEmployeeId);
  $ts->execute();
  $tasks = $ts->get_result();
}
?>
<div class="container mx-auto">
  <div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-700">DevOps / Collaboration</h2>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <?php if ($roleViewAll): ?>
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
        <div class="relative">
          <input type="hidden" name="assignee_employee_id" id="assignee_employee_id" />
          <input type="text" id="assignee_search" class="enhanced-input w-full" placeholder="Search teammate by name..." autocomplete="off" />
          <div id="assignee_dropdown" class="absolute z-50 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-48 overflow-y-auto"></div>
        </div>
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
    <?php else: ?>
      <!-- If not lead/manager, show only sprints list occupying full width on small screens -->
      <div class="bg-white p-6 rounded shadow lg:col-span-3">
        <h3 class="text-lg font-semibold mb-4">Active Sprints</h3>
        <ul class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <?php while($s = $sprints->fetch_assoc()): ?>
            <li class="border rounded p-3"><div class="font-semibold"><?php echo e($s['name']); ?></div>
              <div class="text-xs text-gray-500"><?php echo e($s['start_date']); ?> → <?php echo e($s['end_date']); ?></div>
            </li>
          <?php endwhile; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-white p-6 rounded shadow mt-8">
    <h3 class="text-lg font-semibold mb-4"><?php echo $roleViewAll ? 'Recent Tasks' : 'My and Broadcast Tasks'; ?></h3>
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
              <td class="px-4 py-2 text-sm"><?php echo e($t['assignee'] ?: 'All Employees'); ?></td>
              <td class="px-4 py-2 text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($t['created_at'])); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Team member search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('assignee_search');
    const hiddenInput = document.getElementById('assignee_employee_id');
    const dropdown = document.getElementById('assignee_dropdown');
    
    if (!searchInput) return;
    
    let employees = [];
    let searchTimeout;
    
    // Fetch employees list
    function fetchEmployees() {
        fetch('actions/search_employees.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    employees = data.employees;
                }
            })
            .catch(error => console.error('Error fetching employees:', error));
    }
    
    // Filter and display employees
    function filterEmployees(query) {
        if (query.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }
        
        const filtered = employees.filter(emp => 
            emp.name.toLowerCase().includes(query.toLowerCase()) ||
            emp.designation.toLowerCase().includes(query.toLowerCase()) ||
            emp.department.toLowerCase().includes(query.toLowerCase())
        );
        
        displayEmployees(filtered);
    }
    
    // Display employee options
    function displayEmployees(employeeList) {
        if (employeeList.length === 0) {
            dropdown.innerHTML = '<div class="p-2 text-gray-500 text-sm">No employees found</div>';
            dropdown.classList.remove('hidden');
            return;
        }
        
        const html = employeeList.map(emp => `
            <div class="p-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0" 
                 onclick="selectEmployee(${emp.id}, '${emp.name}', '${emp.designation}', '${emp.department}')">
                <div class="font-medium text-gray-900">${emp.name}</div>
                <div class="text-sm text-gray-600">${emp.designation} • ${emp.department}</div>
            </div>
        `).join('');
        
        dropdown.innerHTML = html;
        dropdown.classList.remove('hidden');
    }
    
    // Handle search input
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filterEmployees(this.value);
        }, 300);
    });
    
    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
    
    // Show dropdown when focusing on search input
    searchInput.addEventListener('focus', function() {
        if (this.value.length >= 2) {
            filterEmployees(this.value);
        }
    });
    
    // Fetch employees on page load
    fetchEmployees();
});

// Select employee function (called from dropdown)
function selectEmployee(id, name, designation, department) {
    document.getElementById('assignee_employee_id').value = id;
    document.getElementById('assignee_search').value = `${name} (${designation})`;
    document.getElementById('assignee_dropdown').classList.add('hidden');
}

// Clear selection
function clearSelection() {
    document.getElementById('assignee_employee_id').value = '';
    document.getElementById('assignee_search').value = '';
    document.getElementById('assignee_dropdown').classList.add('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>