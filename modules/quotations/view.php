<?php
/**
 * View Quotation Details
 * Procurement Management CMS - Phase 03
 */

$pageTitle = 'Quotation Details';
$pageSubtitle = 'Review Vendor Bid & Commercial Breakdown';
$activeNav = 'quotations';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canManage = $isAdmin || $isManager || $isOfficer;
$canApprove = $isAdmin || $isManager;

$quotationId = (int)($_GET['id'] ?? 0);
if ($quotationId <= 0) {
    $_SESSION['flash_error'] = 'Invalid quotation ID.';
    redirect('modules/quotations/index.php');
}

// 1. Fetch Quotation with PR, Supplier, and User details
$quotation = null;
try {
    $stmt = $db->prepare("
        SELECT q.*,
               pr.request_no,
               pr.purpose AS pr_purpose,
               pr.status AS pr_status,
               pr.estimated_total AS pr_estimated_cost,
               d.name AS department_name,
               u_req.full_name AS requester_name,
               s.name AS supplier_name,
               s.supplier_code,
               s.email AS supplier_email,
               s.phone AS supplier_phone,
               s.tax_number AS supplier_tax,
               s.address AS supplier_address,
               s.city AS supplier_city,
               s.country AS supplier_country,
               u_cr.full_name AS creator_name,
               (SELECT COUNT(*) FROM quotations q2 WHERE q2.purchase_request_id = q.purchase_request_id AND q2.deleted_at IS NULL) AS total_bids_count
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        LEFT JOIN departments d ON d.id = pr.department_id
        LEFT JOIN users u_req ON u_req.id = pr.requested_by
        JOIN suppliers s ON s.id = q.supplier_id
        LEFT JOIN users u_cr ON u_cr.id = q.created_by
        WHERE q.id = :id AND q.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $quotationId]);
    $quotation = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching quotation: ' . $e->getMessage());
}

if (!$quotation) {
    $_SESSION['flash_error'] = 'Quotation record not found or has been deleted.';
    redirect('modules/quotations/index.php');
}

