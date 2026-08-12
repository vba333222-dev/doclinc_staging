<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Chat extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Chat_m');
		$this->load->helper('request_authz');
		$this->load->helper('request_navigation');
		$this->load->helper('notification');
		$this->load->helper('nakes_presence_client');
		if ($this->session->userdata('logged_in') != TRUE) {
			if (strtolower((string) $this->router->fetch_method()) === 'attachment') {
				show_404();
			}
			redirect('login');
		}
	}

	public function index()
	{
		$request_id = (int) ($this->input->get('request_id', TRUE) ?: $this->input->get('reqId', TRUE));
		if (doclinc_chat_actor_is_command_center()) {
			redirect(doclinc_request_return_target(doclinc_current_user_role()));
			return;
		}
		if ($request_id < 1 || !doclinc_can_view_chat($request_id)) {
			doclinc_log_request_event('unauthorized_request_access', $request_id, array('target' => 'chat'));
			$role = doclinc_current_user_role();
			redirect(doclinc_request_return_target($role));
			return;
		}

		$request = $this->Chat_m->get_request_for_chat($request_id);
		if (!$request) {
			doclinc_log_request_event('unauthorized_request_access', $request_id, array('target' => 'chat_missing_request'));
			redirect(doclinc_request_return_target(doclinc_current_user_role()));
			return;
		}
		$current_role = doclinc_current_user_role();
		$is_command_center = doclinc_chat_actor_is_command_center();
		$identity_context = $current_role === 'dokter'
			? doclinc_dokter_identity_context(doclinc_current_user_id())
			: array();
		$this->load->view('thread_v', array(
			'request' => $request,
			'request_id' => $request_id,
			'can_send' => doclinc_can_send_chat($request_id),
			'current_user_id' => doclinc_current_user_id(),
			'current_role' => $current_role,
			'is_command_center' => $is_command_center,
			'nakes_presence_bootstrap' => doclinc_nakes_presence_client_bootstrap($identity_context),
			'back_url' => doclinc_request_return_url(
				$current_role,
				isset($request->request_status) ? $request->request_status : ''
			),
		));
	}

	public function messages()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'GET') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan')));
			return;
		}
		$request_id = (int) $this->input->get('request_id', TRUE);
		$after_id = (int) $this->input->get('after_id', TRUE);
		$user_id = (int) $this->session->userdata('id');

		if ($request_id < 1 || doclinc_chat_actor_is_command_center($user_id)
			|| !doclinc_can_view_chat($request_id, $user_id)) {
			doclinc_log_request_event('unauthorized_request_access', $request_id, array('target' => 'chat_messages'));
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Anda tidak memiliki akses.')));
			return;
		}

		$this->output->set_output(json_encode(array(
			'status' => 'success',
			'can_send' => doclinc_can_send_chat($request_id, $user_id),
			'messages' => $this->Chat_m->get_messages($request_id, $user_id, 50, $after_id),
		)));
	}

	public function send()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan')));
			return;
		}

		$request_id = (int) $this->input->post('request_id');
		$user_id = (int) $this->session->userdata('id');
		if ($request_id < 1 || !doclinc_can_send_chat($request_id, $user_id)) {
			doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'chat_send'));
			$read_only = doclinc_chat_actor_is_command_center($user_id);
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array(
					'status' => 'error',
					'safe_error_code' => $read_only ? 'chat_read_only' : 'actor_denied',
					'message' => $read_only ? 'Chat pasien tidak tersedia.' : 'Anda tidak memiliki akses.',
				)));
			return;
		}

		$message_text = $this->input->post('message_text', TRUE);
		$message = $this->Chat_m->send_text_message($request_id, $user_id, $message_text);
		if (!$message) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Pesan belum dapat dikirim.')));
			return;
		}

		$this->output->set_output(json_encode(array('status' => 'success', 'message' => $message)));
	}

	public function mark_read()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Metode tidak diizinkan')));
			return;
		}

		$request_id = (int) $this->input->post('request_id');
		$user_id = (int) $this->session->userdata('id');
		if ($request_id < 1 || doclinc_chat_actor_is_command_center($user_id)
			|| !$this->Chat_m->mark_read($request_id, $user_id)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode(array('status' => 'error', 'message' => 'Anda tidak memiliki akses.')));
			return;
		}

		$this->output->set_output(json_encode(array('status' => 'success')));
	}

	public function foto()
	{
		if (!$this->authorize_upload_request()) {
			return;
		}

		$request_id = (int) $this->input->post('request_id');
		$user_id = (int) $this->session->userdata('id');
		$config = $this->Chat_m->image_upload_config();
		if ($config === false) {
			log_message('error', 'Private chat attachment storage is unavailable.');
			$this->output
				->set_status_header(503)
				->set_output(json_encode(['status' => 'error', 'message' => 'Penyimpanan gambar belum siap. Coba lagi.']));
			return;
		}

		$this->load->library('upload', $config);

		if (!$this->upload->do_upload('foto')) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode(['status' => 'error', 'message' => $this->chat_image_upload_error_message()]));
		} else {
			$data = $this->upload->data();
			$message = $this->Chat_m->send_uploaded_image_message($request_id, $user_id, $data);
			if (!$message) {
				@unlink($data['full_path']);
				$this->output
					->set_status_header(400)
					->set_output(json_encode(['status' => 'error', 'message' => 'Gambar belum dapat dikirim.']));
				return;
			}

			$this->output->set_output(json_encode([
				'status' => 'success',
				'message' => $message
			]));
		}
	}

	public function attachment($message_id = 0)
	{
		if ($this->input->method(TRUE) !== 'GET') {
			show_404();
		}

		$message_id = (int) $message_id;
		$user_id = (int) $this->session->userdata('id');
		if (doclinc_chat_actor_is_command_center($user_id)) {
			show_404();
		}
		$attachment = $this->Chat_m->get_attachment_for_user($message_id, $user_id);
		if (!$attachment) {
			doclinc_log_request_event('unauthorized_request_access', 0, array(
				'target' => 'chat_attachment',
				'message_id' => $message_id,
			));
			show_404();
		}

		$contents = @file_get_contents($attachment['path']);
		if (!is_string($contents) || strlen($contents) !== (int) $attachment['size']) {
			log_message('error', 'Chat attachment could not be read for message ' . $message_id . '.');
			show_404();
		}

		$this->output
			->set_status_header(200)
			->set_content_type($attachment['mime'])
			->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0')
			->set_header('Pragma: no-cache')
			->set_header('X-Content-Type-Options: nosniff')
			->set_header('Cross-Origin-Resource-Policy: same-origin')
			->set_header('Content-Length: ' . (int) $attachment['size'])
			->set_header('Content-Disposition: inline; filename="' . $attachment['original_name'] . '"')
			->set_output($contents);
	}

	public function video()
	{
		$this->output
			->set_content_type('application/json')
			->set_status_header(400)
			->set_output(json_encode(['status' => 'error', 'message' => 'Upload video belum tersedia']));
	}

	private function authorize_upload_request()
	{
		$this->output->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			$this->output
				->set_status_header(405)
				->set_output(json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan']));
			return false;
		}

		$request_id = (int) $this->input->post('request_id');
		if ($request_id < 1 || !doclinc_can_send_chat($request_id)) {
			doclinc_log_request_event('unauthorized_request_update', $request_id, array('target' => 'chat_upload'));
			$read_only = doclinc_chat_actor_is_command_center();
			$this->output
				->set_status_header(403)
				->set_output(json_encode([
					'status' => 'error',
					'safe_error_code' => $read_only ? 'chat_read_only' : 'actor_denied',
					'message' => $read_only ? 'Chat pasien tidak tersedia.' : 'Anda tidak memiliki akses.',
				]));
			return false;
		}

		return true;
	}

	private function chat_image_upload_error_message()
	{
		return 'Gambar tidak valid. Gunakan JPG/PNG/WEBP dengan ukuran maksimal 4 MB.';
	}
}
