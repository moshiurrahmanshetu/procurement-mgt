<?php
/**
 * Edit Draft Purchase Request Form
 * Procurement Management CMS - Phase 02
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid purchase request ID.');
    redirect('modules/purchase_requests/index.php');
}

$user = currentUser();
$userId = currentUserId();
$isAdmin = userHasRole('administrator');
$db = getDb();

// 1. Fetch Request
$pr = null;
try {
    $stmt = $db->prepare("SELECT * FROM purchase_requests WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $id]);
    $pr = $stmt->fetch();
} catch (Exception $e) {
    error_log('Fetch PR for Edit Error: ' . $e->getMessage());
}

if (!$pr) {
    setFlash('error', 'Purchase request not found.');
    redirect('modules/purchase_requests/index.php');
}

// 2. Enforce Draft Status
if ($pr['status'] !== 'draft') {
    setFlash('error', 'Only draft purchase requests can be modified.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 3. Enforce Ownership / Admin Authorization
$isOwner = ((int)$pr['requested_by'] === $userId);
if (!$isOwner && !$isAdmin) {
    setFlash('error', 'Access Denied: You cannot modify another user\'s requisition.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 4. Fetch Departments
$departments = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM departments WHERE deleted_at IS NULL AND status = 'active' ORDER BY name ASC");
    $departments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Fetch Depts Error: ' . $e->getMessage());
}

// 5. Fetch Existing Line Items
$items = [];
try {
    $itemStmt = $db->prepare("SELECT * FROM purchase_request_items WHERE purchase_request_id = :pr_id ORDER BY id ASC");
    $itemStmt->execute([':pr_id' => $id]);
    $items = $itemStmt->fetchAll();
} catch (Exception $e) {
    error_log('Fetch PR Items for Edit Error: ' . $e->getMessage());
}

if (empty($items)) {
    $items[] = [
        'item_name' => '',
        'description' => '',
        'quantity' => 1,
        'unit' => 'Pcs',
        'estimated_unit_price' => 0.00,
        'estimated_total' => 0.00
    ];
}

$pageTitle = 'Edit Request ' . $pr['request_no'];
$pageSubtitle = 'Modify draft requisition details and items';
$activeNav = 'purchase_requests';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<form action="<?= url('modules/purchase_requests/update.php') ?>" method="POST" id="prEditForm">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= $pr['id'] ?>">

    <div class="row g-4">
        <!-- Main Form Details -->
        <div class="col-lg-8">
            <!-- Header Information Card -->
            <div class="card-cms mb-4">
                <div class="card-cms-header">
                    <h3 class="card-cms-title">
                        <i class="bi bi-pencil-square text-primary"></i>
                        <span>Edit Requisition: <span class="font-monospace text-primary"><?= e($pr['request_no']) ?></span></span>
                    </h3>
                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 small">Draft</span>
                </div>
                <div class="card-cms-body">
                    <div class="row g-3">
                        <!-- Department -->
                        <div class="col-md-6">
                            <label for="department_id" class="form-label-cms">Department <span class="text-danger">*</span></label>
                            <select class="form-select form-select-cms" id="department_id" name="department_id" required>
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= ((int)$pr['department_id'] === (int)$d['id']) ? 'selected' : '' ?>>
                                        <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Priority -->
                        <div class="col-md-6">
                            <label for="priority" class="form-label-cms">Priority Level <span class="text-danger">*</span></label>
                            <select class="form-select form-select-cms" id="priority" name="priority" required>
                                <option value="low" <?= ($pr['priority'] === 'low') ? 'selected' : '' ?>>Low - Routine Stock</option>
                                <option value="medium" <?= ($pr['priority'] === 'medium') ? 'selected' : '' ?>>Medium - Normal Business</option>
                                <option value="high" <?= ($pr['priority'] === 'high') ? 'selected' : '' ?>>High - Time Sensitive</option>
                                <option value="urgent" <?= ($pr['priority'] === 'urgent') ? 'selected' : '' ?>>Urgent - Critical Requirement</option>
                            </select>
                        </div>

                        <!-- Request Date -->
                        <div class="col-md-6">
                            <label for="request_date" class="form-label-cms">Request Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-cms" id="request_date" name="request_date" 
                                   value="<?= e($pr['request_date']) ?>" required>
                        </div>

                        <!-- Required Date -->
                        <div class="col-md-6">
                            <label for="required_date" class="form-label-cms">Required By Date</label>
                            <input type="date" class="form-control form-control-cms" id="required_date" name="required_date" 
                                   value="<?= e($pr['required_date'] ?? '') ?>">
                            <div class="form-text small">Target date needed by department.</div>
                        </div>

                        <!-- Purpose -->
                        <div class="col-12">
                            <label for="purpose" class="form-label-cms">Purpose / Justification <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-cms" id="purpose" name="purpose" rows="3" 
                                      placeholder="Explain the business need..." required><?= e($pr['purpose']) ?></textarea>
                        </div>

                        <!-- Additional Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label-cms">Additional Notes / Special Instructions</label>
                            <textarea class="form-control form-control-cms" id="notes" name="notes" rows="2" 
                                      placeholder="Optional notes..."><?= e($pr['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requested Items Table Card -->
            <div class="card-cms">
                <div class="card-cms-header">
                    <h3 class="card-cms-title">
                        <i class="bi bi-list-check text-primary"></i>
                        <span>Requisition Line Items</span>
                    </h3>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addItemRowBtn">
                        <i class="bi bi-plus-circle me-1"></i> Add Line Item
                    </button>
                </div>
                <div class="card-cms-body p-0">
                    <div class="table-responsive">
                        <table class="table table-cms align-middle mb-0" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Item Name & Description <span class="text-danger">*</span></th>
                                    <th style="width: 15%;">Quantity <span class="text-danger">*</span></th>
                                    <th style="width: 18%;">Unit <span class="text-danger">*</span></th>
                                    <th style="width: 18%;">Est. Unit Price ($) <span class="text-danger">*</span></th>
                                    <th style="width: 14%;" class="text-end">Line Total ($)</th>
                                    <th style="width: 5%;" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <?php foreach ($items as $idx => $item): ?>
                                    <tr class="item-row">
                                        <td>
                                            <input type="text" class="form-control form-control-sm item-name mb-1" name="items[<?= $idx ?>][name]" 
                                                   value="<?= e($item['item_name']) ?>" placeholder="e.g. Item Name" required>
                                            <input type="text" class="form-control form-control-sm text-muted item-desc" name="items[<?= $idx ?>][description]" 
                                                   value="<?= e($item['description'] ?? '') ?>" placeholder="Specifications / details (optional)">
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm item-qty text-end" name="items[<?= $idx ?>][quantity]" 
                                                   value="<?= (float)$item['quantity'] ?>" min="0.01" step="any" required>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm item-unit" name="items[<?= $idx ?>][unit]" required>
                                                <?php 
                                                    $units = ['Pcs' => 'Pieces (Pcs)', 'Unit' => 'Units', 'Box' => 'Boxes', 'Set' => 'Sets', 'Pack' => 'Packs', 'Kg' => 'Kilograms (Kg)', 'Meter' => 'Meters', 'Liter' => 'Liters', 'Roll' => 'Rolls', 'Lot' => 'Lot'];
                                                    foreach ($units as $uVal => $uLabel):
                                                ?>
                                                    <option value="<?= $uVal ?>" <?= ($item['unit'] === $uVal) ? 'selected' : '' ?>><?= $uLabel ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm item-price text-end font-monospace" name="items[<?= $idx ?>][unit_price]" 
                                                   value="<?= number_format((float)$item['estimated_unit_price'], 2, '.', '') ?>" min="0" step="0.01" required>
                                        </td>
                                        <td class="text-end font-monospace fw-semibold item-line-total text-dark">
                                            <?= formatCurrency($item['estimated_total']) ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm border-0 remove-row-btn" title="Remove Item" <?= (count($items) <= 1) ? 'disabled' : '' ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Summary & Action Sidebar -->
        <div class="col-lg-4">
            <!-- Financial Summary Card -->
            <div class="card-cms mb-4">
                <div class="card-cms-header">
                    <h3 class="card-cms-title">
                        <i class="bi bi-calculator text-primary"></i>
                        <span>Estimated Cost Summary</span>
                    </h3>
                </div>
                <div class="card-cms-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span class="text-muted">Estimated Subtotal:</span>
                        <span class="font-monospace fw-semibold fs-6" id="summarySubtotal"><?= formatCurrency($pr['estimated_subtotal']) ?></span>
                    </div>

                    <div class="mb-3 pt-2 border-top">
                        <label for="estimated_tax" class="form-label-cms small d-flex justify-content-between">
                            <span>Estimated Tax ($)</span>
                            <span class="text-muted small">Optional</span>
                        </label>
                        <input type="number" class="form-control form-control-cms font-monospace text-end" id="estimated_tax" name="estimated_tax" 
                               value="<?= number_format((float)$pr['estimated_tax'], 2, '.', '') ?>" min="0" step="0.01">
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top mb-4">
                        <span class="fw-bold text-dark fs-6">Estimated Total:</span>
                        <span class="font-monospace fw-bold text-primary fs-5" id="summaryGrandTotal"><?= formatCurrency($pr['estimated_total']) ?></span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <button type="submit" name="submit_action" value="draft" class="btn btn-outline-secondary">
                            <i class="bi bi-save me-1"></i> Update Draft
                        </button>
                        <button type="submit" name="submit_action" value="submit" class="btn btn-primary-cms">
                            <i class="bi bi-send me-1"></i> Update &amp; Submit for Approval
                        </button>
                        <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="btn btn-light btn-sm text-muted mt-1">
                            Cancel Changes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Vanilla JS for Row Calculations -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = <?= count($items) + 10 ?>;
    const tableBody = document.getElementById('itemsTableBody');
    const addRowBtn = document.getElementById('addItemRowBtn');
    const taxInput = document.getElementById('estimated_tax');
    const subtotalDisplay = document.getElementById('summarySubtotal');
    const grandTotalDisplay = document.getElementById('summaryGrandTotal');

    function calculateTotals() {
        let subtotal = 0;
        const rows = tableBody.querySelectorAll('.item-row');

        rows.forEach(function (row) {
            const qtyInput = row.querySelector('.item-qty');
            const priceInput = row.querySelector('.item-price');
            const lineTotalDisplay = row.querySelector('.item-line-total');

            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const lineTotal = qty * price;

            lineTotalDisplay.textContent = '$' + lineTotal.toFixed(2);
            subtotal += lineTotal;
        });

        subtotalDisplay.textContent = '$' + subtotal.toFixed(2);

        const tax = parseFloat(taxInput.value) || 0;
        const grandTotal = subtotal + tax;
        grandTotalDisplay.textContent = '$' + grandTotal.toFixed(2);

        const removeButtons = tableBody.querySelectorAll('.remove-row-btn');
        removeButtons.forEach(function (btn) {
            btn.disabled = (rows.length <= 1);
        });
    }

    addRowBtn.addEventListener('click', function () {
        const newRow = document.createElement('tr');
        newRow.className = 'item-row';
        newRow.innerHTML = `
            <td>
                <input type="text" class="form-control form-control-sm item-name mb-1" name="items[${rowIndex}][name]" 
                       placeholder="Item name / specification" required>
                <input type="text" class="form-control form-control-sm text-muted item-desc" name="items[${rowIndex}][description]" 
                       placeholder="Specifications / details (optional)">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-qty text-end" name="items[${rowIndex}][quantity]" 
                       value="1" min="0.01" step="any" required>
            </td>
            <td>
                <select class="form-select form-select-sm item-unit" name="items[${rowIndex}][unit]" required>
                    <option value="Pcs" selected>Pieces (Pcs)</option>
                    <option value="Unit">Units</option>
                    <option value="Box">Boxes</option>
                    <option value="Set">Sets</option>
                    <option value="Pack">Packs</option>
                    <option value="Kg">Kilograms (Kg)</option>
                    <option value="Meter">Meters</option>
                    <option value="Liter">Liters</option>
                    <option value="Roll">Rolls</option>
                    <option value="Lot">Lot</option>
                </select>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-price text-end font-monospace" name="items[${rowIndex}][unit_price]" 
                       value="0.00" min="0" step="0.01" required>
            </td>
            <td class="text-end font-monospace fw-semibold item-line-total text-dark">
                $0.00
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm border-0 remove-row-btn" title="Remove Item">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tableBody.appendChild(newRow);
        rowIndex++;
        calculateTotals();
    });

    tableBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) {
            calculateTotals();
        }
    });

    tableBody.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.remove-row-btn');
        if (removeBtn && !removeBtn.disabled) {
            const row = removeBtn.closest('.item-row');
            if (tableBody.querySelectorAll('.item-row').length > 1) {
                row.remove();
                calculateTotals();
            }
        }
    });

    taxInput.addEventListener('input', calculateTotals);
    calculateTotals();
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
