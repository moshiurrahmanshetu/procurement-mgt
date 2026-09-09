<?php
/**
 * Goods Receipt Audit History Log View
 * Procurement Management CMS - Phase 05
 */

$pageTitle = 'Goods Receipt History';
$pageSubtitle = 'Full Chronological Audit Trail & State Transitions';
$activeNav = 'goods_receiving';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewAll = $isAdmin || $isManager || $isOfficer;

$grnId = (int)($_GET['id'] ?? 0);
if ($grnId <= 0) {
    setFlash('error', 'Invalid Goods Receipt ID.');
    redirect('modules/goods_receiving/index.php');
}

// 1. Fetch GRN Record
$grn = null;
try {
    $stmt = $db->prepare("
        SELECT gr.*, po.po_no, s.name AS supplier_name, s.supplier_code, pr.requested_by AS pr_requested_by
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN suppliers s ON s.id = gr.supplier_id
        WHERE gr.id = :id AND gr.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $grnId]);
    $grn = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching GRN history header: ' . $e->getMessage());
}

if (!$grn) {
    setFlash('error', 'Goods Receipt not found or has been deleted.');
    redirect('modules/goods_receiving/index.php');
}

if (!$canViewAll && (int)$grn['pr_requested_by'] !== $userId) {
    setFlash('error', 'Access Denied.');
    redirect('modules/goods_receiving/index.php');
}

// 2. Fetch History Records
$history = [];
try {
    $hStmt = $db->prepare("
        SELECT grh.*, u.full_name, u.username, u.email
        FROM goods_receipt_history grh
        LEFT JOIN users u ON u.id = grh.user_id
        WHERE grh.goods_receipt_id = :grn_id
        ORDER BY grh.id DESC
    ");
    $hStmt->execute([':grn_id' => $grnId]);
    $history = $hStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching GRN history: ' . $e->getMessage());
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
                    <li class="breadcrumb-item"><a href="<?= url('modules/goods_receiving/view.php?id=' . $grn['id']) ?>" class="text-decoration-none font-monospace"><?= e($grn['grn_no']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Audit History</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 mb-0 text-gray-800 fw-bold font-monospace"><?= e($grn['grn_no']) ?> - Audit Trail</h1>
                <?= getGrnStatusBadge($grn['status']) ?>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Purchase Order: <strong class="text-dark font-monospace"><?= e($grn['po_no']) ?></strong> &bull;
                Vendor: <strong class="text-dark"><?= e($grn['supplier_name']) ?></strong>
            </p>
        </div>
        <div>
            <a href="<?= url('modules/goods_receiving/view.php?id=' . $grn['id']) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Goods Receipt</span>
            </a>
        </div>
    </div>

    <!-- History Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-primary"></i> Chronological State Transitions & Events
            </h6>
            <span class="badge bg-light text-secondary border"><?= count($history) ?> events</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 180px;">Timestamp</th>
                            <th style="width: 150px;">Action</th>
                            <th style="width: 180px;">User</th>
                            <th style="width: 150px;">Status Change</th>
                            <th class="pe-3">Comments / Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">No history records logged.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td class="ps-3 small text-muted font-monospace">
                                        <?= formatDate($h['created_at'], 'd M Y, h:i:s A') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">
                                            <?= e(ucfirst(str_replace('_', ' ', $h['action']))) ?>
                                        </span>
                                    </td>
                                    <td class="small">
                                        <div class="fw-semibold text-dark"><?= e($h['full_name'] ?? 'System') ?></div>
                                        <div class="text-muted" style="font-size: 0.6875rem;">@<?= e($h['username'] ?? 'system') ?></div>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($h['new_status'])): ?>
                                            <?= !empty($h['old_status']) ? '<span class="text-muted">' . e(ucfirst($h['old_status'])) . '</span> &rarr; ' : '' ?>
                                            <span class="fw-bold text-dark"><?= e(ucfirst($h['new_status'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3 small text-secondary">
                                        <?= !empty($h['comments']) ? nl2br(e($h['comments'])) : '<span class="fst-italic text-muted">-</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
