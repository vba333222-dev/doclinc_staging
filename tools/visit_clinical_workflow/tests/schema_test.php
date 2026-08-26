<?php

require_once __DIR__ . '/bootstrap.php';

vcw_load_baseline_schema();
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

foreach (array('requests', 'medicalrecords', 'request_responsible_doctor_assignments', 'request_visit_performer_assignments', 'request_vital_sign_measurements', 'request_events', 'users', 'puskesmas_staff') as $table) {
    vcw_assert_same(1, vcw_count_table($table), $table . ' must exist in the application-compatible baseline');
    echo 'SCHEMA_EVIDENCE_' . strtoupper($table) . '=' . json_encode(array('columns' => vcw_table_columns($table), 'indexes' => vcw_table_indexes($table)), JSON_UNESCAPED_SLASHES) . "\n";
}

foreach (array('visit_dispositions', 'visit_results', 'clinical_reviews', 'clinical_amendments', 'clinical_amendment_items', 'visit_result_vital_sign_measurements') as $table) {
    vcw_assert_same(0, vcw_count_table($table), $table . ' must not pre-exist baseline unexpectedly');
}

foreach (array(
    array('medicalrecords', 'clinical_finalized_at'),
    array('medicalrecords', 'clinical_finalized_by_user_id'),
    array('request_events', 'domain_event_key'),
) as $field) {
    $statement = vcw_db()->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $statement->bind_param('ss', $field[0], $field[1]);
    $statement->execute();
    $statement->bind_result($count);
    $statement->fetch();
    $statement->close();
    vcw_assert_same(0, (int) $count, $field[0] . '.' . $field[1] . ' must not pre-exist baseline unexpectedly');
}

echo "BASELINE_SCHEMA_RECONCILIATION=PASS\n";
echo "APPLICATION_COMPATIBLE_BASELINE=PASS\n";
echo "DATABASE_HOST_LOOPBACK=PASS\n";
echo "REQUEST_QUARANTINE_GUARD=PASS\n";
echo "NEW_VISIT_DOMAIN_TABLES_ABSENT=PASS\n";
