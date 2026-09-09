<?php
/**
 * Test Clean Sequential Import of All 6 SQL Schemas
 */
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDb();
$schemaFiles = [
    '01_auth_schema.sql',
    '02_purchase_requests_schema.sql',
    '03_suppliers_quotations_schema.sql',
    '04_purchase_orders_schema.sql',
    '05_goods_receiving_schema.sql',
    '06_reports_settings_schema.sql'
];

echo "=====================================================\n";
echo "TESTING CLEAN SEQUENTIAL IMPORT OF ALL 6 SQL SCHEMAS\n";
echo "=====================================================\n";

foreach ($schemaFiles as $file) {
    $filePath = dirname(__DIR__) . '/database/' . $file;
    if (!file_exists($filePath)) {
        echo "[FAIL] Missing schema file: {$file}\n";
        exit(1);
    }
    $sql = file_get_contents($filePath);
    try {
        $pdo->exec($sql);
        echo "[PASS] Successfully imported: {$file}\n";
    } catch (Exception $e) {
        echo "[FAIL] Error importing {$file}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n--- Verifying All Expected Tables Exist ---\n";
$expectedTables = [
    'roles', 'users', 'user_roles', 'password_resets', 'activity_logs',
    'departments', 'purchase_requests', 'purchase_request_items', 'purchase_request_history',
    'suppliers', 'supplier_contacts', 'quotations', 'quotation_items', 'quotation_history',
    'purchase_orders', 'purchase_order_items', 'purchase_order_history',
    'goods_receipts', 'goods_receipt_items', 'goods_receipt_history',
    'settings'
];

$allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$missingTables = array_diff($expectedTables, $allTables);
if (empty($missingTables)) {
    echo "[PASS] All " . count($expectedTables) . " tables exist in database after clean import.\n";
    echo "=====================================================\n";
    echo "ALL 6 SCHEMAS IMPORTED CLEANLY WITH ZERO FK ERRORS!\n";
    echo "=====================================================\n";
} else {
    echo "[FAIL] Missing tables: " . implode(', ', $missingTables) . "\n";
    exit(1);
}
