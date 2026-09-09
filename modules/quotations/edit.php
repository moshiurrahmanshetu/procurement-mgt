<?php
/**
 * Edit Draft Quotation View
 * Procurement Management CMS - Phase 03
 */

$pageTitle = 'Edit Draft Quotation';
$pageSubtitle = 'Update Vendor Quotation Details and Line Items';
$activeNav = 'quotations';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// Permission check: Admin, Manager, Procurement Officer
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to edit quotations.';
    redirect('modules/quotations/index.php');
}

$quotationId = (int)($_GET['id'] ?? 0);
if ($quotationId <= 0) {
    $_SESSION['flash_error'] = 'Invalid quotation ID.';
    redirect('modules/quotations/index.php');
}

$db = getDb();
$quotation = null;
try {
    $stmt = $db->prepare("
        SELECT q.*,
               pr.request_no, pr.purpose AS pr_purpose, pr.estimated_total AS pr_estimated_cost,
               d.name AS department_name, u_req.full_name AS requester_name,
               s.name AS supplier_name, s.supplier_code
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        LEFT JOIN departments d ON d.id = pr.department_id
        LEFT JOIN users u_req ON u_req.id = pr.requested_by
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE q.id = :id AND q.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $quotationId]);
    $quotation = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching quotation for edit: ' . $e->getMessage());
}

if (!$quotation) {
    $_SESSION['flash_error'] = 'Quotation not found or has been deleted.';
    redirect('modules/quotations/index.php');
}

// Can only edit if status is draft
if ($quotation['status'] !== 'draft') {
    $_SESSION['flash_error'] = "Only draft quotations can be edited. Current status is '{$quotation['status']}'.";
    redirect('modules/quotations/view.php?id=' . $quotationId);
}

