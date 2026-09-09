<?php
/**
 * Installation Wizard — Step 4: Administrator Account Setup
 * Procurement Management CMS — Final Complete Installer
 */

require_once __DIR__ . '/includes/installer-functions.php';
require_once __DIR__ . '/includes/requirements.php';

installer_start_session();

// Lock Protection
if (installer_is_locked()) {
    header('Location: ' . installer_base_url('installer/index.php'));
    exit;
}

// Ensure database was configured and schema imported
if (empty($_SESSION['installer_db']) || empty($_SESSION['installer_db_imported'])) {
    header('Location: ' . installer_base_url('installer/database.php'));
    exit;
}

$dbConfig = $_SESSION['installer_db'];
$connTest = installer_test_db_connection(
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['dbname'],
    $dbConfig['user'],
    $dbConfig['pass']
);

if (!$connTest['success'] || !$connTest['pdo']) {
    $_SESSION['installer_flash_error'] = 'Database connection lost. Please re-enter credentials.';
    header('Location: ' . installer_base_url('installer/database.php'));
    exit;
}

$pdo = $connTest['pdo'];
$error = null;

$fullName = $_POST['full_name'] ?? 'System Administrator';
$username = $_POST['username'] ?? 'admin';
$email = $_POST['email'] ?? 'admin@example.com';

// Handle Admin Account Creation Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    if (!installer_verify_csrf()) {
        $error = 'Security validation token expired. Please try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Input Validations
        if ($fullName === '' || $username === '' || $email === '' || $password === '') {
            $error = 'All required fields must be completed.';
        } elseif (strlen($fullName) < 2 || strlen($fullName) > 150) {
            $error = 'Full Name must be between 2 and 150 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\.]{3,30}$/', $username)) {
            $error = 'Username must be between 3 and 30 alphanumeric characters, dots, underscores, or hyphens.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } elseif (in_array(strtolower($password), ['12345678', 'password', 'admin123', 'root1234', 'qwertyuiop'])) {
            $error = 'The chosen password is too common. Please choose a stronger password.';
        } else {
            $adminResult = installer_create_admin($pdo, $fullName, $username, $email, $password);

            if ($adminResult['success']) {
                $_SESSION['installer_admin_created'] = true;
                $_SESSION['installer_admin_username'] = $username;
                $_SESSION['installer_admin_email'] = $email;
                header('Location: ' . installer_base_url('installer/finalize.php'));
                exit;
            } else {
                $error = $adminResult['message'];
            }
        }
    }
}

$currentStep = 4;
$pageTitle = 'Step 4: Administrator Account';

require_once __DIR__ . '/includes/installer-header.php';
?>

<div class="installer-card">
    <div class="installer-card-header">
        <h4 class="mb-1 fw-bold text-dark">Step 4: Administrator Account</h4>
        <p class="text-muted mb-0">Create the primary super-administrator account to manage your procurement system.</p>
    </div>

    <form method="POST" action="<?= installer_base_url('installer/admin.php') ?>" id="admin-form">
        <?= installer_csrf_field() ?>
        <input type="hidden" name="action" value="create_admin">

        <div class="installer-card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-3 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-danger flex-shrink-0"></i>
                    <div><?= installer_e($error) ?></div>
                </div>
            <?php endif; ?>

            <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
                <i class="bi bi-shield-lock-fill fs-3 text-primary flex-shrink-0"></i>
                <div class="small">
                    This account will be assigned the <strong>Administrator</strong> role with complete access to all modules, system configurations, and user management.
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-12">
                    <label for="full_name" class="form-label fw-semibold text-dark">
                        Full Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?= installer_e($fullName) ?>" required placeholder="e.g. System Administrator">
                </div>

                <div class="col-md-6">
                    <label for="username" class="form-label fw-semibold text-dark">
                        Username <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?= installer_e($username) ?>" required placeholder="e.g. admin">
                    <div class="form-text">Used for signing in to the CMS dashboard.</div>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold text-dark">
                        Email Address <span class="text-danger">*</span>
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= installer_e($email) ?>" required placeholder="e.g. admin@example.com">
                    <div class="form-text">Used for password recovery and system notifications.</div>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label fw-semibold text-dark">
                        Password <span class="text-danger">*</span>
                    </label>
                    <input type="password" class="form-control" id="password" name="password" 
                           required minlength="8" placeholder="Minimum 8 characters">
                </div>

                <div class="col-md-6">
                    <label for="confirm_password" class="form-label fw-semibold text-dark">
                        Confirm Password <span class="text-danger">*</span>
                    </label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           required minlength="8" placeholder="Repeat password">
                </div>
            </div>
        </div>

        <div class="installer-card-footer d-flex flex-column flex-sm-row justify-content-between gap-3">
            <a href="<?= installer_base_url('installer/import.php') ?>" class="btn btn-installer-secondary">
                <i class="bi bi-arrow-left"></i> Previous Step
            </a>
            <button type="submit" class="btn btn-installer-primary" id="btn-submit-admin">
                Save & Finalize Installation <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/includes/installer-footer.php';
