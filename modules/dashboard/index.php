<?php
/**
 * Application Dashboard
 * Procurement Management CMS - Complete Final Phase 06
 */

$pageTitle = 'Executive Dashboard';
$pageSubtitle = 'Procurement Overview, Pipeline Lifecycle, Financial Commitments, and Audit Activity';
$activeNav = 'dashboard';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$isRequester = userHasRole('requester');
$canViewAll = $isAdmin || $isManager || $isOfficer;

// ==============================================================================
// 1. Purchase Request Statistics
// ==============================================================================
$prStats = [
    'total'            => 0,
    'draft'            => 0,
    'pending_approval' => 0,
    'approved'         => 0,
    'rejected'         => 0,
    'cancelled'        => 0,
    'total_value'      => 0.00
];

try {
    $statWhere = "WHERE deleted_at IS NULL";
    $statParams = [];

    if (!$canViewAll) {
        $statWhere .= " AND requested_by = :uid";
        $statParams[':uid'] = $userId;
    }

    $statQuery = "
        SELECT 
            status, 
            COUNT(*) as count,
            COALESCE(SUM(estimated_total), 0.00) as sum_val
        FROM purchase_requests 
        {$statWhere} 
        GROUP BY status
    ";
    $stmt = $db->prepare($statQuery);
    $stmt->execute($statParams);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $s = $row['status'];
        if (isset($prStats[$s])) {
            $prStats[$s] = (int)$row['count'];
        }
        $prStats['total'] += (int)$row['count'];
        $prStats['total_value'] += (float)$row['sum_val'];
    }
} catch (Exception $e) {
    error_log('Dashboard PR Stats Error: ' . $e->getMessage());
}

// ==============================================================================
// 2. Suppliers & Quotations Metrics
// ==============================================================================
$supplierCount = 0;
$quotationStats = [
    'total'         => 0,
    'draft'         => 0,
    'under_review'  => 0,
    'selected'      => 0,
    'rejected'      => 0,
    'awarded_value' => 0.00
];

try {
    $supCountStmt = $db->query("SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL AND status = 'active'");
    $supplierCount = (int)$supCountStmt->fetchColumn();

    $qStatStmt = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
            SUM(CASE WHEN status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) AS under_review,
            SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) AS selected,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN status = 'selected' THEN total_amount ELSE 0 END) AS awarded_value
        FROM quotations
        WHERE deleted_at IS NULL
    ");
    $qCounts = $qStatStmt->fetch();
    if ($qCounts) {
        $quotationStats['total'] = (int)$qCounts['total'];
        $quotationStats['draft'] = (int)$qCounts['draft'];
        $quotationStats['under_review'] = (int)$qCounts['under_review'];
        $quotationStats['selected'] = (int)$qCounts['selected'];
        $quotationStats['rejected'] = (int)$qCounts['rejected'];
        $quotationStats['awarded_value'] = (float)$qCounts['awarded_value'];
    }
} catch (Exception $e) {
    error_log('Dashboard Supplier/Quotation Error: ' . $e->getMessage());
}

// ==============================================================================
// 3. Purchase Order Metrics
// ==============================================================================
$poStats = [
    'total'              => 0,
    'draft'              => 0,
    'pending_approval'   => 0,
    'approved'           => 0,
    'sent'               => 0,
    'partially_received' => 0,
    'fully_received'     => 0,
    'total_committed'    => 0.00
];

try {
    $poStatStmt = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
            SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) AS pending_approval,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN status = 'partially_received' THEN 1 ELSE 0 END) AS partially_received,
            SUM(CASE WHEN status = 'fully_received' THEN 1 ELSE 0 END) AS fully_received,
            SUM(CASE WHEN status IN ('approved', 'sent', 'partially_received', 'fully_received', 'closed') THEN grand_total ELSE 0 END) AS total_committed
        FROM purchase_orders
        WHERE deleted_at IS NULL
    ");
    $poCounts = $poStatStmt->fetch();
    if ($poCounts) {
        $poStats['total'] = (int)$poCounts['total'];
        $poStats['draft'] = (int)$poCounts['draft'];
        $poStats['pending_approval'] = (int)$poCounts['pending_approval'];
        $poStats['approved'] = (int)$poCounts['approved'];
        $poStats['sent'] = (int)$poCounts['sent'];
        $poStats['partially_received'] = (int)$poCounts['partially_received'];
        $poStats['fully_received'] = (int)$poCounts['fully_received'];
        $poStats['total_committed'] = (float)$poCounts['total_committed'];
    }
} catch (Exception $e) {
    error_log('Dashboard PO Query Error: ' . $e->getMessage());
}

