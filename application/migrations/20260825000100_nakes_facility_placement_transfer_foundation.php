<?php
if (PHP_SAPI !== 'cli') exit('No direct script access allowed');
ini_set('display_errors', '0');
ini_set('log_errors', '0');

const DOCLINC_NAKES_PLACEMENT_MIGRATION_ID = '20260825000100_nakes_facility_placement_transfer_foundation';
const DOCLINC_NAKES_PLACEMENT_LOCK = 'doclinc_nakes_placement_foundation';

function nakes_placement_true($name) { $value = getenv($name); return is_string($value) && in_array(strtolower(trim($value)), array('1','true','yes','on'), true); }
function nakes_placement_fail($code, $opened = false, $ddl = 0) { fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE={$code}\nDATABASE_CONNECTION_OPENED=" . ($opened ? 'true' : 'false') . "\nDDL_EXECUTED=" . ($ddl > 0 ? 'true' : 'false') . "\nDDL_STATEMENT_COUNT={$ddl}\n"); exit(1); }
function nakes_placement_table(mysqli $db, $database, $table) { $stmt=$db->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');$stmt->bind_param('ss',$database,$table);$stmt->execute();return $stmt->get_result()->fetch_assoc(); }
function nakes_placement_column(mysqli $db, $database, $table, $column) { $stmt=$db->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');$stmt->bind_param('sss',$database,$table,$column);$stmt->execute();return $stmt->get_result()->fetch_assoc(); }
function nakes_placement_index(mysqli $db,$database,$table,$index) { $s=$db->prepare('SELECT NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? GROUP BY NON_UNIQUE');$s->bind_param('sss',$database,$table,$index);$s->execute();return $s->get_result()->fetch_assoc(); }
function nakes_placement_verify(mysqli $db,$database) { foreach(array('nakes_facility_placements','nakes_facility_transfers') as $t){$v=nakes_placement_table($db,$database,$t);if(!$v||strtolower($v['ENGINE'])!=='innodb'||strtolower($v['TABLE_COLLATION'])!=='utf8mb4_unicode_ci')throw new RuntimeException('target_schema_mismatch');} $p=nakes_placement_column($db,$database,'nakes_facility_placements','active_staff_key');$s=nakes_placement_column($db,$database,'nakes_facility_transfers','scheduled_staff_key');$pe=strtolower(str_replace('`','',(string)($p['GENERATION_EXPRESSION']??'')));$se=strtolower(str_replace('`','',(string)($s['GENERATION_EXPRESSION']??'')));if(!$p||!$s||strtolower($p['EXTRA'])!=='stored generated' || strtolower($s['EXTRA'])!=='stored generated' || strpos(preg_replace('/\s+/','',$pe),"status='active'")===false || strpos(preg_replace('/\s+/','',$se),"status='scheduled'")===false)throw new RuntimeException('target_schema_mismatch');$i=nakes_placement_index($db,$database,'nakes_facility_placements','uq_nakes_active_staff');$j=nakes_placement_index($db,$database,'nakes_facility_transfers','uq_nakes_scheduled_transfer');if(!$i||$i['columns_list']!=='active_staff_key'||(int)$i['NON_UNIQUE']!==0||!$j||$j['columns_list']!=='scheduled_staff_key'||(int)$j['NON_UNIQUE']!==0)throw new RuntimeException('target_schema_mismatch'); }

$options = getopt('', array('apply','environment:','confirm-database:','confirm-disposable-test:','backup-reference:','confirm-backup-sha256:'));
if (!array_key_exists('apply', $options)) {
	echo 'MIGRATION_ID=' . DOCLINC_NAKES_PLACEMENT_MIGRATION_ID . "\nEXECUTION_MODE=PLAN\nDATABASE_CONNECTION_OPENED=false\nDDL_EXECUTED=false\nPLANNED_TABLE_COUNT=2\nEXISTING_ROWS_CHANGED=false\n";
	exit(0);
}
if (!nakes_placement_true('DOCLINC_NAKES_PLACEMENT_SCHEMA_WRITE_ENABLED')) nakes_placement_fail('schema_write_disabled');
$environment = strtolower(trim((string) ($options['environment'] ?? '')));
$database = trim((string) (getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_NAME') ?: ''));
if (!in_array($environment,array('test','staging'),true)) nakes_placement_fail('environment_not_allowed');
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string)$options['confirm-database'])) nakes_placement_fail('database_confirmation_mismatch');
if ($environment === 'test' && (!nakes_placement_true('DOCLINC_NAKES_PLACEMENT_DISPOSABLE_TEST') || !isset($options['confirm-disposable-test']) || !hash_equals('true', strtolower((string)$options['confirm-disposable-test'])) || preg_match('/\A(?:doclinc_nakes_placement_test|doclinc_governance_test)_[a-z0-9_]+\z/', strtolower($database)) !== 1)) nakes_placement_fail('disposable_test_database_required');
if ($environment === 'staging') { if ($database !== 'doclinc-staging' || nakes_placement_true('DOCLINC_NAKES_PLACEMENT_DISPOSABLE_TEST') || !nakes_placement_true('DOCLINC_NAKES_PLACEMENT_SCHEMA_STAGING_WRITE_ENABLED')) nakes_placement_fail('staging_database_required'); $b=trim((string)($options['backup-reference']??''));$h=strtolower(trim((string)($options['confirm-backup-sha256']??'')));if($b===''||!is_readable($b)||!preg_match('/\A[a-f0-9]{64}\z/',$h)||!hash_equals($h,strtolower((string)hash_file('sha256',$b))))nakes_placement_fail('backup_confirmation_mismatch'); }
if ($database === '' || !isset($options['confirm-database']) || !hash_equals($database, (string)$options['confirm-database'])) nakes_placement_fail('database_confirmation_mismatch');
$host = getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_PORT') ?: 3306);
$user = trim((string)(getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_USER') ?: ''));
$password = getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_PASSWORD');
$allowed = array_filter(array_map('trim', explode(',', (string)(getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_ALLOWED_USERS') ?: ''))));
if (!in_array(strtolower(trim((string)$host)), array('127.0.0.1','localhost','mariadb','db'), true) || $user === '' || !is_string($password) || $password === '' || !in_array($user, $allowed, true)) nakes_placement_fail('database_identity_not_allowed');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); $db=null; $lock=false; $ddl=0;
try {
	$db = new mysqli($host,$user,$password,$database,$port); $db->set_charset('utf8mb4');
	$actual=$db->query('SELECT DATABASE() AS database_name')->fetch_assoc(); if (!$actual || !hash_equals($database,(string)$actual['database_name'])) throw new RuntimeException('connected_database_mismatch');
	foreach(array('users','puskesmas_staff','m_puskesmas','requests') as $base){ if(!nakes_placement_table($db,$database,$base)) throw new RuntimeException('base_schema_mismatch'); }
	$lockRow=$db->query("SELECT GET_LOCK('" . DOCLINC_NAKES_PLACEMENT_LOCK . "',10) AS acquired")->fetch_assoc(); if(!$lockRow || (int)$lockRow['acquired']!==1) throw new RuntimeException('migration_lock_unavailable'); $lock=true;
	$existing = (nakes_placement_table($db,$database,'nakes_facility_placements') ? 1 : 0) + (nakes_placement_table($db,$database,'nakes_facility_transfers') ? 1 : 0);
	if ($existing === 1) throw new RuntimeException('partial_schema_detected');
	if ($existing === 0) {
		$db->query("CREATE TABLE nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, staff_id INT UNSIGNED NOT NULL, facility_code VARCHAR(64) NOT NULL, effective_from DATETIME NOT NULL, effective_until DATETIME NULL, status ENUM('active','ended') NOT NULL DEFAULT 'active', active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED, created_by_user_id INT NOT NULL, ended_by_user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (placement_id), UNIQUE KEY uq_nakes_active_staff (active_staff_key), KEY idx_nakes_placement_staff_status (staff_id,status,placement_id), KEY idx_nakes_placement_facility_status (facility_code,status,staff_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); $ddl++;
		$db->query("CREATE TABLE nakes_facility_transfers (transfer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, staff_id INT UNSIGNED NOT NULL, from_placement_id BIGINT UNSIGNED NOT NULL, destination_facility_code VARCHAR(64) NOT NULL, effective_at DATETIME NOT NULL, reason VARCHAR(500) NOT NULL, status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled', scheduled_staff_key INT UNSIGNED AS (CASE WHEN status='scheduled' THEN staff_id ELSE NULL END) STORED, requested_by_user_id INT NOT NULL, requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, completed_by_user_id INT NULL, completed_at DATETIME NULL, cancelled_by_user_id INT NULL, cancelled_at DATETIME NULL, last_block_reason VARCHAR(500) NULL, PRIMARY KEY (transfer_id), UNIQUE KEY uq_nakes_scheduled_transfer (scheduled_staff_key), KEY idx_nakes_transfer_effective (status,effective_at,staff_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); $ddl++;
	}
	nakes_placement_verify($db,$database);
	echo "MIGRATION_RESULT=PASS\nEXECUTION_MODE=APPLY\nDDL_EXECUTED=" . ($ddl > 0 ? 'true' : 'false') . "\nDDL_STATEMENT_COUNT={$ddl}\nALREADY_APPLIED=" . ($ddl === 0 ? 'true' : 'false') . "\nEXISTING_ROWS_CHANGED=false\n";
} catch(Throwable $e) { if($lock && $db) $db->query("SELECT RELEASE_LOCK('" . DOCLINC_NAKES_PLACEMENT_LOCK . "')"); if($db) $db->close(); $safe=array('connected_database_mismatch','base_schema_mismatch','migration_lock_unavailable','partial_schema_detected','target_schema_mismatch','disposable_test_database_required','database_confirmation_mismatch'); nakes_placement_fail(in_array($e->getMessage(),$safe,true)?$e->getMessage():'migration_failed',$db instanceof mysqli,$ddl); }
if($lock && $db) $db->query("SELECT RELEASE_LOCK('" . DOCLINC_NAKES_PLACEMENT_LOCK . "')"); if($db) $db->close();
