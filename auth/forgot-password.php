<?php
/**
 * Forgot Password Request Page
 * Procurement Management CMS
 */

require_once dirname(__DIR__) . '/includes/init.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('modules/dashboard/index.php');
}

$errors = [];
$successMessage = '';
$devResetUrl = null;
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $emailInput = sanitizeInput($_POST['email'] ?? '');

        if (empty($emailInput)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($errors)) {
            try {
                $db = getDb();
                $stmt = $db->prepare("
                    SELECT id, full_name, email, status 
                    FROM users 
                    WHERE email = :email AND deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([':email' => $emailInput]);
                $user = $stmt->fetch();

                if ($user && $user['status'] === 'active') {
                    // Generate secure random token
                    $plainToken = bin2hex(random_bytes(32));
                    $hashedToken = hash('sha256', $plainToken);
                    $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);

                    // Invalidate previous unused tokens for this user
                    $deleteStmt = $db->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
                    $deleteStmt->execute([':user_id' => $user['id']]);

                    // Insert new token
                    $insertStmt = $db->prepare("
                        INSERT INTO password_resets (user_id, token, expires_at, created_at)
                        VALUES (:user_id, :token, :expires_at, NOW())
                    ");
                    $insertStmt->execute([
                        ':user_id'    => $user['id'],
                        ':token'      => $hashedToken,
                        ':expires_at' => $expiresAt
                    ]);

                    logActivity((int)$user['id'], 'Password Reset Requested', 'Password reset link generated.');

                    // In development mode, provide the direct test link
                    if (defined('DEV_MODE') && DEV_MODE) {
                        $devResetUrl = url('auth/reset-password.php?token=' . $plainToken);
                    }
                }

                // Generic message to prevent email enumeration
                $successMessage = 'If an active account with that email exists, reset instructions have been generated.';
            } catch (Exception $e) {
                error_log('Forgot Password Error: ' . $e->getMessage());
                $errors[] = 'An error occurred while processing your request. Please try again later.';
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
    <title>Forgot Password | <?= e(APP_NAME) ?></title>

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
        <h2 class="auth-title">Reset Password</h2>
        <p class="auth-instruction">Enter your registered email address to receive password reset instructions.</p>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
                <i class="bi bi-check-circle-fill fs-5 me-2 flex-shrink-0"></i>
                <div class="flex-grow-1 small"><?= e($successMessage) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($devResetUrl): ?>
            <!-- Development Mode Helper Link -->
            <div class="dev-reset-box">
                <div class="d-flex align-items-center gap-2 text-primary fw-semibold mb-1">
                    <i class="bi bi-code-slash"></i>
                    <span>Development Mode Link:</span>
                </div>
                <p class="text-muted small mb-2">Since local SMTP email is not configured, use this one-time link:</p>
                <a href="<?= e($devResetUrl) ?>" class="btn btn-sm btn-outline-primary w-100 text-truncate font-monospace" style="font-size: 0.75rem;">
                    <?= e($devResetUrl) ?>
                </a>
            </div>
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

        <form action="<?= url('auth/forgot-password.php') ?>" method="POST">
            <?= csrfField() ?>

            <!-- Email Input -->
            <div class="mb-4">
                <label for="email" class="form-label-cms">Email Address</label>
                <div class="auth-input-group">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= e($emailInput) ?>" placeholder="e.g. user@example.com" required autofocus>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="auth-btn-primary">
                <i class="bi bi-send"></i>
                <span>Send Reset Instructions</span>
            </button>

            <!-- Back to Login Link -->
            <div class="auth-links">
                <a href="<?= url('auth/login.php') ?>" class="d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to Sign In</span>
                </a>
            </div>
        </form>
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
