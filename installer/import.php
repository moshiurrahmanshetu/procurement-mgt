<?php
/**
 * Installation Wizard — Step 3: SQL Schema Selection & Import
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

// Ensure database parameters exist in session
if (empty($_SESSION['installer_db'])) {
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
$existingTables = installer_detect_existing_tables($pdo);
$bundledSqlPath = installer_root_path() . 'database' . DIRECTORY_SEPARATOR . 'install.sql';
$bundledSqlExists = file_exists($bundledSqlPath);

$error = null;
$importProgress = null;

// Handle SQL Import Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_sql') {
    if (!installer_verify_csrf()) {
        $error = 'Security validation token expired. Please try again.';
    } else {
        $sourceType = $_POST['sql_source'] ?? 'bundled';
        $sqlContent = '';

        if ($sourceType === 'upload') {
            // Process uploaded SQL file
            if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a valid SQL file to upload.';
            } else {
                $file = $_FILES['sql_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if ($ext !== 'sql') {
                    $error = 'Only .sql files are allowed for schema import.';
                } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $error = 'The uploaded SQL file exceeds the 5MB size limit.';
                } else {
                    $content = @file_get_contents($file['tmp_name']);
                    @unlink($file['tmp_name']); // Immediately remove temp file

                    if ($content === false || trim($content) === '') {
                        $error = 'The uploaded SQL file is empty or could not be read.';
                    } elseif (stripos($content, '<?php') !== false || stripos($content, '<?=') !== false) {
                        $error = 'Security check failed: SQL file contains invalid code tags.';
                    } else {
                        $sqlContent = $content;
                    }
                }
            }
        } else {
            // Use bundled install.sql
            if (!$bundledSqlExists) {
                $error = 'Bundled master SQL file (database/install.sql) was not found on this server.';
            } else {
                $sqlContent = @file_get_contents($bundledSqlPath);
                if ($sqlContent === false) {
                    $error = 'Could not read bundled master installation SQL file.';
                }
            }
        }

        if (!$error && $sqlContent !== '') {
            $importResult = installer_execute_sql($pdo, $sqlContent);

            if ($importResult['success']) {
                $_SESSION['installer_db_imported'] = true;
                header('Location: ' . installer_base_url('installer/admin.php'));
                exit;
            } else {
                $error = $importResult['error'] ?? 'Database import failed. Please check the schema file.';
            }
        }
    }
}

$currentStep = 3;
$pageTitle = 'Step 3: Database Import';

require_once __DIR__ . '/includes/installer-header.php';
?>

<div class="installer-card">
    <div class="installer-card-header">
        <h4 class="mb-1 fw-bold text-dark">Step 3: Database Schema Import</h4>
        <p class="text-muted mb-0">Select and install the database tables and lookup seed data.</p>
    </div>

    <form method="POST" action="<?= installer_base_url('installer/import.php') ?>" enctype="multipart/form-data" id="import-form">
        <?= installer_csrf_field() ?>
        <input type="hidden" name="action" value="import_sql">

        <div class="installer-card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-3 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-danger flex-shrink-0"></i>
                    <div><?= installer_e($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($existingTables)): ?>
                <div class="alert alert-warning d-flex align-items-start gap-3 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-warning flex-shrink-0 mt-1"></i>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">Existing Application Tables Detected</h6>
                        <p class="small mb-2">
                            The target database <code><?= installer_e($dbConfig['dbname']) ?></code> already contains <strong><?= count($existingTables) ?></strong> table(s) from the Procurement Management CMS (e.g. <em><?= implode(', ', array_slice($existingTables, 0, 4)) ?></em>).
                        </p>
                        <p class="small mb-0 text-muted">
                            Proceeding will recreate these tables with a fresh schema. If you wish to preserve existing data, please cancel and configure a new empty database.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <label class="form-label fw-semibold text-dark mb-3">Choose SQL Schema Source:</label>

                <!-- Option 1: Bundled SQL -->
                <div class="card p-3 mb-3 border <?= $bundledSqlExists ? 'border-primary bg-light' : 'opacity-75' ?>">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sql_source" id="source_bundled" value="bundled" <?= $bundledSqlExists ? 'checked' : 'disabled' ?>>
                        <label class="form-check-label fw-semibold" for="source_bundled">
                            Use Bundled Installation Database (Recommended)
                        </label>
                        <div class="small text-muted mt-1">
                            <?php if ($bundledSqlExists): ?>
                                <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Bundled schema found:</span>
                                <code>database/install.sql</code> (<?= installer_format_bytes(filesize($bundledSqlPath)) ?>) — contains all 21 modular tables and static seed data.
                            <?php else: ?>
                                <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Bundled install.sql file not found in database directory.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Option 2: Upload Custom SQL -->
                <div class="card p-3 border">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sql_source" id="source_upload" value="upload" <?= !$bundledSqlExists ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="source_upload">
                            Upload Custom .SQL File
                        </label>
                        <div class="small text-muted mt-1 mb-2">
                            Upload a custom or backup SQL schema file (Max 5MB).
                        </div>
                        <div id="upload-container" class="mt-2 <?= $bundledSqlExists ? 'd-none' : '' ?>">
                            <input class="form-control" type="file" id="sql_file" name="sql_file" accept=".sql">
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-3 bg-light rounded border text-muted small">
                <i class="bi bi-info-circle-fill text-primary me-1"></i>
                The database schema includes: Roles, Users, User Roles, Activity Logs, Departments, Requisitions, Suppliers, Quotations, Purchase Orders, Goods Receipts, and System Settings.
            </div>
        </div>

        <div class="installer-card-footer d-flex flex-column flex-sm-row justify-content-between gap-3">
            <a href="<?= installer_base_url('installer/database.php') ?>" class="btn btn-installer-secondary">
                <i class="bi bi-arrow-left"></i> Previous Step
            </a>
            <button type="submit" class="btn btn-installer-primary" id="btn-submit-import">
                Import & Continue <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/includes/installer-footer.php';
