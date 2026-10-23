<?php
/**
 * Probe penjelajah master ICD-10.
 *
 * Memeriksa bahwa penjelajah membaca berkas master apa adanya, konsisten dengan
 * manifestnya, dan TIDAK menyalin data ke tabel `clinical_master_*` (pipeline itu
 * ber-governance dan alat impornya belum ada).
 *
 * Jalankan:
 *   docker exec doclinc-php8100 \
 *     php /var/www/html/tools/admin_panel/tests/icd10_explorer_probe.php
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
require_once BASEPATH . 'core/Model.php';
class ProbeApp { public $db; public function __construct($db) { $this->db = $db; } }
$GLOBALS['probe_app'] = new ProbeApp($db);
if (!function_exists('get_instance')) { function &get_instance() { return $GLOBALS['probe_app']; } }
require_once APPPATH . 'modules/icd10/models/Icd10_m.php';
$model = new Icd10_m();

$fails = 0;
function check($label, $cond, $detail = '')
{
	global $fails;
	if ($cond) { echo "PASS  {$label}\n"; return; }
	$fails++;
	echo "FAIL  {$label}  {$detail}\n";
}

echo "== Penjelajah ICD-10 ==\n";
check('I1 berkas master tersedia', $model->available() === true);

$manifest = $model->manifest();
check('I2 manifest terbaca dan lengkap',
	($manifest['package'] ?? '') !== '' && (int) ($manifest['total_records'] ?? 0) === 12597
		&& (int) ($manifest['file_count'] ?? 0) === 5,
	json_encode(array('package' => $manifest['package'] ?? null, 'total' => $manifest['total_records'] ?? null)));
check('I3 checksum manifest dihitung', strlen((string) ($manifest['manifest_checksum'] ?? '')) === 64);

$chapters = $model->chapters();
$blocks = $model->blocks();
$aliases = $model->aliases();
check('I4 bab sesuai manifest', count($chapters) === 22, 'bab=' . count($chapters));
check('I5 blok sesuai manifest', count($blocks) === 274, 'blok=' . count($blocks));
check('I6 alias Indonesia sesuai manifest', count($aliases) === 53, 'alias=' . count($aliases));
check('I7 alias masih ditandai draft (tidak diklaim final)',
	(string) ($aliases[0]['status_review_klinis'] ?? '') !== ''
		&& stripos((string) $aliases[0]['status_review_klinis'], 'validation') !== false,
	(string) ($aliases[0]['status_review_klinis'] ?? ''));

$index = $model->loadIndex();
check('I8 indeks berisi seluruh kode diagnosis', (int) ($index['meta']['entry_count'] ?? 0) === 12246,
	'entry=' . ($index['meta']['entry_count'] ?? 'n/a'));
check('I9 indeks memuat alias', (int) ($index['meta']['alias_count'] ?? 0) === 53);
$second = $model->loadIndex();
check('I10 indeks kedua dibaca dari cache', ($second['meta']['from_cache'] ?? false) === true,
	'cache=' . ($second['meta']['cache_written'] ?? 'n/a'));

$by_code = $model->search('A09', 10);
$codes = array();
foreach ($by_code['rows'] as $row) { $codes[] = $row['kode']; }
check('I11 pencarian kode menemukan A09 dan turunannya',
	in_array('A09', $codes, true) && (int) $by_code['total'] >= 3, implode(',', array_slice($codes, 0, 4)));
check('I12 alias Indonesia muncul pada hasil pencarian',
	(string) $by_code['rows'][0]['alias_indonesia'] !== ''
		&& stripos((string) $by_code['rows'][0]['alias_status_review'], 'validation') !== false,
	(string) $by_code['rows'][0]['alias_indonesia']);

$by_name = $model->search('Cholera', 10);
$found_a00 = false;
foreach ($by_name['rows'] as $row) {
	if ($row['kode'] === 'A00') { $found_a00 = true; }
}
check('I13 pencarian judul resmi menemukan A00 Cholera', $found_a00 === true);
check('I14 kata kunci tak dikenal tidak memaksakan hasil',
	(int) $model->search('zzzzbukanapaapa', 5)['total'] === 0);
check('I15 batas hasil dihormati', count($model->search('a', 5)['rows']) <= 5);

$datasets = (int) $db->query('SELECT COUNT(*) AS total FROM clinical_master_datasets')->row_array()['total'];
check('I16 penjelajah TIDAK menyalin data ke pipeline clinical_master_*', $datasets === 0,
	'baris=' . $datasets);

echo "\n" . ($fails === 0 ? 'PROBE_RESULT=PASS' : "PROBE_RESULT=FAIL (gagal={$fails})") . "\n";
exit($fails === 0 ? 0 : 1);
