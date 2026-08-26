<?php

require_once __DIR__ . '/bootstrap.php';

if ((getenv('VCW_BASELINE_LOADED') ?: '') !== '1') {
    vcw_load_baseline_schema();
}
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

$task2 = (getenv('VCW_TASK2_SCHEMA') ?: '') === '1';
if (!$task2) {
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
} else {
    foreach (array('visit_dispositions', 'visit_results', 'visit_result_vital_sign_measurements', 'clinical_reviews', 'clinical_amendments', 'clinical_amendment_items') as $table) {
        vcw_assert_same(1, vcw_count_table($table), $table . ' must exist after Task 2 migrations');
    }

    foreach (array(
        array('medicalrecords', 'clinical_finalized_at'),
        array('medicalrecords', 'clinical_finalized_by_user_id'),
        array('request_events', 'domain_event_key'),
        array('request_visit_performer_assignments', 'ended_by_user_id'),
        array('request_visit_performer_assignments', 'end_reason'),
        array('request_visit_performer_assignments', 'completed_at'),
    ) as $field) {
        $statement = vcw_db()->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $statement->bind_param('ss', $field[0], $field[1]);
        $statement->execute();
        $statement->bind_result($count);
        $statement->fetch();
        $statement->close();
        vcw_assert_same(1, (int) $count, $field[0] . '.' . $field[1] . ' must exist after Task 2 migrations');
    }

    $column = function ($table, $name) {
        $statement = vcw_db()->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, DATETIME_PRECISION FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $statement->bind_param('ss', $table, $name);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result->fetch_assoc();
        $statement->close();
        vcw_assert_true(is_array($row), $table . '.' . $name . ' metadata missing');
        return $row;
    };
    $index = function ($table, $name) {
        $statement = vcw_db()->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND NON_UNIQUE = 0');
        $statement->bind_param('ss', $table, $name);
        $statement->execute();
        $statement->bind_result($count);
        $statement->fetch();
        $statement->close();
        return (int) $count;
    };
    $indexColumns = function ($table, $columns) {
        $rows = vcw_table_indexes($table);
        $found = array();
        foreach ($rows as $row) {
            if ((int) $row['NON_UNIQUE'] === 0) {
                $found[$row['INDEX_NAME']][(int) $row['SEQ_IN_INDEX']] = $row['COLUMN_NAME'];
            }
        }
        foreach ($found as $parts) {
            ksort($parts);
            if (array_values($parts) === $columns) {
                return true;
            }
        }
        return false;
    };
    foreach (array(
        array('visit_dispositions', 'active_request_key'),
        array('visit_results', 'active_draft_key'),
    ) as $generated) {
        vcw_assert_true(stripos($column($generated[0], $generated[1])['EXTRA'], 'GENERATED') !== false, $generated[0] . '.' . $generated[1] . ' must be generated');
    }
    foreach (array(
        array('visit_dispositions', 'request_id', 'int(11)'),
        array('visit_results', 'visit_assignment_id', 'bigint(20) unsigned'),
        array('visit_result_vital_sign_measurements', 'measurement_id', 'bigint(20) unsigned'),
        array('clinical_amendments', 'record_id', 'int(11)'),
        array('request_visit_performer_assignments', 'completed_at', 'datetime(6)'),
    ) as $physical) {
        vcw_assert_same($physical[2], $column($physical[0], $physical[1])['COLUMN_TYPE'], $physical[0] . '.' . $physical[1] . ' physical type mismatch');
    }
    vcw_assert_same(6, (int) $column('visit_results', 'created_at')['DATETIME_PRECISION'], 'visit result timestamp precision mismatch');
    vcw_assert_same(6, (int) $column('visit_result_vital_sign_measurements', 'linked_at')['DATETIME_PRECISION'], 'result TTV timestamp precision mismatch');
    vcw_assert_same('longtext', $column('visit_results', 'findings_json')['COLUMN_TYPE'], 'findings JSON storage type mismatch');
    vcw_assert_true(stripos($column('request_visit_performer_assignments', 'status')['COLUMN_TYPE'], "'selesai'") !== false, 'performer status must support selesai');
    vcw_assert_true($index('visit_dispositions', 'uq_visit_disposition_active_request') === 1, 'active disposition uniqueness missing');
    vcw_assert_true($index('visit_results', 'uq_visit_result_active_draft') === 1, 'active result draft uniqueness missing');
    vcw_assert_true($index('clinical_reviews', 'uq_clinical_review_result') === 1, 'clinical review uniqueness missing');
    vcw_assert_true($indexColumns('clinical_amendments', array('record_id', 'sequence_no')), 'amendment sequence uniqueness missing');
    vcw_assert_true($index('request_events', 'uq_request_events_domain_key') === 1, 'domain event key uniqueness missing');
    vcw_assert_true($index('request_visit_performer_assignments', 'uq_visit_performer_active_request') === 1, 'active performer uniqueness missing');
    vcw_assert_same('YES', $column('request_events', 'domain_event_key')['IS_NULLABLE'], 'domain event key must be nullable');

    $expectFailure = function ($sql, $message) {
        $failed = false;
        try { vcw_db()->query($sql); } catch (Throwable $exception) { $failed = true; }
        vcw_assert_true($failed, $message);
    };
    vcw_assert_safe_request_id(10001);
    vcw_db()->query("INSERT INTO users (userId,nama,email,password,role,status) VALUES (10001,'Task2 Synthetic','task2-10001@example.invalid','x','dokter','aktif')");
    vcw_db()->query("INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (10001,'TASK2-PKM',10001,'Task2 Synthetic','dokter','aktif')");
    vcw_db()->query("INSERT INTO requests (request_id,user_id,location,request_status) VALUES (10001,10001,'synthetic','Pending')");
    vcw_db()->query("INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,assigned_at) VALUES (10001,10001,10001,10001,NOW(6))");
    $assignment = vcw_db()->insert_id;
    vcw_db()->query("INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,assigned_at) VALUES (10001,10001,10001,10001,NOW(6))");
    $responsible = vcw_db()->insert_id;
    vcw_db()->query("INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (10001,1,'visit','routine',10001,NOW(6),'task2-disposition-1')");
    $expectFailure("INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (10001,2,'visit','routine',10001,NOW(6),'task2-disposition-2')", 'active disposition uniqueness must reject duplicate');
    vcw_db()->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,findings_json,actions_json,created_at,updated_at,submission_key) VALUES (10001," . (int) $assignment . ",1,10001,10001,'draft','{}','{}',NOW(6),NOW(6),'task2-result-1')");
    $result = vcw_db()->insert_id;
    $expectFailure("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,findings_json,actions_json,created_at,updated_at,submission_key) VALUES (10001," . (int) $assignment . ",2,10001,10001,'draft','{}','{}',NOW(6),NOW(6),'task2-result-2')", 'active result draft uniqueness must reject duplicate');
    $expectFailure("INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,assigned_at) VALUES (10001,10001,10001,10001,NOW(6))", 'active performer uniqueness must reject duplicate');
    vcw_db()->query("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10001," . (int) $result . ",10001," . (int) $responsible . ",'approved',NOW(6),'task2-review-1')");
    $expectFailure("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10001," . (int) $result . ",10001," . (int) $responsible . ",'approved',NOW(6),'task2-review-2')", 'clinical review uniqueness must reject duplicate');
    vcw_db()->query("INSERT INTO medicalrecords (record_id,request_id) VALUES (10001,10001)");
    vcw_db()->query("INSERT INTO clinical_amendments (record_id,request_id,sequence_no,created_by_user_id,reason,created_at,idempotency_key) VALUES (10001,10001,1,10001,'synthetic',NOW(6),'task2-amendment-1')");
    $expectFailure("INSERT INTO clinical_amendments (record_id,request_id,sequence_no,created_by_user_id,reason,created_at,idempotency_key) VALUES (10001,10001,1,10001,'synthetic',NOW(6),'task2-amendment-2')", 'amendment sequence uniqueness must reject duplicate');
    vcw_db()->query("INSERT INTO request_events (request_id,event_type,domain_event_key) VALUES (10001,'task2-null-a',NULL),(10001,'task2-null-b',NULL)");
    vcw_db()->query("INSERT INTO request_events (request_id,event_type,domain_event_key) VALUES (10001,'task2-key-a','task2-domain-key')");
    $expectFailure("INSERT INTO request_events (request_id,event_type,domain_event_key) VALUES (10001,'task2-key-b','task2-domain-key')", 'domain event key uniqueness must reject duplicate');
    echo "TASK2_SCHEMA_CONSTRAINTS=PASS\n";
}

echo "BASELINE_SCHEMA_RECONCILIATION=PASS\n";
echo "APPLICATION_COMPATIBLE_BASELINE=PASS\n";
echo "DATABASE_HOST_LOOPBACK=PASS\n";
echo "REQUEST_QUARANTINE_GUARD=PASS\n";
echo "NEW_VISIT_DOMAIN_TABLES_ABSENT=" . ($task2 ? 'N/A' : 'PASS') . "\n";
