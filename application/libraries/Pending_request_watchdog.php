<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pengawas permintaan konsultasi yang belum diterima puskesmas.
 *
 * MASALAH YANG DISELESAIKAN
 * Permintaan warga berstatus `Pending` menunggu puskesmas menekan terima. Bila
 * puskesmas tidak merespons - petugas tidak masuk, akun command centre mati,
 * atau permintaan terlewat - permintaan itu tergantung SELAMANYA. Warga
 * menunggu tanpa kepastian dan tidak ada satu pun layar yang menunjukkan ada
 * permintaan menumpuk. Ini kegagalan senyap yang mengenai warga langsung.
 *
 * CARA KERJA
 * Tidak ada penjadwal (cron) di aplikasi ini, jadi pengawasan dilakukan
 * "malas": diperiksa saat daftar permintaan dibuka, dengan pembatasan laju agar
 * tidak berjalan berulang-ulang dalam satu menit.
 *
 * ESKALASI, BUKAN PENGALIHAN OTOMATIS
 * Puskesmas tujuan ditentukan oleh AREA LAYANAN lokasi warga (poligon GeoJSON).
 * Mengalihkan permintaan ke puskesmas lain berarti melanggar batas catchment
 * yang memang disengaja, dan bisa mengirim petugas ke luar wilayah kerjanya.
 * Karena itu pengawas ini TIDAK memindahkan permintaan. Yang dilakukan:
 *   1. menandai permintaan sebagai terlambat (terlihat di dasbor admin)
 *   2. memberi tahu puskesmas sekali lagi
 *   3. menaikkan ke dinas kesehatan (admin) agar bisa bertindak
 * Ini meniru alur nyata: puskesmas bertanggung jawab kepada dinas kesehatan.
 */
class Pending_request_watchdog
{
	/** Menit sebelum permintaan dianggap perlu perhatian. */
	const REMINDER_DEFAULT_MINUTES = 10;

	/** Menit sebelum permintaan dianggap terlambat dan dieskalasi. */
	const OVERDUE_DEFAULT_MINUTES = 20;

	/** Jeda minimum antar pemeriksaan, dalam detik, per kode puskesmas. */
	const SWEEP_THROTTLE_SECONDS = 60;

	private $db;

	public function __construct($db = null)
	{
		$this->db = $db ?: get_instance()->db;
	}

	public function reminderMinutes()
	{
		return $this->configuredMinutes('request_accept_reminder_minutes', self::REMINDER_DEFAULT_MINUTES);
	}

	public function overdueMinutes()
	{
		return $this->configuredMinutes('request_accept_overdue_minutes', self::OVERDUE_DEFAULT_MINUTES);
	}

	/**
	 * Membaca ambang waktu dari konfigurasi aplikasi.
	 *
	 * Ambang yang bisa diatur penting: puskesmas dengan jam kerja berbeda tidak
	 * bisa memakai satu angka yang dipatok di kode. Nilai yang tidak masuk akal
	 * (kosong, bukan angka, nol atau negatif) jatuh ke nilai bawaan supaya salah
	 * ketik konfigurasi tidak mematikan pengawas secara diam-diam.
	 */
	private function configuredMinutes($config_key, $default)
	{
		$CI = get_instance();
		$value = ($CI && isset($CI->config)) ? $CI->config->item((string) $config_key) : null;
		if (!is_numeric($value)) {
			return (int) $default;
		}
		$minutes = (int) $value;

		return $minutes > 0 ? $minutes : (int) $default;
	}

