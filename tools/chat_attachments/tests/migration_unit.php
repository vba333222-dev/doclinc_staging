<?php

define('BASEPATH', __DIR__ . '/');

$sandbox = sys_get_temp_dir() . '/doclinc-chat-migration-' . bin2hex(random_bytes(8));
$public_root = $sandbox . '/public';
$private_root = $sandbox . '/private/chat-attachments';
$backup_root = $sandbox . '/backup';
mkdir($public_root . '/uploads/chat_images', 0700, true);
define('FCPATH', $public_root . '/');

require_once dirname(__DIR__) . '/LegacyChatAttachmentMigrator.php';

$passed = 0;
$failed = 0;

function migration_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$label}\n";
		return;
	}
	$failed++;
	echo "FAIL {$label}\n";
}

function migration_cleanup($path)
{
	if (!is_dir($path)) {
		return;
	}
	foreach (scandir($path) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		$target = $path . DIRECTORY_SEPARATOR . $entry;
		if (is_dir($target) && !is_link($target)) {
			migration_cleanup($target);
		} else {
			@unlink($target);
		}
	}
	@rmdir($path);
}

final class MigrationFakeResult
{
	private $rows;
	private $index = 0;

	public function __construct(array $rows)
	{
		$this->rows = array_values($rows);
	}

	public function fetch_assoc()
	{
		if (!isset($this->rows[$this->index])) {
			return null;
		}
		return $this->rows[$this->index++];
	}

	public function close()
	{
	}
}

final class MigrationFakeStatement
{
	private $database;
	private $path;
	private $text;
	private $message_id;
	public $affected_rows = 0;

	public function __construct(MigrationFakeDatabase $database)
	{
		$this->database = $database;
	}

	public function bind_param($types, &$path, &$text, &$message_id)
	{
		$this->path =& $path;
		$this->text =& $text;
		$this->message_id =& $message_id;
		return true;
	}

	public function execute()
	{
		$this->affected_rows = $this->database->updateRow(
			(int) $this->message_id,
			(string) $this->path,
			(string) $this->text
		);
		return true;
	}

	public function close()
	{
	}
}

final class MigrationFakeDatabase
{
	public $rows;
	public $fail_on_update = 0;
	private $snapshot;
	private $updates = 0;

	public function __construct(array $rows)
	{
		$this->rows = $rows;
	}

	public function begin_transaction()
	{
		$this->snapshot = $this->rows;
		return true;
	}

	public function commit()
	{
		$this->snapshot = null;
		return true;
	}

	public function rollback()
	{
		if (is_array($this->snapshot)) {
			$this->rows = $this->snapshot;
		}
		$this->snapshot = null;
		return true;
	}

	public function query($sql)
	{
		if (stripos($sql, 'COUNT(*) AS total') !== false) {
			return new MigrationFakeResult(array(array('total' => count($this->legacyRows()))));
		}
		if (stripos($sql, 'SELECT message_id, message_text, attachment_path') !== false) {
			preg_match('/LIMIT\s+([0-9]+)/i', $sql, $match);
			$limit = isset($match[1]) ? (int) $match[1] : 1001;
			return new MigrationFakeResult(array_slice($this->legacyRows(), 0, $limit));
		}
		throw new RuntimeException('unexpected_query');
	}

	public function prepare($sql)
	{
		return new MigrationFakeStatement($this);
	}

	public function updateRow($message_id, $path, $text)
	{
		$this->updates++;
		if ($this->fail_on_update > 0 && $this->updates === $this->fail_on_update) {
			throw new RuntimeException('forced_update_failure');
		}
		foreach ($this->rows as &$row) {
			if ((int) $row['message_id'] === $message_id && (string) $row['message_type'] === 'image') {
				$row['attachment_path'] = $path;
				$row['message_text'] = $text;
				unset($row);
				return 1;
			}
		}
		unset($row);
		return 0;
	}

	private function legacyRows()
	{
		$legacy = array();
		foreach ($this->rows as $row) {
			$path = trim((string) $row['attachment_path']);
			$text = trim((string) $row['message_text']);
			if ((string) $row['message_type'] === 'image'
				&& (strpos($path, 'uploads/chat_images/') === 0
					|| ($path === '' && strpos($text, 'uploads/chat_images/') === 0))) {
				$legacy[] = array(
					'message_id' => $row['message_id'],
					'message_text' => $row['message_text'],
					'attachment_path' => $row['attachment_path'],
				);
			}
		}
		usort($legacy, function ($left, $right) {
			return (int) $left['message_id'] <=> (int) $right['message_id'];
		});
		return $legacy;
	}
}

function migration_storage($public_root, $private_root)
{
	return new Chat_attachment_storage(array(
		'storage_path' => $private_root,
		'public_root' => $public_root,
	));
}

