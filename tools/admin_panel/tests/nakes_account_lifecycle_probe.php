<?php
/**
 * Probe siklus hidup akun nakes: penempatan, aktivasi, dan jalur audit.
 *
 * Yang dibuktikan
 * ---------------
 *   A. invarian data & pagar statis (baca saja);
 *   B. jalur audit benar-benar menulis: `nonaktifkan_user` lalu `aktifkan_user`
 *      dijalankan DI DALAM transaksi, hasilnya diperiksa, lalu di-ROLLBACK
 *      sehingga akun kembali seperti semula.
 *
 * `create_dokter_nakes()` sengaja TIDAK dijalankan: ia membuka transaksinya
 * sendiri, sehingga transaksi probe akan ikut ter-commit. Instrumentasinya
 * diperiksa dari sumber (A5/A6).
 *
 * Jalankan:
 *   docker exec -e ALLOW_STAGE_PROBE=1 doclinc-php8100 \
 *     php /var/www/html/tools/admin_panel/tests/nakes_account_lifecycle_probe.php
 */
$root = dirname(__DIR__, 3) . '/';
define('BASEPATH', $root . 'system/');
define('APPPATH', $root . 'admin_menu/application/');
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

/* Stub minimal supaya model panel dapat dipakai di luar CodeIgniter penuh. */
class ProbeConfig { public function item($key = '') { return null; } }
class ProbeSession { public function userdata($key = null) { return null; } }
class ProbeInput
{
	public function ip_address() { return '127.0.0.1'; }
	public function user_agent() { return 'nakes-account-lifecycle-probe'; }
}
class ProbeLoader
{
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function database($name = '', $return = false) { return $this->db; }
	public function model($name = '') { return null; }
	public function library($name = '') { return null; }
}
class ProbeApp
{
	public $db; public $config; public $session; public $input; public $load;
	public function __construct($db)
	{
		$this->db = $db;
		$this->config = new ProbeConfig();
		$this->session = new ProbeSession();
		$this->input = new ProbeInput();
		$this->load = new ProbeLoader($db);
	}
}
$GLOBALS['probe_app'] = new ProbeApp($db);
if (!function_exists('get_instance')) { function &get_instance() { return $GLOBALS['probe_app']; } }
/* Model panel meng-extend MX_Controller, jadi bentuk minimalnya perlu ada. */
class MX_Controller
{
	public $db; public $config; public $session; public $input; public $load;
	public function __construct()
	{
		$app = isset($GLOBALS['probe_app']) ? $GLOBALS['probe_app'] : null;
		if ($app === null) { return; }
		$this->db = $app->db;
		$this->config = $app->config;
		$this->session = $app->session;
		$this->input = $app->input;
		$this->load = $app->load;
	}
}
require_once BASEPATH . 'core/Model.php';
require_once APPPATH . 'modules/kelola_dokter_nakes/models/Kelola_dokter_nakes_m.php';
$model = new Kelola_dokter_nakes_m();

$fails = 0;
function check($label, $cond, $detail = '')
{
	global $fails;
	if ($cond) { echo "PASS  {$label}\n"; return; }
	$fails++;
	echo "FAIL  {$label}" . ($detail === '' ? '' : " [{$detail}]") . "\n";
}

function audit_count($db, $action, $entity_id = null)
{
	$db->where('action', $action);
	if ($entity_id !== null) { $db->where('entity_id', (string) $entity_id); }
	return $db->count_all_results('audit_logs');
}

function account_row($db, $user_id)
{
	return $db->where('userId', (int) $user_id)->get('users')->row();
}

/* ===================== A. invarian data & pagar statis ===================== */
$active_codes = array();
$rows = $db->query('SELECT kode_pkm FROM m_puskesmas WHERE status = "aktif" AND kode_pkm <> "DEFAULT"');
foreach ($rows->result() as $row) { $active_codes[] = trim((string) $row->kode_pkm); }