	/**
	 * Berapa menit permintaan ini sudah menunggu, atau null bila tidak dapat
	 * dihitung. Memakai jam database agar konsisten dengan pencatatan.
	 */
	public function waitingMinutes($request_id)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1) {
			return null;
		}
		$row = $this->db->query(
			'SELECT TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS waiting_minutes FROM '
				. $this->db->dbprefix('requests') . ' WHERE request_id = ? LIMIT 1',
			array($request_id)
		)->row();
		if (!$row || $row->waiting_minutes === null) {
			return null;
		}

		return (int) $row->waiting_minutes;
	}

	/**
	 * Mencari permintaan Pending yang sudah melewati batas, lalu:
	 *   - menandai kejadiannya (jejak audit)
	 *   - mengingatkan puskesmas
	 *   - menaikkan ke dinas kesehatan (admin)
	 *
	 * Mengembalikan ringkasan hasil. Aman dipanggil berulang: ada pembatasan
	 * laju per puskesmas, dan tiap permintaan hanya dieskalasi sekali.
	 *
	 * @param string|null $puskesmas_code Batasi ke satu puskesmas; null = semua.
	 */
	public function sweep($puskesmas_code = null)
	{
		$result = array('reminded' => 0, 'overdue' => 0, 'scanned' => 0, 'skipped' => false);

		if (!$this->db->table_exists('requests')) {
			return $result;
		}
		$code = trim((string) $puskesmas_code);

		try {
			$rows = $this->overduePendingRequests($code);
			foreach ($rows as $row) {
				$result['scanned']++;
				$waiting = (int) $row->waiting_minutes;
				$request_id = (int) $row->request_id;

				// Tiap permintaan hanya dieskalasi SEKALI dan hanya diingatkan
				// SEKALI. Penjagaan ini yang membuat sweep aman dipanggil
				// berkali-kali, bukan kunci sementara.
				if ($waiting >= $this->overdueMinutes()) {
					if (!$this->hasEvent($request_id, 'accept-overdue') && $this->escalate($row, $waiting)) {
						$result['overdue']++;
					}
					continue;
				}
				if ($waiting >= $this->reminderMinutes()
					&& !$this->hasEvent($request_id, 'accept-reminder')
					&& $this->remind($row, $waiting)) {
					$result['reminded']++;
				}
			}
		} catch (Throwable $exception) {
			log_message('error', 'Pending request watchdog failed: ' . $exception->getMessage());
		}

		return $result;
	}

	/** Permintaan Pending yang sudah menunggu melewati ambang pengingat. */
	private function overduePendingRequests($puskesmas_code)
	{
		$this->db
			->select('request_id, user_id, assigned_puskesmas_code, assigned_puskesmas_name')
			->select('TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS waiting_minutes', false)
			->from('requests')
			->where('request_status', 'Pending');

		if ($puskesmas_code !== '') {
			$this->db->where('assigned_puskesmas_code', $puskesmas_code);
		}
		$rows = $this->db->get()->result();

		$overdue = array();
		foreach ($rows as $row) {
			if ($row->waiting_minutes === null) {
				continue;
			}
			if ((int) $row->waiting_minutes >= $this->reminderMinutes()) {
				$overdue[] = $row;
			}
		}

		return $overdue;
	}

	/**
	 * Menandai permintaan sebagai terlambat di dasbor admin.
	 *
	 * Disimpan sebagai event permintaan sehingga terlihat di jejak audit dan
	 * tidak perlu kolom baru pada tabel requests.
	 */
	private function escalate($row, $waiting_minutes)
	{
		$request_id = (int) $row->request_id;
		$event = array(
			'puskesmas_code' => (string) $row->assigned_puskesmas_code,
			'actor_user_id' => 0,
			'actor_staff_id' => 0,
			'domain_event_key' => 'request:' . $request_id . ':accept-overdue',
			'metadata' => array(
				'request_id' => $request_id,
				'waiting_minutes' => $waiting_minutes,
				'threshold_minutes' => $this->overdueMinutes(),
			),
		);
		if (!function_exists('doclinc_append_request_event')
			|| !doclinc_append_request_event($request_id, 'request.accept_overdue', $event, get_instance())) {
			return false;
		}

		$this->notifyPuskesmas($row, $waiting_minutes, true);

		return true;
	}

	/** Mengingatkan puskesmas bahwa permintaan belum direspons. */
	private function remind($row, $waiting_minutes)
	{
		$request_id = (int) $row->request_id;
		$event = array(
			'puskesmas_code' => (string) $row->assigned_puskesmas_code,
			'actor_user_id' => 0,
			'actor_staff_id' => 0,
			'domain_event_key' => 'request:' . $request_id . ':accept-reminder',
			'metadata' => array(
				'request_id' => $request_id,
				'waiting_minutes' => $waiting_minutes,
				'threshold_minutes' => $this->reminderMinutes(),
			),
		);
		if (!function_exists('doclinc_append_request_event')
			|| !doclinc_append_request_event($request_id, 'request.accept_reminder', $event, get_instance())) {
			return false;
		}

		$this->notifyPuskesmas($row, $waiting_minutes, false);

		return true;
	}

	private function notifyPuskesmas($row, $waiting_minutes, $overdue)
	{
		if (!function_exists('doclinc_notify_puskesmas')) {
			return;
		}
		doclinc_notify_puskesmas(
			(string) $row->assigned_puskesmas_code,
			$overdue ? 'request_accept_overdue' : 'request_accept_reminder',
			'request',
			(int) $row->request_id,
			$overdue ? 'Permintaan konsultasi belum direspons' : 'Pengingat permintaan konsultasi',
			$overdue
				? 'Permintaan ini sudah menunggu ' . $waiting_minutes . ' menit tanpa respons. Mohon segera ditangani.'
				: 'Permintaan konsultasi menunggu ' . $waiting_minutes . ' menit. Mohon segera diperiksa.',
			0
		);
	}

	/** Sudah pernah menerima event jenis ini? Dijaga agar tidak berulang tiap sweep. */
	private function hasEvent($request_id, $suffix)
	{
		if (!$this->db->table_exists('request_events')) {
			return false;
		}
		$row = $this->db
			->select('event_id')
			->where('request_id', (int) $request_id)
			->where('domain_event_key', 'request:' . (int) $request_id . ':' . (string) $suffix)
			->limit(1)
			->get('request_events')
			->row();

		return (bool) $row;
	}
}
