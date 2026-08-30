<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'models/Visit_result_m.php';
require_once APPPATH . 'libraries/Visit_result_service.php';
require_once __DIR__ . '/result_submission_support.php';

vcw_assert_true((bool) $db->table_exists('visit_result_vital_sign_measurements'), 'Task 7C reaches real result-TTV schema');
vcw_assert_true(method_exists('Visit_result_service', 'submit'), 'Task 7C production API missing: Visit_result_service::submit');

$maximum = $db->query('SELECT GREATEST(COALESCE((SELECT MAX(userId) FROM users),0),COALESCE((SELECT MAX(staff_id) FROM puskesmas_staff),0),COALESCE((SELECT MAX(request_id) FROM requests),0)) AS base_id')->row();
$base = (int) $maximum->base_id + 5000;
$service = new Visit_result_service($db);

$valid = r7c_fixture($db, $base, 'VALID');
$resultId = r7c_draft($db, $service, $valid);
$before = $db->where('visit_result_id', $resultId)->get('visit_results')->row_array();
$m1 = r7c_measurement($db, $valid, array('systolic' => 118));
$m2 = r7c_measurement($db, $valid, array('systolic' => 121));
$key = 'result-submit-' . $resultId;
$submitted = $service->submit($resultId, $valid['performer'], array((string) $m2, $m1), $key);
vcw_assert_same('success', $submitted['status'] ?? null, 'completed canonical performer submits');
$row = $db->where('visit_result_id', $resultId)->get('visit_results')->row_array();
vcw_assert_true($row['status'] === 'submitted' && (int) $row['submitted_by_user_id'] === $valid['performer'] && $row['submission_key'] === $key && !empty($row['submitted_at']), 'server submission attribution persisted');
foreach (array('visit_result_id','request_id','visit_assignment_id','version_no','supersedes_result_id','performer_user_id','performer_staff_id','draft_revision','observation_summary','findings_json','actions_json','performer_notes') as $field) {
    vcw_assert_same($before[$field], $row[$field], 'submission preserves factual/version field ' . $field);
}
vcw_assert_same(array($m1, $m2), r7c_link_ids($db, $resultId), 'canonical sorted result-TTV links');
vcw_assert_same(1, r7c_event_count($db, $valid['request']), 'one logical submission event');
$event = $db->where('request_id', $valid['request'])->where('event_type', 'visit_result.submitted')->get('request_events')->row();
$metadata = json_decode((string) $event->metadata_json, true);
vcw_assert_true($event && (int) $event->actor_user_id === $valid['performer'] && (int) $event->actor_staff_id === $valid['performer'] && (string) $event->domain_event_key === 'visit-result:' . $resultId . ':submitted', 'submission event canonical attribution/key');
vcw_assert_same(array('visit_result_id' => $resultId, 'version_no' => 1, 'visit_assignment_id' => $valid['assignment']), $metadata, 'event contains identifiers only');

$replay = $service->submit($resultId, $valid['performer'], array($m2, $m1), $key);
vcw_assert_true(($replay['status'] ?? null) === 'success' && !empty($replay['idempotent_replay']), 'same key and link set replays success');
vcw_assert_same(array($m1, $m2), r7c_link_ids($db, $resultId), 'replay link set unchanged');
vcw_assert_same(1, r7c_event_count($db, $valid['request']), 'replay event not duplicated');
r7c_expect_code($service->submit($resultId, $valid['performer'], array($m1), $key), 'SUBMISSION_KEY_CONFLICT', 'same key different TTV conflicts');
r7c_expect_code($service->submit($resultId, $valid['performer'], array($m1, $m2), $key . '-different'), 'RESULT_ALREADY_SUBMITTED', 'submitted result different key denied');
r7c_expect_code($service->saveDraft($resultId, $valid['performer'], 1, r7c_payload('late')), 'RESULT_ALREADY_SUBMITTED', 'real submitted result immutable to save');
r7c_expect_code($service->getOrCreateDraft($valid['request'], $valid['performer']), 'RESULT_ALREADY_SUBMITTED', 'real submitted result gets no successor');

foreach (array('arrived', 'in_service') as $offset => $state) {
    $fixture = r7c_fixture($db, $base + 100 + ($offset * 20), strtoupper($state), array('visit_status' => $state));
    $draftId = r7c_draft($db, $service, $fixture, $state);
    $measurement = r7c_measurement($db, $fixture);
    $snapshot = $db->where('visit_result_id', $draftId)->get('visit_results')->row_array();
    r7c_expect_code($service->submit($draftId, $fixture['performer'], array($measurement), 'state-' . $draftId), 'INVALID_WORKFLOW_STATE', 'submit denied at ' . $state);
    vcw_assert_same($snapshot, $db->where('visit_result_id', $draftId)->get('visit_results')->row_array(), 'state denial preserves draft ' . $state);
}

