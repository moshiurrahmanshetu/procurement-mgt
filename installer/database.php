<?php
/**
 * Installation Wizard — Step 2: Database Configuration
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

// Ensure Step 1 Requirements Passed
$reqData = installer_get_all_requirements();
if (!$reqData['all_passed']) {
    header('Location: ' . installer_base_url('installer/index.php'));
    exit;
}

$error = null;
$success = null;

// Handle AJAX Connection Test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'test_connection') {
    header('Content-Type: application/json');

    if (!installer_verify_csrf()) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid or expired. Please refresh the page.']);
        exit;
    }

    $host = trim($_POST['db_host'] ?? '127.0.0.1');
    $port = (int)($_POST['db_port'] ?? 3306);
    $dbname = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';

    if ($host === '' || $dbname === '' || $user === '') {
        echo json_encode(['success' => false, 'message' => 'Please fill in Database Host, Database Name, and Username.']);
        exit;
    }

    $testResult = installer_test_db_connection($host, $port, $dbname, $user, $pass);
    echo json_encode($testResult);
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_database') {
    if (!installer_verify_csrf()) {
        $error = 'Security validation token expired. Please try again.';
    } else {
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = (int)($_POST['db_port'] ?? 3306);
        $dbname = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';

        if ($host === '' || $dbname === '' || $user === '') {
            $error = 'Database Host, Database Name, and Username are required fields.';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $dbname)) {
            $error = 'Database Name contains invalid characters. Use only letters, numbers, underscores, and dashes.';
        } else {
            $connTest = installer_test_db_connection($host, $port, $dbname, $user, $pass);
            if ($connTest['success']) {
                // Save database parameters in installer session
                $_SESSION['installer_db'] = [
                    'host'   => $host,
                    'port'   => $port,
                    'dbname' => $dbname,
                    'user'   => $user,
                    'pass'   => $pass
                ];
                header('Location: ' . installer_base_url('installer/import.php'));
                exit;
            } else {
                $error = $connTest['message'];
            }
        }
    }
}

// Load default or previously entered values
$savedDb = $_SESSION['installer_db'] ?? [];
$dbHost = $savedDb['host'] ?? '127.0.0.1';
$dbPort = $savedDb['port'] ?? 3306;
$dbName = $savedDb['dbname'] ?? 'procurement_mgt';
$dbUser = $savedDb['user'] ?? 'root';
$dbPass = $savedDb['pass'] ?? '';

$currentStep = 2;
$pageTitle = 'Step 2: Database Setup';

require_once __DIR__ . '/includes/installer-header.php';
?>

<div class="installer-card">
    <div class="installer-card-header">
        <h4 class="mb-1 fw-bold text-dark">Step 2: Database Configuration</h4>
        <p class="text-muted mb-0">Enter your MySQL or MariaDB database connection parameters.</p>
    </div>

    <form method="POST" action="<?= installer_base_url('installer/database.php') ?>" id="database-form">
        <?= installer_csrf_field() ?>
        <input type="hidden" name="action" value="save_database">

        <div class="installer-card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-3 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-danger flex-shrink-0"></i>
                    <div><?= installer_e($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Live AJAX Feedback Container -->
            <div id="connection-status-alert" class="d-none alert mb-4" role="alert">
                <div class="d-flex align-items-center gap-2">
                    <i id="connection-status-icon" class="bi fs-4"></i>
                    <span id="connection-status-text"></span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="db_host" class="form-label fw-semibold text-dark">
                        Database Host <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="db_host" name="db_host" 
                           value="<?= installer_e($dbHost) ?>" required placeholder="e.g. 127.0.0.1 or localhost">
                    <div class="form-text">Usually <code>127.0.0.1</code> or <code>localhost</code> for most hosting providers.</div>
                </div>

                <div class="col-md-4">
                    <label for="db_port" class="form-label fw-semibold text-dark">
                        Database Port <span class="text-danger">*</span>
                    </label>
                    <input type="number" class="form-control" id="db_port" name="db_port" 
                           value="<?= installer_e($dbPort) ?>" required min="1" max="65535" placeholder="3306">
                    <div class="form-text">Default MySQL port is <code>3306</code>.</div>
                </div>

                <div class="col-md-12">
                    <label for="db_name" class="form-label fw-semibold text-dark">
                        Database Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="db_name" name="db_name" 
                           value="<?= installer_e($dbName) ?>" required placeholder="e.g. procurement_mgt">
                    <div class="form-text">The database must already exist in your MySQL server / cPanel.</div>
                </div>

                <div class="col-md-6">
                    <label for="db_user" class="form-label fw-semibold text-dark">
                        Database Username <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="db_user" name="db_user" 
                           value="<?= installer_e($dbUser) ?>" required placeholder="e.g. root">
                </div>

                <div class="col-md-6">
                    <label for="db_pass" class="form-label fw-semibold text-dark">
                        Database Password
                    </label>
                    <input type="password" class="form-control" id="db_pass" name="db_pass" 
                           value="<?= installer_e($dbPass) ?>" placeholder="Enter password (leave empty if none)">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="button" class="btn btn-installer-secondary" id="btn-test-db-connection">
                    <i class="bi bi-plug"></i> Test Connection
                </button>
            </div>
        </div>

        <div class="installer-card-footer d-flex flex-column flex-sm-row justify-content-between gap-3">
            <a href="<?= installer_base_url('installer/index.php') ?>" class="btn btn-installer-secondary">
                <i class="bi bi-arrow-left"></i> Previous Step
            </a>
            <button type="submit" class="btn btn-installer-primary" id="btn-submit-db">
                Continue to Database Import <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/includes/installer-footer.php';
