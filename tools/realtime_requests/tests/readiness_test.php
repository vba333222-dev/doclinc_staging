<?php

require_once __DIR__ . '/MariaDbReadiness.php';

$passed = 0;
$failed = 0;
function readiness_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; fwrite(STDERR, "FAIL {$label}\n");
}

final class ReadinessProbeResult
{
	private $ready;
	public function __construct($ready) { $this->ready = (bool) $ready; }
	public function fetch_assoc() { return array('ready_value' => $this->ready ? 1 : 0); }
	public function close() {}
}

final class ReadinessProbeConnection
{
	public $connect_errno = 0;
	public $closed = false;
	private $ready;
	public function __construct($ready = true) { $this->ready = (bool) $ready; }
	public function query($sql) {
		if ($sql !== 'SELECT 1 AS ready_value') { throw new RuntimeException('unexpected_query'); }
		return new ReadinessProbeResult($this->ready);
	}
	public function close() { $this->closed = true; }
}

$now = 0;
$sleeps = array();
$closed_failures = array();
$connect_attempts = 0;
$delayed = MariaDbReadiness::wait(
	array('host' => 'fixture', 'user' => 'fixture', 'password' => 'not-reported', 'port' => 3306, 'timeout_ms' => 1000, 'interval_ms' => 100),
	function () use (&$connect_attempts, &$closed_failures) {
		$connect_attempts++;
		if ($connect_attempts < 3) {
			$connection = new ReadinessProbeConnection(false);
			$closed_failures[] = $connection;
			return $connection;
		}
		return new ReadinessProbeConnection(true);
	},
	function () use (&$now) { return $now; },
	function ($milliseconds) use (&$now, &$sleeps) { $sleeps[] = $milliseconds; $now += $milliseconds; }
);
readiness_expect($delayed->attempts === 3 && $connect_attempts === 3, 'delayed_availability_retries_then_succeeds');
readiness_expect($sleeps === array(100, 100) && $delayed->elapsed_ms === 200, 'delayed_retry_interval_bounded');
readiness_expect(count(array_filter($closed_failures, function ($connection) { return $connection->closed; })) === 2, 'failed_probe_connections_closed');
$delayed->connection->close();

$now = 0;
$attempts = 0;
$timed_out = false;
try {
	MariaDbReadiness::wait(
		array('password' => 'not-reported', 'timeout_ms' => 250, 'interval_ms' => 100),
		function () use (&$attempts) { $attempts++; throw new RuntimeException('authentication_failed'); },
		function () use (&$now) { return $now; },
		function ($milliseconds) use (&$now) { $now += $milliseconds; }
	);
} catch (RuntimeException $exception) {
	$timed_out = $exception->getMessage() === 'database_readiness_timeout';
}
readiness_expect($timed_out && $now === 250 && $attempts === 4, 'permanent_failure_times_out_nonzero_contract');
readiness_expect($attempts <= 4, 'retry_count_bounded');

$now = 0;
$sleep_count = 0;
$immediate = MariaDbReadiness::wait(
	array('timeout_ms' => 1000, 'interval_ms' => 100),
	function () { return new ReadinessProbeConnection(true); },
	function () use (&$now) { return $now; },
	function ($milliseconds) use (&$sleep_count) { $sleep_count++; }
);
readiness_expect($immediate->attempts === 1 && $sleep_count === 0, 'ready_immediately_does_not_sleep');
$immediate->connection->close();

$source = file_get_contents(__DIR__ . '/MariaDbReadiness.php');
readiness_expect(strpos($source, 'not-reported') === false && strpos($source, 'SELECT 1 AS ready_value') !== false, 'helper_contains_no_test_secret');

echo "REALTIME_REQUEST_READINESS_PASSED={$passed}\nREALTIME_REQUEST_READINESS_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
