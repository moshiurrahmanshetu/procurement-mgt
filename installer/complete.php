<?php
/**
 * Installation Wizard — Success & Completion Screen
 * Procurement Management CMS — Final Complete Installer
 */

require_once __DIR__ . '/includes/installer-functions.php';

installer_start_session();

// If lock does not exist and completed flag not set, go back to step 1
if (!installer_is_locked() && empty($_SESSION['installer_completed'])) {
    header('Location: ' . installer_base_url('installer/index.php'));
    exit;
}

$adminUsername = $_SESSION['final_admin_username'] ?? 'admin';
$adminEmail = $_SESSION['final_admin_email'] ?? 'admin@example.com';

$currentStep = 5;
$pageTitle = 'Installation Complete';

require_once __DIR__ . '/includes/installer-header.php';
?>

<div class="installer-card">
    <div class="installer-card-header text-center py-4 bg-light border-bottom">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle shadow-sm" style="width: 64px; height: 64px; font-size: 2rem;">
                <i class="bi bi-check-lg"></i>
            </span>
        </div>
        <h3 class="fw-bold text-dark mb-1">Installation Complete!</h3>
        <p class="text-muted mb-0">Your Procurement Management System is ready for use.</p>
    </div>

    <div class="installer-card-body p-4">
        <div class="alert alert-success d-flex align-items-center gap-3 mb-4" role="alert">
            <i class="bi bi-shield-fill-check fs-3 text-success flex-shrink-0"></i>
            <div>
                <h6 class="alert-heading fw-bold mb-1">System Successfully Installed & Locked</h6>
                <div class="small">
                    All tables have been configured, the administrator user has been initialized, and the installation security lock has been applied.
                </div>
            </div>
        </div>

        <h6 class="fw-bold text-dark mb-3">Installation Summary</h6>
        <ul class="list-group list-group-flush mb-4 border rounded">
            <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span>Server Requirements</span>
                </span>
                <span class="badge bg-success">Passed</span>
            </li>
            <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span>Database Schema & Lookups</span>
                </span>
                <span class="badge bg-success">Imported</span>
            </li>
            <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span>Administrator Account</span>
                </span>
                <span class="badge bg-success">Created</span>
            </li>
            <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span>Configuration (config/config.php)</span>
                </span>
                <span class="badge bg-success">Saved</span>
            </li>
            <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span>Installation Security Lock</span>
                </span>
                <span class="badge bg-success">Locked</span>
            </li>
        </ul>

        <div class="card bg-light border p-3 mb-4">
            <h6 class="fw-bold text-dark mb-2">Access Credentials & URLs</h6>
            <div class="row g-2 small">
                <div class="col-sm-4 text-muted">Application URL:</div>
                <div class="col-sm-8 font-monospace text-break">
                    <a href="<?= installer_base_url() ?>" target="_blank"><?= installer_e(installer_base_url()) ?></a>
                </div>

                <div class="col-sm-4 text-muted">Login URL:</div>
                <div class="col-sm-8 font-monospace text-break">
                    <a href="<?= installer_base_url('auth/login.php') ?>"><?= installer_e(installer_base_url('auth/login.php')) ?></a>
                </div>

                <div class="col-sm-4 text-muted">Admin Username:</div>
                <div class="col-sm-8 font-monospace fw-bold text-dark">
                    <?= installer_e($adminUsername) ?>
                </div>

                <div class="col-sm-4 text-muted">Admin Email:</div>
                <div class="col-sm-8 font-monospace text-dark">
                    <?= installer_e($adminEmail) ?>
                </div>
            </div>
        </div>

        <div class="alert alert-warning small mb-0">
            <i class="bi bi-shield-lock-fill me-1"></i>
            <strong>Security Notice:</strong> The installation wizard has now been disabled. If you ever need to perform a clean reinstall, refer to the manual reinstall instructions in the system documentation.
        </div>
    </div>

    <div class="installer-card-footer text-center p-4">
        <a href="<?= installer_base_url('auth/login.php') ?>" class="btn btn-installer-primary btn-lg px-5">
            <i class="bi bi-box-arrow-in-right me-1"></i> Go to Application Login
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/installer-footer.php';
