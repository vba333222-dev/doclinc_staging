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

function migration_fail($code)
{
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . $code . "\n");
	exit(1);
}

if (!$apply) {
	echo "MIGRATION_ID=20260726000100_clinical_suggestions_uat_foundation\n";
	echo "EXECUTION_MODE=PLAN\nDDL_EXECUTED=false\nIMPORTS_PACKAGE_DATA=false\nENABLES_RUNTIME=false\n";
	exit(0);
}
if (!filter_var(getenv('DOCLINC_CLINICAL_SUGGESTION_SCHEMA_WRITE_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN)) {
	migration_fail('schema_write_disabled');
}
if (!in_array($environment, array('staging', 'uat'), true)) {
	migration_fail('environment_not_allowed');
}
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	migration_fail('database_confirmation_mismatch');
}
if (!isset($options['backup-reference']) || trim((string) $options['backup-reference']) === ''
	|| preg_match('/[\x00-\x1F\x7F]/', (string) $options['backup-reference']) === 1) {
	migration_fail('backup_reference_required');
}

$host = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_HOST') ?: 'localhost';
$port = (int) (getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PORT') ?: 3306);
$user = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_USER') ?: '';
$password = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD');
$allowed_users = array_filter(array_map('trim', explode(',', getenv('DOCLINC_CLINICAL_SUGGESTION_ALLOWED_USERS') ?: '')));
if ($user === '' || $password === false || $password === '' || !in_array($user, $allowed_users, true)) {
	migration_fail('database_identity_not_allowed');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$migration_lock_name = 'doclinc_clinical_suggestions_uat_foundation';
$migration_lock_acquired = false;
$created_tables = array();
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$actual = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$actual || !hash_equals($database, (string) $actual['database_name'])) {
		throw new RuntimeException('connected_database_mismatch');
	}
	$lock_statement = $db->prepare('SELECT GET_LOCK(?, 10) AS lock_acquired');
	$lock_statement->bind_param('s', $migration_lock_name);
	$lock_statement->execute();
	$lock_result = $lock_statement->get_result()->fetch_assoc();
	if (!$lock_result || (int) $lock_result['lock_acquired'] !== 1) {
		throw new RuntimeException('migration_lock_unavailable');
	}
	$migration_lock_acquired = true;
	$expected_columns = array(
		'clinical_suggestion_import_batches' => array('suggestion_import_batch_id','batch_reference','package_id','package_version','package_checksum','package_snapshot_sha256','environment_scope','batch_state','term_count','alias_count','created_at','rolled_back_at'),
		'clinical_suggestion_terms' => array('suggestion_term_id','suggestion_import_batch_id','term_type','reference_key','term_code','preferred_label','normalized_label','source_name','source_version','source_dataset','source_governance_status','environment_scope','active_state','created_at'),
		'clinical_suggestion_aliases' => array('suggestion_alias_id','suggestion_term_id','alias_label','normalized_alias','source_name','source_version','source_dataset','source_governance_status','language_code','active_state','created_at'),
	);
	$expected_indexes = array(
		'clinical_suggestion_import_batches' => array('PRIMARY','idx_clinical_suggestion_batch_state','uq_clinical_suggestion_batch_reference'),
		'clinical_suggestion_terms' => array('PRIMARY','idx_clinical_suggestion_term_batch','idx_clinical_suggestion_term_code','idx_clinical_suggestion_term_search','uq_clinical_suggestion_term_natural'),
		'clinical_suggestion_aliases' => array('PRIMARY','idx_clinical_suggestion_alias_search','uq_clinical_suggestion_alias_natural'),
	);
	$expected_constraints = array(
		'clinical_suggestion_import_batches' => array('PRIMARY','chk_clinical_suggestion_batch_environment','chk_clinical_suggestion_batch_state','uq_clinical_suggestion_batch_reference'),
		'clinical_suggestion_terms' => array('PRIMARY','chk_clinical_suggestion_term_active','chk_clinical_suggestion_term_environment','chk_clinical_suggestion_term_type','fk_clinical_suggestion_term_batch','uq_clinical_suggestion_term_natural'),
		'clinical_suggestion_aliases' => array('PRIMARY','chk_clinical_suggestion_alias_active','fk_clinical_suggestion_alias_term','uq_clinical_suggestion_alias_natural'),
	);
	$schema_comment = 'doclinc_clinical_suggestions_uat_foundation_20260726000100';
	$existing_table_count = 0;
	foreach ($expected_columns as $table_name => $columns) {
		$stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
		$stmt->bind_param('s', $table_name);
		$stmt->execute();
		$actual_columns = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
		if (count($actual_columns) > 0) {
			$existing_table_count++;
			$stmt = $db->prepare('SELECT ENGINE,TABLE_COLLATION,TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
			$stmt->bind_param('s', $table_name);
			$stmt->execute();
			$table_definition = $stmt->get_result()->fetch_assoc();
			$stmt = $db->prepare('SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME');
			$stmt->bind_param('s', $table_name);
			$stmt->execute();
			$actual_indexes = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'INDEX_NAME');
			$stmt = $db->prepare('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME');
			$stmt->bind_param('s', $table_name);
			$stmt->execute();
			$actual_constraints = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'CONSTRAINT_NAME');
			$wanted_indexes = $expected_indexes[$table_name];
			$wanted_constraints = $expected_constraints[$table_name];
			sort($actual_indexes);
			sort($actual_constraints);
			sort($wanted_indexes);
			sort($wanted_constraints);
			if ($actual_columns !== $columns || !$table_definition
				|| strtoupper((string) $table_definition['ENGINE']) !== 'INNODB'
				|| (string) $table_definition['TABLE_COLLATION'] !== 'utf8mb4_general_ci'
				|| (string) $table_definition['TABLE_COMMENT'] !== $schema_comment
				|| $actual_indexes !== $wanted_indexes
				|| $actual_constraints !== $wanted_constraints) {
				throw new RuntimeException('existing_schema_mismatch');
			}
		}
	}
	if ($existing_table_count > 0 && $existing_table_count !== count($expected_columns)) throw new RuntimeException('partial_schema_collision');
	if ($existing_table_count === count($expected_columns)) {
		echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=false\nALREADY_APPLIED=true\nIMPORTS_PACKAGE_DATA=false\nENABLES_RUNTIME=false\n";
		return;
	}

	$statements = array(
		'clinical_suggestion_import_batches' => "CREATE TABLE `clinical_suggestion_import_batches` (
			`suggestion_import_batch_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`batch_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`package_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`package_version` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`package_checksum` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`package_snapshot_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`environment_scope` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`batch_state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'applied',
			`term_count` INT UNSIGNED NOT NULL DEFAULT 0,
			`alias_count` INT UNSIGNED NOT NULL DEFAULT 0,
			`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
			`rolled_back_at` DATETIME(6) NULL,
			PRIMARY KEY (`suggestion_import_batch_id`),
			UNIQUE KEY `uq_clinical_suggestion_batch_reference` (`batch_reference`),
			KEY `idx_clinical_suggestion_batch_state` (`environment_scope`, `batch_state`),
			CONSTRAINT `chk_clinical_suggestion_batch_environment` CHECK (`environment_scope` IN ('staging','uat')),
			CONSTRAINT `chk_clinical_suggestion_batch_state` CHECK (`batch_state` IN ('applied','rolled_back'))
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='doclinc_clinical_suggestions_uat_foundation_20260726000100'",
		'clinical_suggestion_terms' => "CREATE TABLE `clinical_suggestion_terms` (
			`suggestion_term_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`suggestion_import_batch_id` BIGINT UNSIGNED NOT NULL,
			`term_type` VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`reference_key` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
			`term_code` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`preferred_label` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`normalized_label` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`source_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`source_version` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`source_dataset` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`source_governance_status` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`environment_scope` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`active_state` TINYINT UNSIGNED NOT NULL DEFAULT 1,
			`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
			PRIMARY KEY (`suggestion_term_id`),
			UNIQUE KEY `uq_clinical_suggestion_term_natural` (`term_type`, `reference_key`),
			KEY `idx_clinical_suggestion_term_search` (`term_type`, `environment_scope`, `active_state`, `normalized_label`),
			KEY `idx_clinical_suggestion_term_code` (`term_type`, `environment_scope`, `active_state`, `term_code`),
			KEY `idx_clinical_suggestion_term_batch` (`suggestion_import_batch_id`),
			CONSTRAINT `fk_clinical_suggestion_term_batch` FOREIGN KEY (`suggestion_import_batch_id`) REFERENCES `clinical_suggestion_import_batches` (`suggestion_import_batch_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
			CONSTRAINT `chk_clinical_suggestion_term_type` CHECK (`term_type` IN ('complaint','symptom','diagnosis','medicine')),
			CONSTRAINT `chk_clinical_suggestion_term_environment` CHECK (`environment_scope` IN ('staging','uat')),
			CONSTRAINT `chk_clinical_suggestion_term_active` CHECK (`active_state` IN (0,1))
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='doclinc_clinical_suggestions_uat_foundation_20260726000100'",
		'clinical_suggestion_aliases' => "CREATE TABLE `clinical_suggestion_aliases` (
			`suggestion_alias_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`suggestion_term_id` BIGINT UNSIGNED NOT NULL,
			`alias_label` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`normalized_alias` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`source_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`source_version` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
			`source_dataset` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`source_governance_status` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`language_code` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'id',
			`active_state` TINYINT UNSIGNED NOT NULL DEFAULT 1,
			`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
			PRIMARY KEY (`suggestion_alias_id`),
			UNIQUE KEY `uq_clinical_suggestion_alias_natural` (`suggestion_term_id`, `normalized_alias`),
			KEY `idx_clinical_suggestion_alias_search` (`active_state`, `normalized_alias`),
			CONSTRAINT `fk_clinical_suggestion_alias_term` FOREIGN KEY (`suggestion_term_id`) REFERENCES `clinical_suggestion_terms` (`suggestion_term_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
			CONSTRAINT `chk_clinical_suggestion_alias_active` CHECK (`active_state` IN (0,1))
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='doclinc_clinical_suggestions_uat_foundation_20260726000100'",
	);
	$step_index = 0;
	foreach ($statements as $table_name => $statement) {
		$step_index++;
		$db->query($statement);
		$created_tables[] = $table_name;
	}
	foreach ($expected_columns as $table_name => $columns) {
		$stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
		$stmt->bind_param('s', $table_name);
		$stmt->execute();
		$actual_columns = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
		if ($actual_columns !== $columns) throw new RuntimeException('created_schema_verification_failed');
	}
	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=true\nIMPORTS_PACKAGE_DATA=false\nENABLES_RUNTIME=false\n";
} catch (Throwable $exception) {
	$cleanup_failed = false;
	if (isset($db) && $db instanceof mysqli && !empty($created_tables)) {
		foreach (array_reverse($created_tables) as $created_table) {
			try {
				$db->query('DROP TABLE `' . $created_table . '`');
			} catch (Throwable $cleanup_exception) {
				$cleanup_failed = true;
			}
		}
	}
	$safe_codes = array(
		'connected_database_mismatch',
		'migration_lock_unavailable',
		'existing_schema_mismatch',
		'partial_schema_collision',
		'created_schema_verification_failed',
	);
	$safe_code = in_array($exception->getMessage(), $safe_codes, true)
		? $exception->getMessage()
		: (isset($step_index) && $step_index > 0 ? 'migration_step_' . $step_index . '_failed' : 'migration_execution_failed');
	if ($cleanup_failed) $safe_code = 'migration_cleanup_failed';
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . $safe_code . "\n");
	exit(1);
} finally {
	$password = null;
	if (isset($db) && $db instanceof mysqli) {
		if ($migration_lock_acquired) {
			try {
				$release_statement = $db->prepare('SELECT RELEASE_LOCK(?)');
				$release_statement->bind_param('s', $migration_lock_name);
				$release_statement->execute();
			} catch (Throwable $release_exception) {
				// The connection close below also releases the session-scoped lock.
			}
		}
		$db->close();
	}
}
