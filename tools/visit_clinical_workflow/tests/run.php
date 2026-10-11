<?php

$mode = isset($argv[1]) ? (string) $argv[1] : '';
    if (!in_array($mode, array('all', 'concurrency', 'reset', 'schema', 'migrate', 'authorization', 'state_resolver', 'clinical_workflow_surface', 'clinical_workflow_surface_runtime', 'database_runtime_guard', 'acceptance_environment', 'clinical_http_surface', 'clinical_http_integration', 'disposition', 'assignment', 'assignment_concurrency', 'placement', 'ci3_smoke', 'ci3_disposition', 'ci3_disposition_full', 'physical_start', 'physical_start_worker', 'row_lock_gate', 'physical_start_concurrency', 'start_first_revision', 'reassign_first_start', 'start_first_reassign', 'vital_signs', 'vital_signs_concurrency', 'result', 'result_concurrency', 'result_submission', 'result_submission_concurrency', 'result_correction', 'result_correction_concurrency', 'review', 'review_concurrency', 'review_cross_request', 'finalization', 'finalization_concurrency', 'amendment_concurrency', 'atomicity', 'timestamp_clock', 'delivery', 'task9_resolver_boundary', 'clinical_closure_schema', 'clinical_closure', 'clinical_closure_legacy_bypass', 'clinical_closure_concurrency', 'clinical_closure_atomicity', 'clinical_closure_resolver_boundary'), true)) {
    fwrite(STDERR, "Usage: php tools/visit_clinical_workflow/tests/run.php schema|migrate|authorization|state_resolver|disposition|ci3_smoke|ci3_disposition\n");
    exit(2);
}

require_once __DIR__ . '/assert.php';
vcw_assert_safe_request_id(10001);
vcw_assert_safe_request_id(10002);

if ($mode === 'concurrency') { require __DIR__ . '/concurrency_test.php'; exit(0); }

if ($mode === 'all') {
    $acceptance = array('reset', 'migrate', 'authorization', 'state_resolver', 'database_runtime_guard', 'clinical_workflow_surface', 'clinical_workflow_surface_runtime', 'clinical_http_surface', 'disposition', 'assignment', 'physical_start', 'physical_start_concurrency', 'start_first_reassign', 'vital_signs', 'vital_signs_concurrency', 'result', 'result_submission', 'result_correction', 'review', 'finalization', 'atomicity', 'timestamp_clock', 'delivery', 'concurrency', 'task9_resolver_boundary', 'clinical_closure_schema', 'clinical_closure', 'clinical_closure_legacy_bypass', 'clinical_closure_atomicity', 'clinical_closure_resolver_boundary');
    vcw_assert_true(count($acceptance) === count(array_unique($acceptance)), 'Acceptance list must not repeat a mode');
    $failures = array();
    foreach ($acceptance as $case) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($case);
        passthru($command, $code);
        if ($code !== 0) { $failures[] = $case; }
    }
    if (!empty($failures)) { fwrite(STDERR, 'TASK13_ALL=FAIL cases=' . implode(',', $failures) . "\n"); exit(1); }
    echo 'TASK13_ALL=PASS cases=' . count($acceptance) . "\n";
    exit(0);
}

try {
    require __DIR__ . '/bootstrap.php';
    if ($mode === 'reset') { $dropped = vcw_reset_disposable_schema(); echo 'DISPOSABLE_RESET=PASS tables=' . $dropped . "\n"; exit(0); }
    if ($mode === 'authorization') { require __DIR__ . '/authorization_test.php'; exit(0); }
    if ($mode === 'state_resolver') { require __DIR__ . '/state_resolver_test.php'; exit(0); }
    if ($mode === 'task9_resolver_boundary') { require __DIR__ . '/task9_resolver_boundary_test.php'; exit(0); }
    if ($mode === 'clinical_workflow_surface') { require __DIR__ . '/clinical_workflow_surface_test.php'; exit(0); }
    if ($mode === 'clinical_workflow_surface_runtime') { require __DIR__ . '/clinical_workflow_surface_runtime_test.php'; exit(0); }
    if ($mode === 'database_runtime_guard') { require __DIR__ . '/database_runtime_guard_test.php'; exit(0); }
    if ($mode === 'acceptance_environment') { require __DIR__ . '/acceptance_environment.php'; exit(0); }
    if ($mode === 'clinical_http_surface') { require __DIR__ . '/clinical_http_surface_test.php'; exit(0); }
    if ($mode === 'clinical_http_integration') { require __DIR__ . '/clinical_http_integration_test.php'; exit(0); }
    if ($mode === 'clinical_closure_schema') { require __DIR__ . '/clinical_closure_schema_test.php'; exit(0); }
    if ($mode === 'clinical_closure') { require __DIR__ . '/clinical_closure_test.php'; exit(0); }
    if ($mode === 'clinical_closure_legacy_bypass') { require __DIR__ . '/clinical_closure_legacy_bypass_test.php'; exit(0); }
    if ($mode === 'clinical_closure_concurrency') { require __DIR__ . '/clinical_closure_concurrency_test.php'; exit(0); }
    if ($mode === 'clinical_closure_atomicity') { require __DIR__ . '/clinical_closure_atomicity_test.php'; exit(0); }
    if ($mode === 'clinical_closure_resolver_boundary') { require __DIR__ . '/clinical_closure_resolver_boundary_test.php'; exit(0); }
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
    if ($mode === 'delivery') { require __DIR__ . '/delivery_test.php'; exit(0); }
    vcw_load_baseline_schema();
    putenv('VCW_BASELINE_LOADED=1');
    if ($mode === 'migrate') {
        $migrations = array(
            dirname(__DIR__, 3) . '/application/migrations/20260826000100_visit_clinical_workflow_foundation.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000200_clinical_amendment_foundation.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000300_visit_performer_assignment_completion.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000400_visit_assignment_operation_foundation.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000500_clinical_review_reviewer_provenance.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000600_clinical_closure_operation_foundation.php',
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
