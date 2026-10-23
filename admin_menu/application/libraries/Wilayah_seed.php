<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Seed wilayah (kecamatan + puskesmas) dari definisi poligon layanan.
 *
 * Mengapa ada: tabel `kecamatan` dan `puskesmas` (yang punya `id_kecamatan`)
 * masih kosong, sedangkan `m_puskesmas` - yang dipakai routing warga - tidak
 * punya kolom kecamatan sama sekali. Tanpa pemetaan ini, laporan "penyakit
 * terbanyak per kecamatan" tidak bisa dihitung, dan itu bukan sesuatu yang
 * boleh ditebak dari alamat teks bebas.
 *
 * Sumber tunggalnya adalah definisi poligon aplikasi utama
 * (application/helpers/puskesmas_routing_helper.php) supaya angka di panel
 * admin dan rute layanan warga tidak pernah berbeda. Nama kecamatan diambil
 * dari nama berkas geojson (Puskesmas<Kecamatan>.geojson); nama puskesmas
 * mengikuti pola itu, dengan pengecualian yang sudah didokumentasikan di helper:
 * kode 2241001 = "Puskesmas Citangkil II".
 *
 * Sifat operasi:
 *   - aditif: tidak pernah DELETE, tidak pernah menimpa nilai yang sudah ada;
 *   - idempoten: menjalankan ulang tidak mengubah apa pun;
 *   - konflik dilaporkan, bukan ditimpa (koreksi operator harus menang);
 *   - tidak mengelola transaksi sendiri - pemanggil yang menentukan, supaya
 *     uji dapat membungkusnya dengan rollback.
 */
class Wilayah_seed
{
	const MIGRATION_ID = '20261023000100_wilayah_kecamatan_puskesmas_seed';

	/** Nama puskesmas yang tidak mengikuti pola "Puskesmas <Kecamatan>". */
	private static $name_overrides = array(
		'2241001' => 'Puskesmas Citangkil II',
	);

	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Definisi wilayah dari poligon aplikasi utama.
	 *
	 * Mengembalikan array('kecamatan' => array(array('nama')), 'puskesmas' =>
	 * array(array('kode_pkm','nama','kecamatan')), 'reason' => 'ok|...').
	 * Berkas geojson yang namanya tidak dikenal dilewati, bukan ditebak.
	 */
	public static function definitions($application_root)
	{
		$root = rtrim((string) $application_root, '/');
		if (!defined('BASEPATH')) {
			define('BASEPATH', $root . '/system/');
		}
		if (!defined('APPPATH')) {
			define('APPPATH', $root . '/application/');
		}
		if (!defined('FCPATH')) {
			define('FCPATH', $root . '/');
		}
		$helper = $root . '/application/helpers/puskesmas_routing_helper.php';
		if (!is_file($helper)) {
			return array('kecamatan' => array(), 'puskesmas' => array(), 'reason' => 'routing_helper_missing');
		}
		require_once $helper;
		if (!function_exists('doclinc_service_area_definitions')) {
			return array('kecamatan' => array(), 'puskesmas' => array(), 'reason' => 'definitions_missing');
		}

		$kecamatan = array();
		$puskesmas = array();
		foreach (doclinc_service_area_definitions() as $definition) {
			$file = (string) (isset($definition['file']) ? $definition['file'] : '');
			$nama = preg_replace('/\APuskesmas(.+)\.geojson\z/', '$1', $file);
			if (!is_string($nama) || $nama === '' || $nama === $file) {
				continue;
			}
			$kecamatan[$nama] = array('nama' => $nama);
			foreach ((array) (isset($definition['codes']) ? $definition['codes'] : array()) as $code) {
				$code = trim((string) $code);
				if ($code === '') {
					continue;
				}
				$puskesmas[] = array(
					'kode_pkm' => $code,
					'nama' => isset(self::$name_overrides[$code]) ? self::$name_overrides[$code] : ('Puskesmas ' . $nama),
					'kecamatan' => $nama,
				);
			}
		}

		return array(
			'kecamatan' => array_values($kecamatan),
			'puskesmas' => $puskesmas,
			'reason' => 'ok',
		);
	}

	/** Tabel target harus sudah ada; DDL milik migrasi kompatibilitas, bukan di sini. */
	public function schemaReady()
	{
		return $this->tableExists('kecamatan') && $this->tableExists('puskesmas')
			&& $this->columnExists('kecamatan', 'nama_kecamatan')
			&& $this->columnExists('puskesmas', 'kode_pkm')
			&& $this->columnExists('puskesmas', 'id_kecamatan');
	}

