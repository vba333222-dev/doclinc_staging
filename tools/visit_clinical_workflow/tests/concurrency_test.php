<?php

require_once __DIR__ . '/assert.php';

$cases = array(
    'two_simultaneous_disposition_revisions' => 'start_first_revision',
    'simultaneous_performer_assignments' => 'assignment_concurrency',
    'concurrent_performer_reassignment' => 'assignment_concurrency',
    'disposition_revision_vs_en_route' => 'start_first_revision',
    'performer_reassignment_vs_en_route' => 'reassign_first_start',
    'stale_draft_save' => 'result_concurrency',
    'duplicate_result_submission' => 'result_submission_concurrency',
    'concurrent_correction_result_creation' => 'result_correction_concurrency',
    'duplicate_doctor_review' => 'review_concurrency',
    'review_approval_vs_invalid_newer_result' => 'review_cross_request',
    'duplicate_medical_record_finalization' => 'finalization_concurrency',
    'duplicate_clinical_closure' => 'clinical_closure_concurrency',
    'duplicate_clinical_amendment' => 'amendment_concurrency',
    'placement_transfer_vs_active_visit_assignment' => 'placement',
);

$modeResults = array();
$failures = array();

foreach ($cases as $case => $mode) {
    if (!array_key_exists($mode, $modeResults)) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/run.php') . ' ' . escapeshellarg($mode);
        $output = array();
        $code = 0;
        exec($command, $output, $code);
        $modeResults[$mode] = ($code === 0);
    }
    if ($modeResults[$mode]) {
        echo 'CONCURRENCY_CASE_' . strtoupper($case) . '=PASS mode=' . $mode . "\n";
    } else {
        $failures[] = $case;
        echo 'CONCURRENCY_CASE_' . strtoupper($case) . '=FAIL mode=' . $mode . "\n";
    }
}

if (!empty($failures)) {
    fwrite(STDERR, 'TASK13_CONCURRENCY_MATRIX=FAIL cases=' . implode(',', $failures) . "\n");
    exit(1);
}

echo 'TASK13_CONCURRENCY_MATRIX=PASS cases=' . count($cases) . "\n";
