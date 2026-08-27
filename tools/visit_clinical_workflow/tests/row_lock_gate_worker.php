<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';

$targetType = (string) ($argv[1] ?? '');
$targetId = (int) ($argv[2] ?? 0);
$readyFile = (string) ($argv[3] ?? '');
$releaseFile = (string) ($argv[4] ?? '');
$targets = array(
    'disposition' => array('table' => 'visit_dispositions', 'key' => 'disposition_id'),
    'performer_assignment' => array('table' => 'request_visit_performer_assignments', 'key' => 'visit_assignment_id'),
);

function gate_emit($payload, $exit = 0)
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
    exit($exit);
}

if (!isset($targets[$targetType]) || $targetId < 1 || $readyFile === '' || $releaseFile === '') {
    gate_emit(array('ok' => false, 'code' => isset($targets[$targetType]) ? 'INVALID_GATE_INPUT' : 'INVALID_TARGET_TYPE', 'target_type' => $targetType, 'target_id' => $targetId, 'pid' => getmypid()), 0);
}

$target = $targets[$targetType];
$connection = $app->db->query('SELECT CONNECTION_ID() AS id')->row();
$connectionId = $connection ? (int) $connection->id : 0;
$app->db->trans_begin();
$row = $app->db->query('SELECT `' . $target['key'] . '` FROM `' . $target['table'] . '` WHERE `' . $target['key'] . '` = ? FOR UPDATE', array($targetId))->row();
if (!$row) {
    $app->db->trans_rollback();
    gate_emit(array('ok' => false, 'code' => 'TARGET_NOT_FOUND', 'target_type' => $targetType, 'target_id' => $targetId, 'connection_id' => $connectionId, 'pid' => getmypid()), 0);
}

$readyPayload = array('ok' => true, 'code' => 'GATE_READY', 'target_type' => $targetType, 'target_id' => $targetId, 'connection_id' => $connectionId, 'pid' => getmypid(), 'lock_acquired' => true);
$tmp = $readyFile . '.' . getmypid() . '.tmp';
file_put_contents($tmp, json_encode($readyPayload, JSON_UNESCAPED_SLASHES), LOCK_EX);
rename($tmp, $readyFile);
$deadline = microtime(true) + 30.0;
while (!is_file($releaseFile) && microtime(true) < $deadline) {
    usleep(100000);
}
$released = is_file($releaseFile);
$app->db->trans_rollback();
gate_emit(array('ok' => $released, 'code' => $released ? 'GATE_COMPLETE' : 'GATE_RELEASE_TIMEOUT', 'target_type' => $targetType, 'target_id' => $targetId, 'connection_id' => $connectionId, 'pid' => getmypid(), 'lock_acquired' => true, 'released' => $released), $released ? 0 : 1);
