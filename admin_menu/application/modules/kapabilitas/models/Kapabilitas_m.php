<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pembaca data untuk halaman kapabilitas admin.
 *
 * Sifat: BACA SAJA. Perubahan kapabilitas tidak ditulis di sini, melainkan lewat
 * `Admin_capability_service` (lihat `Admin_capability_gateway`) supaya aturan
 * "hanya super admin", "super admin terakhir tidak boleh dicabut", dan "tanpa
 * audit dibatalkan" tetap satu pintu.
 */
class Kapabilitas_m extends CI_Model
{
	public function schemaReady()
	{
		return $this->db->table_exists('users')
			&& $this->db->table_exists('admin_user_capabilities');
	}

	/** Semua akun admin beserta kapabilitas yang dimilikinya. */
	public function getAdminUsers()
	{
		/* Kolom nama pada tabel `users` bernama `nama` (bukan `name`). */
		$users = $this->db
			->select('userId, nama, email, role, status')
			->where('role', 'admin')
			->order_by('status', 'ASC')
			->order_by('nama', 'ASC')
			->get('users')
			->result();

		$by_user = array();
		if (!empty($users)) {
			$rows = $this->db
				->select('user_id, capability_code')
				->order_by('capability_code', 'ASC')
				->get('admin_user_capabilities')
				->result();
			foreach ($rows as $row) {
				$by_user[(int) $row->user_id][] = (string) $row->capability_code;
			}
		}

		$admins = array();
		foreach ($users as $user) {
			$admins[] = array(
				'user_id' => (int) $user->userId,
				'nama' => (string) $user->nama,
				'email' => (string) $user->email,
				'status' => (string) $user->status,
				'aktif' => (string) $user->status === 'aktif',
				'codes' => isset($by_user[(int) $user->userId]) ? $by_user[(int) $user->userId] : array(),
			);
		}
		return $admins;
	}

	/** Riwayat perubahan kapabilitas dari tabel audit. */
	public function getRecentCapabilityAudit($limit = 10)
	{
		$limit = (int) $limit;
		if ($limit < 1 || $limit > 50) {
			$limit = 10;
		}
		if (!$this->db->table_exists('audit_logs')) {
			return array();
		}
		/* Nama akun pada tabel `users` ada di kolom `nama`. */
		$has_users = $this->db->field_exists('nama', 'users');
		$name_expression = $has_users
			? '(SELECT u.nama FROM users u WHERE u.userId = a.actor_user_id LIMIT 1)'
			: 'NULL';
		return $this->db
			->select('a.id, a.created_at, a.action, a.actor_user_id, a.metadata_json, ' . $name_expression . ' AS actor_name', FALSE)
			->from('audit_logs a')
			->like('a.action', 'admin_capability_', 'after')
			->order_by('a.id', 'DESC')
			->limit($limit)
			->get()
			->result();
	}
}
