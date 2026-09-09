<?php
/**
 * Edit System User (Administrator Only)
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Edit User Account';
$pageSubtitle = 'Modify Account Information, Permissions, and Status';
$activeNav = 'users';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireRole('administrator');

$userId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    setFlash('error', 'Invalid user ID specified.');
    redirect('modules/users/index.php');
}

$db = getDb();
$userToEdit = null;
$roles = [];
$assignedRoleId = 0;
$isLastAdmin = false;

try {
    // 1. Fetch User
    $stmt = $db->prepare("
        SELECT u.*, ur.role_id, r.slug AS role_slug
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.id = :id AND u.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $userId]);
    $userToEdit = $stmt->fetch();

    if (!$userToEdit) {
        setFlash('error', 'The requested user account was not found.');
        redirect('modules/users/index.php');
    }

    $assignedRoleId = (int)($userToEdit['role_id'] ?? 0);

    // 2. Fetch Active Roles
    $roles = $db->query("SELECT id, name, slug, description FROM roles WHERE status = 'active' ORDER BY id ASC")->fetchAll();

    // 3. Check if user is the last active Administrator
    if ($userToEdit['role_slug'] === 'administrator' && $userToEdit['status'] === 'active') {
        $adminCount = (int)$db->query("
            SELECT COUNT(DISTINCT u.id)
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.slug = 'administrator' AND u.status = 'active' AND u.deleted_at IS NULL
        ")->fetchColumn();

        if ($adminCount <= 1) {
            $isLastAdmin = true;
        }
    }

} catch (Exception $e) {
    error_log('User Edit Fetch Error: ' . $e->getMessage());
    setFlash('error', 'Database error while loading user details.');
    redirect('modules/users/index.php');
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <?php if ($isLastAdmin): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4 border-warning shadow-sm">
                <i class="bi bi-shield-exclamation fs-4 flex-shrink-0 text-warning"></i>
                <div class="small">
                    <strong>Last Active Administrator Account:</strong> This user is currently the sole active Administrator in the system. The Administrator role and active status are locked to prevent administrative lockout.
                </div>
            </div>
        <?php endif; ?>

        <div class="card-cms">
            <div class="card-cms-header d-flex justify-content-between align-items-center">
                <h3 class="card-cms-title">
                    <i class="bi bi-person-gear text-primary"></i>
                    <span>Edit User: <?= e($userToEdit['full_name']) ?> (#<?= str_pad($userToEdit['id'], 3, '0', STR_PAD_LEFT) ?>)</span>
                </h3>
                <a href="<?= url('modules/users/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Users List
                </a>
            </div>

            <form action="<?= url('modules/users/update.php') ?>" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $userToEdit['id'] ?>">

                <div class="card-cms-body p-4">
                    <div class="row g-3">
                        <!-- Full Name -->
                        <div class="col-md-12">
                            <label for="full_name" class="form-label-cms">Full Legal / Employee Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-cms" id="full_name" name="full_name" value="<?= e($userToEdit['full_name']) ?>" required maxlength="150">
                        </div>

                        <!-- Username -->
                        <div class="col-md-6">
                            <label for="username" class="form-label-cms">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">@</span>
                                <input type="text" class="form-control form-control-cms font-monospace" id="username" name="username" value="<?= e($userToEdit['username']) ?>" required maxlength="100" pattern="[a-zA-Z0-9_\-\.]{3,100}">
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="email" class="form-label-cms">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-cms" id="email" name="email" value="<?= e($userToEdit['email']) ?>" required maxlength="191">
                        </div>

                        <!-- Role Selection -->
                        <div class="col-md-6">
                            <label for="role_id" class="form-label-cms">Assigned System Role <span class="text-danger">*</span></label>
                            <?php if ($isLastAdmin): ?>
                                <input type="hidden" name="role_id" value="<?= $assignedRoleId ?>">
                                <select class="form-select form-control-cms" id="role_id_disabled" disabled>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= ($assignedRoleId == $r['id']) ? 'selected' : '' ?>>
                                            <?= e($r['name']) ?> (Sole Administrator - Locked)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small text-warning"><i class="bi bi-lock-fill"></i> Role cannot be demoted for the last active administrator.</div>
                            <?php else: ?>
                                <select class="form-select form-control-cms" id="role_id" name="role_id" required>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= ($assignedRoleId == $r['id']) ? 'selected' : '' ?>>
                                            <?= e($r['name']) ?> &mdash; <?= e($r['description']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- Account Status -->
                        <div class="col-md-6">
                            <label for="status" class="form-label-cms">Account Status <span class="text-danger">*</span></label>
                            <?php if ($isLastAdmin): ?>
                                <input type="hidden" name="status" value="active">
                                <select class="form-select form-control-cms" id="status_disabled" disabled>
                                    <option value="active" selected>Active (Sole Administrator - Locked)</option>
                                </select>
                                <div class="form-text small text-warning"><i class="bi bi-lock-fill"></i> Status cannot be deactivated for the last active administrator.</div>
                            <?php else: ?>
                                <select class="form-select form-control-cms" id="status" name="status" required>
                                    <option value="active" <?= ($userToEdit['status'] === 'active') ? 'selected' : '' ?>>Active (Can login)</option>
                                    <option value="inactive" <?= ($userToEdit['status'] === 'inactive') ? 'selected' : '' ?>>Inactive (Disabled)</option>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- Password Change Header (Optional) -->
                        <div class="col-12 mt-4 pt-3 border-top">
                            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-key-fill text-primary me-1"></i> Change Password (Optional)</h6>
                            <p class="text-muted small mb-3">Leave both password fields blank if you do not want to change this user's password.</p>
                        </div>

                        <!-- New Password -->
                        <div class="col-md-6">
                            <label for="password" class="form-label-cms">New Password</label>
                            <input type="password" class="form-control form-control-cms" id="password" name="password" minlength="6" placeholder="Leave blank to keep unchanged">
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label-cms">Confirm New Password</label>
                            <input type="password" class="form-control form-control-cms" id="password_confirm" name="password_confirm" minlength="6" placeholder="Re-enter new password">
                        </div>
                    </div>
                </div>

                <div class="card-cms-footer p-3 bg-light border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">User registered on: <?= formatDate($userToEdit['created_at'], 'd M Y') ?></span>
                    <button type="submit" class="btn btn-primary-cms px-4">
                        <i class="bi bi-check-lg me-1"></i> Update User Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
