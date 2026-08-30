<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'models/Visit_result_m.php';
require_once APPPATH . 'libraries/Visit_result_service.php';

$requestId = (int) ($argv[1] ?? 0);
$actorId = (int) ($argv[2] ?? 0);
$predecessorId = (int) ($argv[3] ?? 0);
$key = (string) ($argv[4] ?? '');
$announceFile = (string) ($argv[5] ?? '');
if ($requestId < 1 || $actorId < 1 || $predecessorId < 1 || $key === '' || $announceFile === '') {
    fwrite(STDERR, "invalid_result_correction_worker_input\n");
    exit(2);
}
$connection = $app->db->query('SELECT CONNECTION_ID() AS id')->row();
$connectionId = $connection ? (int) $connection->id : 0;
$tmp = $announceFile . '.' . getmypid() . '.tmp';
file_put_contents($tmp, json_encode(array('pid' => getmypid(), 'connection_id' => $connectionId), JSON_UNESCAPED_SLASHES), LOCK_EX);
rename($tmp, $announceFile);
$result = (new Visit_result_service($app->db))->createCorrectionDraft($requestId, $actorId, $predecessorId, $key);
echo json_encode(array(
    'ok' => ($result['status'] ?? null) === 'success',
    'code' => $result['safe_error_code'] ?? null,
    'connection_id' => $connectionId,
    'pid' => getmypid(),
    'result' => $result,
), JSON_UNESCAPED_SLASHES) . "\n";
