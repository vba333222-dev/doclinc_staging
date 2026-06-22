<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

$options = getopt('', array('host::', 'database::', 'user::', 'password::', 'port::'));

function option_or_env($options, $option, $env, $fallback = '')
{
	if (isset($options[$option]) && $options[$option] !== false && $options[$option] !== '') {
		return $options[$option];
	}

	foreach ($env as $name) {
		$value = getenv($name);
		if ($value !== false && $value !== '') {
			return $value;
		}
	}

	return $fallback;
}

$host = option_or_env($options, 'host', array('DB_HOST', 'ADMIN_DB_HOST'), 'localhost');
$database = option_or_env($options, 'database', array('DB_NAME', 'ADMIN_DB_NAME'));
$user = option_or_env($options, 'user', array('DB_USER', 'ADMIN_DB_USER'));
$password = option_or_env($options, 'password', array('DB_PASS', 'ADMIN_DB_PASS'));
$port = (int) option_or_env($options, 'port', array('DB_PORT', 'ADMIN_DB_PORT'), 3306);

if ($database === '' || $user === '') {
	fwrite(STDERR, "Missing database name or user.\n");
	exit(1);
}

if ($password === '') {
	fwrite(STDERR, "Missing database password. Set DB_PASS before running this migration.\n");
	exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	echo "Database connection OK.\n";
} catch (mysqli_sql_exception $e) {
	fwrite(STDERR, "Database connection failed. Check host, database, user, and DB_PASS.\n");
	fwrite(STDERR, "Driver message: " . $e->getMessage() . "\n");
	exit(1);
}

function table_exists($db, $table)
{
	$stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
	$stmt->bind_param('s', $table);
	$stmt->execute();
	$stmt->store_result();
	$exists = $stmt->num_rows > 0;
	$stmt->close();
	return $exists;
}

function run_query($db, $sql, $label)
{
	try {
		$db->query($sql);
		echo "[ok] " . $label . "\n";
	} catch (mysqli_sql_exception $e) {
		fwrite(STDERR, "[failed] " . $label . "\n");
		fwrite(STDERR, "Driver message: " . $e->getMessage() . "\n");
		exit(1);
	}
}

if (table_exists($db, 'notifications')) {
	echo "[skip] table exists `notifications`\n";
	exit(0);
}

run_query($db, "CREATE TABLE `notifications` (
	`notification_id` int(11) NOT NULL AUTO_INCREMENT,
	`recipient_user_id` int(11) NULL,
	`recipient_role` varchar(32) NULL,
	`recipient_puskesmas_code` varchar(32) NULL,
	`actor_user_id` int(11) NULL,
	`event_type` varchar(64) NOT NULL,
	`entity_type` varchar(64) NOT NULL,
	`entity_id` varchar(64) NOT NULL,
	`title` varchar(160) NOT NULL,
	`message` text NULL,
	`is_read` tinyint(1) NOT NULL DEFAULT 0,
	`read_at` datetime NULL,
	`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`notification_id`),
	KEY `idx_notifications_user_read_created` (`recipient_user_id`, `is_read`, `created_at`),
	KEY `idx_notifications_puskesmas_read_created` (`recipient_puskesmas_code`, `is_read`, `created_at`),
	KEY `idx_notifications_event_entity` (`event_type`, `entity_type`, `entity_id`),
	KEY `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "created table `notifications`");
