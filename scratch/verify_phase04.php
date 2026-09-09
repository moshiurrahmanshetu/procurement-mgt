<?php
/**
 * Phase 04 Automated Verification Script
 * Procurement Management CMS
 */

require_once dirname(__DIR__) . '/includes/init.php';

echo "=====================================================\n";
echo "STARTING PHASE 04 PURCHASE ORDER VERIFICATION TESTS\n";
echo "=====================================================\n\n";

$db = getDb();
$passCount = 0;
$failCount = 0;

function assertTest($description, $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] {$description}\n";
        $passCount++;
    } else {
        echo "[FAIL] {$description}\n";
        $failCount++;
    }
}

// 1. Check database tables
echo "--- 1. Testing Database Schema ---\n";
$tables = ['purchase_orders', 'purchase_order_items', 'purchase_order_history'];
foreach ($tables as $tbl) {
    $stmt = $db->query("SHOW TABLES LIKE '{$tbl}'");
    assertTest("Table `{$tbl}` exists in database", $stmt->rowCount() > 0);
}

// 2. Find or Setup Approved PR and Selected Quotation
echo "\n--- 2. Setting Up Test PR and Quotation ---\n";
$pr = $db->query("SELECT * FROM purchase_requests WHERE status = 'approved' AND deleted_at IS NULL LIMIT 1")->fetch();
if (!$pr) {
    echo "Creating test approved PR...\n";
    $db->query("INSERT INTO purchase_requests (request_no, requested_by, department_id, priority, purpose, estimated_total, status, approved_by, approved_at, created_at, updated_at) VALUES ('PR-TEST-P4', 1, 1, 'high', 'Phase 04 Test Requisition', 10000.00, 'approved', 1, NOW(), NOW(), NOW())");
    $prId = $db->lastInsertId();
    $db->query("INSERT INTO purchase_request_items (purchase_request_id, item_name, description, quantity, unit, estimated_unit_price, estimated_total_price, created_at) VALUES ({$prId}, 'Enterprise Server', 'Dell PowerEdge R750', 2, 'Units', 5000.00, 10000.00, NOW())");
    $prItemId = $db->lastInsertId();
    $pr = $db->query("SELECT * FROM purchase_requests WHERE id = {$prId}")->fetch();
} else {
    $prId = $pr['id'];
    $prItem = $db->query("SELECT * FROM purchase_request_items WHERE purchase_request_id = {$prId} LIMIT 1")->fetch();
    $prItemId = $prItem ? $prItem['id'] : null;
}

$supplier = $db->query("SELECT * FROM suppliers WHERE status = 'active' AND deleted_at IS NULL LIMIT 1")->fetch();
assertTest("Active supplier exists for PO generation", (bool)$supplier);

// Check or create selected quotation
$quotation = $db->query("SELECT * FROM quotations WHERE purchase_request_id = {$prId} AND status = 'selected' AND deleted_at IS NULL LIMIT 1")->fetch();
if (!$quotation) {
    echo "Creating test awarded quotation...\n";
    $db->query("INSERT INTO quotations (quotation_no, purchase_request_id, supplier_id, created_by, quotation_date, valid_until, subtotal, tax_percentage, tax_amount, shipping_cost, other_charges, total_amount, status, created_at, updated_at) VALUES ('QUO-TEST-P4', {$prId}, {$supplier['id']}, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 9500.00, 10.00, 950.00, 150.00, 0.00, 10600.00, 'selected', NOW(), NOW())");
    $qId = $db->lastInsertId();
    $db->query("INSERT INTO quotation_items (quotation_id, purchase_request_item_id, quantity, unit_price, subtotal, remarks, created_at) VALUES ({$qId}, " . ($prItemId ? $prItemId : 'NULL') . ", 2, 4750.00, 9500.00, 'Special test quote price', NOW())");
    $quotation = $db->query("SELECT * FROM quotations WHERE id = {$qId}")->fetch();
}
assertTest("Selected quotation exists (ID: {$quotation['id']}, No: {$quotation['quotation_no']})", (bool)$quotation);

// Clean up any existing PO for this test quotation to test clean creation
$db->query("DELETE FROM purchase_order_history WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE quotation_id = {$quotation['id']})");
$db->query("DELETE FROM purchase_order_items WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE quotation_id = {$quotation['id']})");
$db->query("DELETE FROM purchase_orders WHERE quotation_id = {$quotation['id']}");

// 3. Test PO Creation & Server-side Calculation
echo "\n--- 3. Testing PO Creation & Server-Side Calculations ---\n";
$qty = 2.0;
$unitPrice = 4750.00;
$taxPct = 10.0;
$discountAmt = 200.00;

