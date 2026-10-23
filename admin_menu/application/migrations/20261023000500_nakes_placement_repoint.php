<?php
/**
 * Pindahkan penempatan akun nakes yang masih menunjuk Puskesmas nonaktif.
 *
 * Mengapa perlu
 * -------------
 * Penempatan nakes disimpan di `users.remark` (kode `m_puskesmas`). Modul
 * "Akun Puskesmas" sudah menolak kode yang tidak aktif saat menyimpan
 * (`Kelola_dokter_nakes_m::puskesmas_exists()` menyaring `status='aktif'`),
 * tetapi data lama belum ikut diperbaiki. Setelah migrasi
 * `20261023000400_m_puskesmas_sync` menonaktifkan dua baris uji coba
 * (PKM01, PKM02), empat akun role `dokter` jadi menunjuk Puskesmas nonaktif:
 *
 *   userId 9002 PUSKESMAS_A_LOCAL  remark=PKM01
 *   userId 9003 PUSKESMAS_B_LOCAL  remark=PKM02
 *   userId 9004 NAKES_DOCTOR_LOCAL remark=PKM01
 *   userId 9005 NAKES_VISIT_LOCAL  remark=PKM01
 *
 * Akun itu dipakai untuk uji internal, bukan data pasien, jadi dipindahkan ke
 * Puskesmas Cilegon (10280101) yang aktif. Kode lama hanya dipetakan, tidak
 * diubah di `m_puskesmas`.
 *
 * Sifat migrasi ini: TERBATAS dan TIDAK MENGHAPUS apa pun.
 *   - hanya kolom `remark` pada baris `users` role `dokter` yang diubah;
 *   - setiap perubahan meninggalkan satu baris `audit_logs` (entity_type
 *     `users`, action `admin_repoint_puskesmas_nakes_user`);
 *   - akun dengan `remark` tidak aktif yang TIDAK ada di peta legacy tidak
 *     diubah, hanya dilaporkan, dan membuat hasil FAIL supaya diputuskan
 *     manusia.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261023000500_nakes_placement_repoint.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   DOCLINC_NAKES_REPOINT_APPLY=1 \
 *   DOCLINC_NAKES_REPOINT_BACKUP_REFERENCE="dump.sql.gz:sha256:..." \
 *   php admin_menu/application/migrations/20261023000500_nakes_placement_repoint.php \
 *     --apply --environment=staging --confirm-database=<db>
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_NAKES_REPOINT_MIGRATION_ID = '20261023000500_nakes_placement_repoint';

/** Peta kode uji coba lama -> kode nyata yang aktif. */
const DOCLINC_NAKES_REPOINT_LEGACY_MAP = array(
	'PKM01' => '10280101',
	'PKM02' => '10280101',
);

function nakes_repoint_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function nakes_repoint_fail($reason, $connected = true)
{
	fwrite(
		STDERR,
		"MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\n"
		. 'DATABASE_CONNECTION_OPENED=' . ($connected ? 'true' : 'false') . "\n"
		. "DATA_WRITES_EXECUTED=false\nDESTRUCTIVE_STATEMENTS=0\n"
	);
	exit(1);
}

/** Kode Puskesmas yang aktif saat ini: kode => nama. */
function nakes_repoint_active_codes(mysqli $db)
{
	$active = array();
	$rows = $db->query('SELECT kode_pkm, nama_puskesmas FROM m_puskesmas WHERE status = "aktif" AND kode_pkm <> "DEFAULT"');
	while ($row = $rows->fetch_assoc()) {
		$active[trim((string) $row['kode_pkm'])] = trim((string) $row['nama_puskesmas']);
	}
	return $active;
}

/**
 * Rencana: akun nakes mana yang penempatannya tidak sah, mana yang dipetakan
 * ulang, dan mana yang harus diputuskan manusia.
 */
