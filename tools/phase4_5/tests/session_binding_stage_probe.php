<?php
/**
 * Probe sesi tunggal per akun (audit error handler).
 *
 * Membuktikan semantik inti: login kedua mencabut sesi pertama. Semua tulisan
 * dibungkus transaksi luar dan di-ROLLBACK, jadi tabel user_login_bindings
 * kembali ke kondisi semula (dibuktikan di akhir).
 *
 * Jalankan di dalam container PHP:
 *   docker exec -e ALLOW_STAGE_PROBE=1 doclinc-php8100 \
 *     php /var/www/html/tools/phase4_5/tests/session_binding_stage_probe.php
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

class ProbeConfig { private $i; public function __construct(array $i) { $this->i = $i; } public function item($k) { return $this->i[$k] ?? null; } public function set($k, $v) { $this->i[$k] = $v; } }
class ProbeLoader { private $db; public function __construct($db) { $this->db = $db; } public function database($g = 'default', $r = false) { return $this->db; } public function helper($n) { foreach ((array) $n as $h) { $f = APPPATH . 'helpers/' . $h . '_helper.php'; if (is_file($f)) require_once $f; } return $this; } }
class ProbeSession { public function userdata($k) { return null; } }
class ProbeApp { public $db; public $load; public $config; public $session; public function __construct($db) { $this->db = $db; $this->load = new ProbeLoader($db); $this->config = new ProbeConfig(array()); $this->session = new ProbeSession(); } }
$GLOBALS['probe_app'] = new ProbeApp($db);
if (!function_exists('get_instance')) { function &get_instance() { return $GLOBALS['probe_app']; } }

require_once APPPATH . 'libraries/Session_binding_service.php';

$fails = 0;
function check($label, $cond, $detail = '') { global $fails; if ($cond) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label  $detail\n"; } }
function one($db, $sql, $binds = array()) { $r = $db->query($sql, $binds); if ($r === false) { fwrite(STDERR, 'SQL_ERROR: ' . $db->error('message') . "\n"); exit(3); } return $r->row(); }

echo "== DB: {$name} ==\n";
$target = one($db, "SELECT userId, username, password, status FROM users WHERE username='pkm_cilegon' LIMIT 1");
if (!$target || (string) $target->status !== 'aktif') { fwrite(STDERR, "refuse: akun uji pkm_cilegon tidak aktif\n"); exit(3); }
$userId = (int) $target->userId;
$hash = (string) $target->password;

$service = new Session_binding_service($db);
check('0 schema user_login_bindings siap', $service->schemaReady() === true);

$baseline = one($db, 'SELECT token_hash, revoked_at FROM user_login_bindings WHERE user_id=?', array($userId));
echo 'baseline: ' . ($baseline ? 'token=' . substr((string) $baseline->token_hash, 0, 8) . ' revoked=' . var_export($baseline->revoked_at, true) : 'tanpa baris') . "\n";

$db->trans_begin();
$tokenA = $service->issue($userId, $hash);
check('A1 login perangkat A menerbitkan token', is_string($tokenA) && strlen($tokenA) === 64);
check('A2 token A valid', $service->validate($userId, $tokenA) === true);
check('A3 token salah ditolak', $service->validate($userId, str_repeat('a', 64)) === false);
check('A4 token kosong ditolak', $service->validate($userId, null) === false);

$tokenB = $service->issue($userId, $hash);
check('B1 login perangkat B menerbitkan token baru', is_string($tokenB) && $tokenB !== $tokenA);
check('B2 token B valid', $service->validate($userId, $tokenB) === true);
check('B3 SESI PERANGKAT A DICABUT', $service->validate($userId, $tokenA) === false);
$row = one($db, 'SELECT COUNT(*) AS n FROM user_login_bindings WHERE user_id=?', array($userId));
check('B4 satu baris binding per akun', (int) $row->n === 1, 'rows=' . (int) $row->n);

check('C1 logout mencabut token aktif', $service->revokeCurrent($userId, $tokenB) === true);
check('C2 token setelah logout tidak valid', $service->validate($userId, $tokenB) === false);
check('C3 revoke ulang dengan token sama ditolak', $service->revokeCurrent($userId, $tokenB) === false);

$tokenD = $service->issue($userId, $hash);
check('D1 login ulang setelah logout berhasil', is_string($tokenD));
check('D2 token baru valid', $service->validate($userId, $tokenD) === true);
check('D3 hash kata sandi salah ditolak', $service->issue($userId, 'bukan-hash') === null);

/* Diagnosa sebab: dasar pesan yang jujur kepada pengguna (bukan sekadar
 * "sesi berakhir"). Dipetakan gate menjadi reason=other_device/signed_out. */
