<?php
require_once dirname(__DIR__) . '/includes/init.php';
$db = getDb();
$sql = file_get_contents(dirname(__DIR__) . '/database/04_purchase_orders_schema.sql');
$db->exec($sql);
echo "04_purchase_orders_schema.sql imported successfully.\n";
