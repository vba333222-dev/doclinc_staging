<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pembatasan percobaan masuk untuk permukaan nakes.
 *
 * Mengapa menghitung dari `audit_logs` alih-alih tabel baru
 * --------------------------------------------------------
 * Setiap kegagalan masuk sudah dicatat `Login_m::log_login_event('login_failed')`
 * ke `audit_logs`, jadi sumbernya sudah ada dan tidak perlu skema baru. Kelas ini
 * **tidak menulis apa pun** — pencatatan tetap satu pintu lewat `Login_m`, dan
 * penandaan pembatasan (`login_throttled`) ditulis pemanggil lewat pintu yang sama.
 *
 * Mengapa per akun lebih dulu, bukan per alamat IP
 * ------------------------------------------------
 * Satu Puskesmas sering berbagi satu koneksi internet. Membatasi hanya per IP
 * berisiko mengunci seluruh staf satu fasilitas karena satu orang salah ketik.
 * Karena itu batas per akun lebih ketat (`login_throttle_max_failures`), dan batas
 * IP dipakai lebih longgar sebagai lapis kedua untuk menahan tebakan nama pengguna.
 *
 * Fail-safe: bila tabel `audit_logs` tidak ada, pembatasan **dilewati** dan
 * pemanggil tetap mencatat. Membiarkan pintu terbuka saat skema rusak dipilih
 * sadar, karena alternatifnya adalah mengunci semua orang dari aplikasi.
 */
class Nakes_login_throttle
{
	const ACTION_FAILURE = 'login_failed';

	private $db;
	private $enabled;
	private $max_failures;
	private $ip_max_failures;
	private $window_seconds;

	public function __construct($db, array $config = array())
	{
		$this->db = $db;
		$this->enabled = !array_key_exists('enabled', $config) || $config['enabled'] === true;
		$this->max_failures = max(1, (int) ($config['max_failures'] ?? 8));
		$this->ip_max_failures = max($this->max_failures, (int) ($config['ip_max_failures'] ?? 20));
		$this->window_seconds = max(60, (int) ($config['window_seconds'] ?? 900));
	}

	public function enabled()
	{
		return $this->enabled;
	}

	/** Apakah sumber hitungannya ada? Bila tidak, pembatasan dilewati. */
	public function failSafe()
	{
		return $this->db->table_exists('audit_logs');
	}

	/**
	 * Keadaan pembatasan untuk satu percobaan masuk.
	 *
	 * @param string $ip      alamat IP pemanggil
	 * @param int    $user_id 0 bila nama pengguna tidak dikenali
	 * @param int    $now     waktu Unix; 0 = sekarang (diinjeksi saat diuji)
	 */
	public function state($ip, $user_id = 0, $now = 0)
	{
		$now = $now > 0 ? (int) $now : time();
		$result = array(
			'allowed' => true,
			'failures' => 0,
			'window_seconds' => $this->window_seconds,
			'retry_after_seconds' => 0,
			'scope' => 'ip',
		);
		if (!$this->enabled || !$this->failSafe()) {
			return $result;
		}

		$user_id = (int) $user_id;
		if ($user_id > 0) {
			$failures = $this->countFailures($ip, $user_id, $now);
			if ($failures >= $this->max_failures) {
				return array(
					'allowed' => false,
					'failures' => $failures,
					'window_seconds' => $this->window_seconds,
					'retry_after_seconds' => $this->retryAfter($ip, $user_id, $now),
					'scope' => 'account',
				);
			}
			$result['failures'] = $failures;
			$result['scope'] = 'account';
		}

		$ip_failures = $this->countFailures($ip, 0, $now);
		if ($ip_failures >= $this->ip_max_failures) {
			return array(
				'allowed' => false,
				'failures' => $ip_failures,
				'window_seconds' => $this->window_seconds,
				'retry_after_seconds' => $this->retryAfter($ip, 0, $now),
				'scope' => 'ip',
			);
		}

		return $result;
	}

	/** Kegagalan dalam jendela waktu; `$user_id` 0 berarti hitung seluruh IP. */
	private function countFailures($ip, $user_id, $now)
	{
		$this->db->where('action', self::ACTION_FAILURE);
		$this->db->where('created_at >=', date('Y-m-d H:i:s', $now - $this->window_seconds));
		if ($user_id > 0) {
			$this->db->where('entity_id', (string) $user_id);
		} else {
			$this->db->where('ip_address', (string) $ip);
		}
		return (int) $this->db->count_all_results('audit_logs');
	}

	/** Sisa waktu sampai kegagalan tertua keluar dari jendela. */
	private function retryAfter($ip, $user_id, $now)
	{
		$this->db->select('created_at');
		$this->db->where('action', self::ACTION_FAILURE);
		$this->db->where('created_at >=', date('Y-m-d H:i:s', $now - $this->window_seconds));
		if ($user_id > 0) {
			$this->db->where('entity_id', (string) $user_id);
		} else {
			$this->db->where('ip_address', (string) $ip);
		}
		$oldest = $this->db->order_by('created_at', 'ASC')->limit(1)->get('audit_logs')->row();
		if (!$oldest) {
			return $this->window_seconds;
		}
		$elapsed = $now - (int) strtotime((string) $oldest->created_at);
		return max(1, $this->window_seconds - $elapsed);
	}
}
