<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);

vcw_assert_true((bool) $db->table_exists('visit_results'), 'Task 7B real schema bootstrap must reach visit_results');
vcw_assert_true((bool) $db->table_exists('request_visit_performer_assignments'), 'Task 7B real schema bootstrap must reach performer assignments');

$modelFile = APPPATH . 'models/Visit_result_m.php';
$serviceFile = APPPATH . 'libraries/Visit_result_service.php';
vcw_assert_true(is_file($modelFile) && is_file($serviceFile), 'Task 7B production API missing: Visit_result_m and Visit_result_service');
require_once $modelFile;
require_once $serviceFile;

function r7b_user($db, $userId, $facility, $staffId, $profession)
{
    $db->query(
        'INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)',
        array($userId, 'Result ' . $userId, 'result-' . $userId . '@example.invalid', 'result-' . $userId, 'x', 'dokter', 'aktif', 0, $facility)
    );
    if ($staffId > 0) {
        $db->query(
            'INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)',
            array($staffId, $facility, $userId, 'Result Staff ' . $userId, $profession, 'aktif')
        );
        $db->query(
            'INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)',
            array($staffId, $facility, '2026-01-01 00:00:00', 'active', $userId)
        );
    }
}

function r7b_request($db, array $f)
{
    vcw_assert_safe_request_id($f['request']);
    $db->query(
        'INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
        array($f['request'], $f['doctor'], 'synthetic', $f['request_status'] ?? 'Accepted', $f['facility'], 'Result Facility', $f['visit_status'], $f['mode'] ?? 'visit', $f['doctor'], $f['projection'] ?? $f['performer'])
    );
    $db->query(
        'INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))',
        array($f['request'], $f['doctor_staff'], $f['doctor'], $f['doctor'], 'aktif')
    );
    if (!empty($f['enrolled'])) {
        $db->query(
            'INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))',
            array($f['request'], 1, $f['decision'] ?? 'visit', ($f['decision'] ?? 'visit') === 'visit' ? 'routine' : null, $f['doctor'], 'result-disposition-' . $f['request'])
        );
    }
    if (!empty($f['prior'])) {
        $db->query(
            'INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,ended_at) VALUES (?,?,?,?,?,DATE_SUB(NOW(6),INTERVAL 2 SECOND),DATE_SUB(NOW(6),INTERVAL 1 SECOND))',
            array($f['request'], $f['prior'][1], $f['prior'][0], $f['command'], 'diganti')
        );
    }
    if (empty($f['omit_assignment'])) {
        $active = !isset($f['assignment_active']) || $f['assignment_active'];
        $db->query(
            'INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,ended_at) VALUES (?,?,?,?,?,NOW(6),?)',
            array($f['request'], $f['performer_staff'], $f['performer'], $f['command'], $active ? 'aktif' : 'dibatalkan', $active ? null : date('Y-m-d H:i:s'))
        );
        return (int) $db->insert_id();
    }
    return 0;
}

function r7b_result_count($db, $requestId)
{
    return (int) $db->where('request_id', (int) $requestId)->count_all_results('visit_results');
}

function r7b_expect_code(array $result, $code, $message)
{
    vcw_assert_same($code, $result['safe_error_code'] ?? null, $message);
}

function r7b_payload($suffix = 'one')
{
    return array(
        'observation_summary' => '  Observasi ' . $suffix . '  ',
        'findings' => array(array('key' => 'skin_color', 'label' => 'Warna kulit', 'value' => ' Normal ')),
        'actions' => array(array('key' => 'positioning', 'label' => 'Posisi', 'value' => 2)),
        'performer_notes' => '  Catatan ' . $suffix . '  ',
    );
}

function r7b_expect_payload_denied($service, $db, $resultId, $actor, array $payload, $message)
{
    $before = $db->where('visit_result_id', $resultId)->get('visit_results')->row_array();
    $failure = $service->saveDraft($resultId, $actor, (int) $before['draft_revision'], $payload);
    r7b_expect_code($failure, 'INVALID_RESULT_PAYLOAD', $message . ' stable code');
    $after = $db->where('visit_result_id', $resultId)->get('visit_results')->row_array();
    vcw_assert_same($before, $after, $message . ' leaves draft unchanged');
}

$baseRow = $db->query('SELECT GREATEST(COALESCE((SELECT MAX(userId) FROM users),0),COALESCE((SELECT MAX(staff_id) FROM puskesmas_staff),0),COALESCE((SELECT MAX(request_id) FROM requests),0)) AS base_id')->row();
$base = (int) $baseRow->base_id + 2000;
$facilityA = 'RESULT-A-' . $base;
$facilityB = 'RESULT-B-' . $base;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?),(?,?,?)', array($facilityA, 'Result Facility A', 'aktif', $facilityB, 'Result Facility B', 'aktif'));

