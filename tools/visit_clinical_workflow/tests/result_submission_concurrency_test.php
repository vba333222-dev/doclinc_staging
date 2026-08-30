<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'models/Visit_result_m.php';
require_once APPPATH . 'libraries/Visit_result_service.php';
require_once __DIR__ . '/result_submission_support.php';

vcw_assert_true(method_exists('Visit_result_service', 'submit'), 'Task 7C submit API required for concurrency');

function r7cc_start($script, array $arguments, &$pipes)
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $script);
    foreach ($arguments as $argument) { $command .= ' ' . escapeshellarg((string) $argument); }
    $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    vcw_assert_true(is_resource($process), 'independent submission worker starts');
    return $process;
}

function r7cc_wait_file($path, $message)
{
    $deadline = microtime(true) + 15.0;
    while (!is_file($path) && microtime(true) < $deadline) { usleep(100000); }
    vcw_assert_true(is_file($path), $message);
    $payload = json_decode((string) file_get_contents($path), true);
    vcw_assert_true(is_array($payload) && !empty($payload['connection_id']), $message . ' payload');
    return $payload;
}

function r7cc_collect($process, array $pipes, $message)
{
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) { fclose($pipe); }
    $exit = proc_close($process);
    vcw_assert_same(0, $exit, $message . ' exit stderr=' . trim($error));
    $payload = json_decode(trim((string) $output), true);
    vcw_assert_true(is_array($payload), $message . ' JSON output=' . trim($output));
    return $payload;
}

function r7cc_root_password()
{
    $inspect = json_decode((string) shell_exec('docker inspect visit_clinical_workflow-db-1'), true);
    foreach (($inspect[0]['Config']['Env'] ?? array()) as $entry) {
        if (strpos($entry, 'MARIADB_ROOT_PASSWORD=') === 0) { return substr($entry, strlen('MARIADB_ROOT_PASSWORD=')); }
    }
    return '';
}

function r7cc_edges($password)
{
    $sql = 'SELECT r.trx_mysql_thread_id,b.trx_mysql_thread_id FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id';
    $command = 'docker exec visit_clinical_workflow-db-1 mariadb -N -B -uroot -p' . escapeshellarg($password) . ' doclinc_visit_test -e ' . escapeshellarg($sql);
    $edges = array();
    foreach (preg_split('/\r?\n/', trim((string) shell_exec($command))) as $line) {
        if (preg_match('/^(\d+)\s+(\d+)$/', trim($line), $match)) { $edges[] = array((int) $match[1], (int) $match[2]); }
    }
    return $edges;
}

function r7cc_wait_graph($password, array $expected, $message)
{
    $deadline = microtime(true) + 15.0;
    while (microtime(true) < $deadline) {
        $actual = r7cc_edges($password);
        $missing = array_filter($expected, function ($edge) use ($actual) { return !in_array($edge, $actual, true); });
        if (!$missing) { return; }
        usleep(100000);
    }
    vcw_assert_true(false, $message);
}

function r7cc_gate($dispositionId, $prefix)
{
    $ready = $prefix . '.gate-ready';
    $release = $prefix . '.gate-release';
    $pipes = array();
    $process = r7cc_start('row_lock_gate_worker.php', array('disposition', $dispositionId, $ready, $release), $pipes);
    $info = r7cc_wait_file($ready, 'submission disposition gate ready');
    return array('process' => $process, 'pipes' => $pipes, 'release' => $release, 'pid' => (int) $info['pid'], 'connection' => (int) $info['connection_id']);
}

function r7cc_run_pair($db, $password, $temp, array $fixture, $resultId, $measurementId, $keyA, $keyB, $label)
{
    $gate = r7cc_gate($fixture['disposition'], $temp . DIRECTORY_SEPARATOR . $label);
    $aPipes = array();
    $a = r7cc_start('result_submission_worker.php', array($resultId, $fixture['performer'], $measurementId, $keyA, $temp . DIRECTORY_SEPARATOR . $label . '-a.json'), $aPipes);
    $aInfo = r7cc_wait_file($temp . DIRECTORY_SEPARATOR . $label . '-a.json', $label . ' worker A announced');
    r7cc_wait_graph($password, array(array((int) $aInfo['connection_id'], $gate['connection'])), $label . ' worker A waits on gate');
    $bPipes = array();
    $b = r7cc_start('result_submission_worker.php', array($resultId, $fixture['performer'], $measurementId, $keyB, $temp . DIRECTORY_SEPARATOR . $label . '-b.json'), $bPipes);
    $bInfo = r7cc_wait_file($temp . DIRECTORY_SEPARATOR . $label . '-b.json', $label . ' worker B announced');
    r7cc_wait_graph($password, array(array((int) $aInfo['connection_id'], $gate['connection']), array((int) $bInfo['connection_id'], (int) $aInfo['connection_id'])), $label . ' B to A to gate wait graph');
    file_put_contents($gate['release'], 'release', LOCK_EX);
    $aResult = r7cc_collect($a, $aPipes, $label . ' worker A');
    $bResult = r7cc_collect($b, $bPipes, $label . ' worker B');
    r7cc_collect($gate['process'], $gate['pipes'], $label . ' gate');
    return array('gate' => $gate, 'a_info' => $aInfo, 'b_info' => $bInfo, 'a' => $aResult, 'b' => $bResult);
}

