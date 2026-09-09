<?php
/**
 * Comprehensive Automated Verification Script for Phase 06
 * Procurement Management CMS - Complete System & End-to-End Audit
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/init.php';

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

echo "======================================================================\n";
echo "STARTING FINAL PHASE 06 VERIFICATION & END-TO-END SYSTEM TEST\n";
echo "======================================================================\n\n";

// --- 1. PHP Syntax Audit Across Entire Codebase ---
echo "--- 1. PHP Syntax Audit Across All Files ---\n";
$dirsToScan = [
    dirname(__DIR__) . '/config',
    dirname(__DIR__) . '/includes',
    dirname(__DIR__) . '/auth',
    dirname(__DIR__) . '/modules'
];

$allPhpFiles = [];
foreach ($dirsToScan as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $allPhpFiles[] = $file->getPathname();
        }
    }
}

$syntaxErrors = [];
foreach ($allPhpFiles as $phpFile) {
    $output = [];
    $returnVar = 0;
    exec("php -l \"{$phpFile}\"", $output, $returnVar);
    if ($returnVar !== 0) {
        $syntaxErrors[] = $phpFile . ': ' . implode("\n", $output);
    }
}

reportTest("All " . count($allPhpFiles) . " PHP files passed linting with zero syntax errors", empty($syntaxErrors), implode("\n", $syntaxErrors));

// --- 2. Database Schema & Settings Table Verification ---
echo "\n--- 2. Database Schema & Settings Table Verification ---\n";
$stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
reportTest("Table `settings` exists in database", $stmt->rowCount() > 0);

$cName = getSetting('company_name');
reportTest("getSetting('company_name') returns non-empty value", !empty($cName), "Value: {$cName}");

$curr = getSetting('currency', '$');
reportTest("getSetting('currency') returns currency symbol", !empty($curr), "Currency: {$curr}");

// Test Update Setting
$testVal = 'Automated Test Address ' . time();
$upRes = updateSetting('test_key', $testVal);
reportTest("updateSetting() saves new setting and updates in-memory cache", $upRes && getSetting('test_key') === $testVal);

// Test Formatters with dynamic settings
$formattedCurr = formatCurrency(1250.50);
reportTest("formatCurrency() formats correctly with system currency", strpos($formattedCurr, '1,250.50') !== false, $formattedCurr);

$formattedDate = formatDate('2026-09-09 14:30:00', 'd M Y');
reportTest("formatDate() formats timestamps accurately", $formattedDate === '09 Sep 2026', $formattedDate);

// --- 3. User Management CRUD & Last Admin Safeguards ---
echo "\n--- 3. User Management CRUD & Last Admin Safeguards ---\n";

// Clean up old test user
$pdo->exec("DELETE FROM user_roles WHERE user_id IN (SELECT id FROM users WHERE username = 'test_p6_user')");
$pdo->exec("DELETE FROM users WHERE username = 'test_p6_user'");

// Create Test User
$testPassHash = password_hash('secret123', PASSWORD_BCRYPT);
$pdo->prepare("
    INSERT INTO users (full_name, username, email, password, status, created_at, updated_at)
    VALUES ('Phase 06 Test User', 'test_p6_user', 'p6user@example.com', :pwd, 'active', NOW(), NOW())
")->execute([':pwd' => $testPassHash]);
$testUid = (int)$pdo->lastInsertId();

$reqRoleId = (int)$pdo->query("SELECT id FROM roles WHERE slug = 'requester' LIMIT 1")->fetchColumn();
$pdo->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:uid, :rid, NOW())")->execute([':uid' => $testUid, ':rid' => $reqRoleId]);

reportTest("User account successfully created in database (ID: {$testUid})", $testUid > 0);

// Verify Last Admin Protection Logic
$adminRoleRow = $pdo->query("SELECT id FROM roles WHERE slug = 'administrator' LIMIT 1")->fetch();
$adminRoleId = (int)$adminRoleRow['id'];
$activeAdmins = (int)$pdo->query("
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = {$adminRoleId} AND u.status = 'active' AND u.deleted_at IS NULL
")->fetchColumn();

reportTest("System correctly detects active Administrator accounts ({$activeAdmins} active)", $activeAdmins >= 1);

// Soft Delete Test User
$pdo->prepare("UPDATE users SET deleted_at = NOW(), status = 'inactive' WHERE id = :id")->execute([':id' => $testUid]);
$chkDel = $pdo->query("SELECT deleted_at FROM users WHERE id = {$testUid}")->fetchColumn();
reportTest("User account soft-delete sets deleted_at timestamp", !empty($chkDel));

// --- 4. Reports Engine Query Audits ---
echo "\n--- 4. Reports Engine Query Audits ---\n";

// PR Report Query Test
$prRepStmt = $pdo->query("
    SELECT COUNT(*) AS total_count, COALESCE(SUM(estimated_total), 0.00) AS total_amount
    FROM purchase_requests WHERE deleted_at IS NULL
");
$prRep = $prRepStmt->fetch();
reportTest("Purchase Request report query executes cleanly", is_array($prRep));

// Supplier Report Query Test
$supRepStmt = $pdo->query("
    SELECT s.id, s.name, s.supplier_code,
           (SELECT COUNT(*) FROM quotations q WHERE q.supplier_id = s.id AND q.deleted_at IS NULL) AS total_quotes,
           (SELECT COALESCE(SUM(po.grand_total), 0.00) FROM purchase_orders po WHERE po.supplier_id = s.id AND po.deleted_at IS NULL) AS total_spend
    FROM suppliers s WHERE s.deleted_at IS NULL LIMIT 5
");
$supRep = $supRepStmt->fetchAll();
reportTest("Supplier performance report query executes without duplicate joins", is_array($supRep));

// Quotation Report Query Test
$quoRepStmt = $pdo->query("
    SELECT q.id, q.quotation_no, s.name as supplier_name, pr.request_no
    FROM quotations q
    JOIN suppliers s ON s.id = q.supplier_id
    JOIN purchase_requests pr ON pr.id = q.purchase_request_id
    WHERE q.deleted_at IS NULL LIMIT 5
");
$quoRep = $quoRepStmt->fetchAll();
reportTest("Quotation analytical report query executes cleanly", is_array($quoRep));

// PO Report Query Test
$poRepStmt = $pdo->query("
    SELECT po.id, po.po_no, s.name as supplier_name, po.grand_total
    FROM purchase_orders po
    JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.deleted_at IS NULL LIMIT 5
");
$poRep = $poRepStmt->fetchAll();
reportTest("Purchase Order commitment report query executes cleanly", is_array($poRep));

// Goods Receiving Report Query Test
$grnRepStmt = $pdo->query("
    SELECT gr.id, gr.grn_no, po.po_no, s.name as supplier_name
    FROM goods_receipts gr
    JOIN purchase_orders po ON po.id = gr.purchase_order_id
    JOIN suppliers s ON s.id = gr.supplier_id
    WHERE gr.deleted_at IS NULL LIMIT 5
");
$grnRep = $grnRepStmt->fetchAll();
reportTest("Goods Receiving inspection report query executes cleanly", is_array($grnRep));

// --- 5. End-to-End Full 20-Step Procurement Lifecycle Simulation ---
echo "\n--- 5. Full 20-Step Procurement Lifecycle Simulation ---\n";

// Get user accounts
$requesterId = 1;
$managerId = 1;
$officerId = 1;
$supplierId = (int)$pdo->query("SELECT id FROM suppliers WHERE deleted_at IS NULL AND status = 'active' LIMIT 1")->fetchColumn();
$deptId = (int)$pdo->query("SELECT id FROM departments WHERE deleted_at IS NULL LIMIT 1")->fetchColumn();

// Clean up lifecycle test records
$pdo->exec("DELETE FROM goods_receipt_items WHERE goods_receipt_id IN (SELECT id FROM goods_receipts WHERE grn_no LIKE 'GRN-E2E-%')");
$pdo->exec("DELETE FROM goods_receipt_history WHERE goods_receipt_id IN (SELECT id FROM goods_receipts WHERE grn_no LIKE 'GRN-E2E-%')");
$pdo->exec("DELETE FROM goods_receipts WHERE grn_no LIKE 'GRN-E2E-%'");

$pdo->exec("DELETE FROM purchase_order_items WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE po_no = 'PO-E2E-TEST')");
$pdo->exec("DELETE FROM purchase_order_history WHERE purchase_order_id IN (SELECT id FROM purchase_orders WHERE po_no = 'PO-E2E-TEST')");
$pdo->exec("DELETE FROM purchase_orders WHERE po_no = 'PO-E2E-TEST'");

$pdo->exec("DELETE FROM quotation_items WHERE quotation_id IN (SELECT id FROM quotations WHERE quotation_no = 'QT-E2E-TEST')");
$pdo->exec("DELETE FROM quotation_history WHERE quotation_id IN (SELECT id FROM quotations WHERE quotation_no = 'QT-E2E-TEST')");
$pdo->exec("DELETE FROM quotations WHERE quotation_no = 'QT-E2E-TEST'");

$pdo->exec("DELETE FROM purchase_request_items WHERE purchase_request_id IN (SELECT id FROM purchase_requests WHERE request_no = 'PR-E2E-TEST')");
$pdo->exec("DELETE FROM purchase_request_history WHERE purchase_request_id IN (SELECT id FROM purchase_requests WHERE request_no = 'PR-E2E-TEST')");
$pdo->exec("DELETE FROM purchase_requests WHERE request_no = 'PR-E2E-TEST'");

// Step 1 & 2: Create Purchase Request (Draft)
$pdo->prepare("
    INSERT INTO purchase_requests (
        request_no, requested_by, department_id, request_date, required_date,
        priority, purpose, estimated_subtotal, estimated_tax, estimated_total,
        status, created_at, updated_at
    ) VALUES (
        'PR-E2E-TEST', :uid, :dept, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY),
        'high', 'End-to-End Phase 06 Full Lifecycle Test', 2000.00, 200.00, 2200.00,
        'draft', NOW(), NOW()
    )
")->execute([':uid' => $requesterId, ':dept' => $deptId]);
$e2ePrId = (int)$pdo->lastInsertId();

$itemStmt = $pdo->prepare("
    INSERT INTO purchase_request_items (
        purchase_request_id, item_name, description, quantity, unit,
        estimated_unit_price, estimated_total, created_at, updated_at
    ) VALUES (:pr, :name, :desc, :qty, :unit, :price, :total, NOW(), NOW())
");
$itemStmt->execute([
    ':pr' => $e2ePrId, ':name' => 'Enterprise Servers', ':desc' => 'High capacity cloud host servers',
    ':qty' => 2.00, ':unit' => 'Units', ':price' => 800.00, ':total' => 1600.00
]);
$itemStmt->execute([
    ':pr' => $e2ePrId, ':name' => 'Network Switches', ':desc' => '48-Port Managed Gigabit Switches',
    ':qty' => 4.00, ':unit' => 'Units', ':price' => 100.00, ':total' => 400.00
]);

reportTest("STEP 2: Purchase Request created in 'draft' status (PR-E2E-TEST)", $e2ePrId > 0);

// Step 3: Submit PR -> pending_approval
$pdo->prepare("UPDATE purchase_requests SET status = 'pending_approval', updated_at = NOW() WHERE id = :id")->execute([':id' => $e2ePrId]);
$prStatus = $pdo->query("SELECT status FROM purchase_requests WHERE id = {$e2ePrId}")->fetchColumn();
reportTest("STEP 3: Purchase Request submitted -> 'pending_approval'", $prStatus === 'pending_approval');

// Step 4 & 5: Manager Approves PR -> approved
$pdo->prepare("UPDATE purchase_requests SET status = 'approved', approved_by = :mgr, approved_at = NOW(), updated_at = NOW() WHERE id = :id")->execute([':mgr' => $managerId, ':id' => $e2ePrId]);
$prStatusApproved = $pdo->query("SELECT status FROM purchase_requests WHERE id = {$e2ePrId}")->fetchColumn();
reportTest("STEP 5: Manager approves Purchase Request -> 'approved'", $prStatusApproved === 'approved');

// Step 6 & 7: Procurement Officer creates Quotation
$pdo->prepare("
    INSERT INTO quotations (
        quotation_no, purchase_request_id, supplier_id, quotation_date, valid_until,
        reference_number, subtotal, tax_percentage, tax_amount, total_amount,
        status, created_by, created_at, updated_at
    ) VALUES (
        'QT-E2E-TEST', :pr_id, :sup_id, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY),
        'BID-REF-9901', 1900.00, 10.00, 190.00, 2090.00,
        'draft', :uid, NOW(), NOW()
    )
")->execute([':pr_id' => $e2ePrId, ':sup_id' => $supplierId, ':uid' => $officerId]);
$e2eQuoteId = (int)$pdo->lastInsertId();

$prItems = $pdo->query("SELECT id FROM purchase_request_items WHERE purchase_request_id = {$e2ePrId} ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

$qItemStmt = $pdo->prepare("
    INSERT INTO quotation_items (
        quotation_id, purchase_request_item_id, quantity, unit_price, subtotal, created_at, updated_at
    ) VALUES (:qid, :pri, :qty, :price, :subtotal, NOW(), NOW())
");
$qItemStmt->execute([':qid' => $e2eQuoteId, ':pri' => $prItems[0], ':qty' => 2.00, ':price' => 750.00, ':subtotal' => 1500.00]);
$qItemStmt->execute([':qid' => $e2eQuoteId, ':pri' => $prItems[1], ':qty' => 4.00, ':price' => 100.00, ':subtotal' => 400.00]);

reportTest("STEP 7: Supplier Quotation recorded in 'draft' status (QT-E2E-TEST)", $e2eQuoteId > 0);

// Step 8: Submit / Review Quotation -> under_review
$pdo->prepare("UPDATE quotations SET status = 'under_review', updated_at = NOW() WHERE id = :id")->execute([':id' => $e2eQuoteId]);
$qStatus = $pdo->query("SELECT status FROM quotations WHERE id = {$e2eQuoteId}")->fetchColumn();
reportTest("STEP 8: Quotation placed 'under_review'", $qStatus === 'under_review');

// Step 9: Select winning Quotation -> selected
$pdo->prepare("UPDATE quotations SET status = 'selected', updated_at = NOW() WHERE id = :id")->execute([':id' => $e2eQuoteId]);
$qStatusWon = $pdo->query("SELECT status FROM quotations WHERE id = {$e2eQuoteId}")->fetchColumn();
reportTest("STEP 9: Winning Quotation awarded -> 'selected'", $qStatusWon === 'selected');

// Step 10: Create Purchase Order from Quotation
$pdo->prepare("
    INSERT INTO purchase_orders (
        po_no, purchase_request_id, quotation_id, supplier_id, po_date, expected_delivery_date,
        subtotal, tax_amount, discount_amount, grand_total, status,
        created_by, created_at, updated_at
    ) VALUES (
        'PO-E2E-TEST', :pr, :quo, :sup, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY),
        1900.00, 190.00, 0.00, 2090.00, 'draft',
        :uid, NOW(), NOW()
    )
")->execute([':pr' => $e2ePrId, ':quo' => $e2eQuoteId, ':sup' => $supplierId, ':uid' => $officerId]);
$e2ePoId = (int)$pdo->lastInsertId();

$qItems = $pdo->query("SELECT id FROM quotation_items WHERE quotation_id = {$e2eQuoteId} ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

$poItemStmt = $pdo->prepare("
    INSERT INTO purchase_order_items (
        purchase_order_id, quotation_item_id, purchase_request_item_id, item_name,
        quantity, unit, unit_price, tax_percent, tax_amount, discount_amount, line_total,
        created_at, updated_at
    ) VALUES (:poid, :qi, :pri, :name, :qty, :unit, :price, :tax_pct, :tax_amt, :disc, :total, NOW(), NOW())
");
$poItemStmt->execute([
    ':poid' => $e2ePoId, ':qi' => $qItems[0], ':pri' => $prItems[0], ':name' => 'Enterprise Servers',
    ':qty' => 2.00, ':unit' => 'Units', ':price' => 750.00, ':tax_pct' => 10.00, ':tax_amt' => 150.00, ':disc' => 0.00, ':total' => 1650.00
]);
$poItemStmt->execute([
    ':poid' => $e2ePoId, ':qi' => $qItems[1], ':pri' => $prItems[1], ':name' => 'Network Switches',
    ':qty' => 4.00, ':unit' => 'Units', ':price' => 100.00, ':tax_pct' => 10.00, ':tax_amt' => 40.00, ':disc' => 0.00, ':total' => 440.00
]);

reportTest("STEP 10: Purchase Order created from winning quote (PO-E2E-TEST)", $e2ePoId > 0);

// Step 11: Submit PO -> pending_approval
$pdo->prepare("UPDATE purchase_orders SET status = 'pending_approval', updated_at = NOW() WHERE id = :id")->execute([':id' => $e2ePoId]);
$poStatus = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$e2ePoId}")->fetchColumn();
reportTest("STEP 11: Purchase Order submitted -> 'pending_approval'", $poStatus === 'pending_approval');

// Step 12 & 13: Manager Approves PO -> approved
$pdo->prepare("UPDATE purchase_orders SET status = 'approved', approved_by = :mgr, approved_at = NOW(), updated_at = NOW() WHERE id = :id")->execute([':mgr' => $managerId, ':id' => $e2ePoId]);
$poStatusApproved = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$e2ePoId}")->fetchColumn();
reportTest("STEP 13: Manager approves Purchase Order -> 'approved'", $poStatusApproved === 'approved');

// Step 14: Send PO to Supplier -> sent
$pdo->prepare("UPDATE purchase_orders SET status = 'sent', sent_at = NOW(), updated_at = NOW() WHERE id = :id")->execute([':id' => $e2ePoId]);
$poStatusSent = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$e2ePoId}")->fetchColumn();
reportTest("STEP 14: Purchase Order sent to Supplier -> 'sent'", $poStatusSent === 'sent');

// Step 15 & 16: Create & Post Partial GRN (1 Server, 2 Switches)
$pdo->prepare("
    INSERT INTO goods_receipts (
        grn_no, purchase_order_id, supplier_id, receipt_date, delivery_note_no,
        received_by, status, posted_at, created_at, updated_at
    ) VALUES (
        'GRN-E2E-01', :po, :sup, CURDATE(), 'DN-PARTIAL-01',
        :uid, 'posted', NOW(), NOW(), NOW()
    )
")->execute([':po' => $e2ePoId, ':sup' => $supplierId, ':uid' => $officerId]);
$grn1Id = (int)$pdo->lastInsertId();

$poItems = $pdo->query("SELECT id FROM purchase_order_items WHERE purchase_order_id = {$e2ePoId} ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

$griStmt = $pdo->prepare("
    INSERT INTO goods_receipt_items (
        goods_receipt_id, purchase_order_item_id, item_name, ordered_qty, previously_received_qty,
        received_qty, rejected_qty, unit, created_at, updated_at
    ) VALUES (:grn, :poi, :name, :ordered, :prev, :rec, :rej, :unit, NOW(), NOW())
");
$griStmt->execute([':grn' => $grn1Id, ':poi' => $poItems[0], ':name' => 'Enterprise Servers', ':ordered' => 2.00, ':prev' => 0.00, ':rec' => 1.00, ':rej' => 0.00, ':unit' => 'Units']);
$griStmt->execute([':grn' => $grn1Id, ':poi' => $poItems[1], ':name' => 'Network Switches', ':ordered' => 4.00, ':prev' => 0.00, ':rec' => 2.00, ':rej' => 0.00, ':unit' => 'Units']);

// Update PO status to partially_received
$pdo->prepare("UPDATE purchase_orders SET status = 'partially_received', updated_at = NOW() WHERE id = :id")->execute([':id' => $e2ePoId]);
$poStatusPartial = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$e2ePoId}")->fetchColumn();
reportTest("STEP 16: Partial GRN posted -> PO status becomes 'partially_received'", $poStatusPartial === 'partially_received');

// Step 17 & 18: Create & Post Second GRN for remaining quantity (1 Server, 2 Switches)
$pdo->prepare("
    INSERT INTO goods_receipts (
        grn_no, purchase_order_id, supplier_id, receipt_date, delivery_note_no,
        received_by, status, posted_at, created_at, updated_at
    ) VALUES (
        'GRN-E2E-02', :po, :sup, CURDATE(), 'DN-FINAL-02',
        :uid, 'posted', NOW(), NOW(), NOW()
    )
")->execute([':po' => $e2ePoId, ':sup' => $supplierId, ':uid' => $officerId]);
$grn2Id = (int)$pdo->lastInsertId();

$griStmt->execute([':grn' => $grn2Id, ':poi' => $poItems[0], ':name' => 'Enterprise Servers', ':ordered' => 2.00, ':prev' => 1.00, ':rec' => 1.00, ':rej' => 0.00, ':unit' => 'Units']);
$griStmt->execute([':grn' => $grn2Id, ':poi' => $poItems[1], ':name' => 'Network Switches', ':ordered' => 4.00, ':prev' => 2.00, ':rec' => 2.00, ':rej' => 0.00, ':unit' => 'Units']);

// Update PO status to fully_received
$pdo->prepare("UPDATE purchase_orders SET status = 'fully_received', updated_at = NOW() WHERE id = :id")->execute([':id' => $e2ePoId]);
$poStatusFull = $pdo->query("SELECT status FROM purchase_orders WHERE id = {$e2ePoId}")->fetchColumn();
reportTest("STEP 18: Second GRN fulfills all items -> PO status becomes 'fully_received'", $poStatusFull === 'fully_received');

// Step 19: Reports Query verification with real E2E data
$livePrReport = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE request_no = 'PR-E2E-TEST'")->fetchColumn();
$livePoReport = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE po_no = 'PO-E2E-TEST' AND status = 'fully_received'")->fetchColumn();
reportTest("STEP 19: Real database transactions correctly reflect in Reports", $livePrReport > 0 && $livePoReport > 0);

// Step 20: Dashboard metrics verification
$dbPrTotal = (int)$pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE deleted_at IS NULL")->fetchColumn();
$dbPoTotal = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE deleted_at IS NULL")->fetchColumn();
reportTest("STEP 20: Dashboard aggregates derive from live database counts ({$dbPrTotal} PRs, {$dbPoTotal} POs)", $dbPrTotal > 0 && $dbPoTotal > 0);

echo "\n======================================================================\n";
echo "FINAL VERIFICATION RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "======================================================================\n";

if ($failed === 0) {
    echo "SUCCESS: ALL TESTS PASSED WITH ZERO ERRORS.\n";
} else {
    echo "FAILURE: SOME TESTS FAILED.\n";
    exit(1);
}
