<?php
$pageTitle = 'Attendance';
include 'includes/header.php';

// Must be logged in
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) { header('Location: ' . url_for('login.php')); exit; }

// Current user's employee id
$current_employee_id = null;
if ($stmt = $conn->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1')) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($current_employee_id);
    $stmt->fetch();
    $stmt->close();
}
if (!$current_employee_id) {
    echo '<div class="p-6 bg-white rounded-lg shadow">Employee profile not found.</div>';
    include 'includes/footer.php';
    exit;
}

// Check if user is HR/Admin
$can_view_all = check_role_access($conn, ['Admin','HR Manager']);
$view = isset($_GET['view']) && $can_view_all ? $_GET['view'] : 'personal';

// Selected employee when viewing all
$selected_employee_id = $current_employee_id;
if ($view === 'staff') {
    $selected_employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : $current_employee_id;
    // Ensure selected exists
    $chk = $conn->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
    $chk->bind_param('i', $selected_employee_id);
    $chk->execute();
    $chk->bind_result($existsCount);
    $chk->fetch();
    $chk->close();
    if ($existsCount === 0) { $selected_employee_id = $current_employee_id; }
}

$month = $_GET['month'] ?? date('Y-m');
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Ensure config exists
$conn->query("CREATE TABLE IF NOT EXISTS attendance_config (id INT PRIMARY KEY DEFAULT 1, office_lat DECIMAL(10,7) NOT NULL DEFAULT 0, office_lng DECIMAL(10,7) NOT NULL DEFAULT 0, radius_meters INT NOT NULL DEFAULT 50, in_start TIME NOT NULL DEFAULT '09:30:00', in_end TIME NOT NULL DEFAULT '09:45:00', out_start TIME NOT NULL DEFAULT '17:30:00', out_end TIME NOT NULL DEFAULT '17:45:00', updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$conn->query("INSERT IGNORE INTO attendance_config (id) VALUES (1)");
$config = $conn->query('SELECT * FROM attendance_config WHERE id=1')->fetch_assoc();

// Function to get employee joining date
function get_employee_joining_date($conn, $employee_id) {
    $stmt = $conn->prepare("SELECT date_of_joining FROM employees WHERE id = ?");
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $stmt->bind_result($joining_date);
    $stmt->fetch();
    $stmt->close();
    return $joining_date;
}

// Function to compute attendance status
function compute_attendance_status($date, $row, $config, $joining_date) {
    // If date is before joining date, return 'Not Joined'
    if ($joining_date && $date < $joining_date) {
        return 'Not Joined';
    }
    
    if (!$row || (!$row['clock_in_time'] && !$row['clock_out_time'])) {
        return 'Absent';
    }
    
    if (!$row['clock_in_time'] || !$row['clock_out_time']) {
        return 'Miss Punch';
    }
    
    $inTs = strtotime($row['clock_in_time']);
    $outTs = strtotime($row['clock_out_time']);
    $workHours = ($outTs - $inTs) / 3600.0;
    
    if ($workHours < 4) return 'Half Day';
    
    $lateIn = strtotime($date . ' ' . $config['in_end']) < $inTs;
    $earlyOut = $outTs < strtotime($date . ' ' . $config['out_end']);
    
    if ($lateIn) return 'Late In';
    if ($earlyOut) return 'Early Out';
    
    return 'Present';
}

// Function to get working hours
function get_working_hours($row) {
    if (!$row || !$row['clock_in_time'] || !$row['clock_out_time']) {
        return '---';
    }
    
    $inTs = strtotime($row['clock_in_time']);
    $outTs = strtotime($row['clock_out_time']);
    $workHours = ($outTs - $inTs) / 3600.0;
    
    if ($workHours < 1) {
        return number_format($workHours * 60, 0) . ' min';
    }
    
    return number_format($workHours, 1) . ' hours';
}

// Function to format time
function format_time($time) {
    if (!$time) return '---';
    return date('h:i A', strtotime($time));
}

// Get employee joining date
$joining_date = get_employee_joining_date($conn, $selected_employee_id);

// Fetch attendance for month for selected employee
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));

$att = [];
$sql = "SELECT date, clock_in_time, clock_out_time, in_photo_base64, out_photo_base64 FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $selected_employee_id, $start, $end);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) { $att[$row['date']] = $row; }
$stmt->close();

// Fetch approved leaves for the month for selected employee
$approved_leaves = [];
$lsql = "SELECT start_date, end_date FROM leaves WHERE employee_id = ? AND status = 'Approved' AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (? BETWEEN start_date AND end_date) OR (? BETWEEN start_date AND end_date))";
$lst = $conn->prepare($lsql);
$lst->bind_param('issssss', $selected_employee_id, $start, $end, $start, $end, $start, $end);
$lst->execute();
$lres = $lst->get_result();
while($lr = $lres->fetch_assoc()) { $approved_leaves[] = $lr; }
$lst->close();

