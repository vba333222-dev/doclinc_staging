<?php
/**
 * Probe gateway kapabilitas admin.
 *
 * Memanggil gateway tanpa membuka aplikasi supaya yang diuji adalah lapisan
 * kapabilitas yang sebenarnya (flag + policy + service), bukan tiruan.
 *
 * Jalankan:
 *   docker exec doclinc-php8100 \
 *     php /var/www/html/tools/admin_panel/tests/admin_capability_gateway_probe.php
 */
$root = dirname(__DIR__, 3) . '/';
define('BASEPATH', $root . 'system/');
define('APPPATH', $root . 'admin_menu/application/');
define('FCPATH', $root);
define('ENVIRONMENT', 'testing');
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
$db = DB(array(
	'dsn' => '', 'hostname' => getenv('DB_HOST') ?: '127.0.0.1', 'username' => getenv('DB_USER') ?: '',
	'password' => getenv('DB_PASS') ?: '', 'database' => getenv('DB_NAME') ?: '', 'dbdriver' => 'mysqli',
	'dbprefix' => '', 'pconnect' => false, 'db_debug' => false, 'cache_on' => false, 'cachedir' => '',
	'char_set' => 'utf8', 'dbcollat' => 'utf8_general_ci', 'port' => (int) (getenv('DB_PORT') ?: 3306), 'stricton' => true,
), true);

class ProbeSession {
	public function userdata($key) { return $key === 'id' ? 9001 : null; }
}
class ProbeInput {
	public function ip_address() { return '127.0.0.1'; }
	public function user_agent() { return 'cli-probe'; }
}
class ProbeCI {
	public $db;
	public $session;
	public $input;
	public function __construct($db) { $this->db = $db; $this->session = new ProbeSession(); $this->input = new ProbeInput(); }
}

require_once APPPATH . 'libraries/Admin_capability_gateway.php';
$fails = 0;
function check($label, $cond, $detail = '')
{
	global $fails;
	if ($cond) { echo "PASS  {$label}\n"; return; }
	$fails++;
	echo "FAIL  {$label}  {$detail}\n";
}

$ci = new ProbeCI($db);
$gateway = new Admin_capability_gateway($ci);

check('G1 flag kapabilitas terbaca dari lingkungan',
	in_array($gateway->reason(), array('feature_disabled', 'enabled'), true), 'reason=' . $gateway->reason());
check('G2 aktor dari sesi dikenali sebagai admin aktif', $gateway->activeAdmin() === true);
check('G3 tiga kapabilitas yang dikenal',
	$gateway->allowedCodes() === array('super_admin', 'clinical_audit', 'clinical_taxonomy_manage'),
	implode(',', $gateway->allowedCodes()));

$grant = $gateway->grant(9001, 'clinical_audit');
if ($gateway->featureEnabled()) {
	check('G4 fitur aktif: hasil grant punya bentuk lengkap',
		array_key_exists('allowed', $grant) && array_key_exists('changed', $grant) && array_key_exists('error', $grant),
		json_encode($grant));
} else {
	check('G4 fitur mati: grant ditolak lapisan kapabilitas, bukan hanya tombolnya',
		$grant['allowed'] === false && $grant['changed'] === false, json_encode($grant));
}
check('G5 aktor tanpa super_admin tidak bisa mengubah apa pun',
	$gateway->hasCapability('super_admin') === false
		? $grant['allowed'] === false
		: true);

/* Bentuk yang dipakai loader CI: parameter dibungkus array sebagai satu argumen. */
$via_loader = new Admin_capability_gateway(array($ci));
check('G6 gateway menerima bentuk parameter loader CI',
	$via_loader->activeAdmin() === true && $via_loader->reason() === $gateway->reason(),
	'active=' . ($via_loader->activeAdmin() ? 'true' : 'false') . ' reason=' . $via_loader->reason());
check('G7 jumlah super admin aktif terbaca', $via_loader->superAdminCount() >= 0);
check('G8 pesan galat aman diterjemahkan ke bahasa manusia',
	Admin_capability_gateway::errorMessage('last_active_super_admin') !== ''
	&& Admin_capability_gateway::errorMessage('feature_disabled') !== '');

echo "\n" . ($fails === 0 ? 'PROBE_RESULT=PASS' : "PROBE_RESULT=FAIL (gagal={$fails})") . "\n";
exit($fails === 0 ? 0 : 1);
