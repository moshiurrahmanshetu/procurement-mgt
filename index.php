<?php
/**
 * Application Root Router
 * Procurement Management CMS
 */

require_once __DIR__ . '/includes/init.php';

if (isLoggedIn()) {
    redirect('modules/dashboard/index.php');
} else {
    redirect('auth/login.php');
}
