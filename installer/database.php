<?php
/**
 * Installation Wizard — Step 2: Database Setup (Placeholder)
 * Procurement Management CMS — Installer Phase 01
 *
 * NOTE: Database configuration and connection testing will be implemented in Installer Phase 02.
 */

require_once __DIR__ . '/includes/installer-functions.php';
require_once __DIR__ . '/includes/requirements.php';

// Start isolated installer session
installer_start_session();

// Check if installation is locked
if (installer_is_locked()) {
    header('Location: ' . installer_base_url('installer/index.php'));
    exit;
}

$currentStep = 2;
$pageTitle = 'Step 2: Database Setup';

require_once __DIR__ . '/includes/installer-header.php';
?>

<div class="installer-card">
    <div class="installer-card-header">
        <h4 class="mb-1 fw-bold text-dark">Step 2: Database Configuration</h4>
        <p class="text-muted mb-0">Configure your MySQL / MariaDB connection parameters.</p>
    </div>

    <div class="installer-card-body text-center py-5">
        <div class="mb-4 text-primary">
            <i class="bi bi-database-gear" style="font-size: 3.5rem;"></i>
        </div>
        <h5 class="fw-bold mb-2">Installer Phase 01 Complete!</h5>
        <p class="text-muted mx-auto" style="max-width: 500px;">
            System requirements have been successfully verified. Database connection testing and configuration will be implemented in <strong>Installer Phase 02</strong>.
        </p>
        <div class="mt-4">
            <a href="<?= installer_base_url('installer/index.php') ?>" class="btn btn-installer-secondary">
                <i class="bi bi-arrow-left"></i> Back to Requirements Check
            </a>
        </div>
    </div>

    <div class="installer-card-footer d-flex justify-content-between">
        <a href="<?= installer_base_url('installer/index.php') ?>" class="btn btn-installer-secondary">
            <i class="bi bi-arrow-left"></i> Previous Step
        </a>
        <button type="button" class="btn btn-installer-primary" disabled>
            Next: Import Database <i class="bi bi-arrow-right"></i>
        </button>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/installer-footer.php';
