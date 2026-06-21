<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

$options = getopt('', array('host::', 'database::', 'user::', 'password::', 'port::'));

$host = $options['host'] ?? getenv('ADMIN_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$database = $options['database'] ?? getenv('ADMIN_DB_NAME') ?: getenv('DB_NAME') ?: '';
$user = $options['user'] ?? getenv('ADMIN_DB_USER') ?: getenv('DB_USER') ?: '';
$password = $options['password'] ?? getenv('ADMIN_DB_PASS') ?: getenv('DB_PASS') ?: '';
$port = (int) ($options['port'] ?? getenv('ADMIN_DB_PORT') ?: getenv('DB_PORT') ?: 3306);

if ($database === '' || $user === '') {
	fwrite(STDERR, "Missing database name or user.\n");
	exit(1);
}

$db = new mysqli($host, $user, $password, $database, $port);
if ($db->connect_errno) {
	fwrite(STDERR, "Database connection failed: " . $db->connect_error . "\n");
	exit(1);
}

$db->set_charset('utf8mb4');

function run_query($db, $sql)
{
	if (!$db->query($sql)) {
		fwrite(STDERR, "Query failed: " . $db->error . "\nSQL: " . $sql . "\n");
		exit(1);
	}
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
	if (!column_exists($db, $table, $column)) {
		run_query($db, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
	}
}

add_column_if_missing($db, 'users', 'remark', 'varchar(100) NULL AFTER `status`');
add_column_if_missing($db, 'users', 'updated_by', 'varchar(100) NULL AFTER `updated_at`');
add_column_if_missing($db, 'requests', 'date', 'datetime NULL AFTER `request_status`');
add_column_if_missing($db, 'requests', 'location_detail', 'text NULL AFTER `location`');
add_column_if_missing($db, 'requests', 'lattitude_dokter', 'varchar(100) NULL AFTER `longitude`');
add_column_if_missing($db, 'requests', 'longitude_dokter', 'varchar(100) NULL AFTER `lattitude_dokter`');

$queries = array(
	"CREATE TABLE IF NOT EXISTS `m_puskesmas` (
		`kode_pkm` varchar(100) NOT NULL,
		`nama_puskesmas` varchar(150) NOT NULL,
		`alamat` text NULL,
		`status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
		`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`kode_pkm`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	"CREATE TABLE IF NOT EXISTS `keluhan` (
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
	"CREATE TABLE IF NOT EXISTS `feeds` (
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
	"CREATE TABLE IF NOT EXISTS `konsultasi` (
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
	"CREATE TABLE IF NOT EXISTS `terapi` (
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
	"CREATE TABLE IF NOT EXISTS `kecamatan` (
		`id_kecamatan` int(11) NOT NULL AUTO_INCREMENT,
		`nama_kecamatan` varchar(150) NOT NULL,
		PRIMARY KEY (`id_kecamatan`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	"CREATE TABLE IF NOT EXISTS `puskesmas` (
		`id_puskesmas` int(11) NOT NULL AUTO_INCREMENT,
		`kode_pkm` varchar(100) NULL,
		`nama_puskesmas` varchar(150) NOT NULL,
		`id_kecamatan` int(11) NULL,
		PRIMARY KEY (`id_puskesmas`),
		KEY `idx_puskesmas_kode` (`kode_pkm`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	"CREATE TABLE IF NOT EXISTS `t_pengaduan` (
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
	"INSERT INTO `m_puskesmas` (`kode_pkm`, `nama_puskesmas`, `status`)
		SELECT 'DEFAULT', 'Puskesmas Default', 'aktif'
		WHERE NOT EXISTS (SELECT 1 FROM `m_puskesmas` WHERE `kode_pkm` = 'DEFAULT')",
	"UPDATE `users`
		SET `remark` = 'DEFAULT'
		WHERE `remark` IS NULL OR `remark` = ''",
	"INSERT IGNORE INTO `m_dokter` (`professional_id`, `name`, `phone`, `email`, `password`, `specialization`, `status`, `created_at`)
		SELECT `userId`, `nama`, CONCAT('user-', `userId`), `email`, `password`, 'Umum', 'On Duty', COALESCE(`created_at`, NOW())
		FROM `users`
		WHERE `role` = 'dokter'",
	"UPDATE `requests`
		SET `date` = COALESCE(`date`, `created_at`)
		WHERE `date` IS NULL",
	"INSERT INTO `konsultasi` (`request_id`, `diagnosa`, `saran`, `kriteria`, `create_date`, `create_user`)
		SELECT `medicalrecords`.`request_id`, `medicalrecords`.`diagnosis`, `medicalrecords`.`recommendations`, 'Selesai Konsultasi', `medicalrecords`.`created_at`, 'system'
		FROM `medicalrecords`
		ON DUPLICATE KEY UPDATE
			`diagnosa` = VALUES(`diagnosa`),
			`saran` = VALUES(`saran`),
			`create_date` = VALUES(`create_date`)"
);

foreach ($queries as $query) {
	run_query($db, $query);
}

echo "Admin database compatibility migration completed.\n";
