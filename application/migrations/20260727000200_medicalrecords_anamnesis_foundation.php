<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}
ini_set('display_errors', '0');
ini_set('log_errors', '0');

$options = getopt('', array('apply', 'confirm-database:', 'environment:', 'backup-reference:'));
$apply = array_key_exists('apply', $options);
$database = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_NAME') ?: '';
$environment = isset($options['environment']) ? strtolower(trim((string) $options['environment'])) : '';

function anamnesis_migration_fail($code)
{
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . $code . "\n");
	exit(1);
}

if (!$apply) {
	echo "MIGRATION_ID=20260727000200_medicalrecords_anamnesis_foundation\n";
	echo "EXECUTION_MODE=PLAN\nDDL_EXECUTED=false\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
	exit(0);
}
if (!filter_var(getenv('DOCLINC_CLINICAL_ANAMNESIS_SCHEMA_WRITE_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN)) {
	anamnesis_migration_fail('schema_write_disabled');
}
if (!in_array($environment, array('staging', 'uat'), true)) {
	anamnesis_migration_fail('environment_not_allowed');
}
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	anamnesis_migration_fail('database_confirmation_mismatch');
}
$disposable_test = filter_var(getenv('DOCLINC_CLINICAL_SUGGESTION_DISPOSABLE_TEST') ?: false, FILTER_VALIDATE_BOOLEAN);
if (!$disposable_test && !hash_equals('doclinc-staging', $database)) {
	anamnesis_migration_fail('staging_database_required');
}
if ($disposable_test && preg_match('/(?:^|[_-])test(?:$|[_-])/', strtolower($database)) !== 1) {
	anamnesis_migration_fail('disposable_test_database_required');
}
$backup_reference = isset($options['backup-reference']) ? trim((string) $options['backup-reference']) : '';
if ($backup_reference === '' || strlen($backup_reference) > 255 || preg_match('/[\x00-\x1F\x7F]/', $backup_reference) === 1) {
	anamnesis_migration_fail('backup_reference_required');
}

$host = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_HOST') ?: 'localhost';
$port = (int) (getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PORT') ?: 3306);
$user = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_USER') ?: '';
$password = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD');
$allowed_users = array_filter(array_map('trim', explode(',', getenv('DOCLINC_CLINICAL_SUGGESTION_ALLOWED_USERS') ?: '')));
if ($user === '' || $password === false || $password === '' || !in_array($user, $allowed_users, true)) {
	anamnesis_migration_fail('database_identity_not_allowed');
}

function anamnesis_column_signature(mysqli $db, $column)
{
	$stmt = $db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'medicalrecords\' AND COLUMN_NAME=?');
	$stmt->bind_param('s', $column);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function anamnesis_assert_base_schema(mysqli $db)
{
	$table = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medicalrecords'")->fetch_assoc();
	if (!$table || strtoupper((string) $table['ENGINE']) !== 'INNODB') {
		throw new RuntimeException('medicalrecords_schema_mismatch');
	}
	$expected = array(
		'record_id' => array('int(11)', 'NO', 'auto_increment'),
		'request_id' => array('int(11)', 'NO', ''),
		'diagnosis' => array('text', 'YES', ''),
		'treatment' => array('text', 'YES', ''),
		'recommendations' => array('text', 'YES', ''),
		'created_at' => array('timestamp', 'NO', ''),
	);
	foreach ($expected as $column => $signature) {
		$actual = anamnesis_column_signature($db, $column);
		if (!$actual
			|| strtolower((string) $actual['COLUMN_TYPE']) !== $signature[0]
			|| (string) $actual['IS_NULLABLE'] !== $signature[1]
			|| strtolower((string) $actual['EXTRA']) !== $signature[2]) {
			throw new RuntimeException('medicalrecords_schema_mismatch');
		}
	}
	$primary = $db->query("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medicalrecords' AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX")->fetch_all(MYSQLI_ASSOC);
	if (array_column($primary, 'COLUMN_NAME') !== array('record_id')) {
		throw new RuntimeException('medicalrecords_schema_mismatch');
	}
	$request_index = $db->query("SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medicalrecords' AND COLUMN_NAME='request_id'")->fetch_assoc();
	if (!$request_index || (int) $request_index['total'] < 1) {
		throw new RuntimeException('medicalrecords_schema_mismatch');
	}
	$relationship = $db->query("SELECT k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE
		FROM information_schema.KEY_COLUMN_USAGE k
		INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
			ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
		WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME='medicalrecords' AND k.COLUMN_NAME='request_id'
			AND k.REFERENCED_TABLE_NAME IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
	if (count($relationship) !== 1
		|| (string) $relationship[0]['REFERENCED_TABLE_NAME'] !== 'requests'
		|| (string) $relationship[0]['REFERENCED_COLUMN_NAME'] !== 'request_id'
		|| strtoupper((string) $relationship[0]['UPDATE_RULE']) !== 'RESTRICT'
		|| strtoupper((string) $relationship[0]['DELETE_RULE']) !== 'RESTRICT') {
		throw new RuntimeException('medicalrecords_schema_mismatch');
	}
}

function anamnesis_assert_column_signature(mysqli $db)
{
	$column = anamnesis_column_signature($db, 'anamnesis');
	if (!$column
		|| strtolower((string) $column['COLUMN_TYPE']) !== 'text'
		|| (string) $column['IS_NULLABLE'] !== 'YES'
		|| !($column['COLUMN_DEFAULT'] === null || strtoupper((string) $column['COLUMN_DEFAULT']) === 'NULL')
		|| (string) $column['EXTRA'] !== '') {
		throw new RuntimeException('anamnesis_column_signature_mismatch');
	}
	$recommendations = anamnesis_column_signature($db, 'recommendations');
	if (!$recommendations || (int) $column['ORDINAL_POSITION'] !== (int) $recommendations['ORDINAL_POSITION'] + 1) {
		throw new RuntimeException('anamnesis_column_signature_mismatch');
	}
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$lock_name = 'doclinc_medicalrecords_anamnesis_20260727000200';
$lock_acquired = false;
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$actual_database = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$actual_database || !hash_equals($database, (string) $actual_database['database_name'])) {
		throw new RuntimeException('connected_database_mismatch');
	}
	$lock = $db->prepare('SELECT GET_LOCK(?, 10) AS lock_acquired');
	$lock->bind_param('s', $lock_name);
	$lock->execute();
	$lock_result = $lock->get_result()->fetch_assoc();
	if (!$lock_result || (int) $lock_result['lock_acquired'] !== 1) {
		throw new RuntimeException('migration_lock_unavailable');
	}
	$lock_acquired = true;

	anamnesis_assert_base_schema($db);
	$existing = anamnesis_column_signature($db, 'anamnesis');
	if ($existing) {
		anamnesis_assert_column_signature($db);
		echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=false\nALREADY_APPLIED=true\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
		return;
	}

	$before_count = (int) $db->query('SELECT COUNT(*) AS total FROM medicalrecords')->fetch_assoc()['total'];
	$db->query('ALTER TABLE `medicalrecords` ADD COLUMN `anamnesis` TEXT NULL AFTER `recommendations`, ALGORITHM=INPLACE, LOCK=NONE');
	anamnesis_assert_column_signature($db);
	$after_count = (int) $db->query('SELECT COUNT(*) AS total FROM medicalrecords')->fetch_assoc()['total'];
	if ($before_count !== $after_count) {
		throw new RuntimeException('existing_rows_changed');
	}

	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=true\nALREADY_APPLIED=false\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
} catch (Throwable $exception) {
	$safe_codes = array(
		'connected_database_mismatch',
		'migration_lock_unavailable',
		'medicalrecords_schema_mismatch',
		'anamnesis_column_signature_mismatch',
		'existing_rows_changed',
	);
	$code = in_array($exception->getMessage(), $safe_codes, true)
		? $exception->getMessage()
		: 'migration_execution_failed';
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . $code . "\n");
	exit(1);
} finally {
	$password = null;
	if (isset($db) && $db instanceof mysqli) {
		if ($lock_acquired) {
			try {
				$release = $db->prepare('SELECT RELEASE_LOCK(?)');
				$release->bind_param('s', $lock_name);
				$release->execute();
			} catch (Throwable $ignored) {
				// Closing the connection also releases this session-scoped lock.
			}
		}
		$db->close();
	}
}
