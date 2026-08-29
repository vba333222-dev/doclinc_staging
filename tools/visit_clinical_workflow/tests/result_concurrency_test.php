<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);

vcw_assert_true((bool) $db->table_exists('visit_results'), 'Task 7B concurrency reaches real result schema');
vcw_assert_true(is_file(APPPATH . 'models/Visit_result_m.php') && is_file(APPPATH . 'libraries/Visit_result_service.php'), 'Task 7B production API missing for concurrency suite');
require_once APPPATH . 'models/Visit_result_m.php';
require_once APPPATH . 'libraries/Visit_result_service.php';

function r7bc_start($script, array $arguments, &$pipes)
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $script);
    foreach ($arguments as $argument) { $command .= ' ' . escapeshellarg((string) $argument); }
    $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    vcw_assert_true(is_resource($process), 'independent result worker starts');
    return $process;
}

function r7bc_wait_file($path, $message)
{
    $deadline = microtime(true) + 15.0;
    while (!is_file($path) && microtime(true) < $deadline) { usleep(100000); }
    vcw_assert_true(is_file($path), $message);
    $payload = json_decode((string) file_get_contents($path), true);
    vcw_assert_true(is_array($payload) && !empty($payload['connection_id']), $message . ' payload');
    return $payload;
}

function r7bc_collect($process, array $pipes, $message)
{
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) { fclose($pipe); }
    $exit = proc_close($process);
    vcw_assert_same(0, $exit, $message . ' exit stderr=' . trim($error));
    $payload = json_decode(trim((string) $output), true);
    vcw_assert_true(is_array($payload), $message . ' JSON');
    return $payload;
}

function r7bc_root_password()
{
    $inspect = json_decode((string) shell_exec('docker inspect visit_clinical_workflow-db-1'), true);
    foreach (($inspect[0]['Config']['Env'] ?? array()) as $entry) {
        if (strpos($entry, 'MARIADB_ROOT_PASSWORD=') === 0) { return substr($entry, strlen('MARIADB_ROOT_PASSWORD=')); }
    }
    return '';
}

function r7bc_edges($password)
{
    $sql = 'SELECT r.trx_mysql_thread_id,b.trx_mysql_thread_id FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id';
    $command = 'docker exec visit_clinical_workflow-db-1 mariadb -N -B -uroot -p' . escapeshellarg($password) . ' doclinc_visit_test -e ' . escapeshellarg($sql);
    $edges = array();
    foreach (preg_split('/\r?\n/', trim((string) shell_exec($command))) as $line) {
        if (preg_match('/^(\d+)\s+(\d+)$/', trim($line), $match)) { $edges[] = array((int) $match[1], (int) $match[2]); }
    }
    return $edges;
}

function r7bc_wait_graph($password, array $expected, $message)
{
    $deadline = microtime(true) + 15.0;
    while (microtime(true) < $deadline) {
        $actual = r7bc_edges($password);
        $missing = array_filter($expected, function ($edge) use ($actual) { return !in_array($edge, $actual, true); });
        if (!$missing) { return; }
        usleep(100000);
    }
    vcw_assert_true(false, $message);
}

function r7bc_fixture($db, $base, $suffix)
{
    $facility = 'RESULT-RACE-' . $suffix . '-' . $base;
    $request = $base;
    $command = $base + 1;
    $doctor = $base + 2;
    $performer = $base + 3;
    vcw_assert_safe_request_id($request);
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'Result Race', 'aktif'));
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($command, 'Result Command', 'result-command-' . $command . '@example.invalid', 'result-command-' . $command, 'x', 'dokter', 'aktif', 0, $facility));
    foreach (array(array($doctor, 'dokter'), array($performer, 'Perawat')) as $person) {
        $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($person[0], 'Result Race', 'result-race-' . $person[0] . '@example.invalid', 'result-race-' . $person[0], 'x', 'dokter', 'aktif', 0, $facility));
        $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($person[0], $facility, $person[0], 'Result Race Staff', $person[1], 'aktif'));
        $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($person[0], $facility, '2026-01-01 00:00:00', 'active', $person[0]));
    }
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)', array($request, $doctor, 'synthetic', 'Accepted', $facility, 'Result Race', 'in_service', 'visit', $doctor, $performer));
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $doctor, $doctor, $doctor, 'aktif'));
    $db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', array($request, 1, 'visit', 'routine', $doctor, 'result-race-' . $request));
    $disposition = (int) $db->insert_id();
    $db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $performer, $performer, $command, 'aktif'));
    return array('request' => $request, 'performer' => $performer, 'disposition' => $disposition);
}

