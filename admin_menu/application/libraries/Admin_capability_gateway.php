<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Jembatan kapabilitas admin untuk panel.
 *
 * Panel ini aplikasi CodeIgniter terpisah, sedangkan lapisan kapabilitas hidup
 * di aplikasi utama (`Admin_capability_store`, `Admin_capability_service`,
 * `Admin_capability_policy`, `Admin_capability_flags`). Gateway ini memuatnya
 * lewat jalur berkas - pola yang sama dengan `Admin_clinical_access_gateway` -
 * sehingga aturan kapabilitas tetap satu sumber dan tidak disalin ulang.
 *
 * Yang TIDAK dilakukan di sini: menulis langsung ke tabel kapabilitas. Semua
 * perubahan lewat `Admin_capability_service`, yang menegakkan tiga hal sekaligus
 * dalam satu transaksi: hanya super admin yang boleh mengubah, super admin
 * terakhir tidak boleh dicabut, dan perubahan tanpa baris audit dibatalkan.
 */
class Admin_capability_gateway
{
	private $CI;
	private $actor = array('user_id' => 0, 'role' => '', 'status' => '');
	private $store = null;
	private $service = null;
	private $enabled = false;
	private $reason = 'resolver_unavailable';

	public function __construct($CI)
	{
		/* CodeIgniter melewatkan parameter `load->library(..., array($this))` sebagai
		 * SATU argumen berupa array, bukan sebagai argumen terpisah. Tanpa baris ini
		 * `$CI` berisi array dan setiap akses berikutnya berhenti dengan fatal yang
		 * tidak terlihat (display_errors mati, log_errors mati) - hanya 500 kosong. */
		if (is_array($CI)) {
			$CI = reset($CI);
		}
		if (!is_object($CI)) {
			$this->reason = 'ci_instance_unavailable';
			return;
		}
		$this->CI = $CI;
		$root = dirname(APPPATH, 2) . '/application/libraries/';
		foreach (array(
			'Doclinc_feature_flags', 'Admin_capability_policy', 'Admin_capability_flags',
			'Admin_capability_store', 'Admin_capability_service', 'Admin_audit_log_writer',
		) as $class) {
			$file = $root . $class . '.php';
			if (is_file($file)) {
				require_once $file;
			}
		}

		if (class_exists('Admin_capability_flags') && class_exists('Doclinc_feature_flags')) {
			$this->enabled = Admin_capability_flags::enabled('capabilities');
			$this->reason = $this->enabled ? 'enabled' : 'feature_disabled';
		}

		$userId = (int) $CI->session->userdata('id');
		$row = array();
		if ($userId > 0 && $CI->db->table_exists('users')) {
			$row = (array) $CI->db->where('userId', $userId)->limit(1)->get('users')->row_array();
		}
		$this->actor = array(
			'user_id' => $userId,
			'role' => (string) ($row['role'] ?? ''),
			'status' => (string) ($row['status'] ?? ''),
		);

		if (class_exists('Admin_capability_store') && class_exists('Admin_capability_service')) {
			$this->store = new Admin_capability_store($CI->db);
			$writer = class_exists('Admin_audit_log_writer') ? new Admin_audit_log_writer($CI->db) : null;
			$this->service = new Admin_capability_service($this->store, $writer, $this->enabled);
		}
	}

	/** Fitur kapabilitas aktif? Dibaca dari flag, bukan diasumsikan. */
	public function featureEnabled()
	{
		return $this->enabled === true && $this->service !== null;
	}

	/** Alasan teknis bila fitur tidak aktif, untuk ditampilkan apa adanya. */
	public function reason()
	{
		if ($this->service === null) {
			return 'service_unavailable';
		}
		return $this->reason;
	}

	public function actor()
	{
		return $this->actor;
	}

	public function activeAdmin()
	{
		return class_exists('Admin_capability_policy')
			&& Admin_capability_policy::activeAdmin($this->actor);
	}

	public function allowedCodes()
	{
		return class_exists('Admin_capability_policy') ? Admin_capability_policy::allowedCodes() : array();
	}

	public function hasCapability($code)
	{
		if ($this->service === null) {
			return false;
		}
		return $this->service->hasCapability($this->actor, (string) $code);
	}

	public function grant($targetUserId, $code)
	{
		if ($this->service === null) {
			return array('allowed' => false, 'changed' => false, 'error' => 'service_unavailable');
		}
		return $this->service->grant($this->actor, (int) $targetUserId, (string) $code);
	}

	public function revoke($targetUserId, $code)
	{
		if ($this->service === null) {
			return array('allowed' => false, 'changed' => false, 'error' => 'service_unavailable');
		}
		return $this->service->revoke($this->actor, (int) $targetUserId, (string) $code);
	}

	/** Jumlah super admin aktif: dasar aturan "super admin terakhir". */
	public function superAdminCount()
	{
		return $this->store === null ? 0 : (int) $this->store->activeSuperAdminCount();
	}

	/** Pesan manusia untuk kode galat aman dari lapisan kapabilitas. */
	public static function errorMessage($code)
	{
		$messages = array(
			'feature_disabled' => 'Fitur kapabilitas admin belum diaktifkan, jadi perubahan tidak dijalankan.',
			'service_unavailable' => 'Lapisan kapabilitas tidak tersedia di panel ini, jadi perubahan tidak dijalankan.',
			'capability_management_denied' => 'Hanya super admin aktif yang boleh mengubah kapabilitas admin lain.',
			'capability_not_granted' => 'Kapabilitas itu belum diberikan kepada pengguna tersebut.',
			'capability_write_failed' => 'Perubahan kapabilitas gagal ditulis.',
			'audit_write_failed' => 'Perubahan dibatalkan karena baris auditnya gagal ditulis.',
			'last_active_super_admin' => 'Super admin terakhir tidak boleh dicabut - panel bisa kehilangan pengelolanya.',
		);
		return isset($messages[(string) $code]) ? $messages[(string) $code] : 'Perubahan kapabilitas tidak dijalankan.';
	}
}
