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
    vcw_assert_same(1, vcw_count_table('visit_assignment_operations'), 'visit_assignment_operations must exist after receipt migration');
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
    $anyIndexColumns = function ($table, $columns) {
        $rows = vcw_table_indexes($table);
        $found = array();
        foreach ($rows as $row) { $found[$row['INDEX_NAME']][(int) $row['SEQ_IN_INDEX']] = $row['COLUMN_NAME']; }
        foreach ($found as $parts) { ksort($parts); if (array_values($parts) === $columns) { return true; } }
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

    foreach (array(
        array('assignment_operation_id', 'bigint(20) unsigned', 'NO', 'auto_increment'),
        array('request_id', 'int(11)', 'NO', ''),
        array('operation_type', "enum('assign','reassign','cancel_before_start')", 'NO', ''),
        array('actor_user_id', 'int(11)', 'NO', ''),
        array('source_assignment_id', 'bigint(20) unsigned', 'YES', ''),
        array('result_assignment_id', 'bigint(20) unsigned', 'YES', ''),
        array('idempotency_key', 'varchar(191)', 'NO', ''),
        array('operation_fingerprint', 'char(64)', 'NO', ''),
        array('created_at', 'datetime(6)', 'NO', ''),
    ) as $physical) {
        $metadata = $column('visit_assignment_operations', $physical[0]);
        vcw_assert_same($physical[1], $metadata['COLUMN_TYPE'], 'assignment operation physical type mismatch for ' . $physical[0]);
        vcw_assert_same($physical[2], $metadata['IS_NULLABLE'], 'assignment operation nullability mismatch for ' . $physical[0]);
        if ($physical[3] !== '') { vcw_assert_true(stripos($metadata['EXTRA'], $physical[3]) !== false, 'assignment operation auto increment missing'); }
    }
    vcw_assert_same(6, (int) $column('visit_assignment_operations', 'created_at')['DATETIME_PRECISION'], 'assignment operation timestamp precision mismatch');
    vcw_assert_true($index('visit_assignment_operations', 'uq_visit_assignment_operation_idempotency') === 1, 'global operation idempotency uniqueness missing');
    vcw_assert_true($anyIndexColumns('visit_assignment_operations', array('request_id', 'created_at')), 'operation request/created lookup index missing');
    $operationForeignKeys = array();
    $foreignKeyStatement = vcw_db()->query("SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = 'visit_assignment_operations' AND k.REFERENCED_TABLE_NAME IS NOT NULL");
    while ($foreignKey = $foreignKeyStatement->fetch_assoc()) { $operationForeignKeys[$foreignKey['COLUMN_NAME']] = $foreignKey; }
    $foreignKeyStatement->free();
    foreach (array('request_id' => array('requests', 'request_id'), 'actor_user_id' => array('users', 'userId'), 'source_assignment_id' => array('request_visit_performer_assignments', 'visit_assignment_id'), 'result_assignment_id' => array('request_visit_performer_assignments', 'visit_assignment_id')) as $field => $target) {
        vcw_assert_true(isset($operationForeignKeys[$field]), 'operation FK missing for ' . $field);
        vcw_assert_same($target[0], $operationForeignKeys[$field]['REFERENCED_TABLE_NAME'], 'operation FK target table mismatch for ' . $field);
        vcw_assert_same($target[1], $operationForeignKeys[$field]['REFERENCED_COLUMN_NAME'], 'operation FK target column mismatch for ' . $field);
        vcw_assert_same('RESTRICT', $operationForeignKeys[$field]['DELETE_RULE'], 'operation FK delete rule mismatch for ' . $field);
    }

    $expectFailure = function ($sql, $message) {
        $failed = false;
        try { vcw_db()->query($sql); } catch (Throwable $exception) { $failed = true; }
        vcw_assert_true($failed, $message);
    };
    vcw_assert_safe_request_id(10001);
    vcw_db()->query("INSERT INTO users (userId,nama,email,password,role,status) VALUES (10001,'Task2 Synthetic','task2-10001@example.invalid','x','dokter','aktif')");
    vcw_db()->query("INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (10001,'TASK2-PKM',10001,'Task2 Synthetic','dokter','aktif')");
    vcw_db()->query("INSERT INTO requests (request_id,user_id,location,request_status) VALUES (10001,10001,'synthetic','Pending')");
    vcw_db()->query("INSERT INTO requests (request_id,user_id,location,request_status) VALUES (10002,10001,'synthetic','Pending'),(10003,10001,'synthetic','Pending'),(10004,10001,'synthetic','Pending'),(10005,10001,'synthetic','Pending')");
    vcw_db()->query("INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,assigned_at) VALUES (10001,10001,10001,10001,NOW(6))");
    $assignment = vcw_db()->insert_id;
    vcw_db()->query("INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (10001,10001,10001,10001,'diganti',NOW(6))");
    $closedAssignment = vcw_db()->insert_id;
    vcw_db()->query("INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,assigned_at) VALUES (10001,10001,10001,10001,NOW(6))");
    $responsible = vcw_db()->insert_id;
    $scalar = function ($sql) { $result = vcw_db()->query($sql); $row = $result->fetch_row(); $result->free(); return (int) $row[0]; };
    vcw_db()->query("INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,assigned_at) VALUES (10002,10001,10001,10001,NOW(6)),(10003,10001,10001,10001,NOW(6)),(10004,10001,10001,10001,NOW(6)),(10005,10001,10001,10001,NOW(6))");
    vcw_db()->query("INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,assigned_at) VALUES (10002,10001,10001,10001,NOW(6)),(10003,10001,10001,10001,NOW(6)),(10004,10001,10001,10001,NOW(6)),(10005,10001,10001,10001,NOW(6))");
    vcw_db()->query("INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (10001,1,'visit','routine',10001,NOW(6),'task2-disposition-1')");
    $expectFailure("INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (10001,2,'visit','routine',10001,NOW(6),'task2-disposition-2')", 'active disposition uniqueness must reject duplicate');
    $expectFailure("INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (10002,1,'visit',NULL,10001,NOW(6),'task2-disposition-null')", 'visit disposition must require urgency');
    vcw_db()->query("INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (10003,1,'non_visit',NULL,10001,NOW(6),'task2-disposition-nonvisit')");
    $expectFailure("INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (10004,1,'non_visit','routine',10001,NOW(6),'task2-disposition-wrong-urgency')", 'non-visit disposition must reject urgency');
    vcw_db()->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,findings_json,actions_json,created_at,updated_at,submission_key) VALUES (10001," . (int) $assignment . ",1,10001,10001,'draft','{}','{}',NOW(6),NOW(6),'task2-result-1')");
    $result = vcw_db()->insert_id;
    $expectFailure("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,findings_json,actions_json,created_at,updated_at,submission_key) VALUES (10001," . (int) $assignment . ",2,10001,10001,'draft','{}','{}',NOW(6),NOW(6),'task2-result-2')", 'active result draft uniqueness must reject duplicate');
    $expectFailure("INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,assigned_at) VALUES (10001,10001,10001,10001,NOW(6))", 'active performer uniqueness must reject duplicate');
    vcw_db()->query("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10001," . (int) $result . ",10001," . (int) $responsible . ",'approved',NOW(6),'task2-review-1')");
    $expectFailure("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10001," . (int) $result . ",10001," . (int) $responsible . ",'approved',NOW(6),'task2-review-2')", 'clinical review uniqueness must reject duplicate');
    $reviewFixtures = array(10002, 10003, 10004, 10005);
    foreach ($reviewFixtures as $requestId) {
        $assignmentId = $scalar('SELECT visit_assignment_id FROM request_visit_performer_assignments WHERE request_id = ' . (int) $requestId);
        vcw_db()->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,findings_json,actions_json,created_at,updated_at,submission_key) VALUES (" . (int) $requestId . "," . $assignmentId . ",1,10001,10001,'submitted','{}','{}',NOW(6),NOW(6),'review-result-" . (int) $requestId . "')");
        $reviewResult = vcw_db()->insert_id;
        $reviewResponsible = $scalar('SELECT responsible_assignment_id FROM request_responsible_doctor_assignments WHERE request_id = ' . (int) $requestId);
        if ($requestId === 10002) { vcw_db()->query("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10002," . $reviewResult . ",10001," . $reviewResponsible . ",'approved',NOW(6),'review-approved-null')"); }
        if ($requestId === 10003) { vcw_db()->query("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,correction_reason,reviewed_at,idempotency_key) VALUES (10003," . $reviewResult . ",10001," . $reviewResponsible . ",'correction_required','Perlu melengkapi temuan',NOW(6),'review-correction-valid')"); }
        if ($requestId === 10004) { $expectFailure("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10004," . $reviewResult . ",10001," . $reviewResponsible . ",'correction_required',NOW(6),'review-correction-null')", 'correction reason must be required'); }
        if ($requestId === 10005) { $expectFailure("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,correction_reason,reviewed_at,idempotency_key) VALUES (10005," . $reviewResult . ",10001," . $reviewResponsible . ",'correction_required','',NOW(6),'review-correction-empty')", 'correction reason must not be empty'); }
    }
    vcw_db()->query("INSERT INTO medicalrecords (record_id,request_id) VALUES (10001,10001)");
    vcw_db()->query("INSERT INTO clinical_amendments (record_id,request_id,sequence_no,created_by_user_id,reason,created_at,idempotency_key) VALUES (10001,10001,1,10001,'synthetic',NOW(6),'task2-amendment-1')");
    $expectFailure("INSERT INTO clinical_amendments (record_id,request_id,sequence_no,created_by_user_id,reason,created_at,idempotency_key) VALUES (10001,10001,1,10001,'synthetic',NOW(6),'task2-amendment-2')", 'amendment sequence uniqueness must reject duplicate');
    vcw_db()->query("INSERT INTO request_events (request_id,event_type,domain_event_key) VALUES (10001,'task2-null-a',NULL),(10001,'task2-null-b',NULL)");
    vcw_db()->query("INSERT INTO request_events (request_id,event_type,domain_event_key) VALUES (10001,'task2-key-a','task2-domain-key')");
    $expectFailure("INSERT INTO request_events (request_id,event_type,domain_event_key) VALUES (10001,'task2-key-b','task2-domain-key')", 'domain event key uniqueness must reject duplicate');

    $operationInsert = function ($requestId, $operationType, $actorUserId, $sourceId, $resultId, $key, $fingerprint) use ($expectFailure) {
        $source = $sourceId === null ? 'NULL' : (string) (int) $sourceId;
        $result = $resultId === null ? 'NULL' : (string) (int) $resultId;
        $sql = "INSERT INTO visit_assignment_operations (request_id,operation_type,actor_user_id,source_assignment_id,result_assignment_id,idempotency_key,operation_fingerprint,created_at) VALUES (" . (int) $requestId . ",'" . $operationType . "'," . (int) $actorUserId . "," . $source . "," . $result . ",'" . $key . "','" . $fingerprint . "',NOW(6))";
        vcw_db()->query($sql);
    };
    $operationInsert(10001, 'assign', 10001, null, $assignment, 'task5-op-assign', str_repeat('a', 64));
    $operationInsert(10001, 'reassign', 10001, $closedAssignment, $assignment, 'task5-op-reassign', str_repeat('b', 64));
    $operationInsert(10001, 'cancel_before_start', 10001, $assignment, null, 'task5-op-cancel', str_repeat('c', 64));
    $expectFailure("INSERT INTO visit_assignment_operations (request_id,operation_type,actor_user_id,source_assignment_id,result_assignment_id,idempotency_key,operation_fingerprint,created_at) VALUES (10001,'assign',10001," . (int) $closedAssignment . "," . (int) $assignment . ",'task5-op-invalid-assign-source','" . str_repeat('d', 64) . "',NOW(6))", 'assign receipt must reject source assignment');
    $expectFailure("INSERT INTO visit_assignment_operations (request_id,operation_type,actor_user_id,source_assignment_id,result_assignment_id,idempotency_key,operation_fingerprint,created_at) VALUES (10001,'assign',10001,NULL,NULL,'task5-op-invalid-assign-result','" . str_repeat('e', 64) . "',NOW(6))", 'assign receipt must require result assignment');
    $expectFailure("INSERT INTO visit_assignment_operations (request_id,operation_type,actor_user_id,source_assignment_id,result_assignment_id,idempotency_key,operation_fingerprint,created_at) VALUES (10001,'reassign',10001,NULL," . (int) $assignment . ",'task5-op-invalid-reassign-source','" . str_repeat('f', 64) . "',NOW(6))", 'reassign receipt must require source assignment');
    $expectFailure("INSERT INTO visit_assignment_operations (request_id,operation_type,actor_user_id,source_assignment_id,result_assignment_id,idempotency_key,operation_fingerprint,created_at) VALUES (10001,'reassign',10001," . (int) $closedAssignment . ",NULL,'task5-op-invalid-reassign-result','" . str_repeat('1', 64) . "',NOW(6))", 'reassign receipt must require result assignment');
    $expectFailure("INSERT INTO visit_assignment_operations (request_id,operation_type,actor_user_id,source_assignment_id,result_assignment_id,idempotency_key,operation_fingerprint,created_at) VALUES (10001,'reassign',10001," . (int) $assignment . "," . (int) $assignment . ",'task5-op-invalid-reassign-same','" . str_repeat('2', 64) . "',NOW(6))", 'reassign receipt must reject identical assignments');
    $expectFailure("INSERT INTO visit_assignment_operations (request_id,operation_type,actor_user_id,source_assignment_id,result_assignment_id,idempotency_key,operation_fingerprint,created_at) VALUES (10001,'cancel_before_start',10001,NULL,NULL,'task5-op-invalid-cancel-source','" . str_repeat('3', 64) . "',NOW(6))", 'cancel receipt must require source assignment');
    $expectFailure("INSERT INTO visit_assignment_operations (request_id,operation_type,actor_user_id,source_assignment_id,result_assignment_id,idempotency_key,operation_fingerprint,created_at) VALUES (10001,'cancel_before_start',10001," . (int) $assignment . "," . (int) $closedAssignment . ",'task5-op-invalid-cancel-result','" . str_repeat('4', 64) . "',NOW(6))", 'cancel receipt must reject result assignment');
    $expectFailure("INSERT INTO visit_assignment_operations (request_id,operation_type,actor_user_id,source_assignment_id,result_assignment_id,idempotency_key,operation_fingerprint,created_at) VALUES (10002,'assign',10001,NULL," . (int) $assignment . ",'task5-op-assign','" . str_repeat('5', 64) . "',NOW(6))", 'idempotency key must be globally unique');
    $operationInsert(10002, 'assign', 10001, null, $scalar('SELECT visit_assignment_id FROM request_visit_performer_assignments WHERE request_id = 10002'), 'task5-op-assign-other', str_repeat('6', 64));
    echo "ASSIGNMENT_OPERATION_SCHEMA=PASS\n";
    echo "ASSIGNMENT_OPERATION_SHAPE_CONSTRAINTS=PASS\n";
    echo "GLOBAL_IDEMPOTENCY_KEY_UNIQUENESS=PASS\n";
    echo "TASK2_SCHEMA_CONSTRAINTS=PASS\n";

    if ((getenv('VCW_TASK8B_SCHEMA') ?: '') === '1') {
        $reviewColumns = array();
        $columnQuery = vcw_db()->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinical_reviews'");
        while ($columnRow = $columnQuery->fetch_assoc()) { $reviewColumns[$columnRow['COLUMN_NAME']] = $columnRow; }
        vcw_assert_true(isset($reviewColumns['reviewer_visit_assignment_id']), 'Task 8B performer provenance column missing');
        vcw_assert_same('YES', $reviewColumns['responsible_assignment_id']['IS_NULLABLE'] ?? null, 'Task 8B Responsible Doctor provenance must be nullable');
        vcw_assert_same('bigint(20) unsigned', $reviewColumns['reviewer_visit_assignment_id']['COLUMN_TYPE'] ?? null, 'Task 8B performer provenance type mismatch');
        $performerFk = vcw_db()->query("SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinical_reviews' AND COLUMN_NAME = 'reviewer_visit_assignment_id' AND REFERENCED_TABLE_NAME = 'request_visit_performer_assignments' AND REFERENCED_COLUMN_NAME = 'visit_assignment_id'")->fetch_assoc();
        vcw_assert_same(1, (int) ($performerFk['c'] ?? 0), 'Task 8B performer provenance FK missing');
        $createReview = vcw_db()->query('SHOW CREATE TABLE clinical_reviews')->fetch_assoc();
        $createSql = strtolower((string) ($createReview['Create Table'] ?? ''));
        vcw_assert_true(strpos($createSql, 'responsible_assignment_id` is not null') !== false && strpos($createSql, 'reviewer_visit_assignment_id` is not null') !== false, 'Task 8B XOR provenance CHECK missing');
        vcw_assert_true(strpos($createSql, 'uq_clinical_review_result') !== false && strpos($createSql, 'uq_clinical_review_idempotency') !== false, 'Task 8B existing review uniques missing');
        $performerAssignment = (int) $scalar('SELECT visit_assignment_id FROM request_visit_performer_assignments WHERE request_id = 10002 LIMIT 1');
        $performerResult = 0;
        vcw_db()->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,findings_json,actions_json,created_at,updated_at) VALUES (10002," . $performerAssignment . ",2,10001,10001,'draft','[]','[]',NOW(6),NOW(6))");
        $performerResult = (int) vcw_db()->insert_id;
        vcw_db()->query("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,reviewer_visit_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10002," . $performerResult . ",10001,NULL," . $performerAssignment . ",'approved',NOW(6),'task8b-performer-valid')");
        $expectFailure("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,reviewer_visit_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10002," . $performerResult . ",10001,NULL,NULL,'approved',NOW(6),'task8b-no-provenance')", 'XOR must reject missing provenance');
        $expectFailure("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,reviewer_visit_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10002," . $performerResult . ",10001," . (int) $responsible . "," . $performerAssignment . ",'approved',NOW(6),'task8b-double-provenance')", 'XOR must reject double provenance');
        $expectFailure("INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,reviewer_visit_assignment_id,decision,reviewed_at,idempotency_key) VALUES (10002," . $performerResult . ",10001,NULL,999999999,'approved',NOW(6),'task8b-invalid-performer-fk')", 'performer provenance FK must reject unknown assignment');
        vcw_assert_same('INT', strtoupper(substr($reviewColumns['reviewer_user_id']['COLUMN_TYPE'], 0, 3)), 'reviewer user type must remain integer');
        $assignmentColumns = vcw_table_columns('request_visit_performer_assignments');
        foreach ($assignmentColumns as $assignmentColumn) { vcw_assert_true($assignmentColumn['COLUMN_NAME'] !== 'completion_review_id', 'completion_review_id must not be added'); }
        echo "TASK8B_PROVENANCE_DB_CONSTRAINTS=PASS\n";
        echo "TASK8B_SCHEMA_TARGET_ASSERTIONS=PASS\n";
    }
}

echo "BASELINE_SCHEMA_RECONCILIATION=PASS\n";
echo "APPLICATION_COMPATIBLE_BASELINE=PASS\n";
echo "DATABASE_HOST_LOOPBACK=PASS\n";
echo "REQUEST_QUARANTINE_GUARD=PASS\n";
echo "NEW_VISIT_DOMAIN_TABLES_ABSENT=" . ($task2 ? 'N/A' : 'PASS') . "\n";
