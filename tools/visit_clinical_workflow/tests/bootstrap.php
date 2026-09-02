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
$baselineSchema = 'C:\\Project\\doclinc-local-mirror\\schema\\alibaba-staging-schema-20260822-034559.sql';

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

function vcw_load_baseline_schema()
{
    global $baselineSchema;
    vcw_assert_true(is_string($baselineSchema) && is_file($baselineSchema), 'Application-compatible baseline schema is missing');

    $sql = file_get_contents($baselineSchema);
    vcw_assert_true($sql !== false && trim($sql) !== '', 'Application-compatible baseline schema is empty');
    // The verified dump is schema-only. Remove dump reset statements because this
    // harness always starts from a fresh disposable database.
    $sql = preg_replace('/^\s*DROP TABLE IF EXISTS .*?;\s*$/mi', '', $sql);
    vcw_assert_true($sql !== null, 'Baseline schema preprocessing failed');
    vcw_assert_true(!preg_match('/(?:^|;)\s*(?:INSERT|UPDATE|DELETE|TRUNCATE|DROP\s+(?:DATABASE|TABLE))\b/i', $sql), 'Baseline schema must contain definitions only');

    $db = vcw_db();
    vcw_assert_true($db->multi_query($sql), 'Application-compatible baseline import failed');
    do {
        $result = $db->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } while ($db->more_results() && $db->next_result());
    vcw_assert_true($db->errno === 0, 'Application-compatible baseline import returned a database error');
}

function vcw_table_columns($table)
{
    vcw_assert_true((bool) preg_match('/^[a-z][a-z0-9_]*$/', $table), 'Unsafe table name');
    $statement = vcw_db()->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, DATETIME_PRECISION FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
    $statement->bind_param('s', $table);
    $statement->execute();
    $result = $statement->get_result();
    $columns = array();
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    $statement->close();
    return $columns;
}

function vcw_table_indexes($table)
{
    vcw_assert_true((bool) preg_match('/^[a-z][a-z0-9_]*$/', $table), 'Unsafe table name');
    $statement = vcw_db()->prepare('SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, NULLABLE FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX');
    $statement->bind_param('s', $table);
    $statement->execute();
    $result = $statement->get_result();
    $indexes = array();
    while ($row = $result->fetch_assoc()) {
        $indexes[] = $row;
    }
    $statement->close();
    return $indexes;
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
