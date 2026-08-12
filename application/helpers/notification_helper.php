<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_notifications_ready')) {
	function doclinc_notifications_ready()
	{
		$CI = &get_instance();
		return $CI->db->table_exists('notifications');
	}
}

if (!function_exists('doclinc_notification_user_context')) {
	function doclinc_notification_user_context($user_id)
	{
		static $context_cache = array();
		$user_id = (int) $user_id;
		if (array_key_exists($user_id, $context_cache)) {
			return $context_cache[$user_id];
		}
		$CI = &get_instance();
		$user = $user_id > 0 ? $CI->db
			->select('userId, role')
			->where('userId', $user_id)
			->limit(1)
			->get('users')
			->row() : null;
		if (!$user) {
			$context_cache[$user_id] = array('user_id' => 0, 'role' => '', 'identity' => null);
			return $context_cache[$user_id];
		}

		$identity = null;
		if ((string) $user->role === 'dokter') {
			$CI->load->helper('request_authz');
			$identity = doclinc_dokter_identity_context($user_id);
		}
		$context_cache[$user_id] = array('user_id' => (int) $user->userId, 'role' => (string) $user->role, 'identity' => $identity);
		return $context_cache[$user_id];
	}
}

if (!function_exists('doclinc_apply_notification_visibility')) {
	function doclinc_apply_notification_visibility($db, $user_context, $notification_alias = 'notifications')
	{
		if (!is_array($user_context) || !isset($user_context['role'])) {
			return;
		}
		if ($user_context['role'] === 'warga') {
			$user_id = isset($user_context['user_id']) ? (int) $user_context['user_id'] : 0;
			$non_operational = "({$notification_alias}.entity_type <> 'request')";
			$request_owner = "({$notification_alias}.entity_type = 'request' AND notification_request.user_id = {$user_id})";
			$db->where("({$non_operational} OR {$request_owner})", null, false);
			return;
		}
		if ($user_context['role'] !== 'dokter') {
			return;
		}

		$identity = isset($user_context['identity']) && is_array($user_context['identity'])
			? $user_context['identity']
			: array();
		$non_operational = "({$notification_alias}.entity_type <> 'request' AND ({$notification_alias}.recipient_puskesmas_code IS NULL OR TRIM({$notification_alias}.recipient_puskesmas_code) = ''))";
		if (empty($identity['valid'])) {
			$db->where($non_operational, null, false);
			return;
		}

		$user_id = (int) $identity['user_id'];
		$puskesmas_code = trim((string) $identity['puskesmas_code']);
		$request_tenant = '(TRIM(notification_request.assigned_puskesmas_code) = ' . $db->escape($puskesmas_code) . ')';
		if ($identity['account_type'] === 'command_center') {
			$legacy_tenant = "((notification_request.assigned_puskesmas_code IS NULL OR TRIM(notification_request.assigned_puskesmas_code) = '') AND notification_request.dokter_id = {$user_id})";
			$request_visibility = "({$notification_alias}.entity_type = 'request' AND ({$request_tenant} OR {$legacy_tenant}))";
			$puskesmas_broadcast = "({$notification_alias}.entity_type <> 'request' AND TRIM({$notification_alias}.recipient_puskesmas_code) = " . $db->escape($puskesmas_code) . ')';
			$db->where("({$non_operational} OR {$request_visibility} OR {$puskesmas_broadcast})", null, false);
			return;
		}

		if ($identity['account_type'] !== 'personal' || empty($identity['staff_id'])) {
			$db->where($non_operational, null, false);
			return;
		}

		$CI = &get_instance();
		if ($CI->config->item('care_team_workflow_enabled') === true) {
			if (!$db->field_exists('responsible_doctor_user_id', 'requests')
				|| !$db->field_exists('visit_performer_user_id', 'requests')) {
				$db->where($non_operational, null, false);
				return;
			}
			$canonical_owner = "(notification_request.responsible_doctor_user_id = {$user_id} OR notification_request.visit_performer_user_id = {$user_id})";
			$request_state = "notification_request.request_status IN ('Accepted','Completed','Cancelled')";
			$request_visibility = "({$notification_alias}.entity_type = 'request' AND {$request_tenant} AND {$request_state} AND {$canonical_owner})";
			$db->where("({$non_operational} OR {$request_visibility})", null, false);
			return;
		}

		$staff_id = (int) $identity['staff_id'];
		$direct_assignment = $db->field_exists('assigned_nakes_user_id', 'requests')
			? 'COALESCE(notification_request.assigned_nakes_user_id, 0)'
			: '0';
		$legacy_owners = array();
		if ($db->field_exists('accepted_by_user_id', 'requests')) {
			$legacy_owners[] = "notification_request.accepted_by_user_id = {$user_id}";
		}
		if ($db->field_exists('dokter_id', 'requests')) {
			$legacy_owners[] = "notification_request.dokter_id = {$user_id}";
		}
		$legacy_owner = !empty($legacy_owners) ? '(' . implode(' OR ', $legacy_owners) . ')' : '0 = 1';

		$active_count = '0';
		$matching_count = '0';
		if ($db->table_exists('request_staff_assignments') && $db->table_exists('puskesmas_staff')) {
			$assignment_table = $db->dbprefix('request_staff_assignments');
			$staff_table = $db->dbprefix('puskesmas_staff');
			$active_count = "(SELECT COUNT(*) FROM {$assignment_table} notification_rsa WHERE notification_rsa.request_id = notification_request.request_id AND notification_rsa.status = 'aktif')";
			$matching_count = "(SELECT COUNT(*) FROM {$assignment_table} notification_match INNER JOIN {$staff_table} notification_staff ON notification_staff.staff_id = notification_match.staff_id WHERE notification_match.request_id = notification_request.request_id AND notification_match.status = 'aktif' AND notification_match.staff_id = {$staff_id} AND notification_staff.user_id = {$user_id} AND notification_staff.status = 'aktif' AND TRIM(notification_staff.kode_pkm) = " . $db->escape($puskesmas_code) . ')';
		}

		$explicit_agreement = "({$active_count} = 0 OR ({$active_count} = 1 AND {$matching_count} = 1))";
		$staff_assignment = "({$active_count} = 1 AND {$matching_count} = 1)";
		$legacy_fallback = "({$active_count} = 0 AND {$legacy_owner})";
		$personal_owner = "(({$direct_assignment} = {$user_id} AND {$explicit_agreement}) OR ({$direct_assignment} = 0 AND ({$staff_assignment} OR {$legacy_fallback})))";
		$request_visibility = "({$notification_alias}.entity_type = 'request' AND {$request_tenant} AND {$personal_owner})";
		$db->where("({$non_operational} OR {$request_visibility})", null, false);
	}
}

