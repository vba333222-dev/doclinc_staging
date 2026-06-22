<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_notifications_ready')) {
	function doclinc_notifications_ready()
	{
		$CI = &get_instance();
		return $CI->db->table_exists('notifications');
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

		$previous_debug = $CI->db->db_debug;
		$CI->db->db_debug = false;
		$result = $CI->db->insert('notifications', $data);
		$insert_id = $result ? $CI->db->insert_id() : false;
		$error = $CI->db->error();
		$CI->db->db_debug = $previous_debug;

		if (!$result) {
			log_message('error', 'Notification insert failed: ' . (!empty($error['message']) ? $error['message'] : 'unknown error'));
			return false;
		}

		return $insert_id;
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
			log_message('error', 'Puskesmas notification skipped: users.remark is missing');
			return 0;
		}

		$users = $CI->db
			->select('userId, role')
			->where('role', 'dokter')
			->where('status', 'aktif')
			->where('TRIM(remark) = ' . $CI->db->escape($puskesmas_code), null, false)
			->get('users')
			->result();

		if (empty($users)) {
			log_message('error', 'Puskesmas notification skipped: no active nakes user for code ' . $puskesmas_code);
			return 0;
		}

		$count = 0;
		foreach ($users as $user) {
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
			if ($created) {
				$count++;
			}
		}

		return $count;
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
		return $CI->db
			->where('recipient_user_id', $user_id)
			->where('is_read', 0)
			->order_by('created_at', 'DESC')
			->limit($limit)
			->get('notifications')
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
		return (int) $CI->db
			->where('recipient_user_id', $user_id)
			->where('is_read', 0)
			->count_all_results('notifications');
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
