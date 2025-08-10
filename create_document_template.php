<?php
/*
--------------------------------------------------------------------------------
-- File: /create_document_template.php (NEW FILE)
-- Description: Form to create or edit a document template.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Create Document Template';
include 'includes/header.php';

// Security Check
if (!has_permission($conn, 'manage_documents')) {
    echo '<div class="p-6">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Check if we are editing an existing template
$template = [
    'id' => '',
    'name' => '',
    'type' => '',
    'content' => ''
];
if (isset($_GET['id'])) {
    $pageTitle = 'Edit Document Template';
    $template_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM document_templates WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $template = $result->fetch_assoc();
    }
    $stmt->close();
}

?>
<!-- TinyMCE Rich Text Editor CDN -->
<script src="[https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js](https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js)" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: 'textarea#content',
    plugins: 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons',
    menubar: 'file edit view insert format tools table help',
    toolbar: 'undo redo | bold italic underline strikethrough | fontfamily fontsize blocks | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  preview save print | insertfile image media template link anchor codesample | ltr rtl',
    height: 600,
  });
</script>

<div class="container mx-auto max-w-4xl">
    <form action="actions/document_action.php" method="POST">
        <input type="hidden" name="action" value="save_template">
        <input type="hidden" name="template_id" value="<?php echo e($template['id']); ?>">

        <div class="bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-6 text-gray-800"><?php echo $pageTitle; ?></h2>

            <div class="form-grid form-grid-2 mb-6">
                <div class="form-group">
                    <label for="name">Template Name</label>
                    <input type="text" name="name" id="name" value="<?php echo e($template['name']); ?>" required class="w-full enhanced-input">
                </div>
                <div class="form-group">
                    <label for="type">Document Type (e.g., Offer Letter)</label>
                    <input type="text" name="type" id="type" value="<?php echo e($template['type']); ?>" required class="w-full enhanced-input">
                </div>
            </div>

            <div class="mb-6">
                <label for="content" class="block text-sm font-medium text-gray-700">Template Content</label>
                <p class="text-xs text-gray-500 mb-2">Use placeholders like <strong>{{employee_name}}</strong>, <strong>{{designation}}</strong>, <strong>{{date_of_joining}}</strong>, <strong>{{current_date}}</strong>. They will be replaced with employee data during generation.</p>
                <textarea id="content" name="content"><?php echo htmlspecialchars($template['content']); ?></textarea>
            </div>

            <!-- Form Actions -->
            <div class="pt-5 border-t border-gray-200">
                <div class="flex justify-end">
                    <a href="documents.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Save Template
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>