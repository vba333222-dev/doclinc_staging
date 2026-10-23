<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pembaca konten landing page DocLinc.
 *
 * Dipakai DUA aplikasi: halaman depan aplikasi utama (pengunjung) dan panel
 * admin (editor). Karena itu logikanya ada di satu berkas ini, bukan disalin ke
 * masing-masing aplikasi - supaya halaman yang dilihat warga dan yang diedit
 * admin tidak bisa menyimpang.
 *
 * Angka statistik TIDAK dibaca dari tabel: dihitung dari data nyata setiap kali
 * dipanggil, dan dikembalikan sebagai null bila tabel sumbernya tidak ada.
 * View bertanggung jawab menampilkan null sebagai "belum tersedia" - bukan nol
 * palsu yang tampak seperti data.
 */
class Landing_content
{
	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function available()
	{
		return $this->db->table_exists('landing_blocks') && $this->db->table_exists('landing_faqs');
	}

	/**
	 * Blok konten. `$only_active = true` untuk halaman publik; editor memakai
	 * false supaya blok yang disembunyikan tetap bisa diubah.
	 */
	public function blocks($only_active = true)
	{
		if (!$this->db->table_exists('landing_blocks')) {
			return array();
		}
		if ($only_active) {
			$this->db->where('aktif', 1);
		}
		return $this->db
			->order_by('urutan', 'ASC')
			->order_by('block_key', 'ASC')
			->get('landing_blocks')
			->result();
	}

	/** Blok yang aktif, berkunci `block_key` supaya view bisa menanyakannya. */
	public function active_blocks()
	{
		$keyed = array();
		foreach ($this->blocks(true) as $block) {
			$keyed[(string) $block->block_key] = $block;
		}
		return $keyed;
	}

	public function faqs($only_active = true)
	{
		if (!$this->db->table_exists('landing_faqs')) {
			return array();
		}
		if ($only_active) {
			$this->db->where('aktif', 1);
		}
		return $this->db
			->order_by('urutan', 'ASC')
			->order_by('id', 'ASC')
			->get('landing_faqs')
			->result();
	}

	/** Angka nyata + keterangan sumbernya. Nilai null berarti belum bisa dihitung. */
	public function stats()
	{
		$stats = array();

		$stats[] = array(
			'label' => 'Kecamatan terlayani',
			'value' => $this->db->table_exists('kecamatan') ? (int) $this->db->count_all_results('kecamatan') : null,
			'source' => 'tabel kecamatan',
		);
		$stats[] = array(
			'label' => 'Puskesmas terdaftar',
			'value' => $this->db->table_exists('puskesmas') ? (int) $this->db->count_all_results('puskesmas') : null,
			'source' => 'tabel puskesmas',
		);
		$stats[] = array(
			'label' => 'Konsultasi selesai',
			'value' => ($this->db->table_exists('requests') && $this->db->field_exists('request_status', 'requests'))
				? (int) $this->db->where('request_status', 'Completed')->count_all_results('requests')
				: null,
			'source' => "permintaan berstatus 'Completed'",
		);
		$stats[] = array(
			'label' => 'Diagnosis terkode',
			'value' => ($this->db->table_exists('medicalrecord_diagnoses') && $this->db->field_exists('coding_state', 'medicalrecord_diagnoses'))
				? (int) $this->db->where('coding_state', 'coded')->count_all_results('medicalrecord_diagnoses')
				: null,
			'source' => "baris diagnosis dengan coding_state 'coded'",
		);

		return $stats;
	}

	/** Blok kontak masih memuat penanda "belum diisi"? Dipakai untuk peringatan. */
	public function contact_is_placeholder()
	{
		$blocks = $this->active_blocks();
		return isset($blocks['kontak'])
			&& stripos((string) $blocks['kontak']->isi, 'belum diisi') !== false;
	}
}
