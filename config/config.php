<?php
/**
 * System Configuration
 * Procurement Management CMS — Production Configuration
 * Generated on: 2026-09-09 19:27:00
 */

// Timezone Setting
date_default_timezone_set('Asia/Dhaka');

// Database Credentials
if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
}

if (!defined('DB_PORT')) {
    define('DB_PORT', 3306);
}

if (!defined('DB_NAME')) {
    define('DB_NAME', 'procurement_mgt');
}

if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Development Mode
if (!defined('DEV_MODE')) {
    define('DEV_MODE', false);
}
