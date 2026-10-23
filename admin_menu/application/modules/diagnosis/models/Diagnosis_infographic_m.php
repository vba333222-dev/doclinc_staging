<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Agregasi diagnosis untuk infografis penyakit terbanyak.
 *
 * Sumber angka: `medicalrecord_diagnoses` (ICD-10) yang di-join ke `requests`
 * lewat `medicalrecords`, lalu ke `puskesmas` -> `kecamatan`. Rantai itu satu-
 * satunya jalan yang jujur: `patients.alamat` adalah teks bebas dan tidak boleh
 * dijadikan dasar perhitungan wilayah.
 *
 * Aturan yang dipegang di sini:
 *   - Waktu memakai urutan kanonik `medicalrecords.clinical_finalized_at` ->
 *     `requests.visit_completed_at` -> `requests.completed_at`. `created_at`
 *     SENGAJA tidak dipakai: itu waktu permintaan dibuat, bukan waktu selesai.
 *   - Baris yang tidak bisa dipetakan ke kecamatan TIDAK dibuang; ia masuk
 *     keranjang `unknown_wilayah` supaya jumlah per kecamatan selalu bisa
 *     dijumlahkan kembali ke total kota.
 *   - `coded` hanya untuk `coding_state = 'coded'`; semua nilai lain (termasuk
 *     NULL dan 'uncoded') dihitung sebagai "belum dikode" - disebut apa adanya,
 *     bukan disembunyikan.
 *   - Semua kunci payload selalu ada, walau sumbernya belum tersedia, supaya
 *     tampilan tidak perlu menebak.
 *
 * Catatan satuan: satu baris diagnosis = satu diagnosis pada satu rekam medis.
 * `entries` = jumlah baris (dipakai untuk peringkat, sama seperti dasbor lama),
 * `records` = jumlah rekam medis terpisah (menunjukkan apakah angkanya datang
 * dari banyak kunjungan atau satu kunjungan dengan banyak diagnosis).
 *
 * Basis kelas: `CI_Model`, bukan `MX_Controller` seperti model lama di panel ini.
 * Model bukan controller; memakai basis framework membuat kelas ini bisa diuji
 * tanpa membuka aplikasi admin (lihat tools/admin_panel/tests/).
 */
class Diagnosis_infographic_m extends CI_Model
{
	const REASON_OK = 'ok';
	const REASON_SCHEMA = 'diagnosis_schema_unavailable';
	const REASON_WILAYAH = 'wilayah_master_empty';

	public function __construct()
	{
		parent::__construct();
	}

	/** Tabel & kolom yang dibutuhkan sudah ada? */
	public function canQueryDiagnosis()
	{
		return $this->db->table_exists('medicalrecord_diagnoses')
			&& $this->db->table_exists('medicalrecords')
			&& $this->db->table_exists('requests')
			&& $this->db->field_exists('diagnosis_code', 'medicalrecord_diagnoses')
			&& $this->db->field_exists('coding_state', 'medicalrecord_diagnoses')
			&& $this->db->field_exists('request_id', 'medicalrecords');
	}

	/** Pemetaan wilayah siap? Tabel ada DAN berisi. */
	public function canQueryWilayah()
	{
		if (!$this->db->table_exists('kecamatan') || !$this->db->table_exists('puskesmas')
			|| !$this->db->field_exists('id_kecamatan', 'puskesmas')) {
			return false;
		}
		return $this->db->count_all_results('kecamatan') > 0
			&& $this->db->count_all_results('puskesmas') > 0;
	}

	/**
	 * Ungkapan waktu kanonik. `created_at` tidak pernah masuk daftar ini.
	 * Mengembalikan 'NULL' bila tidak ada kolom waktu yang bisa dipakai -
	 * pemanggil harus memperlakukan itu sebagai "periode tidak dapat diterapkan",
	 * bukan sebagai "semua data".
	 */
	public function dateExpression()
	{
		$parts = array();
		if ($this->db->field_exists('clinical_finalized_at', 'medicalrecords')) {
			$parts[] = 'm.clinical_finalized_at';
		}
		if ($this->db->field_exists('visit_completed_at', 'requests')) {
			$parts[] = 'r.visit_completed_at';
		}
		if ($this->db->field_exists('completed_at', 'requests')) {
			$parts[] = 'r.completed_at';
		}
		if (empty($parts)) {
			return 'NULL';
		}
		return count($parts) === 1 ? $parts[0] : 'COALESCE(' . implode(', ', $parts) . ')';
	}

