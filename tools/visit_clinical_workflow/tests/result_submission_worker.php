<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'models/Visit_result_m.php';
require_once APPPATH . 'libraries/Visit_result_service.php';

$resultId = (int) ($argv[1] ?? 0);
$actorId = (int) ($argv[2] ?? 0);
$measurementCsv = (string) ($argv[3] ?? '');
$submissionKey = (string) ($argv[4] ?? '');
$announceFile = (string) ($argv[5] ?? '');
if ($resultId < 1 || $actorId < 1 || $submissionKey === '' || $announceFile === '') {
    fwrite(STDERR, "invalid_result_submission_worker_input\n");
    exit(2);
}
$measurementIds = $measurementCsv === '' ? array() : array_map('intval', explode(',', $measurementCsv));
$connection = $app->db->query('SELECT CONNECTION_ID() AS id')->row();
$connectionId = $connection ? (int) $connection->id : 0;
$announce = array('pid' => getmypid(), 'connection_id' => $connectionId, 'mode' => 'submit');
$tmp = $announceFile . '.' . getmypid() . '.tmp';
file_put_contents($tmp, json_encode($announce, JSON_UNESCAPED_SLASHES), LOCK_EX);
rename($tmp, $announceFile);

$result = (new Visit_result_service($app->db))->submit($resultId, $actorId, $measurementIds, $submissionKey);
echo json_encode(array(
    'ok' => ($result['status'] ?? null) === 'success',
    'code' => $result['safe_error_code'] ?? null,
    'connection_id' => $connectionId,
    'pid' => getmypid(),
    'result' => $result,
), JSON_UNESCAPED_SLASHES) . "\n";
