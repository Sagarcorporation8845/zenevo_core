<?php
/*
--------------------------------------------------------------------------------
-- File: /documents.php (NEW FILE)
-- Description: Page to list and manage document templates.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Document Templates';
include 'includes/header.php';

// Security Check
if (!has_permission($conn, 'manage_documents')) {
    echo '<div class="p-6 bg-white rounded-lg shadow-md">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Handle flash messages
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Fetch all document templates
$result = $conn->query("SELECT id, name, type FROM document_templates ORDER BY name ASC");

?>

<div class="container mx-auto">
    <!-- Header with Action Button -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-700">All Document Templates</h2>
        <a href="create_document_template.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
            + Create New Template
        </a>
    </div>

    <!-- Flash Message Display -->
    <?php if ($flash_message): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $flash_message['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
            <?php echo e($flash_message['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Templates Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Template Name</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap font-semibold"><?php echo e($row['name']); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-600 whitespace-no-wrap"><?php echo e($row['type']); ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-center">
                                    <a href="generate_document.php?template_id=<?php echo e($row['id']); ?>" class="text-green-600 hover:text-green-900 font-semibold mr-4">Generate</a>
                                    <a href="create_document_template.php?id=<?php echo e($row['id']); ?>" class="text-indigo-600 hover:text-indigo-900 font-semibold">Edit</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center py-10 text-gray-500">
                                No document templates found. <a href="create_document_template.php" class="text-indigo-600 hover:underline">Create one now</a>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>