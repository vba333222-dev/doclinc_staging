<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Penjelajah master ICD-10 (baca saja).
 *
 * Sumbernya berkas master di repo (`database/master_data/clinical/v1/`), BUKAN
 * tabel `clinical_master_*`. Itu keputusan sadar: tabel itu adalah pipeline impor
 * ber-governance (dataset, package, batch, checksum, status review) dan alat yang
 * ada sekarang hanya mengurus skemanya - `tools/clinical_schema` sendiri
 * melaporkan `PACKAGE_IMPORT_SUPPORTED=false`. Menyalin 12.246 baris langsung ke
 * tabel akan melewati governance itu, jadi tidak dilakukan.
 *
 * Karena berkas diagnosis berukuran ~12 MB, pencarian memakai indeks ringkas
 * yang dibangun sekali lalu di-cache (kode, nama, nama Indonesia, bab, blok,
 * level). Indeks dibangun ulang otomatis bila berkas sumbernya berubah
 * (ukuran + waktu ubah dibandingkan).
 *
 * Catatan kejujuran data: judul resmi berbahasa Inggris; `nama_indonesia` baru
 * tersedia untuk 53 kode lewat dataset alias yang statusnya masih
 * `draft_requires_clinical_coder_validation`. Halaman menampilkan status itu apa
 * adanya, tidak menerjemahkan sendiri.
 */
class Icd10_m extends CI_Model
{
	const CACHE_FILE = 'icd10_index_v1.php';

	private $directory_cache = null;

	/** Direktori master klinis di repo (satu tingkat di atas panel admin). */
	public function masterDirectory()
	{
		if ($this->directory_cache === null) {
			$this->directory_cache = rtrim(dirname(APPPATH, 2) . '/database/master_data/clinical/v1', '/') . '/';
		}
		return $this->directory_cache;
	}

	public function available()
	{
		return is_file($this->masterDirectory() . '20b_master_diagnosis_icd10_who_2019.json');
	}

	/** Baca satu berkas dataset; mengembalikan array kosong bila tidak bisa. */
	public function readDataset($file)
	{
		$path = $this->masterDirectory() . $file;
		if (!is_file($path)) {
			return array();
		}
		$decoded = json_decode((string) file_get_contents($path), true);
		if (!is_array($decoded)) {
			return array();
		}
		return $decoded;
	}

	/** Ringkasan paket dari manifest (tanpa membaca berkas besar). */
	public function manifest()
	{
		$manifest = $this->readDataset('00_icd10_manifest.json');
		if (empty($manifest)) {
			return array();
		}
		$manifest['manifest_checksum'] = is_file($this->masterDirectory() . '00_icd10_manifest.json')
			? hash_file('sha256', $this->masterDirectory() . '00_icd10_manifest.json')
			: '';
		return $manifest;
	}

	public function chapters()
	{
		$dataset = $this->readDataset('20_master_icd10_chapter_who_2019.json');
		return isset($dataset['data']) && is_array($dataset['data']) ? $dataset['data'] : array();
	}

	public function blocks()
	{
		$dataset = $this->readDataset('20a_master_icd10_block_who_2019.json');
		return isset($dataset['data']) && is_array($dataset['data']) ? $dataset['data'] : array();
	}

	/** Alias bahasa Indonesia (statusnya masih draft - ditampilkan apa adanya). */
	public function aliases()
	{
		$dataset = $this->readDataset('20c_master_diagnosis_alias_indonesia_starter.json');
		return isset($dataset['data']) && is_array($dataset['data']) ? $dataset['data'] : array();
	}

	/**
	 * Lokasi cache indeks. Direktori `application/cache` diutamakan, tetapi bila
	 * tidak bisa ditulis (umum pada pemasangan yang ketat) cache jatuh ke
	 * direktori sementara sistem - lebih baik daripada membangun indeks 12 MB
	 * pada setiap pencarian. Lokasi yang benar-benar dipakai dilaporkan di meta
	 * supaya tidak perlu ditebak.
	 */
	private function cachePath()
	{
		$preferred = APPPATH . 'cache/' . self::CACHE_FILE;
		$directory = dirname($preferred);
		if (is_dir($directory) && is_writable($directory)) {
			return $preferred;
		}
		return rtrim(sys_get_temp_dir(), '/') . '/' . self::CACHE_FILE;
	}

	/** Sidik jari berkas sumber: ukuran + waktu ubah. */
	private function sourceFingerprint()
	{
		$path = $this->masterDirectory() . '20b_master_diagnosis_icd10_who_2019.json';
		if (!is_file($path)) {
			return '';
		}
		return filesize($path) . ':' . filemtime($path);
	}

	/**
	 * Indeks ringkas untuk pencarian. Dipakai dari cache bila masih cocok dengan
	 * berkas sumber; kalau tidak, dibangun ulang dari berkas master.
	 */
	public function loadIndex()
	{
		$fingerprint = $this->sourceFingerprint();
		if ($fingerprint === '') {
			return array('meta' => array('status' => 'sumber_tidak_ada'), 'entries' => array(), 'aliases' => array());
		}
		$cache_path = $this->cachePath();
		if (is_file($cache_path)) {
			$cached = @include $cache_path;
			if (is_array($cached) && isset($cached['meta']['fingerprint']) && $cached['meta']['fingerprint'] === $fingerprint) {
				$cached['meta']['from_cache'] = true;
				return $cached;
			}
		}
		$built = $this->buildIndex($fingerprint);
		$written = @file_put_contents(
			$this->cachePath(),
			'<?php defined(\'BASEPATH\') or exit(\'No direct script access allowed\'); return ' . var_export($built, true) . ';'
		);
		$built['meta']['cache_written'] = $written !== false;
		$built['meta']['cache_path'] = $this->cachePath();
		return $built;
	}