$tokenE = $service->issue($userId, $hash);
check('F1 diagnose binding aktif', $service->diagnose($userId, $tokenE) === 'binding_valid');
$tokenF = $service->issue($userId, $hash);
check('F2 diagnose login perangkat lain = superseded', $service->diagnose($userId, $tokenE) === 'binding_superseded');
check('F3 diagnose token hilang', $service->diagnose($userId, null) === 'token_missing');
check('F4 diagnose token cacat bentuk', $service->diagnose($userId, 'bukan-token') === 'token_missing');
$service->revokeCurrent($userId, $tokenF);
check('F5 diagnose setelah logout = revoked', $service->diagnose($userId, $tokenF) === 'binding_revoked');
$db->query('DELETE FROM user_login_bindings WHERE user_id=?', array($userId));
check('F6 diagnose tanpa baris binding = missing', $service->diagnose($userId, $tokenF) === 'binding_missing');

/* Negosiasi isi jawaban gate: JSON untuk pemanggil skrip, redirect untuk
 * navigasi. fetch() tanpa header khusus dikenali dari Sec-Fetch-Mode. */
require_once APPPATH . 'libraries/First_login_gate_policy.php';
$policy = new First_login_gate_policy();
check('G1 header ajax => JSON', $policy->jsonResponse('chat', 'messages', true, '', '', '') === true);
check('G2 Accept JSON => JSON', $policy->jsonResponse('chat', 'messages', false, 'application/json', '', '') === true);
check('G3 Sec-Fetch-Mode cors => JSON', $policy->jsonResponse('chat', 'messages', false, '', '', 'cors') === true);
check('G4 Sec-Fetch-Mode same-origin => JSON', $policy->jsonResponse('home', 'index', false, '', '', 'same-origin') === true);
check('G5 navigasi => redirect', $policy->jsonResponse('home', 'index', false, 'text/html', '', 'navigate') === false);
check('G6 rute AJAX terdaftar => JSON', $policy->jsonResponse('notifikasi', 'list_json', false, '', '', '') === true);
check('G7 rute tak terdaftar tanpa petunjuk => redirect', $policy->jsonResponse('home', 'index', false, 'text/html', '', '') === false);

/* Adopsi sesi lama saat rollout. Fail-closed: hanya boleh ketika akun belum
 * punya baris binding sama sekali; bila baris sudah ada, sesi lama kalah. */
require_once APPPATH . 'libraries/Session_binding_adoption_policy.php';
$adopt = new Session_binding_adoption_policy();
check('H1 saklar mati => tidak adopsi', $adopt->shouldAdopt(false, 'binding_missing') === false);
check('H2 binding_missing + saklar hidup => adopsi', $adopt->shouldAdopt(true, 'binding_missing') === true);
check('H3 binding_superseded => tidak adopsi', $adopt->shouldAdopt(true, 'binding_superseded') === false);
check('H4 binding_revoked => tidak adopsi', $adopt->shouldAdopt(true, 'binding_revoked') === false);
check('H5 token_missing (baris sudah ada) => tidak adopsi', $adopt->shouldAdopt(true, 'token_missing') === false);
check('H6 account_inactive => tidak adopsi', $adopt->shouldAdopt(true, 'account_inactive') === false);
$tokenG = $service->issue($userId, null);
check('H7 issue tanpa hash kata sandi (jalur adopsi)', is_string($tokenG) && strlen($tokenG) === 64);
check('H8 token adopsi langsung valid', $service->validate($userId, $tokenG) === true);

