<?php
/**
 * Fixture akses klinis untuk harness lokal panel admin DocLink.
 *
 * Dua hal yang dibutuhkan halaman arsip rekam medis agar tombol "Buka rekam"
 * benar-benar bisa dibuka — keduanya TIDAK dibuat oleh `bootstrap.php`:
 *
 *   1. tabel `clinical_access_audit`, dan
 *   2. baris kapabilitas `clinical_audit` untuk akun admin.
 *
 * `super_admin` tidak otomatis memberi `clinical_audit`
 * (`Admin_capability_policy::allowedCodes()` mendaftar keduanya terpisah), jadi
 * tanpa butir 2 setiap permintaan berakhir 403 dan modalnya tidak pernah
 * terisi.
 *
 * Kenapa tabelnya dibuat di sini, bukan dengan menjalankan migrasi aslinya:
 * `application/migrations/20260814000100_admin_capability_clinical_access_audit.php`
 * sengaja menolak nama database apa pun selain `doclinc-staging` (mode
 * staging) atau `doclinc_admin_capability_test_*` / `doclinc_governance_test_*`
 * (mode test). Fixture ini bernama `doclinc_stage_local_test` — justru syarat
 * yang dipakai `bootstrap.php` dan `wilayah_seed_test.php`. Jadi DDL di bawah
 * adalah salinan yang disengaja dari migrasi itu, dan skrip ini memeriksa
 * bentuk hasilnya (tipe kolom, PRIMARY, indeks unik) tepat seperti
 * `admin_capability_verify()` milik migrasi. Bila keduanya melenceng, skrip
 * berhenti dengan daftar yang tidak cocok alih-alih diam.
 *
 * Jalankan di dalam kontainer aplikasi (env ADMIN_DB_* sudah ada di sana):
 *
 *   docker exec -e ALLOW_LOCAL_FIXTURE=1 doclinc-app \
 *     php /var/www/html/tools/admin_panel/local/fixture_clinical.php
 *
 * atau tanpa Docker, dengan PHP + MariaDB lokal:
 *
 *   ALLOW_LOCAL_FIXTURE=1 ADMIN_DB_HOST=127.0.0.1 ADMIN_DB_PORT=3307 \
 *   ADMIN_DB_NAME=doclinc_stage_local_test ADMIN_DB_USER=doclinc \
 *   ADMIN_DB_PASS=doclinclokal php tools/admin_panel/local/fixture_clinical.php
 *
 * `--revoke` mencabut kembali kapabilitasnya (tanpa menghapus tabel) supaya
 * keadaan "tanpa akses klinis" bisa diuji tanpa menulis SQL manual.
 *
 * Penjaga: host wajib loopback atau nama layanan compose (`db`/`mariadb`),
 * nama database wajib memuat "stage" DAN "test", dan `ALLOW_LOCAL_FIXTURE=1`
 * wajib disetel — sama seperti `bootstrap.php`.
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "refuse: hanya untuk CLI\n");
	exit(2);
}

/** Nilai env dengan fallback berurutan, mengikuti gaya `bootstrap.php`. */
function clinical_env($names, $fallback = '')
{
	foreach ((array) $names as $name) {
		$value = getenv($name);
		if ($value !== false && $value !== '') {
			return $value;
		}
	}
	return $fallback;
}

function clinical_fail($message)
{
	fwrite(STDERR, "REFUSE: {$message}\n");
	exit(2);
}

if (getenv('ALLOW_LOCAL_FIXTURE') !== '1') {
	clinical_fail('set ALLOW_LOCAL_FIXTURE=1 (penjaga supaya tidak jalan tanpa sengaja)');
}

$revoke = in_array('--revoke', $_SERVER['argv'], true);

