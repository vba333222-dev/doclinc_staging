<?php

if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}
if (!function_exists('mysqli_report')) {
	fwrite(STDERR, "SAFE_ERROR_STAGE=mysqli_extension_missing\n");
	exit(1);
}

define('BASEPATH', __DIR__ . '/');
$sandbox = sys_get_temp_dir() . '/doclinc-chat-migration-integration-' . bin2hex(random_bytes(8));
$public_root = $sandbox . '/public';
$private_root = $sandbox . '/private/chat-attachments';
mkdir($public_root . '/uploads/chat_images', 0700, true);
define('FCPATH', $public_root . '/');

require_once dirname(__DIR__) . '/LegacyChatAttachmentMigrator.php';

$passed = 0;
$failed = 0;
$admin = null;
$database_connection = null;
$database_created = false;

function migration_integration_expect($condition, $label)
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

function migration_integration_cleanup($path)
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
			migration_integration_cleanup($target);
		} else {
			@unlink($target);
		}
	}
	@rmdir($path);
}

function migration_integration_env($name, $default = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $default;
}

$disposable = strtolower(trim(migration_integration_env('DOCLINC_CHAT_MIGRATION_DISPOSABLE_TEST')));
$database = strtolower(trim(migration_integration_env('DOCLINC_CHAT_MIGRATION_TEST_DB_NAME')));
if (!in_array($disposable, array('1', 'true', 'yes', 'on'), true)
	|| preg_match('/^doclinc_chat_attachment_test_[a-z0-9_]+$/', $database) !== 1) {
	fwrite(STDERR, "SAFE_ERROR_STAGE=disposable_database_required\n");
	exit(1);
}

