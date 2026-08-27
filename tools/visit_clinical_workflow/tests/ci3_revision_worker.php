<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$role = (string)($argv[1] ?? 'A'); $barrier = (string)($argv[2] ?? '');
$requestId = (int)($argv[3] ?? 29001); $actorId = (int)($argv[4] ?? 29001); $expectedVersion = (int)($argv[5] ?? 1);
$requiredProfession = (string)($argv[6] ?? ''); $idempotencyKey = (string)($argv[7] ?? ('race-revision-' . $role));
if (!in_array($role, array('A','B'), true) || $barrier === '') { throw new RuntimeException('invalid worker arguments'); }
$ready = $barrier.'.'.$role.'.ready'; $release = $barrier.'.release';
file_put_contents($ready,'ready');
$deadline = microtime(true)+20; while (!is_file($release) && microtime(true)<$deadline) { usleep(20000); }
if (!is_file($release)) { throw new RuntimeException('barrier timeout'); }
$conn = $app->db->query('SELECT CONNECTION_ID() AS id')->row();
$service = new Visit_disposition_service($app->db, new Visit_workflow_policy($app->db, true), true);
$input = array('decision'=>'visit','urgency'=>$role==='A'?'priority':'urgent'); if ($requiredProfession !== '') { $input['required_profession'] = $requiredProfession; }
$result = $service->revise($requestId, $actorId, $expectedVersion, $input, $idempotencyKey);
echo json_encode(array('worker'=>$role,'pid'=>getmypid(),'connection_id'=>$conn ? (int)$conn->id : 0,'result'=>$result))."\n";