if (!function_exists('doclinc_create_notification')) {
	function doclinc_create_notification($payload)
	{
		if (!doclinc_notifications_ready()) {
			return false;
		}

		$required = array('event_type', 'entity_type', 'entity_id', 'title');
		foreach ($required as $field) {
			if (!isset($payload[$field]) || trim((string) $payload[$field]) === '') {
				log_message('error', 'Notification insert skipped: missing ' . $field);
				return false;
			}
		}

		$CI = &get_instance();
		$data = array(
			'recipient_user_id' => isset($payload['recipient_user_id']) && $payload['recipient_user_id'] !== '' ? (int) $payload['recipient_user_id'] : null,
			'recipient_role' => isset($payload['recipient_role']) ? substr(trim((string) $payload['recipient_role']), 0, 32) : null,
			'recipient_puskesmas_code' => isset($payload['recipient_puskesmas_code']) ? substr(trim((string) $payload['recipient_puskesmas_code']), 0, 32) : null,
			'actor_user_id' => isset($payload['actor_user_id']) && $payload['actor_user_id'] !== '' ? (int) $payload['actor_user_id'] : null,
			'event_type' => substr(trim((string) $payload['event_type']), 0, 64),
			'entity_type' => substr(trim((string) $payload['entity_type']), 0, 64),
			'entity_id' => substr(trim((string) $payload['entity_id']), 0, 64),
			'title' => substr(trim((string) $payload['title']), 0, 160),
			'message' => isset($payload['message']) ? trim((string) $payload['message']) : null,
			'is_read' => 0,
			'created_at' => date('Y-m-d H:i:s'),
		);

		require_once APPPATH . 'libraries/Notification_delivery_service.php';
		$feature_state = array('enabled' => $CI->config->item('realtime_notifications_enabled') === true);
		$delivery = new Notification_delivery_service($CI->db, $feature_state);
		$insert_id = $delivery->create($data);
		if ($insert_id === false) {
			log_message('error', 'Notification delivery transaction failed.');
			return false;
		}

		return $insert_id;
	}
}

