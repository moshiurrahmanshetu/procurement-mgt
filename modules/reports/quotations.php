<?php
/**
 * Quotation & Bidding Report
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Quotation & Bidding Report';
$pageSubtitle = 'Competitive Bidding Analysis, Price Submissions, and Award Allocations';
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
    error_log('Quotation Report Supplier Lookup Error: ' . $e->getMessage());
}

// 3. Build Prepared SQL Query
$where = ["q.deleted_at IS NULL"];
$params = [];

if (!empty($dateFrom)) {
    $where[] = "q.quotation_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "q.quotation_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

if (!empty($supplierId)) {
    $where[] = "q.supplier_id = :sup_id";
    $params[':sup_id'] = $supplierId;
}

if (!empty($status)) {
    $where[] = "q.status = :status";
    $params[':status'] = $status;
}

$whereClause = implode(' AND ', $where);

// Summary Metrics
$summary = [
    'total_count'    => 0,
    'total_amount'   => 0.00,
    'selected_count' => 0,
    'selected_amount'=> 0.00,
    'under_review'   => 0,
    'rejected_count' => 0
];

$records = [];

try {
    $sumSql = "
        SELECT 
            COUNT(*) AS total_count,
            COALESCE(SUM(q.total_amount), 0.00) AS total_amount,
            SUM(CASE WHEN q.status = 'selected' THEN 1 ELSE 0 END) AS selected_count,
            COALESCE(SUM(CASE WHEN q.status = 'selected' THEN q.total_amount ELSE 0 END), 0.00) AS selected_amount,
            SUM(CASE WHEN q.status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) AS under_review,
            SUM(CASE WHEN q.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
        FROM quotations q
        WHERE {$whereClause}
    ";
    $sumStmt = $db->prepare($sumSql);
    $sumStmt->execute($params);
    $sumRow = $sumStmt->fetch();
    if ($sumRow) {
        $summary = [
            'total_count'    => (int)$sumRow['total_count'],
            'total_amount'   => (float)$sumRow['total_amount'],
            'selected_count' => (int)$sumRow['selected_count'],
            'selected_amount'=> (float)$sumRow['selected_amount'],
            'under_review'   => (int)$sumRow['under_review'],
            'rejected_count' => (int)$sumRow['rejected_count']
        ];
    }

    $dataSql = "
        SELECT 
            q.*,
            pr.request_no,
            s.name AS supplier_name,
            s.supplier_code,
            u.full_name AS creator_name
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        LEFT JOIN users u ON u.id = q.created_by
        WHERE {$whereClause}
        ORDER BY q.quotation_date DESC, q.id DESC
    ";
    $dataStmt = $db->prepare($dataSql);
    $dataStmt->execute($params);
    $records = $dataStmt->fetchAll();
} catch (Exception $e) {
    error_log('Quotation Report Data Error: ' . $e->getMessage());
}

$queryParams = http_build_query([
    'type'        => 'quotations',
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

<!-- Filter Criteria Card -->
<div class="card-cms mb-4">
    <div class="card-cms-header">
        <h3 class="card-cms-title">
            <i class="bi bi-funnel-fill text-primary"></i>
            <span>Filter Criteria</span>
        </h3>
    </div>
    <div class="card-cms-body p-3">
        <form method="GET" action="<?= url('modules/reports/quotations.php') ?>">
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
                        <option value="submitted" <?= ($status === 'submitted') ? 'selected' : '' ?>>Submitted</option>
                        <option value="under_review" <?= ($status === 'under_review') ? 'selected' : '' ?>>Under Review</option>
                        <option value="selected" <?= ($status === 'selected') ? 'selected' : '' ?>>Selected (Awarded)</option>
                        <option value="rejected" <?= ($status === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                        <option value="expired" <?= ($status === 'expired') ? 'selected' : '' ?>>Expired</option>
                        <option value="cancelled" <?= ($status === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end pt-2 border-top">
                    <a href="<?= url('modules/reports/quotations.php') ?>" class="btn btn-outline-secondary btn-sm">
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
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="stat-label">Total Quotations</div>
                <h4 class="stat-value"><?= number_format($summary['total_count']) ?></h4>
                <div class="small text-muted font-monospace"><?= formatCurrency($summary['total_amount']) ?> gross</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div>
                <div class="stat-label">Awarded Contracts</div>
                <h4 class="stat-value text-success"><?= number_format($summary['selected_count']) ?></h4>
                <div class="small text-muted font-monospace"><?= formatCurrency($summary['selected_amount']) ?> awarded</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-warning border-4">
            <div class="stat-icon warning">
                <i class="bi bi-search"></i>
            </div>
            <div>
                <div class="stat-label">Under Evaluation</div>
                <h4 class="stat-value text-warning-emphasis"><?= number_format($summary['under_review']) ?></h4>
                <div class="small text-muted">Awaiting selection</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-danger border-4">
            <div class="stat-icon" style="background-color: #fef2f2; color: #dc2626;">
                <i class="bi bi-x-circle"></i>
            </div>
            <div>
                <div class="stat-label">Rejected / Non-winning</div>
                <h4 class="stat-value text-danger"><?= number_format($summary['rejected_count']) ?></h4>
                <div class="small text-muted">Outbid quotations</div>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card-cms">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-table text-primary"></i>
            <span>Quotation Records (<?= count($records) ?>)</span>
        </h3>
    </div>
    <div class="card-cms-body p-0">
        <?php if (!empty($records)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Quote No</th>
                            <th>PR Reference</th>
                            <th>Supplier Vendor</th>
                            <th>Quote Date</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('modules/quotations/view.php?id=' . $row['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($row['quotation_no']) ?>
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
                                    <?= formatDate($row['quotation_date'], 'd M Y') ?>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= !empty($row['valid_until']) ? formatDate($row['valid_until'], 'd M Y') : 'Open' ?>
                                </td>
                                <td>
                                    <?= getQuotationStatusBadge($row['status']) ?>
                                </td>
                                <td class="text-end small font-monospace text-muted">
                                    <?= formatCurrency($row['subtotal']) ?>
                                </td>
                                <td class="text-end small font-monospace fw-bold text-dark">
                                    <?= formatCurrency($row['total_amount']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('modules/quotations/view.php?id=' . $row['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View Quotation">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="7" class="text-end">Gross Filtered Total:</td>
                            <td class="text-end font-monospace"><?= formatCurrency($summary['total_amount']) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-receipt-cutoff fs-1 d-block mb-2 text-light"></i>
                <p class="mb-0">No quotation records found for the selected filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
