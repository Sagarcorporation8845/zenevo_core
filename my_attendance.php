<?php
$pageTitle = 'My Attendance';
include 'includes/header.php';

// Must be logged in
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) { header('Location: ' . url_for('login.php')); exit; }

// Find employee id for current user
$employee_id = null;
if ($stmt = $conn->prepare('SELECT id FROM employees WHERE user_id = ?')) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($employee_id);
    $stmt->fetch();
    $stmt->close();
}
if (!$employee_id) {
    echo '<div class="p-6 bg-white rounded-lg shadow">Employee profile not found.</div>';
    include 'includes/footer.php';
    exit;
}

$month = $_GET['month'] ?? date('Y-m');
// Ensure config exists
$conn->query("CREATE TABLE IF NOT EXISTS attendance_config (id INT PRIMARY KEY DEFAULT 1, office_lat DECIMAL(10,7) NOT NULL DEFAULT 0, office_lng DECIMAL(10,7) NOT NULL DEFAULT 0, radius_meters INT NOT NULL DEFAULT 50, in_start TIME NOT NULL DEFAULT '09:30:00', in_end TIME NOT NULL DEFAULT '09:45:00', out_start TIME NOT NULL DEFAULT '17:30:00', out_end TIME NOT NULL DEFAULT '17:45:00', updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$conn->query("INSERT IGNORE INTO attendance_config (id) VALUES (1)");
$config = $conn->query('SELECT * FROM attendance_config WHERE id=1')->fetch_assoc();

// Fetch attendance for month
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));

$att = [];
$sql = "SELECT date, clock_in_time, clock_out_time FROM attendance WHERE employee_id=? AND date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $employee_id, $start, $end);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) { $att[$row['date']] = $row; }
$stmt->close();

function compute_flag($date, $row, $config) {
    if (!$row || (!$row['clock_in_time'] && !$row['clock_out_time'])) return 'Absent';
    if (!$row['clock_in_time'] || !$row['clock_out_time']) return 'Miss Punch';
    $in = strtotime($row['clock_in_time']);
    $out = strtotime($row['clock_out_time']);
    $workHours = ($out - $in) / 3600.0;
    $late = $config['in_end'] ? (strtotime($date . ' ' . $config['in_end']) < $in) : false;
    $early = $config['out_end'] ? ($out < strtotime($date . ' ' . $config['out_end'])) : false;
    if ($workHours < 4) return 'Half Day';
    if ($late || $early) return 'Late/Early';
    return 'Present';
}

$days = (int)date('t', strtotime($start));
$summary = ['present'=>0,'absent'=>0,'flagged'=>0];
$cards = [];
for ($d=1; $d <= $days; $d++) {
    $date = date('Y-m-d', strtotime($start . ' +' . ($d-1) . ' day'));
    $row = $att[$date] ?? null;
    $flag = compute_flag($date, $row, $config);
    if ($flag === 'Present') $summary['present']++; elseif ($flag==='Absent') $summary['absent']++; else $summary['flagged']++;
    $cards[] = ['date'=>$date,'row'=>$row,'flag'=>$flag];
}
?>
<div class="container mx-auto">
  <div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-700">My Attendance</h2>
    <input type="month" class="enhanced-input" value="<?php echo e($month); ?>" onchange="window.location='?month='+this.value" />
  </div>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white p-5 rounded shadow"><div class="text-sm text-gray-500">Present</div><div class="text-2xl font-bold text-green-600"><?php echo (int)$summary['present']; ?></div></div>
    <div class="bg-white p-5 rounded shadow"><div class="text-sm text-gray-500">Absent</div><div class="text-2xl font-bold text-red-600"><?php echo (int)$summary['absent']; ?></div></div>
    <div class="bg-white p-5 rounded shadow"><div class="text-sm text-gray-500">Flagged</div><div class="text-2xl font-bold text-yellow-600"><?php echo (int)$summary['flagged']; ?></div></div>
    <div class="bg-white p-5 rounded shadow"><div class="text-sm text-gray-500">Total Days</div><div class="text-2xl font-bold"><?php echo $days; ?></div></div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <?php foreach ($cards as $c): $row=$c['row']; $flag=$c['flag'];
      $bg = $flag==='Present' ? 'border-green-500' : ($flag==='Absent' ? 'border-red-500' : 'border-yellow-400');
    ?>
      <div class="bg-white rounded-lg shadow border-t-4 <?php echo $bg; ?>">
        <div class="p-5">
          <div class="flex justify-between items-center mb-3">
            <div class="text-sm font-semibold <?php echo $flag==='Present'?'text-green-600':($flag==='Absent'?'text-red-600':'text-yellow-600'); ?>"><?php echo e($flag); ?></div>
            <div class="text-xs text-gray-500"><?php echo date('d M Y', strtotime($c['date'])); ?></div>
          </div>
          <div class="text-sm text-gray-800 space-y-1">
            <div>In: <?php echo $row && $row['clock_in_time'] ? date('h:i A', strtotime($row['clock_in_time'])) : '—'; ?></div>
            <div>Out: <?php echo $row && $row['clock_out_time'] ? date('h:i A', strtotime($row['clock_out_time'])) : '—'; ?></div>
            <div>
              <?php if ($row && $row['clock_in_time'] && $row['clock_out_time']):
                $h = (strtotime($row['clock_out_time']) - strtotime($row['clock_in_time']))/3600.0;
                echo 'Total: ' . number_format($h,1) . ' hrs';
              else: echo 'Total: —'; endif; ?>
            </div>
          </div>
          <?php if ($flag !== 'Present'): ?>
          <form action="actions/attendance_ticket_action.php" method="POST" class="mt-3">
            <input type="hidden" name="action" value="create" />
            <input type="hidden" name="date" value="<?php echo e($c['date']); ?>" />
            <input type="hidden" name="flag" value="<?php echo e($flag==='Late/Early'?'Late Entry':$flag); ?>" />
            <input name="reason" class="enhanced-input w-full mb-2" placeholder="Reason (optional)" />
            <button class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">Create Ticket</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>