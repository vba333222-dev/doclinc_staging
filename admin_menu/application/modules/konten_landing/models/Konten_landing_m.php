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

	public function getBlocks()
	{
		return $this->db
			->order_by('urutan', 'ASC')
			->order_by('block_key', 'ASC')
			->get('landing_blocks')
			->result();
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
		return $this->db
			->order_by('urutan', 'ASC')
			->order_by('id', 'ASC')
			->get('landing_faqs')
			->result();
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
	 * Angka nyata untuk bagian statistik landing. Setiap angka diberi keterangan
	 * sumbernya supaya pengelola tahu dari mana asalnya; yang tidak bisa dihitung
	 * (tabelnya belum ada) dikembalikan sebagai null, bukan nol palsu.
	 */
	public function getPublicStats()
	{
		$stats = array();

		$kecamatan = $this->db->table_exists('kecamatan') ? (int) $this->db->count_all_results('kecamatan') : null;
		$stats[] = array(
			'label' => 'Kecamatan terlayani',
			'value' => $kecamatan,
			'source' => 'tabel kecamatan',
		);

		$puskesmas = $this->db->table_exists('puskesmas') ? (int) $this->db->count_all_results('puskesmas') : null;
		$stats[] = array(
			'label' => 'Puskesmas terdaftar',
			'value' => $puskesmas,
			'source' => 'tabel puskesmas',
		);

		$selesai = null;
		if ($this->db->table_exists('requests') && $this->db->field_exists('request_status', 'requests')) {
			$selesai = (int) $this->db->where('request_status', 'Completed')->count_all_results('requests');
		}
		$stats[] = array(
			'label' => 'Konsultasi selesai',
			'value' => $selesai,
			'source' => "permintaan berstatus 'Completed'",
		);

		$terkode = null;
		if ($this->db->table_exists('medicalrecord_diagnoses') && $this->db->field_exists('coding_state', 'medicalrecord_diagnoses')) {
			$terkode = (int) $this->db->where('coding_state', 'coded')->count_all_results('medicalrecord_diagnoses');
		}
		$stats[] = array(
			'label' => 'Diagnosis terkode',
			'value' => $terkode,
			'source' => "baris diagnosis dengan coding_state 'coded'",
		);

		return $stats;
	}
}
