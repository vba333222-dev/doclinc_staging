<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'models/Visit_result_m.php';
require_once APPPATH . 'libraries/Visit_result_service.php';
require_once __DIR__ . '/result_submission_support.php';

vcw_assert_true(method_exists('Visit_result_service', 'createCorrectionDraft'), 'Task 7D production API missing: Visit_result_service::createCorrectionDraft');

function r7d_seed_review($db, array $fixture, $resultId, $decision, $key)
{
    $responsible = $db->where('request_id', $fixture['request'])->where('status', 'aktif')->get('request_responsible_doctor_assignments')->row();
    vcw_assert_true((bool) $responsible, 'correction fixture responsible assignment exists');
    $db->insert('clinical_reviews', array(
        'request_id' => $fixture['request'], 'visit_result_id' => (int) $resultId,
        'reviewer_user_id' => $fixture['doctor'], 'responsible_assignment_id' => (int) $responsible->responsible_assignment_id,
        'decision' => $decision, 'correction_reason' => $decision === 'correction_required' ? 'Perbaiki catatan faktual.' : null,
        'review_notes' => 'Synthetic Task 7D', 'reviewed_at' => date('Y-m-d H:i:s.u'), 'idempotency_key' => $key,
    ));
}

function r7d_submitted($db, Visit_result_service $service, array $fixture, $suffix)
{
    $result = r7c_draft($db, $service, $fixture, $suffix);
    $measurement = r7c_measurement($db, $fixture);
    $submit = $service->submit($result, $fixture['performer'], array($measurement), 'r7d-submit-' . $result);
    vcw_assert_same('success', $submit['status'] ?? null, 'Task 7D fixture submits predecessor');
    return array($result, $measurement);
}

function r7d_expect($result, $code, $message)
{
    vcw_assert_same($code, $result['safe_error_code'] ?? null, $message);
}

$maximum = $db->query('SELECT GREATEST(COALESCE((SELECT MAX(userId) FROM users),0),COALESCE((SELECT MAX(staff_id) FROM puskesmas_staff),0),COALESCE((SELECT MAX(request_id) FROM requests),0)) AS base_id')->row();
$base = (int) $maximum->base_id + 9000;
$service = new Visit_result_service($db);

$noReview = r7c_fixture($db, $base, 'NOREVIEW');
list($noReviewResult) = r7d_submitted($db, $service, $noReview, 'no-review');
r7d_expect($service->createCorrectionDraft($noReview['request'], $noReview['performer'], $noReviewResult, 'no-review-' . $noReviewResult), 'CORRECTION_REVIEW_REQUIRED', 'correction without review denied');

$approved = r7c_fixture($db, $base + 100, 'APPROVED');
list($approvedResult) = r7d_submitted($db, $service, $approved, 'approved');
r7d_seed_review($db, $approved, $approvedResult, 'approved', 'approved-review-' . $approvedResult);
r7d_expect($service->createCorrectionDraft($approved['request'], $approved['performer'], $approvedResult, 'approved-' . $approvedResult), 'CORRECTION_NOT_ALLOWED', 'correction after approval denied');

$fixture = r7c_fixture($db, $base + 200, 'VALID');
list($v1, $measurement) = r7d_submitted($db, $service, $fixture, 'v1-copy');
r7d_seed_review($db, $fixture, $v1, 'correction_required', 'correction-review-' . $v1);
$v1Before = $db->where('visit_result_id', $v1)->get('visit_results')->row_array();
$v1Links = r7c_link_ids($db, $v1);
$v1Review = $db->where('visit_result_id', $v1)->get('clinical_reviews')->row_array();
r7d_expect($service->getOrCreateDraft($fixture['request'], $fixture['performer']), 'RESULT_ALREADY_SUBMITTED', 'normal get-or-create cannot bypass explicit correction creation');
$created = $service->createCorrectionDraft($fixture['request'], $fixture['performer'], $v1, 'correction-' . $v1);
vcw_assert_same('success', $created['status'] ?? null, 'correction-required review permits correction draft');
$v2 = (int) $created['visit_result_id'];
$v2Row = $db->where('visit_result_id', $v2)->get('visit_results')->row_array();
vcw_assert_same(2, (int) $v2Row['version_no'], 'successor version increments');
vcw_assert_same($v1, (int) $v2Row['supersedes_result_id'], 'successor link points to predecessor');
foreach (array('observation_summary','findings_json','actions_json','performer_notes','request_id','visit_assignment_id','performer_user_id','performer_staff_id') as $field) { vcw_assert_same($v1Before[$field], $v2Row[$field], 'correction copies factual/attribution ' . $field); }
vcw_assert_true($v2Row['status'] === 'draft' && (int) $v2Row['draft_revision'] === 0 && $v2Row['submitted_at'] === null && $v2Row['submitted_by_user_id'] === null && $v2Row['submission_key'] === null, 'successor draft defaults');
vcw_assert_same(array(), r7c_link_ids($db, $v2), 'correction does not copy TTV links');
vcw_assert_same(0, (int) $db->where('visit_result_id', $v2)->count_all_results('clinical_reviews'), 'correction does not copy review');
vcw_assert_same($v1Before, $db->where('visit_result_id', $v1)->get('visit_results')->row_array(), 'predecessor result immutable');
vcw_assert_same($v1Links, r7c_link_ids($db, $v1), 'predecessor TTV links preserved');
vcw_assert_same($v1Review, $db->where('visit_result_id', $v1)->get('clinical_reviews')->row_array(), 'predecessor review preserved');

