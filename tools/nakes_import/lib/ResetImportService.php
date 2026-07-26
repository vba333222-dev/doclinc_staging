<?php

require_once __DIR__ . '/NakesImportException.php';
require_once __DIR__ . '/NakesSource.php';

class DoclincResetImportService
{
	private const REAL_CODES = array('10280201', '10280101', '10280501', '2241001', '10280301', '10280402', '10280701', '10280401', '10280601');
	private const OPTIONAL_COMPATIBILITY_TABLES = array('locations', 'rating', 'puskesmas');
	private const PRE_COUNTS = array(
		'admin_notification_reads' => 0, 'audit_logs' => 354, 'call_sessions' => 12,
		'consultation_messages' => 39, 'feeds' => 3, 'konsultasi' => 18,
		'medicalrecords' => 19, 'm_dokter' => 8, 'm_puskesmas' => 10,
		'notifications' => 104, 'puskesmas_staff' => 8, 'requests' => 37,
		'request_events' => 49, 'request_staff_assignments' => 14, 'terapi' => 11, 'users' => 27,
	);
	private $db;
	private $database;
	private $databaseUser;
	private $disposable;
	private $lockAcquired = false;

	public function __construct(mysqli $db, $database, $disposable = false, $databaseUser = '')
	{
		$this->db = $db;
		$this->database = $database;
		$this->databaseUser = (string) $databaseUser;
		$this->disposable = $disposable;
	}

	public static function connectFromEnvironment()
	{
		$database = trim((string) (getenv('DOCLINC_NAKES_RESET_DB_NAME') ?: ''));
		$user = trim((string) (getenv('DOCLINC_NAKES_RESET_DB_USER') ?: ''));
		$password = getenv('DOCLINC_NAKES_RESET_DB_PASSWORD');
		$allowed = array_filter(array_map('trim', explode(',', (string) (getenv('DOCLINC_NAKES_RESET_ALLOWED_USERS') ?: ''))));
		if ($database === '' || $user === '' || !is_string($password) || $password === '' || !in_array($user, $allowed, true)) {
			throw new NakesImportException('database_identity_not_allowed');
		}
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		try {
			$db = new mysqli(
				getenv('DOCLINC_NAKES_RESET_DB_HOST') ?: 'localhost',
				$user,
				$password,
				$database,
				(int) (getenv('DOCLINC_NAKES_RESET_DB_PORT') ?: 3306)
			);
			$db->set_charset('utf8mb4');
		} catch (Throwable $exception) {
			throw new NakesImportException('database_connection_failed');
		} finally {
			$password = null;
		}
		$actual = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
		if (!$actual || !hash_equals($database, (string) $actual['database_name'])) {
			$db->close();
			throw new NakesImportException('connected_database_mismatch');
		}
		$disposable = self::envTrue('DOCLINC_NAKES_DISPOSABLE_TEST');
		if ((!$disposable && $database !== 'doclinc-staging') || ($disposable && preg_match('/(?:^|[_-])test(?:$|[_-])/', strtolower($database)) !== 1)) {
			$db->close();
			throw new NakesImportException($disposable ? 'disposable_test_database_required' : 'staging_database_required');
		}
		return new self($db, $database, $disposable, $user);
	}

	public function close()
	{
		if ($this->lockAcquired) $this->releaseLock();
		$this->db->close();
	}

	public function plan(array $source)
	{
		$this->assertPrivileges();
		$this->db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
		$this->db->query('SET TRANSACTION READ ONLY');
		$this->db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
		try {
			$state = $this->validateState($source);
			$this->db->rollback();
			return $this->publicPlan($source, $state);
		} catch (Throwable $exception) {
			$this->db->rollback();
			throw $exception;
		}
	}

	public function apply(array $source, array $confirmation, $sourcePath)
	{
		$this->assertApplyConfirmation($confirmation);
		$this->assertPrivileges();
		$this->acquireLock();
		$transactionStarted=false;
		try {
			$this->assertPrivileges();
			$source=(new DoclincNakesSource())->inspect($sourcePath,true);
			if(count($source['records'])!==DoclincNakesSource::TOTAL)throw new NakesImportException('source_record_count_mismatch');
			$this->db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
			$this->db->begin_transaction();$transactionStarted=true;
			$this->lockCriticalRows();
			$state = $this->validateState($source, $confirmation['actor_admin_user_id']);
			$this->executeReset($state);
			$this->insertNakes($source['records'], $state, $confirmation['actor_admin_user_id']);
			$post = $this->validatePostState($source, $state, (int) $confirmation['actor_admin_user_id']);
			$this->db->commit();$transactionStarted=false;
			return $post;
		} catch (Throwable $exception) {
			if($transactionStarted)$this->db->rollback();
			throw $exception;
		} finally {
			$this->releaseLock();
		}
	}

