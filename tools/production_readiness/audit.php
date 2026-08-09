<?php
if (PHP_SAPI !== 'cli') { exit('No direct script access allowed'); }
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 2) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 2) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 2) . '/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }

ini_set('display_errors', '0');
ini_set('log_errors', '0');

require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
require_once APPPATH . 'libraries/Profile_image_storage.php';
require_once APPPATH . 'libraries/Role_prerequisite_service.php';
require_once APPPATH . 'libraries/Role_identity_policy.php';
require_once APPPATH . 'libraries/Nakes_credential_policy.php';
require_once APPPATH . 'libraries/Nakes_profile_readiness_policy.php';
require_once __DIR__ . '/ProductionReadinessReport.php';
require_once __DIR__ . '/ReadinessIdentityResolver.php';

$db = null;
$transaction_started = false;
$stage = 'bootstrap';
$exit_code = 1;

function readiness_fail($stage, $code)
{
	fwrite(STDERR, "PRODUCTION_READINESS_AUDIT=FAIL\nSAFE_ERROR_STAGE={$stage}\nSAFE_ERROR_CODE={$code}\n");
}

function readiness_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function readiness_schema_gaps($db, $database)
{
	$requirements = array(
		'users' => array('userId', 'nama', 'email', 'role', 'status', 'must_change_password', 'password_changed_at', 'no_hp', 'alamat', 'tgl', 'gender', 'foto', 'remark', 'nik', 'nomor_kk', 'nomor_bpjs_kis'),
		'puskesmas_staff' => array('staff_id', 'kode_pkm', 'nama', 'gelar', 'no_hp', 'profesi', 'nomor_sip', 'sip_expired_at', 'nip', 'user_id', 'status'),
		'm_puskesmas' => array('kode_pkm', 'nama_puskesmas', 'alamat', 'latitude', 'longitude', 'status'),
	);
	$gaps = array();
	foreach ($requirements as $table => $fields) {
		if (!$db->table_exists($table)) {
			$gaps[] = $table;
			continue;
		}
		foreach ($fields as $field) {
			if (!$db->field_exists($field, $table)) {
				$gaps[] = $table . '.' . $field;
			}
		}
	}

	$column_contracts = array(
		array('users', 'nik', 'char(16)', 'YES', 'ascii', 'ascii_bin'),
		array('users', 'nomor_kk', 'char(16)', 'YES', 'ascii', 'ascii_bin'),
		array('users', 'nomor_bpjs_kis', 'varchar(13)', 'YES', 'ascii', 'ascii_bin'),
		array('puskesmas_staff', 'nip', 'char(18)', 'YES', 'ascii', 'ascii_bin'),
		array('puskesmas_staff', 'gelar', 'varchar(100)', 'YES', 'utf8mb4', 'utf8mb4_unicode_ci'),
	);
	foreach ($column_contracts as $contract) {
		$row = $db->query(
			'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
			array($database, $contract[0], $contract[1])
		)->row_array();
		if (!$row || strtolower((string) $row['COLUMN_TYPE']) !== $contract[2]
			|| (string) $row['IS_NULLABLE'] !== $contract[3]
			|| !($row['COLUMN_DEFAULT'] === null || strtoupper(trim((string) $row['COLUMN_DEFAULT'])) === 'NULL')
			|| strtolower((string) $row['CHARACTER_SET_NAME']) !== $contract[4]
			|| strtolower((string) $row['COLLATION_NAME']) !== $contract[5]) {
			$gaps[] = $contract[0] . '.' . $contract[1] . '.contract';
		}
	}
	$sip_expiry = $db->query(
		'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
		array($database, 'puskesmas_staff', 'sip_expired_at')
	)->row_array();
	if (!$sip_expiry || strtolower((string) $sip_expiry['COLUMN_TYPE']) !== 'date'
		|| (string) $sip_expiry['IS_NULLABLE'] !== 'YES'
		|| !($sip_expiry['COLUMN_DEFAULT'] === null || strtoupper(trim((string) $sip_expiry['COLUMN_DEFAULT'])) === 'NULL')) {
		$gaps[] = 'puskesmas_staff.sip_expired_at.contract';
	}
	$indexes = array(
		array('users', 'uq_users_nik', 'nik'),
		array('users', 'uq_users_nomor_bpjs_kis', 'nomor_bpjs_kis'),
		array('puskesmas_staff', 'uq_puskesmas_staff_nip', 'nip'),
	);
	foreach ($indexes as $index) {
		$rows = $db->query(
			'SELECT NON_UNIQUE,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',
			array($database, $index[0], $index[1])
		)->result_array();
		if (count($rows) !== 1 || (int) $rows[0]['NON_UNIQUE'] !== 0 || (string) $rows[0]['COLUMN_NAME'] !== $index[2]) {
			$gaps[] = $index[0] . '.' . $index[1];
		}
	}
	return array_values(array_unique($gaps));
}

