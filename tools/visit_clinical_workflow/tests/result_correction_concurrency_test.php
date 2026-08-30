<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'models/Visit_result_m.php';
require_once APPPATH . 'libraries/Visit_result_service.php';
require_once __DIR__ . '/result_submission_support.php';

vcw_assert_true(method_exists('Visit_result_service', 'createCorrectionDraft'), 'Task 7D correction API required for concurrency');

function r7dc_start($script, array $arguments, &$pipes)
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $script);
    foreach ($arguments as $argument) { $command .= ' ' . escapeshellarg((string) $argument); }
    $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    vcw_assert_true(is_resource($process), 'independent correction worker starts');
    return $process;
}

function r7dc_wait_file($path, $message)
{
    $deadline = microtime(true) + 15.0;
    while (!is_file($path) && microtime(true) < $deadline) { usleep(100000); }
    vcw_assert_true(is_file($path), $message);
    $payload = json_decode((string) file_get_contents($path), true);
    vcw_assert_true(is_array($payload) && !empty($payload['connection_id']), $message . ' payload');
    return $payload;
}

function r7dc_collect($process, array $pipes, $message)
{
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) { fclose($pipe); }
    vcw_assert_same(0, proc_close($process), $message . ' exit stderr=' . trim($error));
    $payload = json_decode(trim((string) $output), true);
    vcw_assert_true(is_array($payload), $message . ' JSON output=' . trim($output));
    return $payload;
}

function r7dc_root_password()
{
    $inspect = json_decode((string) shell_exec('docker inspect visit_clinical_workflow-db-1'), true);
    foreach (($inspect[0]['Config']['Env'] ?? array()) as $entry) {
        if (strpos($entry, 'MARIADB_ROOT_PASSWORD=') === 0) { return substr($entry, strlen('MARIADB_ROOT_PASSWORD=')); }
    }
    return '';
}

function r7dc_edges($password)
{
    $sql = 'SELECT r.trx_mysql_thread_id,b.trx_mysql_thread_id FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id';
    $command = 'docker exec visit_clinical_workflow-db-1 mariadb -N -B -uroot -p' . escapeshellarg($password) . ' doclinc_visit_test -e ' . escapeshellarg($sql);
    $edges = array();
    foreach (preg_split('/\r?\n/', trim((string) shell_exec($command))) as $line) {
        if (preg_match('/^(\d+)\s+(\d+)$/', trim($line), $match)) { $edges[] = array((int) $match[1], (int) $match[2]); }
    }
    return $edges;
}

function r7dc_wait_graph($password, array $expected, $message)
{
    $deadline = microtime(true) + 15.0;
    while (microtime(true) < $deadline) {
        $actual = r7dc_edges($password);
        $missing = array_filter($expected, function ($edge) use ($actual) { return !in_array($edge, $actual, true); });
        if (!$missing) { return; }
        usleep(100000);
    }
    vcw_assert_true(false, $message);
}

function r7dc_seed_review($db, array $fixture, $resultId, $key)
{
    $responsible = $db->where('request_id', $fixture['request'])->where('status', 'aktif')->get('request_responsible_doctor_assignments')->row();
    vcw_assert_true((bool) $responsible, 'correction concurrency responsible assignment exists');
    $db->insert('clinical_reviews', array(
        'request_id' => $fixture['request'], 'visit_result_id' => $resultId, 'reviewer_user_id' => $fixture['doctor'],
        'responsible_assignment_id' => $responsible->responsible_assignment_id, 'decision' => 'correction_required',
        'correction_reason' => 'Perbaiki catatan faktual.', 'review_notes' => 'Synthetic Task 7D concurrency',
        'reviewed_at' => date('Y-m-d H:i:s.u'), 'idempotency_key' => $key,
    ));
}

function r7dc_submitted($db, $service, array $fixture, $suffix)
{
    $resultId = r7c_draft($db, $service, $fixture, $suffix);
    $measurementId = r7c_measurement($db, $fixture);
    vcw_assert_same('success', $service->submit($resultId, $fixture['performer'], array($measurementId), 'r7dc-submit-' . $resultId)['status'] ?? null, 'correction concurrency predecessor submitted');
    return $resultId;
}

function r7dc_gate($dispositionId, $prefix)
{
    $ready = $prefix . '.gate-ready';
    $release = $prefix . '.gate-release';
    $pipes = array();
    $process = r7dc_start('row_lock_gate_worker.php', array('disposition', $dispositionId, $ready, $release), $pipes);
    $info = r7dc_wait_file($ready, 'correction disposition gate ready');
    return array('process' => $process, 'pipes' => $pipes, 'release' => $release, 'pid' => (int) $info['pid'], 'connection' => (int) $info['connection_id']);
}

