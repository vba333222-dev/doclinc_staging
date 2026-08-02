<?php

if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');

if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 2) . '/system/');
}
if (!defined('FCPATH')) {
	define('FCPATH', dirname(__DIR__, 2) . '/');
}

require_once __DIR__ . '/LegacyChatAttachmentMigrator.php';

function chat_attachment_migration_fail($safe_error_code)
{
	$safe_error_code = strtolower(trim((string) $safe_error_code));
	if (preg_match('/^[a-z0-9_]{3,80}$/', $safe_error_code) !== 1) {
		$safe_error_code = 'legacy_migration_failed';
	}
	fwrite(STDERR, "CHAT_ATTACHMENT_MIGRATION=FAIL\nSAFE_ERROR_CODE={$safe_error_code}\n");
	exit(1);
}

function chat_attachment_migration_env($name, $default = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $default;
}

function chat_attachment_migration_database_allowed($environment, $database)
{
	if ($environment === 'staging') {
		return hash_equals('doclinc-staging', $database);
	}
	return $environment === 'test'
		&& preg_match('/^doclinc_chat_attachment_test_[a-z0-9_]+$/', strtolower($database)) === 1;
}

function chat_attachment_migration_absolute_path($path)
{
	$path = rtrim(str_replace('\\', '/', trim((string) $path)), '/');
	if ($path === '' || preg_match('#^(?:/|[A-Za-z]:/)#', $path) !== 1
		|| strpos($path, '/../') !== false || substr($path, -3) === '/..') {
		return '';
	}
	$resolved = realpath($path);
	return $resolved === false ? $path : rtrim(str_replace('\\', '/', $resolved), '/');
}

function chat_attachment_migration_path_within($path, $root)
{
	$path = chat_attachment_migration_absolute_path($path);
	$root = chat_attachment_migration_absolute_path($root);
	return $path !== '' && $root !== '' && $path !== $root && strpos($path . '/', $root . '/') === 0;
}

$options = getopt('', array(
	'inspect', 'apply', 'environment:', 'confirm-database::', 'backup-dir::', 'max-rows::',
));
$inspect = array_key_exists('inspect', $options);
$apply = array_key_exists('apply', $options);
if ($inspect === $apply) {
	chat_attachment_migration_fail('execution_mode_required');
}
if (!function_exists('mysqli_report')) {
	chat_attachment_migration_fail('mysqli_extension_missing');
}

$environment = strtolower(trim((string) (isset($options['environment']) ? $options['environment'] : '')));
$database = trim(chat_attachment_migration_env('DOCLINC_CHAT_MIGRATION_DB_NAME'));
$user = trim(chat_attachment_migration_env('DOCLINC_CHAT_MIGRATION_DB_USER'));
$allowed_users = array_values(array_filter(array_map(
	'trim',
	explode(',', chat_attachment_migration_env('DOCLINC_CHAT_MIGRATION_ALLOWED_USERS'))
)));
$password = chat_attachment_migration_env('DOCLINC_CHAT_MIGRATION_DB_PASSWORD');
$maximum_rows = isset($options['max-rows']) ? $options['max-rows'] : 1000;
$public_root = rtrim(str_replace('\\', '/', FCPATH), '/');
$private_root = trim(chat_attachment_migration_env(
	'DOCLINC_CHAT_ATTACHMENT_STORAGE_PATH',
	dirname(dirname($public_root)) . '/private/chat-attachments'
));
$backup_root = isset($options['backup-dir']) ? trim((string) $options['backup-dir']) : '';
$backup_base = trim(chat_attachment_migration_env('DOCLINC_CHAT_MIGRATION_BACKUP_ROOT'));

