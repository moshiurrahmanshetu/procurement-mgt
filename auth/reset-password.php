<?php
/**
 * Reset Password Page
 * Procurement Management CMS
 */

require_once dirname(__DIR__) . '/includes/init.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('modules/dashboard/index.php');
}

$token = sanitizeInput($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$isValidToken = false;
$resetRecord = null;

if (empty($token)) {
    $errors[] = 'Missing password reset token.';
} else {
    try {
        $db = getDb();
        $hashedToken = hash('sha256', $token);

        $stmt = $db->prepare("
            SELECT pr.*, u.id as user_id, u.full_name, u.email, u.status, u.deleted_at
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = :token 
              AND pr.used_at IS NULL 
              AND pr.expires_at > NOW()
              AND u.deleted_at IS NULL
              AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':token' => $hashedToken]);
        $resetRecord = $stmt->fetch();

        if ($resetRecord) {
            $isValidToken = true;
        } else {
            $errors[] = 'This password reset link is invalid or has expired. Please request a new one.';
        }
    } catch (Exception $e) {
        error_log('Reset Password Query Error: ' . $e->getMessage());
        $errors[] = 'An error occurred while validating the reset token.';
    }
}

// Process Password Reset Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidToken) {
    if (!validateCsrfToken()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($password)) {
            $errors[] = 'Please enter a new password.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'The new password must be at least 8 characters long.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'The password confirmation does not match.';
        }

        if (empty($errors)) {
            try {
                $db = getDb();
                $db->beginTransaction();

                // 1. Update user password
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateUserStmt = $db->prepare("
                    UPDATE users 
                    SET password = :password, updated_at = NOW() 
                    WHERE id = :id
                ");
                $updateUserStmt->execute([
                    ':password' => $newHash,
                    ':id'       => $resetRecord['user_id']
                ]);

                // 2. Mark token as used
                $updateResetStmt = $db->prepare("
                    UPDATE password_resets 
                    SET used_at = NOW() 
                    WHERE id = :id
                ");
                $updateResetStmt->execute([':id' => $resetRecord['id']]);

                // 3. Log activity
                logActivity((int)$resetRecord['user_id'], 'Password Reset Completed', 'User successfully reset their account password.');

                $db->commit();

                setFlash('success', 'Your password has been reset successfully! You can now sign in with your new password.');
                redirect('auth/login.php');
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Password Reset Error: ' . $e->getMessage());
                $errors[] = 'Failed to reset password. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Set New Password | <?= e(APP_NAME) ?></title>

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/bootstrap-icons.css') ?>">

    <!-- Auth Styles -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
</head>
<body class="auth-body">
<div class="auth-card-wrapper">
    <!-- Brand Header -->
    <div class="auth-brand-header">
        <svg class="auth-brand-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 48" fill="none">
            <rect width="42" height="42" rx="10" fill="#2563eb" />
            <path d="M14 26L21 16H28L21 26H14Z" fill="#ffffff" fill-opacity="0.9" />
            <path d="M21 26L28 16H35L28 26H21Z" fill="#60a5fa" />
            <path d="M14 31H28C30.2 31 32 29.2 32 27V25H28V27H14V31Z" fill="#ffffff" />
            <text x="54" y="27" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="20" font-weight="700" fill="#0f172a" letter-spacing="-0.5">Procure<tspan fill="#2563eb">CMS</tspan></text>
            <text x="54" y="38" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="9" font-weight="600" fill="#64748b" letter-spacing="1.2">ENTERPRISE</text>
        </svg>
        <p class="auth-subtitle">Procurement & Supply Chain Management</p>
    </div>

    <!-- Card -->
    <div class="auth-card">
        <h2 class="auth-title">Set New Password</h2>
        
        <?php if ($isValidToken): ?>
            <p class="auth-instruction">Enter your new secure password for <strong><?= e($resetRecord['email']) ?></strong>.</p>
        <?php else: ?>
            <p class="auth-instruction">Password reset link status.</p>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                <div class="flex-grow-1">
                    <?php foreach ($errors as $error): ?>
                        <div><?= e($error) ?></div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($isValidToken): ?>
            <form action="<?= url('auth/reset-password.php') ?>" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <!-- New Password Input -->
                <div class="mb-3">
                    <label for="password" class="form-label-cms">New Password</label>
                    <div class="auth-input-group">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Minimum 8 characters" minlength="8" required autofocus>
                        <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm New Password Input -->
                <div class="mb-4">
                    <label for="password_confirm" class="form-label-cms">Confirm New Password</label>
                    <div class="auth-input-group">
                        <i class="bi bi-shield-check input-icon"></i>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                               placeholder="Re-enter new password" minlength="8" required>
                        <button type="button" class="toggle-password" data-target="password_confirm" aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="auth-btn-primary">
                    <i class="bi bi-check2-circle"></i>
                    <span>Update Password</span>
                </button>
            </form>
        <?php else: ?>
            <div class="text-center py-2">
                <a href="<?= url('auth/forgot-password.php') ?>" class="btn btn-primary-cms w-100">
                    <i class="bi bi-arrow-repeat me-1"></i> Request New Reset Link
                </a>
            </div>
        <?php endif; ?>

        <div class="auth-links">
            <a href="<?= url('auth/login.php') ?>" class="d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Sign In</span>
            </a>
        </div>
    </div>

    <!-- Auth Footer -->
    <div class="auth-footer-text">
        &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
    </div>
</div>

<!-- Scripts -->
<script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