	private function validateState(array $source, $actorAdminId = null)
	{
		$optionalTables = $this->assertSchema();
		$this->assertPreCounts();
		$this->assertRelationshipInventory();
		$command = $this->canonicalCommandCenters();
		$commandIds = array_map('intval', array_column($command, 'userId'));
		$deletedIds = $this->deletedUserIds($commandIds);
		if ($actorAdminId !== null) $this->assertAdminActor($actorAdminId);
		$reserved = $this->reservedLogins($commandIds);
		$prepared = $this->prepareLogins($source['records'], $reserved);
		return array(
			'command_centers' => $command,
			'command_center_ids' => $commandIds,
			'deleted_user_ids' => $deletedIds,
			'prepared_logins' => $prepared,
			'preserved_users' => $this->snapshotRows('users', 'role = \'admin\' OR userId IN (' . implode(',', $commandIds) . ')', 'userId'),
			'preserved_puskesmas' => $this->snapshotRows('m_puskesmas', "UPPER(TRIM(kode_pkm)) <> 'DEFAULT'", 'kode_pkm'),
			'preserved_feeds' => $this->snapshotRows('feeds', '1=1', 'feedId'),
			'clinical_counts' => $this->clinicalCounts(),
			'upload_reference_count' => $this->uploadReferenceCount($deletedIds),
			'max_command_center_id' => max($commandIds),
			'optional_tables' => $optionalTables,
		);
	}

	private function assertSchema()
	{
		$required = array_keys(self::PRE_COUNTS);
		$required = array_merge($required, array('clinical_suggestion_import_batches', 'clinical_suggestion_terms', 'clinical_suggestion_aliases', 'clinical_schema_migrations'));
		foreach ($required as $table) {
			$row = $this->table($table);
			if (!$row || strtoupper((string) $row['ENGINE']) !== 'INNODB') throw new NakesImportException('required_innodb_table_missing');
		}
		$optionalTables = $this->optionalCompatibilityTables();
		foreach($optionalTables as $row)if($row&&strtoupper((string)$row['ENGINE'])!=='INNODB')throw new NakesImportException('required_innodb_table_missing');
		if($optionalTables['locations']&&!$this->column('locations','id_user'))throw new NakesImportException('required_schema_column_missing');
		$columns = array(
			'users' => array('userId','nama','email','username','password','role','status','remark','no_hp','must_change_password','password_changed_at'),
			'puskesmas_staff' => array('staff_id','kode_pkm','nama','no_hp','profesi','penugasan','nomor_sip','user_id','status','created_at','updated_at','created_by_user_id'),
			'm_puskesmas' => array('kode_pkm','status'),
			'requests' => array('request_id','user_id','dokter_id','request_status'),
			'audit_logs' => array('id','actor_user_id','action','entity_type','entity_id','metadata_json','created_at'),
			'notifications' => array('notification_id','recipient_user_id','recipient_puskesmas_code','actor_user_id','entity_type','entity_id'),
		);
		foreach ($columns as $table => $names) foreach ($names as $name) if (!$this->column($table, $name)) throw new NakesImportException('required_schema_column_missing');
		$signatures = array(
			array('users','must_change_password','tinyint(1)','NO','0'),
			array('users','password_changed_at','datetime','YES',null),
			array('puskesmas_staff','penugasan','varchar(150)','YES',null),
		);
		foreach ($signatures as $expected) {
			$actual = $this->column($expected[0], $expected[1]);
			if (strtolower((string) $actual['COLUMN_TYPE']) !== $expected[2] || (string) $actual['IS_NULLABLE'] !== $expected[3]
				|| !($expected[4] === null ? ($actual['COLUMN_DEFAULT'] === null || strtoupper((string) $actual['COLUMN_DEFAULT']) === 'NULL') : (string) $actual['COLUMN_DEFAULT'] === $expected[4])) {
				throw new NakesImportException('foundation_schema_signature_mismatch');
			}
		}
		return $optionalTables;
	}

