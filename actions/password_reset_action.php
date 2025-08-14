<?php
require_once '../config/db.php';
require_once '../includes/mailer.php';

// Create OTP password reset table
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    blocked_until DATETIME NULL,
    block_level INT DEFAULT 0, -- 0: none, 1: 5min, 2: 1hour, 3: 1day
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_POST['action'] ?? '';

function generate_otp(): string {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function get_block_duration($level): int {
    switch ($level) {
        case 1: return 5 * 60; // 5 minutes
        case 2: return 60 * 60; // 1 hour
        case 3: return 24 * 60 * 60; // 1 day
        default: return 0;
    }
}

function is_user_blocked($conn, $email): array {
    $sql = "SELECT blocked_until, block_level, attempts, max_attempts 
            FROM password_reset_otp 
            WHERE email = ? AND blocked_until > NOW() 
            ORDER BY created_at DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $block_info = $result->fetch_assoc();
    $stmt->close();
    
    if ($block_info) {
        $remaining = strtotime($block_info['blocked_until']) - time();
        return [
            'blocked' => true,
            'remaining_seconds' => max(0, $remaining),
            'block_level' => $block_info['block_level'],
            'attempts' => $block_info['attempts'],
            'max_attempts' => $block_info['max_attempts']
        ];
    }
    
    return ['blocked' => false];
}

function format_time_remaining($seconds): string {
    if ($seconds < 60) {
        return "$seconds seconds";
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return "$minutes minutes";
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return "$hours hours $minutes minutes";
    }
}

if ($action === 'request_otp') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $_SESSION['status_error'] = 'Please enter your email address.';
        header('Location: ' . url_for('forgot_password.php'));
        exit();
    }

    // Check if user is blocked
    $block_info = is_user_blocked($conn, $email);
    if ($block_info['blocked']) {
        $time_remaining = format_time_remaining($block_info['remaining_seconds']);
        $_SESSION['status_error'] = "Too many failed attempts. Please try again in $time_remaining.";
        header('Location: ' . url_for('forgot_password.php'));
        exit();
    }

    // Check if user exists
    $stmt = $conn->prepare('SELECT id, name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Generate OTP
        $otp = generate_otp();
        $expires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
        
        // Insert OTP record
        $stmt = $conn->prepare('INSERT INTO password_reset_otp (user_id, email, otp, expires_at) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isss', $user['id'], $email, $otp, $expires);
        $stmt->execute();
        $stmt->close();

        // Send OTP email
        $subject = 'Password Reset OTP';
        $body = '<p>Hello ' . e($user['name']) . ',</p>' .
                '<p>Your password reset OTP is: <strong>' . $otp . '</strong></p>' .
                '<p>This OTP is valid for 10 minutes and can be used only once.</p>' .
                '<p>If you did not request this, you can ignore this email.</p>' .
                '<p>Regards,<br>Zenevo Support</p>';
        
        $mail_sent = send_mail_html($user['email'], $subject, $body);
        
        if ($mail_sent) {
            $_SESSION['status_message'] = 'OTP has been sent to your email address.';
            $_SESSION['reset_email'] = $email; // Store for OTP verification
        } else {
            $_SESSION['status_error'] = 'Failed to send OTP. Please try again.';
        }
    } else {
        // Don't reveal if email exists or not for security
        $_SESSION['status_message'] = 'If the email exists in our system, an OTP has been sent.';
    }

    header('Location: ' . url_for('forgot_password.php'));
    exit();
}

