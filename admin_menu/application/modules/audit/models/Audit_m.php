<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pembaca `audit_logs` untuk panel admin.
 *
 * Sifat: BACA SAJA. Tidak ada satu pun metode tulis di sini, supaya penampil
 * audit tidak pernah bisa mengubah buktinya sendiri.
 *
 * Isi `audit_logs` berasal dari beberapa penulis (gate sesi aplikasi utama,
 * modul wilayah, kapabilitas admin) dengan bentuk metadata berbeda-beda. Karena
 * itu pembaca ini menampilkan apa adanya: kolom metadata ditampilkan sebagai
 * JSON, bukan diterjemahkan dengan aturan yang bisa salah menebak.
 */
class Audit_m extends CI_Model
{
	public function schemaReady()
	{
		return $this->db->table_exists('audit_logs');
	}

	/** Daftar aksi yang pernah tercatat, dipakai untuk pilihan filter. */
	public function getActionOptions()
	{
		$rows = $this->db
			->select('action, COUNT(*) AS total', FALSE)
			->group_by('action')
			->order_by('total', 'DESC')
			->limit(60)
			->get('audit_logs')
			->result();
		$options = array();
		foreach ($rows as $row) {
			$options[] = array('action' => (string) $row->action, 'total' => (int) $row->total);
		}
		return $options;
	}

	/** Aktor yang pernah tercatat, lengkap dengan nama bila bisa ditemukan. */
	public function getActorOptions()
	{
		$has_users = $this->db->table_exists('users') && $this->db->field_exists('nama', 'users');
		$name_expression = $has_users
			? '(SELECT u.nama FROM users u WHERE u.userId = a.actor_user_id LIMIT 1)'
			: 'NULL';
		$rows = $this->db
			->select('a.actor_user_id, COUNT(*) AS total, MAX(' . $name_expression . ') AS nama', FALSE)
			->from('audit_logs a')
			->group_by('a.actor_user_id')
			->order_by('total', 'DESC')
			->limit(60)
			->get()
			->result();
		$options = array();
		foreach ($rows as $row) {
			$options[] = array(
				'actor_user_id' => (int) $row->actor_user_id,
				'total' => (int) $row->total,
				'nama' => (string) ($row->nama ?? ''),
			);
		}
		return $options;
	}

	/** Filter dibatasi ke nilai yang dikenal; sisanya jatuh ke default. */
	public function normalizeFilters(array $filters)
	{
		$days = (int) (isset($filters['days']) ? $filters['days'] : 30);
		if (!in_array($days, array(0, 1, 7, 30, 90), true)) {
			$days = 30;
		}
		$action = trim((string) (isset($filters['action']) ? $filters['action'] : ''));
		if ($action !== '' && preg_match('/\A[a-z0-9_]{1,100}\z/', $action) !== 1) {
			$action = '';
		}
		$actor_user_id = (int) (isset($filters['actor_user_id']) ? $filters['actor_user_id'] : 0);
		if ($actor_user_id < 0) {
			$actor_user_id = 0;
		}
		return array('days' => $days, 'action' => $action, 'actor_user_id' => $actor_user_id);
	}

	public function periodLabel($days)
	{
		if ((int) $days === 0) {
			return 'Seluruh periode';
		}
		return (int) $days . ' hari terakhir';
	}

	private function applyFilters(array $filters)
	{
		if ((int) $filters['days'] > 0) {
			$this->db->where(
				'created_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $filters['days'] . ' DAY)',
				NULL,
				FALSE
			);
		}
		if ((string) $filters['action'] !== '') {
			$this->db->where('action', (string) $filters['action']);
		}
		if ((int) $filters['actor_user_id'] > 0) {
			$this->db->where('actor_user_id', (int) $filters['actor_user_id']);
		}
	}

	public function countLogs(array $filters)
	{
		$this->applyFilters($filters);
		return (int) $this->db->count_all_results('audit_logs');
	}

	/**
	 * Baris terbaru lebih dulu. `$limit` dipagari di pemanggil; metode ini tidak
	 * pernah mengembalikan seluruh tabel sekaligus.
	 */
	public function getLogs(array $filters, $limit = 200)
	{
		$limit = (int) $limit;
		if ($limit < 1 || $limit > 500) {
			$limit = 200;
		}
		$has_users = $this->db->table_exists('users') && $this->db->field_exists('nama', 'users');
		$name_expression = $has_users
			? '(SELECT u.nama FROM users u WHERE u.userId = a.actor_user_id LIMIT 1)'
			: 'NULL';
		$this->applyFilters($filters);
		return $this->db
			->select(
				'a.id, a.actor_user_id, a.action, a.entity_type, a.entity_id, a.ip_address, '
				. 'a.user_agent, a.metadata_json, a.created_at, ' . $name_expression . ' AS actor_name',
				FALSE
			)
			->from('audit_logs a')
			->order_by('a.id', 'DESC')
			->limit($limit)
			->get()
			->result();
	}

	/** Ringkasan per aksi pada periode terpilih. */
	public function getActionSummary(array $filters, $limit = 8)
	{
		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 8;
		}
		$this->applyFilters($filters);
		return $this->db
			->select('action, COUNT(*) AS total', FALSE)
			->group_by('action')
			->order_by('total', 'DESC')
			->order_by('action', 'ASC')
			->limit($limit)
			->get('audit_logs')
			->result();
	}

	/** Ringkasan per aktor pada periode terpilih. */
	public function getActorSummary(array $filters, $limit = 8)
	{
		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 8;
		}
		$has_users = $this->db->table_exists('users') && $this->db->field_exists('nama', 'users');
		$name_expression = $has_users
			? '(SELECT u.nama FROM users u WHERE u.userId = a.actor_user_id LIMIT 1)'
			: 'NULL';
		$this->applyFilters($filters);
		return $this->db
			->select('a.actor_user_id, COUNT(*) AS total, MAX(' . $name_expression . ') AS nama', FALSE)
			->from('audit_logs a')
			->group_by('a.actor_user_id')
			->order_by('total', 'DESC')
			->limit($limit)
			->get()
			->result();
	}

	/** Rentang waktu yang benar-benar tercakup, supaya umur data tidak ditebak. */
	public function getCoverage(array $filters)
	{
		$this->applyFilters($filters);
		$row = $this->db
			->select('MIN(created_at) AS terlama, MAX(created_at) AS terbaru', FALSE)
			->get('audit_logs')
			->row();
		return array(
			'terlama' => (string) ($row->terlama ?? ''),
			'terbaru' => (string) ($row->terbaru ?? ''),
		);
	}
}
