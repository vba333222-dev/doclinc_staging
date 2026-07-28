<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');

const DOCLINC_CARE_OPERATIONS_MIGRATION_ID = '20260728000100_care_operations_foundation';
const DOCLINC_CARE_OPERATIONS_LOCK_NAME = 'doclinc_care_operations_foundation_20260728000100';

function care_operations_fail($code)
{
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . $code . "\n");
	exit(1);
}

function care_operations_env_true($name)
{
	$value = getenv($name);
	return is_string($value) && in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
}

function care_operations_column($type, $nullable, $default, $extra = '', $charset = null, $collation = null)
{
	return array(
		'type' => strtolower($type),
		'nullable' => $nullable,
		'default' => $default,
		'extra' => strtolower($extra),
		'charset' => $charset,
		'collation' => $collation,
	);
}

function care_operations_definitions()
{
	return array(
		'realtime_outbox' => array(
			'comment' => 'doclinc_care_operations_realtime_outbox_20260728000100',
			'create_sha256' => 'c638de6489498fd63f71729bd5cd8f57c68aa8b9e352436547382655874a92c7',
			'sql' => "CREATE TABLE `realtime_outbox` (
				`outbox_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`event_type` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`aggregate_type` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`aggregate_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`audience_type` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`audience_key` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`payload_json` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				`event_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
				`idempotency_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
				`attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				`available_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
				`claimed_at` DATETIME(6) NULL,
				`published_at` DATETIME(6) NULL,
				`last_error_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
				`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
				`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
				PRIMARY KEY (`outbox_id`),
				UNIQUE KEY `uq_realtime_outbox_idempotency` (`idempotency_key`),
				KEY `idx_realtime_outbox_dispatch` (`state`, `available_at`, `outbox_id`),
				KEY `idx_realtime_outbox_audience` (`audience_type`, `audience_key`, `outbox_id`),
				KEY `idx_realtime_outbox_aggregate` (`aggregate_type`, `aggregate_id`, `outbox_id`),
				CONSTRAINT `chk_realtime_outbox_payload_json` CHECK (JSON_VALID(`payload_json`)),
				CONSTRAINT `chk_realtime_outbox_audience` CHECK (`audience_type` IN ('user','puskesmas','request','admin')),
				CONSTRAINT `chk_realtime_outbox_state` CHECK (`state` IN ('pending','claimed','published','failed'))
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='doclinc_care_operations_realtime_outbox_20260728000100'",
			'columns' => array(
				'outbox_id' => care_operations_column('bigint(20) unsigned', 'NO', null, 'auto_increment'),
				'event_type' => care_operations_column('varchar(64)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'aggregate_type' => care_operations_column('varchar(32)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'aggregate_id' => care_operations_column('varchar(64)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'audience_type' => care_operations_column('varchar(20)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'audience_key' => care_operations_column('varchar(128)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'payload_json' => care_operations_column('longtext', 'NO', null, '', 'utf8mb4', 'utf8mb4_bin'),
				'event_version' => care_operations_column('bigint(20) unsigned', 'NO', '1'),
				'idempotency_key' => care_operations_column('char(64)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'state' => care_operations_column('varchar(16)', 'NO', 'pending', '', 'ascii', 'ascii_bin'),
				'attempt_count' => care_operations_column('smallint(5) unsigned', 'NO', '0'),
				'available_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)'),
				'claimed_at' => care_operations_column('datetime(6)', 'YES', null),
				'published_at' => care_operations_column('datetime(6)', 'YES', null),
				'last_error_code' => care_operations_column('varchar(64)', 'YES', null, '', 'ascii', 'ascii_bin'),
				'created_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)'),
				'updated_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)', 'on update current_timestamp(6)'),
			),
			'indexes' => array(
				'PRIMARY' => array(false, array('outbox_id')),
				'uq_realtime_outbox_idempotency' => array(false, array('idempotency_key')),
				'idx_realtime_outbox_dispatch' => array(true, array('state', 'available_at', 'outbox_id')),
				'idx_realtime_outbox_audience' => array(true, array('audience_type', 'audience_key', 'outbox_id')),
				'idx_realtime_outbox_aggregate' => array(true, array('aggregate_type', 'aggregate_id', 'outbox_id')),
			),
			'checks' => array('chk_realtime_outbox_payload_json', 'chk_realtime_outbox_audience', 'chk_realtime_outbox_state'),
		),
		'consultation_visit_media' => array(
			'comment' => 'doclinc_care_operations_visit_media_20260728000100',
			'create_sha256' => '89c8bf4696a9bcd223a673bc3b478a4d9b8765f824d930bf500fa845fd459573',
			'sql' => "CREATE TABLE `consultation_visit_media` (
				`media_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`request_id` INT(11) NOT NULL,
				`medicalrecord_id` INT(11) NULL,
				`uploaded_by_user_id` INT(11) NOT NULL,
				`media_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`storage_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`mime_type` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`size_bytes` BIGINT UNSIGNED NOT NULL,
				`sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`lifecycle_state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
				`staged_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
				`associated_at` DATETIME(6) NULL,
				`finalized_at` DATETIME(6) NULL,
				`failed_at` DATETIME(6) NULL,
				`failure_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
				`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
				`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
				PRIMARY KEY (`media_id`),
				UNIQUE KEY `uq_visit_media_storage_key` (`storage_key`),
				KEY `idx_visit_media_request_state` (`request_id`, `lifecycle_state`, `media_id`),
				KEY `idx_visit_media_medicalrecord` (`medicalrecord_id`, `media_id`),
				KEY `idx_visit_media_pending` (`lifecycle_state`, `staged_at`, `media_id`),
				KEY `idx_visit_media_uploader` (`uploaded_by_user_id`, `created_at`),
				CONSTRAINT `chk_visit_media_type` CHECK (`media_type` IN ('image','video')),
				CONSTRAINT `chk_visit_media_state` CHECK (`lifecycle_state` IN ('pending','associated','ready','failed','quarantined')),
				CONSTRAINT `chk_visit_media_size` CHECK (`size_bytes` > 0)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='doclinc_care_operations_visit_media_20260728000100'",
			'columns' => array(
				'media_id' => care_operations_column('bigint(20) unsigned', 'NO', null, 'auto_increment'),
				'request_id' => care_operations_column('int(11)', 'NO', null),
				'medicalrecord_id' => care_operations_column('int(11)', 'YES', null),
				'uploaded_by_user_id' => care_operations_column('int(11)', 'NO', null),
				'media_type' => care_operations_column('varchar(16)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'storage_key' => care_operations_column('char(64)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'mime_type' => care_operations_column('varchar(100)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'size_bytes' => care_operations_column('bigint(20) unsigned', 'NO', null),
				'sha256' => care_operations_column('char(64)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'lifecycle_state' => care_operations_column('varchar(16)', 'NO', 'pending', '', 'ascii', 'ascii_bin'),
				'staged_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)'),
				'associated_at' => care_operations_column('datetime(6)', 'YES', null),
				'finalized_at' => care_operations_column('datetime(6)', 'YES', null),
				'failed_at' => care_operations_column('datetime(6)', 'YES', null),
				'failure_code' => care_operations_column('varchar(64)', 'YES', null, '', 'ascii', 'ascii_bin'),
				'created_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)'),
				'updated_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)', 'on update current_timestamp(6)'),
			),
			'indexes' => array(
				'PRIMARY' => array(false, array('media_id')),
				'uq_visit_media_storage_key' => array(false, array('storage_key')),
				'idx_visit_media_request_state' => array(true, array('request_id', 'lifecycle_state', 'media_id')),
				'idx_visit_media_medicalrecord' => array(true, array('medicalrecord_id', 'media_id')),
				'idx_visit_media_pending' => array(true, array('lifecycle_state', 'staged_at', 'media_id')),
				'idx_visit_media_uploader' => array(true, array('uploaded_by_user_id', 'created_at')),
			),
			'checks' => array('chk_visit_media_type', 'chk_visit_media_state', 'chk_visit_media_size'),
		),
		'medicalrecord_diagnoses' => array(
			'comment' => 'doclinc_care_operations_medicalrecord_diagnoses_20260728000100',
			'create_sha256' => '52aa905c50e7f6fca87d4ebf075fa3819f8ca1218e690fc31ce4c2ec00c3d6d8',
			'sql' => "CREATE TABLE `medicalrecord_diagnoses` (
				`diagnosis_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`medicalrecord_id` INT(11) NOT NULL,
				`request_id` INT(11) NOT NULL,
				`position` TINYINT UNSIGNED NOT NULL,
				`diagnosis_role` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`diagnosis_code` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
				`diagnosis_label` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
				`display_text` VARCHAR(320) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
				`suggestion_term_id` BIGINT UNSIGNED NULL,
				`reference_source` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
				`reference_version` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
				`created_by_user_id` INT(11) NOT NULL,
				`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
				`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
				PRIMARY KEY (`diagnosis_id`),
				UNIQUE KEY `uq_medicalrecord_diagnosis_position` (`medicalrecord_id`, `position`),
				KEY `idx_medicalrecord_diagnosis_request` (`request_id`, `position`),
				KEY `idx_medicalrecord_diagnosis_term` (`suggestion_term_id`),
				KEY `idx_medicalrecord_diagnosis_creator` (`created_by_user_id`, `created_at`),
				CONSTRAINT `chk_medicalrecord_diagnosis_position` CHECK (`position` BETWEEN 1 AND 5),
				CONSTRAINT `chk_medicalrecord_diagnosis_role` CHECK (`diagnosis_role` IN ('primary','secondary')),
				CONSTRAINT `chk_medicalrecord_diagnosis_role_position` CHECK ((`position` = 1 AND `diagnosis_role` = 'primary') OR (`position` > 1 AND `diagnosis_role` = 'secondary'))
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='doclinc_care_operations_medicalrecord_diagnoses_20260728000100'",
			'columns' => array(
				'diagnosis_id' => care_operations_column('bigint(20) unsigned', 'NO', null, 'auto_increment'),
				'medicalrecord_id' => care_operations_column('int(11)', 'NO', null),
				'request_id' => care_operations_column('int(11)', 'NO', null),
				'position' => care_operations_column('tinyint(3) unsigned', 'NO', null),
				'diagnosis_role' => care_operations_column('varchar(16)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'diagnosis_code' => care_operations_column('varchar(32)', 'YES', null, '', 'utf8mb4', 'utf8mb4_unicode_ci'),
				'diagnosis_label' => care_operations_column('varchar(255)', 'NO', null, '', 'utf8mb4', 'utf8mb4_unicode_ci'),
				'display_text' => care_operations_column('varchar(320)', 'NO', null, '', 'utf8mb4', 'utf8mb4_unicode_ci'),
				'suggestion_term_id' => care_operations_column('bigint(20) unsigned', 'YES', null),
				'reference_source' => care_operations_column('varchar(128)', 'YES', null, '', 'utf8mb4', 'utf8mb4_unicode_ci'),
				'reference_version' => care_operations_column('varchar(64)', 'YES', null, '', 'utf8mb4', 'utf8mb4_unicode_ci'),
				'created_by_user_id' => care_operations_column('int(11)', 'NO', null),
				'created_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)'),
				'updated_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)', 'on update current_timestamp(6)'),
			),
			'indexes' => array(
				'PRIMARY' => array(false, array('diagnosis_id')),
				'uq_medicalrecord_diagnosis_position' => array(false, array('medicalrecord_id', 'position')),
				'idx_medicalrecord_diagnosis_request' => array(true, array('request_id', 'position')),
				'idx_medicalrecord_diagnosis_term' => array(true, array('suggestion_term_id')),
				'idx_medicalrecord_diagnosis_creator' => array(true, array('created_by_user_id', 'created_at')),
			),
			'checks' => array('chk_medicalrecord_diagnosis_position', 'chk_medicalrecord_diagnosis_role', 'chk_medicalrecord_diagnosis_role_position'),
		),
		'nakes_presence' => array(
			'comment' => 'doclinc_care_operations_nakes_presence_20260728000100',
			'create_sha256' => '26815d9feae34d7aceac56f65c9df0a4ef68a6dd796005add86f2e17f558e86b',
			'sql' => "CREATE TABLE `nakes_presence` (
				`user_id` INT(11) NOT NULL,
				`puskesmas_code` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
				`last_seen_at` DATETIME(6) NOT NULL,
				`last_transition_at` DATETIME(6) NULL,
				`last_persisted_at` DATETIME(6) NOT NULL,
				`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
				PRIMARY KEY (`user_id`),
				KEY `idx_nakes_presence_puskesmas_seen` (`puskesmas_code`, `last_seen_at`, `user_id`),
				KEY `idx_nakes_presence_last_seen` (`last_seen_at`, `user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='doclinc_care_operations_nakes_presence_20260728000100'",
			'columns' => array(
				'user_id' => care_operations_column('int(11)', 'NO', null),
				'puskesmas_code' => care_operations_column('varchar(100)', 'NO', null, '', 'utf8mb4', 'utf8mb4_unicode_ci'),
				'last_seen_at' => care_operations_column('datetime(6)', 'NO', null),
				'last_transition_at' => care_operations_column('datetime(6)', 'YES', null),
				'last_persisted_at' => care_operations_column('datetime(6)', 'NO', null),
				'updated_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)', 'on update current_timestamp(6)'),
			),
			'indexes' => array(
				'PRIMARY' => array(false, array('user_id')),
				'idx_nakes_presence_puskesmas_seen' => array(true, array('puskesmas_code', 'last_seen_at', 'user_id')),
				'idx_nakes_presence_last_seen' => array(true, array('last_seen_at', 'user_id')),
			),
			'checks' => array(),
		),
		'visit_location_updates' => array(
			'comment' => 'doclinc_care_operations_visit_location_updates_20260728000100',
			'create_sha256' => 'fe393c25603c16e1d01b5df7827c748c50d807ff04f0a51acd06dbedeb220cb4',
			'sql' => "CREATE TABLE `visit_location_updates` (
				`location_update_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`request_id` INT(11) NOT NULL,
				`nakes_user_id` INT(11) NOT NULL,
				`latitude` DECIMAL(10,7) NOT NULL,
				`longitude` DECIMAL(10,7) NOT NULL,
				`accuracy_m` DECIMAL(10,2) NULL,
				`heading_degrees` DECIMAL(6,2) NULL,
				`speed_mps` DECIMAL(10,2) NULL,
				`client_sequence` BIGINT UNSIGNED NOT NULL,
				`idempotency_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				`captured_at` DATETIME(6) NOT NULL,
				`received_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
				`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
				PRIMARY KEY (`location_update_id`),
				UNIQUE KEY `uq_visit_location_idempotency` (`nakes_user_id`, `idempotency_key`),
				UNIQUE KEY `uq_visit_location_sequence` (`request_id`, `nakes_user_id`, `client_sequence`),
				KEY `idx_visit_location_request_received` (`request_id`, `received_at`, `location_update_id`),
				KEY `idx_visit_location_nakes_received` (`nakes_user_id`, `received_at`, `location_update_id`),
				CONSTRAINT `chk_visit_location_latitude` CHECK (`latitude` BETWEEN -90 AND 90),
				CONSTRAINT `chk_visit_location_longitude` CHECK (`longitude` BETWEEN -180 AND 180),
				CONSTRAINT `chk_visit_location_accuracy` CHECK (`accuracy_m` IS NULL OR `accuracy_m` >= 0),
				CONSTRAINT `chk_visit_location_heading` CHECK (`heading_degrees` IS NULL OR (`heading_degrees` >= 0 AND `heading_degrees` < 360)),
				CONSTRAINT `chk_visit_location_speed` CHECK (`speed_mps` IS NULL OR `speed_mps` >= 0)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='doclinc_care_operations_visit_location_updates_20260728000100'",
			'columns' => array(
				'location_update_id' => care_operations_column('bigint(20) unsigned', 'NO', null, 'auto_increment'),
				'request_id' => care_operations_column('int(11)', 'NO', null),
				'nakes_user_id' => care_operations_column('int(11)', 'NO', null),
				'latitude' => care_operations_column('decimal(10,7)', 'NO', null),
				'longitude' => care_operations_column('decimal(10,7)', 'NO', null),
				'accuracy_m' => care_operations_column('decimal(10,2)', 'YES', null),
				'heading_degrees' => care_operations_column('decimal(6,2)', 'YES', null),
				'speed_mps' => care_operations_column('decimal(10,2)', 'YES', null),
				'client_sequence' => care_operations_column('bigint(20) unsigned', 'NO', null),
				'idempotency_key' => care_operations_column('char(64)', 'NO', null, '', 'ascii', 'ascii_bin'),
				'captured_at' => care_operations_column('datetime(6)', 'NO', null),
				'received_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)'),
				'created_at' => care_operations_column('datetime(6)', 'NO', 'current_timestamp(6)'),
			),
			'indexes' => array(
				'PRIMARY' => array(false, array('location_update_id')),
				'uq_visit_location_idempotency' => array(false, array('nakes_user_id', 'idempotency_key')),
				'uq_visit_location_sequence' => array(false, array('request_id', 'nakes_user_id', 'client_sequence')),
				'idx_visit_location_request_received' => array(true, array('request_id', 'received_at', 'location_update_id')),
				'idx_visit_location_nakes_received' => array(true, array('nakes_user_id', 'received_at', 'location_update_id')),
			),
			'checks' => array('chk_visit_location_latitude', 'chk_visit_location_longitude', 'chk_visit_location_accuracy', 'chk_visit_location_heading', 'chk_visit_location_speed'),
		),
	);
}

function care_operations_table_row(mysqli $db, $table)
{
	$stmt = $db->prepare('SELECT ENGINE,TABLE_COLLATION,TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND TABLE_TYPE=\'BASE TABLE\'');
	$stmt->bind_param('s', $table);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function care_operations_table_exists(mysqli $db, $table)
{
	return care_operations_table_row($db, $table) !== null;
}

function care_operations_column_row(mysqli $db, $table, $column)
{
	$stmt = $db->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
	$stmt->bind_param('ss', $table, $column);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

function care_operations_normalize_default($value)
{
	if ($value === null) {
		return null;
	}
	$value = strtolower((string) $value);
	if ($value === 'null') {
		return null;
	}
	if (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
		return str_replace("''", "'", substr($value, 1, -1));
	}
	return $value;
}

function care_operations_assert_column_signature(mysqli $db, $table, $column, array $expected, $error_code)
{
	$actual = care_operations_column_row($db, $table, $column);
	if (!$actual
		|| strtolower((string) $actual['COLUMN_TYPE']) !== $expected['type']
		|| (string) $actual['IS_NULLABLE'] !== $expected['nullable']
		|| care_operations_normalize_default($actual['COLUMN_DEFAULT']) !== care_operations_normalize_default($expected['default'])
		|| strtolower((string) $actual['EXTRA']) !== $expected['extra']
		|| ($expected['charset'] !== null && (string) $actual['CHARACTER_SET_NAME'] !== $expected['charset'])
		|| ($expected['collation'] !== null && (string) $actual['COLLATION_NAME'] !== $expected['collation'])) {
		throw new RuntimeException($error_code . ':' . $table . '.' . $column);
	}
}

function care_operations_index_signatures(mysqli $db, $table)
{
	$stmt = $db->prepare('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX');
	$stmt->bind_param('s', $table);
	$stmt->execute();
	$signatures = array();
	foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
		$name = (string) $row['INDEX_NAME'];
		if (!isset($signatures[$name])) {
			$signatures[$name] = array((int) $row['NON_UNIQUE'] === 1, array());
		}
		$signatures[$name][1][] = (string) $row['COLUMN_NAME'];
	}
	ksort($signatures);
	return $signatures;
}

function care_operations_check_names(mysqli $db, $table)
{
	$stmt = $db->prepare("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_TYPE='CHECK' ORDER BY CONSTRAINT_NAME");
	$stmt->bind_param('s', $table);
	$stmt->execute();
	return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'CONSTRAINT_NAME');
}

function care_operations_assert_target(mysqli $db, $table, array $definition)
{
	$row = care_operations_table_row($db, $table);
	if (!$row || strtoupper((string) $row['ENGINE']) !== 'INNODB'
		|| (string) $row['TABLE_COLLATION'] !== 'utf8mb4_unicode_ci'
		|| !hash_equals($definition['comment'], (string) $row['TABLE_COMMENT'])) {
		throw new RuntimeException('target_table_signature_mismatch');
	}
	if (preg_match('/\A[a-z0-9_]+\z/', $table) !== 1) {
		throw new RuntimeException('target_table_signature_mismatch');
	}
	$show_create = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch_assoc();
	$create_sql = $show_create ? (string) array_values($show_create)[1] : '';
	$actual_hash = hash('sha256', care_operations_normalize_create_sql($create_sql));
	if (!isset($definition['create_sha256'])
		|| preg_match('/\A[a-f0-9]{64}\z/', (string) $definition['create_sha256']) !== 1
		|| !hash_equals((string) $definition['create_sha256'], $actual_hash)) {
		throw new RuntimeException('target_schema_hash_mismatch:' . $table . '.__show_create');
	}
}

function care_operations_normalize_create_sql($sql)
{
	$sql = str_replace(array("\r\n", "\r"), "\n", trim((string) $sql));
	$sql = preg_replace('/\s+/', ' ', $sql);
	$sql = preg_replace('/\s+AUTO_INCREMENT=\d+\b/i', '', $sql);
	return trim($sql);
}

function care_operations_assert_primary(mysqli $db, $table, array $columns)
{
	$indexes = care_operations_index_signatures($db, $table);
	if (!isset($indexes['PRIMARY']) || $indexes['PRIMARY'][0] !== false || $indexes['PRIMARY'][1] !== $columns) {
		throw new RuntimeException('base_schema_mismatch');
	}
}

function care_operations_assert_base(mysqli $db)
{
	$required_tables = array('users', 'm_puskesmas', 'puskesmas_staff', 'requests', 'request_staff_assignments', 'medicalrecords', 'notifications', 'clinical_suggestion_terms');
	foreach ($required_tables as $table) {
		$row = care_operations_table_row($db, $table);
		if (!$row || strtoupper((string) $row['ENGINE']) !== 'INNODB') {
			throw new RuntimeException('base_schema_mismatch');
		}
	}

	$required_columns = array(
		array('users', 'userId', care_operations_column('int(11)', 'NO', null, 'auto_increment')),
		array('users', 'role', care_operations_column("enum('admin','dokter','warga','')", 'NO', null)),
		array('users', 'status', care_operations_column("enum('aktif','nonaktif')", 'YES', 'aktif')),
		array('users', 'must_change_password', care_operations_column('tinyint(1)', 'NO', '0')),
		array('m_puskesmas', 'kode_pkm', care_operations_column('varchar(100)', 'NO', null)),
		array('m_puskesmas', 'status', care_operations_column("enum('aktif','nonaktif')", 'NO', 'aktif')),
		array('puskesmas_staff', 'staff_id', care_operations_column('int(10) unsigned', 'NO', null, 'auto_increment')),
		array('puskesmas_staff', 'kode_pkm', care_operations_column('varchar(100)', 'NO', null)),
		array('puskesmas_staff', 'user_id', care_operations_column('int(11)', 'YES', null)),
		array('puskesmas_staff', 'status', care_operations_column("enum('aktif','nonaktif')", 'NO', 'aktif')),
		array('requests', 'request_id', care_operations_column('int(11)', 'NO', null, 'auto_increment')),
		array('requests', 'user_id', care_operations_column('int(11)', 'NO', null)),
		array('requests', 'request_status', care_operations_column("enum('Pending','Accepted','Completed','Cancelled')", 'YES', 'Pending')),
		array('requests', 'assigned_puskesmas_code', care_operations_column('varchar(100)', 'YES', null)),
		array('requests', 'assigned_nakes_user_id', care_operations_column('int(11)', 'YES', null)),
		array('requests', 'accepted_by_user_id', care_operations_column('int(11)', 'YES', null)),
		array('requests', 'visit_status', care_operations_column('varchar(30)', 'YES', 'not_started')),
		array('request_staff_assignments', 'assignment_id', care_operations_column('int(10) unsigned', 'NO', null, 'auto_increment')),
		array('request_staff_assignments', 'request_id', care_operations_column('int(11)', 'NO', null)),
		array('request_staff_assignments', 'staff_id', care_operations_column('int(10) unsigned', 'NO', null)),
		array('request_staff_assignments', 'status', care_operations_column("enum('aktif','diganti','dibatalkan')", 'NO', 'aktif')),
		array('medicalrecords', 'record_id', care_operations_column('int(11)', 'NO', null, 'auto_increment')),
		array('medicalrecords', 'request_id', care_operations_column('int(11)', 'NO', null)),
		array('medicalrecords', 'diagnosis', care_operations_column('text', 'YES', null)),
		array('notifications', 'notification_id', care_operations_column('int(11)', 'NO', null, 'auto_increment')),
		array('notifications', 'event_type', care_operations_column('varchar(64)', 'NO', null)),
		array('notifications', 'entity_type', care_operations_column('varchar(64)', 'NO', null)),
		array('notifications', 'entity_id', care_operations_column('varchar(64)', 'NO', null)),
		array('notifications', 'is_read', care_operations_column('tinyint(1)', 'NO', '0')),
		array('clinical_suggestion_terms', 'suggestion_term_id', care_operations_column('bigint(20) unsigned', 'NO', null, 'auto_increment')),
	);
	foreach ($required_columns as $required) {
		care_operations_assert_column_signature($db, $required[0], $required[1], $required[2], 'base_schema_mismatch');
	}
	care_operations_assert_primary($db, 'users', array('userId'));
	care_operations_assert_primary($db, 'm_puskesmas', array('kode_pkm'));
	care_operations_assert_primary($db, 'puskesmas_staff', array('staff_id'));
	care_operations_assert_primary($db, 'requests', array('request_id'));
	care_operations_assert_primary($db, 'request_staff_assignments', array('assignment_id'));
	care_operations_assert_primary($db, 'medicalrecords', array('record_id'));
	care_operations_assert_primary($db, 'notifications', array('notification_id'));
	care_operations_assert_primary($db, 'clinical_suggestion_terms', array('suggestion_term_id'));
}

function care_operations_assert_grants(mysqli $db, $database, $configured_user)
{
	$current = $db->query('SELECT CURRENT_USER() AS account')->fetch_assoc();
	$account = isset($current['account']) ? (string) $current['account'] : '';
	$separator = strpos($account, '@');
	if ($separator === false || !hash_equals($configured_user, substr($account, 0, $separator))) {
		throw new RuntimeException('schema_writer_identity_mismatch');
	}

	$required_select = array_fill_keys(array(
		'users', 'm_puskesmas', 'puskesmas_staff', 'requests',
		'request_staff_assignments', 'medicalrecords', 'notifications',
		'clinical_suggestion_terms',
	), false);
	$create_seen = false;
	foreach ($db->query('SHOW GRANTS FOR CURRENT_USER()')->fetch_all(MYSQLI_NUM) as $row) {
		$grant = (string) $row[0];
		if (preg_match('/\AGRANT USAGE ON \*\.\*/i', $grant) === 1) {
			continue;
		}
		if (stripos($grant, 'GRANT OPTION') !== false) {
			throw new RuntimeException('schema_writer_grants_excessive');
		}
		if (preg_match('/\AGRANT CREATE ON `?([^` .]+)`?\.\* TO /i', $grant, $match) === 1) {
			if (!hash_equals($database, $match[1]) || $create_seen) {
				throw new RuntimeException('schema_writer_grants_excessive');
			}
			$create_seen = true;
			continue;
		}
		if (preg_match('/\AGRANT SELECT ON `?([^` .]+)`?\.`?([^` ]+)`? TO /i', $grant, $match) === 1) {
			if (!hash_equals($database, $match[1]) || !array_key_exists($match[2], $required_select) || $required_select[$match[2]]) {
				throw new RuntimeException('schema_writer_grants_excessive');
			}
			$required_select[$match[2]] = true;
			continue;
		}
		throw new RuntimeException('schema_writer_grants_excessive');
	}
	if (!$create_seen || in_array(false, $required_select, true)) {
		throw new RuntimeException('schema_writer_grants_incomplete');
	}
}

if (defined('DOCLINC_CARE_OPERATIONS_DEFINITIONS_ONLY') && DOCLINC_CARE_OPERATIONS_DEFINITIONS_ONLY === true) {
	return;
}

$options = getopt('', array(
	'apply',
	'environment:',
	'confirm-database:',
	'backup-reference:',
	'confirm-backup-sha256:',
	'confirm-disposable-test:',
));
$definitions = care_operations_definitions();
$apply = array_key_exists('apply', $options);
if (!$apply) {
	echo "MIGRATION_ID=" . DOCLINC_CARE_OPERATIONS_MIGRATION_ID . "\n";
	echo "EXECUTION_MODE=PLAN\nDATABASE_CONNECTION_OPENED=false\nDDL_EXECUTED=false\n";
	echo "PLANNED_TABLE_COUNT=" . count($definitions) . "\n";
	foreach (array_keys($definitions) as $table) {
		echo "PLANNED_TABLE=" . $table . "\n";
	}
	echo "EXISTING_ROWS_CHANGED=false\nEXISTING_PASSWORDS_CHANGED=false\nCLINICAL_MIGRATION_LEDGER_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
	exit(0);
}

if (!care_operations_env_true('DOCLINC_CARE_OPERATIONS_SCHEMA_WRITE_ENABLED')) {
	care_operations_fail('schema_write_disabled');
}
$environment = isset($options['environment']) ? strtolower(trim((string) $options['environment'])) : '';
if (!in_array($environment, array('staging', 'uat', 'test'), true)) {
	care_operations_fail('environment_not_allowed');
}
$database = trim((string) (getenv('DOCLINC_CARE_OPERATIONS_SCHEMA_DB_NAME') ?: ''));
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	care_operations_fail('database_confirmation_mismatch');
}
$disposable = care_operations_env_true('DOCLINC_CARE_OPERATIONS_DISPOSABLE_TEST');
if ($environment === 'test') {
	if (!$disposable || !isset($options['confirm-disposable-test']) || !hash_equals('true', strtolower((string) $options['confirm-disposable-test']))
		|| preg_match('/\Adoclinc_care_operations_test_[a-z0-9_]+\z/', strtolower($database)) !== 1) {
		care_operations_fail('disposable_test_database_required');
	}
} elseif ($disposable || !hash_equals('doclinc-staging', $database)) {
	care_operations_fail('staging_database_required');
}
$backup_reference = isset($options['backup-reference']) ? trim((string) $options['backup-reference']) : '';
$backup_sha256 = isset($options['confirm-backup-sha256']) ? strtolower(trim((string) $options['confirm-backup-sha256'])) : '';
if ($backup_reference === '' || strlen($backup_reference) > 255 || preg_match('/[\x00-\x1F\x7F]/', $backup_reference) === 1) {
	care_operations_fail('backup_reference_required');
}
if (preg_match('/\A[a-f0-9]{64}\z/', $backup_sha256) !== 1) {
	care_operations_fail('backup_checksum_confirmation_mismatch');
}

$host = getenv('DOCLINC_CARE_OPERATIONS_SCHEMA_DB_HOST') ?: 'localhost';
$port = (int) (getenv('DOCLINC_CARE_OPERATIONS_SCHEMA_DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_CARE_OPERATIONS_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_CARE_OPERATIONS_SCHEMA_DB_PASSWORD');
$allowed_users = array_filter(array_map('trim', explode(',', (string) (getenv('DOCLINC_CARE_OPERATIONS_SCHEMA_ALLOWED_USERS') ?: ''))));
if ($user === '' || !is_string($password) || $password === '' || !in_array($user, $allowed_users, true)) {
	care_operations_fail('database_identity_not_allowed');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$lock_acquired = false;
$ddl_executed = 0;
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$actual = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$actual || !hash_equals($database, (string) $actual['database_name'])) {
		throw new RuntimeException('connected_database_mismatch');
	}
	care_operations_assert_grants($db, $database, $user);
	$lock = $db->prepare('SELECT GET_LOCK(?, 10) AS acquired');
	$lock_name = DOCLINC_CARE_OPERATIONS_LOCK_NAME;
	$lock->bind_param('s', $lock_name);
	$lock->execute();
	$lock_row = $lock->get_result()->fetch_assoc();
	if (!$lock_row || (int) $lock_row['acquired'] !== 1) {
		throw new RuntimeException('migration_lock_unavailable');
	}
	$lock_acquired = true;

	care_operations_assert_base($db);
	$present = array();
	foreach (array_keys($definitions) as $table) {
		if (care_operations_table_exists($db, $table)) {
			$present[] = $table;
		}
	}
	if (count($present) === count($definitions)) {
		foreach ($definitions as $table => $definition) {
			care_operations_assert_target($db, $table, $definition);
		}
		echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=false\nDDL_STATEMENT_COUNT=0\nALREADY_APPLIED=true\nEXISTING_ROWS_CHANGED=false\nEXISTING_PASSWORDS_CHANGED=false\nCLINICAL_MIGRATION_LEDGER_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
		return;
	}
	if (count($present) !== 0) {
		throw new RuntimeException('partial_schema_detected');
	}

	foreach ($definitions as $table => $definition) {
		$db->query($definition['sql']);
		$ddl_executed++;
		care_operations_assert_target($db, $table, $definition);
	}
	care_operations_assert_base($db);
	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=true\nDDL_STATEMENT_COUNT=" . $ddl_executed . "\nALREADY_APPLIED=false\nEXISTING_ROWS_CHANGED=false\nEXISTING_PASSWORDS_CHANGED=false\nCLINICAL_MIGRATION_LEDGER_CHANGED=false\nDESTRUCTIVE_ROLLBACK=false\n";
} catch (Throwable $exception) {
	$safe_codes = array(
		'connected_database_mismatch',
		'schema_writer_identity_mismatch',
		'schema_writer_grants_excessive',
		'schema_writer_grants_incomplete',
		'migration_lock_unavailable',
		'base_schema_mismatch',
		'target_table_signature_mismatch',
		'target_schema_hash_mismatch',
		'partial_schema_detected',
	);
	$message = $exception->getMessage();
	$failed_object = '';
	if (strpos($message, ':') !== false) {
		list($message, $failed_object) = explode(':', $message, 2);
	}
	$code = in_array($message, $safe_codes, true) ? $message : 'migration_execution_failed';
	$output = "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . $code . "\nDDL_STATEMENT_COUNT=" . $ddl_executed . "\n";
	if ($code !== 'migration_execution_failed' && preg_match('/\A[a-z0-9_]+\.[A-Za-z0-9_]+\z/', $failed_object) === 1) {
		$output .= 'FAILED_SCHEMA_OBJECT=' . $failed_object . "\n";
	}
	fwrite(STDERR, $output);
	exit(1);
} finally {
	$password = null;
	if (isset($db) && $db instanceof mysqli) {
		if ($lock_acquired) {
			try {
				$release = $db->prepare('SELECT RELEASE_LOCK(?)');
				$lock_name = DOCLINC_CARE_OPERATIONS_LOCK_NAME;
				$release->bind_param('s', $lock_name);
				$release->execute();
			} catch (Throwable $ignored) {
				// Closing the connection also releases this session-scoped lock.
			}
		}
		$db->close();
	}
}
