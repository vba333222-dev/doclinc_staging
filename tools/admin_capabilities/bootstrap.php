<?php
if (PHP_SAPI !== 'cli') { exit('CLI only'); }

$options = getopt('', array('database:', 'user-id:', 'confirm:', 'host::', 'port::', 'user::', 'password::'));
$confirm = (string) ($options['confirm'] ?? '');
if (!isset($options['database'], $options['user-id']) || $confirm !== 'BOOTSTRAP_SUPER_ADMIN') {
	fwrite(STDERR, "Usage: php bootstrap.php --database=doclinc_admin_capability_test_name --user-id=N --confirm=BOOTSTRAP_SUPER_ADMIN [--host=... --user=... --password=...]\n");
	exit(2);
}
if (!in_array(strtolower(trim((string) getenv('DOCLINC_ADMIN_CAPABILITIES_BOOTSTRAP_ENABLED'))), array('1','true','yes','on'), true)) {
	fwrite(STDERR, "Bootstrap is disabled. Set DOCLINC_ADMIN_CAPABILITIES_BOOTSTRAP_ENABLED only for a reviewed disposable recovery run.\n");
	exit(1);
}
$database = (string) $options['database'];
if (preg_match('/\Adoclinc_admin_capability_test_[a-z0-9_]+\z/i', $database) !== 1) {
	fwrite(STDERR, "Refusing non-disposable database.\n");
	exit(1);
}
$host = (string) ($options['host'] ?? (getenv('DB_HOST') ?: 'localhost'));
$port = (int) ($options['port'] ?? (getenv('DB_PORT') ?: 3306));
$allowedHosts = array('localhost', '127.0.0.1', 'mariadb', 'db');
if (!in_array(strtolower(trim($host)), $allowedHosts, true)) { fwrite(STDERR, "Refusing non-local database host.\n"); exit(1); }
$user = (string) ($options['user'] ?? getenv('DB_USER'));
$password = (string) ($options['password'] ?? getenv('DB_PASS'));
if ($user === '' || $password === '') { fwrite(STDERR, "Database credentials are required via arguments or environment.\n"); exit(1); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
	$db->set_charset('utf8mb4');
	$identity = $db->query('SELECT DATABASE() AS database_name')->fetch_assoc();
	if (!$identity || !hash_equals($database, (string) $identity['database_name'])) throw new RuntimeException('database_identity_mismatch');
	$target = (int) $options['user-id'];
	$stmt = $db->prepare("SELECT userId FROM users WHERE userId = ? AND role = 'admin' AND status = 'aktif' LIMIT 1");
	$stmt->bind_param('i', $target); $stmt->execute();
	if (!$stmt->get_result()->fetch_assoc()) throw new RuntimeException('active_admin_not_found');
	$stmt->close();
	$db->query("INSERT INTO admin_user_capabilities (user_id, capability_code, granted_by_user_id, granted_at) VALUES (" . $target . ",'super_admin'," . $target . ",NOW()) ON DUPLICATE KEY UPDATE user_id=user_id");
	$metadata = $db->real_escape_string(json_encode(array('target_user_id'=>$target,'capability_code'=>'super_admin'), JSON_UNESCAPED_SLASHES));
	$db->query("INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata_json, created_at) VALUES (" . $target . ",'admin_capability_granted','admin_user_capability','" . $target . "','" . $metadata . "',NOW())");
	echo "BOOTSTRAP_RESULT=PASS\nDATABASE_WRITE_EXECUTED=true\n";
	$db->close();
} catch (Throwable $e) {
	fwrite(STDERR, "BOOTSTRAP_RESULT=FAIL\nREASON=" . preg_replace('/[^a-z0-9_]/i', '_', $e->getMessage()) . "\n");
	exit(1);
}