	private function assertPreCounts()
	{
		if ($this->disposable && self::envTrue('DOCLINC_NAKES_RELAX_PRECOUNTS_FOR_NEGATIVE_TEST')) return;
		foreach (self::PRE_COUNTS as $table => $expected) {
			if ($this->count($table) !== $expected) throw new NakesImportException('pre_reset_count_mismatch');
		}
		$roles = $this->db->query("SELECT role,status,COUNT(*) AS total FROM users GROUP BY role,status")->fetch_all(MYSQLI_ASSOC);
		$map = array(); foreach ($roles as $row) $map[$row['role'] . ':' . $row['status']] = (int) $row['total'];
		if (($map['admin:aktif'] ?? 0) !== 1 || ($map['dokter:aktif'] ?? 0) !== 11 || ($map['dokter:nonaktif'] ?? 0) !== 4 || ($map['warga:aktif'] ?? 0) !== 11) throw new NakesImportException('pre_reset_user_state_mismatch');
		if((int)$this->db->query("SELECT COUNT(*) total FROM users WHERE role NOT IN ('warga','dokter') AND UPPER(TRIM(COALESCE(remark,'')))='DEFAULT'")->fetch_assoc()['total']!==0)throw new NakesImportException('default_dependency_unreviewed');
		$clinical = $this->clinicalCounts();
		if ($clinical !== array('batches'=>1,'terms'=>11450,'aliases'=>701,'applied_migrations'=>6)) throw new NakesImportException('clinical_preservation_baseline_mismatch');
	}

	private function canonicalCommandCenters()
	{
		$codes = $this->db->query("SELECT kode_pkm FROM m_puskesmas WHERE status='aktif' AND UPPER(TRIM(kode_pkm)) <> 'DEFAULT' ORDER BY kode_pkm")->fetch_all(MYSQLI_ASSOC);
		$actualCodes = array_column($codes, 'kode_pkm'); sort($actualCodes); $expectedCodes = self::REAL_CODES; sort($expectedCodes);
		if ($actualCodes !== $expectedCodes || count($actualCodes) !== 9) throw new NakesImportException('puskesmas_allowlist_mismatch');
		$rows = $this->db->query("SELECT u.* FROM users u INNER JOIN (SELECT TRIM(remark) kode_pkm,MIN(userId) userId FROM users WHERE role='dokter' AND status='aktif' AND TRIM(COALESCE(remark,'')) <> '' AND UPPER(TRIM(remark)) <> 'DEFAULT' GROUP BY TRIM(remark)) canonical ON canonical.userId=u.userId ORDER BY u.remark")->fetch_all(MYSQLI_ASSOC);
		if (count($rows) !== 9) throw new NakesImportException('command_center_count_mismatch');
		$seen = array();
		foreach ($rows as $row) {
			$code = trim((string) $row['remark']);
			if ($row['role'] !== 'dokter' || $row['status'] !== 'aktif' || !in_array($code, self::REAL_CODES, true) || isset($seen[$code])) throw new NakesImportException('command_center_classification_mismatch');
			$stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM puskesmas_staff WHERE user_id=?');
			$id = (int) $row['userId']; $stmt->bind_param('i', $id); $stmt->execute(); $link = $stmt->get_result()->fetch_assoc(); $stmt->close();
			if ((int) $link['total'] !== 0) throw new NakesImportException('command_center_staff_collision');
			$seen[$code] = true;
		}
		return $rows;
	}

	private function deletedUserIds(array $commandIds)
	{
		$sql = "SELECT userId FROM users WHERE role='warga' OR (role='dokter' AND userId NOT IN (" . implode(',', $commandIds) . ')) ORDER BY userId';
		return array_map('intval', array_column($this->db->query($sql)->fetch_all(MYSQLI_ASSOC), 'userId'));
	}

	private function prepareLogins(array $records, array $reserved)
	{
		$source = new DoclincNakesSource();
		$result = array();
		foreach ($records as $record) {
			$username = $source->username($record['nama'], $record['kode_pkm'], $record['no_hp'], $reserved);
			$email = $username . '@staging.doclinc.local';
			if (strlen($username) > 100 || strlen($email) > 100 || isset($reserved[strtolower($email)])) throw new NakesImportException('generated_login_collision');
			$reserved[strtolower($username)] = true; $reserved[strtolower($email)] = true;
			$result[] = array('username' => $username, 'email' => $email);
		}
		return $result;
	}