$command = $base + 1;
$doctor = $base + 2;
$performer = $base + 3;
$replacement = $base + 4;
$unrelated = $base + 5;
$cross = $base + 6;
$doctorPerformer = $base + 7;
r7b_user($db, $command, $facilityA, 0, 'Perawat');
r7b_user($db, $doctor, $facilityA, $doctor, 'dokter');
r7b_user($db, $performer, $facilityA, $performer, 'Perawat');
r7b_user($db, $replacement, $facilityA, $replacement, 'Bidan');
r7b_user($db, $unrelated, $facilityA, $unrelated, 'Perawat');
r7b_user($db, $cross, $facilityB, $cross, 'Perawat');
r7b_user($db, $doctorPerformer, $facilityA, $doctorPerformer, 'dokter');

$service = new Visit_result_service($db);
$request = $base + 100;
$defaults = array('facility' => $facilityA, 'doctor' => $doctor, 'doctor_staff' => $doctor, 'performer' => $performer, 'performer_staff' => $performer, 'command' => $command, 'enrolled' => true);

$stateResults = array();
foreach (array('arrived', 'in_service', 'completed') as $offset => $state) {
    $requestId = $request + $offset;
    $assignmentId = r7b_request($db, array_merge($defaults, array('request' => $requestId, 'visit_status' => $state)));
    $created = $service->getOrCreateDraft($requestId, $performer);
    vcw_assert_same('success', $created['status'] ?? null, 'draft allowed at ' . $state . ' code=' . ($created['safe_error_code'] ?? 'none'));
    $row = $db->where('visit_result_id', (int) $created['visit_result_id'])->get('visit_results')->row();
    vcw_assert_true($row && (int) $row->request_id === $requestId && (int) $row->visit_assignment_id === $assignmentId, 'draft canonical request/assignment at ' . $state);
    vcw_assert_true((int) $row->version_no === 1 && (string) $row->status === 'draft' && (int) $row->draft_revision === 0 && $row->supersedes_result_id === null, 'draft version defaults at ' . $state);
    vcw_assert_true((int) $row->performer_user_id === $performer && (int) $row->performer_staff_id === $performer, 'draft performer attribution at ' . $state);
    vcw_assert_true($row->observation_summary === null && $row->performer_notes === null && $row->findings_json === '[]' && $row->actions_json === '[]', 'draft payload defaults at ' . $state);
    vcw_assert_true($row->submitted_at === null && $row->submitted_by_user_id === null && $row->submission_key === null, 'draft submission fields untouched at ' . $state);
    $stateResults[$state] = $created;
}

foreach (array('not_started', 'en_route') as $offset => $state) {
    $requestId = $request + 10 + $offset;
    r7b_request($db, array_merge($defaults, array('request' => $requestId, 'visit_status' => $state)));
    r7b_expect_code($service->getOrCreateDraft($requestId, $performer), 'INVALID_WORKFLOW_STATE', 'draft denied at ' . $state);
    vcw_assert_same(0, r7b_result_count($db, $requestId), 'no draft at ' . $state);
}

$notAccepted = $request + 20;
r7b_request($db, array_merge($defaults, array('request' => $notAccepted, 'visit_status' => 'arrived', 'request_status' => 'Cancelled')));
r7b_expect_code($service->getOrCreateDraft($notAccepted, $performer), 'REQUEST_NOT_ACCEPTED', 'non-Accepted request denied');
vcw_assert_same(0, r7b_result_count($db, $notAccepted), 'non-Accepted request no result');

$nonEnrolled = $request + 21;
r7b_request($db, array_merge($defaults, array('request' => $nonEnrolled, 'visit_status' => 'arrived', 'enrolled' => false)));
r7b_expect_code($service->getOrCreateDraft($nonEnrolled, $performer), 'WORKFLOW_NOT_ENROLLED', 'non-enrolled denied');
vcw_assert_same(0, r7b_result_count($db, $nonEnrolled), 'non-enrolled no result');

$nonVisit = $request + 22;
r7b_request($db, array_merge($defaults, array('request' => $nonVisit, 'visit_status' => 'arrived', 'mode' => 'non_visit', 'decision' => 'non_visit')));
r7b_expect_code($service->getOrCreateDraft($nonVisit, $performer), 'VISIT_NOT_REQUIRED', 'non-visit disposition denied');
vcw_assert_same(0, r7b_result_count($db, $nonVisit), 'non-visit no result');