if ($action === 'verify_otp') {
    $email = trim($_POST['email'] ?? '');
    $otp = trim($_POST['otp'] ?? '');
    
    if (!$email || !$otp) {
        $_SESSION['status_error'] = 'Email and OTP are required.';
        header('Location: ' . url_for('forgot_password.php'));
        exit();
    }

    // Check if user is blocked
    $block_info = is_user_blocked($conn, $email);
    if ($block_info['blocked']) {
        $time_remaining = format_time_remaining($block_info['remaining_seconds']);
        $_SESSION['status_error'] = "Too many failed attempts. Please try again in $time_remaining.";
        header('Location: ' . url_for('forgot_password.php'));
        exit();
    }

    // Get latest OTP record
    $sql = "SELECT id, user_id, otp, attempts, max_attempts, block_level, expires_at, used 
            FROM password_reset_otp 
            WHERE email = ? AND used = 0 AND expires_at > NOW() 
            ORDER BY created_at DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $otp_record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$otp_record) {
        $_SESSION['status_error'] = 'Invalid or expired OTP. Please request a new one.';
        header('Location: ' . url_for('forgot_password.php'));
        exit();
    }

    // Check if OTP matches
    if ($otp_record['otp'] === $otp) {
        // OTP is correct - mark as used and allow password reset
        $stmt = $conn->prepare('UPDATE password_reset_otp SET used = 1, used_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $otp_record['id']);
        $stmt->execute();
        $stmt->close();

        $_SESSION['reset_user_id'] = $otp_record['user_id'];
        $_SESSION['status_message'] = 'OTP verified successfully. Please set your new password.';
        header('Location: ' . url_for('reset_password.php'));
        exit();
    } else {
        // OTP is incorrect - increment attempts
        $new_attempts = $otp_record['attempts'] + 1;
        $max_attempts = $otp_record['max_attempts'];
        $block_level = $otp_record['block_level'];
        
        if ($new_attempts >= $max_attempts) {
            // Block user
            $block_level++;
            $block_duration = get_block_duration($block_level);
            $blocked_until = date('Y-m-d H:i:s', time() + $block_duration);
            
            $stmt = $conn->prepare('UPDATE password_reset_otp SET attempts = ?, blocked_until = ?, block_level = ? WHERE id = ?');
            $stmt->bind_param('isi', $new_attempts, $blocked_until, $block_level, $otp_record['id']);
            $stmt->execute();
            $stmt->close();
            
            $time_remaining = format_time_remaining($block_duration);
            $_SESSION['status_error'] = "Too many failed attempts. Please try again in $time_remaining.";
        } else {
            // Just increment attempts
            $stmt = $conn->prepare('UPDATE password_reset_otp SET attempts = ? WHERE id = ?');
            $stmt->bind_param('ii', $new_attempts, $otp_record['id']);
            $stmt->execute();
            $stmt->close();
            
            $remaining_attempts = $max_attempts - $new_attempts;
            $_SESSION['status_error'] = "Incorrect OTP. You have $remaining_attempts attempts remaining.";
        }
        
        header('Location: ' . url_for('forgot_password.php'));
        exit();
    }
}

if ($action === 'reset_password') {
    $user_id = $_SESSION['reset_user_id'] ?? 0;
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$user_id) {
        $_SESSION['status_error'] = 'Invalid reset session. Please start over.';
        header('Location: ' . url_for('forgot_password.php'));
        exit();
    }

    if (!$password || !$confirm) {
        $_SESSION['status_error'] = 'All fields are required.';
        header('Location: ' . url_for('reset_password.php'));
        exit();
    }

    if ($password !== $confirm) {
        $_SESSION['status_error'] = 'Passwords do not match.';
        header('Location: ' . url_for('reset_password.php'));
        exit();
    }

    if (strlen($password) < 8) {
        $_SESSION['status_error'] = 'Password must be at least 8 characters long.';
        header('Location: ' . url_for('reset_password.php'));
        exit();
    }

    // Update password
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->bind_param('si', $hashed, $user_id);
    $stmt->execute();
    $stmt->close();

    // Clear reset session
    unset($_SESSION['reset_user_id']);

    $_SESSION['login_success'] = 'Your password has been reset successfully. You can now log in.';
    header('Location: ' . url_for('login.php'));
    exit();
}

header('Location: ' . url_for('login.php'));
exit();