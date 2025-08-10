<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!has_permission($conn, 'manage_invoices')) { http_response_code(403); exit('Forbidden'); }

$action = $_POST['action'] ?? '';

if ($action === 'save_settings') {
    $days = preg_replace('/[^0-9,]/','', $_POST['reminder_days_before'] ?? '7,3,1');
    $from_alias = $_POST['from_alias'] ?? 'billing';
    $stmt = $conn->prepare('REPLACE INTO finance_settings (id, reminder_days_before, from_alias) VALUES (1, ?, ?)');
    $stmt->bind_param('ss', $days, $from_alias);
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Settings saved'];
    header('Location: ' . url_for('finance_settings.php'));
    exit;
}

if ($action === 'send_due_reminders_now') {
    $fs = $conn->query('SELECT * FROM finance_settings WHERE id=1')->fetch_assoc();
    $days = array_map('intval', explode(',', $fs['reminder_days_before']));
    $from = alias_to_email($fs['from_alias']);

    // Find invoices due in N days
    $placeholders = implode(',', array_fill(0, count($days), '?'));
    $types = str_repeat('i', count($days));
    $sql = "SELECT i.invoice_number, i.due_date, i.total_amount, c.name as client_name, c.email as client_email
            FROM invoices i JOIN projects p ON i.project_id=p.id JOIN clients c ON p.client_id=c.id
            WHERE i.status NOT IN ('Paid','Cancelled','Void') AND DATEDIFF(i.due_date, CURDATE()) IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$days);
    $stmt->execute();
    $res = $stmt->get_result();
    $count = 0;
    while ($row = $res->fetch_assoc()) {
        $html = '<div style="font-family:Arial,sans-serif">'
              . '<p>Dear '.$row['client_name'].',</p>'
              . '<p>This is a friendly reminder that invoice <b>'.$row['invoice_number'].'</b> amount ₹'.number_format((float)$row['total_amount'],2)
              . ' is due on <b>'.htmlspecialchars($row['due_date']).'</b>.</p>'
              . '<p>Please ignore if already paid.</p>'
              . '<p>Regards,<br>Zenevo Finance</p>'
              . '</div>';
        if (!empty($row['client_email'])) {
            send_mail_html($row['client_email'], 'Payment Reminder: Invoice '.$row['invoice_number'], $html, $from, 'Zenevo Finance');
            $count++;
        }
    }
    $stmt->close();
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Reminders sent: $count"];
    header('Location: ' . url_for('finance_settings.php'));
    exit;
}

echo 'Invalid action';