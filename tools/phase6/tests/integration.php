<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
require_once dirname(__DIR__, 2) . '/realtime_requests/tests/MariaDbReadiness.php';

class MX_Controller
{
	public $db;
	public $config;
	public $load;
	public $session;
}

class Phase6IntegrationConfig
{
	private $items = array('care_team_workflow_enabled' => true);
	public function item($key) { return $this->items[$key] ?? null; }
	public function set($key, $value) { $this->items[$key] = $value; }
}

class Phase6IntegrationLoader
{
	public function database($group = 'default', $return = true) { return $GLOBALS['phase6_application']->db; }
}

class Phase6IntegrationApplication
{
	public $db;
	public $config;
	public $load;
	public function __construct()
	{
		$this->config = new Phase6IntegrationConfig();
		$this->load = new Phase6IntegrationLoader();
	}
}

$GLOBALS['phase6_application'] = new Phase6IntegrationApplication();

function &get_instance()
{
	return $GLOBALS['phase6_application'];
}

require_once APPPATH . 'helpers/request_authz_helper.php';
require_once APPPATH . 'libraries/Visit_vital_signs_service.php';
require_once APPPATH . 'modules/home_nakes/models/Home_nakes_m.php';
require_once APPPATH . 'modules/konsultasi_nakes/models/Konsultasi_nakes_m.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$passed = 0;
$failed = 0;
$admin = null;
$db = null;
$database = '';

function phase6_integration_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$name}\n";
		return;
	}
	$failed++;
	fwrite(STDERR, "FAIL {$name}\n");
}

function phase6_integration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function phase6_integration_identifier($value)
{
	if (preg_match('/\Adoclinc_phase6_test_[a-f0-9]+\z/', $value) !== 1) {
		throw new RuntimeException('unsafe_database_identifier');
	}
	return '`' . $value . '`';
}

function phase6_integration_apply($database, $host, $port, $user, $password)
{
	$environment = array(
		'DOCLINC_PHASE6_SCHEMA_WRITE_ENABLED' => 'true',
		'DOCLINC_PHASE6_DISPOSABLE_TEST' => 'true',
		'DOCLINC_PHASE6_SCHEMA_DB_HOST' => $host,
		'DOCLINC_PHASE6_SCHEMA_DB_PORT' => $port,
		'DOCLINC_PHASE6_SCHEMA_DB_NAME' => $database,
		'DOCLINC_PHASE6_SCHEMA_DB_USER' => $user,
		'DOCLINC_PHASE6_SCHEMA_DB_PASSWORD' => $password,
		'DOCLINC_PHASE6_SCHEMA_ALLOWED_USERS' => $user,
	);
	foreach ($environment as $name => $value) { putenv($name . '=' . $value); }
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(APPPATH . 'migrations/20260813000100_phase6_clinical_visit_foundation.php')
		. ' --apply --environment=test --confirm-database=' . escapeshellarg($database) . ' --confirm-disposable-test=true';
	$output = array();
	$exit = 1;
	exec($command . ' 2>&1', $output, $exit);
	return array($exit, $output);
}

function phase6_integration_model($class, $db)
{
	$reflection = new ReflectionClass($class);
	$model = $reflection->newInstanceWithoutConstructor();
	$model->db = $db;
	$model->config = $GLOBALS['phase6_application']->config;
	return $model;
}

