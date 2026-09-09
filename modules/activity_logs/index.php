<?php
/**
 * System Audit & Activity Logs Viewer (Administrator & Manager)
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Activity Logs';
$pageSubtitle = 'Complete System Audit Trail, User Operations, and Security Logs';
$activeNav = 'activity_logs';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireRole(['administrator', 'manager']);

$db = getDb();

// 1. Parse GET Filters
$search   = sanitizeInput($_GET['search'] ?? '');
$userId   = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$action   = sanitizeInput($_GET['action'] ?? '');
$dateFrom = sanitizeInput($_GET['date_from'] ?? '');
$dateTo   = sanitizeInput($_GET['date_to'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

// 2. Fetch Lookups for Filters
$usersList = [];
$actionsList = [];

try {
    $usersList = $db->query("SELECT id, full_name, username FROM users WHERE deleted_at IS NULL ORDER BY full_name ASC")->fetchAll();
    $actionsList = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log('Activity Logs Lookups Error: ' . $e->getMessage());
}

// 3. Build Prepared SQL Query
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(a.description LIKE :search OR a.action LIKE :search OR a.ip_address LIKE :search OR u.full_name LIKE :search OR u.username LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($userId)) {
    $where[] = "a.user_id = :uid";
    $params[':uid'] = $userId;
}

if (!empty($action)) {
    $where[] = "a.action = :act";
    $params[':act'] = $action;
}

if (!empty($dateFrom)) {
    $where[] = "DATE(a.created_at) >= :df";
    $params[':df'] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "DATE(a.created_at) <= :dt";
    $params[':dt'] = $dateTo;
}

$whereClause = implode(' AND ', $where);

$totalLogs = 0;
$logs = [];

try {
    // Total Count for Pagination
    $cntStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM activity_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        WHERE {$whereClause}
    ");
    $cntStmt->execute($params);
    $totalLogs = (int)$cntStmt->fetchColumn();

    // Fetch Paginated Records
    $sql = "
        SELECT 
            a.*,
            u.full_name,
            u.username,
            (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = u.id) AS role_names
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE {$whereClause}
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('Activity Logs Query Error: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($totalLogs / $perPage));

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Filter Card -->
<div class="card-cms mb-4">
    <div class="card-cms-header">
        <h3 class="card-cms-title">
            <i class="bi bi-funnel-fill text-primary"></i>
            <span>Filter Audit Logs</span>
        </h3>
    </div>
    <div class="card-cms-body p-3">
        <form method="GET" action="<?= url('modules/activity_logs/index.php') ?>">
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <label class="form-label-cms small">Search Keyword</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search action, description, IP..." value="<?= e($search) ?>">
                </div>

                <div class="col-sm-6 col-md-3">
                    <label class="form-label-cms small">User / Account</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">All Users</option>
                        <?php foreach ($usersList as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($userId == $u['id']) ? 'selected' : '' ?>>
                                <?= e($u['full_name']) ?> (@<?= e($u['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-md-2">
                    <label class="form-label-cms small">Action Type</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <?php foreach ($actionsList as $actName): ?>
                            <option value="<?= e($actName) ?>" <?= ($action === $actName) ? 'selected' : '' ?>>
                                <?= e($actName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-md-2">
                    <label class="form-label-cms small">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>

                <div class="col-sm-6 col-md-2">
                    <label class="form-label-cms small">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end pt-2 border-top">
                    <a href="<?= url('modules/activity_logs/index.php') ?>" class="btn btn-outline-secondary btn-sm">
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

<!-- Logs Table Card -->
<div class="card-cms">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-activity text-primary"></i>
            <span>System Audit Trail (<?= number_format($totalLogs) ?> Events)</span>
        </h3>
        <span class="badge bg-secondary-subtle text-dark border small">Page <?= $page ?> of <?= $totalPages ?></span>
    </div>

    <div class="card-cms-body p-0">
        <?php if (!empty($logs)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0" style="font-size: 0.8125rem;">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Timestamp</th>
                            <th>User Account</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th style="width: 140px;">Client Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="small text-muted font-monospace">
                                    <?= formatDate($log['created_at'], 'd M Y, h:i A') ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['full_name'])): ?>
                                        <div class="fw-semibold text-dark"><?= e($log['full_name']) ?></div>
                                        <div class="text-muted font-monospace small" style="font-size: 0.6875rem;">@<?= e($log['username']) ?> &bull; <?= e($log['role_names'] ?? 'User') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted font-monospace">System / CLI</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1">
                                        <?= e($log['action']) ?>
                                    </span>
                                </td>
                                <td class="text-dark">
                                    <?= e($log['description'] ?? '-') ?>
                                </td>
                                <td class="font-monospace small text-muted">
                                    <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                                <td class="text-muted small text-truncate" style="max-width: 140px;" title="<?= e($log['user_agent'] ?? '') ?>">
                                    <?= e(substr($log['user_agent'] ?? 'Web Browser', 0, 30)) ?>...
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="card-cms-footer p-3 bg-light border-top d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        Showing <?= $offset + 1 ?> &ndash; <?= min($totalLogs, $offset + $perPage) ?> of <?= number_format($totalLogs) ?> logs
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('modules/activity_logs/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Previous</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('modules/activity_logs/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('modules/activity_logs/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clock-history fs-1 d-block mb-2 text-light"></i>
                <p class="mb-0">No audit activity records found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