	/**
	 * Peta `kode => entri` untuk memeriksa kode tertentu tanpa memindai 12.246
	 * baris. Dipakai panel pengodean diagnosis (modul diagnosis) untuk menjawab
	 * satu pertanyaan: "kode yang tertulis di teks ini benar-benar ada dan boleh
	 * dipakai sebagai diagnosis?".
	 *
	 * Indeks yang sama dipakai `search()`, jadi tidak ada pembacaan berkas 12 MB
	 * tambahan: hasilnya berasal dari cache indeks yang sudah ada.
	 */
	public function codeIndex()
	{
		$index = $this->loadIndex();
		if (($index['meta']['status'] ?? '') !== 'ok') {
			return array();
		}
		$map = array();
		foreach ($index['entries'] as $entry) {
			if ($entry['kode'] !== '') {
				$map[$entry['kode']] = $entry;
			}
		}
		return $map;
	}

	private function buildIndex($fingerprint)
	{
		$started = microtime(true);
		$dataset = $this->readDataset('20b_master_diagnosis_icd10_who_2019.json');
		$rows = isset($dataset['data']) && is_array($dataset['data']) ? $dataset['data'] : array();

		$entries = array();
		foreach ($rows as $row) {
			$entries[] = array(
				'kode' => (string) ($row['kode'] ?? ''),
				'nama' => (string) ($row['nama'] ?? ''),
				'nama_indonesia' => (string) ($row['nama_indonesia'] ?? ''),
				'level' => (string) ($row['level'] ?? ''),
				'chapter_code' => (string) ($row['chapter_code'] ?? ''),
				'chapter_name' => (string) ($row['chapter_name'] ?? ''),
				'block_code' => (string) ($row['block_code'] ?? ''),
				'block_name' => (string) ($row['block_name'] ?? ''),
				'is_leaf' => !empty($row['is_leaf']),
				'dapat_dipilih' => !empty($row['dapat_dipilih_dalam_diagnosis']),
				'status_review' => (string) ($row['status_review_klinis'] ?? ''),
			);
		}

		$aliases = array();
		foreach ($this->aliases() as $alias) {
			$kode = strtoupper(trim((string) ($alias['kode_icd10'] ?? '')));
			if ($kode === '') {
				continue;
			}
			$aliases[$kode] = array(
				'nama_indonesia' => (string) ($alias['nama_indonesia'] ?? ''),
				'status_review' => (string) ($alias['status_review_klinis'] ?? ''),
			);
		}

		return array(
			'meta' => array(
				'status' => 'ok',
				'fingerprint' => $fingerprint,
				'built_at' => date('Y-m-d H:i:s'),
				'build_ms' => (int) round((microtime(true) - $started) * 1000),
				'entry_count' => count($entries),
				'alias_count' => count($aliases),
				'source_bytes' => (int) @filesize($this->masterDirectory() . '20b_master_diagnosis_icd10_who_2019.json'),
				'from_cache' => false,
				'cache_written' => false,
			),
			'entries' => $entries,
			'aliases' => $aliases,
		);
	}

	/**
	 * Pencarian: kode, judul resmi, judul Indonesia, alias, atau nama blok.
	 * Mengembalikan maksimal `$limit` baris beserta jumlah total yang cocok.
	 */
	public function search($query, $limit = 40)
	{
		$query = trim((string) $query);
		$index = $this->loadIndex();
		if (($index['meta']['status'] ?? '') !== 'ok') {
			return array('meta' => $index['meta'], 'total' => 0, 'rows' => array());
		}
		$limit = (int) $limit;
		if ($limit < 1 || $limit > 200) {
			$limit = 40;
		}
		$needle = strtoupper($query);
		$total = 0;
		$rows = array();
		foreach ($index['entries'] as $entry) {
			$alias = isset($index['aliases'][$entry['kode']]) ? $index['aliases'][$entry['kode']] : null;
			$haystacks = array(
				strtoupper($entry['kode']),
				strtoupper($entry['nama']),
				strtoupper($entry['nama_indonesia']),
				$alias === null ? '' : strtoupper($alias['nama_indonesia']),
				strtoupper($entry['block_name']),
			);
			$matches = false;
			foreach ($haystacks as $haystack) {
				if ($haystack !== '' && strpos($haystack, $needle) !== false) {
					$matches = true;
					break;
				}
			}
			if (!$matches) {
				continue;
			}
			$total++;
			if (count($rows) < $limit) {
				$rows[] = $entry + array(
					'alias_indonesia' => $alias === null ? '' : $alias['nama_indonesia'],
					'alias_status_review' => $alias === null ? '' : $alias['status_review'],
				);
			}
		}
		return array('meta' => $index['meta'], 'total' => $total, 'rows' => $rows);
	}
}
