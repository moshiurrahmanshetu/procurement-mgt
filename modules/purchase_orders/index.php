<?php
/**
 * Purchase Orders List View
 * Procurement Management CMS - Phase 04
 */

$pageTitle = 'Purchase Orders';
$pageSubtitle = 'Manage Vendor Purchase Orders & Delivery Fulfillment';
$activeNav = 'purchase_orders';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewAll = $isAdmin || $isManager || $isOfficer;
$canManage = $isAdmin || $isOfficer;
$canApprove = $isAdmin || $isManager;

// 1. Capture Filters
$search = sanitizeInput($_GET['search'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$supplierFilter = sanitizeInput($_GET['supplier_id'] ?? '');
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
    error_log('Error fetching suppliers for PO filter: ' . $e->getMessage());
}

// 3. Build Query Conditions
$whereClauses = ['po.deleted_at IS NULL'];
$params = [];

if (!$canViewAll) {
    // Requesters only see POs originating from their own purchase requests
    $whereClauses[] = 'pr.requested_by = :auth_user_id';
    $params[':auth_user_id'] = $userId;
}

if (!empty($search)) {
    $whereClauses[] = '(po.po_no LIKE :search_pono OR pr.request_no LIKE :search_prno OR s.name LIKE :search_sname)';
    $params[':search_pono'] = '%' . $search . '%';
    $params[':search_prno'] = '%' . $search . '%';
    $params[':search_sname'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $whereClauses[] = 'po.status = :status';
    $params[':status'] = $statusFilter;
}

if (!empty($supplierFilter)) {
    $whereClauses[] = 'po.supplier_id = :supplier_id';
    $params[':supplier_id'] = (int)$supplierFilter;
}

if (!empty($dateFrom)) {
    $whereClauses[] = 'po.po_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClauses[] = 'po.po_date <= :date_to';
    $params[':date_to'] = $dateTo;
}

$whereSql = implode(' AND ', $whereClauses);

// Count Total Records
$totalRecords = 0;
try {
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM purchase_orders po
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN suppliers s ON s.id = po.supplier_id
        WHERE {$whereSql}
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
} catch (Exception $e) {
    error_log('Error counting purchase orders: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Purchase Orders
$purchaseOrders = [];
try {
    $dataSql = "
        SELECT po.*,
               pr.request_no,
               pr.purpose AS pr_purpose,
               pr.requested_by AS pr_requested_by,
               s.name AS supplier_name,
               s.supplier_code,
               u_cr.full_name AS creator_name,
               u_app.full_name AS approver_name
        FROM purchase_orders po
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN suppliers s ON s.id = po.supplier_id
        LEFT JOIN users u_cr ON u_cr.id = po.created_by
        LEFT JOIN users u_app ON u_app.id = po.approved_by
        WHERE {$whereSql}
        ORDER BY po.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($dataSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $purchaseOrders = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching purchase orders: ' . $e->getMessage());
}

// Summary Metrics counts
$metrics = [
    'total'            => 0,
    'draft'            => 0,
    'pending_approval' => 0,
    'approved'         => 0,
    'sent'             => 0,
    'cancelled'        => 0,
    'total_value'      => 0.00
];
try {
    $mWhere = "WHERE po.deleted_at IS NULL";
    $mParams = [];
    if (!$canViewAll) {
        $mWhere .= " AND pr.requested_by = :auth_uid";
        $mParams[':auth_uid'] = $userId;
    }
    $mStmt = $db->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN po.status = 'draft' THEN 1 ELSE 0 END) AS draft,
            SUM(CASE WHEN po.status = 'pending_approval' THEN 1 ELSE 0 END) AS pending_approval,
            SUM(CASE WHEN po.status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN po.status = 'sent' THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN po.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN po.status IN ('approved', 'sent') THEN po.grand_total ELSE 0 END) AS total_value
        FROM purchase_orders po
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        {$mWhere}
    ");
    $mStmt->execute($mParams);
    $metrics = $mStmt->fetch() ?: $metrics;
} catch (Exception $e) {
    error_log('Error fetching PO metrics: ' . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Purchase Orders</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Purchase Orders</h1>
            <p class="text-muted small mb-0">Generate, approve, send commercial purchase orders, and monitor fulfillment.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canManage): ?>
                <a href="<?= url('modules/purchase_orders/create.php') ?>" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span>Create Purchase Order</span>
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

    <!-- Summary Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-sm-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-primary border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Total POs</div>
                    <div class="h4 mb-0 fw-bold text-gray-800"><?= number_format((int)$metrics['total']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-sm-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-warning border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Pending Approval</div>
                    <div class="h4 mb-0 fw-bold text-warning-emphasis"><?= number_format((int)$metrics['pending_approval']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-sm-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Approved</div>
                    <div class="h4 mb-0 fw-bold text-success"><?= number_format((int)$metrics['approved']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-sm-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-info border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Sent to Supplier</div>
                    <div class="h4 mb-0 fw-bold text-info"><?= number_format((int)$metrics['sent']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-sm-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-danger border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Cancelled</div>
                    <div class="h4 mb-0 fw-bold text-danger"><?= number_format((int)$metrics['cancelled']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-sm-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-primary border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Committed Value</div>
                    <div class="h5 mb-0 fw-bold text-primary font-monospace"><?= formatCurrency($metrics['total_value']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('modules/purchase_orders/index.php') ?>" class="row g-3 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Search</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-start-0 ps-0" placeholder="PO No, PR No, Supplier..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                    <select name="status" class="form-select bg-light">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_approval" <?= $statusFilter === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Sent to Supplier</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
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
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control bg-light" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-lg-2 col-md-12 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($supplierFilter) || !empty($dateFrom)): ?>
                        <a href="<?= url('modules/purchase_orders/index.php') ?>" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Purchase Orders Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-dark">
                Purchase Orders List <span class="badge bg-light text-secondary border ms-1"><?= number_format($totalRecords) ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 130px;">PO No</th>
                            <th>PO Date</th>
                            <th>Purchase Request</th>
                            <th>Supplier Vendor</th>
                            <th class="text-end">Grand Total</th>
                            <th>Expected Delivery</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3" style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchaseOrders)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-secondary"></i>
                                    <span class="fw-semibold">No purchase orders found</span>
                                    <p class="small text-muted mb-0">Generate a PO from an awarded (selected) quotation.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($purchaseOrders as $po): ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="<?= url('modules/purchase_orders/view.php?id=' . $po['id']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none fw-semibold font-monospace py-1 px-2">
                                            <?= e($po['po_no']) ?>
                                        </a>
                                    </td>
                                    <td class="small text-muted font-monospace">
                                        <?= formatDate($po['po_date'], 'd M Y') ?>
                                    </td>
                                    <td>
                                        <div>
                                            <a href="<?= url('modules/purchase_requests/view.php?id=' . $po['purchase_request_id']) ?>" class="fw-semibold text-dark text-decoration-none hover-primary">
                                                <?= e($po['request_no']) ?>
                                            </a>
                                        </div>
                                        <span class="small text-muted text-truncate d-inline-block" style="max-width: 200px;" title="<?= e($po['pr_purpose']) ?>">
                                            <?= e($po['pr_purpose']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">
                                            <a href="<?= url('modules/suppliers/view.php?id=' . $po['supplier_id']) ?>" class="text-dark text-decoration-none hover-primary">
                                                <?= e($po['supplier_name']) ?>
                                            </a>
                                        </div>
                                        <span class="small text-muted font-monospace"><?= e($po['supplier_code']) ?></span>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace">
                                        <?= formatCurrency($po['grand_total']) ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= !empty($po['expected_delivery_date']) ? formatDate($po['expected_delivery_date'], 'd M Y') : '<span class="fst-italic text-muted">Not set</span>' ?>
                                    </td>
                                    <td class="text-center">
                                        <?= getPoStatusBadge($po['status']) ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= url('modules/purchase_orders/view.php?id=' . $po['id']) ?>" class="btn btn-outline-secondary" title="View Purchase Order">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= url('modules/purchase_orders/print.php?id=' . $po['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Print PO">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <?php if ($po['status'] === 'draft' && $canManage): ?>
                                                <a href="<?= url('modules/purchase_orders/edit.php?id=' . $po['id']) ?>" class="btn btn-outline-primary" title="Edit Draft PO">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" title="Delete Draft PO" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $po['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Delete Confirmation Modal (Draft Only) -->
                                        <?php if ($po['status'] === 'draft' && $canManage): ?>
                                            <div class="modal fade text-start" id="deleteModal<?= $po['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $po['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom">
                                                            <h5 class="modal-title text-danger fs-6 fw-bold" id="deleteModalLabel<?= $po['id'] ?>">
                                                                <i class="bi bi-trash me-2"></i>Delete Draft Purchase Order
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body py-4">
                                                            Are you sure you want to delete draft purchase order <strong><?= e($po['po_no']) ?></strong>?
                                                        </div>
                                                        <div class="modal-footer border-top bg-light">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <form method="POST" action="<?= url('modules/purchase_orders/delete.php') ?>" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                                <input type="hidden" name="id" value="<?= $po['id'] ?>">
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
                    Showing <span class="fw-semibold"><?= $offset + 1 ?></span> to <span class="fw-semibold"><?= min($offset + $perPage, $totalRecords) ?></span> of <span class="fw-semibold"><?= number_format($totalRecords) ?></span> orders
                </div>
                <nav aria-label="Purchase order pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('modules/purchase_orders/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('modules/purchase_orders/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('modules/purchase_orders/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