function readiness_storage_state($storage_path)
{
	$resolved = realpath($storage_path);
	$public = realpath(FCPATH);
	$private = is_string($resolved) && is_string($public)
		&& $resolved !== $public && strpos(rtrim($resolved, '/') . '/', rtrim($public, '/') . '/') !== 0;
	$mode = is_string($resolved) ? (fileperms($resolved) & 0777) : 0;
	$storage_owner = is_string($resolved) ? fileowner($resolved) : false;
	$runtime_owner = is_string($public) ? fileowner($public) : false;
	return array(
		'ready' => is_string($resolved) && is_dir($resolved) && is_readable($resolved),
		'private' => $private,
		'mode_0700' => $mode === 0700,
		'owner_ready' => $storage_owner !== false && $runtime_owner !== false && $storage_owner === $runtime_owner,
	);
}

function readiness_file_owner_ready($path, $storage_path)
{
	$resolved_file = realpath((string) $path);
	$resolved_storage = realpath((string) $storage_path);
	$resolved_public = realpath(FCPATH);
	if (!is_string($resolved_file) || !is_string($resolved_storage) || !is_string($resolved_public)) {
		return false;
	}
	$private = strpos($resolved_file . '/', rtrim($resolved_storage, '/') . '/') === 0;
	$expected_root = $private ? $resolved_storage : $resolved_public;
	$file_owner = fileowner($resolved_file);
	$expected_owner = fileowner($expected_root);
	return $file_owner !== false && $expected_owner !== false && $file_owner === $expected_owner;
}

function readiness_text_valid($value, $minimum)
{
	$value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
	$length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	return $length >= (int) $minimum && !in_array(strtolower($value), array('n/a', 'na', '-', 'default', 'belum ditentukan'), true);
}