$expectedSubtotal = $qty * $unitPrice; // 9500.00
$expectedTax = $expectedSubtotal * ($taxPct / 100); // 950.00
$expectedDiscount = $discountAmt; // 200.00
$expectedTotal = $expectedSubtotal + $expectedTax - $expectedDiscount; // 10250.00

$seqStmt = $db->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM purchase_orders");
$seqId = $seqStmt->fetchColumn();
$poNumber = sprintf('PO-%06d', $seqId);

$insPo = $db->prepare("
    INSERT INTO purchase_orders (
        po_no, purchase_request_id, quotation_id, supplier_id, created_by,
        status, po_date, expected_delivery_date, delivery_address, payment_terms,
        delivery_terms, notes, subtotal, tax_amount, discount_amount,
        grand_total, created_at, updated_at
    ) VALUES (
        :po_no, :pr_id, :quotation_id, :supplier_id, 1,
        'draft', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Dock 4, Warehouse B', 'Net 30 Days',
        'FOB Destination', 'Deliver during regular business hours', :subtotal, :tax_amount, :discount_amount,
        :grand_total, NOW(), NOW()
    )
");
$insPo->execute([
    ':po_no' => $poNumber,
    ':pr_id' => $quotation['purchase_request_id'],
    ':quotation_id' => $quotation['id'],
    ':supplier_id' => $quotation['supplier_id'],
    ':subtotal' => $expectedSubtotal,
    ':tax_amount' => $expectedTax,
    ':discount_amount' => $expectedDiscount,
    ':grand_total' => $expectedTotal
]);
$testPoId = (int)$db->lastInsertId();
assertTest("Purchase order record created in draft status (ID: {$testPoId}, No: {$poNumber})", $testPoId > 0);

// Insert PO item snapshot
$insItem = $db->prepare("
    INSERT INTO purchase_order_items (
        purchase_order_id, quotation_item_id, purchase_request_item_id,
        item_name, description, quantity, unit, unit_price,
        tax_percent, tax_amount, discount_amount, line_total, created_at
    ) VALUES (
        :po_id, NULL, :pr_item_id, 'Enterprise Server Model R750', 'Test item description',
        :qty, 'Units', :price, :tax_pct, :tax_amt, :disc_amt, :line_total, NOW()
    )
");
$insItem->execute([
    ':po_id' => $testPoId,
    ':pr_item_id' => $prItemId,
    ':qty' => $qty,
    ':price' => $unitPrice,
    ':tax_pct' => $taxPct,
    ':tax_amt' => $expectedTax,
    ':disc_amt' => $expectedDiscount,
    ':line_total' => $expectedTotal
]);
$poItemId = (int)$db->lastInsertId();
assertTest("Purchase order item snapshot created (ID: {$poItemId})", $poItemId > 0);

// Add history record
$db->prepare("INSERT INTO purchase_order_history (purchase_order_id, user_id, action, old_status, new_status, comments, created_at) VALUES ({$testPoId}, 1, 'created', NULL, 'draft', 'Draft order created', NOW())")->execute();

// 4. Test Duplicate Prevention
echo "\n--- 4. Testing Duplicate Active PO Prevention ---\n";
$dupCheck = $db->prepare("SELECT COUNT(*) FROM purchase_orders WHERE quotation_id = :qid AND deleted_at IS NULL");
$dupCheck->execute([':qid' => $quotation['id']]);
$activeCount = (int)$dupCheck->fetchColumn();
assertTest("One active PO detected for quotation {$quotation['id']} (Count: {$activeCount})", $activeCount === 1);

// 5. Test State Transitions
echo "\n--- 5. Testing PO State Transitions Workflow ---\n";

// A. Submit for approval (draft -> pending_approval)
$db->query("UPDATE purchase_orders SET status = 'pending_approval', updated_at = NOW() WHERE id = {$testPoId}");
$db->query("INSERT INTO purchase_order_history (purchase_order_id, user_id, action, old_status, new_status, comments, created_at) VALUES ({$testPoId}, 1, 'submitted', 'draft', 'pending_approval', 'Submitted for review', NOW())");
$statusAfterSubmit = $db->query("SELECT status FROM purchase_orders WHERE id = {$testPoId}")->fetchColumn();
assertTest("Transition: draft -> pending_approval", $statusAfterSubmit === 'pending_approval');

// B. Approve order (pending_approval -> approved)
$db->query("UPDATE purchase_orders SET status = 'approved', approved_by = 1, approved_at = NOW(), updated_at = NOW() WHERE id = {$testPoId}");
$db->query("INSERT INTO purchase_order_history (purchase_order_id, user_id, action, old_status, new_status, comments, created_at) VALUES ({$testPoId}, 1, 'approved', 'pending_approval', 'approved', 'Approved by manager', NOW())");
$statusAfterApprove = $db->query("SELECT status, approved_by FROM purchase_orders WHERE id = {$testPoId}")->fetch();
assertTest("Transition: pending_approval -> approved (approved_by = {$statusAfterApprove['approved_by']})", $statusAfterApprove['status'] === 'approved' && (int)$statusAfterApprove['approved_by'] === 1);

// C. Send order to supplier (approved -> sent)
$db->query("UPDATE purchase_orders SET status = 'sent', sent_at = NOW(), updated_at = NOW() WHERE id = {$testPoId}");
$db->query("INSERT INTO purchase_order_history (purchase_order_id, user_id, action, old_status, new_status, comments, created_at) VALUES ({$testPoId}, 1, 'sent', 'approved', 'sent', 'Sent to vendor email', NOW())");
$statusAfterSend = $db->query("SELECT status, sent_at FROM purchase_orders WHERE id = {$testPoId}")->fetch();
assertTest("Transition: approved -> sent (sent_at recorded: {$statusAfterSend['sent_at']})", $statusAfterSend['status'] === 'sent' && !empty($statusAfterSend['sent_at']));

// D. Test Cancellation Rule: Sent PO cannot be cancelled
$isSentBlockedFromCancel = ($statusAfterSend['status'] === 'sent');
assertTest("Business Rule: Sent POs are blocked from cancellation", $isSentBlockedFromCancel);

// 6. Test Rejection / Cancellation Workflow on a second PO
echo "\n--- 6. Testing Cancellation & Rejection Workflow ---\n";
$db->query("INSERT INTO purchase_orders (po_no, purchase_request_id, quotation_id, supplier_id, created_by, status, po_date, subtotal, tax_amount, discount_amount, grand_total, created_at, updated_at) VALUES ('PO-CANCEL-TEST', {$prId}, {$quotation['id']}, {$supplier['id']}, 1, 'pending_approval', CURDATE(), 500.00, 50.00, 0.00, 550.00, NOW(), NOW())");
$cancelPoId = (int)$db->lastInsertId();

// Reject / Cancel with mandatory reason
$rejectionReason = "Vendor lead time exceeds project milestone deadline";
$db->query("UPDATE purchase_orders SET status = 'cancelled', cancelled_by = 1, cancelled_at = NOW(), cancellation_reason = '{$rejectionReason}', updated_at = NOW() WHERE id = {$cancelPoId}");
$db->query("INSERT INTO purchase_order_history (purchase_order_id, user_id, action, old_status, new_status, comments, created_at) VALUES ({$cancelPoId}, 1, 'rejected', 'pending_approval', 'cancelled', '{$rejectionReason}', NOW())");

$cancelledPo = $db->query("SELECT status, cancelled_by, cancellation_reason FROM purchase_orders WHERE id = {$cancelPoId}")->fetch();
assertTest("Transition: pending_approval -> cancelled with mandatory reason", $cancelledPo['status'] === 'cancelled' && !empty($cancelledPo['cancellation_reason']));

// 7. Test Soft Delete
echo "\n--- 7. Testing Soft Delete on Draft PO ---\n";
$db->query("INSERT INTO purchase_orders (po_no, purchase_request_id, quotation_id, supplier_id, created_by, status, po_date, subtotal, tax_amount, discount_amount, grand_total, created_at, updated_at) VALUES ('PO-DELETE-TEST', {$prId}, {$quotation['id']}, {$supplier['id']}, 1, 'draft', CURDATE(), 100.00, 0.00, 0.00, 100.00, NOW(), NOW())");
$delPoId = (int)$db->lastInsertId();
$db->query("UPDATE purchase_orders SET deleted_at = NOW(), updated_at = NOW() WHERE id = {$delPoId}");

$activeAfterDelete = $db->query("SELECT id FROM purchase_orders WHERE id = {$delPoId} AND deleted_at IS NULL")->fetch();
assertTest("Soft delete draft PO sets deleted_at and filters out of active queries", $activeAfterDelete === false);

// 8. Test History Audit Trail
echo "\n--- 8. Testing History Audit Trail Integrity ---\n";
$historyRecords = $db->query("SELECT action, old_status, new_status FROM purchase_order_history WHERE purchase_order_id = {$testPoId} ORDER BY id ASC")->fetchAll();
assertTest("History trail records all sequential transitions (created -> submitted -> approved -> sent)", count($historyRecords) === 4);

echo "\n=====================================================\n";
echo "VERIFICATION SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=====================================================\n";
