<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Notifikasi_admin_m extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->db = $this->load->database('default', TRUE);
	}

	public function current_admin_user_id()
	{
		if (!$this->db->table_exists('users')) {
			return 0;
		}

		$email = trim((string) $this->session->userdata('email'));
		if ($email === '' || !$this->db->field_exists('email', 'users') || !$this->db->field_exists('userId', 'users')) {
			return 0;
		}

		$this->db->select('userId');
		$this->db->from('users');
		$this->db->where('email', $email);
		if ($this->db->field_exists('role', 'users')) {
			$this->db->where('role', 'admin');
		}
		if ($this->db->field_exists('status', 'users')) {
			$this->db->where('status', 'aktif');
		}

		$row = $this->db->get()->row();
		return $row && isset($row->userId) ? (int) $row->userId : 0;
	}

	public function count_unread_events($user_id)
	{
		if (!$this->request_events_ready() || !$this->read_table_ready()) {
			return 0;
		}

		$last_seen_event_id = $this->last_seen_event_id($user_id);

		return (int) $this->with_db_debug_disabled(function () use ($last_seen_event_id) {
			return $this->db
				->where('event_id >', (int) $last_seen_event_id)
				->count_all_results('request_events');
		}, 0);
	}

	public function latest_events($limit = 10)
	{
		if (!$this->request_events_ready()) {
			return array();
		}

		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = 10;
		}
		if ($limit > 10) {
			$limit = 10;
		}

		$select = array(
			'request_events.event_id',
			'request_events.request_id',
			'request_events.event_type',
			'request_events.created_at',
		);
		$join_requests = $this->db->table_exists('requests') && $this->db->field_exists('request_id', 'requests');
		$join_puskesmas = $join_requests
			&& $this->db->table_exists('m_puskesmas')
			&& $this->db->field_exists('kode_pkm', 'm_puskesmas')
			&& $this->db->field_exists('nama_puskesmas', 'm_puskesmas');
		$routed_puskesmas_code_expr = 'NULL';

		if ($join_requests) {
			$has_assigned_puskesmas_code = $this->db->field_exists('assigned_puskesmas_code', 'requests');
			$has_assigned_puskesmas_name = $this->db->field_exists('assigned_puskesmas_name', 'requests');
			$assigned_puskesmas_name_expr = $has_assigned_puskesmas_name ? "NULLIF(TRIM(requests.assigned_puskesmas_name), '')" : 'NULL';
			$assigned_puskesmas_code_expr = $has_assigned_puskesmas_code ? "NULLIF(TRIM(requests.assigned_puskesmas_code), '')" : 'NULL';
			$routed_puskesmas_code_expr = "CASE WHEN UPPER($assigned_puskesmas_code_expr) = 'DEFAULT' THEN NULL ELSE $assigned_puskesmas_code_expr END";
			$assigned_puskesmas_name_clean_expr = "CASE WHEN UPPER(COALESCE($assigned_puskesmas_name_expr, '')) IN ('DEFAULT', 'PUSKESMAS DEFAULT') THEN NULL ELSE $assigned_puskesmas_name_expr END";
			$select[] = $join_puskesmas
				? "COALESCE($assigned_puskesmas_name_clean_expr, assigned_puskesmas.nama_puskesmas, $routed_puskesmas_code_expr, '') AS puskesmas"
				: "COALESCE($assigned_puskesmas_name_clean_expr, $routed_puskesmas_code_expr, '') AS puskesmas";
		} else {
			$select[] = "'' AS puskesmas";
		}

		$rows = $this->with_db_debug_disabled(function () use ($select, $limit, $join_requests, $join_puskesmas, $routed_puskesmas_code_expr) {
			$this->db->select(implode(', ', $select), FALSE);
			$this->db->from('request_events');
			if ($join_requests) {
				$this->db->join('requests', 'requests.request_id = request_events.request_id', 'left');
			}
			if ($join_puskesmas) {
				$this->db->join('m_puskesmas assigned_puskesmas', "assigned_puskesmas.kode_pkm = $routed_puskesmas_code_expr", 'left', FALSE);
			}
			return $this->db
				->order_by('request_events.event_id', 'DESC')
				->limit($limit)
				->get()
				->result();
		}, array());

		$events = array();
		foreach ($rows as $row) {
			$events[] = array(
				'event_id' => isset($row->event_id) ? (int) $row->event_id : 0,
				'request_id' => isset($row->request_id) ? (int) $row->request_id : 0,
				'event_type' => isset($row->event_type) ? (string) $row->event_type : '',
				'title' => $this->event_title(isset($row->event_type) ? (string) $row->event_type : ''),
				'message' => $this->event_message(isset($row->event_type) ? (string) $row->event_type : ''),
				'puskesmas' => isset($row->puskesmas) ? trim((string) $row->puskesmas) : '',
				'created_at' => isset($row->created_at) ? (string) $row->created_at : '',
				'created_at_label' => $this->format_datetime(isset($row->created_at) ? (string) $row->created_at : ''),
			);
		}

		return $events;
	}

	public function mark_all_read($user_id)
	{
		$max_event_id = $this->max_event_id();
		if (!$this->read_table_ready()) {
			return $max_event_id;
		}

		$user_id = (int) $user_id;
		if ($user_id < 1) {
			return $max_event_id;
		}

		$now = date('Y-m-d H:i:s');
		$sql = "INSERT INTO admin_notification_reads (user_id, last_seen_event_id, last_seen_at, created_at, updated_at)
				VALUES (?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE last_seen_event_id = VALUES(last_seen_event_id), last_seen_at = VALUES(last_seen_at), updated_at = VALUES(updated_at)";

		$this->with_db_debug_disabled(function () use ($sql, $user_id, $max_event_id, $now) {
			return $this->db->query($sql, array($user_id, $max_event_id, $now, $now, $now));
		}, false);

		return $max_event_id;
	}

	private function last_seen_event_id($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->read_table_ready()) {
			return 0;
		}

		$row = $this->with_db_debug_disabled(function () use ($user_id) {
			return $this->db
				->select('last_seen_event_id')
				->from('admin_notification_reads')
				->where('user_id', $user_id)
				->limit(1)
				->get()
				->row();
		}, null);

		return $row && isset($row->last_seen_event_id) ? (int) $row->last_seen_event_id : 0;
	}

	private function max_event_id()
	{
		if (!$this->request_events_ready()) {
			return 0;
		}

		$row = $this->with_db_debug_disabled(function () {
			return $this->db
				->select_max('event_id', 'max_event_id')
				->get('request_events')
				->row();
		}, null);

		return $row && isset($row->max_event_id) ? (int) $row->max_event_id : 0;
	}

	private function request_events_ready()
	{
		if (!$this->db->table_exists('request_events')) {
			return false;
		}

		foreach (array('event_id', 'request_id', 'event_type', 'created_at') as $field) {
			if (!$this->db->field_exists($field, 'request_events')) {
				return false;
			}
		}

		return true;
	}

	private function read_table_ready()
	{
		if (!$this->db->table_exists('admin_notification_reads')) {
			return false;
		}

		foreach (array('user_id', 'last_seen_event_id', 'last_seen_at') as $field) {
			if (!$this->db->field_exists($field, 'admin_notification_reads')) {
				return false;
			}
		}

		return true;
	}

	private function event_title($event_type)
	{
		$labels = array(
			'request_created' => 'Konsultasi baru dibuat',
			'request_accepted' => 'Konsultasi diterima',
			'request_cancelled' => 'Konsultasi dibatalkan',
			'visit_started' => 'Kunjungan dimulai',
			'visit_arrived' => 'Nakes tiba di lokasi',
			'visit_in_service' => 'Layanan sedang berjalan',
			'visit_completed' => 'Kunjungan selesai',
			'request_completed' => 'Konsultasi selesai',
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
			return 'Aktivitas kunjungan nakes diperbarui.';
		}

		return 'Status konsultasi diperbarui.';
	}

	private function format_datetime($value)
	{
		$timestamp = strtotime($value);
		if (!$timestamp) {
			return '';
		}

		return date('d M Y H:i', $timestamp);
	}

	private function with_db_debug_disabled($callback, $fallback)
	{
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		try {
			$result = call_user_func($callback);
		} catch (Throwable $e) {
			$result = $fallback;
		}
		$this->db->db_debug = $db_debug;

		return $result === null ? $fallback : $result;
	}
}
