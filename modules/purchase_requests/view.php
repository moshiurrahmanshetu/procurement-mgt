<?php
/**
 * View Purchase Request Details
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
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewAll = $isAdmin || $isManager || $isOfficer;

// 1. Fetch Purchase Request Record
$pr = null;
try {
    $stmt = $db->prepare("
        SELECT pr.*, 
               d.name AS department_name, d.code AS department_code,
               u.full_name AS requester_name, u.username AS requester_username, u.email AS requester_email, u.avatar AS requester_avatar,
               app.full_name AS approver_name, app.username AS approver_username
        FROM purchase_requests pr
        LEFT JOIN departments d ON pr.department_id = d.id
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN users app ON pr.approved_by = app.id
        WHERE pr.id = :id AND pr.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $pr = $stmt->fetch();
} catch (Exception $e) {
    error_log('Fetch PR Detail Error: ' . $e->getMessage());
}

if (!$pr) {
    setFlash('error', 'Purchase request not found or has been deleted.');
    redirect('modules/purchase_requests/index.php');
}

// 2. Authorization Check (Requesters only see own requests)
$isOwner = ((int)$pr['requested_by'] === $userId);
if (!$canViewAll && !$isOwner) {
    setFlash('error', 'Access Denied: You do not have permission to view this requisition.');
    redirect('modules/purchase_requests/index.php');
}

// 3. Fetch Line Items
$items = [];
try {
    $itemStmt = $db->prepare("SELECT * FROM purchase_request_items WHERE purchase_request_id = :pr_id ORDER BY id ASC");
    $itemStmt->execute([':pr_id' => $id]);
    $items = $itemStmt->fetchAll();
} catch (Exception $e) {
    error_log('Fetch PR Items Error: ' . $e->getMessage());
}

// 4. Fetch Workflow History
$history = [];
try {
    $histStmt = $db->prepare("
        SELECT h.*, u.full_name as user_name, u.username 
        FROM purchase_request_history h
        LEFT JOIN users u ON h.user_id = u.id
        WHERE h.purchase_request_id = :pr_id
        ORDER BY h.id ASC
    ");
    $histStmt->execute([':pr_id' => $id]);
    $history = $histStmt->fetchAll();
} catch (Exception $e) {
    error_log('Fetch PR History Error: ' . $e->getMessage());
}

$pageTitle = 'Request ' . $pr['request_no'];
$pageSubtitle = 'Requisition Details & Approval Workflow';
$activeNav = 'purchase_requests';

// Permission Flags for View Actions
$canEdit = ($pr['status'] === 'draft' && ($isOwner || $isAdmin));
$canSubmit = ($pr['status'] === 'draft' && ($isOwner || $isAdmin));
$canApproveReject = ($pr['status'] === 'pending_approval' && ($isManager || $isAdmin));
$canCancel = (in_array($pr['status'], ['draft', 'pending_approval']) && ($isOwner || $isAdmin));
$canDelete = ($pr['status'] === 'draft' && ($isOwner || $isAdmin));

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Rejection Reason Alert if Rejected -->
<?php if ($pr['status'] === 'rejected'): ?>
    <div class="alert alert-danger shadow-sm mb-4 border-danger">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-octagon-fill fs-4 text-danger flex-shrink-0 mt-1"></i>
            <div>
                <h6 class="fw-bold mb-1">Requisition Rejected</h6>
                <p class="mb-1 small">
                    <strong>Reason for rejection:</strong> <?= nl2br(e($pr['rejection_reason'])) ?>
                </p>
                <?php if (!empty($pr['approver_name'])): ?>
                    <div class="small text-muted">
                        Rejected by <?= e($pr['approver_name']) ?> on <?= formatDate($pr['updated_at']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Top Details Header Card -->
<div class="card-cms mb-4">
    <div class="card-cms-header flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <h3 class="card-cms-title font-monospace fs-5">
                <i class="bi bi-file-earmark-text text-primary"></i>
                <span><?= e($pr['request_no']) ?></span>
            </h3>
            <?= getStatusBadge($pr['status']) ?>
            <?= getPriorityBadge($pr['priority']) ?>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if ($canEdit): ?>
                <a href="<?= url('modules/purchase_requests/edit.php?id=' . $pr['id']) ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Draft
                </a>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#submitModal">
                    <i class="bi bi-send me-1"></i> Submit for Approval
                </button>
            <?php endif; ?>

            <?php if ($canApproveReject): ?>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="bi bi-check-lg me-1"></i> Approve
                </button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-lg me-1"></i> Reject
                </button>
            <?php endif; ?>

            <?php if ($canCancel): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="bi bi-slash-circle me-1"></i> Cancel Request
                </button>
            <?php endif; ?>

            <?php if ($canDelete): ?>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            <?php endif; ?>

            <a href="<?= url('modules/purchase_requests/index.php') ?>" class="btn btn-light btn-sm text-muted">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Overview Metadata Grid -->
    <div class="card-cms-body">
        <div class="row g-4">
            <!-- Requester & Department Info -->
            <div class="col-md-6 col-lg-3">
                <div class="text-muted small mb-1">Requested By</div>
                <div class="d-flex align-items-center gap-2">
                    <?= renderAvatar(['avatar' => $pr['requester_avatar'], 'full_name' => $pr['requester_name']], 36) ?>
                    <div>
                        <div class="fw-bold small text-dark"><?= e($pr['requester_name']) ?></div>
                        <div class="text-muted small font-monospace">@<?= e($pr['requester_username']) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="text-muted small mb-1">Department</div>
                <div class="fw-semibold text-dark">
                    <span class="badge bg-light text-dark border me-1"><?= e($pr['department_code']) ?></span>
                    <?= e($pr['department_name']) ?>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="text-muted small mb-1">Request Date</div>
                <div class="fw-semibold text-dark">
                    <i class="bi bi-calendar-event me-1 text-primary"></i> <?= formatDate($pr['request_date'], 'd M Y') ?>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="text-muted small mb-1">Required By Date</div>
                <div class="fw-semibold text-dark">
                    <?php if (!empty($pr['required_date'])): ?>
                        <i class="bi bi-calendar-check me-1 text-primary"></i> <?= formatDate($pr['required_date'], 'd M Y') ?>
                    <?php else: ?>
                        <span class="text-muted">Not specified</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <hr class="my-3 text-muted opacity-25">

        <!-- Purpose & Notes -->
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="text-muted small mb-1">Purpose / Business Justification</div>
                <div class="p-3 bg-light rounded-3 small text-dark">
                    <?= nl2br(e($pr['purpose'])) ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="text-muted small mb-1">Additional Notes / Specifications</div>
                <div class="p-3 bg-light rounded-3 small text-muted">
                    <?= !empty($pr['notes']) ? nl2br(e($pr['notes'])) : '<span class="fst-italic">No additional notes provided.</span>' ?>
                </div>
            </div>
        </div>

        <?php if (!empty($pr['approved_at']) && !empty($pr['approver_name'])): ?>
            <div class="mt-3 p-3 bg-success bg-opacity-10 border border-success-subtle rounded-3 d-flex align-items-center justify-content-between small">
                <div>
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    Approved by <strong><?= e($pr['approver_name']) ?></strong> on <strong><?= formatDate($pr['approved_at']) ?></strong>
                </div>
                <span class="badge bg-success">Authorized</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main Body: Requisition Items & Financial Summary -->
<div class="row g-4">
    <!-- Left Column: Items Table -->
    <div class="col-lg-8">
        <div class="card-cms">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-cart3 text-primary"></i>
                    <span>Requisition Line Items (<?= count($items) ?>)</span>
                </h3>
            </div>
            <div class="card-cms-body p-0">
                <div class="table-responsive">
                    <table class="table table-cms align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Item Details</th>
                                <th class="text-end">Quantity</th>
                                <th>Unit</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $idx => $item): ?>
                                <tr>
                                    <td class="text-muted small font-monospace"><?= $idx + 1 ?></td>
                                    <td>
                                        <div class="fw-bold text-dark small"><?= e($item['item_name']) ?></div>
                                        <?php if (!empty($item['description'])): ?>
                                            <div class="text-muted small"><?= e($item['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end font-monospace small">
                                        <?= (float)$item['quantity'] ?>
                                    </td>
                                    <td class="small">
                                        <span class="badge bg-light text-dark border fw-normal"><?= e($item['unit']) ?></span>
                                    </td>
                                    <td class="text-end font-monospace small text-muted">
                                        <?= formatCurrency($item['estimated_unit_price']) ?>
                                    </td>
                                    <td class="text-end font-monospace fw-semibold small text-dark">
                                        <?= formatCurrency($item['estimated_total']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-light">
                                <td colspan="5" class="text-end fw-semibold small">Estimated Subtotal:</td>
                                <td class="text-end font-monospace fw-bold small text-dark"><?= formatCurrency($pr['estimated_subtotal']) ?></td>
                            </tr>
                            <tr class="bg-light">
                                <td colspan="5" class="text-end fw-semibold small">Estimated Tax:</td>
                                <td class="text-end font-monospace small text-muted"><?= formatCurrency($pr['estimated_tax']) ?></td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="5" class="text-end fw-bold text-dark">Estimated Grand Total:</td>
                                <td class="text-end font-monospace fw-bold text-primary fs-6"><?= formatCurrency($pr['estimated_total']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Workflow History Timeline -->
    <div class="col-lg-4">
        <div class="card-cms">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span>Workflow Audit Trail</span>
                </h3>
            </div>
            <div class="card-cms-body">
                <?php if (!empty($history)): ?>
                    <div class="timeline position-relative ps-3" style="border-left: 2px solid var(--border-color); margin-left: 0.5rem;">
                        <?php foreach ($history as $h): ?>
                            <div class="timeline-item position-relative mb-3 pb-2">
                                <span class="position-absolute translate-middle bg-primary rounded-circle" style="left: -13px; top: 8px; width: 10px; height: 10px; border: 2px solid #ffffff;"></span>
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <span class="fw-bold small text-dark"><?= e($h['action']) ?></span>
                                    <span class="text-muted" style="font-size: 0.6875rem;"><?= formatDate($h['created_at'], 'd M, h:i A') ?></span>
                                </div>
                                <div class="small text-muted mb-1">
                                    By <strong class="text-dark"><?= e($h['user_name'] ?? 'System') ?></strong>
                                    <?php if (!empty($h['new_status'])): ?>
                                        &bull; Status: <span class="badge bg-light text-dark border" style="font-size: 0.6875rem;"><?= e(ucfirst(str_replace('_', ' ', $h['new_status']))) ?></span>
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
                    <p class="text-muted small mb-0">No history entries logged yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ACTION MODALS -->

<!-- 1. Submit Modal -->
<?php if ($canSubmit): ?>
    <div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_requests/submit.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold">Submit Purchase Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        Are you sure you want to submit requisition <strong><?= e($pr['request_no']) ?></strong> for approval?
                        Once submitted, line items and requisition details can no longer be edited.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-send me-1"></i> Confirm &amp; Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 2. Approve Modal -->
<?php if ($canApproveReject): ?>
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_requests/approve.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-success">
                            <i class="bi bi-check-circle-fill me-1"></i> Approve Purchase Request
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p>You are approving purchase request <strong><?= e($pr['request_no']) ?></strong> with an estimated total of <strong><?= formatCurrency($pr['estimated_total']) ?></strong>.</p>
                        <div class="mb-2">
                            <label for="approve_comments" class="form-label-cms small">Approval Notes / Instructions (Optional)</label>
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

    <!-- 3. Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_requests/reject.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-danger">
                            <i class="bi bi-x-circle-fill me-1"></i> Reject Purchase Request
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p>Please enter the mandatory reason for rejecting requisition <strong><?= e($pr['request_no']) ?></strong>:</p>
                        <div class="mb-2">
                            <label for="rejection_reason_view" class="form-label-cms small">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="rejection_reason_view" name="rejection_reason" rows="3" placeholder="State why this requisition is rejected..." required></textarea>
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

<!-- 4. Cancel Modal -->
<?php if ($canCancel): ?>
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_requests/cancel.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-secondary">Cancel Purchase Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        <p>Are you sure you want to cancel requisition <strong><?= e($pr['request_no']) ?></strong>?</p>
                        <div class="mb-2">
                            <label for="cancel_comments" class="form-label-cms small">Cancellation Reason (Optional)</label>
                            <textarea class="form-control form-control-sm" id="cancel_comments" name="comments" rows="2" placeholder="Explain why this request is cancelled..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-dark btn-sm">
                            <i class="bi bi-slash-circle me-1"></i> Cancel Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 5. Delete Modal -->
<?php if ($canDelete): ?>
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= url('modules/purchase_requests/delete.php') ?>" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6 fw-bold text-danger">Delete Draft Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body small">
                        Are you sure you want to permanently delete draft request <strong><?= e($pr['request_no']) ?></strong>?
                        This action cannot be undone.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash me-1"></i> Permanently Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
