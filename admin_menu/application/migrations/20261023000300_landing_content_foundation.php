<?php
/**
 * Fondasi konten landing page DocLinc.
 *
 * Mengapa tabel baru, bukan memakai tabel template lama
 * -----------------------------------------------------
 * Modul `landing_page` di panel berasal dari template lain dan membaca 16 tabel
 * `tbl_*` (tbl_home, tbl_harga, tbl_alat_tes, ...) yang tidak ada di database
 * DocLinc. Membuat tabel itu dari tebakan skema akan menghasilkan konten kosong
 * yang tampak jalan. Karena itu fondasi baru ini dirancang untuk kebutuhan
 * DocLinc saja, dengan dua tabel kecil:
 *
 *   - `landing_blocks`: blok teks berkunci (judul hero, ringkasan, tentang,
 *     kontak). Satu baris satu blok, kuncinya unik supaya kode pemanggil bisa
 *     menanyakan blok dengan nama, bukan nomor.
 *   - `landing_faqs`: tanya-jawab yang ditampilkan berurutan.
 *
 * Angka statistik landing (jumlah kecamatan, puskesmas, layanan selesai) TIDAK
 * disimpan di sini: itu dihitung dari data nyata saat ditampilkan, supaya tidak
 * ada angka basi yang harus diperbarui manual.
 *
 * Sifat: aditif, idempoten, tanpa pernyataan destruktif. Isi awal hanya ditulis
 * bila tabelnya masih kosong, dan teksnya sengaja hanya memuat hal yang benar
 * tentang aplikasi ini (alur konsultasi warga -> Puskesmas -> rekam medis).
 * Bagian yang faktanya belum diketahui (kontak resmi) diisi penanda agar diisi
 * pengelola, bukan dikarang.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261023000300_landing_content_foundation.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   DOCLINC_LANDING_CONTENT_APPLY=1 \
 *   DOCLINC_LANDING_CONTENT_BACKUP_REFERENCE="dump.sql.gz:sha256:..." \
 *   php admin_menu/application/migrations/20261023000300_landing_content_foundation.php \
 *     --apply --environment=staging --confirm-database=<db>
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_LANDING_CONTENT_MIGRATION_ID = '20261023000300_landing_content_foundation';

function landing_content_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function landing_content_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

/** Definisi tabel: hanya yang dibutuhkan halaman landing DocLinc. */
function landing_content_tables()
{
	return array(
		'landing_blocks' => 'CREATE TABLE `landing_blocks` ('
			. '`id` int(11) NOT NULL AUTO_INCREMENT,'
			. '`block_key` varchar(64) NOT NULL,'
			. '`judul` varchar(200) NOT NULL,'
			. '`isi` text NOT NULL,'
			. '`urutan` int(11) NOT NULL DEFAULT 0,'
			. '`aktif` tinyint(1) NOT NULL DEFAULT 1,'
			. '`updated_by_user_id` int(11) DEFAULT NULL,'
			. '`updated_at` datetime NOT NULL,'
			. 'PRIMARY KEY (`id`),'
			. 'UNIQUE KEY `uq_landing_blocks_key` (`block_key`)'
			. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
		'landing_faqs' => 'CREATE TABLE `landing_faqs` ('
			. '`id` int(11) NOT NULL AUTO_INCREMENT,'
			. '`pertanyaan` varchar(255) NOT NULL,'
			. '`jawaban` text NOT NULL,'
			. '`urutan` int(11) NOT NULL DEFAULT 0,'
			. '`aktif` tinyint(1) NOT NULL DEFAULT 1,'
			. '`updated_by_user_id` int(11) DEFAULT NULL,'
			. '`updated_at` datetime NOT NULL,'
			. 'PRIMARY KEY (`id`),'
			. 'KEY `idx_landing_faqs_urutan` (`aktif`, `urutan`)'
			. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
	);
}

