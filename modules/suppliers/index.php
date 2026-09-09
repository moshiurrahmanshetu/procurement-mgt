<?php
/**
 * Supplier List View
 * Procurement Management CMS - Phase 03
 */

$pageTitle = 'Suppliers';
$pageSubtitle = 'Manage Vendors and Supplier Directory';
$activeNav = 'suppliers';

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
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 2. Build Query
$whereClauses = ['s.deleted_at IS NULL'];
$params = [];

if (!empty($search)) {
    $whereClauses[] = '(s.supplier_code LIKE :search_code OR s.name LIKE :search_name OR s.email LIKE :search_email OR s.phone LIKE :search_phone OR s.tax_number LIKE :search_tax)';
    $params[':search_code'] = '%' . $search . '%';
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_email'] = '%' . $search . '%';
    $params[':search_phone'] = '%' . $search . '%';
    $params[':search_tax'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $whereClauses[] = 's.status = :status';
    $params[':status'] = $statusFilter;
}

$whereSql = implode(' AND ', $whereClauses);

// Count Total Records
$totalRecords = 0;
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM suppliers s WHERE {$whereSql}");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
} catch (Exception $e) {
    error_log('Error counting suppliers: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Records with Primary Contact & Quotation Counts
$suppliers = [];
try {
    $dataSql = "
        SELECT s.*,
               sc.name AS contact_person,
               sc.email AS contact_email,
               sc.phone AS contact_phone,
               (SELECT COUNT(*) FROM quotations q WHERE q.supplier_id = s.id AND q.deleted_at IS NULL) AS total_quotations,
               (SELECT COUNT(*) FROM quotations q WHERE q.supplier_id = s.id AND q.status = 'selected' AND q.deleted_at IS NULL) AS awarded_quotations
        FROM suppliers s
        LEFT JOIN supplier_contacts sc ON sc.supplier_id = s.id AND sc.is_primary = 1
        WHERE {$whereSql}
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($dataSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $suppliers = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching suppliers: ' . $e->getMessage());
}

// Statistics counts for top cards
$stats = [
    'total'       => 0,
    'active'      => 0,
    'inactive'    => 0,
    'blacklisted' => 0
];
try {
    $statStmt = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
            SUM(CASE WHEN status = 'blacklisted' THEN 1 ELSE 0 END) AS blacklisted
        FROM suppliers
        WHERE deleted_at IS NULL
    ");
    $stats = $statStmt->fetch() ?: $stats;
} catch (Exception $e) {
    error_log('Error fetching supplier statistics: ' . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Suppliers</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Supplier Directory</h1>
            <p class="text-muted small mb-0">Manage registered vendors, primary contacts, and quotation histories.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canManage): ?>
                <a href="<?= url('modules/suppliers/create.php') ?>" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add New Supplier</span>
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

    <!-- Metric Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase mb-1">Total Suppliers</div>
                            <div class="h4 mb-0 fw-bold text-gray-800"><?= number_format((int)$stats['total']) ?></div>
                        </div>
                        <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                            <i class="bi bi-building fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase mb-1">Active Vendors</div>
                            <div class="h4 mb-0 fw-bold text-success"><?= number_format((int)$stats['active']) ?></div>
                        </div>
                        <div class="bg-success-subtle text-success p-3 rounded-circle">
                            <i class="bi bi-check-circle fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-secondary border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase mb-1">Inactive</div>
                            <div class="h4 mb-0 fw-bold text-secondary"><?= number_format((int)$stats['inactive']) ?></div>
                        </div>
                        <div class="bg-secondary-subtle text-secondary p-3 rounded-circle">
                            <i class="bi bi-pause-circle fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-danger border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase mb-1">Blacklisted</div>
                            <div class="h4 mb-0 fw-bold text-danger"><?= number_format((int)$stats['blacklisted']) ?></div>
                        </div>
                        <div class="bg-danger-subtle text-danger p-3 rounded-circle">
                            <i class="bi bi-slash-circle fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('modules/suppliers/index.php') ?>" class="row g-3 align-items-center">
                <div class="col-lg-6 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-start-0 ps-0" placeholder="Search by code, supplier name, email, phone, tax ID..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-lg-3 col-md-4">
                    <select name="status" class="form-select bg-light">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="blacklisted" <?= $statusFilter === 'blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-filter"></i> Apply Filters
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                        <a href="<?= url('modules/suppliers/index.php') ?>" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Suppliers Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-dark">
                Suppliers <span class="badge bg-light text-secondary border ms-1"><?= number_format($totalRecords) ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 130px;">Code</th>
                            <th>Supplier Name</th>
                            <th>Contact Info</th>
                            <th>Location</th>
                            <th class="text-center">Quotations</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-building-x fs-1 d-block mb-2 text-secondary"></i>
                                    <span class="fw-semibold">No suppliers found</span>
                                    <p class="small text-muted mb-0">Try refining your search or add a new supplier vendor.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($suppliers as $s): ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="<?= url('modules/suppliers/view.php?id=' . $s['id']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none fw-semibold font-monospace py-1 px-2">
                                            <?= e($s['supplier_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">
                                            <a href="<?= url('modules/suppliers/view.php?id=' . $s['id']) ?>" class="text-dark text-decoration-none hover-primary">
                                                <?= e($s['name']) ?>
                                            </a>
                                        </div>
                                        <?php if (!empty($s['tax_number'])): ?>
                                            <span class="small text-muted font-monospace"><i class="bi bi-receipt me-1"></i>TAX: <?= e($s['tax_number']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?php if (!empty($s['contact_person'])): ?>
                                                <div class="fw-medium text-dark"><i class="bi bi-person me-1 text-muted"></i><?= e($s['contact_person']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($s['email'])): ?>
                                                <div><a href="mailto:<?= e($s['email']) ?>" class="text-decoration-none text-muted"><i class="bi bi-envelope me-1"></i><?= e($s['email']) ?></a></div>
                                            <?php endif; ?>
                                            <?php if (!empty($s['phone'])): ?>
                                                <div class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($s['phone']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-muted">
                                            <?= e($s['city'] ?? '') ?><?= (!empty($s['city']) && !empty($s['country'])) ? ', ' : '' ?><?= e($s['country'] ?? '') ?>
                                            <?php if (empty($s['city']) && empty($s['country'])): ?>
                                                <span class="text-muted fst-italic">Not specified</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <?= (int)$s['total_quotations'] ?> total
                                        </span>
                                        <?php if ((int)$s['awarded_quotations'] > 0): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" title="Awarded quotations">
                                                <i class="bi bi-trophy-fill me-1"></i><?= (int)$s['awarded_quotations'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?= getSupplierStatusBadge($s['status']) ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= url('modules/suppliers/view.php?id=' . $s['id']) ?>" class="btn btn-outline-secondary" title="View Supplier Profile">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($canManage): ?>
                                                <a href="<?= url('modules/suppliers/edit.php?id=' . $s['id']) ?>" class="btn btn-outline-primary" title="Edit Supplier">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" title="Delete Supplier" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $s['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Delete Confirmation Modal -->
                                        <?php if ($canManage): ?>
                                            <div class="modal fade text-start" id="deleteModal<?= $s['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $s['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom">
                                                            <h5 class="modal-title text-danger fs-6 fw-bold" id="deleteModalLabel<?= $s['id'] ?>">
                                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Supplier
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body py-4">
                                                            <p class="mb-2">Are you sure you want to delete supplier <strong><?= e($s['name']) ?></strong> (<?= e($s['supplier_code']) ?>)?</p>
                                                            <p class="text-muted small mb-0">This will soft-delete the supplier record. Existing quotation history will remain preserved for audit compliance.</p>
                                                        </div>
                                                        <div class="modal-footer border-top bg-light">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <form method="POST" action="<?= url('modules/suppliers/delete.php') ?>" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
                    Showing <span class="fw-semibold"><?= $offset + 1 ?></span> to <span class="fw-semibold"><?= min($offset + $perPage, $totalRecords) ?></span> of <span class="fw-semibold"><?= number_format($totalRecords) ?></span> suppliers
                </div>
                <nav aria-label="Supplier pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('modules/suppliers/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('modules/suppliers/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('modules/suppliers/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
