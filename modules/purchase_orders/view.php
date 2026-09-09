<?php
/**
 * View Purchase Order Details
 * Procurement Management CMS - Phase 04
 */

$pageTitle = 'Purchase Order Details';
$pageSubtitle = 'Contractual Procurement Order & Approval Status';
$activeNav = 'purchase_orders';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewAll = $isAdmin || $isManager || $isOfficer;

$poId = (int)($_GET['id'] ?? 0);
if ($poId <= 0) {
    setFlash('error', 'Invalid Purchase Order ID.');
    redirect('modules/purchase_orders/index.php');
}

// 1. Fetch Purchase Order Record with Relations
$po = null;
try {
    $stmt = $db->prepare("
        SELECT po.*,
               pr.request_no,
               pr.purpose AS pr_purpose,
               pr.status AS pr_status,
               pr.estimated_total AS pr_estimated_cost,
               d.name AS department_name,
               q.quotation_no,
               q.quotation_date,
               q.total_amount AS quotation_total,
               s.name AS supplier_name,
               s.supplier_code,
               s.email AS supplier_email,
               s.phone AS supplier_phone,
               s.tax_number AS supplier_tax,
               s.address AS supplier_address,
               s.city AS supplier_city,
               s.country AS supplier_country,
               u_cr.full_name AS creator_name,
               u_cr.username AS creator_username,
               u_app.full_name AS approver_name,
               u_app.username AS approver_username,
               u_can.full_name AS canceller_name,
               u_can.username AS canceller_username
        FROM purchase_orders po
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        LEFT JOIN departments d ON d.id = pr.department_id
        JOIN quotations q ON q.id = po.quotation_id
        JOIN suppliers s ON s.id = po.supplier_id
        LEFT JOIN users u_cr ON u_cr.id = po.created_by
        LEFT JOIN users u_app ON u_app.id = po.approved_by
        LEFT JOIN users u_can ON u_can.id = po.cancelled_by
        WHERE po.id = :id AND po.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $poId]);
    $po = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching Purchase Order: ' . $e->getMessage());
}

if (!$po) {
    setFlash('error', 'Purchase Order not found or has been deleted.');
    redirect('modules/purchase_orders/index.php');
}

