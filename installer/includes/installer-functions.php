<?php
/**
 * Installer Core Helper Functions
 * Procurement Management CMS — Final Complete Installer
 *
 * Provides isolated, collision-free helper routines for the installation wizard.
 */

/**
 * Returns the absolute root filesystem path of the application with a trailing directory separator.
 *
 * @return string
 */
function installer_root_path(): string
{
    return dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
}

/**
 * Returns the absolute filesystem path of the installer directory with a trailing directory separator.
 *
 * @return string
 */
function installer_path(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR;
}

/**
 * Safely escapes output for HTML rendering.
 *
 * @param mixed $value
 * @return string
 */
function installer_e($value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Dynamically detects the application base URL without hardcoded hostnames or paths.
 *
 * @param string $path Optional relative path to append
 * @return string
 */
function installer_base_url(string $path = ''): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $_SERVER['DOCUMENT_ROOT']) : '';
    $appRoot = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, dirname(dirname(__DIR__)));
    $relativeDir = '';

    if (!empty($docRoot) && strpos($appRoot, $docRoot) === 0) {
        $relativeDir = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    }

    $relativeDir = trim($relativeDir, '/');
    $baseUrl = $protocol . $host . ($relativeDir !== '' ? '/' . $relativeDir . '/' : '/');

    $path = ltrim($path, '/');
    return $baseUrl . $path;
}

/**
 * Returns the URL for an installer or application asset.
 *
 * @param string $path
 * @param bool $isInstallerAsset
 * @return string
 */
function installer_asset_url(string $path, bool $isInstallerAsset = false): string
{
    $path = ltrim($path, '/');
    if ($isInstallerAsset) {
        return installer_base_url('installer/assets/' . $path);
    }
    return installer_base_url('assets/' . $path);
}

/**
 * Returns the absolute path to the installation lock file.
 *
 * @return string
 */
function installer_lock_file_path(): string
{
    return installer_root_path() . 'config' . DIRECTORY_SEPARATOR . 'installed.lock';
}

/**
 * Checks whether the application is already installed and locked.
 *
 * @return bool
 */
function isInstallationLocked(): bool
{
    $lockFile = installer_lock_file_path();
    if (file_exists($lockFile)) {
        return true;
    }

    // Secondary fallback lock check inside installer directory
    $secondaryLock = installer_path() . 'installed.lock';
    return file_exists($secondaryLock);
}

/**
 * Alias for isInstallationLocked() with standard installer prefix.
 *
 * @return bool
 */
function installer_is_locked(): bool
{
    return isInstallationLocked();
}

/**
 * Starts an isolated installer session if not already active.
 */
