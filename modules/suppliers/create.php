<?php
/**
 * Create Supplier View
 * Procurement Management CMS - Phase 03
 */

$pageTitle = 'Add Supplier';
$pageSubtitle = 'Register a New Vendor or Contractor';
$activeNav = 'suppliers';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// Permission check: Admin, Manager, Procurement Officer
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to add suppliers.';
    redirect('modules/suppliers/index.php');
}

$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('modules/dashboard/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('modules/suppliers/index.php') ?>" class="text-decoration-none">Suppliers</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add Supplier</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Add New Supplier</h1>
            <p class="text-muted small mb-0">Enter vendor company profile, address, financial details, and primary contact person.</p>
        </div>
        <div>
            <a href="<?= url('modules/suppliers/index.php') ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Suppliers</span>
            </a>
        </div>
    </div>

    <!-- Error Alert -->
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('modules/suppliers/store.php') ?>" id="supplierForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

        <div class="row g-4">
            <!-- Left Column: Company Profile & Address -->
            <div class="col-lg-8">
                <!-- Basic Information Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-building text-primary"></i> Company Information
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="name" class="form-label fw-semibold">Company / Supplier Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Apex Global Supplies Ltd." value="<?= e($old['name'] ?? '') ?>" required>
                                <div class="invalid-feedback">Please enter the supplier name.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">Company Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="sales@apexsupplies.com" value="<?= e($old['email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold">Company Phone</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
                                    <input type="text" class="form-control" id="phone" name="phone" placeholder="+1 (555) 019-2834" value="<?= e($old['phone'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="tax_number" class="form-label fw-semibold">Tax ID / VAT Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-receipt"></i></span>
                                    <input type="text" class="form-control font-monospace" id="tax_number" name="tax_number" placeholder="TAX-9982341" value="<?= e($old['tax_number'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="website" class="form-label fw-semibold">Website URL</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-globe"></i></span>
                                    <input type="url" class="form-control" id="website" name="website" placeholder="https://www.apexsupplies.com" value="<?= e($old['website'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Information Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt text-primary"></i> Address & Location
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="address" class="form-label fw-semibold">Street Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Suite 400, Industrial Commerce Blvd."><?= e($old['address'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="city" class="form-label fw-semibold">City</label>
                                <input type="text" class="form-control" id="city" name="city" placeholder="e.g. New York" value="<?= e($old['city'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="state" class="form-label fw-semibold">State / Province</label>
                                <input type="text" class="form-control" id="state" name="state" placeholder="e.g. NY" value="<?= e($old['state'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="country" class="form-label fw-semibold">Country</label>
                                <input type="text" class="form-control" id="country" name="country" placeholder="e.g. United States" value="<?= e($old['country'] ?? 'United States') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="postal_code" class="form-label fw-semibold">Postal / Zip Code</label>
                                <input type="text" class="form-control font-monospace" id="postal_code" name="postal_code" placeholder="10001" value="<?= e($old['postal_code'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Primary Contact Person Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-person-badge text-primary"></i> Primary Contact Person (Optional)
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="contact_name" class="form-label fw-semibold">Contact Full Name</label>
                                <input type="text" class="form-control" id="contact_name" name="contact_name" placeholder="e.g. Robert Smith" value="<?= e($old['contact_name'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="contact_job_title" class="form-label fw-semibold">Designation / Role</label>
                                <input type="text" class="form-control" id="contact_job_title" name="contact_job_title" placeholder="e.g. Key Account Executive" value="<?= e($old['contact_job_title'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="contact_email" class="form-label fw-semibold">Contact Direct Email</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" placeholder="robert@apexsupplies.com" value="<?= e($old['contact_email'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="contact_phone" class="form-label fw-semibold">Contact Direct Phone</label>
                                <input type="text" class="form-control" id="contact_phone" name="contact_phone" placeholder="+1 (555) 019-2835" value="<?= e($old['contact_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Financial Details & Status Settings -->
            <div class="col-lg-4">
                <!-- Status & Control Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-sliders text-primary"></i> Account Status
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label for="status" class="form-label fw-semibold">Supplier Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active (Can Receive RFQ & Quotations)</option>
                                <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive (Suspended)</option>
                                <option value="blacklisted" <?= ($old['status'] ?? '') === 'blacklisted' ? 'selected' : '' ?>>Blacklisted (Disqualified)</option>
                            </select>
                            <div class="form-text small">Only active suppliers can submit bids and quotations.</div>
                        </div>
                    </div>
                </div>

                <!-- Banking & Payment Details Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-credit-card text-primary"></i> Banking & Payment Information
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="bank_name" class="form-label fw-semibold">Bank Name</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" placeholder="e.g. JPMorgan Chase" value="<?= e($old['bank_name'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label for="bank_account_name" class="form-label fw-semibold">Account Holder Name</label>
                                <input type="text" class="form-control" id="bank_account_name" name="bank_account_name" placeholder="Apex Global Supplies Ltd." value="<?= e($old['bank_account_name'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label for="bank_account_number" class="form-label fw-semibold">Account / IBAN Number</label>
                                <input type="text" class="form-control font-monospace" id="bank_account_number" name="bank_account_number" placeholder="889922114400" value="<?= e($old['bank_account_number'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label for="bank_routing" class="form-label fw-semibold">Routing / Swift Code</label>
                                <input type="text" class="form-control font-monospace" id="bank_routing" name="bank_routing" placeholder="CHASUS33" value="<?= e($old['bank_routing'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Internal Notes Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-sticky text-primary"></i> Internal Notes
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Internal remarks, performance notes, specialties..."><?= e($old['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-check-lg"></i>
                        <span>Save Supplier</span>
                    </button>
                    <a href="<?= url('modules/suppliers/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('supplierForm');
    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
