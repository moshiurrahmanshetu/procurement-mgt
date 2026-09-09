<?php
/**
 * Installer Core Helper Functions
 * Procurement Management CMS — Installer Phase 01
 *
 * Provides isolated, collision-free helper routines for the installation wizard.
 */

// Prevent direct execution if required
if (defined('STDIN')) {
    // CLI execution allowed for testing
}

/**
 * Returns the absolute root filesystem path of the application with a trailing directory separator.
 *
 * @return string
 */
function installer_root_path(): string
{
    return dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
}

/**
 * Returns the absolute filesystem path of the installer directory with a trailing directory separator.
 *
 * @return string
 */
function installer_path(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR;
}

/**
 * Safely escapes output for HTML rendering.
 *
 * @param mixed $value
 * @return string
 */
function installer_e($value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Dynamically detects the application base URL without hardcoded hostnames or paths.
 *
 * @param string $path Optional relative path to append
 * @return string
 */
function installer_base_url(string $path = ''): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $_SERVER['DOCUMENT_ROOT']) : '';
    $appRoot = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, dirname(dirname(__DIR__)));
    $relativeDir = '';

    if (!empty($docRoot) && strpos($appRoot, $docRoot) === 0) {
        $relativeDir = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    }

    $relativeDir = trim($relativeDir, '/');
    $baseUrl = $protocol . $host . ($relativeDir !== '' ? '/' . $relativeDir . '/' : '/');

    $path = ltrim($path, '/');
    return $baseUrl . $path;
}

/**
 * Returns the URL for an installer or application asset.
 *
 * @param string $path
 * @param bool $isInstallerAsset
 * @return string
 */
function installer_asset_url(string $path, bool $isInstallerAsset = false): string
{
    $path = ltrim($path, '/');
    if ($isInstallerAsset) {
        return installer_base_url('installer/assets/' . $path);
    }
    return installer_base_url('assets/' . $path);
}

/**
 * Returns the absolute path to the installation lock file.
 *
 * @return string
 */
function installer_lock_file_path(): string
{
    return installer_root_path() . 'config' . DIRECTORY_SEPARATOR . 'installed.lock';
}

/**
 * Checks whether the application is already installed and locked.
 *
 * @return bool
 */
function isInstallationLocked(): bool
{
    $lockFile = installer_lock_file_path();
    if (file_exists($lockFile)) {
        return true;
    }

    // Secondary fallback lock check inside installer directory
    $secondaryLock = installer_path() . 'installed.lock';
    return file_exists($secondaryLock);
}

/**
 * Alias for isInstallationLocked() with standard installer prefix.
 *
 * @return bool
 */
function installer_is_locked(): bool
{
    return isInstallationLocked();
}

/**
 * Starts an isolated installer session if not already active.
 */
function installer_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        if (!headers_sent()) {
            session_name('PROCURE_INSTALLER_SESSID');

            session_set_cookie_params([
                'lifetime' => 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        @session_start();
    }
}

/**
 * Formats byte values into human-readable strings.
 *
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function installer_format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}
