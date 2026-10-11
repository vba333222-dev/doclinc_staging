<?php
/** Test-only disposable fixture bootstrap for the Task 11 browser suite. */
putenv('VCW_HTTP_DB_HOST=' . (getenv('VCW_HTTP_DB_HOST') ?: (getenv('DB_HOST') ?: 'task11-db')));
putenv('VCW_HTTP_DB_PORT=' . (getenv('VCW_HTTP_DB_PORT') ?: (getenv('DB_PORT') ?: '3306')));
putenv('VCW_HTTP_DB_NAME=' . (getenv('VCW_HTTP_DB_NAME') ?: (getenv('DB_NAME') ?: 'doclinc_visit_test')));
putenv('VCW_HTTP_DB_USER=' . (getenv('VCW_HTTP_DB_USER') ?: (getenv('DB_USER') ?: 'doclinc_test')));
putenv('VCW_HTTP_DB_PASSWORD=' . (getenv('VCW_HTTP_DB_PASSWORD') ?: (getenv('DB_PASS') ?: 'doclinc_test_only')));
require_once dirname(__DIR__, 2) . '/visit_clinical_workflow/tests/clinical_http_acceptance_support.php';

$factory = new Task11FixtureFactory();
$requests = array(
    'visit_happy' => $factory->request('visit'),
    'visit_correction' => $factory->request('visit'),
    'doctor_performer' => $factory->request('visit'),
    'nondoctor_performer' => $factory->request('visit'),
    'nonvisit' => $factory->request('non_visit'),
    'authority' => $factory->request('visit'),
    'stale' => $factory->request('visit'),
    'post_closure' => $factory->request('visit'),
    'flag_off' => $factory->request('visit'),
    'legacy' => $factory->legacyRequest(),
    'historical_completed' => $factory->historicalCompletedRequest(),
);
echo json_encode(array(
    'suffix' => $factory->suffix,
    'facility' => $factory->facility,
    'password' => $factory->password(),
    'actors' => $factory->actors,
    'requests' => $requests,
), JSON_UNESCAPED_SLASHES) . PHP_EOL;
