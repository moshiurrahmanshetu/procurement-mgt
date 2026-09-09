<?php
/**
 * View Supplier Details
 * Procurement Management CMS - Phase 03
 */

$pageTitle = 'Supplier Profile';
$pageSubtitle = 'Vendor Details, Contacts, and Quotation History';
$activeNav = 'suppliers';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canManage = $isAdmin || $isManager || $isOfficer;

$supplierId = (int)($_GET['id'] ?? 0);
if ($supplierId <= 0) {
    $_SESSION['flash_error'] = 'Invalid supplier ID.';
    redirect('modules/suppliers/index.php');
}

// 1. Fetch Supplier Info
$supplier = null;
try {
    $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching supplier: ' . $e->getMessage());
}

if (!$supplier) {
    $_SESSION['flash_error'] = 'Supplier record not found or has been deleted.';
    redirect('modules/suppliers/index.php');
}

// 2. Fetch Supplier Contacts
$contacts = [];
try {
    $cStmt = $db->prepare("SELECT * FROM supplier_contacts WHERE supplier_id = :id ORDER BY is_primary DESC, id ASC");
    $cStmt->execute([':id' => $supplierId]);
    $contacts = $cStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching supplier contacts: ' . $e->getMessage());
}

// 3. Fetch Quotations submitted by this supplier
$quotations = [];
try {
    $qStmt = $db->prepare("
        SELECT q.*, pr.request_no, pr.purpose AS pr_purpose, pr.status AS pr_status
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        WHERE q.supplier_id = :id AND q.deleted_at IS NULL
        ORDER BY q.id DESC
    ");
    $qStmt->execute([':id' => $supplierId]);
    $quotations = $qStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching supplier quotations: ' . $e->getMessage());
}

// Quotation stats for this supplier
$totalBids = count($quotations);
$awardedBids = 0;
$totalAwardedValue = 0;
foreach ($quotations as $q) {
    if ($q['status'] === 'selected') {
        $awardedBids++;
        $totalAwardedValue += (float)$q['total_amount'];
    }
}

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
                    <li class="breadcrumb-item active" aria-current="page"><?= e($supplier['supplier_code']) ?></li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 mb-0 text-gray-800 fw-bold"><?= e($supplier['name']) ?></h1>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1 fs-6">
                    <?= e($supplier['supplier_code']) ?>
                </span>
                <?= getSupplierStatusBadge($supplier['status']) ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canManage): ?>
                <a href="<?= url('modules/suppliers/edit.php?id=' . $supplier['id']) ?>" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
                    <i class="bi bi-pencil"></i>
                    <span>Edit Profile</span>
                </a>
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addContactModal">
                    <i class="bi bi-person-plus"></i>
                    <span>Add Contact</span>
                </button>
            <?php endif; ?>
            <a href="<?= url('modules/suppliers/index.php') ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-primary border-4 h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Quotations Submitted</div>
                        <div class="h4 mb-0 fw-bold text-gray-800"><?= $totalBids ?></div>
                    </div>
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                        <i class="bi bi-file-earmark-text fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4 h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Awarded Contracts</div>
                        <div class="h4 mb-0 fw-bold text-success"><?= $awardedBids ?></div>
                    </div>
                    <div class="bg-success-subtle text-success p-3 rounded-circle">
                        <i class="bi bi-trophy fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-info border-4 h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Awarded Value</div>
                        <div class="h4 mb-0 fw-bold text-info"><?= formatCurrency($totalAwardedValue) ?></div>
                    </div>
                    <div class="bg-info-subtle text-info p-3 rounded-circle">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Left Column: Details & Banking -->
        <div class="col-lg-7">
            <!-- Company Overview -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-building text-primary"></i> Company Profile & Details
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Company Email</span>
                            <?php if (!empty($supplier['email'])): ?>
                                <a href="mailto:<?= e($supplier['email']) ?>" class="fw-semibold text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-envelope text-muted"></i> <?= e($supplier['email']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Not provided</span>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Company Phone</span>
                            <?php if (!empty($supplier['phone'])): ?>
                                <span class="fw-semibold text-dark d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-telephone text-muted"></i> <?= e($supplier['phone']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Not provided</span>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Tax ID / VAT Registration</span>
                            <?php if (!empty($supplier['tax_number'])): ?>
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                    <?= e($supplier['tax_number']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Not provided</span>
                            <?php endif; ?>
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Website</span>
                            <?php if (!empty($supplier['website'])): ?>
                                <a href="<?= e($supplier['website']) ?>" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-globe"></i> <?= e($supplier['website']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Not provided</span>
                            <?php endif; ?>
                        </div>

                        <div class="col-12"><hr class="my-1 text-muted"></div>

                        <div class="col-12">
                            <span class="text-muted small d-block">Physical Address</span>
                            <div class="fw-medium text-dark">
                                <?= !empty($supplier['address']) ? nl2br(e($supplier['address'])) . '<br>' : '' ?>
                                <?= e($supplier['city'] ?? '') ?><?= (!empty($supplier['city']) && !empty($supplier['state'])) ? ', ' : '' ?><?= e($supplier['state'] ?? '') ?>
                                <?= e($supplier['postal_code'] ?? '') ?>
                                <?= (!empty($supplier['country'])) ? '<br>' . e($supplier['country']) : '' ?>
                                <?php if (empty($supplier['address']) && empty($supplier['city']) && empty($supplier['country'])): ?>
                                    <span class="text-muted fst-italic">No address on file</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($supplier['notes'])): ?>
                            <div class="col-12"><hr class="my-1 text-muted"></div>
                            <div class="col-12">
                                <span class="text-muted small d-block mb-1">Internal Notes</span>
                                <div class="bg-light p-3 rounded text-secondary small">
                                    <?= nl2br(e($supplier['notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-light py-2 px-4 border-top text-muted small d-flex justify-content-between">
                    <span>Registered: <?= formatDate($supplier['created_at']) ?></span>
                    <span>Last Updated: <?= formatDate($supplier['updated_at']) ?></span>
                </div>
            </div>

            <!-- Banking & Remittance -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-credit-card text-primary"></i> Banking & Remittance Details
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Bank Name</span>
                            <span class="fw-semibold text-dark"><?= e($supplier['bank_name'] ?: 'Not configured') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Account Holder</span>
                            <span class="fw-semibold text-dark"><?= e($supplier['bank_account_name'] ?: 'Not configured') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Account / IBAN Number</span>
                            <span class="fw-semibold font-monospace text-dark"><?= e($supplier['bank_account_number'] ?: 'Not configured') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Routing / SWIFT Code</span>
                            <span class="fw-semibold font-monospace text-dark"><?= e($supplier['bank_routing'] ?: 'Not configured') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Contact Persons -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-people text-primary"></i> Contact Persons
                        <span class="badge bg-light text-secondary border"><?= count($contacts) ?></span>
                    </h6>
                    <?php if ($canManage): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                            <i class="bi bi-plus-lg me-1"></i>Add Contact
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($contacts)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                            <span class="fw-semibold">No contacts registered</span>
                            <p class="small text-muted mb-0">Add a primary point of contact for this vendor.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($contacts as $c): ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                                <?= e($c['name']) ?>
                                                <?php if ($c['is_primary']): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle small px-2 py-0">Primary</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($c['job_title'])): ?>
                                                <div class="text-muted small"><?= e($c['job_title']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($canManage): ?>
                                            <form method="POST" action="<?= url('modules/suppliers/delete-contact.php') ?>" onsubmit="return confirm('Remove contact person <?= e($c['name']) ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                <input type="hidden" name="supplier_id" value="<?= $supplier['id'] ?>">
                                                <input type="hidden" name="contact_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Contact">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small">
                                        <?php if (!empty($c['email'])): ?>
                                            <div class="text-muted"><i class="bi bi-envelope me-1"></i><a href="mailto:<?= e($c['email']) ?>" class="text-decoration-none text-muted"><?= e($c['email']) ?></a></div>
                                        <?php endif; ?>
                                        <?php if (!empty($c['phone'])): ?>
                                            <div class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($c['phone']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($c['mobile'])): ?>
                                            <div class="text-muted"><i class="bi bi-phone me-1"></i><?= e($c['mobile']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quotation History Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-receipt-cutoff text-primary"></i> Quotation & Bid History
                <span class="badge bg-light text-secondary border"><?= count($quotations) ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3">Quotation No</th>
                            <th>Purchase Request</th>
                            <th>Quotation Date</th>
                            <th>Valid Until</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quotations)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    <span class="fw-semibold">No quotations recorded for this supplier</span>
                                    <p class="small text-muted mb-0">Quotations submitted for approved purchase requests will appear here.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($quotations as $q): ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="<?= url('modules/quotations/view.php?id=' . $q['id']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none fw-semibold font-monospace py-1 px-2">
                                            <?= e($q['quotation_no']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <a href="<?= url('modules/purchase_requests/view.php?id=' . $q['purchase_request_id']) ?>" class="fw-semibold text-dark text-decoration-none">
                                                <?= e($q['request_no']) ?>
                                            </a>
                                        </div>
                                        <span class="small text-muted text-truncate d-inline-block" style="max-width: 250px;">
                                            <?= e($q['pr_purpose']) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= formatDate($q['quotation_date'], 'd M Y') ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= !empty($q['valid_until']) ? formatDate($q['valid_until'], 'd M Y') : '<span class="fst-italic text-muted">Not specified</span>' ?>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace">
                                        <?= formatCurrency($q['total_amount']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?= getQuotationStatusBadge($q['status']) ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="<?= url('modules/quotations/view.php?id=' . $q['id']) ?>" class="btn btn-sm btn-outline-secondary" title="View Quotation">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Contact Person -->
<?php if ($canManage): ?>
    <div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="<?= url('modules/suppliers/store-contact.php') ?>">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="supplier_id" value="<?= $supplier['id'] ?>">

                    <div class="modal-header border-bottom">
                        <h5 class="modal-title fw-bold fs-6" id="addContactModalLabel">
                            <i class="bi bi-person-plus text-primary me-2"></i>Add Contact Person
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <div class="mb-3">
                            <label for="modal_contact_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modal_contact_name" name="name" required placeholder="e.g. Jane Doe">
                        </div>
                        <div class="mb-3">
                            <label for="modal_contact_job" class="form-label fw-semibold">Designation / Role</label>
                            <input type="text" class="form-control" id="modal_contact_job" name="job_title" placeholder="e.g. Sales Manager">
                        </div>
                        <div class="mb-3">
                            <label for="modal_contact_email" class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" id="modal_contact_email" name="email" placeholder="jane@supplier.com">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="modal_contact_phone" class="form-label fw-semibold">Phone</label>
                                <input type="text" class="form-control" id="modal_contact_phone" name="phone" placeholder="+1 (555) ...">
                            </div>
                            <div class="col-6">
                                <label for="modal_contact_mobile" class="form-label fw-semibold">Mobile</label>
                                <input type="text" class="form-control" id="modal_contact_mobile" name="mobile" placeholder="+1 (555) ...">
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="modal_is_primary" name="is_primary" value="1">
                            <label class="form-check-label fw-medium" for="modal_is_primary">
                                Set as Primary Contact Person
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
