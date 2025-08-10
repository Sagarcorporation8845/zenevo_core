<?php
require_once '../config/db.php';
require_once '../includes/mailer.php';

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=MyISAM DEFAULT CHARSET=latin1");

$action = $_POST['action'] ?? '';

function absolute_url(string $pathWithLeadingSlash): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $pathWithLeadingSlash;
}

if ($action === 'request') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $_SESSION['status_error'] = 'Please enter your email address.';
        header('Location: ' . url_for('forgot_password.php'));
        exit();
    }

    $stmt = $conn->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
        $stmt = $conn->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $user['id'], $token, $expires);
        $stmt->execute();
        $stmt->close();

        $resetLink = absolute_url(url_for('reset_password.php') . '?token=' . urlencode($token));
        $subject = 'Password Reset Request';
        $body = '<p>Hello ' . e($user['name']) . ',</p>' .
                '<p>We received a request to reset your password. Click the link below to set a new password. This link is valid for 1 hour and can be used only once.</p>' .
                '<p><a href="' . $resetLink . '" target="_blank">Reset your password</a></p>' .
                '<p>If you did not request this, you can ignore this email.</p>' .
                '<p>Regards,<br>Zenevo Support</p>';
        send_mail_html($user['email'], $subject, $body);
    }

    // Always show a generic message
    $_SESSION['status_message'] = 'If the email exists in our system, a reset link has been sent.';
    header('Location: ' . url_for('forgot_password.php'));
    exit();
}

if ($action === 'reset') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$token || !$password || !$confirm) {
        $_SESSION['status_error'] = 'All fields are required.';
        header('Location: ' . url_for('reset_password.php') . '?token=' . urlencode($token));
        exit();
    }
    if ($password !== $confirm) {
        $_SESSION['status_error'] = 'Passwords do not match.';
        header('Location: ' . url_for('reset_password.php') . '?token=' . urlencode($token));
        exit();
    }

    $stmt = $conn->prepare('SELECT id, user_id FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reset) {
        $_SESSION['status_error'] = 'Invalid or expired reset link.';
        header('Location: ' . url_for('reset_password.php') . '?token=' . urlencode($token));
        exit();
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->bind_param('si', $hashed, $reset['user_id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('UPDATE password_resets SET used = 1, used_at = NOW() WHERE id = ?');
    $stmt->bind_param('i', $reset['id']);
    $stmt->execute();
    $stmt->close();

    $_SESSION['login_success'] = 'Your password has been reset. You can now log in.';
    header('Location: ' . url_for('login.php'));
    exit();
}

header('Location: ' . url_for('login.php'));
exit();