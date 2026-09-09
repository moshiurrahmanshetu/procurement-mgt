<?php
/**
 * View Goods Receipt Note (GRN) Details
 * Procurement Management CMS - Phase 05
 */

$pageTitle = 'Goods Receipt Details';
$pageSubtitle = 'Physical Delivery Inspection & Receiving Record';
$activeNav = 'goods_receiving';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewAll = $isAdmin || $isManager || $isOfficer;
$canManage = $isAdmin || $isOfficer;

$grnId = (int)($_GET['id'] ?? 0);
if ($grnId <= 0) {
    setFlash('error', 'Invalid Goods Receipt ID.');
    redirect('modules/goods_receiving/index.php');
}

// 1. Fetch GRN Record with Relations
$grn = null;
try {
    $stmt = $db->prepare("
        SELECT gr.*,
               po.po_no,
               po.po_date,
               po.status AS po_status,
               po.grand_total AS po_grand_total,
               po.delivery_address AS po_delivery_address,
               po.delivery_terms AS po_delivery_terms,
               pr.id AS purchase_request_id,
               pr.request_no,
               pr.purpose AS pr_purpose,
               pr.requested_by AS pr_requested_by,
               d.name AS department_name,
               s.name AS supplier_name,
               s.supplier_code,
               s.email AS supplier_email,
               s.phone AS supplier_phone,
               s.tax_number AS supplier_tax,
               s.address AS supplier_address,
               u_rec.full_name AS receiver_name,
               u_rec.username AS receiver_username
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        LEFT JOIN departments d ON d.id = pr.department_id
        JOIN suppliers s ON s.id = gr.supplier_id
        LEFT JOIN users u_rec ON u_rec.id = gr.received_by
        WHERE gr.id = :id AND gr.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $grnId]);
    $grn = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching GRN: ' . $e->getMessage());
}

if (!$grn) {
    setFlash('error', 'Goods Receipt not found or has been deleted.');
    redirect('modules/goods_receiving/index.php');
}

// IDOR & Role Access Enforcement
if (!$canViewAll && (int)$grn['pr_requested_by'] !== $userId) {
    setFlash('error', 'Access Denied: You do not have permission to view this Goods Receipt.');
    redirect('modules/goods_receiving/index.php');
}

