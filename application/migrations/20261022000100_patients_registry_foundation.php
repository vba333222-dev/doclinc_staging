<?php
/**
 * Fondasi registri pasien ber-NIK, waktu selesai kanonik, dan status pengodean
 * diagnosis.
 *
 * Mengapa migrasi ini ada
 * -----------------------
 * 1. Rekam medis hanya menunjuk `request_id`; tidak ada identitas pasien yang
 *    stabil, sehingga riwayat seorang pasien tidak bisa dicari per NIK dan
 *    tidak bisa dibaca ulang per tanggal.
 * 2. Daftar "selesai" memakai `requests.created_at` (tanggal permintaan dibuat)
 *    dan tanpa filter tanggal, jadi arsip tidak bisa dipersempit per bulan.
 * 3. `medicalrecord_diagnoses` bisa menyimpan kode ICD-10, tetapi tidak ada
 *    penanda apakah sebuah baris sudah dikode atau masih teks bebas.
 *
 * Semua perubahan bersifat aditif: tidak ada DROP, TRUNCATE, atau UPDATE.
 * Pengisian data dijalankan terpisah oleh
 * application/controllers/Doclinc_patients_backfill.php agar DDL dan data tidak
 * tercampur dalam satu langkah.
 *
 * Pemakaian
 * ---------
 *   php application/migrations/20261022000100_patients_registry_foundation.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   DOCLINC_PATIENTS_REGISTRY_SCHEMA_WRITE_ENABLED=1 \
 *   DOCLINC_PATIENTS_REGISTRY_SCHEMA_DB_NAME=<db> \
 *   php application/migrations/20261022000100_patients_registry_foundation.php \
 *     --apply --environment=staging --confirm-database=<db>
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_PATIENTS_REGISTRY_MIGRATION_ID = '20261022000100_patients_registry_foundation';

function patients_registry_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function patients_registry_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DDL_EXECUTED=false\nEXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

function patients_registry_table(mysqli $db, $table)
{
	$statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
	$statement->bind_param('s', $table);
	$statement->execute();
	$statement->bind_result($count);
	$statement->fetch();
	$statement->close();
	return (int) $count === 1;
}

function patients_registry_column(mysqli $db, $table, $column)
{
	$statement = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
	$statement->bind_param('ss', $table, $column);
	$statement->execute();
	$statement->bind_result($count);
	$statement->fetch();
	$statement->close();
	return (int) $count === 1;
}

function patients_registry_index(mysqli $db, $table, $index)
{
	$statement = $db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
	$statement->bind_param('ss', $table, $index);
	$statement->execute();
	$statement->bind_result($count);
	$statement->fetch();
	$statement->close();
	return (int) $count > 0;
}


function patients_registry_foreign_key(mysqli $db, $table, $constraint)
{
	$statement = $db->prepare('SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?');
	$statement->bind_param('ss', $table, $constraint);
	$statement->execute();
	$statement->bind_result($count);
	$statement->fetch();
	$statement->close();
	return (int) $count === 1;
}

function patients_registry_count(mysqli $db, $table)
{
	$row = $db->query('SELECT COUNT(*) AS total FROM `' . $table . '`')->fetch_assoc();
	return (int) $row['total'];
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:'));
if (!array_key_exists('apply', $options)) {
	echo 'MIGRATION_ID=' . DOCLINC_PATIENTS_REGISTRY_MIGRATION_ID . "\n"
		. "EXECUTION_MODE=PLAN\nDATABASE_WRITE_EXECUTED=false\n"
		. "PLANNED_TABLE_COUNT=1\nPLANNED_COLUMN_COUNT=3\nPLANNED_INDEX_COUNT=3\n"
		. "PLANNED_FOREIGN_KEY_COUNT=2\nDESTRUCTIVE_STATEMENTS=0\n";
	exit(0);
}

if (!patients_registry_true(getenv('DOCLINC_PATIENTS_REGISTRY_SCHEMA_WRITE_ENABLED'))) {
	patients_registry_fail('schema_write_disabled', false);
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('test', 'staging', 'uat', 'production'), true)) {
	patients_registry_fail('environment_not_allowed', false);
}
$database = trim((string) (getenv('DOCLINC_PATIENTS_REGISTRY_SCHEMA_DB_NAME') ?: ''));
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	patients_registry_fail('database_confirmation_mismatch', false);
}

$host = trim((string) (getenv('DOCLINC_PATIENTS_REGISTRY_SCHEMA_DB_HOST') ?: '127.0.0.1'));
$port = (int) (getenv('DOCLINC_PATIENTS_REGISTRY_SCHEMA_DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_PATIENTS_REGISTRY_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_PATIENTS_REGISTRY_SCHEMA_DB_PASSWORD');
if ($user === '' || !is_string($password)) {
	patients_registry_fail('database_identity_missing', false);
}
if ($password === '' && !in_array(strtolower($host), array('127.0.0.1', 'localhost', '::1'), true)) {
	patients_registry_fail('database_password_required_for_remote_host', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');

	$identity = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$identity || !hash_equals($database, (string) $identity['database_name'])) {
		patients_registry_fail('database_identity_mismatch');
	}

	$lock = $db->query("SELECT GET_LOCK('doclinc_patients_registry', 10) AS locked")->fetch_assoc();
	if (!$lock || (int) $lock['locked'] !== 1) {
		patients_registry_fail('migration_lock_unavailable');
	}

	foreach (array('users', 'requests', 'medicalrecords', 'medicalrecord_diagnoses') as $base) {
		if (!patients_registry_table($db, $base)) {
			patients_registry_fail('base_schema_mismatch');
		}
	}

	$counts_before = array(
		'users' => patients_registry_count($db, 'users'),
		'requests' => patients_registry_count($db, 'requests'),
		'medicalrecords' => patients_registry_count($db, 'medicalrecords'),
		'medicalrecord_diagnoses' => patients_registry_count($db, 'medicalrecord_diagnoses'),
	);

	$tables_created = 0;
	$columns_added = 0;
	$indexes_added = 0;
	$foreign_keys_added = 0;
	$ddl_executed = false;



	if (!patients_registry_table($db, 'patients')) {
		$db->query("CREATE TABLE `patients` (
			`patient_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`nik` CHAR(16) NOT NULL,
			`nama` VARCHAR(150) NOT NULL,
			`tgl_lahir` DATE NULL,
			`gender` ENUM('L','P') NULL,
			`no_hp` VARCHAR(50) NULL,
			`alamat` VARCHAR(255) NULL,
			`nomor_kk` CHAR(16) NULL,
			`nomor_bpjs_kis` CHAR(13) NULL,
			`primary_user_id` INT(11) NULL,
			`status` ENUM('aktif','nonaktif','digabung') NOT NULL DEFAULT 'aktif',
			`merged_into_patient_id` BIGINT UNSIGNED NULL,
			`merged_at` DATETIME(6) NULL,
			`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
			`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
			PRIMARY KEY (`patient_id`),
			UNIQUE KEY `uq_patients_nik` (`nik`),
			KEY `idx_patients_nama` (`nama`),
			KEY `idx_patients_user` (`primary_user_id`),
			KEY `idx_patients_status` (`status`),
			CONSTRAINT `fk_patients_user` FOREIGN KEY (`primary_user_id`) REFERENCES `users` (`userId`) ON DELETE SET NULL ON UPDATE RESTRICT,
			CONSTRAINT `fk_patients_merged` FOREIGN KEY (`merged_into_patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
			CONSTRAINT `chk_patients_nik_len` CHECK (CHAR_LENGTH(`nik`) = 16)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$tables_created++;
		$ddl_executed = true;
	}

	foreach (array('patient_id', 'nik', 'nama', 'status', 'primary_user_id', 'merged_into_patient_id') as $required_column) {
		if (!patients_registry_column($db, 'patients', $required_column)) {
			patients_registry_fail('patients_schema_mismatch');
		}
	}

	if (!patients_registry_column($db, 'medicalrecords', 'patient_id')) {
		$db->query('ALTER TABLE `medicalrecords` ADD COLUMN `patient_id` BIGINT UNSIGNED NULL AFTER `request_id`');
		$columns_added++;
		$ddl_executed = true;
	}
	if (!patients_registry_index($db, 'medicalrecords', 'idx_mr_patient_date')) {
		$db->query('ALTER TABLE `medicalrecords` ADD KEY `idx_mr_patient_date` (`patient_id`, `clinical_finalized_at`, `record_id`)');
		$indexes_added++;
		$ddl_executed = true;
	}
	if (!patients_registry_foreign_key($db, 'medicalrecords', 'fk_mr_patient')) {
		$db->query('ALTER TABLE `medicalrecords` ADD CONSTRAINT `fk_mr_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT');
		$foreign_keys_added++;
		$ddl_executed = true;
	}

	if (!patients_registry_column($db, 'requests', 'completed_at')) {
		$db->query('ALTER TABLE `requests` ADD COLUMN `completed_at` DATETIME(6) NULL AFTER `visit_completed_at`');
		$columns_added++;
		$ddl_executed = true;
	}
	if (!patients_registry_index($db, 'requests', 'idx_requests_archive')) {
		$db->query('ALTER TABLE `requests` ADD KEY `idx_requests_archive` (`assigned_puskesmas_code`, `request_status`, `completed_at`, `request_id`)');
		$indexes_added++;
		$ddl_executed = true;
	}

	if (!patients_registry_column($db, 'medicalrecord_diagnoses', 'coding_state')) {
		$db->query("ALTER TABLE `medicalrecord_diagnoses` ADD COLUMN `coding_state` ENUM('uncoded','coded') NOT NULL DEFAULT 'uncoded' AFTER `diagnosis_label`");
		$columns_added++;
		$ddl_executed = true;
	}
	if (!patients_registry_index($db, 'medicalrecord_diagnoses', 'idx_mrd_code')) {
		$db->query('ALTER TABLE `medicalrecord_diagnoses` ADD KEY `idx_mrd_code` (`diagnosis_code`)');
		$indexes_added++;
		$ddl_executed = true;
	}


	// Verifikasi setelah DDL: setiap objek harus ada, dan jumlah baris tidak berubah.
	foreach (array('medicalrecords' => 'patient_id', 'requests' => 'completed_at', 'medicalrecord_diagnoses' => 'coding_state') as $table => $column) {
		if (!patients_registry_column($db, $table, $column)) {
			patients_registry_fail('schema_verification_failed');
		}
	}
	foreach (array('idx_mr_patient_date' => 'medicalrecords', 'idx_requests_archive' => 'requests', 'idx_mrd_code' => 'medicalrecord_diagnoses') as $index => $table) {
		if (!patients_registry_index($db, $table, $index)) {
			patients_registry_fail('schema_verification_failed');
		}
	}
	if (!patients_registry_foreign_key($db, 'medicalrecords', 'fk_mr_patient')) {
		patients_registry_fail('schema_verification_failed');
	}

	$counts_after = array(
		'users' => patients_registry_count($db, 'users'),
		'requests' => patients_registry_count($db, 'requests'),
		'medicalrecords' => patients_registry_count($db, 'medicalrecords'),
		'medicalrecord_diagnoses' => patients_registry_count($db, 'medicalrecord_diagnoses'),
	);
	if ($counts_before !== $counts_after) {
		patients_registry_fail('existing_rows_changed');
	}

	echo 'MIGRATION_ID=' . DOCLINC_PATIENTS_REGISTRY_MIGRATION_ID . "\n"
		. "EXECUTION_MODE=APPLY\n"
		. 'DDL_EXECUTED=' . ($ddl_executed ? 'true' : 'false') . "\n"
		. 'ALREADY_APPLIED=' . ($ddl_executed ? 'false' : 'true') . "\n"
		. "TABLES_CREATED={$tables_created}\n"
		. "COLUMNS_ADDED={$columns_added}\n"
		. "INDEXES_ADDED={$indexes_added}\n"
		. "FOREIGN_KEYS_ADDED={$foreign_keys_added}\n"
		. "EXISTING_ROWS_CHANGED=false\nDESTRUCTIVE_STATEMENTS=0\n";
} catch (Throwable $exception) {
	$safe = array(
		'database_identity_mismatch',
		'migration_lock_unavailable',
		'base_schema_mismatch',
		'patients_schema_mismatch',
		'schema_verification_failed',
		'existing_rows_changed',
	);
	$reason = in_array($exception->getMessage(), $safe, true) ? $exception->getMessage() : 'migration_execution_failed';
	patients_registry_fail($reason);
}