function nakes_repoint_plan(mysqli $db)
{
	$active = nakes_repoint_active_codes($db);
	$rows = $db->query('SELECT userId, username, status, remark FROM users WHERE role = "dokter" ORDER BY userId');
	$repoint = array();
	$unresolved = array();
	$ok = 0;
	while ($row = $rows->fetch_assoc()) {
		$remark = trim((string) $row['remark']);
		if ($remark !== '' && isset($active[$remark])) {
			$ok++;
			continue;
		}
		$entry = array(
			'userId' => (int) $row['userId'],
			'username' => (string) $row['username'],
			'from' => $remark,
		);
		if (isset(DOCLINC_NAKES_REPOINT_LEGACY_MAP[$remark])) {
			$entry['to'] = DOCLINC_NAKES_REPOINT_LEGACY_MAP[$remark];
			$repoint[] = $entry;
			continue;
		}
		$entry['to'] = null;
		$unresolved[] = $entry;
	}
	return array(
		'active' => $active,
		'repoint' => $repoint,
		'unresolved' => $unresolved,
		'ok' => $ok,
	);
}

$options = getopt('', array('apply', 'environment:', 'confirm-database:'));

$host = trim((string) (getenv('DOCLINC_NAKES_REPOINT_DB_HOST') ?: getenv('ADMIN_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1'));
$port = (int) (getenv('DOCLINC_NAKES_REPOINT_DB_PORT') ?: getenv('ADMIN_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_NAKES_REPOINT_DB_USER') ?: getenv('ADMIN_DB_USER') ?: getenv('DB_USER') ?: ''));
$password = getenv('DOCLINC_NAKES_REPOINT_DB_PASSWORD');
if ($password === false || $password === '') {
	$password = getenv('ADMIN_DB_PASS');
}
if ($password === false || $password === '') {
	$password = getenv('DB_PASS');
}
$database = trim((string) (getenv('DOCLINC_NAKES_REPOINT_DB_NAME') ?: getenv('ADMIN_DB_NAME') ?: getenv('DB_NAME') ?: ''));

if (!array_key_exists('apply', $options)) {
	if ($database === '' || $user === '' || !is_string($password)) {
		echo 'MIGRATION_ID=' . DOCLINC_NAKES_REPOINT_MIGRATION_ID . "\n"
			. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=definitions_only\nDATABASE_WRITE_EXECUTED=false\n"
			. 'LEGACY_MAP=' . json_encode(DOCLINC_NAKES_REPOINT_LEGACY_MAP) . "\n"
			. "DESTRUCTIVE_STATEMENTS=0\n";
		exit(0);
	}
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	try {
		$db = new mysqli($host, $user, $password, $database, $port);
		$db->set_charset('utf8mb4');
	} catch (Throwable $exception) {
		nakes_repoint_fail('database_connection_failed', false);
	}
	$plan = nakes_repoint_plan($db);
	$codes = array();
	foreach ($plan['repoint'] as $entry) {
		$codes[] = $entry['userId'] . ':' . ($entry['from'] === '' ? '(kosong)' : $entry['from']) . '->' . $entry['to'];
	}
	$unresolved = array();
	foreach ($plan['unresolved'] as $entry) {
		$unresolved[] = $entry['userId'] . ':' . ($entry['from'] === '' ? '(kosong)' : $entry['from']);
	}
	echo 'MIGRATION_ID=' . DOCLINC_NAKES_REPOINT_MIGRATION_ID . "\n"
		. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=m_puskesmas+users\nDATABASE_WRITE_EXECUTED=false\n"
		. 'ACTIVE_CODES=' . count($plan['active'])
		. ' NAKES_TOTAL=' . ($plan['ok'] + count($plan['repoint']) + count($plan['unresolved'])) . "\n"
		. 'ALREADY_VALID=' . $plan['ok'] . ' PLANNED_REPOINT=' . count($plan['repoint'])
		. ' PLANNED_UNRESOLVED=' . count($plan['unresolved']) . "\n"
		. 'PLANNED_REPOINT_DETAIL=' . (empty($codes) ? 'none' : implode(',', $codes)) . "\n"
		. 'PLANNED_UNRESOLVED_DETAIL=' . (empty($unresolved) ? 'none' : implode(',', $unresolved)) . "\n"
		. "DESTRUCTIVE_STATEMENTS=0\n";
	exit(0);
}

/* ---------------------------------------------------------------------------
 * Mode terapkan: empat pagar - saklar env, --apply, konfirmasi nama database,
 * dan referensi backup.
 * ------------------------------------------------------------------------ */
