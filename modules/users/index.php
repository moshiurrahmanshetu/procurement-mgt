<?php
/**
 * Users Management List (Administrator Only)
 * Procurement Management CMS
 */

$pageTitle = 'Users Management';
$pageSubtitle = 'System Accounts and Role Assignments';
$activeNav = 'users';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireRole('administrator');

$db = getDb();
$users = [];

try {
    $stmt = $db->query("
        SELECT u.id, u.full_name, u.username, u.email, u.avatar, u.status, u.last_login, u.created_at,
               GROUP_CONCAT(r.name SEPARATOR ', ') AS role_names
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.deleted_at IS NULL
        GROUP BY u.id
        ORDER BY u.id ASC
    ");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Users List Query Error: ' . $e->getMessage());
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card-cms">
    <div class="card-cms-header">
        <h3 class="card-cms-title">
            <i class="bi bi-people-fill text-primary"></i>
            <span>Registered System Users</span>
        </h3>
        <span class="badge bg-secondary-subtle text-dark border px-2 py-1 small">
            <?= count($users) ?> Total Accounts
        </span>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="font-monospace text-muted small">
                                    #<?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= renderAvatar($u, 36) ?>
                                        <div class="fw-semibold text-dark"><?= e($u['full_name']) ?></div>
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
                                <td class="small text-muted">
                                    <?= formatDate($u['last_login'], 'd M Y, h:i A') ?>
                                </td>
                                <td class="small text-muted">
                                    <?= formatDate($u['created_at'], 'd M Y') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people fs-1 d-block mb-2 text-light"></i>
                <p class="mb-0">No active users found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
