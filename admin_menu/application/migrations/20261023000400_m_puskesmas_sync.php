<?php
/**
 * Sinkronkan master legacy `m_puskesmas` dengan master wilayah DocLinc.
 *
 * Mengapa perlu
 * -------------
 * `m_puskesmas` adalah master lama yang dibaca oleh rute layanan warga
 * (`puskesmas_routing_helper`), otorisasi permintaan (`request_authz_helper`),
 * penempatan nakes, dan modul Data Puskesmas di panel. Isinya baru tiga baris:
 * satu kode nyata (10280101 Puskesmas Cilegon) dan dua baris uji coba
 * (PKM01/PKM02 "Puskesmas A/B Local"). Sementara master wilayah DocLinc
 * (`puskesmas`) sudah berisi 9 kode nyata untuk 8 kecamatan.
 *
 * Sifat migrasi ini: ADITIF dan TIDAK MENGHAPUS apa pun.
 *   - kode yang belum ada di `m_puskesmas` ditambahkan dari `puskesmas`
 *     (nama diambil apa adanya; alamat dan titik lokasi dibiarkan kosong supaya
 *     dilengkapi pengelola - panel Data Puskesmas sudah menandainya belum siap);
 *   - baris uji coba lama (PKM01, PKM02) hanya dinonaktifkan, tidak dihapus,
 *     supaya jejaknya tetap ada dan tidak ada riwayat yang hilang.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261023000400_m_puskesmas_sync.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   DOCLINC_MPKM_SYNC_APPLY=1 \
 *   DOCLINC_MPKM_SYNC_BACKUP_REFERENCE="dump.sql.gz:sha256:..." \
 *   php admin_menu/application/migrations/20261023000400_m_puskesmas_sync.php \
 *     --apply --environment=staging --confirm-database=<db>
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_MPKM_SYNC_MIGRATION_ID = '20261023000400_m_puskesmas_sync';

/** Kode lama yang isinya uji coba dan hanya boleh dinonaktifkan, bukan dihapus. */
const DOCLINC_MPKM_LEGACY_TEST_CODES = array('PKM01', 'PKM02');

function mpkm_sync_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function mpkm_sync_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

/** Kode nyata yang harus ada di master legacy, diambil dari master wilayah. */
function mpkm_sync_source_codes(mysqli $db)
{
	$rows = $db->query('SELECT kode_pkm, nama_puskesmas FROM puskesmas WHERE kode_pkm IS NOT NULL AND TRIM(kode_pkm) <> "" ORDER BY kode_pkm');
	$codes = array();
	while ($row = $rows->fetch_assoc()) {
		$codes[trim((string) $row['kode_pkm'])] = trim((string) $row['nama_puskesmas']);
	}
	return $codes;
}

/** Keadaan `m_puskesmas` saat ini: kode -> status. */
function mpkm_sync_current(mysqli $db)
{
	$rows = $db->query('SELECT kode_pkm, nama_puskesmas, status FROM m_puskesmas');
	$current = array();
	while ($row = $rows->fetch_assoc()) {
		$current[trim((string) $row['kode_pkm'])] = array(
			'nama' => trim((string) $row['nama_puskesmas']),
			'status' => (string) $row['status'],
		);
	}
	return $current;
}

