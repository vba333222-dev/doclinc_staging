<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Data master wilayah: `kecamatan` dan `puskesmas` (pemetaan kode -> kecamatan).
 *
 * Tabel ini yang membuat infografis per kecamatan bisa dihitung, tetapi skema
 * database tidak menegakkan apa pun: `nama_kecamatan` tidak unik, `kode_pkm`
 * tidak unik, dan `id_kecamatan` tanpa foreign key. Karena itu aturan berikut
 * ditegakkan di sini, bukan diserahkan ke database:
 *
 *   - `nama_kecamatan` unik tanpa membedakan huruf besar/kecil;
 *   - `kode_pkm` unik tanpa membedakan huruf besar/kecil. Kode ganda akan
 *     membuat satu permintaan terhitung di dua kecamatan sekaligus, jadi ini
 *     bukan sekadar kerapian;
 *   - puskesmas hanya boleh menunjuk kecamatan yang benar-benar ada;
 *   - kecamatan yang masih punya puskesmas TIDAK boleh dihapus;
 *   - puskesmas yang kodenya masih dipakai permintaan TIDAK boleh dihapus,
 *     karena menghapusnya memindahkan baris ke keranjang "tanpa pemetaan".
 *
 * Sifat: semua penulisan memakai prepared statement lewat query builder CI.
 */
class Wilayah_m extends CI_Model
{
	public function schemaReady()
	{
		return $this->db->table_exists('kecamatan')
			&& $this->db->table_exists('puskesmas')
			&& $this->db->field_exists('id_kecamatan', 'puskesmas');
	}

	/** Daftar kecamatan + jumlah puskesmas di dalamnya. */
	public function getKecamatanList()
	{
		$this->db->select('k.id_kecamatan, k.nama_kecamatan, COUNT(p.id_puskesmas) AS puskesmas_count', FALSE);
		$this->db->from('kecamatan k');
		$this->db->join('puskesmas p', 'p.id_kecamatan = k.id_kecamatan', 'left');
		$this->db->group_by('k.id_kecamatan, k.nama_kecamatan', FALSE);
		$this->db->order_by('k.nama_kecamatan', 'ASC');
		return $this->db->get()->result();
	}

	/** Daftar puskesmas + nama kecamatan + berapa permintaan yang memakai kodenya. */
	public function getPuskesmasList()
	{
		$requests_ready = $this->db->table_exists('requests')
			&& $this->db->field_exists('assigned_puskesmas_code', 'requests');
		$usage = $requests_ready
			? '(SELECT COUNT(*) FROM requests r WHERE TRIM(r.assigned_puskesmas_code) = TRIM(p.kode_pkm))'
			: '0';
		$this->db->select(
			'p.id_puskesmas, p.kode_pkm, p.nama_puskesmas, p.id_kecamatan, k.nama_kecamatan, ' . $usage . ' AS usage_count',
			FALSE
		);
		$this->db->from('puskesmas p');
		$this->db->join('kecamatan k', 'k.id_kecamatan = p.id_kecamatan', 'left');
		$this->db->order_by('k.nama_kecamatan', 'ASC');
		$this->db->order_by('p.nama_puskesmas', 'ASC');
		return $this->db->get()->result();
	}

	public function getKecamatanById($id)
	{
		return $this->db->where('id_kecamatan', (int) $id)->limit(1)->get('kecamatan')->row();
	}

	public function getPuskesmasById($id)
	{
		return $this->db->where('id_puskesmas', (int) $id)->limit(1)->get('puskesmas')->row();
	}

	/** Nama kecamatan sudah dipakai? Perbandingan tanpa beda huruf besar/kecil. */
	public function kecamatanNameExists($nama, $except_id = 0)
	{
		$this->db->where('LOWER(TRIM(nama_kecamatan)) = LOWER(TRIM(' . $this->db->escape((string) $nama) . '))', NULL, FALSE);
		if ((int) $except_id > 0) {
			$this->db->where('id_kecamatan <>', (int) $except_id);
		}
		return $this->db->count_all_results('kecamatan') > 0;
	}

	/** Kode puskesmas sudah dipakai? Kode ganda membuat hitungan wilayah ganda. */
	public function codeExists($kode, $except_id = 0)
	{
		$kode = trim((string) $kode);
		if ($kode === '') {
			return false;
		}
		$this->db->where('LOWER(TRIM(kode_pkm)) = LOWER(TRIM(' . $this->db->escape($kode) . '))', NULL, FALSE);
		if ((int) $except_id > 0) {
			$this->db->where('id_puskesmas <>', (int) $except_id);
		}
		return $this->db->count_all_results('puskesmas') > 0;
	}

	/** Berapa permintaan yang memakai kode ini. Dipakai sebagai pagar hapus. */
	public function codeUsageCount($kode)
	{
		$kode = trim((string) $kode);
		if ($kode === '' || !$this->db->table_exists('requests') || !$this->db->field_exists('assigned_puskesmas_code', 'requests')) {
			return 0;
		}
		$this->db->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($kode), NULL, FALSE);
		return (int) $this->db->count_all_results('requests');
	}

	public function puskesmasCountInKecamatan($id_kecamatan)
	{
		return (int) $this->db->where('id_kecamatan', (int) $id_kecamatan)->count_all_results('puskesmas');
	}

	public function createKecamatan($nama)
	{
		$this->db->insert('kecamatan', array('nama_kecamatan' => trim((string) $nama)));
		return (int) $this->db->insert_id();
	}

	public function updateKecamatan($id, $nama)
	{
		return $this->db->where('id_kecamatan', (int) $id)
			->update('kecamatan', array('nama_kecamatan' => trim((string) $nama)));
	}

	public function deleteKecamatan($id)
	{
		return $this->db->where('id_kecamatan', (int) $id)->delete('kecamatan');
	}

	public function createPuskesmas($kode, $nama, $id_kecamatan)
	{
		$this->db->insert('puskesmas', array(
			'kode_pkm' => trim((string) $kode),
			'nama_puskesmas' => trim((string) $nama),
			'id_kecamatan' => (int) $id_kecamatan,
		));
		return (int) $this->db->insert_id();
	}

	public function updatePuskesmas($id, $kode, $nama, $id_kecamatan)
	{
		return $this->db->where('id_puskesmas', (int) $id)->update('puskesmas', array(
			'kode_pkm' => trim((string) $kode),
			'nama_puskesmas' => trim((string) $nama),
			'id_kecamatan' => (int) $id_kecamatan,
		));
	}

	public function deletePuskesmas($id)
	{
		return $this->db->where('id_puskesmas', (int) $id)->delete('puskesmas');
	}
}
