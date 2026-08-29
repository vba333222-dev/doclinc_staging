<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'models/Visit_result_m.php';
require_once APPPATH . 'libraries/Visit_result_service.php';

$mode = (string) ($argv[1] ?? '');
$targetId = (int) ($argv[2] ?? 0);
$actorId = (int) ($argv[3] ?? 0);
$argument = (string) ($argv[4] ?? '');
$announceFile = (string) ($argv[5] ?? '');
if (!in_array($mode, array('get_or_create', 'save'), true) || $targetId < 1 || $actorId < 1 || $announceFile === '') {
    fwrite(STDERR, "invalid_result_worker_input\n");
    exit(2);
}

$connection = $app->db->query('SELECT CONNECTION_ID() AS id')->row();
$connectionId = $connection ? (int) $connection->id : 0;
$announce = array('pid' => getmypid(), 'connection_id' => $connectionId, 'mode' => $mode);
$tmp = $announceFile . '.' . getmypid() . '.tmp';
file_put_contents($tmp, json_encode($announce, JSON_UNESCAPED_SLASHES), LOCK_EX);
rename($tmp, $announceFile);

$service = new Visit_result_service($app->db);
if ($mode === 'get_or_create') {
    $result = $service->getOrCreateDraft($targetId, $actorId);
} else {
    $payload = array(
        'observation_summary' => 'Concurrent ' . $argument,
        'findings' => array(array('key' => 'worker', 'label' => 'Worker', 'value' => $argument)),
        'actions' => array(),
        'performer_notes' => null,
    );
    $result = $service->saveDraft($targetId, $actorId, 0, $payload);
}

echo json_encode(array(
    'ok' => ($result['status'] ?? null) === 'success',
    'code' => $result['safe_error_code'] ?? null,
    'connection_id' => $connectionId,
    'pid' => getmypid(),
    'result' => $result,
), JSON_UNESCAPED_SLASHES) . "\n";