// Fetch Quotation Items
$items = [];
try {
    $itemStmt = $db->prepare("
        SELECT qi.*, pri.item_name, pri.description AS pr_item_desc, pri.unit, pri.estimated_unit_price
        FROM quotation_items qi
        LEFT JOIN purchase_request_items pri ON pri.id = qi.purchase_request_item_id
        WHERE qi.quotation_id = :id
        ORDER BY qi.id ASC
    ");
    $itemStmt->execute([':id' => $quotationId]);
    $items = $itemStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching items for edit: ' . $e->getMessage());
}

$old = $_SESSION['old_input'] ?? $quotation;
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
                    <li class="breadcrumb-item"><a href="<?= url('modules/quotations/index.php') ?>" class="text-decoration-none">Quotations</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('modules/quotations/view.php?id=' . $quotation['id']) ?>" class="text-decoration-none"><?= e($quotation['quotation_no']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Draft</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Edit Draft Quotation: <?= e($quotation['quotation_no']) ?></h1>
            <p class="text-muted small mb-0">Vendor: <strong><?= e($quotation['supplier_name']) ?></strong> &bull; Requisition: <strong><?= e($quotation['request_no']) ?></strong></p>
        </div>
        <div>
            <a href="<?= url('modules/quotations/view.php?id=' . $quotation['id']) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Quotation</span>
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

    <form method="POST" action="<?= url('modules/quotations/update.php') ?>" id="quotationForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="id" value="<?= $quotation['id'] ?>">

        <div class="row g-4">
            <!-- Left Column: Items and Commercial Terms -->
            <div class="col-lg-8">
                <!-- Items Pricing Table Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-list-check text-primary"></i> Quotation Line Items & Unit Prices
                        </h6>
                        <span class="badge bg-light text-secondary border"><?= count($items) ?> items</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0" id="quoteItemsTable">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th style="width: 35%;">Item & Specifications</th>
                                        <th style="width: 12%;" class="text-center">PR Quantity</th>
                                        <th style="width: 10%;" class="text-center">UOM</th>
                                        <th style="width: 20%;" class="text-end">Offered Unit Price ($) <span class="text-danger">*</span></th>
                                        <th style="width: 23%;" class="text-end">Subtotal ($)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $idx => $item): ?>
                                        <tr class="quote-item-row" data-index="<?= $idx ?>">
                                            <td>
                                                <input type="hidden" name="items[<?= $idx ?>][id]" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="items[<?= $idx ?>][purchase_request_item_id]" value="<?= $item['purchase_request_item_id'] ?>">
                                                <div class="fw-semibold text-dark"><?= e($item['item_name']) ?></div>
                                                <?php if (!empty($item['pr_item_desc'])): ?>
                                                    <div class="small text-muted"><?= e($item['pr_item_desc']) ?></div>
                                                <?php endif; ?>
                                                <div class="mt-1">
                                                    <input type="text" class="form-control form-control-sm" name="items[<?= $idx ?>][remarks]" placeholder="Vendor remarks..." value="<?= e($item['remarks'] ?? '') ?>">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-center font-monospace item-qty" name="items[<?= $idx ?>][quantity]" value="<?= (float)$item['quantity'] ?>" required readonly>
                                            </td>
                                            <td class="text-center small text-muted">
                                                <?= e($item['unit']) ?>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" step="0.01" min="0" class="form-control text-end font-monospace item-price" name="items[<?= $idx ?>][unit_price]" placeholder="0.00" value="<?= e($item['unit_price']) ?>" required>
                                                </div>
                                                <div class="text-muted small text-end mt-1">Est: $<?= number_format((float)$item['estimated_unit_price'], 2) ?></div>
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-bold font-monospace text-dark item-subtotal-display">$0.00</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Commercial Terms & Conditions Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-text text-primary"></i> Commercial & Delivery Terms
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="payment_terms" class="form-label fw-semibold">Payment Terms</label>
                                <input type="text" class="form-control" id="payment_terms" name="payment_terms" value="<?= e($old['payment_terms'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="delivery_terms" class="form-label fw-semibold">Delivery Terms / Lead Time</label>
                                <input type="text" class="form-control" id="delivery_terms" name="delivery_terms" value="<?= e($old['delivery_terms'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label fw-semibold">Vendor Remarks / Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?= e($old['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Dates & Financial Breakdown -->
            <div class="col-lg-4">
                <!-- Quotation Dates Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-calendar-event text-primary"></i> Quotation Timeline
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label for="quotation_date" class="form-label fw-semibold">Quotation Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="quotation_date" name="quotation_date" value="<?= e($old['quotation_date'] ?? date('Y-m-d')) ?>" required>
                        </div>

                        <div class="mb-0">
                            <label for="valid_until" class="form-label fw-semibold">Validity / Expiry Date</label>
                            <input type="date" class="form-control" id="valid_until" name="valid_until" value="<?= e($old['valid_until'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Financial Breakdown Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-calculator text-primary"></i> Financial Summary
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- Items Subtotal -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Items Subtotal:</span>
                            <span class="fw-bold font-monospace text-dark fs-6" id="summarySubtotal">$0.00</span>
                        </div>

                        <!-- Tax Rate & Tax Amount -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="tax_percentage" class="form-label text-muted small mb-0">Tax / VAT Rate (%):</label>
                                <span class="font-monospace text-muted small" id="summaryTaxAmount">$0.00</span>
                            </div>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0" max="100" class="form-control text-end font-monospace" id="tax_percentage" name="tax_percentage" value="<?= e($old['tax_percentage'] ?? '0.00') ?>">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                        <!-- Shipping Cost -->
                        <div class="mb-3">
                            <label for="shipping_cost" class="form-label text-muted small mb-1">Shipping & Freight ($):</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control text-end font-monospace" id="shipping_cost" name="shipping_cost" value="<?= e($old['shipping_cost'] ?? '0.00') ?>">
                            </div>
                        </div>

                        <!-- Other Charges -->
                        <div class="mb-3">
                            <label for="other_charges" class="form-label text-muted small mb-1">Other Surcharges ($):</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control text-end font-monospace" id="other_charges" name="other_charges" value="<?= e($old['other_charges'] ?? '0.00') ?>">
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- Grand Total -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark fs-5">Grand Total:</span>
                            <span class="fw-bold font-monospace text-primary fs-4" id="summaryGrandTotal">$0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-check-lg"></i>
                        <span>Update Draft Quotation</span>
                    </button>
                    <a href="<?= url('modules/quotations/view.php?id=' . $quotation['id']) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function recalculateTotals() {
        let subtotal = 0;
        const rows = document.querySelectorAll('.quote-item-row');

        rows.forEach(row => {
            const qtyInput = row.querySelector('.item-qty');
            const priceInput = row.querySelector('.item-price');
            const subtotalDisplay = row.querySelector('.item-subtotal-display');

            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const itemSubtotal = qty * price;

            subtotal += itemSubtotal;
            subtotalDisplay.textContent = '$' + itemSubtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        });

        const taxPercent = parseFloat(document.getElementById('tax_percentage').value) || 0;
        const shippingCost = parseFloat(document.getElementById('shipping_cost').value) || 0;
        const otherCharges = parseFloat(document.getElementById('other_charges').value) || 0;

        const taxAmount = subtotal * (taxPercent / 100);
        const grandTotal = subtotal + taxAmount + shippingCost + otherCharges;

        document.getElementById('summarySubtotal').textContent = '$' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('summaryTaxAmount').textContent = '+$' + taxAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('summaryGrandTotal').textContent = '$' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    document.querySelectorAll('.item-price, .item-qty').forEach(input => {
        input.addEventListener('input', recalculateTotals);
    });

    document.getElementById('tax_percentage').addEventListener('input', recalculateTotals);
    document.getElementById('shipping_cost').addEventListener('input', recalculateTotals);
    document.getElementById('other_charges').addEventListener('input', recalculateTotals);

    // Initial calculation
    recalculateTotals();

    // Form validation
    const form = document.getElementById('quotationForm');
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