	/** Rencana (baca saja). Tidak menulis apa pun. */
	public function plan(array $definitions)
	{
		$report = array(
			'schema_ready' => $this->schemaReady(),
			'kecamatan' => array('total' => 0, 'present' => 0, 'insert' => array()),
			'puskesmas' => array('total' => 0, 'present' => 0, 'insert' => array(), 'fill_kecamatan' => array(), 'conflict' => array()),
		);
		if (!$report['schema_ready']) {
			return $report;
		}

		foreach ($definitions['kecamatan'] as $row) {
			$report['kecamatan']['total']++;
			if ($this->kecamatanId($row['nama']) === null) {
				$report['kecamatan']['insert'][] = $row['nama'];
			} else {
				$report['kecamatan']['present']++;
			}
		}

		foreach ($definitions['puskesmas'] as $row) {
			$report['puskesmas']['total']++;
			$existing = $this->puskesmasRow($row['kode_pkm']);
			if ($existing === null) {
				$report['puskesmas']['insert'][] = $row['kode_pkm'];
				continue;
			}
			$report['puskesmas']['present']++;
			$kecamatan_id = $this->kecamatanId($row['kecamatan']);
			if ($existing['id_kecamatan'] === null || $existing['id_kecamatan'] < 1) {
				if ($kecamatan_id !== null) {
					$report['puskesmas']['fill_kecamatan'][] = $row['kode_pkm'];
				}
				continue;
			}
			if ($kecamatan_id !== null && $existing['id_kecamatan'] !== $kecamatan_id) {
				$report['puskesmas']['conflict'][] = array(
					'kode_pkm' => $row['kode_pkm'],
					'expected' => $kecamatan_id,
					'found' => $existing['id_kecamatan'],
				);
			}
		}

		return $report;
	}

	/**
	 * Terapkan rencana. Pemanggil yang mengelola transaksi.
	 *
	 * Hanya menambah baris yang belum ada dan mengisi `id_kecamatan` yang masih
	 * NULL. Baris yang sudah punya pemetaan BERBEDA tidak disentuh - dilaporkan
	 * sebagai konflik supaya koreksi operator tidak hilang.
	 */
	public function apply(array $definitions, array $plan)
	{
		$result = array(
			'kecamatan_inserted' => 0,
			'puskesmas_inserted' => 0,
			'puskesmas_filled' => 0,
			'conflicts_left_untouched' => isset($plan['puskesmas']['conflict']) ? count($plan['puskesmas']['conflict']) : 0,
		);
		if (empty($plan['schema_ready'])) {
			return $result;
		}

		$by_code = array();
		foreach ($definitions['puskesmas'] as $row) {
			$by_code[$row['kode_pkm']] = $row;
		}

		foreach ($plan['kecamatan']['insert'] as $nama) {
			$statement = $this->db->prepare('INSERT INTO `kecamatan` (`nama_kecamatan`) VALUES (?)');
			$statement->bind_param('s', $nama);
			$statement->execute();
			$statement->close();
			$result['kecamatan_inserted']++;
		}

		foreach ($plan['puskesmas']['insert'] as $kode) {
			if (!isset($by_code[$kode])) {
				continue;
			}
			$row = $by_code[$kode];
			$kecamatan_id = $this->kecamatanId($row['kecamatan']);
			if ($kecamatan_id === null) {
				continue;
			}
			$statement = $this->db->prepare('INSERT INTO `puskesmas` (`kode_pkm`, `nama_puskesmas`, `id_kecamatan`) VALUES (?, ?, ?)');
			$statement->bind_param('ssi', $row['kode_pkm'], $row['nama'], $kecamatan_id);
			$statement->execute();
			$statement->close();
			$result['puskesmas_inserted']++;
		}

		foreach ($plan['puskesmas']['fill_kecamatan'] as $kode) {
			if (!isset($by_code[$kode])) {
				continue;
			}
			$row = $by_code[$kode];
			$kecamatan_id = $this->kecamatanId($row['kecamatan']);
			if ($kecamatan_id === null) {
				continue;
			}
			$statement = $this->db->prepare('UPDATE `puskesmas` SET `id_kecamatan` = ? WHERE `kode_pkm` = ? AND `id_kecamatan` IS NULL');
			$statement->bind_param('is', $kecamatan_id, $row['kode_pkm']);
			$statement->execute();
			$result['puskesmas_filled'] += $statement->affected_rows > 0 ? 1 : 0;
			$statement->close();
		}

		return $result;
	}