function installer_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        if (!headers_sent()) {
            session_name('PROCURE_INSTALLER_SESSID');

            session_set_cookie_params([
                'lifetime' => 7200,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        @session_start();
    }
}

/**
 * Generates or retrieves the isolated installer CSRF token.
 *
 * @return string
 */
function installer_csrf_token(): string
{
    installer_start_session();
    if (empty($_SESSION['installer_csrf_token'])) {
        $_SESSION['installer_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['installer_csrf_token'];
}

/**
 * Generates an HTML hidden input for installer CSRF protection.
 *
 * @return string
 */
function installer_csrf_field(): string
{
    return '<input type="hidden" name="installer_csrf" value="' . installer_e(installer_csrf_token()) . '">';
}

/**
 * Validates the submitted installer CSRF token.
 *
 * @param string|null $token
 * @return bool
 */
function installer_verify_csrf(?string $token = null): bool
{
    installer_start_session();
    if ($token === null) {
        $token = $_POST['installer_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    if (empty($token) || empty($_SESSION['installer_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['installer_csrf_token'], $token);
}

/**
 * Tests database connectivity using PDO.
 *
 * @param string $host
 * @param int $port
 * @param string $dbname
 * @param string $user
 * @param string $pass
 * @param string $charset
 * @return array ['success' => bool, 'message' => string, 'pdo' => ?PDO, 'error_code' => ?int]
 */
function installer_test_db_connection(
    string $host,
    int $port,
    string $dbname,
    string $user,
    string $pass,
    string $charset = 'utf8mb4'
): array {
    if (!extension_loaded('pdo_mysql')) {
        return [
            'success'    => false,
            'message'    => 'The PDO MySQL extension (pdo_mysql) is not loaded on this server.',
            'pdo'        => null,
            'error_code' => null
        ];
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbname, $charset);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
        PDO::ATTR_EMULATE_PREPARES   => false
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return [
            'success'    => true,
            'message'    => 'Connection successful! The database is online and accessible.',
            'pdo'        => $pdo,
            'error_code' => null
        ];
    } catch (PDOException $e) {
        $code = (int)$e->getCode();
        $msg = $e->getMessage();

        // Categorize errors safely without leaking sensitive server strings
        if (strpos($msg, 'Access denied') !== false || $code === 1045) {
            $userMsg = 'Database Access Denied. Please verify the Database Username and Password.';
        } elseif (strpos($msg, 'Unknown database') !== false || $code === 1049) {
            $userMsg = 'Database "' . installer_e($dbname) . '" does not exist. Please create the database in your hosting control panel first.';
        } elseif (strpos($msg, 'Connection refused') !== false || strpos($msg, '2002') !== false) {
            $userMsg = 'Could not connect to MySQL server at ' . installer_e($host) . ':' . $port . '. Check hostname and port.';
        } else {
            $userMsg = 'Database connection failed: ' . installer_e(strip_tags($msg));
        }

        return [
            'success'    => false,
            'message'    => $userMsg,
            'pdo'        => null,
            'error_code' => $code
        ];
    }
}

/**
 * Checks if target database contains existing tables from the CMS.
 *
 * @param PDO $pdo
 * @return array List of existing table names matching CMS schema
 */
function installer_detect_existing_tables(PDO $pdo): array
{
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $cmsTables = [
        'users', 'roles', 'user_roles', 'purchase_requests', 'suppliers',
        'quotations', 'purchase_orders', 'goods_receipts', 'settings'
    ];

    return array_values(array_intersect($allTables, $cmsTables));
}

/**
 * Parses and executes SQL script containing multiple statements safely.
 *
 * @param PDO $pdo
 * @param string $sqlContent
 * @return array ['success' => bool, 'executed_count' => int, 'error' => ?string]
 */
function installer_execute_sql(PDO $pdo, string $sqlContent): array
{
    // Temporarily disable foreign key checks during batch schema creation
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    } catch (Exception $e) {
        // Continue if unsupported
    }

    // Split SQL content into separate statements respecting string quotes
    $statements = [];
    $current = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $length = strlen($sqlContent);

    for ($i = 0; $i < $length; $i++) {
        $char = $sqlContent[$i];
        $prevChar = ($i > 0) ? $sqlContent[$i - 1] : '';

        // Handle single quote escaping
        if ($char === "'" && $prevChar !== '\\' && !$inDoubleQuote) {
            $inSingleQuote = !$inSingleQuote;
        }
        // Handle double quote escaping
        elseif ($char === '"' && $prevChar !== '\\' && !$inSingleQuote) {
            $inDoubleQuote = !$inDoubleQuote;
        }

        // Statement separator
        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
        } else {
            $current .= $char;
        }
    }

    $finalStmt = trim($current);
    if ($finalStmt !== '') {
        $statements[] = $finalStmt;
    }

    $executedCount = 0;

    foreach ($statements as $index => $statement) {
        // Strip SQL line comments
        $lines = explode("\n", $statement);
        $cleanedLines = [];
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (strpos($trimmedLine, '--') === 0 || strpos($trimmedLine, '#') === 0) {
                continue;
            }
            $cleanedLines[] = $line;
        }
        $cleanedStatement = trim(implode("\n", $cleanedLines));

        if ($cleanedStatement === '') {
            continue;
        }

        try {
            $pdo->exec($cleanedStatement);
            $executedCount++;
        } catch (PDOException $e) {
            // Restore foreign key checks before exiting
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            } catch (Exception $ign) {}

            return [
                'success'        => false,
                'executed_count' => $executedCount,
                'error'          => 'SQL Execution Error at statement #' . ($index + 1) . ': ' . $e->getMessage()
            ];
        }
    }

    // Restore foreign key checks
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    } catch (Exception $e) {}

    return [
        'success'        => true,
        'executed_count' => $executedCount,
        'error'          => null
    ];
}

/**
 * Creates the initial administrator user account and assigns Administrator role.
 *
 * @param PDO $pdo
 * @param string $fullName
 * @param string $username
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'user_id' => ?int]
 */