$authorityRequest = $request + 30;
r7b_request($db, array_merge($defaults, array('request' => $authorityRequest, 'visit_status' => 'arrived')));
foreach (array($unrelated, $command, $cross, $doctor) as $actor) {
    r7b_expect_code($service->getOrCreateDraft($authorityRequest, $actor), 'ACCESS_DENIED', 'noncanonical actor denied ' . $actor);
}
vcw_assert_same(0, r7b_result_count($db, $authorityRequest), 'authority denials create no result');

$replacedRequest = $request + 31;
r7b_request($db, array_merge($defaults, array('request' => $replacedRequest, 'visit_status' => 'arrived', 'performer' => $replacement, 'performer_staff' => $replacement, 'prior' => array($performer, $performer))));
r7b_expect_code($service->getOrCreateDraft($replacedRequest, $performer), 'ACCESS_DENIED', 'replaced performer denied');

$terminalRequest = $request + 32;
r7b_request($db, array_merge($defaults, array('request' => $terminalRequest, 'visit_status' => 'arrived', 'assignment_active' => false)));
r7b_expect_code($service->getOrCreateDraft($terminalRequest, $performer), 'ACCESS_DENIED', 'terminal performer denied');

$projectionOnly = $request + 33;
r7b_request($db, array_merge($defaults, array('request' => $projectionOnly, 'visit_status' => 'arrived', 'omit_assignment' => true)));
r7b_expect_code($service->getOrCreateDraft($projectionOnly, $performer), 'ACCESS_DENIED', 'projection-only actor denied');

$crossAssigned = $request + 34;
r7b_request($db, array_merge($defaults, array('request' => $crossAssigned, 'visit_status' => 'arrived', 'performer' => $cross, 'performer_staff' => $cross)));
r7b_expect_code($service->getOrCreateDraft($crossAssigned, $cross), 'ACCESS_DENIED', 'cross-facility canonical assignment denied');
vcw_assert_same(0, r7b_result_count($db, $crossAssigned), 'cross-facility canonical assignment creates no result');

$doctorRequest = $request + 35;
$doctorAssignment = r7b_request($db, array_merge($defaults, array('request' => $doctorRequest, 'visit_status' => 'completed', 'doctor' => $doctorPerformer, 'doctor_staff' => $doctorPerformer, 'performer' => $doctorPerformer, 'performer_staff' => $doctorPerformer)));
$doctorDraft = $service->getOrCreateDraft($doctorRequest, $doctorPerformer);
$doctorRow = $db->where('visit_result_id', (int) ($doctorDraft['visit_result_id'] ?? 0))->get('visit_results')->row();
vcw_assert_true(($doctorDraft['status'] ?? null) === 'success' && $doctorRow && (int) $doctorRow->visit_assignment_id === $doctorAssignment, 'doctor canonical performer draft allowed');

$reuseRequest = $request + 40;
r7b_request($db, array_merge($defaults, array('request' => $reuseRequest, 'visit_status' => 'in_service')));
$firstDraft = $service->getOrCreateDraft($reuseRequest, $performer);
$sameDraft = $service->getOrCreateDraft($reuseRequest, $performer);
vcw_assert_true(($firstDraft['status'] ?? null) === 'success' && ($sameDraft['status'] ?? null) === 'success' && (int) $firstDraft['visit_result_id'] === (int) $sameDraft['visit_result_id'], 'getOrCreate reuses canonical draft');
vcw_assert_same(1, r7b_result_count($db, $reuseRequest), 'getOrCreate row count one');

$resultId = (int) $firstDraft['visit_result_id'];
$saveOne = $service->saveDraft($resultId, $performer, 0, r7b_payload('one'));
vcw_assert_true(($saveOne['status'] ?? null) === 'success' && (int) ($saveOne['draft_revision'] ?? -1) === 1, 'save revision zero to one');
$savedOne = $db->where('visit_result_id', $resultId)->get('visit_results')->row();
vcw_assert_true($savedOne->observation_summary === 'Observasi one' && $savedOne->performer_notes === 'Catatan one', 'top-level factual text normalized');
vcw_assert_same(array(array('key' => 'skin_color', 'label' => 'Warna kulit', 'value' => 'Normal')), json_decode($savedOne->findings_json, true), 'findings normalized');
vcw_assert_same(array(array('key' => 'positioning', 'label' => 'Posisi', 'value' => '2')), json_decode($savedOne->actions_json, true), 'actions normalized');

