<?php
/**
 * Create Quotation View
 * Procurement Management CMS - Phase 03
 */

$pageTitle = 'Record Quotation';
$pageSubtitle = 'Enter Supplier Bid for Approved Purchase Request';
$activeNav = 'quotations';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// Permission check: Admin, Manager, Procurement Officer
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to create quotations.';
    redirect('modules/quotations/index.php');
}

$user = currentUser();
$db = getDb();

$selectedPrId = (int)($_GET['purchase_request_id'] ?? 0);

// 1. Fetch All Approved Purchase Requests
$approvedPRs = [];
try {
    $prStmt = $db->query("
        SELECT pr.id, pr.request_no, pr.purpose, pr.estimated_total, pr.required_date,
               d.name AS department_name, u.full_name AS requester_name
        FROM purchase_requests pr
        LEFT JOIN departments d ON d.id = pr.department_id
        LEFT JOIN users u ON u.id = pr.requested_by
        WHERE pr.status = 'approved' AND pr.deleted_at IS NULL
        ORDER BY pr.id DESC
    ");
    $approvedPRs = $prStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching approved PRs: ' . $e->getMessage());
}

// 2. If a PR is selected, fetch PR details and PR items
$currentPR = null;
$prItems = [];
$existingSupplierIdsForPR = [];

if ($selectedPrId > 0) {
    try {
        $stmt = $db->prepare("
            SELECT pr.*, d.name AS department_name, u.full_name AS requester_name
            FROM purchase_requests pr
            LEFT JOIN departments d ON d.id = pr.department_id
            LEFT JOIN users u ON u.id = pr.requested_by
            WHERE pr.id = :id AND pr.status = 'approved' AND pr.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $selectedPrId]);
        $currentPR = $stmt->fetch();

        if ($currentPR) {
            $itemsStmt = $db->prepare("
                SELECT * FROM purchase_request_items
                WHERE purchase_request_id = :pr_id
                ORDER BY id ASC
            ");
            $itemsStmt->execute([':pr_id' => $selectedPrId]);
            $prItems = $itemsStmt->fetchAll();

            // Find suppliers who already submitted a quotation for this PR
            $existStmt = $db->prepare("
                SELECT supplier_id FROM quotations
                WHERE purchase_request_id = :pr_id AND deleted_at IS NULL
            ");
            $existStmt->execute([':pr_id' => $selectedPrId]);
            $existingSupplierIdsForPR = $existStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        error_log('Error loading PR details for quotation: ' . $e->getMessage());
    }
}

// 3. Fetch Active Suppliers
$suppliers = [];
try {
    $supStmt = $db->query("
        SELECT id, supplier_code, name, email, phone, city, country
        FROM suppliers
        WHERE status = 'active' AND deleted_at IS NULL
        ORDER BY name ASC
    ");
    $suppliers = $supStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching suppliers: ' . $e->getMessage());
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
                    <li class="breadcrumb-item"><a href="<?= url('modules/quotations/index.php') ?>" class="text-decoration-none">Quotations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create Quotation</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Record Supplier Quotation</h1>
            <p class="text-muted small mb-0">Record quotation details, vendor item pricing, taxes, shipping, and delivery terms.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('modules/quotations/index.php') ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Quotations</span>
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

    <!-- Step 1: Select Approved Purchase Request -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-primary border-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <label for="pr_selector" class="form-label fw-bold text-dark mb-1">
                        Select Approved Purchase Request <span class="text-danger">*</span>
                    </label>
                    <select id="pr_selector" class="form-select form-select-lg" onchange="if(this.value) window.location.href = '<?= url('modules/quotations/create.php?purchase_request_id=') ?>' + this.value;">
                        <option value="">-- Choose an Approved Purchase Request --</option>
                        <?php foreach ($approvedPRs as $apr): ?>
                            <option value="<?= $apr['id'] ?>" <?= $selectedPrId == $apr['id'] ? 'selected' : '' ?>>
                                <?= e($apr['request_no']) ?> &mdash; <?= e($apr['purpose']) ?> (Dept: <?= e($apr['department_name'] ?? 'N/A') ?> | Est: <?= formatCurrency($apr['estimated_total']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <?php if (empty($approvedPRs)): ?>
                        <div class="text-danger small fw-semibold">
                            <i class="bi bi-exclamation-circle me-1"></i> No approved purchase requests available.
                        </div>
                    <?php else: ?>
                        <span class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i> Only approved requests can receive supplier bids.
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($currentPR): ?>
        <!-- PR Summary Banner -->
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
            <div class="card-body p-3">
                <div class="row g-3 small">
                    <div class="col-md-3 col-sm-6">
                        <span class="text-muted d-block">Request Number</span>
                        <a href="<?= url('modules/purchase_requests/view.php?id=' . $currentPR['id']) ?>" class="fw-bold text-primary font-monospace text-decoration-none">
                            <?= e($currentPR['request_no']) ?>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <span class="text-muted d-block">Department / Requester</span>
                        <span class="fw-semibold text-dark"><?= e($currentPR['department_name'] ?? 'N/A') ?> / <?= e($currentPR['requester_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <span class="text-muted d-block">Required By Date</span>
                        <span class="fw-semibold text-dark"><?= !empty($currentPR['required_date']) ? formatDate($currentPR['required_date'], 'd M Y') : 'Flexible' ?></span>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <span class="text-muted d-block">Estimated Budget</span>
                        <span class="fw-bold text-success font-monospace"><?= formatCurrency($currentPR['estimated_total']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quotation Creation Form -->
        <form method="POST" action="<?= url('modules/quotations/store.php') ?>" id="quotationForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="purchase_request_id" value="<?= $currentPR['id'] ?>">

            <div class="row g-4">
                <!-- Left Column: Supplier, Items, Terms -->
                <div class="col-lg-8">
                    <!-- Supplier Selection Card -->
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-building text-primary"></i> Select Supplier Vendor <span class="text-danger">*</span>
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="supplier_id" class="form-label fw-semibold">Vendor Company</label>
                                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                                        <option value="">-- Choose Supplier --</option>
                                        <?php foreach ($suppliers as $s): ?>
                                            <?php
                                                $alreadyQuoted = in_array($s['id'], $existingSupplierIdsForPR);
                                            ?>
                                            <option value="<?= $s['id'] ?>" <?= ($old['supplier_id'] ?? '') == $s['id'] ? 'selected' : '' ?> <?= $alreadyQuoted ? 'disabled' : '' ?>>
                                                <?= e($s['name']) ?> (<?= e($s['supplier_code']) ?>) <?= !empty($s['city']) ? ' - ' . e($s['city']) : '' ?> <?= $alreadyQuoted ? ' [Already Submitted Quotation]' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a supplier.</div>
                                    <div class="form-text small">Suppliers who already submitted a quotation for this PR are disabled to prevent duplicates.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Pricing Table Card -->
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-list-check text-primary"></i> Quotation Line Items & Unit Prices
                            </h6>
                            <span class="badge bg-light text-secondary border"><?= count($prItems) ?> items</span>
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
                                        <?php if (empty($prItems)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    No line items found for this purchase request.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($prItems as $idx => $item): ?>
                                                <tr class="quote-item-row" data-index="<?= $idx ?>">
                                                    <td>
                                                        <input type="hidden" name="items[<?= $idx ?>][purchase_request_item_id]" value="<?= $item['id'] ?>">
                                                        <div class="fw-semibold text-dark"><?= e($item['item_name']) ?></div>
                                                        <?php if (!empty($item['description'])): ?>
                                                            <div class="small text-muted"><?= e($item['description']) ?></div>
                                                        <?php endif; ?>
                                                        <div class="mt-1">
                                                            <input type="text" class="form-control form-control-sm" name="items[<?= $idx ?>][remarks]" placeholder="Vendor remarks/brand/spec notes...">
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
                                                            <input type="number" step="0.01" min="0" class="form-control text-end font-monospace item-price" name="items[<?= $idx ?>][unit_price]" placeholder="0.00" value="<?= e($item['estimated_unit_price'] ?? '') ?>" required>
                                                        </div>
                                                        <div class="text-muted small text-end mt-1">Est: $<?= number_format((float)$item['estimated_unit_price'], 2) ?></div>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="fw-bold font-monospace text-dark item-subtotal-display">$0.00</span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
                                    <input type="text" class="form-control" id="payment_terms" name="payment_terms" placeholder="e.g. Net 30 Days / 50% Advance" value="<?= e($old['payment_terms'] ?? 'Net 30 Days') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label for="delivery_terms" class="form-label fw-semibold">Delivery Terms / Lead Time</label>
                                    <input type="text" class="form-control" id="delivery_terms" name="delivery_terms" placeholder="e.g. 5-7 Business Days, FOB Destination" value="<?= e($old['delivery_terms'] ?? '5-7 Business Days') ?>">
                                </div>

                                <div class="col-12">
                                    <label for="notes" class="form-label fw-semibold">Vendor Remarks / Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional quotation notes, warranties, exclusions, or vendor comments..."><?= e($old['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Quotation Meta & Financial Summary -->
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
                                <input type="date" class="form-control" id="valid_until" name="valid_until" value="<?= e($old['valid_until'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                                <div class="form-text small">Default validity is 30 calendar days.</div>
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
                                    <input type="number" step="0.01" min="0" max="100" class="form-control text-end font-monospace" id="tax_percentage" name="tax_percentage" value="<?= e($old['tax_percentage'] ?? '0.00') ?>" placeholder="0.00">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <!-- Shipping / Freight Cost -->
                            <div class="mb-3">
                                <label for="shipping_cost" class="form-label text-muted small mb-1">Shipping & Freight ($):</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control text-end font-monospace" id="shipping_cost" name="shipping_cost" value="<?= e($old['shipping_cost'] ?? '0.00') ?>" placeholder="0.00">
                                </div>
                            </div>

                            <!-- Other Charges -->
                            <div class="mb-3">
                                <label for="other_charges" class="form-label text-muted small mb-1">Other Surcharges / Fees ($):</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control text-end font-monospace" id="other_charges" name="other_charges" value="<?= e($old['other_charges'] ?? '0.00') ?>" placeholder="0.00">
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
                            <span>Save Quotation as Draft</span>
                        </button>
                        <a href="<?= url('modules/quotations/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </form>

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
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-3 py-5 text-center text-muted">
            <i class="bi bi-arrow-up-circle fs-1 text-primary mb-3"></i>
            <h5 class="fw-bold text-dark">Please Select a Purchase Request</h5>
            <p class="mb-0 text-muted">Choose an approved purchase request from the dropdown above to load its requisition items and enter supplier quotes.</p>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
