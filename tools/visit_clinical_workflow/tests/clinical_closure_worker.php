<?php
require_once __DIR__ . '/ci3_bootstrap.php';
require_once APPPATH . 'libraries/Clinical_closure_service.php';
$requestId = (int) ($argv[1] ?? 0); $actor = (int) ($argv[2] ?? 0); $mode = (string) ($argv[3] ?? 'non_visit'); $key = (string) ($argv[4] ?? '');
$service = new Clinical_closure_service($GLOBALS['vcw_app']->db);
$result = $mode === 'visit' ? $service->closeClinicalVisit($requestId, $actor, $key) : $service->closeClinicalConsultation($requestId, $actor, $key);
echo json_encode(array(
    'status' => $result['status'] ?? 'error',
    'safe_error_code' => $result['safe_error_code'] ?? null,
    'request_id' => $result['request_id'] ?? $requestId,
    'closure_operation_id' => $result['closure_operation_id'] ?? null,
    'closed_at' => $result['closed_at'] ?? null,
    'authority_source' => $result['authority_source'] ?? null,
    'idempotent' => !empty($result['idempotent']),
));
