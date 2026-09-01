<?php
require_once __DIR__ . '/ci3_bootstrap.php';
require_once APPPATH . 'libraries/Clinical_closure_service.php';
$requestId = (int) ($argv[1] ?? 0); $actor = (int) ($argv[2] ?? 0); $mode = (string) ($argv[3] ?? 'non_visit'); $key = (string) ($argv[4] ?? '');
$barrierDir = getenv('TASK10_BARRIER_DIR') ?: ''; $runId = getenv('TASK10_RUN_ID') ?: ''; $scenarioId = getenv('TASK10_SCENARIO_ID') ?: ''; $workerId = getenv('TASK10_WORKER_ID') ?: '';
$callBeginNs = null; $callEndNs = null;
if ($barrierDir !== '' && $runId !== '' && $scenarioId !== '' && ($workerId === 'A' || $workerId === 'B')) {
    $db = $GLOBALS['vcw_app']->db;
    $request = $db->select('request_id,user_id,request_status,assigned_puskesmas_code,consultation_mode,visit_status')->where('request_id', $requestId)->get('requests')->row_array();
    $disposition = $db->select('disposition_id,version_no,decision')->where('request_id', $requestId)->order_by('version_no', 'DESC')->limit(1)->get('visit_dispositions')->row_array();
    $responsible = $db->select('responsible_assignment_id,user_id,staff_id,status')->where('request_id', $requestId)->where('status', 'aktif')->order_by('responsible_assignment_id', 'DESC')->limit(1)->get('request_responsible_doctor_assignments')->row_array();
    $record = $db->select('record_id,clinical_finalized_at,clinical_finalized_by_user_id')->where('request_id', $requestId)->order_by('record_id', 'DESC')->limit(1)->get('medicalrecords')->row_array();
    $user = $db->select('userId,role,status')->where('userId', $actor)->get('users')->row_array();
    $staff = $db->select('staff_id,user_id,profesi,status,kode_pkm')->where('user_id', $actor)->order_by('staff_id', 'DESC')->limit(1)->get('puskesmas_staff')->row_array();
    $result = $db->select('visit_result_id,visit_assignment_id,performer_user_id,performer_staff_id,status')->where('request_id', $requestId)->where('status', 'submitted')->order_by('visit_result_id', 'DESC')->limit(1)->get('visit_results')->row_array();
    $review = $result ? $db->select('clinical_review_id,decision')->where('visit_result_id', (int) $result['visit_result_id'])->get('clinical_reviews')->row_array() : array();
    $assignment = $result ? $db->select('visit_assignment_id,user_id,staff_id,status')->where('visit_assignment_id', (int) $result['visit_assignment_id'])->get('request_visit_performer_assignments')->row_array() : array();
    $facts = array('request' => $request, 'disposition' => $disposition, 'responsible' => $responsible, 'record' => array('record_id' => $record['record_id'] ?? null, 'finalized' => !empty($record['clinical_finalized_at']), 'finalized_by' => $record['clinical_finalized_by_user_id'] ?? null), 'user' => $user, 'staff' => $staff, 'result' => $result, 'review' => $review, 'assignment' => $assignment, 'mode' => $mode);
    $fingerprint = hash('sha256', json_encode($facts, JSON_UNESCAPED_SLASHES));
    $ready = array('run_id' => $runId, 'scenario_id' => $scenarioId, 'worker_id' => $workerId, 'operation' => 'closure', 'request_id' => $requestId, 'user_id' => $actor, 'mode' => $mode, 'fixture_fingerprint' => $fingerprint, 'call_begin_ns' => 0);
    $tmp = $barrierDir . DIRECTORY_SEPARATOR . 'ready-' . $workerId . '.tmp'; $readyPath = $barrierDir . DIRECTORY_SEPARATOR . 'ready-' . $workerId . '.json';
    file_put_contents($tmp, json_encode($ready)); rename($tmp, $readyPath);
    $goPath = $barrierDir . DIRECTORY_SEPARATOR . 'go.json'; $deadline = microtime(true) + 10; $goData = null;
    do { if (is_file($goPath)) { $candidate = json_decode((string) file_get_contents($goPath), true); if (is_array($candidate) && ($candidate['run_id'] ?? '') === $runId && ($candidate['scenario_id'] ?? '') === $scenarioId) { $goData = $candidate; break; } } usleep(10000); } while (microtime(true) < $deadline);
    if ($goData === null) { fwrite(STDERR, "BARRIER_PROTOCOL_TIMEOUT\n"); exit(2); }
    $callBeginNs = function_exists('hrtime') ? hrtime(true) : (int) round(microtime(true) * 1000000000);
}
$service = new Clinical_closure_service($GLOBALS['vcw_app']->db);
$result = $mode === 'visit' ? $service->closeClinicalVisit($requestId, $actor, $key) : $service->closeClinicalConsultation($requestId, $actor, $key);
if ($callBeginNs !== null) { $callEndNs = function_exists('hrtime') ? hrtime(true) : (int) round(microtime(true) * 1000000000); }
echo json_encode(array(
    'status' => $result['status'] ?? 'error',
    'safe_error_code' => $result['safe_error_code'] ?? null,
    'request_id' => $result['request_id'] ?? $requestId,
    'closure_operation_id' => $result['closure_operation_id'] ?? null,
    'closed_at' => $result['closed_at'] ?? null,
    'authority_source' => $result['authority_source'] ?? null,
    'idempotent' => !empty($result['idempotent']),
    'call_begin_ns' => $callBeginNs,
    'call_end_ns' => $callEndNs,
));
