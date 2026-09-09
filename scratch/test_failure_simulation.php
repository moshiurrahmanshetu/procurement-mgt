<?php
/**
 * Failure Simulation Test for Installer Phase 01
 */

require_once __DIR__ . '/../installer/includes/installer-functions.php';
require_once __DIR__ . '/../installer/includes/requirements.php';

echo "--- Testing Simulated PHP Version Failure ---\n";
$failPhp = installer_check_php_version('99.0.0');
echo "Passed: " . ($failPhp['passed'] ? 'TRUE' : 'FALSE') . "\n";
echo "Message: " . $failPhp['message'] . "\n";
assert($failPhp['passed'] === false, "Simulated PHP 99.0.0 check should fail");

echo "\n--- Testing Missing Extension Failure ---\n";
$failExt = installer_check_extension('non_existent_dummy_ext_xyz', 'Dummy Extension');
echo "Passed: " . ($failExt['passed'] ? 'TRUE' : 'FALSE') . "\n";
echo "Message: " . $failExt['message'] . "\n";
assert($failExt['passed'] === false, "Missing extension check should fail");

echo "\n--- Testing Non-existent Directory Failure ---\n";
$failDir = installer_check_directory('invalid_path/dummy_folder/non_existent', 'Dummy Non Existent Folder');
echo "Passed: " . ($failDir['passed'] ? 'TRUE' : 'FALSE') . "\n";
echo "Message: " . $failDir['message'] . "\n";

echo "\nALL SIMULATION TESTS COMPLETED SUCCESSFULLY!\n";
