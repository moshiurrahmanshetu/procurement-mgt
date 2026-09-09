<?php
/**
 * Access Denied 403 Page
 * Procurement Management CMS
 */

require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'Access Denied';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden | <?= e(APP_NAME) ?></title>

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/bootstrap-icons.css') ?>">

    <!-- Styles -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
</head>
<body class="auth-body">
<div class="auth-card-wrapper text-center">
    <div class="auth-card">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle p-3" style="width:72px;height:72px;">
                <i class="bi bi-shield-lock-fill fs-1"></i>
            </span>
        </div>
        <h2 class="auth-title mb-1">Access Restricted</h2>
        <p class="auth-subtitle mb-4">403 - Insufficient Privileges</p>
        
        <p class="text-muted small mb-4">
            You do not have the administrative permissions required to access the requested module. If you believe this is an error, please contact your system administrator.
        </p>

        <div class="d-flex flex-column gap-2">
            <a href="<?= url('modules/dashboard/index.php') ?>" class="auth-btn-primary text-decoration-none">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Return to Dashboard</span>
            </a>
            <?php if (!isLoggedIn()): ?>
                <a href="<?= url('auth/login.php') ?>" class="btn btn-outline-secondary">
                    <span>Sign In with Another Account</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