	private function executeReset(array $state)
	{
		$this->db->query('DELETE FROM audit_logs');
		$this->db->query('DELETE FROM admin_notification_reads');
		$this->db->query('DELETE FROM call_sessions');
		$this->db->query('DELETE FROM consultation_messages');
		$this->db->query('DELETE FROM notifications');
		$this->db->query('DELETE FROM request_events');
		$this->db->query('DELETE FROM request_staff_assignments');
		if ($state['optional_tables']['rating']) $this->db->query('DELETE FROM rating');
		$this->db->query('DELETE FROM terapi');
		$this->db->query('DELETE FROM konsultasi');
		$this->db->query('DELETE FROM medicalrecords');
		$this->db->query('DELETE FROM requests');
		if ($state['optional_tables']['locations']) $this->deleteIds('locations', 'id_user', $state['deleted_user_ids']);
		$this->db->query('DELETE FROM puskesmas_staff');
		$this->db->query('DELETE FROM m_dokter');
		$this->deleteIds('users', 'userId', $state['deleted_user_ids']);
		if ($state['optional_tables']['puskesmas'] && $this->column('puskesmas', 'kode_pkm')) $this->db->query("DELETE FROM puskesmas WHERE UPPER(TRIM(kode_pkm))='DEFAULT'");
		$this->db->query("DELETE FROM m_puskesmas WHERE UPPER(TRIM(kode_pkm))='DEFAULT'");
	}

	private function insertNakes(array $records, array $state, $actorAdminId)
	{
		$userSql = 'INSERT INTO users (nama,email,username,password,role,status,remark,no_hp,must_change_password,password_changed_at,created_at,updated_at) VALUES (?,?,?,?,\'dokter\',\'aktif\',?,?,1,NULL,NOW(),NOW())';
		$userStmt = $this->db->prepare($userSql);
		$staffFields = array('kode_pkm','user_id','nama','no_hp','profesi','penugasan','nomor_sip','status','created_at','updated_at','created_by_user_id');
		$staffValues = '?,?,?,?,?,?,NULL,\'aktif\',NOW(),NOW(),?';
		if ($this->column('puskesmas_staff', 'updated_by_user_id')) { $staffFields[] = 'updated_by_user_id'; $staffValues .= ',?'; }
		$staffStmt = $this->db->prepare('INSERT INTO puskesmas_staff (`' . implode('`,`', $staffFields) . '`) VALUES (' . $staffValues . ')');
		foreach ($records as $index => $record) {
			$login = $state['prepared_logins'][$index];
			$localPassword = '0' . substr($record['no_hp'], 3);
			$hash = password_hash($localPassword, PASSWORD_DEFAULT);
			if (!is_string($hash) || !password_verify($localPassword, $hash) || password_verify($record['no_hp'], $hash)) throw new NakesImportException('password_hash_contract_failed');
			$recordName = $record['nama']; $loginEmail = $login['email']; $loginUsername = $login['username']; $code = $record['kode_pkm']; $phone = $record['no_hp'];
			$userStmt->bind_param('ssssss', $recordName, $loginEmail, $loginUsername, $hash, $code, $phone);
			$userStmt->execute();
			$userId = (int) $this->db->insert_id;
			if ($userId <= $state['max_command_center_id']) throw new NakesImportException('imported_user_id_order_failed');
			$profession = $record['profesi']; $assignment = $record['penugasan'];
			if (count($staffFields) === 12) {
				$staffStmt->bind_param('sissssii', $code, $userId, $recordName, $phone, $profession, $assignment, $actorAdminId, $actorAdminId);
			} else {
				$staffStmt->bind_param('sissssi', $code, $userId, $recordName, $phone, $profession, $assignment, $actorAdminId);
			}
			$staffStmt->execute();
			$localPassword = null; $hash = null;
		}
		$userStmt->close(); $staffStmt->close();
	}

