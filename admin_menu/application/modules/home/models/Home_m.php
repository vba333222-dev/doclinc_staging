<?php
class Home_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function empty_result($columns)
	{
		$select = array();
		foreach ($columns as $column) {
			$select[] = 'NULL AS ' . $column;
		}

		return $this->db->query('SELECT ' . implode(', ', $select) . ' WHERE 1=0');
	}

	/**
	 * Daftar kolom satu tabel, disimpan selama satu permintaan.
	 *
	 * Mengapa ada: `DB_driver::field_exists()` memanggil `list_fields()` yang
	 * TIDAK menyimpan hasilnya pada build CodeIgniter di repo ini - hanya
	 * `table_names` yang ter-cache, bukan nama kolom. Akibatnya setiap
	 * `field_exists()` menjadi satu `SHOW COLUMNS` sungguhan.
	 *
	 * Dasbor monitoring bertanya soal skema sekitar 48 kali per muat halaman,
	 * padahal hanya ada empat tabel berbeda yang ditanyakan. Mengukur di staging
	 * menunjukkan satu `SHOW COLUMNS` berharga ~0,9 ms dan tidak bergantung
	 * jumlah baris, jadi biaya itu tetap sama pada 11 baris maupun jutaan.
	 * Menyimpannya di sini memangkas 48 kueri menjadi paling banyak 4.
	 *
	 * Aman: hasilnya hanya bergantung pada skema, dan skema tidak berubah di
	 * tengah satu permintaan.
	 */
	private $schema_columns = array();

	private function table_columns($table)
	{
		if (!array_key_exists($table, $this->schema_columns)) {
			/* `list_fields()` pada tabel yang tidak ada mengembalikan FALSE, dan
			   `in_array($x, FALSE)` melempar TypeError di PHP 8. Tabel yang tidak
			   ada dijawab sebagai daftar kosong supaya pemanggil menerima `false`
			   seperti yang diharapkan, bukan galat. */
			$this->schema_columns[$table] = $this->db->table_exists($table)
				? (array) $this->db->list_fields($table)
				: array();
		}

		return $this->schema_columns[$table];
	}

	private function field_exists_cached($field, $table)
	{
		return in_array($field, $this->table_columns($table), true);
	}

	private function assigned_puskesmas_code_expr($request_alias = 'requests')
	{
		$raw = $this->field_exists_cached('assigned_puskesmas_code', 'requests')
			? "CONVERT(NULLIF(TRIM($request_alias.assigned_puskesmas_code), '') USING utf8mb4) COLLATE utf8mb4_general_ci"
			: 'NULL';

		return "CASE WHEN UPPER(COALESCE($raw, '')) = 'DEFAULT' THEN NULL ELSE $raw END";
	}

	private function assigned_puskesmas_name_expr($request_alias = 'requests')
	{
		$raw = $this->field_exists_cached('assigned_puskesmas_name', 'requests')
			? "NULLIF(TRIM($request_alias.assigned_puskesmas_name), '')"
			: 'NULL';

		return "CASE WHEN UPPER(COALESCE($raw, '')) IN ('DEFAULT', 'PUSKESMAS DEFAULT') THEN NULL ELSE $raw END";
	}

	private function puskesmas_display_expr($request_alias = 'requests', $puskesmas_alias = 'assigned_puskesmas')
	{
		$assigned_name = $this->assigned_puskesmas_name_expr($request_alias);
		$assigned_code = $this->assigned_puskesmas_code_expr($request_alias);
		$master_name = $this->can_join_puskesmas() ? "$puskesmas_alias.nama_puskesmas" : 'NULL';

		return "COALESCE($assigned_name, $master_name, $assigned_code, 'Legacy / Belum terklasifikasi')";
	}

	private function puskesmas_key_expr($request_alias = 'requests')
	{
		$assigned_code = $this->assigned_puskesmas_code_expr($request_alias);

		return "COALESCE($assigned_code, 'LEGACY_UNCLASSIFIED')";
	}

	private function can_join_puskesmas()
	{
		return $this->db->table_exists('m_puskesmas')
			&& $this->field_exists_cached('kode_pkm', 'm_puskesmas')
			&& $this->field_exists_cached('nama_puskesmas', 'm_puskesmas');
	}

	private function request_date_expr($request_alias = 'requests')
	{
		if ($this->field_exists_cached('created_at', 'requests')) {
			return "$request_alias.created_at";
		}

		if ($this->field_exists_cached('date', 'requests')) {
			return "$request_alias.date";
		}

		return 'NULL';
	}

	private function medicalrecord_date_expr($medical_alias = 'medicalrecords', $request_alias = 'requests')
	{
		if ($this->field_exists_cached('created_at', 'medicalrecords')) {
			return "$medical_alias.created_at";
		}

		if ($this->field_exists_cached('updated_at', 'medicalrecords')) {
			return "$medical_alias.updated_at";
		}

		return $this->request_date_expr($request_alias);
	}

	private function can_query_diagnosis_analytics()
	{
		return $this->db->table_exists('medicalrecords')
			&& $this->db->table_exists('requests')
			&& $this->field_exists_cached('request_id', 'medicalrecords')
			&& $this->field_exists_cached('diagnosis', 'medicalrecords')
			&& $this->field_exists_cached('request_id', 'requests');
	}

	private function can_query_request_events()
	{
		if (!$this->db->table_exists('request_events')) {
			return false;
		}

		foreach (array('event_id', 'request_id', 'event_type', 'created_at') as $field) {
			if (!$this->field_exists_cached($field, 'request_events')) {
				return false;
			}
		}

		return true;
	}

	private function can_query_pic_assignment()
	{
		if (!$this->db->table_exists('request_staff_assignments')) {
			return false;
		}

		foreach (array('request_id', 'status') as $field) {
			if (!$this->field_exists_cached($field, 'request_staff_assignments')) {
				return false;
			}
		}

		return true;
	}

	private function event_title($event_type)
	{
		$labels = array(
			'request_created' => 'Menunggu konfirmasi Puskesmas',
			'request_accepted' => 'Diterima',
			'request_cancelled' => 'Dibatalkan',
			'visit_started' => 'Dalam perjalanan',
			'visit_arrived' => 'Sudah tiba',
			'visit_in_service' => 'Sedang ditangani',
			'visit_completed' => 'Selesai',
			'request_completed' => 'Selesai',
			'pic_assigned' => 'PIC ditugaskan',
			'pic_changed' => 'PIC diganti',
			'pic_cleared' => 'PIC dihapus',
		);

		return isset($labels[$event_type]) ? $labels[$event_type] : 'Aktivitas konsultasi diperbarui';
	}

	private function event_message($event_type)
	{
		if (in_array($event_type, array('pic_assigned', 'pic_changed', 'pic_cleared'), true)) {
			return 'Penugasan PIC pada konsultasi diperbarui.';
		}

		if (in_array($event_type, array('visit_started', 'visit_arrived', 'visit_in_service', 'visit_completed'), true)) {
			return 'Aktivitas kunjungan Puskesmas diperbarui.';
		}

		return 'Status konsultasi diperbarui.';
	}

	private function format_datetime($value)
	{
		$timestamp = strtotime((string) $value);
		return $timestamp ? date('d M Y H:i', $timestamp) : '-';
	}

	public function get_recent_request_events($limit = 8)
	{
		if (!$this->can_query_request_events()) {
			return array();
		}

		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 8;
		}

		$display_expr = $this->puskesmas_display_expr('requests', 'assigned_puskesmas');
		$has_requests = $this->db->table_exists('requests') && $this->field_exists_cached('request_id', 'requests');
		$select_puskesmas = $has_requests ? "$display_expr AS puskesmas" : "'Legacy / Belum terklasifikasi' AS puskesmas";

		$this->db->select("request_events.event_id, request_events.request_id, request_events.event_type, request_events.created_at, $select_puskesmas", FALSE);
		$this->db->from('request_events');
		if ($has_requests) {
			$this->db->join('requests', 'requests.request_id = request_events.request_id', 'left');
			if ($this->can_join_puskesmas()) {
				$this->db->join('m_puskesmas assigned_puskesmas', 'assigned_puskesmas.kode_pkm = ' . $this->assigned_puskesmas_code_expr('requests'), 'left', FALSE);
			}
		}
		$this->db->order_by('request_events.event_id', 'DESC');
		$this->db->limit($limit);

		$rows = $this->db->get()->result();
		foreach ($rows as $row) {
			$row->event_label = $this->event_title(isset($row->event_type) ? (string) $row->event_type : '');
			$row->event_message = $this->event_message(isset($row->event_type) ? (string) $row->event_type : '');
			$row->created_at_label = $this->format_datetime($row->created_at);
		}

		return $rows;
	}

	public function get_diagnosis_dashboard($days = 30)
	{
		$days = (int) $days;
		if (!in_array($days, array(7, 30, 90), true)) {
			$days = 30;
		}

		$analytics = array(
			'period_days' => $days,
			'top_diagnoses' => array(),
			'puskesmas_distribution' => array(),
			'max_top_total' => 0,
			'max_puskesmas_total' => 0,
			'source_available' => $this->can_query_diagnosis_analytics(),
		);

		if (!$analytics['source_available']) {
			return $analytics;
		}

		$date_expr = $this->medicalrecord_date_expr('medicalrecords', 'requests');
		$display_expr = $this->puskesmas_display_expr('requests', 'assigned_puskesmas');
		$code_expr = $this->puskesmas_key_expr('requests');

		$this->db->select("TRIM(medicalrecords.diagnosis) AS diagnosis, COUNT(*) AS total", FALSE);
		$this->db->from('medicalrecords');
		$this->db->join('requests', 'requests.request_id = medicalrecords.request_id', 'inner');
		$this->apply_diagnosis_analytics_filters($date_expr, $days);
		$this->db->group_by('TRIM(medicalrecords.diagnosis)', FALSE);
		$this->db->order_by('total', 'DESC');
		$this->db->limit(8);
		$analytics['top_diagnoses'] = $this->db->get()->result();
		foreach ($analytics['top_diagnoses'] as $row) {
			$analytics['max_top_total'] = max($analytics['max_top_total'], (int) ($row->total ?? 0));
		}

		$this->db->select("$display_expr AS puskesmas, $code_expr AS puskesmas_code, TRIM(medicalrecords.diagnosis) AS diagnosis, COUNT(*) AS total", FALSE);
		$this->db->from('medicalrecords');
		$this->db->join('requests', 'requests.request_id = medicalrecords.request_id', 'inner');
		if ($this->can_join_puskesmas()) {
			$this->db->join('m_puskesmas assigned_puskesmas', 'assigned_puskesmas.kode_pkm = ' . $this->assigned_puskesmas_code_expr('requests'), 'left', FALSE);
		}
		$this->apply_diagnosis_analytics_filters($date_expr, $days);
		$this->db->group_by($display_expr, FALSE);
		$this->db->group_by($code_expr, FALSE);
		$this->db->group_by('TRIM(medicalrecords.diagnosis)', FALSE);
		$this->db->order_by('total', 'DESC');
		$rows = $this->db->get()->result();

		$grouped = array();
		foreach ($rows as $row) {
			$key = (string) ($row->puskesmas_code ?? 'LEGACY_UNCLASSIFIED');
			if (!isset($grouped[$key])) {
				$grouped[$key] = (object) array(
					'puskesmas' => $row->puskesmas ?? 'Legacy / Belum terklasifikasi',
					'puskesmas_code' => $row->puskesmas_code ?? 'LEGACY_UNCLASSIFIED',
					'total' => 0,
					'top_diagnosis' => '-',
					'top_diagnosis_total' => 0,
				);
			}

			$total = (int) ($row->total ?? 0);
			$grouped[$key]->total += $total;
			if ($total > $grouped[$key]->top_diagnosis_total) {
				$grouped[$key]->top_diagnosis = $row->diagnosis ?? '-';
				$grouped[$key]->top_diagnosis_total = $total;
			}
		}

		$analytics['puskesmas_distribution'] = array_values($grouped);
		usort($analytics['puskesmas_distribution'], function ($a, $b) {
			return (int) $b->total <=> (int) $a->total;
		});
		$analytics['puskesmas_distribution'] = array_slice($analytics['puskesmas_distribution'], 0, 8);
		foreach ($analytics['puskesmas_distribution'] as $row) {
			$analytics['max_puskesmas_total'] = max($analytics['max_puskesmas_total'], (int) ($row->total ?? 0));
		}

		return $analytics;
	}

	private function apply_diagnosis_analytics_filters($date_expr, $days)
	{
		$this->db->where('medicalrecords.diagnosis IS NOT NULL', NULL, FALSE);
		$this->db->where("TRIM(medicalrecords.diagnosis) <> ''", NULL, FALSE);
		if ($date_expr !== 'NULL') {
			/* Tanpa pembungkus DATE().
			   `DATE(x) >= D` setara persis dengan `x >= D` karena D adalah
			   tanggal (tengah malam), jadi membungkusnya tidak menambah
			   ketepatan - ia hanya mematikan indeks pada kolom tanggalnya.
			   Diukur pada 100.000 permintaan: type=ALL 69.264 baris menjadi
			   type=range 2.853 baris, 160 ms menjadi 25 ms, dengan jumlah dan
			   sidik hasil yang identik. */
			$this->db->where("$date_expr >= DATE_SUB(CURDATE(), INTERVAL " . (int) $days . " DAY)", NULL, FALSE);
		}
	}

	public function change_password($user_id, $new_password)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->db->table_exists('users')) {
			return false;
		}

		$this->db->where('userId', $user_id);
		return $this->db->update('users', array('password' => $new_password, 'updated_at' => date('Y-m-d H:i:s')));
	}

	public function get_user_by_email($email)
	{
		if (!$this->db->table_exists('users')) {
			return $this->empty_result(array('userId', 'email', 'password'));
		}

		return $this->db->where('email', $email)->get('users');
	}

	/* =====================================================================
	   MONITORING LAYANAN KESEHATAN (Dinas Kesehatan) - agregat berperiode
	   ---------------------------------------------------------------------
	   Blok ini menambahkan pembacaan berperiode untuk dasbor Dinkes. Metode
	   lama di atas (get_recent_request_events, get_diagnosis_dashboard) TIDAK
	   diubah karena masih dipakai panel-panel yang tetap ada di halaman ini.

	   Aturan yang dipegang blok ini:
	   - Definisi setiap KPI hanya ada di sini. View tidak menghitung apa pun
	     dan tidak mengarang angka: sumber kosong tampil sebagai keadaan
	     kosong, bukan nol.
	   - Nilai dari pengguna selalu lewat binding `?`.
	   - Batas periode: `kolom >= awal AND kolom < akhir` (awal inklusif,
	     akhir eksklusif).
	   - Zona waktu mengikuti jam basis data (NOW()), bukan jam PHP, supaya
	     angka dasbor sama dengan angka yang dicatat aplikasi.
	   - `request_status` adalah siklus permintaan yang otoritatif;
	     `visit_status` adalah siklus kunjungan yang terpisah dan tidak boleh
	     dipertukarkan.
	   ===================================================================== */

	/** Batas rentang laporan, mencegah satu permintaan mengambil seluruh riwayat. */
	const MONITORING_MAX_DAYS = 366;

	/** Batas baris yang dipakai saat menghitung median waktu respons. */
	const MONITORING_MEDIAN_ROW_LIMIT = 20000;

	/** Nama bulan singkat Bahasa Indonesia, tanpa bergantung pada setlocale. */
	private function monitoring_month_labels()
	{
		return array(
			1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
			7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
		);
	}

	private function monitoring_is_date($value)
	{
		if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
			return false;
		}
		$parts = explode('-', $value);

		return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
	}

	/** Melabeli tanggal sebagai "24 Okt" atau "24 Okt 2026". */
	public function monitoring_date_label($date, $with_year = false)
	{
		$date = trim((string) $date);
		if (!$this->monitoring_is_date(substr($date, 0, 10))) {
			return '-';
		}
		$month = (int) substr($date, 5, 2);
		$labels = $this->monitoring_month_labels();
		$label = (int) substr($date, 8, 2) . ' ' . (isset($labels[$month]) ? $labels[$month] : substr($date, 5, 2));

		return $with_year ? $label . ' ' . substr($date, 0, 4) : $label;
	}

	/** Melabeli tanggal+waktu sebagai "24 Okt 2026 14.30" (format Indonesia). */
	public function monitoring_datetime_label($value)
	{
		$value = trim((string) $value);
		if ($value === '') {
			return '-';
		}
		$timestamp = strtotime($value);
		if (!$timestamp) {
			return '-';
		}

		return $this->monitoring_date_label(date('Y-m-d', $timestamp), true)
			. ' ' . date('H.i', $timestamp);
	}

	/** Jam basis data. Dasbor memakai jam yang sama dengan pencatatan aplikasi. */
	public function monitoring_db_now()
	{
		$row = $this->db->query('SELECT NOW() AS sekarang')->row();

		return ($row && $row->sekarang) ? (string) $row->sekarang : date('Y-m-d H:i:s');
	}

	public function monitoring_period_options()
	{
		return array(
			'hari_ini' => 'Hari ini',
			'7' => '7 hari terakhir',
			'30' => '30 hari terakhir',
			'90' => '90 hari terakhir',
			'kustom' => 'Rentang khusus',
		);
	}

	/** Daftar Puskesmas untuk filter. Master operasional kanonik = m_puskesmas. */
	public function monitoring_puskesmas_options()
	{
		if (!$this->can_join_puskesmas()) {
			return array();
		}

		return $this->db
			->select('kode_pkm, nama_puskesmas, status')
			->from('m_puskesmas')
			->order_by('nama_puskesmas', 'ASC')
			->get()
			->result();
	}

	/**
	 * Menyelesaikan pilihan periode menjadi batas waktu yang eksplisit.
	 *
	 * Selalu mengembalikan kunci yang sama supaya view tidak perlu menebak.
	 * Masukan yang tidak sah TIDAK menghasilkan halaman galat: filter jatuh ke
	 * 30 hari terakhir dan alasannya dikembalikan lewat `notice`, supaya
	 * pengguna tahu angkanya bukan angka yang ia minta.
	 */
	public function monitoring_resolve_period($preset, $dari, $sampai)
	{
		$options = $this->monitoring_period_options();
		$preset = (string) $preset;
		$notice = '';

		if (!isset($options[$preset])) {
			$preset = '30';
		}

		$today = substr($this->monitoring_db_now(), 0, 10);
		$start_date = $today;
		$end_date = $today;
		$days = 1;

		if ($preset === 'kustom') {
			$dari = trim((string) $dari);
			$sampai = trim((string) $sampai);
			if (!$this->monitoring_is_date($dari) || !$this->monitoring_is_date($sampai)) {
				$preset = '30';
				$notice = 'Tanggal awal dan tanggal akhir wajib diisi dengan tanggal yang sah. Filter dikembalikan ke 30 hari terakhir.';
			} elseif ($dari > $sampai) {
				$preset = '30';
				$notice = 'Tanggal awal melebihi tanggal akhir. Filter dikembalikan ke 30 hari terakhir.';
			} else {
				$span = (int) floor((strtotime($sampai) - strtotime($dari)) / 86400) + 1;
				if ($span > self::MONITORING_MAX_DAYS) {
					$preset = '30';
					$notice = 'Rentang laporan dibatasi ' . self::MONITORING_MAX_DAYS . ' hari. Filter dikembalikan ke 30 hari terakhir.';
				} else {
					$start_date = $dari;
					$end_date = $sampai;
					$days = $span;
				}
			}
		}

		if ($preset !== 'kustom') {
			$days = ($preset === 'hari_ini') ? 1 : (int) $preset;
			$end_date = $today;
			$start_date = date('Y-m-d', strtotime($end_date . ' -' . ($days - 1) . ' day'));
		}

		$bucket = ($days <= 31) ? 'day' : 'week';
		$previous_end = $start_date;
		$previous_start = date('Y-m-d', strtotime($start_date . ' -' . $days . ' day'));
		$previous_end_label = date('Y-m-d', strtotime($previous_end . ' -1 day'));

		return array(
			'ok' => true,
			'preset' => $preset,
			'preset_label' => $options[$preset],
			'notice' => $notice,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'start' => $start_date . ' 00:00:00',
			'end' => date('Y-m-d', strtotime($end_date . ' +1 day')) . ' 00:00:00',
			'days' => $days,
			'bucket' => $bucket,
			'bucket_label' => ($bucket === 'day') ? 'harian' : 'mingguan',
			'label' => $this->monitoring_date_label($start_date, true)
				. ' - ' . $this->monitoring_date_label($end_date, true),
			'previous_start' => $previous_start . ' 00:00:00',
			'previous_end' => $previous_end . ' 00:00:00',
			'previous_label' => $days . ' hari sebelumnya ('
				. $this->monitoring_date_label($previous_start) . ' - '
				. $this->monitoring_date_label($previous_end_label) . ')',
			'dari_input' => ($preset === 'kustom') ? $start_date : '',
			'sampai_input' => ($preset === 'kustom') ? $end_date : '',
		);
	}

	/**
	 * Ambang eskalasi permintaan yang belum diterima.
	 *
	 * Dibaca dari konfigurasi yang SAMA dengan pengawas permintaan
	 * (`application/libraries/Pending_request_watchdog.php`), supaya dasbor dan
	 * pengawas tidak pernah memakai dua angka berbeda untuk konsep yang sama.
	 */
	public function monitoring_escalation_thresholds()
	{
		$reminder = $this->config->item('request_accept_reminder_minutes');
		$overdue = $this->config->item('request_accept_overdue_minutes');

		return array(
			'reminder' => (is_numeric($reminder) && (int) $reminder > 0) ? (int) $reminder : 10,
			'overdue' => (is_numeric($overdue) && (int) $overdue > 0) ? (int) $overdue : 20,
		);
	}

	/**
	 * Selisih kerangka jam antara kolom DATETIME dan NOW() basis data.
	 *
	 * Sejak zona waktu PHP disamakan dengan zona sesi basis data (lihat
	 * `config.php`), selisih ini **0** dan tidak ada penggeseran yang dilakukan.
	 * Fungsi ini tetap ada sebagai penjaga: kalau kelak salah satu sisi diubah
	 * tanpa mengubah yang lain, batas periode otomatis menyesuaikan alih-alih
	 * meleset diam-diam.
	 *
	 * Selisihnya DIHITUNG, bukan dipatok, sehingga tidak ada angka yang perlu
	 * diingat saat konfigurasi berubah.
	 */
	private function monitoring_datetime_frame_shift_seconds()
	{
		$db_offset = $this->monitoring_scalar(
			'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS selisih'
		);

		// date('Z') = offset zona waktu PHP terhadap UTC, dalam detik.
		return (int) $db_offset - (int) date('Z');
	}

	/**
	 * Batas periode yang diterjemahkan ke kerangka jam kolom DATETIME.
	 *
	 * Penggeseran dilakukan pada BATAS, bukan pada kolom, sehingga
	 * `completed_at >= ? AND completed_at < ?` tetap sargable dan indeks
	 * (request_status, completed_at) tetap dipakai untuk rentang.
	 */
	private function monitoring_bounds_in_datetime_frame($start, $end)
	{
		$shift = $this->monitoring_datetime_frame_shift_seconds();
		if ($shift === 0) {
			return array('start' => $start, 'end' => $end);
		}

		return array(
			'start' => date('Y-m-d H:i:s', strtotime($start) - $shift),
			'end' => date('Y-m-d H:i:s', strtotime($end) - $shift),
		);
	}

	/**
	 * Permintaan masuk dan layanan selesai untuk satu rentang.
	 *
	 * Dua query saja, dan keduanya mengembalikan kunci yang sama sehingga
	 * periode pembanding memakai definisi yang identik.
	 */
	private function monitoring_period_totals($start, $end, $filter = '')
	{
		$totals = array(
			'masuk' => 0,
			'selesai_kohort' => 0,
			'batal_kohort' => 0,
			'aktif_kohort' => 0,
			'selesai_pada_periode' => 0,
			'selesai_dari_sebelumnya' => 0,
		);

		if (!$this->db->table_exists('requests')) {
			return $totals;
		}

		$facility = $this->monitoring_facility_where($filter);

		$cohort = $this->db->query(
			"SELECT
				COUNT(*) AS masuk,
				SUM(CASE WHEN request_status = 'Completed' THEN 1 ELSE 0 END) AS selesai_kohort,
				SUM(CASE WHEN request_status = 'Cancelled' THEN 1 ELSE 0 END) AS batal_kohort,
				SUM(CASE WHEN request_status IN ('Pending','Accepted') THEN 1 ELSE 0 END) AS aktif_kohort
			FROM requests
			WHERE created_at >= ? AND created_at < ?" . $facility,
			array($start, $end)
		)->row();

		if ($cohort) {
			$totals['masuk'] = (int) $cohort->masuk;
			$totals['selesai_kohort'] = (int) $cohort->selesai_kohort;
			$totals['batal_kohort'] = (int) $cohort->batal_kohort;
			$totals['aktif_kohort'] = (int) $cohort->aktif_kohort;
		}

		/* Layanan selesai diukur dari waktu selesai, bukan waktu masuk:
		   pekerjaan yang dimulai sebelum periode tetapi selesai di dalam periode
		   tetap dihitung sebagai layanan yang selesai pada periode itu.
		   Batasnya digeser ke kerangka jam completed_at supaya rentangnya benar;
		   `created_at` tetap dibandingkan dengan batas asli karena keduanya
		   memakai jam basis data. */
		$completed_bounds = $this->monitoring_bounds_in_datetime_frame($start, $end);

		$completed = $this->db->query(
			"SELECT
				COUNT(*) AS selesai_pada_periode,
				SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) AS selesai_dari_sebelumnya
			FROM requests
			WHERE request_status = 'Completed' AND completed_at >= ? AND completed_at < ?" . $facility,
			array($start, $completed_bounds['start'], $completed_bounds['end'])
		)->row();

		if ($completed) {
			$totals['selesai_pada_periode'] = (int) $completed->selesai_pada_periode;
			$totals['selesai_dari_sebelumnya'] = (int) $completed->selesai_dari_sebelumnya;
		}

		return $totals;
	}

	/**
	 * Ringkasan periode: angka utama, pembanding periode sebelumnya, dan
	 * tingkat penyelesaian kohort.
	 *
	 * Tingkat penyelesaian memakai kohort yang sama di pembilang dan penyebut
	 * (permintaan yang MASUK pada periode itu). Membagi selesai-periode dengan
	 * masuk-periode bukan tingkat penyelesaian yang sah, jadi tidak dipakai.
	 */
	public function monitoring_period_summary($period, $filter = '')
	{
		$current = $this->monitoring_period_totals($period['start'], $period['end'], $filter);
		$previous = $this->monitoring_period_totals($period['previous_start'], $period['previous_end'], $filter);

		$cohort = $current['selesai_kohort'] + $current['batal_kohort'] + $current['aktif_kohort'];
		$rate = ($cohort > 0) ? round($current['selesai_kohort'] * 100 / $cohort, 1) : null;

		return array(
			'masuk' => $current['masuk'],
			'selesai' => $current['selesai_pada_periode'],
			'selesai_kohort' => $current['selesai_kohort'],
			'selesai_dari_sebelumnya' => $current['selesai_dari_sebelumnya'],
			'batal' => $current['batal_kohort'],
			'aktif' => $current['aktif_kohort'],
			'masuk_sebelumnya' => $previous['masuk'],
			'selesai_sebelumnya' => $previous['selesai_pada_periode'],
			'delta_masuk' => $current['masuk'] - $previous['masuk'],
			'delta_selesai' => $current['selesai_pada_periode'] - $previous['selesai_pada_periode'],
			'tingkat_penyelesaian' => $rate,
			'tingkat_penyelesaian_kohort' => $cohort,
		);
	}

	/**
	 * Beban kerja saat ini (point-in-time), terpisah dari angka periode.
	 *
	 * `kumulatif_*` disertakan supaya angka yang dulu tampil di kartu lama tetap
	 * dapat ditampilkan tanpa berubah arti.
	 */
	public function monitoring_outstanding($filter = '')
	{
		$outstanding = array(
			'pending' => 0,
			'accepted' => 0,
			'aktif' => 0,
			'lewat_reminder' => 0,
			'lewat_overdue' => 0,
			'kumulatif_masuk' => 0,
			'kumulatif_selesai' => 0,
			'kumulatif_batal' => 0,
			'reminder_minutes' => 10,
			'overdue_minutes' => 20,
			'filter_aktif' => false,
		);

		$thresholds = $this->monitoring_escalation_thresholds();
		$outstanding['reminder_minutes'] = (int) $thresholds['reminder'];
		$outstanding['overdue_minutes'] = (int) $thresholds['overdue'];

		if (!$this->db->table_exists('requests')) {
			return $outstanding;
		}

		$reminder = (int) $thresholds['reminder'];
		$overdue = (int) $thresholds['overdue'];
		$facility = $this->monitoring_facility_where($filter);
		$outstanding['filter_aktif'] = ($facility !== '');

		$row = $this->db->query(
			"SELECT
				SUM(CASE WHEN request_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
				SUM(CASE WHEN request_status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
				SUM(CASE WHEN request_status IN ('Pending','Accepted') THEN 1 ELSE 0 END) AS aktif,
				SUM(CASE WHEN request_status = 'Completed' THEN 1 ELSE 0 END) AS kumulatif_selesai,
				SUM(CASE WHEN request_status = 'Cancelled' THEN 1 ELSE 0 END) AS kumulatif_batal,
				COUNT(*) AS kumulatif_masuk,
				SUM(CASE WHEN request_status = 'Pending'
					AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= " . $reminder . " THEN 1 ELSE 0 END) AS lewat_reminder,
				SUM(CASE WHEN request_status = 'Pending'
					AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= " . $overdue . " THEN 1 ELSE 0 END) AS lewat_overdue
			FROM requests
			WHERE 1=1" . $facility
		)->row();

		if ($row) {
			foreach (array_keys($outstanding) as $key) {
				if (isset($row->{$key})) {
					$outstanding[$key] = (int) $row->{$key};
				}
			}
		}

		return $outstanding;
	}

	/**
	 * Kesiapan operasional Puskesmas.
	 *
	 * Akun aktif TIDAK sama dengan fasilitas beroperasi. Fasilitas dihitung
	 * beroperasi hanya bila punya minimal satu staf aktif yang tertaut akun
	 * nakes aktif - pola join yang sama dengan Nakes_presence_service.
	 */
	public function monitoring_facility_readiness()
	{
		$result = array(
			'terdaftar' => 0,
			'aktif' => 0,
			'nonaktif' => 0,
			'beroperasi' => 0,
			'tanpa_staf' => 0,
			'staf_tanpa_akun' => 0,
			'sumber_tersedia' => false,
		);

		if (!$this->can_join_puskesmas()) {
			return $result;
		}
		$result['sumber_tersedia'] = true;

		$counts = $this->db->query(
			"SELECT
				COUNT(*) AS terdaftar,
				SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) AS aktif,
				SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) AS nonaktif
			FROM m_puskesmas"
		)->row();

		if ($counts) {
			$result['terdaftar'] = (int) $counts->terdaftar;
			$result['aktif'] = (int) $counts->aktif;
			$result['nonaktif'] = (int) $counts->nonaktif;
		}

		if (!$this->db->table_exists('puskesmas_staff') || !$this->db->table_exists('users')) {
			return $result;
		}

		$operational = $this->db->query(
			"SELECT COUNT(DISTINCT mp.kode_pkm) AS beroperasi
			FROM m_puskesmas mp
			JOIN puskesmas_staff ps
				ON ps.kode_pkm = mp.kode_pkm
				AND ps.status = 'aktif'
				AND ps.user_id IS NOT NULL
			JOIN users u
				ON u.userId = ps.user_id
				AND u.role = 'dokter'
				AND u.status = 'aktif'
			WHERE mp.status = 'aktif'"
		)->row();

		if ($operational) {
			$result['beroperasi'] = (int) $operational->beroperasi;
		}

		$without_staff = $this->db->query(
			"SELECT COUNT(*) AS tanpa_staf
			FROM m_puskesmas mp
			WHERE mp.status = 'aktif'
				AND NOT EXISTS (
					SELECT 1 FROM puskesmas_staff ps
					JOIN users u ON u.userId = ps.user_id AND u.role = 'dokter' AND u.status = 'aktif'
					WHERE ps.kode_pkm = mp.kode_pkm AND ps.status = 'aktif' AND ps.user_id IS NOT NULL
				)"
		)->row();

		if ($without_staff) {
			$result['tanpa_staf'] = (int) $without_staff->tanpa_staf;
		}

		$staff_without_account = $this->db->query(
			"SELECT COUNT(*) AS staf_tanpa_akun
			FROM puskesmas_staff ps
			LEFT JOIN users u ON u.userId = ps.user_id AND u.role = 'dokter' AND u.status = 'aktif'
			WHERE ps.status = 'aktif' AND (ps.user_id IS NULL OR u.userId IS NULL)"
		)->row();

		if ($staff_without_account) {
			$result['staf_tanpa_akun'] = (int) $staff_without_account->staf_tanpa_akun;
		}

		return $result;
	}

	/**
	 * Seri waktu permintaan masuk dan layanan selesai.
	 *
	 * Selalu mengembalikan satu keranjang untuk setiap rentang pada periode,
	 * termasuk keranjang bernilai nol: hari tanpa permintaan memang bernilai
	 * nol, itu nol yang sebenarnya. Sebaliknya, baris `Completed` tanpa
	 * `completed_at` tidak dapat ditempatkan pada tanggal mana pun; jumlahnya
	 * dilaporkan terpisah sebagai celah data, bukan dihitung sebagai nol.
	 */
	public function monitoring_series($period, $filter = '')
	{
		$series = array(
			'bucket' => $period['bucket'],
			'bucket_label' => $period['bucket_label'],
			'labels' => array(),
			'range_labels' => array(),
			'masuk' => array(),
			'selesai' => array(),
			'total_masuk' => 0,
			'total_selesai' => 0,
			'maks' => 0,
			'selesai_tanpa_waktu' => 0,
			'ada_data' => false,
		);

		if (!$this->db->table_exists('requests')) {
			return $series;
		}

		$start_ts = strtotime($period['start_date']);
		$end_ts = strtotime($period['end_date']);
		if (!$start_ts || !$end_ts || $end_ts < $start_ts) {
			return $series;
		}

		$step = ($period['bucket'] === 'week') ? 7 : 1;
		$index = array();

		for ($ts = $start_ts; $ts <= $end_ts; $ts = strtotime('+' . $step . ' day', $ts)) {
			$bucket_end = strtotime('+' . ($step - 1) . ' day', $ts);
			if ($bucket_end > $end_ts) {
				$bucket_end = $end_ts;
			}

			$bucket_start_date = date('Y-m-d', $ts);
			$bucket_end_date = date('Y-m-d', $bucket_end);
			$position = count($series['labels']);

			$series['labels'][] = $this->monitoring_date_label($bucket_start_date);
			$series['range_labels'][] = ($bucket_start_date === $bucket_end_date)
				? $this->monitoring_date_label($bucket_start_date)
				: $this->monitoring_date_label($bucket_start_date) . ' - ' . $this->monitoring_date_label($bucket_end_date);
			$series['masuk'][] = 0;
			$series['selesai'][] = 0;

			for ($day = $ts; $day <= $bucket_end; $day = strtotime('+1 day', $day)) {
				$index[date('Y-m-d', $day)] = $position;
			}
		}

		$facility = $this->monitoring_facility_where($filter);

		$incoming = $this->db->query(
			"SELECT DATE(created_at) AS hari, COUNT(*) AS jumlah
			FROM requests
			WHERE created_at >= ? AND created_at < ?" . $facility . "
			GROUP BY DATE(created_at)",
			array($period['start'], $period['end'])
		)->result();

		foreach ($incoming as $row) {
			$key = substr((string) $row->hari, 0, 10);
			if (isset($index[$key])) {
				$series['masuk'][$index[$key]] += (int) $row->jumlah;
			}
		}

		$completed_bounds = $this->monitoring_bounds_in_datetime_frame($period['start'], $period['end']);
		$completed = $this->db->query(
			"SELECT DATE(completed_at) AS hari, COUNT(*) AS jumlah
			FROM requests
			WHERE request_status = 'Completed' AND completed_at >= ? AND completed_at < ?" . $facility . "
			GROUP BY DATE(completed_at)",
			array($completed_bounds['start'], $completed_bounds['end'])
		)->result();

		foreach ($completed as $row) {
			$key = substr((string) $row->hari, 0, 10);
			if (isset($index[$key])) {
				$series['selesai'][$index[$key]] += (int) $row->jumlah;
			}
		}

		$series['total_masuk'] = array_sum($series['masuk']);
		$series['total_selesai'] = array_sum($series['selesai']);
		$series['maks'] = max(
			$series['masuk'] ? max($series['masuk']) : 0,
			$series['selesai'] ? max($series['selesai']) : 0
		);
		$series['ada_data'] = ($series['total_masuk'] > 0 || $series['total_selesai'] > 0);

		$missing = $this->db->query(
			"SELECT COUNT(*) AS tanpa_waktu
			FROM requests
			WHERE request_status = 'Completed' AND completed_at IS NULL" . $facility
		)->row();
		if ($missing) {
			$series['selesai_tanpa_waktu'] = (int) $missing->tanpa_waktu;
		}

		return $series;
	}

	/**
	 * Perbandingan aktivitas layanan antar Puskesmas.
	 *
	 * Fasilitas terdaftar yang tidak punya aktivitas tetap muncul dengan nilai
	 * nol supaya Dinkes bisa membedakan "tidak ada permintaan" dari "tidak
	 * terpantau". Urutan mengikuti besaran permintaan (menurun), bukan
	 * peringkat mutu: kapasitas dan jam kerja fasilitas tidak sama.
	 */
	public function monitoring_facility_comparison($period, $puskesmas_filter)
	{
		$filter = trim((string) $puskesmas_filter);
		$result = array(
			'rows' => array(),
			'maks_masuk' => 0,
			'total_masuk' => 0,
			'total_selesai' => 0,
			'total_aktif' => 0,
			'jumlah_fasilitas_aktif' => 0,
			/* Cakupan pelaporan: berapa fasilitas aktif yang benar-benar punya
			   aktivitas pada periode ini. Ini indikator tersendiri bagi Dinkes,
			   karena rata-rata yang hanya dihitung dari fasilitas yang melapor
			   akan terbaca aman walaupun sebagian besar fasilitas diam. */
			'fasilitas_melapor' => 0,
			'cakupan_persen' => null,
			'ada_data' => false,
			'filter' => $filter,
		);

		if (!$this->db->table_exists('requests')) {
			return $result;
		}

		$code_expr = $this->puskesmas_key_expr('requests');
		$display_expr = $this->puskesmas_display_expr('requests', 'assigned_puskesmas');
		$assigned_code = $this->assigned_puskesmas_code_expr('requests');

		$where = array('requests.created_at >= ?', 'requests.created_at < ?');
		$binds = array($period['start'], $period['end']);

		if ($filter === '__legacy__') {
			$where[] = "($assigned_code IS NULL OR UPPER(COALESCE($assigned_code, '')) = 'DEFAULT')";
		} elseif ($filter !== '') {
			$where[] = $code_expr . ' = ' . $this->db->escape($filter);
		}

		$join = $this->can_join_puskesmas()
			? 'LEFT JOIN m_puskesmas assigned_puskesmas ON assigned_puskesmas.kode_pkm = ' . $assigned_code
			: '';

		$rows = $this->db->query(
			"SELECT
				$code_expr AS kode,
				$display_expr AS nama,
				COUNT(*) AS masuk,
				SUM(CASE WHEN requests.request_status = 'Completed' THEN 1 ELSE 0 END) AS selesai,
				SUM(CASE WHEN requests.request_status IN ('Pending','Accepted') THEN 1 ELSE 0 END) AS aktif,
				SUM(CASE WHEN requests.request_status = 'Cancelled' THEN 1 ELSE 0 END) AS batal
			FROM requests
			$join
			WHERE " . implode(' AND ', $where) . "
			GROUP BY $code_expr, $display_expr
			ORDER BY masuk DESC, nama ASC",
			$binds
		)->result();

		$by_code = array();
		foreach ($rows as $row) {
			$by_code[(string) $row->kode] = $row;
		}

		$combined = array();
		if ($this->can_join_puskesmas() && $filter === '') {
			$registered = $this->db
				->select('kode_pkm, nama_puskesmas, status')
				->from('m_puskesmas')
				->order_by('nama_puskesmas', 'ASC')
				->get()
				->result();

			foreach ($registered as $facility) {
				$kode = (string) $facility->kode_pkm;
				$combined[] = (object) array(
					'kode' => $kode,
					'nama' => (string) $facility->nama_puskesmas,
					'status_fasilitas' => (string) $facility->status,
					'terdaftar' => true,
					'masuk' => isset($by_code[$kode]) ? (int) $by_code[$kode]->masuk : 0,
					'selesai' => isset($by_code[$kode]) ? (int) $by_code[$kode]->selesai : 0,
					'aktif' => isset($by_code[$kode]) ? (int) $by_code[$kode]->aktif : 0,
					'batal' => isset($by_code[$kode]) ? (int) $by_code[$kode]->batal : 0,
				);
				unset($by_code[$kode]);
			}
		}

		foreach ($by_code as $row) {
			$combined[] = (object) array(
				'kode' => (string) $row->kode,
				'nama' => (string) $row->nama,
				'status_fasilitas' => '',
				'terdaftar' => false,
				'masuk' => (int) $row->masuk,
				'selesai' => (int) $row->selesai,
				'aktif' => (int) $row->aktif,
				'batal' => (int) $row->batal,
			);
		}

		usort($combined, function ($a, $b) {
			if ($a->masuk === $b->masuk) {
				return strcmp($a->nama, $b->nama);
			}
			return ($a->masuk < $b->masuk) ? 1 : -1;
		});

		foreach ($combined as $row) {
			$result['total_masuk'] += $row->masuk;
			$result['total_selesai'] += $row->selesai;
			$result['total_aktif'] += $row->aktif;
			$result['maks_masuk'] = max($result['maks_masuk'], $row->masuk);
			if ($row->masuk > 0) {
				$result['ada_data'] = true;
			}
			if ($row->status_fasilitas === 'aktif') {
				$result['jumlah_fasilitas_aktif']++;
				if ($row->masuk > 0) {
					$result['fasilitas_melapor']++;
				}
			}
		}

		/* Penyebut nol berarti cakupan tidak dapat dinyatakan, bukan nol persen.
		   Pembedaan itu penting: "belum dapat dihitung" dan "tidak ada yang
		   melapor" adalah dua keadaan yang berbeda. */
		if ($result['jumlah_fasilitas_aktif'] > 0) {
			$result['cakupan_persen'] = (int) round(
				$result['fasilitas_melapor'] * 100 / $result['jumlah_fasilitas_aktif']
			);
		}

		$result['rows'] = $combined;

		return $result;
	}

	/** Melabeli durasi detik sebagai "45 dtk", "12 mnt 30 dtk", "2 j 05 mnt". */
	public function monitoring_duration_label($seconds)
	{
		if ($seconds === null || $seconds === '' || !is_numeric($seconds)) {
			return '-';
		}
		$seconds = (int) $seconds;
		if ($seconds < 0) {
			return '-';
		}
		if ($seconds < 60) {
			return $seconds . ' dtk';
		}
		$minutes = (int) floor($seconds / 60);
		if ($minutes < 60) {
			return $minutes . ' mnt ' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT) . ' dtk';
		}
		$hours = (int) floor($minutes / 60);
		if ($hours < 24) {
			return $hours . ' j ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . ' mnt';
		}
		$days = (int) floor($hours / 24);

		return $days . ' hr ' . str_pad((string) ($hours % 24), 2, '0', STR_PAD_LEFT) . ' j';
	}

	/**
	 * Median dan persentil ke-90 waktu respons.
	 *
	 * Titik ukur: dari event `request_created` ke event `request_accepted` di
	 * `request_events`. Tabel `requests` tidak menyimpan `accepted_at`, jadi
	 * tabel event adalah satu-satunya sumber yang sah.
	 *
	 * Populasi: hanya permintaan yang MASUK pada periode DAN sudah punya kedua
	 * event. Permintaan yang masih menunggu tidak punya durasi dan tidak
	 * diikutsertakan; jumlahnya dilaporkan supaya populasi pengukuran jelas.
	 * Durasi negatif dibuang sebagai data tidak sah.
	 */
	public function monitoring_response_median($period, $filter = '')
	{
		$result = array(
			'sumber_tersedia' => false,
			'median_seconds' => null,
			'p90_seconds' => null,
			'sampel' => 0,
			'tanpa_terima' => 0,
			'durasi_tidak_sah' => 0,
			'terpotong' => false,
		);

		if (!$this->can_query_request_events()) {
			return $result;
		}
		$result['sumber_tersedia'] = true;

		$limit = (int) self::MONITORING_MEDIAN_ROW_LIMIT;
		$facility = $this->monitoring_facility_where($filter);
		$rows = $this->db->query(
			"SELECT
				requests.request_id,
				MIN(CASE WHEN request_events.event_type = 'request_created' THEN request_events.created_at END) AS dibuat,
				MIN(CASE WHEN request_events.event_type = 'request_accepted' THEN request_events.created_at END) AS diterima
			FROM requests
			JOIN request_events ON request_events.request_id = requests.request_id
			WHERE requests.created_at >= ? AND requests.created_at < ?
				AND request_events.event_type IN ('request_created','request_accepted')" . $facility . "
			GROUP BY requests.request_id
			LIMIT " . $limit,
			array($period['start'], $period['end'])
		)->result();

		$durations = array();
		foreach ($rows as $row) {
			if ($row->dibuat === null || $row->diterima === null) {
				$result['tanpa_terima']++;
				continue;
			}
			$seconds = strtotime((string) $row->diterima) - strtotime((string) $row->dibuat);
			if ($seconds < 0) {
				$result['durasi_tidak_sah']++;
				continue;
			}
			$durations[] = $seconds;
		}

		$result['terpotong'] = (count($rows) >= $limit);
		sort($durations);
		$count = count($durations);
		$result['sampel'] = $count;

		if ($count > 0) {
			$middle = (int) floor(($count - 1) / 2);
			$result['median_seconds'] = ($count % 2 === 1)
				? $durations[$middle]
				: (int) round(($durations[$middle] + $durations[$middle + 1]) / 2);

			$p90 = (int) ceil($count * 0.9) - 1;
			$result['p90_seconds'] = $durations[max(0, min($p90, $count - 1))];
		}

		return $result;
	}

	/**
	 * Pengecualian operasional yang membutuhkan tindak lanjut.
	 *
	 * Tingkat ditentukan aturan tertulis, bukan warna:
	 *  - kritis      : belum diterima DAN (Puskesmas tujuan belum ditetapkan
	 *                  ATAU sudah melewati ambang terlambat)
	 *  - peringatan  : belum diterima, sudah melewati ambang pengingat
	 *  - informasi   : sudah diterima tetapi belum punya PIC aktif
	 * Ketiga ambang waktu dibaca dari konfigurasi yang sama dengan
	 * Pending_request_watchdog, sehingga dasbor dan pengawas tidak berbeda.
	 */
	public function monitoring_exceptions($limit = 10, $filter = '', $with_rows = true)
	{
		$thresholds = $this->monitoring_escalation_thresholds();
		$reminder = (int) $thresholds['reminder'];
		$overdue = (int) $thresholds['overdue'];
		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 10;
		}

		$result = array(
			'rows' => array(),
			'jumlah_kritis' => 0,
			'jumlah_peringatan' => 0,
			'jumlah_informasi' => 0,
			'total' => 0,
			'sudah_dieskalasi' => 0,
			'sudah_ditandai' => 0,
			'reminder_minutes' => $reminder,
			'overdue_minutes' => $overdue,
			'sumber_tersedia' => false,
			'terpotong' => false,
			'filter_aktif' => false,
		);

		if (!$this->db->table_exists('requests')) {
			return $result;
		}
		$result['sumber_tersedia'] = true;

		$assigned_code = $this->assigned_puskesmas_code_expr('requests');
		$display = $this->puskesmas_display_expr('requests', 'assigned_puskesmas');
		$waiting = "TIMESTAMPDIFF(MINUTE, requests.created_at, NOW())";

		/* NOT EXISTS, bukan LEFT JOIN: penugasan PIC bisa lebih dari satu baris
		   per permintaan, dan join akan menggandakan hitungan. */
		$has_pic_schema = $this->can_query_pic_assignment();
		$without_pic = $has_pic_schema
			? "NOT EXISTS (SELECT 1 FROM request_staff_assignments pic
				WHERE pic.request_id = requests.request_id AND pic.status = 'aktif')"
			: '1=0';

		$pending = "requests.request_status = 'Pending'";
		$accepted = "requests.request_status = 'Accepted'";
		$no_facility = "$assigned_code IS NULL";

		$is_critical = "($pending AND ($no_facility OR $waiting >= $overdue))";
		$is_warning = "($pending AND NOT ($no_facility) AND $waiting >= $reminder AND $waiting < $overdue)";
		$is_info = "($accepted AND $without_pic)";
		$any_exception = "($is_critical OR $is_warning OR $is_info)";

		$join = $this->can_join_puskesmas()
			? 'LEFT JOIN m_puskesmas assigned_puskesmas ON assigned_puskesmas.kode_pkm = ' . $assigned_code
			: '';
		$facility = $this->monitoring_facility_where($filter);
		$result['filter_aktif'] = ($facility !== '');

		/* Penanda tindak lanjut dihitung pada himpunan pengecualian yang SAMA,
		   bukan hanya pada sepuluh baris yang ditampilkan. Kalau tidak, angka
		   kemajuannya berubah mengikuti panjang daftar - dan itu menyesatkan.
		   `monitoring_followups.request_id` adalah kunci utama, jadi join ini
		   1:1 dan tidak menggandakan hitungan. */
		$followup_available = $this->monitoring_followups_available();
		$followup_join = $followup_available
			? 'LEFT JOIN monitoring_followups mf ON mf.request_id = requests.request_id'
			: '';
		$followup_count = $followup_available
			? ",\n\t\t\t\tSUM(CASE WHEN mf.request_id IS NOT NULL THEN 1 ELSE 0 END) AS sudah_ditandai"
			: '';

		$counts = $this->db->query(
			"SELECT
				SUM(CASE WHEN $is_critical THEN 1 ELSE 0 END) AS kritis,
				SUM(CASE WHEN $is_warning THEN 1 ELSE 0 END) AS peringatan,
				SUM(CASE WHEN $is_info THEN 1 ELSE 0 END) AS informasi" . $followup_count . "
			FROM requests
			$join
			$followup_join
			WHERE $any_exception" . $facility
		)->row();

		if ($counts) {
			$result['jumlah_kritis'] = (int) $counts->kritis;
			$result['jumlah_peringatan'] = (int) $counts->peringatan;
			$result['jumlah_informasi'] = (int) $counts->informasi;
			$result['total'] = $result['jumlah_kritis'] + $result['jumlah_peringatan'] + $result['jumlah_informasi'];
			$result['sudah_ditandai'] = isset($counts->sudah_ditandai) ? (int) $counts->sudah_ditandai : 0;
		}

		/* Baris dan jumlah eskalasi hanya diperlukan oleh panel. Pembaruan
		   berkala tiap 30 detik hanya memakai angkanya, jadi meminta daftar
		   baris berarti membayar pengurutan atas seluruh pengecualian untuk
		   hasil yang tidak dibaca. */
		if (!$with_rows) {
			return $result;
		}

		$severity = "CASE
			WHEN $is_critical THEN 'kritis'
			WHEN $is_warning THEN 'peringatan'
			ELSE 'informasi'
		END";
		$reason = "CASE
			WHEN $pending AND $no_facility THEN 'Puskesmas tujuan belum ditetapkan'
			WHEN $pending AND $waiting >= $overdue THEN 'Belum diterima, melewati ambang terlambat'
			WHEN $pending THEN 'Belum diterima, melewati ambang pengingat'
			ELSE 'Sudah diterima tetapi belum ada PIC aktif'
		END";

		$rows = $this->db->query(
			"SELECT
				requests.request_id,
				requests.request_status AS status,
				$display AS puskesmas,
				$severity AS tingkat,
				$reason AS alasan,
				$waiting AS menit,
				requests.created_at AS created_at
			FROM requests
			$join
			WHERE $any_exception" . $facility . "
			ORDER BY
				CASE WHEN $is_critical THEN 0 WHEN $is_warning THEN 1 ELSE 2 END,
				$waiting DESC
			LIMIT " . $limit
		)->result();

		foreach ($rows as $row) {
			$row->created_at_label = $this->monitoring_datetime_label($row->created_at);
			$row->menit_label = $this->monitoring_duration_label(((int) $row->menit) * 60);
			$row->menit = (int) $row->menit;
		}

		$result['rows'] = $rows;
		$result['terpotong'] = (count($rows) >= $limit);

		if ($this->can_query_request_events()) {
			$escalated = $this->db->query(
				"SELECT COUNT(DISTINCT request_id) AS jumlah
				FROM request_events
				WHERE event_type = 'request.accept_overdue'"
			)->row();
			if ($escalated) {
				$result['sudah_dieskalasi'] = (int) $escalated->jumlah;
			}
		}

		return $result;
	}

	/**
	 * Potongan WHERE untuk filter Puskesmas.
	 *
	 * Dipakai SEMUA agregat yang berbicara tentang permintaan supaya kartu KPI,
	 * tren, dan daftar pengecualian selalu memakai filter yang sama. Nilainya
	 * sudah lewat `escape()`, jadi potongan ini aman disambung ke SQL.
	 */
	private function monitoring_facility_where($filter)
	{
		$filter = trim((string) $filter);
		if ($filter === '') {
			return '';
		}

		$assigned_code = $this->assigned_puskesmas_code_expr('requests');
		if ($filter === '__legacy__') {
			return " AND ($assigned_code IS NULL OR UPPER(COALESCE($assigned_code, '')) = 'DEFAULT')";
		}

		return ' AND ' . $this->puskesmas_key_expr('requests') . ' = ' . $this->db->escape($filter);
	}

	/** Satu nilai bilangan bulat dari query. Mengembalikan 0 bila tidak ada baris. */
	private function monitoring_scalar($sql, $binds = array())
	{
		$row = empty($binds) ? $this->db->query($sql)->row() : $this->db->query($sql, $binds)->row();
		if (!$row) {
			return 0;
		}
		$values = array_values((array) $row);

		return isset($values[0]) ? (int) $values[0] : 0;
	}

	/**
	 * Kapan data terakhir masuk.
	 *
	 * Dipakai dasbor untuk menyatakan kesegaran data apa adanya. Bila jam
	 * terakhir sudah lama, itu memang keadaan datanya, bukan alasan untuk
	 * menyembunyikan angka.
	 */
	public function monitoring_freshness()
	{
		$result = array(
			'terakhir' => '',
			'label' => '-',
			'menit_lalu' => null,
			'terakhir_event' => '-',
			'sumber_tersedia' => false,
		);

		if (!$this->db->table_exists('requests')) {
			return $result;
		}
		$result['sumber_tersedia'] = true;

		$row = $this->db->query('SELECT MAX(created_at) AS terakhir FROM requests')->row();
		if ($row && $row->terakhir) {
			$result['terakhir'] = (string) $row->terakhir;
			$result['label'] = $this->monitoring_datetime_label($row->terakhir);
			$result['menit_lalu'] = $this->monitoring_scalar(
				'SELECT TIMESTAMPDIFF(MINUTE, MAX(created_at), NOW()) AS selisih FROM requests'
			);
		}

		if ($this->can_query_request_events()) {
			$event = $this->db->query('SELECT MAX(created_at) AS terakhir FROM request_events')->row();
			if ($event && $event->terakhir) {
				$result['terakhir_event'] = $this->monitoring_datetime_label($event->terakhir);
			}
		}

		return $result;
	}

	/**
	 * Mutu data pelaporan, dengan simpanan sementara.
	 *
	 * Semua pemeriksaan READ-ONLY dan hanya menghitung. Dasbor tidak boleh
	 * memperbaiki catatan klinis agar angkanya tampak konsisten; perbaikan hanya
	 * lewat alur yang sudah berwenang di modul masing-masing.
	 *
	 * Tingkat:
	 *  - kritis     : membuat angka dasbor tidak dapat dipercaya
	 *  - peringatan : angka sah, tetapi ada keadaan yang perlu dirapikan
	 *  - informasi  : kelengkapan konfigurasi, bukan kesalahan pencatatan
	 *
	 * Mengapa ada simpanan: sepuluh pemeriksaan ini membaca SELURUH riwayat dan
	 * sengaja tidak punya penyaring rentang tanggal. Diukur pada 100.000
	 * permintaan, panel ini memakan 2.600 ms dari 3.207 ms satu muat halaman.
	 * Berbagai rumusan alternatif diuji dan semuanya lebih lambat, jadi biayanya
	 * inheren dan satu-satunya jalan adalah tidak menghitungnya ulang.
	 *
	 * Umur simpanannya DILAPORKAN ke halaman, bukan disembunyikan: pembaca harus
	 * tahu bahwa angkanya bisa tertinggal beberapa menit dari keadaan terbaru.
	 */
	public function monitoring_data_quality()
	{
		$ttl = (int) $this->config->item('monitoring_data_quality_cache_seconds');
		$key = 'monitoring_data_quality';

		if ($ttl > 0) {
			$stored = $this->monitoring_cache_read($key, $ttl);
			if ($stored !== null) {
				$stored['dari_simpanan'] = true;

				return $stored;
			}
		}

		$result = $this->monitoring_data_quality_compute();
		$result['dihitung_pada'] = date('Y-m-d H:i:s');
		$result['dihitung_label'] = $this->monitoring_datetime_label($result['dihitung_pada']);
		$result['dari_simpanan'] = false;

		if ($ttl > 0) {
			$this->monitoring_cache_write($key, $result);
		}

		return $result;
	}

	/**
	 * Membaca simpanan bila tabelnya ada dan isinya belum kedaluwarsa.
	 *
	 * Mengembalikan null bila tabel belum ada, isinya rusak, atau sudah
	 * kedaluwarsa. Pemanggil menghitung ulang dalam semua keadaan itu, sehingga
	 * dasbor tidak pernah gagal karena simpanannya bermasalah.
	 */
	private function monitoring_cache_read($key, $ttl)
	{
		if (!$this->db->table_exists('monitoring_cache')) {
			return null;
		}

		$row = $this->db->query(
			'SELECT payload, computed_at FROM monitoring_cache WHERE cache_key = ? LIMIT 1',
			array((string) $key)
		)->row();
		if (!$row) {
			return null;
		}

		$umur = time() - (int) strtotime((string) $row->computed_at);
		if ($umur < 0 || $umur > (int) $ttl) {
			return null;
		}

		$decoded = json_decode((string) $row->payload, true);

		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * Menyimpan hasil. Kegagalan menyimpan tidak boleh menggagalkan halaman:
	 * hasilnya sudah dihitung, jadi yang hilang hanya percepatan berikutnya.
	 */
	private function monitoring_cache_write($key, array $payload)
	{
		if (!$this->db->table_exists('monitoring_cache')) {
			return false;
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->query(
			'INSERT INTO monitoring_cache (cache_key, payload, computed_at)
			 VALUES (?, ?, ?)
			 ON DUPLICATE KEY UPDATE payload = VALUES(payload), computed_at = VALUES(computed_at)',
			array((string) $key, json_encode($payload), date('Y-m-d H:i:s'))
		);
		$error = $this->db->error();
		$this->db->db_debug = $db_debug;

		return empty($error['code']);
	}

	private function monitoring_data_quality_compute()
	{
		$checks = array();
		$tersedia = $this->db->table_exists('requests');

		/* Selisih kerangka jam antara PHP dan sesi basis data. Kini 0 karena
		   zona waktu sudah disamakan. Dihitung di awal supaya ikut dilaporkan
		   ke dasbor: penjaga yang mengoreksi batas periode tanpa memberi tahu
		   adalah penjaga yang menyembunyikan pergeseran, dan itu berlawanan
		   dengan prinsip berhenti-dan-lapor yang dipegang sistem ini. */
		$frame_shift = $this->monitoring_datetime_frame_shift_seconds();

		if ($tersedia) {
			$assigned_code = $this->assigned_puskesmas_code_expr('requests');

			$checks[] = array(
				'kode' => 'tanpa_fasilitas',
				'label' => 'Permintaan tanpa Puskesmas tujuan',
				'sebab' => 'Kode Puskesmas kosong, sehingga permintaan tidak dapat ditelusuri ke fasilitas penanggung jawab.',
				'tingkat' => 'kritis',
				'tindakan' => 'Buka Pantau konsultasi, saring Puskesmas belum tersedia',
				'jumlah' => $this->monitoring_scalar(
					"SELECT COUNT(*) AS jumlah FROM requests
					WHERE $assigned_code IS NULL OR UPPER(COALESCE($assigned_code, '')) = 'DEFAULT'"
				),
			);

			if ($this->can_join_puskesmas()) {
				$checks[] = array(
					'kode' => 'fasilitas_nonaktif',
					'label' => 'Permintaan menunjuk Puskesmas nonaktif',
					'sebab' => 'Fasilitas tujuannya berstatus nonaktif di master, sehingga tidak ada tim yang menanganinya.',
					'tingkat' => 'kritis',
					'tindakan' => 'Periksa penempatan staf dan status fasilitas',
					'jumlah' => $this->monitoring_scalar(
						"SELECT COUNT(*) AS jumlah FROM requests
						JOIN m_puskesmas mp ON mp.kode_pkm = $assigned_code
						WHERE mp.status = 'nonaktif'"
					),
				);

				$checks[] = array(
					'kode' => 'kode_tidak_terdaftar',
					'label' => 'Kode Puskesmas tidak ada di master',
					'sebab' => 'Kode pada permintaan tidak cocok dengan satu pun baris m_puskesmas.',
					'tingkat' => 'peringatan',
					'tindakan' => 'Selaraskan kode fasilitas di master wilayah',
					'jumlah' => $this->monitoring_scalar(
						"SELECT COUNT(*) AS jumlah FROM requests
						WHERE $assigned_code IS NOT NULL
							AND NOT EXISTS (SELECT 1 FROM m_puskesmas mp WHERE mp.kode_pkm = $assigned_code)"
					),
				);
			}

			$checks[] = array(
				'kode' => 'selesai_tanpa_waktu',
				'label' => 'Selesai tanpa waktu selesai',
				'sebab' => 'Baris berstatus Completed tetapi completed_at kosong, sehingga tidak masuk seri layanan selesai.',
				'tingkat' => 'peringatan',
				'tindakan' => 'Telusuri lewat Penampil audit',
				'jumlah' => $this->monitoring_scalar(
					"SELECT COUNT(*) AS jumlah FROM requests
					WHERE request_status = 'Completed' AND completed_at IS NULL"
				),
			);

			/* Selisih kerangka jam dipakai untuk membandingkan kolom DATETIME
			   dengan kolom TIMESTAMP secara setara. Nilainya sudah dihitung di
			   awal fungsi; di sini hanya dipakai. */

			$checks[] = array(
				'kode' => 'selesai_sebelum_dibuat',
				'label' => 'Waktu selesai lebih awal daripada waktu masuk',
				'sebab' => 'Waktu selesai tidak mungkin mendahului waktu masuk. Pada baris lama, penyebabnya'
					. ' kerangka jam yang berbeda antar kolom (baris sebelum zona waktu disamakan);'
					. ' pada baris baru, penyebabnya urutan layanan yang salah.',
				'tingkat' => 'peringatan',
				'tindakan' => 'Telusuri lewat Penampil audit',
				'jumlah' => $this->monitoring_scalar(
					"SELECT COUNT(*) AS jumlah FROM requests
					WHERE completed_at IS NOT NULL
						AND DATE_ADD(completed_at, INTERVAL " . (int) $frame_shift . " SECOND) < created_at"
				),
			);

			if ($this->can_query_request_events()) {
				/* Event `request_created` terjadi pada saat yang sama dengan baris
				   permintaan dibuat, jadi selisihnya harus mendekati nol. Selisih
				   besar berarti event dan created_at memakai kerangka jam berbeda -
				   akibatnya perbandingan waktu lintas tabel untuk baris itu tidak
				   sah. Perbandingan antar-event dalam satu permintaan tetap sah,
				   sehingga median waktu respons tidak terpengaruh. */
				$checks[] = array(
					'kode' => 'jejak_event_beda_kerangka',
					'label' => 'Jejak pembuatan permintaan beda kerangka jam dengan waktu masuk',
					'sebab' => 'Selisih antara event request_created dan requests.created_at melebihi 5 menit.'
						. ' Keduanya menandai kejadian yang sama, jadi selisih sebesar itu berarti salah satu'
						. ' ditulis dengan kerangka jam yang berbeda (baris lama dari sebelum zona waktu disamakan).'
						. ' Perbandingan waktu lintas tabel untuk baris itu tidak sah.',
					'tingkat' => 'peringatan',
					'tindakan' => 'Baris lama: periksa Amandemen PRODUCT.md 2026-10-25',
					'jumlah' => $this->monitoring_scalar(
						"SELECT COUNT(*) AS jumlah FROM requests r
						JOIN request_events e ON e.request_id = r.request_id AND e.event_type = 'request_created'
						WHERE ABS(TIMESTAMPDIFF(SECOND, e.created_at, r.created_at)) > 300"
					),
				);

				/* Jangkar lokal untuk completed_at: event penyelesaian permintaan
				   yang sama. Keduanya ditulis PHP, jadi harus berdekatan. Selisih
				   berjam-jam menandakan kerangka jam yang berbeda. */
				$checks[] = array(
					'kode' => 'selesai_beda_kerangka',
					'label' => 'Waktu selesai beda kerangka jam dengan jejak penyelesaiannya',
					'sebab' => 'completed_at berselisih lebih dari satu jam dari seluruh event penyelesaian'
						. ' (request_completed / visit_completed) pada permintaan yang sama, padahal keduanya'
						. ' ditulis pada saat yang sama. Baris lama dari sebelum zona waktu disamakan.',
					'tingkat' => 'peringatan',
					'tindakan' => 'Baris lama: periksa Amandemen PRODUCT.md 2026-10-25',
					'jumlah' => $this->monitoring_scalar(
						"SELECT COUNT(*) AS jumlah FROM requests r
						WHERE r.request_status = 'Completed' AND r.completed_at IS NOT NULL
							AND EXISTS (
								SELECT 1 FROM request_events e
								WHERE e.request_id = r.request_id
									AND e.event_type IN ('request_completed','visit_completed')
							)
							AND NOT EXISTS (
								SELECT 1 FROM request_events e
								WHERE e.request_id = r.request_id
									AND e.event_type IN ('request_completed','visit_completed')
									AND ABS(TIMESTAMPDIFF(SECOND, e.created_at, r.completed_at)) <= 3600
							)"
					),
				);
			}

			if ($this->can_query_request_events()) {
				$checks[] = array(
					'kode' => 'tanpa_jejak_diterima',
					'label' => 'Diterima atau selesai tanpa jejak penerimaan',
					'sebab' => 'Tidak ada event request_accepted pada request_events, sehingga waktu respons tidak dapat dihitung.',
					'tingkat' => 'peringatan',
					'tindakan' => 'Telusuri lewat Penampil audit',
					'jumlah' => $this->monitoring_scalar(
						"SELECT COUNT(*) AS jumlah FROM requests
						WHERE request_status IN ('Accepted','Completed')
							AND NOT EXISTS (
								SELECT 1 FROM request_events e
								WHERE e.request_id = requests.request_id AND e.event_type = 'request_accepted'
							)"
					),
				);
			}
		}

		$readiness = $this->monitoring_facility_readiness();
		if ($readiness['sumber_tersedia']) {
			$checks[] = array(
				'kode' => 'fasilitas_tanpa_staf',
				'label' => 'Puskesmas aktif tanpa staf aktif',
				'sebab' => 'Fasilitas berstatus aktif, tetapi tidak punya satu pun staf aktif yang tertaut akun nakes aktif.',
				'tingkat' => 'peringatan',
				'tindakan' => 'Lengkapi staf dan akunnya di Staf Puskesmas',
				'jumlah' => $readiness['tanpa_staf'],
			);
			$checks[] = array(
				'kode' => 'staf_tanpa_akun',
				'label' => 'Staf aktif tanpa akun nakes aktif',
				'sebab' => 'Baris staf aktif belum tertaut akun, atau akunnya tidak aktif atau bukan nakes.',
				'tingkat' => 'informasi',
				'tindakan' => 'Lengkapi tautan akun di Staf Puskesmas',
				'jumlah' => $readiness['staf_tanpa_akun'],
			);
		}

		$summary = array('kritis' => 0, 'peringatan' => 0, 'informasi' => 0);
		$total = 0;
		foreach ($checks as $check) {
			if ($check['jumlah'] > 0) {
				$summary[$check['tingkat']] += $check['jumlah'];
				$total += $check['jumlah'];
			}
		}

		return array(
			'tersedia' => $tersedia,
			'checks' => $checks,
			'jumlah_kritis' => $summary['kritis'],
			'jumlah_peringatan' => $summary['peringatan'],
			'jumlah_informasi' => $summary['informasi'],
			'total_temuan' => $total,
			'ada_temuan' => ($total > 0),
			/* 0 berarti kerangka jam PHP dan sesi basis data selaras. Nilai
			   selain 0 berarti batas periode sedang digeser otomatis, dan itu
			   harus terlihat di dasbor, bukan hanya terjadi diam-diam. */
			'kerangka_jam_detik' => (int) $frame_shift,
		);
	}

	/**
	 * Apakah tabel penanda tindak lanjut tersedia.
	 *
	 * Dasbor tetap berfungsi penuh tanpa tabel ini; yang hilang hanya kemampuan
	 * menandai. Fitur yang tidak wajib tidak boleh mematikan halaman yang wajib,
	 * jadi pemeriksaan ini yang menjadi gerbang, bukan galat.
	 */
	private function monitoring_followups_available()
	{
		return $this->db->table_exists('monitoring_followups')
			&& $this->field_exists_cached('request_id', 'monitoring_followups')
			&& $this->field_exists_cached('marked_at', 'monitoring_followups');
	}

	/**
	 * Penanda tindak lanjut untuk sekumpulan permintaan.
	 *
	 * Satu kueri untuk seluruh baris yang ditampilkan, bukan satu kueri per
	 * baris. Daftar id disaring menjadi bilangan bulat positif sebelum masuk ke
	 * SQL, jadi tidak ada nilai dari luar yang disambung apa adanya.
	 */
	public function monitoring_followup_map(array $request_ids)
	{
		$map = array();
		if (!$this->monitoring_followups_available()) {
			return $map;
		}

		$ids = array();
		foreach ($request_ids as $request_id) {
			$request_id = (int) $request_id;
			if ($request_id > 0) {
				$ids[$request_id] = $request_id;
			}
		}
		if (empty($ids)) {
			return $map;
		}

		$rows = $this->db->query(
			'SELECT request_id, marked_by_user_id, note, marked_at
			 FROM monitoring_followups
			 WHERE request_id IN (' . implode(',', $ids) . ')'
		)->result();

		foreach ($rows as $row) {
			$map[(int) $row->request_id] = array(
				'oleh_user_id' => (int) $row->marked_by_user_id,
				'catatan' => (string) $row->note,
				'ditandai_pada' => (string) $row->marked_at,
				'ditandai_label' => $this->monitoring_datetime_label($row->marked_at),
			);
		}

		return $map;
	}

	/**
	 * Admin yang sedang masuk, atau 0 bila tidak dapat dipastikan.
	 *
	 * Dipastikan lewat tabel `users`, bukan hanya dari sesi: jejak audit yang
	 * mencatat pelaku yang salah lebih buruk daripada tidak ada jejak.
	 */
	private function monitoring_followup_actor_id()
	{
		$user_id = (int) $this->session->userdata('id');
		if ($user_id < 1 || !$this->db->table_exists('users')) {
			return 0;
		}

		$row = $this->db
			->select('userId')
			->where('userId', $user_id)
			->where('role', 'admin')
			->where('status', 'aktif')
			->limit(1)
			->get('users')
			->row();

		return $row ? (int) $row->userId : 0;
	}

	/**
	 * Satu baris jejak audit untuk perubahan penanda.
	 *
	 * Kegagalan menulis jejak tidak boleh menggagalkan tindakan yang sudah
	 * berhasil, jadi `db_debug` dimatikan selama penulisan - pola yang sama
	 * dengan modul admin lain.
	 */
	private function monitoring_followup_audit($action, $request_id, $actor, array $metadata)
	{
		if (!$this->db->table_exists('audit_logs')) {
			return false;
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$result = $this->db->insert('audit_logs', array(
			'actor_user_id' => $actor,
			'action' => $action,
			'entity_type' => 'request',
			'entity_id' => (string) (int) $request_id,
			'ip_address' => $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'metadata_json' => json_encode($metadata),
			'created_at' => date('Y-m-d H:i:s'),
		));
		$this->db->db_debug = $db_debug;

		return $result;
	}

	/** Memotong catatan agar tidak melebihi lebar kolom. */
	private function monitoring_followup_note($note)
	{
		$note = trim((string) $note);

		return function_exists('mb_substr') ? mb_substr($note, 0, 255) : substr($note, 0, 255);
	}

	/**
	 * Menandai satu permintaan sebagai sudah ditindaklanjuti.
	 *
	 * Menulis HANYA ke `monitoring_followups` dan `audit_logs`. Tabel `requests`
	 * dan `medicalrecords` tidak disentuh: dasbor pemantauan tidak boleh
	 * mengubah catatan klinis.
	 *
	 * Mengembalikan array berisi `ok` dan `kode`, bukan melempar, supaya
	 * pemanggil dapat menjawab klien dengan sebab yang jelas.
	 */
	public function monitoring_mark_followup($request_id, $note = '')
	{
		$request_id = (int) $request_id;
		if ($request_id < 1) {
			return array('ok' => false, 'kode' => 'request_id_tidak_sah');
		}
		if (!$this->monitoring_followups_available()) {
			return array('ok' => false, 'kode' => 'tabel_penanda_tidak_tersedia');
		}

		$actor = $this->monitoring_followup_actor_id();
		if ($actor < 1) {
			return array('ok' => false, 'kode' => 'pelaku_tidak_dikenali');
		}

		/* Permintaan harus benar-benar ada. Tanpa pemeriksaan ini, penanda dapat
		   dibuat untuk nomor sembarang dan panel pengecualian akan menampilkan
		   penanda yang tidak menunjuk apa pun. */
		if (!$this->db->table_exists('requests')) {
			return array('ok' => false, 'kode' => 'sumber_permintaan_tidak_tersedia');
		}
		$exists = $this->db
			->select('request_id')
			->where('request_id', $request_id)
			->limit(1)
			->get('requests')
			->row();
		if (!$exists) {
			return array('ok' => false, 'kode' => 'permintaan_tidak_ditemukan');
		}

		$note = $this->monitoring_followup_note($note);
		$now = date('Y-m-d H:i:s');

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->query(
			'INSERT INTO monitoring_followups (request_id, marked_by_user_id, note, marked_at)
			 VALUES (?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE marked_by_user_id = VALUES(marked_by_user_id),
			                         note = VALUES(note),
			                         marked_at = VALUES(marked_at)',
			array($request_id, $actor, $note === '' ? null : $note, $now)
		);
		$error = $this->db->error();
		$this->db->db_debug = $db_debug;

		if (!empty($error['code'])) {
			return array('ok' => false, 'kode' => 'penulisan_penanda_gagal');
		}

		$this->monitoring_followup_audit('monitoring_followup_marked', $request_id, $actor, array(
			'request_id' => $request_id,
			'catatan' => $note,
		));

		return array(
			'ok' => true,
			'kode' => 'ok',
			'request_id' => $request_id,
			'ditandai_pada' => $now,
			'ditandai_label' => $this->monitoring_datetime_label($now),
			'oleh_user_id' => $actor,
		);
	}

	/**
	 * Mencabut penanda tindak lanjut.
	 *
	 * Ada supaya penandaan yang keliru dapat dibatalkan. Riwayatnya tetap
	 * tersimpan di `audit_logs`; yang hilang hanya keadaan sekarang.
	 */
	public function monitoring_unmark_followup($request_id)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1) {
			return array('ok' => false, 'kode' => 'request_id_tidak_sah');
		}
		if (!$this->monitoring_followups_available()) {
			return array('ok' => false, 'kode' => 'tabel_penanda_tidak_tersedia');
		}

		$actor = $this->monitoring_followup_actor_id();
		if ($actor < 1) {
			return array('ok' => false, 'kode' => 'pelaku_tidak_dikenali');
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->where('request_id', $request_id)->delete('monitoring_followups');
		$error = $this->db->error();
		$terhapus = $this->db->affected_rows();
		$this->db->db_debug = $db_debug;

		if (!empty($error['code'])) {
			return array('ok' => false, 'kode' => 'pencabutan_penanda_gagal');
		}

		if ($terhapus > 0) {
			$this->monitoring_followup_audit('monitoring_followup_cleared', $request_id, $actor, array(
				'request_id' => $request_id,
			));
		}

		return array('ok' => true, 'kode' => 'ok', 'request_id' => $request_id);
	}

	/**
	 * Irisan tipis untuk pembaruan berkala dasbor.
	 *
	 * Sengaja hanya memuat nilai yang cepat berubah dan murah dihitung. Agregat
	 * periode - tren, perbandingan fasilitas, mutu data - TIDAK ikut di sini:
	 * angka itu berubah lambat, dan mengirimnya setiap 30 detik hanya menambah
	 * beban tanpa menambah keputusan.
	 *
	 * `sidik` adalah sidik jari keadaan. Klien membandingkannya lebih dulu, jadi
	 * halaman tidak disentuh sama sekali bila tidak ada yang berubah - tidak ada
	 * kedip, tidak ada gerak, tidak ada pekerjaan sia-sia.
	 *
	 * Sifat: BACA SAJA, dan hanya menghitung.
	 */
	public function monitoring_live_slice($filter = '')
	{
		$slice = array(
			'sumber_tersedia' => false,
			'sidik' => '',
			'jam_server' => date('H:i:s'),
			'beban' => array(
				'pending' => 0, 'accepted' => 0, 'aktif' => 0,
				'kumulatif_selesai' => 0, 'kumulatif_batal' => 0, 'kumulatif_masuk' => 0,
			),
			'eksepsi' => array('kritis' => 0, 'peringatan' => 0, 'informasi' => 0, 'total' => 0),
			'kesegaran' => array('label' => '-', 'menit_lalu' => null),
		);

		if (!$this->db->table_exists('requests')) {
			return $slice;
		}
		$slice['sumber_tersedia'] = true;

		$outstanding = $this->monitoring_outstanding($filter);
		$slice['beban'] = array(
			'pending' => (int) $outstanding['pending'],
			'accepted' => (int) $outstanding['accepted'],
			'aktif' => (int) $outstanding['aktif'],
			'kumulatif_selesai' => (int) $outstanding['kumulatif_selesai'],
			'kumulatif_batal' => (int) $outstanding['kumulatif_batal'],
			'kumulatif_masuk' => (int) $outstanding['kumulatif_masuk'],
		);

		/* Aturan eskalasi dipakai ulang dari monitoring_exceptions supaya ambang
		   dan tingkatnya hanya punya satu definisi. Barisnya tidak diminta:
		   yang diperlukan di sini hanya angkanya, dan daftar baris berarti
		   pengurutan atas seluruh pengecualian yang tidak dibaca siapa pun. */
		$exceptions = $this->monitoring_exceptions(0, $filter, false);
		$slice['eksepsi'] = array(
			'kritis' => (int) $exceptions['jumlah_kritis'],
			'peringatan' => (int) $exceptions['jumlah_peringatan'],
			'informasi' => (int) $exceptions['jumlah_informasi'],
			'total' => (int) $exceptions['total'],
		);

		$freshness = $this->monitoring_freshness();
		$slice['kesegaran'] = array(
			'label' => (string) $freshness['label'],
			'menit_lalu' => $freshness['menit_lalu'] === null ? null : (int) $freshness['menit_lalu'],
		);

		$slice['sidik'] = substr(sha1(implode('|', array(
			trim((string) $filter),
			$slice['beban']['pending'],
			$slice['beban']['accepted'],
			$slice['beban']['aktif'],
			$slice['beban']['kumulatif_selesai'],
			$slice['beban']['kumulatif_batal'],
			$slice['beban']['kumulatif_masuk'],
			$slice['eksepsi']['kritis'],
			$slice['eksepsi']['peringatan'],
			$slice['eksepsi']['informasi'],
			(string) $slice['kesegaran']['label'],
		))), 0, 12);

		return $slice;
	}


	// MARKER_MONITORING_END
}
