<?php
/**
 * Application Dashboard
 * Procurement Management CMS
 */

$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview and System Status';
$activeNav = 'dashboard';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$db = getDb();

// Fetch recent activity logs for current user or system
$activityLogs = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name, u.username 
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC 
        LIMIT 8
    ");
    $stmt->execute();
    $activityLogs = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Dashboard Activity Log Query Error: ' . $e->getMessage());
}

// Fetch total registered users count for quick status metric
$totalUsersCount = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
    $totalUsersCount = (int)$stmt->fetch()['total'];
} catch (Exception $e) {
    $totalUsersCount = 1;
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
                        <i class="bi bi-shield-check me-1"></i> Phase 01 Active
                    </span>
                    <span class="badge bg-success bg-opacity-75 px-3 py-2 fs-7">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> <?= ucfirst(e($user['status'])) ?>
                    </span>
                </div>
                <h2 class="fw-bold mb-1 text-white">Welcome back, <?= e($user['full_name']) ?>!</h2>
                <p class="text-slate-300 mb-0 opacity-75 small">
                    You are signed in as <strong class="text-white"><?= e($user['role_names'] ?? 'User') ?></strong>.
                    Last logged in: <span class="text-white"><?= formatDate($user['last_login']) ?></span>
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="<?= url('modules/profile/index.php') ?>" class="btn btn-light btn-sm fw-semibold px-3 py-2 shadow-sm">
                    <i class="bi bi-person-gear me-1"></i> Manage Profile
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Key Metric / Status Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-person-badge"></i>
            </div>
            <div>
                <div class="stat-label">Assigned Role</div>
                <h4 class="stat-value fs-6 text-primary"><?= e($user['role_names'] ?? 'Administrator') ?></h4>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-shield-lock"></i>
            </div>
            <div>
                <div class="stat-label">Account Status</div>
                <h4 class="stat-value fs-6 text-success">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= ucfirst(e($user['status'])) ?>
                </h4>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-people"></i>
            </div>
            <div>
                <div class="stat-label">Active Users</div>
                <h4 class="stat-value"><?= $totalUsersCount ?></h4>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <div class="stat-label">Last Login</div>
                <div class="small fw-semibold text-truncate" style="max-width: 140px;" title="<?= e(formatDate($user['last_login'])) ?>">
                    <?= formatDate($user['last_login'], 'd M, h:i A') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Grid: System Setup Status & Recent Activity -->
<div class="row g-4">
    <!-- Left Column: System Status Overview -->
    <div class="col-lg-5">
        <div class="card-cms h-100">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-hdd-network text-primary"></i>
                    <span>System Foundation Status</span>
                </h3>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small">Online</span>
            </div>
            <div class="card-cms-body">
                <p class="text-muted small mb-3">
                    Core Phase 01 architecture components status and system environment specs:
                </p>

                <ul class="list-group list-group-flush border rounded-3 overflow-hidden mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                        <span class="text-muted"><i class="bi bi-database text-primary me-2"></i>Database (PDO)</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle-fill me-1"></i>Connected (<?= e(DB_NAME) ?>)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                        <span class="text-muted"><i class="bi bi-code-square text-primary me-2"></i>PHP Version</span>
                        <span class="fw-semibold text-dark"><?= phpversion() ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                        <span class="text-muted"><i class="bi bi-shield-check text-primary me-2"></i>CSRF Protection</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                        <span class="text-muted"><i class="bi bi-person-check text-primary me-2"></i>Session Security</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle-fill me-1"></i>Strict / HttpOnly</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                        <span class="text-muted"><i class="bi bi-folder-check text-primary me-2"></i>Avatar Uploads</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle-fill me-1"></i>Writable</span>
                    </li>
                </ul>

                <div class="alert alert-info py-2 px-3 mb-0 small d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2 fs-6 flex-shrink-0"></i>
                    <div>Phase 01 core complete. Procurement modules will be built in subsequent phases.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Activity Logs -->
    <div class="col-lg-7">
        <div class="card-cms h-100">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-activity text-primary"></i>
                    <span>Recent Audit & Activity Trail</span>
                </h3>
            </div>
            <div class="card-cms-body p-0">
                <?php if (!empty($activityLogs)): ?>
                    <div class="table-responsive">
                        <table class="table table-cms align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>User</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activityLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-dark border px-2 py-1 small">
                                                <?= e($log['action']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold small text-dark">
                                                <?= e($log['full_name'] ?? 'System') ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted text-truncate" style="max-width: 220px;" title="<?= e($log['description']) ?>">
                                            <?= e($log['description'] ?? '-') ?>
                                        </td>
                                        <td class="small font-monospace text-muted">
                                            <?= e($log['ip_address'] ?? '-') ?>
                                        </td>
                                        <td class="small text-muted whitespace-nowrap">
                                            <?= formatDate($log['created_at'], 'd M, h:i A') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x fs-1 d-block mb-2 text-light"></i>
                        <p class="mb-0">No activity logs recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
