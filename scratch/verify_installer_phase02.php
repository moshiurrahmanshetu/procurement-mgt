<?php
/**
 * Automated Verification Suite — Installer Final Phase 02
 * Procurement Management CMS
 */

// Start session before output
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

echo "=======================================================\n";
echo "INSTALLER FINAL PHASE 02 — END-TO-END VERIFICATION\n";
echo "=======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $description, bool $condition, string $details = ''): void
{
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo " [FAIL] " . $description . " — " . $details . "\n";
        $failCount++;
    }
}

$root = dirname(__DIR__);

// 1. Lint all installer and key modified files
$filesToLint = [
    'installer/includes/installer-functions.php',
    'installer/includes/requirements.php',
    'installer/includes/installer-header.php',
    'installer/includes/installer-footer.php',
    'installer/index.php',
    'installer/database.php',
    'installer/import.php',
    'installer/admin.php',
    'installer/finalize.php',
    'installer/complete.php',
    'index.php',
    'auth/login.php'
];

foreach ($filesToLint as $relPath) {
    $fullPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnVar);
    $syntaxOk = ($returnVar === 0 && strpos(implode(' ', $output), 'No syntax errors detected') !== false);
    assertTest("Syntax lint: " . $relPath, $syntaxOk, implode(' ', $output));
}

// 2. Test Master SQL Schema File (database/install.sql)
$installSqlPath = $root . '/database/install.sql';
assertTest("database/install.sql exists", file_exists($installSqlPath));

$sqlContent = file_get_contents($installSqlPath);
assertTest("database/install.sql is not empty", strlen($sqlContent) > 1000);
assertTest("database/install.sql does not contain default development admin plaintext password", strpos($sqlContent, 'admin123') === false);
assertTest("database/install.sql does not hardcode CREATE DATABASE or USE procurement_mgt", stripos($sqlContent, 'CREATE DATABASE') === false && stripos($sqlContent, 'USE `procurement_mgt`') === false);

// 3. Test Master SQL Import on a Clean Test Database
require_once $root . '/installer/includes/installer-functions.php';

// Connect to MySQL server and create a temporary test database
$host = '127.0.0.1';
$port = 3306;
$testDbName = 'procure_test_installer_' . time();
$user = 'root';
$pass = '';