$nakes = $db->query('SELECT userId, username, status, remark, must_change_password, password_changed_at FROM users WHERE role = "dokter"')->result();
$bad_placement = array();
$contradict = array();
foreach ($nakes as $row) {
	$remark = trim((string) $row->remark);
	if ($remark === '' || !in_array($remark, $active_codes, true)) {
		$bad_placement[] = $row->userId . ':' . ($remark === '' ? '(kosong)' : $remark);
	}
	if ((int) $row->must_change_password === 1 && $row->password_changed_at !== null) {
		$contradict[] = (string) $row->userId;
	}
}
check('A1 setiap akun role=dokter menunjuk Puskesmas aktif',
	count($nakes) > 0 && empty($bad_placement),
	'akun=' . count($nakes) . ' menyimpang=' . (empty($bad_placement) ? 'none' : implode(',', $bad_placement)));

/* Penempatan juga disimpan di `puskesmas_staff.kode_pkm`. Migrasi
   20261023000500 hanya memperbaiki `users.remark`, sehingga dua sumber ini bisa
   berbeda; keduanya diperiksa supaya tidak ada staf yang tertahan karena
   menunjuk Puskesmas yang sudah nonaktif. */
$staff_rows = $db->query('SELECT staff_id, kode_pkm, status FROM puskesmas_staff ORDER BY staff_id')->result();
$bad_staff = array();
foreach ($staff_rows as $row) {
	$kode = trim((string) $row->kode_pkm);
	if ($kode === '' || !in_array($kode, $active_codes, true)) {
		$bad_staff[] = $row->staff_id . ':' . ($kode === '' ? '(kosong)' : $kode);
	}
}
check('A1b setiap staf Puskesmas menunjuk Puskesmas aktif',
	empty($bad_staff),
	'staf=' . count($staff_rows) . ' menyimpang=' . (empty($bad_staff) ? 'none' : implode(',', $bad_staff)));
check('A2 kode Puskesmas nonaktif ditolak model', $model->puskesmas_exists('PKM11') === false);
check('A3 kode Puskesmas aktif diterima model', $model->puskesmas_exists('10280101') === true);
check('A4 tidak ada akun must_change_password=1 dengan password_changed_at terisi',
	empty($contradict), 'menyimpang=' . (empty($contradict) ? 'none' : implode(',', $contradict)));

$model_src = (string) file_get_contents(APPPATH . 'modules/kelola_dokter_nakes/models/Kelola_dokter_nakes_m.php');
check('A5 pembuatan akun menyetel must_change_password=1',
	strpos($model_src, "'must_change_password' => 1") !== false);
$actions = array(
	'admin_create_puskesmas_nakes_user',
	'admin_enable_puskesmas_nakes_user',
	'admin_disable_puskesmas_nakes_user',
	'admin_update_puskesmas_nakes_user',
	'admin_reset_puskesmas_nakes_password',
	'admin_destroy_puskesmas_nakes_user',
);
$missing = array();
foreach ($actions as $action) {
	if (strpos($model_src, $action) === false) { $missing[] = $action; }
}
check('A6 enam aksi siklus hidup akun terinstrumentasi audit',
	empty($missing), 'hilang=' . (empty($missing) ? 'none' : implode(',', $missing)));

$signup_path = $root . 'application/modules/sign_up/controllers/Sign_up.php';
$signup_src = is_file($signup_path) ? (string) file_get_contents($signup_path) : '';
/* Spasi di sekitar `=>` tidak distandarkan di repo, jadi dinormalkan dulu. */
$signup_norm = preg_replace('/\s+/', '', $signup_src);
check('A7 pendaftaran publik memaku role=warga',
	$signup_norm !== '' && strpos($signup_norm, "'role'=>'warga'") !== false,
	$signup_src === '' ? 'berkas tidak ditemukan' : 'pola role=warga tidak ada');
check('A8 pendaftaran publik tidak membaca role dari POST',
	$signup_norm !== '' && strpos($signup_norm, "post('role'") === false
		&& strpos($signup_norm, 'post("role"') === false);

