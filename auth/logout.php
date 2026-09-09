<?php
/**
 * User Logout Action
 * Procurement Management CMS
 */

require_once dirname(__DIR__) . '/includes/init.php';

logoutUser();

// Start a fresh session to set flash message
startAppSession();
setFlash('success', 'You have been successfully signed out.');

redirect('auth/login.php');
