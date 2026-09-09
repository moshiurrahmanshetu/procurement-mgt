<?php
/**
 * Purchase Order History Audit Trail
 * Procurement Management CMS - Phase 04
 */

$pageTitle = 'PO Audit History';
$pageSubtitle = 'Chronological Lifecycle & Approvals Trail';
$activeNav = 'purchase_orders';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$poId = (int)($_GET['id'] ?? 0);
if ($poId <= 0) {
    setFlash('error', 'Invalid Purchase Order ID.');
    redirect('modules/purchase_orders/index.php');
}

$db = getDb();

// 1. Fetch PO details
$po = null;
try {
    $stmt = $db->prepare("
        SELECT po.*, s.name AS supplier_name, s.supplier_code
        FROM purchase_orders po
        JOIN suppliers s ON s.id = po.supplier_id
        WHERE po.id = :id AND po.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $poId]);
    $po = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching PO for history: ' . $e->getMessage());
}

if (!$po) {
    setFlash('error', 'Purchase Order not found.');
    redirect('modules/purchase_orders/index.php');
}

// 2. Fetch history records
$history = [];
try {
    $hStmt = $db->prepare("
        SELECT poh.*, u.full_name, u.username, u.email
        FROM purchase_order_history poh
        LEFT JOIN users u ON u.id = poh.user_id
        WHERE poh.purchase_order_id = :id
        ORDER BY poh.created_at DESC, poh.id DESC
    ");
    $hStmt->execute([':id' => $poId]);
    $history = $hStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching PO history list: ' . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Audit Trail</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Lifecycle Audit Trail: <span class="font-monospace text-primary"><?= e($po['po_no']) ?></span></h1>
            <p class="text-muted small mb-0">Complete chronological audit record of all state transitions, approvals, dispatch, and revisions.</p>
        </div>
        <div>
            <a href="<?= url('modules/purchase_orders/view.php?id=' . $po['id']) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Order</span>
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-primary"></i> Detailed Event History (<?= count($history) ?> events)
                    </h6>
                    <?= getPoStatusBadge($po['status']) ?>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($history)): ?>
                        <div class="text-center py-4 text-muted">No history records logged.</div>
                    <?php else: ?>
                        <div class="timeline position-relative ps-4" style="border-left: 2px solid #dee2e6; margin-left: 1rem;">
                            <?php foreach ($history as $h): ?>
                                <div class="timeline-item position-relative mb-4 pb-2">
                                    <span class="position-absolute translate-middle bg-primary rounded-circle" style="left: -17px; top: 10px; width: 12px; height: 12px; border: 3px solid #ffffff;"></span>
                                    <div class="card border border-light-subtle shadow-xs">
                                        <div class="card-body p-3">
                                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2 gap-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-primary text-white fw-bold text-uppercase" style="font-size: 0.75rem;"><?= e($h['action']) ?></span>
                                                    <?php if (!empty($h['old_status']) && !empty($h['new_status'])): ?>
                                                        <span class="small text-muted font-monospace">
                                                            <?= e($h['old_status']) ?> &rarr; <strong class="text-dark"><?= e($h['new_status']) ?></strong>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="text-muted small font-monospace">
                                                    <i class="bi bi-calendar3 me-1"></i><?= formatDate($h['created_at'], 'd M Y, h:i:s A') ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted mb-2">
                                                Triggered by: <strong class="text-dark"><?= e($h['full_name'] ?? 'System User') ?></strong> (<?= e($h['username'] ?? 'N/A') ?>)
                                            </div>
                                            <?php if (!empty($h['comments'])): ?>
                                                <div class="p-3 bg-light rounded text-dark small">
                                                    <?= nl2br(e($h['comments'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
