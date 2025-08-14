<?php
/*
--------------------------------------------------------------------------------
-- File: /actions/search_employees.php
-- Description: API endpoint to search employees for team collaboration
--------------------------------------------------------------------------------
*/

session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Set JSON content type
header('Content-Type: application/json');

try {
    // Fetch all active employees with their details
    $sql = "SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) as name, 
                   e.designation, e.department, u.email, u.is_active
            FROM employees e 
            JOIN users u ON e.user_id = u.id 
            WHERE u.is_active = 1 
            ORDER BY e.first_name, e.last_name";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'designation' => $row['designation'] ?: 'N/A',
            'department' => $row['department'] ?: 'N/A',
            'email' => $row['email']
        ];
    }
    
    // Log the search action
    $audit_sql = "INSERT INTO audit_logs (user_id, action, details, resource, ip_address, user_agent) 
                 VALUES (?, 'employee_search', 'User searched for employees', 'employees', ?, ?)";
    $audit_stmt = $conn->prepare($audit_sql);
    if ($audit_stmt) {
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $audit_stmt->bind_param('iss', $user_id, $ip_address, $user_agent);
        $audit_stmt->execute();
        $audit_stmt->close();
    }
    
    echo json_encode([
        'success' => true, 
        'employees' => $employees,
        'count' => count($employees)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>