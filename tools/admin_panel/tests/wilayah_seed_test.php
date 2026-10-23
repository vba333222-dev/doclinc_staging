<?php
/**
 * Uji seed wilayah (kecamatan + puskesmas) dari definisi poligon.
 *
 * Semua tulisan dibungkus transaksi luar dan di-ROLLBACK: tabel dikosongkan di
 * dalam transaksi supaya hasil ujinya tidak bergantung pada keadaan database,
 * lalu dikembalikan. Satu efek samping yang tidak bisa dihindari: penghitung
 * AUTO_INCREMENT tabel tersebut maju - tidak berbahaya karena id di sana tidak
 * pernah dipakai sebagai angka yang bermakna.
 *
 * Jalankan di dalam container PHP:
 *   docker exec -e ALLOW_STAGE_PROBE=1 doclinc-php8100 \
 *     php /var/www/html/tools/admin_panel/tests/wilayah_seed_test.php
 */
$root = dirname(__DIR__, 3) . '/';
define('BASEPATH', $root . 'admin_menu/system/');
define('APPPATH', $root . 'admin_menu/application/');
define('FCPATH', $root . 'admin_menu/');
require_once APPPATH . 'libraries/Wilayah_seed.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
$port = (int) (getenv('DB_PORT') ?: 3306);
if (getenv('ALLOW_STAGE_PROBE') !== '1') { fwrite(STDERR, "refuse: set ALLOW_STAGE_PROBE=1\n"); exit(2); }
if (stripos($name, 'stage') === false) { fwrite(STDERR, "refuse: bukan database stage ($name)\n"); exit(2); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($host, $user, $pass, $name, $port);
$db->set_charset('utf8mb4');

$fails = 0;
function check($label, $cond, $detail = '')
{
	global $fails;
	if ($cond) { echo "PASS  {$label}\n"; return; }
	$fails++;
	echo "FAIL  {$label}  {$detail}\n";
}
function row_count($db, $table)
{
	$row = $db->query('SELECT COUNT(*) AS total FROM `' . $table . '`')->fetch_assoc();
	return (int) $row['total'];
}
function one_value($db, $sql)
{
	$row = $db->query($sql)->fetch_assoc();
	return $row === null ? null : array_values($row)[0];
}

echo "== DB: {$name} ==\n";
$definitions = Wilayah_seed::definitions($root);
$seed = new Wilayah_seed($db);

/* --- 1. Definisi dari sumber tunggal (poligon aplikasi utama) --------------- */
check('D1 sumber definisi tersedia', ($definitions['reason'] ?? '') === 'ok', (string) ($definitions['reason'] ?? ''));
$kecamatan_names = array();
foreach ($definitions['kecamatan'] as $row) { $kecamatan_names[] = $row['nama']; }
sort($kecamatan_names);
$expected_kecamatan = array('Cibeber', 'Cilegon', 'Citangkil', 'Ciwandan', 'Gerogol', 'Jombang', 'Pulomerak', 'Purwakarta');
check('D2 delapan kecamatan Kota Cilegon', $kecamatan_names === $expected_kecamatan, implode(',', $kecamatan_names));

$codes = array();
$by_code = array();
foreach ($definitions['puskesmas'] as $row) { $codes[] = $row['kode_pkm']; $by_code[$row['kode_pkm']] = $row; }
sort($codes, SORT_STRING);
$expected_codes = array('10280101', '10280201', '10280301', '10280401', '10280402', '10280501', '10280601', '10280701', '2241001');
sort($expected_codes, SORT_STRING);
check('D3 sembilan kode puskesmas (Citangkil punya dua)', $codes === $expected_codes, implode(',', $codes));
check('D4 kode 2241001 = Puskesmas Citangkil II',
	isset($by_code['2241001']) && $by_code['2241001']['nama'] === 'Puskesmas Citangkil II',
	(string) ($by_code['2241001']['nama'] ?? ''));
$mapped = true;
foreach ($definitions['puskesmas'] as $row) {
	if (!in_array($row['kecamatan'], $expected_kecamatan, true)) { $mapped = false; }
}
check('D5 setiap puskesmas memetakan ke kecamatan yang ada', $mapped);

/* --- 2. Siklus penuh di dalam transaksi ----------------------------------- */
$baseline_kecamatan = row_count($db, 'kecamatan');
$baseline_puskesmas = row_count($db, 'puskesmas');

$db->begin_transaction();
$db->query('DELETE FROM `puskesmas`');
$db->query('DELETE FROM `kecamatan`');

check('S1 prasyarat skema', $seed->schemaReady() === true);

$plan = $seed->plan($definitions);
check('S2 rencana pada tabel kosong: 8 + 9 akan ditulis',
	$plan['kecamatan']['present'] === 0 && count($plan['kecamatan']['insert']) === 8
	&& $plan['puskesmas']['present'] === 0 && count($plan['puskesmas']['insert']) === 9);

$result = $seed->apply($definitions, $plan);
check('S3 terapkan menulis 8 kecamatan + 9 puskesmas',
	$result['kecamatan_inserted'] === 8 && $result['puskesmas_inserted'] === 9,
	json_encode($result));

$verify = $seed->verify($definitions);
check('S4 hasil: 8 + 9 baris, tanpa pemetaan kosong, tanpa baris yatim',
	$verify['kecamatan_rows'] === 8 && $verify['puskesmas_rows'] === 9
	&& $verify['puskesmas_without_kecamatan'] === 0 && $verify['puskesmas_orphan'] === 0,
	json_encode($verify));
check('S5 setiap kecamatan punya puskesmas', $verify['kecamatan_without_puskesmas'] === array(),
	implode(',', $verify['kecamatan_without_puskesmas']));
check('S6 pemetaan tersimpan benar (10280101 -> Cilegon)',
	(int) one_value($db, "SELECT p.id_kecamatan FROM puskesmas p WHERE p.kode_pkm='10280101'")
	=== (int) one_value($db, "SELECT id_kecamatan FROM kecamatan WHERE nama_kecamatan='Cilegon'"));

$plan_again = $seed->plan($definitions);
$result_again = $seed->apply($definitions, $plan_again);
check('S7 idempoten: jalan kedua tidak menulis apa pun',
	$result_again['kecamatan_inserted'] === 0 && $result_again['puskesmas_inserted'] === 0
	&& $result_again['puskesmas_filled'] === 0
	&& $plan_again['kecamatan']['present'] === 8 && $plan_again['puskesmas']['present'] === 9,
	json_encode($result_again));

/* --- 3. Konflik tidak ditimpa, pemetaan kosong diisi ---------------------- */
$db->query("UPDATE `puskesmas` SET `id_kecamatan` = (SELECT id_kecamatan FROM `kecamatan` WHERE nama_kecamatan='Jombang') WHERE `kode_pkm`='10280201'");
$plan_conflict = $seed->plan($definitions);
check('C1 pemetaan yang berbeda dilaporkan sebagai konflik',
	count($plan_conflict['puskesmas']['conflict']) === 1
	&& $plan_conflict['puskesmas']['conflict'][0]['kode_pkm'] === '10280201',
	json_encode($plan_conflict['puskesmas']['conflict']));
$result_conflict = $seed->apply($definitions, $plan_conflict);
$still_jombang = (int) one_value($db, "SELECT k.id_kecamatan FROM puskesmas p JOIN kecamatan k ON k.id_kecamatan=p.id_kecamatan WHERE p.kode_pkm='10280201' AND k.nama_kecamatan='Jombang'");
check('C2 konflik TIDAK ditimpa (koreksi operator menang)',
	$result_conflict['conflicts_left_untouched'] === 1 && $still_jombang > 0);

$db->query("UPDATE `puskesmas` SET `id_kecamatan` = NULL WHERE `kode_pkm`='10280301'");
$plan_fill = $seed->plan($definitions);
check('C3 pemetaan kosong dijadwalkan diisi', in_array('10280301', $plan_fill['puskesmas']['fill_kecamatan'], true));
$result_fill = $seed->apply($definitions, $plan_fill);
check('C4 pemetaan kosong benar-benar terisi',
	$result_fill['puskesmas_filled'] === 1
	&& one_value($db, "SELECT id_kecamatan FROM puskesmas WHERE kode_pkm='10280301'") !== null);

/* --- 4. Deteksi baris yatim ----------------------------------------------- */
$db->query("INSERT INTO `puskesmas` (`kode_pkm`, `nama_puskesmas`, `id_kecamatan`) VALUES ('UJI-YATIM', 'Puskesmas Uji Yatim', 999999)");
$verify_orphan = $seed->verify($definitions);
check('O1 puskesmas dengan kecamatan tidak dikenal terdeteksi',
	$verify_orphan['puskesmas_orphan'] === 1, json_encode($verify_orphan));
$db->query("DELETE FROM `puskesmas` WHERE `kode_pkm`='UJI-YATIM'");

$db->rollback();

$after_kecamatan = row_count($db, 'kecamatan');
$after_puskesmas = row_count($db, 'puskesmas');
check('E1 tabel kembali seperti semula',
	$after_kecamatan === $baseline_kecamatan && $after_puskesmas === $baseline_puskesmas,
	"kecamatan {$baseline_kecamatan}->{$after_kecamatan}, puskesmas {$baseline_puskesmas}->{$after_puskesmas}");

/* --- 5. Skema hilang harus ditolak, bukan ditebak -------------------------
 * DDL tidak transaksional, jadi bagian ini berjalan SETELAH rollback dan tabel
 * langsung dikembalikan. Jendela saat tabel bernama lain hanya milidetik pada
 * database staging lokal. */
$db->query('RENAME TABLE `puskesmas` TO `puskesmas_uji_hilang`');
$plan_missing = $seed->plan($definitions);
check('X1 tabel hilang -> schema_ready=false dan rencana kosong',
	$plan_missing['schema_ready'] === false && count($plan_missing['kecamatan']['insert']) === 0);
$db->query('RENAME TABLE `puskesmas_uji_hilang` TO `puskesmas`');
check('X2 tabel dikembalikan dan skema siap lagi', $seed->schemaReady() === true);

echo "\n" . ($fails === 0 ? "PROBE_RESULT=PASS" : "PROBE_RESULT=FAIL (gagal={$fails})") . "\n";
exit($fails === 0 ? 0 : 1);