if (!nakes_repoint_true(getenv('DOCLINC_NAKES_REPOINT_APPLY'))) {
	nakes_repoint_fail('nakes_repoint_apply_disabled', false);
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('test', 'staging', 'uat', 'production'), true)) {
	nakes_repoint_fail('environment_not_allowed', false);
}
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	nakes_repoint_fail('database_confirmation_mismatch', false);
}
$backup_reference = trim((string) (getenv('DOCLINC_NAKES_REPOINT_BACKUP_REFERENCE') ?: ''));
if ($backup_reference === '') {
	nakes_repoint_fail('backup_reference_missing', false);
}
if ($user === '' || !is_string($password)) {
	nakes_repoint_fail('database_identity_missing', false);
}
if ($password === '' && !in_array(strtolower($host), array('127.0.0.1', 'localhost', '::1'), true)) {
	nakes_repoint_fail('database_password_required_for_remote_host', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	nakes_repoint_fail('database_connection_failed', false);
}

$lock = $db->query("SELECT GET_LOCK('doclinc_nakes_placement_repoint', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	nakes_repoint_fail('nakes_repoint_lock_unavailable');
}

$plan = nakes_repoint_plan($db);
foreach (array_unique(array_values(DOCLINC_NAKES_REPOINT_LEGACY_MAP)) as $target) {
	if (!isset($plan['active'][$target])) {
		$db->query("SELECT RELEASE_LOCK('doclinc_nakes_placement_repoint')");
		nakes_repoint_fail('repoint_target_not_active');
	}
}

$actor = 'migration:' . DOCLINC_NAKES_REPOINT_MIGRATION_ID;
$repointed = 0;
$audit_written = 0;
$db->begin_transaction();
try {
	$update = $db->prepare('UPDATE users SET remark = ?, updated_by = ?, updated_at = NOW() WHERE userId = ? AND role = "dokter"');
	$audit = $db->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata_json, created_at) VALUES (NULL, ?, "users", ?, ?, NOW())');
	foreach ($plan['repoint'] as $entry) {
		$update->bind_param('ssi', $entry['to'], $actor, $entry['userId']);
		$update->execute();
		if ($update->affected_rows !== 1) {
			throw new RuntimeException('repoint_not_applied');
		}
		$repointed++;
		$metadata = json_encode(array(
			'target_user_id' => $entry['userId'],
			'role' => 'dokter',
			'field' => 'remark',
			'from' => $entry['from'],
			'to' => $entry['to'],
			'reason' => 'puskesmas_legacy_nonaktif',
			'actor' => $actor,
		));
		$action = 'admin_repoint_puskesmas_nakes_user';
		$entity_id = (string) $entry['userId'];
		$audit->bind_param('sss', $action, $entity_id, $metadata);
		$audit->execute();
		$audit_written++;
	}
	$update->close();
	$audit->close();
	$db->commit();
} catch (Throwable $exception) {
	$db->rollback();
	$db->query("SELECT RELEASE_LOCK('doclinc_nakes_placement_repoint')");
	nakes_repoint_fail('nakes_repoint_write_failed');
}
$db->query("SELECT RELEASE_LOCK('doclinc_nakes_placement_repoint')");

$after = nakes_repoint_plan($db);
$left = array();
foreach ($after['unresolved'] as $entry) {
	$left[] = $entry['userId'] . ':' . ($entry['from'] === '' ? '(kosong)' : $entry['from']);
}

echo 'MIGRATION_ID=' . DOCLINC_NAKES_REPOINT_MIGRATION_ID . "\n"
	. "EXECUTION_MODE=APPLY\nDATABASE_WRITE_EXECUTED=true\n"
	. 'BACKUP_REFERENCE=' . $backup_reference . "\n"
	. 'REPOINTED=' . $repointed . ' AUDIT_ROWS_WRITTEN=' . $audit_written . "\n"
	. 'VERIFY_NAKES_TOTAL=' . ($after['ok'] + count($after['repoint']) + count($after['unresolved']))
	. ' VERIFY_PLACEMENT_VALID=' . $after['ok'] . ' VERIFY_UNRESOLVED=' . count($after['unresolved']) . "\n"
	. 'VERIFY_UNRESOLVED_DETAIL=' . (empty($left) ? 'none' : implode(',', $left)) . "\n"
	. "DESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=" . (empty($after['unresolved']) ? 'PASS' : 'FAIL') . "\n";
exit(empty($after['unresolved']) ? 0 : 1);
