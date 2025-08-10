<?php
require_once 'config/db.php';
$token = $_GET['token'] ?? '';
$status_error = $_SESSION['status_error'] ?? '';
unset($_SESSION['status_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="<?php echo url_for('assets/css/app.css'); ?>">
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen font-sans">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <div class="flex justify-center mb-4">
                <img src="<?php echo url_for('assets/logo.svg'); ?>" alt="Company Inc." class="h-16 w-auto">
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Set a new password</h2>
            <p class="mt-2 text-sm text-gray-600">Choose a strong password for your account.</p>
        </div>

        <?php if ($status_error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg" role="alert">
                <span class="block sm:inline"><?php echo e($status_error); ?></span>
            </div>
        <?php endif; ?>

        <form class="space-y-6" action="actions/password_reset_action.php" method="POST">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="token" value="<?php echo e($token); ?>">
            <div>
                <label for="password" class="text-sm font-medium text-gray-700">New password</label>
                <div class="mt-1">
                    <input id="password" name="password" type="password" required class="w-full enhanced-input">
                </div>
            </div>
            <div>
                <label for="confirm_password" class="text-sm font-medium text-gray-700">Confirm new password</label>
                <div class="mt-1">
                    <input id="confirm_password" name="confirm_password" type="password" required class="w-full enhanced-input">
                </div>
            </div>
            <div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Reset password</button>
            </div>
        </form>
    </div>
</body>
</html>