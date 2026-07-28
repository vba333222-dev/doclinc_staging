<?php

if (!defined('BASEPATH')) {
	define('BASEPATH', dirname(__DIR__, 3) . '/system/');
}
require_once dirname(__DIR__, 2) . '/care_operations_foundation/CareOperationsFixture.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Realtime_outbox_writer.php';
require_once dirname(__DIR__) . '/RealtimeOutboxDispatcher.php';
require_once dirname(__DIR__) . '/CentrifugoTransport.php';
require_once dirname(__DIR__) . '/InMemoryRealtimeTransport.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$passed = 0;
$failed = 0;
$database = '';
$users = array();

function integration_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo 'PASS ' . $label . "\n";
		return;
	}
	$failed++;
	fwrite(STDERR, 'FAIL ' . $label . "\n");
}

function integration_env($name, $fallback = '')
{
	$value = getenv($name);
	return is_string($value) && $value !== '' ? $value : $fallback;
}

function integration_identifier($value)
{
	if (!is_string($value) || preg_match('/\A[a-zA-Z0-9_]+\z/', $value) !== 1) {
		throw new InvalidArgumentException('unsafe_test_identifier');
	}
	return '`' . $value . '`';
}

final class IntegrationCiDatabase
{
	public $db_debug = true;
	private $db;
	private $error = array('code' => 0);
	private $insert_id = 0;
	private $forced_failure;

	public function __construct(mysqli $db, $forced_failure = false)
	{
		$this->db = $db;
		$this->forced_failure = (bool) $forced_failure;
	}

	public function insert($table, array $data)
	{
		if ($this->forced_failure || $table !== 'realtime_outbox') {
			$this->error = array('code' => 1205);
			return false;
		}
		$sql = 'INSERT INTO realtime_outbox (event_type,aggregate_type,aggregate_id,audience_type,audience_key,payload_json,event_version,idempotency_key) VALUES (?,?,?,?,?,?,?,?)';
		try {
			$stmt = $this->db->prepare($sql);
			$stmt->bind_param('ssssssis', $data['event_type'], $data['aggregate_type'], $data['aggregate_id'],
				$data['audience_type'], $data['audience_key'], $data['payload_json'], $data['event_version'], $data['idempotency_key']);
			$stmt->execute();
			$this->insert_id = $stmt->insert_id;
			$stmt->close();
			$this->error = array('code' => 0);
			return true;
		} catch (mysqli_sql_exception $exception) {
			$this->error = array('code' => (int) $exception->getCode());
			return false;
		}
	}

	public function error()
	{
		return $this->error;
	}

	public function insert_id()
	{
		return $this->insert_id;
	}
}

function integration_event($reference, $aggregate_id = 1001)
{
	return array(
		'event_id' => 'evt.synthetic.' . $reference,
		'event_type' => 'request.assignment.changed',
		'aggregate_type' => 'request',
		'aggregate_id' => (string) $aggregate_id,
		'version' => 1,
		'invalidation' => 'assignment',
		'audience' => 'user:101',
	);
}

function integration_create_user(mysqli $admin, $database, $privileges)
{
	global $users;
	$user = 'outbox_' . bin2hex(random_bytes(4));
	$password = bin2hex(random_bytes(24));
	$account = "'" . $admin->real_escape_string($user) . "'@'%'";
	$admin->query('CREATE USER ' . $account . " IDENTIFIED BY '" . $admin->real_escape_string($password) . "'");
	$users[] = $user;
	$admin->query('GRANT ' . $privileges . ' ON ' . integration_identifier($database) . '.`realtime_outbox` TO ' . $account);
	return array($user, $password);
}

function integration_connect($database, $user, $password)
{
	$db = new mysqli(
		integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		$user,
		$password,
		$database,
		(int) integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306')
	);
	$db->set_charset('utf8mb4');
	return $db;
}

