<?php
/**
 * Application Root Router
 * Procurement Management CMS
 */

// If application is not installed, redirect directly to installer
if (!file_exists(__DIR__ . '/config/installed.lock')) {
    header('Location: installer/');
    exit;
}

require_once __DIR__ . '/includes/init.php';

if (isLoggedIn()) {
    redirect('modules/dashboard/index.php');
} else {
    redirect('auth/login.php');
}

