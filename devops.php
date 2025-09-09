<?php
$pageTitle = 'DevOps / Collaboration';
include 'includes/header.php';

// Ensure schema
$conn->query("CREATE TABLE IF NOT EXISTS sprints (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS tasks (id INT AUTO_INCREMENT PRIMARY KEY, sprint_id INT NULL, title VARCHAR(200) NOT NULL, description TEXT NULL, status ENUM('Todo','In Progress','Blocked','Done') DEFAULT 'Todo', assignee_employee_id INT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$roleViewAll = check_role_access($conn, ['Admin','Team Lead','HR Manager']);
$isManager = check_role_access($conn, ['Manager']);
$currentUserId = $_SESSION['user_id'];
$currentEmployeeId = null;
if ($st = $conn->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1')) {
  $st->bind_param('i', $currentUserId);
  $st->execute();
  $st->bind_result($currentEmployeeId);
  $st->fetch();
  $st->close();
}

// Get manager's team members if user is a manager
$teamMembers = [];
if ($isManager && $currentEmployeeId) {
    $teamQuery = $conn->prepare("SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) as name, e.designation, e.department
                                FROM employee_managers em
                                JOIN employees e ON em.employee_id = e.id
                                JOIN users u ON e.user_id = u.id
                                WHERE em.manager_id = ? AND em.is_active = 1 AND u.is_active = 1
                                ORDER BY e.first_name, e.last_name");
    $teamQuery->bind_param('i', $currentEmployeeId);
    $teamQuery->execute();
    $teamResult = $teamQuery->get_result();
    while ($member = $teamResult->fetch_assoc()) {
        $teamMembers[] = $member;
    }
    $teamQuery->close();
}

// Fetch data
$sprints = $conn->query('SELECT * FROM sprints ORDER BY start_date DESC');

if ($roleViewAll) {
  $tasks = $conn->query('SELECT t.*, CONCAT(e.first_name, " ", e.last_name) AS assignee, s.name as sprint_name
                         FROM tasks t 
                         LEFT JOIN employees e ON t.assignee_employee_id=e.id 
                         LEFT JOIN sprints s ON t.sprint_id = s.id
                         ORDER BY t.created_at DESC LIMIT 200');
} elseif ($isManager && $currentEmployeeId) {
  // Managers see tasks assigned to their team members
  $ts = $conn->prepare('SELECT t.*, CONCAT(e.first_name, " ", e.last_name) AS assignee, s.name as sprint_name
                        FROM tasks t 
                        LEFT JOIN employees e ON t.assignee_employee_id=e.id
                        LEFT JOIN sprints s ON t.sprint_id = s.id
                        LEFT JOIN employee_managers em ON e.id = em.employee_id
                        WHERE (em.manager_id = ? AND em.is_active = 1) OR t.assignee_employee_id IS NULL OR t.created_by = ?
                        ORDER BY t.created_at DESC LIMIT 200');
  $ts->bind_param('ii', $currentEmployeeId, $currentUserId);
  $ts->execute();
  $tasks = $ts->get_result();
} else {
  // Employees see tasks assigned to them or unassigned (broadcast)
  $ts = $conn->prepare('SELECT t.*, CONCAT(e.first_name, " ", e.last_name) AS assignee, s.name as sprint_name
                        FROM tasks t 
                        LEFT JOIN employees e ON t.assignee_employee_id=e.id
                        LEFT JOIN sprints s ON t.sprint_id = s.id
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
    <?php if ($roleViewAll || $isManager): ?>
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
        
        <?php if ($isManager && !empty($teamMembers)): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Quick Assign to Team Member</label>
          <div class="grid grid-cols-2 gap-2">
            <?php foreach ($teamMembers as $member): ?>
              <button type="button" onclick="selectEmployee(<?php echo $member['id']; ?>, '<?php echo e($member['name']); ?>', '<?php echo e($member['designation']); ?>', '<?php echo e($member['department']); ?>')" 
                      class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-left">
                <?php echo e($member['name']); ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-3">
          <select name="priority" class="enhanced-input">
            <option value="Medium">Medium Priority</option>
            <option value="Low">Low Priority</option>
            <option value="High">High Priority</option>
            <option value="Critical">Critical Priority</option>
          </select>
          <input type="date" name="deadline" class="enhanced-input" placeholder="Deadline (optional)" />
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
    <h3 class="text-lg font-semibold mb-4">
      <?php 
      if ($roleViewAll) {
          echo 'All Tasks';
      } elseif ($isManager) {
          echo 'Team Tasks';
      } else {
          echo 'My Tasks';
      }
      ?>
    </h3>
    <div class="overflow-x-auto">
      <table class="min-w-full">
        <thead class="bg-gray-50"><tr>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Title</th>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Sprint</th>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Status</th>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Priority</th>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Assignee</th>
          <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-600">Created</th>
          <?php if ($roleViewAll || $isManager): ?>
          <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-600">Actions</th>
          <?php endif; ?>
        </tr></thead>
        <tbody>
          <?php if ($tasks && $tasks->num_rows > 0): ?>
            <?php while($t = $tasks->fetch_assoc()): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-2">
                  <div class="text-sm font-medium text-gray-900"><?php echo e($t['title']); ?></div>
                  <?php if ($t['description']): ?>
                    <div class="text-xs text-gray-500"><?php echo e(substr($t['description'], 0, 100)) . (strlen($t['description']) > 100 ? '...' : ''); ?></div>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-sm">
                  <?php if ($t['sprint_name']): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      <?php echo e($t['sprint_name']); ?>
                    </span>
                  <?php else: ?>
                    <span class="text-gray-400">No Sprint</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    <?php 
                    switch($t['status']) {
                        case 'Todo': echo 'bg-gray-100 text-gray-800'; break;
                        case 'In Progress': echo 'bg-yellow-100 text-yellow-800'; break;
                        case 'Blocked': echo 'bg-red-100 text-red-800'; break;
                        case 'Done': echo 'bg-green-100 text-green-800'; break;
                        default: echo 'bg-gray-100 text-gray-800'; break;
                    }
                    ?>">
                    <?php echo e($t['status']); ?>
                  </span>
                </td>
                <td class="px-4 py-2">
                  <?php if (isset($t['priority'])): ?>
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium
                      <?php 
                      switch($t['priority']) {
                          case 'Critical': echo 'bg-red-100 text-red-800'; break;
                          case 'High': echo 'bg-orange-100 text-orange-800'; break;
                          case 'Medium': echo 'bg-blue-100 text-blue-800'; break;
                          case 'Low': echo 'bg-gray-100 text-gray-800'; break;
                          default: echo 'bg-gray-100 text-gray-800'; break;
                      }
                      ?>">
                      <?php echo e($t['priority'] ?? 'Medium'); ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-sm"><?php echo e($t['assignee'] ?: 'All Employees'); ?></td>
                <td class="px-4 py-2 text-sm text-gray-500"><?php echo date('M j, Y', strtotime($t['created_at'])); ?></td>
                <?php if ($roleViewAll || $isManager): ?>
                <td class="px-4 py-2 text-center">
                  <div class="flex justify-center space-x-2">
                    <button onclick="editTask(<?php echo $t['id']; ?>)" class="text-blue-600 hover:text-blue-900 text-xs">
                      Edit
                    </button>
                    <form action="actions/devops_action.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this task?')">
                      <input type="hidden" name="action" value="delete_task">
                      <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                      <button type="submit" class="text-red-600 hover:text-red-900 text-xs">
                        Delete
                      </button>
                    </form>
                  </div>
                </td>
                <?php endif; ?>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="<?php echo ($roleViewAll || $isManager) ? '7' : '6'; ?>" class="text-center py-8 text-gray-500">
                No tasks found. Create your first task above!
              </td>
            </tr>
          <?php endif; ?>
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