/** Isi awal blok. Hanya pernyataan yang benar tentang aplikasi ini. */
function landing_content_blocks()
{
	return array(
		array(
			'block_key' => 'hero_judul',
			'judul' => 'Judul utama',
			'isi' => 'Konsultasi kesehatan daring untuk warga Kota Cilegon',
			'urutan' => 10,
		),
		array(
			'block_key' => 'hero_ringkasan',
			'judul' => 'Ringkasan pembuka',
			'isi' => 'Warga mengajukan konsultasi, Puskesmas menerima dan menugaskan tenaga kesehatan, '
				. 'lalu jawabannya disimpan sebagai rekam medis yang bisa ditinjau kembali.',
			'urutan' => 20,
		),
		array(
			'block_key' => 'tentang',
			'judul' => 'Tentang layanan',
			'isi' => 'DocLinc menghubungkan warga dengan Puskesmas di wilayah Kota Cilegon. '
				. 'Setiap konsultasi tercatat sebagai permintaan layanan, sehingga alurnya dapat dipantau '
				. 'dan rekam medisnya tetap tersimpan di fasilitas kesehatan yang menangani.',
			'urutan' => 30,
		),
		array(
			'block_key' => 'kontak',
			'judul' => 'Kontak resmi',
			'isi' => 'Belum diisi. Lengkapi dengan kanal resmi Dinas Kesehatan atau Puskesmas '
				. '(nomor telepon, surel, atau jam layanan) sebelum halaman ini dipublikasikan.',
			'urutan' => 40,
		),
	);
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:'));

$host = trim((string) (getenv('DOCLINC_LANDING_CONTENT_DB_HOST') ?: getenv('ADMIN_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1'));
$port = (int) (getenv('DOCLINC_LANDING_CONTENT_DB_PORT') ?: getenv('ADMIN_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_LANDING_CONTENT_DB_USER') ?: getenv('ADMIN_DB_USER') ?: getenv('DB_USER') ?: ''));
$password = getenv('DOCLINC_LANDING_CONTENT_DB_PASSWORD');
if ($password === false || $password === '') {
	$password = getenv('ADMIN_DB_PASS');
}
if ($password === false || $password === '') {
	$password = getenv('DB_PASS');
}
$database = trim((string) (getenv('DOCLINC_LANDING_CONTENT_DB_NAME') ?: getenv('ADMIN_DB_NAME') ?: getenv('DB_NAME') ?: ''));

$tables = landing_content_tables();
$blocks = landing_content_blocks();

if (!array_key_exists('apply', $options)) {
	/* Mode rencana: baca saja. */
	if ($database === '' || $user === '' || !is_string($password)) {
		echo 'MIGRATION_ID=' . DOCLINC_LANDING_CONTENT_MIGRATION_ID . "\n"
			. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=definitions_only\nDATABASE_WRITE_EXECUTED=false\n"
			. 'PLANNED_TABLES=' . count($tables) . ' PLANNED_BLOCKS=' . count($blocks) . "\n"
			. "DESTRUCTIVE_STATEMENTS=0\n";
		exit(0);
	}
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	try {
		$db = new mysqli($host, $user, $password, $database, $port);
		$db->set_charset('utf8mb4');
	} catch (Throwable $exception) {
		landing_content_fail('database_connection_failed', false);
	}
	$present = 0;
	$block_rows = 0;
	foreach (array_keys($tables) as $table) {
		$escaped = $db->real_escape_string($table);
		$row = $db->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$escaped}'")->fetch_assoc();
		if ((int) $row['total'] > 0) {
			$present++;
		}
	}
	if ($present === count($tables)) {
		$block_rows = (int) $db->query('SELECT COUNT(*) AS total FROM landing_blocks')->fetch_assoc()['total'];
	}
	echo 'MIGRATION_ID=' . DOCLINC_LANDING_CONTENT_MIGRATION_ID . "\n"
		. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=information_schema\nDATABASE_WRITE_EXECUTED=false\n"
		. 'PLANNED_TABLES=' . count($tables) . ' TABLES_PRESENT=' . $present . "\n"
		. 'PLANNED_BLOCKS=' . count($blocks) . ' CURRENT_BLOCKS=' . $block_rows . "\n"
		. 'PLANNED_SEED=' . ($present === count($tables) && $block_rows > 0 ? 0 : count($blocks)) . "\n"
		. "DESTRUCTIVE_STATEMENTS=0\n";
	exit(0);
}

