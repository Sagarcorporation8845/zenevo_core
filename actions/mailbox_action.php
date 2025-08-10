<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!check_role_access($conn, ['Admin','HR Manager','Finance Manager'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save_template') {
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $from_alias = trim($_POST['from_alias'] ?? 'support');
    $html = $_POST['html'] ?? '';

    if ($name && $subject && $html) {
        $stmt = $conn->prepare("INSERT INTO mail_templates (name, subject, from_alias, html, created_by) VALUES (?, ?, ?, ?, ?)");
        $uid = $_SESSION['user_id'] ?? null;
        $stmt->bind_param('ssssi', $name, $subject, $from_alias, $html, $uid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Template saved'];
    } else {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please fill all fields'];
    }
    header('Location: ' . url_for('mailbox.php'));
    exit;
}

if ($action === 'send_bulk') {
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'CSV upload failed'];
        header('Location: ' . url_for('mailbox.php'));
        exit;
    }
    $template_id = (int)($_POST['template_id'] ?? 0);
    $tpl = null;
    if ($template_id) {
        $res = $conn->prepare('SELECT * FROM mail_templates WHERE id=?');
        $res->bind_param('i', $template_id);
        $res->execute();
        $tpl = $res->get_result()->fetch_assoc();
        $res->close();
    }
    if (!$tpl) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid template selected'];
        header('Location: ' . url_for('mailbox.php'));
        exit;
    }

    $job_stmt = $conn->prepare("INSERT INTO mail_jobs (template_id, uploaded_filename, created_by, status) VALUES (?,?,?, 'Processing')");
    $uid = $_SESSION['user_id'] ?? null;
    $orig = $_FILES['csv']['name'];
    $job_stmt->bind_param('isi', $template_id, $orig, $uid);
    $job_stmt->execute();
    $job_id = $conn->insert_id;
    $job_stmt->close();

    $file = fopen($_FILES['csv']['tmp_name'], 'r');
    // Try to detect header
    $header = fgetcsv($file);
    $nameIndex = -1; $emailIndex = -1;
    foreach ($header as $idx => $col) {
        $colLower = strtolower(trim($col));
        if ($colLower === 'name') $nameIndex = $idx;
        if ($colLower === 'email') $emailIndex = $idx;
    }
    if ($emailIndex === -1) {
        // assume no header
        rewind($file);
    }
    $count = 0; $sent = 0;

    // Determine from address by alias
    $from = alias_to_email($tpl['from_alias']);
    $subject = $tpl['subject'];

    while (($row = fgetcsv($file)) !== false) {
        $count++;
        $name = '';
        $email = '';
        if ($emailIndex !== -1) {
            $name = $nameIndex !== -1 ? trim($row[$nameIndex] ?? '') : '';
            $email = trim($row[$emailIndex] ?? '');
        } else {
            $name = trim($row[0] ?? '');
            $email = trim($row[1] ?? '');
        }
        if (!$email) { continue; }
        $body = str_replace(['[Candidate Name]','{{name}}','{{Name}}'], $name ?: 'Candidate', $tpl['html']);
        $ok = send_mail_html($email, $subject, $body, $from, 'Zenevo');
        $status = $ok ? 'Sent' : 'Failed';
        if ($ok) { $sent++; }
        $stmt = $conn->prepare("INSERT INTO mail_job_recipients (job_id, name, email, status) VALUES (?,?,?,?)");
        $stmt->bind_param('isss', $job_id, $name, $email, $status);
        $stmt->execute();
        $stmt->close();
    }
    fclose($file);

    $upd = $conn->prepare("UPDATE mail_jobs SET total_recipients=?, sent_count=?, status='Completed' WHERE id=?");
    $upd->bind_param('iii', $count, $sent, $job_id);
    $upd->execute();
    $upd->close();

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Emails processed: $sent / $count"];
    header('Location: ' . url_for('mailbox.php'));
    exit;
}

echo 'Invalid action';