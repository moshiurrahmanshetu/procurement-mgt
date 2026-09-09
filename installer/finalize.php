<?php
/**
 * Installation Wizard — Step 5: Finalization & System Lock
 * Procurement Management CMS — Final Complete Installer
 */

require_once __DIR__ . '/includes/installer-functions.php';
require_once __DIR__ . '/includes/requirements.php';

installer_start_session();

// Lock Protection
if (installer_is_locked()) {
    header('Location: ' . installer_base_url('installer/index.php'));
    exit;
}

// Ensure database configuration and admin setup are present
if (empty($_SESSION['installer_db']) || empty($_SESSION['installer_admin_created'])) {
    header('Location: ' . installer_base_url('installer/admin.php'));
    exit;
}

$dbConfig = $_SESSION['installer_db'];
$adminUser = $_SESSION['installer_admin_username'] ?? 'admin';
$adminEmail = $_SESSION['installer_admin_email'] ?? 'admin@example.com';

$error = null;

// Execute Finalization
$configResult = installer_write_config(
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['dbname'],
    $dbConfig['user'],
    $dbConfig['pass']
);

if (!$configResult['success']) {
    $error = $configResult['message'];
} else {
    // Re-verify connection with newly saved config
    $finalTest = installer_test_db_connection(
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['dbname'],
        $dbConfig['user'],
        $dbConfig['pass']
    );

    if (!$finalTest['success']) {
        $error = 'Configuration was saved, but live connection test failed: ' . $finalTest['message'];
    } else {
        // Create permanent installation lock
        $lockCreated = installer_create_lock_file([
            'admin_username' => $adminUser,
            'admin_email'    => $adminEmail,
            'db_name'        => $dbConfig['dbname']
        ]);

        if (!$lockCreated) {
            $error = 'Failed to create installation lock file in config/installed.lock. Please check filesystem write permissions.';
        } else {
            // Save completion state and clean up sensitive session variables
            $_SESSION['installer_completed'] = true;
            $_SESSION['final_admin_username'] = $adminUser;
            $_SESSION['final_admin_email'] = $adminEmail;

            installer_clear_session();

            header('Location: ' . installer_base_url('installer/complete.php'));
            exit;
        }
    }
}

$currentStep = 5;
$pageTitle = 'Step 5: Finalizing Installation';

require_once __DIR__ . '/includes/installer-header.php';
?>

<div class="installer-card">
    <div class="installer-card-header">
        <h4 class="mb-1 fw-bold text-dark">Installation Finalization</h4>
        <p class="text-muted mb-0">Saving system configuration and applying security lock.</p>
    </div>

    <div class="installer-card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-3 text-danger flex-shrink-0"></i>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Finalization Failed</h6>
                    <div class="small"><?= installer_e($error) ?></div>
                </div>
            </div>

            <p class="text-muted small">
                The installation lock was not created. Please resolve the issue above and retry.
            </p>

            <div class="mt-4">
                <a href="<?= installer_base_url('installer/finalize.php') ?>" class="btn btn-installer-primary">
                    <i class="bi bi-arrow-clockwise"></i> Retry Finalization
                </a>
                <a href="<?= installer_base_url('installer/admin.php') ?>" class="btn btn-installer-secondary ms-2">
                    <i class="bi bi-arrow-left"></i> Back to Admin Setup
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/installer-footer.php';
