<?php
/**
 * System Settings Management (Administrator Only)
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'System Settings';
$pageSubtitle = 'Company Profile, Localization, and Branding Configuration';
$activeNav = 'settings';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireRole('administrator');

$settings = getAllSettings();

// Helper to safely get setting value from array
function val(array $settings, string $key, string $default = ''): string
{
    return $settings[$key]['setting_value'] ?? $default;
}

$companyName    = val($settings, 'company_name', 'ProcureCMS Enterprise Ltd.');
$companyEmail   = val($settings, 'company_email', 'procurement@example.com');
$companyPhone   = val($settings, 'company_phone', '+1 (555) 019-2834');
$companyAddress = val($settings, 'company_address', '100 Enterprise Way, Suite 400, New York, NY 10001');
$currency       = val($settings, 'currency', '$');
$dateFormat     = val($settings, 'date_format', 'd M Y');
$timezone       = val($settings, 'timezone', 'Asia/Dhaka');
$companyLogo    = val($settings, 'company_logo', '');

// Available Common Timezones
$timezones = [
    'UTC'                  => 'UTC (Coordinated Universal Time)',
    'America/New_York'     => 'America/New_York (EST/EDT - New York)',
    'America/Chicago'      => 'America/Chicago (CST/CDT - Chicago)',
    'America/Denver'       => 'America/Denver (MST/MDT - Denver)',
    'America/Los_Angeles'  => 'America/Los_Angeles (PST/PDT - Los Angeles)',
    'Europe/London'        => 'Europe/London (GMT/BST - London)',
    'Europe/Paris'         => 'Europe/Paris (CET/CEST - Paris)',
    'Europe/Berlin'        => 'Europe/Berlin (CET/CEST - Berlin)',
    'Asia/Dubai'           => 'Asia/Dubai (GST - Dubai)',
    'Asia/Kolkata'         => 'Asia/Kolkata (IST - Mumbai, New Delhi)',
    'Asia/Dhaka'           => 'Asia/Dhaka (BST - Dhaka)',
    'Asia/Bangkok'         => 'Asia/Bangkok (ICT - Bangkok)',
    'Asia/Singapore'       => 'Asia/Singapore (SGT - Singapore)',
    'Asia/Tokyo'           => 'Asia/Tokyo (JST - Tokyo)',
    'Australia/Sydney'     => 'Australia/Sydney (AEST/AEDT - Sydney)'
];

// Available Date Formats
$dateFormats = [
    'd M Y'       => date('d M Y') . ' (e.g. 09 Sep 2026)',
    'd M Y, h:i A'=> date('d M Y, h:i A') . ' (e.g. 09 Sep 2026, 09:30 PM)',
    'Y-m-d'       => date('Y-m-d') . ' (e.g. 2026-09-09)',
    'd/m/Y'       => date('d/m/Y') . ' (e.g. 09/09/2026)',
    'm/d/Y'       => date('m/d/Y') . ' (e.g. 09/09/2026)',
    'd-m-Y'       => date('d-m-Y') . ' (e.g. 09-09-2026)'
];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row g-4">
    <!-- Left Navigation Summary & Branding Preview Card -->
    <div class="col-lg-4">
        <!-- Company Identity Card -->
        <div class="card-cms text-center mb-4">
            <div class="card-cms-body p-4">
                <div class="mb-3 position-relative d-inline-block">
                    <?php if (!empty($companyLogo) && file_exists(LOGO_UPLOAD_DIR . $companyLogo)): ?>
                        <div class="p-3 bg-light rounded border d-inline-block shadow-sm">
                            <img src="<?= asset('uploads/logos/' . $companyLogo) ?>" id="logoPreview" alt="<?= e($companyName) ?>" 
                                 class="img-fluid" style="max-height: 80px; max-width: 200px; object-fit: contain;">
                        </div>
                    <?php else: ?>
                        <div class="p-4 bg-light rounded border d-inline-block shadow-sm text-muted">
                            <i class="bi bi-building fs-1 text-primary d-block mb-1"></i>
                            <span class="small font-monospace">No Logo Uploaded</span>
                        </div>
                    <?php endif; ?>
                </div>

                <h4 class="fw-bold mb-1 text-dark"><?= e($companyName) ?></h4>
                <p class="text-muted small mb-2"><?= e($companyEmail) ?></p>

                <div class="d-flex justify-content-center gap-2 mb-3">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                        <i class="bi bi-currency-exchange me-1"></i> Currency: <?= e($currency) ?>
                    </span>
                    <span class="badge bg-secondary-subtle text-dark border px-2 py-1">
                        <i class="bi bi-clock me-1"></i> <?= e($timezone) ?>
                    </span>
                </div>

                <!-- Logo Upload / Remove Form -->
                <form action="<?= url('modules/settings/update.php') ?>" method="POST" enctype="multipart/form-data" class="mt-3 pt-3 border-top text-start">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="logo">

                    <div class="mb-3">
                        <label for="companyLogoInput" class="form-label-cms small">Update Company Logo</label>
                        <input class="form-control form-control-sm" type="file" id="companyLogoInput" name="company_logo" accept="image/jpeg,image/png,image/webp,image/svg+xml" required>
                        <div class="form-text small" style="font-size: 0.75rem;">PNG, JPG, WEBP, or SVG (Max 2MB)</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-cms btn-sm flex-grow-1">
                            <i class="bi bi-upload me-1"></i> Upload Logo
                        </button>

                        <?php if (!empty($companyLogo)): ?>
                            <button type="submit" name="action" value="remove_logo" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove company logo?');" title="Remove Logo">
                                <i class="bi bi-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Help & Documentation Card -->
        <div class="card-cms">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-shield-lock-fill text-primary"></i>
                    <span>Configuration Notice</span>
                </h3>
            </div>
            <div class="card-cms-body p-3">
                <p class="small text-muted mb-2">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    System settings configure default presentation and printing across all procurement modules.
                </p>
                <ul class="small text-muted ps-3 mb-0">
                    <li class="mb-1"><strong>Reports & Printouts:</strong> Company name, logo, address, and contact details appear in header blocks.</li>
                    <li class="mb-1"><strong>Financials:</strong> Currency symbol automatically applies to Requisitions, RFQs, POs, and Reports.</li>
                    <li><strong>Localization:</strong> Date format changes format standard dates throughout the application.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Settings Configuration Forms -->
    <div class="col-lg-8">
        <!-- Settings Form -->
        <form action="<?= url('modules/settings/update.php') ?>" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="section" value="general">

            <!-- Company Information Card -->
            <div class="card-cms mb-4">
                <div class="card-cms-header d-flex justify-content-between align-items-center">
                    <h3 class="card-cms-title">
                        <i class="bi bi-building-gear text-primary"></i>
                        <span>Company & Organization Details</span>
                    </h3>
                    <span class="badge bg-light text-muted border small">General Info</span>
                </div>
                <div class="card-cms-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="company_name" class="form-label-cms">Company / Organization Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-cms" id="company_name" name="company_name" value="<?= e($companyName) ?>" required maxlength="150">
                        </div>

                        <div class="col-md-6">
                            <label for="company_email" class="form-label-cms">Procurement Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-cms" id="company_email" name="company_email" value="<?= e($companyEmail) ?>" required maxlength="150">
                        </div>

                        <div class="col-md-6">
                            <label for="company_phone" class="form-label-cms">Telephone / Phone</label>
                            <input type="text" class="form-control form-control-cms" id="company_phone" name="company_phone" value="<?= e($companyPhone) ?>" maxlength="50">
                        </div>

                        <div class="col-md-6">
                            <label for="currency" class="form-label-cms">Default Currency Symbol <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-cms font-monospace" id="currency" name="currency" value="<?= e($currency) ?>" required maxlength="10" placeholder="$, €, £, ৳, ₹">
                            <div class="form-text small">E.g., $, USD, €, £, ৳, ₹, ¥</div>
                        </div>

                        <div class="col-12">
                            <label for="company_address" class="form-label-cms">Official Business Address</label>
                            <textarea class="form-control form-control-cms" id="company_address" name="company_address" rows="3" maxlength="500"><?= e($companyAddress) ?></textarea>
                            <div class="form-text small">Printed on formal Purchase Order and Goods Receipt documents.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Regional & Localization Settings Card -->
            <div class="card-cms mb-4">
                <div class="card-cms-header d-flex justify-content-between align-items-center">
                    <h3 class="card-cms-title">
                        <i class="bi bi-globe-americas text-primary"></i>
                        <span>Localization & Formats</span>
                    </h3>
                    <span class="badge bg-light text-muted border small">Locale</span>
                </div>
                <div class="card-cms-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="date_format" class="form-label-cms">System Date Format <span class="text-danger">*</span></label>
                            <select class="form-select form-control-cms" id="date_format" name="date_format" required>
                                <?php foreach ($dateFormats as $fmtKey => $fmtLabel): ?>
                                    <option value="<?= e($fmtKey) ?>" <?= ($dateFormat === $fmtKey) ? 'selected' : '' ?>>
                                        <?= e($fmtLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="timezone" class="form-label-cms">System Timezone <span class="text-danger">*</span></label>
                            <select class="form-select form-control-cms" id="timezone" name="timezone" required>
                                <?php foreach ($timezones as $tzKey => $tzLabel): ?>
                                    <option value="<?= e($tzKey) ?>" <?= ($timezone === $tzKey) ? 'selected' : '' ?>>
                                        <?= e($tzLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-cms-footer p-3 bg-light border-top d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary-cms px-4">
                        <i class="bi bi-check-lg me-1"></i> Save System Settings
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
