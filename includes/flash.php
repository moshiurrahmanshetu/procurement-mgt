<?php
/**
 * Flash Messaging System
 * Procurement Management CMS
 */

/**
 * Sets a flash message in the session.
 *
 * @param string $type Message type (success, error, danger, warning, info)
 * @param string $message The message content
 */
function setFlash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        if (function_exists('startAppSession')) {
            startAppSession();
        } else {
            session_start();
        }
    }

    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }

    // Normalize danger to error
    $type = ($type === 'danger') ? 'error' : $type;

    if (!isset($_SESSION['flash_messages'][$type])) {
        $_SESSION['flash_messages'][$type] = [];
    }

    $_SESSION['flash_messages'][$type][] = $message;
}

/**
 * Checks if flash messages of a specific type exist.
 *
 * @param string $type
 * @return bool
 */
function hasFlash(string $type): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }

    $type = ($type === 'danger') ? 'error' : $type;
    return !empty($_SESSION['flash_messages'][$type]);
}

/**
 * Retrieves and clears flash messages for a specific type.
 *
 * @param string $type
 * @return array
 */
function getFlash(string $type): array
{
    if (session_status() === PHP_SESSION_NONE) {
        return [];
    }

    $type = ($type === 'danger') ? 'error' : $type;
    $messages = $_SESSION['flash_messages'][$type] ?? [];
    unset($_SESSION['flash_messages'][$type]);

    return $messages;
}

/**
 * Retrieves and clears all flash messages.
 *
 * @return array
 */
function getAllFlashes(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        return [];
    }

    $messages = $_SESSION['flash_messages'] ?? [];
    $_SESSION['flash_messages'] = [];

    // Also check legacy flash variables
    if (isset($_SESSION['flash_error'])) {
        $messages['error'][] = $_SESSION['flash_error'];
        unset($_SESSION['flash_error']);
    }
    if (isset($_SESSION['flash_success'])) {
        $messages['success'][] = $_SESSION['flash_success'];
        unset($_SESSION['flash_success']);
    }

    return $messages;
}

/**
 * Renders all flash messages as Bootstrap 5 dismissible alerts.
 *
 * @return string HTML output
 */
function renderFlashMessages(): string
{
    $flashes = getAllFlashes();
    if (empty($flashes)) {
        return '';
    }

    $output = '';

    $typeMap = [
        'success' => ['class' => 'alert-success', 'icon' => 'bi-check-circle-fill'],
        'error'   => ['class' => 'alert-danger',  'icon' => 'bi-exclamation-triangle-fill'],
        'warning' => ['class' => 'alert-warning', 'icon' => 'bi-exclamation-circle-fill'],
        'info'    => ['class' => 'alert-info',    'icon' => 'bi-info-circle-fill']
    ];

    foreach ($flashes as $type => $messages) {
        $meta = $typeMap[$type] ?? ['class' => 'alert-secondary', 'icon' => 'bi-bell-fill'];
        $class = $meta['class'];
        $icon = $meta['icon'];

        foreach ($messages as $msg) {
            $output .= '<div class="alert ' . $class . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">';
            $output .= '  <i class="bi ' . $icon . ' fs-5 me-2 flex-shrink-0"></i>';
            $output .= '  <div class="flex-grow-1">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
            $output .= '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $output .= '</div>';
        }
    }

    return $output;
}