function phase6_integration_db($host, $port, $user, $password, $database)
{
	return DB(array('dsn' => '', 'hostname' => $host, 'username' => $user, 'password' => $password, 'database' => $database, 'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false, 'db_debug' => false, 'cache_on' => false, 'cachedir' => '', 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'swap_pre' => '', 'encrypt' => false, 'compress' => false, 'stricton' => true, 'failover' => array(), 'save_queries' => true, 'port' => $port), true);
}

$password = phase6_integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') {
	fwrite(STDERR, "PHASE6_INTEGRATION=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$host = phase6_integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
	$port = (int) phase6_integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
	$user = phase6_integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
	$ready = MariaDbReadiness::wait(array('host' => $host, 'user' => $user, 'password' => $password, 'port' => $port, 'timeout_ms' => 45000, 'interval_ms' => 500));
	$admin = $ready->connection;
	$database = 'doclinc_phase6_test_' . bin2hex(random_bytes(6));
	$admin->query('CREATE DATABASE ' . phase6_integration_identifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$bootstrap = new mysqli($host, $user, $password, $database, $port);
	$bootstrap->set_charset('utf8mb4');
	$ddl = array(
		"CREATE TABLE users(userId INT(11) NOT NULL,nama VARCHAR(100) NULL,role ENUM('admin','dokter','warga','') NOT NULL,status ENUM('aktif','nonaktif') NULL DEFAULT 'aktif',remark VARCHAR(100) NULL,PRIMARY KEY(userId)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		"CREATE TABLE m_puskesmas(kode_pkm VARCHAR(100) NOT NULL,nama_puskesmas VARCHAR(150) NOT NULL,status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',PRIMARY KEY(kode_pkm)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		"CREATE TABLE puskesmas_staff(staff_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,user_id INT(11) NULL,kode_pkm VARCHAR(100) NOT NULL,nama VARCHAR(150) NOT NULL,gelar VARCHAR(100) NULL,profesi VARCHAR(100) NULL,nomor_sip VARCHAR(100) NULL,sip_expired_at DATE NULL,status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',PRIMARY KEY(staff_id),KEY idx_staff_user(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		"CREATE TABLE requests(request_id INT(11) NOT NULL AUTO_INCREMENT,user_id INT(11) NOT NULL,dokter_id INT(11) NOT NULL,request_status ENUM('Pending','Accepted','Completed','Cancelled') NOT NULL DEFAULT 'Pending',assigned_puskesmas_code VARCHAR(100) NULL,assigned_nakes_user_id INT(11) NULL,accepted_by_user_id INT(11) NULL,responsible_doctor_user_id INT(11) NULL,visit_performer_user_id INT(11) NULL,consultation_mode VARCHAR(30) NULL,visit_status VARCHAR(30) NULL,updated_at DATETIME NULL,PRIMARY KEY(request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		"CREATE TABLE request_responsible_doctor_assignments(responsible_assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,request_id INT(11) NOT NULL,staff_id INT(10) UNSIGNED NOT NULL,user_id INT(11) NOT NULL,assigned_by_user_id INT(11) NOT NULL,status ENUM('aktif','diganti','dibatalkan') NOT NULL DEFAULT 'aktif',assigned_at DATETIME NOT NULL,ended_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NULL,PRIMARY KEY(responsible_assignment_id),KEY idx_responsible_request_status(request_id,status,responsible_assignment_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		"CREATE TABLE request_visit_performer_assignments(visit_assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,request_id INT(11) NOT NULL,staff_id INT(10) UNSIGNED NOT NULL,user_id INT(11) NOT NULL,assigned_by_user_id INT(11) NOT NULL,status ENUM('aktif','diganti','dibatalkan') NOT NULL DEFAULT 'aktif',assigned_at DATETIME NOT NULL,ended_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NULL,PRIMARY KEY(visit_assignment_id),KEY idx_visit_request_status(request_id,status,visit_assignment_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		"CREATE TABLE request_events(event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,request_id INT(11) NOT NULL,event_type VARCHAR(80) NOT NULL,puskesmas_code VARCHAR(100) NULL,actor_user_id INT(11) NULL,actor_role VARCHAR(50) NULL,message VARCHAR(255) NULL,metadata_json TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(event_id),KEY idx_request_events_request(request_id,event_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		"CREATE TABLE medicalrecords(record_id INT(11) NOT NULL AUTO_INCREMENT,request_id INT(11) NOT NULL,diagnosis TEXT NULL,treatment TEXT NULL,recommendations TEXT NULL,anamnesis TEXT NULL,created_at DATETIME NULL,PRIMARY KEY(record_id),KEY idx_medical_request(request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
	);
	foreach ($ddl as $sql) { $bootstrap->query($sql); }
	$bootstrap->query("INSERT INTO m_puskesmas VALUES('PKM01','Puskesmas Satu','aktif'),('PKM02','Puskesmas Dua','aktif')");
	$bootstrap->query("INSERT INTO users VALUES(10,'Pusat Layanan','dokter','aktif','PKM01'),(101,'Warga Uji','warga','aktif',NULL),(201,'Ayu Pratama','dokter','aktif','PKM01'),(202,'Rina Sehat','dokter','aktif','PKM01'),(203,'Budi Sehat','dokter','aktif','PKM01'),(204,'Sari Sehat','dokter','nonaktif','PKM01'),(301,'Citra Lain','dokter','aktif','PKM02')");
	$bootstrap->query("INSERT INTO puskesmas_staff(staff_id,user_id,kode_pkm,nama,gelar,profesi,status) VALUES(31,201,'PKM01','Ayu Pratama','dr.','Dokter Umum','aktif'),(32,202,'PKM01','Rina Sehat',NULL,'Perawat','aktif'),(33,203,'PKM01','Budi Sehat',NULL,'Bidan','aktif'),(34,204,'PKM01','Sari Sehat',NULL,'Perawat','aktif'),(35,301,'PKM02','Citra Lain',NULL,'Perawat','aktif')");
	$bootstrap->query("INSERT INTO requests(request_id,user_id,dokter_id,request_status,assigned_puskesmas_code,responsible_doctor_user_id,visit_performer_user_id,consultation_mode,visit_status) VALUES(100,101,10,'Accepted','PKM01',201,202,'visit','arrived'),(101,101,10,'Accepted','PKM01',201,202,'visit','arrived'),(102,101,10,'Accepted','PKM01',201,NULL,'non_visit','not_started'),(103,101,10,'Accepted','PKM01',201,202,'visit','not_started'),(104,101,10,'Accepted','PKM01',201,203,'visit','completed'),(105,101,10,'Accepted','PKM01',201,202,'visit','in_service')");
	$bootstrap->query("INSERT INTO request_responsible_doctor_assignments(request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,created_at) VALUES(100,31,201,10,'aktif',NOW(),NOW()),(101,31,201,10,'aktif',NOW(),NOW()),(102,31,201,10,'aktif',NOW(),NOW()),(103,31,201,10,'aktif',NOW(),NOW()),(104,31,201,10,'aktif',NOW(),NOW()),(105,31,201,10,'aktif',NOW(),NOW())");
	$bootstrap->query("INSERT INTO request_visit_performer_assignments(request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,created_at) VALUES(100,32,202,201,'aktif',NOW(),NOW()),(101,32,202,201,'aktif',NOW(),NOW()),(103,32,202,201,'aktif',NOW(),NOW()),(104,33,203,201,'aktif',NOW(),NOW()),(105,32,202,201,'aktif',NOW(),NOW())");
	$bootstrap->close();

	$db = phase6_integration_db($host, $port, $user, $password, $database);
	$GLOBALS['phase6_application']->db = $db;
	$service = new Visit_vital_signs_service($db);
	phase6_integration_expect(!$service->schemaReady(), 'source_safe_before_phase6_migration');
	$model = phase6_integration_model('Konsultasi_nakes_m', $db);
	$doctor = doclinc_dokter_identity_context(201, true);
	$GLOBALS['phase6_application']->config->set('care_team_workflow_enabled', false);
	$legacy_command = doclinc_dokter_identity_context(10, true);
	phase6_integration_expect($model->save_konsultasi_nakes(102, 'Diagnosis uji', 'Saran uji', 'Selesai Konsultasi', '', null, array(), 10, $legacy_command), 'feature_off_legacy_completion_without_phase6_schema_preserved');
	$db->where('request_id', 102)->update('requests', array('request_status' => 'Accepted', 'visit_status' => 'not_started'));
	$db->where('request_id', 102)->delete('medicalrecords');
	$GLOBALS['phase6_application']->config->set('care_team_workflow_enabled', true);
	$doctor = doclinc_dokter_identity_context(201, true);
	$missing_attribution = $model->save_konsultasi_nakes(102, 'Diagnosis uji', 'Saran uji', 'Selesai Konsultasi', '', null, array(), 201, $doctor);
	$missing_attribution_request = $db->where('request_id', 102)->get('requests')->row();
	phase6_integration_expect(!$missing_attribution
		&& $model->last_failure_code() === 'clinical_attribution_schema_unavailable'
		&& $missing_attribution_request && (string) $missing_attribution_request->request_status === 'Accepted'
		&& $db->where('request_id', 102)->count_all_results('medicalrecords') === 0, 'care_team_completion_denied_when_attribution_fields_absent');
	$db->query('ALTER TABLE medicalrecords ADD COLUMN responsible_doctor_user_id INT(11) NULL');
	$db->close();
	$db = phase6_integration_db($host, $port, $user, $password, $database);
	$GLOBALS['phase6_application']->db = $db;
	$model = phase6_integration_model('Konsultasi_nakes_m', $db);
	$doctor = doclinc_dokter_identity_context(201, true);
	$partial_attribution = $model->save_konsultasi_nakes(102, 'Diagnosis uji', 'Saran uji', 'Selesai Konsultasi', '', null, array(), 201, $doctor);
	$partial_attribution_request = $db->where('request_id', 102)->get('requests')->row();
	phase6_integration_expect(!$partial_attribution
		&& $model->last_failure_code() === 'clinical_attribution_schema_unavailable'
		&& $partial_attribution_request && (string) $partial_attribution_request->request_status === 'Accepted'
		&& $db->where('request_id', 102)->count_all_results('medicalrecords') === 0, 'care_team_completion_denied_when_attribution_schema_partial');
	$db->query('ALTER TABLE medicalrecords ADD COLUMN recorded_by_user_id INT(11) NULL');
	$db->close();
	$db = phase6_integration_db($host, $port, $user, $password, $database);
	$GLOBALS['phase6_application']->db = $db;
	$model = phase6_integration_model('Konsultasi_nakes_m', $db);
	$doctor = doclinc_dokter_identity_context(201, true);
	$missing_vital_schema = $model->save_konsultasi_nakes(101, 'Diagnosis uji', 'Saran uji', 'Kunjungan Nakes', '', null, array(), 201, $doctor);
	$missing_vital_request = $db->where('request_id', 101)->get('requests')->row();
	phase6_integration_expect(!$missing_vital_schema
		&& $model->last_failure_code() === 'vital_signs_schema_unavailable'
		&& $missing_vital_request && (string) $missing_vital_request->request_status === 'Accepted'
		&& $db->where('request_id', 101)->count_all_results('medicalrecords') === 0, 'visit_completion_denied_before_vital_schema');
	$db->query('ALTER TABLE medicalrecords DROP COLUMN responsible_doctor_user_id, DROP COLUMN recorded_by_user_id');
	$db->close();
	list($migration_exit, $migration_output) = phase6_integration_apply($database, $host, $port, $user, $password);
	phase6_integration_expect($migration_exit === 0 && in_array('MIGRATION_RESULT=PASS', $migration_output, true), 'phase6_migration_applies');
	$db = phase6_integration_db($host, $port, $user, $password, $database);
	$GLOBALS['phase6_application']->db = $db;
	$service = new Visit_vital_signs_service($db);
	phase6_integration_expect($service->schemaReady(), 'vital_signs_schema_ready');

	$values = array('systolic' => '120', 'diastolic' => '80', 'pulse' => '72', 'respiratory_rate' => '18', 'temperature_c' => '36.7', 'oxygen_saturation' => '98', 'notes' => 'Stabil', 'measured_by_user_id' => 10, 'responsible_doctor_user_id' => 10, 'measured_at' => '2000-01-01');
	phase6_integration_expect(($service->record(100, 10, doclinc_dokter_identity_context(10, true), $values)['safe_error_code'] ?? '') === 'access_denied', 'command_center_vital_write_denied');
	phase6_integration_expect(($service->record(100, 201, doclinc_dokter_identity_context(201, true), $values)['safe_error_code'] ?? '') === 'access_denied', 'responsible_doctor_cannot_impersonate_performer');
	phase6_integration_expect(($service->record(100, 203, doclinc_dokter_identity_context(203, true), $values)['safe_error_code'] ?? '') === 'access_denied', 'unrelated_personal_nakes_denied');
	phase6_integration_expect(($service->record(100, 301, doclinc_dokter_identity_context(301, true), $values)['safe_error_code'] ?? '') === 'access_denied', 'cross_facility_nakes_denied');
	phase6_integration_expect(($service->record(100, 204, doclinc_dokter_identity_context(204, true), $values)['safe_error_code'] ?? '') === 'access_denied', 'inactive_nakes_denied');
	$result = $service->record(100, 202, doclinc_dokter_identity_context(202, true), $values);
	$row = $db->where('request_id', 100)->get('request_vital_sign_measurements')->row();
	phase6_integration_expect(($result['status'] ?? '') === 'success', 'canonical_visit_performer_records_vital_signs');
	phase6_integration_expect($row && (int) $row->measured_by_user_id === 202 && (int) $row->measured_by_staff_id === 32, 'measurement_actor_attribution_preserved');
	phase6_integration_expect($row && (int) $row->responsible_doctor_user_id === 201 && (int) $row->visit_performer_user_id === 202, 'care_team_attribution_snapshotted');
	phase6_integration_expect($row && (string) $row->measured_at !== '2000-01-01 00:00:00.000000', 'client_measurement_timestamp_ignored');
	phase6_integration_expect(($service->record(103, 202, doclinc_dokter_identity_context(202, true), $values)['safe_error_code'] ?? '') === 'access_denied', 'visit_status_gate_denies_early_measurement');
	$home_model = phase6_integration_model('Home_nakes_m', $db);
	phase6_integration_expect(($home_model->update_visit_status(103, 10, 'en_route', doclinc_dokter_identity_context(10, true))['status'] ?? '') === 'error', 'command_center_visit_status_denied');
	phase6_integration_expect(($home_model->update_visit_status(103, 201, 'en_route', doclinc_dokter_identity_context(201, true))['status'] ?? '') === 'error', 'responsible_doctor_cannot_impersonate_visit_performer');
	phase6_integration_expect(($home_model->update_visit_status(103, 203, 'en_route', doclinc_dokter_identity_context(203, true))['status'] ?? '') === 'error', 'unrelated_nakes_visit_status_denied');
	$en_route = $home_model->update_visit_status(103, 202, 'en_route', doclinc_dokter_identity_context(202, true));
	phase6_integration_expect(($en_route['status'] ?? '') === 'success' && !empty($en_route['changed']), 'visit_performer_starts_route');
	$before_repeat = $db->where('request_id', 103)->get('requests')->row();
	$events_before_repeat = $db->where('request_id', 103)->where('event_type', 'visit_started')->count_all_results('request_events');
	$repeat = $home_model->update_visit_status(103, 202, 'en_route', doclinc_dokter_identity_context(202, true));
	phase6_integration_expect(($repeat['status'] ?? '') === 'success' && empty($repeat['changed']), 'repeated_visit_status_is_idempotent');
	$after_repeat = $db->where('request_id', 103)->get('requests')->row();
	$events_after_repeat = $db->where('request_id', 103)->where('event_type', 'visit_started')->count_all_results('request_events');
	phase6_integration_expect($before_repeat && $after_repeat
		&& (string) $before_repeat->updated_at === (string) $after_repeat->updated_at
		&& $events_before_repeat === 1 && $events_after_repeat === 1, 'repeated_visit_status_writes_no_request_or_lifecycle_event');
	phase6_integration_expect(($home_model->update_visit_status(103, 202, 'in_service', doclinc_dokter_identity_context(202, true))['status'] ?? '') === 'error', 'visit_status_skip_denied');
	phase6_integration_expect(($home_model->update_visit_status(103, 202, 'arrived', doclinc_dokter_identity_context(202, true))['status'] ?? '') === 'success'
		&& ($home_model->update_visit_status(103, 202, 'in_service', doclinc_dokter_identity_context(202, true))['status'] ?? '') === 'success', 'canonical_visit_lifecycle_reaches_in_service');
	$without_ttv = $home_model->update_visit_status(103, 202, 'completed', doclinc_dokter_identity_context(202, true));
	$current_103 = $db->where('request_id', 103)->get('requests')->row();
	phase6_integration_expect(($without_ttv['safe_error_code'] ?? '') === 'vital_signs_required'
		&& $current_103 && (string) $current_103->visit_status === 'in_service', 'visit_status_completion_without_current_ttv_denied');
	phase6_integration_expect(($service->record(103, 202, doclinc_dokter_identity_context(202, true), $values)['status'] ?? '') === 'success', 'current_visit_measurement_recorded');
	$completed_103 = $home_model->update_visit_status(103, 202, 'completed', doclinc_dokter_identity_context(202, true));
	phase6_integration_expect(($completed_103['status'] ?? '') === 'success' && !empty($completed_103['changed']), 'visit_status_completion_after_current_ttv_passes');
	$repeat_completed_103 = $home_model->update_visit_status(103, 202, 'completed', doclinc_dokter_identity_context(202, true));
	phase6_integration_expect(($repeat_completed_103['status'] ?? '') === 'success' && empty($repeat_completed_103['changed']), 'repeated_completed_status_has_no_mutation');

	$model = phase6_integration_model('Konsultasi_nakes_m', $db);
	$doctor = doclinc_dokter_identity_context(201, true);
	$performer = doclinc_dokter_identity_context(202, true);
	$command = doclinc_dokter_identity_context(10, true);
	phase6_integration_expect(!$model->save_konsultasi_nakes(101, 'Diagnosis uji', 'Saran uji', 'Kunjungan Nakes', '', null, array(), 201, $doctor)
		&& $model->last_failure_code() === 'visit_status_incomplete', 'visit_completion_waits_for_performer_completion');
	phase6_integration_expect(($service->record(101, 202, $performer, $values)['status'] ?? '') === 'success', 'visit_measurement_fixture_created');
	$db->where('request_id', 101)->update('requests', array('visit_status' => 'completed'));
	phase6_integration_expect(!$model->save_konsultasi_nakes(101, 'Diagnosis palsu', 'Saran palsu', 'Kunjungan Nakes', '', null, array(), 202, $performer), 'visit_performer_cannot_write_doctor_record');
	phase6_integration_expect(!$model->save_konsultasi_nakes(101, 'Diagnosis palsu', 'Saran palsu', 'Kunjungan Nakes', '', null, array(), 10, $command), 'command_center_clinical_write_denied');
	phase6_integration_expect($model->save_konsultasi_nakes(101, 'Diagnosis uji', 'Saran uji', 'Kunjungan Nakes', '', null, array(), 201, $doctor), 'responsible_doctor_completes_visit_after_ttv');
	$record = $db->where('request_id', 101)->get('medicalrecords')->row();
	phase6_integration_expect($record && (int) $record->responsible_doctor_user_id === 201 && (int) $record->recorded_by_user_id === 201, 'clinical_record_attributed_to_responsible_doctor');
	phase6_integration_expect($model->save_konsultasi_nakes(102, 'Diagnosis jarak jauh', 'Saran jarak jauh', 'Selesai Konsultasi', '', null, array(), 201, $doctor), 'responsible_doctor_completes_non_visit_without_ttv');
	$db->insert('request_vital_sign_measurements', array('request_id' => 104, 'measured_by_user_id' => 202, 'measured_by_staff_id' => 32, 'responsible_doctor_user_id' => 201, 'visit_performer_user_id' => 202, 'systolic' => 120, 'measured_at' => '2026-08-13 00:00:00.000000'));
	phase6_integration_expect(!$model->save_konsultasi_nakes(104, 'Diagnosis uji', 'Saran uji', 'Kunjungan Nakes', '', null, array(), 201, $doctor)
		&& $model->last_failure_code() === 'vital_signs_required', 'prior_performer_measurement_does_not_satisfy_current_assignment');
	phase6_integration_expect(($service->record(105, 202, $performer, $values)['status'] ?? '') === 'success', 'first_assignment_cycle_measurement_recorded');
	$db->where('request_id', 105)->where('status', 'aktif')->update('request_visit_performer_assignments', array('status' => 'diganti', 'ended_at' => date('Y-m-d H:i:s')));
	sleep(1);
	$db->insert('request_visit_performer_assignments', array('request_id' => 105, 'staff_id' => 32, 'user_id' => 202, 'assigned_by_user_id' => 201, 'status' => 'aktif', 'assigned_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')));
	$old_cycle = $home_model->update_visit_status(105, 202, 'completed', $performer);
	phase6_integration_expect(($old_cycle['safe_error_code'] ?? '') === 'vital_signs_required', 'same_performer_reassignment_rejects_old_measurement');
	phase6_integration_expect(($service->record(105, 202, $performer, $values)['status'] ?? '') === 'success', 'same_performer_new_assignment_measurement_recorded');
	$db->where('request_id', 105)->where('status', 'aktif')->update('request_responsible_doctor_assignments', array('status' => 'diganti', 'ended_at' => date('Y-m-d H:i:s')));
	sleep(1);
	$db->insert('request_responsible_doctor_assignments', array('request_id' => 105, 'staff_id' => 31, 'user_id' => 201, 'assigned_by_user_id' => 10, 'status' => 'aktif', 'assigned_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')));
	$responsible_old_cycle = $home_model->update_visit_status(105, 202, 'completed', $performer);
	phase6_integration_expect(($responsible_old_cycle['safe_error_code'] ?? '') === 'vital_signs_required', 'same_responsible_doctor_reassignment_rejects_old_measurement');
	phase6_integration_expect(($service->record(105, 202, $performer, $values)['status'] ?? '') === 'success', 'current_care_team_cycle_measurement_recorded');
	phase6_integration_expect(($home_model->update_visit_status(105, 202, 'completed', $performer)['status'] ?? '') === 'success', 'same_care_team_new_assignment_completion_passes');
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL integration_runtime\nSAFE_ERROR_CODE=phase6_integration_failed\n");
} finally {
	if ($db) { $db->close(); }
	if ($admin && $database !== '' && preg_match('/\Adoclinc_phase6_test_[a-f0-9]+\z/', $database) === 1) {
		$admin->query('DROP DATABASE IF EXISTS ' . phase6_integration_identifier($database));
	}
	if ($admin) { $admin->close(); }
}

echo "PHASE6_INTEGRATION_ASSERTIONS=" . ($passed + $failed) . "\n";
echo "PHASE6_INTEGRATION_PASS={$passed}\nPHASE6_INTEGRATION_FAIL={$failed}\nPHASE6_INTEGRATION_SKIP=0\n";
exit($failed === 0 ? 0 : 1);