$replay = $service->createCorrectionDraft($fixture['request'], $fixture['performer'], $v1, 'correction-' . $v1);
vcw_assert_true(($replay['status'] ?? null) === 'success' && !empty($replay['idempotent_replay']) && (int) $replay['visit_result_id'] === $v2, 'same correction key replays same successor');
r7d_expect($service->createCorrectionDraft($fixture['request'], $fixture['performer'], $v1, 'different-correction-' . $v1), 'CORRECTION_ALREADY_EXISTS', 'different key cannot reuse correction draft');
$existingCorrectionDraft = $service->getOrCreateDraft($fixture['request'], $fixture['performer']);
vcw_assert_true(($existingCorrectionDraft['status'] ?? null) === 'success' && (int) $existingCorrectionDraft['visit_result_id'] === $v2, 'normal get-or-create returns the explicit correction draft');

$edited = r7c_payload('v2-edit');
$saved = $service->saveDraft($v2, $fixture['performer'], 0, $edited);
vcw_assert_true(($saved['status'] ?? null) === 'success' && (int) $saved['draft_revision'] === 1, 'correction draft integrates optimistic save');
$v2Submit = $service->submit($v2, $fixture['performer'], array($measurement), 'submit-v2-' . $v2);
vcw_assert_same('success', $v2Submit['status'] ?? null, 'correction version submits through Task 7C');
vcw_assert_same($v1Before, $db->where('visit_result_id', $v1)->get('visit_results')->row_array(), 'v2 submission preserves predecessor');
vcw_assert_same('aktif', (string) $db->where('visit_assignment_id', $fixture['assignment'])->get('request_visit_performer_assignments')->row()->status, 'correction submission keeps assignment active');
r7d_expect($service->createCorrectionDraft($fixture['request'], $fixture['performer'], $v1, 'stale-v1-' . $v1), 'CORRECTION_NOT_ALLOWED', 'non-latest predecessor denied');
r7d_seed_review($db, $fixture, $v2, 'correction_required', 'correction-review-' . $v2);
$v3Create = $service->createCorrectionDraft($fixture['request'], $fixture['performer'], $v2, 'correction-' . $v2);
vcw_assert_true(($v3Create['status'] ?? null) === 'success' && (int) $v3Create['version_no'] === 3, 'generic v2 to v3 chain');

foreach (array($fixture['unrelated'], $fixture['command'], $fixture['cross'], $fixture['doctor']) as $actor) {
    r7d_expect($service->createCorrectionDraft($fixture['request'], $actor, $v2, 'bad-actor-' . $actor . '-' . $v2), 'ACCESS_DENIED', 'correction actor denied');
}

$doctorFixture = r7c_fixture($db, $base + 300, 'DOCTOR', array('doctor_performer' => true));
list($doctorV1) = r7d_submitted($db, $service, $doctorFixture, 'doctor');
r7d_seed_review($db, $doctorFixture, $doctorV1, 'correction_required', 'doctor-review-' . $doctorV1);
vcw_assert_same('success', $service->createCorrectionDraft($doctorFixture['request'], $doctorFixture['performer'], $doctorV1, 'doctor-correction-' . $doctorV1)['status'] ?? null, 'canonical doctor performer may create correction draft');

$stateFixture = r7c_fixture($db, $base + 400, 'STATE');
$stateDraft = r7c_draft($db, $service, $stateFixture, 'state');
$stateMeasurement = r7c_measurement($db, $stateFixture);
vcw_assert_same('success', $service->submit($stateDraft, $stateFixture['performer'], array($stateMeasurement), 'state-submit-' . $stateDraft)['status'] ?? null, 'state fixture predecessor submitted while completed');
$db->where('request_id', $stateFixture['request'])->update('requests', array('visit_status' => 'in_service'));
r7d_seed_review($db, $stateFixture, $stateDraft, 'correction_required', 'state-review-' . $stateDraft);
r7d_expect($service->createCorrectionDraft($stateFixture['request'], $stateFixture['performer'], $stateDraft, 'state-correction-' . $stateDraft), 'INVALID_WORKFLOW_STATE', 'correction requires physical completed');