function readiness_operational_state($db, ReadinessIdentityResolver $resolver)
{
	$managed_gap_counts = array(
		'facility_name' => 0, 'facility_address' => 0, 'facility_latitude' => 0,
		'facility_longitude' => 0, 'facility_command_center' => 0,
		'staff_name' => 0, 'staff_phone' => 0, 'staff_profession' => 0,
		'staff_title' => 0, 'staff_birthdate' => 0, 'staff_gender' => 0,
		'staff_registration_number' => 0, 'staff_registration_expiry' => 0,
		'staff_sip_expiring' => 0, 'staff_sip_expired' => 0, 'staff_identity' => 0,
	);
	$facilities = $db->select('kode_pkm, nama_puskesmas, alamat, latitude, longitude')->where('status', 'aktif')->get('m_puskesmas')->result();
	$facility_complete = 0;
	$facility_command_center_ready = 0;
	foreach ($facilities as $facility) {
		$latitude = trim((string) $facility->latitude);
		$longitude = trim((string) $facility->longitude);
		$name_ready = readiness_text_valid($facility->nama_puskesmas, 3);
		$address_ready = readiness_text_valid($facility->alamat, 5);
		$latitude_ready = $latitude !== '' && is_numeric($latitude) && (float) $latitude >= -90 && (float) $latitude <= 90;
		$longitude_ready = $longitude !== '' && is_numeric($longitude) && (float) $longitude >= -180 && (float) $longitude <= 180;
		if (!$name_ready) { $managed_gap_counts['facility_name']++; }
		if (!$address_ready) { $managed_gap_counts['facility_address']++; }
		if (!$latitude_ready) { $managed_gap_counts['facility_latitude']++; }
		if (!$longitude_ready) { $managed_gap_counts['facility_longitude']++; }
		if ($name_ready && $address_ready && $latitude_ready && $longitude_ready) {
			$facility_complete++;
		}
		$command_center_ready = $resolver->commandCenterReady($facility->kode_pkm);
		if ($command_center_ready) {
			$facility_command_center_ready++;
		} else {
			$managed_gap_counts['facility_command_center']++;
		}
	}

	$staff_rows = $db
		->select('ps.staff_id, ps.kode_pkm, ps.nama, ps.no_hp, ps.profesi, ps.nomor_sip, ps.user_id, ps.status, staff_user.nama AS account_name, staff_user.no_hp AS account_phone, staff_user.tgl AS account_birthdate, staff_user.gender AS account_gender, staff_user.status AS account_status')
		->select($db->field_exists('gelar', 'puskesmas_staff') ? 'ps.gelar' : 'NULL AS gelar', false)
		->select($db->field_exists('sip_expired_at', 'puskesmas_staff') ? 'ps.sip_expired_at' : 'NULL AS sip_expired_at', false)
		->from('puskesmas_staff AS ps')
		->join('users AS staff_user', 'staff_user.userId = ps.user_id', 'left')
		->where('ps.status', 'aktif')
		->get()->result();
	$staff_ready = 0;
	$profile_policy = new Nakes_profile_readiness_policy();
	foreach ($staff_rows as $staff) {
		$identity = $resolver->resolve((int) $staff->user_id);
		$identity_ready = !empty($identity['valid'])
			&& (string) $identity['account_type'] === 'personal'
			&& (int) $identity['staff_id'] === (int) $staff->staff_id
			&& (string) $identity['puskesmas_code'] === trim((string) $staff->kode_pkm);
		$state = $profile_policy->evaluate(array(
			'name' => $staff->account_name, 'title' => $staff->gelar,
			'birthdate' => $staff->account_birthdate, 'gender' => $staff->account_gender,
			'profession' => $staff->profesi, 'registration_number' => $staff->nomor_sip,
			'registration_expires_at' => $staff->sip_expired_at, 'phone' => $staff->account_phone,
			'account_state' => $identity_ready ? 'linked' : 'invalid', 'staff_status' => $staff->status,
			'account_status' => $staff->account_status, 'facility_status' => $identity_ready ? 'aktif' : '',
		));
		if (in_array('name', $state['missing_fields'], true)) { $managed_gap_counts['staff_name']++; }
		if (in_array('phone', $state['missing_fields'], true)) { $managed_gap_counts['staff_phone']++; }
		if (in_array('title', $state['missing_fields'], true)) { $managed_gap_counts['staff_title']++; }
		if (in_array('birthdate', $state['missing_fields'], true)) { $managed_gap_counts['staff_birthdate']++; }
		if (in_array('gender', $state['missing_fields'], true)) { $managed_gap_counts['staff_gender']++; }
		if (in_array('profession', $state['missing_fields'], true)) { $managed_gap_counts['staff_profession']++; }
		if (in_array('registration_number', $state['missing_fields'], true)) { $managed_gap_counts['staff_registration_number']++; }
		if (in_array('registration_expiry', $state['missing_fields'], true)) { $managed_gap_counts['staff_registration_expiry']++; }
		if ($state['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_EXPIRING) { $managed_gap_counts['staff_sip_expiring']++; }
		if ($state['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_EXPIRED) { $managed_gap_counts['staff_sip_expired']++; }
		if (!$identity_ready) { $managed_gap_counts['staff_identity']++; }
		if ($state['operationally_ready'] && $identity_ready) {
			$staff_ready++;
		}
	}
	return array(
		'facility_total' => count($facilities),
		'facility_complete' => $facility_complete,
		'facility_command_center_ready' => $facility_command_center_ready,
		'staff_total' => count($staff_rows),
		'staff_ready' => $staff_ready,
		'managed_gap_counts' => $managed_gap_counts,
	);
}

try {
	$database = readiness_env('DOCLINC_READINESS_DB_NAME');
	$user = readiness_env('DOCLINC_READINESS_DB_USER');
	$password = readiness_env('DOCLINC_READINESS_DB_PASSWORD');
	$allowed_users = array_filter(array_map('trim', explode(',', readiness_env('DOCLINC_READINESS_ALLOWED_USERS'))));
	if ($database !== 'doclinc-staging') { throw new RuntimeException('database_not_allowlisted'); }
	if ($user === '' || $password === '' || !in_array($user, $allowed_users, true)) { throw new RuntimeException('database_identity_not_allowlisted'); }
	$host = readiness_env('DOCLINC_READINESS_DB_HOST', '127.0.0.1');
	$port = (int) readiness_env('DOCLINC_READINESS_DB_PORT', '3306');
	$storage_path = readiness_env('DOCLINC_READINESS_PROFILE_STORAGE', '/home/doclinc-dev/private/profile-images');
	if ($host !== '127.0.0.1' || $port !== 3306 || $storage_path !== '/home/doclinc-dev/private/profile-images') {
		throw new RuntimeException('staging_target_not_allowlisted');
	}

	$stage = 'database_connect';
	$db = DB(array(
		'dsn' => '', 'hostname' => $host, 'username' => $user, 'password' => $password, 'database' => $database,
		'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false, 'db_debug' => false, 'cache_on' => false,
		'cachedir' => '', 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'swap_pre' => '',
		'encrypt' => false, 'compress' => false, 'stricton' => true, 'failover' => array(), 'save_queries' => false, 'port' => $port,
	), true);
	if (!$db || !$db->conn_id) { throw new RuntimeException('database_connection_failed'); }
	$actual = $db->query('SELECT DATABASE() AS database_name')->row();
	if (!$actual || !hash_equals($database, (string) $actual->database_name)) { throw new RuntimeException('connected_database_mismatch'); }
	if (!$db->query('START TRANSACTION READ ONLY')) { throw new RuntimeException('read_only_transaction_failed'); }
	$transaction_started = true;

	$stage = 'schema';
	$schema_gaps = readiness_schema_gaps($db, $database);
	$states = array();
	$operational_state = array();
	if (empty($schema_gaps)) {
		$stage = 'actor_projection';
		$actors = $db->select('userId, role, must_change_password, password_changed_at')->where_in('role', array('warga', 'dokter'))->where('status', 'aktif')
			->order_by('userId', 'ASC')->limit(10001)->get('users')->result();
		if (count($actors) > 10000) { throw new RuntimeException('actor_result_too_large'); }
		$resolver = new ReadinessIdentityResolver($db);
		$operational_state = readiness_operational_state($db, $resolver);
		$storage = new Profile_image_storage(array('storage_path' => $storage_path, 'public_root' => FCPATH));
		$service = new Role_prerequisite_service($db, array(
			'identity_resolver' => array($resolver, 'resolve'),
			'photo_validator' => function ($stored_key) use ($storage, $storage_path) {
				$path = $storage->resolve_stored_file($stored_key);
				return $path !== false
					&& readiness_file_owner_ready($path, $storage_path)
					&& $storage->allowed_mime($path) !== '';
			},
		));
		$credential_policy = new Nakes_credential_policy();
		foreach ($actors as $actor) {
			$state = $service->evaluate((int) $actor->userId, true);
			$credential_state = $credential_policy->state(
				(string) $actor->role,
				(int) $actor->must_change_password,
				$actor->password_changed_at
			);
			if ($credential_policy->requiresChange($actor->role, $actor->must_change_password, $actor->password_changed_at)) {
				$state = array(
					'complete' => false,
					'actor_type' => 'denied',
					'safe_error_code' => 'actor_denied',
					'missing_fields' => array(),
					'schema_gaps' => array(),
					'self_service_fields' => array(),
					'managed_fields' => array(),
					'remediation_mode' => 'none',
				);
			}
			$state['audit_credential_state'] = $credential_state;
			$state['audit_denial_reason'] = $credential_policy->requiresChange($actor->role, $actor->must_change_password, $actor->password_changed_at)
				? 'password_change_required'
				: (((string) ($state['actor_type'] ?? '') === 'denied'
					|| (string) ($state['safe_error_code'] ?? '') === 'actor_denied') ? 'identity_invalid' : '');
			$states[] = $state;
		}
	}

	$stage = 'report';
	$storage_state = readiness_storage_state($storage_path);
	$report = ProductionReadinessReport::compile($states, $schema_gaps, $storage_state, $operational_state);
	$transaction = $db->query('SELECT @@in_transaction AS active')->row();
	if (!$transaction || (int) $transaction->active !== 1) { throw new RuntimeException('read_only_transaction_lost'); }
	$db->query('ROLLBACK');
	$transaction_started = false;

	echo "PRODUCTION_READINESS_AUDIT=PASS\n";
	echo "DATABASE_TARGET_CONFIRMED=true\nREAD_ONLY_TRANSACTION=true\nDATABASE_WRITE_EXECUTED=false\nPII_PRINTED=false\n";
	foreach (ProductionReadinessReport::lines($report) as $line) { echo $line . "\n"; }
	$exit_code = $report['activation_ready'] ? 0 : 3;
} catch (Throwable $exception) {
	$allowed = array(
		'database_not_allowlisted', 'database_identity_not_allowlisted', 'database_connection_failed',
		'staging_target_not_allowlisted', 'connected_database_mismatch', 'read_only_transaction_failed', 'actor_result_too_large',
		'read_only_transaction_lost',
	);
	$code = in_array($exception->getMessage(), $allowed, true) ? $exception->getMessage() : 'readiness_audit_failed';
	readiness_fail($stage, $code);
	$exit_code = 1;
} finally {
	if ($db && $transaction_started) { try { $db->query('ROLLBACK'); } catch (Throwable $ignored) {} }
	if ($db && method_exists($db, 'close')) { $db->close(); }
}

exit($exit_code);