$authority = r7c_fixture($db, $base + 200, 'AUTH');
$authorityResult = r7c_draft($db, $service, $authority, 'authority');
$authorityMeasurement = r7c_measurement($db, $authority);
foreach (array($authority['unrelated'], $authority['command'], $authority['cross'], $authority['doctor']) as $actor) {
    r7c_expect_code($service->submit($authorityResult, $actor, array($authorityMeasurement), 'actor-' . $actor), 'ACCESS_DENIED', 'noncanonical submit actor denied');
}
vcw_assert_same('draft', (string) $db->where('visit_result_id', $authorityResult)->get('visit_results')->row()->status, 'authority denials preserve draft');
vcw_assert_same(0, r7c_event_count($db, $authority['request']), 'authority denials create no event');
vcw_assert_same(array(), r7c_link_ids($db, $authorityResult), 'authority denials create no links');

$doctorFixture = r7c_fixture($db, $base + 300, 'DOCTOR', array('doctor_performer' => true));
$doctorResult = r7c_draft($db, $service, $doctorFixture, 'doctor');
$doctorMeasurement = r7c_measurement($db, $doctorFixture);
vcw_assert_same('success', $service->submit($doctorResult, $doctorFixture['performer'], array($doctorMeasurement), 'doctor-' . $doctorResult)['status'] ?? null, 'doctor canonical performer submits');

$replaced = r7c_fixture($db, $base + 400, 'REPLACED');
$replacedResult = r7c_draft($db, $service, $replaced, 'replaced');
$replacedMeasurement = r7c_measurement($db, $replaced);
$db->where('visit_assignment_id', $replaced['assignment'])->update('request_visit_performer_assignments', array('status' => 'diganti', 'ended_at' => date('Y-m-d H:i:s')));
$db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($replaced['request'], $replaced['replacement'], $replaced['replacement'], $replaced['command'], 'aktif'));
$db->where('request_id', $replaced['request'])->update('requests', array('visit_performer_user_id' => $replaced['replacement']));
r7c_expect_code($service->submit($replacedResult, $replaced['performer'], array($replacedMeasurement), 'replaced-' . $replacedResult), 'ACCESS_DENIED', 'replaced performer denied');

$terminal = r7c_fixture($db, $base + 500, 'TERMINAL');
$terminalResult = r7c_draft($db, $service, $terminal, 'terminal');
$terminalMeasurement = r7c_measurement($db, $terminal);
$db->where('visit_assignment_id', $terminal['assignment'])->update('request_visit_performer_assignments', array('status' => 'selesai', 'ended_at' => date('Y-m-d H:i:s'), 'completed_at' => date('Y-m-d H:i:s')));
r7c_expect_code($service->submit($terminalResult, $terminal['performer'], array($terminalMeasurement), 'terminal-' . $terminalResult), 'ACCESS_DENIED', 'terminal performer denied');

$projectionOnly = r7c_fixture($db, $base + 550, 'PROJECTION');
$projectionResult = r7c_draft($db, $service, $projectionOnly, 'projection');
$projectionMeasurement = r7c_measurement($db, $projectionOnly);
$db->where('request_id', $projectionOnly['request'])->update('requests', array('visit_performer_user_id' => $projectionOnly['replacement']));
r7c_expect_code($service->submit($projectionResult, $projectionOnly['performer'], array($projectionMeasurement), 'projection-' . $projectionResult), 'ACCESS_DENIED', 'projection-only performer denied');

$notAccepted = r7c_fixture($db, $base + 575, 'NOTACCEPTED');
$notAcceptedResult = r7c_draft($db, $service, $notAccepted, 'not-accepted');
$notAcceptedMeasurement = r7c_measurement($db, $notAccepted);
$db->where('request_id', $notAccepted['request'])->update('requests', array('request_status' => 'Completed'));
r7c_expect_code($service->submit($notAcceptedResult, $notAccepted['performer'], array($notAcceptedMeasurement), 'not-accepted-' . $notAcceptedResult), 'REQUEST_NOT_ACCEPTED', 'non-Accepted request denied');

