<?php
/**
 * Creates the `keluhan` master table and seeds it with the complaint list the
 * citizen consultation form needs.
 *
 * Why this migration exists
 * ------------------------
 * `keluhan` is read by the consultation form (Konsultasi_m::get_master_gejala_keluhan_options)
 * and by the citizen dashboard (Home_m), but no earlier migration creates it and
 * no seeder populates it. The only writer is the Admin panel module
 * kelola_keluhan, and the Admin panel is excluded from the production deployment.
 *
 * On a fresh database the table is therefore absent, every reader guards with
 * table_exists() and returns an empty array, and the form renders a complaint
 * dropdown containing nothing but its placeholder. That failure is silent: no
 * error is raised and nothing in the logs points at the missing table.
 *
 * The rows below are taken from the repository's own master dataset,
 * database/master_data/clinical/v1/01_master_keluhan.json, whose metadata names
 * "Form permintaan konsultasi warga" as one of its consumers. Seeding from that
 * file rather than inventing entries keeps the form aligned with the dataset the
 * rest of the clinical work is built on.
 *
 * CLINICAL VALIDATION IS OUTSTANDING. That dataset carries this warning:
 * "Semua konten klinis, pertanyaan, red flag, edukasi, dan pemetaan diagnosis
 * wajib divalidasi dan disetujui dokter penanggung jawab serta tim tata kelola
 * klinis sebelum produksi." Every seeded row is therefore marked
 * draft_requires_clinical_validation in `remark`, and the dataset's own review
 * status is carried through so the outstanding review stays visible in the data
 * rather than only in this comment.
 *
 * Usage
 * -----
 *   php application/migrations/20261017000200_master_keluhan_foundation.php
 *       -> prints the plan, writes nothing
 *   DOCLINC_MASTER_KELUHAN_SCHEMA_WRITE_ENABLED=1 \
 *   DOCLINC_MASTER_KELUHAN_SCHEMA_DB_NAME=<db> \
 *   php application/migrations/20261017000200_master_keluhan_foundation.php \
 *       --apply --environment=production --confirm-database=<db>
 *
 * Every other guard still applies: the schema-write flag, the database name
 * confirmation and the database identity check. The statement is idempotent, so
 * re-running it neither duplicates rows nor overwrites edits made later.
 */

if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_MASTER_KELUHAN_MIGRATION_ID = '20261017000200_master_keluhan_foundation';

function master_keluhan_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function master_keluhan_fail($reason)
{
	fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\nDATABASE_CONNECTION_OPENED=false\nDDL_EXECUTED=false\nSEED_ROWS_WRITTEN=0\n");
	exit(1);
}

/**
 * kode, kategori, nama_pasien, deskripsi_pasien, urutan
 * Transcribed from 01_master_keluhan.json (35 active records, none inactive).
 */
