<?php
/**
 * Application Dashboard
 * Procurement Management CMS - Phase 02
 */

$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview, Requisition Metrics, and Activity';
$activeNav = 'dashboard';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewAll = $isAdmin || $isManager || $isOfficer;

// 1. Fetch Purchase Request Statistics
$prStats = [
    'total'            => 0,
    'draft'            => 0,
    'pending_approval' => 0,
    'approved'         => 0,
    'rejected'         => 0,
    'cancelled'        => 0
];

try {
    $statWhere = "WHERE deleted_at IS NULL";
    $statParams = [];

    if (!$canViewAll) {
        $statWhere .= " AND requested_by = :uid";
        $statParams[':uid'] = $userId;
    }

    $statQuery = "
        SELECT status, COUNT(*) as count 
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
    }
} catch (Exception $e) {
    error_log('Dashboard PR Stats Error: ' . $e->getMessage());
}

// 2. Fetch Recent Purchase Requests (Top 5)
$recentRequests = [];
try {
    $prWhere = "WHERE pr.deleted_at IS NULL";
    $prParams = [];

    if (!$canViewAll) {
        $prWhere .= " AND pr.requested_by = :uid";
        $prParams[':uid'] = $userId;
    }

    $prQuery = "
        SELECT pr.*, d.name as department_name, d.code as department_code, u.full_name as requester_name
        FROM purchase_requests pr
        LEFT JOIN departments d ON pr.department_id = d.id
        LEFT JOIN users u ON pr.requested_by = u.id
        {$prWhere}
        ORDER BY pr.id DESC
        LIMIT 5
    ";
    $prStmt = $db->prepare($prQuery);
    $prStmt->execute($prParams);
    $recentRequests = $prStmt->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard Recent PRs Error: ' . $e->getMessage());
}

// 3. Fetch Phase 03 Supplier & Quotation Summary Metrics
$supplierCount = 0;
$quotationStats = [
    'total'         => 0,
    'under_review'  => 0,
    'selected'      => 0,
    'awarded_value' => 0.00
];
$recentQuotations = [];

try {
    $supCountStmt = $db->query("SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL AND status = 'active'");
    $supplierCount = (int)$supCountStmt->fetchColumn();

    $qStatStmt = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) AS under_review,
            SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) AS selected,
            SUM(CASE WHEN status = 'selected' THEN total_amount ELSE 0 END) AS awarded_value
        FROM quotations
        WHERE deleted_at IS NULL
    ");
    $quotationStats = $qStatStmt->fetch() ?: $quotationStats;

    // Fetch Top 5 Recent Quotations
    $rqStmt = $db->query("
        SELECT q.*, pr.request_no, s.name AS supplier_name, s.supplier_code
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE q.deleted_at IS NULL
        ORDER BY q.id DESC
        LIMIT 5
    ");
    $recentQuotations = $rqStmt->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard Phase 03 Query Error: ' . $e->getMessage());
}

// 4. Fetch Phase 04 Purchase Order Summary Metrics
$poStats = [
    'total'            => 0,
    'draft'            => 0,
    'pending_approval' => 0,
    'approved'         => 0,
    'sent'             => 0,
    'total_value'      => 0.00
];
$recentOrders = [];

try {
    $poStatStmt = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
            SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) AS pending_approval,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN status IN ('approved', 'sent') THEN grand_total ELSE 0 END) AS total_value
        FROM purchase_orders
        WHERE deleted_at IS NULL
    ");
    $poStats = $poStatStmt->fetch() ?: $poStats;

    // Fetch Top 5 Recent POs
    $roStmt = $db->query("
        SELECT po.*, s.name AS supplier_name, s.supplier_code, q.quotation_no, pr.request_no
        FROM purchase_orders po
        JOIN suppliers s ON s.id = po.supplier_id
        JOIN quotations q ON q.id = po.quotation_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        WHERE po.deleted_at IS NULL
        ORDER BY po.id DESC
        LIMIT 5
    ");
    $recentOrders = $roStmt->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard Phase 04 PO Query Error: ' . $e->getMessage());
}

