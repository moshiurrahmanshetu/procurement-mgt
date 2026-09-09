<?php
/**
 * Purchase Requests List View
 * Procurement Management CMS - Phase 02
 */

$pageTitle = 'Purchase Requests';
$pageSubtitle = 'Manage and Track Item Requisitions';
$activeNav = 'purchase_requests';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$isRequester = userHasRole('requester');
$canViewAll = $isAdmin || $isManager || $isOfficer;

// 1. Capture Filters
$search = sanitizeInput($_GET['search'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$priorityFilter = sanitizeInput($_GET['priority'] ?? '');
$deptFilter = sanitizeInput($_GET['department_id'] ?? '');
$dateFrom = sanitizeInput($_GET['date_from'] ?? '');
$dateTo = sanitizeInput($_GET['date_to'] ?? '');
$userFilter = sanitizeInput($_GET['user_id'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 2. Fetch Active Departments for filter dropdown
$departments = [];
try {
    $deptStmt = $db->query("SELECT id, name, code FROM departments WHERE deleted_at IS NULL AND status = 'active' ORDER BY name ASC");
    $departments = $deptStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching departments: ' . $e->getMessage());
}

// 3. Fetch Users for filter dropdown (if privileged)
$allRequesters = [];
if ($canViewAll) {
    try {
        $uStmt = $db->query("SELECT id, full_name, username FROM users WHERE deleted_at IS NULL ORDER BY full_name ASC");
        $allRequesters = $uStmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error fetching users: ' . $e->getMessage());
    }
}

// 4. Build Dynamic WHERE Conditions
$whereClauses = ['pr.deleted_at IS NULL'];
$params = [];

if (!$canViewAll) {
    // Regular requesters only see their own requests
    $whereClauses[] = 'pr.requested_by = :auth_user_id';
    $params[':auth_user_id'] = $userId;
} elseif (!empty($userFilter)) {
    $whereClauses[] = 'pr.requested_by = :filter_user_id';
    $params[':filter_user_id'] = (int)$userFilter;
}

if (!empty($search)) {
    $whereClauses[] = '(pr.request_no LIKE :search_no OR pr.purpose LIKE :search_purpose)';
    $params[':search_no'] = '%' . $search . '%';
    $params[':search_purpose'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $whereClauses[] = 'pr.status = :status';
    $params[':status'] = $statusFilter;
}

if (!empty($priorityFilter)) {
    $whereClauses[] = 'pr.priority = :priority';
    $params[':priority'] = $priorityFilter;
}

if (!empty($deptFilter)) {
    $whereClauses[] = 'pr.department_id = :dept_id';
    $params[':dept_id'] = (int)$deptFilter;
}

if (!empty($dateFrom)) {
    $whereClauses[] = 'pr.request_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClauses[] = 'pr.request_date <= :date_to';
    $params[':date_to'] = $dateTo;
}

$whereSql = implode(' AND ', $whereClauses);

// 5. Count Total Matching Records
$totalRecords = 0;
try {
    $countSql = "SELECT COUNT(*) as total FROM purchase_requests pr WHERE {$whereSql}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetch()['total'];
} catch (Exception $e) {
    error_log('Count PR Query Error: ' . $e->getMessage());
}

$totalPages = max(1, ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// 6. Fetch Paginated Records
$requests = [];
try {
    $querySql = "
        SELECT pr.*, 
               d.name AS department_name, d.code AS department_code,
               u.full_name AS requester_name, u.username AS requester_username, u.avatar AS requester_avatar,
               app.full_name AS approver_name
        FROM purchase_requests pr
        LEFT JOIN departments d ON pr.department_id = d.id
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN users app ON pr.approved_by = app.id
        WHERE {$whereSql}
        ORDER BY pr.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($querySql);

    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $requests = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Fetch PR Query Error: ' . $e->getMessage());
}

// Helper to preserve active query params in links
function buildPaginationQuery(int $targetPage): string
{
    $queryParams = $_GET;
    $queryParams['page'] = $targetPage;
    return '?' . http_build_query($queryParams);
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Action Header & Filters Card -->
<div class="card-cms mb-4">
    <div class="card-cms-header flex-wrap gap-2">
        <h3 class="card-cms-title">
            <i class="bi bi-cart-check-fill text-primary"></i>
            <span>Purchase Requisitions</span>
        </h3>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary-subtle text-dark border px-2 py-1 small">
                <?= number_format($totalRecords) ?> Total Found
            </span>
            <a href="<?= url('modules/purchase_requests/create.php') ?>" class="btn btn-primary-cms btn-sm">
                <i class="bi bi-plus-lg me-1"></i> New Request
            </a>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="card-cms-body border-bottom bg-light bg-opacity-50 p-3">
        <form method="GET" action="<?= url('modules/purchase_requests/index.php') ?>" class="row g-2 align-items-end">
            <!-- Search Keyword -->
            <div class="col-md-3 col-sm-6">
                <label for="search" class="form-label-cms small mb-1">Search Keyword</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= e($search) ?>" placeholder="Request No or Purpose...">
                </div>
            </div>

            <!-- Status Filter -->
            <div class="col-md-2 col-sm-6">
                <label for="status" class="form-label-cms small mb-1">Status</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="draft" <?= ($statusFilter === 'draft') ? 'selected' : '' ?>>Draft</option>
                    <option value="pending_approval" <?= ($statusFilter === 'pending_approval') ? 'selected' : '' ?>>Pending Approval</option>
                    <option value="approved" <?= ($statusFilter === 'approved') ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= ($statusFilter === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                    <option value="cancelled" <?= ($statusFilter === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    <option value="completed" <?= ($statusFilter === 'completed') ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <!-- Priority Filter -->
            <div class="col-md-2 col-sm-6">
                <label for="priority" class="form-label-cms small mb-1">Priority</label>
                <select class="form-select form-select-sm" id="priority" name="priority">
                    <option value="">All Priorities</option>
                    <option value="low" <?= ($priorityFilter === 'low') ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= ($priorityFilter === 'medium') ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= ($priorityFilter === 'high') ? 'selected' : '' ?>>High</option>
                    <option value="urgent" <?= ($priorityFilter === 'urgent') ? 'selected' : '' ?>>Urgent</option>
                </select>
            </div>

            <!-- Department Filter -->
            <div class="col-md-2 col-sm-6">
                <label for="department_id" class="form-label-cms small mb-1">Department</label>
                <select class="form-select form-select-sm" id="department_id" name="department_id">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($deptFilter == $d['id']) ? 'selected' : '' ?>>
                            <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($canViewAll && !empty($allRequesters)): ?>
                <!-- Requested By Filter (Privileged only) -->
                <div class="col-md-2 col-sm-6">
                    <label for="user_id" class="form-label-cms small mb-1">Requested By</label>
                    <select class="form-select form-select-sm" id="user_id" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($allRequesters as $reqUser): ?>
                            <option value="<?= $reqUser['id'] ?>" <?= ($userFilter == $reqUser['id']) ? 'selected' : '' ?>>
                                <?= e($reqUser['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="col-md-1 col-sm-6 d-flex gap-1">
                <button type="submit" class="btn btn-primary-cms btn-sm flex-grow-1" title="Filter Records">
                    <i class="bi bi-funnel-fill"></i>
                </button>
                <a href="<?= url('modules/purchase_requests/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Table Body -->
    <div class="card-cms-body p-0">
        <?php if (!empty($requests)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Request No</th>
                            <th>Request Date</th>
                            <th>Department</th>
                            <th>Requested By</th>
                            <th>Required By</th>
                            <th>Priority</th>
                            <th class="text-end">Est. Total</th>
                            <th>Status</th>
                            <th class="text-end" style="min-width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $pr): ?>
                            <?php 
                                $isOwner = ((int)$pr['requested_by'] === $userId);
                                $canEdit = ($pr['status'] === 'draft' && ($isOwner || $isAdmin));
                                $canSubmit = ($pr['status'] === 'draft' && ($isOwner || $isAdmin));
                                $canApproveReject = ($pr['status'] === 'pending_approval' && ($isManager || $isAdmin));
                                $canCancel = (in_array($pr['status'], ['draft', 'pending_approval']) && ($isOwner || $isAdmin));
                                $canDelete = ($pr['status'] === 'draft' && ($isOwner || $isAdmin));
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($pr['request_no']) ?>
                                    </a>
                                </td>
                                <td class="small text-muted">
                                    <?= formatDate($pr['request_date'], 'd M Y') ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border small fw-normal">
                                        <?= e($pr['department_code'] ?? '-') ?>
                                    </span>
                                    <span class="small text-muted d-none d-lg-inline"><?= e($pr['department_name'] ?? '') ?></span>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <?= e($pr['requester_name'] ?? 'User #' . $pr['requested_by']) ?>
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    <?= !empty($pr['required_date']) ? formatDate($pr['required_date'], 'd M Y') : '<span class="text-light">-</span>' ?>
                                </td>
                                <td>
                                    <?= getPriorityBadge($pr['priority']) ?>
                                </td>
                                <td class="text-end font-monospace fw-semibold small text-dark">
                                    <?= formatCurrency($pr['estimated_total']) ?>
                                </td>
                                <td>
                                    <?= getStatusBadge($pr['status']) ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <!-- View Detail -->
                                        <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="btn btn-outline-secondary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <?php if ($canEdit): ?>
                                            <!-- Edit Draft -->
                                            <a href="<?= url('modules/purchase_requests/edit.php?id=' . $pr['id']) ?>" class="btn btn-outline-primary" title="Edit Draft">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($canSubmit): ?>
                                            <!-- Submit Draft Modal Trigger -->
                                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#submitModal<?= $pr['id'] ?>" title="Submit for Approval">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($canApproveReject): ?>
                                            <!-- Quick Approve Button -->
                                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $pr['id'] ?>" title="Approve Request">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <!-- Quick Reject Button -->
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $pr['id'] ?>" title="Reject Request">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($canDelete): ?>
                                            <!-- Delete Draft -->
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $pr['id'] ?>" title="Delete Draft">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Modals for Actions -->
                                    <?php if ($canSubmit): ?>
                                        <div class="modal fade text-start" id="submitModal<?= $pr['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form action="<?= url('modules/purchase_requests/submit.php') ?>" method="POST">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fs-6 fw-bold">Submit Purchase Request</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body small">
                                                            Are you sure you want to submit <strong><?= e($pr['request_no']) ?></strong> for management review?
                                                            Once submitted, you will no longer be able to edit this request.
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="bi bi-send me-1"></i> Submit Request
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($canApproveReject): ?>
                                        <!-- Approve Modal -->
                                        <div class="modal fade text-start" id="approveModal<?= $pr['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form action="<?= url('modules/purchase_requests/approve.php') ?>" method="POST">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fs-6 fw-bold text-success">
                                                                <i class="bi bi-check-circle-fill me-1"></i> Approve Purchase Request
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body small">
                                                            <p>You are approving requisition <strong><?= e($pr['request_no']) ?></strong> for <strong><?= formatCurrency($pr['estimated_total']) ?></strong>.</p>
                                                            <div class="mb-2">
                                                                <label for="approve_comments<?= $pr['id'] ?>" class="form-label-cms small">Approval Notes / Instructions (Optional)</label>
                                                                <textarea class="form-control form-control-sm" id="approve_comments<?= $pr['id'] ?>" name="comments" rows="2" placeholder="Optional comments..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="bi bi-check-lg me-1"></i> Confirm Approval
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div class="modal fade text-start" id="rejectModal<?= $pr['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form action="<?= url('modules/purchase_requests/reject.php') ?>" method="POST">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fs-6 fw-bold text-danger">
                                                                <i class="bi bi-x-circle-fill me-1"></i> Reject Purchase Request
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body small">
                                                            <p>Please provide a mandatory reason for rejecting requisition <strong><?= e($pr['request_no']) ?></strong>:</p>
                                                            <div class="mb-2">
                                                                <label for="rejection_reason<?= $pr['id'] ?>" class="form-label-cms small">Rejection Reason <span class="text-danger">*</span></label>
                                                                <textarea class="form-control form-control-sm" id="rejection_reason<?= $pr['id'] ?>" name="rejection_reason" rows="3" placeholder="State why this requisition is rejected..." required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="bi bi-x-circle me-1"></i> Reject Request
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($canDelete): ?>
                                        <!-- Delete Modal -->
                                        <div class="modal fade text-start" id="deleteModal<?= $pr['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form action="<?= url('modules/purchase_requests/delete.php') ?>" method="POST">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fs-6 fw-bold text-danger">Delete Draft Request</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body small">
                                                            Are you sure you want to permanently delete draft request <strong><?= e($pr['request_no']) ?></strong>?
                                                            This action cannot be undone.
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="bi bi-trash me-1"></i> Delete Draft
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <?php if ($totalPages > 1): ?>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center p-3 border-top gap-2">
                    <div class="small text-muted">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalRecords) ?> of <?= number_format($totalRecords) ?> entries
                    </div>
                    <nav aria-label="Purchase Requests Pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Prev -->
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= ($page > 1) ? buildPaginationQuery($page - 1) : '#' ?>">Previous</a>
                            </li>

                            <!-- Page Numbers -->
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <?php if ($p == 1 || $p == $totalPages || ($p >= $page - 2 && $p <= $page + 2)): ?>
                                    <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= buildPaginationQuery($p) ?>"><?= $p ?></a>
                                    </li>
                                <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
                                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next -->
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= ($page < $totalPages) ? buildPaginationQuery($page + 1) : '#' ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Clean Empty State -->
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle p-3 mb-3 text-muted" style="width:64px;height:64px;">
                    <i class="bi bi-file-earmark-text fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">No Purchase Requests Found</h5>
                <p class="text-muted small mb-3">
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($deptFilter) || !empty($priorityFilter)): ?>
                        No requests matched your active filter criteria. Try resetting the filters.
                    <?php else: ?>
                        There are currently no purchase requisitions recorded in the system.
                    <?php endif; ?>
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($deptFilter) || !empty($priorityFilter)): ?>
                        <a href="<?= url('modules/purchase_requests/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                        </a>
                    <?php endif; ?>
                    <a href="<?= url('modules/purchase_requests/create.php') ?>" class="btn btn-primary-cms btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Create Purchase Request
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
