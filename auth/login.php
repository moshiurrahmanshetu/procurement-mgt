<?php
/**
 * User Login Page
 * Procurement Management CMS
 */

require_once dirname(__DIR__) . '/includes/init.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('modules/dashboard/index.php');
}

$errors = [];
$loginInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!validateCsrfToken()) {
        $errors[] = 'Invalid security token or session expired. Please refresh and try again.';
    } else {
        $loginInput = sanitizeInput($_POST['login_input'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        // 2. Validate Required Fields
        if (empty($loginInput)) {
            $errors[] = 'Please enter your username or email address.';
        }
        if (empty($password)) {
            $errors[] = 'Please enter your password.';
        }

        // 3. Process Authentication
        if (empty($errors)) {
            try {
                $db = getDb();
                $stmt = $db->prepare("
                    SELECT u.*, 
                           GROUP_CONCAT(r.slug SEPARATOR ',') AS role_slugs,
                           GROUP_CONCAT(r.name SEPARATOR ', ') AS role_names
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id AND r.status = 'active'
                    WHERE (u.username = :username OR u.email = :email)
                      AND u.deleted_at IS NULL
                    GROUP BY u.id
                    LIMIT 1
                ");
                $stmt->execute([
                    ':username' => $loginInput,
                    ':email'    => $loginInput
                ]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'active') {
                        $errors[] = 'Your account has been deactivated. Please contact the system administrator.';
                    } else {
                        // Successful login
                        loginUser($user, $remember);
                        setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                        redirect('modules/dashboard/index.php');
                    }
                } else {
                    // Safe generic error
                    $errors[] = 'Invalid credentials provided. Please check and try again.';
                }
            } catch (Exception $e) {
                error_log('Login Error: ' . $e->getMessage());
                $errors[] = 'An error occurred during authentication. Please try again later.';
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
    <title>Sign In | <?= e(APP_NAME) ?></title>

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

    <!-- Login Card -->
    <div class="auth-card">
        <h2 class="auth-title">Sign In</h2>
        <p class="auth-instruction">Enter your credentials to access your account.</p>

        <!-- Flash Messages -->
        <?= renderFlashMessages() ?>

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

        <form action="<?= url('auth/login.php') ?>" method="POST" autocomplete="on">
            <?= csrfField() ?>

            <!-- Username / Email Input -->
            <div class="mb-3">
                <label for="login_input" class="form-label-cms">Username or Email</label>
                <div class="auth-input-group">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" class="form-control" id="login_input" name="login_input" 
                           value="<?= e($loginInput) ?>" placeholder="e.g. admin or admin@example.com" required autofocus>
                </div>
            </div>

            <!-- Password Input -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label-cms mb-0">Password</label>
                    <a href="<?= url('auth/forgot-password.php') ?>" class="small text-decoration-none text-primary">Forgot password?</a>
                </div>
                <div class="auth-input-group">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                    <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Remember Me -->
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                <label class="form-check-label small text-muted" for="remember">
                    Remember me on this device
                </label>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="auth-btn-primary">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Sign In to Dashboard</span>
            </button>
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