	/** Periksa hasil: kelengkapan, pemetaan kosong, dan baris yatim. Baca saja. */
	public function verify(array $definitions)
	{
		$report = array(
			'schema_ready' => $this->schemaReady(),
			'kecamatan_rows' => 0,
			'puskesmas_rows' => 0,
			'puskesmas_without_kecamatan' => 0,
			'puskesmas_orphan' => 0,
			'kecamatan_without_puskesmas' => array(),
			'kecamatan_expected' => count($definitions['kecamatan']),
			'puskesmas_expected' => count($definitions['puskesmas']),
		);
		if (!$report['schema_ready']) {
			return $report;
		}
		$report['kecamatan_rows'] = $this->countRows('kecamatan');
		$report['puskesmas_rows'] = $this->countRows('puskesmas');
		$report['puskesmas_without_kecamatan'] = $this->countWhere('puskesmas', 'id_kecamatan IS NULL');
		$report['puskesmas_orphan'] = $this->countOrphanPuskesmas();
		$report['kecamatan_without_puskesmas'] = $this->kecamatanWithoutPuskesmas();
		return $report;
	}

	private function kecamatanId($nama)
	{
		$statement = $this->db->prepare('SELECT `id_kecamatan` FROM `kecamatan` WHERE `nama_kecamatan` = ? LIMIT 1');
		$statement->bind_param('s', $nama);
		$statement->execute();
		$statement->bind_result($id);
		$found = $statement->fetch();
		$statement->close();
		return $found ? (int) $id : null;
	}

	private function puskesmasRow($kode)
	{
		$statement = $this->db->prepare('SELECT `id_puskesmas`, `id_kecamatan` FROM `puskesmas` WHERE `kode_pkm` = ? LIMIT 1');
		$statement->bind_param('s', $kode);
		$statement->execute();
		$statement->bind_result($id, $kecamatan_id);
		$found = $statement->fetch();
		$statement->close();
		if (!$found) {
			return null;
		}
		return array(
			'id_puskesmas' => (int) $id,
			'id_kecamatan' => $kecamatan_id === null ? null : (int) $kecamatan_id,
		);
	}

	private function countRows($table)
	{
		$row = $this->db->query('SELECT COUNT(*) AS total FROM `' . $table . '`')->fetch_assoc();
		return (int) $row['total'];
	}

	private function countWhere($table, $condition)
	{
		$row = $this->db->query('SELECT COUNT(*) AS total FROM `' . $table . '` WHERE ' . $condition)->fetch_assoc();
		return (int) $row['total'];
	}

	private function countOrphanPuskesmas()
	{
		$sql = 'SELECT COUNT(*) AS total FROM `puskesmas` p LEFT JOIN `kecamatan` k ON k.id_kecamatan = p.id_kecamatan '
			. 'WHERE p.id_kecamatan IS NOT NULL AND k.id_kecamatan IS NULL';
		$row = $this->db->query($sql)->fetch_assoc();
		return (int) $row['total'];
	}

	private function kecamatanWithoutPuskesmas()
	{
		$sql = 'SELECT k.nama_kecamatan FROM `kecamatan` k LEFT JOIN `puskesmas` p ON p.id_kecamatan = k.id_kecamatan '
			. 'GROUP BY k.id_kecamatan, k.nama_kecamatan HAVING COUNT(p.id_puskesmas) = 0 ORDER BY k.nama_kecamatan';
		$result = $this->db->query($sql);
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			$rows[] = (string) $row['nama_kecamatan'];
		}
		return $rows;
	}

	private function tableExists($table)
	{
		$statement = $this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
		$statement->bind_param('s', $table);
		$statement->execute();
		$statement->bind_result($count);
		$statement->fetch();
		$statement->close();
		return (int) $count === 1;
	}

	private function columnExists($table, $column)
	{
		$statement = $this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
		$statement->bind_param('ss', $table, $column);
		$statement->execute();
		$statement->bind_result($count);
		$statement->fetch();
		$statement->close();
		return (int) $count === 1;
	}
}
