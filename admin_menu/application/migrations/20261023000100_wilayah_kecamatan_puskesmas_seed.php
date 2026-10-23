<?php
/**
 * Seed wilayah: 8 kecamatan Kota Cilegon + 9 puskesmas dengan pemetaan kecamatan.
 *
 * Mengapa migrasi ini ada
 * -----------------------
 * Tabel `kecamatan` dan `puskesmas` (yang punya `id_kecamatan`) sudah dibuat
 * oleh migrasi kompatibilitas panel admin, tetapi kosong. `requests` hanya
 * menyimpan `assigned_puskesmas_code`, jadi tanpa pemetaan kode -> puskesmas ->
 * kecamatan, laporan "penyakit terbanyak per kecamatan" tidak bisa dihitung.
 *
 * Sumber data: definisi poligon aplikasi utama
 * (`application/helpers/puskesmas_routing_helper.php`) - sumber yang sama yang
 * dipakai merutekan warga, supaya panel admin dan rute layanan tidak berbeda.
 * Nama kecamatan diambil dari nama berkas geojson; nama puskesmas mengikuti
 * pola itu kecuali kode 2241001 yang helper itu sendiri sebut "Puskesmas
 * Citangkil II". Bila penamaan resmi Dinas berbeda, koreksinya lewat modul
 * master wilayah di panel admin - bukan dengan mengubah berkas geojson (itu
 * mengubah routing).
 *
 * Sifat: aditif (tanpa DELETE), idempoten, konflik dilaporkan bukan ditimpa.
 * DDL tabel BUKAN milik skrip ini; bila tabelnya belum ada, skrip menolak.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261023000100_wilayah_kecamatan_puskesmas_seed.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   DOCLINC_WILAYAH_SEED_APPLY=1 \
 *   DOCLINC_WILAYAH_SEED_BACKUP_REFERENCE="dump.sql.gz:sha256:..." \
 *   php admin_menu/application/migrations/20261023000100_wilayah_kecamatan_puskesmas_seed.php \
 *     --apply --environment=staging --confirm-database=<db>
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_WILAYAH_SEED_MIGRATION_ID = '20261023000100_wilayah_kecamatan_puskesmas_seed';

function wilayah_seed_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function wilayah_seed_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

$admin_root = dirname(__DIR__, 2);
$main_root = dirname(__DIR__, 3);
define('BASEPATH', $admin_root . '/system/');
define('APPPATH', $admin_root . '/application/');
define('FCPATH', $admin_root . '/');
require_once APPPATH . 'libraries/Wilayah_seed.php';

$definitions = Wilayah_seed::definitions($main_root);
if (($definitions['reason'] ?? '') !== 'ok' || empty($definitions['kecamatan']) || empty($definitions['puskesmas'])) {
	wilayah_seed_fail('definitions_unavailable', false);
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:'));

$host = trim((string) (getenv('DOCLINC_WILAYAH_SEED_DB_HOST') ?: getenv('ADMIN_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1'));
$port = (int) (getenv('DOCLINC_WILAYAH_SEED_DB_PORT') ?: getenv('ADMIN_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_WILAYAH_SEED_DB_USER') ?: getenv('ADMIN_DB_USER') ?: getenv('DB_USER') ?: ''));
$password = getenv('DOCLINC_WILAYAH_SEED_DB_PASSWORD');
if ($password === false || $password === '') {
	$password = getenv('ADMIN_DB_PASS');
}
if ($password === false || $password === '') {
	$password = getenv('DB_PASS');
}
$database = trim((string) (getenv('DOCLINC_WILAYAH_SEED_DB_NAME') ?: getenv('ADMIN_DB_NAME') ?: getenv('DB_NAME') ?: ''));

if (!array_key_exists('apply', $options)) {
	/* Mode rencana: baca saja. Bila kredensial tidak tersedia, tetap berguna -
	 * yang dicetak adalah definisi sumbernya, bukan tebakan keadaan tabel. */
	if ($database === '' || $user === '' || !is_string($password)) {
		echo 'MIGRATION_ID=' . DOCLINC_WILAYAH_SEED_MIGRATION_ID . "\n"
			. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=definitions_only\nDATABASE_WRITE_EXECUTED=false\n"
			. 'PLANNED_KECAMATAN=' . count($definitions['kecamatan']) . "\n"
			. 'PLANNED_PUSKESMAS=' . count($definitions['puskesmas']) . "\n"
			. "DESTRUCTIVE_STATEMENTS=0\n";
		exit(0);
	}
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	try {
		$db = new mysqli($host, $user, $password, $database, $port);
		$db->set_charset('utf8mb4');
	} catch (Throwable $exception) {
		wilayah_seed_fail('database_connection_failed', false);
	}
	$seed = new Wilayah_seed($db);
	$plan = $seed->plan($definitions);
	$verify = $seed->verify($definitions);
	echo 'MIGRATION_ID=' . DOCLINC_WILAYAH_SEED_MIGRATION_ID . "\n"
		. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=service_area_definitions\nDATABASE_WRITE_EXECUTED=false\n"
		. 'SCHEMA_READY=' . ($plan['schema_ready'] ? 'true' : 'false') . "\n"
		. 'PLANNED_KECAMATAN=' . count($definitions['kecamatan']) . ' PLANNED_PUSKESMAS=' . count($definitions['puskesmas']) . "\n"
		. 'KECAMATAN_PRESENT=' . $plan['kecamatan']['present'] . ' KECAMATAN_INSERT=' . count($plan['kecamatan']['insert']) . "\n"
		. 'PUSKESMAS_PRESENT=' . $plan['puskesmas']['present'] . ' PUSKESMAS_INSERT=' . count($plan['puskesmas']['insert'])
		. ' PUSKESMAS_FILL=' . count($plan['puskesmas']['fill_kecamatan'])
		. ' PUSKESMAS_CONFLICT=' . count($plan['puskesmas']['conflict']) . "\n"
		. 'CURRENT_KECAMATAN_ROWS=' . $verify['kecamatan_rows'] . ' CURRENT_PUSKESMAS_ROWS=' . $verify['puskesmas_rows'] . "\n"
		. "DESTRUCTIVE_STATEMENTS=0\n";
	exit(0);
}

