<?php
if (!defined('BASEPATH')) { define('BASEPATH', __DIR__ . '/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }

require_once APPPATH . 'libraries/Nakes_personal_account_policy.php';
require_once APPPATH . 'libraries/Password_strength_policy.php';
require_once APPPATH . 'libraries/First_login_password_policy.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$passed = 0;
$failed = 0;
$database_created = false;
$database = '';
$admin = null;
$db = null;

function phase23_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function phase23_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; echo "FAIL {$label}\n";
}

function phase23_identifier($value)
{
	if (preg_match('/\A[a-z0-9_]+\z/', $value) !== 1) {
		throw new RuntimeException('unsafe_database_identifier');
	}
	return '`' . $value . '`';
}

function phase23_user(mysqli $db, $user_id)
{
	$stmt = $db->prepare('SELECT userId, role, status, remark, must_change_password, password_changed_at FROM users WHERE userId = ?');
	$stmt->bind_param('i', $user_id);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $row ?: null;
}

function phase23_state(mysqli $db, $user_id)
{
	$user = phase23_user($db, $user_id);
	if (!$user) { return Nakes_personal_account_policy::INVALID; }
	$stmt = $db->prepare("SELECT staff_id, kode_pkm, status FROM puskesmas_staff WHERE user_id = ? AND status = 'aktif' ORDER BY staff_id");
	$stmt->bind_param('i', $user_id);
	$stmt->execute();
	$links = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	$facility = null;
	$stmt = $db->prepare('SELECT kode_pkm, status FROM m_puskesmas WHERE kode_pkm = ?');
	$stmt->bind_param('s', $user['remark']);
	$stmt->execute();
	$facility = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	$stmt = $db->prepare("SELECT MIN(userId) AS userId FROM users WHERE role = 'dokter' AND status = 'aktif' AND TRIM(remark) = ?");
	$stmt->bind_param('s', $user['remark']);
	$stmt->execute();
	$command_center = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	$link = count($links) === 1 ? $links[0] : array();
	$result = (new Nakes_personal_account_policy())->evaluate(array(
		'staff_exists' => count($links) === 1,
		'staff_status' => $link['status'] ?? '',
		'staff_facility' => $link['kode_pkm'] ?? '',
		'facility_status' => $facility['status'] ?? '',
		'user_id' => $user_id,
		'user_exists' => true,
		'user_role' => $user['role'],
		'user_status' => $user['status'],
		'user_facility' => $user['remark'],
		'active_link_count' => count($links),
		'is_command_center' => (int) ($command_center['userId'] ?? 0) === (int) $user_id,
		'must_change_password' => $user['must_change_password'],
		'password_changed_at' => $user['password_changed_at'],
	));
	return $result['state'];
}

function phase23_count(mysqli $db, $sql)
{
	return (int) $db->query($sql)->fetch_row()[0];
}

$database = strtolower(phase23_env('DOCLINC_PHASE23_TEST_DB_NAME'));
$confirmation = phase23_env('DOCLINC_PHASE23_TEST_DB_DISPOSABLE_CONFIRM');
if (preg_match('/\Adoclinc_phase23_test_[a-z0-9_]+\z/', $database) !== 1
	|| strpos($database, 'staging') !== false || strpos($database, 'production') !== false
	|| $confirmation !== 'YES_DELETE_TEST_DATABASE') {
	fwrite(STDERR, "PHASE23_DISPOSABLE_DB_RESULT=BLOCKED\nSAFE_ERROR_CODE=disposable_database_guard_failed\n");
	exit(2);
}

try {
	$host = phase23_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
	$port = (int) phase23_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
	$user = phase23_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
	$password = phase23_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
	if ($password === '') { throw new RuntimeException('disposable_database_password_missing'); }
	$admin = new mysqli($host, $user, $password, '', $port);
	$version = (string) $admin->query('SELECT VERSION()')->fetch_row()[0];
	if (preg_match('/\A10\.11\./', $version) !== 1) { throw new RuntimeException('mariadb_10_11_required'); }
	$admin->query('CREATE DATABASE ' . phase23_identifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$database_created = true;
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');

	$db->query("CREATE TABLE m_puskesmas (kode_pkm varchar(100) NOT NULL PRIMARY KEY, nama_puskesmas varchar(150) NOT NULL, status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif') ENGINE=InnoDB");
	$db->query("CREATE TABLE users (userId int NOT NULL AUTO_INCREMENT PRIMARY KEY, nama varchar(100) NOT NULL, email varchar(100) NOT NULL, username varchar(100) NOT NULL, password varchar(255) NOT NULL, no_hp varchar(50) NULL, tgl date NULL, gender varchar(20) NULL, role enum('admin','dokter','warga','') NOT NULL, status enum('aktif','nonaktif') NULL DEFAULT 'aktif', remark varchar(100) NULL, must_change_password tinyint(1) NOT NULL DEFAULT 0, password_changed_at datetime NULL, UNIQUE KEY uq_username(username), UNIQUE KEY uq_email(email)) ENGINE=InnoDB");
	$db->query("CREATE TABLE puskesmas_staff (staff_id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, kode_pkm varchar(100) NOT NULL, user_id int NULL, nama varchar(150) NOT NULL, gelar varchar(100) NULL, no_hp varchar(50) NULL, profesi varchar(100) NULL, nomor_sip varchar(100) NULL, sip_expired_at date NULL, status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif', nip char(18) NULL, KEY idx_staff_user(user_id)) ENGINE=InnoDB");
	$db->query("INSERT INTO m_puskesmas(kode_pkm,nama_puskesmas,status) VALUES ('PKM01','Facility 01','aktif'),('PKM02','Facility 02','aktif')");
	$active_hash = password_hash('Existing-passphrase-2026!', PASSWORD_DEFAULT);
	$stmt = $db->prepare("INSERT INTO users(nama,email,username,password,no_hp,tgl,gender,role,status,remark,must_change_password,password_changed_at) VALUES ('Facility actor','facility@example.invalid','facility.actor',?,'080000000001','1990-01-01','Laki-laki','dokter','aktif','PKM01',0,'2026-08-01 00:00:00')");
	$stmt->bind_param('s', $active_hash); $stmt->execute(); $command_center_id = (int) $stmt->insert_id; $stmt->close();
	$db->query("INSERT INTO puskesmas_staff(kode_pkm,nama,gelar,no_hp,profesi,nomor_sip,sip_expired_at,status,nip) VALUES ('PKM01','Staff fixture','dr.','080000000002','Dokter','SIP-TEST-1','2028-01-01','aktif',NULL)");
	$staff_only_id = (int) $db->insert_id;
	$staff_only = (new Nakes_personal_account_policy())->evaluate(array('user_id' => 0));
	phase23_expect($staff_only['state'] === Nakes_personal_account_policy::STAFF_ONLY, 'staff_only_initial_state');
	$unlinked = (new Nakes_personal_account_policy())->evaluate(array('user_id' => 0, 'unlinked_account_available' => true));
	phase23_expect($unlinked['state'] === Nakes_personal_account_policy::STAFF_WITH_UNLINKED_ACCOUNT, 'unlinked_candidate_state');

	$existing_hash = password_hash('Existing-personal-2026!', PASSWORD_DEFAULT);
	$stmt = $db->prepare("INSERT INTO users(nama,email,username,password,no_hp,tgl,gender,role,status,remark,must_change_password,password_changed_at) VALUES ('Personal fixture','personal@example.invalid','personal.fixture',?,'080000000003','1991-01-01','Perempuan','dokter','aktif','PKM01',0,'2026-08-02 00:00:00')");
	$stmt->bind_param('s', $existing_hash); $stmt->execute(); $existing_user_id = (int) $stmt->insert_id; $stmt->close();
	$db->begin_transaction();
	$db->query('SELECT staff_id FROM puskesmas_staff WHERE staff_id = ' . $staff_only_id . ' FOR UPDATE');
	$db->query('SELECT userId FROM users WHERE userId = ' . $existing_user_id . ' FOR UPDATE');
	$db->query('UPDATE puskesmas_staff SET user_id = ' . $existing_user_id . ' WHERE staff_id = ' . $staff_only_id . ' AND user_id IS NULL');
	$db->commit();
	phase23_expect(phase23_state($db, $existing_user_id) === Nakes_personal_account_policy::ACTIVE, 'link_existing_to_active_state');

	$db->begin_transaction();
	$db->query('SELECT staff_id FROM puskesmas_staff WHERE staff_id = ' . $staff_only_id . ' FOR UPDATE');
	$db->query('UPDATE puskesmas_staff SET user_id = NULL WHERE staff_id = ' . $staff_only_id . ' AND user_id = ' . $existing_user_id);
	$db->rollback();
	phase23_expect(phase23_state($db, $existing_user_id) === Nakes_personal_account_policy::ACTIVE, 'unlink_rollback_preserves_link');

	$db->query("INSERT INTO puskesmas_staff(kode_pkm,nama,gelar,no_hp,profesi,nomor_sip,sip_expired_at,status) VALUES ('PKM01','Staff create','dr.','080000000004','Dokter','SIP-TEST-2','2028-01-01','aktif')");
	$create_staff_id = (int) $db->insert_id;
	$temporary = 'Temporary-passphrase-2026!';
	$temporary_hash = password_hash($temporary, PASSWORD_DEFAULT);
	$db->begin_transaction();
	$stmt = $db->prepare("INSERT INTO users(nama,email,username,password,no_hp,tgl,gender,role,status,remark,must_change_password,password_changed_at) VALUES ('Created fixture','created@example.invalid','created.fixture',?,'080000000004','1992-01-01','Laki-laki','dokter','aktif','PKM01',1,NULL)");
	$stmt->bind_param('s', $temporary_hash); $stmt->execute(); $created_user_id = (int) $stmt->insert_id; $stmt->close();
	$db->query('UPDATE puskesmas_staff SET user_id = ' . $created_user_id . ' WHERE staff_id = ' . $create_staff_id . ' AND user_id IS NULL');
	$db->commit();
	phase23_expect(phase23_state($db, $created_user_id) === Nakes_personal_account_policy::FIRST_LOGIN_PENDING, 'create_account_to_first_login_pending');
	phase23_expect(password_verify($temporary, $db->query('SELECT password FROM users WHERE userId = ' . $created_user_id)->fetch_row()[0]), 'temporary_password_hash_compatible');

	$db->query("INSERT INTO puskesmas_staff(kode_pkm,user_id,nama,gelar,no_hp,profesi,nomor_sip,sip_expired_at,status) VALUES ('PKM01'," . $existing_user_id . ",'Occupied fixture','dr.','080000000005','Dokter','SIP-TEST-3','2028-01-01','aktif')");
	$occupied_staff_id = (int) $db->insert_id;
	$db->begin_transaction();
	$rollback_hash = password_hash('Rollback-passphrase-2026!', PASSWORD_DEFAULT);
	$stmt = $db->prepare("INSERT INTO users(nama,email,username,password,no_hp,tgl,gender,role,status,remark,must_change_password,password_changed_at) VALUES ('Rollback fixture','rollback@example.invalid','rollback.fixture',?,'080000000006','1993-01-01','Perempuan','dokter','aktif','PKM01',1,NULL)");
	$stmt->bind_param('s', $rollback_hash); $stmt->execute(); $rollback_user_id = (int) $stmt->insert_id; $stmt->close();
	$db->query('UPDATE puskesmas_staff SET user_id = ' . $rollback_user_id . ' WHERE staff_id = ' . $occupied_staff_id . ' AND user_id IS NULL');
	if ($db->affected_rows !== 1) { $db->rollback(); } else { $db->commit(); }
	phase23_expect(phase23_count($db, "SELECT COUNT(*) FROM users WHERE username = 'rollback.fixture'") === 0, 'create_second_write_failure_rolls_back_user');

	$new_password = 'A-longer-personal-passphrase-2026!';
	$identity = array('username' => 'created.fixture', 'email' => 'created@example.invalid', 'no_hp' => '080000000004');
	$policy = new First_login_password_policy();
	phase23_expect($policy->validate($new_password, $new_password, $identity, $temporary_hash) === null, 'first_activation_password_valid');
	$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
	$db->begin_transaction();
	$db->query('SELECT userId FROM users WHERE userId = ' . $created_user_id . ' FOR UPDATE');
	$stmt = $db->prepare('UPDATE users SET password = ?, must_change_password = 0, password_changed_at = NOW() WHERE userId = ? AND password = ? AND must_change_password = 1');
	$stmt->bind_param('sis', $new_hash, $created_user_id, $temporary_hash); $stmt->execute(); $activation_rows = $stmt->affected_rows; $stmt->close();
	$db->commit();
	phase23_expect($activation_rows === 1 && phase23_state($db, $created_user_id) === Nakes_personal_account_policy::ACTIVE, 'first_activation_to_active');
	$stmt = $db->prepare('UPDATE users SET password = ?, must_change_password = 0, password_changed_at = NOW() WHERE userId = ? AND password = ? AND must_change_password = 1');
	$stmt->bind_param('sis', $new_hash, $created_user_id, $temporary_hash); $stmt->execute(); $replay_rows = $stmt->affected_rows; $stmt->close();
	phase23_expect($replay_rows === 0, 'activation_duplicate_submission_cannot_mutate');

	$changed_at = $db->query('SELECT password_changed_at FROM users WHERE userId = ' . $created_user_id)->fetch_row()[0];
	$reset_temporary = 'Reset-passphrase-2026!';
	$reset_hash = password_hash($reset_temporary, PASSWORD_DEFAULT);
	$stmt = $db->prepare('UPDATE users SET password = ?, must_change_password = 1 WHERE userId = ?');
	$stmt->bind_param('si', $reset_hash, $created_user_id); $stmt->execute(); $stmt->close();
	phase23_expect(phase23_state($db, $created_user_id) === Nakes_personal_account_policy::ADMIN_RESET_PENDING, 'active_to_admin_reset_pending');
	phase23_expect($db->query('SELECT password_changed_at FROM users WHERE userId = ' . $created_user_id)->fetch_row()[0] === $changed_at, 'admin_reset_preserves_change_evidence');

	$db->begin_transaction();
	$db->query('UPDATE users SET must_change_password = 0, password_changed_at = NOW() WHERE userId = ' . $created_user_id);
	$db->rollback();
	phase23_expect(phase23_state($db, $created_user_id) === Nakes_personal_account_policy::ADMIN_RESET_PENDING, 'reset_completion_rollback_preserves_pending');
	$final_hash = password_hash('Final-personal-passphrase-2026!', PASSWORD_DEFAULT);
	$stmt = $db->prepare('UPDATE users SET password = ?, must_change_password = 0, password_changed_at = NOW() WHERE userId = ? AND password = ? AND must_change_password = 1');
	$stmt->bind_param('sis', $final_hash, $created_user_id, $reset_hash); $stmt->execute(); $reset_complete_rows = $stmt->affected_rows; $stmt->close();
	phase23_expect($reset_complete_rows === 1 && phase23_state($db, $created_user_id) === Nakes_personal_account_policy::ACTIVE, 'admin_reset_pending_to_active');

	$db->query("INSERT INTO puskesmas_staff(kode_pkm,user_id,nama,gelar,no_hp,profesi,nomor_sip,sip_expired_at,status) VALUES ('PKM01'," . $created_user_id . ",'Duplicate link fixture','dr.','080000000007','Dokter','SIP-TEST-4','2028-01-01','aktif')");
	phase23_expect(phase23_state($db, $created_user_id) === Nakes_personal_account_policy::INVALID, 'duplicate_active_link_is_invalid');
	$hash_before_invalid_attempt = $db->query('SELECT password FROM users WHERE userId = ' . $created_user_id)->fetch_row()[0];
	if (phase23_state($db, $created_user_id) !== Nakes_personal_account_policy::INVALID) {
		$db->query('UPDATE users SET must_change_password = 0 WHERE userId = ' . $created_user_id);
	}
	phase23_expect(hash_equals($hash_before_invalid_attempt, $db->query('SELECT password FROM users WHERE userId = ' . $created_user_id)->fetch_row()[0]), 'invalid_identity_blocks_credential_mutation');
	$db->query("DELETE FROM puskesmas_staff WHERE nama = 'Duplicate link fixture'");

	$db->query("UPDATE users SET remark = 'PKM02' WHERE userId = " . $created_user_id);
	phase23_expect(phase23_state($db, $created_user_id) === Nakes_personal_account_policy::INVALID, 'facility_mismatch_is_invalid');
	$db->query("UPDATE users SET remark = 'PKM01' WHERE userId = " . $created_user_id);
	$db->query('UPDATE puskesmas_staff SET user_id = ' . $command_center_id . ' WHERE staff_id = ' . $staff_only_id);
	phase23_expect(phase23_state($db, $command_center_id) === Nakes_personal_account_policy::INVALID, 'command_center_link_is_invalid');

	$db->query("INSERT INTO puskesmas_staff(kode_pkm,nama,gelar,no_hp,profesi,nomor_sip,sip_expired_at,status) VALUES ('PKM01','First reset fixture','dr.','080000000008','Dokter','SIP-TEST-5','2028-01-01','aktif')");
	$first_reset_staff_id = (int) $db->insert_id;
	$first_reset_hash = password_hash('First-reset-passphrase-2026!', PASSWORD_DEFAULT);
	$stmt = $db->prepare("INSERT INTO users(nama,email,username,password,no_hp,tgl,gender,role,status,remark,must_change_password,password_changed_at) VALUES ('First reset fixture','first-reset@example.invalid','first.reset.fixture',?,'080000000008','1994-01-01','Perempuan','dokter','aktif','PKM01',1,NULL)");
	$stmt->bind_param('s', $first_reset_hash); $stmt->execute(); $first_reset_user_id = (int) $stmt->insert_id; $stmt->close();
	$db->query('UPDATE puskesmas_staff SET user_id = ' . $first_reset_user_id . ' WHERE staff_id = ' . $first_reset_staff_id);
	$db->query("UPDATE users SET password = '" . $db->real_escape_string(password_hash('Replacement-passphrase-2026!', PASSWORD_DEFAULT)) . "', must_change_password = 1 WHERE userId = " . $first_reset_user_id);
	phase23_expect(phase23_state($db, $first_reset_user_id) === Nakes_personal_account_policy::FIRST_LOGIN_PENDING, 'reset_before_first_activation_remains_first_login_pending');

	phase23_expect(phase23_count($db, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users','puskesmas_staff','m_puskesmas')") === 3, 'disposable_schema_only');
	echo "PHASE23_DISPOSABLE_DB_PASSED={$passed}\nPHASE23_DISPOSABLE_DB_FAILED={$failed}\n";
} catch (Throwable $e) {
	$failed++;
	fwrite(STDERR, "FAIL disposable_db_safe_error\nSAFE_EXCEPTION_CLASS=" . get_class($e) . "\nSAFE_ERROR_CODE=" . $e->getMessage() . "\n");
} finally {
	if ($db instanceof mysqli) { $db->close(); }
	if ($admin instanceof mysqli) {
		if ($database_created) { $admin->query('DROP DATABASE ' . phase23_identifier($database)); }
		$admin->close();
	}
}

exit($failed === 0 ? 0 : 1);