$saveTwo = $service->saveDraft($resultId, $performer, 1, r7b_payload('two'));
vcw_assert_true(($saveTwo['status'] ?? null) === 'success' && (int) ($saveTwo['draft_revision'] ?? -1) === 2, 'save revision one to two');
$beforeStale = $db->where('visit_result_id', $resultId)->get('visit_results')->row_array();
$stale = $service->saveDraft($resultId, $performer, 1, r7b_payload('stale'));
r7b_expect_code($stale, 'STALE_DRAFT_REVISION', 'stale revision denied');
vcw_assert_same($beforeStale, $db->where('visit_result_id', $resultId)->get('visit_results')->row_array(), 'stale payload cannot overwrite');

foreach (array($unrelated, $command) as $wrongActor) {
    $beforeWrong = $db->where('visit_result_id', $resultId)->get('visit_results')->row_array();
    r7b_expect_code($service->saveDraft($resultId, $wrongActor, 2, r7b_payload('wrong')), 'ACCESS_DENIED', 'wrong save actor denied');
    vcw_assert_same($beforeWrong, $db->where('visit_result_id', $resultId)->get('visit_results')->row_array(), 'wrong save actor preserves row');
}

$replacedSaveRequest = $request + 42;
$replacedSaveAssignment = r7b_request($db, array_merge($defaults, array('request' => $replacedSaveRequest, 'visit_status' => 'arrived')));
$replacedSaveDraft = $service->getOrCreateDraft($replacedSaveRequest, $performer);
vcw_assert_same('success', $replacedSaveDraft['status'] ?? null, 'replaced-save fixture draft');
$db->where('visit_assignment_id', $replacedSaveAssignment)->update('request_visit_performer_assignments', array('status' => 'diganti', 'ended_at' => date('Y-m-d H:i:s')));
$db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($replacedSaveRequest, $replacement, $replacement, $command, 'aktif'));
$db->where('request_id', $replacedSaveRequest)->update('requests', array('visit_performer_user_id' => $replacement));
$replacedSaveId = (int) $replacedSaveDraft['visit_result_id'];
$replacedSaveBefore = $db->where('visit_result_id', $replacedSaveId)->get('visit_results')->row_array();
r7b_expect_code($service->saveDraft($replacedSaveId, $performer, 0, r7b_payload('replaced')), 'ACCESS_DENIED', 'replaced draft owner denied save');
vcw_assert_same($replacedSaveBefore, $db->where('visit_result_id', $replacedSaveId)->get('visit_results')->row_array(), 'replaced draft owner preserves row');

$submittedRequest = $request + 41;
$submittedAssignment = r7b_request($db, array_merge($defaults, array('request' => $submittedRequest, 'visit_status' => 'completed')));
$db->insert('visit_results', array(
    'request_id' => $submittedRequest, 'visit_assignment_id' => $submittedAssignment, 'version_no' => 1,
    'performer_user_id' => $performer, 'performer_staff_id' => $performer, 'status' => 'submitted',
    'draft_revision' => 1, 'observation_summary' => 'Submitted', 'findings_json' => '[]', 'actions_json' => '[]',
    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'), 'submitted_at' => date('Y-m-d H:i:s'),
    'submitted_by_user_id' => $performer, 'submission_key' => 'result-submitted-' . $submittedRequest,
));
$submittedId = (int) $db->insert_id();
$submittedBefore = $db->where('visit_result_id', $submittedId)->get('visit_results')->row_array();
r7b_expect_code($service->saveDraft($submittedId, $performer, 1, r7b_payload('submitted')), 'RESULT_ALREADY_SUBMITTED', 'submitted row immutable');
vcw_assert_same($submittedBefore, $db->where('visit_result_id', $submittedId)->get('visit_results')->row_array(), 'submitted row unchanged');
r7b_expect_code($service->getOrCreateDraft($submittedRequest, $performer), 'RESULT_ALREADY_SUBMITTED', 'getOrCreate cannot create successor');
vcw_assert_same(1, r7b_result_count($db, $submittedRequest), 'no correction successor in 7B');

$valid = $service->saveDraft($resultId, $performer, 2, array('observation_summary' => null, 'findings' => array(), 'actions' => array(), 'performer_notes' => null));
vcw_assert_same('success', $valid['status'] ?? null, 'valid empty factual payload');
$revision = (int) ($valid['draft_revision'] ?? 3);

