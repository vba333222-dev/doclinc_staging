<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Chat_attachment_storage
{
	const PRIVATE_PREFIX = 'chat-images/';
	const LEGACY_PREFIX = 'uploads/chat_images/';

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
		if ($resolved === false || !$this->path_is_within($resolved, $this->storage_root) || !is_writable($resolved)) {
			return false;
		}

		$this->storage_root = rtrim(str_replace('\\', '/', $resolved), '/');
		return $this->storage_root . DIRECTORY_SEPARATOR;
	}

	public function stored_key_from_upload(array $upload_data)
	{
		$file_name = isset($upload_data['file_name']) ? basename((string) $upload_data['file_name']) : '';
		if (!$this->valid_file_name($file_name)) {
			return '';
		}

		return self::PRIVATE_PREFIX . $file_name;
	}

	public function safe_stored_key($value)
	{
		$value = trim(str_replace('\\', '/', (string) $value));
		if ($value === '' || strpos($value, '..') !== false || strpos($value, ':') !== false || strpos($value, '//') !== false || $value[0] === '/') {
			return '';
		}

		foreach (array(self::PRIVATE_PREFIX, self::LEGACY_PREFIX) as $prefix) {
			if (strpos($value, $prefix) !== 0) {
				continue;
			}
			$file_name = substr($value, strlen($prefix));
			return $this->valid_file_name($file_name) ? $prefix . $file_name : '';
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
			$root = $this->ensure_storage_directory();
			if ($root === false) {
				return false;
			}
			$file_name = substr($stored_key, strlen(self::PRIVATE_PREFIX));
			return $this->resolve_existing_file($root, $file_name);
		}

		$file_name = substr($stored_key, strlen(self::LEGACY_PREFIX));
		$legacy_root = $this->public_root . '/' . rtrim(self::LEGACY_PREFIX, '/');
		return $this->resolve_existing_file($legacy_root . '/', $file_name);
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

	private function resolve_existing_file($root, $file_name)
	{
		$root = rtrim(str_replace('\\', '/', (string) $root), '/');
		$resolved_root = realpath($root);
		$resolved_file = realpath($root . '/' . $file_name);
		if ($resolved_root === false || $resolved_file === false || !$this->path_is_within($resolved_file, $resolved_root) || !is_file($resolved_file) || !is_readable($resolved_file)) {
			return false;
		}

		return $resolved_file;
	}

	private function valid_file_name($file_name)
	{
		return is_string($file_name)
			&& preg_match('/^[a-f0-9]{16,64}\\.(?:jpe?g|png|webp)$/i', $file_name) === 1;
	}

	private function normalize_root($path)
	{
		$path = rtrim(str_replace('\\', '/', trim((string) $path)), '/');
		if ($path === '' || strpos($path, '/../') !== false || substr($path, -3) === '/..') {
			return '';
		}

		if (preg_match('#^(?:/|[A-Za-z]:/)#', $path) !== 1) {
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
