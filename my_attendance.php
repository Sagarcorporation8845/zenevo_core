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
} elseif ($view === 'all_staff') {
    // For all staff view, we'll process all employees
    $selected_employee_id = null;
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

// Set up date range
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));

if ($view === 'all_staff' && $can_view_all) {
    // For all staff view - no need to fetch individual data here
    $joining_date = null;
    $att = [];
    $approved_leaves = [];
} else {
    // Get employee joining date for single employee view
    $joining_date = get_employee_joining_date($conn, $selected_employee_id);

    // Fetch attendance for month for selected employee
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
}

function is_approved_leave_for_date(array $leaves, string $date): bool {
    foreach ($leaves as $lr) {
        if ($date >= $lr['start_date'] && $date <= $lr['end_date']) return true;
    }
    return false;
}

// Generate calendar structure for the month
$days = (int)date('t', strtotime($start));
$today = date('Y-m-d');
$summary = ['present'=>0,'absent'=>0,'late_in'=>0,'leave'=>0,'not_joined'=>0];

// Get first day of month and its day of week (0=Sunday, 6=Saturday)
$first_day = date('Y-m-01', strtotime($start));
$first_day_of_week = date('w', strtotime($first_day));

// Generate calendar data
$calendar_weeks = [];
$current_week = [];

