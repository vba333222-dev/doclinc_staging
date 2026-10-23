<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Penjelajah master ICD-10 (baca saja).
 *
 * Membaca berkas master di repo, bukan tabel `clinical_master_*` - lihat alasan
 * lengkapnya di `Icd10_m`. Tidak ada aksi tulis di modul ini.
 *
 * Pencarian memakai POST lalu diarahkan ke URL segmen, mengikuti pola halaman
 * diagnosis/wilayah/audit: panel ini memakai `index_page = 'index.php?'` sehingga
 * query string ditolak filter URI CodeIgniter.
 */
class Icd10 extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Icd10_m');
		$this->load->helper('form');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			show_error('Akses admin diperlukan.', 403, 'Akses ditolak');
		}
	}

	public function index($q = '-')
	{
		$this->session->set_flashdata('title', 'Master ICD-10');
		$query = $this->sanitize_query($q === '-' ? '' : (string) $q);
		$this->load->view('commons/header');
		$this->load->view('icd10_v', array(
			'available' => $this->Icd10_m->available(),
			'manifest' => $this->Icd10_m->manifest(),
			'chapters' => $this->Icd10_m->chapters(),
			'blocks' => $this->Icd10_m->blocks(),
			'aliases' => $this->Icd10_m->aliases(),
			'query' => $query,
			'search' => $query === '' ? null : $this->Icd10_m->search($query, 40),
		));
		$this->load->view('commons/footer');
	}

	/** Terapkan kata kunci dari formulir (POST), lalu arahkan ke URL segmen. */
	public function filter()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect('icd10', 'refresh');
			return;
		}
		$query = $this->sanitize_query((string) $this->input->post('q', TRUE));
		redirect('icd10/index/' . ($query === '' ? '-' : rawurlencode($query)), 'refresh');
	}

	/**
	 * Kata kunci dibatasi ke huruf, angka, spasi, titik, koma, dan tanda hubung.
	 * Alasannya bukan kerapian: segmen URL panel ini harus lolos
	 * `permitted_uri_chars`, dan karakter seperti garis miring akan memecah segmen.
	 */
	private function sanitize_query($value)
	{
		$value = preg_replace('/[^A-Za-z0-9 .,-]/', ' ', (string) $value);
		$value = trim(preg_replace('/\s+/', ' ', (string) $value));
		if (strlen($value) > 60) {
			$value = substr($value, 0, 60);
		}
		return $value;
	}
}
