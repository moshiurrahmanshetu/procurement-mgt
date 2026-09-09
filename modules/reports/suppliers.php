<?php
/**
 * Supplier & Vendor Performance Report
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Supplier & Vendor Report';
$pageSubtitle = 'Vendor Performance, Quotation Participation, and Committed Spend';
$activeNav = 'reports';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

// 1. Parse GET Filters
$search  = sanitizeInput($_GET['search'] ?? '');
$status  = sanitizeInput($_GET['status'] ?? '');
$country = sanitizeInput($_GET['country'] ?? '');

// 2. Fetch Unique Countries for Filter Dropdown
$countries = [];
try {
    $countries = $db->query("
        SELECT DISTINCT country 
        FROM suppliers 
        WHERE deleted_at IS NULL AND country IS NOT NULL AND country != '' 
        ORDER BY country ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log('Supplier Report Country Error: ' . $e->getMessage());
}

// 3. Build Prepared SQL Query
$where = ["s.deleted_at IS NULL"];
$params = [];

if (!empty($search)) {
    $where[] = "(s.name LIKE :search OR s.supplier_code LIKE :search OR s.email LIKE :search OR s.company_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($status)) {
    $where[] = "s.status = :status";
    $params[':status'] = $status;
}

if (!empty($country)) {
    $where[] = "s.country = :country";
    $params[':country'] = $country;
}

$whereClause = implode(' AND ', $where);

// Summary Metrics
$summary = [
    'total_suppliers'   => 0,
    'active_suppliers'  => 0,
    'total_quotes'      => 0,
    'selected_quotes'   => 0,
    'total_pos'         => 0,
    'total_po_spend'    => 0.00
];

$records = [];

try {
    // Fetch Suppliers with correlated subqueries to avoid multiplying aggregate sums
    $sql = "
        SELECT 
            s.*,
            (SELECT sc.name FROM supplier_contacts sc WHERE sc.supplier_id = s.id AND sc.is_primary = 1 LIMIT 1) AS primary_contact,
            (SELECT COUNT(*) FROM quotations q WHERE q.supplier_id = s.id AND q.deleted_at IS NULL) AS total_quotes,
            (SELECT COUNT(*) FROM quotations q WHERE q.supplier_id = s.id AND q.status = 'selected' AND q.deleted_at IS NULL) AS selected_quotes,
            (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id AND po.deleted_at IS NULL) AS total_pos,
            (SELECT COALESCE(SUM(po.grand_total), 0.00) FROM purchase_orders po WHERE po.supplier_id = s.id AND po.status IN ('approved', 'sent', 'partially_received', 'fully_received', 'closed') AND po.deleted_at IS NULL) AS total_spend
        FROM suppliers s
        WHERE {$whereClause}
        ORDER BY s.name ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    foreach ($records as $row) {
        $summary['total_suppliers']++;
        if ($row['status'] === 'active') {
            $summary['active_suppliers']++;
        }
        $summary['total_quotes'] += (int)$row['total_quotes'];
        $summary['selected_quotes'] += (int)$row['selected_quotes'];
        $summary['total_pos'] += (int)$row['total_pos'];
        $summary['total_po_spend'] += (float)$row['total_spend'];
    }
} catch (Exception $e) {
    error_log('Supplier Report Query Error: ' . $e->getMessage());
}

$queryParams = http_build_query([
    'type'    => 'suppliers',
    'search'  => $search,
    'status'  => $status,
    'country' => $country
]);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Report Navigation Breadcrumbs & Top Bar -->
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
        <form method="GET" action="<?= url('modules/reports/suppliers.php') ?>">
            <div class="row g-3">
                <div class="col-sm-6 col-md-5">
                    <label class="form-label-cms small">Search Keyword</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Supplier name, code, email, company..." value="<?= e($search) ?>">
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label-cms small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($status === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        <option value="blacklisted" <?= ($status === 'blacklisted') ? 'selected' : '' ?>>Blacklisted</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-4">
                    <label class="form-label-cms small">Country</label>
                    <select name="country" class="form-select form-select-sm">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= e($c) ?>" <?= ($country === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end pt-2 border-top">
                    <a href="<?= url('modules/reports/suppliers.php') ?>" class="btn btn-outline-secondary btn-sm">
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
                <i class="bi bi-building"></i>
            </div>
            <div>
                <div class="stat-label">Total Suppliers</div>
                <h4 class="stat-value"><?= number_format($summary['total_suppliers']) ?></h4>
                <div class="small text-success"><?= number_format($summary['active_suppliers']) ?> active</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-info border-4">
            <div class="stat-icon" style="background-color: #e0f2fe; color: #0284c7;">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="stat-label">Quotations Received</div>
                <h4 class="stat-value text-info"><?= number_format($summary['total_quotes']) ?></h4>
                <div class="small text-muted font-monospace"><?= number_format($summary['selected_quotes']) ?> selected bids</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success">
                <i class="bi bi-file-earmark-check"></i>
            </div>
            <div>
                <div class="stat-label">Purchase Orders Issued</div>
                <h4 class="stat-value text-success"><?= number_format($summary['total_pos']) ?></h4>
                <div class="small text-muted">Contracts generated</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-warning border-4">
            <div class="stat-icon warning">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <div class="stat-label">Total Committed Spend</div>
                <h4 class="stat-value text-warning-emphasis font-monospace"><?= formatCurrency($summary['total_po_spend']) ?></h4>
                <div class="small text-muted">Cumulative PO value</div>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card-cms">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-table text-primary"></i>
            <span>Supplier Records (<?= count($records) ?>)</span>
        </h3>
    </div>
    <div class="card-cms-body p-0">
        <?php if (!empty($records)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Supplier Code</th>
                            <th>Vendor Name</th>
                            <th>Primary Contact</th>
                            <th>Contact Info</th>
                            <th>Country</th>
                            <th>Status</th>
                            <th class="text-center">Quotes (Won)</th>
                            <th class="text-center">POs</th>
                            <th class="text-end">Committed Spend</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('modules/suppliers/view.php?id=' . $row['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($row['supplier_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($row['name']) ?></div>
                                    <?php if (!empty($row['company_name']) && $row['company_name'] !== $row['name']): ?>
                                        <div class="text-muted small" style="font-size: 0.6875rem;"><?= e($row['company_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-dark">
                                    <?= e($row['primary_contact'] ?? '-') ?>
                                </td>
                                <td class="small">
                                    <?php if (!empty($row['email'])): ?>
                                        <div><a href="mailto:<?= e($row['email']) ?>" class="text-decoration-none text-muted"><?= e($row['email']) ?></a></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['phone'])): ?>
                                        <div class="text-muted font-monospace" style="font-size: 0.6875rem;"><?= e($row['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= e($row['country'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= getSupplierStatusBadge($row['status']) ?>
                                </td>
                                <td class="text-center small font-monospace">
                                    <span class="badge bg-light text-dark border">
                                        <?= (int)$row['total_quotes'] ?> (<?= (int)$row['selected_quotes'] ?> won)
                                    </span>
                                </td>
                                <td class="text-center small font-monospace fw-semibold text-dark">
                                    <?= (int)$row['total_pos'] ?>
                                </td>
                                <td class="text-end small font-monospace fw-bold text-dark">
                                    <?= formatCurrency($row['total_spend']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('modules/suppliers/view.php?id=' . $row['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View Vendor Profile">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="8" class="text-end">Total Spend for Filtered Vendors:</td>
                            <td class="text-end font-monospace"><?= formatCurrency($summary['total_po_spend']) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-building-slash fs-1 d-block mb-2 text-light"></i>
                <p class="mb-0">No records found for the selected filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
