<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Notifikasi_admin extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Notifikasi_admin_m');
	}

	public function summary()
	{
		if (!$this->require_admin()) {
			return;
		}

		$user_id = $this->Notifikasi_admin_m->current_admin_user_id();
		if ($user_id < 1) {
			return $this->json_response(array(
				'ok' => false,
				'unread_count' => 0,
				'events' => array(),
			), 403);
		}

		return $this->json_response(array(
			'ok' => true,
			'unread_count' => $this->Notifikasi_admin_m->count_unread_events($user_id),
			'events' => $this->Notifikasi_admin_m->latest_events(10),
		));
	}

	public function mark_read()
	{
		if (!$this->require_admin()) {
			return;
		}

		if ($this->input->method(TRUE) !== 'POST') {
			return $this->json_response(array('ok' => false, 'message' => 'Method not allowed'), 405);
		}

		$user_id = $this->Notifikasi_admin_m->current_admin_user_id();
		if ($user_id < 1) {
			return $this->json_response(array('ok' => false, 'message' => 'Anda tidak memiliki akses.'), 403);
		}

		$last_seen_event_id = $this->Notifikasi_admin_m->mark_all_read($user_id);

		return $this->json_response(array(
			'ok' => true,
			'last_seen_event_id' => $last_seen_event_id,
			'unread_count' => 0,
		));
	}

	private function require_admin()
	{
		if ($this->session->userdata('is_login') == FALSE || $this->session->userdata('level') !== 'admin') {
			$this->json_response(array(
				'ok' => false,
				'unread_count' => 0,
				'events' => array(),
				'message' => 'Anda tidak memiliki akses.',
			), 403);
			return false;
		}

		return true;
	}

	private function json_response($payload, $status = 200)
	{
		return $this->output
			->set_status_header($status)
			->set_content_type('application/json')
			->set_output(json_encode($payload));
	}
}
