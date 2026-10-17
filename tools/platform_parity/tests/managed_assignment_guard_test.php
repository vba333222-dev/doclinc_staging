<?php
/**
 * Managed-visit assignment guard regression test.
 *
 * Care_team_service::assignVisitPerformer() is the assignment authority the
 * responsible doctor uses. For a request owned by the managed clinical workflow
 * it must honour the responsible doctor's own disposition: the request has to be
 * enrolled, the active disposition has to require a visit, the performer has to
 * match required_profession, and the performer has to hold an active facility
 * placement. Requests outside the managed workflow keep the legacy behaviour so
 * unenrolled consultations stay operable.
 *
 * This is the regression test for the divergence found in the parity audit: the
 * workflow state resolver emitted WAITING_DOCTOR_DISPOSITION before
 * WAITING_PERFORMER_ASSIGNMENT while the assignment path ignored the disposition
 * entirely.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';

class ManagedGuardConfig
{
	private $items;
	public function __construct(array $items) { $this->items = $items; }
	public function item($key) { return isset($this->items[$key]) ? $this->items[$key] : null; }
	public function set($key, $value) { $this->items[$key] = $value; }
}

class ManagedGuardApplication
{
	public $db;
	public $config;
	public function __construct(array $items) { $this->config = new ManagedGuardConfig($items); }
}

/** Records delivery attempts so no notifications/outbox tables are required. */
class ManagedGuardNotificationDelivery
{
	public $calls = 0;
	public function createWithinTransaction(array $payload) { $this->calls++; return (object) array('notification_id' => $this->calls); }
}

$GLOBALS['managed_guard_application'] = new ManagedGuardApplication(array('visit_clinical_workflow_enabled' => false));

function &get_instance()
{
	return $GLOBALS['managed_guard_application'];
}

require_once APPPATH . 'libraries/Care_team_service.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$passed = 0;
$failed = 0;
$admin = null;
$db = null;
$database = '';

function mag_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$name}\n"; return; }
	$failed++; fwrite(STDERR, "FAIL {$name}\n");
}

function mag_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function mag_identifier($value)
{
	if (preg_match('/\A[a-z0-9_]+\z/', $value) !== 1) {
		throw new RuntimeException('unsafe_database_identifier');
	}
	return '`' . $value . '`';
}

function mag_feature($enabled)
{
	$GLOBALS['managed_guard_application']->config->set('visit_clinical_workflow_enabled', $enabled === true);
}

