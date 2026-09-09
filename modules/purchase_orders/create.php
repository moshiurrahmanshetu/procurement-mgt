<?php
/**
 * Create Purchase Order
 * Procurement Management CMS - Phase 04
 */

$pageTitle = 'Create Purchase Order';
$pageSubtitle = 'Issue Official PO from Awarded Supplier Quotation';
$activeNav = 'purchase_orders';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

// RBAC: Only Procurement Officers and Administrators can generate POs
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: Only Procurement Officers and Administrators can generate Purchase Orders.');
    redirect('modules/purchase_orders/index.php');
}

$selectedQuotationId = (int)($_GET['quotation_id'] ?? 0);

// 1. Fetch all eligible quotations (status = 'selected' and no active PO)
$eligibleQuotations = [];
try {
    $qStmt = $db->query("
        SELECT q.id, q.quotation_no, q.total_amount, q.quotation_date,
               pr.id AS pr_id, pr.request_no, pr.purpose AS pr_purpose,
               s.name AS supplier_name, s.supplier_code
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE q.status = 'selected' 
          AND q.deleted_at IS NULL
          AND NOT EXISTS (
              SELECT 1 FROM purchase_orders po 
              WHERE po.quotation_id = q.id 
                AND po.deleted_at IS NULL
          )
        ORDER BY q.id DESC
    ");
    $eligibleQuotations = $qStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching eligible quotations for PO: ' . $e->getMessage());
}

// 2. If a quotation is selected, load its full details and items
$selectedQuotation = null;
$quotationItems = [];

if ($selectedQuotationId > 0) {
    try {
        $stmt = $db->prepare("
            SELECT q.*,
                   pr.id AS pr_id, pr.request_no, pr.purpose AS pr_purpose, pr.estimated_total AS pr_estimated_cost,
                   d.name AS department_name,
                   u_req.full_name AS requester_name,
                   s.id AS supplier_id, s.name AS supplier_name, s.supplier_code, s.email AS supplier_email,
                   s.phone AS supplier_phone, s.tax_number AS supplier_tax, s.address AS supplier_address,
                   s.city AS supplier_city, s.country AS supplier_country
            FROM quotations q
            JOIN purchase_requests pr ON pr.id = q.purchase_request_id
            LEFT JOIN departments d ON d.id = pr.department_id
            LEFT JOIN users u_req ON u_req.id = pr.requested_by
            JOIN suppliers s ON s.id = q.supplier_id
            WHERE q.id = :id AND q.status = 'selected' AND q.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $selectedQuotationId]);
        $selectedQuotation = $stmt->fetch();

        if ($selectedQuotation) {
            // Check if PO already exists for this quotation
            $checkPo = $db->prepare("SELECT id, po_no FROM purchase_orders WHERE quotation_id = :qid AND deleted_at IS NULL LIMIT 1");
            $checkPo->execute([':qid' => $selectedQuotationId]);
            $existingPo = $checkPo->fetch();
            if ($existingPo) {
                setFlash('error', "A Purchase Order ({$existingPo['po_no']}) has already been generated for this quotation.");
                redirect('modules/purchase_orders/view.php?id=' . $existingPo['id']);
            }

            // Fetch items
            $itemStmt = $db->prepare("
                SELECT qi.*,
                       pri.item_name,
                       pri.description AS pr_item_desc,
                       pri.unit
                FROM quotation_items qi
                LEFT JOIN purchase_request_items pri ON pri.id = qi.purchase_request_item_id
                WHERE qi.quotation_id = :qid
                ORDER BY qi.id ASC
            ");
            $itemStmt->execute([':qid' => $selectedQuotationId]);
            $quotationItems = $itemStmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log('Error loading selected quotation for PO creation: ' . $e->getMessage());
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
                    <li class="breadcrumb-item"><a href="<?= url('modules/purchase_orders/index.php') ?>" class="text-decoration-none">Purchase Orders</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create New PO</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Create Purchase Order</h1>
            <p class="text-muted small mb-0">Generate a legally binding purchase contract from an awarded supplier bid.</p>
        </div>
        <div>
            <a href="<?= url('modules/purchase_orders/index.php') ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Orders</span>
            </a>
        </div>
    </div>

    <!-- Step 1: Select Winning Quotation if not loaded -->
    <?php if (!$selectedQuotation): ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-award-fill text-warning"></i> Select Winning Quotation
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($eligibleQuotations)): ?>
                            <div class="text-center py-5">
                                <div class="avatar-lg bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                                    <i class="bi bi-file-earmark-x fs-2"></i>
                                </div>
                                <h5 class="fw-bold text-dark">No Available Selected Quotations</h5>
                                <p class="text-muted small max-w-md mx-auto mb-4" style="max-width: 450px;">
                                    Purchase Orders can only be created from approved purchase requests with an officially awarded (selected) quotation that has not yet had a Purchase Order generated.
                                </p>
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="<?= url('modules/quotations/index.php') ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-file-earmark-text me-1"></i> Go to Quotations
                                    </a>
                                    <a href="<?= url('modules/purchase_requests/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-card-checklist me-1"></i> Go to Requisitions
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="GET" action="<?= url('modules/purchase_orders/create.php') ?>" class="mb-0">
                                <div class="mb-4">
                                    <label for="quotation_id" class="form-label fw-bold text-dark">
                                        Awarded Quotation <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select form-select-lg" id="quotation_id" name="quotation_id" required onchange="this.form.submit()">
                                        <option value="">-- Choose an Awarded Quotation --</option>
                                        <?php foreach ($eligibleQuotations as $eq): ?>
                                            <option value="<?= $eq['id'] ?>" <?= $selectedQuotationId === (int)$eq['id'] ? 'selected' : '' ?>>
                                                <?= e($eq['quotation_no']) ?> &bull; <?= e($eq['supplier_name']) ?> &bull; <?= formatCurrency($eq['total_amount']) ?> (PR: <?= e($eq['request_no']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Selecting a quotation will automatically populate line items, supplier commercial terms, and requisition references.</div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                        <span>Proceed to Order Setup</span>
                                        <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Step 2: Full PO Form with Loaded Quotation -->
        <form method="POST" action="<?= url('modules/purchase_orders/store.php') ?>" id="poCreateForm" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="quotation_id" value="<?= $selectedQuotation['id'] ?>">

            <!-- Quotation Reference Banner -->
            <div class="alert alert-primary border-primary-subtle shadow-sm d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-3 rounded-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-award fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark">
                            Source Quotation: <span class="text-primary font-monospace"><?= e($selectedQuotation['quotation_no']) ?></span>
                            &bull; PR: <span class="text-dark font-monospace"><?= e($selectedQuotation['request_no']) ?></span>
                        </div>
                        <div class="small text-muted">
                            Vendor: <strong class="text-dark"><?= e($selectedQuotation['supplier_name']) ?></strong> (<?= e($selectedQuotation['supplier_code']) ?>)
                            &bull; Original Quote Total: <strong class="text-dark font-monospace"><?= formatCurrency($selectedQuotation['total_amount']) ?></strong>
                        </div>
                    </div>
                </div>
                <div>
                    <a href="<?= url('modules/purchase_orders/create.php') ?>" class="btn btn-outline-primary btn-sm bg-white">
                        <i class="bi bi-arrow-repeat me-1"></i> Change Quotation
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left Column: PO Parameters & Items Table -->
                <div class="col-lg-8">
                    <!-- PO Details Card -->
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-ruled text-primary"></i> Purchase Order Details & Logistics
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="po_date" class="form-label small fw-bold text-dark">
                                        PO Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="po_date" name="po_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="expected_delivery_date" class="form-label small fw-bold text-dark">
                                        Expected Delivery Date
                                    </label>
                                    <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date" value="<?= !empty($selectedQuotation['valid_until']) ? date('Y-m-d', strtotime('+7 days')) : '' ?>">
                                </div>

                                <div class="col-12">
                                    <label for="delivery_address" class="form-label small fw-bold text-dark">
                                        Delivery / Shipping Destination Address
                                    </label>
                                    <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" placeholder="Enter warehouse/office delivery address..."><?= e($selectedQuotation['department_name'] ? "Receiving Dock / {$selectedQuotation['department_name']} Dept\nMain Campus Logistics Center" : "Main Warehouse Receiving Dock") ?></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label for="payment_terms" class="form-label small fw-bold text-dark">
                                        Payment Terms
                                    </label>
                                    <input type="text" class="form-control" id="payment_terms" name="payment_terms" value="<?= e($selectedQuotation['payment_terms'] ?: 'Net 30 Days') ?>" placeholder="e.g. Net 30, 50% Advance, etc.">
                                </div>

                                <div class="col-md-6">
                                    <label for="delivery_terms" class="form-label small fw-bold text-dark">
                                        Delivery / Shipping Terms
                                    </label>
                                    <input type="text" class="form-control" id="delivery_terms" name="delivery_terms" value="<?= e($selectedQuotation['delivery_terms'] ?: 'FOB Destination / Standard Delivery') ?>" placeholder="e.g. FOB Destination, Doorstep Delivery">
                                </div>

                                <div class="col-12">
                                    <label for="notes" class="form-label small fw-bold text-dark">
                                        PO Notes & Special Instructions for Vendor
                                    </label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Include any special instructions, packaging requirements, or delivery contacts..."><?= e($selectedQuotation['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PO Line Items Table Card -->
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-box-seam text-primary"></i> Order Line Items & Commercial Snapshot
                            </h6>
                            <span class="badge bg-light text-secondary border"><?= count($quotationItems) ?> Items</span>
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
                                        <?php 
                                        $defaultTaxRate = (float)($selectedQuotation['tax_percentage'] ?? 0);
                                        foreach ($quotationItems as $idx => $item): 
                                            $qty = (float)$item['quantity'];
                                            $price = (float)$item['unit_price'];
                                            $itemTaxPct = $defaultTaxRate;
                                            $itemDisc = 0.00;
                                            $itemSub = $qty * $price;
                                            $itemTax = $itemSub * ($itemTaxPct / 100);
                                            $itemTotal = $itemSub + $itemTax - $itemDisc;
                                        ?>
                                            <tr class="item-row" data-index="<?= $idx ?>">
                                                <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                                <td>
                                                    <input type="hidden" name="items[<?= $idx ?>][quotation_item_id]" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][purchase_request_item_id]" value="<?= (int)($item['purchase_request_item_id'] ?? 0) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][item_name]" value="<?= e($item['item_name']) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][description]" value="<?= e($item['pr_item_desc'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][unit]" value="<?= e($item['unit'] ?? 'Units') ?>">

                                                    <div class="fw-semibold text-dark"><?= e($item['item_name']) ?></div>
                                                    <?php if (!empty($item['pr_item_desc'])): ?>
                                                        <div class="small text-muted"><?= e($item['pr_item_desc']) ?></div>
                                                    <?php endif; ?>
                                                    <span class="badge bg-light text-secondary border font-monospace mt-1" style="font-size: 0.7rem;">Unit: <?= e($item['unit'] ?? 'Units') ?></span>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" min="0.01" class="form-control text-center item-qty font-monospace" name="items[<?= $idx ?>][quantity]" value="<?= number_format($qty, 2, '.', '') ?>" required>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" step="0.01" min="0" class="form-control text-end item-price font-monospace" name="items[<?= $idx ?>][unit_price]" value="<?= number_format($price, 2, '.', '') ?>" required>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" min="0" max="100" class="form-control text-center item-tax font-monospace" name="items[<?= $idx ?>][tax_percent]" value="<?= number_format($itemTaxPct, 2, '.', '') ?>">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" step="0.01" min="0" class="form-control text-end item-discount font-monospace" name="items[<?= $idx ?>][discount_amount]" value="<?= number_format($itemDisc, 2, '.', '') ?>">
                                                    </div>
                                                </td>
                                                <td class="text-end pe-3 font-monospace fw-bold text-dark item-line-total">
                                                    $<?= number_format($itemTotal, 2) ?>
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
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Create Draft Order</span>
                                </button>
                                <a href="<?= url('modules/purchase_orders/index.php') ?>" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>

                            <div class="text-center mt-3">
                                <span class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i> Saved as Draft initially. You can submit it for approval on the next step.
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Vendor Card -->
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-building text-primary"></i> Selected Supplier
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="fw-bold text-dark mb-1"><?= e($selectedQuotation['supplier_name']) ?></div>
                            <div class="small text-muted font-monospace mb-2"><?= e($selectedQuotation['supplier_code']) ?></div>
                            <div class="small text-muted mb-1">
                                <i class="bi bi-envelope me-1"></i> <?= e($selectedQuotation['supplier_email'] ?: 'No email') ?>
                            </div>
                            <div class="small text-muted mb-1">
                                <i class="bi bi-telephone me-1"></i> <?= e($selectedQuotation['supplier_phone'] ?: 'No phone') ?>
                            </div>
                            <?php if (!empty($selectedQuotation['supplier_tax'])): ?>
                                <div class="small text-muted mb-0">
                                    <i class="bi bi-file-text me-1"></i> Tax/VAT ID: <?= e($selectedQuotation['supplier_tax']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Purchase Request Card -->
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-card-checklist text-primary"></i> Requisition Reference
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="fw-bold text-dark mb-1 font-monospace"><?= e($selectedQuotation['request_no']) ?></div>
                            <div class="small text-muted mb-2"><?= e($selectedQuotation['pr_purpose']) ?></div>
                            <div class="small text-muted">
                                Department: <strong class="text-dark"><?= e($selectedQuotation['department_name'] ?? 'N/A') ?></strong><br>
                                Requester: <strong class="text-dark"><?= e($selectedQuotation['requester_name'] ?? 'N/A') ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
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
