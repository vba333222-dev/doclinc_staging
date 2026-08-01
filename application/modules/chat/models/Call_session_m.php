<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Call_session_m extends CI_Model
{
	const TABLE = 'call_sessions';

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('request_authz');
		$this->load->helper('livekit');
	}

	public function table_ready()
	{
		return $this->db->table_exists(self::TABLE);
	}

	public function active_statuses()
	{
		return array('ringing', 'answered');
	}

	public function terminal_statuses()
	{
		return array('ended', 'rejected', 'missed', 'failed');
	}

	public function ring_timeout_seconds()
	{
		return $this->config_int('CALL_RING_TIMEOUT_SECONDS', 'call_ring_timeout_seconds', 60, 5, 3600);
	}

	public function status_poll_seconds()
	{
		return $this->config_int('CALL_STATUS_POLL_SECONDS', 'call_status_poll_seconds', 3, 1, 60);
	}

	public function stale_cleanup_limit()
	{
		return $this->config_int('CALL_STALE_CLEANUP_LIMIT', 'call_stale_cleanup_limit', 20, 1, 200);
	}

	public function get_by_id($call_id)
	{
		$call_id = (int) $call_id;
		if ($call_id < 1 || !$this->table_ready()) {
			return null;
		}

		return $this->db
			->where('call_id', $call_id)
			->get(self::TABLE)
			->row();
	}

	public function get_active_by_request($request_id)
	{
		return $this->get_active_call_for_request($request_id);
	}

	public function get_active_call_for_request($request_id)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1 || !$this->table_ready()) {
			return null;
		}

		$call = $this->db
			->where('request_id', $request_id)
			->where_in('status', $this->active_statuses())
			->order_by('call_id', 'DESC')
			->limit(1)
			->get(self::TABLE)
			->row();

		if ($call && $this->is_call_expired($call)) {
			$this->mark_missed((int) $call->call_id);
			return null;
		}

		return $call;
	}

	public function get_latest_by_request($request_id)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1 || !$this->table_ready()) {
			return null;
		}

		return $this->db
			->where('request_id', $request_id)
			->order_by('call_id', 'DESC')
			->limit(1)
			->get(self::TABLE)
			->row();
	}

	public function get_latest_visible_call_for_callee($user_id, $request_id = 0)
	{
		$user_id = (int) $user_id;
		$request_id = (int) $request_id;
		if ($user_id < 1 || !$this->table_ready()) {
			return null;
		}

		$caller_name_select = "'' AS caller_name";
		if ($this->db->field_exists('nama', 'users')) {
			$caller_name_select = 'users.nama AS caller_name';
		} elseif ($this->db->field_exists('name', 'users')) {
			$caller_name_select = 'users.name AS caller_name';
		}

		$this->db
			->select('call_sessions.*, requests.request_status, requests.user_id, ' . $caller_name_select, false)
			->from(self::TABLE)
			->join('requests', 'requests.request_id = call_sessions.request_id', 'inner')
			->join('users', 'users.userId = call_sessions.caller_user_id', 'left')
			->where('call_sessions.callee_user_id', $user_id)
			->where('requests.user_id', $user_id)
			->where('requests.request_status', 'Accepted')
			->where('call_sessions.status', 'ringing');

		if ($request_id > 0) {
			$this->db->where('call_sessions.request_id', $request_id);
		}

		$call = $this->db
			->order_by('call_sessions.call_id', 'DESC')
			->limit(1)
			->get()
			->row();

		if ($call && $this->is_call_expired($call)) {
			$this->mark_missed((int) $call->call_id);
			return null;
		}

		return $call;
	}

	public function get_latest_incoming_for_warga($user_id, $request_id = 0)
	{
		return $this->get_latest_visible_call_for_callee($user_id, $request_id);
	}

	public function start_or_reuse($request, $caller_user_id, $call_type)
	{
		if (!$request || !$this->table_ready()) {
			return null;
		}

		$request_id = (int) $request->request_id;
		$this->expire_stale_ringing_calls($this->stale_cleanup_limit());
		$existing = $this->get_active_call_for_request($request_id);
		if ($existing) {
			$existing->was_created = false;
			return $existing;
		}

		$call_type = $call_type === 'audio' ? 'audio' : 'video';
		$now = date('Y-m-d H:i:s');
		$data = array(
			'request_id' => $request_id,
			'room_name' => doclinc_livekit_room_name($request_id),
			'call_type' => $call_type,
			'caller_user_id' => (int) $caller_user_id,
			'callee_user_id' => (int) $request->user_id,
			'status' => 'ringing',
			'started_at' => $now,
			'created_at' => $now,
		);

		$this->db->insert(self::TABLE, $data);
		$call_id = (int) $this->db->insert_id();
		$created = $call_id > 0 ? $this->get_by_id($call_id) : null;
		if ($created) {
			$created->was_created = true;
		}
		return $created;
	}

	public function expire_stale_ringing_calls($limit = 20)
	{
		if (!$this->table_ready()) {
			return 0;
		}

		$limit = (int) $limit;
		if ($limit < 1) {
			$limit = $this->stale_cleanup_limit();
		}

		$threshold = date('Y-m-d H:i:s', time() - $this->ring_timeout_seconds());
		$calls = $this->db
			->select('call_id')
			->where('status', 'ringing')
			->group_start()
				->group_start()
					->where('started_at IS NOT NULL', null, false)
					->where('started_at <', $threshold)
				->group_end()
				->or_group_start()
					->where('started_at IS NULL', null, false)
					->where('created_at <', $threshold)
				->group_end()
			->group_end()
			->order_by('call_id', 'ASC')
			->limit($limit)
			->get(self::TABLE)
			->result();

		if (empty($calls)) {
			return 0;
		}

		$ids = array();
		foreach ($calls as $call) {
			$ids[] = (int) $call->call_id;
		}

		$now = date('Y-m-d H:i:s');
		$this->db
			->where_in('call_id', $ids)
			->where('status', 'ringing')
			->update(self::TABLE, array(
				'status' => 'missed',
				'ended_at' => $now,
				'updated_at' => $now,
			));

		return $this->db->affected_rows();
	}

	public function is_call_expired($call)
	{
		if (!$call || !isset($call->status) || $call->status !== 'ringing') {
			return false;
		}

		$started_at = isset($call->started_at) ? strtotime((string) $call->started_at) : false;
		if (!$started_at) {
			$started_at = isset($call->created_at) ? strtotime((string) $call->created_at) : false;
		}
		if (!$started_at) {
			return false;
		}

		return (time() - $started_at) > $this->ring_timeout_seconds();
	}

	public function mark_missed($call_id)
	{
		if (!$this->table_ready()) {
			return false;
		}

		$now = date('Y-m-d H:i:s');
		$this->db
			->where('call_id', (int) $call_id)
			->where('status', 'ringing')
			->update(self::TABLE, array(
				'status' => 'missed',
				'ended_at' => $now,
				'updated_at' => $now,
			));

		return $this->db->affected_rows() > 0;
	}

	public function answer($call_id)
	{
		$call = $this->get_by_id($call_id);
		if (!$call || !in_array($call->status, array('ringing', 'answered'), true)) {
			return $call;
		}

		if ($call->status === 'answered') {
			return $call;
		}

		$now = date('Y-m-d H:i:s');
		$this->db
			->where('call_id', (int) $call_id)
			->where('status', 'ringing')
			->update(self::TABLE, array(
				'status' => 'answered',
				'answered_at' => $now,
				'updated_at' => $now,
			));

		return $this->get_by_id($call_id);
	}

	public function reject($call_id)
	{
		if (!$this->table_ready()) {
			return false;
		}

		$now = date('Y-m-d H:i:s');
		$this->db
			->where('call_id', (int) $call_id)
			->where('status', 'ringing')
			->update(self::TABLE, array(
				'status' => 'rejected',
				'ended_at' => $now,
				'updated_at' => $now,
			));

		return $this->db->affected_rows() > 0;
	}

	public function end_call($call_id = 0, $request_id = 0)
	{
		if (!$this->table_ready()) {
			return false;
		}

		$call_id = (int) $call_id;
		$request_id = (int) $request_id;
		if ($call_id < 1 && $request_id < 1) {
			return false;
		}

		$now = date('Y-m-d H:i:s');
		if ($call_id > 0) {
			$this->db->where('call_id', $call_id);
		} else {
			$this->db->where('request_id', $request_id);
		}

		$this->db
			->where_in('status', $this->active_statuses())
			->update(self::TABLE, array(
				'status' => 'ended',
				'ended_at' => $now,
				'updated_at' => $now,
			));

		return $this->db->affected_rows() > 0;
	}

	public function status_payload($call, $message = 'Status panggilan', $expired = false)
	{
		if (!$call) {
			return array(
				'success' => false,
				'message' => 'Panggilan tidak tersedia',
				'ended' => true,
				'expired' => false,
			);
		}

		$status = isset($call->status) ? (string) $call->status : '';
		return array(
			'success' => true,
			'call_id' => (int) $call->call_id,
			'request_id' => (int) $call->request_id,
			'status' => $status,
			'call_type' => isset($call->call_type) ? (string) $call->call_type : 'video',
			'message' => $message,
			'ended' => in_array($status, $this->terminal_statuses(), true),
			'expired' => (bool) $expired,
		);
	}

	public function format_call($call)
	{
		if (!$call) {
			return null;
		}

		$caller_name = '';
		if (isset($call->caller_name) && trim((string) $call->caller_name) !== '') {
			$caller_name = trim((string) $call->caller_name);
		}

		return array(
			'call_id' => (int) $call->call_id,
			'request_id' => (int) $call->request_id,
			'call_type' => $call->call_type,
			'room' => $call->room_name,
			'status' => $call->status,
			'caller_name' => $caller_name !== '' ? $caller_name : 'Nakes DocLink',
		);
	}

	private function config_int($env_key, $config_key, $default, $min, $max)
	{
		$value = getenv($env_key);
		if ($value === false || $value === '') {
			$value = $this->config->item($config_key);
		}
		if (!is_numeric($value)) {
			return (int) $default;
		}

		$value = (int) $value;
		if ($value < $min) {
			return (int) $min;
		}
		if ($value > $max) {
			return (int) $max;
		}

		return $value;
	}
}
