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

if (table_exists($db, 'call_sessions')) {
	echo "[skip] table exists `call_sessions`\n";
	exit(0);
}

$create_call_sessions_sql = "CREATE " . "TABLE `call_sessions` (
	`call_id` bigint unsigned NOT NULL AUTO_INCREMENT,
	`request_id` int(11) NOT NULL,
	`room_name` varchar(120) NOT NULL,
	`call_type` enum('audio','video') NOT NULL DEFAULT 'video',
	`caller_user_id` int(11) NOT NULL,
	`callee_user_id` int(11) NOT NULL,
	`status` enum('ringing','answered','ended','rejected','missed','failed') NOT NULL DEFAULT 'ringing',
	`started_at` datetime NULL,
	`answered_at` datetime NULL,
	`ended_at` datetime NULL,
	`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`call_id`),
	KEY `idx_call_sessions_request_status` (`request_id`, `status`),
	KEY `idx_call_sessions_callee_status` (`callee_user_id`, `status`),
	KEY `idx_call_sessions_room_name` (`room_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

run_query($db, $create_call_sessions_sql, "created table `call_sessions`");