function r7bc_gate($dispositionId, $prefix)
{
    $ready = $prefix . '.gate-ready';
    $release = $prefix . '.gate-release';
    $pipes = array();
    $process = r7bc_start('row_lock_gate_worker.php', array('disposition', $dispositionId, $ready, $release), $pipes);
    $info = r7bc_wait_file($ready, 'disposition gate ready');
    return array('process' => $process, 'pipes' => $pipes, 'release' => $release, 'pid' => (int) $info['pid'], 'connection' => (int) $info['connection_id']);
}

$password = r7bc_root_password();
vcw_assert_true($password !== '', 'disposable lock observer credential available');
$maximum = $db->query('SELECT GREATEST(COALESCE((SELECT MAX(userId) FROM users),0),COALESCE((SELECT MAX(staff_id) FROM puskesmas_staff),0),COALESCE((SELECT MAX(request_id) FROM requests),0)) AS base_id')->row();
$base = (int) $maximum->base_id + 3000;
$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vcw-task7b-' . $base;
vcw_assert_true(mkdir($temp), 'Task 7B concurrency temp directory');

$create = r7bc_fixture($db, $base, 'CREATE');
$createGate = r7bc_gate($create['disposition'], $temp . DIRECTORY_SEPARATOR . 'create');
$createAPipes = array();
$createA = r7bc_start('result_worker.php', array('get_or_create', $create['request'], $create['performer'], '-', $temp . DIRECTORY_SEPARATOR . 'create-a.json'), $createAPipes);
$createAInfo = r7bc_wait_file($temp . DIRECTORY_SEPARATOR . 'create-a.json', 'create worker A announced');
r7bc_wait_graph($password, array(array((int) $createAInfo['connection_id'], $createGate['connection'])), 'worker A waits on disposition gate');
$createBPipes = array();
$createB = r7bc_start('result_worker.php', array('get_or_create', $create['request'], $create['performer'], '-', $temp . DIRECTORY_SEPARATOR . 'create-b.json'), $createBPipes);
$createBInfo = r7bc_wait_file($temp . DIRECTORY_SEPARATOR . 'create-b.json', 'create worker B announced');
r7bc_wait_graph($password, array(array((int) $createAInfo['connection_id'], $createGate['connection']), array((int) $createBInfo['connection_id'], (int) $createAInfo['connection_id'])), 'get/create B to A to gate wait graph');
file_put_contents($createGate['release'], 'release', LOCK_EX);
$createAResult = r7bc_collect($createA, $createAPipes, 'create worker A');
$createBResult = r7bc_collect($createB, $createBPipes, 'create worker B');
r7bc_collect($createGate['process'], $createGate['pipes'], 'create gate');
vcw_assert_true(!empty($createAResult['ok']) && !empty($createBResult['ok']), 'both get/create workers succeed');
vcw_assert_same((int) $createAResult['result']['visit_result_id'], (int) $createBResult['result']['visit_result_id'], 'both workers return same draft');
vcw_assert_same(1, (int) $db->where('request_id', $create['request'])->count_all_results('visit_results'), 'one v1 only after race');