try {
    $serverPdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $serverPdo->exec("CREATE DATABASE `$testDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $testPdo = new PDO("mysql:host=$host;port=$port;dbname=$testDbName", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    assertTest("Temporary test database `$testDbName` created", true);
} catch (Exception $e) {
    assertTest("Temporary test database creation", false, $e->getMessage());
    exit(1);
}

// 4. Test installer_execute_sql()
$importResult = installer_execute_sql($testPdo, $sqlContent);
assertTest("installer_execute_sql() succeeded on clean database", $importResult['success'] === true, $importResult['error'] ?? '');
assertTest("Executed statements count > 20", $importResult['executed_count'] > 20, "Executed: " . $importResult['executed_count']);

// 5. Verify All 21 Tables Created in Test Database
$tablesStmt = $testPdo->query("SHOW TABLES");
$createdTables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

$expectedTables = [
    'roles', 'users', 'user_roles', 'password_resets', 'activity_logs',
    'departments', 'purchase_requests', 'purchase_request_items', 'purchase_request_history',
    'suppliers', 'supplier_contacts', 'quotations', 'quotation_items', 'quotation_history',
    'purchase_orders', 'purchase_order_items', 'purchase_order_history',
    'goods_receipts', 'goods_receipt_items', 'goods_receipt_history',
    'settings'
];

$allTablesPresent = true;
foreach ($expectedTables as $tbl) {
    if (!in_array($tbl, $createdTables)) {
        $allTablesPresent = false;
        echo "Missing table: $tbl\n";
    }
}
assertTest("All 21 tables created in target database", $allTablesPresent, "Found " . count($createdTables) . " tables");

// 6. Verify Seed Data (Roles, Departments, Settings)
$rolesCount = (int)$testPdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
assertTest("Seed roles inserted (4 roles)", $rolesCount === 4, "Found: $rolesCount");

$deptCount = (int)$testPdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
assertTest("Seed departments inserted (6 departments)", $deptCount === 6, "Found: $deptCount");

$settingsCount = (int)$testPdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
assertTest("Seed settings inserted (8 settings)", $settingsCount === 8, "Found: $settingsCount");

$usersCount = (int)$testPdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
assertTest("Zero users exist before installer admin step", $usersCount === 0, "Found: $usersCount");

// 7. Test installer_create_admin()
$adminResult = installer_create_admin(
    $testPdo,
    'Marketplace Super Admin',
    'superadmin',
    'superadmin@example.com',
    'SuperSecretPassword123!'
);
assertTest("installer_create_admin() created administrator user", $adminResult['success'] === true, $adminResult['message']);

$adminUser = $testPdo->query("SELECT * FROM users WHERE username = 'superadmin'")->fetch(PDO::FETCH_ASSOC);
assertTest("Admin record exists with hashed password", $adminUser && password_verify('SuperSecretPassword123!', $adminUser['password']));

$adminRoleCheck = $testPdo->query("SELECT r.slug FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = " . (int)$adminUser['id'])->fetchColumn();
assertTest("Admin is assigned role 'administrator'", $adminRoleCheck === 'administrator');

// 8. Test Duplicate Admin Protection
$dupAdminResult = installer_create_admin(
    $testPdo,
    'Duplicate Admin',
    'superadmin',
    'another@example.com',
    'AnotherPassword123!'
);
assertTest("installer_create_admin() rejects duplicate username", $dupAdminResult['success'] === false);

$dupEmailResult = installer_create_admin(
    $testPdo,
    'Duplicate Email',
    'otheruser',
    'superadmin@example.com',
    'AnotherPassword123!'
);
assertTest("installer_create_admin() rejects duplicate email", $dupEmailResult['success'] === false);

// 9. Test installer_write_config()
$configWriteResult = installer_write_config($host, $port, $testDbName, $user, $pass);
assertTest("installer_write_config() written successfully", $configWriteResult['success'] === true);

// Verify config can be read and evaluated safely
$configFile = $root . '/config/config.php';
assertTest("config/config.php is readable", is_readable($configFile));
$configPhpCode = file_get_contents($configFile);
assertTest("config/config.php contains DB_NAME '$testDbName'", strpos($configPhpCode, $testDbName) !== false);

// 10. Test Lock Creation & Lock Behavior
$lockCreated = installer_create_lock_file([
    'admin_username' => 'superadmin',
    'admin_email'    => 'superadmin@example.com',
    'db_name'        => $testDbName
]);
assertTest("installer_create_lock_file() created lock", $lockCreated === true && file_exists(installer_lock_file_path()));
assertTest("installer_is_locked() returns true when locked", installer_is_locked() === true);

// 11. Test Error Handling in Database Connection Tester
$badConn = installer_test_db_connection($host, $port, 'non_existent_db_xyz_99999', $user, 'wrong_pass_123');
assertTest("installer_test_db_connection() fails gracefully on bad credentials", $badConn['success'] === false && !empty($badConn['message']));

// 12. Test Uninstalled vs Installed Root Router
assertTest("Root index.php has lock guard", strpos(file_get_contents($root . '/index.php'), 'installed.lock') !== false);
assertTest("auth/login.php has lock guard", strpos(file_get_contents($root . '/auth/login.php'), 'installed.lock') !== false);

// 13. Test CSRF Protection
$csrfToken = installer_csrf_token();
assertTest("installer_csrf_token() generates valid 64-character token", strlen($csrfToken) === 64);
assertTest("installer_verify_csrf() verifies valid token", installer_verify_csrf($csrfToken) === true);
assertTest("installer_verify_csrf() rejects invalid token", installer_verify_csrf('invalid_token_123') === false);

// 14. Clean Up Test Database
try {
    $serverPdo->exec("DROP DATABASE `$testDbName`");
    assertTest("Temporary test database cleaned up", true);
} catch (Exception $e) {
    echo "Warning: could not drop test database $testDbName: " . $e->getMessage() . "\n";
}

// 15. Restore Default Development Database Configuration & Lock for Local Dev
installer_write_config('127.0.0.1', 3306, 'procurement_mgt', 'root', '', 'utf8mb4', 'Asia/Dhaka', true);
installer_create_lock_file([
    'admin_username' => 'admin',
    'admin_email'    => 'admin@example.com',
    'db_name'        => 'procurement_mgt'
]);
assertTest("Development configuration and lock restored for procurement_mgt", file_exists(installer_lock_file_path()));

echo "\n-------------------------------------------------------\n";
echo "SUMMARY: " . $passCount . " PASSED, " . $failCount . " FAILED\n";
echo "=======================================================\n";
