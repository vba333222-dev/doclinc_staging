<?php

require_once dirname(__DIR__, 2) . '/application/libraries/Chat_attachment_storage.php';

final class LegacyChatAttachmentMigrator
{
	private $db;
	private $storage;
	private $public_root;
	private $private_root;
	private $backup_root;

	public function __construct($db, Chat_attachment_storage $storage, $public_root, $private_root, $backup_root = '')
	{
		$this->db = $db;
		$this->storage = $storage;
		$this->public_root = $this->absoluteRoot($public_root, 'public_root_invalid');
		$this->private_root = $this->absoluteRoot($private_root, 'private_root_invalid');
		$this->backup_root = trim((string) $backup_root) === ''
			? ''
			: $this->absoluteRoot($backup_root, 'backup_root_invalid');
	}

	public function inspect($maximum_rows = 1000)
	{
		$rows = $this->legacyRows($maximum_rows, false);
		$truncated = count($rows) > (int) $maximum_rows;
		if ($truncated) {
			$rows = array_slice($rows, 0, (int) $maximum_rows);
		}
		$files = array();
		$missing_files = array();
		$recoverable_files = array();
		$invalid = 0;

		foreach ($rows as $row) {
			try {
				$key = $this->legacyKey($row);
				$files[$key] = true;
				if ($this->storage->resolve_stored_file($key) === false) {
					$file_name = substr($key, strlen(Chat_attachment_storage::LEGACY_PREFIX));
					$private_candidate = $this->private_root . '/' . $file_name;
					if (is_file($private_candidate) && is_readable($private_candidate)
						&& $this->storage->allowed_mime($private_candidate) !== '') {
						$recoverable_files[$key] = true;
					} else {
						$missing_files[$key] = true;
					}
				}
			} catch (Throwable $exception) {
				$invalid++;
			}
		}

		return array(
			'rows' => count($rows),
			'unique_files' => count($files),
			'missing_files' => count($missing_files),
			'recoverable_files' => count($recoverable_files),
			'invalid_rows' => $invalid,
			'truncated' => $truncated,
		);
	}

