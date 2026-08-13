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
		$request = doclinc_request_row((int) $request_id);
		if (!$request) {
			return $request;
		}
		$participant = $this->get_chat_participant_context((int) $request_id, $request);
		$request->chat_clinician_user_id = $participant['user_id'];
		$request->chat_clinician_name = $participant['name'];
		$request->chat_clinician_photo_url = $participant['photo_url'];
		return $request;
	}

	public function get_chat_participant_payload($request_id)
	{
		$participant = $this->get_chat_participant_context((int) $request_id);
		return array(
			'name' => $participant['name'],
			'photo_url' => $participant['photo_url'],
			'neutral' => $participant['user_id'] < 1,
		);
	}

	public function get_chat_participant_context($request_id, $request = null)
	{
		$request_id = (int) $request_id;
		$neutral = array('user_id' => 0, 'name' => 'Tenaga kesehatan', 'photo_url' => '');
		if ($request_id < 1) {
			return $neutral;
		}
		if (!$request) {
			$request = doclinc_request_row($request_id);
		}
		if (!$request) {
			return $neutral;
		}

		$candidates = array();
		if ($this->care_team_identity_ready()) {
			$people = $this->db
				->select('r.responsible_doctor_user_id, r.visit_performer_user_id')
				->select('doctor.nama AS responsible_doctor_name, performer.nama AS visit_performer_name')
				->from('requests r')
				->join('users doctor', 'doctor.userId = r.responsible_doctor_user_id', 'left')
				->join('users performer', 'performer.userId = r.visit_performer_user_id', 'left')
				->where('r.request_id', (int) $request_id)
				->limit(1)
				->get()
				->row();
			if ($people) {
				$candidates[(int) $people->responsible_doctor_user_id] = $people->responsible_doctor_name;
				$candidates[(int) $people->visit_performer_user_id] = $people->visit_performer_name;
			}
		} elseif ($this->config->item('care_team_workflow_enabled') !== true) {
			$legacy_user_id = (int) doclinc_request_handling_nakes_id($request);
			if ($legacy_user_id > 0) {
				$user = $this->db
					->select('userId, nama')
					->where('userId', $legacy_user_id)
					->limit(1)
					->get('users')
					->row();
				if ($user) {
					$candidates[(int) $user->userId] = $user->nama;
				}
			}
		}

		$facility = isset($request->assigned_puskesmas_code) ? trim((string) $request->assigned_puskesmas_code) : '';
		foreach ($candidates as $user_id => $name) {
			$identity = $user_id > 0 ? doclinc_dokter_identity_context($user_id, true) : array();
			if (empty($identity['valid'])
				|| (string) ($identity['account_type'] ?? '') !== 'personal'
				|| ($facility !== '' && (string) ($identity['puskesmas_code'] ?? '') !== $facility)
				|| trim((string) $name) === '') {
				unset($candidates[$user_id]);
			}
		}
		if (empty($candidates)) {
			return $neutral;
		}

		$clinician_user_id = 0;
		if ($this->messages_ready()) {
			$senders = $this->db
				->select('sender_user_id')
				->where('request_id', $request_id)
				->where_in('sender_user_id', array_keys($candidates))
				->where('deleted_at IS NULL', null, false)
				->group_by('sender_user_id')
				->limit(2)
				->get('consultation_messages')
				->result();
			if (count($senders) > 1) {
				return $neutral;
			}
			$clinician_user_id = count($senders) === 1 ? (int) $senders[0]->sender_user_id : 0;
		}
		if ($clinician_user_id < 1 && count($candidates) === 1) {
			$clinician_user_id = (int) array_key_first($candidates);
		}
		if ($clinician_user_id > 0 && isset($candidates[$clinician_user_id])) {
			return array(
				'user_id' => $clinician_user_id,
				'name' => trim((string) $candidates[$clinician_user_id]),
				'photo_url' => base_url('profile/photo/' . $clinician_user_id),
			);
		}
		return $neutral;
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

	private function send_image_message($request_id, $current_user_id, $relative_path, $mime_type = '', $original_name = '')
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

	public function image_upload_config()
	{
		$upload_path = $this->attachment_storage()->ensure_storage_directory();
		if ($upload_path === false) {
			return false;
		}

		return array(
			'upload_path' => $upload_path,
			'allowed_types' => 'jpg|jpeg|png|webp',
			'max_size' => max(1, (int) $this->config->item('chat_attachment_max_size_kb')),
			'encrypt_name' => TRUE,
			'detect_mime' => TRUE,
			'mod_mime_fix' => TRUE,
			'remove_spaces' => TRUE,
		);
	}

	public function send_uploaded_image_message($request_id, $current_user_id, $upload_data)
	{
		if (!is_array($upload_data) || empty($upload_data['file_name'])) {
			return false;
		}
		$stored_key = $this->attachment_storage()->stored_key_from_upload($upload_data);
		if ($stored_key === '') {
			return false;
		}
		$storage = $this->attachment_storage();
		$stored_path = $storage->resolve_stored_file($stored_key);
		$actual_mime = $storage->allowed_mime($stored_path);
		$max_bytes = max(1, (int) $this->config->item('chat_attachment_max_size_kb')) * 1024;
		$stored_size = $stored_path !== false ? @filesize($stored_path) : false;
		if ($stored_path === false || $actual_mime === '' || $stored_size === false || $stored_size < 1 || $stored_size > $max_bytes) {
			return false;
		}

		return $this->send_image_message(
			$request_id,
			$current_user_id,
			$stored_key,
			$actual_mime,
			isset($upload_data['client_name']) ? $upload_data['client_name'] : ''
		);
	}

	public function get_attachment_for_user($message_id, $current_user_id)
	{
		$message_id = (int) $message_id;
		$current_user_id = (int) $current_user_id;
		if ($message_id < 1 || $current_user_id < 1 || !$this->messages_ready()) {
			return false;
		}

		$row = $this->db
			->select('message_id, request_id, message_type, message_text, attachment_path, attachment_mime, attachment_original_name, deleted_at')
			->where('message_id', $message_id)
			->limit(1)
			->get('consultation_messages')
			->row_array();
		if (!$row || !empty($row['deleted_at']) || (string) $row['message_type'] !== 'image'
			|| !doclinc_can_view_chat((int) $row['request_id'], $current_user_id)) {
			return false;
		}

		$stored_key = $this->safe_attachment_path(isset($row['attachment_path']) ? $row['attachment_path'] : '');
		if ($stored_key === '') {
			$stored_key = $this->safe_attachment_path(isset($row['message_text']) ? $row['message_text'] : '');
		}
		$storage = $this->attachment_storage();
		$path = $storage->resolve_stored_file($stored_key);
		$mime = $storage->allowed_mime($path);
		$max_bytes = max(1, (int) $this->config->item('chat_attachment_max_size_kb')) * 1024;
		$size = $path !== false ? @filesize($path) : false;
		if ($path === false || $mime === '' || $size === false || $size < 1 || $size > $max_bytes) {
			return false;
		}

		$extension = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
		$original_name = $this->safe_download_name(isset($row['attachment_original_name']) ? $row['attachment_original_name'] : '', $extension);

		return array(
			'message_id' => (int) $row['message_id'],
			'request_id' => (int) $row['request_id'],
			'path' => $path,
			'mime' => $mime,
			'size' => (int) $size,
			'original_name' => $original_name,
		);
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

	private function care_team_identity_ready()
	{
		return $this->config->item('care_team_workflow_enabled') === true
			&& $this->db->field_exists('responsible_doctor_user_id', 'requests')
			&& $this->db->field_exists('visit_performer_user_id', 'requests');
	}

	private function format_message($row)
	{
		$attachment_path = $this->safe_attachment_path(isset($row['attachment_path']) ? $row['attachment_path'] : '');
		if ($attachment_path === '' && isset($row['message_type']) && $row['message_type'] === 'image') {
			$attachment_path = $this->safe_attachment_path(isset($row['message_text']) ? $row['message_text'] : '');
		}
		$attachment_mime = strtolower(trim((string) (isset($row['attachment_mime']) ? $row['attachment_mime'] : '')));
		if (!in_array($attachment_mime, array('image/jpeg', 'image/png', 'image/webp'), true)) {
			$attachment_mime = '';
		}
		$attachment_extension = $attachment_mime === 'image/png' ? 'png' : ($attachment_mime === 'image/webp' ? 'webp' : 'jpg');
		$attachment_name = $attachment_path !== ''
			? $this->safe_download_name(isset($row['attachment_original_name']) ? $row['attachment_original_name'] : '', $attachment_extension)
			: '';

		return array(
			'message_id' => (int) $row['message_id'],
			'request_id' => (int) $row['request_id'],
			'sender_user_id' => (int) $row['sender_user_id'],
			'sender_role' => $row['sender_role'],
			'sender_photo_url' => base_url('profile/photo/' . (int) $row['sender_user_id']),
			'message_type' => $row['message_type'],
			'message_text' => (string) $row['message_type'] === 'image' ? '' : $row['message_text'],
			'attachment_url' => $attachment_path !== '' ? base_url('chat/attachment/' . (int) $row['message_id']) : '',
			'attachment_mime' => $attachment_mime,
			'attachment_original_name' => $attachment_name,
			'is_read' => (int) $row['is_read'],
			'created_at' => $row['created_at'],
		);
	}

	private function safe_attachment_path($path)
	{
		return $this->attachment_storage()->safe_stored_key($path);
	}

	private function attachment_storage()
	{
		require_once APPPATH . 'libraries/Chat_attachment_storage.php';
		return new Chat_attachment_storage(array(
			'storage_path' => (string) $this->config->item('chat_attachment_storage_path'),
			'public_root' => FCPATH,
		));
	}

	private function safe_download_name($value, $extension)
	{
		$value = basename(str_replace(array("\r", "\n", "\0"), '', (string) $value));
		$value = pathinfo($value, PATHINFO_FILENAME);
		$value = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $value);
		$value = trim((string) $value, " .-\t\n\r\0\x0B");
		if ($value === '') {
			$value = 'foto-konsultasi';
		}
		return substr($value, 0, 132) . '.' . $extension;
	}

	private function notify_recipient($request_id, $sender_user_id)
	{
		$request = doclinc_request_row($request_id);
		if (!$request) {
			return;
		}

		$recipient_user_id = null;
		if ((string) $request->user_id === (string) $sender_user_id) {
			$recipient_user_id = doclinc_request_handling_nakes_id($request);
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
