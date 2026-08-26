<?php

require_once __DIR__ . '/bootstrap.php';

vcw_assert_true(in_array(getenv('VCW_DB_HOST') ?: '127.0.0.1', array('127.0.0.1', 'localhost'), true), 'Database host must be loopback');

foreach (array(47, 52) as $requestId) {
    $rejected = false;
    try {
        vcw_assert_safe_request_id($requestId);
    } catch (RuntimeException $exception) {
        $rejected = true;
    }
    vcw_assert_true($rejected, 'Quarantine guard must reject request ' . $requestId);
}

foreach (array('visit_dispositions', 'visit_results', 'clinical_reviews') as $table) {
    vcw_assert_same(0, vcw_count_table($table), $table . ' must not pre-exist baseline unexpectedly');
}

echo "BASELINE_SCHEMA_RECONCILIATION=PASS\n";
echo "DATABASE_HOST_LOOPBACK=PASS\n";
echo "REQUEST_QUARANTINE_GUARD=PASS\n";
echo "NEW_VISIT_DOMAIN_TABLES_ABSENT=PASS\n";
