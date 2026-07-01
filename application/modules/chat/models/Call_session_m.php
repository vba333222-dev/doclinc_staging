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
		$request_id = (int) $request_id;
		if ($request_id < 1 || !$this->table_ready()) {
			return null;
		}

		return $this->db
			->where('request_id', $request_id)
			->where_in('status', $this->active_statuses())
			->order_by('call_id', 'DESC')
			->limit(1)
			->get(self::TABLE)
			->row();
	}

	public function get_latest_incoming_for_warga($user_id, $request_id = 0)
	{
		$user_id = (int) $user_id;
		$request_id = (int) $request_id;
		if ($user_id < 1 || !$this->table_ready()) {
			return null;
		}

		$this->db
			->select('call_sessions.*, requests.request_status, requests.user_id, users.nama AS caller_nama, users.name AS caller_name')
			->from(self::TABLE)
			->join('requests', 'requests.request_id = call_sessions.request_id', 'inner')
			->join('users', 'users.userId = call_sessions.caller_user_id', 'left')
			->where('call_sessions.callee_user_id', $user_id)
			->where('requests.user_id', $user_id)
			->where('requests.request_status', 'Accepted')
			->where_in('call_sessions.status', $this->active_statuses());

		if ($request_id > 0) {
			$this->db->where('call_sessions.request_id', $request_id);
		}

		return $this->db
			->order_by('call_sessions.call_id', 'DESC')
			->limit(1)
			->get()
			->row();
	}

	public function start_or_reuse($request, $caller_user_id, $call_type)
	{
		if (!$request || !$this->table_ready()) {
			return null;
		}

		$request_id = (int) $request->request_id;
		$existing = $this->get_active_by_request($request_id);
		if ($existing) {
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
		return $call_id > 0 ? $this->get_by_id($call_id) : null;
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

	public function format_call($call)
	{
		if (!$call) {
			return null;
		}

		$caller_name = '';
		foreach (array('caller_nama', 'caller_name') as $field) {
			if (isset($call->{$field}) && trim((string) $call->{$field}) !== '') {
				$caller_name = trim((string) $call->{$field});
				break;
			}
		}

		return array(
			'call_id' => (int) $call->call_id,
			'request_id' => (int) $call->request_id,
			'call_type' => $call->call_type,
			'room' => $call->room_name,
			'status' => $call->status,
			'caller_name' => $caller_name !== '' ? $caller_name : 'Nakes Doclinc',
		);
	}
}
