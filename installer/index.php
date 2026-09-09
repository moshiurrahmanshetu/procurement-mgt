<?php
/**
 * Installation Wizard — Step 1: System Requirements Check
 * Procurement Management CMS — Installer Phase 01
 */

require_once __DIR__ . '/includes/installer-functions.php';
require_once __DIR__ . '/includes/requirements.php';

// Start isolated installer session
installer_start_session();

// Check if installation is locked
if (installer_is_locked()) {
    $pageTitle = 'Installation Locked';
    $currentStep = 1;
    require_once __DIR__ . '/includes/installer-header.php';
    ?>
    <div class="installer-card lock-card">
        <div class="installer-card-header">
            <div class="d-flex align-items-center gap-3">
                <div class="text-danger fs-2">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div>
                    <h4 class="mb-1 text-danger fw-bold">Application is Already Installed</h4>
                    <p class="text-muted mb-0">The installation wizard has been locked for security.</p>
                </div>
            </div>
        </div>
        <div class="installer-card-body">
            <div class="alert alert-warning mb-4">
                <div class="d-flex gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
                    <div>
                        <strong>Installation Lock Active</strong><br>
                        This Procurement Management CMS installation is currently locked to prevent unauthorized reconfiguration or database overwriting.
                    </div>
                </div>
            </div>

            <p class="text-secondary mb-3">
                To perform a fresh re-installation, you must manually delete the lock file located at:
            </p>
            <div class="p-3 bg-light border rounded font-monospace small mb-4 text-break">
                <?= installer_e(installer_lock_file_path()) ?>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="<?= installer_base_url('auth/login.php') ?>" class="btn btn-installer-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Go to Application Login
                </a>
                <a href="<?= installer_base_url() ?>" class="btn btn-installer-secondary">
                    <i class="bi bi-house"></i> Home Page
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/installer-footer.php';
    exit;
}

// Evaluate all system requirements
$reqData = installer_get_all_requirements();
$currentStep = 1;
$pageTitle = 'Step 1: System Requirements';

require_once __DIR__ . '/includes/installer-header.php';
?>

<div class="installer-card">
    <div class="installer-card-header">
        <div class="d-flex flex-column flex-md-row md-align-items-center justify-content-between gap-2">
            <div>
                <h4 class="mb-1 fw-bold text-dark">Step 1: System Requirements Check</h4>
                <p class="text-muted mb-0">
                    Verify that your server environment meets the minimum prerequisites before continuing.
                </p>
            </div>
            <div>
                <span class="badge <?= $reqData['all_passed'] ? 'bg-success' : 'bg-danger' ?> fs-6 px-3 py-2">
                    <i class="bi <?= $reqData['all_passed'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                    <?= $reqData['all_passed'] ? 'All Passed' : $reqData['failed_critical'] . ' Failed' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="installer-card-body">
        <?php if ($reqData['all_passed']): ?>
            <div class="alert alert-success d-flex align-items-center gap-3 mb-4" role="alert">
                <i class="bi bi-check-circle-fill fs-3 text-success"></i>
                <div>
                    <h6 class="alert-heading mb-1 fw-bold">Requirements Verified!</h6>
                    <div class="small">
                        Your server environment satisfies all mandatory prerequisites. You may continue to the database setup step.
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger d-flex align-items-center gap-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
                <div>
                    <h6 class="alert-heading mb-1 fw-bold">Requirements Check Failed</h6>
                    <div class="small">
                        Please resolve the failed requirement(s) listed in red below and click <strong>Re-check Requirements</strong> before continuing.
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($reqData['warning_count'] > 0 && $reqData['all_passed']): ?>
            <div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
                <i class="bi bi-info-circle-fill fs-4 text-warning"></i>
                <div class="small">
                    Some optional extensions or settings are not optimal, but they will not block the installation.
                </div>
            </div>
        <?php endif; ?>

        <!-- Requirement Categories -->
        <?php foreach ($reqData['categories'] as $categoryKey => $category): ?>
            <div class="requirement-section-title">
                <i class="bi <?= installer_e($category['icon']) ?> text-primary"></i>
                <span><?= installer_e($category['title']) ?></span>
            </div>

            <div class="table-responsive">
                <table class="table table-requirements align-middle mb-4">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Requirement</th>
                            <th style="width: 20%;">Required</th>
                            <th style="width: 25%;">Current / Detected</th>
                            <th style="width: 20%;" class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category['checks'] as $check): ?>
                            <tr class="<?= !$check['passed'] && $check['is_critical'] ? 'row-failed' : '' ?>">
                                <td>
                                    <div class="fw-semibold text-dark"><?= installer_e($check['name']) ?></div>
                                    <?php if (!$check['passed']): ?>
                                        <div class="small text-danger mt-1">
                                            <i class="bi bi-arrow-return-right me-1"></i><?= installer_e($check['message']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= installer_e($check['required']) ?></span>
                                </td>
                                <td>
                                    <span class="font-monospace small <?= $check['passed'] ? 'text-dark' : 'text-danger fw-bold' ?>">
                                        <?= installer_e($check['current']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($check['passed']): ?>
                                        <span class="status-badge passed">
                                            <i class="bi bi-check-circle-fill"></i> Passed
                                        </span>
                                    <?php elseif ($check['is_critical']): ?>
                                        <span class="status-badge failed">
                                            <i class="bi bi-x-circle-fill"></i> Failed
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge warning">
                                            <i class="bi bi-exclamation-circle-fill"></i> Warning
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="installer-card-footer d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3">
        <div>
            <button type="button" class="btn btn-installer-secondary" id="btn-recheck-requirements">
                <i class="bi bi-arrow-clockwise"></i> Re-check Requirements
            </button>
        </div>
        <div>
            <?php if ($reqData['all_passed']): ?>
                <a href="<?= installer_base_url('installer/database.php') ?>" class="btn btn-installer-primary">
                    Continue to Database Setup <i class="bi bi-arrow-right"></i>
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-installer-primary" disabled title="Please resolve all failed requirements before continuing">
                    Continue to Database Setup <i class="bi bi-arrow-right"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/installer-footer.php';
