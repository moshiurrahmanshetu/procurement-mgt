<?php
/**
 * Quotation Audit History View
 * Procurement Management CMS - Phase 03
 */

$pageTitle = 'Quotation History';
$pageSubtitle = 'Audit Trail & Workflow State Transitions';
$activeNav = 'quotations';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

$quotationId = (int)($_GET['id'] ?? 0);
if ($quotationId <= 0) {
    $_SESSION['flash_error'] = 'Invalid quotation ID.';
    redirect('modules/quotations/index.php');
}

$quotation = null;
try {
    $stmt = $db->prepare("
        SELECT q.*, pr.request_no, s.name AS supplier_name, s.supplier_code
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE q.id = :id AND q.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $quotationId]);
    $quotation = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching quotation for history: ' . $e->getMessage());
}

if (!$quotation) {
    $_SESSION['flash_error'] = 'Quotation not found or has been deleted.';
    redirect('modules/quotations/index.php');
}

$history = [];
try {
    $hStmt = $db->prepare("
        SELECT qh.*, u.full_name, u.username
        FROM quotation_history qh
        LEFT JOIN users u ON u.id = qh.user_id
        WHERE qh.quotation_id = :id
        ORDER BY qh.created_at ASC, qh.id ASC
    ");
    $hStmt->execute([':id' => $quotationId]);
    $history = $hStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching history: ' . $e->getMessage());
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('modules/dashboard/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('modules/quotations/index.php') ?>" class="text-decoration-none">Quotations</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('modules/quotations/view.php?id=' . $quotation['id']) ?>" class="text-decoration-none"><?= e($quotation['quotation_no']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Audit History</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Audit History: <?= e($quotation['quotation_no']) ?></h1>
            <p class="text-muted small mb-0">Vendor: <strong><?= e($quotation['supplier_name']) ?></strong> &bull; PR: <strong><?= e($quotation['request_no']) ?></strong></p>
        </div>
        <div>
            <a href="<?= url('modules/quotations/view.php?id=' . $quotation['id']) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Quotation</span>
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-primary"></i> Complete Event History
            </h6>
        </div>
        <div class="card-body p-4">
            <?php if (empty($history)): ?>
                <div class="text-muted text-center py-4">No audit records found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3" style="width: 180px;">Timestamp</th>
                                <th style="width: 180px;">User</th>
                                <th style="width: 140px;">Action</th>
                                <th style="width: 220px;">State Transition</th>
                                <th class="pe-3">Comments / Audit Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td class="ps-3 small text-muted font-monospace">
                                        <?= formatDate($h['created_at']) ?>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= e($h['full_name'] ?? 'System Process') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border text-capitalize">
                                            <?= str_replace('_', ' ', e($h['action'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small d-flex align-items-center gap-1">
                                            <?= !empty($h['from_status']) ? getQuotationStatusBadge($h['from_status']) : '<span class="text-muted fst-italic">None</span>' ?>
                                            <i class="bi bi-arrow-right text-muted"></i>
                                            <?= !empty($h['to_status']) ? getQuotationStatusBadge($h['to_status']) : '<span class="text-muted fst-italic">None</span>' ?>
                                        </div>
                                    </td>
                                    <td class="pe-3 small text-dark">
                                        <?= e($h['comments'] ?: '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