function installer_create_admin(
    PDO $pdo,
    string $fullName,
    string $username,
    string $email,
    string $password
): array {
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->execute([':username' => $username, ':email' => $email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if (strtolower($existing['username']) === strtolower($username)) {
            return ['success' => false, 'message' => 'Username "' . installer_e($username) . '" is already taken.', 'user_id' => null];
        }
        return ['success' => false, 'message' => 'Email "' . installer_e($email) . '" is already registered.', 'user_id' => null];
    }

    // Verify Administrator role exists
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'administrator' LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch();

    if (!$role) {
        // Create role if missing
        $pdo->exec("INSERT INTO roles (id, name, slug, description, status) VALUES (1, 'Administrator', 'administrator', 'Full system access', 'active')");
        $adminRoleId = 1;
    } else {
        $adminRoleId = (int)$role['id'];
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    try {
        $pdo->beginTransaction();

        $userStmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, status, created_at, updated_at) VALUES (:full_name, :username, :email, :password, 'active', NOW(), NOW())");
        $userStmt->execute([
            ':full_name' => $fullName,
            ':username'  => $username,
            ':email'     => $email,
            ':password'  => $hashedPassword
        ]);

        $userId = (int)$pdo->lastInsertId();

        $roleAssignStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
        $roleAssignStmt->execute([
            ':user_id' => $userId,
            ':role_id' => $adminRoleId
        ]);

        // Insert initial activity log
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Web-Installer';
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) VALUES (:user_id, 'System Installed', 'Procurement Management CMS successfully installed and primary administrator account initialized.', :ip, :agent, NOW())");
        $logStmt->execute([
            ':user_id' => $userId,
            ':ip'      => $ip,
            ':agent'   => substr($agent, 0, 255)
        ]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Administrator account successfully created.',
            'user_id' => $userId
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'message' => 'Failed to create administrator account: ' . $e->getMessage(),
            'user_id' => null
        ];
    }
}

/**
 * Safely writes database configuration to config/config.php with rigorous PHP escaping.
 *
 * @param string $host
 * @param int $port
 * @param string $dbname
 * @param string $user
 * @param string $pass
 * @param string $charset
 * @param string $timezone
 * @param bool $devMode
 * @return array ['success' => bool, 'message' => string]
 */
function installer_write_config(
    string $host,
    int $port,
    string $dbname,
    string $user,
    string $pass,
    string $charset = 'utf8mb4',
    string $timezone = 'Asia/Dhaka',
    bool $devMode = false
): array {
    $configFile = installer_root_path() . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    $configDir = dirname($configFile);

    if (!is_writable($configDir) && !is_writable($configFile)) {
        return [
            'success' => false,
            'message' => 'The configuration directory (config/) is not writable. Please adjust directory permissions.'
        ];
    }

    $template = "<?php\n"
        . "/**\n"
        . " * System Configuration\n"
        . " * Procurement Management CMS — Production Configuration\n"
        . " * Generated on: " . date('Y-m-d H:i:s') . "\n"
        . " */\n\n"
        . "// Timezone Setting\n"
        . "date_default_timezone_set(" . var_export($timezone, true) . ");\n\n"
        . "// Database Credentials\n"
        . "if (!defined('DB_HOST')) {\n"
        . "    define('DB_HOST', " . var_export($host, true) . ");\n"
        . "}\n\n"
        . "if (!defined('DB_PORT')) {\n"
        . "    define('DB_PORT', " . (int)$port . ");\n"
        . "}\n\n"
        . "if (!defined('DB_NAME')) {\n"
        . "    define('DB_NAME', " . var_export($dbname, true) . ");\n"
        . "}\n\n"
        . "if (!defined('DB_USER')) {\n"
        . "    define('DB_USER', " . var_export($user, true) . ");\n"
        . "}\n\n"
        . "if (!defined('DB_PASS')) {\n"
        . "    define('DB_PASS', " . var_export($pass, true) . ");\n"
        . "}\n\n"
        . "if (!defined('DB_CHARSET')) {\n"
        . "    define('DB_CHARSET', " . var_export($charset, true) . ");\n"
        . "}\n\n"
        . "// Development Mode\n"
        . "if (!defined('DEV_MODE')) {\n"
        . "    define('DEV_MODE', " . ($devMode ? 'true' : 'false') . ");\n"
        . "}\n";

    $written = @file_put_contents($configFile, $template);

    if ($written === false) {
        return [
            'success' => false,
            'message' => 'Failed to write configuration file to config/config.php.'
        ];
    }

    return [
        'success' => true,
        'message' => 'Configuration file saved successfully.'
    ];
}

/**
 * Creates the permanent installation lock file.
 *
 * @param array $metadata
 * @return bool
 */
function installer_create_lock_file(array $metadata = []): bool
{
    $lockFile = installer_lock_file_path();
    $lockDir = dirname($lockFile);

    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0755, true);
    }

    $lockData = array_merge([
        'installed_at'    => date('c'),
        'version'         => '1.0.0',
        'installation_id' => bin2hex(random_bytes(32))
    ], $metadata);

    // Write lock as JSON
    $json = json_encode($lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return (@file_put_contents($lockFile, $json) !== false);
}

/**
 * Clears installer sensitive session variables upon completion.
 */
function installer_clear_session(): void
{
    installer_start_session();
    unset(
        $_SESSION['installer_db'],
        $_SESSION['installer_db_pass'],
        $_SESSION['installer_db_imported'],
        $_SESSION['installer_admin_created']
    );
}

/**
 * Formats byte values into human-readable strings.
 *
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function installer_format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}