// ==============================================================================
// 4. Goods Receiving Metrics
// ==============================================================================
$grnStats = [
    'total'          => 0,
    'draft'          => 0,
    'posted'         => 0,
    'total_accepted' => 0.00,
    'total_rejected' => 0.00
];

try {
    $grnStatStmt = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
            SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) AS posted
        FROM goods_receipts
        WHERE deleted_at IS NULL
    ");
    $grnCounts = $grnStatStmt->fetch();
    if ($grnCounts) {
        $grnStats['total'] = (int)$grnCounts['total'];
        $grnStats['draft'] = (int)$grnCounts['draft'];
        $grnStats['posted'] = (int)$grnCounts['posted'];
    }

    $qtyStmt = $db->query("
        SELECT 
            COALESCE(SUM(gri.received_qty), 0) as total_accepted,
            COALESCE(SUM(gri.rejected_qty), 0) as total_rejected
        FROM goods_receipt_items gri
        JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
        WHERE gr.deleted_at IS NULL AND gr.status = 'posted'
    ");
    $qtyRow = $qtyStmt->fetch();
    if ($qtyRow) {
        $grnStats['total_accepted'] = (float)$qtyRow['total_accepted'];
        $grnStats['total_rejected'] = (float)$qtyRow['total_rejected'];
    }
} catch (Exception $e) {
    error_log('Dashboard GRN Query Error: ' . $e->getMessage());
}

// ==============================================================================
// 5. Recent Datasets (Top 5 each)
// ==============================================================================
$recentRequests = [];
$recentQuotations = [];
$recentOrders = [];
$recentGrns = [];
$activityLogs = [];

