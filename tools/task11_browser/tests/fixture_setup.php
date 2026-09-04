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
$factory = new Task11FixtureFactory();
$doctorRequest = $factory->request('visit');
// Initial prerequisite setup: make the doctor-performer the responsible
// clinician for its dedicated self-review scenario.
task11_sql('UPDATE requests SET responsible_doctor_user_id=? WHERE request_id=?', array($factory->actor('DOCTOR_PERFORMER')['id'], $doctorRequest));
// The canonical schema uses `diganti` for a superseded responsible-doctor
// assignment. Keep this setup within the frozen enum rather than inventing a
// test-only status value.
task11_sql('UPDATE request_responsible_doctor_assignments SET status="diganti" WHERE request_id=?', array($doctorRequest));
task11_sql('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($doctorRequest, $factory->actor('DOCTOR_PERFORMER')['id'], $factory->actor('DOCTOR_PERFORMER')['id'], $factory->actor('DOCTOR_PERFORMER')['id'], 'aktif'));
$requests = array(
    'visit_happy' => $factory->request('visit'), 'visit_correction' => $factory->request('visit'),
    'doctor_performer' => $doctorRequest, 'nondoctor_performer' => $factory->request('visit'),
    'nonvisit' => $factory->request('non_visit'), 'authority' => $factory->request('visit'),
    'stale' => $factory->request('visit'), 'idempotency' => $factory->request('visit'), 'post_closure' => $factory->request('visit'),
    'flag_off' => $factory->request('visit'), 'legacy' => $factory->legacyRequest(),
    'historical_completed' => $factory->historicalCompletedRequest(),
);
$special = array(
    'INACTIVE' => $factory->specialActor('Task11 Inactive Actor', 'dokter', 'nonaktif', $factory->facility),
    'UNCLASSIFIED' => $factory->specialActor('Task11 Unclassified Actor', '', 'aktif', $factory->facility),
);
echo json_encode(array('suffix' => $factory->suffix, 'facility' => $factory->facility, 'password' => $factory->password(), 'actors' => $factory->actors, 'special' => $special, 'requests' => $requests), JSON_UNESCAPED_SLASHES) . PHP_EOL;
