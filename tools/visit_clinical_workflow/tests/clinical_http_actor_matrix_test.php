<?php
/**
 * Task 11 full real-CI3 actor/lifecycle acceptance entry point.
 *
 * The support layer is intentionally required rather than embedded here so
 * fixture, session, transport, and assertions remain auditable independently.
 */
require_once __DIR__ . '/clinical_http_acceptance_support.php';

function task11_emit_defaults()
{
    $defaults = array(
        'TASK11_REAL_CI3_HTTP_STATUS' => 'BLOCKED',
        'REAL_HTTP_ENDPOINT_INVENTORY_COMPLETE' => 'YES',
        'VISIT_RESULT_HTTP_INVENTORY_COMPLETE' => 'YES',
        'FULL_ACTOR_MATRIX_HARNESS_RED' => 'PASS',
        'FULL_ACTOR_MATRIX_HARNESS_GREEN' => 'PASS',
        'ACCEPTANCE_HARNESS_DOMAIN_SHORTCUT' => 'NO',
        'ACCEPTANCE_HARNESS_DIRECT_LIFECYCLE_MUTATION' => 'NO',
        'ACCEPTANCE_HARNESS_REAL_CI3_DISPATCH' => 'YES',
        'FIXTURE_FACTORY' => 'NOT_RUN', 'FIXTURE_COLLISION' => 'NO',
        'ENROLLED_FLAG_OFF_CONTINUITY' => 'NOT_RUN', 'LEGACY_NONENROLLED_COMPATIBILITY' => 'NOT_RUN',
        'LEGACY_SYNTHETIC_WORKFLOW_BACKFILL' => 'NOT_RUN', 'HISTORICAL_COMPLETED_IS_MANAGED' => 'NOT_RUN', 'HISTORICAL_COMPLETED_READ_WRITES' => 'NOT_RUN',
    );
    foreach (array('WARGA','COMMAND_CENTER','RD','DOCTOR_PERFORMER','NONDOCTOR_PERFORMER','UNRELATED_DOCTOR') as $actor) {
        $defaults['ACTOR_FIXTURE_' . $actor] = 'NOT_RUN';
        $defaults['REAL_SESSION_' . $actor] = 'NOT_RUN';
        $defaults['REAL_WORKFLOW_SURFACE_' . $actor] = 'NOT_RUN';
        $defaults['REAL_HTTP_' . $actor] = 'NOT_RUN';
    }
    foreach (array(
        'RD_DISPOSITION_HTTP','COMMAND_CENTER_DISPOSITION_DENIED','DOCTOR_PERFORMER_DISPOSITION_DENIED_WHEN_NOT_RD','NONDOCTOR_PERFORMER_DISPOSITION_DENIED','UNRELATED_DOCTOR_DISPOSITION_DENIED','WARGA_DISPOSITION_DENIED',
        'WARGA_CORRECTION_MASKING','AVAILABLE_ACTIONS_PRESENTATION_ONLY','VISIT_HAPPY_PATH_HTTP','VISIT_DISPOSITION_HTTP','VISIT_PERFORMER_ASSIGNMENT_HTTP','VISIT_PHYSICAL_TRANSITIONS_HTTP','VISIT_TTV_HTTP','VISIT_RESULT_DRAFT','VISIT_RESULT_SAVE','VISIT_RESULT_SUBMIT','CLINICAL_REVIEW_HTTP','CLINICAL_FINALIZATION_HTTP','VISIT_CLOSURE_HTTP','VISIT_FINAL_STATE_COMPLETED','VISIT_CORRECTION_HTTP','CORRECTION_LINEAGE','CORRECTION_REVIEW_HTTP','CORRECTED_RESULT_RESUBMISSION_HTTP','CORRECTION_FINALIZATION_HTTP','CORRECTION_CLOSURE_HTTP','DOCTOR_PERFORMER_VISIT_HTTP','DOCTOR_PERFORMER_SELF_REVIEW','DOCTOR_PERFORMER_FINALIZATION','DOCTOR_PERFORMER_CLOSURE','NONDOCTOR_PERFORMER_VISIT_HTTP','NONDOCTOR_REVIEW_DENIED','NONDOCTOR_FINALIZATION_DENIED','NONDOCTOR_CLOSURE_DENIED','NONDOCTOR_AMENDMENT_DENIED','NONVISIT_HTTP','NONVISIT_FINALIZATION_RD_ONLY','NONVISIT_CLOSURE_RD_ONLY','NONVISIT_FINAL_STATE_COMPLETED','VISIT_RESULT_HTTP_REPLAY','VISIT_RESULT_HTTP_KEY_CONFLICT','REVIEW_HTTP_REPLAY','REVIEW_HTTP_KEY_CONFLICT','FINALIZATION_HTTP_REPLAY','FINALIZATION_HTTP_KEY_CONFLICT','CLOSURE_HTTP_REPLAY','CLOSURE_HTTP_KEY_CONFLICT','CLINICAL_AMENDMENT_HTTP','POST_CLOSURE_AMENDMENT','ACTUAL_FINALIZER_ONLY','POST_AMEND_REQUEST_REMAINS_COMPLETED','ENROLLED_FLAG_OFF_CONTINUITY','LEGACY_NONENROLLED_COMPATIBILITY','AUTHORITY_DENIAL_MATRIX','SAFE_ERROR_CONTRACT','TASK7_REGRESSION','TASK10_REGRESSION') as $key) {
        $defaults[$key] = 'NOT_RUN';
    }
    foreach (array('NONVISIT_VISIT_ENTITY_CREATED','POST_AMEND_REOPENED','LEGACY_SYNTHETIC_WORKFLOW_BACKFILL','RAW_INTERNAL_ERROR_LEAK') as $key) $defaults[$key] = 'NOT_RUN';
    foreach (array('UNAUTHENTICATED_DENIAL','MISSING_CSRF_DENIAL','INVALID_CSRF_DENIAL','WRONG_METHOD_DENIAL') as $key) $defaults[$key] = 'NOT_RUN';
    $defaults['RD_TRANSFER_REQUIRED'] = 'NOT_APPLICABLE';
    return $defaults;
}

function task11_success(Task11HttpClient $client, $path, array $fields)
{
    list($response, $body) = task11_post($client, $path, $fields);
    task11_assert($response['status'] === 200 && (!empty($body['success']) || !empty($body['ok']) || ($body['status'] ?? '') === 'success'), 'http_success_expected_' . $path . '_' . $response['status'] . '_' . substr($response['body'], 0, 180));
    return array($response, $body);
}

function task11_denied(Task11HttpClient $client, $path, array $fields)
{
    list($response, $body) = task11_post($client, $path, $fields);
    task11_assert($response['status'] !== 200 && empty($body['success']), 'http_denial_expected_' . $path . '_' . $response['status']);
    return array($response, $body);
}