if (!function_exists('doclinc_notification_realtime_bootstrap')) {
	function doclinc_notification_realtime_bootstrap()
	{
		$CI = &get_instance();
		require_once APPPATH . 'libraries/Notification_realtime_policy.php';
		if ($CI->config->item('realtime_notifications_enabled') !== true
			|| $CI->config->item('realtime_client_enabled') !== true
			|| $CI->session->userdata('logged_in') != true) {
			return null;
		}
		$user_id = (int) $CI->session->userdata('id');
		$role = (string) $CI->session->userdata('role');
		if ($user_id < 1 || !in_array($role, array('warga', 'dokter'), true)
			|| !$CI->db->field_exists('must_change_password', 'users')
			|| !doclinc_nakes_credential_schema_allows_runtime($CI->db)) {
			return null;
		}
		$user = $CI->db->select(
			'userId, role, status, must_change_password, ' . doclinc_nakes_password_changed_at_projection($CI->db),
			false
		)
			->where('userId', $user_id)->limit(1)->get('users')->row();
		if (!$user || (string) $user->role !== $role || (string) $user->status !== 'aktif') {
			return null;
		}
		$must_change_password = doclinc_nakes_password_change_blocked($user->role, $user->must_change_password, $user->password_changed_at);
		$identity = null;
		if ($role === 'dokter') {
			$CI->load->helper('request_authz');
			$identity = doclinc_dokter_identity_context($user_id, true);
		}
		$policy = new Notification_realtime_policy();
		if (!$policy->actorAllowed(array(
			'authenticated' => true,
			'user_id' => $user_id,
			'role' => $role,
			'status' => (string) $user->status,
			'must_change_password' => $must_change_password,
			'identity' => $identity,
		))) {
			return null;
		}
		$base_path = parse_url(base_url(), PHP_URL_PATH);
		$base_path = is_string($base_path) ? '/' . trim($base_path, '/') : '';
		$base_path = $base_path === '/' ? '' : $base_path;
		return array(
			'enabled' => true,
			'websocket_url' => (string) $CI->config->item('realtime_client_websocket_url'),
			'connection_token_url' => $base_path . '/realtime/connection-token',
			'subscription_token_url' => $base_path . '/realtime/subscription-token',
			'snapshot_url' => $base_path . '/notifications/snapshot',
			'sound_url' => $base_path . '/assets/audio/doclinc-notification.wav',
			'channel' => 'user:' . $user_id,
			'poll_interval_ms' => 30000,
		);
	}
}

if (!function_exists('doclinc_notify_user')) {
	function doclinc_notify_user($recipient_user_id, $event_type, $entity_type, $entity_id, $title, $message = '', $actor_user_id = null)
	{
		$recipient_user_id = (int) $recipient_user_id;
		if ($recipient_user_id < 1) {
			return false;
		}

		$CI = &get_instance();
		$user = $CI->db
			->select('role')
			->where('userId', $recipient_user_id)
			->where('status', 'aktif')
			->get('users')
			->row();
		if (!$user) {
			return false;
		}
		if ((string) $entity_type === 'request') {
			$CI->load->helper('request_authz');
			if ((string) $user->role === 'dokter') {
				$identity = doclinc_dokter_identity_context($recipient_user_id);
				if (!doclinc_can_view_request_notification((int) $entity_id, $identity)) {
					return false;
				}
			} elseif ((string) $user->role === 'warga') {
				$request = doclinc_request_row((int) $entity_id);
				if (!$request || (int) $request->user_id !== $recipient_user_id) {
					return false;
				}
			} else {
				return false;
			}
		}

		return doclinc_create_notification(array(
			'recipient_user_id' => $recipient_user_id,
			'recipient_role' => $user->role,
			'actor_user_id' => $actor_user_id,
			'event_type' => $event_type,
			'entity_type' => $entity_type,
			'entity_id' => $entity_id,
			'title' => $title,
			'message' => $message,
		));
	}
}

