<?php
/**
 * Goods Receiving Notes (GRN) List View
 * Procurement Management CMS - Phase 05
 */

$pageTitle = 'Goods Receiving (GRN)';
$pageSubtitle = 'Inspect, Receive, and Accept Physical Deliveries against Purchase Orders';
$activeNav = 'goods_receiving';

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
    error_log('Error fetching suppliers for GRN filter: ' . $e->getMessage());
}

// 3. Build Query Conditions
$whereClauses = ['gr.deleted_at IS NULL'];
$params = [];

if (!$canViewAll) {
    // Requester access: only see GRNs for their purchase requests
    $whereClauses[] = 'pr.requested_by = :auth_user_id';
    $params[':auth_user_id'] = $userId;
}

if (!empty($search)) {
    $whereClauses[] = '(gr.grn_no LIKE :search_grn OR po.po_no LIKE :search_po OR s.name LIKE :search_sname OR gr.delivery_note_no LIKE :search_dn)';
    $params[':search_grn'] = '%' . $search . '%';
    $params[':search_po'] = '%' . $search . '%';
    $params[':search_sname'] = '%' . $search . '%';
    $params[':search_dn'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $whereClauses[] = 'gr.status = :status';
    $params[':status'] = $statusFilter;
}

if (!empty($supplierFilter)) {
    $whereClauses[] = 'gr.supplier_id = :supplier_id';
    $params[':supplier_id'] = (int)$supplierFilter;
}

if (!empty($dateFrom)) {
    $whereClauses[] = 'gr.receipt_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClauses[] = 'gr.receipt_date <= :date_to';
    $params[':date_to'] = $dateTo;
}

$whereSql = implode(' AND ', $whereClauses);

// Count Total Records
$totalRecords = 0;
try {
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN suppliers s ON s.id = gr.supplier_id
        WHERE {$whereSql}
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
} catch (Exception $e) {
    error_log('Error counting GRNs: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Goods Receipts
$goodsReceipts = [];
try {
    $dataSql = "
        SELECT gr.*,
               po.po_no,
               po.status AS po_status,
               pr.request_no,
               s.name AS supplier_name,
               s.supplier_code,
               u_rec.full_name AS receiver_name,
               (SELECT COUNT(*) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS item_count,
               (SELECT COALESCE(SUM(received_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_received_qty,
               (SELECT COALESCE(SUM(rejected_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_rejected_qty
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN suppliers s ON s.id = gr.supplier_id
        LEFT JOIN users u_rec ON u_rec.id = gr.received_by
        WHERE {$whereSql}
        ORDER BY gr.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($dataSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $goodsReceipts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching goods receipts: ' . $e->getMessage());
}

// Summary Metrics counts
$metrics = [
    'total_grns'         => 0,
    'draft_grns'         => 0,
    'posted_grns'        => 0,
    'partially_received' => 0,
    'fully_received'     => 0
];
try {
    $mWhere = "WHERE gr.deleted_at IS NULL";
    $mParams = [];
    if (!$canViewAll) {
        $mWhere .= " AND pr.requested_by = :auth_uid";
        $mParams[':auth_uid'] = $userId;
    }

    $mStmt = $db->prepare("
        SELECT
            COUNT(*) AS total_grns,
            SUM(CASE WHEN gr.status = 'draft' THEN 1 ELSE 0 END) AS draft_grns,
            SUM(CASE WHEN gr.status = 'posted' THEN 1 ELSE 0 END) AS posted_grns
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        {$mWhere}
    ");
    $mStmt->execute($mParams);
    $grnCounts = $mStmt->fetch();
    if ($grnCounts) {
        $metrics['total_grns'] = (int)$grnCounts['total_grns'];
        $metrics['draft_grns'] = (int)$grnCounts['draft_grns'];
        $metrics['posted_grns'] = (int)$grnCounts['posted_grns'];
    }

    $poWhere = "WHERE deleted_at IS NULL";
    $poParams = [];
    if (!$canViewAll) {
        $poWhere .= " AND purchase_request_id IN (SELECT id FROM purchase_requests WHERE requested_by = :auth_uid2)";
        $poParams[':auth_uid2'] = $userId;
    }
    $poCountStmt = $db->prepare("
        SELECT
            SUM(CASE WHEN status = 'partially_received' THEN 1 ELSE 0 END) AS partially_received,
            SUM(CASE WHEN status = 'fully_received' THEN 1 ELSE 0 END) AS fully_received
        FROM purchase_orders
        {$poWhere}
    ");
    $poCountStmt->execute($poParams);
    $poCounts = $poCountStmt->fetch();
    if ($poCounts) {
        $metrics['partially_received'] = (int)$poCounts['partially_received'];
        $metrics['fully_received'] = (int)$poCounts['fully_received'];
    }
} catch (Exception $e) {
    error_log('Error fetching GRN metrics: ' . $e->getMessage());
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
                    <li class="breadcrumb-item active" aria-current="page">Goods Receiving (GRN)</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Goods Receiving & GRN Management</h1>
            <p class="text-muted small mb-0">Record deliveries, perform acceptance inspections, manage partial receipts, and track remaining balances.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canManage): ?>
                <a href="<?= url('modules/goods_receiving/create.php') ?>" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span>Receive Goods (New GRN)</span>
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
                    <div class="text-muted small fw-semibold text-uppercase">Total GRNs</div>
                    <div class="h4 mb-0 fw-bold text-gray-800"><?= number_format((int)$metrics['total_grns']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-sm-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-secondary border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Draft Receipts</div>
                    <div class="h4 mb-0 fw-bold text-secondary"><?= number_format((int)$metrics['draft_grns']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Posted (Confirmed)</div>
                    <div class="h4 mb-0 fw-bold text-success"><?= number_format((int)$metrics['posted_grns']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-sm-6 col-6">
            <div class="card border-0 shadow-sm rounded-3 border-start border-info border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Partially Received POs</div>
                    <div class="h4 mb-0 fw-bold text-info"><?= number_format((int)$metrics['partially_received']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-semibold text-uppercase">Fully Fulfilled POs</div>
                    <div class="h4 mb-0 fw-bold text-success"><?= number_format((int)$metrics['fully_received']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= url('modules/goods_receiving/index.php') ?>" class="row g-3 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Search</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-start-0 ps-0" placeholder="GRN No, PO No, Vendor, Delivery Note..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                    <select name="status" class="form-select bg-light">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="posted" <?= $statusFilter === 'posted' ? 'selected' : '' ?>>Posted (Locked)</option>
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
                    <label class="form-label small fw-semibold text-muted mb-1">Receipt Date</label>
                    <input type="date" name="date_from" class="form-control bg-light" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-lg-2 col-md-12 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($supplierFilter) || !empty($dateFrom)): ?>
                        <a href="<?= url('modules/goods_receiving/index.php') ?>" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Goods Receipts Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-dark">
                Goods Receipt Notes (GRN) <span class="badge bg-light text-secondary border ms-1"><?= number_format($totalRecords) ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 130px;">GRN No</th>
                            <th>Purchase Order</th>
                            <th>Supplier Vendor</th>
                            <th>Receipt Date</th>
                            <th>Delivery Note #</th>
                            <th>Received By</th>
                            <th class="text-center">Items / Quantities</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3" style="width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($goodsReceipts)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                                    <span class="fw-semibold">No goods receipts found</span>
                                    <p class="small text-muted mb-0">Receive physical items against sent or partially received purchase orders.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($goodsReceipts as $gr): ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="<?= url('modules/goods_receiving/view.php?id=' . $gr['id']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none fw-semibold font-monospace py-1 px-2">
                                            <?= e($gr['grn_no']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <a href="<?= url('modules/purchase_orders/view.php?id=' . $gr['purchase_order_id']) ?>" class="fw-bold font-monospace text-dark text-decoration-none hover-primary">
                                                <?= e($gr['po_no']) ?>
                                            </a>
                                        </div>
                                        <span class="small text-muted font-monospace">Req: <?= e($gr['request_no']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">
                                            <a href="<?= url('modules/suppliers/view.php?id=' . $gr['supplier_id']) ?>" class="text-dark text-decoration-none hover-primary">
                                                <?= e($gr['supplier_name']) ?>
                                            </a>
                                        </div>
                                        <span class="small text-muted font-monospace"><?= e($gr['supplier_code']) ?></span>
                                    </td>
                                    <td class="small text-muted font-monospace">
                                        <?= formatDate($gr['receipt_date'], 'd M Y') ?>
                                    </td>
                                    <td class="small">
                                        <?= !empty($gr['delivery_note_no']) ? '<span class="font-monospace text-dark fw-semibold">' . e($gr['delivery_note_no']) . '</span>' : '<span class="text-muted fst-italic">None</span>' ?>
                                    </td>
                                    <td class="small text-dark fw-medium">
                                        <?= e($gr['receiver_name'] ?? 'Authorized Officer') ?>
                                    </td>
                                    <td class="text-center small">
                                        <span class="badge bg-light text-dark border font-monospace">
                                            <?= (int)$gr['item_count'] ?> items &bull; +<?= number_format((float)$gr['total_received_qty'], 0) ?> rec
                                            <?php if ((float)$gr['total_rejected_qty'] > 0): ?>
                                                <span class="text-danger ms-1">(-<?= number_format((float)$gr['total_rejected_qty'], 0) ?> rej)</span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?= getGrnStatusBadge($gr['status']) ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= url('modules/goods_receiving/view.php?id=' . $gr['id']) ?>" class="btn btn-outline-secondary" title="View Goods Receipt">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= url('modules/goods_receiving/print.php?id=' . $gr['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Print GRN">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <?php if ($gr['status'] === 'draft' && $canManage): ?>
                                                <a href="<?= url('modules/goods_receiving/edit.php?id=' . $gr['id']) ?>" class="btn btn-outline-primary" title="Edit Draft GRN">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" title="Delete Draft GRN" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $gr['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Delete Confirmation Modal (Draft Only) -->
                                        <?php if ($gr['status'] === 'draft' && $canManage): ?>
                                            <div class="modal fade text-start" id="deleteModal<?= $gr['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $gr['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom">
                                                            <h5 class="modal-title text-danger fs-6 fw-bold" id="deleteModalLabel<?= $gr['id'] ?>">
                                                                <i class="bi bi-trash me-2"></i>Delete Draft Goods Receipt
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body py-4">
                                                            Are you sure you want to delete draft goods receipt note <strong><?= e($gr['grn_no']) ?></strong>?
                                                        </div>
                                                        <div class="modal-footer border-top bg-light">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <form method="POST" action="<?= url('modules/goods_receiving/delete.php') ?>" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                                <input type="hidden" name="id" value="<?= $gr['id'] ?>">
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
                    Showing <span class="fw-semibold"><?= $offset + 1 ?></span> to <span class="fw-semibold"><?= min($offset + $perPage, $totalRecords) ?></span> of <span class="fw-semibold"><?= number_format($totalRecords) ?></span> GRNs
                </div>
                <nav aria-label="GRN pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('modules/goods_receiving/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('modules/goods_receiving/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('modules/goods_receiving/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