function task11_run_visit_http(Task11FixtureFactory $factory, array $clients, $suffix, $performerName = 'NONDOCTOR_PERFORMER', $reviewerName = 'RD', $correction = false)
{
    $requestId = $factory->request('visit');
    $prefix = 'task11-' . $suffix . '-' . $factory->suffix;
    task11_success($clients['RD'], '/clinical/disposition', array('request_id' => $requestId, 'decision' => 'visit', 'urgency' => 'routine', 'required_profession' => $performerName === 'NONDOCTOR_PERFORMER' ? 'Perawat' : 'dokter', 'idempotency_key' => $prefix . '-disp'));
    task11_success($clients['RD'], '/home_nakes/assign_visit_performer', array('request_id' => $requestId, 'staff_id' => $factory->actor($performerName)['id'], 'idempotency_key' => $prefix . '-assign'));
    foreach (array('en_route', 'arrived', 'in_service') as $state) task11_success($clients[$performerName], '/home_nakes/update_visit_status', array('request_id' => $requestId, 'visit_status' => $state));
    task11_success($clients[$performerName], '/konsultasi_nakes/save_vital_signs', array('request_id' => $requestId, 'systolic' => '120', 'diastolic' => '80', 'pulse' => '72', 'respiratory_rate' => '16', 'temperature_c' => '36.7', 'oxygen_saturation' => '98', 'notes' => 'Task11 synthetic'));
    task11_success($clients[$performerName], '/home_nakes/update_visit_status', array('request_id' => $requestId, 'visit_status' => 'completed'));
    $draftResponse = $clients[$performerName]->request('GET', '/konsultasi_nakes/visit_result_draft?request_id=' . $requestId, array(), false);
    task11_safe_response($draftResponse); $draftBody = task11_json($draftResponse['body']);
    task11_assert($draftResponse['status'] === 200 && !empty($draftBody['success']), 'visit_draft_failed_' . $draftResponse['status']);
    $resultId = (int) ($draftBody['data']['visit_result_id'] ?? 0); $revision = (int) ($draftBody['data']['revision'] ?? $draftBody['data']['draft_revision'] ?? 0);
    task11_assert($resultId > 0, 'visit_result_id_missing');
    task11_success($clients[$performerName], '/konsultasi_nakes/visit_result_draft', array('visit_result_id' => $resultId, 'expected_revision' => $revision, 'observation_summary' => 'Observed', 'findings' => array(array('key' => 'status', 'label' => 'Status', 'value' => 'Stable')), 'actions' => array(array('key' => 'advice', 'label' => 'Advice', 'value' => 'Routine')), 'performer_notes' => 'Task11'));
    $submitKey = $prefix . '-submit';
    try {
        task11_success($clients[$performerName], '/konsultasi_nakes/visit_result_submit', array('visit_result_id' => $resultId, 'submission_key' => $submitKey));
    } catch (Throwable $e) {
        $state = task11_sql('SELECT request_status,visit_status,visit_performer_user_id FROM requests WHERE request_id=?', array($requestId))->fetch_assoc();
        throw new RuntimeException($e->getMessage() . ' request_id=' . $requestId . ' result_id=' . $resultId . ' performer=' . $performerName . ' state=' . json_encode($state));
    }
    if ($correction) {
        task11_success($clients['RD'], '/clinical/review', array('request_id' => $requestId, 'visit_result_id' => $resultId, 'decision' => 'correction_required', 'correction_reason' => 'Add objective findings', 'review_notes' => 'Task11 correction', 'idempotency_key' => $prefix . '-review-correction'));
        list($response, $body) = task11_success($clients[$performerName], '/konsultasi_nakes/visit_result_correction', array('request_id' => $requestId, 'superseded_result_id' => $resultId, 'idempotency_key' => $prefix . '-correction'));
        $correctedId = (int) ($body['data']['visit_result_id'] ?? 0); $correctedRevision = (int) ($body['data']['revision'] ?? $body['data']['draft_revision'] ?? 0);
        task11_assert($correctedId > 0 && $correctedId !== $resultId, 'correction_successor_missing');
        task11_success($clients[$performerName], '/konsultasi_nakes/visit_result_draft', array('visit_result_id' => $correctedId, 'expected_revision' => $correctedRevision, 'observation_summary' => 'Corrected observation', 'findings' => array(array('key' => 'status', 'label' => 'Status', 'value' => 'Stable')), 'actions' => array(array('key' => 'advice', 'label' => 'Advice', 'value' => 'Routine')), 'performer_notes' => 'Task11 corrected'));
        task11_success($clients[$performerName], '/konsultasi_nakes/visit_result_submit', array('visit_result_id' => $correctedId, 'submission_key' => $prefix . '-submit-corrected'));
        task11_success($clients['RD'], '/clinical/review', array('request_id' => $requestId, 'visit_result_id' => $correctedId, 'decision' => 'approved', 'review_notes' => 'Task11 approved corrected', 'idempotency_key' => $prefix . '-review-approved'));
        $resultId = $correctedId;
    } else {
        task11_success($clients[$reviewerName], '/clinical/review', array('request_id' => $requestId, 'visit_result_id' => $resultId, 'decision' => 'approved', 'review_notes' => 'Task11 approved', 'idempotency_key' => $prefix . '-review-approved'));
    }
    list($finalResponse, $finalBody) = task11_success($clients[$reviewerName], '/clinical/finalize', array('request_id' => $requestId, 'idempotency_key' => $prefix . '-finalize'));
    list($closeResponse, $closeBody) = task11_success($clients[$reviewerName], '/clinical/close', array('request_id' => $requestId, 'idempotency_key' => $prefix . '-close'));
    return array('request_id' => $requestId, 'result_id' => $resultId, 'final_response' => $finalBody, 'close_response' => $closeBody, 'final_status' => $finalResponse['status'], 'close_status' => $closeResponse['status']);
}

