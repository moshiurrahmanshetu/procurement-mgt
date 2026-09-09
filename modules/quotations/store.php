<?php
/**
 * Store Quotation Handler (POST)
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/quotations/index.php');
}

// Permission check: Admin, Manager, Procurement Officer
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to create quotations.';
    redirect('modules/quotations/index.php');
}

$prId = (int)($_POST['purchase_request_id'] ?? 0);

// Verify CSRF Token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
    $_SESSION['old_input'] = $_POST;
    redirect('modules/quotations/create.php' . ($prId > 0 ? '?purchase_request_id=' . $prId : ''));
}

$user = currentUser();
$db = getDb();

$supplierId = (int)($_POST['supplier_id'] ?? 0);
$quotationDate = sanitizeInput($_POST['quotation_date'] ?? date('Y-m-d'));
$validUntil = sanitizeInput($_POST['valid_until'] ?? '');
$paymentTerms = sanitizeInput($_POST['payment_terms'] ?? '');
$deliveryTerms = sanitizeInput($_POST['delivery_terms'] ?? '');
$notes = sanitizeInput($_POST['notes'] ?? '');
$taxPercentage = max(0, (float)($_POST['tax_percentage'] ?? 0));
$shippingCost = max(0, (float)($_POST['shipping_cost'] ?? 0));
$otherCharges = max(0, (float)($_POST['other_charges'] ?? 0));
$rawItems = $_POST['items'] ?? [];

// 1. Validate Purchase Request
$pr = null;
try {
    $prStmt = $db->prepare("SELECT * FROM purchase_requests WHERE id = :id AND status = 'approved' AND deleted_at IS NULL LIMIT 1");
    $prStmt->execute([':id' => $prId]);
    $pr = $prStmt->fetch();
} catch (Exception $e) {
    error_log('Error checking PR: ' . $e->getMessage());
}

if (!$pr) {
    $_SESSION['flash_error'] = 'Purchase Request not found or is not approved.';
    redirect('modules/quotations/create.php');
}

// 2. Validate Supplier
$supplier = null;
try {
    $sStmt = $db->prepare("SELECT * FROM suppliers WHERE id = :id AND status = 'active' AND deleted_at IS NULL LIMIT 1");
    $sStmt->execute([':id' => $supplierId]);
    $supplier = $sStmt->fetch();
} catch (Exception $e) {
    error_log('Error checking supplier: ' . $e->getMessage());
}

if (!$supplier) {
    $_SESSION['flash_error'] = 'Please select a valid, active supplier vendor.';
    $_SESSION['old_input'] = $_POST;
    redirect('modules/quotations/create.php?purchase_request_id=' . $prId);
}

// 3. Duplicate Quotation Check
try {
    $dupStmt = $db->prepare("SELECT COUNT(*) FROM quotations WHERE purchase_request_id = :pr_id AND supplier_id = :sup_id AND deleted_at IS NULL");
    $dupStmt->execute([':pr_id' => $prId, ':sup_id' => $supplierId]);
    if ($dupStmt->fetchColumn() > 0) {
        $_SESSION['flash_error'] = "Supplier '{$supplier['name']}' has already submitted a quotation for Purchase Request {$pr['request_no']}.";
        $_SESSION['old_input'] = $_POST;
        redirect('modules/quotations/create.php?purchase_request_id=' . $prId);
    }
} catch (Exception $e) {
    error_log('Error checking duplicate quotation: ' . $e->getMessage());
}

// 4. Validate and Sanitize Items
$validatedItems = [];
$subtotal = 0.0;

if (empty($rawItems) || !is_array($rawItems)) {
    $_SESSION['flash_error'] = 'At least one item is required in the quotation.';
    $_SESSION['old_input'] = $_POST;
    redirect('modules/quotations/create.php?purchase_request_id=' . $prId);
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
    redirect('modules/quotations/create.php?purchase_request_id=' . $prId);
}

// 5. Calculate Server-Side Financials
$subtotal = round($subtotal, 2);
$taxAmount = round($subtotal * ($taxPercentage / 100), 2);
$shippingCost = round($shippingCost, 2);
$otherCharges = round($otherCharges, 2);
$totalAmount = round($subtotal + $taxAmount + $shippingCost + $otherCharges, 2);

try {
    $db->beginTransaction();

    // Generate Quotation Code (QT-000001)
    $maxStmt = $db->query("SELECT MAX(id) FROM quotations");
    $maxId = (int)$maxStmt->fetchColumn();
    $quotationNo = sprintf('QT-%06d', $maxId + 1);

    // Double check code uniqueness
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM quotations WHERE quotation_no = :qno");
    $checkStmt->execute([':qno' => $quotationNo]);
    if ($checkStmt->fetchColumn() > 0) {
        $quotationNo = sprintf('QT-%06d', $maxId + rand(2, 99));
    }

    // Insert Quotation Master
    $insertStmt = $db->prepare("
        INSERT INTO quotations (
            quotation_no, purchase_request_id, supplier_id, quotation_date, valid_until,
            subtotal, tax_percentage, tax_amount, shipping_cost, other_charges, total_amount,
            payment_terms, delivery_terms, notes, status, created_by, created_at, updated_at
        ) VALUES (
            :qno, :pr_id, :supplier_id, :quote_date, :valid_until,
            :subtotal, :tax_pct, :tax_amt, :shipping, :other, :total,
            :payment_terms, :delivery_terms, :notes, 'draft', :created_by, NOW(), NOW()
        )
    ");

    $insertStmt->execute([
        ':qno'            => $quotationNo,
        ':pr_id'          => $prId,
        ':supplier_id'    => $supplierId,
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
        ':notes'          => !empty($notes) ? $notes : null,
        ':created_by'     => $user['id']
    ]);

    $quotationId = (int)$db->lastInsertId();

    // Insert Quotation Items
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
            :quotation_id, :user_id, 'created', NULL, 'draft', :comments, NOW()
        )
    ");
    $histStmt->execute([
        ':quotation_id' => $quotationId,
        ':user_id'      => $user['id'],
        ':comments'     => "Quotation created as draft for Purchase Request {$pr['request_no']}"
    ]);

    $db->commit();

    logActivity($user['id'], 'create_quotation', "Created quotation {$quotationNo} for PR {$pr['request_no']} from {$supplier['name']}");

    $_SESSION['flash_success'] = "Quotation '{$quotationNo}' saved successfully as draft.";
    redirect('modules/quotations/view.php?id=' . $quotationId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error saving quotation: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error saving quotation: ' . $e->getMessage();
    $_SESSION['old_input'] = $_POST;
    redirect('modules/quotations/create.php?purchase_request_id=' . $prId);
}