if (!chat_attachment_migration_database_allowed($environment, $database)) {
	chat_attachment_migration_fail('database_environment_denied');
}
if ($user === '' || !in_array($user, $allowed_users, true)) {
	chat_attachment_migration_fail('database_identity_denied');
}
if ($password === '') {
	chat_attachment_migration_fail('database_password_missing');
}
if ($apply) {
	$confirmed = isset($options['confirm-database']) ? trim((string) $options['confirm-database']) : '';
	if ($confirmed === '' || !hash_equals($database, $confirmed)) {
		chat_attachment_migration_fail('database_confirmation_mismatch');
	}
	$backup_base_resolved = $backup_base === '' ? false : realpath($backup_base);
	$backup_parent_resolved = $backup_root === '' ? false : realpath(dirname($backup_root));
	if ($backup_root === '' || $backup_base_resolved === false || !is_dir($backup_base_resolved)) {
		chat_attachment_migration_fail('backup_root_required');
	}
	$backup_base_resolved = rtrim(str_replace('\\', '/', $backup_base_resolved), '/');
	$backup_parent_resolved = $backup_parent_resolved === false
		? ''
		: rtrim(str_replace('\\', '/', $backup_parent_resolved), '/');
	if ($backup_parent_resolved !== $backup_base_resolved
		|| !chat_attachment_migration_path_within($backup_root, $backup_base_resolved)) {
		chat_attachment_migration_fail('backup_root_denied');
	}
}

$database_port = filter_var(
	chat_attachment_migration_env('DOCLINC_CHAT_MIGRATION_DB_PORT', '3306'),
	FILTER_VALIDATE_INT,
	array('options' => array('min_range' => 1, 'max_range' => 65535))
);
if ($database_port === false) {
	chat_attachment_migration_fail('database_port_invalid');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = null;
try {
	$db = new mysqli(
		chat_attachment_migration_env('DOCLINC_CHAT_MIGRATION_DB_HOST', '127.0.0.1'),
		$user,
		$password,
		$database,
		(int) $database_port
	);
	$password = null;
	$db->set_charset('utf8mb4');

	$storage = new Chat_attachment_storage(array(
		'storage_path' => $private_root,
		'public_root' => $public_root,
	));
	$migrator = new LegacyChatAttachmentMigrator(
		$db,
		$storage,
		$public_root,
		$private_root,
		$backup_root
	);

	if ($inspect) {
		$result = $migrator->inspect($maximum_rows);
		echo "CHAT_ATTACHMENT_MIGRATION=PASS\nEXECUTION_MODE=INSPECT\n";
		echo 'LEGACY_ROWS=' . (int) $result['rows'] . "\n";
		echo 'LEGACY_UNIQUE_FILES=' . (int) $result['unique_files'] . "\n";
		echo 'LEGACY_MISSING_FILES=' . (int) $result['missing_files'] . "\n";
		echo 'LEGACY_RECOVERABLE_FILES=' . (int) $result['recoverable_files'] . "\n";
		echo 'LEGACY_INVALID_ROWS=' . (int) $result['invalid_rows'] . "\n";
		echo 'RESULT_TRUNCATED=' . ($result['truncated'] ? 'true' : 'false') . "\n";
		echo "DATABASE_WRITE_EXECUTED=false\nFILESYSTEM_CHANGED=false\n";
		exit(($result['missing_files'] === 0 && $result['invalid_rows'] === 0 && !$result['truncated']) ? 0 : 1);
	}

	$result = $migrator->migrate($maximum_rows);
	echo "CHAT_ATTACHMENT_MIGRATION=PASS\nEXECUTION_MODE=APPLY\n";
	echo 'ROWS_MIGRATED=' . (int) $result['rows_migrated'] . "\n";
	echo 'FILES_MIGRATED=' . (int) $result['files_migrated'] . "\n";
	echo 'LEGACY_ROWS_REMAINING=' . (int) $result['legacy_rows_remaining'] . "\n";
	echo 'BACKUP_MANIFEST=' . $result['manifest'] . "\n";
	echo 'DATABASE_WRITE_EXECUTED=' . ($result['rows_migrated'] > 0 ? 'true' : 'false') . "\n";
	echo 'LEGACY_PUBLIC_FILES_RELOCATED=' . ($result['files_migrated'] > 0 ? 'true' : 'false') . "\n";
} catch (Throwable $exception) {
	$password = null;
	chat_attachment_migration_fail($exception->getMessage());
} finally {
	$password = null;
	if ($db instanceof mysqli) {
		$db->close();
	}
}