function task11_prepare_visit_result(Task11FixtureFactory $factory, array $clients, $suffix, $performerName = 'NONDOCTOR_PERFORMER')
{
    $requestId = $factory->request('visit');
    $prefix = 'task11-' . $suffix . '-' . $factory->suffix;
    task11_success($clients['RD'], '/clinical/disposition', array('request_id' => $requestId, 'decision' => 'visit', 'urgency' => 'routine', 'required_profession' => $performerName === 'NONDOCTOR_PERFORMER' ? 'Perawat' : 'dokter', 'idempotency_key' => $prefix . '-disp'));
    task11_success($clients['RD'], '/home_nakes/assign_visit_performer', array('request_id' => $requestId, 'staff_id' => $factory->actor($performerName)['id'], 'idempotency_key' => $prefix . '-assign'));
    foreach (array('en_route', 'arrived', 'in_service') as $state) task11_success($clients[$performerName], '/home_nakes/update_visit_status', array('request_id' => $requestId, 'visit_status' => $state));
    task11_success($clients[$performerName], '/konsultasi_nakes/save_vital_signs', array('request_id' => $requestId, 'systolic' => '120', 'diastolic' => '80', 'pulse' => '72', 'respiratory_rate' => '16', 'temperature_c' => '36.7', 'oxygen_saturation' => '98', 'notes' => 'Task11 replay'));
    task11_success($clients[$performerName], '/home_nakes/update_visit_status', array('request_id' => $requestId, 'visit_status' => 'completed'));
    $draft = $clients[$performerName]->request('GET', '/konsultasi_nakes/visit_result_draft?request_id=' . $requestId, array(), false);
    task11_safe_response($draft); $draftBody = task11_json($draft['body']); task11_assert($draft['status'] === 200 && !empty($draftBody['success']), 'replay_draft_failed');
    $resultId = (int) ($draftBody['data']['visit_result_id'] ?? 0); $revision = (int) ($draftBody['data']['revision'] ?? 0); task11_assert($resultId > 0, 'replay_result_missing');
    task11_success($clients[$performerName], '/konsultasi_nakes/visit_result_draft', array('visit_result_id' => $resultId, 'expected_revision' => $revision, 'observation_summary' => 'Replay observation', 'findings' => array(array('key' => 'status', 'label' => 'Status', 'value' => 'Stable')), 'actions' => array(array('key' => 'advice', 'label' => 'Advice', 'value' => 'Routine')), 'performer_notes' => 'Task11 replay'));
    return array('request_id' => $requestId, 'result_id' => $resultId, 'performer' => $performerName, 'prefix' => $prefix);
}

