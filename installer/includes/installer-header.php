<?php
/**
 * Installer Header Layout
 * Procurement Management CMS — Installer Phase 01
 */

require_once __DIR__ . '/installer-functions.php';

$currentStep = $currentStep ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= isset($pageTitle) ? installer_e($pageTitle) . ' — ' : '' ?>Installation Wizard | Procurement Management CMS</title>
    
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="<?= installer_asset_url('css/bootstrap.min.css') ?>">
    
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="<?= installer_asset_url('css/bootstrap-icons.css') ?>">
    
    <!-- Installer Custom CSS -->
    <link rel="stylesheet" href="<?= installer_asset_url('css/installer.css', true) ?>">
</head>
<body class="installer-body">

<!-- Header / Navigation Bar -->
<header class="installer-navbar">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <a href="<?= installer_base_url('installer/index.php') ?>" class="installer-brand">
                <span class="installer-brand-icon">
                    <i class="bi bi-box-seam-fill"></i>
                </span>
                <div>
                    <div>Procurement Management System</div>
                    <small class="text-white-50 fs-6 fw-normal d-block">Quick Installation & Setup Wizard</small>
                </div>
            </a>
            <div class="d-none d-sm-flex align-items-center gap-2">
                <span class="installer-badge">
                    <i class="bi bi-shield-check me-1"></i> Phase 01: Requirements
                </span>
            </div>
        </div>
    </div>
</header>

<!-- Main Installer Content Wrapper -->
<main class="installer-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">

                <!-- Step Progress Tracker -->
                <div class="installer-steps">
                    <div class="step-item <?= $currentStep >= 1 ? 'active' : '' ?> <?= $currentStep > 1 ? 'completed' : '' ?>">
                        <div class="step-circle">
                            <?php if ($currentStep > 1): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                1
                            <?php endif; ?>
                        </div>
                        <span class="step-label">Requirements</span>
                    </div>

                    <div class="step-item <?= $currentStep === 2 ? 'active' : '' ?> <?= $currentStep > 2 ? 'completed' : '' ?>">
                        <div class="step-circle">
                            <?php if ($currentStep > 2): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                2
                            <?php endif; ?>
                        </div>
                        <span class="step-label">Database</span>
                    </div>

                    <div class="step-item <?= $currentStep === 3 ? 'active' : '' ?> <?= $currentStep > 3 ? 'completed' : '' ?>">
                        <div class="step-circle">
                            <?php if ($currentStep > 3): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                3
                            <?php endif; ?>
                        </div>
                        <span class="step-label">Import</span>
                    </div>

                    <div class="step-item <?= $currentStep === 4 ? 'active' : '' ?> <?= $currentStep > 4 ? 'completed' : '' ?>">
                        <div class="step-circle">
                            <?php if ($currentStep > 4): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                4
                            <?php endif; ?>
                        </div>
                        <span class="step-label">Admin</span>
                    </div>

                    <div class="step-item <?= $currentStep === 5 ? 'active' : '' ?>">
                        <div class="step-circle">5</div>
                        <span class="step-label">Finish</span>
                    </div>
                </div>
