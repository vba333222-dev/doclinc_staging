<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Editor konten landing page DocLinc.
 *
 * Mengurus teks yang memang diketik pengelola (blok + tanya-jawab). Statistik
 * tidak diisi manual: dihitung dari data nyata oleh model.
 *
 * Modul ini TERPISAH dari modul `landing_page` (warisan template lain yang
 * membaca tabel `tbl_*` dan tidak bisa dipakai di database ini). Pemisahan itu
 * disengaja: konten DocLinc tidak boleh tercampur dengan tabel template lama.
 *
 * Semua perubahan dicatat di `audit_logs` (entity_type 'landing_content'),
 * dengan bentuk baris yang sama seperti modul wilayah.
 */
class Konten_landing extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Konten_landing_m');
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
		$this->session->set_flashdata('title', 'Konten landing');
		$schema_ready = $this->Konten_landing_m->schemaReady();
		$this->load->view('commons/header');
		$this->load->view('konten_landing_v', array(
			'schema_ready' => $schema_ready,
			'blocks' => $schema_ready ? $this->Konten_landing_m->getBlocks() : array(),
			'faqs' => $schema_ready ? $this->Konten_landing_m->getFaqs() : array(),
			'stats' => $schema_ready ? $this->Konten_landing_m->getPublicStats() : array(),
		));
		$this->load->view('commons/footer');
	}

	public function update_block($id = 0)
	{
		if (!$this->require_post()) {
			return;
		}
		$block = $this->Konten_landing_m->getBlock($id);
		if (!$block) {
			$this->fail('Blok konten tidak ditemukan.');
			return;
		}
		$judul = $this->posted_text('judul');
		$isi = $this->posted_text('isi');
		if ($judul === '' || $isi === '') {
			$this->fail('Judul dan isi blok wajib diisi.');
			return;
		}
		$before = array(
			'judul' => (string) $block->judul,
			'isi' => (string) $block->isi,
			'urutan' => (int) $block->urutan,
			'aktif' => (int) $block->aktif,
		);
		$after = array(
			'judul' => $judul,
			'isi' => $isi,
			'urutan' => (int) $this->input->post('urutan', TRUE),
			'aktif' => $this->input->post('aktif', TRUE) !== null ? 1 : 0,
		);
		if ($before === $after) {
			$this->succeed('Tidak ada perubahan pada blok ini.');
			return;
		}
		$this->Konten_landing_m->updateBlock($block->id, array(
			'judul' => $after['judul'],
			'isi' => $after['isi'],
			'urutan' => $after['urutan'],
			'aktif' => $after['aktif'] === 1,
			'updated_by_user_id' => (int) $this->session->userdata('id'),
		));
		$this->audit('landing_content_block_updated', (int) $block->id, array(
			'block_key' => (string) $block->block_key,
			'sebelum' => $before,
			'sesudah' => $after,
		));
		$this->succeed('Blok konten diperbarui.');
	}

	public function create_faq()
	{
		if (!$this->require_post()) {
			return;
		}
		$pertanyaan = $this->posted_text('pertanyaan');
		$jawaban = $this->posted_text('jawaban');
		if ($pertanyaan === '' || $jawaban === '') {
			$this->fail('Pertanyaan dan jawaban wajib diisi.');
			return;
		}
		$id = $this->Konten_landing_m->createFaq(array(
			'pertanyaan' => $pertanyaan,
			'jawaban' => $jawaban,
			'urutan' => (int) $this->input->post('urutan', TRUE),
			'aktif' => $this->input->post('aktif', TRUE) !== null,
			'updated_by_user_id' => (int) $this->session->userdata('id'),
		));
		$this->audit('landing_content_faq_created', $id, array('pertanyaan' => $pertanyaan));
		$this->succeed('Tanya-jawab ditambahkan.');
	}

	public function update_faq($id = 0)
	{
		if (!$this->require_post()) {
			return;
		}
		$faq = $this->Konten_landing_m->getFaq($id);
		if (!$faq) {
			$this->fail('Tanya-jawab tidak ditemukan.');
			return;
		}
		$pertanyaan = $this->posted_text('pertanyaan');
		$jawaban = $this->posted_text('jawaban');
		if ($pertanyaan === '' || $jawaban === '') {
			$this->fail('Pertanyaan dan jawaban wajib diisi.');
			return;
		}
		$this->Konten_landing_m->updateFaq($faq->id, array(
			'pertanyaan' => $pertanyaan,
			'jawaban' => $jawaban,
			'urutan' => (int) $this->input->post('urutan', TRUE),
			'aktif' => $this->input->post('aktif', TRUE) !== null,
			'updated_by_user_id' => (int) $this->session->userdata('id'),
		));
		$this->audit('landing_content_faq_updated', (int) $faq->id, array(
			'sebelum' => (string) $faq->pertanyaan,
			'sesudah' => $pertanyaan,
		));
		$this->succeed('Tanya-jawab diperbarui.');
	}

	public function delete_faq($id = 0)
	{
		if (!$this->require_post()) {
			return;
		}
		$faq = $this->Konten_landing_m->getFaq($id);
		if (!$faq) {
			$this->fail('Tanya-jawab tidak ditemukan.');
			return;
		}
		$this->Konten_landing_m->deleteFaq($faq->id);
		$this->audit('landing_content_faq_deleted', (int) $faq->id, array('pertanyaan' => (string) $faq->pertanyaan));
		$this->succeed('Tanya-jawab dihapus.');
	}

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
		redirect('konten_landing', 'refresh');
	}

	private function succeed($message)
	{
		$this->session->set_flashdata('success', $message);
		redirect('konten_landing', 'refresh');
	}

	/** Catat perubahan konten. Kegagalan menulis audit tidak menghentikan permintaan. */
	private function audit($action, $entity_id, array $metadata)
	{
		if (!$this->db->table_exists('audit_logs')) {
			return;
		}
		$this->db->db_debug = false;
		$this->db->insert('audit_logs', array(
			'actor_user_id' => (int) $this->session->userdata('id'),
			'action' => (string) $action,
			'entity_type' => 'landing_content',
			'entity_id' => (string) $entity_id,
			'ip_address' => (string) $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'metadata_json' => json_encode($metadata),
			'created_at' => date('Y-m-d H:i:s'),
		));
		$this->db->db_debug = true;
	}
}
