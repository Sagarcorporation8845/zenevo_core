<?php
/*
--------------------------------------------------------------------------------
-- File: /create_invoice.php (NEW FILE)
-- Description: Form to create a new invoice.
--------------------------------------------------------------------------------
*/

$pageTitle = 'Create New Invoice';
include 'includes/header.php';

// Security Check
if (!has_permission($conn, 'manage_invoices')) {
    echo '<div class="p-6">You do not have permission to view this page.</div>';
    include 'includes/footer.php';
    exit();
}

// Fetch projects to populate the dropdown
$projects_result = $conn->query("SELECT p.id, p.name as project_name, c.name as client_name FROM projects p JOIN clients c ON p.client_id = c.id ORDER BY c.name, p.name");

?>

<div class="container mx-auto">
    <form action="actions/invoice_action.php" method="POST" id="invoice-form">
        <input type="hidden" name="action" value="create_invoice">

        <div class="bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">New Invoice Details</h2>

            <!-- Top Section: Client, Dates -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div>
                    <label for="project_id" class="block text-sm font-medium text-gray-700">Project (Client)</label>
                    <select id="project_id" name="project_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="" disabled selected>Select a project</option>
                        <?php while($project = $projects_result->fetch_assoc()): ?>
                            <option value="<?php echo e($project['id']); ?>">
                                <?php echo e($project['project_name']); ?> (<?php echo e($project['client_name']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="issue_date">Issue Date</label>
                    <input type="date" name="issue_date" id="issue_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full enhanced-input">
                </div>
                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" name="due_date" id="due_date" required class="w-full enhanced-input">
                </div>
            </div>

            <!-- Invoice Items Section -->
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Invoice Items</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/2">Description</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                        </tr>
                    </thead>
                    <tbody id="invoice-items-body">
                        <!-- JS will populate this -->
                    </tbody>
                </table>
            </div>
            <button type="button" id="add-item-btn" class="mt-4 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg text-sm">
                + Add Item
            </button>

            <!-- Totals Section -->
            <div class="flex justify-end mt-8">
                <div class="w-full max-w-sm">
                    <div class="flex justify-between py-2 border-b">
                        <span class="font-medium text-gray-600">Subtotal</span>
                        <span id="subtotal" class="font-bold text-gray-800">$0.00</span>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="font-medium text-gray-600">Tax (0%)</span>
                        <span id="tax" class="font-bold text-gray-800">$0.00</span>
                    </div>
                    <div class="flex justify-between py-4 bg-gray-50 -mx-4 px-4 rounded-b-lg">
                        <span class="font-bold text-lg text-gray-900">Total</span>
                        <span id="grand-total" class="font-bold text-lg text-indigo-600">$0.00</span>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 pt-5 border-t border-gray-200">
                <div class="flex justify-end">
                    <a href="invoices.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Create Invoice
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addItemBtn = document.getElementById('add-item-btn');
    const itemsBody = document.getElementById('invoice-items-body');

    // Function to add a new item row
    function addNewItem() {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-4 py-2 border-b"><input type="text" name="descriptions[]" class="w-full table-input" placeholder="Item description" required></td>
            <td class="px-4 py-2 border-b"><input type="number" name="quantities[]" class="w-24 table-input item-qty" placeholder="1" value="1" min="1" required></td>
            <td class="px-4 py-2 border-b"><input type="number" name="unit_prices[]" class="w-32 table-input item-price" placeholder="0.00" step="0.01" min="0" required></td>
            <td class="px-4 py-2 border-b text-right"><span class="item-total font-semibold">$0.00</span></td>
            <td class="px-4 py-2 border-b text-center"><button type="button" class="text-red-500 hover:text-red-700 remove-item-btn font-bold">X</button></td>
        `;
        itemsBody.appendChild(row);
    }

    // Add first item on page load
    addNewItem();

    // Event listener for adding new items
    addItemBtn.addEventListener('click', addNewItem);

    // Event listener for removing items (uses event delegation)
    itemsBody.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-item-btn')) {
            e.target.closest('tr').remove();
            updateTotals();
        }
    });

    // Event listener for updating totals when quantity or price changes
    itemsBody.addEventListener('input', function(e) {
        if (e.target && (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price'))) {
            const row = e.target.closest('tr');
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const total = qty * price;
            row.querySelector('.item-total').textContent = '$' + total.toFixed(2);
            updateTotals();
        }
    });

    // Function to calculate and update all totals
    function updateTotals() {
        let subtotal = 0;
        document.querySelectorAll('#invoice-items-body tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            subtotal += qty * price;
        });

        // For now, tax is 0. This can be made dynamic later.
        const tax = 0;
        const grandTotal = subtotal + tax;

        document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
        document.getElementById('tax').textContent = '$' + tax.toFixed(2);
        document.getElementById('grand-total').textContent = '$' + grandTotal.toFixed(2);
    }
});
</script>

<?php
include 'includes/footer.php';
?>