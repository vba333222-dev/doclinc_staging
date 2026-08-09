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
		$this->prepare_json_response();
		if ($this->input->method(TRUE) !== 'GET') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan')));
			return;
		}
		if ($this->session->userdata('logged_in') != TRUE) {
			$this->output
				->set_status_header(401)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Silakan masuk terlebih dahulu.')));
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

	public function snapshot()
	{
		$this->prepare_json_response();
		require_once APPPATH . 'libraries/Notification_realtime_policy.php';
		if ($this->input->method(TRUE) !== 'GET') {
			$this->snapshot_error(405, 'method_not_allowed');
			return;
		}
		if ($this->config->item('realtime_notifications_enabled') !== true) {
			$this->snapshot_error(404, 'feature_disabled');
			return;
		}
		if ($this->session->userdata('logged_in') != TRUE) {
			$this->snapshot_error(401, 'authentication_required');
			return;
		}
		$user_id = (int) $this->session->userdata('id');
		$session_role = (string) $this->session->userdata('role');
		if ($user_id < 1 || !in_array($session_role, array('warga', 'dokter'), true)
			|| !$this->db->field_exists('must_change_password', 'users')
			|| !doclinc_nakes_credential_schema_allows_runtime($this->db)) {
			$this->snapshot_error(403, 'access_denied');
			return;
		}
		$user = $this->db->select(
			'userId, role, status, must_change_password, ' . doclinc_nakes_password_changed_at_projection($this->db),
			false
		)
			->where('userId', $user_id)->limit(1)->get('users')->row();
		if (!$user || (string) $user->role !== $session_role || (string) $user->status !== 'aktif') {
			$this->snapshot_error(403, 'access_denied');
			return;
		}
		$must_change_password = doclinc_nakes_password_change_blocked($user->role, $user->must_change_password, $user->password_changed_at);
		if ($must_change_password) {
			$this->snapshot_error(403, 'password_change_required');
			return;
		}
		$user_context = doclinc_notification_user_context($user_id);
		$policy = new Notification_realtime_policy();
		if (!$policy->actorAllowed(array(
			'authenticated' => true,
			'user_id' => $user_id,
			'role' => $session_role,
			'status' => (string) $user->status,
			'must_change_password' => $must_change_password,
			'identity' => isset($user_context['identity']) ? $user_context['identity'] : null,
		))) {
			$this->snapshot_error(403, 'access_denied');
			return;
		}
		$limit = (int) $this->input->get('limit', TRUE);
		$limit = $limit > 0 ? min($limit, 50) : 20;
		$notifications = doclinc_get_unread_notifications($user_id, $limit);
		foreach ($notifications as &$notification) {
			$notification['action_url'] = $this->notification_action_url($notification, $user_id, $session_role, $user_context);
			foreach (array('recipient_user_id', 'recipient_role', 'recipient_puskesmas_code', 'actor_user_id', 'read_at') as $internal_field) {
				unset($notification[$internal_field]);
			}
		}
		unset($notification);
		$this->output->set_output(json_encode(array(
			'success' => true,
			'data' => array(
				'unread_count' => doclinc_count_unread_notifications($user_id),
				'notifications' => $notifications,
				'limit' => $limit,
			),
		), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
	}

	public function mark_read()
	{
		$this->prepare_json_response();
		if ($this->session->userdata('logged_in') != TRUE) {
			$this->output
				->set_status_header(401)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Silakan masuk terlebih dahulu.')));
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
				->set_output(json_encode(array('status' => 'error', 'message' => 'Anda tidak memiliki akses.')));
			return;
		}

		$this->output->set_output(json_encode(array('status' => 'success')));
	}

	private function prepare_json_response()
	{
		$this->output->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: no-store, private, max-age=0')
			->set_header('Pragma: no-cache')
			->set_header('X-Content-Type-Options: nosniff');
	}

	private function snapshot_error($status, $code)
	{
		$this->output->set_status_header((int) $status)->set_output(json_encode(array(
			'success' => false,
			'error' => (string) $code,
		)));
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
