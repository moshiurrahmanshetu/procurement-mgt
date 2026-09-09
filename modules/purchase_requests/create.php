<?php
/**
 * Create Purchase Request Form
 * Procurement Management CMS - Phase 02
 */

$pageTitle = 'Create Purchase Request';
$pageSubtitle = 'Initiate a new procurement requisition';
$activeNav = 'purchase_requests';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

// Fetch active departments
$departments = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM departments WHERE deleted_at IS NULL AND status = 'active' ORDER BY name ASC");
    $departments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching departments: ' . $e->getMessage());
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<form action="<?= url('modules/purchase_requests/store.php') ?>" method="POST" id="prCreateForm">
    <?= csrfField() ?>

    <div class="row g-4">
        <!-- Main Form Details -->
        <div class="col-lg-8">
            <!-- Header Information Card -->
            <div class="card-cms mb-4">
                <div class="card-cms-header">
                    <h3 class="card-cms-title">
                        <i class="bi bi-file-earmark-text text-primary"></i>
                        <span>Requisition Details</span>
                    </h3>
                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 small">New Draft</span>
                </div>
                <div class="card-cms-body">
                    <div class="row g-3">
                        <!-- Department -->
                        <div class="col-md-6">
                            <label for="department_id" class="form-label-cms">Department <span class="text-danger">*</span></label>
                            <select class="form-select form-select-cms" id="department_id" name="department_id" required>
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>">
                                        <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Priority -->
                        <div class="col-md-6">
                            <label for="priority" class="form-label-cms">Priority Level <span class="text-danger">*</span></label>
                            <select class="form-select form-select-cms" id="priority" name="priority" required>
                                <option value="low">Low - Routine Stock</option>
                                <option value="medium" selected>Medium - Normal Business</option>
                                <option value="high">High - Time Sensitive</option>
                                <option value="urgent">Urgent - Critical Requirement</option>
                            </select>
                        </div>

                        <!-- Request Date -->
                        <div class="col-md-6">
                            <label for="request_date" class="form-label-cms">Request Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-cms" id="request_date" name="request_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <!-- Required Date -->
                        <div class="col-md-6">
                            <label for="required_date" class="form-label-cms">Required By Date</label>
                            <input type="date" class="form-control form-control-cms" id="required_date" name="required_date" 
                                   min="<?= date('Y-m-d') ?>">
                            <div class="form-text small">Target date needed by department.</div>
                        </div>

                        <!-- Purpose -->
                        <div class="col-12">
                            <label for="purpose" class="form-label-cms">Purpose / Justification <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-cms" id="purpose" name="purpose" rows="3" 
                                      placeholder="Explain the business need for this purchase requisition..." required></textarea>
                        </div>

                        <!-- Additional Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label-cms">Additional Notes / Special Instructions</label>
                            <textarea class="form-control form-control-cms" id="notes" name="notes" rows="2" 
                                      placeholder="Optional vendor preferences, delivery notes, or specifications..."></textarea>
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
                                <!-- Initial Item Row -->
                                <tr class="item-row">
                                    <td>
                                        <input type="text" class="form-control form-control-sm item-name mb-1" name="items[0][name]" 
                                               placeholder="e.g. Dell Monitor 27 inch" required>
                                        <input type="text" class="form-control form-control-sm text-muted item-desc" name="items[0][description]" 
                                               placeholder="Specifications / details (optional)">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm item-qty text-end" name="items[0][quantity]" 
                                               value="1" min="0.01" step="any" required>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm item-unit" name="items[0][unit]" required>
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
                                        <input type="number" class="form-control form-control-sm item-price text-end font-monospace" name="items[0][unit_price]" 
                                               value="0.00" min="0" step="0.01" required>
                                    </td>
                                    <td class="text-end font-monospace fw-semibold item-line-total text-dark">
                                        $0.00
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm border-0 remove-row-btn" title="Remove Item" disabled>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
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
                        <span class="font-monospace fw-semibold fs-6" id="summarySubtotal">$0.00</span>
                    </div>

                    <div class="mb-3 pt-2 border-top">
                        <label for="estimated_tax" class="form-label-cms small d-flex justify-content-between">
                            <span>Estimated Tax ($)</span>
                            <span class="text-muted small">Optional</span>
                        </label>
                        <input type="number" class="form-control form-control-cms font-monospace text-end" id="estimated_tax" name="estimated_tax" 
                               value="0.00" min="0" step="0.01">
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top mb-4">
                        <span class="fw-bold text-dark fs-6">Estimated Total:</span>
                        <span class="font-monospace fw-bold text-primary fs-5" id="summaryGrandTotal">$0.00</span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <button type="submit" name="submit_action" value="draft" class="btn btn-outline-secondary">
                            <i class="bi bi-save me-1"></i> Save as Draft
                        </button>
                        <button type="submit" name="submit_action" value="submit" class="btn btn-primary-cms">
                            <i class="bi bi-send me-1"></i> Save &amp; Submit for Approval
                        </button>
                        <a href="<?= url('modules/purchase_requests/index.php') ?>" class="btn btn-light btn-sm text-muted mt-1">
                            Cancel &amp; Go Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Helpful Workflow Guidelines Card -->
            <div class="card-cms">
                <div class="card-cms-header">
                    <h3 class="card-cms-title">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Workflow Overview</span>
                    </h3>
                </div>
                <div class="card-cms-body small text-muted">
                    <ol class="ps-3 mb-0">
                        <li class="mb-1"><strong>Draft:</strong> Save your requisition to make revisions later.</li>
                        <li class="mb-1"><strong>Submit:</strong> Sends the request directly to Department Managers for approval.</li>
                        <li class="mb-1"><strong>Recalculation:</strong> Line item prices and totals are verified and recalculated server-side.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Vanilla JavaScript for Multi-Item Dynamic Rows & Live Calculations -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = 1;
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

        // Update remove button state (disable if only 1 row)
        const removeButtons = tableBody.querySelectorAll('.remove-row-btn');
        removeButtons.forEach(function (btn) {
            btn.disabled = (rows.length <= 1);
        });
    }

    // Add New Item Row
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

    // Event Delegation for Qty/Price changes and row removal
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

    // Initial calculation
    calculateTotals();
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
