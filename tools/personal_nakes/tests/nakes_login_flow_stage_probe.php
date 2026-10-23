<?php
/**
 * Probe alur masuk dan aktivasi akun nakes.
 *
 * Yang dibuktikan
 * ---------------
 *   1. Penahanan login pertama TIDAK bergantung pada saklar fitur
 *      `nakes_credential_enforcement_enabled`. Ini cacat yang ditemukan
 *      2026-10-24: selama saklar itu mati, nakes yang melewati halaman aktivasi
 *      bisa masuk memakai password sementara dari Admin tanpa pernah diminta
 *      menggantinya.
 *   2. Kontrak jawaban permukaan nakes: kode "3" berarti kredensial benar tetapi
 *      langkah ganti password belum selesai.
 *   3. Akun yang belum lengkap (mis. belum tertaut data staf Puskesmas) tidak
 *      diputar dalam lingkaran: pesannya ditulis lebih dulu, baru sesinya ditutup.
 *
 * Jalankan:
 *   docker exec -e ALLOW_STAGE_PROBE=1 doclinc-php8100 \
 *     php /var/www/html/tools/personal_nakes/tests/nakes_login_flow_stage_probe.php
 */
$root = dirname(__DIR__, 3) . '/';
define('BASEPATH', $root . 'system/');
define('APPPATH', $root . 'application/');
define('FCPATH', $root);
define('ENVIRONMENT', 'testing');
$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
$port = (int) (getenv('DB_PORT') ?: 3306);
if (getenv('ALLOW_STAGE_PROBE') !== '1') { fwrite(STDERR, "refuse: set ALLOW_STAGE_PROBE=1\n"); exit(2); }
if (stripos($name, 'stage') === false) { fwrite(STDERR, "refuse: bukan database stage ($name)\n"); exit(2); }
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
$db = DB(array(
	'dsn' => '', 'hostname' => $host, 'username' => $user, 'password' => $pass,
	'database' => $name, 'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false,
	'db_debug' => false, 'cache_on' => false, 'cachedir' => '', 'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci', 'port' => $port, 'stricton' => true,
), true);
if (!$db) { fwrite(STDERR, "refuse: koneksi gagal\n"); exit(2); }

/* Stub config: sengaja mengembalikan null, artinya saklar fitur MATI — justru
   kondisi yang dulu membuat penahanan login pertama hilang. */
class ProbeConfig
{
	private $items;
	public function __construct($items = array()) { $this->items = $items; }
	public function item($key = '') { return $this->items[$key] ?? null; }
}
class ProbeApp
{
	public $db; public $config;
	public function __construct($db, $config) { $this->db = $db; $this->config = $config; }
}
$GLOBALS['probe_app'] = new ProbeApp($db, new ProbeConfig());
if (!function_exists('get_instance')) { function &get_instance() { return $GLOBALS['probe_app']; } }
require_once APPPATH . 'helpers/nakes_credential_enforcement_helper.php';

$fails = 0;
function check($label, $cond, $detail = '')
{
	global $fails;
	if ($cond) { echo "PASS  {$label}\n"; return; }
	$fails++;
	echo "FAIL  {$label}" . ($detail === '' ? '' : " [{$detail}]") . "\n";
}

$controller = (string) file_get_contents(APPPATH . 'modules/login_nakes/controllers/Login_nakes.php');
$loginView = (string) file_get_contents(APPPATH . 'modules/login_nakes/views/login_nakes_v.php');
$gate = (string) file_get_contents(APPPATH . 'hooks/Password_change_gate.php');

function method_body($source, $name, $next = '')
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) { return ''; }
	$end = $next === '' ? strlen($source) : strpos($source, 'function ' . $next . '(', $start + 1);
	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

/* ============ 1. Penahanan login pertama tidak bergantung saklar ========== */
check('N1 saklar enforcement memang mati di probe ini',
	doclinc_nakes_credential_enforcement_policy()->enabled() === false);
check('N2 nilai efektif lama tetap 0 saat saklar mati (cacat yang diperbaiki)',
	(int) doclinc_nakes_effective_must_change_password('dokter', 1, null) === 0);
check('N3 penahanan login pertama tetap berlaku saat saklar mati',
	doclinc_nakes_first_login_gate_required('dokter', 1) === true);
check('N4 akun nakes yang sudah mengganti password tidak ditahan',
	doclinc_nakes_first_login_gate_required('dokter', 0) === false);
check('N5 role non-nakes tidak ikut ditahan',
	doclinc_nakes_first_login_gate_required('warga', 1) === false
		&& doclinc_nakes_first_login_gate_required('admin', 1) === false);

/* ===================== 2. Kontrak jawaban & pemasangan =================== */
$auth_body = method_body($controller, 'auth', 'role_label');
check('N6 auth() memakai penahanan login pertama',
	strpos($auth_body, 'doclinc_nakes_first_login_gate_required(') !== false);
