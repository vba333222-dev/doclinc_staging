<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
require_once APPPATH . 'libraries/Session_binding_service.php';
require_once APPPATH . 'libraries/Care_team_service.php';
require_once APPPATH . 'libraries/Nakes_presence_policy.php';
require_once dirname(__DIR__, 2) . '/realtime_requests/tests/MariaDbReadiness.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$passed = 0;
$failed = 0;
$admin = null;
$db = null;
$database = '';

function phase45_db_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$name}\n"; return; }
	$failed++; fwrite(STDERR, "FAIL {$name}\n");
}

function phase45_db_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function phase45_db_identifier($value)
{
	if (preg_match('/\A[a-z0-9_]+\z/', $value) !== 1) {
		throw new RuntimeException('unsafe_database_identifier');
	}
	return '`' . $value . '`';
}

function phase45_db_argument($prefix)
{
	global $argv;
	foreach ($argv as $argument) {
		if (strpos($argument, $prefix) === 0) {
			return substr($argument, strlen($prefix));
		}
	}
	return '';
}

function phase45_child_database($password, $database)
{
	return DB(array(
		'dsn' => '', 'hostname' => phase45_db_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		'username' => phase45_db_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'), 'password' => $password,
		'database' => $database, 'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false,
		'db_debug' => false, 'cache_on' => false, 'cachedir' => '', 'char_set' => 'utf8mb4',
		'dbcollat' => 'utf8mb4_unicode_ci', 'swap_pre' => '', 'encrypt' => false, 'compress' => false,
		'stricton' => true, 'failover' => array(), 'save_queries' => true,
		'port' => (int) phase45_db_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306'),
	), true);
}

function phase45_child_wait($database, $barrier)
{
	if (preg_match('/\Adoclinc_session_care_test_[a-z0-9_]+\z/', $database) !== 1 || $barrier === '') {
		exit(3);
	}
	$deadline = microtime(true) + 10;
	while (!is_file($barrier) && microtime(true) < $deadline) {
		usleep(10000);
	}
	if (!is_file($barrier)) {
		exit(4);
	}
}

function phase45_run_concurrently($command_one, $command_two, $barrier)
{
	$descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$one = proc_open($command_one, $descriptors, $one_pipes);
	$two = proc_open($command_two, $descriptors, $two_pipes);
	if (!is_resource($one) || !is_resource($two)) {
		return null;
	}
	touch($barrier);
	fclose($one_pipes[0]);
	fclose($two_pipes[0]);
	$one_output = trim(stream_get_contents($one_pipes[1]));
	$two_output = trim(stream_get_contents($two_pipes[1]));
	$one_error = trim(stream_get_contents($one_pipes[2]));
	$two_error = trim(stream_get_contents($two_pipes[2]));
	fclose($one_pipes[1]);
	fclose($one_pipes[2]);
	fclose($two_pipes[1]);
	fclose($two_pipes[2]);
	$one_exit = proc_close($one);
	$two_exit = proc_close($two);
	return array($one_exit, $one_output, $one_error, $two_exit, $two_output, $two_error);
}