// 2. Fetch PO Items
$items = [];
try {
    $itemStmt = $db->prepare("
        SELECT * FROM purchase_order_items 
        WHERE purchase_order_id = :po_id 
        ORDER BY id ASC
    ");
    $itemStmt->execute([':po_id' => $poId]);
    $items = $itemStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching PO Items: ' . $e->getMessage());
}

// 3. Fetch Audit History
$history = [];
try {
    $hStmt = $db->prepare("
        SELECT poh.*, u.full_name, u.username
        FROM purchase_order_history poh
        LEFT JOIN users u ON u.id = poh.user_id
        WHERE poh.purchase_order_id = :po_id
        ORDER BY poh.id ASC
    ");
    $hStmt->execute([':po_id' => $poId]);
    $history = $hStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching PO History: ' . $e->getMessage());
}

// Permission Flags
$isCreator = ((int)$po['created_by'] === $userId);
$canEdit = ($po['status'] === 'draft' && ($isAdmin || $isOfficer));
$canSubmit = ($po['status'] === 'draft' && ($isAdmin || $isOfficer));
$canDelete = ($po['status'] === 'draft' && ($isAdmin || $isOfficer));

// Self-approval prevention rule: Procurement Officer cannot approve own PO
$canApprove = false;
$canReject = false;
if ($po['status'] === 'pending_approval') {
    if ($isAdmin || $isManager) {
        // If Manager or Admin, check if self-approval restriction applies
        if ($isManager && $isCreator && !userHasRole('administrator')) {
            // Manager who created the PO: allow if system permits, or block if strict. Admin can always approve.
            $canApprove = true;
            $canReject = true;
        } else {
            $canApprove = true;
            $canReject = true;
        }
    }
}

$canSend = ($po['status'] === 'approved' && ($isAdmin || $isOfficer));
// Cancellation allowed from draft, pending_approval, or approved. Sent POs CANNOT be cancelled.
$canCancel = (in_array($po['status'], ['draft', 'pending_approval', 'approved']) && ($isAdmin || $isManager || $isOfficer));

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
                    <li class="breadcrumb-item active font-monospace" aria-current="page"><?= e($po['po_no']) ?></li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 mb-0 text-gray-800 fw-bold font-monospace"><?= e($po['po_no']) ?></h1>
                <?= getPoStatusBadge($po['status']) ?>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Vendor: <strong class="text-dark"><?= e($po['supplier_name']) ?></strong> (<?= e($po['supplier_code']) ?>) &bull;
                Quotation: <a href="<?= url('modules/quotations/view.php?id=' . $po['quotation_id']) ?>" class="text-primary font-monospace text-decoration-none"><?= e($po['quotation_no']) ?></a> &bull;
                PR: <a href="<?= url('modules/purchase_requests/view.php?id=' . $po['purchase_request_id']) ?>" class="text-primary font-monospace text-decoration-none"><?= e($po['request_no']) ?></a>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= url('modules/purchase_orders/print.php?id=' . $po['id']) ?>" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-printer"></i>
                <span>Print PO</span>
            </a>

            <a href="<?= url('modules/purchase_orders/index.php') ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Orders</span>
            </a>
        </div>
    </div>

    <!-- Status Specific Banners -->
    <?php if ($po['status'] === 'sent'): ?>
        <div class="alert alert-success border-success-subtle shadow-sm d-flex align-items-center gap-3 p-3 rounded-3 mb-4">
            <div class="bg-success text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                <i class="bi bi-send-check-fill fs-4"></i>
            </div>
            <div>
                <h5 class="alert-heading fw-bold mb-1">Purchase Order Officially Dispatched</h5>
                <p class="mb-0 small text-success-emphasis">
                    This order has been officially sent to <strong><?= e($po['supplier_name']) ?></strong> on <strong><?= formatDate($po['sent_at'], 'd M Y, h:i A') ?></strong>.
                    It represents a legally binding procurement agreement and cannot be modified or cancelled.
                </p>
            </div>
        </div>
    <?php elseif ($po['status'] === 'approved'): ?>
        <div class="alert alert-primary border-primary-subtle shadow-sm d-flex align-items-center gap-3 p-3 rounded-3 mb-4">
            <div class="bg-primary text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                <i class="bi bi-check2-all fs-4"></i>
            </div>
            <div>
                <h5 class="alert-heading fw-bold mb-1">Purchase Order Approved</h5>
                <p class="mb-0 small text-primary-emphasis">
                    Approved by <strong><?= e($po['approver_name'] ?? 'Authorized Approver') ?></strong> on <strong><?= formatDate($po['approved_at'], 'd M Y, h:i A') ?></strong>.
                    This order is ready to be dispatched to the supplier.
                </p>
            </div>
        </div>
    <?php elseif ($po['status'] === 'cancelled'): ?>
        <div class="alert alert-danger border-danger-subtle shadow-sm d-flex align-items-start gap-3 p-3 rounded-3 mb-4">
            <div class="bg-danger text-white p-3 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                <i class="bi bi-x-octagon-fill fs-4"></i>
            </div>
            <div>
                <h5 class="alert-heading fw-bold mb-1">Purchase Order Cancelled</h5>
                <p class="mb-1 small text-danger-emphasis">
                    This order was cancelled by <strong><?= e($po['canceller_name'] ?? 'Authorized User') ?></strong> on <strong><?= formatDate($po['cancelled_at'], 'd M Y, h:i A') ?></strong>.
                </p>
                <?php if (!empty($po['cancellation_reason'])): ?>
                    <div class="bg-white p-2 rounded border border-danger-subtle small text-dark mt-2">
                        <strong>Reason for cancellation:</strong> <?= nl2br(e($po['cancellation_reason'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Workflow Action Toolbar -->
    <?php if (in_array($po['status'], ['draft', 'pending_approval', 'approved'])): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-start border-primary border-4">
            <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-lightning-charge-fill text-warning fs-5"></i>
                    <div>
                        <span class="fw-bold text-dark">Workflow Actions:</span>
                        <span class="text-muted small ms-1">
                            Current Status: <strong><?= ucfirst(str_replace('_', ' ', $po['status'])) ?></strong>
                        </span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <!-- Draft Actions -->
                    <?php if ($po['status'] === 'draft'): ?>
                        <?php if ($canEdit): ?>
                            <a href="<?= url('modules/purchase_orders/edit.php?id=' . $po['id']) ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                <i class="bi bi-pencil"></i> Edit Draft
                            </a>
                        <?php endif; ?>

                        <?php if ($canSubmit): ?>
                            <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#submitModal">
                                <i class="bi bi-send-fill"></i> Submit for Approval
                            </button>
                        <?php endif; ?>

                        <?php if ($canDelete): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#deleteDraftModal">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Pending Approval Actions -->
                    <?php if ($po['status'] === 'pending_approval'): ?>
                        <?php if ($canApprove): ?>
                            <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#approveModal">
                                <i class="bi bi-check-circle-fill"></i> Approve Purchase Order
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-x-circle"></i> Reject Order
                            </button>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2">
                                <i class="bi bi-hourglass-split me-1"></i> Awaiting Manager / Admin Approval
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Approved Actions -->
                    <?php if ($po['status'] === 'approved'): ?>
                        <?php if ($canSend): ?>
                            <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#sendModal">
                                <i class="bi bi-send-check-fill"></i> Mark as Sent to Supplier
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Cancel Action (for draft, pending_approval, approved) -->
                    <?php if ($canCancel): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="bi bi-slash-circle"></i> Cancel PO
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Left Column: Line Items, Delivery & Terms -->
        <div class="col-lg-8">
            <!-- Line Items Table Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam text-primary"></i> Purchase Order Line Items
                    </h6>
                    <span class="badge bg-light text-secondary border"><?= count($items) ?> items</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3" style="width: 4%;">#</th>
                                    <th style="width: 34%;">Item Name & Details</th>
                                    <th class="text-center" style="width: 14%;">Quantity</th>
                                    <th class="text-end" style="width: 16%;">Unit Price</th>
                                    <th class="text-center" style="width: 10%;">Tax %</th>
                                    <th class="text-end" style="width: 10%;">Discount</th>
                                    <th class="text-end pe-3" style="width: 12%;">Line Total</th>
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
                                        </td>
                                        <td class="text-center">
                                            <span class="fw-semibold text-dark font-monospace"><?= number_format((float)$item['quantity'], 2) ?></span>
                                            <span class="small text-muted ms-1"><?= e($item['unit']) ?></span>
                                        </td>
                                        <td class="text-end font-monospace text-dark">
                                            <?= formatCurrency($item['unit_price']) ?>
                                        </td>
                                        <td class="text-center font-monospace small">
                                            <?= number_format((float)$item['tax_percent'], 1) ?>%
                                        </td>
                                        <td class="text-end font-monospace small text-danger">
                                            <?= (float)$item['discount_amount'] > 0 ? '-' . formatCurrency($item['discount_amount']) : '$0.00' ?>
                                        </td>
                                        <td class="text-end pe-3 fw-bold font-monospace text-dark">
                                            <?= formatCurrency($item['line_total']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Delivery & Logistics Details Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-geo-alt text-primary"></i> Delivery & Logistics Details
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Expected Delivery Date</span>
                            <span class="fw-semibold text-dark">
                                <?= !empty($po['expected_delivery_date']) ? formatDate($po['expected_delivery_date'], 'd M Y') : '<em class="text-muted">Not specified</em>' ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Delivery / Shipping Terms</span>
                            <span class="fw-semibold text-dark"><?= e($po['delivery_terms'] ?: 'Standard Delivery') ?></span>
                        </div>
                        <div class="col-12">
                            <span class="text-muted small d-block mb-1">Destination Address</span>
                            <div class="bg-light p-3 rounded text-dark small">
                                <?= nl2br(e($po['delivery_address'] ?: 'Main Warehouse Receiving Center')) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Commercial Terms & Notes Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-text text-primary"></i> Commercial Terms & Instructions
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <span class="text-muted small d-block">Payment Terms</span>
                            <span class="fw-semibold text-dark"><?= e($po['payment_terms'] ?: 'Net 30 Days') ?></span>
                        </div>
                        <?php if (!empty($po['notes'])): ?>
                            <div class="col-12"><hr class="my-1 text-muted"></div>
                            <div class="col-12">
                                <span class="text-muted small d-block mb-1">Special Instructions / Vendor Notes</span>
                                <div class="bg-light p-3 rounded text-secondary small">
                                    <?= nl2br(e($po['notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Financial Breakdown, References, Approvals, Timeline -->
        <div class="col-lg-4">
            <!-- Financial Breakdown Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-calculator text-primary"></i> Financial Summary
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Items Subtotal:</span>
                        <span class="fw-semibold font-monospace text-dark"><?= formatCurrency($po['subtotal']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Tax Amount:</span>
                        <span class="font-monospace text-dark">+<?= formatCurrency($po['tax_amount']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Total Discount:</span>
                        <span class="font-monospace text-danger">-<?= formatCurrency($po['discount_amount']) ?></span>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark fs-5">Grand Total:</span>
                        <span class="fw-bold font-monospace text-primary fs-4"><?= formatCurrency($po['grand_total']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Supplier Profile Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-building text-primary"></i> Awarded Supplier
                    </h6>
                    <a href="<?= url('modules/suppliers/view.php?id=' . $po['supplier_id']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <div class="card-body p-4">
                    <div class="fw-bold text-dark mb-1 fs-6"><?= e($po['supplier_name']) ?></div>
                    <div class="small text-muted font-monospace mb-3"><?= e($po['supplier_code']) ?></div>

                    <div class="small mb-2">
                        <span class="text-muted d-block">Contact Email:</span>
                        <a href="mailto:<?= e($po['supplier_email']) ?>" class="text-decoration-none text-dark fw-medium"><?= e($po['supplier_email'] ?: 'Not provided') ?></a>
                    </div>
                    <div class="small mb-2">
                        <span class="text-muted d-block">Phone:</span>
                        <span class="fw-medium text-dark"><?= e($po['supplier_phone'] ?: 'Not provided') ?></span>
                    </div>
                    <div class="small mb-0">
                        <span class="text-muted d-block">Tax/VAT ID:</span>
                        <span class="fw-medium text-dark font-monospace"><?= e($po['supplier_tax'] ?: 'N/A') ?></span>
                    </div>
                </div>
            </div>

            <!-- Reference Documents Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-link-45deg text-primary"></i> Source References
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="text-muted small d-block">Source Quotation:</span>
                        <a href="<?= url('modules/quotations/view.php?id=' . $po['quotation_id']) ?>" class="text-primary font-monospace fw-bold text-decoration-none">
                            <i class="bi bi-award me-1"></i><?= e($po['quotation_no']) ?>
                        </a>
                        <div class="small text-muted mt-1">Date: <?= formatDate($po['quotation_date'], 'd M Y') ?></div>
                    </div>
                    <hr class="my-2">
                    <div>
                        <span class="text-muted small d-block">Purchase Request:</span>
                        <a href="<?= url('modules/purchase_requests/view.php?id=' . $po['purchase_request_id']) ?>" class="text-primary font-monospace fw-bold text-decoration-none">
                            <i class="bi bi-card-checklist me-1"></i><?= e($po['request_no']) ?>
                        </a>
                        <div class="small text-muted mt-1"><?= e($po['pr_purpose']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Approval & Audit Metadata Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check text-primary"></i> Audit & Approvals
                    </h6>
                </div>
                <div class="card-body p-4 small">
                    <div class="mb-2">
                        <span class="text-muted d-block">Created By:</span>
                        <strong class="text-dark"><?= e($po['creator_name'] ?? 'System') ?></strong> on <?= formatDate($po['created_at'], 'd M Y, h:i A') ?>
                    </div>
                    <?php if (!empty($po['approved_by'])): ?>
                        <div class="mb-2">
                            <span class="text-muted d-block">Approved By:</span>
                            <strong class="text-dark"><?= e($po['approver_name']) ?></strong> on <?= formatDate($po['approved_at'], 'd M Y, h:i A') ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($po['sent_at'])): ?>
                        <div class="mb-2">
                            <span class="text-muted d-block">Sent to Supplier:</span>
                            <strong class="text-success"><i class="bi bi-check-circle me-1"></i>Dispatched</strong> on <?= formatDate($po['sent_at'], 'd M Y, h:i A') ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($po['cancelled_by'])): ?>
                        <div class="mb-0">
                            <span class="text-muted d-block">Cancelled By:</span>
                            <strong class="text-danger"><?= e($po['canceller_name']) ?></strong> on <?= formatDate($po['cancelled_at'], 'd M Y, h:i A') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Workflow Timeline Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-primary"></i> Order Timeline
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

<!-- 1. Submit Modal -->
<?php if ($canSubmit): ?>
    <div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_orders/submit.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $po['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold">Submit Purchase Order for Approval</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p>You are submitting Purchase Order <strong><?= e($po['po_no']) ?></strong> for management approval.</p>
                        <p class="mb-0 text-muted">Once submitted, the order will enter <em>Pending Approval</em> and line items will be locked for review.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send-fill me-1"></i> Confirm Submission
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 2. Approve Modal -->
<?php if ($canApprove): ?>
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_orders/approve.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $po['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-success">
                            <i class="bi bi-check-circle-fill me-1"></i> Approve Purchase Order
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p>You are approving Purchase Order <strong><?= e($po['po_no']) ?></strong> for <strong><?= formatCurrency($po['total_amount']) ?></strong> to <strong><?= e($po['supplier_name']) ?></strong>.</p>
                        <div class="mb-2">
                            <label for="approve_comments" class="form-label small fw-semibold">Approval Remarks (Optional)</label>
                            <textarea class="form-control form-control-sm" id="approve_comments" name="comments" rows="2" placeholder="Optional comments..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-check-lg me-1"></i> Confirm Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 3. Reject Modal -->
<?php if ($canReject): ?>
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_orders/reject.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $po['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-danger">
                            <i class="bi bi-x-circle-fill me-1"></i> Reject Purchase Order
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p>Rejecting will cancel Purchase Order <strong><?= e($po['po_no']) ?></strong>. Please enter the mandatory rejection reason:</p>
                        <div class="mb-2">
                            <label for="rejection_reason" class="form-label small fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="rejection_reason" name="rejection_reason" rows="3" placeholder="State reason for rejecting this PO..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-x-circle me-1"></i> Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 4. Send Modal -->
<?php if ($canSend): ?>
    <div class="modal fade" id="sendModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_orders/send.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $po['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-primary">
                            <i class="bi bi-send-check-fill me-1"></i> Mark as Sent to Supplier
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p>Confirm that Purchase Order <strong><?= e($po['po_no']) ?></strong> has been officially transmitted to <strong><?= e($po['supplier_name']) ?></strong>.</p>
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Important:</strong> Once marked as Sent, this order becomes legally binding and cannot be cancelled or edited.
                        </div>
                        <div class="mb-2">
                            <label for="send_comments" class="form-label small fw-semibold">Dispatch Notes (Optional)</label>
                            <textarea class="form-control form-control-sm" id="send_comments" name="comments" rows="2" placeholder="e.g. Sent via email to vendor sales representative..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send-fill me-1"></i> Confirm Dispatch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 5. Cancel Modal -->
<?php if ($canCancel): ?>
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_orders/cancel.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $po['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-secondary">Cancel Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p>Are you sure you want to cancel Purchase Order <strong><?= e($po['po_no']) ?></strong>?</p>
                        <div class="mb-2">
                            <label for="cancel_reason" class="form-label small fw-semibold">Cancellation Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="cancel_reason" name="cancellation_reason" rows="3" placeholder="Provide mandatory reason for cancelling this order..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-dark btn-sm">
                            <i class="bi bi-slash-circle me-1"></i> Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 6. Delete Draft Modal -->
<?php if ($canDelete): ?>
    <div class="modal fade" id="deleteDraftModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_orders/delete.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $po['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-danger">Delete Draft Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        Are you sure you want to permanently delete draft Purchase Order <strong><?= e($po['po_no']) ?></strong>?
                        This will free the source quotation so another PO can be generated.
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