$config_src = (string) file_get_contents($root . 'application/config/config.php');
check('A9 config utama menyambungkan flag ganti sandi login pertama',
	strpos($config_src, 'first_login_password_change_enabled') !== false
		&& strpos($config_src, 'DOCLINC_FIRST_LOGIN_PASSWORD_CHANGE_ENABLED') !== false);
$login_src = (string) file_get_contents($root . 'application/modules/login/controllers/Login.php');
check('A10 Login.php menegakkan flag ganti sandi login pertama',
	strpos($login_src, "item('first_login_password_change_enabled')") !== false
		&& strpos($login_src, 'must_change_password') !== false);

/* ============ B. jalur audit dijalankan di dalam transaksi ============== */
$target = null;
foreach ($nakes as $row) {
	if ((string) $row->username === 'NAKES_VISIT_LOCAL') { $target = $row; break; }
}
if ($target === null && !empty($nakes)) { $target = $nakes[0]; }
if ($target === null) {
	check('B0 ada akun nakes untuk diuji', false, 'tidak ada baris users role=dokter');
	echo "\nPROBE_RESULT=FAIL (gagal={$fails})\n";
	exit(1);
}
$target_id = (int) $target->userId;
$baseline_status = (string) $target->status;
$baseline_remark = (string) $target->remark;
$baseline_audit = (int) $db->count_all_results('audit_logs');
$baseline_disable = audit_count($db, 'admin_disable_puskesmas_nakes_user', $target_id);

$db->query('START TRANSACTION');
$disabled = $model->nonaktifkan_user($target_id, 'probe', $baseline_remark);
$after_disable = account_row($db, $target_id);
check('B1 nonaktifkan_user mengubah status menjadi nonaktif',
	$disabled !== false && $after_disable && (string) $after_disable->status === 'nonaktif',
	'status=' . ($after_disable ? (string) $after_disable->status : 'null'));
check('B2 audit admin_disable_puskesmas_nakes_user bertambah satu',
	audit_count($db, 'admin_disable_puskesmas_nakes_user', $target_id) === $baseline_disable + 1,
	'sebelum=' . $baseline_disable . ' sesudah=' . audit_count($db, 'admin_disable_puskesmas_nakes_user', $target_id));
check('B3 penempatan remark tidak terhapus saat dinonaktifkan',
	$after_disable && (string) $after_disable->remark === $baseline_remark,
	'remark=' . ($after_disable ? (string) $after_disable->remark : 'null'));

$enabled = $model->aktifkan_user($target_id, 'probe', $baseline_remark);
$after_enable = account_row($db, $target_id);
check('B4 aktifkan_user mengubah status menjadi aktif',
	$enabled !== false && $after_enable && (string) $after_enable->status === 'aktif',
	'status=' . ($after_enable ? (string) $after_enable->status : 'null'));
check('B5 audit admin_enable_puskesmas_nakes_user bertambah satu',
	audit_count($db, 'admin_enable_puskesmas_nakes_user', $target_id) === 1);
check('B6 baris audit baru memakai entity_type=users',
	(int) $db->where('action', 'admin_enable_puskesmas_nakes_user')
		->where('entity_id', (string) $target_id)
		->where('entity_type', 'users')
		->count_all_results('audit_logs') === 1);

$db->query('ROLLBACK');
$restored = account_row($db, $target_id);
check('B7 rollback mengembalikan status dan penempatan akun',
	$restored
		&& (string) $restored->status === $baseline_status
		&& (string) $restored->remark === $baseline_remark,
	'status=' . ($restored ? (string) $restored->status : 'null'));
check('B8 rollback mengembalikan jumlah baris audit_logs',
	(int) $db->count_all_results('audit_logs') === $baseline_audit,
	'sebelum=' . $baseline_audit . ' sesudah=' . (int) $db->count_all_results('audit_logs'));

echo "\n" . ($fails === 0 ? 'PROBE_RESULT=PASS' : "PROBE_RESULT=FAIL (gagal={$fails})") . "\n";
exit($fails === 0 ? 0 : 1);
