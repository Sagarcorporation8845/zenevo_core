<?php
require_once __DIR__ . '/../config/db.php';

if (!has_permission($conn, 'view_reports')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$filename = 'export_' . $type . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fputcsv($output, ['Generated At', date('c')]);
fputcsv($output, []);

switch ($type) {
    case 'employees':
        fputcsv($output, ['Employee ID', 'Name', 'Email', 'Department', 'Designation', 'DOJ', 'Active']);
        $sql = "SELECT e.id, CONCAT(e.first_name,' ',e.last_name) AS full_name, u.email, e.department, e.designation, e.date_of_joining, u.is_active
                FROM employees e JOIN users u ON e.user_id = u.id ORDER BY e.id ASC";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($output, [
                    $row['id'],
                    $row['full_name'],
                    $row['email'],
                    $row['department'],
                    $row['designation'],
                    $row['date_of_joining'],
                    $row['is_active'] ? 'Yes' : 'No'
                ]);
            }
            $res->close();
        }
        break;

    case 'leaves':
        $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        fputcsv($output, ['Employee', 'Type', 'Start Date', 'End Date', 'Status', 'Days']);
        $sql = "SELECT CONCAT(e.first_name,' ',e.last_name) AS employee, l.type, l.start_date, l.end_date, l.status,
                       DATEDIFF(l.end_date, l.start_date) + 1 AS days
                FROM leaves l JOIN employees e ON l.employee_id = e.id
                WHERE DATE_FORMAT(l.start_date, '%Y-%m') = ?
                ORDER BY l.start_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $month);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($row = $rs->fetch_assoc()) {
            fputcsv($output, [$row['employee'], $row['type'], $row['start_date'], $row['end_date'], $row['status'], $row['days']]);
        }
        $stmt->close();
        break;

    case 'departments':
        fputcsv($output, ['Department', 'Active Employees']);
        $sql = "SELECT e.department, COUNT(*) AS cnt
                FROM employees e JOIN users u ON e.user_id = u.id
                WHERE u.is_active = 1
                GROUP BY e.department ORDER BY cnt DESC";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($output, [$row['department'], $row['cnt']]);
            }
            $res->close();
        }
        break;

    default:
        fputcsv($output, ['Unsupported export type']);
        break;
}

fclose($output);
exit;