$invalidPayloads = array(
    'unknown top-level key' => r7b_payload('bad') + array('unknown' => 'x'),
    'clinical diagnosis key' => r7b_payload('bad') + array('diagnosis' => 'x'),
    'clinical prescription key' => r7b_payload('bad') + array('prescription' => 'x'),
    'clinical final assessment key' => r7b_payload('bad') + array('final_assessment' => 'x'),
    'findings shape' => array('observation_summary' => null, 'findings' => 'bad', 'actions' => array(), 'performer_notes' => null),
    'actions item shape' => array('observation_summary' => null, 'findings' => array(), 'actions' => array(array('key' => 'a', 'label' => 'A')), 'performer_notes' => null),
    'extra item key' => array('observation_summary' => null, 'findings' => array(array('key' => 'a', 'label' => 'A', 'value' => 'v', 'extra' => true)), 'actions' => array(), 'performer_notes' => null),
    'item count' => array('observation_summary' => null, 'findings' => array_fill(0, 51, array('key' => 'a', 'label' => 'A', 'value' => 'v')), 'actions' => array(), 'performer_notes' => null),
    'key format' => array('observation_summary' => null, 'findings' => array(array('key' => 'Bad-Key', 'label' => 'A', 'value' => 'v')), 'actions' => array(), 'performer_notes' => null),
    'label length' => array('observation_summary' => null, 'findings' => array(array('key' => 'a', 'label' => str_repeat('L', 121), 'value' => 'v')), 'actions' => array(), 'performer_notes' => null),
    'value length' => array('observation_summary' => null, 'findings' => array(array('key' => 'a', 'label' => 'A', 'value' => str_repeat('v', 2001))), 'actions' => array(), 'performer_notes' => null),
    'nested value' => array('observation_summary' => null, 'findings' => array(array('key' => 'a', 'label' => 'A', 'value' => array('nested'))), 'actions' => array(), 'performer_notes' => null),
    'invalid utf8' => array('observation_summary' => "\xC3\x28", 'findings' => array(), 'actions' => array(), 'performer_notes' => null),
    'text capacity' => array('observation_summary' => str_repeat('x', 65536), 'findings' => array(), 'actions' => array(), 'performer_notes' => null),
);
foreach (array('diagnosa', 'resep', 'assessment_final', 'doctor_plan', 'treatment_plan', 'clinical_closure') as $clinicalField) {
    $invalidPayloads['clinical authority key ' . $clinicalField] = r7b_payload('bad') + array($clinicalField => 'x');
}
foreach ($invalidPayloads as $message => $payload) {
    r7b_expect_payload_denied($service, $db, $resultId, $performer, $payload, $message);
}

echo "DRAFT_AT_ARRIVED=PASS\n";
echo "DRAFT_AT_IN_SERVICE=PASS\n";
echo "DRAFT_AT_COMPLETED=PASS\n";
echo "DRAFT_AT_NOT_STARTED_DENIED=PASS\n";
echo "DRAFT_AT_EN_ROUTE_DENIED=PASS\n";
echo "DOCTOR_PERFORMER_DRAFT=PASS\n";
echo "COMMAND_CENTER_DRAFT_DENIED=PASS\n";
echo "WRONG_PERFORMER_DRAFT_DENIED=PASS\n";
echo "REPLACED_PERFORMER_DRAFT_DENIED=PASS\n";
echo "TERMINAL_PERFORMER_DRAFT_DENIED=PASS\n";
echo "GET_OR_CREATE_REUSES_DRAFT=PASS\n";
echo "SAVE_REVISION_0_TO_1=PASS\n";
echo "SAVE_REVISION_1_TO_2=PASS\n";
echo "STALE_REVISION_REJECTED=PASS\n";
echo "SUBMITTED_DRAFT_SAVE_DENIED=PASS\n";
echo "SUBMITTED_GET_OR_CREATE_DOES_NOT_CREATE_SUCCESSOR=PASS\n";
echo "RESULT_VALID_FACTUAL_PAYLOAD=PASS\n";
echo "RESULT_UNKNOWN_TOP_LEVEL_KEY_DENIED=PASS\n";
echo "RESULT_CLINICAL_AUTHORITY_FIELDS_DENIED=PASS\n";
echo "RESULT_FINDINGS_SHAPE_GUARD=PASS\n";
echo "RESULT_ACTIONS_SHAPE_GUARD=PASS\n";
echo "RESULT_ITEM_COUNT_GUARD=PASS\n";
echo "RESULT_KEY_FORMAT_GUARD=PASS\n";
echo "RESULT_LABEL_LENGTH_GUARD=PASS\n";
echo "RESULT_VALUE_LENGTH_GUARD=PASS\n";
echo "RESULT_NESTED_VALUE_DENIED=PASS\n";
echo "RESULT_INVALID_UTF8_DENIED=PASS\n";
echo "RESULT_TEXT_CAPACITY_GUARD=PASS\n";
