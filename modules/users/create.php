<?php
/**
 * Create New System User (Administrator Only)
 * Procurement Management CMS - Phase 06
 */

$pageTitle = 'Add New User';
$pageSubtitle = 'Create System Account and Assign Operational Role';
$activeNav = 'users';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireRole('administrator');

$db = getDb();
$roles = [];
try {
    $roles = $db->query("SELECT id, name, slug, description FROM roles WHERE status = 'active' ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {
    error_log('User Create Roles Lookup Error: ' . $e->getMessage());
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-cms">
            <div class="card-cms-header d-flex justify-content-between align-items-center">
                <h3 class="card-cms-title">
                    <i class="bi bi-person-plus-fill text-primary"></i>
                    <span>New User Account Details</span>
                </h3>
                <a href="<?= url('modules/users/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Users List
                </a>
            </div>

            <form action="<?= url('modules/users/store.php') ?>" method="POST">
                <?= csrfField() ?>

                <div class="card-cms-body p-4">
                    <div class="row g-3">
                        <!-- Full Name -->
                        <div class="col-md-12">
                            <label for="full_name" class="form-label-cms">Full Legal / Employee Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-cms" id="full_name" name="full_name" placeholder="e.g. Jane Doe" required maxlength="150">
                        </div>

                        <!-- Username -->
                        <div class="col-md-6">
                            <label for="username" class="form-label-cms">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">@</span>
                                <input type="text" class="form-control form-control-cms font-monospace" id="username" name="username" placeholder="janedoe" required maxlength="100" pattern="[a-zA-Z0-9_\-\.]{3,100}">
                            </div>
                            <div class="form-text small">Unique login identifier (alphanumeric, min 3 chars).</div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="email" class="form-label-cms">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-cms" id="email" name="email" placeholder="jane.doe@example.com" required maxlength="191">
                        </div>

                        <!-- Role Selection -->
                        <div class="col-md-6">
                            <label for="role_id" class="form-label-cms">Assigned System Role <span class="text-danger">*</span></label>
                            <select class="form-select form-control-cms" id="role_id" name="role_id" required>
                                <option value="">Select Role...</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= ($r['slug'] === 'requester') ? 'selected' : '' ?>>
                                        <?= e($r['name']) ?> &mdash; <?= e($r['description']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Initial Account Status -->
                        <div class="col-md-6">
                            <label for="status" class="form-label-cms">Account Status <span class="text-danger">*</span></label>
                            <select class="form-select form-control-cms" id="status" name="status" required>
                                <option value="active" selected>Active (Can login immediately)</option>
                                <option value="inactive">Inactive (Disabled)</option>
                            </select>
                        </div>

                        <!-- Password -->
                        <div class="col-md-6">
                            <label for="password" class="form-label-cms">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-cms" id="password" name="password" required minlength="6" placeholder="Min 6 characters">
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label-cms">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-cms" id="password_confirm" name="password_confirm" required minlength="6" placeholder="Re-enter password">
                        </div>
                    </div>
                </div>

                <div class="card-cms-footer p-3 bg-light border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="bi bi-info-circle me-1"></i> Passwords are automatically secured with bcrypt encryption.</span>
                    <button type="submit" class="btn btn-primary-cms px-4">
                        <i class="bi bi-person-check-fill me-1"></i> Create User Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
