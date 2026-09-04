<?php

function task7_lock_observer_edges()
{
    if (getenv('DOCLINC_TEST_RUNTIME') !== '1') {
        throw new RuntimeException('lock_observer_test_runtime_required');
    }
    $host = getenv('VCW_LOCK_OBSERVER_HOST');
    $port = (int) getenv('VCW_LOCK_OBSERVER_PORT');
    $database = getenv('VCW_LOCK_OBSERVER_NAME');
    $user = getenv('VCW_LOCK_OBSERVER_USER');
    $password = getenv('VCW_LOCK_OBSERVER_PASSWORD');
    if ($host === false || $port < 1 || $database === false || $user === false || $password === false
        || !in_array($host, array('127.0.0.1', 'task11-db', 'db'), true)
        || !in_array($port, array(3306, 33317), true) || $database !== 'doclinc_visit_test'
        || trim($user) === '' || $password === '') {
        throw new RuntimeException('lock_observer_refuses_non_disposable_target');
    }
    $workerHost = getenv('VCW_DB_HOST');
    $workerPort = (int) getenv('VCW_DB_PORT');
    $workerName = getenv('VCW_DB_NAME');
    if ($workerHost !== false && $workerHost !== '' && $workerHost !== $host) {
        throw new RuntimeException('lock_observer_target_mismatch');
    }
    if ($workerPort > 0 && $workerPort !== $port) {
        throw new RuntimeException('lock_observer_target_mismatch');
    }
    if ($workerName !== false && $workerName !== '' && $workerName !== $database) {
        throw new RuntimeException('lock_observer_target_mismatch');
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli($host, $user, $password, $database, $port);
    if ($db->connect_errno) { throw new RuntimeException('lock_observer_connection_failed'); }
    if (!$db->set_charset('utf8mb4')) { throw new RuntimeException('lock_observer_charset_failed'); }
    $sql = 'SELECT r.trx_mysql_thread_id,b.trx_mysql_thread_id FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id';
    $result = @$db->query($sql);
    if (!$result) { throw new RuntimeException('lock_observer_query_failed'); }
    $edges = array();
    while ($row = $result->fetch_row()) { $edges[] = array((int) $row[0], (int) $row[1]); }
    $result->free();
    $db->close();
    return $edges;
}

function task7_lock_observer_wait_graph(array $expected, $message)
{
    $deadline = microtime(true) + 15.0;
    while (microtime(true) < $deadline) {
        $actual = task7_lock_observer_edges();
        $missing = array_filter($expected, function ($edge) use ($actual) { return !in_array($edge, $actual, true); });
        if (!$missing) { return; }
        usleep(100000);
    }
    throw new RuntimeException($message);
}