/* Bentuk jawaban gate: keputusan yang sebelumnya menempel di hook dan tidak
 * bisa diuji. Sekarang murni, jadi bisa diperiksa satu per satu. */
require_once APPPATH . 'libraries/Session_binding_response_policy.php';
$response = new Session_binding_response_policy();
check('I1 superseded => other_device', $response->reasonCode('binding_superseded') === 'other_device');
check('I2 revoked => signed_out', $response->reasonCode('binding_revoked') === 'signed_out');
check('I3 nonaktif => account_inactive', $response->reasonCode('account_inactive') === 'account_inactive');
check('I4 binding hilang => expired', $response->reasonCode('binding_missing') === 'expired');
check('I5 token hilang => expired', $response->reasonCode('token_missing') === 'expired');
check('I6 sebab tak dikenal => expired', $response->reasonCode('apa-pun') === 'expired');
check('I7 POST non-AJAX kehilangan isian', $response->isUnsaved(false, 'POST') === true);
check('I8 POST huruf kecil tetap dihitung', $response->isUnsaved(false, 'post') === true);
check('I9 POST AJAX tidak dihitung', $response->isUnsaved(true, 'POST') === false);
check('I10 GET tidak dihitung', $response->isUnsaved(false, 'GET') === false);
check('I11 target redirect dasar', $response->redirectTarget('other_device', false) === 'login?session=expired&reason=other_device');
check('I12 target redirect + isian hilang', $response->redirectTarget('other_device', true) === 'login?session=expired&reason=other_device&unsaved=1');
check('I13 sebab di-encode', $response->redirectTarget('a b&c', false) === 'login?session=expired&reason=a%20b%26c');
$body = $response->jsonBody('other_device');
check('I14 badan JSON mempertahankan kode lama', $body['safe_error_code'] === 'session_expired' && $body['success'] === false && $body['reason'] === 'other_device');
check('I15 nama cookie di-trim', $response->sessionCookieName('  cilegon_bersatu_session  ') === 'cilegon_bersatu_session');
check('I16 nama cookie kosong tetap kosong', $response->sessionCookieName('   ') === '');
/* Audit pencabutan harus ada di jalur AJAX juga: deteksi pertama hampir selalu
 * datang dari polling, bukan navigasi (terbukti di staging 2026-10-23). */
$audit_ajax = $response->auditMetadata('other_device', 'home', 'livekit_incoming_call', true);
check('I17 audit jalur AJAX diberi channel json', $audit_ajax['channel'] === 'json');
check('I18 audit jalur halaman diberi channel html', $response->auditMetadata('other_device', 'chat', 'index', false)['channel'] === 'html');
check('I19 audit mempertahankan sebab dan asal permintaan',
	$audit_ajax['reason'] === 'other_device'
	&& $audit_ajax['controller'] === 'home'
	&& $audit_ajax['method'] === 'livekit_incoming_call');
check('I20 audit dapat di-encode JSON',
	json_encode($audit_ajax) === '{"reason":"other_device","controller":"home","method":"livekit_incoming_call","channel":"json"}');
check('I21 sebab kosong tetap jadi string', $response->auditMetadata(null, null, null, false)['reason'] === '');

$db->trans_rollback();

$after = one($db, 'SELECT token_hash, revoked_at FROM user_login_bindings WHERE user_id=?', array($userId));
$same = ($baseline === null && $after === null)
	|| ($baseline !== null && $after !== null
		&& (string) $baseline->token_hash === (string) $after->token_hash
		&& (string) $baseline->revoked_at === (string) $after->revoked_at);
check('E1 tabel binding kembali seperti semula', $same, json_encode(array($baseline, $after)));

echo "\nPROBE_RESULT=" . ($fails === 0 ? 'PASS' : 'FAIL') . " (gagal=$fails)\n";
exit($fails === 0 ? 0 : 1);
