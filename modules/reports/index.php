<?php
/**
 * Reports Hub & Analytics Center
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Procurement Reports';
$pageSubtitle = 'Real-time Analytical Reports, Audits, and Export Center';
$activeNav = 'reports';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$isRequester = userHasRole('requester');
$db = getDb();

// Calculate High-Level Report Metrics from actual DB
$stats = [
    'total_prs'         => 0,
    'approved_prs'      => 0,
    'pr_value'          => 0.00,
    'active_suppliers'  => 0,
    'total_quotations'  => 0,
    'awarded_quotes'    => 0,
    'total_pos'         => 0,
    'po_committed_value'=> 0.00,
    'posted_grns'       => 0,
    'total_received_qty'=> 0.00
];

try {
    // 1. PR Stats
    $prWhere = ($isRequester && !$isAdmin && !$isManager && !$isOfficer) ? "WHERE deleted_at IS NULL AND requested_by = " . (int)$user['id'] : "WHERE deleted_at IS NULL";
    $prRow = $db->query("
        SELECT 
            COUNT(*) AS total_prs,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_prs,
            COALESCE(SUM(estimated_total), 0.00) AS pr_value
        FROM purchase_requests
        {$prWhere}
    ")->fetch();
    if ($prRow) {
        $stats['total_prs'] = (int)$prRow['total_prs'];
        $stats['approved_prs'] = (int)$prRow['approved_prs'];
        $stats['pr_value'] = (float)$prRow['pr_value'];
    }

    // 2. Suppliers
    $stats['active_suppliers'] = (int)$db->query("SELECT COUNT(*) FROM suppliers WHERE deleted_at IS NULL AND status = 'active'")->fetchColumn();

    // 3. Quotations
    $qRow = $db->query("
        SELECT 
            COUNT(*) AS total_quotations,
            SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) AS awarded_quotes
        FROM quotations
        WHERE deleted_at IS NULL
    ")->fetch();
    if ($qRow) {
        $stats['total_quotations'] = (int)$qRow['total_quotations'];
        $stats['awarded_quotes'] = (int)$qRow['awarded_quotes'];
    }

    // 4. Purchase Orders
    $poRow = $db->query("
        SELECT 
            COUNT(*) AS total_pos,
            COALESCE(SUM(CASE WHEN status IN ('approved', 'sent', 'partially_received', 'fully_received', 'closed') THEN grand_total ELSE 0 END), 0.00) AS po_committed_value
        FROM purchase_orders
        WHERE deleted_at IS NULL
    ")->fetch();
    if ($poRow) {
        $stats['total_pos'] = (int)$poRow['total_pos'];
        $stats['po_committed_value'] = (float)$poRow['po_committed_value'];
    }

    // 5. GRN Stats
    $grnRow = $db->query("
        SELECT 
            COUNT(*) AS posted_grns
        FROM goods_receipts
        WHERE deleted_at IS NULL AND status = 'posted'
    ")->fetch();
    if ($grnRow) {
        $stats['posted_grns'] = (int)$grnRow['posted_grns'];
    }

    $grnQty = $db->query("
        SELECT COALESCE(SUM(gri.received_qty), 0)
        FROM goods_receipt_items gri
        JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
        WHERE gr.deleted_at IS NULL AND gr.status = 'posted'
    ")->fetchColumn();
    $stats['total_received_qty'] = (float)$grnQty;

} catch (Exception $e) {
    error_log('Reports Hub Query Error: ' . $e->getMessage());
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Header Banner -->
<div class="card-cms mb-4">
    <div class="card-cms-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-file-earmark-bar-graph-fill text-primary me-2"></i>Reports & Intelligence Hub
                </h2>
                <p class="text-muted mb-0 small">
                    Access real-time procurement reporting, audit performance metrics, print official summaries, and export data.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border p-2 small font-monospace">
                    <i class="bi bi-calendar3 me-1 text-primary"></i> <?= date('d M Y, h:i A') ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- High Level KPI Strip -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-primary border-4">
            <div class="stat-icon primary">
                <i class="bi bi-cart-check"></i>
            </div>
            <div>
                <div class="stat-label">Total Requisitions</div>
                <h4 class="stat-value"><?= number_format($stats['total_prs']) ?></h4>
                <div class="small text-muted font-monospace"><?= formatCurrency($stats['pr_value']) ?> est.</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-info border-4">
            <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="stat-label">Quotations / Bids</div>
                <h4 class="stat-value text-info"><?= number_format($stats['total_quotations']) ?></h4>
                <div class="small text-muted font-monospace"><?= number_format($stats['awarded_quotes']) ?> awarded</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success">
                <i class="bi bi-file-earmark-check"></i>
            </div>
            <div>
                <div class="stat-label">Committed PO Spend</div>
                <h4 class="stat-value text-success font-monospace"><?= formatCurrency($stats['po_committed_value']) ?></h4>
                <div class="small text-muted font-monospace"><?= number_format($stats['total_pos']) ?> purchase orders</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-warning border-4">
            <div class="stat-icon warning">
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <div class="stat-label">Goods Intake (GRN)</div>
                <h4 class="stat-value text-warning-emphasis"><?= number_format($stats['posted_grns']) ?></h4>
                <div class="small text-muted font-monospace"><?= number_format($stats['total_received_qty'], 0) ?> items accepted</div>
            </div>
        </div>
    </div>
</div>

<!-- 5 Report Navigation Cards Grid -->
<div class="row g-4">
    <!-- Report 1: Purchase Request Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card-cms h-100 d-flex flex-column hover-shadow">
            <div class="card-cms-body p-4 flex-grow-1">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon primary">
                        <i class="bi bi-cart3"></i>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Report 01</span>
                </div>
                <h4 class="fw-bold text-dark mb-2">Purchase Request Report</h4>
                <p class="text-muted small mb-3">
                    Analyze requisition pipeline, department demand, urgency priorities, approval cycle times, and estimated spend.
                </p>
                <div class="bg-light p-2 rounded small text-muted font-monospace mb-3">
                    <strong>Filters:</strong> Date range, Department, Requester, Status
                </div>
            </div>
            <div class="card-cms-footer p-3 bg-light border-top d-flex gap-2">
                <a href="<?= url('modules/reports/purchase_requests.php') ?>" class="btn btn-primary-cms btn-sm flex-grow-1">
                    <i class="bi bi-bar-chart me-1"></i> View Report
                </a>
                <a href="<?= url('modules/reports/export.php?type=purchase_requests') ?>" class="btn btn-outline-secondary btn-sm" title="Export CSV">
                    <i class="bi bi-download"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Report 2: Supplier Directory & Performance Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card-cms h-100 d-flex flex-column hover-shadow">
            <div class="card-cms-body p-4 flex-grow-1">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon" style="background-color: #f3e8ff; color: #7c3aed;">
                        <i class="bi bi-building"></i>
                    </div>
                    <span class="badge bg-secondary-subtle text-dark border">Report 02</span>
                </div>
                <h4 class="fw-bold text-dark mb-2">Supplier & Vendor Report</h4>
                <p class="text-muted small mb-3">
                    Track vendor performance, quote participation, awarded contracts, contact information, and cumulative spend.
                </p>
                <div class="bg-light p-2 rounded small text-muted font-monospace mb-3">
                    <strong>Filters:</strong> Vendor Search, Status, Location
                </div>
            </div>
            <div class="card-cms-footer p-3 bg-light border-top d-flex gap-2">
                <a href="<?= url('modules/reports/suppliers.php') ?>" class="btn btn-primary-cms btn-sm flex-grow-1">
                    <i class="bi bi-bar-chart me-1"></i> View Report
                </a>
                <a href="<?= url('modules/reports/export.php?type=suppliers') ?>" class="btn btn-outline-secondary btn-sm" title="Export CSV">
                    <i class="bi bi-download"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Report 3: Supplier Quotation Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card-cms h-100 d-flex flex-column hover-shadow">
            <div class="card-cms-body p-4 flex-grow-1">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;">
                        <i class="bi bi-receipt-cutoff"></i>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Report 03</span>
                </div>
                <h4 class="fw-bold text-dark mb-2">Quotation & Bidding Report</h4>
                <p class="text-muted small mb-3">
                    Audit competitive vendor bidding, price submissions, expiration validity, and winning award allocations.
                </p>
                <div class="bg-light p-2 rounded small text-muted font-monospace mb-3">
                    <strong>Filters:</strong> Date Range, Supplier, Status
                </div>
            </div>
            <div class="card-cms-footer p-3 bg-light border-top d-flex gap-2">
                <a href="<?= url('modules/reports/quotations.php') ?>" class="btn btn-primary-cms btn-sm flex-grow-1">
                    <i class="bi bi-bar-chart me-1"></i> View Report
                </a>
                <a href="<?= url('modules/reports/export.php?type=quotations') ?>" class="btn btn-outline-secondary btn-sm" title="Export CSV">
                    <i class="bi bi-download"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Report 4: Purchase Order Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card-cms h-100 d-flex flex-column hover-shadow">
            <div class="card-cms-body p-4 flex-grow-1">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon success">
                        <i class="bi bi-file-earmark-check-fill"></i>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Report 04</span>
                </div>
                <h4 class="fw-bold text-dark mb-2">Purchase Order Report</h4>
                <p class="text-muted small mb-3">
                    Detailed procurement commitment tracking, approved orders, dispatched supplier POs, and fulfillment progress.
                </p>
                <div class="bg-light p-2 rounded small text-muted font-monospace mb-3">
                    <strong>Filters:</strong> Date range, Supplier, Status
                </div>
            </div>
            <div class="card-cms-footer p-3 bg-light border-top d-flex gap-2">
                <a href="<?= url('modules/reports/purchase_orders.php') ?>" class="btn btn-primary-cms btn-sm flex-grow-1">
                    <i class="bi bi-bar-chart me-1"></i> View Report
                </a>
                <a href="<?= url('modules/reports/export.php?type=purchase_orders') ?>" class="btn btn-outline-secondary btn-sm" title="Export CSV">
                    <i class="bi bi-download"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Report 5: Goods Receiving (GRN) Report -->
    <div class="col-md-6 col-lg-4">
        <div class="card-cms h-100 d-flex flex-column hover-shadow">
            <div class="card-cms-body p-4 flex-grow-1">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon warning">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Report 05</span>
                </div>
                <h4 class="fw-bold text-dark mb-2">Goods Receiving Report</h4>
                <p class="text-muted small mb-3">
                    Warehouse intake audit, shipment inspection remarks, accepted vs defective goods counts, and receiver records.
                </p>
                <div class="bg-light p-2 rounded small text-muted font-monospace mb-3">
                    <strong>Filters:</strong> Date range, Supplier, PO Number, Status
                </div>
            </div>
            <div class="card-cms-footer p-3 bg-light border-top d-flex gap-2">
                <a href="<?= url('modules/reports/goods_receiving.php') ?>" class="btn btn-primary-cms btn-sm flex-grow-1">
                    <i class="bi bi-bar-chart me-1"></i> View Report
                </a>
                <a href="<?= url('modules/reports/export.php?type=goods_receiving') ?>" class="btn btn-outline-secondary btn-sm" title="Export CSV">
                    <i class="bi bi-download"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Export & Printing Center Quick Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card-cms h-100 d-flex flex-column bg-light border-dashed">
            <div class="card-cms-body p-4 flex-grow-1 text-center">
                <div class="stat-icon mx-auto mb-3" style="background-color: #f1f5f9; color: #475569;">
                    <i class="bi bi-printer-fill fs-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Native Exports & Printing</h5>
                <p class="text-muted small mb-3">
                    All reports support instant browser-based A4 printable formats with official company headers and native CSV data export.
                </p>
                <div class="text-muted small">
                    <i class="bi bi-check2-circle text-success me-1"></i> No external PDF/spreadsheet libraries
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
