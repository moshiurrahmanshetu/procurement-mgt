<?php
/**
 * Update Draft Quotation Handler (POST)
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/quotations/index.php');
}

// Permission check: Admin, Manager, Procurement Officer
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to edit quotations.';
    redirect('modules/quotations/index.php');
}

$quotationId = (int)($_POST['id'] ?? 0);
if ($quotationId <= 0) {
    $_SESSION['flash_error'] = 'Invalid quotation ID.';
    redirect('modules/quotations/index.php');
}

// Verify CSRF Token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
    $_SESSION['old_input'] = $_POST;
    redirect('modules/quotations/edit.php?id=' . $quotationId);
}

$user = currentUser();
$db = getDb();

// 1. Fetch Existing Quotation
$quotation = null;
try {
    $stmt = $db->prepare("SELECT * FROM quotations WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $quotationId]);
    $quotation = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error checking quotation: ' . $e->getMessage());
}

if (!$quotation) {
    $_SESSION['flash_error'] = 'Quotation not found or has been deleted.';
    redirect('modules/quotations/index.php');
}

if ($quotation['status'] !== 'draft') {
    $_SESSION['flash_error'] = "Only draft quotations can be edited. Current status is '{$quotation['status']}'.";
    redirect('modules/quotations/view.php?id=' . $quotationId);
}

// 2. Capture & Sanitize Form Input
$quotationDate = sanitizeInput($_POST['quotation_date'] ?? date('Y-m-d'));
$validUntil = sanitizeInput($_POST['valid_until'] ?? '');
$paymentTerms = sanitizeInput($_POST['payment_terms'] ?? '');
$deliveryTerms = sanitizeInput($_POST['delivery_terms'] ?? '');
$notes = sanitizeInput($_POST['notes'] ?? '');
$taxPercentage = max(0, (float)($_POST['tax_percentage'] ?? 0));
$shippingCost = max(0, (float)($_POST['shipping_cost'] ?? 0));
$otherCharges = max(0, (float)($_POST['other_charges'] ?? 0));
$rawItems = $_POST['items'] ?? [];

// 3. Validate Items
$validatedItems = [];
$subtotal = 0.0;

if (empty($rawItems) || !is_array($rawItems)) {
    $_SESSION['flash_error'] = 'At least one item is required in the quotation.';
    $_SESSION['old_input'] = $_POST;
    redirect('modules/quotations/edit.php?id=' . $quotationId);
}

foreach ($rawItems as $item) {
    $prItemId = (int)($item['purchase_request_item_id'] ?? 0);
    $qty = max(0, (float)($item['quantity'] ?? 0));
    $unitPrice = max(0, (float)($item['unit_price'] ?? 0));
    $remarks = sanitizeInput($item['remarks'] ?? '');

    if ($prItemId <= 0 || $qty <= 0) {
        continue;
    }

    $itemSubtotal = round($qty * $unitPrice, 2);
    $subtotal += $itemSubtotal;

    $validatedItems[] = [
        'purchase_request_item_id' => $prItemId,
        'quantity'                 => $qty,
        'unit_price'               => $unitPrice,
        'subtotal'                 => $itemSubtotal,
        'remarks'                  => !empty($remarks) ? $remarks : null
    ];
}

if (empty($validatedItems)) {
    $_SESSION['flash_error'] = 'Please enter valid quantities and unit prices for the quotation items.';
    $_SESSION['old_input'] = $_POST;
    redirect('modules/quotations/edit.php?id=' . $quotationId);
}

// 4. Calculate Server-Side Financials
$subtotal = round($subtotal, 2);
$taxAmount = round($subtotal * ($taxPercentage / 100), 2);
$shippingCost = round($shippingCost, 2);
$otherCharges = round($otherCharges, 2);
$totalAmount = round($subtotal + $taxAmount + $shippingCost + $otherCharges, 2);

try {
    $db->beginTransaction();

    // Update Quotation Master
    $updateStmt = $db->prepare("
        UPDATE quotations SET
            quotation_date = :quote_date,
            valid_until = :valid_until,
            subtotal = :subtotal,
            tax_percentage = :tax_pct,
            tax_amount = :tax_amt,
            shipping_cost = :shipping,
            other_charges = :other,
            total_amount = :total,
            payment_terms = :payment_terms,
            delivery_terms = :delivery_terms,
            notes = :notes,
            updated_at = NOW()
        WHERE id = :id AND deleted_at IS NULL
    ");

    $updateStmt->execute([
        ':id'             => $quotationId,
        ':quote_date'     => $quotationDate,
        ':valid_until'    => !empty($validUntil) ? $validUntil : null,
        ':subtotal'       => $subtotal,
        ':tax_pct'        => $taxPercentage,
        ':tax_amt'        => $taxAmount,
        ':shipping'       => $shippingCost,
        ':other'          => $otherCharges,
        ':total'          => $totalAmount,
        ':payment_terms'  => !empty($paymentTerms) ? $paymentTerms : null,
        ':delivery_terms' => !empty($deliveryTerms) ? $deliveryTerms : null,
        ':notes'          => !empty($notes) ? $notes : null
    ]);

    // Replace Quotation Items
    $delItemsStmt = $db->prepare("DELETE FROM quotation_items WHERE quotation_id = :qid");
    $delItemsStmt->execute([':qid' => $quotationId]);

    $itemInsertStmt = $db->prepare("
        INSERT INTO quotation_items (
            quotation_id, purchase_request_item_id, quantity, unit_price, subtotal, remarks, created_at
        ) VALUES (
            :quotation_id, :pr_item_id, :quantity, :unit_price, :subtotal, :remarks, NOW()
        )
    ");

    foreach ($validatedItems as $vItem) {
        $itemInsertStmt->execute([
            ':quotation_id' => $quotationId,
            ':pr_item_id'   => $vItem['purchase_request_item_id'],
            ':quantity'     => $vItem['quantity'],
            ':unit_price'   => $vItem['unit_price'],
            ':subtotal'     => $vItem['subtotal'],
            ':remarks'      => $vItem['remarks']
        ]);
    }

    // Insert History Record
    $histStmt = $db->prepare("
        INSERT INTO quotation_history (
            quotation_id, user_id, action, from_status, to_status, comments, created_at
        ) VALUES (
            :quotation_id, :user_id, 'updated', 'draft', 'draft', 'Draft quotation line items and commercial financials updated', NOW()
        )
    ");
    $histStmt->execute([
        ':quotation_id' => $quotationId,
        ':user_id'      => $user['id']
    ]);

    $db->commit();

    logActivity($user['id'], 'update_quotation', "Updated draft quotation {$quotation['quotation_no']}");

    $_SESSION['flash_success'] = "Draft quotation '{$quotation['quotation_no']}' updated successfully.";
    redirect('modules/quotations/view.php?id=' . $quotationId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error updating quotation: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error updating quotation: ' . $e->getMessage();
    $_SESSION['old_input'] = $_POST;
    redirect('modules/quotations/edit.php?id=' . $quotationId);
}