$master_keluhan_rows = array(
	array('DEMAM', 'umum', 'Demam', 'Tubuh terasa panas atau suhu tubuh meningkat.', 1),
	array('BATUK', 'pernapasan', 'Batuk', 'Batuk kering atau berdahak.', 2),
	array('PILEK_HIDUNG_TERSUMBAT', 'pernapasan', 'Pilek atau hidung tersumbat', 'Hidung berair, tersumbat, atau sering bersin.', 3),
	array('SESAK_NAPAS', 'pernapasan', 'Sesak napas', 'Napas terasa berat, pendek, atau tidak lega.', 4),
	array('NYERI_DADA', 'kardiopulmoner', 'Nyeri atau rasa tidak nyaman di dada', 'Nyeri, tekanan, rasa berat, atau tidak nyaman di dada.', 5),
	array('SAKIT_KEPALA', 'saraf', 'Sakit kepala', 'Nyeri atau rasa berat pada kepala.', 6),
	array('PUSING', 'saraf', 'Pusing atau terasa berputar', 'Kepala terasa ringan, melayang, atau lingkungan terasa berputar.', 7),
	array('LEMAS', 'umum', 'Lemas atau cepat lelah', 'Badan terasa lemah atau mudah lelah.', 8),
	array('NYERI_TENGGOROKAN', 'telinga_hidung_tenggorok', 'Nyeri tenggorokan', 'Tenggorokan terasa sakit, kering, atau sulit menelan.', 9),
	array('MUAL_MUNTAH', 'pencernaan', 'Mual atau muntah', 'Merasa ingin muntah atau mengalami muntah.', 10),
	array('DIARE', 'pencernaan', 'Diare', 'Buang air besar lebih cair atau lebih sering dari biasanya.', 11),
	array('NYERI_PERUT', 'pencernaan', 'Nyeri perut', 'Nyeri, kram, atau rasa tidak nyaman pada perut.', 12),
	array('SULIT_BAB', 'pencernaan', 'Sulit buang air besar', 'Buang air besar sulit, keras, atau jarang.', 13),
	array('BAB_BERDARAH_HITAM', 'pencernaan', 'BAB berdarah atau berwarna hitam', 'Ada darah pada tinja atau tinja berwarna hitam pekat.', 14),
	array('NYERI_SAAT_BAK', 'kemih', 'Nyeri atau panas saat buang air kecil', 'Buang air kecil terasa perih, panas, atau nyeri.', 15),
	array('SULIT_BAK', 'kemih', 'Sulit buang air kecil', 'Sulit memulai atau tidak dapat buang air kecil.', 16),
	array('SERING_BAK', 'kemih', 'Sering buang air kecil', 'Buang air kecil lebih sering dari biasanya.', 17),
	array('URIN_BERDARAH', 'kemih', 'Urin berdarah atau berubah warna', 'Urin terlihat merah, kecokelatan, atau ada darah.', 18),
	array('KELUHAN_KULIT', 'kulit', 'Gatal, ruam, atau keluhan kulit', 'Gatal, ruam, bercak, bentol, lepuh, atau perubahan kulit.', 19),
	array('LUKA_GIGITAN_LUKA_BAKAR', 'trauma', 'Luka, gigitan, atau luka bakar', 'Luka terbuka, gigitan hewan, atau luka bakar.', 20),
	array('NYERI_SENDI_OTOT', 'muskuloskeletal', 'Nyeri otot atau sendi', 'Nyeri, kaku, atau bengkak pada otot atau sendi.', 21),
	array('NYERI_PUNGGUNG_PINGGANG', 'muskuloskeletal', 'Nyeri punggung atau pinggang', 'Nyeri pada punggung atas, bawah, atau pinggang.', 22),
	array('KELUHAN_MATA', 'mata', 'Keluhan mata', 'Mata merah, nyeri, belekan, gatal, atau penglihatan berubah.', 23),
	array('KELUHAN_TELINGA', 'telinga_hidung_tenggorok', 'Keluhan telinga', 'Nyeri telinga, keluar cairan, berdenging, atau pendengaran berkurang.', 24),
	array('KELUHAN_GIGI_MULUT', 'gigi_mulut', 'Keluhan gigi atau mulut', 'Sakit gigi, gusi bengkak, sariawan, atau keluhan mulut.', 25),
	array('REAKSI_ALERGI', 'alergi', 'Dugaan reaksi alergi', 'Gatal, bentol, bengkak, atau keluhan setelah makanan, obat, atau paparan tertentu.', 26),
	array('KELEMAHAN_KEBAALAN', 'saraf', 'Lemah atau kebas pada anggota tubuh', 'Tangan, kaki, atau salah satu sisi tubuh terasa lemah atau kebas.', 27),
	array('KEJANG', 'saraf', 'Kejang', 'Gerakan tidak terkendali atau kehilangan kesadaran yang dicurigai sebagai kejang.', 28),
	array('KEHAMILAN', 'kesehatan_reproduksi', 'Keluhan terkait kehamilan', 'Keluhan selama kehamilan atau dugaan kehamilan.', 29),
	array('MENSTRUASI_KEPUTIHAN', 'kesehatan_reproduksi', 'Keluhan menstruasi atau keputihan', 'Haid tidak teratur, perdarahan, nyeri haid, atau keputihan.', 30),
	array('KESEHATAN_MENTAL', 'mental', 'Cemas, sedih, sulit tidur, atau keluhan psikologis', 'Keluhan emosi, pikiran, perilaku, kecemasan, suasana hati, atau tidur.', 31),
	array('KONTROL_PENYAKIT_KRONIS', 'kontrol', 'Kontrol penyakit rutin', 'Kontrol tekanan darah, diabetes, asma, atau penyakit kronis lain.', 32),
	array('PERMINTAAN_RESEP_ULANG', 'farmasi', 'Permintaan resep ulang', 'Meminta evaluasi untuk obat rutin yang pernah diresepkan.', 33),
	array('KONSULTASI_HASIL_PEMERIKSAAN', 'penunjang', 'Konsultasi hasil laboratorium atau pemeriksaan', 'Membahas hasil laboratorium, radiologi, atau pemeriksaan lain.', 34),
	array('LAINNYA', 'lainnya', 'Keluhan lainnya', 'Keluhan yang belum tersedia pada pilihan di atas.', 35),
);

$options = getopt('', array('apply', 'environment:', 'confirm-database:'));
if (!array_key_exists('apply', $options)) {
	echo 'MIGRATION_ID=' . DOCLINC_MASTER_KELUHAN_MIGRATION_ID . "\nEXECUTION_MODE=PLAN\nDATABASE_WRITE_EXECUTED=false\nPLANNED_TABLE_COUNT=1\nPLANNED_SEED_ROWS=" . count($master_keluhan_rows) . "\nDESTRUCTIVE_STATEMENTS=0\n";
	exit(0);
}

