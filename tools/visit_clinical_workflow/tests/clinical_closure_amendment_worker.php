<?php
require_once __DIR__ . '/ci3_bootstrap.php';
require_once APPPATH . 'libraries/Clinical_amendment_service.php';

$requestId = (int) ($argv[1] ?? 0);
$recordId = (int) ($argv[2] ?? 0);
$actor = (int) ($argv[3] ?? 0);
$key = (string) ($argv[4] ?? '');
$reason = (string) ($argv[5] ?? 'race');
$value = (string) ($argv[6] ?? 'corrected');
$dir = getenv('TASK10_BARRIER_DIR') ?: '';
$run = getenv('TASK10_RUN_ID') ?: '';
$scenario = getenv('TASK10_SCENARIO_ID') ?: '';
$workerId = getenv('TASK10_WORKER_ID') ?: '';
$begin = null;
$end = null;

if ($dir !== '' && $run !== '' && $scenario !== '' && ($workerId === 'A' || $workerId === 'B')) {
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
    $facts = array('request' => $request, 'disposition' => $disposition, 'responsible' => $responsible, 'record' => array('record_id' => $record['record_id'] ?? null, 'finalized' => !empty($record['clinical_finalized_at']), 'finalized_by' => $record['clinical_finalized_by_user_id'] ?? null), 'user' => $user, 'staff' => $staff, 'result' => $result, 'review' => $review, 'assignment' => $assignment, 'mode' => 'visit');
    $fingerprint = hash('sha256', json_encode($facts, JSON_UNESCAPED_SLASHES));
    $ready = array('run_id' => $run, 'scenario_id' => $scenario, 'worker_id' => $workerId, 'operation' => 'amendment', 'request_id' => $requestId, 'record_id' => $recordId, 'user_id' => $actor, 'mode' => 'visit', 'fixture_fingerprint' => $fingerprint, 'call_begin_ns' => 0);
    $tmp = $dir . '/ready-' . $workerId . '.tmp';
    file_put_contents($tmp, json_encode($ready));
    rename($tmp, $dir . '/ready-' . $workerId . '.json');
    $go = null;
    $deadline = microtime(true) + 10;
    do {
        if (is_file($dir . '/go.json')) {
            $candidate = json_decode((string) file_get_contents($dir . '/go.json'), true);
            if (is_array($candidate) && ($candidate['run_id'] ?? '') === $run && ($candidate['scenario_id'] ?? '') === $scenario) {
                $go = $candidate;
                break;
            }
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    if ($go === null) {
        fwrite(STDERR, "BARRIER_PROTOCOL_TIMEOUT\n");
        exit(2);
    }
    $begin = function_exists('hrtime') ? hrtime(true) : (int) round(microtime(true) * 1000000000);
}

$service = new Clinical_amendment_service($GLOBALS['vcw_app']->db);
$result = $service->amend($requestId, $recordId, $actor, $reason, array(array('field_key' => 'diagnosis', 'corrected_value' => $value)), $key);
if ($begin !== null) {
    $end = function_exists('hrtime') ? hrtime(true) : (int) round(microtime(true) * 1000000000);
}
echo json_encode(array('status' => $result['status'] ?? 'error', 'safe_error_code' => $result['safe_error_code'] ?? null, 'request_id' => $requestId, 'operation' => 'amendment', 'call_begin_ns' => $begin, 'call_end_ns' => $end));
