<?php
/*
--------------------------------------------------------------------------------
-- File: /includes/sidebar.php (UPDATED)
-- Description: The main navigation sidebar with a new link for Documents.
--------------------------------------------------------------------------------
*/

$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="w-64 bg-gray-800 text-white flex flex-col no-scrollbar">
    <div class="p-4 border-b border-gray-700 flex items-center justify-center">
        <img src="<?php echo url_for('assets/logo.svg'); ?>" alt="Company Inc." class="h-12 w-auto">
    </div>
    <nav class="flex-1 px-2 py-4 space-y-2">
        <a href="<?php echo url_for('dashboard.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            Dashboard
        </a>

        <a href="<?php echo url_for('profile.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
            My Profile
        </a>

        <?php if (has_permission($conn, 'manage_employees')): ?>
            <a href="<?php echo url_for('employees.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo in_array($current_page, ['employees.php', 'add_employee.php']) ? 'active' : ''; ?>">
                Employee Management
            </a>
        <?php endif; ?>

        <?php if (has_permission($conn, 'manage_leaves') || $_SESSION['role_id'] == 4): // Show to managers or employees ?>
            <a href="<?php echo url_for('leaves.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo in_array($current_page, ['leaves.php', 'apply_leave.php']) ? 'active' : ''; ?>">
                Leave Management
            </a>
        <?php endif; ?>

        <?php if (has_permission($conn, 'manage_invoices')): ?>
            <a href="<?php echo url_for('invoices.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo in_array($current_page, ['invoices.php', 'create_invoice.php']) ? 'active' : ''; ?>">
                Finance & Invoices
            </a>
        <?php endif; ?>

        <?php if (has_permission($conn, 'manage_documents')): ?>
            <a href="<?php echo url_for('documents.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo in_array($current_page, ['documents.php', 'create_document_template.php', 'generate_document.php']) ? 'active' : ''; ?>">
                Documents
            </a>
        <?php endif; ?>

        <a href="<?php echo url_for('my_attendance.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo ($current_page == 'my_attendance.php') ? 'active' : ''; ?>">
            Attendance
        </a>

        <?php if (has_permission($conn, 'manage_employees')): ?>
            <a href="<?php echo url_for('attendance_settings.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo ($current_page == 'attendance_settings.php') ? 'active' : ''; ?>">
                Attendance Settings
            </a>
        <?php endif; ?>

        <?php if (check_role_access($conn, ['Admin','HR Manager','Finance Manager'])): ?>
            <a href="<?php echo url_for('mailbox.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo ($current_page == 'mailbox.php') ? 'active' : ''; ?>">
                Mailbox
            </a>
        <?php endif; ?>

        <?php if (has_permission($conn, 'view_reports')): ?>
            <a href="<?php echo url_for('reports.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                Reports
            </a>
        <?php endif; ?>

        <?php if (check_role_access($conn, ['Admin','HR Manager','Team Lead'])): ?>
            <a href="<?php echo url_for('devops.php'); ?>" class="sidebar-link flex items-center px-4 py-2.5 rounded-md hover:bg-gray-700 <?php echo ($current_page == 'devops.php') ? 'active' : ''; ?>">
                DevOps
            </a>
        <?php endif; ?>
    </nav>
</div>