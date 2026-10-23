<?php
/**
 * Pindahkan penempatan STAF Puskesmas yang masih menunjuk Puskesmas nonaktif.
 *
 * Mengapa perlu
 * -------------
 * Penempatan staf disimpan di `puskesmas_staff.kode_pkm`, terpisah dari
 * `users.remark` yang dipakai akun login. Migrasi
 * `20261023000500_nakes_placement_repoint` hanya memperbaiki `users.remark`,
 * sehingga dua baris staf tetap menunjuk kode uji coba yang sudah dinonaktifkan:
 *
 *   staff_id 9004 NAKES_DOCTOR_LOCAL kode_pkm=PKM01
 *   staff_id 9005 NAKES_VISIT_LOCAL  kode_pkm=PKM01
 *
 * Akibatnya nyata: `Kelola_staff_puskesmas_m::staff_is_operationally_complete()`
 * menuntut Puskesmas aktif, jadi kedua staf itu tidak bisa diaktifkan dan
 * pemeriksaan kesiapan unit selalu menandainya. Penjaga di jalur tulis sudah ada
 * (`puskesmas_is_active()` di `validated_payload()`), jadi ini murni sisa data
 * lama.
 *
 * Sifat migrasi ini: TERBATAS dan TIDAK MENGHAPUS apa pun.
 *   - hanya kolom `puskesmas_staff.kode_pkm` yang diubah;
 *   - setiap perubahan meninggalkan satu baris `audit_logs` (entity_type
 *     `puskesmas_staff`, action `admin_repoint_puskesmas_staff`);
 *   - baris dengan kode tidak aktif yang TIDAK ada di peta legacy tidak diubah,
 *     hanya dilaporkan, dan membuat hasil FAIL supaya diputuskan manusia.
 *
 * Pemakaian
 * ---------
 *   php admin_menu/application/migrations/20261024000200_puskesmas_staff_placement_repoint.php
 *     -> mencetak rencana, tidak menulis apa pun
 *
 *   DOCLINC_STAFF_REPOINT_APPLY=1 \
 *   DOCLINC_STAFF_REPOINT_BACKUP_REFERENCE="dump.sql.gz:sha256:..." \
 *   php admin_menu/application/migrations/20261024000200_puskesmas_staff_placement_repoint.php \
 *     --apply --environment=staging --confirm-database=<db>
 */
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

const DOCLINC_STAFF_REPOINT_MIGRATION_ID = '20261024000200_puskesmas_staff_placement_repoint';

/** Peta kode uji coba lama -> kode nyata yang aktif. */
const DOCLINC_STAFF_REPOINT_LEGACY_MAP = array(
	'PKM01' => '10280101',
	'PKM02' => '10280101',
);