$host = migration_integration_env('DOCLINC_CHAT_MIGRATION_TEST_DB_HOST', '127.0.0.1');
$port = (int) migration_integration_env('DOCLINC_CHAT_MIGRATION_TEST_DB_PORT', '3306');
$user = migration_integration_env('DOCLINC_CHAT_MIGRATION_TEST_DB_ADMIN_USER', 'root');
$password = migration_integration_env('DOCLINC_CHAT_MIGRATION_TEST_DB_ADMIN_PASSWORD');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$admin = new mysqli($host, $user, $password, '', $port);
	$password = null;
	$admin->set_charset('utf8mb4');
	$quoted_database = '`' . str_replace('`', '``', $database) . '`';
	$admin->query("CREATE DATABASE {$quoted_database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
	$database_created = true;

	$database_connection = new mysqli($host, $user, migration_integration_env('DOCLINC_CHAT_MIGRATION_TEST_DB_ADMIN_PASSWORD'), $database, $port);
	$database_connection->set_charset('utf8mb4');
	$database_connection->query(
		"CREATE TABLE consultation_messages (
			message_id int(11) NOT NULL AUTO_INCREMENT,
			message_type varchar(32) NOT NULL,
			message_text text NULL,
			attachment_path varchar(255) NULL,
			PRIMARY KEY (message_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
	);

	$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
	$file_name = str_repeat('d', 32) . '.png';
	$legacy_key = 'uploads/chat_images/' . $file_name;
	file_put_contents($public_root . '/' . $legacy_key, $png);
	$insert = $database_connection->prepare(
		"INSERT INTO consultation_messages (message_type, message_text, attachment_path)
		 VALUES ('image', ?, ?), ('image', ?, '')"
	);
	$insert->bind_param('sss', $legacy_key, $legacy_key, $legacy_key);
	$insert->execute();
	$insert->close();

	$storage = new Chat_attachment_storage(array(
		'storage_path' => $private_root,
		'public_root' => $public_root,
	));
	$migrator = new LegacyChatAttachmentMigrator(
		$database_connection,
		$storage,
		$public_root,
		$private_root,
		$sandbox . '/backup-success'
	);
	$result = $migrator->migrate(10);
	migration_integration_expect($result['rows_migrated'] === 2 && $result['files_migrated'] === 1, 'actual_mariadb_rows_migrated');
	$query = $database_connection->query("SELECT attachment_path, message_text FROM consultation_messages ORDER BY message_id");
	$rows = $query->fetch_all(MYSQLI_ASSOC);
	$query->close();
	$private_key = 'chat-images/' . $file_name;
	migration_integration_expect(count($rows) === 2
		&& $rows[0]['attachment_path'] === $private_key
		&& $rows[0]['message_text'] === $private_key
		&& $rows[1]['attachment_path'] === $private_key
		&& $rows[1]['message_text'] === $private_key, 'actual_mariadb_keys_exact');
	migration_integration_expect(is_file($private_root . '/' . $file_name)
		&& !is_file($public_root . '/' . $legacy_key), 'actual_filesystem_cutover_exact');
	migration_integration_expect(is_file($sandbox . '/backup-success/files/' . $file_name), 'actual_backup_persisted');

	$rollback_name = str_repeat('e', 32) . '.png';
	$rollback_key = 'uploads/chat_images/' . $rollback_name;
	file_put_contents($public_root . '/' . $rollback_key, $png);
	$rollback_insert = $database_connection->prepare(
		"INSERT INTO consultation_messages (message_type, message_text, attachment_path)
		 VALUES ('image', ?, ?)"
	);
	$rollback_insert->bind_param('ss', $rollback_key, $rollback_key);
	$rollback_insert->execute();
	$rollback_message_id = (int) $database_connection->insert_id;
	$rollback_insert->close();
	$database_connection->query(
		"CREATE TRIGGER force_chat_attachment_update_failure
		 BEFORE UPDATE ON consultation_messages FOR EACH ROW
		 BEGIN
			IF OLD.message_id = {$rollback_message_id} THEN
				SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced_update_failure';
			END IF;
		 END"
	);

	$rollback_migrator = new LegacyChatAttachmentMigrator(
		$database_connection,
		$storage,
		$public_root,
		$private_root,
		$sandbox . '/backup-rollback'
	);
	$rollback_failed = false;
	try {
		$rollback_migrator->migrate(10);
	} catch (Throwable $exception) {
		$rollback_failed = strpos($exception->getMessage(), 'forced_update_failure') !== false;
	}
	migration_integration_expect($rollback_failed, 'actual_trigger_failure_reaches_migrator');
	$rollback_query = $database_connection->query(
		"SELECT attachment_path, message_text FROM consultation_messages WHERE message_id = {$rollback_message_id}"
	);
	$rollback_row = $rollback_query->fetch_assoc();
	$rollback_query->close();
	migration_integration_expect($rollback_row['attachment_path'] === $rollback_key
		&& $rollback_row['message_text'] === $rollback_key, 'actual_database_rollback_exact');
	migration_integration_expect(is_file($public_root . '/' . $rollback_key)
		&& !is_file($private_root . '/' . $rollback_name), 'actual_filesystem_rollback_exact');
	$rollback_manifest = json_decode(file_get_contents($sandbox . '/backup-rollback/manifest.json'), true);
	migration_integration_expect(is_array($rollback_manifest)
		&& $rollback_manifest['status'] === 'rolled_back', 'actual_rollback_manifest_exact');
} catch (Throwable $exception) {
	$password = null;
	fwrite(STDERR, "SAFE_ERROR_STAGE=migration_integration_failed\n");
	$failed++;
} finally {
	$password = null;
	if ($database_connection instanceof mysqli) {
		try {
			$database_connection->query('DROP TRIGGER IF EXISTS force_chat_attachment_update_failure');
		} catch (Throwable $ignored) {
		}
		$database_connection->close();
	}
	if ($admin instanceof mysqli) {
		if ($database_created) {
			try {
				$quoted_database = '`' . str_replace('`', '``', $database) . '`';
				$admin->query("DROP DATABASE {$quoted_database}");
			} catch (Throwable $ignored) {
			}
		}
		$admin->close();
	}
	migration_integration_cleanup($sandbox);
}

echo "CHAT_ATTACHMENT_MIGRATION_INTEGRATION_PASSED={$passed}\n";
echo "CHAT_ATTACHMENT_MIGRATION_INTEGRATION_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
