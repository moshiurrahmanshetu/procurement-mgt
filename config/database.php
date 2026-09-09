<?php
/**
 * Database Connection Handler
 * Procurement Management CMS
 */

require_once __DIR__ . '/config.php';

/**
 * Returns the singleton PDO database connection instance.
 *
 * @return PDO
 * @throws PDOException
 */
function getDb(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            if (defined('DEV_MODE') && DEV_MODE) {
                die('<div style="font-family:sans-serif;padding:20px;margin:20px;background:#fee2e2;border:1px solid #ef4444;border-radius:8px;color:#991b1b;">
                    <h3 style="margin-top:0;">Database Connection Failed</h3>
                    <p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>
                    <p><small>Check your database settings in <code>config/config.php</code> and ensure MySQL is running.</small></p>
                </div>');
            } else {
                die('A database error occurred. Please contact the system administrator.');
            }
        }
    }

    return $pdo;
}
