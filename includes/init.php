<?php
/**
 * Global Initialization Script
 * Procurement Management CMS
 */

// Load Configurations
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Load Core System Helpers
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

// Start Session with Secure Attributes
startAppSession();
