<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

$options = getopt('', array('host::', 'database::', 'user::', 'password::', 'port::'));

function option_or_env($options, $option, $env, $fallback = '')
{
	if (isset($options[$option]) && $options[$option] !== false && $options[$option] !== '') {
		return $options[$option];
	}

	foreach ($env as $name) {
		$value = getenv($name);
		if ($value !== false && $value !== '') {
			return $value;
		}
	}

	return $fallback;
}

$host = option_or_env($options, 'host', array('ADMIN_DB_HOST', 'DB_HOST'), 'localhost');
$database = option_or_env($options, 'database', array('ADMIN_DB_NAME', 'DB_NAME'));
$user = option_or_env($options, 'user', array('ADMIN_DB_USER', 'DB_USER'));
$password = option_or_env($options, 'password', array('ADMIN_DB_PASS', 'DB_PASS'));
$port = (int) option_or_env($options, 'port', array('ADMIN_DB_PORT', 'DB_PORT'), 3306);

if ($database === '' || $user === '') {
	fwrite(STDERR, "Missing database name or user.\n");
	exit(1);
}

if ($password === '') {
	fwrite(STDERR, "Missing database password. Set ADMIN_DB_PASS before running this migration.\n");
	exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	echo "Database connection OK.\n";
} catch (mysqli_sql_exception $e) {
	fwrite(STDERR, "Database connection failed. Check host, database, user, and ADMIN_DB_PASS.\n");
	fwrite(STDERR, "Driver message: " . $e->getMessage() . "\n");
	exit(1);
}

function run_query($db, $sql, $label)
{
	try {
		$db->query($sql);
		echo "[ok] " . $label . "\n";
	} catch (mysqli_sql_exception $e) {
		fwrite(STDERR, "[failed] " . $label . "\n");
		fwrite(STDERR, "Driver message: " . $e->getMessage() . "\n");
		exit(1);
	}
}

function table_exists($db, $table)
{
	$stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
	$stmt->bind_param('s', $table);
	$stmt->execute();
	$stmt->store_result();
	$exists = $stmt->num_rows > 0;
	$stmt->close();
	return $exists;
}

function column_exists($db, $table, $column)
{
	$stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
	$stmt->bind_param('ss', $table, $column);
	$stmt->execute();
	$stmt->store_result();
	$exists = $stmt->num_rows > 0;
	$stmt->close();
	return $exists;
}

function add_column_if_missing($db, $table, $column, $definition)
{
	if (!table_exists($db, $table)) {
		echo "[skip] missing table `$table`, column `$column`\n";
		return;
	}

	if (!column_exists($db, $table, $column)) {
		run_query($db, "ALTER TABLE `$table` ADD COLUMN `$column` $definition", "added column `$table`.`$column`");
		return;
	}

	echo "[skip] column exists `$table`.`$column`\n";
}

function create_table_if_missing($db, $table, $sql)
{
	if (table_exists($db, $table)) {
		echo "[skip] table exists `$table`\n";
		return;
	}

	run_query($db, $sql, "created table `$table`");
}

function run_query_if_tables_exist($db, $label, $sql, $tables)
{
	foreach ($tables as $table) {
		if (!table_exists($db, $table)) {
			echo "[skip] " . $label . " requires missing table `" . $table . "`\n";
			return;
		}
	}

	run_query($db, $sql, $label);
}

add_column_if_missing($db, 'users', 'remark', 'varchar(100) NULL AFTER `status`');
add_column_if_missing($db, 'users', 'updated_by', 'varchar(100) NULL AFTER `updated_at`');
add_column_if_missing($db, 'users', 'no_hp', 'varchar(50) NULL AFTER `email`');
add_column_if_missing($db, 'users', 'tgl', 'date NULL AFTER `no_hp`');
add_column_if_missing($db, 'users', 'gender', 'varchar(20) NULL AFTER `tgl`');
add_column_if_missing($db, 'users', 'alamat', 'text NULL AFTER `gender`');
add_column_if_missing($db, 'users', 'ktp', 'varchar(255) NULL AFTER `alamat`');
add_column_if_missing($db, 'users', 'foto', 'varchar(255) NULL AFTER `ktp`');
add_column_if_missing($db, 'requests', 'date', 'datetime NULL AFTER `request_status`');
add_column_if_missing($db, 'requests', 'location_detail', 'text NULL AFTER `location`');
add_column_if_missing($db, 'requests', 'lattitude_dokter', 'varchar(100) NULL AFTER `longitude`');
add_column_if_missing($db, 'requests', 'longitude_dokter', 'varchar(100) NULL AFTER `lattitude_dokter`');
add_column_if_missing($db, 'requests', 'assigned_puskesmas_code', 'varchar(100) NULL AFTER `dokter_id`');
add_column_if_missing($db, 'requests', 'assigned_puskesmas_name', 'varchar(255) NULL AFTER `assigned_puskesmas_code`');
add_column_if_missing($db, 'requests', 'patient_latitude', 'decimal(10,7) NULL AFTER `longitude_dokter`');
add_column_if_missing($db, 'requests', 'patient_longitude', 'decimal(10,7) NULL AFTER `patient_latitude`');
add_column_if_missing($db, 'requests', 'accepted_by_user_id', 'int(11) NULL AFTER `patient_longitude`');