$save = r7bc_fixture($db, $base + 100, 'SAVE');
$draft = (new Visit_result_service($db))->getOrCreateDraft($save['request'], $save['performer']);
vcw_assert_same('success', $draft['status'] ?? null, 'stale-save fixture draft');
$resultId = (int) $draft['visit_result_id'];
$saveGate = r7bc_gate($save['disposition'], $temp . DIRECTORY_SEPARATOR . 'save');
$saveAPipes = array();
$saveA = r7bc_start('result_worker.php', array('save', $resultId, $save['performer'], 'A', $temp . DIRECTORY_SEPARATOR . 'save-a.json'), $saveAPipes);
$saveAInfo = r7bc_wait_file($temp . DIRECTORY_SEPARATOR . 'save-a.json', 'save worker A announced');
r7bc_wait_graph($password, array(array((int) $saveAInfo['connection_id'], $saveGate['connection'])), 'save A waits on disposition gate');
$saveBPipes = array();
$saveB = r7bc_start('result_worker.php', array('save', $resultId, $save['performer'], 'B', $temp . DIRECTORY_SEPARATOR . 'save-b.json'), $saveBPipes);
$saveBInfo = r7bc_wait_file($temp . DIRECTORY_SEPARATOR . 'save-b.json', 'save worker B announced');
r7bc_wait_graph($password, array(array((int) $saveAInfo['connection_id'], $saveGate['connection']), array((int) $saveBInfo['connection_id'], (int) $saveAInfo['connection_id'])), 'stale save B to A to gate wait graph');
file_put_contents($saveGate['release'], 'release', LOCK_EX);
$saveAResult = r7bc_collect($saveA, $saveAPipes, 'save worker A');
$saveBResult = r7bc_collect($saveB, $saveBPipes, 'save worker B');
r7bc_collect($saveGate['process'], $saveGate['pipes'], 'save gate');
$results = array($saveAResult, $saveBResult);
$successes = array_values(array_filter($results, function ($item) { return !empty($item['ok']); }));
$stales = array_values(array_filter($results, function ($item) { return ($item['code'] ?? null) === 'STALE_DRAFT_REVISION'; }));
vcw_assert_same(1, count($successes), 'exactly one stale-save winner');
vcw_assert_same(1, count($stales), 'exactly one stale-save loser');
$saved = $db->where('visit_result_id', $resultId)->get('visit_results')->row();
$winner = strpos((string) $saved->observation_summary, 'A') !== false ? 'A' : 'B';
$loser = $winner === 'A' ? 'B' : 'A';
vcw_assert_true((int) $saved->draft_revision === 1 && (string) $saved->observation_summary === 'Concurrent ' . $winner, 'winning payload and revision persisted');
vcw_assert_true(strpos((string) $saved->observation_summary, $loser) === false && strpos((string) $saved->findings_json, '"value":"' . $loser . '"') === false, 'losing payload absent');
vcw_assert_true((int) $saveAInfo['connection_id'] !== (int) $saveBInfo['connection_id'], 'stale-save workers use independent DB connections');

foreach (glob($temp . DIRECTORY_SEPARATOR . '*') as $file) { if (is_file($file)) { unlink($file); } }
rmdir($temp);

echo 'GET_CREATE_GATE_PID=' . $createGate['pid'] . ' GET_CREATE_GATE_CONNECTION=' . $createGate['connection']
    . ' GET_CREATE_A_PID=' . $createAInfo['pid'] . ' GET_CREATE_A_CONNECTION=' . $createAInfo['connection_id']
    . ' GET_CREATE_B_PID=' . $createBInfo['pid'] . ' GET_CREATE_B_CONNECTION=' . $createBInfo['connection_id'] . "\n";
echo 'STALE_SAVE_GATE_PID=' . $saveGate['pid'] . ' STALE_SAVE_GATE_CONNECTION=' . $saveGate['connection']
    . ' STALE_SAVE_A_PID=' . $saveAInfo['pid'] . ' STALE_SAVE_A_CONNECTION=' . $saveAInfo['connection_id']
    . ' STALE_SAVE_B_PID=' . $saveBInfo['pid'] . ' STALE_SAVE_B_CONNECTION=' . $saveBInfo['connection_id'] . "\n";
echo "TASK7B_GET_OR_CREATE_WAIT_GRAPH=PASS\n";
echo "TASK7B_GET_OR_CREATE_RACE=PASS\n";
echo "TASK7B_ONE_V1_ONLY=PASS\n";
echo "TASK7B_SAME_CANONICAL_DRAFT=PASS\n";
echo "TASK7B_STALE_SAVE_WAIT_GRAPH=PASS\n";
echo "TASK7B_STALE_SAVE_RACE=PASS\n";
echo "TASK7B_STALE_SAVE_NO_OVERWRITE=PASS\n";
echo "TASK7B_STALE_SAVE_INDEPENDENT_CONNECTIONS=PASS\n";
