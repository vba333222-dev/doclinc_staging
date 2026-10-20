<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'libraries/Visit_result_service.php';
require_once APPPATH . 'libraries/Clinical_review_service.php';
require_once __DIR__ . '/result_submission_support.php';

$passed = 0;
$failed = 0;
function rn_expect($condition, $label)
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL {$label}\n";
}

$base = random_int(61000, 61800);
$db->db_debug = false;
$db->trans_begin();
try {
    $fixture = r7c_fixture($db, $base, 'NOTES');
    $service = new Visit_result_service($db);
    $draft = $service->getOrCreateDraft($fixture['request'], $fixture['performer']);
    rn_expect(!empty($draft['status']) && $draft['status'] === 'success', 'draft_created');
    $resultId = (int) ($draft['visit_result_id'] ?? 0);

    $legacy = $service->saveDraft($resultId, $fixture['performer'], 0, r7c_payload('legacy'));
    rn_expect(!empty($legacy['status']) && $legacy['status'] === 'success', 'legacy_payload_accepted');
    $row = $db->query('SELECT anamnesis,therapy FROM visit_results WHERE visit_result_id=?', array($resultId))->row();
    rn_expect($row && $row->anamnesis === null && $row->therapy === null, 'legacy_payload_leaves_notes_null');

    $payload = r7c_payload('notes');
    $payload['anamnesis'] = 'Nyeri kepala sejak dua hari, tidak demam.';
    $payload['therapy'] = 'Paracetamol 500 mg bila perlu.';
    $saved = $service->saveDraft($resultId, $fixture['performer'], 1, $payload);
    rn_expect(!empty($saved['status']) && $saved['status'] === 'success', 'notes_payload_saved');
    $row = $db->query('SELECT anamnesis,therapy FROM visit_results WHERE visit_result_id=?', array($resultId))->row();
    rn_expect($row && $row->anamnesis === $payload['anamnesis'] && $row->therapy === $payload['therapy'], 'notes_values_persisted');

    $unknown = $payload;
    $unknown['unknown_key'] = 'x';
    $rejected = $service->saveDraft($resultId, $fixture['performer'], 2, $unknown);
    rn_expect(($rejected['safe_error_code'] ?? '') === 'INVALID_RESULT_PAYLOAD', 'unknown_key_rejected');

    $measurementId = r7c_measurement($db, $fixture);
    $submitted = $service->submit($resultId, $fixture['performer'], array($measurementId), 'visit-notes-submit-' . $base);
    rn_expect(!empty($submitted['status']) && $submitted['status'] === 'success', 'result_submitted');

    $reviewService = new Clinical_review_service($db);
    $review = $reviewService->review($fixture['request'], $resultId, $fixture['doctor'], 'correction_required', 'Mohon lengkapi anamnesis.', null, 'visit-notes-review-' . $base);
    rn_expect(!empty($review['status']) && $review['status'] === 'success', 'correction_review_created');

    $correction = $service->createCorrectionDraft($fixture['request'], $fixture['performer'], $resultId, 'visit-notes-correction-' . $base);
    rn_expect(!empty($correction['status']) && $correction['status'] === 'success', 'correction_draft_created');
    $correctionId = (int) ($correction['visit_result_id'] ?? 0);
    $copy = $db->query('SELECT anamnesis,therapy FROM visit_results WHERE visit_result_id=?', array($correctionId))->row();
    rn_expect($copy && $copy->anamnesis === $payload['anamnesis'] && $copy->therapy === $payload['therapy'], 'correction_copies_notes');
} catch (Throwable $exception) {
    rn_expect(false, 'result_notes_exception:' . $exception->getMessage());
}
$db->trans_rollback();

echo 'RESULT_NOTES=PASSED=' . $passed . ', FAILED=' . $failed . "\n";
exit($failed > 0 ? 1 : 0);
