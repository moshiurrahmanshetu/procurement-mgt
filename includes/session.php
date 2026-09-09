<?php
/**
 * Safe Session Handler
 * Procurement Management CMS
 */

require_once dirname(__DIR__) . '/config/constants.php';

/**
 * Initializes session safely with secure parameters.
 */
function startAppSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Configure secure cookie params before session start
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        
        session_name('PROCURE_CMS_SESSID');
        
        session_set_cookie_params([
            'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    // Inactivity timeout handling for logged in users
    if (isset($_SESSION['user_id'])) {
        $currentTime = time();
        $lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200;

        if (isset($_SESSION['last_active_time']) && ($currentTime - $_SESSION['last_active_time'] > $lifetime)) {
            // Session expired due to inactivity
            unset($_SESSION['user_id']);
            unset($_SESSION['user_data']);
            session_regenerate_id(true);
            $_SESSION['flash_error'] = 'Your session has expired due to inactivity. Please log in again.';
        } else {
            $_SESSION['last_active_time'] = $currentTime;
        }
    }
}

/**
 * Regenerates the current session ID safely.
 */
function regenerateAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