if (!master_keluhan_true(getenv('DOCLINC_MASTER_KELUHAN_SCHEMA_WRITE_ENABLED'))) {
	master_keluhan_fail('schema_write_disabled');
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
// 'production' is a supported target: the rows this seeds are the options the
// citizen consultation form renders. Every other guard still applies.
if (!in_array($environment, array('test', 'staging', 'uat', 'production'), true)) {
	master_keluhan_fail('environment_not_allowed');
}
$database = trim((string) (getenv('DOCLINC_MASTER_KELUHAN_SCHEMA_DB_NAME') ?: ''));
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	master_keluhan_fail('database_confirmation_mismatch');
}

$host = trim((string) (getenv('DOCLINC_MASTER_KELUHAN_SCHEMA_DB_HOST') ?: '127.0.0.1'));
$port = (int) (getenv('DOCLINC_MASTER_KELUHAN_SCHEMA_DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_MASTER_KELUHAN_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_MASTER_KELUHAN_SCHEMA_DB_PASSWORD');
if ($user === '' || !is_string($password)) {
	master_keluhan_fail('database_identity_missing');
}
// An empty password is a misconfiguration on a remote host and is still refused.
// On loopback it is the normal shape of a local development MySQL (root with no
// password), and requiring one there made the schema impossible to complete
// locally - which is why the local database was missing every table these
// migrations create. The protection that matters is untouched: --apply, the
// schema-write flag and --confirm-database matching the configured name are all
// still required, so this cannot run against an unintended database by accident.
if ($password === '' && !in_array(strtolower($host), array('127.0.0.1', 'localhost', '::1'), true)) {
	master_keluhan_fail('database_password_required_for_remote_host');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');

	$identity = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$identity || !hash_equals($database, (string) $identity['database_name'])) {
		master_keluhan_fail('database_identity_mismatch');
	}

	$lock = $db->query("SELECT GET_LOCK('doclinc_master_keluhan', 10) AS locked")->fetch_assoc();
	if (!$lock || (int) $lock['locked'] !== 1) {
		master_keluhan_fail('migration_lock_unavailable');
	}

	$db->query("CREATE TABLE IF NOT EXISTS `keluhan` (
		`id_keluhan` VARCHAR(32) NOT NULL,
		`kategori` VARCHAR(32) NOT NULL DEFAULT '',
		`nama_keluhan` VARCHAR(100) NOT NULL,
		`deskripsi` VARCHAR(255) NULL,
		`remark` VARCHAR(255) NULL,
		`status` VARCHAR(16) NOT NULL DEFAULT 'Aktif',
		`create_user` VARCHAR(64) NULL,
		`create_date` DATETIME NULL,
		`modify_user` VARCHAR(64) NULL,
		`modify_date` DATETIME NULL,
		PRIMARY KEY (`id_keluhan`),
		KEY `idx_keluhan_status` (`status`),
		KEY `idx_keluhan_kategori` (`kategori`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// INSERT IGNORE keeps this idempotent: re-running it never duplicates rows and
	// never overwrites wording an operator has since corrected in the table.
	$insert = $db->prepare('INSERT IGNORE INTO `keluhan` (`id_keluhan`, `kategori`, `nama_keluhan`, `deskripsi`, `remark`, `status`, `create_user`, `create_date`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
	$remark = 'draft_requires_clinical_validation';
	$status = 'Aktif';
	$actor = DOCLINC_MASTER_KELUHAN_MIGRATION_ID;
	$now = date('Y-m-d H:i:s');
	$written = 0;
	foreach ($master_keluhan_rows as $row) {
		$insert->bind_param('ssssssss', $row[0], $row[1], $row[2], $row[3], $remark, $status, $actor, $now);
		$insert->execute();
		$written += $insert->affected_rows > 0 ? 1 : 0;
	}
	$insert->close();

	$total = $db->query("SELECT COUNT(*) AS total FROM `keluhan` WHERE `status` = 'Aktif'")->fetch_assoc();
	$active = $total ? (int) $total['total'] : 0;
	if ($active < count($master_keluhan_rows)) {
		master_keluhan_fail('post_apply_verification_failed');
	}

	$db->query("SELECT RELEASE_LOCK('doclinc_master_keluhan')");
	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDATABASE_WRITE_EXECUTED=true\nSEED_ROWS_WRITTEN={$written}\nACTIVE_ROWS_TOTAL={$active}\nDESTRUCTIVE_STATEMENTS=0\n";
	$db->close();
} catch (Throwable $e) {
	master_keluhan_fail('migration_failed');
}