$invalidStored = r7c_fixture($db, $base + 600, 'BADPAYLOAD');
$invalidResult = r7c_draft($db, $service, $invalidStored, 'bad-payload');
$invalidMeasurement = r7c_measurement($db, $invalidStored);
$db->where('visit_result_id', $invalidResult)->update('visit_results', array('findings_json' => '{"unexpected":"shape"}'));
r7c_expect_code($service->submit($invalidResult, $invalidStored['performer'], array($invalidMeasurement), 'bad-payload-' . $invalidResult), 'INVALID_RESULT_PAYLOAD', 'stored factual payload revalidated');
vcw_assert_same('draft', (string) $db->where('visit_result_id', $invalidResult)->get('visit_results')->row()->status, 'invalid stored payload remains draft');

$references = r7c_fixture($db, $base + 700, 'REFERENCES');
$referenceResult = r7c_draft($db, $service, $references, 'references');
$validMeasurement = r7c_measurement($db, $references);
$foreignRequestMeasurement = r7c_measurement($db, $references, array('request_id' => $references['request'] + 999));
$foreignPerformerMeasurement = r7c_measurement($db, $references, array('measured_by_user_id' => $references['unrelated'], 'visit_performer_user_id' => $references['unrelated']));
$wrongStaffMeasurement = r7c_measurement($db, $references, array('measured_by_staff_id' => $references['unrelated']));
$priorMeasurement = r7c_measurement($db, $references, array('measured_at' => '2025-01-01 00:00:00.000000'));
foreach (array(
    array($foreignRequestMeasurement, 'foreign request'),
    array($foreignPerformerMeasurement, 'foreign performer'),
    array($wrongStaffMeasurement, 'wrong staff'),
    array($priorMeasurement, 'prior cycle'),
    array(999999999, 'missing measurement')
) as $case) {
    r7c_expect_code($service->submit($referenceResult, $references['performer'], array($case[0]), 'reference-' . $referenceResult . '-' . $case[1]), 'INVALID_MEASUREMENT_REFERENCES', $case[1] . ' denied');
    vcw_assert_same('draft', (string) $db->where('visit_result_id', $referenceResult)->get('visit_results')->row()->status, $case[1] . ' no submission');
    vcw_assert_same(array(), r7c_link_ids($db, $referenceResult), $case[1] . ' no links');
}
foreach (array(array($validMeasurement, $validMeasurement), array(0), array(-1), array('bad'), array(array(1)), array(new stdClass())) as $index => $ids) {
    r7c_expect_code($service->submit($referenceResult, $references['performer'], $ids, 'invalid-refs-' . $referenceResult . '-' . $index), 'INVALID_MEASUREMENT_REFERENCES', 'measurement input validation ' . $index);
}
foreach (array('', str_repeat('k', 192)) as $index => $badKey) {
    r7c_expect_code($service->submit($referenceResult, $references['performer'], array($validMeasurement), $badKey), 'INVALID_SUBMISSION_KEY', 'submission key validation ' . $index);
}

$global = r7c_fixture($db, $base + 800, 'GLOBAL');
$globalResult = r7c_draft($db, $service, $global, 'global');
$globalMeasurement = r7c_measurement($db, $global);
r7c_expect_code($service->submit($globalResult, $global['performer'], array($globalMeasurement), $key), 'SUBMISSION_KEY_CONFLICT', 'global submission key collision denied');
vcw_assert_same('draft', (string) $db->where('visit_result_id', $globalResult)->get('visit_results')->row()->status, 'global key conflict preserves second result');

$eventFailure = r7c_fixture($db, $base + 900, 'EVENTFAIL');
$eventFailureResult = r7c_draft($db, $service, $eventFailure, 'event-failure');
$eventFailureMeasurement = r7c_measurement($db, $eventFailure);
$trigger = 'vcw_r7c_event_failure';
$db->query('DROP TRIGGER IF EXISTS `' . $trigger . '`');
$db->query("CREATE TRIGGER `{$trigger}` BEFORE INSERT ON `request_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='task7c event failure'");
$eventFailureResultCall = $service->submit($eventFailureResult, $eventFailure['performer'], array($eventFailureMeasurement), 'event-failure-' . $eventFailureResult);
$db->query('DROP TRIGGER IF EXISTS `' . $trigger . '`');
r7c_expect_code($eventFailureResultCall, 'WRITE_FAILED', 'event persistence failure returns stable domain error');
$eventFailureRow = $db->where('visit_result_id', $eventFailureResult)->get('visit_results')->row();
vcw_assert_true((string) $eventFailureRow->status === 'draft' && $eventFailureRow->submission_key === null && $eventFailureRow->submitted_at === null, 'event failure rolls back result transition');
vcw_assert_same(array(), r7c_link_ids($db, $eventFailureResult), 'event failure rolls back links');
vcw_assert_same(0, r7c_event_count($db, $eventFailure['request']), 'event failure creates no event');

