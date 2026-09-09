<?php
/**
 * Quotation Management List View
 * Procurement Management CMS - Phase 03
 */

$pageTitle = 'Quotations';
$pageSubtitle = 'Manage Supplier Bids & RFQ Responses';
$activeNav = 'quotations';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canManage = $isAdmin || $isManager || $isOfficer;

// 1. Capture Filters
$search = sanitizeInput($_GET['search'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$supplierFilter = sanitizeInput($_GET['supplier_id'] ?? '');
$prFilter = sanitizeInput($_GET['pr_id'] ?? '');
$dateFrom = sanitizeInput($_GET['date_from'] ?? '');
$dateTo = sanitizeInput($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 2. Fetch Active Suppliers for dropdown filter
$allSuppliers = [];
try {
    $sStmt = $db->query("SELECT id, name, supplier_code FROM suppliers WHERE deleted_at IS NULL ORDER BY name ASC");
    $allSuppliers = $sStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching suppliers for filter: ' . $e->getMessage());
}

// 3. Fetch Approved Purchase Requests for filter dropdown
$approvedPRs = [];
try {
    $prStmt = $db->query("SELECT id, request_no, purpose FROM purchase_requests WHERE status = 'approved' AND deleted_at IS NULL ORDER BY id DESC");
    $approvedPRs = $prStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching approved PRs: ' . $e->getMessage());
}

// 4. Build Query Conditions
$whereClauses = ['q.deleted_at IS NULL'];
$params = [];

if (!empty($search)) {
    $whereClauses[] = '(q.quotation_no LIKE :search_qno OR pr.request_no LIKE :search_prno OR s.name LIKE :search_sname)';
    $params[':search_qno'] = '%' . $search . '%';
    $params[':search_prno'] = '%' . $search . '%';
    $params[':search_sname'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $whereClauses[] = 'q.status = :status';
    $params[':status'] = $statusFilter;
}

if (!empty($supplierFilter)) {
    $whereClauses[] = 'q.supplier_id = :supplier_id';
    $params[':supplier_id'] = (int)$supplierFilter;
}

if (!empty($prFilter)) {
    $whereClauses[] = 'q.purchase_request_id = :pr_id';
    $params[':pr_id'] = (int)$prFilter;
}

if (!empty($dateFrom)) {
    $whereClauses[] = 'q.quotation_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClauses[] = 'q.quotation_date <= :date_to';
    $params[':date_to'] = $dateTo;
}

$whereSql = implode(' AND ', $whereClauses);

// Count Total Records
$totalRecords = 0;
try {
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE {$whereSql}
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
} catch (Exception $e) {
    error_log('Error counting quotations: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Quotations List
$quotations = [];
try {
    $dataSql = "
        SELECT q.*,
               pr.request_no,
               pr.purpose AS pr_purpose,
               pr.status AS pr_status,
               s.name AS supplier_name,
               s.supplier_code,
               u.full_name AS creator_name,
               (SELECT COUNT(*) FROM quotations q2 WHERE q2.purchase_request_id = q.purchase_request_id AND q2.deleted_at IS NULL) AS competing_bids_count
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        LEFT JOIN users u ON u.id = q.created_by
        WHERE {$whereSql}
        ORDER BY q.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($dataSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $quotations = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching quotations: ' . $e->getMessage());
}

// Metrics counts for top summary cards
$metrics = [
    'total'         => 0,
    'submitted'     => 0,
    'under_review'  => 0,
    'selected'      => 0,
    'awarded_value' => 0.00
];
try {
    $mStmt = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) AS under_review,
            SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) AS selected,
            SUM(CASE WHEN status = 'selected' THEN total_amount ELSE 0 END) AS awarded_value
        FROM quotations
        WHERE deleted_at IS NULL
    ");
    $metrics = $mStmt->fetch() ?: $metrics;
} catch (Exception $e) {
    error_log('Error fetching quotation metrics: ' . $e->getMessage());
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('modules/dashboard/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Quotations</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Supplier Quotations</h1>
            <p class="text-muted small mb-0">Record, review, compare vendor quotes, and award winning bids.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canManage): ?>
                <a href="<?= url('modules/quotations/create.php') ?>" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span>Create Quotation</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-primary border-4 h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Quotations</div>
                        <div class="h4 mb-0 fw-bold text-gray-800"><?= number_format((int)$metrics['total']) ?></div>
                    </div>
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                        <i class="bi bi-file-earmark-spreadsheet fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-warning border-4 h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Pending / Review</div>
                        <div class="h4 mb-0 fw-bold text-warning-emphasis">
                            <?= number_format((int)$metrics['submitted'] + (int)$metrics['under_review']) ?>
                        </div>
                    </div>
                    <div class="bg-warning-subtle text-warning-emphasis p-3 rounded-circle">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4 h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Awarded (Selected)</div>
                        <div class="h4 mb-0 fw-bold text-success"><?= number_format((int)$metrics['selected']) ?></div>
                    </div>
                    <div class="bg-success-subtle text-success p-3 rounded-circle">
                        <i class="bi bi-trophy-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-info border-4 h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Awarded Value</div>
                        <div class="h4 mb-0 fw-bold text-info"><?= formatCurrency($metrics['awarded_value']) ?></div>
                    </div>
                    <div class="bg-info-subtle text-info p-3 rounded-circle">
                        <i class="bi bi-cash-coin fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('modules/quotations/index.php') ?>" class="row g-3 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Search</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-start-0 ps-0" placeholder="QT No, PR No, Supplier..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                    <select name="status" class="form-select bg-light">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="under_review" <?= $statusFilter === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                        <option value="selected" <?= $statusFilter === 'selected' ? 'selected' : '' ?>>Selected (Awarded)</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Supplier</label>
                    <select name="supplier_id" class="form-select bg-light">
                        <option value="">All Suppliers</option>
                        <?php foreach ($allSuppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $supplierFilter == $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['name']) ?> (<?= e($s['supplier_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Purchase Request</label>
                    <select name="pr_id" class="form-select bg-light">
                        <option value="">All Purchase Requests</option>
                        <?php foreach ($approvedPRs as $pr): ?>
                            <option value="<?= $pr['id'] ?>" <?= $prFilter == $pr['id'] ? 'selected' : '' ?>>
                                <?= e($pr['request_no']) ?> - <?= e(mb_strimwidth($pr['purpose'], 0, 25, '...')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-12 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($supplierFilter) || !empty($prFilter)): ?>
                        <a href="<?= url('modules/quotations/index.php') ?>" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Quotations Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-dark">
                Quotations List <span class="badge bg-light text-secondary border ms-1"><?= number_format($totalRecords) ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 130px;">Quote No</th>
                            <th>PR Reference</th>
                            <th>Supplier Vendor</th>
                            <th>Quote Date</th>
                            <th>Valid Until</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3" style="width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quotations)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-receipt-cutoff fs-1 d-block mb-2 text-secondary"></i>
                                    <span class="fw-semibold">No quotations found</span>
                                    <p class="small text-muted mb-0">Record a new vendor quotation for an approved purchase request.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($quotations as $q): ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="<?= url('modules/quotations/view.php?id=' . $q['id']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none fw-semibold font-monospace py-1 px-2">
                                            <?= e($q['quotation_no']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <a href="<?= url('modules/purchase_requests/view.php?id=' . $q['purchase_request_id']) ?>" class="fw-semibold text-dark text-decoration-none hover-primary">
                                                <?= e($q['request_no']) ?>
                                            </a>
                                        </div>
                                        <span class="small text-muted text-truncate d-inline-block" style="max-width: 220px;" title="<?= e($q['pr_purpose']) ?>">
                                            <?= e($q['pr_purpose']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">
                                            <a href="<?= url('modules/suppliers/view.php?id=' . $q['supplier_id']) ?>" class="text-dark text-decoration-none hover-primary">
                                                <?= e($q['supplier_name']) ?>
                                            </a>
                                        </div>
                                        <span class="small text-muted font-monospace"><?= e($q['supplier_code']) ?></span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= formatDate($q['quotation_date'], 'd M Y') ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?php if (!empty($q['valid_until'])): ?>
                                            <?php
                                                $isExpired = strtotime($q['valid_until']) < strtotime(date('Y-m-d'));
                                            ?>
                                            <span class="<?= $isExpired && $q['status'] !== 'selected' ? 'text-danger fw-semibold' : '' ?>">
                                                <?= formatDate($q['valid_until'], 'd M Y') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="fst-italic text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace">
                                        <?= formatCurrency($q['total_amount']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?= getQuotationStatusBadge($q['status']) ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= url('modules/quotations/view.php?id=' . $q['id']) ?>" class="btn btn-outline-secondary" title="View Quotation">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($q['competing_bids_count'] > 1): ?>
                                                <a href="<?= url('modules/quotations/compare.php?purchase_request_id=' . $q['purchase_request_id']) ?>" class="btn btn-outline-info" title="Compare all <?= $q['competing_bids_count'] ?> bids for PR">
                                                    <i class="bi bi-layout-three-columns"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($q['status'] === 'draft' && $canManage): ?>
                                                <a href="<?= url('modules/quotations/edit.php?id=' . $q['id']) ?>" class="btn btn-outline-primary" title="Edit Draft Quotation">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" title="Delete Draft" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $q['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Delete Confirmation Modal (Draft Only) -->
                                        <?php if ($q['status'] === 'draft' && $canManage): ?>
                                            <div class="modal fade text-start" id="deleteModal<?= $q['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $q['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom">
                                                            <h5 class="modal-title text-danger fs-6 fw-bold" id="deleteModalLabel<?= $q['id'] ?>">
                                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Draft Quotation
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body py-4">
                                                            <p class="mb-2">Are you sure you want to delete draft quotation <strong><?= e($q['quotation_no']) ?></strong> from <strong><?= e($q['supplier_name']) ?></strong>?</p>
                                                        </div>
                                                        <div class="modal-footer border-top bg-light">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <form method="POST" action="<?= url('modules/quotations/delete.php') ?>" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                                <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm">Confirm Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="small text-muted">
                    Showing <span class="fw-semibold"><?= $offset + 1 ?></span> to <span class="fw-semibold"><?= min($offset + $perPage, $totalRecords) ?></span> of <span class="fw-semibold"><?= number_format($totalRecords) ?></span> quotations
                </div>
                <nav aria-label="Quotation pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('modules/quotations/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('modules/quotations/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('modules/quotations/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