	/** Filter dari luar dibatasi ke nilai yang dikenal; sisanya jatuh ke default. */
	public function normalizeFilters(array $filters)
	{
		$days = (int) (isset($filters['days']) ? $filters['days'] : 30);
		if (!in_array($days, array(0, 7, 30, 90), true)) {
			$days = 30;
		}
		$kecamatan_id = (int) (isset($filters['kecamatan_id']) ? $filters['kecamatan_id'] : 0);
		if ($kecamatan_id < 0) {
			$kecamatan_id = 0;
		}
		$coding_state = strtolower(trim((string) (isset($filters['coding_state']) ? $filters['coding_state'] : '')));
		if (!in_array($coding_state, array('', 'coded', 'uncoded'), true)) {
			$coding_state = '';
		}
		return array('days' => $days, 'kecamatan_id' => $kecamatan_id, 'coding_state' => $coding_state);
	}

	public function periodLabel($days)
	{
		if ((int) $days === 0) {
			return 'Seluruh periode';
		}
		return (int) $days . ' hari terakhir';
	}

	/** Daftar kecamatan untuk filter. Kosong bila master wilayah belum diisi. */
	public function getKecamatanOptions()
	{
		if (!$this->db->table_exists('kecamatan')) {
			return array();
		}
		$rows = $this->db
			->select('id_kecamatan, nama_kecamatan')
			->order_by('nama_kecamatan', 'ASC')
			->get('kecamatan')
			->result();
		$options = array();
		foreach ($rows as $row) {
			$options[] = array(
				'id_kecamatan' => (int) $row->id_kecamatan,
				'nama_kecamatan' => (string) $row->nama_kecamatan,
			);
		}
		return $options;
	}

	/**
	 * Ungkapan yang dipakai di semua agregasi wilayah.
	 * Kode puskesmas pada `requests` dibandingkan dengan `puskesmas.kode_pkm`
	 * memakai kolasi yang sama supaya join tidak gagal karena perbedaan kolasi.
	 */
	private function wilayahExpressions()
	{
		return array(
			'code' => "CONVERT(NULLIF(TRIM(r.assigned_puskesmas_code), '') USING utf8mb4) COLLATE utf8mb4_general_ci",
			'label' => "COALESCE(NULLIF(TRIM(d.diagnosis_label), ''), NULLIF(TRIM(d.display_text), ''))",
		);
	}

	/** Filter umum: periode (bila mungkin), status pengodean, kecamatan. */
	private function applyCommonFilters($date_expr, array $filters)
	{
		if ($date_expr !== 'NULL' && (int) $filters['days'] > 0) {
			/* Tanpa pembungkus DATE(): `x >= DATE_SUB(CURDATE(), INTERVAL n DAY)`
			 * menghasilkan himpunan baris yang sama dengan `DATE(x) >= ...` untuk
			 * kolom DATETIME maupun DATE, tetapi tidak menutup jalan indeks.
			 *
			 * Hasil pengukuran 2026-10-23 (tools/admin_panel/tests/
			 * diagnosis_infographic_index_evidence.php, 2.000 rekam + 6.000 baris
			 * diagnosis): bentuk COALESCE ini TETAP memindai penuh `medicalrecords`
			 * (type=ALL) walau ada indeks pada `clinical_finalized_at`, karena
			 * fungsi atas kolom menutup akses rentang. Indeks itu karena itu tidak
			 * ditambahkan - lihat catatan di skrip bukti. Bila volume tumbuh,
			 * perbaikannya adalah kolom waktu kanonik yang dipelihara di
			 * `medicalrecords`, bukan indeks pada bentuk ini. */
			$this->db->where(
				$date_expr . ' >= DATE_SUB(CURDATE(), INTERVAL ' . (int) $filters['days'] . ' DAY)',
				NULL,
				FALSE
			);
		}
		if ($filters['coding_state'] === 'coded') {
			$this->db->where('d.coding_state', 'coded');
		} elseif ($filters['coding_state'] === 'uncoded') {
			$this->db->where("(d.coding_state IS NULL OR d.coding_state <> 'coded')", NULL, FALSE);
		}
	}

