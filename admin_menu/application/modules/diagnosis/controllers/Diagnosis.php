<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Infografis penyakit terbanyak per kecamatan dan seluruh Kota Cilegon.
 *
 * Halaman ini hanya menampilkan apa yang benar-benar ada di database. Saat
 * diagnosis belum dikode, angkanya kecil - dan itu ditulis apa adanya, bukan
 * ditutupi angka contoh. Semua perhitungan ada di `Diagnosis_infographic_m`;
 * controller ini hanya menyiapkan filter, merender, dan melayani unduhan.
 *
 * Ekspor CSV dihitung di server dari payload yang SAMA dengan halaman, supaya
 * angka di layar dan angka di berkas tidak mungkin berbeda.
 */
class Diagnosis extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Diagnosis_infographic_m');
		/* Helper form dipakai untuk membuka formulir filter: form_open() otomatis
		 * menyertakan token CSRF, yang wajib karena csrf_protection = TRUE. */
		$this->load->helper('form');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			show_error('Akses admin diperlukan.', 403, 'Akses ditolak');
		}
	}

	/**
	 * Halaman infografis.
	 *
	 * Filter datang sebagai SEGMEN URL (`diagnosis/index/30/0/coded`), bukan query
	 * string. Alasannya teknis dan sudah diuji: `index_page` panel bernilai
	 * 'index.php?', sehingga CI menempelkan seluruh query string ke URI; karakter
	 * '=' dan '&' tidak ada di `permitted_uri_chars` dan permintaan dijawab 400.
	 */
	public function index($days = null, $kecamatan_id = null, $coding_state = null)
	{
		$this->session->set_flashdata('title', 'Infografis penyakit terbanyak');
		$filters = $this->filters_from_args($days, $kecamatan_id, $coding_state);
		$this->load->view('commons/header');
		$this->load->view('diagnosis_v', array(
			'infographic' => $this->Diagnosis_infographic_m->getInfographic($filters),
			'filters' => $filters,
			'kecamatan_options' => $this->Diagnosis_infographic_m->getKecamatanOptions(),
		));
		$this->load->view('commons/footer');
	}

	/**
	 * Terapkan filter dari formulir (POST), lalu arahkan ke URL segmen.
	 * POST dipakai karena query string tidak bisa dipakai di panel ini; setelah
	 * diarahkan, alamatnya bisa disalin, di-bookmark, dan di-refresh tanpa
	 * mengirim ulang formulir.
	 */
	public function filter()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect('diagnosis', 'refresh');
			return;
		}
		$filters = $this->Diagnosis_infographic_m->normalizeFilters(array(
			'days' => (string) $this->input->post('days', true),
			'kecamatan_id' => (string) $this->input->post('kecamatan_id', true),
			'coding_state' => (string) $this->input->post('coding_state', true),
		));
		redirect(
			'diagnosis/index/' . (int) $filters['days'] . '/' . (int) $filters['kecamatan_id'] . '/' . $filters['coding_state'],
			'refresh'
		);
	}

	/** Unduh CSV dari payload yang sama dengan yang tampil di layar. */
	public function export($days = null, $kecamatan_id = null, $coding_state = null)
	{
		$filters = $this->filters_from_args($days, $kecamatan_id, $coding_state);
		$payload = $this->Diagnosis_infographic_m->getInfographic($filters);

		$csv = $this->csv_line(array('Infografis penyakit terbanyak - Kota Cilegon'));
		$csv .= $this->csv_line(array('Periode', $payload['period']['label']));
		$csv .= $this->csv_line(array('Dasar waktu', 'clinical_finalized_at, lalu visit_completed_at, lalu completed_at'));
		$csv .= $this->csv_line(array('Sumber', 'medicalrecord_diagnoses (ICD-10)'));
		$csv .= $this->csv_line(array('Jumlah diagnosis tercatat', $payload['city']['total_entries']));
		$csv .= $this->csv_line(array('Rekam medis terpisah', $payload['city']['distinct_records']));
		$csv .= $this->csv_line(array('Terkode', $payload['city']['coded']));
		$csv .= $this->csv_line(array('Belum dikode', $payload['city']['uncoded']));
		if (!$payload['wilayah_available']) {
			$csv .= $this->csv_line(array('Catatan', $payload['unknown_wilayah']['note']));
		}
		$csv .= $this->csv_line(array(''));
		$csv .= $this->csv_line(array('Kode ICD-10 terbanyak (seluruh kota)'));
		$csv .= $this->csv_line(array('Kode', 'Label', 'Diagnosis tercatat', 'Rekam medis'));
		foreach ($payload['city']['top_codes'] as $row) {
			$csv .= $this->csv_line(array($row['code'], $row['label'], $row['entries'], $row['records']));
		}
		$csv .= $this->csv_line(array(''));
		$csv .= $this->csv_line(array('Ringkasan per kecamatan'));
		$csv .= $this->csv_line(array('Kecamatan', 'Diagnosis tercatat', 'Rekam medis', 'Terkode', 'Belum dikode', 'Kode terbanyak', 'Label kode terbanyak'));
		foreach ($payload['by_kecamatan'] as $row) {
			$csv .= $this->csv_line(array(
				$row['kecamatan'], $row['entries'], $row['records'], $row['coded'], $row['uncoded'],
				$row['top_code'], $row['top_label'],
			));
		}
		$csv .= $this->csv_line(array(''));
		$csv .= $this->csv_line(array('Tanpa pemetaan wilayah', $payload['unknown_wilayah']['entries']));
		$csv .= $this->csv_line(array('Catatan', $payload['unknown_wilayah']['note']));

		$filename = 'infografis-diagnosis-cilegon-' . ($payload['period']['from'] === null ? 'seluruh-periode' : $payload['period']['from'] . '_' . $payload['period']['to']) . '.csv';
		$this->output
			->set_content_type('text/csv', 'utf-8')
			->set_header('Content-Disposition: attachment; filename="' . $filename . '"')
			->set_header('Cache-Control: no-store')
			->set_output($csv);
	}

	/**
	 * Sebaran satu kode ICD-10 per kecamatan, untuk drill-down di halaman.
	 * Semua masukan lewat segmen URL (alasan sama dengan `index()`), jadi tidak
	 * ada query string yang bisa ditolak URI filter.
	 */
	public function code_distribution($code = null, $days = null, $kecamatan_id = null, $coding_state = null)
	{
		$this->output->set_content_type('application/json')->set_header('Cache-Control: no-store');
		if ($this->input->method(TRUE) !== 'GET') {
			$this->json_response(405, false, 'method_not_allowed');
			return;
		}
		$code = strtoupper(trim((string) $code));
		if ($code === '' || preg_match('/\A[A-Z][0-9]{2}([.][0-9A-Z]{1,4})?\z/', $code) !== 1) {
			$this->json_response(400, false, 'code_invalid');
			return;
		}
		$filters = $this->filters_from_args($days, $kecamatan_id, $coding_state);
		if ($this->Diagnosis_infographic_m->canQueryDiagnosis() !== true
			|| $this->Diagnosis_infographic_m->canQueryWilayah() !== true) {
			$this->json_response(503, false, 'wilayah_master_empty');
			return;
		}
		$this->json_response(200, true, 'ok', array(
			'code' => $code,
			'rows' => $this->Diagnosis_infographic_m->getCodeByKecamatan($code, $filters),
		));
	}

	/** Filter dari segmen URL; nilai di luar daftar dikenal dibatasi model. */
	private function filters_from_args($days, $kecamatan_id, $coding_state)
	{
		return $this->Diagnosis_infographic_m->normalizeFilters(array(
			'days' => (string) $days,
			'kecamatan_id' => (string) $kecamatan_id,
			'coding_state' => (string) $coding_state,
		));
	}

	private function json_response($status, $success, $code, array $data = array())
	{
		$body = array('success' => (bool) $success, 'safe_error_code' => (string) $code);
		if ($success) {
			$body['data'] = $data;
		}
		$this->output->set_status_header((int) $status)->set_output(json_encode($body));
	}

	/** Satu baris CSV dengan kutip RFC 4180 supaya koma di label tidak merusak kolom. */
	private function csv_line(array $cells)
	{
		$escaped = array();
		foreach ($cells as $cell) {
			$escaped[] = '"' . str_replace('"', '""', (string) $cell) . '"';
		}
		return implode(',', $escaped) . "\r\n";
	}
}
