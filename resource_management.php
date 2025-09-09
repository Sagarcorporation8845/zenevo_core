<?php
$pageTitle = 'Resource Management';
include 'includes/header.php';

// Security Check - Only Admin and HR can access
if (!check_role_access($conn, ['Admin', 'HR Manager'])) {
    echo '<div class="p-6 bg-white rounded-lg shadow-md">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Create tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS `employee_managers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `manager_id` INT NOT NULL,
  `assigned_by` INT NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) DEFAULT 1,
  KEY `idx_employee` (`employee_id`),
  KEY `idx_manager` (`manager_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'error', 'broadcast') DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `is_shown` TINYINT(1) DEFAULT 0,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_read` (`user_id`, `is_read`),
  KEY `idx_user_shown` (`user_id`, `is_shown`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Add Manager role and permissions if they don't exist
$conn->query("INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (5, 'Manager')");
$conn->query("INSERT IGNORE INTO `permissions` (`id`, `name`, `description`) VALUES
(10, 'manage_team', 'Manage assigned team members'),
(11, 'create_sprints', 'Create and manage sprints'),
(12, 'assign_tasks', 'Assign tasks to team members'),
(13, 'view_team_reports', 'View team productivity reports'),
(14, 'send_messages', 'Send messages to team members')");
$conn->query("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(5, 10), (5, 11), (5, 12), (5, 13), (5, 14)");

// Handle flash messages
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Get all managers (users with Manager role)
$managers_query = "SELECT e.id, e.first_name, e.last_name, u.email, e.designation, e.department
                   FROM employees e 
                   JOIN users u ON e.user_id = u.id 
                   JOIN roles r ON u.role_id = r.id 
                   WHERE r.name = 'Manager' AND u.is_active = 1
                   ORDER BY e.first_name, e.last_name";
$managers_result = $conn->query($managers_query);

// Get all employees with their current manager assignments
$employees_query = "SELECT e.id, e.first_name, e.last_name, e.designation, e.department,
                           em.manager_id, 
                           CONCAT(m.first_name, ' ', m.last_name) as manager_name,
                           u.email
                    FROM employees e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN employee_managers em ON e.id = em.employee_id AND em.is_active = 1
                    LEFT JOIN employees m ON em.manager_id = m.id
                    WHERE u.is_active = 1
                    ORDER BY e.first_name, e.last_name";
$employees_result = $conn->query($employees_query);
?>

<div class="container mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-semibold text-gray-700">Resource Management</h2>
            <p class="text-gray-600 mt-1">Assign and manage employee-manager relationships</p>
        </div>
        <button onclick="openBroadcastModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
            </svg>
            Send Broadcast
        </button>
    </div>

    <!-- Flash Message Display -->
    <?php if ($flash_message): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
            <?php echo e($flash_message['message']); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Manager Assignment Section -->
        <div class="lg:col-span-2">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Employee Manager Assignment</h3>
                
                <!-- Manager Assignment Form -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <form action="actions/resource_action.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="assign_manager">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Employee Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Employee</label>
                                <div class="relative">
                                    <input type="hidden" name="employee_id" id="employee_id" required>
                                    <input type="text" id="employee_search" class="enhanced-input w-full" 
                                           placeholder="Search employee by name..." autocomplete="off" required>
                                    <div id="employee_dropdown" class="absolute z-50 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-48 overflow-y-auto"></div>
                                </div>
                            </div>
                            
                            <!-- Manager Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Manager</label>
                                <div class="relative">
                                    <input type="hidden" name="manager_id" id="manager_id" required>
                                    <input type="text" id="manager_search" class="enhanced-input w-full" 
                                           placeholder="Search manager by name..." autocomplete="off" required>
                                    <div id="manager_dropdown" class="absolute z-50 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-48 overflow-y-auto"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-medium">
                                Assign Manager
                            </button>
                            <button type="button" onclick="clearForm()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md font-medium">
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Employee List with Manager Assignments -->
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Employee</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Department</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Current Manager</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($employees_result && $employees_result->num_rows > 0): ?>
                                <?php while($employee = $employees_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 w-8 h-8">
                                                    <div class="w-full h-full rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-semibold text-sm">
                                                        <?php echo e(strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1))); ?>
                                                    </div>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?php echo e($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500"><?php echo e($employee['designation']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900"><?php echo e($employee['department']); ?></td>
                                        <td class="px-4 py-3">
                                            <?php if ($employee['manager_name']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <?php echo e($employee['manager_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    No Manager
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button onclick="selectEmployee(<?php echo $employee['id']; ?>, '<?php echo e($employee['first_name'] . ' ' . $employee['last_name']); ?>', '<?php echo e($employee['designation']); ?>', '<?php echo e($employee['department']); ?>')" 
                                                        class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                                    Assign
                                                </button>
                                                <?php if ($employee['manager_id']): ?>
                                                    <form action="actions/resource_action.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this manager assignment?')">
                                                        <input type="hidden" name="action" value="remove_manager">
                                                        <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium">
                                                            Remove
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-8 text-gray-500">
                                        No employees found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Managers Overview -->
        <div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Managers Overview</h3>
                
                <?php if ($managers_result && $managers_result->num_rows > 0): ?>
                    <div class="space-y-3">
                        <?php while($manager = $managers_result->fetch_assoc()): ?>
                            <?php
                            // Get team count for this manager
                            $team_count_query = "SELECT COUNT(*) as team_size FROM employee_managers WHERE manager_id = ? AND is_active = 1";
                            $stmt = $conn->prepare($team_count_query);
                            $stmt->bind_param('i', $manager['id']);
                            $stmt->execute();
                            $team_result = $stmt->get_result();
                            $team_count = $team_result->fetch_assoc()['team_size'];
                            $stmt->close();
                            ?>
                            <div class="p-3 border border-gray-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 w-10 h-10">
                                            <div class="w-full h-full rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-semibold">
                                                <?php echo e(strtoupper(substr($manager['first_name'], 0, 1) . substr($manager['last_name'], 0, 1))); ?>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900">
                                                <?php echo e($manager['first_name'] . ' ' . $manager['last_name']); ?>
                                            </p>
                                            <p class="text-xs text-gray-500"><?php echo e($manager['designation']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-gray-900"><?php echo $team_count; ?></p>
                                        <p class="text-xs text-gray-500">Team Members</p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-500">
                        <p>No managers found.</p>
                        <p class="text-xs mt-1">Create users with Manager role first.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Broadcast Message Modal -->
<div id="broadcastModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <form action="actions/resource_action.php" method="POST">
                <input type="hidden" name="action" value="send_broadcast">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Send Broadcast Message</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                            <input type="text" name="title" class="enhanced-input w-full" placeholder="Notification title" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                            <textarea name="message" rows="4" class="enhanced-input w-full" placeholder="Your message to all employees..." required></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                            <select name="type" class="enhanced-input w-full">
                                <option value="info">Information</option>
                                <option value="success">Success</option>
                                <option value="warning">Warning</option>
                                <option value="error">Important</option>
                                <option value="broadcast">Broadcast</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3">
                    <button type="button" onclick="closeBroadcastModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                        Send to All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let employees = [];
let managers = [];

// Fetch data on page load
document.addEventListener('DOMContentLoaded', function() {
    fetchEmployees();
    fetchManagers();
});

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

// Fetch managers list
function fetchManagers() {
    fetch('actions/search_employees.php?role=Manager')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                managers = data.employees;
            }
        })
        .catch(error => console.error('Error fetching managers:', error));
}

// Employee search functionality
const employeeSearch = document.getElementById('employee_search');
const employeeDropdown = document.getElementById('employee_dropdown');

employeeSearch.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    if (query.length < 1) {
        employeeDropdown.classList.add('hidden');
        return;
    }
    
    const filtered = employees.filter(emp => 
        emp.name.toLowerCase().includes(query) ||
        emp.designation.toLowerCase().includes(query) ||
        emp.department.toLowerCase().includes(query)
    );
    
    displayEmployeeOptions(filtered);
});

function displayEmployeeOptions(employeeList) {
    if (employeeList.length === 0) {
        employeeDropdown.innerHTML = '<div class="p-2 text-gray-500 text-sm">No employees found</div>';
        employeeDropdown.classList.remove('hidden');
        return;
    }
    
    const html = employeeList.map(emp => `
        <div class="p-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0" 
             onclick="selectEmployeeFromDropdown(${emp.id}, '${emp.name}', '${emp.designation}', '${emp.department}')">
            <div class="font-medium text-gray-900">${emp.name}</div>
            <div class="text-sm text-gray-600">${emp.designation} • ${emp.department}</div>
        </div>
    `).join('');
    
    employeeDropdown.innerHTML = html;
    employeeDropdown.classList.remove('hidden');
}

// Manager search functionality
const managerSearch = document.getElementById('manager_search');
const managerDropdown = document.getElementById('manager_dropdown');

managerSearch.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    if (query.length < 1) {
        managerDropdown.classList.add('hidden');
        return;
    }
    
    const filtered = managers.filter(mgr => 
        mgr.name.toLowerCase().includes(query) ||
        mgr.designation.toLowerCase().includes(query) ||
        mgr.department.toLowerCase().includes(query)
    );
    
    displayManagerOptions(filtered);
});