// 2. Fetch Line Items
$items = [];
$totalAccepted = 0.0;
$totalRejected = 0.0;
try {
    $itemStmt = $db->prepare("
        SELECT gri.*
        FROM goods_receipt_items gri
        WHERE gri.goods_receipt_id = :grn_id
        ORDER BY gri.id ASC
    ");
    $itemStmt->execute([':grn_id' => $grnId]);
    $items = $itemStmt->fetchAll();

    foreach ($items as $item) {
        $totalAccepted += (float)$item['received_qty'];
        $totalRejected += (float)$item['rejected_qty'];
    }
} catch (Exception $e) {
    error_log('Error fetching GRN Items: ' . $e->getMessage());
}

// 3. Fetch Audit History
$history = [];
try {
    $hStmt = $db->prepare("
        SELECT grh.*, u.full_name, u.username
        FROM goods_receipt_history grh
        LEFT JOIN users u ON u.id = grh.user_id
        WHERE grh.goods_receipt_id = :grn_id
        ORDER BY grh.id ASC
    ");
    $hStmt->execute([':grn_id' => $grnId]);
    $history = $hStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching GRN History: ' . $e->getMessage());
}

$isDraft = ($grn['status'] === 'draft');
$isPosted = ($grn['status'] === 'posted');
$canEdit = ($isDraft && $canManage);
$canPost = ($isDraft && $canManage);
$canDelete = ($isDraft && $canManage);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('modules/dashboard/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('modules/goods_receiving/index.php') ?>" class="text-decoration-none">Goods Receiving</a></li>
                    <li class="breadcrumb-item active font-monospace" aria-current="page"><?= e($grn['grn_no']) ?></li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 mb-0 text-gray-800 fw-bold font-monospace"><?= e($grn['grn_no']) ?></h1>
                <?= getGrnStatusBadge($grn['status']) ?>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Order: <a href="<?= url('modules/purchase_orders/view.php?id=' . $grn['purchase_order_id']) ?>" class="text-primary font-monospace text-decoration-none fw-semibold"><?= e($grn['po_no']) ?></a> &bull;
                Vendor: <strong class="text-dark"><?= e($grn['supplier_name']) ?></strong> (<?= e($grn['supplier_code']) ?>) &bull;
                Receipt Date: <span class="text-dark font-monospace"><?= formatDate($grn['receipt_date'], 'd M Y') ?></span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= url('modules/goods_receiving/print.php?id=' . $grn['id']) ?>" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-printer"></i>
                <span>Print GRN</span>
            </a>

            <a href="<?= url('modules/goods_receiving/index.php') ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Receipts</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Status Specific Banners -->
    <?php if ($isPosted): ?>
        <div class="alert alert-success border-success-subtle shadow-sm d-flex align-items-center gap-3 p-3 rounded-3 mb-4">
            <div class="bg-success text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                <i class="bi bi-check2-all fs-4"></i>
            </div>
            <div>
                <h5 class="alert-heading fw-bold mb-1">Posted Goods Receipt Note — Immutable Record</h5>
                <p class="mb-0 small text-success-emphasis">
                    This receiving record was confirmed and posted on <strong><?= formatDate($grn['posted_at'], 'd M Y, h:i A') ?></strong>.
                    It has officially updated Purchase Order <strong><?= e($grn['po_no']) ?></strong> receiving fulfillment and is locked against modifications.
                </p>
            </div>
        </div>
    <?php elseif ($isDraft): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-start border-warning border-4">
            <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-hourglass-split text-warning fs-5"></i>
                    <div>
                        <span class="fw-bold text-dark">Draft Goods Receipt:</span>
                        <span class="text-muted small ms-1">
                            This receipt is unposted and has not yet affected Purchase Order fulfillment totals.
                        </span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($canEdit): ?>
                        <a href="<?= url('modules/goods_receiving/edit.php?id=' . $grn['id']) ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                            <i class="bi bi-pencil"></i> Edit Draft
                        </a>
                    <?php endif; ?>

                    <?php if ($canPost): ?>
                        <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#postModal">
                            <i class="bi bi-check-circle-fill"></i> Post Goods Receipt
                        </button>
                    <?php endif; ?>

                    <?php if ($canDelete): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#deleteDraftModal">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Left Column: Items Breakdown & Delivery Info -->
        <div class="col-lg-8">
            <!-- Received Line Items Table Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam text-primary"></i> Received Item Breakdown
                    </h6>
                    <span class="badge bg-light text-secondary border"><?= count($items) ?> items</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3" style="width: 4%;">#</th>
                                    <th style="width: 32%;">Item Name & Description</th>
                                    <th class="text-center" style="width: 12%;">Ordered</th>
                                    <th class="text-center" style="width: 12%;">Prior Rec.</th>
                                    <th class="text-center bg-primary-subtle text-primary fw-bold" style="width: 14%;">Accepted Rec.</th>
                                    <th class="text-center bg-danger-subtle text-danger fw-bold" style="width: 12%;">Rejected</th>
                                    <th class="pe-3" style="width: 14%;">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $idx => $item): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($item['item_name']) ?></div>
                                            <?php if (!empty($item['description'])): ?>
                                                <div class="small text-muted"><?= e($item['description']) ?></div>
                                            <?php endif; ?>
                                            <span class="badge bg-light text-secondary border small mt-1 font-monospace"><?= e($item['unit']) ?></span>
                                        </td>
                                        <td class="text-center font-monospace fw-semibold text-dark">
                                            <?= number_format((float)$item['ordered_qty'], 2) ?>
                                        </td>
                                        <td class="text-center font-monospace text-muted">
                                            <?= number_format((float)$item['previously_received_qty'], 2) ?>
                                        </td>
                                        <td class="text-center font-monospace fw-bold text-success bg-primary-subtle bg-opacity-25">
                                            +<?= number_format((float)$item['received_qty'], 2) ?>
                                        </td>
                                        <td class="text-center font-monospace text-danger bg-danger-subtle bg-opacity-25">
                                            <?= (float)$item['rejected_qty'] > 0 ? '-' . number_format((float)$item['rejected_qty'], 2) : '0.00' ?>
                                        </td>
                                        <td class="pe-3 small text-muted">
                                            <?= !empty($item['notes']) ? e($item['notes']) : '<span class="fst-italic text-muted">-</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end fw-bold text-dark pe-3">Inspection Totals:</td>
                                    <td class="text-center font-monospace fw-bold text-success bg-primary-subtle bg-opacity-25">
                                        +<?= number_format($totalAccepted, 2) ?>
                                    </td>
                                    <td class="text-center font-monospace fw-bold text-danger bg-danger-subtle bg-opacity-25">
                                        <?= $totalRejected > 0 ? '-' . number_format($totalRejected, 2) : '0.00' ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Delivery & Carrier Information Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-truck text-primary"></i> Delivery & Waybill Information
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Receipt / Delivery Date</span>
                            <span class="fw-semibold text-dark font-monospace"><?= formatDate($grn['receipt_date'], 'd M Y') ?></span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Delivery Note / Challan / Tracking #</span>
                            <span class="fw-semibold text-dark font-monospace"><?= e($grn['delivery_note_no'] ?: 'Not recorded') ?></span>
                        </div>
                        <div class="col-12"><hr class="my-1 text-muted"></div>
                        <div class="col-12">
                            <span class="text-muted small d-block mb-1">Receiving Notes & Inspection Observations</span>
                            <div class="bg-light p-3 rounded text-secondary small">
                                <?= !empty($grn['notes']) ? nl2br(e($grn['notes'])) : '<em class="text-muted">No specific inspection notes recorded.</em>' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: References, Receiver, Audit Timeline -->
        <div class="col-lg-4">
            <!-- Purchase Order Reference Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-check text-primary"></i> Purchase Order
                    </h6>
                    <a href="<?= url('modules/purchase_orders/view.php?id=' . $grn['purchase_order_id']) ?>" class="btn btn-sm btn-outline-secondary" title="View Full PO">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <div class="card-body p-4 small">
                    <div class="mb-2">
                        <span class="text-muted d-block">PO Number:</span>
                        <a href="<?= url('modules/purchase_orders/view.php?id=' . $grn['purchase_order_id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none fs-6">
                            <?= e($grn['po_no']) ?>
                        </a>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted d-block">PO Status:</span>
                        <?= getPoStatusBadge($grn['po_status']) ?>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted d-block">PO Date:</span>
                        <span class="font-monospace text-dark"><?= formatDate($grn['po_date'], 'd M Y') ?></span>
                    </div>
                    <div class="mb-0">
                        <span class="text-muted d-block">Order Value:</span>
                        <span class="fw-bold font-monospace text-dark"><?= formatCurrency($grn['po_grand_total']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Supplier Profile Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-building text-primary"></i> Supplier
                    </h6>
                    <a href="<?= url('modules/suppliers/view.php?id=' . $grn['supplier_id']) ?>" class="btn btn-sm btn-outline-secondary" title="View Supplier Profile">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <div class="card-body p-4 small">
                    <div class="fw-bold text-dark mb-1 fs-6"><?= e($grn['supplier_name']) ?></div>
                    <div class="font-monospace text-muted mb-3"><?= e($grn['supplier_code']) ?></div>

                    <div class="mb-2">
                        <span class="text-muted d-block">Email:</span>
                        <a href="mailto:<?= e($grn['supplier_email']) ?>" class="text-decoration-none text-dark fw-medium"><?= e($grn['supplier_email'] ?: 'N/A') ?></a>
                    </div>
                    <div class="mb-0">
                        <span class="text-muted d-block">Phone:</span>
                        <span class="fw-medium text-dark"><?= e($grn['supplier_phone'] ?: 'N/A') ?></span>
                    </div>
                </div>
            </div>

            <!-- Receiving & Audit Metadata Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check text-primary"></i> Audit & Receiving Authority
                    </h6>
                </div>
                <div class="card-body p-4 small">
                    <div class="mb-2">
                        <span class="text-muted d-block">Received / Recorded By:</span>
                        <strong class="text-dark"><?= e($grn['receiver_name'] ?? 'Authorized Officer') ?></strong>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted d-block">Recorded On:</span>
                        <span class="text-dark"><?= formatDate($grn['created_at'], 'd M Y, h:i A') ?></span>
                    </div>
                    <?php if (!empty($grn['posted_at'])): ?>
                        <div class="mb-0">
                            <span class="text-muted d-block">Posted & Locked On:</span>
                            <strong class="text-success"><i class="bi bi-check-circle me-1"></i><?= formatDate($grn['posted_at'], 'd M Y, h:i A') ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timeline / History Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-primary"></i> GRN Activity Trail
                    </h6>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($history)): ?>
                        <div class="timeline position-relative ps-3" style="border-left: 2px solid #dee2e6; margin-left: 0.5rem;">
                            <?php foreach ($history as $h): ?>
                                <div class="timeline-item position-relative mb-3 pb-2">
                                    <span class="position-absolute translate-middle bg-primary rounded-circle" style="left: -13px; top: 8px; width: 10px; height: 10px; border: 2px solid #ffffff;"></span>
                                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                                        <span class="fw-bold small text-dark"><?= e(ucfirst(str_replace('_', ' ', $h['action']))) ?></span>
                                        <span class="text-muted" style="font-size: 0.6875rem;"><?= formatDate($h['created_at'], 'd M, h:i A') ?></span>
                                    </div>
                                    <div class="small text-muted mb-1">
                                        By <strong class="text-dark"><?= e($h['full_name'] ?? 'System') ?></strong>
                                        <?php if (!empty($h['new_status'])): ?>
                                            &bull; <span class="badge bg-light text-dark border" style="font-size: 0.6875rem;"><?= e(ucfirst(str_replace('_', ' ', $h['new_status']))) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($h['comments'])): ?>
                                        <div class="p-2 bg-light rounded small text-dark" style="font-size: 0.75rem;">
                                            <?= nl2br(e($h['comments'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No history events logged yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- MODALS -->
<!-- ============================================ -->

<!-- 1. Post Modal -->
<?php if ($canPost): ?>
    <div class="modal fade" id="postModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/goods_receiving/post.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $grn['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-success">
                            <i class="bi bi-check-circle-fill me-1"></i> Post Goods Receipt Note
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p>You are about to post Goods Receipt <strong><?= e($grn['grn_no']) ?></strong> against Purchase Order <strong><?= e($grn['po_no']) ?></strong>.</p>
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Important:</strong> Posting is permanent and will commit <strong><?= number_format($totalAccepted, 2) ?></strong> accepted items to the Purchase Order. Once posted, this GRN cannot be edited or deleted.
                        </div>
                        <div class="mb-2">
                            <label for="post_comments" class="form-label small fw-semibold">Posting Remarks (Optional)</label>
                            <textarea class="form-control form-control-sm" id="post_comments" name="comments" rows="2" placeholder="e.g. Verified quantities against warehouse physical intake..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-check2-all me-1"></i> Confirm & Post GRN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 2. Delete Draft Modal -->
<?php if ($canDelete): ?>
    <div class="modal fade" id="deleteDraftModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/goods_receiving/delete.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $grn['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-danger">Delete Draft Goods Receipt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        Are you sure you want to permanently delete draft Goods Receipt <strong><?= e($grn['grn_no']) ?></strong>?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash me-1"></i> Delete Draft
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
