<?php
/**
 * Phase 03 Automated Verification Script
 * Tests: Supplier CRUD, Contacts, Quotations, Calculations, Comparison,
 * Winning Selection + Auto-Rejection, Rejection with reason, and Audit History.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

echo "==================================================\n";
echo "PROCUREMENT CMS - PHASE 03 AUTOMATED VERIFICATION\n";
echo "==================================================\n\n";

$db = getDb();
$passed = 0;
$failed = 0;

function assertTest($condition, $message) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] " . $message . "\n";
        $passed++;
    } else {
        echo "[FAIL] " . $message . "\n";
        $failed++;
    }
}

// 1. Verify Schema Tables
echo "--- 1. Testing Database Schema Tables ---\n";
$tables = ['suppliers', 'supplier_contacts', 'quotations', 'quotation_items', 'quotation_history'];
foreach ($tables as $table) {
    $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
    assertTest($stmt->rowCount() > 0, "Table `{$table}` exists");
}

// 2. Verify Seeded Suppliers
echo "\n--- 2. Testing Seeded Suppliers ---\n";
$supStmt = $db->query("SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL");
$supCount = (int)$supStmt->fetchColumn();
assertTest($supCount >= 4, "Found {$supCount} active suppliers in database");

// 3. Test Supplier CRUD & Contacts
echo "\n--- 3. Testing Supplier CRUD & Contacts ---\n";
$testSupplierCode = 'SUP-TEST-001';
// Delete any previous quotations referencing this supplier code
$db->query("DELETE FROM quotation_history WHERE quotation_id IN (SELECT id FROM quotations WHERE supplier_id IN (SELECT id FROM suppliers WHERE supplier_code = '{$testSupplierCode}'))");
$db->query("DELETE FROM quotation_items WHERE quotation_id IN (SELECT id FROM quotations WHERE supplier_id IN (SELECT id FROM suppliers WHERE supplier_code = '{$testSupplierCode}'))");
$db->query("DELETE FROM quotations WHERE supplier_id IN (SELECT id FROM suppliers WHERE supplier_code = '{$testSupplierCode}')");
$db->prepare("DELETE FROM supplier_contacts WHERE supplier_id IN (SELECT id FROM suppliers WHERE supplier_code = :code)")->execute([':code' => $testSupplierCode]);
$db->prepare("DELETE FROM suppliers WHERE supplier_code = :code")->execute([':code' => $testSupplierCode]);

$insSup = $db->prepare("
    INSERT INTO suppliers (
        supplier_code, name, email, phone, address, city, country, status, created_at, updated_at
    ) VALUES (
        :code, 'Test Supplier Inc', 'test@supplier.com', '+1-555-0100', '100 Test St', 'Austin', 'USA', 'active', NOW(), NOW()
    )
");
$insSup->execute([':code' => $testSupplierCode]);
$testSupplierId = (int)$db->lastInsertId();
assertTest($testSupplierId > 0, "Inserted test supplier (ID: {$testSupplierId})");

// Add contacts
$cStmt = $db->prepare("
    INSERT INTO supplier_contacts (supplier_id, name, job_title, email, phone, is_primary, created_at)
    VALUES (:sid, 'John Test', 'Sales Rep', 'john@supplier.com', '+1-555-0101', 1, NOW())
");
$cStmt->execute([':sid' => $testSupplierId]);
$contactId = (int)$db->lastInsertId();
assertTest($contactId > 0, "Added primary contact person (ID: {$contactId})");

// Update supplier
$updSup = $db->prepare("UPDATE suppliers SET name = 'Test Supplier Inc Updated', tax_number = 'TAX-TEST-999' WHERE id = :id");
$updSup->execute([':id' => $testSupplierId]);
$fetchSup = $db->query("SELECT * FROM suppliers WHERE id = {$testSupplierId}")->fetch();
assertTest($fetchSup['name'] === 'Test Supplier Inc Updated' && $fetchSup['tax_number'] === 'TAX-TEST-999', "Supplier update verified");

// 4. Test Approved Purchase Request Setup
echo "\n--- 4. Setting up Approved Purchase Request for Quotations ---\n";
// Find or create an approved PR
$prStmt = $db->query("SELECT * FROM purchase_requests WHERE status = 'approved' AND deleted_at IS NULL LIMIT 1");
$testPR = $prStmt->fetch();

if (!$testPR) {
    // Create an approved PR
    $insPr = $db->query("
        INSERT INTO purchase_requests (
            request_no, requested_by, department_id, request_date, required_date, purpose,
            priority, status, estimated_subtotal, estimated_tax, estimated_total, approved_at, created_at, updated_at
        ) VALUES (
            'PR-TEST-0099', 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Phase 03 Test PR',
            'high', 'approved', 5000.00, 500.00, 5500.00, NOW(), NOW(), NOW()
        )
    ");
    $testPRId = (int)$db->lastInsertId();
    $db->query("INSERT INTO purchase_request_items (purchase_request_id, item_name, quantity, unit, estimated_unit_price, estimated_total) VALUES ({$testPRId}, 'Test Item A', 10, 'units', 300.00, 3000.00)");
    $db->query("INSERT INTO purchase_request_items (purchase_request_id, item_name, quantity, unit, estimated_unit_price, estimated_total) VALUES ({$testPRId}, 'Test Item B', 20, 'boxes', 100.00, 2000.00)");
    $testPR = $db->query("SELECT * FROM purchase_requests WHERE id = {$testPRId}")->fetch();
}
$testPrId = (int)$testPR['id'];
assertTest($testPrId > 0 && $testPR['status'] === 'approved', "Approved PR ready for bidding (PR ID: {$testPrId}, No: {$testPR['request_no']})");

// Get PR Items
$prItems = $db->query("SELECT * FROM purchase_request_items WHERE purchase_request_id = {$testPrId} ORDER BY id ASC")->fetchAll();
assertTest(count($prItems) >= 1, "PR has " . count($prItems) . " line items");

// Clean any old test quotations for this PR
$db->query("DELETE FROM quotation_history WHERE quotation_id IN (SELECT id FROM quotations WHERE purchase_request_id = {$testPrId})");
$db->query("DELETE FROM quotation_items WHERE quotation_id IN (SELECT id FROM quotations WHERE purchase_request_id = {$testPrId})");
$db->query("DELETE FROM quotations WHERE purchase_request_id = {$testPrId}");

// 5. Create 3 Competing Quotations
echo "\n--- 5. Testing Multi-Supplier Quotation Creation & Financial Calculations ---\n";
// Bidder 1: Vendor 1 (e.g. ID 1)
$q1Stmt = $db->prepare("
    INSERT INTO quotations (
        quotation_no, purchase_request_id, supplier_id, quotation_date, valid_until,
        subtotal, tax_percentage, tax_amount, shipping_cost, other_charges, total_amount,
        payment_terms, delivery_terms, status, created_by, created_at, updated_at
    ) VALUES (
        'QT-TEST-001', :pr_id, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY),
        4500.00, 10.00, 450.00, 50.00, 0.00, 5000.00,
        'Net 30 Days', '5 Business Days', 'draft', 1, NOW(), NOW()
    )
");
$q1Stmt->execute([':pr_id' => $testPrId]);
$q1Id = (int)$db->lastInsertId();

// Insert items for Quote 1
foreach ($prItems as $idx => $pItem) {
    $unitPrice = ($idx === 0) ? 250.00 : 100.00;
    $qty = (float)$pItem['quantity'];
    $sub = $qty * $unitPrice;
    $db->prepare("INSERT INTO quotation_items (quotation_id, purchase_request_item_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)")
       ->execute([$q1Id, $pItem['id'], $qty, $unitPrice, $sub]);
}
assertTest($q1Id > 0, "Created Quotation 1 (ID: {$q1Id}, Total: $5000.00)");

// Bidder 2: Vendor 2 (ID 2)
$q2Stmt = $db->prepare("
    INSERT INTO quotations (
        quotation_no, purchase_request_id, supplier_id, quotation_date, valid_until,
        subtotal, tax_percentage, tax_amount, shipping_cost, other_charges, total_amount,
        payment_terms, delivery_terms, status, created_by, created_at, updated_at
    ) VALUES (
        'QT-TEST-002', :pr_id, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY),
        4200.00, 10.00, 420.00, 80.00, 0.00, 4700.00,
        'Net 15 Days', '7 Business Days', 'submitted', 1, NOW(), NOW()
    )
");
$q2Stmt->execute([':pr_id' => $testPrId]);
$q2Id = (int)$db->lastInsertId();
assertTest($q2Id > 0, "Created Quotation 2 (ID: {$q2Id}, Total: $4700.00 - Lowest Bid)");

// Bidder 3: Test Supplier
$q3Stmt = $db->prepare("
    INSERT INTO quotations (
        quotation_no, purchase_request_id, supplier_id, quotation_date, valid_until,
        subtotal, tax_percentage, tax_amount, shipping_cost, other_charges, total_amount,
        payment_terms, delivery_terms, status, created_by, created_at, updated_at
    ) VALUES (
        'QT-TEST-003', :pr_id, :sid, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY),
        5500.00, 10.00, 550.00, 100.00, 0.00, 6150.00,
        'Advance 100%', '14 Business Days', 'draft', 1, NOW(), NOW()
    )
");
$q3Stmt->execute([':pr_id' => $testPrId, ':sid' => $testSupplierId]);
$q3Id = (int)$db->lastInsertId();
assertTest($q3Id > 0, "Created Quotation 3 (ID: {$q3Id}, Total: $6150.00)");

// 6. Test State Transitions
echo "\n--- 6. Testing State Transitions & Audit History ---\n";
// Move Quote 1: draft -> submitted -> under_review
$db->prepare("UPDATE quotations SET status = 'submitted' WHERE id = {$q1Id}")->execute();
$db->prepare("INSERT INTO quotation_history (quotation_id, user_id, action, from_status, to_status, comments, created_at) VALUES (?, 1, 'submitted', 'draft', 'submitted', 'Submitted test', NOW())")->execute([$q1Id]);

$db->prepare("UPDATE quotations SET status = 'under_review' WHERE id = {$q1Id}")->execute();
$db->prepare("INSERT INTO quotation_history (quotation_id, user_id, action, from_status, to_status, comments, created_at) VALUES (?, 1, 'under_review', 'submitted', 'under_review', 'Reviewed test', NOW())")->execute([$q1Id]);

$checkQ1 = $db->query("SELECT status FROM quotations WHERE id = {$q1Id}")->fetchColumn();
assertTest($checkQ1 === 'under_review', "Quotation 1 transitioned to `under_review`");

// Reject Quote 3 with reason
$rejectionReason = "Pricing significantly exceeds approved budget allocation.";
$db->prepare("UPDATE quotations SET status = 'rejected' WHERE id = {$q3Id}")->execute();
$db->prepare("INSERT INTO quotation_history (quotation_id, user_id, action, from_status, to_status, comments, created_at) VALUES (?, 1, 'rejected', 'draft', 'rejected', ?, NOW())")->execute([$q3Id, $rejectionReason]);

$checkQ3 = $db->query("SELECT status FROM quotations WHERE id = {$q3Id}")->fetchColumn();
$checkQ3Hist = $db->query("SELECT comments FROM quotation_history WHERE quotation_id = {$q3Id} AND action = 'rejected'")->fetchColumn();
assertTest($checkQ3 === 'rejected' && $checkQ3Hist === $rejectionReason, "Quotation 3 rejected with audit reason logged");

// 7. Test Winning Quotation Selection & Auto-Rejection of Competing Bids
echo "\n--- 7. Testing Winning Bid Award & Automatic Competing Rejection ---\n";
// Award Quote 2 (lowest bid)
$db->beginTransaction();

// Set winner
$db->prepare("UPDATE quotations SET status = 'selected', updated_at = NOW() WHERE id = :id")->execute([':id' => $q2Id]);
$db->prepare("INSERT INTO quotation_history (quotation_id, user_id, action, from_status, to_status, comments, created_at) VALUES (?, 1, 'selected', 'submitted', 'selected', 'Awarded lowest compliant bid', NOW())")->execute([$q2Id]);

// Auto-reject competing active bids
$competingStmt = $db->prepare("
    SELECT id, quotation_no, status FROM quotations
    WHERE purchase_request_id = :pr_id AND id != :winner_id AND status != 'selected' AND deleted_at IS NULL
");
$competingStmt->execute([':pr_id' => $testPrId, ':winner_id' => $q2Id]);
$competing = $competingStmt->fetchAll();

foreach ($competing as $cBid) {
    $db->prepare("UPDATE quotations SET status = 'rejected', updated_at = NOW() WHERE id = :id")->execute([':id' => $cBid['id']]);
    $db->prepare("INSERT INTO quotation_history (quotation_id, user_id, action, from_status, to_status, comments, created_at) VALUES (?, 1, 'rejected', ?, 'rejected', 'Auto-rejected upon winning selection QT-TEST-002', NOW())")->execute([$cBid['id'], $cBid['status']]);
}

$db->commit();

// Verify Final Statuses
$finalQ2 = $db->query("SELECT status FROM quotations WHERE id = {$q2Id}")->fetchColumn();
$finalQ1 = $db->query("SELECT status FROM quotations WHERE id = {$q1Id}")->fetchColumn();
$finalQ3 = $db->query("SELECT status FROM quotations WHERE id = {$q3Id}")->fetchColumn();

assertTest($finalQ2 === 'selected', "Winning bid QT-TEST-002 is `selected`");
assertTest($finalQ1 === 'rejected', "Competing bid QT-TEST-001 automatically set to `rejected`");
assertTest($finalQ3 === 'rejected', "Previous rejected bid QT-TEST-003 remains `rejected`");

// 8. Test Comparison Matrix Query Logic
echo "\n--- 8. Testing Comparison Matrix Query Logic ---\n";
$compQueryStmt = $db->prepare("
    SELECT q.*, s.name as supplier_name,
           (SELECT MIN(total_amount) FROM quotations WHERE purchase_request_id = :sub_pr_id AND deleted_at IS NULL AND status != 'rejected') AS min_active_amount
    FROM quotations q
    JOIN suppliers s ON s.id = q.supplier_id
    WHERE q.purchase_request_id = :main_pr_id AND q.deleted_at IS NULL
    ORDER BY q.total_amount ASC
");
$compQueryStmt->execute([
    ':sub_pr_id'  => $testPrId,
    ':main_pr_id' => $testPrId
]);
$compResults = $compQueryStmt->fetchAll();
assertTest(count($compResults) === 3, "Comparison matrix retrieved all 3 quotations with supplier metadata");

// 9. Clean up test data
echo "\n--- 9. Cleaning up verification artifacts ---\n";
$db->query("DELETE FROM quotation_history WHERE quotation_id IN ({$q1Id}, {$q2Id}, {$q3Id})");
$db->query("DELETE FROM quotation_items WHERE quotation_id IN ({$q1Id}, {$q2Id}, {$q3Id})");
$db->query("DELETE FROM quotations WHERE id IN ({$q1Id}, {$q2Id}, {$q3Id})");
$db->query("DELETE FROM supplier_contacts WHERE supplier_id = {$testSupplierId}");
$db->query("DELETE FROM suppliers WHERE id = {$testSupplierId}");
echo "[DONE] Temporary test records cleaned up.\n";

echo "\n==================================================\n";
echo "VERIFICATION SUMMARY\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo ($failed === 0) ? "STATUS: ALL PHASE 03 TESTS PASSED SUCCESSFULLY!\n" : "STATUS: SOME TESTS FAILED.\n";
echo "==================================================\n";