$host = strtolower(trim((string) clinical_env(array('ADMIN_DB_HOST', 'DB_HOST'), '127.0.0.1')));
$database = (string) clinical_env(array('ADMIN_DB_NAME', 'DB_NAME'));
$user = (string) clinical_env(array('ADMIN_DB_USER', 'DB_USER'));
$password = (string) clinical_env(array('ADMIN_DB_PASS', 'DB_PASS'));
$port = (int) clinical_env(array('ADMIN_DB_PORT', 'DB_PORT'), 3306);

if (!in_array($host, array('127.0.0.1', 'localhost', 'db', 'mariadb'), true)) {
	clinical_fail("host database '{$host}' bukan loopback/layanan compose");
}
if ($database === '') {
	clinical_fail('ADMIN_DB_NAME/DB_NAME kosong');
}
if (stripos($database, 'stage') === false || stripos($database, 'test') === false) {
	clinical_fail("nama database '{$database}' wajib memuat 'stage' dan 'test'");
}
if ($user === '') {
	clinical_fail('ADMIN_DB_USER/DB_USER kosong');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
} catch (mysqli_sql_exception $e) {
	clinical_fail('koneksi gagal: ' . $e->getMessage());
}
$db->set_charset('utf8mb4');

$tables = array();
$result = $db->query('SHOW TABLES');
while ($row = $result->fetch_array(MYSQLI_NUM)) {
	$tables[strtolower((string) $row[0])] = true;
}

foreach (array('users', 'admin_user_capabilities') as $required) {
	if (!isset($tables[$required])) {
		clinical_fail("tabel '{$required}' belum ada — jalankan bootstrap.php --fresh lebih dulu");
	}
}


/* DDL dari application/migrations/20260814000100_admin_capability_clinical_access_audit.php
   (lihat catatan di kepala berkas: migrasi itu menolak nama database fixture). */
$ddl = "CREATE TABLE IF NOT EXISTS `clinical_access_audit` (
	`access_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`actor_user_id` INT(11) NOT NULL,
	`record_id` INT(11) NOT NULL,
	`request_id` INT(11) NULL,
	`patient_user_id` INT(11) NULL,
	`puskesmas_code` VARCHAR(100) NULL,
	`access_kind` VARCHAR(64) NOT NULL,
	`audit_session_hash` CHAR(64) NOT NULL,
	`first_access_in_session` TINYINT(1) NOT NULL DEFAULT 0,
	`reason_code` VARCHAR(64) NULL,
	`reason_text` VARCHAR(500) NULL,
	`reason_root_access_id` BIGINT UNSIGNED NULL,
	`ip_address` VARCHAR(45) NULL,
	`user_agent` VARCHAR(255) NULL,
	`created_at` DATETIME NOT NULL,
	PRIMARY KEY (`access_id`),
	KEY `idx_clinical_access_record_session` (`record_id`, `audit_session_hash`),
	KEY `idx_clinical_access_actor_created` (`actor_user_id`, `created_at`),
	KEY `idx_clinical_access_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$created = !isset($tables['clinical_access_audit']);
$db->query($ddl);

/* Bentuk tabel diperiksa, bukan diasumsikan. `int(11)` dan `bigint unsigned`
   ditulis berbeda oleh MariaDB dan MySQL, jadi lebar tampilan dibuang dulu
   sebelum dibandingkan. */
