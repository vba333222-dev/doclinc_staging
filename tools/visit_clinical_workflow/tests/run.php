<?php

$mode = isset($argv[1]) ? (string) $argv[1] : '';
    if (!in_array($mode, array('schema', 'migrate', 'authorization', 'state_resolver', 'disposition', 'assignment', 'assignment_concurrency', 'placement', 'ci3_smoke', 'ci3_disposition', 'ci3_disposition_full', 'physical_start', 'physical_start_worker', 'row_lock_gate', 'physical_start_concurrency', 'start_first_revision', 'reassign_first_start', 'start_first_reassign', 'vital_signs', 'vital_signs_concurrency', 'result', 'result_concurrency', 'result_submission', 'result_submission_concurrency', 'result_correction', 'result_correction_concurrency', 'review', 'review_concurrency', 'review_cross_request', 'finalization', 'finalization_concurrency', 'amendment_concurrency', 'atomicity', 'timestamp_clock', 'task9_resolver_boundary', 'clinical_closure_schema'), true)) {
    fwrite(STDERR, "Usage: php tools/visit_clinical_workflow/tests/run.php schema|migrate|authorization|state_resolver|disposition|ci3_smoke|ci3_disposition\n");
    exit(2);
}

try {
    require __DIR__ . '/bootstrap.php';
    if ($mode === 'authorization') { require __DIR__ . '/authorization_test.php'; exit(0); }
    if ($mode === 'state_resolver') { require __DIR__ . '/state_resolver_test.php'; exit(0); }
    if ($mode === 'task9_resolver_boundary') { require __DIR__ . '/task9_resolver_boundary_test.php'; exit(0); }
    if ($mode === 'clinical_closure_schema') { require __DIR__ . '/clinical_closure_schema_test.php'; exit(0); }
    if ($mode === 'disposition') { require __DIR__ . '/disposition_test.php'; exit(0); }
    if ($mode === 'assignment') { require __DIR__ . '/assignment_test.php'; exit(0); }
    if ($mode === 'assignment_concurrency') { require __DIR__ . '/assignment_concurrency_test.php'; exit(0); }
    if ($mode === 'placement') { require __DIR__ . '/placement_test.php'; exit(0); }
    if ($mode === 'ci3_smoke') { require __DIR__ . '/ci3_smoke_test.php'; exit(0); }
    if ($mode === 'ci3_disposition') { require __DIR__ . '/ci3_disposition_smoke.php'; exit(0); }
    if ($mode === 'ci3_disposition_full') { require __DIR__ . '/ci3_disposition_full_test.php'; exit(0); }
    if ($mode === 'physical_start') { require __DIR__ . '/physical_start_test.php'; exit(0); }
    if ($mode === 'physical_start_worker') { require __DIR__ . '/physical_start_worker_test.php'; exit(0); }
    if ($mode === 'row_lock_gate') { require __DIR__ . '/row_lock_gate_worker_test.php'; exit(0); }
    if ($mode === 'physical_start_concurrency') { require __DIR__ . '/physical_start_concurrency_test.php'; exit(0); }
    if ($mode === 'start_first_revision') { $argv[1] = 'start_first_revision'; require __DIR__ . '/physical_start_concurrency_test.php'; exit(0); }
    if ($mode === 'reassign_first_start') { $argv[1] = 'reassign_first_start'; require __DIR__ . '/physical_start_concurrency_test.php'; exit(0); }
    if ($mode === 'start_first_reassign') { $argv[1] = 'start_first_reassign'; require __DIR__ . '/physical_start_concurrency_test.php'; exit(0); }
    if ($mode === 'vital_signs') { require __DIR__ . '/vital_signs_test.php'; exit(0); }
    if ($mode === 'vital_signs_concurrency') { require __DIR__ . '/vital_signs_concurrency_test.php'; exit(0); }
    if ($mode === 'result') { require __DIR__ . '/result_test.php'; exit(0); }
    if ($mode === 'result_concurrency') { require __DIR__ . '/result_concurrency_test.php'; exit(0); }
    if ($mode === 'result_submission') { require __DIR__ . '/result_submission_test.php'; exit(0); }
    if ($mode === 'result_submission_concurrency') { require __DIR__ . '/result_submission_concurrency_test.php'; exit(0); }
    if ($mode === 'result_correction') { require __DIR__ . '/result_correction_test.php'; exit(0); }
    if ($mode === 'result_correction_concurrency') { require __DIR__ . '/result_correction_concurrency_test.php'; exit(0); }
    if ($mode === 'review') { require __DIR__ . '/review_test.php'; exit(0); }
    if ($mode === 'review_concurrency') { require __DIR__ . '/review_concurrency_test.php'; exit(0); }
    if ($mode === 'review_cross_request') { require __DIR__ . '/review_cross_request_key_test.php'; exit(0); }
    if ($mode === 'finalization') { require __DIR__ . '/finalization_test.php'; exit(0); }
    if ($mode === 'finalization_concurrency') { require __DIR__ . '/finalization_concurrency_test.php'; exit(0); }
    if ($mode === 'amendment_concurrency') { require __DIR__ . '/amendment_concurrency_test.php'; exit(0); }
    if ($mode === 'atomicity') { require __DIR__ . '/task9_atomicity_test.php'; exit(0); }
    if ($mode === 'timestamp_clock') { require __DIR__ . '/timestamp_clock_test.php'; exit(0); }
    vcw_load_baseline_schema();
    putenv('VCW_BASELINE_LOADED=1');
    if ($mode === 'migrate') {
        $migrations = array(
            dirname(__DIR__, 3) . '/application/migrations/20260826000100_visit_clinical_workflow_foundation.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000200_clinical_amendment_foundation.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000300_visit_performer_assignment_completion.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000400_visit_assignment_operation_foundation.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000500_clinical_review_reviewer_provenance.php',
        );
        foreach ($migrations as $migration) {
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($migration);
            passthru($command . ' --apply', $exitCode);
            if ($exitCode !== 0) { throw new RuntimeException('migration_apply_failed'); }
        }
        putenv('VCW_TASK2_SCHEMA=1');
    }
    require __DIR__ . '/schema_test.php';
    if ($mode === 'migrate') {
        echo "MIGRATION_APPLY=PASS\n";
        echo "EXACT_SCHEMA=PASS\n";
        foreach ($migrations as $migration) {
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($migration);
            passthru($command . ' --apply', $exitCode);
            if ($exitCode !== 0) { throw new RuntimeException('migration_rerun_failed'); }
        }
        echo "MIGRATION_RERUN=PASS\n";
    } else {
        echo "VISIT_WORKFLOW_SCHEMA_TEST=PASS\n";
    }
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'VISIT_WORKFLOW_SCHEMA_TEST=FAIL message=' . $exception->getMessage() . "\n");
    exit(1);
}
