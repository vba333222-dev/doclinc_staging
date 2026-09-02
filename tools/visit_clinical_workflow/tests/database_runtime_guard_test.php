<?php
require_once __DIR__ . '/assert.php';
putenv('DOCLINC_TEST_RUNTIME=1');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=33317');
putenv('DB_NAME=doclinc_visit_test');
putenv('DB_USER=doclinc_test');
putenv('DB_PASS=doclinc_test_only');
define('BASEPATH', __DIR__);
$db = array();
require dirname(__DIR__, 3) . '/application/config/database.php';
vcw_assert_true(isset($db['default']['port']) && (string) $db['default']['port'] === '33317', 'test runtime selects disposable DB port');
echo "DATABASE_RUNTIME_GUARD=PASS\n";