/* ---------------------------------------------------------------------------
 * Mode terapkan. Empat pagar harus terbuka sekaligus: saklar env, --apply,
 * nama database yang dikonfirmasi, dan referensi backup. Tanpa itu skrip
 * berhenti sebelum menyentuh data.
 * ------------------------------------------------------------------------- */
if (!wilayah_seed_true(getenv('DOCLINC_WILAYAH_SEED_APPLY'))) {
	wilayah_seed_fail('seed_apply_disabled', false);
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('test', 'staging', 'uat', 'production'), true)) {
	wilayah_seed_fail('environment_not_allowed', false);
}
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	wilayah_seed_fail('database_confirmation_mismatch', false);
}
$backup_reference = trim((string) (getenv('DOCLINC_WILAYAH_SEED_BACKUP_REFERENCE') ?: ''));
if ($backup_reference === '') {
	wilayah_seed_fail('backup_reference_missing', false);
}
if ($user === '' || !is_string($password)) {
	wilayah_seed_fail('database_identity_missing', false);
}
if ($password === '' && !in_array(strtolower($host), array('127.0.0.1', 'localhost', '::1'), true)) {
	wilayah_seed_fail('database_password_required_for_remote_host', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	wilayah_seed_fail('database_connection_failed', false);
}

$seed = new Wilayah_seed($db);
if (!$seed->schemaReady()) {
	/* Tabel dibuat migrasi lain; skrip ini tidak boleh membuatnya sendiri. */
	wilayah_seed_fail('wilayah_tables_missing');
}

$lock = $db->query("SELECT GET_LOCK('doclinc_wilayah_seed', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	wilayah_seed_fail('seed_lock_unavailable');
}

$plan = $seed->plan($definitions);
$db->begin_transaction();
try {
	$result = $seed->apply($definitions, $plan);
	$db->commit();
} catch (Throwable $exception) {
	$db->rollback();
	wilayah_seed_fail('seed_apply_failed');
}
$db->query("SELECT RELEASE_LOCK('doclinc_wilayah_seed')");
$verify = $seed->verify($definitions);

echo 'MIGRATION_ID=' . DOCLINC_WILAYAH_SEED_MIGRATION_ID . "\n"
	. "EXECUTION_MODE=APPLY\nDATABASE_WRITE_EXECUTED=true\n"
	. 'BACKUP_REFERENCE=' . $backup_reference . "\n"
	. 'KECAMATAN_INSERTED=' . $result['kecamatan_inserted'] . ' PUSKESMAS_INSERTED=' . $result['puskesmas_inserted']
	. ' PUSKESMAS_FILLED=' . $result['puskesmas_filled']
	. ' CONFLICTS_LEFT_UNTOUCHED=' . $result['conflicts_left_untouched'] . "\n"
	. 'VERIFY_KECAMATAN_ROWS=' . $verify['kecamatan_rows'] . ' VERIFY_PUSKESMAS_ROWS=' . $verify['puskesmas_rows']
	. ' VERIFY_WITHOUT_KECAMATAN=' . $verify['puskesmas_without_kecamatan']
	. ' VERIFY_ORPHAN=' . $verify['puskesmas_orphan'] . "\n"
	. "DESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=PASS\n";
exit(0);
