<?php
/**
 * System Requirements Checker
 * Procurement Management CMS — Installer Phase 01
 *
 * Performs detailed environment, extension, and filesystem permission checks.
 */

require_once __DIR__ . '/installer-functions.php';

/**
 * Checks PHP version against a required minimum version.
 *
 * @param string $minVersion
 * @return array
 */
function installer_check_php_version(string $minVersion = '8.0.0'): array
{
    $currentVersion = PHP_VERSION;
    $passed = version_compare($currentVersion, $minVersion, '>=');

    return [
        'name'        => 'PHP Version',
        'required'    => 'PHP ' . $minVersion . '+',
        'current'     => 'PHP ' . $currentVersion,
        'passed'      => $passed,
        'is_critical' => true,
        'message'     => $passed 
            ? 'PHP version is compatible.' 
            : 'Your server is running PHP ' . $currentVersion . '. Please upgrade to PHP ' . $minVersion . ' or higher.'
    ];
}

/**
 * Checks if a specific PHP extension is loaded.
 *
 * @param string $extension
 * @param string $displayName
 * @param bool $isCritical
 * @param string|null $customMessage
 * @return array
 */
function installer_check_extension(string $extension, string $displayName, bool $isCritical = true, ?string $customMessage = null): array
{
    $loaded = extension_loaded($extension);

    return [
        'name'        => $displayName,
        'required'    => 'Enabled',
        'current'     => $loaded ? 'Enabled' : 'Disabled',
        'passed'      => $loaded,
        'is_critical' => $isCritical,
        'message'     => $loaded 
            ? 'Extension is loaded and active.' 
            : ($customMessage ?? 'Please enable the ' . $displayName . ' (' . $extension . ') extension in php.ini.')
    ];
}

/**
 * Checks if a directory exists and is writable. Attempts to safely create it if absent and parent is writable.
 *
 * @param string $relativePath Directory path relative to application root
 * @param string $displayName
 * @param bool $isCritical
 * @return array
 */
function installer_check_directory(string $relativePath, string $displayName, bool $isCritical = true): array
{
    $root = installer_root_path();
    $fullPath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($relativePath, '/\\') . DIRECTORY_SEPARATOR;

    // If directory does not exist, attempt to create it if parent is writable
    if (!file_exists($fullPath)) {
        @mkdir($fullPath, 0755, true);
    }

    $exists = is_dir($fullPath);
    $writable = $exists && is_writable($fullPath);

    $currentStatus = 'Non-existent';
    if ($exists) {
        $currentStatus = $writable ? 'Writable' : 'Read-Only';
    }

    $displayPath = str_replace('\\', '/', $relativePath);

    return [
        'name'        => $displayName,
        'path'        => $displayPath,
        'required'    => 'Writable',
        'current'     => $currentStatus,
        'passed'      => $writable,
        'is_critical' => $isCritical,
        'message'     => $writable 
            ? 'Directory is writable.' 
            : ($exists 
                ? 'Directory ' . $displayPath . ' is not writable. Please adjust directory permissions.' 
                : 'Directory ' . $displayPath . ' could not be created or found.')
    ];
}

/**
 * Checks a boolean or value-based php.ini directive.
 *
 * @param string $directive
 * @param string $displayName
 * @param bool $expectedBool
 * @param bool $isCritical
 * @return array
 */
function installer_check_ini_bool(string $directive, string $displayName, bool $expectedBool = true, bool $isCritical = true): array
{
    $val = ini_get($directive);
    $currentBool = filter_var($val, FILTER_VALIDATE_BOOLEAN) || strtolower($val) === 'on' || $val === '1';
    $passed = ($currentBool === $expectedBool);

    return [
        'name'        => $displayName,
        'required'    => $expectedBool ? 'Enabled' : 'Disabled',
        'current'     => $currentBool ? 'Enabled' : 'Disabled',
        'passed'      => $passed,
        'is_critical' => $isCritical,
        'message'     => $passed 
            ? 'Setting matches recommended configuration.' 
            : 'Please set ' . $directive . ' = ' . ($expectedBool ? 'On' : 'Off') . ' in php.ini.'
    ];
}

/**
 * Retrieves all system requirements grouped by category with overall compliance summary.
 *
 * @return array
 */
