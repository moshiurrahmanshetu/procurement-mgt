<?php
/**
 * Update Draft Purchase Order
 * Procurement Management CMS - Phase 04
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Check: Only Procurement Officers & Administrators can update draft POs
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: Only Procurement Officers and Administrators can update Purchase Orders.');
    redirect('modules/purchase_orders/index.php');
}

// 2. Request Method & CSRF Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/purchase_orders/index.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid security token.');
    redirect('modules/purchase_orders/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid Purchase Order ID.');
    redirect('modules/purchase_orders/index.php');
}

$db = getDb();
$userId = currentUserId();

$poDate = trim($_POST['po_date'] ?? date('Y-m-d'));
$deliveryDate = !empty($_POST['expected_delivery_date']) ? trim($_POST['expected_delivery_date']) : (!empty($_POST['delivery_date']) ? trim($_POST['delivery_date']) : null);
$deliveryAddress = trim($_POST['delivery_address'] ?? '');
$paymentTerms = trim($_POST['payment_terms'] ?? '');
$deliveryTerms = trim($_POST['delivery_terms'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$rawItems = $_POST['items'] ?? [];

if (empty($poDate)) {
    setFlash('error', 'PO Date is required.');
    redirect('modules/purchase_orders/edit.php?id=' . $id);
}

if (empty($rawItems) || !is_array($rawItems)) {
    setFlash('error', 'At least one line item is required.');
    redirect('modules/purchase_orders/edit.php?id=' . $id);
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = :id AND deleted_at IS NULL FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $po = $stmt->fetch();

    if (!$po) {
        $db->rollBack();
        setFlash('error', 'Purchase Order not found.');
        redirect('modules/purchase_orders/index.php');
    }

    if ($po['status'] !== 'draft') {
        $db->rollBack();
        setFlash('error', 'Only draft Purchase Orders can be modified.');
        redirect('modules/purchase_orders/view.php?id=' . $id);
    }

    // Process and validate items server-side
    $processedItems = [];
    $subtotal = 0.00;
    $totalTax = 0.00;
    $totalDiscount = 0.00;

    foreach ($rawItems as $idx => $itemData) {
        $itemId = !empty($itemData['id']) ? (int)$itemData['id'] : null;
        $itemName = trim($itemData['item_name'] ?? '');
        $itemDesc = trim($itemData['description'] ?? '');
        $unit = trim($itemData['unit'] ?? 'Units');
        $qty = (float)($itemData['quantity'] ?? 0);
        $unitPrice = (float)($itemData['unit_price'] ?? 0);
        $taxPercent = max(0, min(100, (float)($itemData['tax_percent'] ?? 0)));
        $discountAmount = max(0, (float)($itemData['discount_amount'] ?? 0));
        $quotationItemId = !empty($itemData['quotation_item_id']) ? (int)$itemData['quotation_item_id'] : null;
        $prItemId = !empty($itemData['purchase_request_item_id']) ? (int)$itemData['purchase_request_item_id'] : null;

        if (empty($itemName)) {
            $db->rollBack();
            setFlash('error', "Item name cannot be empty for line #" . ($idx + 1));
            redirect('modules/purchase_orders/edit.php?id=' . $id);
        }

        if ($qty <= 0) {
            $db->rollBack();
            setFlash('error', "Quantity must be greater than zero for item '{$itemName}'.");
            redirect('modules/purchase_orders/edit.php?id=' . $id);
        }

        if ($unitPrice < 0) {
            $db->rollBack();
            setFlash('error', "Unit price cannot be negative for item '{$itemName}'.");
            redirect('modules/purchase_orders/edit.php?id=' . $id);
        }

        $lineSubtotal = round($qty * $unitPrice, 2);
        $lineTax = round($lineSubtotal * ($taxPercent / 100), 2);
        $lineDiscount = round($discountAmount, 2);
        $lineTotal = max(0, round($lineSubtotal + $lineTax - $lineDiscount, 2));

        $subtotal += $lineSubtotal;
        $totalTax += $lineTax;
        $totalDiscount += $lineDiscount;

        $processedItems[] = [
            'id' => $itemId,
            'quotation_item_id' => $quotationItemId,
            'purchase_request_item_id' => $prItemId,
            'item_name' => $itemName,
            'description' => $itemDesc,
            'unit' => $unit,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'tax_percent' => $taxPercent,
            'tax_amount' => $lineTax,
            'discount_amount' => $lineDiscount,
            'line_total' => $lineTotal
        ];
    }

    $grandTotal = max(0, round($subtotal + $totalTax - $totalDiscount, 2));

    // Update purchase_orders record
    $updPo = $db->prepare("
        UPDATE purchase_orders 
        SET po_date = :po_date,
            expected_delivery_date = :expected_delivery_date,
            delivery_address = :delivery_address,
            payment_terms = :payment_terms,
            delivery_terms = :delivery_terms,
            notes = :notes,
            subtotal = :subtotal,
            tax_amount = :tax_amount,
            discount_amount = :discount_amount,
            grand_total = :grand_total,
            updated_at = NOW()
        WHERE id = :id
    ");

    $updPo->execute([
        ':po_date' => $poDate,
        ':expected_delivery_date' => $deliveryDate,
        ':delivery_address' => $deliveryAddress,
        ':payment_terms' => $paymentTerms,
        ':delivery_terms' => $deliveryTerms,
        ':notes' => $notes,
        ':subtotal' => $subtotal,
        ':tax_amount' => $totalTax,
        ':discount_amount' => $totalDiscount,
        ':grand_total' => $grandTotal,
        ':id' => $id
    ]);

    // Replace line items cleanly
    $delItems = $db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = :po_id");
    $delItems->execute([':po_id' => $id]);

    $insItem = $db->prepare("
        INSERT INTO purchase_order_items (
            purchase_order_id, quotation_item_id, purchase_request_item_id,
            item_name, description, quantity, unit, unit_price,
            tax_percent, tax_amount, discount_amount, line_total, created_at
        ) VALUES (
            :po_id, :quotation_item_id, :pr_item_id,
            :item_name, :description, :quantity, :unit, :unit_price,
            :tax_percent, :tax_amount, :discount_amount, :line_total, NOW()
        )
    ");

    foreach ($processedItems as $pItem) {
        $insItem->execute([
            ':po_id' => $id,
            ':quotation_item_id' => $pItem['quotation_item_id'],
            ':pr_item_id' => $pItem['purchase_request_item_id'],
            ':item_name' => $pItem['item_name'],
            ':description' => $pItem['description'],
            ':quantity' => $pItem['quantity'],
            ':unit' => $pItem['unit'],
            ':unit_price' => $pItem['unit_price'],
            ':tax_percent' => $pItem['tax_percent'],
            ':tax_amount' => $pItem['tax_amount'],
            ':discount_amount' => $pItem['discount_amount'],
            ':line_total' => $pItem['line_total']
        ]);
    }

    // Insert history entry
    $hStmt = $db->prepare("
        INSERT INTO purchase_order_history (
            purchase_order_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :po_id, :user_id, 'updated', 'draft', 'draft', :comments, NOW()
        )
    ");
    $hStmt->execute([
        ':po_id' => $id,
        ':user_id' => $userId,
        ':comments' => 'Purchase Order items and details modified in draft status.'
    ]);

    logActivity("Updated Draft Purchase Order {$po['po_no']}", 'purchase_orders', $id);

    $db->commit();
    setFlash('success', "Draft Purchase Order {$po['po_no']} updated successfully.");
    redirect('modules/purchase_orders/view.php?id=' . $id);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Update PO Error: ' . $e->getMessage());
    setFlash('error', 'Error updating Purchase Order: ' . $e->getMessage());
    redirect('modules/purchase_orders/edit.php?id=' . $id);
}
