<?php

class ClinicalPackagePathPolicy
{
	private $allowedRootsEnvironment;

	public function __construct($allowedRootsEnvironment)
	{
		$this->allowedRootsEnvironment = (string) $allowedRootsEnvironment;
	}

	public function resolveAndInventory($requestedRoot)
	{
		$allowedRaw = getenv($this->allowedRootsEnvironment);
		if ($allowedRaw === false || trim($allowedRaw) === '') {
			throw new ClinicalPackageException('package_allowed_roots_missing', 'Clinical package allowed roots are not configured.');
		}
		if (!$this->isAbsolutePath($requestedRoot) || preg_match('~(?:^|[\\\\/])\.\.(?:[\\\\/]|$)~', $requestedRoot) === 1) {
			throw new ClinicalPackageException('package_path_invalid', 'Package root must be an absolute traversal-free path.');
		}
		$this->assertNoLexicalSymlinkComponents($requestedRoot, 'package_root_symlink_rejected', 'Symlink package-root path components are prohibited.');
		$packageRoot = realpath($requestedRoot);
		if ($packageRoot === false || !is_dir($packageRoot) || !is_readable($packageRoot)) {
			throw new ClinicalPackageException('package_root_unavailable', 'Package root is unavailable.');
		}

		$allowedRoots = array();
		foreach (explode(PATH_SEPARATOR, $allowedRaw) as $candidate) {
			$candidate = trim($candidate);
			if ($candidate === '') {
				continue;
			}
			if (!$this->isAbsolutePath($candidate)) {
				throw new ClinicalPackageException('package_allowed_root_invalid', 'An allowed package root is invalid.');
			}
			$this->assertNoLexicalSymlinkComponents($candidate, 'package_allowed_root_invalid', 'Allowed package roots may not contain symlink components.');
			$resolved = realpath($candidate);
			if ($resolved === false || !is_dir($resolved)) {
				throw new ClinicalPackageException('package_allowed_root_invalid', 'An allowed package root is unavailable.');
			}
			$allowedRoots[] = $resolved;
		}
		if (count($allowedRoots) === 0) {
			throw new ClinicalPackageException('package_allowed_roots_missing', 'Clinical package allowed roots are empty.');
		}

		$matchedRoot = null;
		foreach ($allowedRoots as $allowedRoot) {
			if ($this->isInside($allowedRoot, $packageRoot)) {
				$matchedRoot = $allowedRoot;
				break;
			}
		}
		if ($matchedRoot === null) {
			throw new ClinicalPackageException('package_root_not_allowed', 'Package root is outside the configured allowlist.');
		}
		$this->assertNoSymlinkPathComponents($matchedRoot, $packageRoot);

		$inventory = array();
		$this->scanDirectory($packageRoot, '', $inventory);
		usort($inventory, function ($left, $right) {
			return strcmp($left['relative_path'], $right['relative_path']);
		});
		return array('root' => $packageRoot, 'files' => $inventory);
	}

	public function validateRelativePath($path)
	{
		$this->assertSafeRelativePath($path);
		return $path;
	}

	private function scanDirectory($root, $relativeDirectory, array &$inventory)
	{
		$absolute = $relativeDirectory === '' ? $root : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
		$entries = @scandir($absolute, SCANDIR_SORT_NONE);
		if ($entries === false) {
			throw new ClinicalPackageException('package_directory_read_failed', 'Package directory could not be inspected.');
		}
		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$relative = $relativeDirectory === '' ? $entry : $relativeDirectory . '/' . $entry;
			$this->assertSafeRelativePath($relative);
			$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
			if (is_link($path)) {
				throw new ClinicalPackageException('package_symlink_rejected', 'Symlinks inside a clinical package are prohibited.', array('path' => $relative));
			}
			$type = @filetype($path);
			if ($type === 'dir') {
				$this->scanDirectory($root, $relative, $inventory);
				continue;
			}
			if ($type !== 'file' || !is_file($path) || !is_readable($path)) {
				throw new ClinicalPackageException('package_special_file_rejected', 'Only readable regular package files are permitted.', array('path' => $relative));
			}
			if (is_executable($path)) {
				throw new ClinicalPackageException('package_executable_file_rejected', 'Executable package files are prohibited.', array('path' => $relative));
			}
			$size = @filesize($path);
			if ($size === false) {
				throw new ClinicalPackageException('package_file_stat_failed', 'Package file metadata is unavailable.', array('path' => $relative));
			}
			$inventory[] = array('relative_path' => $relative, 'absolute_path' => $path, 'byte_size' => (int) $size);
		}
	}

	private function assertNoSymlinkPathComponents($allowedRoot, $packageRoot)
	{
		$relative = substr($packageRoot, strlen(rtrim($allowedRoot, '/\\')));
		$current = rtrim($allowedRoot, '/\\');
		foreach (preg_split('/[\\\\\/]+/', trim($relative, '/\\')) as $component) {
			if ($component === '') {
				continue;
			}
			$current .= DIRECTORY_SEPARATOR . $component;
			if (is_link($current)) {
				throw new ClinicalPackageException('package_root_symlink_rejected', 'Symlink path components are prohibited.');
			}
		}
	}

	private function assertNoLexicalSymlinkComponents($path, $errorCode, $message)
	{
		$normalized = str_replace('\\', '/', $path);
		if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
			$current = substr($normalized, 0, 3);
			$remainder = substr($normalized, 3);
		} else {
			$current = DIRECTORY_SEPARATOR;
			$remainder = ltrim($normalized, '/');
		}
		foreach (explode('/', $remainder) as $component) {
			if ($component === '' || $component === '.') continue;
			$current = rtrim($current, '/\\') . DIRECTORY_SEPARATOR . $component;
			clearstatcache(true, $current);
			if (is_link($current)) {
				throw new ClinicalPackageException($errorCode, $message);
			}
		}
	}

	private function assertSafeRelativePath($path)
	{
		$valid = $path !== ''
			&& trim($path) === $path
			&& preg_match('/[\x00-\x1F\x7F]/', $path) !== 1
			&& $path[0] !== '/'
			&& strpos($path, '\\') === false
			&& preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) !== 1
			&& $path !== '.' && $path !== '..'
			&& strpos($path, '../') !== 0
			&& strpos($path, '/../') === false
			&& substr($path, -3) !== '/..';
		if (!$valid || preg_match('//u', $path) !== 1) {
			throw new ClinicalPackageException('package_relative_path_invalid', 'Package contains an unsafe relative path.');
		}
	}

	private function isInside($root, $path)
	{
		$root = rtrim($root, '/\\');
		if (DIRECTORY_SEPARATOR === '\\') {
			$root = strtolower($root);
			$path = strtolower($path);
		}
		return $path === $root || strpos($path, $root . DIRECTORY_SEPARATOR) === 0;
	}

	private function isAbsolutePath($path)
	{
		return is_string($path) && preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1;
	}
}