function installer_get_all_requirements(): array
{
    $categories = [];
    $allPassed = true;
    $failedCriticalCount = 0;
    $warningCount = 0;

    // Category 1: Core PHP & Environment
    $envChecks = [];
    $envChecks[] = installer_check_php_version('8.0.0');
    $envChecks[] = installer_check_ini_bool('file_uploads', 'File Uploads Support', true, true);

    // Memory limit check (informational)
    $memoryLimit = ini_get('memory_limit');
    $envChecks[] = [
        'name'        => 'Memory Limit',
        'required'    => '128M+ recommended',
        'current'     => $memoryLimit ?: 'Unknown',
        'passed'      => true,
        'is_critical' => false,
        'message'     => 'Allocated PHP memory limit.'
    ];

    // Upload max filesize check (informational)
    $maxUpload = ini_get('upload_max_filesize');
    $envChecks[] = [
        'name'        => 'Max File Upload Size',
        'required'    => '2M+ recommended',
        'current'     => $maxUpload ?: 'Unknown',
        'passed'      => true,
        'is_critical' => false,
        'message'     => 'Maximum allowed size for uploaded files.'
    ];

    $categories['environment'] = [
        'title'  => 'PHP Environment & Directives',
        'icon'   => 'bi-cpu',
        'checks' => $envChecks
    ];

    // Category 2: Required PHP Extensions
    $extChecks = [];
    $extChecks[] = installer_check_extension('pdo', 'PDO Core Extension', true, 'PDO extension is required for secure database transactions.');
    $extChecks[] = installer_check_extension('pdo_mysql', 'PDO MySQL / MariaDB Driver', true, 'PDO MySQL driver is required to connect to your database.');
    $extChecks[] = installer_check_extension('session', 'Session Support', true, 'PHP session extension is required for authentication and state management.');
    $extChecks[] = installer_check_extension('json', 'JSON Extension', true, 'JSON extension is required for data exchange and configurations.');
    $extChecks[] = installer_check_extension('mbstring', 'Multibyte String (mbstring)', true, 'Mbstring extension is required for UTF-8 character handling.');
    $extChecks[] = installer_check_extension('fileinfo', 'File Information (fileinfo)', true, 'Fileinfo extension is required for MIME-type file verification.');
    
    // Optional/Recommended extensions
    $extChecks[] = installer_check_extension('openssl', 'OpenSSL Support', false, 'OpenSSL is recommended for cryptographic token generation.');
    $extChecks[] = installer_check_extension('gd', 'GD Image Library', false, 'GD library is recommended for avatar and logo manipulation.');
    $extChecks[] = installer_check_extension('ctype', 'Ctype Extension', false, 'Ctype is recommended for character validation.');

    $categories['extensions'] = [
        'title'  => 'PHP Extensions',
        'icon'   => 'bi-puzzle',
        'checks' => $extChecks
    ];

    // Category 3: Directory Permissions
    $dirChecks = [];
    $dirChecks[] = installer_check_directory('config', 'Configuration Directory (config/)', true);
    $dirChecks[] = installer_check_directory('assets/uploads', 'Uploads Base Directory (assets/uploads/)', true);
    $dirChecks[] = installer_check_directory('assets/uploads/avatars', 'Avatars Directory (assets/uploads/avatars/)', true);
    $dirChecks[] = installer_check_directory('assets/uploads/logos', 'Logos Directory (assets/uploads/logos/)', true);

    $categories['permissions'] = [
        'title'  => 'Filesystem & Directory Permissions',
        'icon'   => 'bi-folder-check',
        'checks' => $dirChecks
    ];

    // Compute overall statistics
    foreach ($categories as $catKey => $category) {
        foreach ($category['checks'] as $check) {
            if (!$check['passed']) {
                if ($check['is_critical']) {
                    $allPassed = false;
                    $failedCriticalCount++;
                } else {
                    $warningCount++;
                }
            }
        }
    }

    return [
        'categories'          => $categories,
        'all_passed'          => $allPassed,
        'failed_critical'     => $failedCriticalCount,
        'warning_count'       => $warningCount,
        'total_checks'        => count($envChecks) + count($extChecks) + count($dirChecks)
    ];
}
