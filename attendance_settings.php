<?php
$pageTitle = 'Attendance Settings';
include 'includes/header.php';

if (!has_permission($conn, 'manage_employees')) {
    echo '<div class="p-6 bg-white rounded-lg shadow-md">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Ensure schema exists
require_once __DIR__ . '/api/helpers.php';
ensure_schema($conn);
$config = get_config($conn);

$flash = '';
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
?>
<div class="container mx-auto max-w-3xl">
    <?php if ($flash): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $flash['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>
    <form action="actions/attendance_action.php" method="POST" class="bg-white p-8 rounded-lg shadow-md">
        <input type="hidden" name="action" value="save_config">
        <h2 class="text-2xl font-bold mb-6">Office Geofence & Time Windows</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-group">
                <label for="office_lat">Office Latitude</label>
                <input type="number" step="0.0000001" name="office_lat" id="office_lat" class="w-full enhanced-input" value="<?php echo e($config['office_lat']); ?>" required>
            </div>
            <div class="form-group">
                <label for="office_lng">Office Longitude</label>
                <input type="number" step="0.0000001" name="office_lng" id="office_lng" class="w-full enhanced-input" value="<?php echo e($config['office_lng']); ?>" required>
            </div>
            <div class="form-group">
                <label for="radius_meters">Radius (meters)</label>
                <input type="number" name="radius_meters" id="radius_meters" class="w-full enhanced-input" value="<?php echo e($config['radius_meters']); ?>" required>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
            <div class="form-group">
                <label for="in_start">Check-in Start</label>
                <input type="time" name="in_start" id="in_start" class="w-full enhanced-input" value="<?php echo e($config['in_start']); ?>" required>
            </div>
            <div class="form-group">
                <label for="in_end">Check-in End</label>
                <input type="time" name="in_end" id="in_end" class="w-full enhanced-input" value="<?php echo e($config['in_end']); ?>" required>
            </div>
            <div class="form-group">
                <label for="out_start">Check-out Start</label>
                <input type="time" name="out_start" id="out_start" class="w-full enhanced-input" value="<?php echo e($config['out_start']); ?>" required>
            </div>
            <div class="form-group">
                <label for="out_end">Check-out End</label>
                <input type="time" name="out_end" id="out_end" class="w-full enhanced-input" value="<?php echo e($config['out_end']); ?>" required>
            </div>
        </div>
        <div class="text-right mt-8">
            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Save Settings</button>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>