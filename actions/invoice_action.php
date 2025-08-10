<?php
/*
--------------------------------------------------------------------------------
-- File: /actions/invoice_action.php (NEW FILE)
-- Description: Handles creating, updating, and deleting invoices.
--------------------------------------------------------------------------------
*/
require_once '../config/db.php';
require_login();

// --- Main Logic: Check the requested action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- Create Invoice Action ---
    if ($_POST['action'] === 'create_invoice') {
        // Security check
        if (!has_permission($conn, 'manage_invoices')) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'You do not have permission to create invoices.'];
            header('Location: ' . url_for('invoices.php'));
            exit();
        }

        // --- 1. Get and Validate Main Invoice Data ---
        $project_id = $_POST['project_id'];
        $issue_date = $_POST['issue_date'];
        $due_date = $_POST['due_date'];
        $status = 'Draft'; // Invoices are always created as Drafts
        $invoice_number = 'INV-' . time(); // Simple unique invoice number

        // --- 2. Get and Validate Invoice Items ---
        $descriptions = $_POST['descriptions'];
        $quantities = $_POST['quantities'];
        $unit_prices = $_POST['unit_prices'];
        $total_amount = 0;
        $invoice_items = [];

        for ($i = 0; $i < count($descriptions); $i++) {
            if (!empty($descriptions[$i]) && !empty($quantities[$i]) && !empty($unit_prices[$i])) {
                $quantity = (int)$quantities[$i];
                $unit_price = (float)$unit_prices[$i];
                $item_total = $quantity * $unit_price;
                $total_amount += $item_total;
                $invoice_items[] = [
                    'description' => $descriptions[$i],
                    'quantity' => $quantity,
                    'unit_price' => $unit_price
                ];
            }
        }

        if (empty($invoice_items)) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Cannot create an empty invoice. Please add at least one item.'];
            header('Location: ' . url_for('create_invoice.php'));
            exit();
        }

        // --- 3. Database Transaction ---
        // A transaction ensures that either all queries succeed, or none do.
        // This prevents creating an invoice without its items if something goes wrong.
        $conn->begin_transaction();

        try {
            // Insert into `invoices` table
            $sql_invoice = "INSERT INTO invoices (project_id, invoice_number, issue_date, due_date, status, total_amount) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_invoice = $conn->prepare($sql_invoice);
            $stmt_invoice->bind_param("issssd", $project_id, $invoice_number, $issue_date, $due_date, $status, $total_amount);
            $stmt_invoice->execute();

            // Get the ID of the invoice we just created
            $invoice_id = $conn->insert_id;

            // Insert into `invoice_items` table
            $sql_items = "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price) VALUES (?, ?, ?, ?)";
            $stmt_items = $conn->prepare($sql_items);

            foreach ($invoice_items as $item) {
                $stmt_items->bind_param("isid", $invoice_id, $item['description'], $item['quantity'], $item['unit_price']);
                $stmt_items->execute();
            }

            // If all queries were successful, commit the transaction
            $conn->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Invoice created successfully!'];
            header('Location: ' . url_for('invoices.php'));
            exit();

        } catch (mysqli_sql_exception $exception) {
            // If any query failed, roll back the transaction
            $conn->rollback();
            error_log("Invoice creation failed: " . $exception->getMessage());
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Failed to create invoice. Please try again.'];
            header('Location: ' . url_for('create_invoice.php'));
            exit();
        }
    }
} else {
    header('Location: ' . url_for('dashboard.php'));
    exit();
}
?>