function r7dc_run_pair($password, $temp, array $fixture, $predecessorId, $keyA, $keyB, $label)
{
    $gate = r7dc_gate($fixture['disposition'], $temp . DIRECTORY_SEPARATOR . $label);
    $aPipes = array();
    $a = r7dc_start('result_correction_worker.php', array($fixture['request'], $fixture['performer'], $predecessorId, $keyA, $temp . DIRECTORY_SEPARATOR . $label . '-a.json'), $aPipes);
    $aInfo = r7dc_wait_file($temp . DIRECTORY_SEPARATOR . $label . '-a.json', $label . ' worker A announced');
    r7dc_wait_graph($password, array(array((int) $aInfo['connection_id'], $gate['connection'])), $label . ' worker A waits on gate');
    $bPipes = array();
    $b = r7dc_start('result_correction_worker.php', array($fixture['request'], $fixture['performer'], $predecessorId, $keyB, $temp . DIRECTORY_SEPARATOR . $label . '-b.json'), $bPipes);
    $bInfo = r7dc_wait_file($temp . DIRECTORY_SEPARATOR . $label . '-b.json', $label . ' worker B announced');
    r7dc_wait_graph($password, array(array((int) $aInfo['connection_id'], $gate['connection']), array((int) $bInfo['connection_id'], (int) $aInfo['connection_id'])), $label . ' B to A to gate wait graph');
    file_put_contents($gate['release'], 'release', LOCK_EX);
    $aResult = r7dc_collect($a, $aPipes, $label . ' worker A');
    $bResult = r7dc_collect($b, $bPipes, $label . ' worker B');
    r7dc_collect($gate['process'], $gate['pipes'], $label . ' gate');
    return array('gate' => $gate, 'a_info' => $aInfo, 'b_info' => $bInfo, 'a' => $aResult, 'b' => $bResult);
}

$password = r7dc_root_password();
vcw_assert_true($password !== '', 'disposable metadata observer credential available');
$maximum = $db->query('SELECT GREATEST(COALESCE((SELECT MAX(userId) FROM users),0),COALESCE((SELECT MAX(staff_id) FROM puskesmas_staff),0),COALESCE((SELECT MAX(request_id) FROM requests),0)) AS base_id')->row();
$base = (int) $maximum->base_id + 11000;
$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vcw-task7d-' . $base;
vcw_assert_true(mkdir($temp), 'Task 7D concurrency temp directory');
$service = new Visit_result_service($db);

$same = r7c_fixture($db, $base, 'SAMEKEY');
$sameV1 = r7dc_submitted($db, $service, $same, 'same');
r7dc_seed_review($db, $same, $sameV1, 'r7dc-review-same-' . $sameV1);
$sameKey = 'r7dc-same-' . $sameV1;
$sameRace = r7dc_run_pair($password, $temp, $same, $sameV1, $sameKey, $sameKey, 'same');
vcw_assert_true(!empty($sameRace['a']['ok']) && !empty($sameRace['b']['ok']), 'same-key correction calls both resolve success');
vcw_assert_same((int) $sameRace['a']['result']['visit_result_id'], (int) $sameRace['b']['result']['visit_result_id'], 'same-key correction returns one canonical successor');
vcw_assert_same(1, (int) $db->where('supersedes_result_id', $sameV1)->count_all_results('visit_results'), 'same-key exactly one successor row');
vcw_assert_same(1, (int) $db->where('request_id', $same['request'])->where('event_type', 'visit_result.correction_draft_created')->count_all_results('request_events'), 'same-key exactly one correction event');

$different = r7c_fixture($db, $base + 100, 'DIFFERENTKEY');
$differentV1 = r7dc_submitted($db, $service, $different, 'different');
r7dc_seed_review($db, $different, $differentV1, 'r7dc-review-different-' . $differentV1);
$differentRace = r7dc_run_pair($password, $temp, $different, $differentV1, 'r7dc-a-' . $differentV1, 'r7dc-b-' . $differentV1, 'different');
$calls = array($differentRace['a'], $differentRace['b']);
$winners = array_values(array_filter($calls, function ($item) { return !empty($item['ok']); }));
$losers = array_values(array_filter($calls, function ($item) { return ($item['code'] ?? null) === 'CORRECTION_ALREADY_EXISTS'; }));
vcw_assert_same(1, count($winners), 'different correction keys have one winner');
vcw_assert_same(1, count($losers), 'different correction key loser is safely denied');
vcw_assert_same(1, (int) $db->where('supersedes_result_id', $differentV1)->count_all_results('visit_results'), 'different-key exactly one successor row');
vcw_assert_same(1, (int) $db->where('request_id', $different['request'])->where('event_type', 'visit_result.correction_draft_created')->count_all_results('request_events'), 'different-key exactly one correction event');
vcw_assert_true((int) $sameRace['a_info']['connection_id'] !== (int) $sameRace['b_info']['connection_id'] && (int) $differentRace['a_info']['connection_id'] !== (int) $differentRace['b_info']['connection_id'], 'correction workers use independent connections');

foreach (glob($temp . DIRECTORY_SEPARATOR . '*') as $file) { if (is_file($file)) { unlink($file); } }
rmdir($temp);

echo 'SAME_KEY_GATE_CONNECTION=' . $sameRace['gate']['connection'] . ' SAME_KEY_A_CONNECTION=' . $sameRace['a_info']['connection_id'] . ' SAME_KEY_B_CONNECTION=' . $sameRace['b_info']['connection_id'] . "\n";
echo 'DIFFERENT_KEY_GATE_CONNECTION=' . $differentRace['gate']['connection'] . ' DIFFERENT_KEY_A_CONNECTION=' . $differentRace['a_info']['connection_id'] . ' DIFFERENT_KEY_B_CONNECTION=' . $differentRace['b_info']['connection_id'] . "\n";
echo "TASK7D_SAME_KEY_WAIT_GRAPH=PASS\nTASK7D_SAME_KEY_CONCURRENT_CORRECTION=PASS\nTASK7D_SAME_KEY_ONE_SUCCESSOR=PASS\nTASK7D_SAME_KEY_ONE_EVENT=PASS\n";
echo "TASK7D_DIFFERENT_KEY_WAIT_GRAPH=PASS\nTASK7D_DIFFERENT_KEY_CORRECTION_RACE=PASS\nTASK7D_DIFFERENT_KEY_ONE_WINNER=PASS\nTASK7D_DIFFERENT_KEY_NO_PARTIAL_MUTATION=PASS\n";
