<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'libraries/Clinical_review_service.php';

$requestId = (int) ($argv[1] ?? 0);
$resultId = (int) ($argv[2] ?? 0);
$reviewerId = (int) ($argv[3] ?? 0);
$decision = (string) ($argv[4] ?? '');
$reason = ($argv[5] ?? '') === '-' ? null : (string) $argv[5];
$notes = ($argv[6] ?? '') === '-' ? null : (string) $argv[6];
$key = (string) ($argv[7] ?? '');
$announce = (string) ($argv[8] ?? '');
$startGate = (string) ($argv[9] ?? '');
if ($requestId < 1 || $resultId < 1 || $reviewerId < 1 || $key === '' || $announce === '') { exit(2); }
$row = $app->db->query('SELECT CONNECTION_ID() id')->row();
$connectionId = (int) $row->id;
$temp = $announce . '.' . getmypid() . '.tmp';
file_put_contents($temp, json_encode(array('pid' => getmypid(), 'connection_id' => $connectionId)), LOCK_EX);
rename($temp, $announce);
if ($startGate !== '') {
    $deadline = microtime(true) + 15.0;
    while (!is_file($startGate) && microtime(true) < $deadline) { usleep(20000); }
    if (!is_file($startGate)) { echo json_encode(array('ok'=>false,'code'=>'HARNESS_START_TIMEOUT','connection_id'=>$connectionId,'pid'=>getmypid(),'result'=>array('status'=>'error','safe_error_code'=>'HARNESS_START_TIMEOUT')))."\n"; exit(3); }
}
$result = (new Clinical_review_service($app->db))->review($requestId, $resultId, $reviewerId, $decision, $reason, $notes, $key);
echo json_encode(array('ok' => ($result['status'] ?? null) === 'success', 'code' => $result['safe_error_code'] ?? null, 'connection_id' => $connectionId, 'pid' => getmypid(), 'result' => $result, 'db_error_code' => (int) (($app->db->error()['code'] ?? 0))), JSON_UNESCAPED_SLASHES) . "\n";