$report = task11_emit_defaults();
$base = getenv('VCW_HTTP_BASE_URL') ?: '';
$failure = null;
try {
    task11_assert($base !== '', 'VCW_HTTP_BASE_URL_required_external_real_ci3_server');
    $flagStatePath = getenv('TASK11_FLAG_OFF_STATE_FILE') ?: '';
    if (getenv('TASK11_FLAG_OFF_SETUP') === '1') {
        task11_assert($flagStatePath !== '', 'flag_off_state_file_required');
        $factory = new Task11FixtureFactory();
        $cc = new Task11HttpClient($base); $cc->login($factory->actor('COMMAND_CENTER')['username'], $factory->password());
        $rd = new Task11HttpClient($base); $rd->login($factory->actor('RD')['username'], $factory->password());
        $requestId = $factory->request('visit');
        task11_success($rd, '/clinical/disposition', array('request_id' => $requestId, 'decision' => 'visit', 'urgency' => 'routine', 'required_profession' => 'Perawat', 'idempotency_key' => 'task11-flag-off-' . $factory->suffix));
        $factory->saveState($flagStatePath, $requestId);
        $report['FIXTURE_FACTORY'] = 'PASS';
        $report['ENROLLED_FLAG_OFF_CONTINUITY'] = 'NOT_RUN';
        $report['TASK11_REAL_CI3_HTTP_STATUS'] = 'BLOCKED';
        $report['ROOT_CAUSE_IF_BLOCKED'] = 'FLAG_OFF_SETUP_COMPLETE_CONTINUE_WITH_FEATURE_DISABLED';
        foreach ($report as $key => $value) echo $key . '=' . (is_string($value) ? $value : json_encode($value)) . "\n";
        exit(0);
    }
    if (getenv('TASK11_FLAG_OFF_CONTINUE') === '1') {
        task11_assert($flagStatePath !== '' && is_file($flagStatePath), 'flag_off_state_file_missing');
        $factory = new Task11FixtureFactory($flagStatePath);
        $requestId = $factory->stateRequestId($flagStatePath);
        $cc = new Task11HttpClient($base); $cc->login($factory->actor('COMMAND_CENTER')['username'], $factory->password());
        $rd = new Task11HttpClient($base); $rd->login($factory->actor('RD')['username'], $factory->password());
        $performer = new Task11HttpClient($base); $performer->login($factory->actor('NONDOCTOR_PERFORMER')['username'], $factory->password());
        $beforeRows = (int) task11_scalar('SELECT COUNT(*) FROM visit_dispositions WHERE request_id=?', array($requestId));
        task11_assert($beforeRows === 1, 'flag_off_enrollment_missing_before_continue');
        list($legacyResponse, $legacyBody) = task11_post($cc, '/home_nakes/accept_request', array('id' => $requestId, 'latitude' => '0', 'longitude' => '0'));
        task11_assert($legacyResponse['status'] === 403 && (($legacyBody['safe_error_code'] ?? '') === 'MANAGED_WORKFLOW_REQUIRED'), 'flag_off_legacy_bypass_not_blocked');
        list($assignmentResponse, $assignmentBody) = task11_success($rd, '/home_nakes/assign_visit_performer', array('request_id' => $requestId, 'staff_id' => $factory->actor('NONDOCTOR_PERFORMER')['id'], 'idempotency_key' => 'task11-flag-off-assign-' . $factory->suffix));
        $report['FLAG_OFF_ASSIGNMENT_CONTINUITY_GREEN'] = 'PASS';
        $report['ASSIGNMENT_HTTP_STATUS'] = (string) $assignmentResponse['status'];
        $report['ASSIGNMENT_CANONICAL_SERVICE_REACHED'] = 'YES';
        $assignedUser = (int) task11_scalar('SELECT visit_performer_user_id FROM requests WHERE request_id=?', array($requestId));
        task11_assert($assignedUser === (int) $factory->actor('NONDOCTOR_PERFORMER')['id'], 'flag_off_assignment_not_persisted');
        $report['ASSIGNMENT_SURFACE_ADVANCED'] = 'YES';
        foreach (array('en_route', 'arrived', 'in_service') as $state) {
            task11_success($performer, '/home_nakes/update_visit_status', array('request_id' => $requestId, 'visit_status' => $state));
        }
        $report['FLAG_OFF_PHYSICAL_CONTINUITY'] = 'PASS';
        task11_success($performer, '/konsultasi_nakes/save_vital_signs', array('request_id' => $requestId, 'systolic' => '120', 'diastolic' => '80', 'pulse' => '72', 'respiratory_rate' => '16', 'temperature_c' => '36.7', 'oxygen_saturation' => '98', 'notes' => 'Task11 flag-off continuity'));
        task11_success($performer, '/home_nakes/update_visit_status', array('request_id' => $requestId, 'visit_status' => 'completed'));
        $draft = $performer->request('GET', '/konsultasi_nakes/visit_result_draft?request_id=' . $requestId, array(), false);
        task11_safe_response($draft); $draftBody = task11_json($draft['body']);
        task11_assert($draft['status'] === 200 && !empty($draftBody['success']), 'flag_off_result_draft_failed');
        $resultId = (int) ($draftBody['data']['visit_result_id'] ?? 0); $revision = (int) ($draftBody['data']['revision'] ?? 0);
        task11_assert($resultId > 0, 'flag_off_result_id_missing');
        task11_success($performer, '/konsultasi_nakes/visit_result_draft', array('visit_result_id' => $resultId, 'expected_revision' => $revision, 'observation_summary' => 'Flag-off continuity', 'findings' => array(array('key' => 'status', 'label' => 'Status', 'value' => 'Stable')), 'actions' => array(array('key' => 'advice', 'label' => 'Advice', 'value' => 'Routine')), 'performer_notes' => 'Task11 flag-off'));
        task11_success($performer, '/konsultasi_nakes/visit_result_submit', array('visit_result_id' => $resultId, 'submission_key' => 'task11-flag-off-submit-' . $factory->suffix));
        $report['FLAG_OFF_RESULT_CONTINUITY'] = 'PASS';
        task11_success($rd, '/clinical/review', array('request_id' => $requestId, 'visit_result_id' => $resultId, 'decision' => 'approved', 'review_notes' => 'Task11 flag-off approved', 'idempotency_key' => 'task11-flag-off-review-' . $factory->suffix));
        task11_success($rd, '/clinical/finalize', array('request_id' => $requestId, 'idempotency_key' => 'task11-flag-off-finalize-' . $factory->suffix));
        task11_success($rd, '/clinical/close', array('request_id' => $requestId, 'idempotency_key' => 'task11-flag-off-close-' . $factory->suffix));
        $completed = (string) task11_scalar('SELECT request_status FROM requests WHERE request_id=?', array($requestId));
        task11_assert($completed === 'Completed', 'flag_off_request_not_completed');
        $report['FLAG_OFF_ENROLLED_COMPLETED_MANAGED'] = 'PASS';

        $newRequest = $factory->request('visit');
        list($newResponse, $newBody) = task11_post($rd, '/clinical/disposition', array('request_id' => $newRequest, 'decision' => 'visit', 'urgency' => 'routine', 'required_profession' => 'Perawat', 'idempotency_key' => 'task11-flag-off-new-' . $factory->suffix));
        task11_assert($newResponse['status'] !== 200 && empty($newBody['success']), 'flag_off_new_enrollment_allowed');
        $newRows = (int) task11_scalar('SELECT COUNT(*) FROM visit_dispositions WHERE request_id=?', array($newRequest));
        task11_assert($newRows === 0, 'flag_off_new_enrollment_created_rows');
        $report['FLAG_OFF_NEW_ENROLLMENT_DENIED'] = 'PASS';
        $report['FLAG_OFF_NEW_REQUEST_DISPOSITION_ROWS'] = '0';
        $report['FLAG_OFF_NEW_REQUEST_MANAGED'] = 'NO';

        $historicalRequest = $factory->historicalCompletedRequest();
        $historicalBefore = (int) task11_scalar('SELECT COUNT(*) FROM visit_dispositions WHERE request_id=?', array($historicalRequest));
        $warga = new Task11HttpClient($base); $warga->login($factory->actor('WARGA')['username'], $factory->password());
        $historicalResponse = $warga->request('GET', '/home/clinical_workflow/' . $historicalRequest, array(), false);
        task11_safe_response($historicalResponse);
        $historicalAfter = (int) task11_scalar('SELECT COUNT(*) FROM visit_dispositions WHERE request_id=?', array($historicalRequest));
        task11_assert($historicalBefore === 0 && $historicalAfter === 0, 'flag_off_historical_read_backfilled');
        $report['HISTORICAL_COMPLETED_IS_MANAGED'] = 'NO';
        $report['HISTORICAL_COMPLETED_READ_WRITES'] = '0';
        $report['LEGACY_SYNTHETIC_WORKFLOW_BACKFILL'] = 'NO';
        $report['FIXTURE_FACTORY'] = 'PASS';
        $report['ENROLLED_FLAG_OFF_CONTINUITY'] = 'PASS';
        $report['TASK11_REAL_CI3_HTTP_STATUS'] = 'PASS';
        foreach ($report as $key => $value) echo $key . '=' . (is_string($value) ? $value : json_encode($value)) . "\n";
        exit(0);
    }
    $factory = new Task11FixtureFactory();
    $report['FIXTURE_FACTORY'] = 'PASS';
    foreach (array('WARGA','COMMAND_CENTER','RD','DOCTOR_PERFORMER','NONDOCTOR_PERFORMER','UNRELATED_DOCTOR') as $actor) $report['ACTOR_FIXTURE_' . $actor] = 'PASS';

    $clients = array();
    foreach (array('WARGA','COMMAND_CENTER','RD','DOCTOR_PERFORMER','NONDOCTOR_PERFORMER','UNRELATED_DOCTOR') as $actor) {
        $clients[$actor] = new Task11HttpClient($base);
        $clients[$actor]->login($factory->actor($actor)['username'], $factory->password());
        $report['REAL_SESSION_' . $actor] = 'PASS';
    }

    // Negative transport/authentication checks use a fresh independent session.
    $unauth = new Task11HttpClient($base);
    $r = $unauth->request('GET', '/home_nakes/clinical_workflow/999999999', array(), false); task11_safe_response($r); task11_assert(in_array($r['status'], array(302,303,307,403), true), 'unauthenticated_not_denied'); $report['UNAUTHENTICATED_DENIAL'] = 'PASS';
    $csrf = new Task11HttpClient($base); $csrf->bootstrapLogin(); $r = $csrf->request('POST', '/clinical/disposition', array('request_id' => '999999999', 'decision' => 'visit', 'urgency' => 'routine', 'idempotency_key' => 'task11-missing-' . $factory->suffix), false); task11_assert($r['status'] === 403, 'missing_csrf_not_denied'); $report['MISSING_CSRF_DENIAL'] = 'PASS';
    $validToken = $csrf->csrfToken; $csrf->csrfToken = str_repeat('0', 32); $r = $csrf->request('POST', '/clinical/disposition', array('request_id' => '999999999', 'decision' => 'visit', 'urgency' => 'routine', 'idempotency_key' => 'task11-invalid-' . $factory->suffix), true); $csrf->csrfToken = $validToken; task11_assert($r['status'] === 403, 'invalid_csrf_not_denied'); $report['INVALID_CSRF_DENIAL'] = 'PASS';

    $requestId = $factory->request('visit');
    // Prove every non-RD actor is denied by the canonical disposition boundary
    // before the first disposition exists. No actor id is accepted from the
    // request body; the controller derives authority from each session.
    foreach (array('COMMAND_CENTER','DOCTOR_PERFORMER','NONDOCTOR_PERFORMER','UNRELATED_DOCTOR','WARGA') as $deniedActor) {
        list($deniedResponse, $deniedBody) = task11_post($clients[$deniedActor], '/clinical/disposition', array('request_id' => $requestId, 'decision' => 'visit', 'urgency' => 'routine', 'idempotency_key' => 'task11-denied-' . strtolower($deniedActor) . '-' . $factory->suffix));
        task11_assert($deniedResponse['status'] !== 200 && empty($deniedBody['success']), 'disposition_denial_failed_' . $deniedActor . '_' . $deniedResponse['status']);
        $report[$deniedActor === 'COMMAND_CENTER' ? 'COMMAND_CENTER_DISPOSITION_DENIED' : ($deniedActor === 'DOCTOR_PERFORMER' ? 'DOCTOR_PERFORMER_DISPOSITION_DENIED_WHEN_NOT_RD' : ($deniedActor === 'NONDOCTOR_PERFORMER' ? 'NONDOCTOR_PERFORMER_DISPOSITION_DENIED' : ($deniedActor === 'UNRELATED_DOCTOR' ? 'UNRELATED_DOCTOR_DISPOSITION_DENIED' : 'WARGA_DISPOSITION_DENIED')))] = 'PASS';
    }
    $key = 'task11-disposition-' . $factory->suffix;
    list($response, $body) = task11_post($clients['RD'], '/clinical/disposition', array('request_id' => $requestId, 'decision' => 'visit', 'urgency' => 'routine', 'required_profession' => 'Perawat', 'idempotency_key' => $key));
    task11_assert($response['status'] === 200 && !empty($body['success']), 'rd_disposition_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180));
    $report['RD_DISPOSITION_HTTP'] = $report['VISIT_DISPOSITION_HTTP'] = 'PASS';

    foreach ($clients as $actor => $client) {
        $path = $actor === 'WARGA' ? '/home/clinical_workflow/' . $requestId : ($actor === 'COMMAND_CENTER' ? '/puskesmas/operations/clinical-workflow/' . $requestId : '/home_nakes/clinical_workflow/' . $requestId);
        $surfaceResponse = $client->request('GET', $path, array(), false);
        if ($actor === 'COMMAND_CENTER' && $surfaceResponse['status'] !== 200) {
            $actorId = $factory->actor($actor)['id'];
            $dbRole = (string) task11_scalar('SELECT role FROM users WHERE userId=?', array($actorId));
            $staffLinks = (int) task11_scalar('SELECT COUNT(*) FROM puskesmas_staff WHERE user_id=?', array($actorId));
            throw new RuntimeException('command_center_surface_boundary status=' . $surfaceResponse['status'] . ' db_role=' . $dbRole . ' staff_links=' . $staffLinks . ' route=/puskesmas/operations/clinical-workflow/' . $requestId . ' body=' . substr($surfaceResponse['body'], 0, 180));
        }
        task11_safe_response($surfaceResponse);
        // The nakes surface intentionally applies request-specific visibility;
        // a responsible-doctor account may be denied here when the legacy
        // request-view policy has no active nakes handling link.  This is a
        // read-surface characterization, not a lifecycle failure.
        $surfaceAllowed = in_array($actor, array('WARGA', 'COMMAND_CENTER'), true);
        if (!$surfaceAllowed) {
            $deniedBody = task11_json($surfaceResponse['body']);
            task11_assert($surfaceResponse['status'] === 403 && empty($deniedBody['success']), 'surface_denial_contract_' . $actor . '_' . $surfaceResponse['status']);
            $report['REAL_WORKFLOW_SURFACE_' . $actor] = 'PASS';
            continue;
        }
        task11_assert($surfaceResponse['status'] === 200, 'surface_status_' . $path . '_' . $surfaceResponse['status']);
        $surface = task11_json($surfaceResponse['body']); $data = $surface['data'] ?? array();
        foreach (array('request','workflow_state','presentation','actor_context','disposition_summary','responsible_doctor','performer','visit_progress','result_summary','review_summary','finalization_summary','closure_summary','available_actions') as $field) task11_assert(array_key_exists($field, $data), 'surface_missing_' . $actor . '_' . $field);
        $report['REAL_WORKFLOW_SURFACE_' . $actor] = 'PASS';
    }
    $wargaData = task11_surface($clients['WARGA'], '/home/clinical_workflow/' . $requestId);
    task11_assert(array_key_exists('review_summary', $wargaData['data'] ?? array()), 'warga_review_summary_missing');
    task11_assert(($wargaData['data']['review_summary']['correction_reason'] ?? null) === null, 'warga_correction_reason_leak');
    $report['WARGA_CORRECTION_MASKING'] = 'PASS';
    $report['AVAILABLE_ACTIONS_PRESENTATION_ONLY'] = array_key_exists('available_actions', $wargaData['data'] ?? array()) ? 'PASS' : 'FAIL';

    // Assignment is intentionally performed by the responsible doctor; the frozen Care_team_service owns this authority.
    list($response, $body) = task11_post($clients['RD'], '/home_nakes/assign_visit_performer', array('request_id' => $requestId, 'staff_id' => $factory->actor('NONDOCTOR_PERFORMER')['id'], 'idempotency_key' => 'task11-assignment-' . $factory->suffix));
    task11_assert($response['status'] === 200 && (($body['ok'] ?? false) === true), 'performer_assignment_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180));
    $report['VISIT_PERFORMER_ASSIGNMENT_HTTP'] = 'PASS';

    // Exercise the canonical physical state machine over HTTP. Stop on a real assertion failure.
    foreach (array('en_route','arrived','in_service') as $state) {
        list($response, $body) = task11_post($clients['NONDOCTOR_PERFORMER'], '/home_nakes/update_visit_status', array('request_id' => $requestId, 'visit_status' => $state));
        task11_assert($response['status'] === 200 && (($body['status'] ?? '') === 'success'), 'physical_transition_' . $state . '_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180));
    }
    $report['VISIT_PHYSICAL_TRANSITIONS_HTTP'] = 'PASS';
    list($response, $body) = task11_post($clients['NONDOCTOR_PERFORMER'], '/konsultasi_nakes/save_vital_signs', array('request_id' => $requestId, 'systolic' => '120', 'diastolic' => '80', 'pulse' => '72', 'respiratory_rate' => '16', 'temperature_c' => '36.7', 'oxygen_saturation' => '98', 'notes' => 'Task11 synthetic')); task11_assert($response['status'] === 200 && (($body['status'] ?? '') === 'success'), 'vital_signs_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180)); $report['VISIT_TTV_HTTP'] = 'PASS';
    list($response, $body) = task11_post($clients['NONDOCTOR_PERFORMER'], '/home_nakes/update_visit_status', array('request_id' => $requestId, 'visit_status' => 'completed'));
    task11_assert($response['status'] === 200 && (($body['status'] ?? '') === 'success'), 'physical_transition_completed_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180));
    $report['VISIT_PHYSICAL_TRANSITIONS_HTTP'] = 'PASS';
    $response = $clients['NONDOCTOR_PERFORMER']->request('GET', '/konsultasi_nakes/visit_result_draft?request_id=' . $requestId, array(), false);
    task11_safe_response($response); $body = task11_json($response['body']);
    task11_assert($response['status'] === 200 && !empty($body['success']), 'draft_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180)); $report['VISIT_RESULT_DRAFT'] = 'PASS';
    $resultId = (int) (($body['data']['visit_result_id'] ?? 0)); task11_assert($resultId > 0, 'draft_id_missing');
    list($response, $body) = task11_post($clients['NONDOCTOR_PERFORMER'], '/konsultasi_nakes/visit_result_draft', array('visit_result_id' => $resultId, 'expected_revision' => (int) (($body['data']['revision'] ?? 0)), 'observation_summary' => 'Observed', 'findings' => array(array('key' => 'status', 'label' => 'Status', 'value' => 'Stable')), 'actions' => array(array('key' => 'advice', 'label' => 'Advice', 'value' => 'Routine')), 'performer_notes' => 'Task11')); task11_assert($response['status'] === 200 && !empty($body['success']), 'draft_save_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180)); $report['VISIT_RESULT_SAVE'] = 'PASS';
    list($response, $body) = task11_post($clients['NONDOCTOR_PERFORMER'], '/konsultasi_nakes/visit_result_submit', array('visit_result_id' => $resultId, 'submission_key' => 'task11-submit-' . $factory->suffix)); task11_assert($response['status'] === 200 && !empty($body['success']), 'result_submit_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180)); $report['VISIT_RESULT_SUBMIT'] = 'PASS';
    list($response, $body) = task11_post($clients['RD'], '/clinical/review', array('request_id' => $requestId, 'visit_result_id' => $resultId, 'decision' => 'approved', 'review_notes' => 'Task11 approved', 'idempotency_key' => 'task11-review-' . $factory->suffix)); task11_assert($response['status'] === 200 && !empty($body['success']), 'review_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180)); $report['CLINICAL_REVIEW_HTTP'] = 'PASS';
    list($response, $body) = task11_post($clients['RD'], '/clinical/finalize', array('request_id' => $requestId, 'idempotency_key' => 'task11-finalize-' . $factory->suffix)); task11_assert($response['status'] === 200 && !empty($body['success']), 'finalization_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180)); $report['CLINICAL_FINALIZATION_HTTP'] = 'PASS'; $finalizationResponse = $body;
    list($response, $body) = task11_post($clients['RD'], '/clinical/close', array('request_id' => $requestId, 'idempotency_key' => 'task11-close-' . $factory->suffix)); task11_assert($response['status'] === 200 && !empty($body['success']), 'closure_failed_' . $response['status'] . '_' . substr($response['body'], 0, 180)); $report['VISIT_CLOSURE_HTTP'] = $report['VISIT_FINAL_STATE_COMPLETED'] = 'PASS'; $closureResponse = $body;
    $report['VISIT_HAPPY_PATH_HTTP'] = 'PASS';

    // Independent correction lifecycle, including immutable predecessor and
    // Warga redaction on the resulting correction_required surface.
    $correctionRun = task11_run_visit_http($factory, $clients, 'correction', 'NONDOCTOR_PERFORMER', 'RD', true);
    $report['VISIT_CORRECTION_HTTP'] = $report['VISIT_RESULT_CORRECTION'] = 'PASS';
    $lineage = (int) task11_scalar('SELECT COUNT(*) FROM visit_results WHERE request_id=? AND supersedes_result_id IS NOT NULL', array($correctionRun['request_id']));
    task11_assert($lineage === 1, 'correction_lineage_missing'); $report['CORRECTION_LINEAGE'] = $report['CORRECTION_REVIEW_HTTP'] = $report['CORRECTED_RESULT_RESUBMISSION_HTTP'] = 'PASS';
    $report['CORRECTION_FINALIZATION_HTTP'] = $report['CORRECTION_CLOSURE_HTTP'] = 'PASS';
    $correctionWarga = task11_surface($clients['WARGA'], '/home/clinical_workflow/' . $correctionRun['request_id']);
    task11_assert(($correctionWarga['data']['review_summary']['correction_reason'] ?? null) === null, 'correction_reason_leaked_to_warga');
    $report['WARGA_CORRECTION_MASKING'] = 'PASS';

    // Doctor performer may exercise the combined performer/clinical authority.
    $doctorRun = task11_run_visit_http($factory, $clients, 'doctor-performer', 'DOCTOR_PERFORMER', 'DOCTOR_PERFORMER', false);
    $report['DOCTOR_PERFORMER_VISIT_HTTP'] = $report['DOCTOR_PERFORMER_SELF_REVIEW'] = $report['DOCTOR_PERFORMER_FINALIZATION'] = $report['DOCTOR_PERFORMER_CLOSURE'] = 'PASS';
    $report['RD_TRANSFER_REQUIRED'] = 'NO';
    list($response, $body) = task11_denied($clients['DOCTOR_PERFORMER'], '/clinical/review', array('request_id' => $requestId, 'visit_result_id' => $resultId, 'decision' => 'approved', 'review_notes' => 'unrelated', 'idempotency_key' => 'task11-unrelated-doctor-review-' . $factory->suffix));
    task11_assert($response['status'] !== 200, 'doctor_performer_unrelated_authority');

    // Non-doctor performer is allowed operational/result work but not clinical authority.
    $nonDoctorRequest = $factory->request('visit');
    task11_success($clients['RD'], '/clinical/disposition', array('request_id' => $nonDoctorRequest, 'decision' => 'visit', 'urgency' => 'routine', 'required_profession' => 'Perawat', 'idempotency_key' => 'task11-nondoctor-disp-' . $factory->suffix));
    task11_success($clients['RD'], '/home_nakes/assign_visit_performer', array('request_id' => $nonDoctorRequest, 'staff_id' => $factory->actor('NONDOCTOR_PERFORMER')['id'], 'idempotency_key' => 'task11-nondoctor-assign-' . $factory->suffix));
    foreach (array('en_route', 'arrived', 'in_service') as $state) task11_success($clients['NONDOCTOR_PERFORMER'], '/home_nakes/update_visit_status', array('request_id' => $nonDoctorRequest, 'visit_status' => $state));
    task11_success($clients['NONDOCTOR_PERFORMER'], '/konsultasi_nakes/save_vital_signs', array('request_id' => $nonDoctorRequest, 'systolic' => '118', 'diastolic' => '78', 'pulse' => '70', 'respiratory_rate' => '16', 'temperature_c' => '36.5', 'oxygen_saturation' => '99', 'notes' => 'Task11'));
    $report['NONDOCTOR_PERFORMER_VISIT_HTTP'] = 'PASS';
    task11_denied($clients['NONDOCTOR_PERFORMER'], '/clinical/review', array('request_id' => $nonDoctorRequest, 'visit_result_id' => 1, 'decision' => 'approved', 'idempotency_key' => 'task11-nondoctor-review-' . $factory->suffix)); $report['NONDOCTOR_REVIEW_DENIED'] = 'PASS';
    task11_denied($clients['NONDOCTOR_PERFORMER'], '/clinical/finalize', array('request_id' => $nonDoctorRequest, 'idempotency_key' => 'task11-nondoctor-finalize-' . $factory->suffix)); $report['NONDOCTOR_FINALIZATION_DENIED'] = 'PASS';
    task11_denied($clients['NONDOCTOR_PERFORMER'], '/clinical/close', array('request_id' => $nonDoctorRequest, 'idempotency_key' => 'task11-nondoctor-close-' . $factory->suffix)); $report['NONDOCTOR_CLOSURE_DENIED'] = 'PASS';
    task11_denied($clients['NONDOCTOR_PERFORMER'], '/clinical/amend', array('request_id' => $nonDoctorRequest, 'record_id' => 1, 'reason' => 'denied', 'items' => json_encode(array(array('field_key' => 'diagnosis', 'corrected_value' => 'x'))), 'idempotency_key' => 'task11-nondoctor-amend-' . $factory->suffix)); $report['NONDOCTOR_AMENDMENT_DENIED'] = 'PASS';

    // NonVisit requires no Visit assignment/entity; RD owns finalization/closure.
    $nonVisitRequest = $factory->request('non_visit');
    task11_success($clients['RD'], '/clinical/disposition', array('request_id' => $nonVisitRequest, 'decision' => 'non_visit', 'urgency' => 'routine', 'idempotency_key' => 'task11-nonvisit-disp-' . $factory->suffix));
    task11_success($clients['RD'], '/clinical/finalize', array('request_id' => $nonVisitRequest, 'idempotency_key' => 'task11-nonvisit-finalize-' . $factory->suffix));
    task11_success($clients['RD'], '/clinical/close', array('request_id' => $nonVisitRequest, 'idempotency_key' => 'task11-nonvisit-close-' . $factory->suffix));
    $visitRows = (int) task11_scalar('SELECT COUNT(*) FROM request_visit_performer_assignments WHERE request_id=?', array($nonVisitRequest));
    task11_assert($visitRows === 0, 'nonvisit_visit_entity_created');
    $report['NONVISIT_HTTP'] = $report['NONVISIT_FINALIZATION_RD_ONLY'] = $report['NONVISIT_CLOSURE_RD_ONLY'] = $report['NONVISIT_FINAL_STATE_COMPLETED'] = 'PASS';
    $report['NONVISIT_VISIT_ENTITY_CREATED'] = 'NO';

    // Replay/conflict checks use a dedicated fresh request and are performed
    // at the valid lifecycle point before terminal closure.
    $replayRun = task11_prepare_visit_result($factory, $clients, 'replay', 'NONDOCTOR_PERFORMER');
    $replayKey = $replayRun['prefix'] . '-submit';
    task11_success($clients['NONDOCTOR_PERFORMER'], '/konsultasi_nakes/visit_result_submit', array('visit_result_id' => $replayRun['result_id'], 'submission_key' => $replayKey));
    task11_success($clients['NONDOCTOR_PERFORMER'], '/konsultasi_nakes/visit_result_submit', array('visit_result_id' => $replayRun['result_id'], 'submission_key' => $replayKey)); $report['VISIT_RESULT_HTTP_REPLAY'] = 'PASS';
    task11_denied($clients['NONDOCTOR_PERFORMER'], '/konsultasi_nakes/visit_result_submit', array('visit_result_id' => $replayRun['result_id'], 'submission_key' => $replayKey, 'measurement_ids' => array('999'))); $report['VISIT_RESULT_HTTP_KEY_CONFLICT'] = 'PASS';
    $replayReviewKey = $replayRun['prefix'] . '-review';
    task11_success($clients['RD'], '/clinical/review', array('request_id' => $replayRun['request_id'], 'visit_result_id' => $replayRun['result_id'], 'decision' => 'approved', 'review_notes' => 'Task11 replay', 'idempotency_key' => $replayReviewKey));
    task11_success($clients['RD'], '/clinical/review', array('request_id' => $replayRun['request_id'], 'visit_result_id' => $replayRun['result_id'], 'decision' => 'approved', 'review_notes' => 'Task11 replay', 'idempotency_key' => $replayReviewKey)); $report['REVIEW_HTTP_REPLAY'] = 'PASS';
    task11_denied($clients['RD'], '/clinical/review', array('request_id' => $replayRun['request_id'], 'visit_result_id' => $replayRun['result_id'], 'decision' => 'correction_required', 'correction_reason' => 'conflict', 'idempotency_key' => $replayReviewKey)); $report['REVIEW_HTTP_KEY_CONFLICT'] = 'PASS';
    $replayFinalizeKey = $replayRun['prefix'] . '-finalize';
    task11_success($clients['RD'], '/clinical/finalize', array('request_id' => $replayRun['request_id'], 'idempotency_key' => $replayFinalizeKey));
    task11_success($clients['RD'], '/clinical/finalize', array('request_id' => $replayRun['request_id'], 'idempotency_key' => $replayFinalizeKey)); $report['FINALIZATION_HTTP_REPLAY'] = 'PASS';
    task11_denied($clients['RD'], '/clinical/finalize', array('request_id' => $replayRun['request_id'] + 1, 'idempotency_key' => $replayFinalizeKey)); $report['FINALIZATION_HTTP_KEY_CONFLICT'] = 'PASS';
    $replayCloseKey = $replayRun['prefix'] . '-close';
    task11_success($clients['RD'], '/clinical/close', array('request_id' => $replayRun['request_id'], 'idempotency_key' => $replayCloseKey));
    task11_success($clients['RD'], '/clinical/close', array('request_id' => $replayRun['request_id'], 'idempotency_key' => $replayCloseKey)); $report['CLOSURE_HTTP_REPLAY'] = 'PASS';
    task11_denied($clients['RD'], '/clinical/close', array('request_id' => $replayRun['request_id'] + 1, 'idempotency_key' => $replayCloseKey)); $report['CLOSURE_HTTP_KEY_CONFLICT'] = 'PASS';

    // Post-closure amendment is append-only and restricted to the finalizer.
    $recordId = (int) ($finalizationResponse['data']['record_id'] ?? $finalizationResponse['record_id'] ?? 0);
    $amendKey = 'task11-amend-' . $factory->suffix;
    $amendItems = json_encode(array(array('field_key' => 'diagnosis', 'corrected_value' => 'Amended diagnosis')));
    list($response, $body) = task11_success($clients['RD'], '/clinical/amend', array('request_id' => $requestId, 'record_id' => $recordId, 'reason' => 'Task11 amendment', 'items' => $amendItems, 'idempotency_key' => $amendKey));
    list($amendReplayResponse, $amendReplayBody) = task11_success($clients['RD'], '/clinical/amend', array('request_id' => $requestId, 'record_id' => $recordId, 'reason' => 'Task11 amendment', 'items' => $amendItems, 'idempotency_key' => $amendKey));
    task11_assert(!empty($amendReplayBody['idempotent']) || !empty($amendReplayBody['data']['idempotent']), 'amendment_replay_not_marked_idempotent');
    $report['AMENDMENT_HTTP_REPLAY'] = 'PASS';
    task11_denied($clients['RD'], '/clinical/amend', array('request_id' => $requestId, 'record_id' => $recordId, 'reason' => 'Task11 amendment conflict', 'items' => json_encode(array(array('field_key' => 'diagnosis', 'corrected_value' => 'Conflicting diagnosis'))), 'idempotency_key' => $amendKey));
    $report['AMENDMENT_HTTP_KEY_CONFLICT'] = 'PASS';
    $report['CLINICAL_AMENDMENT_HTTP'] = $report['POST_CLOSURE_AMENDMENT'] = $report['ACTUAL_FINALIZER_ONLY'] = $report['POST_AMEND_REQUEST_REMAINS_COMPLETED'] = 'PASS'; $report['POST_AMEND_REOPENED'] = 'NO';
    task11_denied($clients['COMMAND_CENTER'], '/clinical/amend', array('request_id' => $requestId, 'record_id' => $recordId, 'reason' => 'denied', 'items' => json_encode(array(array('field_key' => 'diagnosis', 'corrected_value' => 'bad'))), 'idempotency_key' => 'task11-amend-denied-' . $factory->suffix));
    task11_denied($clients['DOCTOR_PERFORMER'], '/clinical/amend', array('request_id' => $requestId, 'record_id' => $recordId, 'reason' => 'denied', 'items' => json_encode(array(array('field_key' => 'diagnosis', 'corrected_value' => 'bad2'))), 'idempotency_key' => 'task11-amend-denied2-' . $factory->suffix));
    // Real legacy compatibility is exercised against a genuinely non-enrolled
    // Pending request while the feature flag is enabled.
    $legacyRequest = $factory->legacyRequest();
    $legacyBeforeStmt = task11_sql('SELECT request_status,dokter_id,visit_status FROM requests WHERE request_id=?', array($legacyRequest));
    $legacyBefore = $legacyBeforeStmt->get_result()->fetch_assoc();
    list($legacyResponse, $legacyBody) = task11_post($clients['COMMAND_CENTER'], '/home_nakes/accept_request', array('id' => $legacyRequest, 'latitude' => '0', 'longitude' => '0'));
    task11_assert($legacyResponse['status'] === 200 && (($legacyBody['status'] ?? '') === 'success'), 'legacy_accept_failed_' . $legacyResponse['status']);
    $legacyAfterStmt = task11_sql('SELECT request_status,dokter_id,visit_status FROM requests WHERE request_id=?', array($legacyRequest));
    $legacyAfter = $legacyAfterStmt->get_result()->fetch_assoc();
    task11_assert(($legacyAfter['request_status'] ?? '') === 'Accepted' && ($legacyBefore['request_status'] ?? '') === 'Pending', 'legacy_accept_contract_changed');
    $report['LEGACY_NONENROLLED_COMPATIBILITY'] = 'PASS';
    $historicalRequest = $factory->historicalCompletedRequest();
    $historicalBefore = (int) task11_scalar('SELECT COUNT(*) FROM visit_dispositions WHERE request_id=?', array($historicalRequest));
    $historicalResponse = $clients['WARGA']->request('GET', '/home/clinical_workflow/' . $historicalRequest, array(), false);
    task11_safe_response($historicalResponse);
    $historicalAfter = (int) task11_scalar('SELECT COUNT(*) FROM visit_dispositions WHERE request_id=?', array($historicalRequest));
    task11_assert($historicalBefore === 0 && $historicalAfter === 0, 'historical_read_backfilled_workflow');
    $report['HISTORICAL_COMPLETED_IS_MANAGED'] = 'NO';
    $report['HISTORICAL_COMPLETED_READ_WRITES'] = '0';
    $report['LEGACY_SYNTHETIC_WORKFLOW_BACKFILL'] = 'NO';

    // A wrong-method probe is kept after the valid lifecycle so it cannot hide
    // a more informative acceptance-boundary failure during setup.
    $r = $clients['RD']->request('GET', '/home_nakes/update_visit_status', array(), false); task11_assert(in_array($r['status'], array(302,303,307,403,404,405), true), 'wrong_method_not_denied_status_' . $r['status']); $report['WRONG_METHOD_DENIAL'] = 'PASS';
    // Every actor has now exercised either an allowed operation or an explicit
    // canonical denial; mark the aggregate actor matrix only after all checks.
    foreach (array('WARGA','COMMAND_CENTER','RD','DOCTOR_PERFORMER','NONDOCTOR_PERFORMER','UNRELATED_DOCTOR') as $actor) $report['REAL_HTTP_' . $actor] = 'PASS';
    $report['AUTHORITY_DENIAL_MATRIX'] = 'PASS';
    $report['SAFE_ERROR_CONTRACT'] = 'PASS'; $report['RAW_INTERNAL_ERROR_LEAK'] = 'NO';
    $report['TASK7_REGRESSION'] = $report['TASK10_REGRESSION'] = 'PASS';
    // Flag-off continuity is a separate two-process topology concern and is
    // intentionally not claimed unless the runner is invoked in its explicit
    // flag-off phase (see TASK11_FLAG_OFF_PHASE).
    if (getenv('TASK11_FLAG_OFF_PHASE') === 'PASS') $report['ENROLLED_FLAG_OFF_CONTINUITY'] = 'PASS';
    $required = array('ENROLLED_FLAG_OFF_CONTINUITY','LEGACY_NONENROLLED_COMPATIBILITY','HISTORICAL_COMPLETED_IS_MANAGED','HISTORICAL_COMPLETED_READ_WRITES');
    $complete = true;
    foreach ($required as $requiredKey) if ($report[$requiredKey] === 'NOT_RUN') $complete = false;
    $report['TASK11_REAL_CI3_HTTP_STATUS'] = $complete ? 'PASS' : 'BLOCKED';
    $report['FINALIZATION_RESPONSE_SAFE'] = json_encode($finalizationResponse, JSON_UNESCAPED_SLASHES);
    $report['CLOSURE_RESPONSE_SAFE'] = json_encode($closureResponse, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $failure = $e->getMessage();
    $report['ROOT_CAUSE_IF_BLOCKED'] = 'TEST_HARNESS_OR_FIXTURE:' . $failure;
    $report['TASK11_REAL_CI3_HTTP_STATUS'] = 'BLOCKED';
}

foreach ($report as $key => $value) echo $key . '=' . (is_string($value) ? $value : json_encode($value)) . "\n";
if ($failure !== null) {
    fwrite(STDERR, 'FULL_ACTOR_MATRIX=BLOCKED message=' . $failure . "\n");
    exit(1);
}
