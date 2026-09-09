<?php
/**
 * CSRF Protection
 * Procurement Management CMS
 */

/**
 * Generates or retrieves the active session CSRF token.
 *
 * @return string
 */
function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        if (function_exists('startAppSession')) {
            startAppSession();
        } else {
            session_start();
        }
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Gets the current CSRF token string.
 *
 * @return string
 */
function csrfToken(): string
{
    return generateCsrfToken();
}

/**
 * Renders an HTML hidden input containing the CSRF token.
 *
 * @return string
 */
function csrfField(): string
{
    $token = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validates a CSRF token against the session token.
 *
 * @param string|null $token
 * @return bool
 */
function validateCsrfToken(?string $token = null): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        if (function_exists('startAppSession')) {
            startAppSession();
        } else {
            session_start();
        }
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    return is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verifies CSRF for state-changing requests and aborts on failure.
 *
 * @param string|null $redirectUrl Optional URL to redirect to on failure
 */
function verifyCsrfRequest(?string $redirectUrl = null): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCsrfToken()) {
            if (function_exists('setFlash')) {
                setFlash('error', 'Invalid security token or session expired. Please try again.');
            }
            
            if ($redirectUrl !== null && function_exists('redirect')) {
                redirect($redirectUrl);
            } elseif (!empty($_SERVER['HTTP_REFERER']) && function_exists('redirect')) {
                redirect($_SERVER['HTTP_REFERER']);
            } else {
                http_response_code(403);
                die('<div style="font-family:sans-serif;padding:20px;margin:20px;background:#fee2e2;border:1px solid #ef4444;border-radius:8px;color:#991b1b;">
                    <h3>403 Forbidden - Security Token Validation Failed</h3>
                    <p>Your form submission could not be verified. Please refresh the page and try again.</p>
                </div>');
            }
        }
    }
}