	/** Join dasar: diagnosis -> rekam medis -> permintaan (-> wilayah bila diminta). */
	private function baseQuery($date_expr, array $filters, $with_wilayah = false)
	{
		$this->db->from('medicalrecord_diagnoses d');
		$this->db->join('medicalrecords m', 'm.record_id = d.medicalrecord_id', 'inner');
		$this->db->join('requests r', 'r.request_id = m.request_id', 'inner');
		/* Filter kecamatan WAJIB berlaku juga untuk agregat kota. Tanpa join ini,
		 * angka kota akan mengabaikan filter dan tidak sama dengan jumlah rincian. */
		$need_wilayah = $with_wilayah || (int) $filters['kecamatan_id'] > 0;
		if ($need_wilayah) {
			$expr = $this->wilayahExpressions();
			$this->db->join('puskesmas p', 'p.kode_pkm = ' . $expr['code'], 'left', FALSE);
			$this->db->join('kecamatan k', 'k.id_kecamatan = p.id_kecamatan', 'left');
			if ((int) $filters['kecamatan_id'] > 0) {
				$this->db->where('k.id_kecamatan', (int) $filters['kecamatan_id']);
			}
		}
		$this->applyCommonFilters($date_expr, $filters);
	}

	/** Baris terkode vs belum terkode. Semua yang bukan 'coded' dihitung belum dikode. */
	public function getCodingStateBreakdown(array $filters)
	{
		$date_expr = $this->dateExpression();
		$this->db->select(
			"COUNT(*) AS entries, "
			. "SUM(CASE WHEN d.coding_state = 'coded' THEN 1 ELSE 0 END) AS coded, "
			. "SUM(CASE WHEN d.coding_state IS NULL OR d.coding_state <> 'coded' THEN 1 ELSE 0 END) AS uncoded, "
			. 'COUNT(DISTINCT d.medicalrecord_id) AS records',
			FALSE
		);
		$this->baseQuery($date_expr, $filters);
		$row = $this->db->get()->row();
		return array(
			'entries' => (int) ($row->entries ?? 0),
			'coded' => (int) ($row->coded ?? 0),
			'uncoded' => (int) ($row->uncoded ?? 0),
			'records' => (int) ($row->records ?? 0),
		);
	}

