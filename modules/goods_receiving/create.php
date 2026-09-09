<?php
/**
 * Create Goods Receipt (GRN) Form
 * Procurement Management CMS - Phase 05
 */

$pageTitle = 'Receive Goods (New GRN)';
$pageSubtitle = 'Inspect, Count, and Accept Received Deliveries against Purchase Orders';
$activeNav = 'goods_receiving';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// RBAC: Only Admin and Procurement Officer can create GRN
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: Only Procurement Officers and Administrators can receive goods.');
    redirect('modules/goods_receiving/index.php');
}

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$selectedPoId = (int)($_GET['po_id'] ?? 0);

// 1. Fetch Eligible Purchase Orders (status in 'sent', 'partially_received')
$eligibleOrders = [];
try {
    $poStmt = $db->query("
        SELECT po.id, po.po_no, po.po_date, po.status, po.grand_total,
               s.name AS supplier_name, s.supplier_code,
               pr.request_no
        FROM purchase_orders po
        JOIN suppliers s ON s.id = po.supplier_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        WHERE po.deleted_at IS NULL
          AND po.status IN ('sent', 'partially_received')
        ORDER BY po.id DESC
    ");
    $eligibleOrders = $poStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching eligible POs for GRN: ' . $e->getMessage());
}

// 2. If a PO is selected, fetch full details and calculate remaining quantities
$selectedPo = null;
$poItems = [];

if ($selectedPoId > 0) {
    try {
        $stmt = $db->prepare("
            SELECT po.*,
                   s.name AS supplier_name, s.supplier_code, s.email AS supplier_email, s.phone AS supplier_phone,
                   pr.request_no, pr.purpose AS pr_purpose,
                   d.name AS department_name,
                   q.quotation_no
            FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id
            JOIN purchase_requests pr ON pr.id = po.purchase_request_id
            LEFT JOIN departments d ON d.id = pr.department_id
            JOIN quotations q ON q.id = po.quotation_id
            WHERE po.id = :id AND po.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $selectedPoId]);
        $selectedPo = $stmt->fetch();

        if ($selectedPo) {
            if (!in_array($selectedPo['status'], ['sent', 'partially_received'])) {
                setFlash('error', 'Purchase Order ' . $selectedPo['po_no'] . ' is not eligible for goods receiving (Current Status: ' . ucfirst(str_replace('_', ' ', $selectedPo['status'])) . ').');
                redirect('modules/goods_receiving/create.php');
            }

            // Fetch PO line items with cumulative POSTED received quantities
            $itemStmt = $db->prepare("
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
                       COALESCE((
                           SELECT SUM(gri.rejected_qty)
                           FROM goods_receipt_items gri
                           JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
                           WHERE gr.purchase_order_id = poi.purchase_order_id
                             AND gr.status = 'posted'
                             AND gr.deleted_at IS NULL
                             AND gri.purchase_order_item_id = poi.id
                       ), 0) AS previously_rejected_qty
                FROM purchase_order_items poi
                WHERE poi.purchase_order_id = :po_id
                ORDER BY poi.id ASC
            ");
            $itemStmt->execute([':po_id' => $selectedPoId]);
            $rawItems = $itemStmt->fetchAll();

            foreach ($rawItems as $item) {
                $ordered = (float)$item['quantity'];
                $prevRec = (float)$item['previously_received_qty'];
                $remaining = max(0.0, $ordered - $prevRec);

                $item['calculated_remaining'] = $remaining;
                $poItems[] = $item;
            }
        }
    } catch (Exception $e) {
        error_log('Error loading selected PO details: ' . $e->getMessage());
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
                    <li class="breadcrumb-item"><a href="<?= url('modules/goods_receiving/index.php') ?>" class="text-decoration-none">Goods Receiving</a></li>
                    <li class="breadcrumb-item active" aria-current="page">New Goods Receipt</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Receive Goods & Create GRN</h1>
            <p class="text-muted small mb-0">Record and inspect delivery shipments against active purchase orders.</p>
        </div>
        <div>
            <a href="<?= url('modules/goods_receiving/index.php') ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i>
                <span>Back to GRN List</span>
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

    <!-- Step 1: Select Purchase Order -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">1</span>
                <span>Select Purchase Order for Receiving</span>
            </h6>
        </div>
        <div class="card-body p-4">
            <form method="GET" action="<?= url('modules/goods_receiving/create.php') ?>" class="row g-3 align-items-end">
                <div class="col-lg-8 col-md-9">
                    <label for="po_select" class="form-label small fw-semibold text-dark">
                        Eligible Purchase Order <span class="text-danger">*</span>
                        <span class="text-muted fw-normal small ms-1">(Orders with status 'Sent' or 'Partially Received')</span>
                    </label>
                    <select class="form-select" id="po_select" name="po_id" required onchange="this.form.submit()">
                        <option value="">-- Choose an active Purchase Order to receive --</option>
                        <?php foreach ($eligibleOrders as $order): ?>
                            <option value="<?= $order['id'] ?>" <?= ($selectedPoId === (int)$order['id']) ? 'selected' : '' ?>>
                                <?= e($order['po_no']) ?> &bull; <?= e($order['supplier_name']) ?> (<?= e($order['supplier_code']) ?>) &bull; <?= ucfirst(str_replace('_', ' ', $order['status'])) ?> &bull; <?= formatCurrency($order['grand_total']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4 col-md-3">
                    <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Load Order Items</span>
                    </button>
                </div>
            </form>

            <?php if (empty($eligibleOrders)): ?>
                <div class="alert alert-warning border-warning-subtle shadow-sm mt-3 mb-0 small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>No eligible purchase orders found.</strong> Only purchase orders that have been officially marked as <strong>Sent</strong> or <strong>Partially Received</strong> can receive goods.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Step 2: GRN Delivery Details & Items Inspection (if PO loaded) -->
    <?php if ($selectedPo && !empty($poItems)): ?>
        <form method="POST" action="<?= url('modules/goods_receiving/store.php') ?>" id="grnForm">
            <?= csrfField() ?>
            <input type="hidden" name="purchase_order_id" value="<?= $selectedPo['id'] ?>">

            <div class="row g-4 mb-4">
                <!-- PO & Supplier Reference Card -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 mb-4 h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-check-fill text-primary"></i> Order & Supplier Summary
                            </h6>
                        </div>
                        <div class="card-body p-4 small">
                            <div class="mb-3">
                                <span class="text-muted d-block">Purchase Order:</span>
                                <a href="<?= url('modules/purchase_orders/view.php?id=' . $selectedPo['id']) ?>" target="_blank" class="fw-bold font-monospace text-primary text-decoration-none fs-6">
                                    <?= e($selectedPo['po_no']) ?> <i class="bi bi-box-arrow-up-right small"></i>
                                </a>
                                <div class="mt-1"><?= getPoStatusBadge($selectedPo['status']) ?></div>
                            </div>

                            <hr class="my-2 text-muted">

                            <div class="mb-3">
                                <span class="text-muted d-block">Awarded Supplier:</span>
                                <strong class="text-dark fs-6"><?= e($selectedPo['supplier_name']) ?></strong>
                                <div class="font-monospace text-muted small"><?= e($selectedPo['supplier_code']) ?></div>
                            </div>

                            <hr class="my-2 text-muted">

                            <div class="mb-3">
                                <span class="text-muted d-block">Purchase Requisition:</span>
                                <span class="font-monospace text-dark fw-semibold"><?= e($selectedPo['request_no']) ?></span>
                                <div class="text-muted"><?= e($selectedPo['department_name'] ?? 'General') ?></div>
                            </div>

                            <hr class="my-2 text-muted">

                            <div class="mb-0">
                                <span class="text-muted d-block">Delivery Destination:</span>
                                <div class="bg-light p-2 rounded text-dark mt-1">
                                    <?= nl2br(e($selectedPo['delivery_address'] ?: 'Main Warehouse Receiving Dock')) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Receipt Header Details -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3 mb-4 h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">2</span>
                                <span>Delivery & Inspection Information</span>
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="receipt_date" class="form-label small fw-semibold text-dark">
                                        Receipt / Delivery Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="receipt_date" name="receipt_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                                    <div class="form-text small">Date the items physically arrived.</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="delivery_note_no" class="form-label small fw-semibold text-dark">
                                        Vendor Delivery Note / Challan / Tracking #
                                    </label>
                                    <input type="text" class="form-control font-monospace" id="delivery_note_no" name="delivery_note_no" placeholder="e.g. DN-98741 or CHN-2026-01" maxlength="100">
                                    <div class="form-text small">Physical delivery note or invoice number from carrier/supplier.</div>
                                </div>

                                <div class="col-12">
                                    <label for="grn_notes" class="form-label small fw-semibold text-dark">
                                        Receiving Inspection Notes & Observations
                                    </label>
                                    <textarea class="form-control" id="grn_notes" name="notes" rows="3" placeholder="Condition of packaging, carrier remarks, seal inspection, or reasons for any rejections..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Line Items Inspection Table -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">3</span>
                        <span>Item Inspection & Acceptance Quantities</span>
                    </h6>
                    <div class="text-muted small">
                        <i class="bi bi-info-circle text-primary me-1"></i> Enter accepted received quantities and any rejected quantities below.
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3" style="width: 4%;">#</th>
                                    <th style="width: 28%;">Item Name & Description</th>
                                    <th class="text-center" style="width: 10%;">Ordered</th>
                                    <th class="text-center" style="width: 10%;">Previously Received</th>
                                    <th class="text-center" style="width: 10%;">Remaining to Receive</th>
                                    <th class="text-center bg-primary-subtle text-primary fw-bold" style="width: 14%;">Receive Qty (Accept)</th>
                                    <th class="text-center bg-danger-subtle text-danger fw-bold" style="width: 12%;">Reject Qty (Damaged)</th>
                                    <th class="pe-3" style="width: 12%;">Line Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($poItems as $idx => $item): ?>
                                    <?php $isFullyReceived = ($item['calculated_remaining'] <= 0.0001); ?>
                                    <tr class="<?= $isFullyReceived ? 'table-light text-muted opacity-75' : '' ?>">
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
                                            <?php if ((float)$item['previously_rejected_qty'] > 0): ?>
                                                <div class="small text-danger" title="Previously rejected">(-<?= number_format((float)$item['previously_rejected_qty'], 2) ?>)</div>
                                            <?php endif; ?>
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
                                            <?php if ($isFullyReceived): ?>
                                                <input type="number" class="form-control form-control-sm text-center font-monospace" value="0.00" disabled>
                                                <input type="hidden" name="items[<?= $idx ?>][received_qty]" value="0">
                                            <?php else: ?>
                                                <input type="number"
                                                       class="form-control form-control-sm text-center font-monospace fw-bold border-primary text-primary receive-qty-input"
                                                       name="items[<?= $idx ?>][received_qty]"
                                                       min="0"
                                                       max="<?= (float)$item['calculated_remaining'] ?>"
                                                       step="0.01"
                                                       value="0.00"
                                                       data-remaining="<?= (float)$item['calculated_remaining'] ?>"
                                                       data-index="<?= $idx ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center bg-danger-subtle bg-opacity-25">
                                            <input type="number"
                                                   class="form-control form-control-sm text-center font-monospace text-danger reject-qty-input"
                                                   name="items[<?= $idx ?>][rejected_qty]"
                                                   min="0"
                                                   step="0.01"
                                                   value="0.00"
                                                   data-index="<?= $idx ?>">
                                        </td>
                                        <td class="pe-3">
                                            <input type="text"
                                                   class="form-control form-control-sm"
                                                   name="items[<?= $idx ?>][notes]"
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
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Saving as <strong>Draft</strong> will record the physical receipt without affecting PO status until reviewed and <strong>Posted</strong>.
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= url('modules/goods_receiving/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm" id="saveDraftBtn">
                            <i class="bi bi-save me-1"></i> Save Goods Receipt (Draft)
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
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
            if (hasOverReceiving) {
                saveBtn.disabled = true;
            } else {
                saveBtn.disabled = false;
            }
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