// 5. Fetch Phase 05 Goods Receiving Summary Metrics
$grnStats = [
    'total'              => 0,
    'draft'              => 0,
    'posted'             => 0,
    'partially_received' => 0,
    'fully_received'     => 0
];
$recentGrns = [];

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

    $poReceivingStmt = $db->query("
        SELECT
            SUM(CASE WHEN status = 'partially_received' THEN 1 ELSE 0 END) AS partially_received,
            SUM(CASE WHEN status = 'fully_received' THEN 1 ELSE 0 END) AS fully_received
        FROM purchase_orders
        WHERE deleted_at IS NULL
    ");
    $poRecCounts = $poReceivingStmt->fetch();
    if ($poRecCounts) {
        $grnStats['partially_received'] = (int)$poRecCounts['partially_received'];
        $grnStats['fully_received'] = (int)$poRecCounts['fully_received'];
    }

    // Fetch Top 5 Recent GRNs
    $rgrnStmt = $db->query("
        SELECT gr.*, po.po_no, s.name AS supplier_name, s.supplier_code, u.full_name AS receiver_name,
               (SELECT COUNT(*) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS item_count,
               (SELECT COALESCE(SUM(received_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_received_qty
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN suppliers s ON s.id = gr.supplier_id
        LEFT JOIN users u ON u.id = gr.received_by
        WHERE gr.deleted_at IS NULL
        ORDER BY gr.id DESC
        LIMIT 5
    ");
    $recentGrns = $rgrnStmt->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard Phase 05 GRN Query Error: ' . $e->getMessage());
}

// 6. Fetch Recent Activity Logs
$activityLogs = [];
try {
    $actStmt = $db->prepare("
        SELECT a.*, u.full_name, u.username 
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC 
        LIMIT 6
    ");
    $actStmt->execute();
    $activityLogs = $actStmt->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard Activity Log Query Error: ' . $e->getMessage());
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card-cms mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #ffffff;">
    <div class="card-cms-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-primary px-3 py-2 fs-7 font-monospace fw-semibold">
                        <i class="bi bi-box-seam-fill me-1"></i> Phase 05: Goods Receiving / GRN
                    </span>
                    <span class="badge bg-success bg-opacity-75 px-3 py-2 fs-7">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> <?= ucfirst(e($user['status'])) ?>
                    </span>
                </div>
                <h2 class="fw-bold mb-1 text-white">Welcome back, <?= e($user['full_name']) ?>!</h2>
                <p class="text-slate-300 mb-0 opacity-75 small">
                    Signed in as <strong class="text-white"><?= e($user['role_names'] ?? 'User') ?></strong> &bull;
                    Last logged in: <span class="text-white"><?= formatDate($user['last_login']) ?></span>
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-3 mt-lg-0 d-flex flex-wrap justify-content-lg-end gap-2">
                <a href="<?= url('modules/purchase_requests/create.php') ?>" class="btn btn-primary btn-sm fw-semibold px-3 py-2 shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> New Request
                </a>
                <?php if ($canViewAll): ?>
                    <a href="<?= url('modules/goods_receiving/create.php') ?>" class="btn btn-warning btn-sm fw-semibold px-3 py-2 shadow-sm text-dark">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Receive Goods
                    </a>
                    <a href="<?= url('modules/purchase_orders/create.php') ?>" class="btn btn-info btn-sm fw-semibold px-3 py-2 shadow-sm text-dark">
                        <i class="bi bi-file-earmark-plus-fill me-1"></i> Issue PO
                    </a>
                    <a href="<?= url('modules/quotations/create.php') ?>" class="btn btn-success btn-sm fw-semibold px-3 py-2 shadow-sm">
                        <i class="bi bi-receipt me-1"></i> Record Quote
                    </a>
                    <a href="<?= url('modules/suppliers/create.php') ?>" class="btn btn-outline-light btn-sm fw-semibold px-3 py-2 shadow-sm">
                        <i class="bi bi-building-add me-1"></i> Add Vendor
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Requisition Metrics Cards -->
<div class="row g-3 mb-4">
    <!-- Total Requests -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon primary" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-cart3"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Total Requests</div>
                <h4 class="stat-value fs-5"><?= number_format($prStats['total']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Drafts -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: var(--surface-subtle); color: var(--text-muted);">
                <i class="bi bi-file-earmark"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Drafts</div>
                <h4 class="stat-value fs-5 text-secondary"><?= number_format($prStats['draft']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Pending Approval -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon warning" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Pending Approval</div>
                <h4 class="stat-value fs-5 text-warning-emphasis"><?= number_format($prStats['pending_approval']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Approved -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon success" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Approved</div>
                <h4 class="stat-value fs-5 text-success"><?= number_format($prStats['approved']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Rejected -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: var(--danger-bg); color: var(--danger);">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Rejected</div>
                <h4 class="stat-value fs-5 text-danger"><?= number_format($prStats['rejected']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Cancelled -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #f1f5f9; color: #475569;">
                <i class="bi bi-slash-circle"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Cancelled</div>
                <h4 class="stat-value fs-5 text-dark"><?= number_format($prStats['cancelled']) ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Procurement & Sourcing Metrics Cards (Phase 03) -->
<div class="row g-3 mb-4">
    <!-- Active Suppliers -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-primary border-4">
            <div class="stat-icon primary" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-building"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Active Suppliers</div>
                <h4 class="stat-value fs-5 text-gray-800"><?= number_format($supplierCount) ?></h4>
            </div>
        </div>
    </div>

    <!-- Total Quotations -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-info border-4">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #e0f2fe; color: #0284c7;">
                <i class="bi bi-file-earmark-spreadsheet"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Total Quotations</div>
                <h4 class="stat-value fs-5 text-info"><?= number_format((int)$quotationStats['total']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Awarded Bids -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Awarded Bids</div>
                <h4 class="stat-value fs-5 text-success"><?= number_format((int)$quotationStats['selected']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Awarded Value -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-warning border-4">
            <div class="stat-icon warning" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Awarded Value</div>
                <h4 class="stat-value fs-5 text-warning-emphasis font-monospace"><?= formatCurrency($quotationStats['awarded_value']) ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Order Logistics & Fulfillment Metrics (Phase 04) -->
<div class="row g-3 mb-4">
    <!-- Total Purchase Orders -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-primary border-4">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #dbeafe; color: #1d4ed8;">
                <i class="bi bi-file-earmark-check-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Total Purchase Orders</div>
                <h4 class="stat-value fs-5 text-dark"><?= number_format((int)$poStats['total']) ?></h4>
            </div>
        </div>
    </div>

    <!-- POs Pending Approval -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-warning border-4">
            <div class="stat-icon warning" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">POs Pending Approval</div>
                <h4 class="stat-value fs-5 text-warning-emphasis"><?= number_format((int)$poStats['pending_approval']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Approved POs -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-info border-4">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #e0f2fe; color: #0284c7;">
                <i class="bi bi-check2-all"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Approved (Ready)</div>
                <h4 class="stat-value fs-5 text-info"><?= number_format((int)$poStats['approved']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Dispatched / Sent Value -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-send-check-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Sent to Suppliers (<?= number_format((int)$poStats['sent']) ?>)</div>
                <h4 class="stat-value fs-5 text-success font-monospace"><?= formatCurrency($poStats['total_value']) ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Goods Receiving & Warehouse Intake Metrics (Phase 05) -->
<div class="row g-3 mb-4">
    <!-- Total GRNs -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-primary border-4">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #dbeafe; color: #1d4ed8;">
                <i class="bi bi-box-arrow-in-down"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Total Goods Receipts</div>
                <h4 class="stat-value fs-5 text-dark"><?= number_format((int)$grnStats['total']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Posted GRNs -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Posted Receipts (Locked)</div>
                <h4 class="stat-value fs-5 text-success"><?= number_format((int)$grnStats['posted']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Partially Received POs -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-info border-4">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #e0f2fe; color: #0284c7;">
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Partially Received POs</div>
                <h4 class="stat-value fs-5 text-info"><?= number_format((int)$grnStats['partially_received']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Fully Received POs -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-box2-check-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Fully Fulfilled POs</div>
                <h4 class="stat-value fs-5 text-success"><?= number_format((int)$grnStats['fully_received']) ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid: Recent PRs & System Audit -->
<div class="row g-4 mb-4">
    <!-- Recent Purchase Requests Table -->
    <div class="col-lg-8">
        <div class="card-cms h-100">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-cart-check-fill text-primary"></i>
                    <span>Recent Purchase Requests</span>
                </h3>
                <a href="<?= url('modules/purchase_requests/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    View All Requisitions <i class="bi bi-arrow-right ms-1"></i>
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
                                            <span class="badge bg-light text-dark border"><?= e($pr['department_code']) ?></span>
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
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-activity text-primary"></i>
                    <span>Recent Audit Activity</span>
                </h3>
            </div>
            <div class="card-cms-body p-0">
                <?php if (!empty($activityLogs)): ?>
                    <div class="list-group list-group-flush small">
                        <?php foreach ($activityLogs as $log): ?>
                            <div class="list-group-item py-2 px-3">
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <span class="fw-bold text-dark"><?= e($log['action']) ?></span>
                                    <span class="text-muted" style="font-size: 0.6875rem;"><?= formatDate($log['created_at'], 'd M, h:i A') ?></span>
                                </div>
                                <div class="text-muted text-truncate" style="font-size: 0.75rem;" title="<?= e($log['description']) ?>">
                                    <?= e($log['description'] ?? '-') ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.6875rem;">
                                    By <strong class="text-dark"><?= e($log['full_name'] ?? 'System') ?></strong>
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

<!-- Recent Purchase Orders Card (Phase 04) -->
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

<!-- Recent Goods Receipts (GRNs) Card (Phase 05) -->
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
                                        <?= (int)$rgrn['item_count'] ?> items &bull; +<?= number_format((float)$rgrn['total_received_qty'], 0) ?> rec
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

<!-- Recent Supplier Quotations Card (Phase 03) -->
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
