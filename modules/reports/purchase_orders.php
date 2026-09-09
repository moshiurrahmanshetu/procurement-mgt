<?php
/**
 * Purchase Order Report
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Purchase Order Report';
$pageSubtitle = 'Order Commitments, Dispatched Contracts, and Fulfillment Status';
$activeNav = 'reports';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

// 1. Parse GET Filters
$dateFrom   = sanitizeInput($_GET['date_from'] ?? '');
$dateTo     = sanitizeInput($_GET['date_to'] ?? '');
$supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
$status     = sanitizeInput($_GET['status'] ?? '');

// 2. Fetch Supplier List for Dropdown
$suppliers = [];
try {
    $suppliers = $db->query("SELECT id, name, supplier_code FROM suppliers WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    error_log('PO Report Supplier Lookup Error: ' . $e->getMessage());
}

// 3. Build Prepared SQL Query
$where = ["po.deleted_at IS NULL"];
$params = [];

if (!empty($dateFrom)) {
    $where[] = "po.po_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "po.po_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

if (!empty($supplierId)) {
    $where[] = "po.supplier_id = :sup_id";
    $params[':sup_id'] = $supplierId;
}

if (!empty($status)) {
    $where[] = "po.status = :status";
    $params[':status'] = $status;
}

$whereClause = implode(' AND ', $where);

// Summary Metrics
$summary = [
    'total_count'     => 0,
    'total_amount'    => 0.00,
    'sent_count'      => 0,
    'sent_amount'     => 0.00,
    'fulfilled_count' => 0,
    'pending_count'   => 0
];

$records = [];

try {
    $sumSql = "
        SELECT 
            COUNT(*) AS total_count,
            COALESCE(SUM(po.grand_total), 0.00) AS total_amount,
            SUM(CASE WHEN po.status IN ('sent', 'partially_received', 'fully_received') THEN 1 ELSE 0 END) AS sent_count,
            COALESCE(SUM(CASE WHEN po.status IN ('sent', 'partially_received', 'fully_received') THEN po.grand_total ELSE 0 END), 0.00) AS sent_amount,
            SUM(CASE WHEN po.status = 'fully_received' THEN 1 ELSE 0 END) AS fulfilled_count,
            SUM(CASE WHEN po.status = 'pending_approval' THEN 1 ELSE 0 END) AS pending_count
        FROM purchase_orders po
        WHERE {$whereClause}
    ";
    $sumStmt = $db->prepare($sumSql);
    $sumStmt->execute($params);
    $sumRow = $sumStmt->fetch();
    if ($sumRow) {
        $summary = [
            'total_count'     => (int)$sumRow['total_count'],
            'total_amount'    => (float)$sumRow['total_amount'],
            'sent_count'      => (int)$sumRow['sent_count'],
            'sent_amount'     => (float)$sumRow['sent_amount'],
            'fulfilled_count' => (int)$sumRow['fulfilled_count'],
            'pending_count'   => (int)$sumRow['pending_count']
        ];
    }

    $dataSql = "
        SELECT 
            po.*,
            pr.request_no,
            s.name AS supplier_name,
            s.supplier_code,
            u.full_name AS creator_name,
            appr.full_name AS approver_name
        FROM purchase_orders po
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN suppliers s ON s.id = po.supplier_id
        LEFT JOIN users u ON u.id = po.created_by
        LEFT JOIN users appr ON appr.id = po.approved_by
        WHERE {$whereClause}
        ORDER BY po.po_date DESC, po.id DESC
    ";
    $dataStmt = $db->prepare($dataSql);
    $dataStmt->execute($params);
    $records = $dataStmt->fetchAll();
} catch (Exception $e) {
    error_log('PO Report Data Error: ' . $e->getMessage());
}

$queryParams = http_build_query([
    'type'        => 'purchase_orders',
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'supplier_id' => $supplierId,
    'status'      => $status
]);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Top Bar -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <a href="<?= url('modules/reports/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Reports Hub
        </a>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('modules/reports/print.php?' . $queryParams) ?>" target="_blank" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer me-1"></i> Print Report
        </a>
        <a href="<?= url('modules/reports/export.php?' . $queryParams) ?>" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export to CSV
        </a>
    </div>
</div>

<!-- Filter Box -->
<div class="card-cms mb-4">
    <div class="card-cms-header">
        <h3 class="card-cms-title">
            <i class="bi bi-funnel-fill text-primary"></i>
            <span>Filter Criteria</span>
        </h3>
    </div>
    <div class="card-cms-body p-3">
        <form method="GET" action="<?= url('modules/reports/purchase_orders.php') ?>">
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <label class="form-label-cms small">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label-cms small">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label-cms small">Supplier / Vendor</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= ($supplierId == $sup['id']) ? 'selected' : '' ?>>
                                <?= e($sup['name']) ?> (<?= e($sup['supplier_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label-cms small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= ($status === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_approval" <?= ($status === 'pending_approval') ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="approved" <?= ($status === 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="sent" <?= ($status === 'sent') ? 'selected' : '' ?>>Sent to Supplier</option>
                        <option value="partially_received" <?= ($status === 'partially_received') ? 'selected' : '' ?>>Partially Received</option>
                        <option value="fully_received" <?= ($status === 'fully_received') ? 'selected' : '' ?>>Fully Received</option>
                        <option value="cancelled" <?= ($status === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        <option value="closed" <?= ($status === 'closed') ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end pt-2 border-top">
                    <a href="<?= url('modules/reports/purchase_orders.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Clear Filters
                    </a>
                    <button type="submit" class="btn btn-primary-cms btn-sm px-3">
                        <i class="bi bi-search me-1"></i> Apply Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Metrics Strip -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-primary border-4">
            <div class="stat-icon primary">
                <i class="bi bi-file-earmark-check"></i>
            </div>
            <div>
                <div class="stat-label">Total Filtered POs</div>
                <h4 class="stat-value"><?= number_format($summary['total_count']) ?></h4>
                <div class="small text-muted font-monospace"><?= formatCurrency($summary['total_amount']) ?> gross</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success">
                <i class="bi bi-send-check-fill"></i>
            </div>
            <div>
                <div class="stat-label">Dispatched Commitments</div>
                <h4 class="stat-value text-success"><?= number_format($summary['sent_count']) ?></h4>
                <div class="small text-muted font-monospace"><?= formatCurrency($summary['sent_amount']) ?> active spend</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-info border-4">
            <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;">
                <i class="bi bi-box2-check-fill"></i>
            </div>
            <div>
                <div class="stat-label">Fully Fulfilled</div>
                <h4 class="stat-value text-info"><?= number_format($summary['fulfilled_count']) ?></h4>
                <div class="small text-muted">All goods accepted</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-warning border-4">
            <div class="stat-icon warning">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-label">Pending Approval</div>
                <h4 class="stat-value text-warning-emphasis"><?= number_format($summary['pending_count']) ?></h4>
                <div class="small text-muted">Manager review queue</div>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card-cms">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-table text-primary"></i>
            <span>Purchase Order Records (<?= count($records) ?>)</span>
        </h3>
    </div>
    <div class="card-cms-body p-0">
        <?php if (!empty($records)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>PR Reference</th>
                            <th>Vendor Supplier</th>
                            <th>PO Date</th>
                            <th>Expected Delivery</th>
                            <th>Status</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Grand Total</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('modules/purchase_orders/view.php?id=' . $row['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($row['po_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?= url('modules/purchase_requests/view.php?id=' . $row['purchase_request_id']) ?>" class="text-dark fw-semibold text-decoration-none small font-monospace">
                                        <?= e($row['request_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark small"><?= e($row['supplier_name']) ?></div>
                                    <span class="text-muted font-monospace small" style="font-size: 0.6875rem;"><?= e($row['supplier_code']) ?></span>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= formatDate($row['po_date'], 'd M Y') ?>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= !empty($row['expected_delivery_date']) ? formatDate($row['expected_delivery_date'], 'd M Y') : '-' ?>
                                </td>
                                <td>
                                    <?= getPoStatusBadge($row['status']) ?>
                                </td>
                                <td class="text-end small font-monospace text-muted">
                                    <?= formatCurrency($row['subtotal']) ?>
                                </td>
                                <td class="text-end small font-monospace fw-bold text-dark">
                                    <?= formatCurrency($row['grand_total']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('modules/purchase_orders/view.php?id=' . $row['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View Order Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="7" class="text-end">Total Calculated Spend:</td>
                            <td class="text-end font-monospace"><?= formatCurrency($summary['total_amount']) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-light"></i>
                <p class="mb-0">No purchase orders found for the selected filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
