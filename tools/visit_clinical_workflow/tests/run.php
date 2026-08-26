<?php

$mode = isset($argv[1]) ? (string) $argv[1] : '';
if (!in_array($mode, array('schema', 'migrate', 'authorization', 'state_resolver'), true)) {
    fwrite(STDERR, "Usage: php tools/visit_clinical_workflow/tests/run.php schema|migrate|authorization|state_resolver\n");
    exit(2);
}

try {
    require __DIR__ . '/bootstrap.php';
    if ($mode === 'authorization') { require __DIR__ . '/authorization_test.php'; exit(0); }
    if ($mode === 'state_resolver') { require __DIR__ . '/state_resolver_test.php'; exit(0); }
    vcw_load_baseline_schema();
    putenv('VCW_BASELINE_LOADED=1');
    if ($mode === 'migrate') {
        $migrations = array(
            dirname(__DIR__, 3) . '/application/migrations/20260826000100_visit_clinical_workflow_foundation.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000200_clinical_amendment_foundation.php',
            dirname(__DIR__, 3) . '/application/migrations/20260826000300_visit_performer_assignment_completion.php',
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
