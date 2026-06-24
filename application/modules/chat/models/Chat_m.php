<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Chat_m extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('request_authz');
		$this->load->helper('notification');
	}

	public function get_request_for_chat($request_id)
	{
		return doclinc_request_row((int) $request_id);
	}

	public function get_messages($request_id, $current_user_id, $limit = 50, $after_id = 0)
	{
		$request_id = (int) $request_id;
		$current_user_id = (int) $current_user_id;
		$limit = max(1, min((int) $limit, 100));
		$after_id = max(0, (int) $after_id);

		if (!$this->messages_ready() || !doclinc_can_view_chat($request_id, $current_user_id)) {
			return array();
		}

		$this->db
			->where('request_id', $request_id)
			->where('deleted_at IS NULL', null, false);
		if ($after_id > 0) {
			$this->db->where('message_id >', $after_id);
		}

		$rows = $this->db
			->order_by('message_id', 'ASC')
			->limit($limit)
			->get('consultation_messages')
			->result_array();

		return array_map(array($this, 'format_message'), $rows);
	}

	public function send_text_message($request_id, $current_user_id, $message_text)
	{
		$request_id = (int) $request_id;
		$current_user_id = (int) $current_user_id;
		$message_text = trim((string) $message_text);

		if (!$this->messages_ready() || !doclinc_can_send_chat($request_id, $current_user_id)) {
			return false;
		}

		if ($message_text === '') {
			return false;
		}

		if (strlen($message_text) > 2000) {
			$message_text = substr($message_text, 0, 2000);
		}

		$role = doclinc_current_user_role();
		$data = array(
			'request_id' => $request_id,
			'sender_user_id' => $current_user_id,
			'sender_role' => $role,
			'message_type' => 'text',
			'message_text' => $message_text,
			'is_read' => 0,
			'created_at' => date('Y-m-d H:i:s'),
		);

		$this->db->insert('consultation_messages', $data);
		$message_id = $this->db->insert_id();
		if (!$message_id) {
			return false;
		}

		$this->notify_recipient($request_id, $current_user_id);

		$row = $this->db
			->where('message_id', $message_id)
			->get('consultation_messages')
			->row_array();

		return $row ? $this->format_message($row) : false;
	}

	public function send_image_message($request_id, $current_user_id, $relative_path, $mime_type = '', $original_name = '')
	{
		$request_id = (int) $request_id;
		$current_user_id = (int) $current_user_id;
		$relative_path = $this->safe_attachment_path($relative_path);

		if (!$this->messages_ready() || !doclinc_can_send_chat($request_id, $current_user_id) || $relative_path === '') {
			return false;
		}

		$data = array(
			'request_id' => $request_id,
			'sender_user_id' => $current_user_id,
			'sender_role' => doclinc_current_user_role(),
			'message_type' => 'image',
			'message_text' => $relative_path,
			'attachment_path' => $relative_path,
			'attachment_mime' => substr(trim((string) $mime_type), 0, 120),
			'attachment_original_name' => substr(basename((string) $original_name), 0, 180),
			'is_read' => 0,
			'created_at' => date('Y-m-d H:i:s'),
		);

		$this->db->insert('consultation_messages', $data);
		$message_id = $this->db->insert_id();
		if (!$message_id) {
			return false;
		}

		$this->notify_recipient($request_id, $current_user_id);

		$row = $this->db
			->where('message_id', $message_id)
			->get('consultation_messages')
			->row_array();

		return $row ? $this->format_message($row) : false;
	}

	public function mark_read($request_id, $current_user_id)
	{
		$request_id = (int) $request_id;
		$current_user_id = (int) $current_user_id;

		if (!$this->messages_ready() || !doclinc_can_view_chat($request_id, $current_user_id)) {
			return false;
		}

		$this->db
			->where('request_id', $request_id)
			->where('sender_user_id !=', $current_user_id)
			->where('is_read', 0)
			->where('deleted_at IS NULL', null, false)
			->update('consultation_messages', array('is_read' => 1));

		return true;
	}

	private function messages_ready()
	{
		return $this->db->table_exists('consultation_messages');
	}

	private function format_message($row)
	{
		$attachment_path = $this->safe_attachment_path(isset($row['attachment_path']) ? $row['attachment_path'] : '');
		if ($attachment_path === '' && isset($row['message_type']) && $row['message_type'] === 'image') {
			$attachment_path = $this->safe_attachment_path(isset($row['message_text']) ? $row['message_text'] : '');
		}

		return array(
			'message_id' => (int) $row['message_id'],
			'request_id' => (int) $row['request_id'],
			'sender_user_id' => (int) $row['sender_user_id'],
			'sender_role' => $row['sender_role'],
			'message_type' => $row['message_type'],
			'message_text' => $row['message_text'],
			'attachment_path' => $attachment_path,
			'attachment_url' => $attachment_path !== '' ? base_url($attachment_path) : '',
			'attachment_mime' => $row['attachment_mime'],
			'attachment_original_name' => $row['attachment_original_name'],
			'is_read' => (int) $row['is_read'],
			'created_at' => $row['created_at'],
		);
	}

	private function safe_attachment_path($path)
	{
		$path = trim(str_replace('\\', '/', (string) $path));
		if ($path === '' || strpos($path, '..') !== false || strpos($path, ':') !== false || strpos($path, '//') !== false || $path[0] === '/') {
			return '';
		}

		if (strpos($path, 'uploads/chat_images/') !== 0) {
			return '';
		}

		return $path;
	}

	private function notify_recipient($request_id, $sender_user_id)
	{
		$request = doclinc_request_row($request_id);
		if (!$request) {
			return;
		}

		$recipient_user_id = null;
		if ((string) $request->user_id === (string) $sender_user_id) {
			if (!empty($request->accepted_by_user_id)) {
				$recipient_user_id = $request->accepted_by_user_id;
			} elseif (!empty($request->dokter_id)) {
				$recipient_user_id = $request->dokter_id;
			}
		} else {
			$recipient_user_id = $request->user_id;
		}

		if ($recipient_user_id) {
			doclinc_notify_user(
				$recipient_user_id,
				'chat_message',
				'request',
				$request_id,
				'Pesan konsultasi baru',
				'Ada pesan baru pada konsultasi Anda.',
				$sender_user_id
			);
		}
	}
}