$tables = array(
	'm_puskesmas' => "CREATE TABLE IF NOT EXISTS `m_puskesmas` (
		`kode_pkm` varchar(100) NOT NULL,
		`nama_puskesmas` varchar(150) NOT NULL,
		`alamat` text NULL,
		`latitude` decimal(10,7) NULL,
		`longitude` decimal(10,7) NULL,
		`status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
		`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`kode_pkm`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'keluhan' => "CREATE TABLE IF NOT EXISTS `keluhan` (
		`id_keluhan` varchar(20) NOT NULL,
		`kategori` varchar(100) NULL,
		`nama_keluhan` varchar(255) NOT NULL,
		`deskripsi` text NULL,
		`remark` text NULL,
		`status` enum('Aktif','Non-Aktif') NOT NULL DEFAULT 'Aktif',
		`create_user` varchar(100) NULL,
		`create_date` datetime NULL,
		`modify_user` varchar(100) NULL,
		`modify_date` datetime NULL,
		PRIMARY KEY (`id_keluhan`),
		KEY `idx_keluhan_status` (`status`),
		KEY `idx_keluhan_nama` (`nama_keluhan`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'feeds' => "CREATE TABLE IF NOT EXISTS `feeds` (
		`feedId` int(11) NOT NULL AUTO_INCREMENT,
		`subject` varchar(255) NOT NULL,
		`gambar` varchar(255) NULL,
		`status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
		`create_at` datetime NULL,
		`create_user` varchar(100) NULL,
		`updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`feedId`),
		KEY `idx_feeds_status` (`status`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'konsultasi' => "CREATE TABLE IF NOT EXISTS `konsultasi` (
		`konsul_id` int(11) NOT NULL AUTO_INCREMENT,
		`request_id` int(11) NOT NULL,
		`diagnosa` text NULL,
		`saran` text NULL,
		`kriteria` varchar(100) NULL,
		`rujukan` varchar(255) NULL,
		`foto` varchar(255) NULL,
		`create_date` datetime NULL,
		`create_user` varchar(100) NULL,
		`modify_date` datetime NULL,
		`modify_user` varchar(100) NULL,
		PRIMARY KEY (`konsul_id`),
		UNIQUE KEY `uniq_konsultasi_request` (`request_id`),
		KEY `idx_konsultasi_kriteria` (`kriteria`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'terapi' => "CREATE TABLE IF NOT EXISTS `terapi` (
		`terapi_id` int(11) NOT NULL AUTO_INCREMENT,
		`konsul_id` int(11) NOT NULL,
		`terapi` varchar(255) NULL,
		`signa` varchar(255) NULL,
		`keterangan` text NULL,
		`create_date` datetime NULL,
		`create_user` varchar(100) NULL,
		PRIMARY KEY (`terapi_id`),
		KEY `idx_terapi_konsul` (`konsul_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'kecamatan' => "CREATE TABLE IF NOT EXISTS `kecamatan` (
		`id_kecamatan` int(11) NOT NULL AUTO_INCREMENT,
		`nama_kecamatan` varchar(150) NOT NULL,
		PRIMARY KEY (`id_kecamatan`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'puskesmas' => "CREATE TABLE IF NOT EXISTS `puskesmas` (
		`id_puskesmas` int(11) NOT NULL AUTO_INCREMENT,
		`kode_pkm` varchar(100) NULL,
		`nama_puskesmas` varchar(150) NOT NULL,
		`id_kecamatan` int(11) NULL,
		PRIMARY KEY (`id_puskesmas`),
		KEY `idx_puskesmas_kode` (`kode_pkm`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	't_pengaduan' => "CREATE TABLE IF NOT EXISTS `t_pengaduan` (
		`id_pengaduan` int(11) NOT NULL AUTO_INCREMENT,
		`status` varchar(50) NULL,
		`kategori` varchar(100) NULL,
		`lokasi` int(11) NULL,
		`deskripsi` text NULL,
		`nama` varchar(150) NULL,
		`email` varchar(150) NULL,
		`tanggal` date NULL,
		`file` varchar(255) NULL,
		`dinas` varchar(150) NULL,
		`created_at` datetime NULL,
		PRIMARY KEY (`id_pengaduan`),
		KEY `idx_pengaduan_lokasi` (`lokasi`),
		KEY `idx_pengaduan_status` (`status`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	'audit_logs' => "CREATE TABLE IF NOT EXISTS `audit_logs` (
		`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		`actor_user_id` int(11) NULL,
		`action` varchar(100) NOT NULL,
		`entity_type` varchar(100) NULL,
		`entity_id` varchar(100) NULL,
		`ip_address` varchar(45) NULL,
		`user_agent` varchar(255) NULL,
		`metadata_json` text NULL,
		`created_at` datetime NOT NULL,
		PRIMARY KEY (`id`),
		KEY `idx_audit_logs_action` (`action`),
		KEY `idx_audit_logs_actor` (`actor_user_id`),
		KEY `idx_audit_logs_created_at` (`created_at`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

foreach ($tables as $table => $query) {
	create_table_if_missing($db, $table, $query);
}

add_column_if_missing($db, 'm_puskesmas', 'latitude', 'decimal(10,7) NULL AFTER `alamat`');
add_column_if_missing($db, 'm_puskesmas', 'longitude', 'decimal(10,7) NULL AFTER `latitude`');

$puskesmas_seed = array(
	array('10280101', 'Puskesmas Cilegon', -6.027077, 106.040176),
	array('10280201', 'Puskesmas Cibeber', -6.035414, 106.050550),
	array('10280301', 'Puskesmas Ciwandan', -5.978580, 105.992680),
	array('10280401', 'Puskesmas Pulo Merak', -5.917950, 106.025680),
	array('10280402', 'Puskesmas Grogol', -5.955560, 106.021060),
	array('10280501', 'Puskesmas Citangkil', -6.017040, 106.018410),
	array('10280601', 'Puskesmas Purwakarta', -6.017121, 106.061413),
	array('10280701', 'Puskesmas Jombang', -6.017770, 106.050300),
	array('2241001', 'Puskesmas Citangkil II', -6.012550, 106.007620),
);

if (table_exists($db, 'm_puskesmas')) {
	$stmt = $db->prepare("INSERT INTO `m_puskesmas` (`kode_pkm`, `nama_puskesmas`, `latitude`, `longitude`, `status`)
		VALUES (?, ?, ?, ?, 'aktif')
		ON DUPLICATE KEY UPDATE
			`nama_puskesmas` = VALUES(`nama_puskesmas`),
			`latitude` = VALUES(`latitude`),
			`longitude` = VALUES(`longitude`),
			`status` = 'aktif'");
	foreach ($puskesmas_seed as $row) {
		$stmt->bind_param('ssdd', $row[0], $row[1], $row[2], $row[3]);
		$stmt->execute();
		echo "[ok] seeded puskesmas `" . $row[0] . "`\n";
	}
	$stmt->close();
}

$queries = array(
	array('seed default puskesmas', "INSERT INTO `m_puskesmas` (`kode_pkm`, `nama_puskesmas`, `status`)
		SELECT 'DEFAULT', 'Puskesmas Default', 'nonaktif'
		WHERE NOT EXISTS (SELECT 1 FROM `m_puskesmas` WHERE `kode_pkm` = 'DEFAULT')"),
	array('disable default puskesmas fallback', "UPDATE `m_puskesmas`
		SET `status` = 'nonaktif'
		WHERE `kode_pkm` = 'DEFAULT'"),
	array('backfill users remark', "UPDATE `users`
		SET `remark` = 'DEFAULT'
		WHERE `remark` IS NULL OR `remark` = ''"),
	array('sync doctors into m_dokter', "INSERT IGNORE INTO `m_dokter` (`professional_id`, `name`, `phone`, `email`, `password`, `specialization`, `status`, `created_at`)
		SELECT `userId`, `nama`, CONCAT('user-', `userId`), `email`, `password`, 'Umum', 'On Duty', COALESCE(`created_at`, NOW())
		FROM `users`
		WHERE `role` = 'dokter'"),
	array('backfill requests date', "UPDATE `requests`
		SET `date` = COALESCE(`date`, `created_at`)
		WHERE `date` IS NULL"),
	array('mirror medical records into konsultasi', "INSERT INTO `konsultasi` (`request_id`, `diagnosa`, `saran`, `kriteria`, `create_date`, `create_user`)
		SELECT `medicalrecords`.`request_id`, `medicalrecords`.`diagnosis`, `medicalrecords`.`recommendations`, 'Selesai Konsultasi', `medicalrecords`.`created_at`, 'system'
		FROM `medicalrecords`
		ON DUPLICATE KEY UPDATE
			`diagnosa` = VALUES(`diagnosa`),
			`saran` = VALUES(`saran`),
			`create_date` = VALUES(`create_date`)")
);

foreach ($queries as $query) {
	$required_tables = array();
	if ($query[0] === 'seed default puskesmas') {
		$required_tables = array('m_puskesmas');
	} elseif ($query[0] === 'disable default puskesmas fallback') {
		$required_tables = array('m_puskesmas');
	} elseif ($query[0] === 'backfill users remark') {
		$required_tables = array('users');
	} elseif ($query[0] === 'sync doctors into m_dokter') {
		$required_tables = array('m_dokter', 'users');
	} elseif ($query[0] === 'backfill requests date') {
		$required_tables = array('requests');
	} elseif ($query[0] === 'mirror medical records into konsultasi') {
		$required_tables = array('medicalrecords', 'konsultasi');
	}

	run_query_if_tables_exist($db, $query[0], $query[1], $required_tables);
}

echo "Admin database compatibility migration completed.\n";
