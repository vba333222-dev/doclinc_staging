<?php
/**
 * Probe model konten landing (blok + tanya-jawab + statistik nyata).
 *
 * Semua tulisan dibungkus transaksi lalu di-ROLLBACK, jadi isi landing kembali
 * seperti semula (dibuktikan di akhir).
 *
 * Jalankan:
 *   docker exec -e ALLOW_STAGE_PROBE=1 doclinc-php8100 \
 *     php /var/www/html/tools/admin_panel/tests/landing_content_stage_probe.php
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
require_once BASEPATH . 'core/Model.php';
class ProbeApp { public $db; public function __construct($db) { $this->db = $db; } }
$GLOBALS['probe_app'] = new ProbeApp($db);
if (!function_exists('get_instance')) { function &get_instance() { return $GLOBALS['probe_app']; } }
require_once APPPATH . 'modules/konten_landing/models/Konten_landing_m.php';
$model = new Konten_landing_m();

$fails = 0;
function check($label, $cond, $detail = '')
{
	global $fails;
	if ($cond) { echo "PASS  {$label}\n"; return; }
	$fails++;
	echo "FAIL  {$label}  {$detail}\n";
}
function count_rows($db, $table)
{
	$row = $db->query('SELECT COUNT(*) AS total FROM `' . $table . '`')->row_array();
	return (int) $row['total'];
}

echo "== DB: {$name} ==\n";
check('L1 skema konten landing siap', $model->schemaReady() === true);

$blocks = $model->getBlocks();
check('L2 blok awal terisi', count($blocks) >= 4, 'jumlah=' . count($blocks));
check('L3 blok terurut dan berkunci',
	(string) $blocks[0]->block_key === 'hero_judul', 'pertama=' . (string) $blocks[0]->block_key);

$baseline_faq = count_rows($db, 'landing_faqs');
$db->query('START TRANSACTION');

/* Blok: ubah lalu baca ulang. */
$target = $blocks[0];
$model->updateBlock($target->id, array(
	'judul' => (string) $target->judul,
	'isi' => 'Uji probe konten landing',
	'urutan' => 10,
	'aktif' => true,
	'updated_by_user_id' => 9001,
));
$after = $model->getBlock($target->id);
check('L4 isi blok tersimpan dan tercatat pengubahnya',
	(string) $after->isi === 'Uji probe konten landing' && (int) $after->updated_by_user_id === 9001);

/* Blok yang dinonaktifkan tidak boleh muncul sebagai blok tampil di view: di sini
 * cukup dipastikan nilainya benar-benar tersimpan. */
$model->updateBlock($target->id, array(
	'judul' => (string) $target->judul,
	'isi' => 'Uji probe konten landing',
	'urutan' => 10,
	'aktif' => false,
	'updated_by_user_id' => 9001,
));
check('L5 status tampil/sembunyi tersimpan apa adanya', (int) $model->getBlock($target->id)->aktif === 0);

/* Tanya-jawab: tambah, ubah, hapus. */
$faq_id = $model->createFaq(array(
	'pertanyaan' => 'Uji probe: pertanyaan',
	'jawaban' => 'Uji probe: jawaban',
	'urutan' => 5,
	'aktif' => true,
	'updated_by_user_id' => 9001,
));
check('L6 tanya-jawab dibuat', $faq_id > 0 && $model->getFaq($faq_id) !== null);
$model->updateFaq($faq_id, array(
	'pertanyaan' => 'Uji probe: pertanyaan (diubah)',
	'jawaban' => 'Uji probe: jawaban',
	'urutan' => 6,
	'aktif' => false,
	'updated_by_user_id' => 9001,
));
$faq_after = $model->getFaq($faq_id);
check('L7 tanya-jawab diubah',
	(string) $faq_after->pertanyaan === 'Uji probe: pertanyaan (diubah)' && (int) $faq_after->urutan === 6);
check('L8 tanya-jawab dihapus', $model->deleteFaq($faq_id) === true && $model->getFaq($faq_id) === null);

/* Statistik: angka harus berasal dari tabel nyata, dan null bila tabelnya tidak ada. */
$stats = $model->getPublicStats();
$labels = array();
foreach ($stats as $stat) { $labels[] = $stat['label']; }
check('L9 empat statistik dengan sumbernya',
	count($stats) === 4 && !in_array('', $labels, true) && $stats[0]['source'] !== '');
check('L10 statistik kecamatan sama dengan isi tabel kecamatan',
	$stats[0]['value'] === null || $stats[0]['value'] === count_rows($db, 'kecamatan'),
	'nilai=' . var_export($stats[0]['value'], true));
check('L11 statistik konsultasi selesai sama dengan hitungan langsung',
	$stats[2]['value'] === null
		|| $stats[2]['value'] === (int) $db->where('request_status', 'Completed')->count_all_results('requests'),
	'nilai=' . var_export($stats[2]['value'], true));

$db->query('ROLLBACK');
check('L12 tabel konten kembali seperti semula',
	count_rows($db, 'landing_faqs') === $baseline_faq
		&& (string) $model->getBlock($target->id)->isi !== 'Uji probe konten landing',
	'faq=' . count_rows($db, 'landing_faqs'));

echo "\n" . ($fails === 0 ? 'PROBE_RESULT=PASS' : "PROBE_RESULT=FAIL (gagal={$fails})") . "\n";
exit($fails === 0 ? 0 : 1);
