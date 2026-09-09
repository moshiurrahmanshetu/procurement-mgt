<?php
/**
 * Goods Receiving (GRN) Report
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Goods Receiving Report';
$pageSubtitle = 'Warehouse Intake, Shipment Receipts, Quality Inspection, and Defect Audits';
$activeNav = 'reports';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

// 1. Parse GET Filters
$dateFrom   = sanitizeInput($_GET['date_from'] ?? '');
$dateTo     = sanitizeInput($_GET['date_to'] ?? '');
$supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
$poId       = !empty($_GET['po_id']) ? (int)$_GET['po_id'] : null;
$status     = sanitizeInput($_GET['status'] ?? '');

// 2. Fetch Lookups
$suppliers = [];
$orders = [];
try {
    $suppliers = $db->query("SELECT id, name, supplier_code FROM suppliers WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
    $orders = $db->query("SELECT id, po_no FROM purchase_orders WHERE deleted_at IS NULL ORDER BY po_no DESC")->fetchAll();
} catch (Exception $e) {
    error_log('GRN Report Lookups Error: ' . $e->getMessage());
}

// 3. Build Prepared SQL Query
$where = ["gr.deleted_at IS NULL"];
$params = [];

if (!empty($dateFrom)) {
    $where[] = "gr.receipt_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "gr.receipt_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

if (!empty($supplierId)) {
    $where[] = "gr.supplier_id = :sup_id";
    $params[':sup_id'] = $supplierId;
}

if (!empty($poId)) {
    $where[] = "gr.purchase_order_id = :po_id";
    $params[':po_id'] = $poId;
}

if (!empty($status)) {
    $where[] = "gr.status = :status";
    $params[':status'] = $status;
}

$whereClause = implode(' AND ', $where);

// Summary Metrics
$summary = [
    'total_count'    => 0,
    'posted_count'   => 0,
    'draft_count'    => 0,
    'total_received' => 0.00,
    'total_rejected' => 0.00
];

$records = [];

try {
    // Aggregates
    $sumSql = "
        SELECT 
            COUNT(DISTINCT gr.id) AS total_count,
            SUM(CASE WHEN gr.status = 'posted' THEN 1 ELSE 0 END) AS posted_count,
            SUM(CASE WHEN gr.status = 'draft' THEN 1 ELSE 0 END) AS draft_count,
            COALESCE(SUM(gri.received_qty), 0.00) AS total_received,
            COALESCE(SUM(gri.rejected_qty), 0.00) AS total_rejected
        FROM goods_receipts gr
        LEFT JOIN goods_receipt_items gri ON gri.goods_receipt_id = gr.id
        WHERE {$whereClause}
    ";
    $sumStmt = $db->prepare($sumSql);
    $sumStmt->execute($params);
    $sumRow = $sumStmt->fetch();
    if ($sumRow) {
        $summary = [
            'total_count'    => (int)$sumRow['total_count'],
            'posted_count'   => (int)$sumRow['posted_count'],
            'draft_count'    => (int)$sumRow['draft_count'],
            'total_received' => (float)$sumRow['total_received'],
            'total_rejected' => (float)$sumRow['total_rejected']
        ];
    }

    $dataSql = "
        SELECT 
            gr.*,
            po.po_no,
            s.name AS supplier_name,
            s.supplier_code,
            u.full_name AS receiver_name,
            (SELECT COALESCE(SUM(received_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_rec_qty,
            (SELECT COALESCE(SUM(rejected_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_rej_qty,
            (SELECT COUNT(*) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS line_items_count
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN suppliers s ON s.id = gr.supplier_id
        LEFT JOIN users u ON u.id = gr.received_by
        WHERE {$whereClause}
        ORDER BY gr.receipt_date DESC, gr.id DESC
    ";
    $dataStmt = $db->prepare($dataSql);
    $dataStmt->execute($params);
    $records = $dataStmt->fetchAll();
} catch (Exception $e) {
    error_log('GRN Report Data Error: ' . $e->getMessage());
}

$queryParams = http_build_query([
    'type'        => 'goods_receiving',
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'supplier_id' => $supplierId,
    'po_id'       => $poId,
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
        <form method="GET" action="<?= url('modules/reports/goods_receiving.php') ?>">
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <label class="form-label-cms small">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label-cms small">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label-cms small">Supplier</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= ($supplierId == $sup['id']) ? 'selected' : '' ?>>
                                <?= e($sup['name']) ?> (<?= e($sup['supplier_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label-cms small">Purchase Order</label>
                    <select name="po_id" class="form-select form-select-sm">
                        <option value="">All POs</option>
                        <?php foreach ($orders as $ord): ?>
                            <option value="<?= $ord['id'] ?>" <?= ($poId == $ord['id']) ? 'selected' : '' ?>>
                                <?= e($ord['po_no']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label-cms small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= ($status === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="posted" <?= ($status === 'posted') ? 'selected' : '' ?>>Posted (Locked)</option>
                        <option value="cancelled" <?= ($status === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end pt-2 border-top">
                    <a href="<?= url('modules/reports/goods_receiving.php') ?>" class="btn btn-outline-secondary btn-sm">
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
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <div class="stat-label">Total Shipments</div>
                <h4 class="stat-value"><?= number_format($summary['total_count']) ?></h4>
                <div class="small text-muted"><?= number_format($summary['draft_count']) ?> pending drafts</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <div class="stat-label">Posted Intake (Locked)</div>
                <h4 class="stat-value text-success"><?= number_format($summary['posted_count']) ?></h4>
                <div class="small text-muted">Permanent warehouse records</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-info border-4">
            <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;">
                <i class="bi bi-boxes"></i>
            </div>
            <div>
                <div class="stat-label">Accepted Units</div>
                <h4 class="stat-value text-info font-monospace">+<?= number_format($summary['total_received'], 0) ?></h4>
                <div class="small text-muted">Stock received in good order</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-danger border-4">
            <div class="stat-icon" style="background-color: #fef2f2; color: #dc2626;">
                <i class="bi bi-x-octagon-fill"></i>
            </div>
            <div>
                <div class="stat-label">Damaged / Rejected</div>
                <h4 class="stat-value text-danger font-monospace"><?= number_format($summary['total_rejected'], 0) ?></h4>
                <div class="small text-muted">Returned or defect units</div>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card-cms">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-table text-primary"></i>
            <span>Goods Receipt Records (<?= count($records) ?>)</span>
        </h3>
    </div>
    <div class="card-cms-body p-0">
        <?php if (!empty($records)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th>GRN Number</th>
                            <th>Purchase Order</th>
                            <th>Supplier Vendor</th>
                            <th>Receipt Date</th>
                            <th>Received By</th>
                            <th>Status</th>
                            <th class="text-center">Accepted Qty</th>
                            <th class="text-center">Rejected Qty</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('modules/goods_receiving/view.php?id=' . $row['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($row['grn_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?= url('modules/purchase_orders/view.php?id=' . $row['purchase_order_id']) ?>" class="text-dark fw-semibold text-decoration-none small font-monospace">
                                        <?= e($row['po_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark small"><?= e($row['supplier_name']) ?></div>
                                    <span class="text-muted font-monospace small" style="font-size: 0.6875rem;"><?= e($row['supplier_code']) ?></span>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= formatDate($row['receipt_date'], 'd M Y') ?>
                                </td>
                                <td class="small text-dark fw-medium">
                                    <?= e($row['receiver_name'] ?? 'Authorized Officer') ?>
                                </td>
                                <td>
                                    <?= getGrnStatusBadge($row['status']) ?>
                                </td>
                                <td class="text-center small font-monospace fw-bold text-success">
                                    +<?= number_format((float)$row['total_rec_qty'], 0) ?>
                                </td>
                                <td class="text-center small font-monospace fw-semibold <?= ((float)$row['total_rej_qty'] > 0) ? 'text-danger' : 'text-muted' ?>">
                                    <?= number_format((float)$row['total_rej_qty'], 0) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('modules/goods_receiving/view.php?id=' . $row['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View GRN">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="6" class="text-end">Cumulative Accepted vs Rejected Totals:</td>
                            <td class="text-center font-monospace text-success">+<?= number_format($summary['total_received'], 0) ?></td>
                            <td class="text-center font-monospace text-danger"><?= number_format($summary['total_rejected'], 0) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-box-arrow-in-down fs-1 d-block mb-2 text-light"></i>
                <p class="mb-0">No goods receipts found for the selected filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