if (!function_exists('doclinc_notify_puskesmas')) {
	function doclinc_notify_puskesmas($puskesmas_code, $event_type, $entity_type, $entity_id, $title, $message = '', $actor_user_id = null)
	{
		$puskesmas_code = trim((string) $puskesmas_code);
		if ($puskesmas_code === '' || strtoupper($puskesmas_code) === 'DEFAULT') {
			return 0;
		}

		if (!doclinc_notifications_ready()) {
			return 0;
		}

		$CI = &get_instance();
		if (!$CI->db->field_exists('remark', 'users')) {
			return 0;
		}

		$user = $CI->db
			->select('userId, role')
			->where('role', 'dokter')
			->where('status', 'aktif')
			->where('TRIM(remark) = ' . $CI->db->escape($puskesmas_code), null, false)
			->order_by('userId', 'ASC')
			->limit(1)
			->get('users')
			->row();

		if (!$user) {
			return 0;
		}
		$CI->load->helper('request_authz');
		$identity = doclinc_dokter_identity_context((int) $user->userId);
		if (empty($identity['valid'])
			|| $identity['account_type'] !== 'command_center'
			|| trim((string) $identity['puskesmas_code']) !== $puskesmas_code) {
			return 0;
		}
		if ((string) $entity_type === 'request'
			&& !doclinc_can_view_request_notification((int) $entity_id, $identity)) {
			return 0;
		}

		$created = doclinc_create_notification(array(
				'recipient_user_id' => $user->userId,
				'recipient_role' => $user->role,
				'recipient_puskesmas_code' => $puskesmas_code,
				'actor_user_id' => $actor_user_id,
				'event_type' => $event_type,
				'entity_type' => $entity_type,
				'entity_id' => $entity_id,
				'title' => $title,
				'message' => $message,
			));

		return $created ? 1 : 0;
	}
}

if (!function_exists('doclinc_get_unread_notifications')) {
	function doclinc_get_unread_notifications($user_id, $limit = 20)
	{
		if (!doclinc_notifications_ready()) {
			return array();
		}

		$user_id = (int) $user_id;
		$limit = max(1, min((int) $limit, 50));
		if ($user_id < 1) {
			return array();
		}

		$CI = &get_instance();
		$user_context = doclinc_notification_user_context($user_id);
		$CI->db
			->select('notifications.*')
			->from('notifications')
			->join('requests notification_request', "notifications.entity_type = 'request' AND notification_request.request_id = CAST(notifications.entity_id AS UNSIGNED)", 'left', false)
			->where('notifications.recipient_user_id', $user_id)
			->where('is_read', 0)
			->order_by('notifications.created_at', 'DESC')
			->order_by('notifications.notification_id', 'DESC')
			->limit($limit);
		doclinc_apply_notification_visibility($CI->db, $user_context);
		return $CI->db
			->get()
			->result_array();
	}
}

if (!function_exists('doclinc_count_unread_notifications')) {
	function doclinc_count_unread_notifications($user_id)
	{
		if (!doclinc_notifications_ready()) {
			return 0;
		}

		$user_id = (int) $user_id;
		if ($user_id < 1) {
			return 0;
		}

		$CI = &get_instance();
		$user_context = doclinc_notification_user_context($user_id);
		$CI->db
			->from('notifications')
			->join('requests notification_request', "notifications.entity_type = 'request' AND notification_request.request_id = CAST(notifications.entity_id AS UNSIGNED)", 'left', false)
			->where('notifications.recipient_user_id', $user_id)
			->where('notifications.is_read', 0);
		doclinc_apply_notification_visibility($CI->db, $user_context);
		return (int) $CI->db->count_all_results();
	}
}

if (!function_exists('doclinc_mark_notification_read')) {
	function doclinc_mark_notification_read($notification_id, $user_id)
	{
		if (!doclinc_notifications_ready()) {
			return false;
		}

		$notification_id = (int) $notification_id;
		$user_id = (int) $user_id;
		if ($notification_id < 1 || $user_id < 1) {
			return false;
		}

		$CI = &get_instance();
		$user_context = doclinc_notification_user_context($user_id);
		$CI->db
			->select('notifications.notification_id, notifications.is_read')
			->from('notifications')
			->join('requests notification_request', "notifications.entity_type = 'request' AND notification_request.request_id = CAST(notifications.entity_id AS UNSIGNED)", 'left', false)
			->where('notifications.notification_id', $notification_id)
			->where('notifications.recipient_user_id', $user_id);
		doclinc_apply_notification_visibility($CI->db, $user_context);
		$notification = $CI->db
			->get()
			->row();
		if (!$notification) {
			return false;
		}
		if ((int) $notification->is_read === 1) {
			return true;
		}

		$CI->db
			->where('notification_id', $notification_id)
			->where('recipient_user_id', $user_id)
			->update('notifications', array(
				'is_read' => 1,
				'read_at' => date('Y-m-d H:i:s'),
			));

		return $CI->db->affected_rows() > 0;
	}
}