	private function validatePostState(array $source, array $state, $actorAdminId)
	{
		$expectedZero = array('request_staff_assignments','requests','medicalrecords','konsultasi','terapi','consultation_messages','call_sessions','request_events','m_dokter','admin_notification_reads','audit_logs');
		foreach ($expectedZero as $table) if ($this->count($table) !== 0) throw new NakesImportException('post_reset_zero_state_failed');
		if ($this->count('m_puskesmas') !== 9 || (int) $this->db->query("SELECT COUNT(*) total FROM m_puskesmas WHERE UPPER(TRIM(kode_pkm))='DEFAULT'")->fetch_assoc()['total'] !== 0) throw new NakesImportException('post_puskesmas_state_failed');
		if($state['optional_tables']['puskesmas']&&$this->column('puskesmas','kode_pkm')&&(int)$this->db->query("SELECT COUNT(*) total FROM puskesmas WHERE UPPER(TRIM(kode_pkm))='DEFAULT'")->fetch_assoc()['total']!==0)throw new NakesImportException('post_default_dependency_failed');
		if($state['optional_tables']['rating']&&$this->count('rating')!==0)throw new NakesImportException('post_dummy_dependency_failed');
		if($state['optional_tables']['locations']&&$state['deleted_user_ids']){$deleted=implode(',',array_map('intval',$state['deleted_user_ids']));if((int)$this->db->query("SELECT COUNT(*) total FROM locations WHERE id_user IN ($deleted)")->fetch_assoc()['total']!==0)throw new NakesImportException('post_dummy_dependency_failed');}
		$roles = $this->db->query("SELECT role,status,COUNT(*) total FROM users GROUP BY role,status")->fetch_all(MYSQLI_ASSOC); $map=array(); foreach($roles as $r)$map[$r['role'].':'.$r['status']]=(int)$r['total'];
		if (($map['admin:aktif']??0)!==1 || ($map['dokter:aktif']??0)!==35 || $this->count('users')!==36
			||(int)$this->db->query("SELECT COUNT(*) total FROM users WHERE role='warga' OR (role='dokter' AND status<>'aktif')")->fetch_assoc()['total']!==0||$this->count('puskesmas_staff')!==26) throw new NakesImportException('post_user_state_failed');
		$distribution = array(); foreach($this->db->query('SELECT kode_pkm,COUNT(*) total FROM puskesmas_staff GROUP BY kode_pkm')->fetch_all(MYSQLI_ASSOC) as $row)$distribution[$row['kode_pkm']] = (int)$row['total'];
		$expected = array_count_values(array_column($source['records'],'kode_pkm')); ksort($distribution); ksort($expected); if($distribution!==$expected) throw new NakesImportException('post_distribution_failed');
		if ($this->snapshotRows('users', 'role = \'admin\' OR userId IN (' . implode(',', $state['command_center_ids']) . ')', 'userId') !== $state['preserved_users']) throw new NakesImportException('preserved_user_changed');
		if ($this->snapshotRows('m_puskesmas', '1=1', 'kode_pkm') !== $state['preserved_puskesmas'] || $this->snapshotRows('feeds','1=1','feedId') !== $state['preserved_feeds']) throw new NakesImportException('preserved_master_changed');
		if ($this->clinicalCounts() !== $state['clinical_counts']) throw new NakesImportException('clinical_data_changed');
		$personal = $this->db->query("SELECT u.userId,u.nama,u.email,u.username,u.no_hp,u.password,u.must_change_password,u.password_changed_at,u.status AS user_status,ps.user_id,ps.kode_pkm,ps.nama AS staff_nama,ps.no_hp AS staff_no_hp,ps.profesi,ps.penugasan,ps.nomor_sip,ps.status AS staff_status,ps.created_by_user_id FROM users u JOIN puskesmas_staff ps ON ps.user_id=u.userId WHERE u.role='dokter' ORDER BY u.userId")->fetch_all(MYSQLI_ASSOC);
		if (count($personal)!==26) throw new NakesImportException('personal_identity_state_failed');
		foreach($personal as $index=>$row){
			$record=$source['records'][$index];$login=$state['prepared_logins'][$index];$local='0'.substr($record['no_hp'],3);
			if($row['nama']!==$record['nama']||$row['email']!==$login['email']||$row['username']!==$login['username']||$row['no_hp']!==$record['no_hp']
				||!password_verify($local,$row['password'])||password_verify($row['no_hp'],$row['password'])||(int)$row['must_change_password']!==1||$row['password_changed_at']!==null
				||$row['user_status']!=='aktif'||(int)$row['userId']!==(int)$row['user_id']||$row['kode_pkm']!==$record['kode_pkm']||$row['staff_nama']!==$record['nama']
				||$row['staff_no_hp']!==$record['no_hp']||$row['profesi']!=='Dokter'||$row['penugasan']!==$record['penugasan']||$row['nomor_sip']!==null
				||$row['staff_status']!=='aktif'||(int)$row['created_by_user_id']!==$actorAdminId) throw new NakesImportException('imported_account_contract_failed');
			$local=null;
		}
		if(count($this->canonicalCommandCenters())!==9)throw new NakesImportException('post_command_center_state_failed');
		if($this->count('notifications')!==0)throw new NakesImportException('post_notification_state_failed');
		return array(
			'imported_nakes'=>26,'personal_nakes'=>26,'active_doctor_users'=>35,'active_warga_users'=>0,'puskesmas_staff'=>26,
			'preserved_command_centers'=>9,'preserved_puskesmas'=>9,'default_puskesmas'=>0,'m_dokter'=>0,
			'requests'=>0,'medicalrecords'=>0,'konsultasi'=>0,'terapi'=>0,'consultation_messages'=>0,'call_sessions'=>0,
			'request_events'=>0,'request_staff_assignments'=>0,'transaction_notifications'=>0,'feeds'=>3,'clinical_suggestion_batches'=>1,
			'audit_logs'=>0,
			'legacy_default_dependencies'=>0,'dummy_ratings'=>0,'deleted_user_locations'=>0,
			'clinical_suggestion_terms'=>11450,'clinical_suggestion_aliases'=>701,'clinical_applied_migrations'=>6,
			'staff_distribution'=>$distribution,
			'upload_orphan_candidates'=>$state['upload_reference_count'],'metadata_written'=>false
		);
	}

