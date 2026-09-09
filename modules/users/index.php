<?php
/**
 * Users Management List (Administrator Only)
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Users Management';
$pageSubtitle = 'System Accounts, Security Permissions, and Role Assignments';
$activeNav = 'users';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireRole('administrator');

$db = getDb();
$currentUserId = currentUserId();
$search = sanitizeInput($_GET['search'] ?? '');
$roleFilter = sanitizeInput($_GET['role'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? '');

$users = [];
$rolesList = [];

try {
    $rolesList = $db->query("SELECT id, name, slug FROM roles WHERE status = 'active' ORDER BY id ASC")->fetchAll();

    $where = ["u.deleted_at IS NULL"];
    $params = [];

    if (!empty($search)) {
        $where[] = "(u.full_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if (!empty($roleFilter)) {
        $where[] = "r.slug = :rslug";
        $params[':rslug'] = $roleFilter;
    }

    if (!empty($statusFilter)) {
        $where[] = "u.status = :status";
        $params[':status'] = $statusFilter;
    }

    $whereClause = implode(' AND ', $where);

    $sql = "
        SELECT u.id, u.full_name, u.username, u.email, u.avatar, u.status, u.last_login, u.created_at,
               GROUP_CONCAT(r.slug SEPARATOR ',') AS role_slugs,
               GROUP_CONCAT(r.name SEPARATOR ', ') AS role_names
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE {$whereClause}
        GROUP BY u.id
        ORDER BY u.id ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('Users List Query Error: ' . $e->getMessage());
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Header Actions -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark">User Accounts & Access Control</h4>
        <span class="text-muted small"><?= count($users) ?> total registered account(s) match current criteria</span>
    </div>
    <div>
        <a href="<?= url('modules/users/create.php') ?>" class="btn btn-primary-cms btn-sm px-3 shadow-sm">
            <i class="bi bi-person-plus-fill me-1"></i> Add New User
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card-cms mb-4">
    <div class="card-cms-body p-3">
        <form method="GET" action="<?= url('modules/users/index.php') ?>">
            <div class="row g-2 align-items-center">
                <div class="col-sm-6 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, username, or email..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Assigned Roles</option>
                        <?php foreach ($rolesList as $rl): ?>
                            <option value="<?= e($rl['slug']) ?>" <?= ($roleFilter === $rl['slug']) ? 'selected' : '' ?>>
                                <?= e($rl['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary-cms btn-sm flex-grow-1">Filter</button>
                    <?php if (!empty($search) || !empty($roleFilter) || !empty($statusFilter)): ?>
                        <a href="<?= url('modules/users/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table Card -->
<div class="card-cms">
    <div class="card-cms-header d-flex justify-content-between align-items-center">
        <h3 class="card-cms-title">
            <i class="bi bi-people-fill text-primary"></i>
            <span>System Users Directory</span>
        </h3>
    </div>

    <div class="card-cms-body p-0">
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-cms align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>User / Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Assigned Role(s)</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Registered</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php $isSelf = ((int)$u['id'] === $currentUserId); ?>
                            <tr>
                                <td class="font-monospace text-muted small">
                                    #<?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= renderAvatar($u, 36) ?>
                                        <div>
                                            <div class="fw-semibold text-dark">
                                                <?= e($u['full_name']) ?>
                                                <?php if ($isSelf): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 0.625rem;">You</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-monospace small text-muted">@<?= e($u['username']) ?></span>
                                </td>
                                <td class="small">
                                    <a href="mailto:<?= e($u['email']) ?>" class="text-decoration-none text-muted">
                                        <?= e($u['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($u['role_names'])): ?>
                                        <?php $rolesArr = explode(', ', $u['role_names']); ?>
                                        <?php foreach ($rolesArr as $rName): ?>
                                            <span class="badge badge-role me-1"><?= e($rName) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">No Role Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge-status-active">
                                            <i class="bi bi-check-circle me-1"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status-inactive">
                                            <i class="bi bi-x-circle me-1"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= formatDate($u['last_login'], 'd M Y, h:i A') ?>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= formatDate($u['created_at'], 'd M Y') ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 align-items-center">
                                        <!-- Edit Button -->
                                        <a href="<?= url('modules/users/edit.php?id=' . $u['id']) ?>" class="btn btn-outline-secondary btn-sm" title="Edit User">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Toggle Status Form Button -->
                                        <form action="<?= url('modules/users/toggle-status.php') ?>" method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <?php if ($u['status'] === 'active'): ?>
                                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Deactivate User" onclick="return confirm('Deactivate account for <?= addslashes(e($u['full_name'])) ?>?');">
                                                    <i class="bi bi-pause-circle"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-outline-success btn-sm" title="Activate User">
                                                    <i class="bi bi-play-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Delete Button (Hidden for Self) -->
                                        <?php if (!$isSelf): ?>
                                            <form action="<?= url('modules/users/delete.php') ?>" method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete User" onclick="return confirm('Are you sure you want to delete user <?= addslashes(e($u['full_name'])) ?>? This will soft-delete their account.');">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people fs-1 d-block mb-2 text-light"></i>
                <p class="mb-0">No active users match your criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
