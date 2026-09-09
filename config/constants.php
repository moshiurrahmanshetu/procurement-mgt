<?php
/**
 * System Constants
 * Procurement Management CMS
 */

// Root filesystem path with trailing directory separator
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

// Dynamic Base URL detection
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Calculate base directory relative to document root
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $_SERVER['DOCUMENT_ROOT']) : '';
    $currentDir = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, dirname(__DIR__));
    $relativeDir = '';
    
    if (!empty($docRoot) && strpos($currentDir, $docRoot) === 0) {
        $relativeDir = str_replace('\\', '/', substr($currentDir, strlen($docRoot)));
    }
    
    $relativeDir = trim($relativeDir, '/');
    $baseUrl = $protocol . $host . ($relativeDir !== '' ? '/' . $relativeDir . '/' : '/');
    
    define('BASE_URL', $baseUrl);
}

// Application Metadata
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Procurement Management CMS');
}

if (!defined('APP_SHORT_NAME')) {
    define('APP_SHORT_NAME', 'ProcureCMS');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// Uploads Directories
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
}

if (!defined('AVATAR_UPLOAD_DIR')) {
    define('AVATAR_UPLOAD_DIR', UPLOAD_DIR . 'avatars' . DIRECTORY_SEPARATOR);
}

// Avatar Upload Limits
if (!defined('AVATAR_MAX_SIZE')) {
    define('AVATAR_MAX_SIZE', 2 * 1024 * 1024); // 2 MB
}

if (!defined('ALLOWED_AVATAR_MIMES')) {
    define('ALLOWED_AVATAR_MIMES', [
        'image/jpeg',
        'image/png',
        'image/webp'
    ]);
}

if (!defined('ALLOWED_AVATAR_EXTS')) {
    define('ALLOWED_AVATAR_EXTS', [
        'jpg',
        'jpeg',
        'png',
        'webp'
    ]);
}

// Password Reset Expiry (in seconds - 1 hour)
if (!defined('PASSWORD_RESET_EXPIRY')) {
    define('PASSWORD_RESET_EXPIRY', 3600);
}

// Session Lifetime (in seconds - 2 hours)
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 7200);
}
