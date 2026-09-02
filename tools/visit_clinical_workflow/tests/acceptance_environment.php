<?php

require_once __DIR__ . '/bootstrap.php';

if (getenv('DOCLINC_TEST_RUNTIME') !== '1' || $host !== '127.0.0.1' || (int) $port !== 33317 || $database !== 'doclinc_governance_test_task11') {
    throw new RuntimeException('acceptance_environment_guard_failed');
}

function acceptance_schema_preflight()
{
    $required = array(
        'users', 'requests', 'm_puskesmas', 'puskesmas_staff',
        'nakes_facility_placements', 'request_responsible_doctor_assignments',
        'request_visit_performer_assignments', 'request_vital_sign_measurements',
        'visit_dispositions', 'visit_results', 'visit_result_vital_sign_measurements',
        'clinical_reviews', 'clinical_amendments', 'clinical_amendment_items',
        'clinical_closure_operations', 'clinical_schema_migrations', 'request_events',
    );
    foreach ($required as $table) {
        if (vcw_count_table($table) !== 1) {
            throw new RuntimeException('acceptance_schema_missing:' . $table);
        }
    }
}

acceptance_schema_preflight();
echo "ACCEPTANCE_SCHEMA_PREFLIGHT=PASS\n";