vcw_assert_same('Accepted', (string) $db->where('request_id', $valid['request'])->get('requests')->row()->request_status, 'submission preserves Accepted request');
vcw_assert_same('completed', (string) $db->where('request_id', $valid['request'])->get('requests')->row()->visit_status, 'submission preserves completed Visit');
vcw_assert_same('aktif', (string) $db->where('visit_assignment_id', $valid['assignment'])->get('request_visit_performer_assignments')->row()->status, 'submission preserves active assignment');
vcw_assert_true(!array_key_exists('systolic', $row) && !array_key_exists('temperature_c', $row), 'result row duplicates no TTV values');

echo "RESULT_SUBMIT_COMPLETED=PASS\n";
echo "RESULT_SUBMIT_BEFORE_COMPLETED_DENIED=PASS\n";
echo "RESULT_SUBMIT_CANONICAL_PERFORMER=PASS\n";
echo "RESULT_SUBMIT_DOCTOR_PERFORMER=PASS\n";
echo "RESULT_SUBMIT_UNRELATED_DENIED=PASS\n";
echo "RESULT_SUBMIT_REPLACED_DENIED=PASS\n";
echo "RESULT_SUBMIT_TERMINAL_DENIED=PASS\n";
echo "RESULT_SUBMIT_COMMAND_CENTER_DENIED=PASS\n";
echo "RESULT_SUBMIT_CROSS_FACILITY_DENIED=PASS\n";
echo "RESULT_SUBMIT_NON_PERFORMER_DOCTOR_DENIED=PASS\n";
echo "RESULT_SUBMIT_PROJECTION_ONLY_DENIED=PASS\n";
echo "RESULT_SUBMIT_NON_ACCEPTED_REQUEST_DENIED=PASS\n";
echo "RESULT_SUBMITTED_SERVER_ATTRIBUTION=PASS\n";
echo "RESULT_SUBMITTED_PAYLOAD_PRESERVED=PASS\n";
echo "RESULT_INVALID_STORED_PAYLOAD_DENIED=PASS\n";
echo "RESULT_TTV_VALID_LINKS=PASS\n";
echo "RESULT_TTV_FOREIGN_REQUEST_DENIED=PASS\n";
echo "RESULT_TTV_FOREIGN_PERFORMER_DENIED=PASS\n";
echo "RESULT_TTV_WRONG_STAFF_DENIED=PASS\n";
echo "RESULT_TTV_PRIOR_CYCLE_DENIED=PASS\n";
echo "RESULT_TTV_MISSING_DENIED=PASS\n";
echo "RESULT_TTV_DUPLICATE_INPUT_DENIED=PASS\n";
echo "RESULT_TTV_VALUES_NOT_DUPLICATED=PASS\n";
echo "SUBMIT_SAME_KEY_SAME_INPUT_REPLAY=PASS\n";
echo "SUBMIT_REPLAY_LINK_SET_UNCHANGED=PASS\n";
echo "SUBMIT_REPLAY_EVENT_NOT_DUPLICATED=PASS\n";
echo "SUBMIT_SAME_KEY_DIFFERENT_TTV_CONFLICT=PASS\n";
echo "SUBMIT_SAME_KEY_DIFFERENT_RESULT_CONFLICT=PASS\n";
echo "SUBMIT_ALREADY_SUBMITTED_DIFFERENT_KEY_DENIED=PASS\n";
echo "TASK7C_GLOBAL_SUBMISSION_KEY_CONFLICT=PASS\n";
echo "TASK7C_SUBMITTED_SAVE_IMMUTABLE=PASS\n";
echo "TASK7C_SUBMITTED_GET_OR_CREATE_NO_SUCCESSOR=PASS\n";
echo "TASK7C_EVENT_FAILURE_ATOMICITY=PASS\n";
echo "RESULT_AFTER_SUBMIT_REQUEST_ACCEPTED=PASS\n";
echo "RESULT_AFTER_SUBMIT_VISIT_COMPLETED=PASS\n";
echo "RESULT_AFTER_SUBMIT_ASSIGNMENT_ACTIVE=PASS\n";
