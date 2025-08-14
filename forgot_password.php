<?php
require_once 'config/db.php';

$status_message = $_SESSION['status_message'] ?? '';
$status_error = $_SESSION['status_error'] ?? '';
$reset_email = $_SESSION['reset_email'] ?? '';
unset($_SESSION['status_message'], $_SESSION['status_error'], $_SESSION['reset_email']);

// Check if user is blocked
$block_info = null;
if ($reset_email) {
    $sql = "SELECT blocked_until, block_level, attempts, max_attempts 
            FROM password_reset_otp 
            WHERE email = ? AND blocked_until > NOW() 
            ORDER BY created_at DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $reset_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $block_info = $result->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="<?php echo url_for('assets/css/app.css'); ?>">
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen font-sans">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <div class="flex justify-center mb-4">
                <img src="<?php echo url_for('assets/logo.svg'); ?>" alt="Company Inc." class="h-16 w-auto">
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Forgot your password?</h2>
            <p class="mt-2 text-sm text-gray-600">
                <?php if ($reset_email): ?>
                    Enter the OTP sent to your email to reset your password.
                <?php else: ?>
                    Enter your email and we will send you an OTP.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($status_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg" role="alert">
                <span class="block sm:inline"><?php echo e($status_message); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($status_error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg" role="alert">
                <span class="block sm:inline"><?php echo e($status_error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($block_info): ?>
            <?php 
            $remaining = strtotime($block_info['blocked_until']) - time();
            $time_remaining = '';
            if ($remaining < 60) {
                $time_remaining = "$remaining seconds";
            } elseif ($remaining < 3600) {
                $minutes = floor($remaining / 60);
                $time_remaining = "$minutes minutes";
            } else {
                $hours = floor($remaining / 3600);
                $minutes = floor(($remaining % 3600) / 60);
                $time_remaining = "$hours hours $minutes minutes";
            }
            ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg" role="alert">
                <span class="block sm:inline">
                    <strong>Account temporarily blocked:</strong> Too many failed attempts. 
                    Please try again in <?php echo $time_remaining; ?>.
                </span>
            </div>
        <?php endif; ?>

        <?php if (!$reset_email): ?>
            <!-- Email Request Form -->
            <form class="space-y-6" action="actions/password_reset_action.php" method="POST">
                <input type="hidden" name="action" value="request_otp">
                <div>
                    <label for="email" class="text-sm font-medium text-gray-700">Email address</label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" autocomplete="email" required 
                               class="w-full enhanced-input" <?php echo $block_info ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed" 
                            <?php echo $block_info ? 'disabled' : ''; ?>>
                        Send OTP
                    </button>
                </div>
            </form>
        <?php else: ?>
            <!-- OTP Verification Form -->
            <form class="space-y-6" action="actions/password_reset_action.php" method="POST">
                <input type="hidden" name="action" value="verify_otp">
                <input type="hidden" name="email" value="<?php echo e($reset_email); ?>">
                
                <div>
                    <label for="otp" class="text-sm font-medium text-gray-700">Enter OTP</label>
                    <div class="mt-1">
                        <input id="otp" name="otp" type="text" maxlength="6" pattern="[0-9]{6}" 
                               placeholder="123456" required class="w-full enhanced-input text-center text-lg tracking-widest"
                               <?php echo $block_info ? 'disabled' : ''; ?>>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Enter the 6-digit code sent to <?php echo e($reset_email); ?></p>
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                            <?php echo $block_info ? 'disabled' : ''; ?>>
                        Verify OTP
                    </button>
                    <button type="button" onclick="window.location.href='<?php echo url_for('forgot_password.php'); ?>'" 
                            class="flex-1 flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Back
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <div class="text-center">
            <a href="<?php echo url_for('login.php'); ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">Back to login</a>
        </div>
    </div>

    <script>
        // Auto-focus OTP input and format
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp');
            if (otpInput) {
                otpInput.focus();
                
                // Only allow numbers
                otpInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
    </script>
</body>
</html>