function is_approved_leave_for_date(array $leaves, string $date): bool {
    foreach ($leaves as $lr) {
        if ($date >= $lr['start_date'] && $date <= $lr['end_date']) return true;
    }
    return false;
}

// Generate attendance cards for the month
$days = (int)date('t', strtotime($start));
$today = date('Y-m-d');
$summary = ['present'=>0,'absent'=>0,'late_in'=>0,'leave'=>0,'not_joined'=>0];
$cards = [];

for ($d=1; $d <= $days; $d++) {
    $date = date('Y-m-d', strtotime($start . ' +' . ($d-1) . ' day'));
    $isFuture = ($date > $today);
    $onLeave = is_approved_leave_for_date($approved_leaves, $date);
    $row = $att[$date] ?? null;
    
    // Check if employee had joined by this date
    $hasJoined = !$joining_date || $date >= $joining_date;
    
    if (!$hasJoined) {
        $flag = 'Not Joined';
        $summary['not_joined']++;
    } elseif ($onLeave) {
        $flag = 'Approved Leave';
        $summary['leave']++;
    } elseif ($isFuture) {
        $flag = 'Future';
    } else {
        $flag = compute_attendance_status($date, $row, $config, $joining_date);
        if ($flag === 'Present') $summary['present']++;
        elseif ($flag === 'Absent') $summary['absent']++;
        elseif ($flag === 'Late In') $summary['late_in']++;
    }

    $cards[] = [
        'date' => $date,
        'row' => $row,
        'flag' => $flag,
        'future' => $isFuture,
        'leave' => $onLeave,
        'hasJoined' => $hasJoined
    ];
}

// For HR staff view, get today's attendance for all employees
$today_staff_attendance = [];
if ($view === 'staff' && $can_view_all) {
    $today_sql = "SELECT a.*, e.first_name, e.last_name, e.date_of_joining 
                   FROM attendance a 
                   JOIN employees e ON a.employee_id = e.id 
                   WHERE a.date = ?";
    $today_stmt = $conn->prepare($today_sql);
    $today_stmt->bind_param('s', $selected_date);
    $today_stmt->execute();
    $today_res = $today_stmt->get_result();
    while($row = $today_res->fetch_assoc()) {
        $today_staff_attendance[] = $row;
    }
    $today_stmt->close();
}

// Get all employees for HR dropdown
$all_employees = [];
if ($can_view_all) {
    $emp_sql = "SELECT id, first_name, last_name, date_of_joining FROM employees ORDER BY first_name, last_name";
    $emp_res = $conn->query($emp_sql);
    while($emp = $emp_res->fetch_assoc()) {
        $all_employees[] = $emp;
    }
}
?>

