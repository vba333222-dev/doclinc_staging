<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Profile_image_storage
{
	const PRIVATE_PREFIX = 'profile-images/';
	const LEGACY_PREFIX = 'uploads/profile/';

	private $storage_root;
	private $public_root;

	public function __construct(array $options = array())
	{
		$this->storage_root = $this->normalize_root(isset($options['storage_path']) ? $options['storage_path'] : '');
		$this->public_root = $this->normalize_root(isset($options['public_root']) ? $options['public_root'] : FCPATH);
	}

	public function ensure_storage_directory()
	{
		if ($this->storage_root === '' || $this->public_root === '' || $this->path_is_within($this->storage_root, $this->public_root)) {
			return false;
		}

		if (!is_dir($this->storage_root) && !@mkdir($this->storage_root, 0700, true)) {
			return false;
		}

		@chmod($this->storage_root, 0700);
		$resolved = realpath($this->storage_root);
		if ($resolved === false || $this->path_is_within($resolved, $this->public_root) || !is_writable($resolved)) {
			return false;
		}

		$this->storage_root = rtrim(str_replace('\\', '/', $resolved), '/');
		return $this->storage_root . DIRECTORY_SEPARATOR;
	}

	public function stored_key_from_upload(array $upload_data)
	{
		$file_name = isset($upload_data['file_name']) ? basename((string) $upload_data['file_name']) : '';
		return $this->valid_private_file_name($file_name) ? self::PRIVATE_PREFIX . $file_name : '';
	}

	public function safe_stored_key($value)
	{
		$value = trim(str_replace('\\', '/', (string) $value));
		if ($value === '' || strpos($value, '..') !== false || strpos($value, ':') !== false || strpos($value, '//') !== false || $value[0] === '/') {
			return '';
		}

		if ($this->valid_legacy_file_name($value)) {
			return self::LEGACY_PREFIX . $value;
		}

		foreach (array(self::PRIVATE_PREFIX, self::LEGACY_PREFIX) as $prefix) {
			if (strpos($value, $prefix) !== 0) {
				continue;
			}
			$file_name = substr($value, strlen($prefix));
			$valid = $prefix === self::PRIVATE_PREFIX
				? $this->valid_private_file_name($file_name)
				: $this->valid_legacy_file_name($file_name);
			return $valid ? $prefix . $file_name : '';
		}

		return '';
	}

	public function resolve_stored_file($stored_key)
	{
		$stored_key = $this->safe_stored_key($stored_key);
		if ($stored_key === '') {
			return false;
		}

		if (strpos($stored_key, self::PRIVATE_PREFIX) === 0) {
			$root = $this->existing_storage_directory();
			if ($root === false) {
				return false;
			}
			return $this->resolve_existing_file($root, substr($stored_key, strlen(self::PRIVATE_PREFIX)));
		}

		$legacy_root = $this->public_root . '/' . rtrim(self::LEGACY_PREFIX, '/');
		return $this->resolve_existing_file($legacy_root . '/', substr($stored_key, strlen(self::LEGACY_PREFIX)));
	}

	public function allowed_mime($path)
	{
		if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
			return '';
		}

		$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
		$mime = $finfo ? finfo_file($finfo, $path) : '';
		if ($finfo) {
			finfo_close($finfo);
		}
		$mime = is_string($mime) ? strtolower(trim($mime)) : '';

		return in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true) ? $mime : '';
	}

	public function upload_is_valid($path, $stored_key, $max_size_kb)
	{
		$stored_key = $this->safe_stored_key($stored_key);
		$size = is_file($path) ? filesize($path) : false;
		$mime = $this->allowed_mime($path);
		if ($stored_key === '' || strpos($stored_key, self::PRIVATE_PREFIX) !== 0 || $size === false || $size < 1 || $size > ((int) $max_size_kb * 1024)) {
			return false;
		}

		$extension = strtolower(pathinfo($stored_key, PATHINFO_EXTENSION));
		return ($mime === 'image/jpeg' && in_array($extension, array('jpg', 'jpeg'), true))
			|| ($mime === 'image/png' && $extension === 'png')
			|| ($mime === 'image/webp' && $extension === 'webp');
	}

	public function remove_private_file($stored_key)
	{
		$stored_key = $this->safe_stored_key($stored_key);
		if ($stored_key === '' || strpos($stored_key, self::PRIVATE_PREFIX) !== 0) {
			return false;
		}
		$path = $this->resolve_stored_file($stored_key);
		return $path !== false && @unlink($path);
	}

	private function existing_storage_directory()
	{
		if ($this->storage_root === '' || $this->public_root === '' || !is_dir($this->storage_root)) {
			return false;
		}

		$resolved = realpath($this->storage_root);
		if ($resolved === false || $this->path_is_within($resolved, $this->public_root) || !is_readable($resolved)) {
			return false;
		}

		return rtrim(str_replace('\\', '/', $resolved), '/') . DIRECTORY_SEPARATOR;
	}

	private function resolve_existing_file($root, $file_name)
	{
		$resolved_root = realpath(rtrim(str_replace('\\', '/', (string) $root), '/'));
		$resolved_file = realpath(rtrim((string) $root, '/\\') . DIRECTORY_SEPARATOR . $file_name);
		if ($resolved_root === false || $resolved_file === false || !$this->path_is_within($resolved_file, $resolved_root) || !is_file($resolved_file) || !is_readable($resolved_file)) {
			return false;
		}
		return $resolved_file;
	}

	private function valid_private_file_name($file_name)
	{
		return is_string($file_name) && preg_match('/^[a-f0-9]{16,64}\.(?:jpe?g|png|webp)$/i', $file_name) === 1;
	}

	private function valid_legacy_file_name($file_name)
	{
		return is_string($file_name)
			&& strlen($file_name) <= 160
			&& preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)$/i', $file_name) === 1;
	}

	private function normalize_root($path)
	{
		$path = rtrim(str_replace('\\', '/', trim((string) $path)), '/');
		if ($path === '' || strpos($path, '/../') !== false || substr($path, -3) === '/..' || preg_match('#^(?:/|[A-Za-z]:/)#', $path) !== 1) {
			return '';
		}
		$resolved = realpath($path);
		return $resolved !== false ? rtrim(str_replace('\\', '/', $resolved), '/') : $path;
	}

	private function path_is_within($path, $root)
	{
		$path = rtrim(str_replace('\\', '/', (string) $path), '/');
		$root = rtrim(str_replace('\\', '/', (string) $root), '/');
		return $path === $root || strpos($path . '/', $root . '/') === 0;
	}
}
