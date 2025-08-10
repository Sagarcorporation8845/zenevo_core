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

// Toggle: personal vs all employees (only for HR/Admin)
$can_view_all = check_role_access($conn, ['Admin','HR Manager']);
$view = isset($_GET['view']) && $can_view_all && $_GET['view'] === 'all' ? 'all' : 'mine';

// Selected employee when viewing all
$selected_employee_id = $current_employee_id;
if ($view === 'all') {
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

// Ensure config exists
$conn->query("CREATE TABLE IF NOT EXISTS attendance_config (id INT PRIMARY KEY DEFAULT 1, office_lat DECIMAL(10,7) NOT NULL DEFAULT 0, office_lng DECIMAL(10,7) NOT NULL DEFAULT 0, radius_meters INT NOT NULL DEFAULT 50, in_start TIME NOT NULL DEFAULT '09:30:00', in_end TIME NOT NULL DEFAULT '09:45:00', out_start TIME NOT NULL DEFAULT '17:30:00', out_end TIME NOT NULL DEFAULT '17:45:00', updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$conn->query("INSERT IGNORE INTO attendance_config (id) VALUES (1)");
$config = $conn->query('SELECT * FROM attendance_config WHERE id=1')->fetch_assoc();

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

function compute_flag(string $date, ?array $row, array $config): string {
    if (!$row || (!$row['clock_in_time'] && !$row['clock_out_time'])) return 'Absent';
    if (!$row['clock_in_time'] || !$row['clock_out_time']) return 'Miss Punch';
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

$days = (int)date('t', strtotime($start));
$today = date('Y-m-d');
$summary = ['present'=>0,'absent'=>0,'flagged'=>0,'leave'=>0];
$cards = [];
for ($d=1; $d <= $days; $d++) {
    $date = date('Y-m-d', strtotime($start . ' +' . ($d-1) . ' day'));
    $isFuture = ($date > $today);
    $onLeave = is_approved_leave_for_date($approved_leaves, $date);
    $row = $att[$date] ?? null;
    $flag = $onLeave ? 'Approved Leave' : ($isFuture ? 'Future' : compute_flag($date, $row, $config));

    if ($flag === 'Present') $summary['present']++;
    elseif ($flag === 'Approved Leave') $summary['leave']++;
    elseif ($flag === 'Absent') $summary['absent']++;
    elseif ($flag !== 'Future') $summary['flagged']++;

    $cards[] = ['date'=>$date,'row'=>$row,'flag'=>$flag,'future'=>$isFuture,'leave'=>$onLeave];
}
?>
<div class="container mx-auto">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
      <h2 class="text-2xl font-semibold text-gray-700">Attendance - <?php echo date('F Y', strtotime($start)); ?></h2>
      <?php if ($can_view_all): ?>
        <div class="ml-2 inline-flex rounded-md shadow-sm" role="group">
          <a href="<?php echo url_for('my_attendance.php?view=mine&month='.e($month)); ?>" class="px-3 py-1 text-sm border <?php echo $view==='mine'?'bg-indigo-600 text-white border-indigo-600':'bg-white text-gray-700 border-gray-300'; ?> rounded-l">My</a>
          <a href="<?php echo url_for('my_attendance.php?view=all&month='.e($month)); ?>" class="px-3 py-1 text-sm border <?php echo $view==='all'?'bg-indigo-600 text-white border-indigo-600':'bg-white text-gray-700 border-gray-300'; ?> rounded-r">All Employees</a>
        </div>
      <?php endif; ?>
    </div>
    <div class="flex items-center gap-3">
      <input type="month" class="enhanced-input" value="<?php echo e($month); ?>" onchange="const u=new URL(window.location.href);u.searchParams.set('month',this.value);window.location=u.toString();" />
      <?php if ($view==='all' && $can_view_all): ?>
        <?php $emps = $conn->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM employees ORDER BY name ASC"); ?>
        <form method="GET" class="flex items-center gap-2">
          <input type="hidden" name="view" value="all" />
          <input type="hidden" name="month" value="<?php echo e($month); ?>" />
          <select name="employee_id" class="enhanced-input">
            <?php while($e = $emps->fetch_assoc()): ?>
              <option value="<?php echo (int)$e['id']; ?>" <?php echo (int)$e['id']===$selected_employee_id?'selected':''; ?>><?php echo e($e['name']); ?></option>
            <?php endwhile; ?>
          </select>
          <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded">Load</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white p-5 rounded shadow"><div class="text-sm text-gray-500">Present</div><div class="text-2xl font-bold text-green-600"><?php echo (int)$summary['present']; ?></div></div>
    <div class="bg-white p-5 rounded shadow"><div class="text-sm text-gray-500">Absent</div><div class="text-2xl font-bold text-red-600"><?php echo (int)$summary['absent']; ?></div></div>
    <div class="bg-white p-5 rounded shadow"><div class="text-sm text-gray-500">Flagged</div><div class="text-2xl font-bold text-yellow-600"><?php echo (int)$summary['flagged']; ?></div></div>
    <div class="bg-white p-5 rounded shadow"><div class="text-sm text-gray-500">Approved Leave</div><div class="text-2xl font-bold text-purple-600"><?php echo (int)$summary['leave']; ?></div></div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <?php foreach ($cards as $c): $row=$c['row']; $flag=$c['flag']; $future=$c['future']; $onLeave=$c['leave'];
      $border = $onLeave ? 'border-purple-500' : ($flag==='Present' ? 'border-green-500' : ($flag==='Absent' ? 'border-red-500' : ($future ? 'border-gray-300' : 'border-yellow-400')));
      $statusColor = $onLeave ? 'text-purple-600' : ($flag==='Present'?'text-green-600':($flag==='Absent'?'text-red-600':($future?'text-gray-500':'text-yellow-600')));
      $dateChipBg = $onLeave ? 'bg-purple-600' : ($flag==='Present' ? 'bg-green-600' : ($flag==='Absent' ? 'bg-red-600' : ($future ? 'bg-gray-400' : 'bg-yellow-500')));
    ?>
      <div class="bg-white rounded-lg shadow border-t-4 <?php echo $border; ?> overflow-hidden">
        <div class="p-5">
          <div class="mb-3">
            <?php if ($future): ?>
              <div class="flex items-center justify-center h-32 bg-gray-50 rounded-lg">
                <div class="text-xl font-semibold text-gray-500"><?php echo date('d M', strtotime($c['date'])); ?></div>
              </div>
            <?php elseif ($onLeave): ?>
              <div class="flex items-center justify-center h-32 bg-purple-50 rounded-lg">
                <div class="text-lg font-semibold text-purple-600">Approved Leave</div>
              </div>
            <?php elseif ($flag === 'Absent'): ?>
              <div class="flex items-center justify-center h-32 bg-red-50 rounded-lg">
                <?php if (file_exists(__DIR__ . '/assets/cross.png')): ?>
                  <img src="<?php echo url_for('assets/cross.png'); ?>" alt="Absent" class="h-16 w-16 object-contain" />
                <?php else: ?>
                  <svg class="h-16 w-16 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" class="text-red-200" fill="currentColor" opacity="0.2"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <?php $photo = $row && $row['in_photo_base64'] ? $row['in_photo_base64'] : ($row['out_photo_base64'] ?? ''); ?>
              <?php if ($photo): ?>
                <img src="data:image/jpeg;base64,<?php echo e($photo); ?>" alt="Selfie" class="w-full h-32 object-cover rounded-lg" />
              <?php else: ?>
                <div class="flex items-center justify-center h-32 bg-gray-50 rounded-lg text-gray-400">No Photo</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="flex justify-between items-center mb-3">
            <div class="text-sm font-semibold <?php echo $statusColor; ?>"><?php echo e($flag === 'Future' ? '' : $flag); ?></div>
            <div class="text-xs text-gray-500"><?php echo date('d M Y', strtotime($c['date'])); ?></div>
          </div>

          <?php if (!$future && !$onLeave): ?>
          <div class="text-sm text-gray-800 grid grid-cols-2 gap-x-4 gap-y-1">
            <div>In: <?php echo $row && $row['clock_in_time'] ? date('h:i A', strtotime($row['clock_in_time'])) : '—'; ?></div>
            <div>Out: <?php echo $row && $row['clock_out_time'] ? date('h:i A', strtotime($row['clock_out_time'])) : '—'; ?></div>
            <div class="col-span-2">
              <?php if ($row && $row['clock_in_time'] && $row['clock_out_time']):
                $h = (strtotime($row['clock_out_time']) - strtotime($row['clock_in_time']))/3600.0;
                echo 'Total: ' . number_format($h,1) . ' hrs';
              else: echo 'Total: —'; endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php 
            $ticketable = in_array($flag, ['Miss Punch','Late In','Early Out','Half Day'], true);
          ?>
          <?php if ($ticketable): ?>
            <div class="mt-3 flex items-center justify-between">
              <button type="button" class="text-yellow-700 hover:text-yellow-800 text-sm inline-flex items-center" onclick="this.nextElementSibling.classList.toggle('hidden')" title="Create ticket">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-yellow-100 font-bold">i</span>
              </button>
              <span class="text-xs px-2 py-1 rounded text-white <?php echo $dateChipBg; ?>"><?php echo date('d M Y', strtotime($c['date'])); ?></span>
            </div>
            <form action="actions/attendance_ticket_action.php" method="POST" class="mt-2 hidden">
              <input type="hidden" name="action" value="create" />
              <input type="hidden" name="date" value="<?php echo e($c['date']); ?>" />
              <?php $ticketFlag = ($flag === 'Late In') ? 'Late Entry' : $flag; ?>
              <input type="hidden" name="flag" value="<?php echo e($ticketFlag); ?>" />
              <input name="reason" class="enhanced-input w-full mb-2" placeholder="Reason (optional)" />
              <button class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">Create Ticket</button>
            </form>
          <?php else: ?>
            <div class="mt-3 flex justify-end">
              <span class="text-xs px-2 py-1 rounded text-white <?php echo $dateChipBg; ?>"><?php echo date('d M Y', strtotime($c['date'])); ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>