<?php
/**
 * Store Purchase Order
 * Procurement Management CMS - Phase 04
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Check: Only Procurement Officers & Administrators can create POs
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: Only Procurement Officers and Administrators can create Purchase Orders.');
    redirect('modules/purchase_orders/index.php');
}

// 2. Request Method & CSRF Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/purchase_orders/index.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid security token. Please try submitting the form again.');
    redirect('modules/purchase_orders/index.php');
}

$db = getDb();
$userId = currentUserId();

$quotationId = (int)($_POST['quotation_id'] ?? 0);
$poDate = trim($_POST['po_date'] ?? date('Y-m-d'));
$deliveryDate = !empty($_POST['expected_delivery_date']) ? trim($_POST['expected_delivery_date']) : (!empty($_POST['delivery_date']) ? trim($_POST['delivery_date']) : null);
$deliveryAddress = trim($_POST['delivery_address'] ?? '');
$paymentTerms = trim($_POST['payment_terms'] ?? '');
$deliveryTerms = trim($_POST['delivery_terms'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$rawItems = $_POST['items'] ?? [];

// 3. Validation
if ($quotationId <= 0) {
    setFlash('error', 'Invalid source quotation selected.');
    redirect('modules/purchase_orders/create.php');
}

if (empty($poDate)) {
    setFlash('error', 'PO Date is required.');
    redirect('modules/purchase_orders/create.php?quotation_id=' . $quotationId);
}

// Fetch source quotation details
$quotation = null;
try {
    $qStmt = $db->prepare("
        SELECT q.*, pr.id AS pr_id, pr.request_no, s.name AS supplier_name
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE q.id = :id AND q.status = 'selected' AND q.deleted_at IS NULL
        LIMIT 1
    ");
    $qStmt->execute([':id' => $quotationId]);
    $quotation = $qStmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching quotation in store.php: ' . $e->getMessage());
}

if (!$quotation) {
    setFlash('error', 'The selected quotation is either invalid, not awarded (selected), or deleted.');
    redirect('modules/purchase_orders/create.php');
}

// 4. Duplicate Check: Ensure one active PO per selected quotation
try {
    $dupCheck = $db->prepare("
        SELECT id, po_no 
        FROM purchase_orders 
        WHERE quotation_id = :qid AND deleted_at IS NULL 
        LIMIT 1
    ");
    $dupCheck->execute([':qid' => $quotationId]);
    $existingPo = $dupCheck->fetch();

    if ($existingPo) {
        setFlash('error', "Purchase Order {$existingPo['po_no']} has already been generated for this quotation.");
        redirect('modules/purchase_orders/view.php?id=' . $existingPo['id']);
    }
} catch (Exception $e) {
    error_log('Error checking duplicate PO: ' . $e->getMessage());
}

// 5. Validate Line Items & Recalculate Totals Server-Side
if (empty($rawItems) || !is_array($rawItems)) {
    setFlash('error', 'At least one line item is required for the Purchase Order.');
    redirect('modules/purchase_orders/create.php?quotation_id=' . $quotationId);
}

$processedItems = [];
$subtotal = 0.00;
$totalTax = 0.00;
$totalDiscount = 0.00;

foreach ($rawItems as $idx => $itemData) {
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
        setFlash('error', "Item name cannot be empty for item #" . ($idx + 1));
        redirect('modules/purchase_orders/create.php?quotation_id=' . $quotationId);
    }

    if ($qty <= 0) {
        setFlash('error', "Quantity must be greater than zero for item '{$itemName}'.");
        redirect('modules/purchase_orders/create.php?quotation_id=' . $quotationId);
    }

    if ($unitPrice < 0) {
        setFlash('error', "Unit price cannot be negative for item '{$itemName}'.");
        redirect('modules/purchase_orders/create.php?quotation_id=' . $quotationId);
    }

    $lineSubtotal = round($qty * $unitPrice, 2);
    $lineTax = round($lineSubtotal * ($taxPercent / 100), 2);
    $lineDiscount = round($discountAmount, 2);
    $lineTotal = max(0, round($lineSubtotal + $lineTax - $lineDiscount, 2));

    $subtotal += $lineSubtotal;
    $totalTax += $lineTax;
    $totalDiscount += $lineDiscount;

    $processedItems[] = [
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

// 6. Execute Database Transaction
try {
    $db->beginTransaction();

    // Generate unique PO sequence PO-%06d inside transaction
    $seqStmt = $db->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM purchase_orders");
    $seqRow = $seqStmt->fetch();
    $nextId = (int)$seqRow['next_id'];
    $poNumber = sprintf('PO-%06d', $nextId);

    // Double check uniqueness of po_no
    $uniqStmt = $db->prepare("SELECT id FROM purchase_orders WHERE po_no = :po_no LIMIT 1");
    $uniqStmt->execute([':po_no' => $poNumber]);
    if ($uniqStmt->fetch()) {
        $poNumber = sprintf('PO-%06d-%d', $nextId, time() % 1000);
    }

    // Insert purchase_orders record
    $poStmt = $db->prepare("
        INSERT INTO purchase_orders (
            po_no, purchase_request_id, quotation_id, supplier_id, created_by,
            status, po_date, expected_delivery_date, delivery_address, payment_terms,
            delivery_terms, notes, subtotal, tax_amount, discount_amount,
            grand_total, created_at, updated_at
        ) VALUES (
            :po_no, :pr_id, :quotation_id, :supplier_id, :created_by,
            'draft', :po_date, :expected_delivery_date, :delivery_address, :payment_terms,
            :delivery_terms, :notes, :subtotal, :tax_amount, :discount_amount,
            :grand_total, NOW(), NOW()
        )
    ");

    $poStmt->execute([
        ':po_no' => $poNumber,
        ':pr_id' => $quotation['pr_id'],
        ':quotation_id' => $quotation['id'],
        ':supplier_id' => $quotation['supplier_id'],
        ':created_by' => $userId,
        ':po_date' => $poDate,
        ':expected_delivery_date' => $deliveryDate,
        ':delivery_address' => $deliveryAddress,
        ':payment_terms' => $paymentTerms,
        ':delivery_terms' => $deliveryTerms,
        ':notes' => $notes,
        ':subtotal' => $subtotal,
        ':tax_amount' => $totalTax,
        ':discount_amount' => $totalDiscount,
        ':grand_total' => $grandTotal
    ]);

    $poId = (int)$db->lastInsertId();

    // Insert purchase_order_items (snapshot pattern)
    $itemInsert = $db->prepare("
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
        $itemInsert->execute([
            ':po_id' => $poId,
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
    $histStmt = $db->prepare("
        INSERT INTO purchase_order_history (
            purchase_order_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :po_id, :user_id, 'created', NULL, 'draft', :comments, NOW()
        )
    ");
    $histStmt->execute([
        ':po_id' => $poId,
        ':user_id' => $userId,
        ':comments' => "Purchase Order created in draft status from Quotation {$quotation['quotation_no']}."
    ]);

    // Activity Log
    logActivity("Created Purchase Order {$poNumber} from Quotation {$quotation['quotation_no']}", 'purchase_orders', $poId);

    $db->commit();

    setFlash('success', "Purchase Order {$poNumber} created successfully in Draft status.");
    redirect('modules/purchase_orders/view.php?id=' . $poId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error creating Purchase Order: ' . $e->getMessage());
    setFlash('error', 'A database error occurred while creating the Purchase Order: ' . $e->getMessage());
    redirect('modules/purchase_orders/create.php?quotation_id=' . $quotationId);
}
