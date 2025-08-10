<?php
/*
--------------------------------------------------------------------------------
-- File: /generate_document.php (NEW FILE)
-- Description: Page to select an employee and generate a document from a template.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Generate Document';
include 'includes/header.php';

// Security Check
if (!has_permission($conn, 'manage_documents')) {
    echo '<div class="p-6">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Get template ID from URL
if (!isset($_GET['template_id'])) {
    header('Location: /documents.php');
    exit();
}
$template_id = $_GET['template_id'];

// Fetch the selected template
$stmt_template = $conn->prepare("SELECT name FROM document_templates WHERE id = ?");
$stmt_template->bind_param("i", $template_id);
$stmt_template->execute();
$template = $stmt_template->get_result()->fetch_assoc();
$stmt_template->close();

if (!$template) {
    echo '<div class="p-6">Template not found.</div>';
    include 'includes/footer.php';
    exit();
}

// Fetch all active employees
$employees_result = $conn->query("SELECT e.id, e.first_name, e.last_name FROM employees e JOIN users u ON e.user_id = u.id WHERE u.is_active = 1 ORDER BY e.first_name");

?>

<div class="container mx-auto max-w-2xl">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-2 text-gray-800">Generate: <?php echo e($template['name']); ?></h2>
        <p class="text-sm text-gray-600 mb-6">Select an employee to generate their document.</p>

        <form action="actions/document_action.php" method="POST" target="_blank">
            <input type="hidden" name="action" value="generate_document">
            <input type="hidden" name="template_id" value="<?php echo e($template_id); ?>">

            <div class="mb-6">
                <label for="employee_id" class="block text-sm font-medium text-gray-700">Select Employee</label>
                <select id="employee_id" name="employee_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="" disabled selected>-- Choose an employee --</option>
                    <?php while($employee = $employees_result->fetch_assoc()): ?>
                        <option value="<?php echo e($employee['id']); ?>">
                            <?php echo e($employee['first_name'] . ' ' . $employee['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Form Actions -->
            <div class="pt-5 border-t border-gray-200">
                <div class="flex justify-end">
                    <a href="documents.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                        Generate & Preview
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>