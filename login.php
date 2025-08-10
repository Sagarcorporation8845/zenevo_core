<?php
/*
--------------------------------------------------------------------------------
-- File: /login.php
-- Description: The main login page for all users.
--------------------------------------------------------------------------------
*/

// Include the database configuration. This also starts the session.
require_once 'config/db.php';

// If a user is already logged in, redirect them to the dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: ' . url_for('dashboard.php'));
    exit();
}

$error_message = '';
$success_message = '';
// Check if there's a login error message in the session.
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
if (isset($_SESSION['login_success'])) {
    $success_message = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HR & Finance Platform</title>
    <link rel="stylesheet" href="<?php echo url_for('assets/css/app.css'); ?>">
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen font-sans">

    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <!-- Company Logo -->
            <div class="flex justify-center mb-4">
                <img src="<?php echo url_for('assets/logo.svg'); ?>" alt="Company Inc." class="h-16 w-auto">
            </div>
            <h2 class="text-2xl font-bold text-gray-900">
                Portal Login
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Sign in to access your dashboard
            </p>
        </div>

        <!-- Display login messages -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
                <span class="block sm:inline"><?php echo e($success_message); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
                <span class="block sm:inline"><?php echo e($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form class="space-y-6" action="actions/auth_action.php" method="POST">
            <!-- This hidden input tells our action file what to do -->
            <input type="hidden" name="action" value="login">

            <div>
                <label for="email" class="text-sm font-medium text-gray-700">Email address</label>
                <div class="mt-1">
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="w-full enhanced-input">
                </div>
            </div>

            <div>
                <label for="password" class="text-sm font-medium text-gray-700">Password</label>
                <div class="mt-1">
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="w-full enhanced-input">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Sign in
                </button>
            </div>
                    <div class="flex items-center justify-between">
                <a href="<?php echo url_for('forgot_password.php'); ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">Forgot password?</a>
            </div>
        </form>
    </div>
 
 </body>
 </html>