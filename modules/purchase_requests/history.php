<?php
/**
 * View Purchase Request Full History Timeline
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
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewAll = $isAdmin || $isManager || $isOfficer;

$db = getDb();

// 1. Fetch Request
$pr = null;
try {
    $stmt = $db->prepare("
        SELECT pr.*, d.name AS department_name, d.code AS department_code, u.full_name AS requester_name 
        FROM purchase_requests pr
        LEFT JOIN departments d ON pr.department_id = d.id
        LEFT JOIN users u ON pr.requested_by = u.id
        WHERE pr.id = :id AND pr.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $pr = $stmt->fetch();
} catch (Exception $e) {
    error_log('Fetch PR History Page Error: ' . $e->getMessage());
}

if (!$pr) {
    setFlash('error', 'Purchase request not found.');
    redirect('modules/purchase_requests/index.php');
}

// 2. Authorization Check
$isOwner = ((int)$pr['requested_by'] === $userId);
if (!$canViewAll && !$isOwner) {
    setFlash('error', 'Access Denied: You cannot view history for this requisition.');
    redirect('modules/purchase_requests/index.php');
}

// 3. Fetch History Entries
$history = [];
try {
    $histStmt = $db->prepare("
        SELECT h.*, u.full_name as user_name, u.username, u.avatar
        FROM purchase_request_history h
        LEFT JOIN users u ON h.user_id = u.id
        WHERE h.purchase_request_id = :pr_id
        ORDER BY h.id ASC
    ");
    $histStmt->execute([':pr_id' => $id]);
    $history = $histStmt->fetchAll();
} catch (Exception $e) {
    error_log('Fetch PR History Timeline Error: ' . $e->getMessage());
}

$pageTitle = 'Audit History: ' . $pr['request_no'];
$pageSubtitle = 'Chronological status transitions and audit log';
$activeNav = 'purchase_requests';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-cms mb-4">
            <div class="card-cms-header">
                <div class="d-flex align-items-center gap-2">
                    <h3 class="card-cms-title font-monospace">
                        <i class="bi bi-clock-history text-primary"></i>
                        <span><?= e($pr['request_no']) ?> Timeline</span>
                    </h3>
                    <?= getStatusBadge($pr['status']) ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Requisition
                    </a>
                </div>
            </div>
            <div class="card-cms-body">
                <div class="mb-4 pb-3 border-bottom small text-muted d-flex flex-wrap justify-content-between gap-2">
                    <div><strong>Department:</strong> <?= e($pr['department_name']) ?> (<?= e($pr['department_code']) ?>)</div>
                    <div><strong>Requested By:</strong> <?= e($pr['requester_name']) ?></div>
                    <div><strong>Estimated Total:</strong> <span class="font-monospace text-dark fw-bold"><?= formatCurrency($pr['estimated_total']) ?></span></div>
                </div>

                <?php if (!empty($history)): ?>
                    <div class="timeline position-relative ps-4" style="border-left: 2px solid var(--border-color); margin-left: 1rem;">
                        <?php foreach ($history as $h): ?>
                            <div class="timeline-item position-relative mb-4">
                                <span class="position-absolute translate-middle bg-primary rounded-circle" style="left: -17px; top: 12px; width: 14px; height: 14px; border: 3px solid #ffffff; box-shadow: 0 0 0 2px var(--primary);"></span>
                                <div class="card bg-light border-0 shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-bold text-dark mb-0 fs-6"><?= e($h['action']) ?></h6>
                                            <span class="text-muted small"><?= formatDate($h['created_at'], 'd M Y, h:i:s A') ?></span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mb-2 small text-muted">
                                            <?= renderAvatar(['avatar' => $h['avatar'] ?? null, 'full_name' => $h['user_name'] ?? 'System'], 24) ?>
                                            <span>User: <strong class="text-dark"><?= e($h['user_name'] ?? 'System') ?></strong></span>
                                            <?php if (!empty($h['old_status']) || !empty($h['new_status'])): ?>
                                                &bull; Transition: 
                                                <span class="badge bg-secondary-subtle text-dark border small"><?= e(ucfirst(str_replace('_', ' ', $h['old_status'] ?? 'none'))) ?></span>
                                                <i class="bi bi-arrow-right"></i>
                                                <span class="badge bg-primary-subtle text-primary border small"><?= e(ucfirst(str_replace('_', ' ', $h['new_status'] ?? 'none'))) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($h['comments'])): ?>
                                            <div class="p-2 bg-white rounded border small text-dark mt-2">
                                                <?= nl2br(e($h['comments'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted py-4 mb-0">No history events recorded.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