	private function publicPlan(array $source,array $state)
	{
		return array('execution_mode'=>'plan','write_executed'=>false,'source_record_count'=>count($source['records']),'sheet_counts'=>$source['sheet_counts'],'source_inference_count'=>$source['source_inference_count'],'nullable_assignment_count'=>$source['nullable_assignment_count'],'assignment_normalization_count'=>$source['assignment_normalization_count'],'preserved_puskesmas_count'=>9,'preserved_command_center_count'=>9,'deleted_user_count'=>count($state['deleted_user_ids']),'audit_rows_planned_for_delete'=>self::PRE_COUNTS['audit_logs'],'upload_orphan_candidates'=>$state['upload_reference_count'],'m_dokter_post_count'=>0);
	}

	private function assertApplyConfirmation(array $c)
	{
		if (!self::envTrue('DOCLINC_NAKES_RESET_WRITE_ENABLED')) throw new NakesImportException('reset_write_disabled');
		$environment=strtolower(trim((string)(getenv('DOCLINC_NAKES_RESET_ENVIRONMENT')?:''))); if(!in_array($environment,array('staging','uat'),true))throw new NakesImportException('environment_not_allowed');
		$required=array('confirm_database','confirm_source_sha256','confirm_source_record_count','confirm_preserved_puskesmas_count','confirm_preserved_command_center_count','confirm_backup_sha256','reset_reference','actor_admin_user_id');foreach($required as $key)if(!isset($c[$key])||$c[$key]==='')throw new NakesImportException('apply_confirmation_missing');
		if(!hash_equals($this->database,(string)$c['confirm_database'])||!hash_equals(DoclincNakesSource::SHA256,strtolower((string)$c['confirm_source_sha256']))||(int)$c['confirm_source_record_count']!==26||(int)$c['confirm_preserved_puskesmas_count']!==9||(int)$c['confirm_preserved_command_center_count']!==9)throw new NakesImportException('apply_confirmation_mismatch');
		if(!$this->disposable&&!hash_equals('0a383da6019e4e8f2a217f79cd601aa004783fdd6d16803b459ad0f570102bfa',strtolower((string)$c['confirm_backup_sha256'])))throw new NakesImportException('backup_checksum_confirmation_mismatch');
		if(preg_match('/^uat-nakes-reset-[0-9]{8}T[0-9]{6}Z-[a-z0-9]{6,32}$/',(string)$c['reset_reference'])!==1)throw new NakesImportException('reset_reference_invalid');
		if((int)$c['actor_admin_user_id']<1)throw new NakesImportException('admin_actor_invalid');
	}

