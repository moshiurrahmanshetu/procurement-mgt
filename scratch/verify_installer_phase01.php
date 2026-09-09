<?php
/**
 * Automated Verification Suite — Installer Phase 01
 * Procurement Management CMS
 */

// Start session before echo to prevent CLI header warnings
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

echo "=======================================================\n";
echo "INSTALLER PHASE 01 — AUTOMATED VERIFICATION SUITE\n";
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

// 1. Lint all installer files
$installerFiles = [
    'installer/includes/installer-functions.php',
    'installer/includes/requirements.php',
    'installer/includes/installer-header.php',
    'installer/includes/installer-footer.php',
    'installer/index.php',
    'installer/database.php'
];

$root = dirname(__DIR__);

foreach ($installerFiles as $relPath) {
    $fullPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnVar);
    $syntaxOk = ($returnVar === 0 && strpos(implode(' ', $output), 'No syntax errors detected') !== false);
    assertTest("Syntax lint: " . $relPath, $syntaxOk, implode(' ', $output));
}

// 2. Test Installer Core Functions
require_once $root . '/installer/includes/installer-functions.php';

assertTest("installer_root_path() returns valid root directory", is_dir(installer_root_path()) && file_exists(installer_root_path() . 'index.php'));
assertTest("installer_path() returns valid installer directory", is_dir(installer_path()) && file_exists(installer_path() . 'index.php'));
assertTest("isInstallationLocked() returns false on fresh installation", isInstallationLocked() === false);
assertTest("installer_is_locked() returns false on fresh installation", installer_is_locked() === false);

// 3. Test Requirements Engine
require_once $root . '/installer/includes/requirements.php';

$phpCheck = installer_check_php_version('8.0.0');
assertTest("installer_check_php_version('8.0.0') evaluates successfully", isset($phpCheck['passed']) && $phpCheck['passed'] === true);

$pdoCheck = installer_check_extension('pdo', 'PDO Core Extension');
assertTest("installer_check_extension('pdo') evaluates successfully", isset($pdoCheck['passed']) && $pdoCheck['passed'] === true);

$pdoMysqlCheck = installer_check_extension('pdo_mysql', 'PDO MySQL');
assertTest("installer_check_extension('pdo_mysql') evaluates successfully", isset($pdoMysqlCheck['passed']) && $pdoMysqlCheck['passed'] === true);

$configDirCheck = installer_check_directory('config', 'Configuration Directory');
assertTest("installer_check_directory('config') is writable", isset($configDirCheck['passed']) && $configDirCheck['passed'] === true);

$allReqs = installer_get_all_requirements();
assertTest("installer_get_all_requirements() returns all 3 categories", isset($allReqs['categories']['environment'], $allReqs['categories']['extensions'], $allReqs['categories']['permissions']));
assertTest("installer_get_all_requirements() overall all_passed is true in current environment", $allReqs['all_passed'] === true);
assertTest("installer_get_all_requirements() failed_critical is 0", $allReqs['failed_critical'] === 0);

// 4. Test Lock File Simulation
$lockFile = installer_lock_file_path();
@file_put_contents($lockFile, 'Installed on ' . date('c'));
assertTest("isInstallationLocked() detects lock file when present", isInstallationLocked() === true);
@unlink($lockFile);
assertTest("isInstallationLocked() returns false after lock file cleanup", isInstallationLocked() === false);

// 5. Test Normal CMS Initialization Unaffected (No function collisions)
require_once $root . '/includes/init.php';
assertTest("CMS includes/init.php loads without function collisions", function_exists('isLoggedIn') && function_exists('e') && function_exists('url'));
assertTest("CMS Database Singleton getDb() remains operational", getDb() instanceof PDO);

// 6. Test Render of Installer step 1 (Capture Output)
ob_start();
include $root . '/installer/index.php';
$htmlOutput = ob_get_clean();

assertTest("installer/index.php renders valid HTML", strpos($htmlOutput, '<!DOCTYPE html>') !== false);
assertTest("installer/index.php contains Step 1 heading", strpos($htmlOutput, 'Step 1: System Requirements Check') !== false);
assertTest("installer/index.php contains All Passed badge or success alert", strpos($htmlOutput, 'Requirements Verified!') !== false || strpos($htmlOutput, 'All Passed') !== false);
assertTest("installer/index.php contains Continue to Database Setup button", strpos($htmlOutput, 'Continue to Database Setup') !== false);
assertTest("installer/index.php contains Re-check Requirements button", strpos($htmlOutput, 'Re-check Requirements') !== false);

// 7. Verify Assets exist
assertTest("Bootstrap CSS exists", file_exists($root . '/assets/css/bootstrap.min.css'));
assertTest("Bootstrap Icons CSS exists", file_exists($root . '/assets/css/bootstrap-icons.css'));
assertTest("Installer CSS exists", file_exists($root . '/installer/assets/css/installer.css'));
assertTest("Installer JS exists", file_exists($root . '/installer/assets/js/installer.js'));
assertTest("Installer README.md exists", file_exists($root . '/installer/README.md'));

// 8. Test Step 2 Placeholder Render
ob_start();
include $root . '/installer/database.php';
$dbHtmlOutput = ob_get_clean();
assertTest("installer/database.php renders Phase 02 placeholder cleanly", strpos($dbHtmlOutput, 'Step 2: Database Configuration') !== false && strpos($dbHtmlOutput, 'Installer Phase 01 Complete!') !== false);

echo "\n-------------------------------------------------------\n";
echo "SUMMARY: " . $passCount . " PASSED, " . $failCount . " FAILED\n";
echo "=======================================================\n";
