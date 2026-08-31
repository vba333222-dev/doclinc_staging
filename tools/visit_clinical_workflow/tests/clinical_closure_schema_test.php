<?php

require_once __DIR__ . '/assert.php';

$db = vcw_db();
$table = 'clinical_closure_operations';

$exists = $db->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinical_closure_operations'")->fetch_assoc();
vcw_assert_same(1, (int) $exists['c'], 'clinical_closure_operations table must exist after the closure migration');

function closure_schema_columns(mysqli $db, $table)
{
    $statement = $db->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, DATETIME_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
    $statement->bind_param('s', $table);
    $statement->execute();
    $result = $statement->get_result();
    $rows = array();
    while ($row = $result->fetch_assoc()) { $rows[$row['COLUMN_NAME']] = $row; }
    $statement->close();
    return $rows;
}

function closure_schema_indexes(mysqli $db, $table)
{
    $statement = $db->prepare('SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX');
    $statement->bind_param('s', $table);
    $statement->execute();
    $result = $statement->get_result();
    $rows = array();
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    $statement->close();
    return $rows;
}

function closure_schema_fks(mysqli $db, $table)
{
    $statement = $db->prepare('SELECT k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS r JOIN information_schema.KEY_COLUMN_USAGE k ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE r.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? ORDER BY k.CONSTRAINT_NAME');
    $statement->bind_param('s', $table);
    $statement->execute();
    $result = $statement->get_result();
    $rows = array();
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    $statement->close();
    return $rows;
}

$columns = closure_schema_columns($db, $table);
foreach (array('closure_operation_id', 'request_id', 'closure_mode', 'idempotency_key', 'operation_fingerprint', 'closed_by_user_id', 'authority_source', 'responsible_assignment_id', 'visit_assignment_id', 'closed_at') as $column) {
    vcw_assert_true(isset($columns[$column]), 'Missing closure column ' . $column);
}
vcw_assert_same('bigint(20) unsigned', strtolower($columns['closure_operation_id']['COLUMN_TYPE']), 'Closure operation identity type');
vcw_assert_same('int(11)', strtolower($columns['request_id']['COLUMN_TYPE']), 'Closure request type');
vcw_assert_same('int(11)', strtolower($columns['closed_by_user_id']['COLUMN_TYPE']), 'Closure actor type');
vcw_assert_same('bigint(20) unsigned', strtolower($columns['responsible_assignment_id']['COLUMN_TYPE']), 'Closure responsible assignment type');
vcw_assert_same('bigint(20) unsigned', strtolower($columns['visit_assignment_id']['COLUMN_TYPE']), 'Closure visit assignment type');
vcw_assert_same('char(64)', strtolower($columns['operation_fingerprint']['COLUMN_TYPE']), 'Closure fingerprint type');
vcw_assert_same('datetime(6)', strtolower($columns['closed_at']['COLUMN_TYPE']), 'Closure timestamp precision');
vcw_assert_same('ascii_bin', strtolower($columns['operation_fingerprint']['COLLATION_NAME']), 'Closure fingerprint collation');
vcw_assert_true(strpos(strtolower($columns['closure_mode']['COLUMN_TYPE']), "'visit'") !== false && strpos(strtolower($columns['closure_mode']['COLUMN_TYPE']), "'non_visit'") !== false, 'Closure mode domain');
vcw_assert_true(strpos(strtolower($columns['authority_source']['COLUMN_TYPE']), "'responsible_doctor'") !== false && strpos(strtolower($columns['authority_source']['COLUMN_TYPE']), "'doctor_visit_performer'") !== false, 'Closure authority domain');
vcw_assert_same('6', (string) $columns['closed_at']['DATETIME_PRECISION'], 'Closure DATETIME precision');

$indexes = closure_schema_indexes($db, $table);
$indexMap = array();
foreach ($indexes as $index) { $indexMap[$index['INDEX_NAME'] . ':' . $index['COLUMN_NAME']] = $index; }
vcw_assert_true(isset($indexMap['PRIMARY:closure_operation_id']), 'Closure primary key');
vcw_assert_true(isset($indexMap['uq_clinical_closure_operation_request:request_id']) && (int) $indexMap['uq_clinical_closure_operation_request:request_id']['NON_UNIQUE'] === 0, 'Closure request uniqueness');
vcw_assert_true(isset($indexMap['uq_clinical_closure_operation_idempotency:idempotency_key']) && (int) $indexMap['uq_clinical_closure_operation_idempotency:idempotency_key']['NON_UNIQUE'] === 0, 'Closure global idempotency uniqueness');

$fks = closure_schema_fks($db, $table);
vcw_assert_true(count($fks) >= 3, 'Closure foreign keys');
foreach ($fks as $fk) {
    vcw_assert_same('RESTRICT', strtoupper($fk['DELETE_RULE']), 'Closure delete restriction');
    vcw_assert_same('RESTRICT', strtoupper($fk['UPDATE_RULE']), 'Closure update restriction');
}

$checks = $db->query("SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'clinical_closure_operations'");
$checkText = '';
while ($row = $checks->fetch_assoc()) { $checkText .= ' ' . strtolower($row['CHECK_CLAUSE']); }
vcw_assert_true(strpos($checkText, 'non_visit') !== false && strpos($checkText, 'doctor_visit_performer') !== false, 'Closure provenance shape checks');
vcw_assert_true(strpos($checkText, 'responsible_doctor') !== false, 'Closure responsible provenance check');

$engine = $db->query("SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinical_closure_operations'")->fetch_assoc();
vcw_assert_same('InnoDB', $engine['ENGINE'], 'Closure storage engine');
echo "CLINICAL_CLOSURE_SCHEMA=PASS\nDESTRUCTIVE_STATEMENTS=0\nBACKFILL_ROWS=0\n";