$replaced = r7c_fixture($db, $base + 500, 'REPLACED');
list($replacedV1) = r7d_submitted($db, $service, $replaced, 'replaced');
r7d_seed_review($db, $replaced, $replacedV1, 'correction_required', 'replaced-review-' . $replacedV1);
$db->where('visit_assignment_id', $replaced['assignment'])->update('request_visit_performer_assignments', array('status' => 'diganti', 'ended_at' => date('Y-m-d H:i:s')));
$db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($replaced['request'], $replaced['replacement'], $replaced['replacement'], $replaced['command'], 'aktif'));
$db->where('request_id', $replaced['request'])->update('requests', array('visit_performer_user_id' => $replaced['replacement']));
r7d_expect($service->createCorrectionDraft($replaced['request'], $replaced['performer'], $replacedV1, 'replaced-correction-' . $replacedV1), 'ACCESS_DENIED', 'replaced performer denied correction');

$terminal = r7c_fixture($db, $base + 600, 'TERMINAL');
list($terminalV1) = r7d_submitted($db, $service, $terminal, 'terminal');
r7d_seed_review($db, $terminal, $terminalV1, 'correction_required', 'terminal-review-' . $terminalV1);
$db->where('visit_assignment_id', $terminal['assignment'])->update('request_visit_performer_assignments', array('status' => 'selesai', 'ended_at' => date('Y-m-d H:i:s'), 'completed_at' => date('Y-m-d H:i:s')));
r7d_expect($service->createCorrectionDraft($terminal['request'], $terminal['performer'], $terminalV1, 'terminal-correction-' . $terminalV1), 'ACCESS_DENIED', 'terminal performer denied correction');

$keyOne = r7c_fixture($db, $base + 700, 'KEYONE');
list($keyOneV1) = r7d_submitted($db, $service, $keyOne, 'key-one');
r7d_seed_review($db, $keyOne, $keyOneV1, 'correction_required', 'key-one-review-' . $keyOneV1);
$sharedKey = 'shared-correction-key-' . $keyOneV1;
vcw_assert_same('success', $service->createCorrectionDraft($keyOne['request'], $keyOne['performer'], $keyOneV1, $sharedKey)['status'] ?? null, 'first correction key accepted');
$keyTwo = r7c_fixture($db, $base + 800, 'KEYTWO');
list($keyTwoV1) = r7d_submitted($db, $service, $keyTwo, 'key-two');
r7d_seed_review($db, $keyTwo, $keyTwoV1, 'correction_required', 'key-two-review-' . $keyTwoV1);
r7d_expect($service->createCorrectionDraft($keyTwo['request'], $keyTwo['performer'], $keyTwoV1, $sharedKey), 'CORRECTION_KEY_CONFLICT', 'global correction key collision denied');
r7d_expect($service->createCorrectionDraft($keyTwo['request'], $keyTwo['performer'], $keyTwoV1, ''), 'INVALID_CORRECTION_KEY', 'empty correction key denied');

echo "CORRECTION_WITHOUT_REVIEW_DENIED=PASS\nCORRECTION_AFTER_APPROVAL_DENIED=PASS\nCORRECTION_REQUIRED_REVIEW_ACCEPTED=PASS\n";
echo "CORRECTION_CANONICAL_PERFORMER=PASS\nCORRECTION_UNRELATED_DENIED=PASS\nCORRECTION_COMMAND_CENTER_DENIED=PASS\nCORRECTION_NON_PERFORMER_DOCTOR_DENIED=PASS\nCORRECTION_CROSS_FACILITY_DENIED=PASS\n";
echo "CORRECTION_NON_LATEST_PREDECESSOR_DENIED=PASS\nCORRECTION_LATEST_PREDECESSOR_REQUIRED=PASS\nCORRECTION_VERSION_NO_INCREMENTED=PASS\nCORRECTION_SUPERSEDES_LINK=PASS\n";
echo "CORRECTION_FACTUAL_PAYLOAD_COPIED=PASS\nCORRECTION_TTV_LINKS_NOT_COPIED=PASS\nCORRECTION_REVIEW_NOT_COPIED=PASS\nCORRECTION_PREDECESSOR_IMMUTABLE=PASS\n";
echo "CORRECTION_DRAFT_EDITABLE=PASS\nCORRECTION_DRAFT_REVISION_INTEGRATES_7B=PASS\nCORRECTION_VERSION_SUBMITS_WITH_7C=PASS\nCORRECTION_SUBMISSION_PREDECESSOR_UNCHANGED=PASS\nCORRECTION_SUBMISSION_ASSIGNMENT_REMAINS_ACTIVE=PASS\nCORRECTION_REQUIRES_EXPLICIT_CREATE=PASS\nCORRECTION_VERSION_CHAIN=PASS\n";
echo "CORRECTION_DOCTOR_PERFORMER_ALLOWED=PASS\nCORRECTION_COMPLETED_STATE_REQUIRED=PASS\nCORRECTION_REPLACED_PERFORMER_DENIED=PASS\nCORRECTION_TERMINAL_PERFORMER_DENIED=PASS\nCORRECTION_GLOBAL_KEY_CONFLICT=PASS\nCORRECTION_KEY_VALIDATION=PASS\n";
