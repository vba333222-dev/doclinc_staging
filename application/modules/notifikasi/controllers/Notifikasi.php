<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Notifikasi extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('notification');
		$this->load->helper('request_authz');
	}

	public function index()
	{
		$this->list_json();
	}

	public function list_json()
	{
		$this->output->set_content_type('application/json');
		if ($this->session->userdata('logged_in') != TRUE) {
			$this->output
				->set_status_header(401)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Login diperlukan')));
			return;
		}

		$user_id = (int) $this->session->userdata('id');
		$user_context = doclinc_notification_user_context($user_id);
		$role = !empty($user_context['role']) ? $user_context['role'] : ($this->session->userdata('role') ?: '');
		$limit = (int) $this->input->get('limit', TRUE);
		$limit = $limit > 0 ? $limit : 20;
		$notifications = doclinc_get_unread_notifications($user_id, $limit);
		foreach ($notifications as &$notification) {
			$notification['action_url'] = $this->notification_action_url($notification, $user_id, $role, $user_context);
		}
		unset($notification);

		$this->output->set_output(json_encode(array(
			'status' => 'success',
			'unread_count' => doclinc_count_unread_notifications($user_id),
			'notifications' => $notifications,
		)));
	}

	public function mark_read()
	{
		$this->output->set_content_type('application/json');
		if ($this->session->userdata('logged_in') != TRUE) {
			$this->output
				->set_status_header(401)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Login diperlukan')));
			return;
		}

		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan')));
			return;
		}

		$notification_id = (int) $this->input->post('notification_id');
		$user_id = (int) $this->session->userdata('id');
		if (!doclinc_mark_notification_read($notification_id, $user_id)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Akses tidak diizinkan')));
			return;
		}

		$this->output->set_output(json_encode(array('status' => 'success')));
	}

	private function notification_action_url($notification, $user_id, $role, $user_context = null)
	{
		$fallback = base_url('notifikasi');
		if (empty($notification['entity_type']) || $notification['entity_type'] !== 'request') {
			return $fallback;
		}

		$request_id = (int) (isset($notification['entity_id']) ? $notification['entity_id'] : 0);
		if ($request_id < 1) {
			return $fallback;
		}
		$identity_context = is_array($user_context) && isset($user_context['identity'])
			? $user_context['identity']
			: null;
		if ($role === 'dokter') {
			if (!doclinc_can_view_request_notification($request_id, $identity_context)) {
				return $fallback;
			}
		} elseif (!doclinc_can_view_request($request_id, $user_id, $role)) {
			return $fallback;
		}

		$request = $this->db
			->where('request_id', $request_id)
			->get('requests')
			->row();
		if (!$request) {
			return $fallback;
		}

		if ($role === 'dokter') {
			$is_personal = is_array($identity_context)
				&& !empty($identity_context['valid'])
				&& $identity_context['account_type'] === 'personal';
			$is_handler = $is_personal
				|| (string) $request->dokter_id === (string) $user_id
				|| (isset($request->accepted_by_user_id) && (string) $request->accepted_by_user_id === (string) $user_id)
				|| (isset($request->assigned_nakes_user_id) && (string) $request->assigned_nakes_user_id === (string) $user_id);
			if (!$is_personal && $request->request_status === 'Pending') {
				return base_url('home_nakes?highlight_request_id=' . $request_id) . '#req_konsul';
			}
			if ($is_handler && $request->request_status === 'Accepted') {
				if (isset($notification['event_type']) && $notification['event_type'] === 'chat_message') {
					return base_url('chat?request_id=' . $request_id);
				}
				return base_url('konsultasi_nakes/konsultasi/' . $request_id) . '?kriteria=1';
			}
			if ($is_handler && in_array($request->request_status, array('Completed', 'Cancelled'), true)) {
				return base_url('chat?request_id=' . $request_id);
			}
			return $fallback;
		}

		if ($role === 'warga' && (string) $request->user_id === (string) $user_id) {
			if (in_array($request->request_status, array('Accepted', 'Completed', 'Cancelled'), true)) {
				return base_url('chat?request_id=' . $request_id);
			}
			return base_url('home?highlight_request_id=' . $request_id) . '#riwayat';
		}

		return $fallback;
	}
}