function staff_repoint_true($value)
{
	return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function staff_repoint_fail($reason, $connected = true)
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
function staff_repoint_active_codes(mysqli $db)
{
	$active = array();
	$rows = $db->query('SELECT kode_pkm, nama_puskesmas FROM m_puskesmas WHERE status = "aktif" AND kode_pkm <> "DEFAULT"');
	while ($row = $rows->fetch_assoc()) {
		$active[trim((string) $row['kode_pkm'])] = trim((string) $row['nama_puskesmas']);
	}
	return $active;
}

/**
 * Rencana: baris staf mana yang penempatannya tidak sah, mana yang dipetakan
 * ulang, dan mana yang harus diputuskan manusia.
 */
function staff_repoint_plan(mysqli $db)
{
	$active = staff_repoint_active_codes($db);
	$rows = $db->query('SELECT staff_id, kode_pkm, status FROM puskesmas_staff ORDER BY staff_id');
	$repoint = array();
	$unresolved = array();
	$ok = 0;
	while ($row = $rows->fetch_assoc()) {
		$kode = trim((string) $row['kode_pkm']);
		if ($kode !== '' && isset($active[$kode])) {
			$ok++;
			continue;
		}
		$entry = array(
			'staff_id' => (int) $row['staff_id'],
			'from' => $kode,
		);
		if (isset(DOCLINC_STAFF_REPOINT_LEGACY_MAP[$kode])) {
			$entry['to'] = DOCLINC_STAFF_REPOINT_LEGACY_MAP[$kode];
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

$host = trim((string) (getenv('DOCLINC_STAFF_REPOINT_DB_HOST') ?: getenv('ADMIN_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1'));
$port = (int) (getenv('DOCLINC_STAFF_REPOINT_DB_PORT') ?: getenv('ADMIN_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
$user = trim((string) (getenv('DOCLINC_STAFF_REPOINT_DB_USER') ?: getenv('ADMIN_DB_USER') ?: getenv('DB_USER') ?: ''));
$password = getenv('DOCLINC_STAFF_REPOINT_DB_PASSWORD');
if ($password === false || $password === '') {
	$password = getenv('ADMIN_DB_PASS');
}
if ($password === false || $password === '') {
	$password = getenv('DB_PASS');
}
$database = trim((string) (getenv('DOCLINC_STAFF_REPOINT_DB_NAME') ?: getenv('ADMIN_DB_NAME') ?: getenv('DB_NAME') ?: ''));

if (!array_key_exists('apply', $options)) {
	if ($database === '' || $user === '' || !is_string($password)) {
		echo 'MIGRATION_ID=' . DOCLINC_STAFF_REPOINT_MIGRATION_ID . "\n"
			. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=definitions_only\nDATABASE_WRITE_EXECUTED=false\n"
			. 'LEGACY_MAP=' . json_encode(DOCLINC_STAFF_REPOINT_LEGACY_MAP) . "\n"
			. "DESTRUCTIVE_STATEMENTS=0\n";
		exit(0);
	}
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	try {
		$db = new mysqli($host, $user, $password, $database, $port);
		$db->set_charset('utf8mb4');
	} catch (Throwable $exception) {
		staff_repoint_fail('database_connection_failed', false);
	}
	$plan = staff_repoint_plan($db);
	$codes = array();
	foreach ($plan['repoint'] as $entry) {
		$codes[] = $entry['staff_id'] . ':' . ($entry['from'] === '' ? '(kosong)' : $entry['from']) . '->' . $entry['to'];
	}
	$unresolved = array();
	foreach ($plan['unresolved'] as $entry) {
		$unresolved[] = $entry['staff_id'] . ':' . ($entry['from'] === '' ? '(kosong)' : $entry['from']);
	}
	echo 'MIGRATION_ID=' . DOCLINC_STAFF_REPOINT_MIGRATION_ID . "\n"
		. "EXECUTION_MODE=PLAN\nPLAN_SOURCE=m_puskesmas+puskesmas_staff\nDATABASE_WRITE_EXECUTED=false\n"
		. 'ACTIVE_CODES=' . count($plan['active'])
		. ' STAFF_TOTAL=' . ($plan['ok'] + count($plan['repoint']) + count($plan['unresolved'])) . "\n"
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
if (!staff_repoint_true(getenv('DOCLINC_STAFF_REPOINT_APPLY'))) {
	staff_repoint_fail('staff_repoint_apply_disabled', false);
}
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
if (!in_array($environment, array('test', 'staging', 'uat', 'production'), true)) {
	staff_repoint_fail('environment_not_allowed', false);
}
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string) $options['confirm-database'])) {
	staff_repoint_fail('database_confirmation_mismatch', false);
}
$backup_reference = trim((string) (getenv('DOCLINC_STAFF_REPOINT_BACKUP_REFERENCE') ?: ''));
if ($backup_reference === '') {
	staff_repoint_fail('backup_reference_missing', false);
}
if ($user === '' || !is_string($password)) {
	staff_repoint_fail('database_identity_missing', false);
}
if ($password === '' && !in_array(strtolower($host), array('127.0.0.1', 'localhost', '::1'), true)) {
	staff_repoint_fail('database_password_required_for_remote_host', false);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
} catch (Throwable $exception) {
	staff_repoint_fail('database_connection_failed', false);
}

$lock = $db->query("SELECT GET_LOCK('doclinc_staff_placement_repoint', 10) AS acquired")->fetch_assoc();
if (empty($lock['acquired'])) {
	staff_repoint_fail('staff_repoint_lock_unavailable');
}

$plan = staff_repoint_plan($db);
foreach (array_unique(array_values(DOCLINC_STAFF_REPOINT_LEGACY_MAP)) as $target) {
	if (!isset($plan['active'][$target])) {
		$db->query("SELECT RELEASE_LOCK('doclinc_staff_placement_repoint')");
		staff_repoint_fail('repoint_target_not_active');
	}
}

$actor = 'migration:' . DOCLINC_STAFF_REPOINT_MIGRATION_ID;
$repointed = 0;
$audit_written = 0;
$db->begin_transaction();
try {
	$update = $db->prepare('UPDATE puskesmas_staff SET kode_pkm = ? WHERE staff_id = ?');
	$audit = $db->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata_json, created_at) VALUES (NULL, ?, "puskesmas_staff", ?, ?, NOW())');
	foreach ($plan['repoint'] as $entry) {
		$update->bind_param('si', $entry['to'], $entry['staff_id']);
		$update->execute();
		if ($update->affected_rows !== 1) {
			throw new RuntimeException('repoint_not_applied');
		}
		$repointed++;
		$metadata = json_encode(array(
			'target_staff_id' => $entry['staff_id'],
			'field' => 'kode_pkm',
			'from' => $entry['from'],
			'to' => $entry['to'],
			'reason' => 'puskesmas_legacy_nonaktif',
			'actor' => $actor,
		));
		$action = 'admin_repoint_puskesmas_staff';
		$entity_id = (string) $entry['staff_id'];
		$audit->bind_param('sss', $action, $entity_id, $metadata);
		$audit->execute();
		$audit_written++;
	}
	$update->close();
	$audit->close();
	$db->commit();
} catch (Throwable $exception) {
	$db->rollback();
	$db->query("SELECT RELEASE_LOCK('doclinc_staff_placement_repoint')");
	staff_repoint_fail('staff_repoint_write_failed');
}
$db->query("SELECT RELEASE_LOCK('doclinc_staff_placement_repoint')");

$after = staff_repoint_plan($db);
$left = array();
foreach ($after['unresolved'] as $entry) {
	$left[] = $entry['staff_id'] . ':' . ($entry['from'] === '' ? '(kosong)' : $entry['from']);
}

echo 'MIGRATION_ID=' . DOCLINC_STAFF_REPOINT_MIGRATION_ID . "\n"
	. "EXECUTION_MODE=APPLY\nDATABASE_WRITE_EXECUTED=true\n"
	. 'BACKUP_REFERENCE=' . $backup_reference . "\n"
	. 'REPOINTED=' . $repointed . ' AUDIT_ROWS_WRITTEN=' . $audit_written . "\n"
	. 'VERIFY_STAFF_TOTAL=' . ($after['ok'] + count($after['repoint']) + count($after['unresolved']))
	. ' VERIFY_PLACEMENT_VALID=' . $after['ok'] . ' VERIFY_UNRESOLVED=' . count($after['unresolved']) . "\n"
	. 'VERIFY_UNRESOLVED_DETAIL=' . (empty($left) ? 'none' : implode(',', $left)) . "\n"
	. "DESTRUCTIVE_STATEMENTS=0\nMIGRATION_RESULT=" . (empty($after['unresolved']) ? 'PASS' : 'FAIL') . "\n";
exit(empty($after['unresolved']) ? 0 : 1);
