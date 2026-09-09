<?php
/**
 * Edit Draft Goods Receipt (GRN) Form
 * Procurement Management CMS - Phase 05
 */

$pageTitle = 'Edit Draft Goods Receipt';
$pageSubtitle = 'Update Delivery Inspection & Acceptance Quantities';
$activeNav = 'goods_receiving';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// RBAC: Only Admin and Procurement Officer can edit draft GRN
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: Only Procurement Officers and Administrators can edit goods receipts.');
    redirect('modules/goods_receiving/index.php');
}

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$grnId = (int)($_GET['id'] ?? 0);
if ($grnId <= 0) {
    setFlash('error', 'Invalid Goods Receipt ID.');
    redirect('modules/goods_receiving/index.php');
}

// 1. Fetch GRN Record
$grn = null;
try {
    $stmt = $db->prepare("
        SELECT gr.*,
               po.po_no,
               po.status AS po_status,
               po.delivery_address AS po_delivery_address,
               s.name AS supplier_name,
               s.supplier_code,
               pr.request_no
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN suppliers s ON s.id = gr.supplier_id
        WHERE gr.id = :id AND gr.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $grnId]);
    $grn = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching GRN for edit: ' . $e->getMessage());
}

if (!$grn) {
    setFlash('error', 'Goods Receipt not found or has been deleted.');
    redirect('modules/goods_receiving/index.php');
}

if ($grn['status'] !== 'draft') {
    setFlash('error', 'Posted goods receipts cannot be edited. This receiving record is permanently locked.');
    redirect('modules/goods_receiving/view.php?id=' . $grnId);
}