function displayManagerOptions(managerList) {
    if (managerList.length === 0) {
        managerDropdown.innerHTML = '<div class="p-2 text-gray-500 text-sm">No managers found</div>';
        managerDropdown.classList.remove('hidden');
        return;
    }
    
    const html = managerList.map(mgr => `
        <div class="p-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0" 
             onclick="selectManagerFromDropdown(${mgr.id}, '${mgr.name}', '${mgr.designation}', '${mgr.department}')">
            <div class="font-medium text-gray-900">${mgr.name}</div>
            <div class="text-sm text-gray-600">${mgr.designation} • ${mgr.department}</div>
        </div>
    `).join('');
    
    managerDropdown.innerHTML = html;
    managerDropdown.classList.remove('hidden');
}

// Selection functions
function selectEmployeeFromDropdown(id, name, designation, department) {
    document.getElementById('employee_id').value = id;
    document.getElementById('employee_search').value = `${name} (${designation})`;
    employeeDropdown.classList.add('hidden');
}

function selectManagerFromDropdown(id, name, designation, department) {
    document.getElementById('manager_id').value = id;
    document.getElementById('manager_search').value = `${name} (${designation})`;
    managerDropdown.classList.add('hidden');
}

function selectEmployee(id, name, designation, department) {
    document.getElementById('employee_id').value = id;
    document.getElementById('employee_search').value = `${name} (${designation})`;
    employeeDropdown.classList.add('hidden');
}

function clearForm() {
    document.getElementById('employee_id').value = '';
    document.getElementById('employee_search').value = '';
    document.getElementById('manager_id').value = '';
    document.getElementById('manager_search').value = '';
    employeeDropdown.classList.add('hidden');
    managerDropdown.classList.add('hidden');
}

// Modal functions
function openBroadcastModal() {
    document.getElementById('broadcastModal').classList.remove('hidden');
}

function closeBroadcastModal() {
    document.getElementById('broadcastModal').classList.add('hidden');
}

// Hide dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!employeeSearch.contains(e.target) && !employeeDropdown.contains(e.target)) {
        employeeDropdown.classList.add('hidden');
    }
    if (!managerSearch.contains(e.target) && !managerDropdown.contains(e.target)) {
        managerDropdown.classList.add('hidden');
    }
});
</script>

<?php include 'includes/footer.php'; ?>