$password = mag_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($password === '') {
	fwrite(STDERR, "MANAGED_ASSIGNMENT_GUARD=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$host = mag_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1');
	$port = (int) mag_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306');
	$user = mag_env('DOCLINC_TEST_DB_ADMIN_USER', 'root');
	$admin = new mysqli($host, $user, $password, '', $port);
	$admin->set_charset('utf8mb4');
	$database = 'doclinc_managed_guard_test_' . bin2hex(random_bytes(5));
	$admin->query('CREATE DATABASE ' . mag_identifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

	$bootstrap = new mysqli($host, $user, $password, $database, $port);
	$bootstrap->set_charset('utf8mb4');
	$ddl = array(
		"CREATE TABLE users(userId int(11) NOT NULL AUTO_INCREMENT,nama varchar(100) NULL,password varchar(255) NOT NULL,role enum('admin','dokter','warga','') NOT NULL,status enum('aktif','nonaktif') NULL DEFAULT 'aktif',remark varchar(100) NULL,PRIMARY KEY(userId)) ENGINE=InnoDB",
		"CREATE TABLE m_puskesmas(kode_pkm varchar(100) NOT NULL,status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',PRIMARY KEY(kode_pkm)) ENGINE=InnoDB",
		"CREATE TABLE puskesmas_staff(staff_id int(10) unsigned NOT NULL AUTO_INCREMENT,user_id int(11) NULL,kode_pkm varchar(100) NOT NULL,nama varchar(150) NOT NULL,gelar varchar(100) NULL,profesi varchar(100) NULL,status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',PRIMARY KEY(staff_id),KEY idx_staff_user(user_id)) ENGINE=InnoDB",
		"CREATE TABLE requests(request_id int(11) NOT NULL AUTO_INCREMENT,request_status enum('Pending','Accepted','Completed','Cancelled') NULL DEFAULT 'Pending',assigned_puskesmas_code varchar(100) NULL,accepted_by_user_id int(11) NULL,responsible_doctor_user_id int(11) NULL,consultation_mode varchar(30) NULL,visit_performer_user_id int(11) NULL,PRIMARY KEY(request_id)) ENGINE=InnoDB",
		"CREATE TABLE request_responsible_doctor_assignments(responsible_assignment_id int(10) unsigned NOT NULL AUTO_INCREMENT,request_id int(11) NOT NULL,staff_id int(10) unsigned NOT NULL,user_id int(11) NOT NULL,assigned_by_user_id int(11) NOT NULL,status enum('aktif','diganti','dibatalkan') NOT NULL DEFAULT 'aktif',assigned_at datetime NOT NULL,ended_at datetime NULL,created_at datetime NOT NULL,updated_at datetime NULL,PRIMARY KEY(responsible_assignment_id)) ENGINE=InnoDB",
		"CREATE TABLE request_visit_performer_assignments(visit_assignment_id int(10) unsigned NOT NULL AUTO_INCREMENT,request_id int(11) NOT NULL,staff_id int(10) unsigned NOT NULL,user_id int(11) NOT NULL,assigned_by_user_id int(11) NOT NULL,status enum('aktif','diganti','dibatalkan') NOT NULL DEFAULT 'aktif',assigned_at datetime NOT NULL,ended_at datetime NULL,created_at datetime NOT NULL,updated_at datetime NULL,PRIMARY KEY(visit_assignment_id),KEY idx_request_status(request_id,status)) ENGINE=InnoDB",
		"CREATE TABLE visit_dispositions(disposition_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,request_id int(11) NOT NULL,version_no int(10) unsigned NOT NULL DEFAULT 1,decision enum('visit','non_visit') NOT NULL,required_profession varchar(100) NULL,superseded_at datetime(6) NULL,created_at datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(disposition_id),KEY idx_disposition_request(request_id,superseded_at)) ENGINE=InnoDB",
		"CREATE TABLE nakes_facility_placements(placement_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,staff_id int(10) unsigned NOT NULL,facility_code varchar(64) NOT NULL,effective_from datetime NULL,effective_until datetime NULL,status enum('active','ended') NOT NULL DEFAULT 'active',created_by_user_id int(11) NULL,PRIMARY KEY(placement_id),KEY idx_placement(staff_id,facility_code,status)) ENGINE=InnoDB",
	);
	foreach ($ddl as $sql) { $bootstrap->query($sql); }
	// user 10 is the facility command centre (first active dokter with remark
	// PKM01 and no staff link); 201/202/203 are personal accounts.
	$bootstrap->query("INSERT INTO m_puskesmas VALUES('PKM01','aktif')");
	$bootstrap->query("INSERT INTO users(userId,nama,password,role,status,remark) VALUES"
		. "(10,'Pusat Layanan','hash-10','dokter','aktif','PKM01'),"
		. "(201,'Ayu Pratama','hash-201','dokter','aktif','PKM01'),"
		. "(202,'Rina Sehat','hash-202','dokter','aktif','PKM01'),"
		. "(203,'Budi Utama','hash-203','dokter','aktif','PKM01')");
	$bootstrap->query("INSERT INTO puskesmas_staff(staff_id,user_id,kode_pkm,nama,gelar,profesi,status) VALUES"
		. "(31,201,'PKM01','Ayu Pratama','dr.','Dokter Umum','aktif'),"
		. "(32,202,'PKM01','Rina Sehat',NULL,'Perawat','aktif'),"
		. "(33,203,'PKM01','Budi Utama','dr.','Dokter Umum','aktif')");
	// staff 31 and 32 hold active placements; staff 33 deliberately does not.
	$bootstrap->query("INSERT INTO nakes_facility_placements(staff_id,facility_code,effective_from,status,created_by_user_id) VALUES"
		. "(31,'PKM01','2026-01-01','active',10),(32,'PKM01','2026-01-01','active',10)");
	$bootstrap->query("INSERT INTO requests(request_id,request_status,assigned_puskesmas_code,accepted_by_user_id,responsible_doctor_user_id,consultation_mode) VALUES"
		. "(100,'Accepted','PKM01',10,201,'visit'),"
		. "(101,'Accepted','PKM01',10,201,'visit'),"
		. "(102,'Accepted','PKM01',10,201,'visit'),"
		. "(103,'Accepted','PKM01',10,201,'visit')");
	// 101 is enrolled and requires a Dokter Umum; 103 is enrolled but its
	// disposition decided against a visit; 100 and 102 stay unenrolled.
	$bootstrap->query("INSERT INTO visit_dispositions(request_id,version_no,decision,required_profession) VALUES"
		. "(101,1,'visit','Dokter Umum'),(103,1,'non_visit',NULL)");
	$bootstrap->close();
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL managed_assignment_guard_bootstrap\nSAFE_ERROR_CODE=managed_assignment_guard_failed\n");
	echo "MANAGED_ASSIGNMENT_GUARD_PASSED={$passed}\nMANAGED_ASSIGNMENT_GUARD_FAILED={$failed}\nMANAGED_ASSIGNMENT_GUARD_SKIPPED=0\n";
	exit(1);
}

$db = DB(array(
	'dsn' => '', 'hostname' => $host, 'username' => $user, 'password' => $password, 'database' => $database,
	'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false, 'db_debug' => false, 'cache_on' => false,
	'cachedir' => '', 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'swap_pre' => '',
	'encrypt' => false, 'compress' => false, 'stricton' => true, 'failover' => array(), 'save_queries' => true, 'port' => $port,
), true);
$GLOBALS['managed_guard_application']->db = $db;

$delivery = new ManagedGuardNotificationDelivery();
$care = new Care_team_service($db, array('enabled' => false), $delivery);
mag_expect($care->schemaReady(), 'guard_fixture_schema_ready');

// 1. A request outside the managed workflow keeps the legacy behaviour.
mag_feature(false);
$result = $care->assignVisitPerformer(100, 31, 201);
mag_expect(!empty($result['ok']), 'unenrolled_request_keeps_legacy_assignment');
mag_expect((int) $db->where('request_id', 100)->where('status', 'aktif')->count_all_results('request_visit_performer_assignments') === 1, 'unenrolled_assignment_is_recorded');

// 2. An enrolled request accepts a performer matching required_profession.
$result = $care->assignVisitPerformer(101, 31, 201);
mag_expect(!empty($result['ok']), 'enrolled_request_accepts_matching_profession');
mag_expect((int) $db->where('request_id', 101)->get('requests')->row()->visit_performer_user_id === 201, 'matching_performer_projected_on_request');

// 3. A performer whose profession the disposition did not authorise is refused.
$result = $care->assignVisitPerformer(101, 32, 201);
mag_expect(empty($result['ok']) && strpos((string) $result['message'], 'profesi') !== false, 'unauthorised_profession_is_refused');

// 4. A performer without an active facility placement is refused.
$result = $care->assignVisitPerformer(101, 33, 201);
mag_expect(empty($result['ok']) && strpos((string) $result['message'], 'belum terdaftar') !== false, 'performer_without_placement_is_refused');

// 5. A managed request with no disposition cannot be assigned at all.
mag_feature(true);
$result = $care->assignVisitPerformer(102, 31, 201);
mag_expect(empty($result['ok']) && strpos((string) $result['message'], 'belum menetapkan kunjungan') !== false, 'managed_request_without_disposition_is_refused');
mag_expect((int) $db->where('request_id', 102)->count_all_results('request_visit_performer_assignments') === 0, 'refused_managed_assignment_writes_nothing');
mag_feature(false);

// 6. An enrolled request whose disposition declined a visit cannot be assigned.
$result = $care->assignVisitPerformer(103, 31, 201);
mag_expect(empty($result['ok']) && strpos((string) $result['message'], 'belum menetapkan kunjungan') !== false, 'non_visit_disposition_is_refused');

// The guard must never bypass the pre-existing actor authority.
$result = $care->assignVisitPerformer(101, 31, 202);
mag_expect(empty($result['ok']), 'non_responsible_doctor_still_refused');

if ($db && method_exists($db, 'close')) { $db->close(); }
$db = null;
if ($admin && $database !== '' && preg_match('/\Adoclinc_managed_guard_test_[a-f0-9]{10}\z/', $database) === 1) {
	try { $admin->query('DROP DATABASE ' . mag_identifier($database)); } catch (Throwable $ignored) { $failed++; }
}
if ($admin) { $admin->close(); }

echo "MANAGED_ASSIGNMENT_GUARD_PASSED={$passed}\nMANAGED_ASSIGNMENT_GUARD_FAILED={$failed}\nMANAGED_ASSIGNMENT_GUARD_SKIPPED=0\n";
exit($failed === 0 ? 0 : 1);



