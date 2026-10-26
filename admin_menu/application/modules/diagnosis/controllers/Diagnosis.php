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
		/* Panel pengodean memakai model sendiri: ia yang tahu master ICD-10 dan
		 * satu-satunya tempat yang menulis `coding_state` dari panel ini. */
		$this->load->model('Diagnosis_coding_m');
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
			'coding' => $this->coding_panel(),
		));
		$this->load->view('commons/footer');
	}

	/**
	 * Data panel "Pengodean diagnosis".
	 *
	 * Daftar teks belum dikode sengaja TIDAK memakai filter periode/kecamatan
	 * (lihat catatan di `Diagnosis_infographic_m::getUncodedTextGroups`): satu
	 * teks yang sama muncul di banyak kunjungan, dan memperbaiki sebagian saja
	 * akan menyisakan pekerjaan yang sama besok. Panel menjelaskan itu ke pembaca.
	 */
	private function coding_panel()
	{
		$empty = array(
			'available' => false,
			'master_available' => false,
			'groups' => array(),
			'total_rows' => 0,
			'truncated' => false,
			'totals' => array(
				'groups' => 0, 'rows' => 0,
				'ready_groups' => 0, 'ready_rows' => 0,
				'review_groups' => 0, 'review_rows' => 0,
				'manual_groups' => 0, 'manual_rows' => 0,
			),
		);
		if (!$this->Diagnosis_coding_m->schemaReady() || !$this->Diagnosis_infographic_m->canQueryDiagnosis()) {
			return $empty;
		}
		$analysis = $this->Diagnosis_coding_m->analyze($this->Diagnosis_infographic_m->getUncodedTextGroups(100));
		$analysis['available'] = true;
		$analysis['total_rows'] = $this->Diagnosis_infographic_m->countUncodedRows();
		/* Daftar dibatasi 100 teks. Kalau total baris lebih besar dari jumlah
		 * baris yang tampil, panel harus mengatakannya - bukan menyimpulkan
		 * "semua sudah dikode" dari daftar yang terpotong. */
		$analysis['truncated'] = (int) $analysis['total_rows'] > (int) $analysis['totals']['rows'];
		return $analysis;
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

	/**
	 * "Kodekan otomatis": menulis kode untuk semua teks yang kodenya sudah
	 * tertulis di dalam teks dan sah di master ICD-10.
	 *
	 * Hanya POST. Setelah selesai, pengguna dikembalikan ke halaman dengan
	 * filter yang sama - bukan ke halaman kosong - supaya angka yang berubah
	 * bisa langsung dibandingkan.
	 */
	public function repair($days = null, $kecamatan_id = null, $coding_state = null)
	{
		$filters = $this->filters_from_args($days, $kecamatan_id, $coding_state);
		if (!$this->require_post($filters, 'Pengodean otomatis harus dijalankan dari tombol di halaman diagnosis.')) {
			return;
		}
		if (!$this->Diagnosis_coding_m->schemaReady() || !$this->Diagnosis_infographic_m->canQueryDiagnosis()) {
			$this->finish($filters, 'error', 'Tabel diagnosis belum tersedia, jadi tidak ada yang bisa dikodekan.');
			return;
		}
		$result = $this->Diagnosis_coding_m->repair($this->Diagnosis_infographic_m->getUncodedTextGroups(100));
		if (!$result['ok']) {
			$this->finish($filters, 'error', 'Tidak ada yang diubah: ' . Diagnosis_coding_m::reasonLabel($result['reason']));
			return;
		}
		if ((int) $result['rows'] < 1) {
			$this->finish($filters, 'error', 'Tidak ada baris yang berubah. Mungkin teks yang bisa dikode otomatis sudah habis.');
			return;
		}
		$remaining = 0;
		foreach ($this->Diagnosis_infographic_m->getUncodedTextGroups(200) as $group) {
			$remaining += (int) $group['entries'];
		}
		$this->audit('diagnosis_coding_repair', array(
			'rows' => (int) $result['rows'],
			'texts' => (int) $result['texts'],
			'remaining_uncoded' => $remaining,
		));
		$this->finish($filters, 'success', number_format((int) $result['rows']) . ' baris diagnosis dikodekan otomatis dari '
			. number_format((int) $result['texts']) . ' teks. Sisa yang belum dikode: ' . number_format($remaining) . ' baris.');
	}

	/**
	 * Halaman pemilih kode untuk satu teks yang tidak memuat kode ICD-10.
	 * POST dari panel (teks yang akan dikodekan ikut terkirim), lalu halaman ini
	 * menampilkan hasil pencarian master dengan tombol "Pilih kode ini".
	 */
	public function pilih()
	{
		$filters = $this->filters_from_post();
		if (!$this->require_post($filters, 'Halaman pemilih kode dibuka dari tombol "Pilih kode" pada halaman diagnosis.')) {
			return;
		}
		$text = $this->posted_text();
		if ($text === '') {
			$this->finish($filters, 'error', 'Teks diagnosis tidak terbaca, jadi kode tidak bisa dipilih. Muat ulang halaman diagnosis.');
			return;
		}
		$submitted = trim((string) $this->input->post('q')) !== '';
		$query = trim((string) $this->input->post('q'));
		if ($query === '') {
			$query = $this->default_query($text);
		}
		$this->session->set_flashdata('title', 'Pilih kode ICD-10');
		$entries = $this->Diagnosis_infographic_m->countUncodedText($text);
		$search = $this->Diagnosis_coding_m->search($query, 25);
		/* Kata kunci awal berasal dari teks diagnosis, bukan dari admin. Halaman
		 * pemilih memakainya untuk membedakan "belum ada yang cocok dengan teks
		 * ini" dari "kata kunci yang Anda ketik tidak ada". */
		$search['submitted'] = $submitted;
		$this->load->view('commons/header');
		$this->load->view('diagnosis_pilih_v', array(
			'text' => $text,
			'entries' => $entries,
			'filters' => $filters,
			'search' => $search,
			'master_available' => $this->Diagnosis_coding_m->masterAvailable(),
		));
		$this->load->view('commons/footer');
	}

	/** Simpan kode pilihan admin untuk satu teks. */
	public function assign()
	{
		$filters = $this->filters_from_post();
		if (!$this->require_post($filters, 'Kode dipilih dari halaman pemilih kode ICD-10.')) {
			return;
		}
		$text = $this->posted_text();
		$code = strtoupper(trim((string) $this->input->post('code')));
		if ($text === '') {
			$this->finish($filters, 'error', 'Teks diagnosis tidak terbaca, jadi tidak ada yang diubah.');
			return;
		}
		$result = $this->Diagnosis_coding_m->assign($text, $code);
		if (!$result['ok']) {
			$this->finish($filters, 'error', 'Tidak ada yang diubah: ' . Diagnosis_coding_m::reasonLabel($result['reason'], $code));
			return;
		}
		$this->audit('diagnosis_coding_assign', array(
			'text' => $text,
			'code' => (string) $result['code'],
			'rows' => (int) $result['rows'],
		));
		if ((int) $result['rows'] < 1) {
			$this->finish($filters, 'error', 'Kode ' . $result['code'] . ' tidak dipakai: tidak ada baris belum dikode dengan teks itu (mungkin sudah dikode sebelumnya).');
			return;
		}
		$this->finish($filters, 'success', number_format((int) $result['rows']) . ' baris dengan teks "'
			. $this->short_text($text) . '" sekarang dikode ' . $result['code'] . '.');
	}

	/** Kata kunci awal pemilih manual: kode yang tertulis di teks, atau labelnya. */
	private function default_query($text)
	{
		require_once dirname(APPPATH, 2) . '/application/libraries/Icd10_code_text.php';
		$parsed = Icd10_code_text::extract($text);
		if ($parsed['code'] !== null) {
			return $parsed['code'];
		}
		return $parsed['label'] === '' ? (string) $text : $parsed['label'];
	}

	/**
	 * Teks diagnosis dari formulir, dibaca APA ADANYA (tanpa filter XSS).
	 * Alasan: teks ini dicocokkan persis dengan kolom `display_text`; mengubah
	 * "&" menjadi "&amp;" membuat pencocokan gagal tanpa pesan yang jelas.
	 * Keamanan tampilan tetap dijaga `html_escape()` di view.
	 */
	private function posted_text()
	{
		$text = (string) $this->input->post('text');
		$text = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
		$text = trim($text);
		if ($text === '' || preg_match('//u', $text) !== 1) {
			return '';
		}
		if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 320) {
			return '';
		}
		return $text;
	}

	private function short_text($text)
	{
		$text = (string) $text;
		if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 60) {
			return mb_substr($text, 0, 57, 'UTF-8') . '...';
		}
		return $text;
	}

	private function filters_from_post()
	{
		return $this->filters_from_args(
			(string) $this->input->post('days'),
			(string) $this->input->post('kecamatan_id'),
			(string) $this->input->post('coding_state')
		);
	}

	/**
	 * POST wajib. GET hanya diarahkan kembali dengan pesan: tautan yang bisa
	 * diklik dari luar tidak boleh mengubah data diagnosis.
	 */
	private function require_post(array $filters, $message)
	{
		if ($this->input->method(TRUE) === 'POST') {
			return true;
		}
		$this->output->set_status_header(405);
		$this->session->set_flashdata('error', (string) $message);
		redirect($this->index_url($filters), 'refresh');
		return false;
	}

	private function finish(array $filters, $flash, $message)
	{
		$this->session->set_flashdata($flash === 'success' ? 'success' : 'error', (string) $message);
		redirect($this->index_url($filters), 'refresh');
	}

	private function index_url(array $filters)
	{
		return 'diagnosis/index/' . (int) $filters['days'] . '/' . (int) $filters['kecamatan_id'] . '/' . (string) $filters['coding_state'];
	}

	/** Catat perubahan pengodean. Kegagalan menulis audit tidak menghentikan permintaan. */
	private function audit($action, array $metadata)
	{
		if (!$this->db->table_exists('audit_logs')) {
			return;
		}
		$this->db->db_debug = false;
		$this->db->insert('audit_logs', array(
			'actor_user_id' => (int) $this->session->userdata('id'),
			'action' => (string) $action,
			'entity_type' => 'medicalrecord_diagnoses',
			'entity_id' => null,
			'ip_address' => (string) $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'created_at' => date('Y-m-d H:i:s'),
		));
		$this->db->db_debug = true;
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
