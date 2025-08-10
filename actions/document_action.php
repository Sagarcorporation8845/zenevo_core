<?php
/*
--------------------------------------------------------------------------------
-- File: /actions/document_action.php (UPDATED)
-- Description: Handles creating, managing, and generating documents.
--------------------------------------------------------------------------------
*/
require_once '../config/db.php';
require_login();

// --- Main Logic: Check the requested action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- Create/Update Document Template Action ---
    if ($_POST['action'] === 'save_template') {
        // Security check
        if (!has_permission($conn, 'manage_documents')) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to perform this action.'];
            header('Location: ' . url_for('documents.php'));
            exit();
        }

        // 1. Get and validate form data
        $template_id = $_POST['template_id']; // Will be empty for new templates
        $name = trim($_POST['name']);
        $type = trim($_POST['type']);
        $content = $_POST['content']; // Content from rich text editor

        if (empty($name) || empty($type) || empty($content)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'All fields are required.'];
            header('Location: ' . url_for('create_document_template.php') . ($template_id ? '?id=' . $template_id : ''));
            exit();
        }

        // 2. Decide whether to INSERT or UPDATE
        if (empty($template_id)) {
            // INSERT new template
            $sql = "INSERT INTO document_templates (name, type, content) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $name, $type, $content);
            $message = 'Template created successfully.';
        } else {
            // UPDATE existing template
            $sql = "UPDATE document_templates SET name = ?, type = ?, content = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $type, $content, $template_id);
            $message = 'Template updated successfully.';
        }

        // 3. Execute the query
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => $message];
            header('Location: ' . url_for('documents.php'));
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to save template.'];
            header('Location: ' . url_for('create_document_template.php') . ($template_id ? '?id=' . $template_id : ''));
        }
        $stmt->close();
        exit();
    }

    // --- Generate Document Action ---
    if ($_POST['action'] === 'generate_document') {
        // Security check
        if (!has_permission($conn, 'manage_documents')) {
            die('Access Denied.'); // Use die() because this opens in a new tab
        }

        $template_id = $_POST['template_id'];
        $employee_id = $_POST['employee_id'];

        // 1. Fetch Template Content
        $stmt_template = $conn->prepare("SELECT content FROM document_templates WHERE id = ?");
        $stmt_template->bind_param("i", $template_id);
        $stmt_template->execute();
        $template_result = $stmt_template->get_result();
        if ($template_result->num_rows === 0) {
            die('Template not found.');
        }
        $template = $template_result->fetch_assoc();
        $content = $template['content'];
        $stmt_template->close();

        // 2. Fetch Employee Data
        $stmt_employee = $conn->prepare(
            "SELECT u.name as employee_name, e.designation, e.date_of_joining
             FROM employees e
             JOIN users u ON e.user_id = u.id
             WHERE e.id = ?"
        );
        $stmt_employee->bind_param("i", $employee_id);
        $stmt_employee->execute();
        $employee_result = $stmt_employee->get_result();
        if ($employee_result->num_rows === 0) {
            die('Employee not found.');
        }
        $employee = $employee_result->fetch_assoc();
        $stmt_employee->close();

        // 3. Define Placeholders and their values
        $placeholders = [
            '{{employee_name}}'     => e($employee['employee_name']),
            '{{designation}}'       => e($employee['designation']),
            '{{date_of_joining}}'   => date('F j, Y', strtotime($employee['date_of_joining'])),
            '{{current_date}}'      => date('F j, Y')
        ];

        // 4. Replace placeholders in the content
        $final_content = str_replace(array_keys($placeholders), array_values($placeholders), $content);

        // 5. Output the final document in a clean preview page
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Document Preview</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                body { font-family: 'Inter', sans-serif; }
                @media print {
                    .no-print { display: none; }
                    body { margin: 0; }
                }
            </style>
        </head>
        <body class="bg-gray-200">
            <div class="container mx-auto p-4 md:p-8">
                <div class="bg-white p-4 md:p-8 rounded-lg shadow-lg">
                    <div class="flex justify-end mb-4 no-print">
                        <button onclick="window.print()" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg">Print Document</button>
                    </div>
                    <div class="prose max-w-none">
                        {$final_content}
                    </div>
                </div>
            </div>
        </body>
        </html>
HTML;
        exit();
    }

} else {
    header('Location: ' . url_for('dashboard.php'));
    exit();
}
?>