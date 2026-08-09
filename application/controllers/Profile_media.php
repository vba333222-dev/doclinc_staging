<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Profile_media extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library('session');
		$this->load->helper('request_authz');
	}

	public function photo($target_user_id = 0)
	{
		if ($this->input->method(true) !== 'GET' || $this->session->userdata('logged_in') != true) {
			show_404();
			return;
		}
		if (!$this->db->table_exists('users')) {
			show_404();
			return;
		}
		foreach (array('userId', 'role', 'status', 'must_change_password', 'foto') as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				show_404();
				return;
			}
		}
		if (!doclinc_nakes_credential_schema_allows_runtime($this->db)) {
			show_404();
			return;
		}

		$actor_id = (int) $this->session->userdata('id');
		$target_user_id = (int) $target_user_id;
		$actor = $this->user_row($actor_id);
		$target = $this->user_row($target_user_id);
		if (!$actor || !$target || (string) $actor->role !== (string) $this->session->userdata('role')) {
			show_404();
			return;
		}
		$this->apply_effective_credential_state($actor);
		$this->apply_effective_credential_state($target);

		require_once APPPATH . 'libraries/Profile_image_policy.php';
		$policy = new Profile_image_policy($this->db);
		if (!$policy->can_view($actor, $target)) {
			show_404();
			return;
		}

		require_once APPPATH . 'libraries/Profile_image_storage.php';
		$storage = new Profile_image_storage(array(
			'storage_path' => $this->config->item('profile_image_storage_path'),
			'public_root' => FCPATH,
		));
		$path = $storage->resolve_stored_file((string) $target->foto);
		$mime = $path !== false ? $storage->allowed_mime($path) : '';
		if ($path === false || $mime === '') {
			$path = FCPATH . 'assets/doclinc/img/default-profile.png';
			$mime = is_file($path) ? $storage->allowed_mime($path) : '';
		}
		if (!is_file($path) || !is_readable($path) || $mime === '') {
			show_404();
			return;
		}

		$body = file_get_contents($path);
		if ($body === false) {
			show_404();
			return;
		}

		$this->output
			->set_status_header(200)
			->set_content_type($mime)
			->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0')
			->set_header('Pragma: no-cache')
			->set_header('X-Content-Type-Options: nosniff')
			->set_header('Cross-Origin-Resource-Policy: same-origin')
			->set_header('Content-Length: ' . strlen($body))
			->set_output($body);
	}

	public function update_photo()
	{
		$this->output->set_content_type('application/json', 'utf-8');
		if ($this->input->method(true) !== 'POST') {
			$this->respond(405, 'method_not_allowed', 'Metode permintaan tidak didukung.');
			return;
		}
		if ($this->session->userdata('logged_in') != true) {
			$this->respond(401, 'session_required', 'Silakan masuk terlebih dahulu.');
			return;
		}
		if (!$this->profile_schema_ready()) {
			$this->respond(503, 'profile_schema_unavailable', 'Penyimpanan profil sedang disiapkan.');
			return;
		}

		$user_id = (int) $this->session->userdata('id');
		$role = (string) $this->session->userdata('role');
		$actor = $this->user_row($user_id);
		$this->apply_effective_credential_state($actor);
		if (!$actor
			|| !in_array($role, array('warga', 'dokter'), true)
			|| (string) $actor->role !== $role
			|| (string) $actor->status !== 'aktif'
			|| (int) $actor->must_change_password === 1) {
			$this->respond(403, 'actor_denied', 'Akun belum dapat memperbarui foto profil.');
			return;
		}
		if (empty($_FILES['foto']) || empty($_FILES['foto']['name'])) {
			$this->respond(422, 'photo_required', 'Pilih foto profil terlebih dahulu.');
			return;
		}

		require_once APPPATH . 'libraries/Profile_image_storage.php';
		$storage = new Profile_image_storage(array(
			'storage_path' => $this->config->item('profile_image_storage_path'),
			'public_root' => FCPATH,
		));
		$upload_directory = $storage->ensure_storage_directory();
		if ($upload_directory === false) {
			$this->respond(503, 'profile_storage_unavailable', 'Penyimpanan foto profil belum siap.');
			return;
		}

		$max_size_kb = max(1, (int) $this->config->item('profile_image_max_size_kb'));
		$upload_config = array(
			'upload_path' => $upload_directory,
			'allowed_types' => 'jpg|jpeg|png|webp',
			'max_size' => $max_size_kb,
			'encrypt_name' => true,
			'detect_mime' => true,
			'mod_mime_fix' => true,
			'remove_spaces' => true,
		);
		$this->load->library('upload');
		$this->upload->initialize($upload_config, true);
		if (!$this->upload->do_upload('foto')) {
			$this->respond(422, 'photo_upload_rejected', 'Gunakan foto JPG, PNG, atau WebP maksimal 5 MB.');
			return;
		}

		$upload_data = $this->upload->data();
		$stored_key = $storage->stored_key_from_upload($upload_data);
		$uploaded_path = isset($upload_data['full_path']) ? (string) $upload_data['full_path'] : '';
		if (!$storage->upload_is_valid($uploaded_path, $stored_key, $max_size_kb)) {
			if ($uploaded_path !== '' && is_file($uploaded_path)) {
				@unlink($uploaded_path);
			}
			$this->respond(422, 'photo_content_invalid', 'Isi file foto tidak valid.');
			return;
		}
		@chmod($uploaded_path, 0600);

		$this->db->trans_begin();
		$locked_actor = $this->locked_user_row($user_id);
		$this->apply_effective_credential_state($locked_actor);
		if (!$locked_actor
			|| (string) $locked_actor->role !== $role
			|| (string) $locked_actor->status !== 'aktif'
			|| (int) $locked_actor->must_change_password === 1) {
			$this->db->trans_rollback();
			$storage->remove_private_file($stored_key);
			$this->respond(403, 'actor_denied', 'Akun belum dapat memperbarui foto profil.');
			return;
		}
		$photo_changed = ($locked_actor->foto === null) !== ($actor->foto === null)
			|| ($locked_actor->foto !== null && !hash_equals((string) $locked_actor->foto, (string) $actor->foto));
		if ($photo_changed) {
			$this->db->trans_rollback();
			$storage->remove_private_file($stored_key);
			$this->respond(500, 'photo_persist_failed', 'Foto profil belum dapat disimpan.');
			return;
		}

		$update_query = $this->db
			->where('userId', $user_id)
			->where('role', $role)
			->where('status', 'aktif');
		if ($locked_actor->foto === null) {
			$update_query->where('foto IS NULL', null, false);
		} else {
			$update_query->where('foto', (string) $locked_actor->foto);
		}
		$updated = $update_query->update('users', array('foto' => $stored_key));
		if (!$updated || $this->db->affected_rows() !== 1 || $this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$storage->remove_private_file($stored_key);
			$this->respond(500, 'photo_persist_failed', 'Foto profil belum dapat disimpan.');
			return;
		}
		$this->db->trans_commit();
		if ($this->db->trans_status() === false) {
			$storage->remove_private_file($stored_key);
			$this->respond(500, 'photo_persist_failed', 'Foto profil belum dapat disimpan.');
			return;
		}

		$previous_key = (string) $locked_actor->foto;
		if ($previous_key !== '' && !hash_equals($previous_key, $stored_key)) {
			$storage->remove_private_file($previous_key);
		}
		$this->session->set_userdata(array('foto' => $stored_key, 'picture' => $stored_key));
		$this->output->set_status_header(200)->set_output(json_encode(array(
			'status' => 'success',
			'success' => true,
			'message' => 'Foto profil diperbarui.',
			'photo_url' => base_url('profile/photo/' . $user_id),
		)));
	}

	private function profile_schema_ready()
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}
		foreach (array('userId', 'role', 'status', 'must_change_password', 'foto') as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				return false;
			}
		}
		return doclinc_nakes_credential_schema_allows_runtime($this->db);
	}

	private function respond($status, $code, $message)
	{
		$this->output->set_status_header((int) $status)->set_output(json_encode(array(
			'status' => 'error',
			'success' => false,
			'safe_error_code' => (string) $code,
			'message' => (string) $message,
		)));
	}

	private function user_row($user_id)
	{
		if ($user_id < 1) {
			return null;
		}
		return $this->db
			->select(
				'userId, role, status, must_change_password, '
					. doclinc_nakes_password_changed_at_projection($this->db) . ', foto',
				false
			)
			->where('userId', $user_id)
			->limit(1)
			->get('users')
			->row();
	}

	private function locked_user_row($user_id)
	{
		$query = $this->db->query(
			'SELECT userId, role, status, must_change_password, '
				. doclinc_nakes_password_changed_at_projection($this->db)
				. ', foto FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? FOR UPDATE',
			array((int) $user_id)
		);
		return $query ? $query->row() : null;
	}

	private function apply_effective_credential_state($user)
	{
		if (is_object($user)) {
			$user->must_change_password = doclinc_nakes_effective_must_change_password(
				$user->role,
				$user->must_change_password,
				$user->password_changed_at
			);
		}
	}
}