function integration_count(mysqli $db, $state = null)
{
	if ($state === null) {
		return (int) $db->query('SELECT COUNT(1) AS total FROM realtime_outbox')->fetch_assoc()['total'];
	}
	$stmt = $db->prepare('SELECT COUNT(1) AS total FROM realtime_outbox WHERE state=?');
	$stmt->bind_param('s', $state);
	$stmt->execute();
	$total = (int) $stmt->get_result()->fetch_assoc()['total'];
	$stmt->close();
	return $total;
}

function integration_run_plan()
{
	$command = array(PHP_BINARY, dirname(__DIR__) . '/realtime_outbox.php');
	$process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, dirname(__DIR__, 3), array());
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return array(proc_close($process), $stdout, $stderr);
}

$admin_password = integration_env('DOCLINC_TEST_DB_ADMIN_PASSWORD');
if ($admin_password === '') {
	fwrite(STDERR, "OUTBOX_INTEGRATION_RESULT=SKIP\nSAFE_ERROR_CODE=disposable_database_password_missing\n");
	exit(2);
}

try {
	$admin = new mysqli(
		integration_env('DOCLINC_TEST_DB_ADMIN_HOST', '127.0.0.1'),
		integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'),
		$admin_password,
		'',
		(int) integration_env('DOCLINC_TEST_DB_ADMIN_PORT', '3306')
	);
	$database = 'doclinc_realtime_outbox_test_' . bin2hex(random_bytes(5));
	$admin->query('CREATE DATABASE ' . integration_identifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$root = integration_connect($database, integration_env('DOCLINC_TEST_DB_ADMIN_USER', 'root'), $admin_password);
	CareOperationsFixture::createSchema($root);
	CareOperationsFixture::seedBaseline($root, 1, 1);
	foreach (care_operations_definitions() as $definition) {
		$root->query($definition['sql']);
	}
	$root->query('CREATE TABLE transaction_probe (probe_id int(11) NOT NULL PRIMARY KEY) ENGINE=InnoDB');
	$baseline = CareOperationsFixture::snapshot($root);
	$other_targets = array('consultation_visit_media', 'medicalrecord_diagnoses', 'nakes_presence', 'visit_location_updates');
	$other_before = array();
	foreach ($other_targets as $table) {
		$other_before[$table] = (int) $root->query('SELECT COUNT(1) AS total FROM ' . integration_identifier($table))->fetch_assoc()['total'];
	}

	list($app_user, $app_password) = integration_create_user($admin, $database, 'INSERT');
	list($worker_user, $worker_password) = integration_create_user($admin, $database, 'SELECT, UPDATE');
	list($missing_user, $missing_password) = integration_create_user($admin, $database, 'SELECT');
	list($excess_user, $excess_password) = integration_create_user($admin, $database, 'SELECT, UPDATE, DELETE');
	$app_db = integration_connect($database, $app_user, $app_password);
	$worker_db = integration_connect($database, $worker_user, $worker_password);
	$missing_db = integration_connect($database, $missing_user, $missing_password);
	$excess_db = integration_connect($database, $excess_user, $excess_password);
	$app_grants = $admin->query("SHOW GRANTS FOR '" . $admin->real_escape_string($app_user) . "'@'%'")->fetch_all(MYSQLI_NUM);
	$app_table_grants = array_values(array_filter(array_map(function ($row) {
		return stripos((string) $row[0], 'GRANT USAGE ON *.*') === 0 ? null : (string) $row[0];
	}, $app_grants)));
	integration_expect(count($app_table_grants) === 1
		&& preg_match('/\AGRANT INSERT ON `?' . preg_quote($database, '/') . '`?\.`?realtime_outbox`? TO /i', $app_table_grants[0]) === 1,
		'application_writer_insert_only_grant');

	RealtimeOutboxDispatcher::assertIdentityAndGrants($worker_db, $database, $worker_user, array($worker_user));
	integration_expect(true, 'dispatcher_exact_grants_accepted');
	try {
		RealtimeOutboxDispatcher::assertIdentityAndGrants($missing_db, $database, $missing_user, array($missing_user));
		integration_expect(false, 'dispatcher_missing_update_rejected');
	} catch (RuntimeException $exception) {
		integration_expect($exception->getMessage() === 'dispatcher_grants_incomplete', 'dispatcher_missing_update_rejected');
	}
	try {
		RealtimeOutboxDispatcher::assertIdentityAndGrants($excess_db, $database, $excess_user, array($excess_user));
		integration_expect(false, 'dispatcher_excess_delete_rejected');
	} catch (RuntimeException $exception) {
		integration_expect($exception->getMessage() === 'dispatcher_grants_excessive', 'dispatcher_excess_delete_rejected');
	}

	list($plan_code, $plan_stdout, $plan_stderr) = integration_run_plan();
	integration_expect($plan_code === 0 && strpos($plan_stdout, 'DATABASE_CONNECTION_OPENED=false') !== false
		&& strpos($plan_stdout, 'DATABASE_WRITE_EXECUTED=false') !== false
		&& strpos($plan_stdout, 'PUBLISH_EXECUTED=false') !== false && $plan_stderr === '', 'plan_zero_write_publish');

	$writer = new Realtime_outbox_writer();
	$feature = array('enabled' => true);
	$root_adapter = new IntegrationCiDatabase($root);
	$root->begin_transaction();
	$root->query('INSERT INTO transaction_probe (probe_id) VALUES (1)');
	$committed = $writer->enqueue($root_adapter, integration_event('commit'), array('PKM01'), $feature);
	$root->commit();
	integration_expect($committed['success'] && integration_count($root) === 1
		&& (int) $root->query('SELECT COUNT(1) AS total FROM transaction_probe')->fetch_assoc()['total'] === 1, 'enqueue_committed_with_caller_transaction');

	$root->begin_transaction();
	$root->query('INSERT INTO transaction_probe (probe_id) VALUES (2)');
	$rolled = $writer->enqueue($root_adapter, integration_event('rollback'), array('PKM01'), $feature);
	$root->rollback();
	integration_expect($rolled['success'] && integration_count($root) === 1
		&& (int) $root->query('SELECT COUNT(1) AS total FROM transaction_probe WHERE probe_id=2')->fetch_assoc()['total'] === 0, 'caller_rollback_removes_outbox_insert');

	$app_adapter = new IntegrationCiDatabase($app_db);
	$app_db->begin_transaction();
	$app_written = $writer->enqueue($app_adapter, integration_event('app-account'), array('PKM01'), $feature);
	$app_db->commit();
	integration_expect($app_written['success'], 'application_insert_only_account_enqueues');
	$duplicate = $writer->enqueue($app_adapter, integration_event('app-account'), array('PKM01'), $feature);
	integration_expect($duplicate['success'] && $duplicate['duplicate'] && integration_count($root) === 2, 'duplicate_idempotency_no_duplicate_row');

	$root->begin_transaction();
	$root->query('INSERT INTO transaction_probe (probe_id) VALUES (3)');
	$forced = $writer->enqueue(new IntegrationCiDatabase($root, true), integration_event('forced'), array('PKM01'), $feature);
	if (!$forced['success']) {
		$root->rollback();
	} else {
		$root->commit();
	}
	integration_expect(!$forced['success'] && (int) $root->query('SELECT COUNT(1) AS total FROM transaction_probe WHERE probe_id=3')->fetch_assoc()['total'] === 0
		&& integration_count($root) === 2, 'forced_insert_failure_caller_rollback');

	foreach (array('batch-a', 'batch-b', 'batch-c') as $reference) {
		$result = $writer->enqueue($root_adapter, integration_event($reference), array('PKM01'), $feature);
		integration_expect($result['success'], 'fixture_enqueue_' . $reference);
	}
	$transport = new InMemoryRealtimeTransport();
	$dispatcher = new RealtimeOutboxDispatcher($worker_db, $transport);
	$first = $dispatcher->run(2, 120, 5);
	integration_expect($first['claimed'] === 2 && $first['published'] === 2 && integration_count($root, 'published') === 2
		&& integration_count($root, 'pending') === 3, 'bounded_claim_batch');
	$second = $dispatcher->run(10, 120, 5);
	integration_expect($second['published'] === 3 && integration_count($root, 'published') === 5, 'successful_publish_marks_rows');
	$publish_count = $transport->publishCount();
	$empty = $dispatcher->run(10, 120, 5);
	integration_expect($empty['claimed'] === 0 && $transport->publishCount() === $publish_count, 'published_rows_not_republished');

	$lock_holder = $root->query("SELECT GET_LOCK('doclinc_realtime_outbox_dispatcher',0) AS acquired")->fetch_assoc();
	try {
		$dispatcher->run(1, 120, 5);
		integration_expect(false, 'concurrent_worker_lock_rejected');
	} catch (RuntimeException $exception) {
		integration_expect((int) $lock_holder['acquired'] === 1 && $exception->getMessage() === 'dispatcher_lock_unavailable', 'concurrent_worker_lock_rejected');
	}
	$root->query("SELECT RELEASE_LOCK('doclinc_realtime_outbox_dispatcher')");

	$centrifugo_calls = array();
	$centrifugo_transport = new CentrifugoTransport(
		'http://127.0.0.1:8000/api/publish',
		bin2hex(random_bytes(24)),
		100,
		200,
		function ($request) use (&$centrifugo_calls) {
			$centrifugo_calls[] = json_decode($request['body'], true);
			return array('status' => 200, 'body' => '{"result":{}}', 'failure' => '');
		}
	);
	$centrifugo_insert = $writer->enqueue($root_adapter, integration_event('centrifugo-success'), array('PKM01'), $feature);
	$centrifugo_summary = (new RealtimeOutboxDispatcher($worker_db, $centrifugo_transport))->run(1, 120, 5);
	$centrifugo_state = $root->query('SELECT state FROM realtime_outbox WHERE outbox_id=' . (int) $centrifugo_insert['outbox_id'])->fetch_assoc()['state'];
	integration_expect($centrifugo_insert['success'] && $centrifugo_summary['published'] === 1
		&& $centrifugo_state === 'published' && count($centrifugo_calls) === 1
		&& $centrifugo_calls[0]['idempotency_key'] === (new Realtime_outbox_contract())->prepare(integration_event('centrifugo-success'), array('PKM01'))['idempotency_key'],
		'centrifugo_success_marks_published');

	$permanent_insert = $writer->enqueue($root_adapter, integration_event('centrifugo-permanent'), array('PKM01'), $feature);
	$permanent_transport = new CentrifugoTransport('http://127.0.0.1:8000/api/publish', bin2hex(random_bytes(24)), 100, 200, function () {
		return array('status' => 403, 'body' => '{}', 'failure' => '');
	});
	$permanent_summary = (new RealtimeOutboxDispatcher($worker_db, $permanent_transport))->run(1, 120, 5);
	$permanent_row = $root->query('SELECT state,last_error_code FROM realtime_outbox WHERE outbox_id=' . (int) $permanent_insert['outbox_id'])->fetch_assoc();
	integration_expect($permanent_summary['failed'] === 1 && $permanent_row['state'] === 'failed'
		&& $permanent_row['last_error_code'] === 'centrifugo_auth_rejected', 'centrifugo_permanent_failure_marks_failed');

	$retry_insert = $writer->enqueue($root_adapter, integration_event('retry'), array('PKM01'), $feature);
	$retry_transport = new CentrifugoTransport('http://127.0.0.1:8000/api/publish', bin2hex(random_bytes(24)), 100, 200, function () {
		return array('status' => 503, 'body' => '{}', 'failure' => '');
	});
	$retry_dispatcher = new RealtimeOutboxDispatcher($worker_db, $retry_transport);
	$retry_summary = $retry_dispatcher->run(1, 120, 5);
	$retry_row = $root->query("SELECT state,attempt_count,last_error_code,(available_at>NOW(6)) AS is_delayed FROM realtime_outbox WHERE idempotency_key='" . $root->real_escape_string($contract_key = (new Realtime_outbox_contract())->prepare(integration_event('retry'), array('PKM01'))['idempotency_key']) . "'")->fetch_assoc();
	integration_expect($retry_insert['success'] && $retry_summary['retry_scheduled'] === 1 && $retry_row['state'] === 'pending'
		&& (int) $retry_row['attempt_count'] === 1 && (int) $retry_row['is_delayed'] === 1
		&& $retry_row['last_error_code'] === 'centrifugo_server_unavailable', 'centrifugo_retry_increments_attempt_and_delays');

	$stale = $writer->enqueue($root_adapter, integration_event('stale'), array('PKM01'), $feature);
	$root->query("UPDATE realtime_outbox SET state='claimed',claimed_at=DATE_SUB(NOW(6),INTERVAL 600 SECOND),attempt_count=1 WHERE outbox_id=" . (int) $stale['outbox_id']);
	$stale_transport = new InMemoryRealtimeTransport();
	$stale_summary = (new RealtimeOutboxDispatcher($worker_db, $stale_transport))->run(1, 30, 5);
	$stale_state = $root->query('SELECT state FROM realtime_outbox WHERE outbox_id=' . (int) $stale['outbox_id'])->fetch_assoc()['state'];
	integration_expect($stale_summary['stale_recovered'] === 1 && $stale_state === 'published', 'stale_lease_recovered');

	$terminal = $writer->enqueue($root_adapter, integration_event('terminal'), array('PKM01'), $feature);
	$root->query('UPDATE realtime_outbox SET attempt_count=4 WHERE outbox_id=' . (int) $terminal['outbox_id']);
	$terminal_transport = new CentrifugoTransport('http://127.0.0.1:8000/api/publish', bin2hex(random_bytes(24)), 100, 200, function () {
		return array('status' => 503, 'body' => '{}', 'failure' => '');
	});
	$terminal_summary = (new RealtimeOutboxDispatcher($worker_db, $terminal_transport))->run(1, 120, 5);
	$terminal_row = $root->query('SELECT state,attempt_count FROM realtime_outbox WHERE outbox_id=' . (int) $terminal['outbox_id'])->fetch_assoc();
	integration_expect($terminal_summary['failed'] === 1 && $terminal_row['state'] === 'failed'
		&& (int) $terminal_row['attempt_count'] === 5, 'maximum_attempts_terminal_state');

	$after = CareOperationsFixture::snapshot($root);
	integration_expect($after === $baseline, 'existing_baseline_unchanged');
	$other_after = array();
	foreach ($other_targets as $table) {
		$other_after[$table] = (int) $root->query('SELECT COUNT(1) AS total FROM ' . integration_identifier($table))->fetch_assoc()['total'];
	}
	integration_expect($other_after === $other_before, 'unrelated_target_tables_unchanged');

	$app_password = $worker_password = $missing_password = $excess_password = null;
} catch (Throwable $exception) {
	$failed++;
	fwrite(STDERR, "FAIL integration_unexpected\nSAFE_ERROR_CODE=outbox_integration_failed\n");
	fwrite(STDERR, 'DIAGNOSTIC_EXCEPTION_CLASS=' . get_class($exception) . "\n");
	fwrite(STDERR, 'DIAGNOSTIC_EXCEPTION_CODE=' . (int) $exception->getCode() . "\n");
} finally {
	$admin_password = null;
	foreach (array('app_db', 'worker_db', 'missing_db', 'excess_db', 'root') as $name) {
		if (isset($$name) && $$name instanceof mysqli) {
			$$name->close();
		}
	}
	if (isset($admin) && $admin instanceof mysqli) {
		if ($database !== '') {
			$admin->query('DROP DATABASE IF EXISTS ' . integration_identifier($database));
		}
		foreach ($users as $user) {
			$admin->query("DROP USER IF EXISTS '" . $admin->real_escape_string($user) . "'@'%'");
		}
		$admin->close();
	}
}

echo 'OUTBOX_INTEGRATION_TESTS_PASSED=' . $passed . "\n";
echo 'OUTBOX_INTEGRATION_TESTS_FAILED=' . $failed . "\n";
exit($failed === 0 ? 0 : 1);