/* ---------------------------------------------------------------------------
 * Mode terapkan: empat pagar - saklar env, --apply, konfirmasi nama database,
 * dan referensi backup.
 * ------------------------------------------------------------------------ */
if (!landing_content_true(getenv('DOCLINC_LANDING_CONTENT_APPLY'))) {
	landing_content_fail('landing_content_apply_disabled', false);
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('test', 'staging', 'uat', 'production'), true)) {
	landing_content_fail('environment_not_allowed', false);
}
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	landing_content_fail('database_confirmation_mismatch', false);
}
$backup_reference = trim((string) (getenv('DOCLINC_LANDING_CONTENT_BACKUP_REFERENCE') ?: ''));
if ($backup_reference === '') {
	landing_content_fail('backup_reference_missing', false);
}
if ($user === '' || !is_string($password)) {
	landing_content_fail('database_identity_missing', false);
}
if ($password === '' && !in_array(strtolower($host), array('127.0.0.1', 'localhost', '::1'), true)) {
	landing_content_fail('database_password_required_for_remote_host', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	landing_content_fail('database_connection_failed', false);
}

$lock = $db->query("SELECT GET_LOCK('doclinc_landing_content', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	landing_content_fail('landing_content_lock_unavailable');
}

/* DDL tidak dapat dibatalkan transaksi (MySQL commit implisit), jadi tabel
 * dibuat di luar transaksi; hanya pengisian isi awal yang dibungkus transaksi. */
$created = array();
foreach ($tables as $table => $definition) {
	$escaped = $db->real_escape_string($table);
	$row = $db->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$escaped}'")->fetch_assoc();
	if ((int) $row['total'] > 0) {
		continue;
	}
	$db->query($definition);
	$created[] = $table;
}

$existing_blocks = (int) $db->query('SELECT COUNT(*) AS total FROM landing_blocks')->fetch_assoc()['total'];
$seeded = 0;
if ($existing_blocks === 0) {
	/* Isi awal hanya ditulis bila tabelnya masih kosong: menjalankan ulang tidak
	 * boleh menimpa tulisan pengelola. */
	$db->begin_transaction();
	try {
		$statement = $db->prepare(
			'INSERT INTO landing_blocks (block_key, judul, isi, urutan, aktif, updated_by_user_id, updated_at) '
			. 'VALUES (?, ?, ?, ?, 1, NULL, NOW())'
		);
		foreach ($blocks as $block) {
			$statement->bind_param('sssi', $block['block_key'], $block['judul'], $block['isi'], $block['urutan']);
			$statement->execute();
			$seeded++;
		}
		$statement->close();
		$db->commit();
	} catch (Throwable $exception) {
		$db->rollback();
		landing_content_fail('landing_content_seed_failed');
	}
}
$db->query("SELECT RELEASE_LOCK('doclinc_landing_content')");

$verify_blocks = (int) $db->query('SELECT COUNT(*) AS total FROM landing_blocks')->fetch_assoc()['total'];
$verify_active = (int) $db->query('SELECT COUNT(*) AS total FROM landing_blocks WHERE aktif = 1')->fetch_assoc()['total'];
$verify_faqs = (int) $db->query('SELECT COUNT(*) AS total FROM landing_faqs')->fetch_assoc()['total'];

echo 'MIGRATION_ID=' . DOCLINC_LANDING_CONTENT_MIGRATION_ID . "\n"
	. "EXECUTION_MODE=APPLY\nDATABASE_WRITE_EXECUTED=true\n"
	. 'BACKUP_REFERENCE=' . $backup_reference . "\n"
	. 'TABLES_CREATED=' . count($created) . ' CREATED=' . (empty($created) ? 'none' : implode(',', $created)) . "\n"
	. 'BLOCKS_SEEDED=' . $seeded . "\n"
	. 'VERIFY_BLOCKS=' . $verify_blocks . ' VERIFY_BLOCKS_AKTIF=' . $verify_active . ' VERIFY_FAQ=' . $verify_faqs . "\n"
	. "DESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
exit(0);