check('N7 auth() menjawab kode "3" saat password masih password sementara',
	strpos($auth_body, 'echo $must_change_password === 1 ? "3" : "1";') !== false);
check('N8 halaman masuk mengantar kode "3" ke langkah ganti password',
	strpos($loginView, "result == '3'") !== false
		&& strpos($loginView, "site_url('login-nakes/change-password')") !== false);
check('N9 gate sesi ikut memakai penahanan yang sama',
	strpos($gate, 'doclinc_nakes_first_login_gate_required(') !== false);

/* ============ 3. Halaman ganti password tidak terkunci oleh saklar ======= */
$context_body = method_body($controller, 'password_change_context', 'password_change_failure');
/* Yang diperiksa bentuk kode `$this->config->item(...)`, bukan kata di komentar. */
$context_code = preg_replace('#/\\*.*?\\*/#s', '', $context_body);
check('N10 konteks ganti password tidak lagi mensyaratkan saklar enforcement',
	$context_body !== ''
		&& strpos($context_code, "config->item('nakes_credential_enforcement_enabled')") === false);
check('N11 konteks tetap mensyaratkan role dokter dan sesi aktif',
	strpos($context_body, "'role') === 'dokter'") !== false
		&& strpos($context_body, "'logged_in'") !== false);

/* ============ 4. Akun belum lengkap: pesan dulu, baru sesi ditutup ======= */
$change_body = method_body($controller, 'change_password', 'update_password');
$flash_pos = strpos($change_body, "set_flashdata('login_success'");
$destroy_pos = strpos($change_body, 'sess_destroy()');
check('N12 sebab ditulis sebelum sesi ditutup (tidak hilang)',
	$flash_pos !== false && $destroy_pos !== false && $flash_pos < $destroy_pos,
	'flash=' . var_export($flash_pos, true) . ' destroy=' . var_export($destroy_pos, true));
check('N13 pesan akun belum lengkap menyebut siapa yang dapat memperbaiki',
	strpos($change_body, 'Hubungi Admin Dinas Kesehatan') !== false);

/* ===================== 5. Keadaan data staging saat ini ================== */
$rows = $db->query("SELECT userId, username, status, remark, must_change_password, password_changed_at FROM users WHERE role = 'dokter' ORDER BY userId")->result();
$blocked = array();
$legacy = array();
foreach ($rows as $row) {
	if (doclinc_nakes_first_login_gate_required('dokter', (int) $row->must_change_password)) {
		$blocked[] = (string) $row->userId;
	}
	$link = (int) $db->where('user_id', (int) $row->userId)->where('status', 'aktif')->count_all_results('puskesmas_staff');
	if ($link === 0) { $legacy[] = (string) $row->userId; }
}
check('N14 jumlah akun nakes terbaca', count($rows) > 0, 'akun=' . count($rows));
check('N15 tidak ada akun yang tertahan saat probe dijalankan (perubahan ini tidak mengunci siapa pun hari ini)',
	empty($blocked), 'tertahan=' . (empty($blocked) ? 'none' : implode(',', $blocked)));
echo 'INFO  akun nakes tanpa catatan staf aktif: ' . (empty($legacy) ? 'none' : implode(',', $legacy)) . "\n";

/* ============ 6. Pembatasan percobaan masuk (Nakes_login_throttle) ======= */
require_once APPPATH . 'libraries/Nakes_login_throttle.php';
$throttle_config = array(
	'enabled' => true,
	'max_failures' => 3,
	'ip_max_failures' => 5,
	'window_seconds' => 900,
);
$throttle = new Nakes_login_throttle($db, $throttle_config);
check('T1 sumber hitungan tersedia', $throttle->failSafe() === true);
check('T2 tanpa kegagalan, masuk diizinkan',
	$throttle->state('203.0.113.10', 9002)['allowed'] === true);

$baseline_audit = (int) $db->count_all_results('audit_logs');
$db->query('START TRANSACTION');
$now = time();
$insert = $db->query(
	"INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, ip_address, metadata_json, created_at)
	 VALUES (NULL, 'login_failed', 'auth', '9002', '203.0.113.10', '{}', ?)",
	array(date('Y-m-d H:i:s', $now - 60))
);
$throttle->state('203.0.113.10', 9002);
for ($i = 0; $i < 3; $i++) {
	$db->query(
		"INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, ip_address, metadata_json, created_at)
		 VALUES (NULL, 'login_failed', 'auth', '9002', '203.0.113.10', '{}', ?)",
		array(date('Y-m-d H:i:s', $now - 30 + $i))
	);
}
$blocked_state = $throttle->state('203.0.113.10', 9002);
check('T3 akun diblokir setelah batas kegagalan terlampaui',
	$blocked_state['allowed'] === false && $blocked_state['scope'] === 'account',
	'allowed=' . var_export($blocked_state['allowed'], true) . ' scope=' . $blocked_state['scope']);
