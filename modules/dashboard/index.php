<?php
/**
 * Application Dashboard
 * Procurement Management CMS - Phase 02
 */

$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview, Requisition Metrics, and Activity';
$activeNav = 'dashboard';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewAll = $isAdmin || $isManager || $isOfficer;

// 1. Fetch Purchase Request Statistics
$prStats = [
    'total'            => 0,
    'draft'            => 0,
    'pending_approval' => 0,
    'approved'         => 0,
    'rejected'         => 0,
    'cancelled'        => 0
];

try {
    $statWhere = "WHERE deleted_at IS NULL";
    $statParams = [];

    if (!$canViewAll) {
        $statWhere .= " AND requested_by = :uid";
        $statParams[':uid'] = $userId;
    }

    $statQuery = "
        SELECT status, COUNT(*) as count 
        FROM purchase_requests 
        {$statWhere} 
        GROUP BY status
    ";
    $stmt = $db->prepare($statQuery);
    $stmt->execute($statParams);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $s = $row['status'];
        if (isset($prStats[$s])) {
            $prStats[$s] = (int)$row['count'];
        }
        $prStats['total'] += (int)$row['count'];
    }
} catch (Exception $e) {
    error_log('Dashboard PR Stats Error: ' . $e->getMessage());
}

// 2. Fetch Recent Purchase Requests (Top 5)
$recentRequests = [];
try {
    $prWhere = "WHERE pr.deleted_at IS NULL";
    $prParams = [];

    if (!$canViewAll) {
        $prWhere .= " AND pr.requested_by = :uid";
        $prParams[':uid'] = $userId;
    }

    $prQuery = "
        SELECT pr.*, d.name as department_name, d.code as department_code, u.full_name as requester_name
        FROM purchase_requests pr
        LEFT JOIN departments d ON pr.department_id = d.id
        LEFT JOIN users u ON pr.requested_by = u.id
        {$prWhere}
        ORDER BY pr.id DESC
        LIMIT 5
    ";
    $prStmt = $db->prepare($prQuery);
    $prStmt->execute($prParams);
    $recentRequests = $prStmt->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard Recent PRs Error: ' . $e->getMessage());
}

