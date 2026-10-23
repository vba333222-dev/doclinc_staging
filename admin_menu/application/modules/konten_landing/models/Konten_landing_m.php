<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Konten landing page DocLinc (tabel `landing_blocks` + `landing_faqs`).
 *
 * Model ini hanya mengurus teks yang memang diketik pengelola. Angka statistik
 * landing TIDAK disimpan di sini: `getPublicStats()` menghitungnya dari data
 * nyata setiap kali dipanggil, supaya tidak ada angka basi yang harus
 * diperbarui manual dan tidak ada angka karangan.
 */
class Konten_landing_m extends CI_Model
{
	public function schemaReady()
	{
		return $this->db->table_exists('landing_blocks') && $this->db->table_exists('landing_faqs');
	}

	/**
	 * Pembacaan konten memakai library bersama `Landing_content` supaya halaman
	 * publik dan panel editor tidak bisa menyimpang. `require_once` lewat jalur
	 * berkas mengikuti pola yang sama dengan gateway kapabilitas.
	 */
	private function content()
	{
		$file = dirname(APPPATH, 2) . '/application/libraries/Landing_content.php';
		if (is_file($file)) {
			require_once $file;
		}
		if (!class_exists('Landing_content')) {
			return null;
		}
		return new Landing_content($this->db);
	}

	public function getBlocks()
	{
		$content = $this->content();
		return $content === null ? array() : $content->blocks(false);
	}

	public function getBlock($id)
	{
		return $this->db->where('id', (int) $id)->limit(1)->get('landing_blocks')->row();
	}

	public function updateBlock($id, array $data)
	{
		return $this->db->where('id', (int) $id)->update('landing_blocks', array(
			'judul' => (string) $data['judul'],
			'isi' => (string) $data['isi'],
			'urutan' => (int) $data['urutan'],
			'aktif' => !empty($data['aktif']) ? 1 : 0,
			'updated_by_user_id' => (int) $data['updated_by_user_id'],
			'updated_at' => date('Y-m-d H:i:s'),
		));
	}

	public function getFaqs()
	{
		$content = $this->content();
		return $content === null ? array() : $content->faqs(false);
	}

	public function getFaq($id)
	{
		return $this->db->where('id', (int) $id)->limit(1)->get('landing_faqs')->row();
	}

	public function createFaq(array $data)
	{
		$this->db->insert('landing_faqs', array(
			'pertanyaan' => (string) $data['pertanyaan'],
			'jawaban' => (string) $data['jawaban'],
			'urutan' => (int) $data['urutan'],
			'aktif' => !empty($data['aktif']) ? 1 : 0,
			'updated_by_user_id' => (int) $data['updated_by_user_id'],
			'updated_at' => date('Y-m-d H:i:s'),
		));
		return (int) $this->db->insert_id();
	}

	public function updateFaq($id, array $data)
	{
		return $this->db->where('id', (int) $id)->update('landing_faqs', array(
			'pertanyaan' => (string) $data['pertanyaan'],
			'jawaban' => (string) $data['jawaban'],
			'urutan' => (int) $data['urutan'],
			'aktif' => !empty($data['aktif']) ? 1 : 0,
			'updated_by_user_id' => (int) $data['updated_by_user_id'],
			'updated_at' => date('Y-m-d H:i:s'),
		));
	}

	public function deleteFaq($id)
	{
		return $this->db->where('id', (int) $id)->delete('landing_faqs');
	}

	/**
	 * Angka nyata untuk bagian statistik landing. Dihitung oleh library bersama
	 * `Landing_content`; setiap angka disertai keterangan sumbernya, dan yang
	 * belum bisa dihitung dikembalikan sebagai null - bukan nol palsu.
	 */
	public function getPublicStats()
	{
		$content = $this->content();
		return $content === null ? array() : $content->stats();
	}
}