// For all staff view, generate data for all employees
if ($view === 'all_staff' && $can_view_all) {
    $all_staff_attendance = [];
    $all_staff_summary = [];
    
    foreach ($all_employees as $emp) {
        $emp_id = $emp['id'];
        $joining_date = $emp['date_of_joining'];
        
        // Fetch attendance for this employee
        $emp_att = [];
        $sql = "SELECT date, clock_in_time, clock_out_time FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iss', $emp_id, $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) { $emp_att[$row['date']] = $row; }
        $stmt->close();
        
        // Fetch approved leaves for this employee
        $emp_leaves = [];
        $lsql = "SELECT start_date, end_date FROM leaves WHERE employee_id = ? AND status = 'Approved' AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (? BETWEEN start_date AND end_date) OR (? BETWEEN start_date AND end_date))";
        $lst = $conn->prepare($lsql);
        $lst->bind_param('issssss', $emp_id, $start, $end, $start, $end, $start, $end);
        $lst->execute();
        $lres = $lst->get_result();
        while($lr = $lres->fetch_assoc()) { $emp_leaves[] = $lr; }
        $lst->close();
        
        $emp_summary = ['present'=>0,'absent'=>0,'late_in'=>0,'leave'=>0,'not_joined'=>0];
        
        for ($d = 1; $d <= $days; $d++) {
            $date = date('Y-m-d', strtotime($start . ' +' . ($d-1) . ' day'));
            $isFuture = ($date > $today);
            $onLeave = is_approved_leave_for_date($emp_leaves, $date);
            $row = $emp_att[$date] ?? null;
            
            $hasJoined = !$joining_date || $date >= $joining_date;
            
            if (!$hasJoined) {
                $flag = 'Not Joined';
                $emp_summary['not_joined']++;
            } elseif ($onLeave) {
                $flag = 'Approved Leave';
                $emp_summary['leave']++;
            } elseif ($isFuture) {
                $flag = 'Future';
            } else {
                $flag = compute_attendance_status($date, $row, $config, $joining_date);
                if ($flag === 'Present') $emp_summary['present']++;
                elseif ($flag === 'Absent') $emp_summary['absent']++;
                elseif ($flag === 'Late In') $emp_summary['late_in']++;
            }
            
            $all_staff_attendance[$emp_id][$date] = [
                'row' => $row,
                'flag' => $flag,
                'future' => $isFuture,
                'leave' => $onLeave,
                'hasJoined' => $hasJoined
            ];
        }
        
        $all_staff_summary[$emp_id] = $emp_summary;
        // Add to overall summary
        foreach ($emp_summary as $key => $value) {
            $summary[$key] += $value;
        }
    }
} else {
    // Add empty cells for days before the month starts
    for ($i = 0; $i < $first_day_of_week; $i++) {
        $current_week[] = null;
    }
}

    // Add all days of the month
    for ($d = 1; $d <= $days; $d++) {
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

        $day_data = [
            'date' => $date,
            'day' => $d,
            'row' => $row,
            'flag' => $flag,
            'future' => $isFuture,
            'leave' => $onLeave,
            'hasJoined' => $hasJoined
        ];
        
        $current_week[] = $day_data;
        
        // If we have 7 days or it's the last day of month, complete the week
        if (count($current_week) == 7 || $d == $days) {
            // Fill remaining cells with null if needed
            while (count($current_week) < 7) {
                $current_week[] = null;
            }
            $calendar_weeks[] = $current_week;
            $current_week = [];
        }
    }
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
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
      <h2 class="text-xl sm:text-2xl font-semibold text-gray-700">Attendance - <?php echo date('F Y', strtotime($start)); ?></h2>
      
      <?php if ($can_view_all): ?>
        <!-- HR View Toggle Buttons -->
        <div class="flex flex-wrap gap-1 sm:inline-flex sm:rounded-md sm:shadow-sm" role="group">
          <a href="<?php echo url_for('my_attendance.php?view=personal&month='.e($month)); ?>" 
             class="px-2 sm:px-3 py-1 text-xs sm:text-sm border <?php echo $view==='personal'?'bg-indigo-600 text-white border-indigo-600':'bg-white text-gray-700 border-gray-300'; ?> rounded sm:rounded-l sm:rounded-r-none">
            Personal
          </a>
          <a href="<?php echo url_for('my_attendance.php?view=staff&month='.e($month)); ?>" 
             class="px-2 sm:px-3 py-1 text-xs sm:text-sm border <?php echo $view==='staff'?'bg-indigo-600 text-white border-indigo-600':'bg-white text-gray-700 border-gray-300'; ?> rounded sm:rounded-none">
            Staff Overview
          </a>
          <a href="<?php echo url_for('my_attendance.php?view=all_staff&month='.e($month)); ?>" 
             class="px-2 sm:px-3 py-1 text-xs sm:text-sm border <?php echo $view==='all_staff'?'bg-indigo-600 text-white border-indigo-600':'bg-white text-gray-700 border-gray-300'; ?> rounded sm:rounded-r sm:rounded-l-none">
            All Staff
          </a>
        </div>
      <?php endif; ?>
    </div>
    
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
      <!-- Month Navigation -->
      <div class="flex items-center gap-1 sm:gap-2">
        <?php 
          $prev_month = date('Y-m', strtotime($month . '-01 -1 month'));
          $next_month = date('Y-m', strtotime($month . '-01 +1 month'));
          $current_params = $_GET;
          $current_params['month'] = $prev_month;
          $prev_url = url_for('my_attendance.php?' . http_build_query($current_params));
          $current_params['month'] = $next_month;
          $next_url = url_for('my_attendance.php?' . http_build_query($current_params));
        ?>
        <a href="<?php echo $prev_url; ?>" 
           class="inline-flex items-center px-2 sm:px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </a>
        
        <!-- Month Selector -->
        <input type="month" class="enhanced-input text-sm" value="<?php echo e($month); ?>" 
               onchange="const u=new URL(window.location.href);u.searchParams.set('month',this.value);window.location=u.toString();" />
        
        <a href="<?php echo $next_url; ?>" 
           class="inline-flex items-center px-2 sm:px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </a>
        
        <!-- Current Month Button -->
        <?php if ($month !== date('Y-m')): ?>
          <?php 
            $current_params = $_GET;
            $current_params['month'] = date('Y-m');
            $today_url = url_for('my_attendance.php?' . http_build_query($current_params));
          ?>
          <a href="<?php echo $today_url; ?>" 
             class="inline-flex items-center px-2 sm:px-3 py-2 border border-indigo-300 shadow-sm text-xs sm:text-sm leading-4 font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Today
          </a>
        <?php endif; ?>
      </div>
      
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
                <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                  <img src="data:image/jpeg;base64,<?php echo e($att['in_photo_base64']); ?>" 
                       alt="Employee Selfie" class="w-full h-full object-cover" />
                </div>
              <?php else: ?>
                <div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center flex-shrink-0">
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

  <?php if ($view === 'all_staff' && $can_view_all): ?>
    <!-- All Staff Calendar View -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div class="p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-700">All Staff Attendance - <?php echo date('F Y', strtotime($start)); ?></h3>
      </div>
      
      <!-- All Staff Summary Cards -->
      <div class="p-6 bg-gray-50">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          <div class="bg-white p-4 rounded-lg shadow-sm">
            <div class="text-sm text-gray-500">Total Present</div>
            <div class="text-xl font-bold text-green-600"><?php echo (int)$summary['present']; ?></div>
          </div>
          <div class="bg-white p-4 rounded-lg shadow-sm">
            <div class="text-sm text-gray-500">Total Absent</div>
            <div class="text-xl font-bold text-red-600"><?php echo (int)$summary['absent']; ?></div>
          </div>
          <div class="bg-white p-4 rounded-lg shadow-sm">
            <div class="text-sm text-gray-500">Total Late</div>
            <div class="text-xl font-bold text-yellow-600"><?php echo (int)$summary['late_in']; ?></div>
          </div>
          <div class="bg-white p-4 rounded-lg shadow-sm">
            <div class="text-sm text-gray-500">On Leave</div>
            <div class="text-xl font-bold text-purple-600"><?php echo (int)$summary['leave']; ?></div>
          </div>
          <div class="bg-white p-4 rounded-lg shadow-sm">
            <div class="text-sm text-gray-500">Not Joined</div>
            <div class="text-xl font-bold text-gray-600"><?php echo (int)$summary['not_joined']; ?></div>
          </div>
        </div>
      </div>

      <!-- Staff List with Attendance -->
      <div class="divide-y">
        <?php foreach ($all_employees as $emp): ?>
          <?php 
            $emp_id = $emp['id'];
            $emp_present = $all_staff_summary[$emp_id]['present'] ?? 0;
            $emp_absent = $all_staff_summary[$emp_id]['absent'] ?? 0;
            $emp_late = $all_staff_summary[$emp_id]['late_in'] ?? 0;
            $emp_leave = $all_staff_summary[$emp_id]['leave'] ?? 0;
            $emp_not_joined = $all_staff_summary[$emp_id]['not_joined'] ?? 0;
            $total_working_days = $emp_present + $emp_absent + $emp_late;
            $attendance_percentage = $total_working_days > 0 ? round(($emp_present + $emp_late) / $total_working_days * 100, 1) : 0;
          ?>
          <div class="p-6 hover:bg-gray-50">
                         <div class="flex flex-col space-y-4">
               <!-- Employee Info -->
               <div class="flex items-center justify-between">
                 <div class="flex items-center space-x-3 sm:space-x-4">
                   <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                     <span class="text-white font-bold text-sm sm:text-lg">
                       <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                     </span>
                   </div>
                   <div>
                     <h4 class="font-semibold text-gray-900 text-sm sm:text-base"><?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></h4>
                     <p class="text-xs sm:text-sm text-gray-500">Joined: <?php echo date('d M Y', strtotime($emp['date_of_joining'])); ?></p>
                   </div>
                 </div>
                 
                 <div class="sm:hidden">
                   <a href="<?php echo url_for('my_attendance.php?view=staff&employee_id='.$emp_id.'&month='.e($month)); ?>" 
                      class="inline-flex items-center px-2 py-1 border border-gray-300 shadow-sm text-xs leading-4 font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                     View
                   </a>
                 </div>
               </div>

               <!-- Attendance Stats -->
               <div class="flex items-center justify-between">
                 <div class="grid grid-cols-5 gap-3 sm:gap-6 flex-1">
                   <div class="text-center">
                     <div class="text-lg sm:text-2xl font-bold text-green-600"><?php echo $emp_present; ?></div>
                     <div class="text-xs text-gray-500">Present</div>
                   </div>
                   <div class="text-center">
                     <div class="text-lg sm:text-2xl font-bold text-red-600"><?php echo $emp_absent; ?></div>
                     <div class="text-xs text-gray-500">Absent</div>
                   </div>
                   <div class="text-center">
                     <div class="text-lg sm:text-2xl font-bold text-yellow-600"><?php echo $emp_late; ?></div>
                     <div class="text-xs text-gray-500">Late</div>
                   </div>
                   <div class="text-center">
                     <div class="text-lg sm:text-2xl font-bold text-purple-600"><?php echo $emp_leave; ?></div>
                     <div class="text-xs text-gray-500">Leave</div>
                   </div>
                   <div class="text-center">
                     <div class="text-sm sm:text-lg font-bold <?php echo $attendance_percentage >= 80 ? 'text-green-600' : ($attendance_percentage >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                       <?php echo $attendance_percentage; ?>%
                     </div>
                     <div class="text-xs text-gray-500">Rate</div>
                   </div>
                 </div>
                 
                 <div class="hidden sm:block ml-4">
                   <a href="<?php echo url_for('my_attendance.php?view=staff&employee_id='.$emp_id.'&month='.e($month)); ?>" 
                      class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                     View Details
                   </a>
                 </div>
               </div>
             </div>

            <!-- Monthly Calendar Strip for this Employee -->
            <div class="mt-4">
              <div class="flex space-x-1 overflow-x-auto pb-2">
                <?php for ($d = 1; $d <= $days; $d++): ?>
                  <?php 
                    $date = date('Y-m-d', strtotime($start . ' +' . ($d-1) . ' day'));
                    $day_att = $all_staff_attendance[$emp_id][$date] ?? null;
                    if ($day_att) {
                      $flag = $day_att['flag'];
                      $future = $day_att['future'];
                      $onLeave = $day_att['leave'];
                      $hasJoined = $day_att['hasJoined'];
                    } else {
                      $flag = 'Future';
                      $future = true;
                      $onLeave = false;
                      $hasJoined = true;
                    }
                    
                    if (!$hasJoined) {
                      $bg_class = 'bg-gray-200';
                      $text_class = 'text-gray-400';
                    } elseif ($onLeave) {
                      $bg_class = 'bg-purple-200';
                      $text_class = 'text-purple-700';
                    } elseif ($flag === 'Present') {
                      $bg_class = 'bg-green-200';
                      $text_class = 'text-green-700';
                    } elseif ($flag === 'Absent') {
                      $bg_class = 'bg-red-200';
                      $text_class = 'text-red-700';
                    } elseif ($flag === 'Late In') {
                      $bg_class = 'bg-orange-200';
                      $text_class = 'text-orange-700';
                    } elseif ($future) {
                      $bg_class = 'bg-gray-100';
                      $text_class = 'text-gray-400';
                    } else {
                      $bg_class = 'bg-yellow-200';
                      $text_class = 'text-yellow-700';
                    }
                  ?>
                  <div class="flex-shrink-0 w-8 h-8 rounded <?php echo $bg_class; ?> flex items-center justify-center <?php echo $text_class; ?> text-xs font-medium"
                       title="<?php echo date('M d, Y', strtotime($date)) . ' - ' . $flag; ?>">
                    <?php echo $d; ?>
                  </div>
                <?php endfor; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>

  <!-- Calendar View -->
  <div class="bg-white rounded-lg shadow overflow-hidden">
    <!-- Calendar Header - Days of Week -->
    <div class="grid grid-cols-7 bg-gray-50 border-b">
      <?php $week_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; ?>
      <?php $week_days_short = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; ?>
      <?php for ($i = 0; $i < 7; $i++): ?>
        <div class="py-2 px-1 text-center text-sm font-semibold text-gray-700 border-r last:border-r-0">
          <span class="hidden sm:inline"><?php echo $week_days[$i]; ?></span>
          <span class="sm:hidden"><?php echo $week_days_short[$i]; ?></span>
        </div>
      <?php endfor; ?>
    </div>

    <!-- Calendar Body -->
    <div class="grid grid-cols-7">
      <?php foreach ($calendar_weeks as $week): ?>
        <?php foreach ($week as $day): ?>
          <div class="min-h-[80px] sm:min-h-[120px] border-r border-b last:border-r-0 
                      <?php echo $day ? 'bg-white hover:bg-gray-50' : 'bg-gray-100'; ?> 
                      transition-colors duration-200">
            <?php if ($day): ?>
              <?php 
                $row = $day['row']; 
                $flag = $day['flag']; 
                $future = $day['future']; 
                $onLeave = $day['leave'];
                $hasJoined = $day['hasJoined'];
                $is_today = $day['date'] === $today;
                
                // Determine styling based on status
                if (!$hasJoined) {
                  $status_color = 'text-gray-500';
                  $bg_color = 'bg-gray-100';
                } elseif ($onLeave) {
                  $status_color = 'text-purple-600';
                  $bg_color = 'bg-purple-100';
                } elseif ($flag === 'Present') {
                  $status_color = 'text-green-600';
                  $bg_color = 'bg-green-100';
                } elseif ($flag === 'Absent') {
                  $status_color = 'text-red-600';
                  $bg_color = 'bg-red-100';
                } elseif ($flag === 'Late In') {
                  $status_color = 'text-orange-600';
                  $bg_color = 'bg-orange-100';
                } elseif ($future) {
                  $status_color = 'text-gray-400';
                  $bg_color = 'bg-gray-50';
                } else {
                  $status_color = 'text-yellow-600';
                  $bg_color = 'bg-yellow-100';
                }
              ?>
              
              <div class="p-1 sm:p-2 h-full flex flex-col">
                <!-- Date Header -->
                <div class="flex justify-between items-center mb-1 sm:mb-2">
                  <span class="text-xs sm:text-sm font-semibold <?php echo $is_today ? 'bg-blue-600 text-white px-1 sm:px-2 py-1 rounded-full' : 'text-gray-900'; ?>">
                    <?php echo $day['day']; ?>
                  </span>
                  <?php if (!$future && $hasJoined && !$onLeave): ?>
                    <div class="w-2 h-2 sm:w-3 sm:h-3 rounded-full <?php echo $flag === 'Present' ? 'bg-green-500' : ($flag === 'Absent' ? 'bg-red-500' : ($flag === 'Late In' ? 'bg-orange-500' : 'bg-gray-400')); ?>"></div>
                  <?php endif; ?>
                </div>

                <!-- Status Badge -->
                <?php if (!$future): ?>
                  <div class="mb-1 sm:mb-2">
                    <span class="text-xs px-1 sm:px-2 py-1 rounded-full <?php echo $bg_color; ?> <?php echo $status_color; ?> font-medium leading-none">
                      <?php echo $flag === 'Not Joined' ? 'Not Joined' : ($onLeave ? 'Leave' : ($flag === 'Absent' ? 'Absent' : ($flag === 'Present' ? 'Present' : ($flag === 'Late In' ? 'Late' : $flag)))); ?>
                    </span>
                  </div>
                <?php endif; ?>

                <!-- Time Details -->
                <?php if ($hasJoined && !$future && !$onLeave && $row): ?>
                  <div class="text-xs text-gray-600 space-y-1 flex-1 hidden sm:block">
                    <?php if ($row['clock_in_time']): ?>
                      <div class="flex items-center">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-1"></span>
                        <span><?php echo format_time($row['clock_in_time']); ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if ($row['clock_out_time']): ?>
                      <div class="flex items-center">
                        <span class="w-2 h-2 bg-red-400 rounded-full mr-1"></span>
                        <span><?php echo format_time($row['clock_out_time']); ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if ($row['clock_in_time'] && $row['clock_out_time']): ?>
                      <div class="text-xs text-gray-500 mt-1">
                        <?php echo get_working_hours($row); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  
                  <!-- Mobile Time Display -->
                  <div class="sm:hidden">
                    <?php if ($row['clock_in_time'] && $row['clock_out_time']): ?>
                      <div class="text-xs text-gray-500 text-center">
                        <?php echo get_working_hours($row); ?>
                      </div>
                    <?php elseif ($row['clock_in_time']): ?>
                      <div class="text-xs text-green-600 text-center">
                        <?php echo date('H:i', strtotime($row['clock_in_time'])); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <!-- Visual Indicator for Special Cases -->
                <?php if ($flag === 'Absent' && $hasJoined && !$future && !$onLeave): ?>
                  <div class="flex justify-center mt-2">
                    <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                      <span class="text-white text-sm font-bold">✕</span>
                    </div>
                  </div>
                <?php elseif ($onLeave): ?>
                  <div class="flex justify-center mt-2">
                    <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                      <span class="text-white text-xs font-bold">L</span>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>