<div class="container mx-auto">
  <!-- Header Section -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
      <h2 class="text-2xl font-semibold text-gray-700">Attendance - <?php echo date('F Y', strtotime($start)); ?></h2>
      
      <?php if ($can_view_all): ?>
        <!-- HR View Toggle Buttons -->
        <div class="ml-2 inline-flex rounded-md shadow-sm" role="group">
          <a href="<?php echo url_for('my_attendance.php?view=personal&month='.e($month)); ?>" 
             class="px-3 py-1 text-sm border <?php echo $view==='personal'?'bg-indigo-600 text-white border-indigo-600':'bg-white text-gray-700 border-gray-300'; ?> rounded-l">
            Personal Attendance
          </a>
          <a href="<?php echo url_for('my_attendance.php?view=staff&month='.e($month)); ?>" 
             class="px-3 py-1 text-sm border <?php echo $view==='staff'?'bg-indigo-600 text-white border-indigo-600':'bg-white text-gray-700 border-gray-300'; ?> rounded-r">
            Staff Attendance
          </a>
        </div>
      <?php endif; ?>
    </div>
    
    <div class="flex items-center gap-3">
      <!-- Month Selector -->
      <input type="month" class="enhanced-input" value="<?php echo e($month); ?>" 
             onchange="const u=new URL(window.location.href);u.searchParams.set('month',this.value);window.location=u.toString();" />
      
      <?php if ($view==='staff' && $can_view_all): ?>
        <!-- Date Selector for Staff View -->
        <input type="date" class="enhanced-input" value="<?php echo e($selected_date); ?>" 
               onchange="const u=new URL(window.location.href);u.searchParams.set('date',this.value);window.location=u.toString();" />
        
        <!-- Employee Selector -->
        <form method="GET" class="flex items-center gap-2">
          <input type="hidden" name="view" value="staff" />
          <input type="hidden" name="month" value="<?php echo e($month); ?>" />
          <input type="hidden" name="date" value="<?php echo e($selected_date); ?>" />
          <select name="employee_id" class="enhanced-input" onchange="this.form.submit()">
            <option value="">Select Employee</option>
            <?php foreach($all_employees as $emp): ?>
              <option value="<?php echo (int)$emp['id']; ?>" 
                      <?php echo (int)$emp['id']===$selected_employee_id?'selected':''; ?>>
                <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white p-5 rounded shadow">
      <div class="text-sm text-gray-500">Present</div>
      <div class="text-2xl font-bold text-green-600"><?php echo (int)$summary['present']; ?></div>
    </div>
    <div class="bg-white p-5 rounded shadow">
      <div class="text-sm text-gray-500">Absent</div>
      <div class="text-2xl font-bold text-red-600"><?php echo (int)$summary['absent']; ?></div>
    </div>
    <div class="bg-white p-5 rounded shadow">
      <div class="text-sm text-gray-500">Late In</div>
      <div class="text-2xl font-bold text-yellow-600"><?php echo (int)$summary['late_in']; ?></div>
    </div>
    <div class="bg-white p-5 rounded shadow">
      <div class="text-sm text-gray-500">Approved Leave</div>
      <div class="text-2xl font-bold text-purple-600"><?php echo (int)$summary['leave']; ?></div>
    </div>
    <div class="bg-white p-5 rounded shadow">
      <div class="text-sm text-gray-500">Not Joined</div>
      <div class="text-2xl font-bold text-gray-600"><?php echo (int)$summary['not_joined']; ?></div>
    </div>
  </div>

  <?php if ($view === 'staff' && $can_view_all && !empty($today_staff_attendance)): ?>
    <!-- Today's Staff Attendance Overview -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h3 class="text-lg font-semibold text-gray-700 mb-4">Today's Staff Attendance - <?php echo date('d M Y', strtotime($selected_date)); ?></h3>
      
      <!-- Today's Summary -->
      <?php 
        $today_summary = ['present' => 0, 'absent' => 0, 'late_in' => 0, 'needs_review' => 0];
        foreach($today_staff_attendance as $att) {
          $status = compute_attendance_status($selected_date, $att, $config, $att['date_of_joining']);
          if ($status === 'Present') $today_summary['present']++;
          elseif ($status === 'Absent') $today_summary['absent']++;
          elseif ($status === 'Late In') $today_summary['late_in']++;
          else $today_summary['needs_review']++;
        }
      ?>
      
      <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="text-center">
          <div class="text-2xl font-bold text-green-600"><?php echo $today_summary['present']; ?></div>
          <div class="text-sm text-gray-500">Present</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-red-600"><?php echo $today_summary['absent']; ?></div>
          <div class="text-sm text-gray-500">Absent</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-yellow-600"><?php echo $today_summary['late_in']; ?></div>
          <div class="text-sm text-gray-500">Late In</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-orange-600"><?php echo $today_summary['needs_review']; ?></div>
          <div class="text-sm text-gray-500">Needs Review</div>
        </div>
      </div>
      
      <!-- Today's Employee Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach($today_staff_attendance as $att): 
          $status = compute_attendance_status($selected_date, $att, $config, $att['date_of_joining']);
          $statusColor = $status === 'Present' ? 'text-green-600' : ($status === 'Absent' ? 'text-red-600' : 'text-yellow-600');
          $bgColor = $status === 'Present' ? 'bg-green-50' : ($status === 'Absent' ? 'bg-red-50' : 'bg-yellow-50');
        ?>
          <div class="bg-white border rounded-lg p-4 <?php echo $bgColor; ?>">
            <div class="flex items-center gap-3 mb-3">
              <?php if ($att['in_photo_base64']): ?>
                <img src="data:image/jpeg;base64,<?php echo e($att['in_photo_base64']); ?>" 
                     alt="Employee" class="w-12 h-12 rounded-full object-cover" />
              <?php else: ?>
                <div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center">
                  <span class="text-gray-600 font-semibold"><?php echo strtoupper(substr($att['first_name'], 0, 1)); ?></span>
                </div>
              <?php endif; ?>
              <div>
                <div class="font-semibold text-gray-800"><?php echo e($att['first_name'] . ' ' . $att['last_name']); ?></div>
                <div class="text-sm <?php echo $statusColor; ?> font-medium"><?php echo e($status); ?></div>
              </div>
            </div>
            <div class="text-sm text-gray-600 space-y-1">
              <div>In: <?php echo format_time($att['clock_in_time']); ?></div>
              <div>Out: <?php echo format_time($att['clock_out_time']); ?></div>
              <div>Total: <?php echo get_working_hours($att); ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Monthly Attendance Grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <?php foreach ($cards as $c): 
      $row = $c['row']; 
      $flag = $c['flag']; 
      $future = $c['future']; 
      $onLeave = $c['leave'];
      $hasJoined = $c['hasJoined'];
      
      // Determine card styling based on status
      if (!$hasJoined) {
        $cardBg = 'bg-gray-100';
        $borderColor = 'border-gray-300';
        $statusColor = 'text-gray-500';
      } elseif ($onLeave) {
        $cardBg = 'bg-purple-50';
        $borderColor = 'border-purple-500';
        $statusColor = 'text-purple-600';
      } elseif ($flag === 'Present') {
        $cardBg = 'bg-green-50';
        $borderColor = 'border-green-500';
        $statusColor = 'text-green-600';
      } elseif ($flag === 'Absent') {
        $cardBg = 'bg-red-50';
        $borderColor = 'border-red-500';
        $statusColor = 'text-red-600';
      } elseif ($flag === 'Late In') {
        $cardBg = 'bg-green-50';
        $borderColor = 'border-green-500';
        $statusColor = 'text-green-600';
      } else {
        $cardBg = 'bg-yellow-50';
        $borderColor = 'border-yellow-500';
        $statusColor = 'text-yellow-600';
      }
    ?>
      <div class="bg-white rounded-lg shadow border-t-4 <?php echo $borderColor; ?> overflow-hidden">
        <div class="p-5">
          <!-- Top Section - Visual Indicator -->
          <div class="mb-3">
            <?php if (!$hasJoined): ?>
              <!-- Not Joined - Grey placeholder -->
              <div class="flex items-center justify-center h-32 bg-gray-100 rounded-lg">
                <div class="text-center">
                  <div class="text-2xl font-semibold text-gray-400"><?php echo date('d', strtotime($c['date'])); ?></div>
                  <div class="text-sm text-gray-400"><?php echo date('M', strtotime($c['date'])); ?></div>
                </div>
              </div>
            <?php elseif ($future): ?>
              <!-- Future Date -->
              <div class="flex items-center justify-center h-32 bg-gray-50 rounded-lg">
                <div class="text-center">
                  <div class="text-2xl font-semibold text-gray-400"><?php echo date('d', strtotime($c['date'])); ?></div>
                  <div class="text-sm text-gray-400"><?php echo date('M', strtotime($c['date'])); ?></div>
                </div>
              </div>
            <?php elseif ($onLeave): ?>
              <!-- Approved Leave -->
              <div class="flex items-center justify-center h-32 bg-purple-100 rounded-lg">
                <div class="text-center">
                  <div class="text-lg font-semibold text-purple-600">Approved</div>
                  <div class="text-sm text-purple-500">Leave</div>
                </div>
              </div>
            <?php elseif ($flag === 'Absent'): ?>
              <!-- Absent - Red X icon -->
              <div class="flex items-center justify-center h-32 bg-red-100 rounded-lg">
                <div class="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center">
                  <span class="text-white text-2xl font-bold">✕</span>
                </div>
              </div>
            <?php else: ?>
              <!-- Present/Late In - Employee photo -->
              <?php $photo = $row && $row['in_photo_base64'] ? $row['in_photo_base64'] : ($row['out_photo_base64'] ?? ''); ?>
              <?php if ($photo): ?>
                <img src="data:image/jpeg;base64,<?php echo e($photo); ?>" 
                     alt="Employee" class="w-full h-32 object-cover rounded-lg" />
              <?php else: ?>
                <div class="flex items-center justify-center h-32 bg-gray-100 rounded-lg">
                  <div class="w-16 h-16 bg-gray-300 rounded-full flex items-center justify-center">
                    <span class="text-gray-600 text-xl font-semibold">👤</span>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          
          <!-- Middle Section - Status and Details -->
          <div class="mb-3">
            <div class="flex justify-between items-center mb-2">
              <div class="text-sm font-semibold <?php echo $statusColor; ?>">
                <?php echo e($flag === 'Future' ? '' : $flag); ?>
              </div>
              <div class="text-xs text-gray-500">
                <?php echo date('d M Y', strtotime($c['date'])); ?>
              </div>
            </div>

            <?php if ($hasJoined && !$future && !$onLeave): ?>
              <div class="text-sm text-gray-800 space-y-1">
                <div>Intime: <?php echo format_time($row['clock_in_time']); ?></div>
                <div>Out time: <?php echo format_time($row['clock_out_time']); ?></div>
                <div>Total working hour: <?php echo get_working_hours($row); ?></div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Bottom Section - Date chip -->
          <div class="flex justify-end">
            <span class="text-xs px-2 py-1 rounded text-white 
                       <?php echo $hasJoined ? ($flag === 'Present' || $flag === 'Late In' ? 'bg-green-600' : 
                                               ($flag === 'Absent' ? 'bg-red-600' : 
                                               ($onLeave ? 'bg-purple-600' : 'bg-yellow-500'))) : 'bg-gray-500'; ?>">
              <?php echo date('d M Y', strtotime($c['date'])); ?>
            </span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>