// 2. Fetch Quotation Items
$items = [];
try {
    $itemStmt = $db->prepare("
        SELECT qi.*,
               pri.item_name,
               pri.description AS pr_item_desc,
               pri.unit,
               pri.estimated_unit_price
        FROM quotation_items qi
        LEFT JOIN purchase_request_items pri ON pri.id = qi.purchase_request_item_id
        WHERE qi.quotation_id = :id
        ORDER BY qi.id ASC
    ");
    $itemStmt->execute([':id' => $quotationId]);
    $items = $itemStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching quotation items: ' . $e->getMessage());
}

// 3. Fetch Quotation Audit History
$history = [];
try {
    $hStmt = $db->prepare("
        SELECT qh.*, u.full_name, u.username
        FROM quotation_history qh
        LEFT JOIN users u ON u.id = qh.user_id
        WHERE qh.quotation_id = :id
        ORDER BY qh.created_at ASC, qh.id ASC
    ");
    $hStmt->execute([':id' => $quotationId]);
    $history = $hStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching quotation history: ' . $e->getMessage());
}

// 4. Fetch Linked Purchase Order (Phase 04 Integration)
$linkedPo = null;
try {
    $poStmt = $db->prepare("
        SELECT id, po_no, status, grand_total, created_at, sent_at
        FROM purchase_orders
        WHERE quotation_id = :qid AND deleted_at IS NULL
        LIMIT 1
    ");
    $poStmt->execute([':qid' => $quotationId]);
    $linkedPo = $poStmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching linked PO: ' . $e->getMessage());
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
                    <li class="breadcrumb-item"><a href="<?= url('modules/quotations/index.php') ?>" class="text-decoration-none">Quotations</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($quotation['quotation_no']) ?></li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 mb-0 text-gray-800 fw-bold"><?= e($quotation['quotation_no']) ?></h1>
                <?= getQuotationStatusBadge($quotation['status']) ?>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Vendor: <strong class="text-dark"><?= e($quotation['supplier_name']) ?></strong> (<?= e($quotation['supplier_code']) ?>) &bull;
                For PR: <a href="<?= url('modules/purchase_requests/view.php?id=' . $quotation['purchase_request_id']) ?>" class="text-primary fw-semibold text-decoration-none"><?= e($quotation['request_no']) ?></a>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($quotation['total_bids_count'] > 1): ?>
                <a href="<?= url('modules/quotations/compare.php?purchase_request_id=' . $quotation['purchase_request_id']) ?>" class="btn btn-outline-info d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-layout-three-columns"></i>
                    <span>Compare All Bids (<?= $quotation['total_bids_count'] ?>)</span>
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm" onclick="window.print();">
                <i class="bi bi-printer"></i>
                <span>Print</span>
            </button>

            <a href="<?= url('modules/quotations/index.php') ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
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

    <!-- Status Specific Hero Banners -->
    <?php if ($quotation['status'] === 'selected'): ?>
        <div class="alert alert-success border-success-subtle shadow-sm d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-3 rounded-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-success text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-trophy-fill fs-4"></i>
                </div>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Winning Quotation Awarded</h5>
                    <p class="mb-0 small text-success-emphasis">
                        This quotation has been officially awarded and selected as the winning bid for Purchase Request <strong><?= e($quotation['request_no']) ?></strong>.
                    </p>
                </div>
            </div>
            <div>
                <?php if ($linkedPo): ?>
                    <a href="<?= url('modules/purchase_orders/view.php?id=' . $linkedPo['id']) ?>" class="btn btn-success d-inline-flex align-items-center gap-2 shadow-sm">
                        <i class="bi bi-file-earmark-check-fill"></i>
                        <span>View PO: <?= e($linkedPo['po_no']) ?></span>
                    </a>
                <?php elseif ($isAdmin || $isOfficer): ?>
                    <a href="<?= url('modules/purchase_orders/create.php?quotation_id=' . $quotation['id']) ?>" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                        <i class="bi bi-file-earmark-plus-fill"></i>
                        <span>Generate Purchase Order</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($quotation['status'] === 'rejected'): ?>
        <div class="alert alert-danger border-danger-subtle shadow-sm d-flex align-items-center gap-3 p-3 rounded-3 mb-4">
            <div class="bg-danger text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                <i class="bi bi-x-circle-fill fs-4"></i>
            </div>
            <div>
                <h5 class="alert-heading fw-bold mb-1">Quotation Rejected / Not Awarded</h5>
                <p class="mb-0 small text-danger-emphasis">
                    This quotation was not selected. Check the timeline history below for decision remarks.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Action Bar for Draft / Review / Selection Transitions -->
    <?php if ($canManage && in_array($quotation['status'], ['draft', 'submitted', 'under_review'])): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-start border-primary border-4">
            <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-lightning-charge-fill text-warning fs-5"></i>
                    <div>
                        <span class="fw-bold text-dark">Workflow Actions:</span>
                        <span class="text-muted small ms-1">
                            Current Status: <strong><?= ucfirst(str_replace('_', ' ', $quotation['status'])) ?></strong>
                        </span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <!-- Draft Actions -->
                    <?php if ($quotation['status'] === 'draft'): ?>
                        <a href="<?= url('modules/quotations/edit.php?id=' . $quotation['id']) ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                            <i class="bi bi-pencil"></i> Edit Draft
                        </a>
                        <form method="POST" action="<?= url('modules/quotations/submit.php') ?>" class="d-inline" onsubmit="return confirm('Submit this quotation for formal review?');">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $quotation['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                                <i class="bi bi-send"></i> Submit for Review
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteDraftModal">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    <?php endif; ?>

                    <!-- Submitted Actions -->
                    <?php if ($quotation['status'] === 'submitted'): ?>
                        <form method="POST" action="<?= url('modules/quotations/review.php') ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $quotation['id'] ?>">
                            <button type="submit" class="btn btn-warning btn-sm d-inline-flex align-items-center gap-1 text-dark fw-semibold">
                                <i class="bi bi-search"></i> Mark Under Review
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Award / Reject Actions (Admin / Manager) -->
                    <?php if ($canApprove && in_array($quotation['status'], ['submitted', 'under_review'])): ?>
                        <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#selectWinningModal">
                            <i class="bi bi-trophy-fill"></i> Award Winning Bid
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bi bi-x-circle"></i> Reject Bid
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Left Column: Line Items, Breakdown, Terms -->
        <div class="col-lg-8">
            <!-- Line Items Table Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-list-check text-primary"></i> Quotation Line Items
                    </h6>
                    <span class="badge bg-light text-secondary border"><?= count($items) ?> items</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3" style="width: 5%;">#</th>
                                    <th style="width: 40%;">Item Name & Remarks</th>
                                    <th class="text-center" style="width: 15%;">Quantity</th>
                                    <th class="text-end" style="width: 20%;">Offered Price</th>
                                    <th class="text-end pe-3" style="width: 20%;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $idx => $item): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($item['item_name']) ?></div>
                                            <?php if (!empty($item['pr_item_desc'])): ?>
                                                <div class="small text-muted"><?= e($item['pr_item_desc']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['remarks'])): ?>
                                                <div class="small text-primary mt-1">
                                                    <i class="bi bi-chat-left-quote me-1"></i><?= e($item['remarks']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="fw-semibold text-dark font-monospace"><?= number_format((float)$item['quantity'], 2) ?></span>
                                            <span class="small text-muted ms-1"><?= e($item['unit']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <span class="font-monospace text-dark"><?= formatCurrency($item['unit_price']) ?></span>
                                            <?php if ((float)$item['estimated_unit_price'] > 0): ?>
                                                <?php
                                                    $diff = (float)$item['unit_price'] - (float)$item['estimated_unit_price'];
                                                    $isLower = $diff < 0;
                                                ?>
                                                <div class="small <?= $isLower ? 'text-success' : 'text-danger' ?>" style="font-size: 0.75rem;">
                                                    <?= $isLower ? '-' : '+' ?><?= formatCurrency(abs($diff)) ?> vs Est
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3 fw-bold font-monospace text-dark">
                                            <?= formatCurrency($item['subtotal']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Commercial Terms Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-text text-primary"></i> Commercial & Delivery Terms
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Payment Terms</span>
                            <span class="fw-semibold text-dark"><?= e($quotation['payment_terms'] ?: 'Not specified') ?></span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Delivery Terms / Lead Time</span>
                            <span class="fw-semibold text-dark"><?= e($quotation['delivery_terms'] ?: 'Not specified') ?></span>
                        </div>
                        <?php if (!empty($quotation['notes'])): ?>
                            <div class="col-12"><hr class="my-1 text-muted"></div>
                            <div class="col-12">
                                <span class="text-muted small d-block mb-1">Vendor Remarks & Special Conditions</span>
                                <div class="bg-light p-3 rounded text-secondary small">
                                    <?= nl2br(e($quotation['notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Financial Summary, Supplier Info, Timeline -->
        <div class="col-lg-4">
            <!-- Financial Breakdown Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-calculator text-primary"></i> Financial Breakdown
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Items Subtotal:</span>
                        <span class="fw-semibold font-monospace text-dark"><?= formatCurrency($quotation['subtotal']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Tax / VAT (<?= number_format((float)$quotation['tax_percentage'], 2) ?>%):</span>
                        <span class="font-monospace text-dark">+<?= formatCurrency($quotation['tax_amount']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Shipping & Freight:</span>
                        <span class="font-monospace text-dark">+<?= formatCurrency($quotation['shipping_cost']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Other Surcharges:</span>
                        <span class="font-monospace text-dark">+<?= formatCurrency($quotation['other_charges']) ?></span>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark fs-5">Grand Total:</span>
                        <span class="fw-bold font-monospace text-primary fs-4"><?= formatCurrency($quotation['total_amount']) ?></span>
                    </div>

                    <?php if ((float)$quotation['pr_estimated_cost'] > 0): ?>
                        <div class="mt-3 pt-2 border-top text-center">
                            <span class="small text-muted d-block">Original PR Budget: <?= formatCurrency($quotation['pr_estimated_cost']) ?></span>
                            <?php
                                $budgetDiff = (float)$quotation['total_amount'] - (float)$quotation['pr_estimated_cost'];
                                $isUnderBudget = $budgetDiff <= 0;
                            ?>
                            <span class="badge <?= $isUnderBudget ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> mt-1">
                                <i class="bi <?= $isUnderBudget ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle' ?> me-1"></i>
                                <?= $isUnderBudget ? 'Under Budget by ' : 'Exceeds Budget by ' ?><?= formatCurrency(abs($budgetDiff)) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Supplier Profile Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-building text-primary"></i> Supplier Profile
                    </h6>
                    <a href="<?= url('modules/suppliers/view.php?id=' . $quotation['supplier_id']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <div class="card-body p-4">
                    <div class="fw-bold text-dark mb-1 fs-6"><?= e($quotation['supplier_name']) ?></div>
                    <div class="small text-muted font-monospace mb-3"><?= e($quotation['supplier_code']) ?></div>

                    <div class="small mb-2">
                        <span class="text-muted d-block">Contact Email:</span>
                        <a href="mailto:<?= e($quotation['supplier_email']) ?>" class="text-decoration-none text-dark fw-medium"><?= e($quotation['supplier_email'] ?: 'Not provided') ?></a>
                    </div>
                    <div class="small mb-2">
                        <span class="text-muted d-block">Phone:</span>
                        <span class="fw-medium text-dark"><?= e($quotation['supplier_phone'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="small mb-0">
                        <span class="text-muted d-block">Location:</span>
                        <span class="fw-medium text-dark">
                            <?= e($quotation['supplier_city'] ?? '') ?><?= (!empty($quotation['supplier_city']) && !empty($quotation['supplier_country'])) ? ', ' : '' ?><?= e($quotation['supplier_country'] ?? '') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Purchase Request Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-text text-primary"></i> Linked Requisition
                    </h6>
                    <a href="<?= url('modules/purchase_requests/view.php?id=' . $quotation['purchase_request_id']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <div class="card-body p-4">
                    <div class="fw-bold text-dark mb-1">
                        <a href="<?= url('modules/purchase_requests/view.php?id=' . $quotation['purchase_request_id']) ?>" class="text-decoration-none text-primary font-monospace">
                            <?= e($quotation['request_no']) ?>
                        </a>
                    </div>
                    <div class="small text-muted mb-2"><?= e($quotation['pr_purpose']) ?></div>
                    <div class="small text-muted">
                        Department: <strong class="text-dark"><?= e($quotation['department_name'] ?? 'N/A') ?></strong><br>
                        Requester: <strong class="text-dark"><?= e($quotation['requester_name'] ?? 'N/A') ?></strong>
                    </div>
                </div>
            </div>

            <!-- Workflow Audit Timeline -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-primary"></i> Quotation Timeline
                    </h6>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($history)): ?>
                        <div class="text-muted small text-center py-2">No history logs recorded.</div>
                    <?php else: ?>
                        <div class="timeline position-relative">
                            <?php foreach ($history as $h): ?>
                                <div class="mb-3 ps-3 border-start border-2 border-primary position-relative">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <span class="fw-bold text-dark small text-capitalize"><?= str_replace('_', ' ', e($h['action'])) ?></span>
                                        <span class="text-muted small" style="font-size: 0.72rem;"><?= formatDate($h['created_at'], 'd M, H:i') ?></span>
                                    </div>
                                    <div class="text-muted small">By: <?= e($h['full_name'] ?? 'System') ?></div>
                                    <?php if (!empty($h['comments'])): ?>
                                        <div class="text-secondary small fst-italic mt-1 bg-light p-2 rounded">
                                            <?= e($h['comments']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Award / Select Winning Bid (Admin / Manager) -->
<?php if ($canApprove): ?>
    <div class="modal fade" id="selectWinningModal" tabindex="-1" aria-labelledby="selectWinningModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="<?= url('modules/quotations/select.php') ?>">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="id" value="<?= $quotation['id'] ?>">

                    <div class="modal-header border-bottom bg-success-subtle">
                        <h5 class="modal-title text-success fw-bold fs-6" id="selectWinningModalLabel">
                            <i class="bi bi-trophy-fill me-2"></i>Award Winning Quotation
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <p class="mb-2">
                            Are you sure you want to award this contract to <strong><?= e($quotation['supplier_name']) ?></strong> for <strong class="text-primary font-monospace"><?= formatCurrency($quotation['total_amount']) ?></strong>?
                        </p>
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Important:</strong> Selecting this quotation will automatically mark all other competing quotation bids for PR <strong><?= e($quotation['request_no']) ?></strong> as <strong>Rejected</strong>.
                        </div>
                        <div class="mb-0">
                            <label for="selection_comments" class="form-label fw-semibold">Selection Justification / Remarks (Optional)</label>
                            <textarea class="form-control" id="selection_comments" name="selection_comments" rows="3" placeholder="Reason for selecting this bidder (e.g. Best price, fastest delivery, technical compliance)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i>Confirm Selection & Award
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Reject Bid -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="<?= url('modules/quotations/reject.php') ?>">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="id" value="<?= $quotation['id'] ?>">

                    <div class="modal-header border-bottom bg-danger-subtle">
                        <h5 class="modal-title text-danger fw-bold fs-6" id="rejectModalLabel">
                            <i class="bi bi-x-circle-fill me-2"></i>Reject Quotation Bid
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <p class="mb-3">
                            Reject quotation <strong><?= e($quotation['quotation_no']) ?></strong> from <strong><?= e($quotation['supplier_name']) ?></strong>.
                        </p>
                        <div class="mb-0">
                            <label for="rejection_reason" class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required placeholder="State the reason for rejecting this bid (e.g. Price too high, failed technical spec, delivery delay)..."></textarea>
                            <div class="form-text small text-danger">A valid reason is mandatory for audit logging.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Delete Draft (Draft Only) -->
<?php if ($quotation['status'] === 'draft' && $canManage): ?>
    <div class="modal fade" id="deleteDraftModal" tabindex="-1" aria-labelledby="deleteDraftModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="<?= url('modules/quotations/delete.php') ?>">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="id" value="<?= $quotation['id'] ?>">

                    <div class="modal-header border-bottom">
                        <h5 class="modal-title text-danger fw-bold fs-6" id="deleteDraftModalLabel">
                            <i class="bi bi-trash me-2"></i>Delete Draft Quotation
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        Are you sure you want to soft-delete draft quotation <strong><?= e($quotation['quotation_no']) ?></strong>?
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">Delete Draft</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
