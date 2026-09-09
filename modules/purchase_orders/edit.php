<?php
/**
 * Edit Draft Purchase Order
 * Procurement Management CMS - Phase 04
 */

$pageTitle = 'Edit Draft Purchase Order';
$pageSubtitle = 'Modify Order Items & Commercial Terms';
$activeNav = 'purchase_orders';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

// RBAC: Only Procurement Officers and Administrators can edit draft POs
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: Only Procurement Officers and Administrators can edit Purchase Orders.');
    redirect('modules/purchase_orders/index.php');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid Purchase Order ID.');
    redirect('modules/purchase_orders/index.php');
}

// 1. Fetch Purchase Order
$po = null;
try {
    $stmt = $db->prepare("
        SELECT po.*,
               pr.request_no, pr.purpose AS pr_purpose,
               s.name AS supplier_name, s.supplier_code, s.email AS supplier_email,
               s.phone AS supplier_phone, s.tax_number AS supplier_tax,
               q.quotation_no
        FROM purchase_orders po
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN quotations q ON q.id = po.quotation_id
        JOIN suppliers s ON s.id = po.supplier_id
        WHERE po.id = :id AND po.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $po = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching PO for edit: ' . $e->getMessage());
}

if (!$po) {
    setFlash('error', 'Purchase Order not found.');
    redirect('modules/purchase_orders/index.php');
}

// Ensure only draft status can be edited
if ($po['status'] !== 'draft') {
    setFlash('error', 'Only draft Purchase Orders can be edited. This order is in status ' . strtoupper($po['status']) . '.');
    redirect('modules/purchase_orders/view.php?id=' . $id);
}

// 2. Fetch PO Items
$items = [];
try {
    $itemStmt = $db->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = :po_id ORDER BY id ASC");
    $itemStmt->execute([':po_id' => $id]);
    $items = $itemStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching PO Items for edit: ' . $e->getMessage());
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
                    <li class="breadcrumb-item"><a href="<?= url('modules/purchase_orders/index.php') ?>" class="text-decoration-none">Purchase Orders</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('modules/purchase_orders/view.php?id=' . $po['id']) ?>" class="text-decoration-none font-monospace"><?= e($po['po_no']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Draft</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Edit Draft Order: <span class="font-monospace text-primary"><?= e($po['po_no']) ?></span></h1>
            <p class="text-muted small mb-0">Modify order terms, quantities, unit prices, or notes before submitting for review.</p>
        </div>
        <div>
            <a href="<?= url('modules/purchase_orders/view.php?id=' . $po['id']) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i>
                <span>Cancel & Back</span>
            </a>
        </div>
    </div>

    <!-- Edit Form -->
    <form method="POST" action="<?= url('modules/purchase_orders/update.php') ?>" id="poEditForm" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $po['id'] ?>">

        <div class="row g-4">
            <!-- Left Column: PO Parameters & Items Table -->
            <div class="col-lg-8">
                <!-- PO Logistics & Terms Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-ruled text-primary"></i> Order Details & Delivery Terms
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="po_date" class="form-label small fw-bold text-dark">
                                    PO Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="po_date" name="po_date" value="<?= e($po['po_date']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="expected_delivery_date" class="form-label small fw-bold text-dark">
                                    Expected Delivery Date
                                </label>
                                <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date" value="<?= e($po['expected_delivery_date'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label for="delivery_address" class="form-label small fw-bold text-dark">
                                    Delivery Destination Address
                                </label>
                                <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2"><?= e($po['delivery_address'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="payment_terms" class="form-label small fw-bold text-dark">
                                    Payment Terms
                                </label>
                                <input type="text" class="form-control" id="payment_terms" name="payment_terms" value="<?= e($po['payment_terms'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="delivery_terms" class="form-label small fw-bold text-dark">
                                    Delivery Terms
                                </label>
                                <input type="text" class="form-control" id="delivery_terms" name="delivery_terms" value="<?= e($po['delivery_terms'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label small fw-bold text-dark">
                                    PO Notes & Special Instructions
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($po['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PO Line Items Table Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-box-seam text-primary"></i> Order Line Items
                        </h6>
                        <span class="badge bg-light text-secondary border"><?= count($items) ?> Items</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="itemsTable">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-3" style="width: 4%;">#</th>
                                        <th style="width: 32%;">Item Details</th>
                                        <th class="text-center" style="width: 14%;">Quantity</th>
                                        <th class="text-end" style="width: 16%;">Unit Price</th>
                                        <th class="text-center" style="width: 12%;">Tax %</th>
                                        <th class="text-end" style="width: 10%;">Discount</th>
                                        <th class="text-end pe-3" style="width: 12%;">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $idx => $item): ?>
                                        <tr class="item-row" data-index="<?= $idx ?>">
                                            <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                            <td>
                                                <input type="hidden" name="items[<?= $idx ?>][id]" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][quotation_item_id]" value="<?= $item['quotation_item_id'] ?? '' ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][purchase_request_item_id]" value="<?= $item['purchase_request_item_id'] ?? '' ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][item_name]" value="<?= e($item['item_name']) ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][description]" value="<?= e($item['description'] ?? '') ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][unit]" value="<?= e($item['unit'] ?? 'Units') ?>">

                                                <div class="fw-semibold text-dark"><?= e($item['item_name']) ?></div>
                                                <?php if (!empty($item['description'])): ?>
                                                    <div class="small text-muted"><?= e($item['description']) ?></div>
                                                <?php endif; ?>
                                                <span class="badge bg-light text-secondary border font-monospace mt-1" style="font-size: 0.7rem;">Unit: <?= e($item['unit'] ?? 'Units') ?></span>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.01" min="0.01" class="form-control text-center item-qty font-monospace" name="items[<?= $idx ?>][quantity]" value="<?= number_format((float)$item['quantity'], 2, '.', '') ?>" required>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" step="0.01" min="0" class="form-control text-end item-price font-monospace" name="items[<?= $idx ?>][unit_price]" value="<?= number_format((float)$item['unit_price'], 2, '.', '') ?>" required>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.01" min="0" max="100" class="form-control text-center item-tax font-monospace" name="items[<?= $idx ?>][tax_percent]" value="<?= number_format((float)$item['tax_percent'], 2, '.', '') ?>">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" step="0.01" min="0" class="form-control text-end item-discount font-monospace" name="items[<?= $idx ?>][discount_amount]" value="<?= number_format((float)$item['discount_amount'], 2, '.', '') ?>">
                                                </div>
                                            </td>
                                            <td class="text-end pe-3 font-monospace fw-bold text-dark item-line-total">
                                                $<?= number_format((float)$item['total_price'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Financial Summary & Reference Cards -->
            <div class="col-lg-4">
                <!-- Live Calculations Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4 sticky-top" style="top: 1rem; z-index: 10;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-calculator text-primary"></i> Order Financial Summary
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Subtotal (Items):</span>
                            <span class="fw-semibold font-monospace text-dark" id="calcSubtotal">$0.00</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Tax Amount:</span>
                            <span class="font-monospace text-dark" id="calcTax">+$0.00</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Total Discount:</span>
                            <span class="font-monospace text-danger" id="calcDiscount">-$0.00</span>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="fw-bold text-dark fs-5">Grand Total:</span>
                            <span class="fw-bold font-monospace text-primary fs-4" id="calcGrandTotal">$0.00</span>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-save-fill"></i>
                                <span>Save Changes</span>
                            </button>
                            <a href="<?= url('modules/purchase_orders/view.php?id=' . $po['id']) ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Supplier Info Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-building text-primary"></i> Awarded Supplier
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="fw-bold text-dark mb-1"><?= e($po['supplier_name']) ?></div>
                        <div class="small text-muted font-monospace mb-2"><?= e($po['supplier_code']) ?></div>
                        <div class="small text-muted mb-1">
                            <i class="bi bi-envelope me-1"></i> <?= e($po['supplier_email'] ?: 'No email') ?>
                        </div>
                        <div class="small text-muted mb-0">
                            <i class="bi bi-telephone me-1"></i> <?= e($po['supplier_phone'] ?: 'No phone') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function recalculateTotals() {
        let subtotal = 0;
        let totalTax = 0;
        let totalDiscount = 0;

        const rows = document.querySelectorAll('#itemsTable tbody tr.item-row');
        rows.forEach(function(row) {
            const qtyInput = row.querySelector('.item-qty');
            const priceInput = row.querySelector('.item-price');
            const taxInput = row.querySelector('.item-tax');
            const discInput = row.querySelector('.item-discount');
            const lineTotalEl = row.querySelector('.item-line-total');

            const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
            const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
            const taxPct = parseFloat(taxInput ? taxInput.value : 0) || 0;
            const disc = parseFloat(discInput ? discInput.value : 0) || 0;

            const lineSub = qty * price;
            const lineTax = lineSub * (taxPct / 100);
            const lineTotal = Math.max(0, lineSub + lineTax - disc);

            if (lineTotalEl) {
                lineTotalEl.textContent = '$' + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            subtotal += lineSub;
            totalTax += lineTax;
            totalDiscount += disc;
        });

        const grandTotal = Math.max(0, subtotal + totalTax - totalDiscount);

        const subtotalEl = document.getElementById('calcSubtotal');
        const taxEl = document.getElementById('calcTax');
        const discEl = document.getElementById('calcDiscount');
        const grandTotalEl = document.getElementById('calcGrandTotal');

        if (subtotalEl) subtotalEl.textContent = '$' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (taxEl) taxEl.textContent = '+$' + totalTax.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (discEl) discEl.textContent = '-$' + totalDiscount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (grandTotalEl) grandTotalEl.textContent = '$' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const table = document.getElementById('itemsTable');
    if (table) {
        table.addEventListener('input', function(e) {
            if (e.target.matches('.item-qty, .item-price, .item-tax, .item-discount')) {
                recalculateTotals();
            }
        });
        recalculateTotals();
    }
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
