<?php
if (PHP_SAPI !== 'cli') { exit('No direct script access allowed'); }

const DOCLINC_ADMIN_CAPABILITY_MIGRATION_ID = '20260814000100_admin_capability_clinical_access_audit';

function admin_capability_true($value) { return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true); }
function admin_capability_fail($reason) { fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\nDATABASE_CONNECTION_OPENED=false\nDDL_EXECUTED=false\nDDL_STATEMENT_COUNT=0\n"); exit(1); }
function admin_capability_table($db,$schema,$table) { $s=$db->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');$s->bind_param('ss',$schema,$table);$s->execute();return $s->get_result()->fetch_assoc(); }
function admin_capability_column($db,$schema,$table,$column) { $s=$db->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');$s->bind_param('sss',$schema,$table,$column);$s->execute();return $s->get_result()->fetch_assoc(); }
function admin_capability_index($db,$schema,$table,$index) { $s=$db->prepare('SELECT NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? GROUP BY NON_UNIQUE');$s->bind_param('sss',$schema,$table,$index);$s->execute();return $s->get_result()->fetch_assoc(); }
function admin_capability_verify($db,$schema) { foreach(array('admin_user_capabilities','clinical_access_audit') as $table){$t=admin_capability_table($db,$schema,$table);if(!$t||strtolower($t['ENGINE'])!=='innodb'||strtolower($t['TABLE_COLLATION'])!=='utf8mb4_unicode_ci')throw new RuntimeException('target_schema_mismatch');} $checks=array(array('admin_user_capabilities','user_id','int(11)','NO'),array('admin_user_capabilities','capability_code','varchar(64)','NO'),array('admin_user_capabilities','granted_by_user_id','int(11)','NO'),array('admin_user_capabilities','granted_at','datetime','NO'),array('clinical_access_audit','access_id','bigint(20) unsigned','NO'),array('clinical_access_audit','actor_user_id','int(11)','NO'),array('clinical_access_audit','record_id','int(11)','NO'),array('clinical_access_audit','audit_session_hash','char(64)','NO'),array('clinical_access_audit','created_at','datetime','NO'));foreach($checks as $c){$v=admin_capability_column($db,$schema,$c[0],$c[1]);if(!$v||strtolower($v['COLUMN_TYPE'])!==$c[2]||strtoupper($v['IS_NULLABLE'])!==$c[3])throw new RuntimeException('target_schema_mismatch');} $idx=array(array('admin_user_capabilities','PRIMARY','user_id,capability_code',0),array('clinical_access_audit','PRIMARY','access_id',0),array('clinical_access_audit','idx_clinical_access_record_session','record_id,audit_session_hash',1));foreach($idx as $i){$v=admin_capability_index($db,$schema,$i[0],$i[1]);if(!$v||$v['columns_list']!==$i[2]||(int)$v['NON_UNIQUE']!==$i[3])throw new RuntimeException('target_schema_mismatch');}}

$options = getopt('', array('apply', 'environment:', 'confirm-database:', 'confirm-disposable-test:', 'backup-reference:', 'confirm-backup-sha256:'));
if (!array_key_exists('apply', $options)) {
	echo "MIGRATION_ID=" . DOCLINC_ADMIN_CAPABILITY_MIGRATION_ID . "\nEXECUTION_MODE=PLAN\nDATABASE_WRITE_EXECUTED=false\nPLANNED_TABLE_COUNT=2\nDESTRUCTIVE_STATEMENTS=0\n";
	exit(0);
}
if (!admin_capability_true(getenv('DOCLINC_ADMIN_SCHEMA_WRITE_ENABLED'))) admin_capability_fail('schema_write_disabled');
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
$database = trim((string) (getenv('DOCLINC_ADMIN_SCHEMA_DB_NAME') ?: ''));
if (!in_array($environment,array('test','staging'),true)) admin_capability_fail('environment_not_allowed');
if (!isset($options['confirm-database']) || !hash_equals($database,(string)$options['confirm-database'])) admin_capability_fail('database_confirmation_mismatch');
if ($environment === 'test' && (!admin_capability_true(getenv('DOCLINC_ADMIN_DISPOSABLE_TEST'))
	|| !isset($options['confirm-disposable-test']) || !hash_equals('true', strtolower((string) $options['confirm-disposable-test']))
	|| !preg_match('/\A(?:doclinc_admin_capability_test|doclinc_governance_test)_[a-z0-9_]+\z/', strtolower($database)))) {
	admin_capability_fail('disposable_test_database_required');
}
if ($environment === 'staging') { if ($database !== 'doclinc-staging' || admin_capability_true(getenv('DOCLINC_ADMIN_DISPOSABLE_TEST')) || !admin_capability_true(getenv('DOCLINC_ADMIN_SCHEMA_STAGING_WRITE_ENABLED'))) admin_capability_fail('staging_database_required'); $b=trim((string)($options['backup-reference']??''));$h=strtolower(trim((string)($options['confirm-backup-sha256']??'')));if($b===''||!is_readable($b)||!preg_match('/\A[a-f0-9]{64}\z/',$h)||!hash_equals($h,strtolower((string)hash_file('sha256',$b))))admin_capability_fail('backup_confirmation_mismatch'); }
$host = getenv('DOCLINC_ADMIN_SCHEMA_DB_HOST') ?: 'localhost';
$port = (int) (getenv('DOCLINC_ADMIN_SCHEMA_DB_PORT') ?: 3306);
$allowedHosts = array('localhost', '127.0.0.1', 'mariadb', 'db');
if (!in_array(strtolower(trim((string)$host)), $allowedHosts, true)) admin_capability_fail('database_host_not_disposable_allowlisted');
$user = trim((string) (getenv('DOCLINC_ADMIN_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_ADMIN_SCHEMA_DB_PASSWORD');
if ($user === '' || !is_string($password) || $password === '') admin_capability_fail('database_identity_missing');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$identity = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$identity || !hash_equals($database, (string) $identity['database_name'])) admin_capability_fail('database_identity_mismatch');
	$lock = $db->query("SELECT GET_LOCK('doclinc_admin_capabilities', 10) AS locked")->fetch_assoc();
	if (!$lock || (int)$lock['locked'] !== 1) admin_capability_fail('migration_lock_unavailable');
	$db->query("CREATE TABLE IF NOT EXISTS `admin_user_capabilities` (
		`user_id` INT(11) NOT NULL,
		`capability_code` VARCHAR(64) NOT NULL,
		`granted_by_user_id` INT(11) NOT NULL,
		`granted_at` DATETIME NOT NULL,
		PRIMARY KEY (`user_id`, `capability_code`),
		KEY `idx_admin_capability_code` (`capability_code`),
		KEY `idx_admin_capability_granted_by` (`granted_by_user_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$db->query("CREATE TABLE IF NOT EXISTS `clinical_access_audit` (
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
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$schema = $db->real_escape_string($database);
	$check = $db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA='{$schema}' AND TABLE_NAME IN ('admin_user_capabilities','clinical_access_audit')");
	$checkRow = $check ? $check->fetch_assoc() : array('total'=>0);
	if ((int)$checkRow['total'] !== 2) admin_capability_fail('post_apply_verification_failed');
	admin_capability_verify($db,$database);
	$db->query("SELECT RELEASE_LOCK('doclinc_admin_capabilities')");
	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDATABASE_WRITE_EXECUTED=true\nDESTRUCTIVE_STATEMENTS=0\n";
	$db->close();
} catch (Throwable $e) {
	admin_capability_fail('migration_failed');
}
