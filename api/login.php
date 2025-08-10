<?php
require_once __DIR__ . '/helpers.php';
ensure_schema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST required'], 405);
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
if (!$email || !$password) {
    json_response(['success' => false, 'error' => 'Email and password are required'], 400);
}

$stmt = $conn->prepare("SELECT id, name, email, password, role_id, is_active FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password'])) {
    json_response(['success' => false, 'error' => 'Invalid credentials'], 401);
}
if (!$user['is_active']) {
    json_response(['success' => false, 'error' => 'Account deactivated'], 403);
}

// Create token valid for 7 days
$token = generate_token();
$expires = (new DateTime('+7 days'))->format('Y-m-d H:i:s');
$stmt = $conn->prepare("INSERT INTO auth_tokens (token, user_id, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param('sis', $token, $user['id'], $expires);
$stmt->execute();
$stmt->close();

audit_log($conn, 'api_login', 'Mobile login token issued', 'attendance_api');

json_response([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role_id' => (int)$user['role_id']
    ]
]);