check('T4 sisa waktu tunggu masuk akal',
	$blocked_state['retry_after_seconds'] > 0 && $blocked_state['retry_after_seconds'] <= 900,
	'retry_after=' . $blocked_state['retry_after_seconds']);
check('T5 akun lain di alamat IP yang sama tetap boleh masuk',
	$throttle->state('203.0.113.10', 9003)['allowed'] === true);

$db->query(
	"INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, ip_address, metadata_json, created_at)
	 VALUES (NULL, 'login_failed', 'auth', NULL, '198.51.100.7', '{}', ?)",
	array(date('Y-m-d H:i:s', $now - 20))
);
for ($i = 1; $i < $throttle_config['ip_max_failures']; $i++) {
	$db->query(
		"INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, ip_address, metadata_json, created_at)
		 VALUES (NULL, 'login_failed', 'auth', NULL, '198.51.100.7', '{}', ?)",
		array(date('Y-m-d H:i:s', $now - 20 + $i))
	);
}
$ip_state = $throttle->state('198.51.100.7', 0);
check('T6 nama pengguna tak dikenal tetap ditahan lewat batas alamat IP',
	$ip_state['allowed'] === false && $ip_state['scope'] === 'ip',
	'allowed=' . var_export($ip_state['allowed'], true) . ' scope=' . $ip_state['scope']
		. ' failures=' . $ip_state['failures']);

$db->query('ROLLBACK');
check('T7 rollback mengembalikan jumlah baris audit_logs',
	(int) $db->count_all_results('audit_logs') === $baseline_audit,
	'sebelum=' . $baseline_audit . ' sesudah=' . (int) $db->count_all_results('audit_logs'));

$disabled = new Nakes_login_throttle($db, array('enabled' => false));
check('T8 saat pembatasan dimatikan, masuk selalu diizinkan',
	$disabled->state('203.0.113.10', 9002)['allowed'] === true);

$window = new Nakes_login_throttle($db, array('enabled' => true, 'max_failures' => 1, 'window_seconds' => 60));
$db->query('START TRANSACTION');
$db->query(
	"INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, ip_address, metadata_json, created_at)
	 VALUES (NULL, 'login_failed', 'auth', '9005', '203.0.113.99', '{}', ?)",
	array(date('Y-m-d H:i:s', time() - 3600))
);
check('T9 kegagalan di luar jendela waktu tidak dihitung',
	$window->state('203.0.113.99', 9005)['allowed'] === true);
$db->query('ROLLBACK');

/* ============ 7. Pesan aktivasi berkeadaan (kode, bukan kalimat) ========= */
$activation_body = method_body($controller, 'activate_auth', 'change_password');
$activation_view = (string) file_get_contents(APPPATH . 'modules/login_nakes/views/activate_nakes_v.php');
preg_match_all("/activation_failure\\('([^']*)'\\)/", $activation_body, $codes);
$code_list = $codes[1] ?? array();
$sentences = array_values(array_filter($code_list, function ($code) {
	return strpos($code, ' ') !== false;
}));
check('V1 seluruh kegagalan aktivasi dikirim sebagai kode, bukan kalimat',
	!empty($code_list) && empty($sentences),
	'kode=' . (empty($code_list) ? 'none' : implode(',', $code_list)) . ' kalimat=' . (empty($sentences) ? 'none' : implode('|', $sentences)));
check('V2 sebab rinci hanya dipakai setelah password terbukti benar',
	strpos($activation_body, '$password_ok') !== false
		&& strpos($activation_body, '$password_ok') < strpos($activation_body, "'account_inactive'"),
	'posisi password_ok=' . var_export(strpos($activation_body, '$password_ok'), true));
check('V3 password salah tetap satu jawaban generik tanpa sebab rinci',
	substr_count($activation_body, "activation_failure('credential_mismatch')") >= 1
		&& substr_count($activation_body, "array('area' => 'activation'))") === 1,
	'generik=' . substr_count($activation_body, "array('area' => 'activation'))"));
check('V4 halaman memetakan kode ke kalimat Indonesia',
	strpos($activation_view, '$activation_messages') !== false
		&& strpos($activation_view, "'credential_mismatch' =>") !== false
		&& strpos($activation_view, "'already_activated' =>") !== false
		&& strpos($activation_view, "'identity_incomplete' =>") !== false);
check('V5 akun yang sudah aktivasi diberi jalan masuk, bukan jalan buntu',
	strpos($activation_view, "\$error_code === 'already_activated'") !== false
		&& strpos($activation_view, "site_url('login-nakes')") !== false);
check('V6 langkah aktivasi disebut sebagai langkah 1 dari 2',
	strpos($activation_view, 'Langkah 1 dari 2') !== false);

echo "\n" . ($fails === 0 ? 'PROBE_RESULT=PASS' : "PROBE_RESULT=FAIL (gagal={$fails})") . "\n";
exit($fails === 0 ? 0 : 1);