try {
	$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
	$file_name = str_repeat('a', 32) . '.png';
	$legacy_key = 'uploads/chat_images/' . $file_name;
	file_put_contents($public_root . '/' . $legacy_key, $png);
	$db = new MigrationFakeDatabase(array(
		array('message_id' => 1, 'message_type' => 'image', 'message_text' => $legacy_key, 'attachment_path' => $legacy_key),
		array('message_id' => 2, 'message_type' => 'image', 'message_text' => $legacy_key, 'attachment_path' => ''),
	));
	$migrator = new LegacyChatAttachmentMigrator(
		$db,
		migration_storage($public_root, $private_root),
		$public_root,
		$private_root,
		$backup_root
	);
	$inspection = $migrator->inspect(10);
	migration_expect($inspection['rows'] === 2 && $inspection['unique_files'] === 1, 'inspect_counts_rows_and_unique_files');
	migration_expect($inspection['missing_files'] === 0 && $inspection['invalid_rows'] === 0, 'inspect_validates_legacy_files');
	$result = $migrator->migrate(10);
	migration_expect($result['rows_migrated'] === 2 && $result['files_migrated'] === 1, 'duplicate_file_references_migrate_once');
	migration_expect(!is_file($public_root . '/' . $legacy_key), 'legacy_public_file_removed');
	migration_expect(is_file($private_root . '/' . $file_name), 'private_file_created');
	migration_expect(is_file($backup_root . '/files/' . $file_name), 'verified_backup_created');
	migration_expect($db->rows[0]['attachment_path'] === 'chat-images/' . $file_name
		&& $db->rows[1]['message_text'] === 'chat-images/' . $file_name, 'database_keys_updated');
	$manifest = json_decode(file_get_contents($backup_root . '/manifest.json'), true);
	migration_expect(is_array($manifest) && $manifest['status'] === 'committed', 'committed_manifest_written');
	migration_expect($migrator->inspect(10)['rows'] === 0, 'rerun_is_idempotent');

	$rollback_root = $sandbox . '/rollback';
	$rollback_public = $rollback_root . '/public';
	$rollback_private = $rollback_root . '/private/chat-attachments';
	$rollback_backup = $rollback_root . '/backup';
	mkdir($rollback_public . '/uploads/chat_images', 0700, true);
	$rollback_name = str_repeat('b', 32) . '.png';
	$rollback_key = 'uploads/chat_images/' . $rollback_name;
	file_put_contents($rollback_public . '/' . $rollback_key, $png);
	$rollback_db = new MigrationFakeDatabase(array(
		array('message_id' => 3, 'message_type' => 'image', 'message_text' => $rollback_key, 'attachment_path' => $rollback_key),
	));
	$rollback_db->fail_on_update = 1;
	$rollback_migrator = new LegacyChatAttachmentMigrator(
		$rollback_db,
		migration_storage($rollback_public, $rollback_private),
		$rollback_public,
		$rollback_private,
		$rollback_backup
	);
	$rollback_error = '';
	try {
		$rollback_migrator->migrate(10);
	} catch (Throwable $exception) {
		$rollback_error = $exception->getMessage();
	}
	migration_expect($rollback_error === 'forced_update_failure', 'database_failure_is_visible');
	migration_expect($rollback_db->rows[0]['attachment_path'] === $rollback_key, 'database_rollback_restores_key');
	migration_expect(is_file($rollback_public . '/' . $rollback_key), 'filesystem_rollback_restores_legacy_file');
	migration_expect(!is_file($rollback_private . '/' . $rollback_name), 'filesystem_rollback_removes_new_private_file');
	$rollback_manifest = json_decode(file_get_contents($rollback_backup . '/manifest.json'), true);
	migration_expect(is_array($rollback_manifest) && $rollback_manifest['status'] === 'rolled_back', 'rollback_manifest_written');

	$recovery_root = $sandbox . '/recovery';
	$recovery_public = $recovery_root . '/public';
	$recovery_private = $recovery_root . '/private/chat-attachments';
	$recovery_backup = $recovery_root . '/backup';
	mkdir($recovery_public . '/uploads/chat_images', 0700, true);
	mkdir($recovery_private, 0700, true);
	$recovery_name = str_repeat('c', 32) . '.png';
	$recovery_key = 'uploads/chat_images/' . $recovery_name;
	file_put_contents($recovery_private . '/' . $recovery_name, $png);
	$recovery_db = new MigrationFakeDatabase(array(
		array('message_id' => 4, 'message_type' => 'image', 'message_text' => $recovery_key, 'attachment_path' => $recovery_key),
	));
	$recovery_migrator = new LegacyChatAttachmentMigrator(
		$recovery_db,
		migration_storage($recovery_public, $recovery_private),
		$recovery_public,
		$recovery_private,
		$recovery_backup
	);
	$recovery_inspection = $recovery_migrator->inspect(10);
	migration_expect($recovery_inspection['missing_files'] === 0
		&& $recovery_inspection['recoverable_files'] === 1, 'partial_move_is_detected_as_recoverable');
	$recovery_result = $recovery_migrator->migrate(10);
	migration_expect($recovery_result['rows_migrated'] === 1
		&& $recovery_db->rows[0]['attachment_path'] === 'chat-images/' . $recovery_name, 'partial_move_rerun_completes');
} finally {
	migration_cleanup($sandbox);
}

echo "CHAT_ATTACHMENT_MIGRATION_UNIT_PASSED={$passed}\n";
echo "CHAT_ATTACHMENT_MIGRATION_UNIT_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