try {
    // Recent PRs
    $prWhere = $canViewAll ? "WHERE pr.deleted_at IS NULL" : "WHERE pr.deleted_at IS NULL AND pr.requested_by = " . (int)$userId;
    $recentRequests = $db->query("
        SELECT pr.*, d.name as department_name, d.code as department_code, u.full_name as requester_name
        FROM purchase_requests pr
        LEFT JOIN departments d ON pr.department_id = d.id
        LEFT JOIN users u ON pr.requested_by = u.id
        {$prWhere}
        ORDER BY pr.id DESC
        LIMIT 5
    ")->fetchAll();

    // Recent POs
    $recentOrders = $db->query("
        SELECT po.*, s.name AS supplier_name, s.supplier_code, pr.request_no
        FROM purchase_orders po
        JOIN suppliers s ON s.id = po.supplier_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        WHERE po.deleted_at IS NULL
        ORDER BY po.id DESC
        LIMIT 5
    ")->fetchAll();

    // Recent GRNs
    $recentGrns = $db->query("
        SELECT gr.*, po.po_no, s.name AS supplier_name, s.supplier_code, u.full_name AS receiver_name,
               (SELECT COALESCE(SUM(received_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_received_qty
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN suppliers s ON s.id = gr.supplier_id
        LEFT JOIN users u ON u.id = gr.received_by
        WHERE gr.deleted_at IS NULL
        ORDER BY gr.id DESC
        LIMIT 5
    ")->fetchAll();

    // Recent Quotations
    $recentQuotations = $db->query("
        SELECT q.*, pr.request_no, s.name AS supplier_name, s.supplier_code
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE q.deleted_at IS NULL
        ORDER BY q.id DESC
        LIMIT 5
    ")->fetchAll();

    // Recent Activity Logs
    $activityLogs = $db->query("
        SELECT a.*, u.full_name, u.username 
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC 
        LIMIT 6
    ")->fetchAll();

} catch (Exception $e) {
    error_log('Dashboard Recent Queries Error: ' . $e->getMessage());
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Welcome Banner (Solid Business Palette) -->
<div class="card-cms mb-4 border-0 shadow-sm" style="background-color: #0f172a; color: #ffffff;">
    <div class="card-cms-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-primary px-3 py-2 fs-7 font-monospace fw-semibold">
                        <i class="bi bi-shield-check me-1"></i> Procurement Operations Center
                    </span>
                    <span class="badge bg-success px-3 py-2 fs-7">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> System Active
                    </span>
                </div>
                <h2 class="fw-bold mb-1 text-white">Welcome, <?= e($user['full_name']) ?></h2>
                <p class="text-slate-300 mb-0 opacity-75 small">
                    Signed in as <strong class="text-white"><?= e($user['role_names'] ?? 'User') ?></strong> &bull;
                    Last session: <span class="text-white"><?= formatDate($user['last_login']) ?></span>
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-3 mt-lg-0 d-flex flex-wrap justify-content-lg-end gap-2">
                <a href="<?= url('modules/purchase_requests/create.php') ?>" class="btn btn-primary btn-sm fw-semibold px-3 py-2 shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> New Request
                </a>
                <?php if ($canViewAll): ?>
                    <a href="<?= url('modules/purchase_orders/create.php') ?>" class="btn btn-info btn-sm fw-semibold px-3 py-2 shadow-sm text-dark">
                        <i class="bi bi-file-earmark-plus-fill me-1"></i> Issue PO
                    </a>
                    <a href="<?= url('modules/goods_receiving/create.php') ?>" class="btn btn-warning btn-sm fw-semibold px-3 py-2 shadow-sm text-dark">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Receive Goods
                    </a>
                    <a href="<?= url('modules/reports/index.php') ?>" class="btn btn-outline-light btn-sm fw-semibold px-3 py-2 shadow-sm">
                        <i class="bi bi-bar-chart-fill me-1"></i> Reports
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Primary Summary Cards Strip -->
<div class="row g-3 mb-4">
    <!-- Total Requests -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon primary" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-cart3"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Requisitions</div>
                <h4 class="stat-value fs-5"><?= number_format($prStats['total']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Pending Approvals -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon warning" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Pending PRs</div>
                <h4 class="stat-value fs-5 text-warning-emphasis"><?= number_format($prStats['pending_approval']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Active Suppliers -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #f3e8ff; color: #7c3aed;">
                <i class="bi bi-building"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Active Vendors</div>
                <h4 class="stat-value fs-5 text-dark"><?= number_format($supplierCount) ?></h4>
            </div>
        </div>
    </div>

    <!-- Quotations -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #e0f2fe; color: #0284c7;">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Quotations</div>
                <h4 class="stat-value fs-5 text-info"><?= number_format($quotationStats['total']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Purchase Orders -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon success" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-file-earmark-check-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Purchase Orders</div>
                <h4 class="stat-value fs-5 text-success"><?= number_format($poStats['total']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Posted GRNs -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #ecfdf5; color: #059669;">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Posted GRNs</div>
                <h4 class="stat-value fs-5 text-success"><?= number_format($grnStats['posted']) ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Procurement Lifecycle Pipeline & Financial Commitments -->
<div class="row g-4 mb-4">
    <!-- Visual Stage Progression Pipeline -->
    <div class="col-lg-8">
        <div class="card-cms h-100">
            <div class="card-cms-header d-flex justify-content-between align-items-center">
                <h3 class="card-cms-title">
                    <i class="bi bi-diagram-3-fill text-primary"></i>
                    <span>Procurement Pipeline Overview</span>
                </h3>
                <span class="badge bg-light text-dark border small">Live Status Flow</span>
            </div>
            <div class="card-cms-body p-4">
                <div class="row g-3">
                    <!-- Stage 1: Draft PR -->
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded border text-center">
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.6875rem;">1. Draft Requisitions</div>
                            <div class="fs-4 fw-bold text-secondary my-1"><?= number_format($prStats['draft']) ?></div>
                            <div class="small text-muted" style="font-size: 0.75rem;">In composition</div>
                        </div>
                    </div>

                    <!-- Stage 2: Pending PR -->
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded border text-center border-warning-subtle">
                            <div class="text-warning-emphasis small fw-semibold text-uppercase" style="font-size: 0.6875rem;">2. PR Approvals</div>
                            <div class="fs-4 fw-bold text-warning my-1"><?= number_format($prStats['pending_approval']) ?></div>
                            <div class="small text-muted" style="font-size: 0.75rem;">Awaiting manager</div>
                        </div>
                    </div>

                    <!-- Stage 3: Approved PR -->
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded border text-center border-success-subtle">
                            <div class="text-success small fw-semibold text-uppercase" style="font-size: 0.6875rem;">3. Approved PRs</div>
                            <div class="fs-4 fw-bold text-success my-1"><?= number_format($prStats['approved']) ?></div>
                            <div class="small text-muted" style="font-size: 0.75rem;">Ready for RFQ</div>
                        </div>
                    </div>

                    <!-- Stage 4: Selected Quotes -->
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded border text-center border-info-subtle">
                            <div class="text-info small fw-semibold text-uppercase" style="font-size: 0.6875rem;">4. Awarded Quotes</div>
                            <div class="fs-4 fw-bold text-info my-1"><?= number_format($quotationStats['selected']) ?></div>
                            <div class="small text-muted" style="font-size: 0.75rem;">Selected bids</div>
                        </div>
                    </div>

                    <!-- Stage 5: PO Pending -->
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded border text-center border-warning-subtle">
                            <div class="text-warning-emphasis small fw-semibold text-uppercase" style="font-size: 0.6875rem;">5. PO Review</div>
                            <div class="fs-4 fw-bold text-warning-emphasis my-1"><?= number_format($poStats['pending_approval']) ?></div>
                            <div class="small text-muted" style="font-size: 0.75rem;">PO authorization</div>
                        </div>
                    </div>

                    <!-- Stage 6: Dispatched PO -->
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded border text-center border-primary-subtle">
                            <div class="text-primary small fw-semibold text-uppercase" style="font-size: 0.6875rem;">6. Sent to Vendor</div>
                            <div class="fs-4 fw-bold text-primary my-1"><?= number_format($poStats['sent']) ?></div>
                            <div class="small text-muted" style="font-size: 0.75rem;">Dispatched orders</div>
                        </div>
                    </div>

                    <!-- Stage 7: Partially Received -->
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded border text-center">
                            <div class="text-primary small fw-semibold text-uppercase" style="font-size: 0.6875rem;">7. Partial Delivery</div>
                            <div class="fs-4 fw-bold text-primary my-1"><?= number_format($poStats['partially_received']) ?></div>
                            <div class="small text-muted" style="font-size: 0.75rem;">In transit balance</div>
                        </div>
                    </div>

                    <!-- Stage 8: Fully Received -->
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded border text-center border-success-subtle">
                            <div class="text-success small fw-semibold text-uppercase" style="font-size: 0.6875rem;">8. Fully Fulfilled</div>
                            <div class="fs-4 fw-bold text-success my-1"><?= number_format($poStats['fully_received']) ?></div>
                            <div class="small text-muted" style="font-size: 0.75rem;">Closed receipts</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Spend & Commitment Metrics -->
    <div class="col-lg-4">
        <div class="card-cms h-100">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-cash-coin text-primary"></i>
                    <span>Financial Commitments</span>
                </h3>
            </div>
            <div class="card-cms-body p-3">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <div>
                            <div class="fw-semibold text-dark">Total Requisition Demand</div>
                            <div class="text-muted" style="font-size: 0.6875rem;">Estimated value of all PRs</div>
                        </div>
                        <span class="font-monospace fw-bold text-dark fs-6"><?= formatCurrency($prStats['total_value']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <div>
                            <div class="fw-semibold text-info">Awarded Quotation Value</div>
                            <div class="text-muted" style="font-size: 0.6875rem;">Winning competitive bids</div>
                        </div>
                        <span class="font-monospace fw-bold text-info fs-6"><?= formatCurrency($quotationStats['awarded_value']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <div>
                            <div class="fw-semibold text-success">Committed PO Spend</div>
                            <div class="text-muted" style="font-size: 0.6875rem;">Approved & dispatched POs</div>
                        </div>
                        <span class="font-monospace fw-bold text-success fs-6"><?= formatCurrency($poStats['total_committed']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <div>
                            <div class="fw-semibold text-dark">Accepted Warehouse Units</div>
                            <div class="text-muted" style="font-size: 0.6875rem;">Cumulative intake units</div>
                        </div>
                        <span class="font-monospace fw-bold text-success fs-6">+<?= number_format($grnStats['total_accepted'], 0) ?> items</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid: Recent PRs & Audit Logs -->
<div class="row g-4 mb-4">
    <!-- Recent Purchase Requests Table -->
    <div class="col-lg-8">
        <div class="card-cms h-100">
            <div class="card-cms-header d-flex justify-content-between align-items-center">
                <h3 class="card-cms-title">
                    <i class="bi bi-cart-check-fill text-primary"></i>
                    <span>Recent Purchase Requests</span>
                </h3>
                <a href="<?= url('modules/purchase_requests/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-cms-body p-0">
                <?php if (!empty($recentRequests)): ?>
                    <div class="table-responsive">
                        <table class="table table-cms align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Request No</th>
                                    <th>Department</th>
                                    <th>Requested By</th>
                                    <th>Priority</th>
                                    <th class="text-end">Est. Total</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRequests as $pr): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                <?= e($pr['request_no']) ?>
                                            </a>
                                            <div class="text-muted" style="font-size: 0.6875rem;">
                                                <?= formatDate($pr['request_date'], 'd M Y') ?>
                                            </div>
                                        </td>
                                        <td class="small">
                                            <span class="badge bg-light text-dark border"><?= e($pr['department_code'] ?? $pr['department_name']) ?></span>
                                        </td>
                                        <td class="small fw-semibold text-dark">
                                            <?= e($pr['requester_name']) ?>
                                        </td>
                                        <td>
                                            <?= getPriorityBadge($pr['priority']) ?>
                                        </td>
                                        <td class="text-end font-monospace small fw-bold text-dark">
                                            <?= formatCurrency($pr['estimated_total']) ?>
                                        </td>
                                        <td>
                                            <?= getStatusBadge($pr['status']) ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cart-x fs-1 d-block mb-2 text-light"></i>
                        <p class="mb-2">No purchase requests recorded yet.</p>
                        <a href="<?= url('modules/purchase_requests/create.php') ?>" class="btn btn-primary-cms btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> Create First Request
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Activity Logs -->
    <div class="col-lg-4">
        <div class="card-cms h-100">
            <div class="card-cms-header d-flex justify-content-between align-items-center">
                <h3 class="card-cms-title">
                    <i class="bi bi-activity text-primary"></i>
                    <span>Recent Audit Activity</span>
                </h3>
                <?php if ($isAdmin || $isManager): ?>
                    <a href="<?= url('modules/activity_logs/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                        All Logs <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-cms-body p-0">
                <?php if (!empty($activityLogs)): ?>
                    <div class="list-group list-group-flush small">
                        <?php foreach ($activityLogs as $log): ?>
                            <div class="list-group-item py-2 px-3">
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <span class="fw-bold text-dark"><?= e($log['action']) ?></span>
                                    <span class="text-muted font-monospace" style="font-size: 0.6875rem;"><?= formatDate($log['created_at'], 'd M, h:i A') ?></span>
                                </div>
                                <div class="text-muted text-truncate" style="font-size: 0.75rem;" title="<?= e($log['description']) ?>">
                                    <?= e($log['description'] ?? '-') ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.6875rem;">
                                    By <strong class="text-dark"><?= e($log['full_name'] ?? ($log['username'] ?? 'System')) ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">
                        No activity logs available.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Purchase Orders Card -->
<div class="card-cms mb-4">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-file-earmark-check-fill text-primary"></i>
            <span>Recent Purchase Orders</span>
        </h3>
        <a href="<?= url('modules/purchase_orders/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            View All Orders <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-cms-body p-0">
        <?php if (!empty($recentOrders)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Requisition</th>
                            <th>Vendor Supplier</th>
                            <th>PO Date</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ro): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('modules/purchase_orders/view.php?id=' . $ro['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($ro['po_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?= url('modules/purchase_requests/view.php?id=' . $ro['purchase_request_id']) ?>" class="text-dark fw-semibold text-decoration-none small font-monospace">
                                        <?= e($ro['request_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark small"><?= e($ro['supplier_name']) ?></div>
                                    <span class="text-muted font-monospace small" style="font-size: 0.6875rem;"><?= e($ro['supplier_code']) ?></span>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= formatDate($ro['po_date'], 'd M Y') ?>
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark small">
                                    <?= formatCurrency($ro['grand_total']) ?>
                                </td>
                                <td class="text-center">
                                    <?= getPoStatusBadge($ro['status']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('modules/purchase_orders/view.php?id=' . $ro['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View Order">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">
                <i class="bi bi-file-earmark-x fs-2 text-secondary d-block mb-1"></i>
                No purchase orders generated yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Goods Receipts (GRNs) Card -->
<div class="card-cms mb-4">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-box-seam-fill text-primary"></i>
            <span>Recent Goods Receipts (GRN)</span>
        </h3>
        <a href="<?= url('modules/goods_receiving/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            View All Receipts <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-cms-body p-0">
        <?php if (!empty($recentGrns)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th>GRN Number</th>
                            <th>Purchase Order</th>
                            <th>Supplier Vendor</th>
                            <th>Receipt Date</th>
                            <th>Received By</th>
                            <th class="text-center">Accepted Items</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentGrns as $rgrn): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('modules/goods_receiving/view.php?id=' . $rgrn['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($rgrn['grn_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?= url('modules/purchase_orders/view.php?id=' . $rgrn['purchase_order_id']) ?>" class="text-dark fw-semibold text-decoration-none small font-monospace">
                                        <?= e($rgrn['po_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark small"><?= e($rgrn['supplier_name']) ?></div>
                                    <span class="text-muted font-monospace small" style="font-size: 0.6875rem;"><?= e($rgrn['supplier_code']) ?></span>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= formatDate($rgrn['receipt_date'], 'd M Y') ?>
                                </td>
                                <td class="small text-dark fw-medium">
                                    <?= e($rgrn['receiver_name'] ?? 'Authorized Officer') ?>
                                </td>
                                <td class="text-center small font-monospace">
                                    <span class="badge bg-light text-dark border">
                                        +<?= number_format((float)$rgrn['total_received_qty'], 0) ?> accepted
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?= getGrnStatusBadge($rgrn['status']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('modules/goods_receiving/view.php?id=' . $rgrn['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View GRN">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">
                <i class="bi bi-box-seam fs-2 text-secondary d-block mb-1"></i>
                No goods receipts recorded yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Supplier Quotations Card -->
<div class="card-cms mb-4">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-receipt-cutoff text-primary"></i>
            <span>Recent Supplier Quotations</span>
        </h3>
        <a href="<?= url('modules/quotations/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            View All Quotations <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-cms-body p-0">
        <?php if (!empty($recentQuotations)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Quote No</th>
                            <th>Requisition</th>
                            <th>Supplier Vendor</th>
                            <th>Date</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentQuotations as $rq): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('modules/quotations/view.php?id=' . $rq['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($rq['quotation_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?= url('modules/purchase_requests/view.php?id=' . $rq['purchase_request_id']) ?>" class="text-dark fw-semibold text-decoration-none small">
                                        <?= e($rq['request_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark small"><?= e($rq['supplier_name']) ?></div>
                                    <span class="text-muted small font-monospace"><?= e($rq['supplier_code']) ?></span>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= formatDate($rq['quotation_date'], 'd M Y') ?>
                                </td>
                                <td class="text-end font-monospace small fw-bold text-dark">
                                    <?= formatCurrency($rq['total_amount']) ?>
                                </td>
                                <td class="text-center">
                                    <?= getQuotationStatusBadge($rq['status']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('modules/quotations/view.php?id=' . $rq['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View Quotation">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">
                <i class="bi bi-receipt fs-2 text-secondary d-block mb-1"></i>
                No vendor quotations recorded yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