/** Rencana: apa yang akan ditambah dan apa yang akan dinonaktifkan. */
function mpkm_sync_plan(mysqli $db)
{
	$source = mpkm_sync_source_codes($db);
	$current = mpkm_sync_current($db);
	$insert = array();
	foreach ($source as $kode => $nama) {
		if (!isset($current[$kode])) {
			$insert[$kode] = $nama;
		}
	}
	$deactivate = array();
	foreach (DOCLINC_MPKM_LEGACY_TEST_CODES as $kode) {
		if (isset($current[$kode]) && $current[$kode]['status'] === 'aktif') {
			$deactivate[$kode] = $current[$kode]['nama'];
		}
	}
	return array(
		'source' => $source,
		'current' => $current,
		'insert' => $insert,
		'deactivate' => $deactivate,
	);
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:'));

$host = trim((string) (getenv('DOCLINC_MPKM_SYNC_DB_HOST') ?: getenv('ADMIN_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1'));
$port = (int) (getenv('DOCLINC_MPKM_SYNC_DB_PORT') ?: getenv('ADMIN_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_MPKM_SYNC_DB_USER') ?: getenv('ADMIN_DB_USER') ?: getenv('DB_USER') ?: ''));
$password = getenv('DOCLINC_MPKM_SYNC_DB_PASSWORD');
if ($password === false || $password === '') {
	$password = getenv('ADMIN_DB_PASS');
}
if ($password === false || $password === '') {
	$password = getenv('DB_PASS');
}
$database = trim((string) (getenv('DOCLINC_MPKM_SYNC_DB_NAME') ?: getenv('ADMIN_DB_NAME') ?: getenv('DB_NAME') ?: ''));

if (!array_key_exists('apply', $options)) {
	if ($database === '' || $user === '' || !is_string($password)) {
		echo 'MIGRATION_ID=' . DOCLINC_MPKM_SYNC_MIGRATION_ID . "\n"
			. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=definitions_only\nDATABASE_WRITE_EXECUTED=false\n"
			. 'LEGACY_TEST_CODES=' . implode(',', DOCLINC_MPKM_LEGACY_TEST_CODES) . "\n"
			. "DESTRUCTIVE_STATEMENTS=0\n";
		exit(0);
	}
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	try {
		$db = new mysqli($host, $user, $password, $database, $port);
		$db->set_charset('utf8mb4');
	} catch (Throwable $exception) {
		mpkm_sync_fail('database_connection_failed', false);
	}
	$plan = mpkm_sync_plan($db);
	echo 'MIGRATION_ID=' . DOCLINC_MPKM_SYNC_MIGRATION_ID . "\n"
		. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=puskesmas\nDATABASE_WRITE_EXECUTED=false\n"
		. 'SOURCE_CODES=' . count($plan['source']) . ' CURRENT_CODES=' . count($plan['current']) . "\n"
		. 'PLANNED_INSERT=' . count($plan['insert']) . ' PLANNED_DEACTIVATE=' . count($plan['deactivate']) . "\n"
		. 'PLANNED_INSERT_CODES=' . (empty($plan['insert']) ? 'none' : implode(',', array_keys($plan['insert']))) . "\n"
		. 'PLANNED_DEACTIVATE_CODES=' . (empty($plan['deactivate']) ? 'none' : implode(',', array_keys($plan['deactivate']))) . "\n"
		. "DESTRUCTIVE_STATEMENTS=0\n";
	exit(0);
}

/* ---------------------------------------------------------------------------
 * Mode terapkan: empat pagar - saklar env, --apply, konfirmasi nama database,
 * dan referensi backup.
 * ------------------------------------------------------------------------ */
if (!mpkm_sync_true(getenv('DOCLINC_MPKM_SYNC_APPLY'))) {
	mpkm_sync_fail('mpkm_sync_apply_disabled', false);
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('test', 'staging', 'uat', 'production'), true)) {
	mpkm_sync_fail('environment_not_allowed', false);
}
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	mpkm_sync_fail('database_confirmation_mismatch', false);
}
$backup_reference = trim((string) (getenv('DOCLINC_MPKM_SYNC_BACKUP_REFERENCE') ?: ''));
if ($backup_reference === '') {
	mpkm_sync_fail('backup_reference_missing', false);
}
if ($user === '' || !is_string($password)) {
	mpkm_sync_fail('database_identity_missing', false);
}
if ($password === '' && !in_array(strtolower($host), array('127.0.0.1', 'localhost', '::1'), true)) {
	mpkm_sync_fail('database_password_required_for_remote_host', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	mpkm_sync_fail('database_connection_failed', false);
}

$lock = $db->query("SELECT GET_LOCK('doclinc_mpkm_sync', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	mpkm_sync_fail('mpkm_sync_lock_unavailable');
}

$plan = mpkm_sync_plan($db);
$inserted = 0;
$deactivated = 0;
$db->begin_transaction();
try {
	$insert = $db->prepare('INSERT INTO m_puskesmas (kode_pkm, nama_puskesmas, status) VALUES (?, ?, "aktif")');
	foreach ($plan['insert'] as $kode => $nama) {
		$insert->bind_param('ss', $kode, $nama);
		$insert->execute();
		$inserted++;
	}
	$insert->close();

	/* Baris uji coba lama hanya dinonaktifkan - tidak dihapus. */
	$deactivate = $db->prepare('UPDATE m_puskesmas SET status = "nonaktif" WHERE kode_pkm = ?');
	foreach (array_keys($plan['deactivate']) as $kode) {
		$deactivate->bind_param('s', $kode);
		$deactivate->execute();
		$deactivated++;
	}
	$deactivate->close();
	$db->commit();
} catch (Throwable $exception) {
	$db->rollback();
	mpkm_sync_fail('mpkm_sync_write_failed');
}
$db->query("SELECT RELEASE_LOCK('doclinc_mpkm_sync')");

$after = mpkm_sync_current($db);
$missing = array();
foreach (array_keys(mpkm_sync_source_codes($db)) as $kode) {
	if (!isset($after[$kode])) {
		$missing[] = $kode;
	}
}
$active = 0;
$legacy_active = 0;
foreach ($after as $kode => $row) {
	if ($row['status'] === 'aktif') {
		$active++;
		if (in_array($kode, DOCLINC_MPKM_LEGACY_TEST_CODES, true)) {
			$legacy_active++;
		}
	}
}

echo 'MIGRATION_ID=' . DOCLINC_MPKM_SYNC_MIGRATION_ID . "\n"
	. "EXECUTION_MODE=APPLY\nDATABASE_WRITE_EXECUTED=true\n"
	. 'BACKUP_REFERENCE=' . $backup_reference . "\n"
	. 'INSERTED=' . $inserted . ' DEACTIVATED=' . $deactivated . "\n"
	. 'VERIFY_TOTAL=' . count($after) . ' VERIFY_AKTIF=' . $active . ' VERIFY_LEGACY_AKTIF=' . $legacy_active . "\n"
	. 'VERIFY_SOURCE_MISSING=' . count($missing) . (empty($missing) ? '' : ' MISSING=' . implode(',', $missing)) . "\n"
	. "DESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=" . (empty($missing) && $legacy_active === 0 ? 'PASS' : 'FAIL') . "\n";
exit(empty($missing) && $legacy_active === 0 ? 0 : 1);