// 3. Fetch Recent Activity Logs
$activityLogs = [];
try {
    $actStmt = $db->prepare("
        SELECT a.*, u.full_name, u.username 
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC 
        LIMIT 6
    ");
    $actStmt->execute();
    $activityLogs = $actStmt->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard Activity Log Query Error: ' . $e->getMessage());
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card-cms mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #ffffff;">
    <div class="card-cms-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="badge bg-primary px-3 py-2 fs-7 font-monospace fw-semibold">
                        <i class="bi bi-cart-check-fill me-1"></i> Phase 02: PR Module
                    </span>
                    <span class="badge bg-success bg-opacity-75 px-3 py-2 fs-7">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> <?= ucfirst(e($user['status'])) ?>
                    </span>
                </div>
                <h2 class="fw-bold mb-1 text-white">Welcome back, <?= e($user['full_name']) ?>!</h2>
                <p class="text-slate-300 mb-0 opacity-75 small">
                    Signed in as <strong class="text-white"><?= e($user['role_names'] ?? 'User') ?></strong> &bull;
                    Last logged in: <span class="text-white"><?= formatDate($user['last_login']) ?></span>
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="<?= url('modules/purchase_requests/create.php') ?>" class="btn btn-primary btn-sm fw-semibold px-3 py-2 shadow-sm me-2">
                    <i class="bi bi-plus-lg me-1"></i> New Request
                </a>
                <a href="<?= url('modules/profile/index.php') ?>" class="btn btn-light btn-sm fw-semibold px-3 py-2 shadow-sm">
                    <i class="bi bi-person-gear me-1"></i> Profile
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Requisition Metrics Cards -->
<div class="row g-3 mb-4">
    <!-- Total Requests -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon primary" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-cart3"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Total Requests</div>
                <h4 class="stat-value fs-5"><?= number_format($prStats['total']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Drafts -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: var(--surface-subtle); color: var(--text-muted);">
                <i class="bi bi-file-earmark"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Drafts</div>
                <h4 class="stat-value fs-5 text-secondary"><?= number_format($prStats['draft']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Pending Approval -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon warning" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Pending Approval</div>
                <h4 class="stat-value fs-5 text-warning-emphasis"><?= number_format($prStats['pending_approval']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Approved -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon success" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Approved</div>
                <h4 class="stat-value fs-5 text-success"><?= number_format($prStats['approved']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Rejected -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: var(--danger-bg); color: var(--danger);">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Rejected</div>
                <h4 class="stat-value fs-5 text-danger"><?= number_format($prStats['rejected']) ?></h4>
            </div>
        </div>
    </div>

    <!-- Cancelled -->
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="width: 42px; height: 42px; font-size: 1.25rem; background-color: #f1f5f9; color: #475569;">
                <i class="bi bi-slash-circle"></i>
            </div>
            <div>
                <div class="stat-label" style="font-size: 0.75rem;">Cancelled</div>
                <h4 class="stat-value fs-5 text-dark"><?= number_format($prStats['cancelled']) ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid: Recent PRs & System Audit -->
<div class="row g-4">
    <!-- Recent Purchase Requests Table -->
    <div class="col-lg-8">
        <div class="card-cms h-100">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-cart-check-fill text-primary"></i>
                    <span>Recent Purchase Requests</span>
                </h3>
                <a href="<?= url('modules/purchase_requests/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    View All Requisitions <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-cms-body p-0">
                <?php if (!empty($recentRequests)): ?>
                    <div class="table-responsive">
                        <table class="table table-cms align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Request No</th>
                                    <th>Department</th>
                                    <th>Requested By</th>
                                    <th>Priority</th>
                                    <th class="text-end">Est. Total</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRequests as $pr): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                <?= e($pr['request_no']) ?>
                                            </a>
                                            <div class="text-muted" style="font-size: 0.6875rem;">
                                                <?= formatDate($pr['request_date'], 'd M Y') ?>
                                            </div>
                                        </td>
                                        <td class="small">
                                            <span class="badge bg-light text-dark border"><?= e($pr['department_code']) ?></span>
                                        </td>
                                        <td class="small fw-semibold text-dark">
                                            <?= e($pr['requester_name']) ?>
                                        </td>
                                        <td>
                                            <?= getPriorityBadge($pr['priority']) ?>
                                        </td>
                                        <td class="text-end font-monospace small fw-bold text-dark">
                                            <?= formatCurrency($pr['estimated_total']) ?>
                                        </td>
                                        <td>
                                            <?= getStatusBadge($pr['status']) ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= url('modules/purchase_requests/view.php?id=' . $pr['id']) ?>" class="btn btn-outline-secondary btn-sm" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cart-x fs-1 d-block mb-2 text-light"></i>
                        <p class="mb-2">No purchase requests recorded yet.</p>
                        <a href="<?= url('modules/purchase_requests/create.php') ?>" class="btn btn-primary-cms btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> Create First Request
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Activity Logs -->
    <div class="col-lg-4">
        <div class="card-cms h-100">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-activity text-primary"></i>
                    <span>Recent Audit Activity</span>
                </h3>
            </div>
            <div class="card-cms-body p-0">
                <?php if (!empty($activityLogs)): ?>
                    <div class="list-group list-group-flush small">
                        <?php foreach ($activityLogs as $log): ?>
                            <div class="list-group-item py-2 px-3">
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <span class="fw-bold text-dark"><?= e($log['action']) ?></span>
                                    <span class="text-muted" style="font-size: 0.6875rem;"><?= formatDate($log['created_at'], 'd M, h:i A') ?></span>
                                </div>
                                <div class="text-muted text-truncate" style="font-size: 0.75rem;" title="<?= e($log['description']) ?>">
                                    <?= e($log['description'] ?? '-') ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.6875rem;">
                                    By <strong class="text-dark"><?= e($log['full_name'] ?? 'System') ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">
                        No activity logs available.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