	private function assertPrivileges()
	{
		if ($this->disposable && self::envTrue('DOCLINC_NAKES_SKIP_GRANT_CHECK_FOR_TEST')) return;
		$identity=(string)$this->db->query('SELECT CURRENT_USER() identity')->fetch_assoc()['identity'];$identityUser=explode('@',$identity,2)[0]??'';if($this->databaseUser===''||!hash_equals($this->databaseUser,$identityUser))throw new NakesImportException('database_identity_not_allowed');
		$selectTables=array_merge(array_keys(self::PRE_COUNTS),array('clinical_suggestion_import_batches','clinical_suggestion_terms','clinical_suggestion_aliases','clinical_schema_migrations'));
		$deleteTables=array('audit_logs','admin_notification_reads','call_sessions','consultation_messages','notifications','request_events','request_staff_assignments','medicalrecords','terapi','konsultasi','requests','puskesmas_staff','m_dokter','users','m_puskesmas');
		$insertTables=array('users','puskesmas_staff');$allowed=array();$seen=array();
		foreach($selectTables as $table)$allowed[$table]['SELECT']=true;
		foreach($deleteTables as $table)$allowed[$table]['DELETE']=true;
		foreach($insertTables as $table)$allowed[$table]['INSERT']=true;
		foreach($this->optionalCompatibilityTables()as $table=>$row)if($row){$allowed[$table]['SELECT']=true;$allowed[$table]['DELETE']=true;$selectTables[]=$table;$deleteTables[]=$table;}
		$result=$this->db->query('SHOW GRANTS FOR CURRENT_USER()');foreach($result->fetch_all(MYSQLI_NUM) as $row){$grant=$row[0];if(preg_match('/^GRANT USAGE ON \*\.\*/i',$grant)===1)continue;if(preg_match('/^GRANT (.+) ON `?([^`. ]+)`?\.`?([^` ]+)`? TO /i',$grant,$m)!==1)throw new NakesImportException('writer_grants_unreviewed');if($m[2]!==$this->database||$m[3]==='*'||!isset($allowed[$m[3]])||stripos($grant,'GRANT OPTION')!==false)throw new NakesImportException('writer_grants_excessive');foreach(array_map('trim',explode(',',$m[1]))as $privilege){$privilege=strtoupper($privilege);if(empty($allowed[$m[3]][$privilege]))throw new NakesImportException('writer_grants_excessive');$seen[$m[3]][$privilege]=true;}}
		foreach($selectTables as $table)if(empty($seen[$table]['SELECT']))throw new NakesImportException('writer_grants_incomplete');
		foreach($deleteTables as $table)if(empty($seen[$table]['DELETE']))throw new NakesImportException('writer_grants_incomplete');
		foreach($insertTables as $table)if(empty($seen[$table]['INSERT']))throw new NakesImportException('writer_grants_incomplete');
	}

