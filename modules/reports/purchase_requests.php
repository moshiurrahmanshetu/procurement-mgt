<?php
/**
 * Purchase Request Report
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Purchase Request Report';
$pageSubtitle = 'Requisition Activity, Department Allocations, and Approval Status';
$activeNav = 'reports';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$isRequester = userHasRole('requester');
$db = getDb();

// 1. Parse GET Filters
$dateFrom     = sanitizeInput($_GET['date_from'] ?? '');
$dateTo       = sanitizeInput($_GET['date_to'] ?? '');
$departmentId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$requestedBy  = !empty($_GET['requested_by']) ? (int)$_GET['requested_by'] : null;
$status       = sanitizeInput($_GET['status'] ?? '');
$priority     = sanitizeInput($_GET['priority'] ?? '');

// If user is strictly a requester, scope to their own requests
if ($isRequester && !$isAdmin && !$isManager && !$isOfficer) {
    $requestedBy = (int)$user['id'];
}

// 2. Fetch Filter Lookup Options
$departments = [];
$usersList = [];
try {
    $departments = $db->query("SELECT id, name, code FROM departments WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
    $usersList = $db->query("SELECT id, full_name, username FROM users WHERE deleted_at IS NULL ORDER BY full_name ASC")->fetchAll();
} catch (Exception $e) {
    error_log('PR Report Lookups Error: ' . $e->getMessage());
}

// 3. Build Prepared SQL Query with Filters
$where = ["pr.deleted_at IS NULL"];
$params = [];

if (!empty($dateFrom)) {
    $where[] = "pr.request_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "pr.request_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

if (!empty($departmentId)) {
    $where[] = "pr.department_id = :dept_id";
    $params[':dept_id'] = $departmentId;
}

if (!empty($requestedBy)) {
    $where[] = "pr.requested_by = :req_by";
    $params[':req_by'] = $requestedBy;
}

if (!empty($status)) {
    $where[] = "pr.status = :status";
    $params[':status'] = $status;
}

if (!empty($priority)) {
    $where[] = "pr.priority = :priority";
    $params[':priority'] = $priority;
}

$whereClause = implode(' AND ', $where);

// Summary Metrics
$summary = [
    'total_count'     => 0,
    'total_amount'    => 0.00,
    'approved_count'  => 0,
    'approved_amount' => 0.00,
    'pending_count'   => 0,
    'pending_amount'  => 0.00,
    'rejected_count'  => 0,
    'draft_count'     => 0
];

$records = [];

try {
    // Calculate accurate aggregate totals
    $sumSql = "
        SELECT 
            COUNT(*) AS total_count,
            COALESCE(SUM(pr.estimated_total), 0.00) AS total_amount,
            SUM(CASE WHEN pr.status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
            COALESCE(SUM(CASE WHEN pr.status = 'approved' THEN pr.estimated_total ELSE 0 END), 0.00) AS approved_amount,
            SUM(CASE WHEN pr.status = 'pending_approval' THEN 1 ELSE 0 END) AS pending_count,
            COALESCE(SUM(CASE WHEN pr.status = 'pending_approval' THEN pr.estimated_total ELSE 0 END), 0.00) AS pending_amount,
            SUM(CASE WHEN pr.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
            SUM(CASE WHEN pr.status = 'draft' THEN 1 ELSE 0 END) AS draft_count
        FROM purchase_requests pr
        WHERE {$whereClause}
    ";
    $sumStmt = $db->prepare($sumSql);
    $sumStmt->execute($params);
    $sumRow = $sumStmt->fetch();
    if ($sumRow) {
        $summary = [
            'total_count'     => (int)$sumRow['total_count'],
            'total_amount'    => (float)$sumRow['total_amount'],
            'approved_count'  => (int)$sumRow['approved_count'],
            'approved_amount' => (float)$sumRow['approved_amount'],
            'pending_count'   => (int)$sumRow['pending_count'],
            'pending_amount'  => (float)$sumRow['pending_amount'],
            'rejected_count'  => (int)$sumRow['rejected_count'],
            'draft_count'     => (int)$sumRow['draft_count']
        ];
    }

    // Fetch Table Data
    $dataSql = "
        SELECT 
            pr.*,
            d.name AS department_name,
            d.code AS department_code,
            u.full_name AS requester_name,
            appr.full_name AS approver_name
        FROM purchase_requests pr
        LEFT JOIN departments d ON pr.department_id = d.id
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN users appr ON pr.approved_by = appr.id
        WHERE {$whereClause}
        ORDER BY pr.request_date DESC, pr.id DESC
    ";
    $dataStmt = $db->prepare($dataSql);
    $dataStmt->execute($params);
    $records = $dataStmt->fetchAll();
} catch (Exception $e) {
    error_log('PR Report Data Query Error: ' . $e->getMessage());
}

// Build Export/Print Query String
$queryParams = http_build_query([
    'type'          => 'purchase_requests',
    'date_from'     => $dateFrom,
    'date_to'       => $dateTo,
    'department_id' => $departmentId,
    'requested_by'  => $requestedBy,
    'status'        => $status,
    'priority'      => $priority
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

<!-- Filter Box -->
<div class="card-cms mb-4">
    <div class="card-cms-header">
        <h3 class="card-cms-title">
            <i class="bi bi-funnel-fill text-primary"></i>
            <span>Filter Criteria</span>
        </h3>
    </div>
    <div class="card-cms-body p-3">
        <form method="GET" action="<?= url('modules/reports/purchase_requests.php') ?>">
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
                    <label class="form-label-cms small">Department</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= ($departmentId == $dept['id']) ? 'selected' : '' ?>>
                                <?= e($dept['name']) ?> (<?= e($dept['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$isRequester || $isAdmin || $isManager || $isOfficer): ?>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label-cms small">Requester</label>
                        <select name="requested_by" class="form-select form-select-sm">
                            <option value="">All Requesters</option>
                            <?php foreach ($usersList as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($requestedBy == $u['id']) ? 'selected' : '' ?>>
                                    <?= e($u['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label-cms small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= ($status === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_approval" <?= ($status === 'pending_approval') ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="approved" <?= ($status === 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= ($status === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                        <option value="cancelled" <?= ($status === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        <option value="completed" <?= ($status === 'completed') ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label-cms small">Priority</label>
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">All Priorities</option>
                        <option value="low" <?= ($priority === 'low') ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= ($priority === 'medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= ($priority === 'high') ? 'selected' : '' ?>>High</option>
                        <option value="urgent" <?= ($priority === 'urgent') ? 'selected' : '' ?>>Urgent</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end pt-2 border-top">
                    <a href="<?= url('modules/reports/purchase_requests.php') ?>" class="btn btn-outline-secondary btn-sm">
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
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div>
                <div class="stat-label">Total Filtered PRs</div>
                <h4 class="stat-value"><?= number_format($summary['total_count']) ?></h4>
                <div class="small text-muted font-monospace"><?= formatCurrency($summary['total_amount']) ?> total</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-success border-4">
            <div class="stat-icon success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div>
                <div class="stat-label">Approved Requisitions</div>
                <h4 class="stat-value text-success"><?= number_format($summary['approved_count']) ?></h4>
                <div class="small text-muted font-monospace"><?= formatCurrency($summary['approved_amount']) ?> approved</div>
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
                <div class="small text-muted font-monospace"><?= formatCurrency($summary['pending_amount']) ?> in queue</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card border-start border-danger border-4">
            <div class="stat-icon" style="background-color: #fef2f2; color: #dc2626;">
                <i class="bi bi-x-circle"></i>
            </div>
            <div>
                <div class="stat-label">Rejected / Cancelled</div>
                <h4 class="stat-value text-danger"><?= number_format($summary['rejected_count']) ?></h4>
                <div class="small text-muted font-monospace"><?= number_format($summary['draft_count']) ?> drafts</div>
            </div>
        </div>
    </div>
</div>

<!-- Report Data Table -->
<div class="card-cms">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-table text-primary"></i>
            <span>Requisition Records (<?= count($records) ?>)</span>
        </h3>
    </div>
    <div class="card-cms-body p-0">
        <?php if (!empty($records)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th>PR Number</th>
                            <th>Date</th>
                            <th>Requester</th>
                            <th>Department</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th class="text-end">Est. Subtotal</th>
                            <th class="text-end">Est. Total</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('modules/purchase_requests/view.php?id=' . $row['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($row['request_no']) ?>
                                    </a>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= formatDate($row['request_date'], 'd M Y') ?>
                                </td>
                                <td class="small fw-medium text-dark">
                                    <?= e($row['requester_name']) ?>
                                </td>
                                <td class="small">
                                    <span class="badge bg-light text-dark border"><?= e($row['department_name']) ?></span>
                                </td>
                                <td>
                                    <?= getPriorityBadge($row['priority']) ?>
                                </td>
                                <td>
                                    <?= getStatusBadge($row['status']) ?>
                                </td>
                                <td class="text-end small font-monospace text-muted">
                                    <?= formatCurrency($row['estimated_subtotal']) ?>
                                </td>
                                <td class="text-end small font-monospace fw-bold text-dark">
                                    <?= formatCurrency($row['estimated_total']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('modules/purchase_requests/view.php?id=' . $row['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View Request Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="7" class="text-end">Total Calculated Amount:</td>
                            <td class="text-end font-monospace"><?= formatCurrency($summary['total_amount']) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                <p class="mb-0">No records found for the selected filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
