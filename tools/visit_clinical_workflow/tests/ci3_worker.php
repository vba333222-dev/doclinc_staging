<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$role = isset($argv[1]) ? (string) $argv[1] : 'worker';
$barrier = isset($argv[2]) ? (string) $argv[2] : '';
if ($barrier === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', basename($barrier))) {
    throw new RuntimeException('invalid barrier');
}
$ready = $barrier . '.' . $role . '.ready';
$release = $barrier . '.release';
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 20;
while (!is_file($release) && microtime(true) < $deadline) { usleep(20000); }
if (!is_file($release)) { throw new RuntimeException('barrier timeout'); }
$id = $app->db->query('SELECT CONNECTION_ID() AS id')->row();
$request = $app->db->query('SELECT MIN(request_id) AS request_id FROM requests WHERE request_id NOT IN (47,52)')->row();
$requestId = $request ? (int) $request->request_id : 0;
$policy = new Visit_workflow_policy($app->db, false);
echo json_encode(array('worker'=>$role,'pid'=>getmypid(),'connection_id'=>$id ? (int)$id->id : 0,'request_id'=>$requestId,'enrolled'=>$requestId > 0 ? (bool)$policy->isEnrolled($requestId) : false)) . "\n";
