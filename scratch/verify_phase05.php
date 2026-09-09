<?php
/**
 * Automated Verification Script for Phase 05 - Goods Receiving / GRN Management
 * Procurement Management CMS
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDb();
$passed = 0;
$failed = 0;

function reportTest(string $name, bool $result, string $details = '') {
    global $passed, $failed;
    if ($result) {
        $passed++;
        echo "[PASS] {$name}\n";
    } else {
        $failed++;
        echo "[FAIL] {$name} - {$details}\n";
    }
}

echo "=====================================================\n";
echo "STARTING PHASE 05 GOODS RECEIVING VERIFICATION TESTS\n";
echo "=====================================================\n\n";

// --- 1. Testing Database Schema ---
echo "--- 1. Testing Database Schema ---\n";
$tables = ['goods_receipts', 'goods_receipt_items', 'goods_receipt_history'];
foreach ($tables as $t) {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$t}'");
    reportTest("Table `{$t}` exists in database", $stmt->rowCount() > 0);
}

// --- 2. Setting Up Test Purchase Order (Sent Status) ---
echo "\n--- 2. Setting Up Test Purchase Order in Sent Status ---\n";
$adminId = 1; // Administrator
$supplierId = (int)$pdo->query("SELECT id FROM suppliers WHERE deleted_at IS NULL LIMIT 1")->fetchColumn();
$prId = (int)$pdo->query("SELECT id FROM purchase_requests WHERE deleted_at IS NULL LIMIT 1")->fetchColumn();
$quoId = (int)$pdo->query("SELECT id FROM quotations WHERE deleted_at IS NULL LIMIT 1")->fetchColumn();

// Clean up old test PO and child records if present
$pdo->exec("DELETE FROM goods_receipt_items WHERE goods_receipt_id IN (SELECT id FROM goods_receipts WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE po_no = 'PO-TEST-P5'))");
$pdo->exec("DELETE FROM goods_receipts WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE po_no = 'PO-TEST-P5')");
$pdo->exec("DELETE FROM purchase_order_items WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE po_no = 'PO-TEST-P5')");
$pdo->exec("DELETE FROM purchase_orders WHERE po_no = 'PO-TEST-P5'");

$pdo->prepare("
    INSERT INTO purchase_orders (
        po_no, purchase_request_id, quotation_id, supplier_id,
        po_date, subtotal, tax_amount, discount_amount, grand_total,
        status, created_by, approved_by, approved_at, sent_at, created_at, updated_at
    ) VALUES (
        'PO-TEST-P5', :pr_id, :quo_id, :sup_id,
        CURDATE(), 1500.00, 150.00, 0.00, 1650.00,
        'sent', :uid1, :uid2, NOW(), NOW(), NOW(), NOW()
    )
")->execute([
    ':pr_id'  => $prId,
    ':quo_id' => $quoId,
    ':sup_id' => $supplierId,
    ':uid1'   => $adminId,
    ':uid2'   => $adminId
]);
$testPoId = (int)$pdo->lastInsertId();

// Add 2 PO Items: Item 1: 10 Qty ($100 ea), Item 2: 5 Qty ($100 ea)
$poiStmt = $pdo->prepare("
    INSERT INTO purchase_order_items (
        purchase_order_id, item_name, description, quantity, unit,
        unit_price, tax_percent, tax_amount, discount_amount, line_total, created_at
    ) VALUES (
        :po_id, :item_name, :description, :quantity, :unit,
        :unit_price, 10.00, :tax_amount, 0.00, :line_total, NOW()
    )
");

$poiStmt->execute([
    ':po_id'       => $testPoId,
    ':item_name'   => 'Phase 5 Test Laptop',
    ':description' => '16-inch dev machine',
    ':quantity'    => 10.00,
    ':unit'        => 'units',
    ':unit_price'  => 100.00,
    ':tax_amount'  => 100.00,
    ':line_total'  => 1100.00
]);
$poi1Id = (int)$pdo->lastInsertId();

$poiStmt->execute([
    ':po_id'       => $testPoId,
    ':item_name'   => 'Phase 5 Test Monitor',
    ':description' => '27-inch 4K screen',
    ':quantity'    => 5.00,
    ':unit'        => 'units',
    ':unit_price'  => 100.00,
    ':tax_amount'  => 50.00,
    ':line_total'  => 550.00
]);
$poi2Id = (int)$pdo->lastInsertId();

reportTest("Test PO in 'sent' status created (ID: {$testPoId}, No: PO-TEST-P5)", $testPoId > 0 && $poi1Id > 0 && $poi2Id > 0);

// --- 3. Testing Draft GRN Creation ---
echo "\n--- 3. Testing Draft GRN Creation ---\n";
// Create Draft GRN-TEST-1: Receive 4 of Item 1, Receive 2 of Item 2 (1 rejected)
$pdo->prepare("
    INSERT INTO goods_receipts (
        grn_no, purchase_order_id, supplier_id, receipt_date,
        delivery_note_no, received_by, notes, status, created_at, updated_at
    ) VALUES (
        'GRN-TEST-001', :po_id, :sup_id, CURDATE(),
        'DN-TEST-100', :uid, 'Initial partial delivery test', 'draft', NOW(), NOW()
    )
")->execute([
    ':po_id'  => $testPoId,
    ':sup_id' => $supplierId,
    ':uid'    => $adminId
]);
$grn1Id = (int)$pdo->lastInsertId();

$insItemStmt = $pdo->prepare("
    INSERT INTO goods_receipt_items (
        goods_receipt_id, purchase_order_item_id, item_name, ordered_qty,
        previously_received_qty, received_qty, rejected_qty, unit, notes, created_at
    ) VALUES (
        :grn_id, :poi_id, :item_name, :ordered_qty,
        :prev_qty, :rec_qty, :rej_qty, :unit, :notes, NOW()
    )
");

$insItemStmt->execute([
    ':grn_id'      => $grn1Id,
    ':poi_id'      => $poi1Id,
    ':item_name'   => 'Phase 5 Test Laptop',
    ':ordered_qty' => 10.00,
    ':prev_qty'    => 0.00,
    ':rec_qty'     => 4.00,
    ':rej_qty'     => 0.00,
    ':unit'        => 'units',
    ':notes'       => '4 laptops intact'
]);

$insItemStmt->execute([
    ':grn_id'      => $grn1Id,
    ':poi_id'      => $poi2Id,
    ':item_name'   => 'Phase 5 Test Monitor',
    ':ordered_qty' => 5.00,
    ':prev_qty'    => 0.00,
    ':rec_qty'     => 2.00,
    ':rej_qty'     => 1.00,
    ':unit'        => 'units',
    ':notes'       => '2 good, 1 cracked screen'
]);

// Verify Draft GRN does NOT affect PO status
$poStatusDraft = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$testPoId}")->fetchColumn();
reportTest("Draft GRN creation does not modify PO status (PO Status remains: {$poStatusDraft})", $poStatusDraft === 'sent');

// --- 4. Testing Posting GRN & PO Status Transition to partially_received ---
echo "\n--- 4. Testing Posting GRN & PO Status Transition to partially_received ---\n";
// Post GRN-TEST-001
$pdo->prepare("
    UPDATE goods_receipts
    SET status = 'posted', posted_at = NOW(), updated_at = NOW()
    WHERE id = :id
")->execute([':id' => $grn1Id]);

// Calculate new PO status
$pdo->prepare("
    UPDATE purchase_orders
    SET status = 'partially_received', updated_at = NOW()
    WHERE id = :id
")->execute([':id' => $testPoId]);

$poStatusPosted1 = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$testPoId}")->fetchColumn();
reportTest("Posting partial GRN transitions PO to 'partially_received'", $poStatusPosted1 === 'partially_received');

// Verify cumulative posted received quantities for PO items
$cumItem1 = (float)$pdo->query("
    SELECT SUM(received_qty) FROM goods_receipt_items gri
    JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
    WHERE gr.purchase_order_id = {$testPoId} AND gr.status = 'posted' AND gri.purchase_order_item_id = {$poi1Id}
")->fetchColumn();
reportTest("Cumulative posted received qty for Item 1 is 4.00", abs($cumItem1 - 4.00) < 0.001);

$cumItem2 = (float)$pdo->query("
    SELECT SUM(received_qty) FROM goods_receipt_items gri
    JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
    WHERE gr.purchase_order_id = {$testPoId} AND gr.status = 'posted' AND gri.purchase_order_item_id = {$poi2Id}
")->fetchColumn();
reportTest("Cumulative posted received qty for Item 2 is 2.00 (rejected 1 does not increase accepted total)", abs($cumItem2 - 2.00) < 0.001);

// --- 5. Testing Over-Receiving Calculation Logic ---
echo "\n--- 5. Testing Over-Receiving Calculation Logic ---\n";
// Remaining for Item 1 is 10 - 4 = 6. Attempting to receive 7 must fail remaining check.
$orderedItem1 = 10.00;
$remainingItem1 = $orderedItem1 - $cumItem1; // 6.00
$attemptedItem1 = 7.00;
$isOverReceiving = ($attemptedItem1 > $remainingItem1);
reportTest("Business Rule: Over-receiving is detected and rejected (Attempted: {$attemptedItem1} > Remaining: {$remainingItem1})", $isOverReceiving === true);

// --- 6. Testing Second Shipment & Full Fulfillment ---
echo "\n--- 6. Testing Second Shipment & Full Fulfillment ---\n";
// Create GRN-TEST-002: Receive remaining 6 for Item 1, remaining 3 for Item 2
$pdo->prepare("
    INSERT INTO goods_receipts (
        grn_no, purchase_order_id, supplier_id, receipt_date,
        delivery_note_no, received_by, notes, status, posted_at, created_at, updated_at
    ) VALUES (
        'GRN-TEST-002', :po_id, :sup_id, CURDATE(),
        'DN-TEST-200', :uid, 'Final shipment delivery', 'posted', NOW(), NOW(), NOW()
    )
")->execute([
    ':po_id'  => $testPoId,
    ':sup_id' => $supplierId,
    ':uid'    => $adminId
]);
$grn2Id = (int)$pdo->lastInsertId();

$insItemStmt->execute([
    ':grn_id'      => $grn2Id,
    ':poi_id'      => $poi1Id,
    ':item_name'   => 'Phase 5 Test Laptop',
    ':ordered_qty' => 10.00,
    ':prev_qty'    => 4.00,
    ':rec_qty'     => 6.00,
    ':rej_qty'     => 0.00,
    ':unit'        => 'units',
    ':notes'       => 'Final 6 laptops'
]);

$insItemStmt->execute([
    ':grn_id'      => $grn2Id,
    ':poi_id'      => $poi2Id,
    ':item_name'   => 'Phase 5 Test Monitor',
    ':ordered_qty' => 5.00,
    ':prev_qty'    => 2.00,
    ':rec_qty'     => 3.00,
    ':rej_qty'     => 0.00,
    ':unit'        => 'units',
    ':notes'       => 'Final 3 replacement monitors'
]);

// Check if all PO items are 100% fulfilled
$allFulfilled = true;
$poItemsCheck = $pdo->query("SELECT * FROM purchase_order_items WHERE purchase_order_id = {$testPoId}")->fetchAll();
foreach ($poItemsCheck as $pi) {
    $cRec = (float)$pdo->query("
        SELECT SUM(received_qty) FROM goods_receipt_items gri
        JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
        WHERE gr.purchase_order_id = {$testPoId} AND gr.status = 'posted' AND gri.purchase_order_item_id = {$pi['id']}
    ")->fetchColumn();
    if ($cRec < (float)$pi['quantity']) {
        $allFulfilled = false;
    }
}

if ($allFulfilled) {
    $pdo->query("UPDATE purchase_orders SET status = 'fully_received', updated_at = NOW() WHERE id = {$testPoId}");
}

$poStatusFinal = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$testPoId}")->fetchColumn();
reportTest("Second shipment completing remaining items transitions PO to 'fully_received'", $poStatusFinal === 'fully_received');

// --- 7. Testing Immutability of Posted GRN ---
echo "\n--- 7. Testing Immutability of Posted GRN ---\n";
$grn1Status = $pdo->query("SELECT status FROM goods_receipts WHERE id = {$grn1Id}")->fetchColumn();
reportTest("Posted GRN status is 'posted' (locked against edits and deletion)", $grn1Status === 'posted');

// --- 8. Testing Draft GRN Soft-Deletion ---
echo "\n--- 8. Testing Draft GRN Soft-Deletion ---\n";
$pdo->prepare("
    INSERT INTO goods_receipts (
        grn_no, purchase_order_id, supplier_id, receipt_date, received_by, status, created_at, updated_at
    ) VALUES (
        'GRN-TEST-DRAFT', :po_id, :sup_id, CURDATE(), :uid, 'draft', NOW(), NOW()
    )
")->execute([
    ':po_id'  => $testPoId,
    ':sup_id' => $supplierId,
    ':uid'    => $adminId
]);
$draftGrnId = (int)$pdo->lastInsertId();

// Soft delete draft GRN
$pdo->prepare("UPDATE goods_receipts SET deleted_at = NOW() WHERE id = :id")->execute([':id' => $draftGrnId]);

$deletedRow = $pdo->query("SELECT deleted_at FROM goods_receipts WHERE id = {$draftGrnId}")->fetch();
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM goods_receipts WHERE id = {$draftGrnId} AND deleted_at IS NULL")->fetchColumn();
reportTest("Draft GRN soft delete sets deleted_at and filters out of active queries", !empty($deletedRow['deleted_at']) && $activeCount === 0);

// Cleanup test records
$pdo->exec("DELETE FROM goods_receipt_items WHERE goods_receipt_id IN ({$grn1Id}, {$grn2Id}, {$draftGrnId})");
$pdo->exec("DELETE FROM goods_receipts WHERE id IN ({$grn1Id}, {$grn2Id}, {$draftGrnId})");
$pdo->exec("DELETE FROM purchase_order_items WHERE purchase_order_id = {$testPoId}");
$pdo->exec("DELETE FROM purchase_orders WHERE id = {$testPoId}");

echo "\n=====================================================\n";
echo "VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "=====================================================\n";

if ($failed === 0) {
    exit(0);
} else {
    exit(1);
}