	/** Kode ICD-10 terbanyak di seluruh kota. Baris tanpa kode tidak masuk daftar ini. */
	public function getCityTopCodes(array $filters, $limit = 10)
	{
		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 10;
		}
		$date_expr = $this->dateExpression();
		$expr = $this->wilayahExpressions();
		$this->db->select(
			"TRIM(d.diagnosis_code) AS code, MAX({$expr['label']}) AS label, "
			. 'COUNT(*) AS entries, COUNT(DISTINCT d.medicalrecord_id) AS records',
			FALSE
		);
		$this->baseQuery($date_expr, $filters);
		$this->db->where("d.diagnosis_code IS NOT NULL AND TRIM(d.diagnosis_code) <> ''", NULL, FALSE);
		$this->db->group_by('TRIM(d.diagnosis_code)', FALSE);
		$this->db->order_by('entries', 'DESC');
		$this->db->order_by('code', 'ASC');
		$this->db->limit($limit);
		$rows = $this->db->get()->result();
		$codes = array();
		foreach ($rows as $row) {
			$codes[] = array(
				'code' => (string) $row->code,
				'label' => (string) ($row->label ?? ''),
				'entries' => (int) $row->entries,
				'records' => (int) $row->records,
			);
		}
		return $codes;
	}

	/**
	 * Isi keranjang "belum dikode" - ditampilkan apa adanya supaya admin tahu apa
	 * yang menumpuk di sana, bukan sekadar melihat angka kosong.
	 */
	public function getUncodedTopLabels(array $filters, $limit = 5)
	{
		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 5;
		}
		$date_expr = $this->dateExpression();
		$expr = $this->wilayahExpressions();
		$this->db->select(
			"COALESCE({$expr['label']}, 'Tanpa keterangan') AS label, COUNT(*) AS entries",
			FALSE
		);
		$this->baseQuery($date_expr, $filters);
		$this->db->where("(d.coding_state IS NULL OR d.coding_state <> 'coded')", NULL, FALSE);
		$this->db->group_by("COALESCE({$expr['label']}, 'Tanpa keterangan')", FALSE);
		$this->db->order_by('entries', 'DESC');
		$this->db->limit($limit);
		$rows = $this->db->get()->result();
		$labels = array();
		foreach ($rows as $row) {
			$labels[] = array('label' => (string) $row->label, 'entries' => (int) $row->entries);
		}
		return $labels;
	}

	/** Jumlah baris yang kode puskesmasnya tidak ada di master wilayah. */
	public function getUnmappedWilayahTotal(array $filters)
	{
		$date_expr = $this->dateExpression();
		$this->db->select('COUNT(*) AS entries', FALSE);
		$this->baseQuery($date_expr, $filters, true);
		$this->db->where('k.id_kecamatan IS NULL', NULL, FALSE);
		$row = $this->db->get()->row();
		return (int) ($row->entries ?? 0);
	}

	/** Jumlah kode ICD-10 berbeda yang muncul pada periode terpilih. */
	public function getDistinctCodeCount(array $filters)
	{
		$date_expr = $this->dateExpression();
		$this->db->select('COUNT(DISTINCT TRIM(d.diagnosis_code)) AS total', FALSE);
		$this->baseQuery($date_expr, $filters);
		$this->db->where("d.diagnosis_code IS NOT NULL AND TRIM(d.diagnosis_code) <> ''", NULL, FALSE);
		$row = $this->db->get()->row();
		return (int) ($row->total ?? 0);
	}

	/**
	 * Agregasi per kecamatan. Baris tanpa pemetaan tidak masuk sini - ia dilaporkan
	 * terpisah sebagai `unknown_wilayah` supaya penjumlahannya tetap utuh.
	 */
	public function getByKecamatan(array $filters)
	{
		$date_expr = $this->dateExpression();
		$this->db->select(
			'k.id_kecamatan AS kecamatan_id, k.nama_kecamatan AS kecamatan, '
			. 'COUNT(*) AS entries, COUNT(DISTINCT d.medicalrecord_id) AS records, '
			. "SUM(CASE WHEN d.coding_state = 'coded' THEN 1 ELSE 0 END) AS coded, "
			. "SUM(CASE WHEN d.coding_state IS NULL OR d.coding_state <> 'coded' THEN 1 ELSE 0 END) AS uncoded",
			FALSE
		);
		$this->baseQuery($date_expr, $filters, true);
		$this->db->where('k.id_kecamatan IS NOT NULL', NULL, FALSE);
		$this->db->group_by('k.id_kecamatan, k.nama_kecamatan', FALSE);
		$this->db->order_by('entries', 'DESC');
		$this->db->order_by('k.nama_kecamatan', 'ASC');
		$rows = $this->db->get()->result();

		/* Diagnosis terbanyak per kecamatan diambil dari kelompok (kecamatan, kode)
		 * yang diurutkan menurun, lalu pemenang pertama tiap kecamatan dipakai.
		 * Sengaja tanpa GROUP_CONCAT (daftar kode bisa panjang dan sulit dibaca)
		 * dan tanpa LIMIT, supaya tidak ada pemenang yang terpotong. */
		$top_by_kecamatan = array();
		$this->db->select(
			'k.id_kecamatan AS kecamatan_id, TRIM(d.diagnosis_code) AS code, '
			. "MAX(COALESCE(NULLIF(TRIM(d.diagnosis_label), ''), NULLIF(TRIM(d.display_text), ''))) AS label, "
			. 'COUNT(*) AS entries',
			FALSE
		);
		$this->baseQuery($date_expr, $filters, true);
		$this->db->where('k.id_kecamatan IS NOT NULL', NULL, FALSE);
		$this->db->where("d.diagnosis_code IS NOT NULL AND TRIM(d.diagnosis_code) <> ''", NULL, FALSE);
		$this->db->group_by('k.id_kecamatan, TRIM(d.diagnosis_code)', FALSE);
		$this->db->order_by('entries', 'DESC');
		foreach ($this->db->get()->result() as $row) {
			$key = (int) $row->kecamatan_id;
			if (!isset($top_by_kecamatan[$key])) {
				$top_by_kecamatan[$key] = array(
					'code' => (string) $row->code,
					'label' => (string) ($row->label ?? ''),
					'entries' => (int) $row->entries,
				);
			}
		}

		$kecamatan = array();
		foreach ($rows as $row) {
			$key = (int) $row->kecamatan_id;
			$top = isset($top_by_kecamatan[$key])
				? $top_by_kecamatan[$key]
				: array('code' => '', 'label' => '', 'entries' => 0);
			$kecamatan[] = array(
				'kecamatan_id' => $key,
				'kecamatan' => (string) $row->kecamatan,
				'entries' => (int) $row->entries,
				'records' => (int) $row->records,
				'coded' => (int) $row->coded,
				'uncoded' => (int) $row->uncoded,
				'top_code' => $top['code'],
				'top_label' => $top['label'],
				'top_entries' => $top['entries'],
			);
		}
		return $kecamatan;
	}

	/** Sebaran satu kode ICD-10 lintas kecamatan (untuk drill-down). */
	public function getCodeByKecamatan($code, array $filters)
	{
		$code = strtoupper(trim((string) $code));
		if ($code === '' || preg_match('/\A[A-Z][0-9]{2}([.][0-9A-Z]{1,4})?\z/', $code) !== 1) {
			return array();
		}
		$date_expr = $this->dateExpression();
		$this->db->select(
			'k.nama_kecamatan AS kecamatan, COUNT(*) AS entries, COUNT(DISTINCT d.medicalrecord_id) AS records',
			FALSE
		);
		$this->baseQuery($date_expr, $filters, true);
		$this->db->where('k.id_kecamatan IS NOT NULL', NULL, FALSE);
		$this->db->where('TRIM(d.diagnosis_code)', $code);
		$this->db->group_by('k.id_kecamatan, k.nama_kecamatan', FALSE);
		$this->db->order_by('entries', 'DESC');
		$rows = $this->db->get()->result();
		$distribution = array();
		foreach ($rows as $row) {
			$distribution[] = array(
				'kecamatan' => (string) $row->kecamatan,
				'entries' => (int) $row->entries,
				'records' => (int) $row->records,
			);
		}
		return $distribution;
	}

	/** Payload lengkap untuk halaman infografis. Semua kunci selalu ada. */
	public function getInfographic(array $filters)
	{
		$filters = $this->normalizeFilters($filters);
		$date_expr = $this->dateExpression();
		$days = (int) $filters['days'];

		$payload = array(
			'source_available' => $this->canQueryDiagnosis(),
			'wilayah_available' => $this->canQueryWilayah(),
			'reason' => self::REASON_OK,
			'period' => array(
				'days' => $days,
				'label' => $this->periodLabel($days),
				'from' => $days > 0 ? date('Y-m-d', strtotime('-' . $days . ' days')) : null,
				'to' => date('Y-m-d'),
				'date_basis' => $date_expr === 'NULL' ? 'unavailable' : 'canonical_completion',
			),
			'city' => array(
				'total_entries' => 0,
				'distinct_records' => 0,
				'coded' => 0,
				'uncoded' => 0,
				'distinct_codes' => 0,
				'kecamatan_with_data' => 0,
				'top_codes' => array(),
				'uncoded_top' => array(),
			),
			'by_kecamatan' => array(),
			'unknown_wilayah' => array('entries' => 0, 'note' => ''),
			'max_kecamatan_entries' => 0,
			'max_city_code_entries' => 0,
		);

		if (!$payload['source_available']) {
			$payload['reason'] = self::REASON_SCHEMA;
			$payload['unknown_wilayah']['note'] = 'Tabel diagnosis belum tersedia, jadi belum ada yang bisa dihitung.';
			return $payload;
		}
		if (!$payload['wilayah_available']) {
			$payload['reason'] = self::REASON_WILAYAH;
		}

		$breakdown = $this->getCodingStateBreakdown($filters);
		$top_codes = $this->getCityTopCodes($filters, 10);
		$payload['city'] = array(
			'total_entries' => $breakdown['entries'],
			'distinct_records' => $breakdown['records'],
			'coded' => $breakdown['coded'],
			'uncoded' => $breakdown['uncoded'],
			'distinct_codes' => $this->getDistinctCodeCount($filters),
			'kecamatan_with_data' => 0,
			'top_codes' => $top_codes,
			'uncoded_top' => $this->getUncodedTopLabels($filters, 5),
		);
		foreach ($top_codes as $row) {
			$payload['max_city_code_entries'] = max($payload['max_city_code_entries'], (int) $row['entries']);
		}

		if ($payload['wilayah_available']) {
			$payload['by_kecamatan'] = $this->getByKecamatan($filters);
			$payload['city']['kecamatan_with_data'] = count($payload['by_kecamatan']);
			foreach ($payload['by_kecamatan'] as $row) {
				$payload['max_kecamatan_entries'] = max($payload['max_kecamatan_entries'], (int) $row['entries']);
			}
			$payload['unknown_wilayah'] = array(
				'entries' => $this->getUnmappedWilayahTotal($filters),
				'note' => 'Baris yang kode puskesmasnya tidak ada di master wilayah. Tidak dihilangkan dari total kota.',
			);
		} else {
			$payload['unknown_wilayah']['note'] = 'Master wilayah (kecamatan + puskesmas) belum diisi, jadi rincian per kecamatan belum bisa dihitung. Total kota tetap ditampilkan.';
		}

		return $payload;
	}
}

