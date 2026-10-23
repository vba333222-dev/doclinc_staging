<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pembaca keadaan sesi dan keamanan panel.
 *
 * Sifat: BACA SAJA. Tidak ada tombol yang mengakhiri sesi orang lain di sini,
 * dan itu keputusan sadar: percobaan 2026-10-23 membuktikan memutus koneksi
 * lewat Centrifugo (`{"user": ...}`) mematikan SEMUA koneksi akun itu - termasuk
 * perangkat yang sah - dengan kode 3503, sementara SDK yang dipakai tidak
 * menyambung ulang untuk kode 3500-3999. Pemutusan sesi tetap dilakukan lewat
 * mekanisme sesi tunggal yang sudah ada (token per perangkat), bukan dari sini.
 */
class Sesi_m extends CI_Model
{
	public function schemaReady()
	{
		return $this->db->table_exists('user_login_bindings') && $this->db->table_exists('users');
	}

	/** Satu baris per akun: keadaan binding sesinya. */
	public function getBindings()
	{
		$has_nama = $this->db->field_exists('nama', 'users');
		$name_expression = $has_nama ? 'u.nama' : 'NULL';
		return $this->db
			->select(
				'b.user_id, b.token_hash, b.issued_at, b.revoked_at, b.updated_at, '
				. 'u.role, u.status, ' . $name_expression . ' AS nama',
				FALSE
			)
			->from('user_login_bindings b')
			->join('users u', 'u.userId = b.user_id', 'left')
			->order_by('b.updated_at', 'DESC')
			->limit(200)
			->get()
			->result();
	}

	public function getSummary()
	{
		if (!$this->db->table_exists('user_login_bindings')) {
			return array('total' => 0, 'aktif' => 0, 'tercabut' => 0, 'tanpa_hash' => 0);
		}
		$total = (int) $this->db->count_all_results('user_login_bindings');
		$aktif = (int) $this->db->where('revoked_at IS NULL', NULL, FALSE)->count_all_results('user_login_bindings');
		$tanpa_hash = (int) $this->db
			->where('token_hash IS NULL', NULL, FALSE)
			->count_all_results('user_login_bindings');
		return array(
			'total' => $total,
			'aktif' => $aktif,
			'tercabut' => $total - $aktif,
			'tanpa_hash' => $tanpa_hash,
		);
	}

	/** Jejak audit bertema sesi (login, tendangan, adopsi sesi lama). */
	public function getSessionAudit($limit = 12)
	{
		$limit = (int) $limit;
		if ($limit < 1 || $limit > 50) {
			$limit = 12;
		}
		if (!$this->db->table_exists('audit_logs')) {
			return array();
		}
		$name_expression = $this->db->field_exists('nama', 'users')
			? '(SELECT u.nama FROM users u WHERE u.userId = a.actor_user_id LIMIT 1)'
			: 'NULL';
		return $this->db
			->select('a.id, a.created_at, a.action, a.actor_user_id, a.metadata_json, ' . $name_expression . ' AS actor_name', FALSE)
			->from('audit_logs a')
			->group_start()
				->like('a.action', 'session_', 'after')
				->or_like('a.action', 'login_', 'after')
			->group_end()
			->order_by('a.id', 'DESC')
			->limit($limit)
			->get()
			->result();
	}

	/**
	 * Cuplikan konfigurasi yang benar-benar berlaku. Ditampilkan apa adanya
	 * supaya pertanyaan "kenapa sesi berperilaku begini" bisa dijawab dari panel,
	 * bukan dengan menebak dari berkas konfigurasi.
	 */
	public function getRuntimeSnapshot()
	{
		$ci = &get_instance();
		return array(
			array('label' => 'Sesi tunggal aktif', 'value' => $ci->config->item('single_active_session_enabled') === true ? 'ya' : 'tidak'),
			array('label' => 'Lingkungan fitur', 'value' => (string) $ci->config->item('single_active_session_environment')),
			array('label' => 'Alasan teknis', 'value' => (string) $ci->config->item('single_active_session_feature_reason')),
			array('label' => 'Adopsi sesi lama', 'value' => $ci->config->item('single_active_session_adopt_legacy') === true ? 'ya' : 'tidak'),
			array('label' => 'Penyimpanan sesi', 'value' => (string) $ci->config->item('sess_driver')),
			array('label' => 'Masa berlaku sesi', 'value' => (string) (int) $ci->config->item('sess_expiration') . ' detik'),
			array('label' => 'Lokasi berkas sesi', 'value' => (string) $ci->config->item('sess_save_path')),
			array('label' => 'display_errors', 'value' => (string) ini_get('display_errors')),
			array('label' => 'log_errors', 'value' => (string) ini_get('log_errors')),
		);
	}
}
