<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
require_once APPPATH . 'libraries/Clinical_finalization_service.php';
$requestId = isset($argv[1]) ? (int) $argv[1] : 0;
$actorUserId = isset($argv[2]) ? (int) $argv[2] : 0;
$key = isset($argv[3]) ? (string) $argv[3] : '';
$result = (new Clinical_finalization_service($app->db))->finalize($requestId, $actorUserId, $key);
echo json_encode(array(
    'status' => $result['status'] ?? 'error',
    'safe_error_code' => $result['safe_error_code'] ?? null,
));
