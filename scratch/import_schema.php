<?php
require_once dirname(__DIR__) . '/includes/init.php';
$db = getDb();
$sql = file_get_contents(dirname(__DIR__) . '/database/06_reports_settings_schema.sql');
$db->exec($sql);
echo "06_reports_settings_schema.sql imported successfully.\n";
