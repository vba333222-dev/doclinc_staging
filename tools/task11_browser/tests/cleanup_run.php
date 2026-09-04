<?php
putenv('VCW_HTTP_DB_HOST=' . (getenv('VCW_HTTP_DB_HOST') ?: (getenv('DB_HOST') ?: 'task11-db')));
putenv('VCW_HTTP_DB_PORT=' . (getenv('VCW_HTTP_DB_PORT') ?: (getenv('DB_PORT') ?: '3306')));
putenv('VCW_HTTP_DB_NAME=' . (getenv('VCW_HTTP_DB_NAME') ?: (getenv('DB_NAME') ?: 'doclinc_visit_test')));
putenv('VCW_HTTP_DB_USER=' . (getenv('VCW_HTTP_DB_USER') ?: (getenv('DB_USER') ?: 'doclinc_test')));
putenv('VCW_HTTP_DB_PASSWORD=' . (getenv('VCW_HTTP_DB_PASSWORD') ?: (getenv('DB_PASS') ?: 'doclinc_test_only')));
require_once dirname(__DIR__, 2) . '/visit_clinical_workflow/tests/clinical_http_acceptance_support.php';
$runId = getenv('TASK11_RUN_ID') ?: '';
task11_assert($runId !== '', 'task11_run_id_required');
task11_cleanup_run($runId);
echo "task11_cleanup_complete\n";
