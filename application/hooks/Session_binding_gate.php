<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Session_binding_gate
{
	public function enforce()
	{
		if (PHP_SAPI === 'cli') {
			return;
		}
		$CI = &get_instance();
		/* Gate ini dipakai DUA aplikasi: aplikasi utama dan panel admin
		 * (admin_menu/application/hooks/Session_binding_gate.php menumpang berkas
		 * ini, tidak menyalinnya). Hanya dua hal yang berbeda di sana - kunci
		 * sesi login dan lokasi library bersama - dan keduanya dibaca dari
		 * config supaya tidak ada implementasi kedua yang bisa menyimpang.
		 * Nilai kosong berarti perilaku aplikasi utama seperti semula. */
		$login_key = (string) $CI->config->item('session_binding_login_key');
		if ($login_key === '') {
			$login_key = 'logged_in';
		}
		$libraries = (string) $CI->config->item('session_binding_library_path');
		if ($libraries === '') {
			$libraries = APPPATH . 'libraries/';
		}
		if ($CI->config->item('single_active_session_enabled') !== true
			|| !$CI->session->userdata($login_key)) {
			return;
		}
		require_once $libraries . 'Session_binding_service.php';
		$service = new Session_binding_service($CI->db);
		$user_id = (int) $CI->session->userdata('id');
		$token = $CI->session->userdata('normal_session_token');
		if ($service->validate($user_id, $token)) {
			return;
		}
		/* Sebabnya dibaca SEBELUM sesi dihancurkan. "Akun dipakai di perangkat
		 * lain" bukan hal yang sama dengan "sesi kedaluwarsa", dan pengguna
		 * berhak tahu bedanya - terutama di aplikasi klinis. */
		$diagnosis = $service->diagnose($user_id, $token);
		require_once $libraries . 'Session_binding_response_policy.php';
		$response_policy = new Session_binding_response_policy();
		/* Jendela rollout: sesi yang dibuat sebelum fitur menyala belum punya
		 * token. Tanpa adopsi, semua pengguna ditendang serentak. Hanya sesi
		 * tanpa baris binding yang boleh diadopsi - lihat policy-nya. */
		require_once $libraries . 'Session_binding_adoption_policy.php';
		$adoption = new Session_binding_adoption_policy();
		if ($adoption->shouldAdopt($CI->config->item('single_active_session_adopt_legacy') === true, $diagnosis)) {
			$issued = $service->issue($user_id, null);
			if (is_string($issued)) {
				$CI->session->set_userdata('normal_session_token', $issued);
				if (hash_equals($issued, (string) $CI->session->userdata('normal_session_token'))) {
					$this->logAdoption($CI, $user_id);
					return;
				}
			}
			/* Adopsi gagal: lanjut sebagai pencabutan biasa. */
		}
		$reason = $response_policy->reasonCode($diagnosis);
		$CI->session->sess_destroy();
		$this->clearSessionCookie($CI);
		require_once $libraries . 'First_login_gate_policy.php';
		$policy = new First_login_gate_policy();
		$is_json = $policy->jsonResponse(
			(string) $CI->router->class,
			(string) $CI->router->method,
			$CI->input->is_ajax_request(),
			(string) $CI->input->server('HTTP_ACCEPT'),
			(string) $CI->input->server('CONTENT_TYPE'),
			(string) $CI->input->server('HTTP_SEC_FETCH_MODE')
		);
		/* Formulir biasa (bukan AJAX) kehilangan isian saat dialihkan ke login.
		 * Katakan itu apa adanya; jangan biarkan pengguna menebak. */
		$unsaved = $response_policy->isUnsaved($is_json, $CI->input->method());
		/* Pencabutan dicatat pada semua jalur, AJAX maupun navigasi. Lihat alasan
		 * dan buktinya di Session_binding_response_policy::auditMetadata(). */
		$this->writeAudit($CI, $user_id, 'session_revoked', $response_policy->auditMetadata(
			$reason,
			(string) $CI->router->class,
			(string) $CI->router->method,
			$is_json
		));
		if ($is_json) {
			$CI->output
				->set_status_header(401)
				->set_content_type('application/json', 'utf-8')
				->set_header('Cache-Control: no-store, private')
				->set_output(json_encode($response_policy->jsonBody($reason)));
			$CI->output->_display();
			exit;
		}
		redirect($response_policy->redirectTarget($reason, $unsaved));
		exit;
	}

	/**
	 * Hapus cookie sesi setelah sesi dihancurkan.
	 *
	 * CI3 di repo ini hanya memanggil session_destroy() (lihat
	 * system/libraries/Session/Session.php), jadi cookie sesi tetap tertinggal
	 * di peramban. Sesi lama tidak bisa dipakai lagi, tetapi membiarkan
	 * cookie-nya membuat peramban terus mengirim ID yang sudah mati dan
	 * menyulitkan diagnosa "kenapa saya ter-logout".
	 *
	 * Catatan penting: pada CI_Input::set_cookie(), expire yang tidak diberikan
	 * berarti "kedaluwarsa di masa lalu" (penghapusan), sedangkan angka <= 0
	 * justru membuat cookie sesi biasa. Jadi expire sengaja tidak diisi.
	 */
	private function clearSessionCookie($CI)
	{
		$name = (string) $CI->config->item('sess_cookie_name');
		if ($name === '') {
			return;
		}
		$CI->input->set_cookie(array(
			'name' => $name,
			'value' => '',
			'path' => (string) $CI->config->item('cookie_path'),
			'domain' => (string) $CI->config->item('cookie_domain'),
		));
	}

	/**
	 * Jejak audit adopsi sesi lama. Selalu dicatat: peristiwa ini terjadi
	 * paling banyak sekali per sesi, dan operator perlu tahu berapa sesi lama
	 * yang masih hidup selama jendela rollout.
	 */
	private function logAdoption($CI, $user_id)
	{
		$this->writeAudit($CI, $user_id, 'session_binding_adopted', array(
			'controller' => (string) $CI->router->class,
			'method' => (string) $CI->router->method,
		));
	}

	/** Kegagalan menulis audit tidak boleh mengubah hasil pemeriksaan sesi. */
	private function writeAudit($CI, $user_id, $action, array $metadata)
	{
		if ((int) $user_id < 1) {
			return;
		}
		$db_debug = $CI->db->db_debug;
		try {
			if (!$CI->db->table_exists('audit_logs')) {
				return;
			}
			$CI->db->db_debug = false;
			$CI->db->insert('audit_logs', array(
				'actor_user_id' => $user_id,
				'action' => $action,
				'entity_type' => 'auth',
				'entity_id' => (string) $user_id,
				'ip_address' => (string) $CI->input->ip_address(),
				'user_agent' => substr((string) $CI->input->user_agent(), 0, 255),
				'metadata_json' => json_encode($metadata),
				'created_at' => date('Y-m-d H:i:s'),
			));
		} catch (Throwable $exception) {
			// Audit bukan syarat pemeriksaan sesi.
		} finally {
			$CI->db->db_debug = $db_debug;
		}
	}
}
