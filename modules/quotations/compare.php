<?php
/**
 * Quotation Comparison Matrix
 * Side-by-side analysis of all supplier bids for a specific Purchase Request
 * Procurement Management CMS - Phase 03
 */

$pageTitle = 'Compare Quotations';
$pageSubtitle = 'Side-by-Side Supplier Bid Analysis & Evaluation Matrix';
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

$prId = (int)($_GET['purchase_request_id'] ?? 0);
if ($prId <= 0) {
    $_SESSION['flash_error'] = 'Please specify a valid Purchase Request to compare quotations.';
    redirect('modules/quotations/index.php');
}

// 1. Fetch Purchase Request Details
$pr = null;
try {
    $prStmt = $db->prepare("
        SELECT pr.*, d.name AS department_name, u.full_name AS requester_name
        FROM purchase_requests pr
        LEFT JOIN departments d ON d.id = pr.department_id
        LEFT JOIN users u ON u.id = pr.requested_by
        WHERE pr.id = :id AND pr.deleted_at IS NULL
        LIMIT 1
    ");
    $prStmt->execute([':id' => $prId]);
    $pr = $prStmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching PR for comparison: ' . $e->getMessage());
}

if (!$pr) {
    $_SESSION['flash_error'] = 'Purchase Request not found.';
    redirect('modules/quotations/index.php');
}

// 2. Fetch PR Items
$prItems = [];
try {
    $itemStmt = $db->prepare("
        SELECT * FROM purchase_request_items
        WHERE purchase_request_id = :id
        ORDER BY id ASC
    ");
    $itemStmt->execute([':id' => $prId]);
    $prItems = $itemStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching PR items: ' . $e->getMessage());
}

// 3. Fetch All Quotations for this PR
$quotations = [];
try {
    $qStmt = $db->prepare("
        SELECT q.*, s.name AS supplier_name, s.supplier_code, s.email AS supplier_email, s.phone AS supplier_phone
        FROM quotations q
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE q.purchase_request_id = :id AND q.deleted_at IS NULL
        ORDER BY q.total_amount ASC, q.id ASC
    ");
    $qStmt->execute([':id' => $prId]);
    $quotations = $qStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching quotations for comparison: ' . $e->getMessage());
}

// 4. Fetch Line Items for each Quotation and map by [quotation_id][pr_item_id]
$quoteItemMap = [];
if (!empty($quotations)) {
    $quoteIds = array_column($quotations, 'id');
    $inPlaceholders = implode(',', array_fill(0, count($quoteIds), '?'));
    try {
        $qiStmt = $db->prepare("
            SELECT * FROM quotation_items
            WHERE quotation_id IN ({$inPlaceholders})
        ");
        $qiStmt->execute($quoteIds);
        $allQi = $qiStmt->fetchAll();
        foreach ($allQi as $qi) {
            $quoteItemMap[$qi['quotation_id']][$qi['purchase_request_item_id']] = $qi;
        }
    } catch (Exception $e) {
        error_log('Error mapping quotation items: ' . $e->getMessage());
    }
}

// Determine Lowest Total Bid among active bids
$lowestTotal = null;
$hasAwardedBid = false;
foreach ($quotations as $q) {
    if ($q['status'] === 'selected') {
        $hasAwardedBid = true;
    }
    if ($q['status'] !== 'rejected' && $q['status'] !== 'cancelled') {
        if ($lowestTotal === null || (float)$q['total_amount'] < $lowestTotal) {
            $lowestTotal = (float)$q['total_amount'];
        }
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
                    <li class="breadcrumb-item"><a href="<?= url('modules/quotations/index.php') ?>" class="text-decoration-none">Quotations</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="text-decoration-none"><?= e($pr['request_no']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Compare Bids</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Quotation Comparison Matrix</h1>
            <p class="text-muted small mb-0">
                Evaluation for Requisition: <strong><?= e($pr['request_no']) ?></strong> &bull; Department: <strong><?= e($pr['department_name'] ?? 'N/A') ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canManage): ?>
                <a href="<?= url('modules/quotations/create.php?purchase_request_id=' . $pr['id']) ?>" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add Another Quotation</span>
                </a>
            <?php endif; ?>
            <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Requisition</span>
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

    <!-- PR Overview Summary Banner -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-start border-primary border-4">
        <div class="card-body p-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <span class="text-muted small d-block">Requisition Reference</span>
                    <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="fw-bold text-primary font-monospace fs-6 text-decoration-none">
                        <?= e($pr['request_no']) ?>
                    </a>
                    <?= getStatusBadge($pr['status']) ?>
                </div>
                <div class="col-md-3">
                    <span class="text-muted small d-block">Purpose / Justification</span>
                    <span class="fw-medium text-dark"><?= e($pr['purpose']) ?></span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted small d-block">Estimated Budget Cost</span>
                    <span class="fw-bold text-success font-monospace fs-6"><?= formatCurrency($pr['estimated_total']) ?></span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted small d-block">Quotations Received</span>
                    <span class="fw-bold text-dark fs-6"><?= count($quotations) ?> Vendor Bids</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($quotations)): ?>
        <div class="card border-0 shadow-sm rounded-3 py-5 text-center text-muted">
            <i class="bi bi-layout-three-columns fs-1 text-secondary mb-3"></i>
            <h5 class="fw-bold text-dark">No Quotations to Compare</h5>
            <p class="mb-3 text-muted">There are no supplier quotations recorded for this purchase request yet.</p>
            <div>
                <a href="<?= url('modules/quotations/create.php?purchase_request_id=' . $pr['id']) ?>" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Record First Quotation
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Comparison Matrix Table -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-table text-primary"></i> Side-by-Side Quotation Comparison
                </h6>
                <div class="small text-muted">
                    <span class="badge bg-success-subtle text-success border border-success-subtle me-1"><i class="bi bi-check-circle me-1"></i>Green highlights indicate lowest price</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0 comparison-table">
                        <!-- Column Headers: Quotation & Supplier Details -->
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 260px; width: 25%;" class="bg-light align-top p-3">
                                    <div class="fw-bold text-dark text-uppercase small mb-1">Requisition Items</div>
                                    <div class="text-muted small">Required specs & estimated budget</div>
                                </th>
                                <?php foreach ($quotations as $q): ?>
                                    <?php
                                        $isLowest = ($lowestTotal !== null && (float)$q['total_amount'] === $lowestTotal && $q['status'] !== 'rejected');
                                        $isSelected = ($q['status'] === 'selected');
                                    ?>
                                    <th style="min-width: 240px; width: <?= floor(75 / count($quotations)) ?>%;" class="p-3 align-top <?= $isSelected ? 'bg-success-subtle border-success' : ($isLowest ? 'bg-light border-primary' : '') ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <a href="<?= url('modules/quotations/view.php?id=' . $q['id']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none font-monospace py-1 px-2">
                                                <?= e($q['quotation_no']) ?>
                                            </a>
                                            <?= getQuotationStatusBadge($q['status']) ?>
                                        </div>
                                        <div class="fw-bold text-dark fs-6 mt-1">
                                            <a href="<?= url('modules/suppliers/view.php?id=' . $q['supplier_id']) ?>" class="text-dark text-decoration-none hover-primary">
                                                <?= e($q['supplier_name']) ?>
                                            </a>
                                        </div>
                                        <div class="small text-muted font-monospace mb-2"><?= e($q['supplier_code']) ?></div>

                                        <?php if ($isSelected): ?>
                                            <div class="badge bg-success text-white py-1 px-2 d-inline-flex align-items-center gap-1 mb-2">
                                                <i class="bi bi-trophy-fill"></i> Awarded Winner
                                            </div>
                                        <?php elseif ($isLowest): ?>
                                            <div class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 d-inline-flex align-items-center gap-1 mb-2">
                                                <i class="bi bi-arrow-down-circle-fill"></i> Lowest Grand Bid
                                            </div>
                                        <?php endif; ?>

                                        <!-- Selection Action Button -->
                                        <?php if ($canApprove && in_array($q['status'], ['submitted', 'under_review', 'draft'])): ?>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-success w-100 d-inline-flex align-items-center justify-content-center gap-1" data-bs-toggle="modal" data-bs-target="#awardModal<?= $q['id'] ?>">
                                                    <i class="bi bi-check2-circle"></i> Award Bid
                                                </button>
                                            </div>

                                            <!-- Modal: Award Bid -->
                                            <div class="modal fade text-start" id="awardModal<?= $q['id'] ?>" tabindex="-1" aria-labelledby="awardModalLabel<?= $q['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <form method="POST" action="<?= url('modules/quotations/select.php') ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                            <input type="hidden" name="id" value="<?= $q['id'] ?>">

                                                            <div class="modal-header border-bottom bg-success-subtle">
                                                                <h5 class="modal-title text-success fw-bold fs-6" id="awardModalLabel<?= $q['id'] ?>">
                                                                    <i class="bi bi-trophy-fill me-2"></i>Award Contract to <?= e($q['supplier_name']) ?>
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body py-4">
                                                                <p class="mb-2">
                                                                    Award winning quotation <strong><?= e($q['quotation_no']) ?></strong> for <strong class="text-primary font-monospace"><?= formatCurrency($q['total_amount']) ?></strong>.
                                                                </p>
                                                                <div class="alert alert-warning small mb-3">
                                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                                    Selecting this bid will automatically mark all other <?= count($quotations) - 1 ?> competing quotations as <strong>Rejected</strong>.
                                                                </div>
                                                                <div class="mb-0">
                                                                    <label for="comments_<?= $q['id'] ?>" class="form-label fw-semibold">Selection Remarks</label>
                                                                    <textarea class="form-control" id="comments_<?= $q['id'] ?>" name="selection_comments" rows="3" placeholder="Reason for selecting this vendor bid..."></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer border-top bg-light">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success btn-sm fw-semibold">Confirm Award</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>

                        <!-- Item-by-Item Breakdown -->
                        <tbody>
                            <tr class="table-secondary">
                                <td colspan="<?= count($quotations) + 1 ?>" class="fw-bold text-dark text-uppercase small py-2 px-3">
                                    <i class="bi bi-boxes me-1"></i> Line Items Pricing Comparison
                                </td>
                            </tr>
                            <?php foreach ($prItems as $item): ?>
                                <?php
                                    // Find lowest price for this specific item across all active bidders
                                    $minItemPrice = null;
                                    foreach ($quotations as $q) {
                                        if ($q['status'] !== 'rejected' && $q['status'] !== 'cancelled') {
                                            $qi = $quoteItemMap[$q['id']][$item['id']] ?? null;
                                            if ($qi && (float)$qi['unit_price'] > 0) {
                                                if ($minItemPrice === null || (float)$qi['unit_price'] < $minItemPrice) {
                                                    $minItemPrice = (float)$qi['unit_price'];
                                                }
                                            }
                                        }
                                    }
                                ?>
                                <tr>
                                    <td class="p-3 bg-light">
                                        <div class="fw-bold text-dark"><?= e($item['item_name']) ?></div>
                                        <div class="small text-muted mb-1"><?= e($item['description'] ?? '') ?></div>
                                        <div class="small text-muted font-monospace">
                                            Qty: <strong><?= number_format((float)$item['quantity'], 2) ?> <?= e($item['unit']) ?></strong>
                                            &bull; Est Unit: <?= formatCurrency($item['estimated_unit_price']) ?>
                                        </div>
                                    </td>
                                    <?php foreach ($quotations as $q): ?>
                                        <?php
                                            $qi = $quoteItemMap[$q['id']][$item['id']] ?? null;
                                            $isItemLowest = ($qi && $minItemPrice !== null && (float)$qi['unit_price'] === $minItemPrice && $q['status'] !== 'rejected');
                                        ?>
                                        <td class="p-3 <?= $isItemLowest ? 'bg-success-subtle text-success-emphasis' : '' ?>">
                                            <?php if ($qi): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="text-muted small">Unit Price:</span>
                                                    <span class="fw-bold font-monospace <?= $isItemLowest ? 'text-success' : 'text-dark' ?>">
                                                        <?= formatCurrency($qi['unit_price']) ?>
                                                        <?php if ($isItemLowest): ?>
                                                            <i class="bi bi-check-circle-fill text-success ms-1" title="Lowest item price"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-muted small">Subtotal:</span>
                                                    <span class="fw-bold font-monospace text-dark"><?= formatCurrency($qi['subtotal']) ?></span>
                                                </div>
                                                <?php if (!empty($qi['remarks'])): ?>
                                                    <div class="small text-muted fst-italic mt-1 border-top pt-1">
                                                        <?= e($qi['remarks']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic small">Item not quoted</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Financial Totals Section -->
                            <tr class="table-secondary">
                                <td colspan="<?= count($quotations) + 1 ?>" class="fw-bold text-dark text-uppercase small py-2 px-3">
                                    <i class="bi bi-calculator me-1"></i> Financials & Commercial Terms
                                </td>
                            </tr>

                            <!-- Items Subtotal -->
                            <tr>
                                <td class="p-3 bg-light fw-semibold text-dark">Items Subtotal</td>
                                <?php foreach ($quotations as $q): ?>
                                    <td class="p-3 text-end font-monospace fw-semibold text-dark">
                                        <?= formatCurrency($q['subtotal']) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Taxes -->
                            <tr>
                                <td class="p-3 bg-light text-muted small">Tax / VAT</td>
                                <?php foreach ($quotations as $q): ?>
                                    <td class="p-3 text-end font-monospace text-muted small">
                                        +<?= formatCurrency($q['tax_amount']) ?> <span class="text-secondary">(<?= number_format((float)$q['tax_percentage'], 2) ?>%)</span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Shipping -->
                            <tr>
                                <td class="p-3 bg-light text-muted small">Shipping & Freight</td>
                                <?php foreach ($quotations as $q): ?>
                                    <td class="p-3 text-end font-monospace text-muted small">
                                        +<?= formatCurrency($q['shipping_cost']) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Other Charges -->
                            <tr>
                                <td class="p-3 bg-light text-muted small">Other Surcharges</td>
                                <?php foreach ($quotations as $q): ?>
                                    <td class="p-3 text-end font-monospace text-muted small">
                                        +<?= formatCurrency($q['other_charges']) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Grand Total Row -->
                            <tr class="table-active">
                                <td class="p-3 bg-light fw-bold text-dark fs-6">Grand Total Amount</td>
                                <?php foreach ($quotations as $q): ?>
                                    <?php
                                        $isLowest = ($lowestTotal !== null && (float)$q['total_amount'] === $lowestTotal && $q['status'] !== 'rejected');
                                    ?>
                                    <td class="p-3 text-end font-monospace fw-bold fs-5 <?= $isLowest ? 'text-success bg-success-subtle' : 'text-primary' ?>">
                                        <?= formatCurrency($q['total_amount']) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Payment Terms -->
                            <tr>
                                <td class="p-3 bg-light text-muted small">Payment Terms</td>
                                <?php foreach ($quotations as $q): ?>
                                    <td class="p-3 small text-dark fw-medium">
                                        <?= e($q['payment_terms'] ?: 'Not specified') ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Delivery Terms -->
                            <tr>
                                <td class="p-3 bg-light text-muted small">Delivery Terms / Lead Time</td>
                                <?php foreach ($quotations as $q): ?>
                                    <td class="p-3 small text-dark fw-medium">
                                        <?= e($q['delivery_terms'] ?: 'Not specified') ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Quote Validity -->
                            <tr>
                                <td class="p-3 bg-light text-muted small">Quotation Validity</td>
                                <?php foreach ($quotations as $q): ?>
                                    <td class="p-3 small text-muted">
                                        <?= !empty($q['valid_until']) ? formatDate($q['valid_until'], 'd M Y') : 'Not specified' ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
