<?php

$mode = isset($argv[1]) ? (string) $argv[1] : '';
if ($mode !== 'schema') {
    fwrite(STDERR, "Usage: php tools/visit_clinical_workflow/tests/run.php schema\n");
    exit(2);
}

try {
    require __DIR__ . '/schema_test.php';
    echo "VISIT_WORKFLOW_SCHEMA_TEST=PASS\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'VISIT_WORKFLOW_SCHEMA_TEST=FAIL message=' . $exception->getMessage() . "\n");
    exit(1);
}
