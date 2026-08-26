<?php

require_once __DIR__ . '/assert.php';

$host = getenv('VCW_DB_HOST') ?: '127.0.0.1';
if (!in_array($host, array('127.0.0.1', 'localhost'), true)) {
    throw new RuntimeException('Visit workflow tests refuse non-loopback DB hosts');
}

$port = (int) (getenv('VCW_DB_PORT') ?: 33317);
$database = getenv('VCW_DB_NAME') ?: 'doclinc_visit_test';
$username = getenv('VCW_DB_USER') ?: 'doclinc_test';
$password = getenv('VCW_DB_PASSWORD') ?: 'doclinc_test_only';

function vcw_db()
{
    static $db;
    global $host, $port, $database, $username, $password;

    if ($db instanceof mysqli) {
        return $db;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($host, $username, $password, $database, $port);
    $db->set_charset('utf8mb4');
    return $db;
}

function vcw_count_table($table)
{
    vcw_assert_true((bool) preg_match('/^[a-z][a-z0-9_]*$/', $table), 'Unsafe table name');
    $db = vcw_db();
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $statement->bind_param('s', $table);
    $statement->execute();
    $statement->bind_result($count);
    $statement->fetch();
    $statement->close();
    return (int) $count;
}

function vcw_query($sql)
{
    vcw_assert_true(is_string($sql) && trim($sql) !== '', 'Empty SQL is not allowed');
    return vcw_db()->query($sql);
}

function vcw_run_migration($className)
{
    throw new RuntimeException('Task 1 does not execute Visit workflow migrations: ' . (string) $className);
}

function vcw_assert_fixture_request_id($requestId)
{
    vcw_assert_safe_request_id($requestId);
    return (int) $requestId;
}