function clinical_column($db, $schema, $table, $column)
{
	$stmt = $db->prepare('SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
	$stmt->bind_param('sss', $schema, $table, $column);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $row;
}

function clinical_index($db, $schema, $table, $index)
{
	$stmt = $db->prepare('SELECT NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? GROUP BY NON_UNIQUE');
	$stmt->bind_param('sss', $schema, $table, $index);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $row;
}

function clinical_type($type)
{
	return preg_replace('/\(\d+\)/', '', strtolower(trim((string) $type)));
}

$expected_columns = array(
	array('access_id', 'bigint unsigned', 'NO'),
	array('actor_user_id', 'int', 'NO'),
	array('record_id', 'int', 'NO'),
	array('audit_session_hash', 'char', 'NO'),
	array('created_at', 'datetime', 'NO'),
);
$expected_indexes = array(
	array('PRIMARY', 'access_id', 0),
	array('idx_clinical_access_record_session', 'record_id,audit_session_hash', 1),
);

$problems = array();
foreach ($expected_columns as $expect) {
	list($column, $type, $nullable) = $expect;
	$actual = clinical_column($db, $database, 'clinical_access_audit', $column);
	if (!$actual) {
		$problems[] = "kolom {$column} tidak ada";
		continue;
	}
	if (clinical_type($actual['COLUMN_TYPE']) !== $type) {
		$problems[] = "kolom {$column} bertipe {$actual['COLUMN_TYPE']}, seharusnya {$type}";
	}
	if (strtoupper((string) $actual['IS_NULLABLE']) !== $nullable) {
		$problems[] = "kolom {$column} nullable={$actual['IS_NULLABLE']}, seharusnya {$nullable}";
	}
}
foreach ($expected_indexes as $expect) {
	list($index, $columns, $unique) = $expect;
	$actual = clinical_index($db, $database, 'clinical_access_audit', $index);
	if (!$actual) {
		$problems[] = "indeks {$index} tidak ada";
		continue;
	}
	if ((string) $actual['columns_list'] !== $columns) {
		$problems[] = "indeks {$index} berkolom {$actual['columns_list']}, seharusnya {$columns}";
	}
	if ((int) $actual['NON_UNIQUE'] !== (int) $unique) {
		$problems[] = "indeks {$index} non_unique={$actual['NON_UNIQUE']}, seharusnya {$unique}";
	}
}
if ($problems) {
	fwrite(STDERR, "GAGAL: bentuk clinical_access_audit melenceng dari migrasi:\n");
	foreach ($problems as $problem) {
		fwrite(STDERR, "  - {$problem}\n");
	}
	exit(1);
}


$granted = array();
$revoked = 0;
if ($revoke) {
	$stmt = $db->prepare("DELETE FROM admin_user_capabilities WHERE capability_code='clinical_audit'");
	$stmt->execute();
	$revoked = (int) $stmt->affected_rows;
	$stmt->close();
} else {
	/* Akun admin aktif di fixture diberi kapabilitas ini. `granted_by_user_id`
	   diisi id pemiliknya sendiri karena fixture tidak punya "pemberi" lain;
	   kolomnya NOT NULL di migrasi, jadi tidak boleh dibiarkan kosong. */
	$db->query("INSERT INTO admin_user_capabilities (user_id, capability_code, granted_by_user_id, granted_at)
		SELECT u.userId, 'clinical_audit', u.userId, NOW() FROM users u
		WHERE u.role='admin' AND u.status='aktif'
		ON DUPLICATE KEY UPDATE user_id = user_id");
	$rows = $db->query("SELECT c.user_id FROM admin_user_capabilities c JOIN users u ON u.userId=c.user_id
		WHERE c.capability_code='clinical_audit' AND u.role='admin' AND u.status='aktif' ORDER BY c.user_id");
	while ($row = $rows->fetch_assoc()) {
		$granted[] = (int) $row['user_id'];
	}
}

$audit_rows = (int) $db->query('SELECT COUNT(*) AS total FROM clinical_access_audit')->fetch_assoc()['total'];
$db->close();

echo "tabel clinical_access_audit : " . ($created ? 'dibuat' : 'sudah ada') . "\n";
echo "bentuk tabel                : cocok dengan migrasi\n";
if ($revoke) {
	echo "kapabilitas clinical_audit  : dicabut ({$revoked} baris)\n";
} else {
	echo 'kapabilitas clinical_audit  : diberikan ke user ' . ($granted ? implode(', ', $granted) : '(tidak ada admin aktif)') . "\n";
}
echo "baris audit tersimpan       : {$audit_rows}\n";
echo "FIXTURE_RESULT=PASS\n";
