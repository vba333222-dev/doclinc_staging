<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Visit_proof_service
{
	private $db;
	private $storage_directory;

	public function __construct($db = null, $storage_directory = null)
	{
		$CI = null;
		if ($db === null) {
			$CI = &get_instance();
			if (!isset($CI->db) || !$CI->db) {
				$CI->load->database();
			}
			$db = $CI->db;
		}
		if ($storage_directory === null) {
			if ($CI === null) {
				$CI = &get_instance();
			}
			$storage_directory = isset($CI->config)
				? $CI->config->item('visit_proof_storage_path')
				: '';
		}
		$this->db = $db;
		$this->storage_directory = is_string($storage_directory) ? trim($storage_directory) : '';
	}

	public function schemaReady()
	{
		if (!$this->db || !$this->db->table_exists('consultation_visit_media')) {
			return false;
		}
		$required = array(
			'media_id', 'request_id', 'medicalrecord_id', 'uploaded_by_user_id',
			'media_type', 'storage_key', 'mime_type', 'size_bytes', 'sha256',
			'lifecycle_state', 'associated_at', 'finalized_at', 'failed_at', 'failure_code',
		);
		foreach ($required as $field) {
			if (!$this->db->field_exists($field, 'consultation_visit_media')) {
				return false;
			}
		}
		return true;
	}

	public function newStorageKey()
	{
		try {
			return bin2hex(random_bytes(32));
		} catch (Exception $exception) {
			return '';
		}
	}

	public function uploadConfig($storage_key)
	{
		if (!is_string($storage_key) || !preg_match('/^[a-f0-9]{64}$/', $storage_key)
			|| !$this->privateStorageReady()) {
			return false;
		}
		return array(
			'upload_path' => rtrim($this->storage_directory, '/\\') . DIRECTORY_SEPARATOR,
			'allowed_types' => 'jpg|jpeg',
			'max_size' => function_exists('doclinc_visit_proof_max_size_kb') ? doclinc_visit_proof_max_size_kb() : 5120,
			'file_name' => $storage_key,
			'overwrite' => false,
			'encrypt_name' => false,
			'detect_mime' => true,
			'mod_mime_fix' => true,
			'remove_spaces' => true,
		);
	}

	public function ensureStorageReady()
	{
		$directory = rtrim($this->storage_directory, '/\\');
		if (!$this->isAbsolutePath($directory)) {
			return false;
		}
		$web_root = realpath(FCPATH);
		$directory_normalized = rtrim(str_replace('\\', '/', $directory), '/') . '/';
		$web_root_normalized = $web_root === false ? '' : rtrim(str_replace('\\', '/', $web_root), '/') . '/';
		if ($web_root_normalized !== '' && strpos($directory_normalized, $web_root_normalized) === 0) {
			return false;
		}
		if (!is_dir($directory) && !@mkdir($directory, 0750, true)) {
			return false;
		}
		return $this->privateStorageReady();
	}

	public function stageUploadedImage($request_id, $user_id, array $upload_data, $expected_storage_key)
	{
		$request_id = (int) $request_id;
		$user_id = (int) $user_id;
		if (!$this->schemaReady() || $request_id < 1 || $user_id < 1
			|| !is_string($expected_storage_key)
			|| !preg_match('/^[a-f0-9]{64}$/', $expected_storage_key)) {
			return array('success' => false, 'reason' => 'schema_or_identity_invalid');
		}

		$full_path = isset($upload_data['full_path']) ? (string) $upload_data['full_path'] : '';
		$file_name = isset($upload_data['file_name']) ? basename((string) $upload_data['file_name']) : '';
		$storage_real = realpath($this->storage_directory);
		$file_real = $full_path !== '' ? realpath($full_path) : false;
		if ($full_path === '' || $file_name === '' || !is_file($full_path)
			|| $storage_real === false || $file_real === false
			|| dirname($file_real) !== $storage_real) {
			return array('success' => false, 'reason' => 'uploaded_file_missing');
		}
		$extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
		if (pathinfo($file_name, PATHINFO_FILENAME) !== $expected_storage_key
			|| !in_array($extension, array('jpg', 'jpeg'), true)) {
			@unlink($full_path);
			return array('success' => false, 'reason' => 'storage_key_mismatch');
		}

		$mime_type = $this->detectedMimeType($full_path);
		$allowed_mimes = array('image/jpeg');
		$image_info = @getimagesize($full_path);
		$size_bytes = @filesize($full_path);
		$max_bytes = (function_exists('doclinc_visit_proof_max_size_kb') ? doclinc_visit_proof_max_size_kb() : 5120) * 1024;
		$mime_extensions = array(
			'image/jpeg' => array('jpg', 'jpeg'),
		);
		$pixels = $image_info !== false && isset($image_info[0], $image_info[1])
			? (int) $image_info[0] * (int) $image_info[1]
			: 0;
		if (!in_array($mime_type, $allowed_mimes, true) || $image_info === false
			|| !isset($mime_extensions[$mime_type]) || !in_array($extension, $mime_extensions[$mime_type], true)
			|| $pixels < 1 || $pixels > 40000000
			|| !is_int($size_bytes) || $size_bytes < 1 || $size_bytes > $max_bytes) {
			@unlink($full_path);
			return array('success' => false, 'reason' => 'invalid_image_content');
		}
		$sha256 = @hash_file('sha256', $full_path);
		if (!is_string($sha256) || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
			@unlink($full_path);
			return array('success' => false, 'reason' => 'hash_failed');
		}

		$row = array(
			'request_id' => $request_id,
			'medicalrecord_id' => null,
			'uploaded_by_user_id' => $user_id,
			'media_type' => 'image',
			'storage_key' => $expected_storage_key,
			'mime_type' => $mime_type,
			'size_bytes' => $size_bytes,
			'sha256' => $sha256,
			'lifecycle_state' => 'pending',
		);
		if (!$this->db->insert('consultation_visit_media', $row)) {
			@unlink($full_path);
			return array('success' => false, 'reason' => 'metadata_insert_failed');
		}
		$media_id = (int) $this->db->insert_id();
		if ($media_id < 1) {
			@unlink($full_path);
			return array('success' => false, 'reason' => 'metadata_identity_missing');
		}

		return array(
			'success' => true,
			'media_id' => $media_id,
			'request_id' => $request_id,
			'uploaded_by_user_id' => $user_id,
			'storage_key' => $expected_storage_key,
			'file_name' => $file_name,
			'full_path' => $full_path,
			'mime_type' => $mime_type,
			'size_bytes' => $size_bytes,
			'sha256' => $sha256,
		);
	}

	public function failStaged(array $context, $failure_code)
	{
		$media_id = isset($context['media_id']) ? (int) $context['media_id'] : 0;
		$failure_code = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $failure_code));
		$failure_code = substr($failure_code !== '' ? $failure_code : 'completion_failed', 0, 64);
		if ($media_id > 0 && $this->schemaReady()) {
			$this->db
				->where('media_id', $media_id)
				->where('lifecycle_state', 'pending')
				->update('consultation_visit_media', array(
					'lifecycle_state' => 'failed',
					'failed_at' => date('Y-m-d H:i:s'),
					'failure_code' => $failure_code,
				));
		}
		$full_path = isset($context['full_path']) ? (string) $context['full_path'] : '';
		if ($full_path !== '' && is_file($full_path)) {
			@unlink($full_path);
		}
	}

	private function detectedMimeType($path)
	{
		if (class_exists('finfo')) {
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$mime = $finfo->file($path);
			if (is_string($mime) && $mime !== '') {
				return strtolower(trim($mime));
			}
		}
		if (function_exists('mime_content_type')) {
			$mime = @mime_content_type($path);
			return is_string($mime) ? strtolower(trim($mime)) : '';
		}
		return '';
	}

	private function privateStorageReady()
	{
		$directory = realpath($this->storage_directory);
		$web_root = realpath(FCPATH);
		if ($directory === false || $web_root === false || !is_dir($directory) || !is_writable($directory)) {
			return false;
		}
		$directory = rtrim(str_replace('\\', '/', $directory), '/') . '/';
		$web_root = rtrim(str_replace('\\', '/', $web_root), '/') . '/';
		return strpos($directory, $web_root) !== 0;
	}

	private function isAbsolutePath($path)
	{
		return is_string($path) && $path !== ''
			&& ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1);
	}
}
