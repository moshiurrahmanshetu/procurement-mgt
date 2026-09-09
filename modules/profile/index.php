<?php
/**
 * User Profile Page
 * Procurement Management CMS
 */

$pageTitle = 'My Profile';
$pageSubtitle = 'Account Overview and Security Settings';
$activeNav = 'profile';

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// Force fresh user data
$user = currentUser(true);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row g-4">
    <!-- Left Column: User Summary Card & Avatar Management -->
    <div class="col-lg-4">
        <!-- Profile Identity Card -->
        <div class="card-cms text-center mb-4">
            <div class="card-cms-body p-4">
                <div class="mb-3 position-relative d-inline-block">
                    <?php if (!empty($user['avatar']) && file_exists(AVATAR_UPLOAD_DIR . $user['avatar'])): ?>
                        <img src="<?= asset('uploads/avatars/' . $user['avatar']) ?>" id="avatarPreview" alt="<?= e($user['full_name']) ?>" 
                             class="rounded-circle shadow-sm border border-2 border-white" style="width: 100px; height: 100px; object-fit: cover;">
                        <div id="avatarFallback" class="d-none"></div>
                    <?php else: ?>
                        <div id="avatarFallback">
                            <?= renderAvatar($user, 100, 'shadow-sm') ?>
                        </div>
                        <img src="" id="avatarPreview" alt="Preview" 
                             class="rounded-circle shadow-sm border border-2 border-white d-none" style="width: 100px; height: 100px; object-fit: cover;">
                    <?php endif; ?>
                </div>

                <h4 class="fw-bold mb-1 text-dark"><?= e($user['full_name']) ?></h4>
                <p class="text-muted small mb-2 font-monospace">@<?= e($user['username']) ?></p>

                <div class="d-flex justify-content-center gap-2 mb-3">
                    <span class="badge badge-role"><?= e($user['role_names'] ?? 'User') ?></span>
                    <span class="badge badge-status-active"><?= ucfirst(e($user['status'])) ?></span>
                </div>

                <!-- Avatar Upload / Remove Form -->
                <form action="<?= url('modules/profile/update-avatar.php') ?>" method="POST" enctype="multipart/form-data" class="mt-3 pt-3 border-top">
                    <?= csrfField() ?>
                    
                    <div class="mb-3 text-start">
                        <label for="avatarFileInput" class="form-label-cms small">Update Profile Photo</label>
                        <input class="form-control form-control-sm" type="file" id="avatarFileInput" name="avatar" accept="image/jpeg,image/png,image/webp" required>
                        <div class="form-text small" style="font-size: 0.75rem;">JPG, PNG, or WEBP (Max 2MB)</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-cms btn-sm flex-grow-1">
                            <i class="bi bi-upload me-1"></i> Upload Photo
                        </button>

                        <?php if (!empty($user['avatar'])): ?>
                            <button type="submit" name="action" value="remove" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove custom avatar photo?');">
                                <i class="bi bi-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Account Meta Details Card -->
        <div class="card-cms">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-info-circle text-primary"></i>
                    <span>Account Metadata</span>
                </h3>
            </div>
            <div class="card-cms-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <span class="text-muted">Account ID</span>
                        <span class="font-monospace fw-semibold">#<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <span class="text-muted">Member Since</span>
                        <span class="text-dark fw-semibold"><?= formatDate($user['created_at'], 'd M Y') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <span class="text-muted">Last Login</span>
                        <span class="text-dark fw-semibold"><?= formatDate($user['last_login']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <span class="text-muted">Last Activity</span>
                        <span class="text-dark fw-semibold"><?= formatDate($user['last_activity']) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Edit Profile Details & Change Password Forms -->
    <div class="col-lg-8">
        <!-- 1. Edit Profile Details Card -->
        <div class="card-cms mb-4">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-person-lines-fill text-primary"></i>
                    <span>Edit Profile Details</span>
                </h3>
            </div>
            <div class="card-cms-body">
                <form action="<?= url('modules/profile/update-profile.php') ?>" method="POST">
                    <?= csrfField() ?>

                    <div class="row g-3">
                        <!-- Full Name -->
                        <div class="col-md-12">
                            <label for="full_name" class="form-label-cms">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-cms" id="full_name" name="full_name" 
                                   value="<?= e($user['full_name']) ?>" required maxlength="150">
                        </div>

                        <!-- Username -->
                        <div class="col-md-6">
                            <label for="username" class="form-label-cms">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">@</span>
                                <input type="text" class="form-control form-control-cms" id="username" name="username" 
                                       value="<?= e($user['username']) ?>" required maxlength="100">
                            </div>
                            <div class="form-text small">Used for sign in. Must be unique.</div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="email" class="form-label-cms">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-cms" id="email" name="email" 
                                   value="<?= e($user['email']) ?>" required maxlength="191">
                            <div class="form-text small">Account notifications & password resets.</div>
                        </div>
                    </div>

                    <div class="mt-4 pt-2 border-top text-end">
                        <button type="submit" class="btn btn-primary-cms">
                            <i class="bi bi-check2 me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Change Password Card -->
        <div class="card-cms">
            <div class="card-cms-header">
                <h3 class="card-cms-title">
                    <i class="bi bi-key-fill text-primary"></i>
                    <span>Change Password</span>
                </h3>
            </div>
            <div class="card-cms-body">
                <form action="<?= url('modules/profile/change-password.php') ?>" method="POST">
                    <?= csrfField() ?>

                    <div class="row g-3">
                        <!-- Current Password -->
                        <div class="col-md-12">
                            <label for="current_password" class="form-label-cms">Current Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-cms" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password" aria-label="Toggle password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div class="col-md-6">
                            <label for="new_password" class="form-label-cms">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-cms" id="new_password" name="new_password" minlength="8" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password" aria-label="Toggle password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text small">Minimum 8 characters.</div>
                        </div>

                        <!-- Confirm New Password -->
                        <div class="col-md-6">
                            <label for="new_password_confirm" class="form-label-cms">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-cms" id="new_password_confirm" name="new_password_confirm" minlength="8" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password_confirm" aria-label="Toggle password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-2 border-top text-end">
                        <button type="submit" class="btn btn-primary-cms">
                            <i class="bi bi-shield-lock me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