// 2. Fetch PO Items with Cumulative POSTED received quantities
$poItems = [];
try {
    $poiStmt = $db->prepare("
        SELECT poi.*,
               COALESCE((
                   SELECT SUM(gri.received_qty)
                   FROM goods_receipt_items gri
                   JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
                   WHERE gr.purchase_order_id = poi.purchase_order_id
                     AND gr.status = 'posted'
                     AND gr.deleted_at IS NULL
                     AND gri.purchase_order_item_id = poi.id
               ), 0) AS previously_received_qty,
               COALESCE(cur_gri.id, 0) AS current_item_id,
               COALESCE(cur_gri.received_qty, 0.00) AS cur_received_qty,
               COALESCE(cur_gri.rejected_qty, 0.00) AS cur_rejected_qty,
               cur_gri.notes AS cur_notes
        FROM purchase_order_items poi
        LEFT JOIN goods_receipt_items cur_gri ON cur_gri.purchase_order_item_id = poi.id AND cur_gri.goods_receipt_id = :grn_id
        WHERE poi.purchase_order_id = :po_id
        ORDER BY poi.id ASC
    ");
    $poiStmt->execute([
        ':grn_id' => $grnId,
        ':po_id'  => (int)$grn['purchase_order_id']
    ]);
    $rawItems = $poiStmt->fetchAll();

    foreach ($rawItems as $item) {
        $ordered = (float)$item['quantity'];
        $prevRec = (float)$item['previously_received_qty'];
        $remaining = max(0.0, $ordered - $prevRec);

        $item['calculated_remaining'] = $remaining;
        $poItems[] = $item;
    }
} catch (Exception $e) {
    error_log('Error loading PO items for GRN edit: ' . $e->getMessage());
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
                    <li class="breadcrumb-item"><a href="<?= url('modules/goods_receiving/index.php') ?>" class="text-decoration-none">Goods Receiving</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('modules/goods_receiving/view.php?id=' . $grn['id']) ?>" class="text-decoration-none font-monospace"><?= e($grn['grn_no']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Draft</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 mb-0 text-gray-800 fw-bold font-monospace">Edit Draft: <?= e($grn['grn_no']) ?></h1>
                <?= getGrnStatusBadge($grn['status']) ?>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Order: <strong class="text-dark"><?= e($grn['po_no']) ?></strong> &bull;
                Vendor: <strong class="text-dark"><?= e($grn['supplier_name']) ?></strong> (<?= e($grn['supplier_code']) ?>)
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('modules/goods_receiving/view.php?id=' . $grn['id']) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i>
                <span>Cancel & View</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('modules/goods_receiving/update.php') ?>" id="editGrnForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $grn['id'] ?>">

        <div class="row g-4 mb-4">
            <!-- Header Delivery Details Card -->
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-truck text-primary"></i> Delivery & Inspection Header Information
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="receipt_date" class="form-label small fw-semibold text-dark">
                                    Receipt / Delivery Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="receipt_date" name="receipt_date" value="<?= e($grn['receipt_date']) ?>" max="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label for="delivery_note_no" class="form-label small fw-semibold text-dark">
                                    Vendor Delivery Note / Challan #
                                </label>
                                <input type="text" class="form-control font-monospace" id="delivery_note_no" name="delivery_note_no" value="<?= e($grn['delivery_note_no']) ?>" placeholder="e.g. DN-98741" maxlength="100">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Purchase Order</label>
                                <div class="form-control bg-light font-monospace fw-bold text-dark">
                                    <?= e($grn['po_no']) ?> (<?= e($grn['supplier_name']) ?>)
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="grn_notes" class="form-label small fw-semibold text-dark">
                                    Receiving Inspection Notes & Observations
                                </label>
                                <textarea class="form-control" id="grn_notes" name="notes" rows="2" placeholder="Condition of packaging, carrier remarks, seal inspection..."><?= e($grn['notes']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items Inspection Table -->
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-box-seam text-primary"></i> Item Acceptance & Rejection Quantities
                        </h6>
                        <span class="text-muted small">Recalculated against posted database receipts</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-3" style="width: 4%;">#</th>
                                        <th style="width: 28%;">Item Name & Description</th>
                                        <th class="text-center" style="width: 10%;">Ordered</th>
                                        <th class="text-center" style="width: 10%;">Posted Prior</th>
                                        <th class="text-center" style="width: 10%;">Max Remaining</th>
                                        <th class="text-center bg-primary-subtle text-primary fw-bold" style="width: 14%;">Receive Qty (Accept)</th>
                                        <th class="text-center bg-danger-subtle text-danger fw-bold" style="width: 12%;">Reject Qty (Damaged)</th>
                                        <th class="pe-3" style="width: 12%;">Line Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($poItems as $idx => $item): ?>
                                        <?php $isFullyReceived = ($item['calculated_remaining'] <= 0.0001); ?>
                                        <tr class="<?= ($isFullyReceived && (float)$item['cur_received_qty'] <= 0) ? 'table-light text-muted opacity-75' : '' ?>">
                                            <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                            <td>
                                                <input type="hidden" name="items[<?= $idx ?>][purchase_order_item_id]" value="<?= $item['id'] ?>">
                                                <div class="fw-semibold text-dark"><?= e($item['item_name']) ?></div>
                                                <?php if (!empty($item['description'])): ?>
                                                    <div class="small text-muted"><?= e($item['description']) ?></div>
                                                <?php endif; ?>
                                                <span class="badge bg-light text-secondary border small mt-1 font-monospace"><?= e($item['unit']) ?></span>
                                            </td>
                                            <td class="text-center font-monospace fw-semibold text-dark">
                                                <?= number_format((float)$item['quantity'], 2) ?>
                                            </td>
                                            <td class="text-center font-monospace text-muted">
                                                <?= number_format((float)$item['previously_received_qty'], 2) ?>
                                            </td>
                                            <td class="text-center font-monospace">
                                                <?php if ($isFullyReceived): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Fulfilled</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-bold fs-7 remaining-badge" data-remaining="<?= (float)$item['calculated_remaining'] ?>">
                                                        <?= number_format((float)$item['calculated_remaining'], 2) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center bg-primary-subtle bg-opacity-25">
                                                <input type="number"
                                                       class="form-control form-control-sm text-center font-monospace fw-bold border-primary text-primary receive-qty-input"
                                                       name="items[<?= $idx ?>][received_qty]"
                                                       min="0"
                                                       max="<?= (float)$item['calculated_remaining'] ?>"
                                                       step="0.01"
                                                       value="<?= number_format((float)$item['cur_received_qty'], 2, '.', '') ?>"
                                                       data-remaining="<?= (float)$item['calculated_remaining'] ?>"
                                                       data-index="<?= $idx ?>">
                                            </td>
                                            <td class="text-center bg-danger-subtle bg-opacity-25">
                                                <input type="number"
                                                       class="form-control form-control-sm text-center font-monospace text-danger reject-qty-input"
                                                       name="items[<?= $idx ?>][rejected_qty]"
                                                       min="0"
                                                       step="0.01"
                                                       value="<?= number_format((float)$item['cur_rejected_qty'], 2, '.', '') ?>"
                                                       data-index="<?= $idx ?>">
                                            </td>
                                            <td class="pe-3">
                                                <input type="text"
                                                       class="form-control form-control-sm"
                                                       name="items[<?= $idx ?>][notes]"
                                                       value="<?= e($item['cur_notes'] ?? '') ?>"
                                                       placeholder="Condition / remark"
                                                       maxlength="255">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="5" class="text-end fw-bold text-dark pe-3">Inspection Summary Totals:</td>
                                        <td class="text-center font-monospace fw-bold text-primary" id="totalReceivedDisplay">0.00</td>
                                        <td class="text-center font-monospace fw-bold text-danger" id="totalRejectedDisplay">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div class="small text-muted">
                            <i class="bi bi-pencil-square text-primary me-1"></i>
                            Changes will update this <strong>Draft</strong> GRN. When finalized, you can post it to update the PO status.
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= url('modules/goods_receiving/view.php?id=' . $grn['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4 shadow-sm" id="saveDraftBtn">
                                <i class="bi bi-check-lg me-1"></i> Update Draft GRN
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const receiveInputs = document.querySelectorAll('.receive-qty-input');
    const rejectInputs = document.querySelectorAll('.reject-qty-input');
    const totalRecDisplay = document.getElementById('totalReceivedDisplay');
    const totalRejDisplay = document.getElementById('totalRejectedDisplay');
    const saveBtn = document.getElementById('saveDraftBtn');

    function calculateTotals() {
        let totalRec = 0;
        let totalRej = 0;
        let hasOverReceiving = false;

        receiveInputs.forEach(input => {
            const val = parseFloat(input.value) || 0;
            const max = parseFloat(input.getAttribute('data-remaining')) || 0;
            if (val > max + 0.0001) {
                input.classList.add('is-invalid');
                hasOverReceiving = true;
            } else {
                input.classList.remove('is-invalid');
            }
            totalRec += val;
        });

        rejectInputs.forEach(input => {
            const val = parseFloat(input.value) || 0;
            totalRej += val;
        });

        if (totalRecDisplay) totalRecDisplay.textContent = totalRec.toFixed(2);
        if (totalRejDisplay) totalRejDisplay.textContent = totalRej.toFixed(2);

        if (saveBtn) {
            saveBtn.disabled = hasOverReceiving;
        }
    }

    receiveInputs.forEach(input => {
        input.addEventListener('input', calculateTotals);
    });

    rejectInputs.forEach(input => {
        input.addEventListener('input', calculateTotals);
    });

    calculateTotals();
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