	private function assertRelationshipInventory()
	{
		$identityColumns=array('user_id','dokter_id','request_id','staff_id','konsul_id','actor_user_id','recipient_user_id','sender_user_id','caller_user_id','callee_user_id','id_user','assigned_by_user_id','created_by_user_id','updated_by_user_id');
		$known=array_fill_keys(array_merge(array_keys(self::PRE_COUNTS),self::OPTIONAL_COMPATIBILITY_TABLES),true);
		$stmt=$this->db->prepare('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');$stmt->execute();foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC)as $row){if(in_array(strtolower($row['COLUMN_NAME']),$identityColumns,true)&&!isset($known[$row['TABLE_NAME']])&&strpos($row['TABLE_NAME'],'clinical_')!==0)throw new NakesImportException('unreviewed_identity_relationship');}$stmt->close();
		$knownCodeLinks=array('m_puskesmas:kode_pkm'=>true,'puskesmas:kode_pkm'=>true,'puskesmas_staff:kode_pkm'=>true,'request_staff_assignments:kode_pkm'=>true,'requests:assigned_puskesmas_code'=>true,'notifications:recipient_puskesmas_code'=>true,'request_events:puskesmas_code'=>true,'m_dokter:kode_pkm'=>true);$stmt=$this->db->prepare("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('kode_pkm','puskesmas_code','assigned_puskesmas_code','recipient_puskesmas_code')");$stmt->execute();foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC)as $row)if(!isset($knownCodeLinks[$row['TABLE_NAME'].':'.$row['COLUMN_NAME']]))throw new NakesImportException('unreviewed_puskesmas_relationship');$stmt->close();
	}

	private function assertAdminActor($id){$stmt=$this->db->prepare("SELECT COUNT(*) total FROM users WHERE userId=? AND role='admin' AND status='aktif'");$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if((int)$row['total']!==1)throw new NakesImportException('admin_actor_invalid');}
	private function reservedLogins(array $commandIds){$ids=implode(',',$commandIds);$rows=$this->db->query("SELECT username,email FROM users WHERE role='admin' OR userId IN ($ids)")->fetch_all(MYSQLI_ASSOC);$set=array();foreach($rows as $row){foreach(array('username','email')as $f)if(trim((string)$row[$f])!=='')$set[strtolower(trim($row[$f]))]=true;}return $set;}
	private function lockCriticalRows(){$this->db->query('SELECT userId FROM users ORDER BY userId FOR UPDATE');$this->db->query('SELECT kode_pkm FROM m_puskesmas ORDER BY kode_pkm FOR UPDATE');$this->db->query('SELECT id FROM audit_logs ORDER BY id FOR UPDATE');}
	private function acquireLock(){$name='doclinc_staging_data_reset_nakes_import';$stmt=$this->db->prepare('SELECT GET_LOCK(?,10) acquired');$stmt->bind_param('s',$name);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$row||(int)$row['acquired']!==1)throw new NakesImportException('reset_lock_unavailable');$this->lockAcquired=true;}
	private function releaseLock(){if(!$this->lockAcquired)return;$name='doclinc_staging_data_reset_nakes_import';try{$stmt=$this->db->prepare('SELECT RELEASE_LOCK(?)');$stmt->bind_param('s',$name);$stmt->execute();$stmt->close();}catch(Throwable $ignored){}$this->lockAcquired=false;}
	private function deleteIds($table,$column,array $ids){if(!$ids)return;$this->db->query('DELETE FROM `'.$table.'` WHERE `'.$column.'` IN ('.implode(',',array_map('intval',$ids)).')');}
	private function optionalCompatibilityTables(){$tables=array();foreach(self::OPTIONAL_COMPATIBILITY_TABLES as $name)$tables[$name]=$this->table($name);return $tables;}
	private function table($name){$stmt=$this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->bind_param('s',$name);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();return $row;}
	private function column($table,$name){$stmt=$this->db->prepare('SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$stmt->bind_param('ss',$table,$name);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();return $row;}
	private function count($table){return(int)$this->db->query('SELECT COUNT(*) total FROM `'.$table.'`')->fetch_assoc()['total'];}
	private function snapshotRows($table,$where,$order){return $this->db->query('SELECT * FROM `'.$table.'` WHERE '.$where.' ORDER BY `'.$order.'`')->fetch_all(MYSQLI_ASSOC);}
	private function clinicalCounts(){return array('batches'=>$this->count('clinical_suggestion_import_batches'),'terms'=>$this->count('clinical_suggestion_terms'),'aliases'=>$this->count('clinical_suggestion_aliases'),'applied_migrations'=>(int)$this->db->query("SELECT COUNT(*) total FROM clinical_schema_migrations WHERE state='applied'")->fetch_assoc()['total']);}
	private function uploadReferenceCount(array $deleted){$count=0;$candidates=array('users'=>array('foto'),'requests'=>array('file','foto','video'),'konsultasi'=>array('foto'),'consultation_messages'=>array('attachment_path'));foreach($candidates as $table=>$columns){if(!$this->table($table))continue;foreach($columns as $column){if(!$this->column($table,$column))continue;$where="$column IS NOT NULL AND TRIM($column)<>''";if($table==='users'&&$deleted)$where.=' AND userId IN ('.implode(',',$deleted).')';$count+=(int)$this->db->query("SELECT COUNT(*) total FROM `$table` WHERE $where")->fetch_assoc()['total'];}}return $count;}
	private static function envTrue($name){$value=getenv($name);return is_string($value)&&in_array(strtolower(trim($value)),array('1','true','yes','on'),true);}
}