$password = r7cc_root_password();
vcw_assert_true($password !== '', 'disposable metadata observer credential available');
$maximum = $db->query('SELECT GREATEST(COALESCE((SELECT MAX(userId) FROM users),0),COALESCE((SELECT MAX(staff_id) FROM puskesmas_staff),0),COALESCE((SELECT MAX(request_id) FROM requests),0)) AS base_id')->row();
$base = (int) $maximum->base_id + 7000;
$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vcw-task7c-' . $base;
vcw_assert_true(mkdir($temp), 'Task 7C concurrency temp directory');
$service = new Visit_result_service($db);

$same = r7c_fixture($db, $base, 'SAMEKEY');
$sameResult = r7c_draft($db, $service, $same, 'same-key');
$sameMeasurement = r7c_measurement($db, $same);
$sameKey = 'concurrent-same-' . $sameResult;
$sameRace = r7cc_run_pair($db, $password, $temp, $same, $sameResult, $sameMeasurement, $sameKey, $sameKey, 'same');
vcw_assert_true(!empty($sameRace['a']['ok']) && !empty($sameRace['b']['ok']), 'same-key concurrent calls both resolve success');
vcw_assert_same(1, (int) $db->where('visit_result_id', $sameResult)->where('status', 'submitted')->count_all_results('visit_results'), 'same-key one submitted mutation');
vcw_assert_same(array($sameMeasurement), r7c_link_ids($db, $sameResult), 'same-key one link set');
vcw_assert_same(1, r7c_event_count($db, $same['request']), 'same-key one event');

$different = r7c_fixture($db, $base + 100, 'DIFFKEY');
$differentResult = r7c_draft($db, $service, $different, 'different-key');
$differentMeasurement = r7c_measurement($db, $different);
$differentRace = r7cc_run_pair($db, $password, $temp, $different, $differentResult, $differentMeasurement, 'concurrent-a-' . $differentResult, 'concurrent-b-' . $differentResult, 'different');
$differentCalls = array($differentRace['a'], $differentRace['b']);
$winners = array_values(array_filter($differentCalls, function ($item) { return !empty($item['ok']); }));
$losers = array_values(array_filter($differentCalls, function ($item) { return ($item['code'] ?? null) === 'RESULT_ALREADY_SUBMITTED'; }));
vcw_assert_same(1, count($winners), 'different-key one winner');
vcw_assert_same(1, count($losers), 'different-key loser safely denied');
$differentRow = $db->where('visit_result_id', $differentResult)->get('visit_results')->row();
$winnerKey = !empty($differentRace['a']['ok']) ? 'concurrent-a-' . $differentResult : 'concurrent-b-' . $differentResult;
vcw_assert_same($winnerKey, (string) $differentRow->submission_key, 'different-key winning key preserved');
vcw_assert_same(array($differentMeasurement), r7c_link_ids($db, $differentResult), 'different-key no partial links');
vcw_assert_same(1, r7c_event_count($db, $different['request']), 'different-key one event');
vcw_assert_true((int) $sameRace['a_info']['connection_id'] !== (int) $sameRace['b_info']['connection_id'] && (int) $differentRace['a_info']['connection_id'] !== (int) $differentRace['b_info']['connection_id'], 'submission workers use independent connections');

foreach (glob($temp . DIRECTORY_SEPARATOR . '*') as $file) { if (is_file($file)) { unlink($file); } }
rmdir($temp);

echo 'SAME_KEY_GATE_PID=' . $sameRace['gate']['pid'] . ' SAME_KEY_GATE_CONNECTION=' . $sameRace['gate']['connection']
    . ' SAME_KEY_A_PID=' . $sameRace['a_info']['pid'] . ' SAME_KEY_A_CONNECTION=' . $sameRace['a_info']['connection_id']
    . ' SAME_KEY_B_PID=' . $sameRace['b_info']['pid'] . ' SAME_KEY_B_CONNECTION=' . $sameRace['b_info']['connection_id'] . "\n";
echo 'DIFFERENT_KEY_GATE_PID=' . $differentRace['gate']['pid'] . ' DIFFERENT_KEY_GATE_CONNECTION=' . $differentRace['gate']['connection']
    . ' DIFFERENT_KEY_A_PID=' . $differentRace['a_info']['pid'] . ' DIFFERENT_KEY_A_CONNECTION=' . $differentRace['a_info']['connection_id']
    . ' DIFFERENT_KEY_B_PID=' . $differentRace['b_info']['pid'] . ' DIFFERENT_KEY_B_CONNECTION=' . $differentRace['b_info']['connection_id'] . "\n";
echo "TASK7C_SAME_KEY_WAIT_GRAPH=PASS\n";
echo "TASK7C_SAME_KEY_CONCURRENT_SUBMIT=PASS\n";
echo "TASK7C_SAME_KEY_ONE_MUTATION=PASS\n";
echo "TASK7C_SAME_KEY_ONE_EVENT=PASS\n";
echo "TASK7C_SAME_KEY_ONE_LINK_SET=PASS\n";
echo "TASK7C_DIFFERENT_KEY_WAIT_GRAPH=PASS\n";
echo "TASK7C_DIFFERENT_KEY_SUBMIT_RACE=PASS\n";
echo "TASK7C_DIFFERENT_KEY_ONE_WINNER=PASS\n";
echo "TASK7C_DIFFERENT_KEY_NO_PARTIAL_MUTATION=PASS\n";