	public function migrate($maximum_rows = 1000)
	{
		if ($this->backup_root === '') {
			throw new RuntimeException('backup_root_required');
		}
		$this->assertSafeRoots();
		$private_path = $this->storage->ensure_storage_directory();
		if ($private_path === false) {
			throw new RuntimeException('private_storage_unavailable');
		}
		$this->ensureDirectory($this->backup_root, 0700, 'backup_directory_unavailable');
		$this->ensureDirectory($this->backup_root . '/files', 0700, 'backup_directory_unavailable');

		$created_private = array();
		$moved_legacy = array();
		$commit_attempted = false;
		$manifest = array(
			'version' => 1,
			'status' => 'prepared',
			'created_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
			'rows' => array(),
			'files' => array(),
		);

		$this->db->begin_transaction();
		try {
			$rows = $this->legacyRows($maximum_rows, true);
			if (count($rows) > (int) $maximum_rows) {
				throw new RuntimeException('legacy_row_limit_reached');
			}

			$file_plans = array();
			foreach ($rows as $row) {
				$legacy_key = $this->legacyKey($row);
				$file_name = substr($legacy_key, strlen(Chat_attachment_storage::LEGACY_PREFIX));
				$private_key = Chat_attachment_storage::PRIVATE_PREFIX . $file_name;
				$manifest['rows'][] = array(
					'message_id' => (int) $row['message_id'],
					'legacy_key' => $legacy_key,
					'private_key' => $private_key,
				);
				$file_plans[$legacy_key] = array(
					'legacy_key' => $legacy_key,
					'private_key' => $private_key,
					'file_name' => $file_name,
				);
			}

			foreach ($file_plans as $legacy_key => $plan) {
				$file_result = $this->moveFile($plan, $created_private, $moved_legacy);
				$manifest['files'][] = $file_result;
			}
			$this->writeManifest($manifest);

			$statement = $this->db->prepare(
				"UPDATE consultation_messages
				 SET attachment_path = ?, message_text = ?
				 WHERE message_id = ? AND message_type = 'image'"
			);
			if (!$statement) {
				throw new RuntimeException('database_statement_prepare_failed');
			}

			foreach ($rows as $row) {
				$legacy_key = $this->legacyKey($row);
				$file_name = substr($legacy_key, strlen(Chat_attachment_storage::LEGACY_PREFIX));
				$private_key = Chat_attachment_storage::PRIVATE_PREFIX . $file_name;
				$current_text = trim((string) $row['message_text']);
				if ($current_text !== '' && $current_text !== $legacy_key) {
					throw new RuntimeException('legacy_row_path_mismatch');
				}
				$message_id = (int) $row['message_id'];
				$statement->bind_param('ssi', $private_key, $private_key, $message_id);
				$statement->execute();
				if ((int) $statement->affected_rows !== 1) {
					throw new RuntimeException('legacy_row_update_conflict');
				}
			}
			$statement->close();

			$remaining = $this->legacyCount();
			if ($remaining !== 0) {
				throw new RuntimeException('legacy_rows_remaining');
			}
			$commit_attempted = true;
			$this->db->commit();
			$manifest['status'] = 'committed';
			$manifest['committed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
			$this->writeManifest($manifest);

			return array(
				'rows_migrated' => count($rows),
				'files_migrated' => count($file_plans),
				'legacy_rows_remaining' => 0,
				'manifest' => $this->backup_root . '/manifest.json',
			);
		} catch (Throwable $exception) {
			if ($commit_attempted) {
				throw new RuntimeException('migration_commit_state_verification_required', 0, $exception);
			}
			$rollback_ok = true;
			try {
				$this->db->rollback();
			} catch (Throwable $rollback_exception) {
				$rollback_ok = false;
			}
			try {
				$this->restoreFiles($created_private, $moved_legacy);
			} catch (Throwable $restore_exception) {
				$rollback_ok = false;
			}
			$manifest['status'] = $rollback_ok ? 'rolled_back' : 'rollback_failed';
			$manifest['safe_error_code'] = $this->safeErrorCode($exception->getMessage());
			$manifest['rolled_back_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
			try {
				$this->writeManifest($manifest);
			} catch (Throwable $manifest_exception) {
				$rollback_ok = false;
			}
			if (!$rollback_ok) {
				throw new RuntimeException('migration_rollback_failed', 0, $exception);
			}
			throw $exception;
		}
	}

	private function legacyRows($maximum_rows, $for_update)
	{
		$maximum_rows = filter_var($maximum_rows, FILTER_VALIDATE_INT);
		if ($maximum_rows === false || $maximum_rows < 1 || $maximum_rows > 10000) {
			throw new InvalidArgumentException('maximum_rows_invalid');
		}
		$sql = "SELECT message_id, message_text, attachment_path
			FROM consultation_messages
			WHERE message_type = 'image'
			AND (
				LEFT(TRIM(COALESCE(attachment_path, '')), 20) = 'uploads/chat_images/'
				OR (
					TRIM(COALESCE(attachment_path, '')) = ''
					AND LEFT(TRIM(COALESCE(message_text, '')), 20) = 'uploads/chat_images/'
				)
			)
			ORDER BY message_id ASC
			LIMIT " . ((int) $maximum_rows + 1);
		if ($for_update) {
			$sql .= ' FOR UPDATE';
		}
		$result = $this->db->query($sql);
		if (!$result) {
			throw new RuntimeException('legacy_row_query_failed');
		}
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		$result->close();
		return $rows;
	}

	private function legacyCount()
	{
		$result = $this->db->query(
			"SELECT COUNT(*) AS total
			 FROM consultation_messages
			 WHERE message_type = 'image'
			 AND (
				LEFT(TRIM(COALESCE(attachment_path, '')), 20) = 'uploads/chat_images/'
				OR LEFT(TRIM(COALESCE(message_text, '')), 20) = 'uploads/chat_images/'
			 )"
		);
		$row = $result ? $result->fetch_assoc() : null;
		if ($result) {
			$result->close();
		}
		if (!is_array($row)) {
			throw new RuntimeException('legacy_count_query_failed');
		}
		return (int) $row['total'];
	}

	private function legacyKey(array $row)
	{
		$path = trim((string) (isset($row['attachment_path']) ? $row['attachment_path'] : ''));
		$text = trim((string) (isset($row['message_text']) ? $row['message_text'] : ''));
		$path_key = strpos($path, Chat_attachment_storage::LEGACY_PREFIX) === 0
			? $this->storage->safe_stored_key($path)
			: '';
		$text_key = strpos($text, Chat_attachment_storage::LEGACY_PREFIX) === 0
			? $this->storage->safe_stored_key($text)
			: '';
		if ($path_key !== '' && $text_key !== '' && !hash_equals($path_key, $text_key)) {
			throw new RuntimeException('legacy_row_path_mismatch');
		}
		$key = $path_key !== '' ? $path_key : $text_key;
		if ($key === '') {
			throw new RuntimeException('legacy_key_invalid');
		}
		return $key;
	}

	private function moveFile(array $plan, array &$created_private, array &$moved_legacy)
	{
		$source = $this->storage->resolve_stored_file($plan['legacy_key']);
		$target = $this->private_root . '/' . $plan['file_name'];
		$backup = $this->backup_root . '/files/' . $plan['file_name'];
		$target_preexisting = is_file($target);

		if ($source === false && !$target_preexisting) {
			throw new RuntimeException('legacy_source_missing');
		}
		$reference = $source !== false ? $source : $target;
		$mime = $this->storage->allowed_mime($reference);
		$size = @filesize($reference);
		$sha256 = @hash_file('sha256', $reference);
		if ($mime === '' || $size === false || $size < 1 || !is_string($sha256) || strlen($sha256) !== 64) {
			throw new RuntimeException('legacy_file_invalid');
		}

		$this->verifiedCopy($reference, $backup, $sha256, 0600);
		if ($target_preexisting) {
			$this->assertFileHash($target, $sha256, 'private_target_conflict');
			if ($source !== false && !@unlink($source)) {
				throw new RuntimeException('legacy_source_remove_failed');
			}
		} else {
			$this->moveVerified($source, $target, $sha256);
			$created_private[] = $target;
		}
		@chmod($target, 0600);
		$moved_legacy[] = array('source' => $this->public_root . '/' . $plan['legacy_key'], 'backup' => $backup);

		return array(
			'legacy_key' => $plan['legacy_key'],
			'private_key' => $plan['private_key'],
			'sha256' => $sha256,
			'size' => (int) $size,
			'mime' => $mime,
		);
	}

	private function moveVerified($source, $target, $sha256)
	{
		if (!is_string($source) || !is_file($source)) {
			throw new RuntimeException('legacy_source_missing');
		}
		if (@rename($source, $target)) {
			$this->assertFileHash($target, $sha256, 'private_copy_verification_failed');
			return;
		}
		$this->verifiedCopy($source, $target, $sha256, 0600);
		if (!@unlink($source)) {
			@unlink($target);
			throw new RuntimeException('legacy_source_remove_failed');
		}
	}

	private function verifiedCopy($source, $target, $sha256, $mode)
	{
		if (is_file($target)) {
			$this->assertFileHash($target, $sha256, 'backup_target_conflict');
			return;
		}
		$temp = $target . '.tmp-' . bin2hex(random_bytes(8));
		if (!@copy($source, $temp)) {
			throw new RuntimeException('file_copy_failed');
		}
		@chmod($temp, $mode);
		try {
			$this->assertFileHash($temp, $sha256, 'file_copy_verification_failed');
			if (!@rename($temp, $target)) {
				throw new RuntimeException('file_copy_finalize_failed');
			}
		} catch (Throwable $exception) {
			@unlink($temp);
			throw $exception;
		}
	}

	private function restoreFiles(array $created_private, array $moved_legacy)
	{
		foreach (array_reverse($moved_legacy) as $move) {
			if (!is_file($move['source']) && is_file($move['backup'])) {
				$expected = @hash_file('sha256', $move['backup']);
				if (!is_string($expected) || !@copy($move['backup'], $move['source'])) {
					throw new RuntimeException('filesystem_restore_failed');
				}
				@chmod($move['source'], 0600);
				$this->assertFileHash($move['source'], $expected, 'filesystem_restore_failed');
			}
		}
		foreach (array_reverse($created_private) as $target) {
			if (is_file($target) && !@unlink($target)) {
				throw new RuntimeException('filesystem_restore_failed');
			}
		}
	}

	private function assertFileHash($path, $expected, $safe_error_code)
	{
		$actual = is_file($path) ? @hash_file('sha256', $path) : false;
		if (!is_string($actual) || !hash_equals($expected, $actual)) {
			throw new RuntimeException($safe_error_code);
		}
	}

	private function assertSafeRoots()
	{
		if ($this->pathWithin($this->private_root, $this->public_root)
			|| $this->pathWithin($this->backup_root, $this->public_root)
			|| $this->pathWithin($this->backup_root, $this->private_root)
			|| $this->pathWithin($this->private_root, $this->backup_root)) {
			throw new RuntimeException('migration_root_overlap');
		}
	}

	private function ensureDirectory($path, $mode, $safe_error_code)
	{
		if (!is_dir($path) && !@mkdir($path, $mode, true)) {
			throw new RuntimeException($safe_error_code);
		}
		@chmod($path, $mode);
		if (!is_dir($path) || !is_writable($path)) {
			throw new RuntimeException($safe_error_code);
		}
	}

	private function writeManifest(array $manifest)
	{
		if ($this->backup_root === '' || !is_dir($this->backup_root)) {
			return;
		}
		$json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		if (!is_string($json)) {
			throw new RuntimeException('manifest_encode_failed');
		}
		$temp = $this->backup_root . '/manifest.json.tmp';
		if (file_put_contents($temp, $json . "\n", LOCK_EX) === false) {
			throw new RuntimeException('manifest_write_failed');
		}
		@chmod($temp, 0600);
		if (!@rename($temp, $this->backup_root . '/manifest.json')) {
			@unlink($temp);
			throw new RuntimeException('manifest_finalize_failed');
		}
	}

	private function absoluteRoot($path, $safe_error_code)
	{
		$path = rtrim(str_replace('\\', '/', trim((string) $path)), '/');
		if ($path === '' || preg_match('#^(?:/|[A-Za-z]:/)#', $path) !== 1
			|| strpos($path, '/../') !== false || substr($path, -3) === '/..') {
			throw new InvalidArgumentException($safe_error_code);
		}
		$resolved = realpath($path);
		return $resolved === false ? $path : rtrim(str_replace('\\', '/', $resolved), '/');
	}

	private function pathWithin($path, $root)
	{
		$path = rtrim(str_replace('\\', '/', (string) $path), '/');
		$root = rtrim(str_replace('\\', '/', (string) $root), '/');
		return $path === $root || strpos($path . '/', $root . '/') === 0;
	}

	private function safeErrorCode($message)
	{
		$message = strtolower(trim((string) $message));
		return preg_match('/^[a-z0-9_]{3,80}$/', $message) === 1 ? $message : 'legacy_migration_failed';
	}
}