$password = phase45_db_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') {
	fwrite(STDERR, "PHASE45_INTEGRATION=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

$child_user_id = (int) phase45_db_argument('--issue-binding-child=');
if ($child_user_id > 0) {
	$child_database = phase45_db_argument('--database=');
	$barrier = phase45_db_argument('--barrier=');
	phase45_child_wait($child_database, $barrier);
	$child_db = phase45_child_database($password, $child_database);
	$expected_hash = phase45_db_argument('--expected-password-hash=');
	$child_token = (new Session_binding_service($child_db))->issue($child_user_id, $expected_hash === '' ? null : $expected_hash);
	$child_db->close();
	echo is_string($child_token) ? $child_token : 'NONE';
	exit(0);
}

$rotate_user_id = (int) phase45_db_argument('--rotate-password-child=');
if ($rotate_user_id > 0) {
	$child_database = phase45_db_argument('--database=');
	$barrier = phase45_db_argument('--barrier=');
	phase45_child_wait($child_database, $barrier);
	$child_db = phase45_child_database($password, $child_database);
	$token = (new Session_binding_service($child_db))->rotatePassword(
		$rotate_user_id,
		phase45_db_argument('--current-token='),
		phase45_db_argument('--expected-password-hash='),
		phase45_db_argument('--new-password-hash=')
	);
	$child_db->close();
	echo is_string($token) ? $token : 'NONE';
	exit(0);
}

$reset_user_id = (int) phase45_db_argument('--reset-password-child=');
if ($reset_user_id > 0) {
	$child_database = phase45_db_argument('--database=');
	$barrier = phase45_db_argument('--barrier=');
	phase45_child_wait($child_database, $barrier);
	$child_db = phase45_child_database($password, $child_database);
	$service = new Session_binding_service($child_db);
	$child_db->trans_begin();
	$locked = $child_db->query('SELECT userId FROM users WHERE userId=? FOR UPDATE', array($reset_user_id))->row();
	$updated = $locked && $child_db->where('userId', $reset_user_id)->update('users', array('password' => phase45_db_argument('--new-password-hash=')));
	$revoked = $updated && $service->revokeLocked($reset_user_id);
	$committed = $revoked && $child_db->trans_status() !== false && $child_db->trans_commit();
	if (!$committed) { $child_db->trans_rollback(); }
	$child_db->close();
	echo $committed ? 'RESET' : 'NONE';
	exit(0);
}

try {
	$host = phase45_db_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
	$port = (int) phase45_db_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
	$user = phase45_db_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
	$ready = MariaDbReadiness::wait(array(
		'host' => $host,
		'user' => $user,
		'password' => $password,
		'port' => $port,
		'timeout_ms' => (int) phase45_db_env('DOCLINC_TEST_DB_READY_TIMEOUT_MS', '45000'),
		'interval_ms' => (int) phase45_db_env('DOCLINC_TEST_DB_READY_INTERVAL_MS', '500'),
	));
	$admin = $ready->connection;
	$database = 'doclinc_session_care_test_' . bin2hex(random_bytes(5));
	$admin->query('CREATE DATABASE ' . phase45_db_identifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$bootstrap = new mysqli($host, $user, $password, $database, $port);
	$bootstrap->set_charset('utf8mb4');
	$ddl = array(
		"CREATE TABLE users(userId int(11) NOT NULL AUTO_INCREMENT,password varchar(255) NOT NULL,role enum('admin','dokter','warga','') NOT NULL,status enum('aktif','nonaktif') NULL DEFAULT 'aktif',remark varchar(100) NULL,must_change_password tinyint(1) NOT NULL DEFAULT 0,password_changed_at datetime NULL,updated_at datetime NULL,PRIMARY KEY(userId)) ENGINE=InnoDB",
		"CREATE TABLE m_puskesmas(kode_pkm varchar(100) NOT NULL,status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',PRIMARY KEY(kode_pkm)) ENGINE=InnoDB",
		"CREATE TABLE puskesmas_staff(staff_id int(10) unsigned NOT NULL AUTO_INCREMENT,user_id int(11) NULL,kode_pkm varchar(100) NOT NULL,profesi varchar(100) NULL,status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',PRIMARY KEY(staff_id),KEY idx_staff_user(user_id)) ENGINE=InnoDB",
		"CREATE TABLE requests(request_id int(11) NOT NULL AUTO_INCREMENT,request_status enum('Pending','Accepted','Completed','Cancelled') NULL DEFAULT 'Pending',assigned_puskesmas_code varchar(100) NULL,accepted_by_user_id int(11) NULL,assigned_nakes_user_id int(11) NULL,assigned_nakes_at datetime NULL,assigned_nakes_by_user_id int(11) NULL,consultation_mode varchar(30) NULL,PRIMARY KEY(request_id)) ENGINE=InnoDB",
		"CREATE TABLE request_staff_assignments(assignment_id int(10) unsigned NOT NULL AUTO_INCREMENT,request_id int(11) NOT NULL,staff_id int(10) unsigned NOT NULL,kode_pkm varchar(100) NOT NULL,assigned_by_user_id int(11) NOT NULL,status enum('aktif','diganti','dibatalkan') NOT NULL DEFAULT 'aktif',note text NULL,assigned_at datetime NOT NULL,ended_at datetime NULL,created_at datetime NOT NULL,updated_at datetime NULL,PRIMARY KEY(assignment_id),KEY idx_request_status(request_id,status)) ENGINE=InnoDB",
	);
	foreach ($ddl as $sql) { $bootstrap->query($sql); }
	$bootstrap->close();

	putenv('DOCLINC_SESSION_CARE_SCHEMA_WRITE_ENABLED=true');
	putenv('DOCLINC_SESSION_CARE_DISPOSABLE_TEST=true');
	putenv('DOCLINC_SESSION_CARE_SCHEMA_DB_HOST=' . $host);
	putenv('DOCLINC_SESSION_CARE_SCHEMA_DB_PORT=' . $port);
	putenv('DOCLINC_SESSION_CARE_SCHEMA_DB_NAME=' . $database);
	putenv('DOCLINC_SESSION_CARE_SCHEMA_DB_USER=' . $user);
	putenv('DOCLINC_SESSION_CARE_SCHEMA_DB_PASSWORD=' . $password);
	putenv('DOCLINC_SESSION_CARE_SCHEMA_ALLOWED_USERS=' . $user);
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(APPPATH . 'migrations/20260811000100_session_care_team_foundation.php')
		. ' --apply --environment=test --confirm-database=' . escapeshellarg($database) . ' --confirm-disposable-test=true';
	$output = array();
	$exit_code = 1;
	exec($command . ' 2>&1', $output, $exit_code);
	phase45_db_expect($exit_code === 0 && in_array('MIGRATION_RESULT=PASS', $output, true), 'migration_applies_to_disposable_database');

	$db = DB(array(
		'dsn' => '', 'hostname' => $host, 'username' => $user, 'password' => $password, 'database' => $database,
		'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false, 'db_debug' => false, 'cache_on' => false,
		'cachedir' => '', 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'swap_pre' => '',
		'encrypt' => false, 'compress' => false, 'stricton' => true, 'failover' => array(), 'save_queries' => true, 'port' => $port,
	), true);

	$db->query("INSERT INTO m_puskesmas VALUES('PKM01','aktif'),('PKM02','aktif')");
	$db->query("INSERT INTO users(userId,password,role,status,remark) VALUES(10,'hash-10-a','dokter','aktif','PKM01'),(20,'hash-20-a','dokter','aktif','PKM02'),(101,'hash-101-a','warga','aktif',NULL),(201,'hash-201-a','dokter','aktif','PKM01'),(202,'hash-202-a','dokter','aktif','PKM01'),(203,'hash-203-a','dokter','nonaktif','PKM01'),(204,'hash-204-a','dokter','aktif','PKM01'),(301,'hash-301-a','dokter','aktif','PKM02')");
	$db->query("INSERT INTO puskesmas_staff(staff_id,user_id,kode_pkm,profesi,status) VALUES(31,201,'PKM01','Dokter Umum','aktif'),(32,202,'PKM01','Perawat','aktif'),(33,301,'PKM02','Dokter','aktif'),(34,203,'PKM01','Bidan','aktif'),(35,204,'PKM01','Dokter','aktif'),(36,204,'PKM01','Dokter','aktif')");
	$db->query("INSERT INTO requests(request_id,request_status,assigned_puskesmas_code,accepted_by_user_id) VALUES(100,'Accepted','PKM01',10),(101,'Accepted','PKM01',10),(102,'Accepted','PKM01',10)");

	$binding = new Session_binding_service($db);
	phase45_db_expect($binding->schemaReady(), 'session_binding_schema_ready');
	$browser_a = $binding->issue(201);
	phase45_db_expect(is_string($browser_a) && $binding->validate(201, $browser_a), 'browser_a_login_valid');
	$browser_b = $binding->issue(201);
	phase45_db_expect(is_string($browser_b) && !$binding->validate(201, $browser_a) && $binding->validate(201, $browser_b), 'browser_b_replaces_browser_a');
	$barrier = tempnam(sys_get_temp_dir(), 'doclinc_binding_');
	if (is_string($barrier) && is_file($barrier)) {
		unlink($barrier);
	}
	$child_command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
		. ' --issue-binding-child=202 --database=' . escapeshellarg($database)
		. ' --barrier=' . escapeshellarg((string) $barrier);
	$descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$child_one = proc_open($child_command, $descriptors, $child_one_pipes);
	$child_two = proc_open($child_command, $descriptors, $child_two_pipes);
	if (is_resource($child_one) && is_resource($child_two) && is_string($barrier)) {
		touch($barrier);
		fclose($child_one_pipes[0]);
		fclose($child_two_pipes[0]);
		$child_one_token = trim(stream_get_contents($child_one_pipes[1]));
		$child_two_token = trim(stream_get_contents($child_two_pipes[1]));
		fclose($child_one_pipes[1]);
		fclose($child_one_pipes[2]);
		fclose($child_two_pipes[1]);
		fclose($child_two_pipes[2]);
		$child_one_exit = proc_close($child_one);
		$child_two_exit = proc_close($child_two);
		phase45_db_expect($child_one_exit === 0 && $child_two_exit === 0
			&& ($binding->validate(202, $child_one_token) xor $binding->validate(202, $child_two_token)), 'concurrent_login_keeps_one_canonical_binding');
	} else {
		phase45_db_expect(false, 'concurrent_login_keeps_one_canonical_binding');
	}
	if (is_string($barrier) && is_file($barrier)) {
		unlink($barrier);
	}
	$barrier = tempnam(sys_get_temp_dir(), 'doclinc_self_rotate_');
	if (is_string($barrier) && is_file($barrier)) { unlink($barrier); }
	$rotate_command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
		. ' --rotate-password-child=201 --database=' . escapeshellarg($database)
		. ' --barrier=' . escapeshellarg((string) $barrier)
		. ' --current-token=' . escapeshellarg($browser_b)
		. ' --expected-password-hash=' . escapeshellarg('hash-201-a')
		. ' --new-password-hash=' . escapeshellarg('hash-201-b');
	$login_command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
		. ' --issue-binding-child=201 --database=' . escapeshellarg($database)
		. ' --barrier=' . escapeshellarg((string) $barrier)
		. ' --expected-password-hash=' . escapeshellarg('hash-201-a');
	$concurrent = phase45_run_concurrently($rotate_command, $login_command, (string) $barrier);
	$rotate_token = $concurrent ? $concurrent[1] : 'NONE';
	$login_token = $concurrent ? $concurrent[4] : 'NONE';
	$rotate_valid = $rotate_token !== 'NONE' && $binding->validate(201, $rotate_token);
	$login_valid = $login_token !== 'NONE' && $binding->validate(201, $login_token);
	$current_password = (string) $db->select('password')->where('userId', 201)->get('users')->row()->password;
	phase45_db_expect($concurrent && $concurrent[0] === 0 && $concurrent[3] === 0
		&& ($rotate_valid xor $login_valid)
		&& (($rotate_valid && $current_password === 'hash-201-b') || ($login_valid && $current_password === 'hash-201-a')), 'self_password_change_and_login_are_serialized');
	if (is_string($barrier) && is_file($barrier)) { unlink($barrier); }

	$current_201_token = $rotate_valid ? $rotate_token : $login_token;
	$current_password = (string) $db->select('password')->where('userId', 201)->get('users')->row()->password;
	$barrier = tempnam(sys_get_temp_dir(), 'doclinc_admin_reset_');
	if (is_string($barrier) && is_file($barrier)) { unlink($barrier); }
	$reset_command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
		. ' --reset-password-child=201 --database=' . escapeshellarg($database)
		. ' --barrier=' . escapeshellarg((string) $barrier)
		. ' --new-password-hash=' . escapeshellarg('hash-201-c');
	$login_command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
		. ' --issue-binding-child=201 --database=' . escapeshellarg($database)
		. ' --barrier=' . escapeshellarg((string) $barrier)
		. ' --expected-password-hash=' . escapeshellarg($current_password);
	$concurrent = phase45_run_concurrently($reset_command, $login_command, (string) $barrier);
	$concurrent_login_token = $concurrent ? $concurrent[4] : 'NONE';
	phase45_db_expect($concurrent && $concurrent[0] === 0 && $concurrent[1] === 'RESET' && $concurrent[3] === 0
		&& (string) $db->select('password')->where('userId', 201)->get('users')->row()->password === 'hash-201-c'
		&& !$binding->validate(201, $current_201_token)
		&& ($concurrent_login_token === 'NONE' || !$binding->validate(201, $concurrent_login_token)), 'admin_reset_and_login_are_serialized');
	if (is_string($barrier) && is_file($barrier)) { unlink($barrier); }

	$current_201_token = $binding->issue(201, 'hash-201-c');
	$db->query("CREATE TRIGGER fail_binding_rotation BEFORE UPDATE ON user_login_bindings FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_binding_failure'");
	$failed_rotation = $binding->rotatePassword(201, $current_201_token, 'hash-201-c', 'hash-201-d');
	$db->query('DROP TRIGGER fail_binding_rotation');
	phase45_db_expect($failed_rotation === null
		&& (string) $db->select('password')->where('userId', 201)->get('users')->row()->password === 'hash-201-c'
		&& $binding->validate(201, $current_201_token), 'binding_write_failure_rolls_back_password');

	$db->query("CREATE TRIGGER fail_password_rotation BEFORE UPDATE ON users FOR EACH ROW BEGIN IF NEW.password='hash-201-e' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_password_failure'; END IF; END");
	$failed_password = $binding->rotatePassword(201, $current_201_token, 'hash-201-c', 'hash-201-e');
	$db->query('DROP TRIGGER fail_password_rotation');
	phase45_db_expect($failed_password === null
		&& (string) $db->select('password')->where('userId', 201)->get('users')->row()->password === 'hash-201-c'
		&& $binding->validate(201, $current_201_token), 'password_write_failure_preserves_binding');

	$unclaimed_token = $binding->rotatePassword(201, $current_201_token, 'hash-201-c', 'hash-201-f');
	$new_login_token = $binding->issue(201, 'hash-201-f');
	phase45_db_expect(is_string($unclaimed_token) && is_string($new_login_token)
		&& !$binding->validate(201, $current_201_token)
		&& !$binding->validate(201, $unclaimed_token)
		&& $binding->validate(201, $new_login_token), 'post_commit_session_failure_requires_new_login');
	$browser_b = $new_login_token;
	$db->trans_begin();
	$db->query('SELECT userId FROM users WHERE userId=201 FOR UPDATE');
	$credential_updated = $db->where('userId', 201)->where('password', 'hash-201-f')->update('users', array(
		'password' => 'hash-201-g',
		'must_change_password' => 0,
		'password_changed_at' => '2026-08-11 12:00:00',
	));
	$credential_token = $credential_updated ? $binding->rotateLocked(201, $browser_b) : null;
	$credential_committed = is_string($credential_token) && $db->trans_status() !== false && $db->trans_commit();
	if (!$credential_committed) { $db->trans_rollback(); }
	$credential_row = $db->select('password, must_change_password, password_changed_at')->where('userId', 201)->get('users')->row();
	phase45_db_expect($credential_committed && !$binding->validate(201, $browser_b) && $binding->validate(201, $credential_token)
		&& (string) $credential_row->password === 'hash-201-g' && (int) $credential_row->must_change_password === 0
		&& (string) $credential_row->password_changed_at === '2026-08-11 12:00:00', 'authenticated_credential_completion_rotates_binding_atomically');
	$browser_b = $credential_token;

	phase45_db_expect(!$binding->revokeCurrent(201, $browser_a) && $binding->validate(201, $browser_b), 'stale_logout_cannot_revoke_current_login');
	$db->trans_begin();
	$db->query('SELECT userId FROM users WHERE userId=201 FOR UPDATE');
	phase45_db_expect($binding->revokeLocked(201), 'admin_reset_revocation_write');
	$db->trans_commit();
	phase45_db_expect(!$binding->validate(201, $browser_b), 'admin_reset_revokes_existing_browser');
	$browser_c = $binding->issue(201);
	$db->trans_begin();
	$db->query('SELECT userId FROM users WHERE userId=201 FOR UPDATE');
	$binding->revokeLocked(201);
	$db->trans_rollback();
	phase45_db_expect($binding->validate(201, $browser_c), 'outer_rollback_preserves_binding');
	$db->where('userId', 201)->update('users', array('status' => 'nonaktif'));
	phase45_db_expect(!$binding->validate(201, $browser_c), 'inactive_account_binding_denied');
	$db->where('userId', 201)->update('users', array('status' => 'aktif'));
	phase45_db_expect(!$binding->validate(201, 'malformed'), 'malformed_binding_denied');
	$binding->revokeCurrent(201, $browser_c);

	$presence = new Nakes_presence_policy();
	$presence_actor = array('authenticated' => true, 'user_id' => 202, 'role' => 'dokter', 'status' => 'aktif', 'must_change_password' => false, 'session_binding_valid' => false, 'identity' => array('valid' => true, 'account_type' => 'personal', 'user_id' => 202, 'staff_id' => 32, 'staff_status' => 'aktif', 'puskesmas_code' => 'PKM01'));
	phase45_db_expect(!$presence->heartbeatAllowed($presence_actor), 'revoked_session_heartbeat_denied');
	$presence_actor['session_binding_valid'] = true;
	phase45_db_expect($presence->heartbeatAllowed($presence_actor), 'valid_session_heartbeat_allowed');

	$care = new Care_team_service($db);
	phase45_db_expect($care->schemaReady(), 'care_team_schema_ready');
	$db->query("INSERT INTO request_staff_assignments(request_id,staff_id,kode_pkm,assigned_by_user_id,status,note,assigned_at,created_at) VALUES(100,31,'PKM01',10,'aktif',NULL,NOW(),NOW())");
	$command_token = $binding->issue(10);
	$binding->revokeCurrent(10, $command_token);
	phase45_db_expect(empty($care->assignResponsibleDoctor(102, 31, 10, $command_token, true)['ok'])
		&& (int) $db->where('request_id', 102)->count_all_results('request_responsible_doctor_assignments') === 0, 'revoked_session_cannot_mutate_consultation');
	phase45_db_expect(empty($care->assignResponsibleDoctor(102, 31, 10, null, true)['ok']), 'activation_context_cannot_mutate_consultation');
	$command_token = $binding->issue(10);
	phase45_db_expect(!empty($care->assignResponsibleDoctor(102, 31, 10, $command_token, true)['ok']), 'current_session_can_mutate_consultation');
	$result = $care->assignResponsibleDoctor(100, 31, 10);
	phase45_db_expect(!empty($result['ok']), 'command_center_assigns_responsible_doctor');
	phase45_db_expect((int) $db->where('request_id', 100)->count_all_results('request_responsible_doctor_assignments') === 1, 'responsible_assignment_history_created');
	$result = $care->assignResponsibleDoctor(100, 31, 10);
	phase45_db_expect(!empty($result['ok']) && (int) $db->where('request_id', 100)->count_all_results('request_responsible_doctor_assignments') === 1, 'responsible_assignment_idempotent');
	phase45_db_expect(empty($care->assignResponsibleDoctor(100, 33, 10)['ok']), 'cross_facility_responsible_doctor_denied');
	phase45_db_expect(empty($care->assignResponsibleDoctor(100, 35, 10)['ok']), 'duplicate_staff_link_responsible_doctor_denied');
	phase45_db_expect(empty($care->assignResponsibleDoctor(100, 31, 201)['ok']), 'personal_account_cannot_coordinate_responsible_assignment');
	phase45_db_expect(empty($care->chooseMode(101, 'visit', 201)['ok']), 'service_mode_requires_responsible_doctor');
	phase45_db_expect(empty($care->chooseMode(100, 'visit', 101)['ok']), 'patient_cannot_choose_service_mode');
	phase45_db_expect(!empty($care->chooseMode(100, 'non_visit', 201)['ok']), 'responsible_doctor_selects_non_visit');
	phase45_db_expect(empty($care->chooseMode(100, 'visit', 202)['ok']), 'unrelated_nakes_cannot_choose_mode');
	phase45_db_expect(empty($care->chooseMode(100, 'visit', 10)['ok']), 'command_center_cannot_impersonate_responsible_doctor');
	phase45_db_expect(!empty($care->chooseMode(100, 'visit', 201)['ok']), 'responsible_doctor_selects_visit');
	phase45_db_expect(empty($care->assignVisitPerformer(100, 33, 201)['ok']), 'cross_facility_visit_performer_denied');
	phase45_db_expect(empty($care->assignVisitPerformer(100, 34, 201)['ok']), 'inactive_visit_performer_denied');
	phase45_db_expect(empty($care->assignVisitPerformer(100, 35, 201)['ok']), 'ambiguous_visit_performer_denied');
	phase45_db_expect(!empty($care->assignVisitPerformer(100, 32, 201)['ok']), 'eligible_visit_performer_assigned');
	$request = $db->where('request_id', 100)->get('requests')->row();
	phase45_db_expect((int) $request->responsible_doctor_user_id === 201 && (int) $request->visit_performer_user_id === 202, 'responsible_doctor_and_performer_are_distinct');
	phase45_db_expect(!empty($care->requestContext(100, 201)['can_assess']) && empty($care->requestContext(100, 201)['can_visit']), 'responsible_doctor_has_assessment_authority');
	phase45_db_expect(!empty($care->requestContext(100, 202)['can_visit']) && empty($care->requestContext(100, 202)['can_assess']), 'visit_performer_has_visit_authority');
	phase45_db_expect(!empty($care->assignVisitPerformer(100, 31, 201)['ok']), 'visit_performer_reassignment_allowed');
	phase45_db_expect((int) $db->where('request_id', 100)->where('status', 'aktif')->count_all_results('request_visit_performer_assignments') === 1
		&& (int) $db->where('request_id', 100)->where('status', 'diganti')->count_all_results('request_visit_performer_assignments') === 1, 'visit_reassignment_preserves_history');
	$request = $db->where('request_id', 100)->get('requests')->row();
	phase45_db_expect((int) $request->responsible_doctor_user_id === 201, 'performer_change_preserves_responsible_doctor');
	phase45_db_expect(!empty($care->chooseMode(100, 'non_visit', 201)['ok']), 'non_visit_cancels_performer');
	$request = $db->where('request_id', 100)->get('requests')->row();
	phase45_db_expect($request->visit_performer_user_id === null && (int) $db->where('request_id', 100)->where('status', 'aktif')->count_all_results('request_visit_performer_assignments') === 0, 'non_visit_requires_no_performer');
	phase45_db_expect((int) $db->where('request_id', 100)->where('status', 'aktif')->count_all_results('request_staff_assignments') === 1, 'legacy_pic_history_is_not_visit_performer_history');

	$db->query("CREATE TRIGGER fail_responsible_update BEFORE UPDATE ON requests FOR EACH ROW BEGIN IF NEW.request_id=101 AND NEW.responsible_doctor_user_id IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'; END IF; END");
	$result = $care->assignResponsibleDoctor(101, 31, 10);
	phase45_db_expect(empty($result['ok']) && (int) $db->where('request_id', 101)->count_all_results('request_responsible_doctor_assignments') === 0, 'responsible_second_write_failure_rolls_back_history');
	$db->query('DROP TRIGGER fail_responsible_update');

	phase45_db_expect(!empty($care->assignResponsibleDoctor(102, 31, 10)['ok']) && !empty($care->chooseMode(102, 'visit', 201)['ok']), 'performer_rollback_fixture_ready');
	$db->query("CREATE TRIGGER fail_performer_update BEFORE UPDATE ON requests FOR EACH ROW BEGIN IF NEW.request_id=102 AND NEW.visit_performer_user_id IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'; END IF; END");
	$result = $care->assignVisitPerformer(102, 32, 201);
	phase45_db_expect(empty($result['ok']) && (int) $db->where('request_id', 102)->count_all_results('request_visit_performer_assignments') === 0, 'performer_second_write_failure_rolls_back_history');
	$db->query('DROP TRIGGER fail_performer_update');
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL integration_runtime\nSAFE_ERROR_CODE=phase45_integration_failed\n");
} finally {
	if ($db && method_exists($db, 'close')) { $db->close(); }
	if ($admin && $database !== '' && preg_match('/\Adoclinc_session_care_test_[a-f0-9]{10}\z/', $database) === 1) {
		try { $admin->query('DROP DATABASE ' . phase45_db_identifier($database)); } catch (Throwable $ignored) { $failed++; }
	}
	if ($admin) { $admin->close(); }
}

echo "PHASE45_DB_PASSED={$passed}\nPHASE45_DB_FAILED={$failed}\nPHASE45_DB_SKIPPED=0\n";
exit($failed === 0 ? 0 : 1);
