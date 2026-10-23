<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Master wilayah: kecamatan dan puskesmas (pemetaan kode -> kecamatan).
 *
 * Halaman ini mengurus sumber data yang dipakai infografis penyakit terbanyak.
 * Karena itu setiap perubahan dicatat di `audit_logs` (entity_type 'wilayah'),
 * dan penghapusan yang akan merusak pemetaan ditolak dengan alasan yang jelas -
 * bukan dijalankan lalu dilaporkan belakangan.
 *
 * Catatan jalur audit: `Admin_audit_log_writer` khusus kapabilitas admin
 * (entity_type-nya dipaku 'admin_user_capability'), jadi baris audit di sini
 * ditulis langsung dengan bentuk yang sama seperti gate sesi aplikasi utama:
 * db_debug dimatikan supaya kegagalan menulis audit tidak pernah menggagalkan
 * permintaan pengguna.
 */
class Wilayah extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Wilayah_m');
		$this->load->helper('form');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			show_error('Akses admin diperlukan.', 403, 'Akses ditolak');
		}
	}

	public function index()
	{
		$this->session->set_flashdata('title', 'Master wilayah');
		$this->load->view('commons/header');
		$this->load->view('wilayah_v', array(
			'schema_ready' => $this->Wilayah_m->schemaReady(),
			'kecamatan_list' => $this->Wilayah_m->schemaReady() ? $this->Wilayah_m->getKecamatanList() : array(),
			'puskesmas_list' => $this->Wilayah_m->schemaReady() ? $this->Wilayah_m->getPuskesmasList() : array(),
		));
		$this->load->view('commons/footer');
	}

	public function store_kecamatan()
	{
		if (!$this->require_post()) {
			return;
		}
		$nama = $this->posted_text('nama_kecamatan');
		if ($nama === '') {
			$this->fail('Nama kecamatan wajib diisi.');
			return;
		}
		if ($this->Wilayah_m->kecamatanNameExists($nama)) {
			$this->fail('Nama kecamatan itu sudah ada.');
			return;
		}
		$id = $this->Wilayah_m->createKecamatan($nama);
		$this->audit('wilayah_kecamatan_created', $id, array('nama_kecamatan' => $nama));
		$this->succeed('Kecamatan ditambahkan.');
	}

	public function update_kecamatan($id = 0)
	{
		if (!$this->require_post()) {
			return;
		}
		$current = $this->Wilayah_m->getKecamatanById($id);
		if (!$current) {
			$this->fail('Kecamatan tidak ditemukan.');
			return;
		}
		$nama = $this->posted_text('nama_kecamatan');
		if ($nama === '') {
			$this->fail('Nama kecamatan wajib diisi.');
			return;
		}
		if ($this->Wilayah_m->kecamatanNameExists($nama, (int) $current->id_kecamatan)) {
			$this->fail('Nama kecamatan itu sudah dipakai kecamatan lain.');
			return;
		}
		if ($nama === (string) $current->nama_kecamatan) {
			$this->succeed('Tidak ada perubahan pada kecamatan.');
			return;
		}
		$this->Wilayah_m->updateKecamatan($current->id_kecamatan, $nama);
		$this->audit('wilayah_kecamatan_updated', (int) $current->id_kecamatan, array(
			'sebelum' => (string) $current->nama_kecamatan,
			'sesudah' => $nama,
		));
		$this->succeed('Kecamatan diperbarui.');
	}

	public function delete_kecamatan($id = 0)
	{
		if (!$this->require_post()) {
			return;
		}
		$current = $this->Wilayah_m->getKecamatanById($id);
		if (!$current) {
			$this->fail('Kecamatan tidak ditemukan.');
			return;
		}
		$puskesmas_count = $this->Wilayah_m->puskesmasCountInKecamatan($current->id_kecamatan);
		if ($puskesmas_count > 0) {
			$this->fail('Kecamatan ' . $current->nama_kecamatan . ' masih punya ' . $puskesmas_count . ' puskesmas. Pindahkan atau hapus puskesmas itu lebih dulu.');
			return;
		}
		$this->Wilayah_m->deleteKecamatan($current->id_kecamatan);
		$this->audit('wilayah_kecamatan_deleted', (int) $current->id_kecamatan, array('nama_kecamatan' => (string) $current->nama_kecamatan));
		$this->succeed('Kecamatan dihapus.');
	}

	public function store_puskesmas()
	{
		if (!$this->require_post()) {
			return;
		}
		$kode = $this->posted_text('kode_pkm');
		$nama = $this->posted_text('nama_puskesmas');
		$id_kecamatan = (int) $this->input->post('id_kecamatan', TRUE);
		if ($kode === '') {
			/* Tanpa kode, puskesmas tidak akan pernah terpetakan dari permintaan
			 * layanan - dan infografis per kecamatan akan selalu kosong. */
			$this->fail('Kode puskesmas wajib diisi supaya bisa dipetakan dari permintaan layanan.');
			return;
		}
		if ($nama === '') {
			$this->fail('Nama puskesmas wajib diisi.');
			return;
		}
		if (!$this->Wilayah_m->getKecamatanById($id_kecamatan)) {
			$this->fail('Kecamatan yang dipilih tidak ditemukan.');
			return;
		}
		if ($this->Wilayah_m->codeExists($kode)) {
			$this->fail('Kode puskesmas ' . $kode . ' sudah dipakai puskesmas lain.');
			return;
		}
		$id = $this->Wilayah_m->createPuskesmas($kode, $nama, $id_kecamatan);
		$this->audit('wilayah_puskesmas_created', $id, array(
			'kode_pkm' => $kode,
			'nama_puskesmas' => $nama,
			'id_kecamatan' => $id_kecamatan,
		));
		$this->succeed('Puskesmas ditambahkan.');
	}

	public function update_puskesmas($id = 0)
	{
		if (!$this->require_post()) {
			return;
		}
		$current = $this->Wilayah_m->getPuskesmasById($id);
		if (!$current) {
			$this->fail('Puskesmas tidak ditemukan.');
			return;
		}
		$kode = $this->posted_text('kode_pkm');
		$nama = $this->posted_text('nama_puskesmas');
		$id_kecamatan = (int) $this->input->post('id_kecamatan', TRUE);
		if ($kode === '' || $nama === '') {
			$this->fail('Kode dan nama puskesmas wajib diisi.');
			return;
		}
		if (!$this->Wilayah_m->getKecamatanById($id_kecamatan)) {
			$this->fail('Kecamatan yang dipilih tidak ditemukan.');
			return;
		}
		if ($this->Wilayah_m->codeExists($kode, (int) $current->id_puskesmas)) {
			$this->fail('Kode puskesmas ' . $kode . ' sudah dipakai puskesmas lain.');
			return;
		}
		/* Mengubah kode berarti memutus pemetaan permintaan yang memakai kode lama.
		 * Perubahan tetap boleh, tetapi akibatnya disebut di muka. */
		$old_usage = $this->Wilayah_m->codeUsageCount($current->kode_pkm);
		if ($old_usage > 0 && strcasecmp(trim((string) $current->kode_pkm), $kode) !== 0) {
			$this->fail(
				'Kode ' . $current->kode_pkm . ' masih dipakai ' . $old_usage . ' permintaan layanan. '
				. 'Mengubahnya akan memindahkan baris-baris itu ke keranjang "tanpa pemetaan wilayah". '
				. 'Perbaiki kode pada permintaan lebih dulu, atau buat puskesmas baru untuk kode tersebut.'
			);
			return;
		}
		$this->Wilayah_m->updatePuskesmas($current->id_puskesmas, $kode, $nama, $id_kecamatan);
		$this->audit('wilayah_puskesmas_updated', (int) $current->id_puskesmas, array(
			'sebelum' => array(
				'kode_pkm' => (string) $current->kode_pkm,
				'nama_puskesmas' => (string) $current->nama_puskesmas,
				'id_kecamatan' => (int) $current->id_kecamatan,
			),
			'sesudah' => array(
				'kode_pkm' => $kode,
				'nama_puskesmas' => $nama,
				'id_kecamatan' => $id_kecamatan,
			),
		));
		$this->succeed('Puskesmas diperbarui.');
	}

	public function delete_puskesmas($id = 0)
	{
		if (!$this->require_post()) {
			return;
		}
		$current = $this->Wilayah_m->getPuskesmasById($id);
		if (!$current) {
			$this->fail('Puskesmas tidak ditemukan.');
			return;
		}
		$usage = $this->Wilayah_m->codeUsageCount($current->kode_pkm);
		if ($usage > 0) {
			$this->fail(
				/* Nama puskesmas sudah memuat kata "Puskesmas", jadi tidak diulang. */
				$current->nama_puskesmas . ' tidak dihapus: kodenya (' . $current->kode_pkm . ') masih dipakai '
				. $usage . ' permintaan layanan. Menghapusnya akan memindahkan baris itu ke keranjang "tanpa pemetaan wilayah".'
			);
			return;
		}
		$this->Wilayah_m->deletePuskesmas($current->id_puskesmas);
		$this->audit('wilayah_puskesmas_deleted', (int) $current->id_puskesmas, array(
			'kode_pkm' => (string) $current->kode_pkm,
			'nama_puskesmas' => (string) $current->nama_puskesmas,
		));
		$this->succeed('Puskesmas dihapus.');
	}

	/** Nama field teks dari POST, dipangkas. */
	private function posted_text($field)
	{
		return trim((string) $this->input->post($field, TRUE));
	}

	private function require_post()
	{
		if ($this->input->method(TRUE) === 'POST') {
			return true;
		}
		$this->fail('Metode tidak diizinkan.');
		return false;
	}

	private function fail($message)
	{
		$this->session->set_flashdata('error', $message);
		redirect('wilayah', 'refresh');
	}

	private function succeed($message)
	{
		$this->session->set_flashdata('success', $message);
		redirect('wilayah', 'refresh');
	}

	/** Catat perubahan wilayah. Kegagalan menulis audit tidak menghentikan permintaan. */
	private function audit($action, $entity_id, array $metadata)
	{
		if (!$this->db->table_exists('audit_logs')) {
			return;
		}
		$this->db->db_debug = false;
		$this->db->insert('audit_logs', array(
			'actor_user_id' => (int) $this->session->userdata('id'),
			'action' => (string) $action,
			'entity_type' => 'wilayah',
			'entity_id' => (string) $entity_id,
			'ip_address' => (string) $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'metadata_json' => json_encode($metadata),
			'created_at' => date('Y-m-d H:i:s'),
		));
		$this->db->db_debug = true;
	}
}
