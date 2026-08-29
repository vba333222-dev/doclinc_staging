<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);

if (!class_exists('MX_Controller')) {
	class MX_Controller
	{
		public $load;
		public $config;
		public $db;

		public function __construct()
		{
			$app = get_instance();
			$this->load = $app->load;
			$this->config = $app->config;
			$this->db = $app->db;
		}
	}
}

require_once APPPATH . 'modules/home_nakes/models/Home_nakes_m.php';

function ttv_concurrency_start($script, array $arguments, &$pipes)
{
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $script);
	foreach ($arguments as $argument) {
		$command .= ' ' . escapeshellarg((string) $argument);
	}
	$process = proc_open($command, array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	), $pipes);
	vcw_assert_true(is_resource($process), 'independent worker starts');
	return $process;
}

function ttv_concurrency_wait_file($path, $message)
{
	$deadline = microtime(true) + 15.0;
	while (!is_file($path) && microtime(true) < $deadline) {
		usleep(100000);
	}
	vcw_assert_true(is_file($path), $message);
	$payload = json_decode((string) file_get_contents($path), true);
	vcw_assert_true(is_array($payload) && !empty($payload['connection_id']), $message . ' payload');
	return $payload;
}

function ttv_concurrency_root_password()
{
	$inspect = json_decode((string) shell_exec('docker inspect visit_clinical_workflow-db-1'), true);
	foreach (($inspect[0]['Config']['Env'] ?? array()) as $entry) {
		if (strpos($entry, 'MARIADB_ROOT_PASSWORD=') === 0) {
			return substr($entry, strlen('MARIADB_ROOT_PASSWORD='));
		}
	}
	return '';
}

function ttv_concurrency_wait_edges($password)
{
	$sql = 'SELECT r.trx_mysql_thread_id,b.trx_mysql_thread_id'
		. ' FROM information_schema.INNODB_LOCK_WAITS w'
		. ' JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id'
		. ' JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id';
	$command = 'docker exec visit_clinical_workflow-db-1 mariadb -N -B -uroot -p'
		. escapeshellarg($password) . ' doclinc_visit_test -e ' . escapeshellarg($sql);
	$edges = array();
	foreach (preg_split('/\r?\n/', trim((string) shell_exec($command))) as $line) {
		if (preg_match('/^(\d+)\s+(\d+)$/', trim($line), $matches)) {
			$edges[] = array((int) $matches[1], (int) $matches[2]);
		}
	}
	return $edges;
}

function ttv_concurrency_wait_graph($password, array $expected, $message)
{
	$deadline = microtime(true) + 15.0;
	while (microtime(true) < $deadline) {
		$edges = ttv_concurrency_wait_edges($password);
		$all = true;
		foreach ($expected as $edge) {
			$found = false;
			foreach ($edges as $actual) {
				if ($actual[0] === $edge[0] && $actual[1] === $edge[1]) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				$all = false;
				break;
			}
		}
		if ($all) {
			return true;
		}
		usleep(100000);
	}
	vcw_assert_true(false, $message);
	return false;
}

function ttv_concurrency_collect($process, array $pipes, $message)
{
	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[0]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit = proc_close($process);
	vcw_assert_same(0, $exit, $message . ' exit; stderr=' . trim($error));
	$payload = json_decode(trim((string) $output), true);
	vcw_assert_true(is_array($payload), $message . ' JSON output');
	return $payload;
}

function ttv_concurrency_fixture($db, $base, $suffix)
{
	$facility = 'TTV-RACE-' . $suffix . '-' . $base;
	$request = $base;
	$command = $base + 1;
	$doctor = $base + 2;
	$performer = $base + 3;
	vcw_assert_safe_request_id($request);
	$db->query(
		'INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)',
		array($facility, 'TTV Race ' . $suffix, 'aktif')
	);
	foreach (array(array($doctor, 'dokter'), array($performer, 'Perawat')) as $staff) {
		$db->query(
			'INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)',
			array($staff[0], 'TTV Race', 'ttv-race-' . $staff[0] . '@example.invalid', 'ttv-race-' . $staff[0], 'x', 'dokter', 'aktif', 0, $facility)
		);
		$db->query(
			'INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)',
			array($staff[0], $facility, $staff[0], 'TTV Race Staff', $staff[1], 'aktif')
		);
		$db->query(
			'INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)',
			array($staff[0], $facility, '2026-01-01 00:00:00', 'active', $staff[0])
		);
	}
	$db->query(
		'INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)',
		array($command, 'TTV Race Command', 'ttv-race-command-' . $command . '@example.invalid', 'ttv-race-command-' . $command, 'x', 'dokter', 'aktif', 0, $facility)
	);
	$db->query(
		'INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
		array($request, $doctor, 'synthetic', 'Accepted', $facility, 'TTV Race', 'in_service', 'visit', $doctor, $performer)
	);
	$db->query(
		'INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))',
		array($request, $doctor, $doctor, $doctor, 'aktif')
	);
	$db->query(
		'INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key) VALUES (?,?,?,?,?,?)',
		array($request, 1, 'visit', 'routine', $doctor, 'ttv-race-disposition-' . $request)
	);
	$db->query(
		'INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))',
		array($request, $performer, $performer, $command, 'aktif')
	);
	return array(
		'request' => $request,
		'performer' => $performer,
		'assignment' => (int) $db->insert_id(),
	);
}

function ttv_concurrency_gate($fixture, $prefix)
{
	$ready = $prefix . '.gate-ready';
	$release = $prefix . '.gate-release';
	$pipes = array();
	$process = ttv_concurrency_start('row_lock_gate_worker.php', array(
		'performer_assignment', $fixture['assignment'], $ready, $release,
	), $pipes);
	$info = ttv_concurrency_wait_file($ready, 'performer assignment gate ready');
	return array(
		'process' => $process,
		'pipes' => $pipes,
		'release' => $release,
		'connection' => (int) $info['connection_id'],
		'pid' => (int) $info['pid'],
	);
}

$password = ttv_concurrency_root_password();
vcw_assert_true($password !== '', 'disposable MariaDB lock observer credential available');
$maximum = $db->query(
	'SELECT GREATEST(COALESCE((SELECT MAX(userId) FROM users),0),COALESCE((SELECT MAX(staff_id) FROM puskesmas_staff),0),COALESCE((SELECT MAX(request_id) FROM requests),0)) AS base_id'
)->row();
$base = (int) $maximum->base_id + 2000;
$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vcw-task6b-' . $base;
vcw_assert_true(mkdir($temp), 'Task 6B concurrency temp directory created');

// Ordering A: the TTV transaction owns the request lock and waits at the
// canonical performer row; completion must queue behind that request lock.
$first = ttv_concurrency_fixture($db, $base, 'TTV-FIRST');
$gateA = ttv_concurrency_gate($first, $temp . DIRECTORY_SEPARATOR . 'a');
$recordAPipes = array();
$recordA = ttv_concurrency_start('vital_signs_worker.php', array(
	'record', $first['request'], $first['performer'], $temp . DIRECTORY_SEPARATOR . 'a-record.json',
), $recordAPipes);
$recordAInfo = ttv_concurrency_wait_file($temp . DIRECTORY_SEPARATOR . 'a-record.json', 'TTV-first writer announced');
ttv_concurrency_wait_graph($password, array(
	array((int) $recordAInfo['connection_id'], $gateA['connection']),
), 'TTV-first writer waits on assignment gate');
$completeAPipes = array();
$completeA = ttv_concurrency_start('vital_signs_worker.php', array(
	'complete', $first['request'], $first['performer'], $temp . DIRECTORY_SEPARATOR . 'a-complete.json',
), $completeAPipes);
$completeAInfo = ttv_concurrency_wait_file($temp . DIRECTORY_SEPARATOR . 'a-complete.json', 'TTV-first completion announced');
ttv_concurrency_wait_graph($password, array(
	array((int) $recordAInfo['connection_id'], $gateA['connection']),
	array((int) $completeAInfo['connection_id'], (int) $recordAInfo['connection_id']),
), 'TTV-first deterministic wait graph');
file_put_contents($gateA['release'], 'release', LOCK_EX);
$recordAResult = ttv_concurrency_collect($recordA, $recordAPipes, 'TTV-first writer');
$completeAResult = ttv_concurrency_collect($completeA, $completeAPipes, 'TTV-first completion');
ttv_concurrency_collect($gateA['process'], $gateA['pipes'], 'TTV-first gate');
$firstRequest = $db->where('request_id', $first['request'])->get('requests')->row();
vcw_assert_true(!empty($recordAResult['ok']), 'TTV-first writer succeeds');
vcw_assert_true(!empty($completeAResult['ok']), 'TTV-first completion rechecks and succeeds');
vcw_assert_same('completed', (string) $firstRequest->visit_status, 'TTV-first final physical state');
vcw_assert_same('Accepted', (string) $firstRequest->request_status, 'TTV-first request remains Accepted');
vcw_assert_same(1, (int) $db->where('request_id', $first['request'])->count_all_results('request_vital_sign_measurements'), 'TTV-first persisted measurement');

// Ordering B: completion owns the request lock and waits at the performer
// row. The TTV writer queues behind the request, so the first completion
// check must fail atomically before the TTV insert can proceed.
$second = ttv_concurrency_fixture($db, $base + 100, 'COMPLETE-FIRST');
$gateB = ttv_concurrency_gate($second, $temp . DIRECTORY_SEPARATOR . 'b');
$completeBPipes = array();
$completeB = ttv_concurrency_start('vital_signs_worker.php', array(
	'complete', $second['request'], $second['performer'], $temp . DIRECTORY_SEPARATOR . 'b-complete.json',
), $completeBPipes);
$completeBInfo = ttv_concurrency_wait_file($temp . DIRECTORY_SEPARATOR . 'b-complete.json', 'completion-first worker announced');
ttv_concurrency_wait_graph($password, array(
	array((int) $completeBInfo['connection_id'], $gateB['connection']),
), 'completion-first worker waits on assignment gate');
$recordBPipes = array();
$recordB = ttv_concurrency_start('vital_signs_worker.php', array(
	'record', $second['request'], $second['performer'], $temp . DIRECTORY_SEPARATOR . 'b-record.json',
), $recordBPipes);
$recordBInfo = ttv_concurrency_wait_file($temp . DIRECTORY_SEPARATOR . 'b-record.json', 'completion-first TTV writer announced');
ttv_concurrency_wait_graph($password, array(
	array((int) $completeBInfo['connection_id'], $gateB['connection']),
	array((int) $recordBInfo['connection_id'], (int) $completeBInfo['connection_id']),
), 'completion-first deterministic wait graph');
file_put_contents($gateB['release'], 'release', LOCK_EX);
$completeBResult = ttv_concurrency_collect($completeB, $completeBPipes, 'completion-first worker');
$recordBResult = ttv_concurrency_collect($recordB, $recordBPipes, 'completion-first TTV writer');
ttv_concurrency_collect($gateB['process'], $gateB['pipes'], 'completion-first gate');
$secondBeforeRetry = $db->where('request_id', $second['request'])->get('requests')->row();
vcw_assert_same('vital_signs_required', $completeBResult['code'] ?? null, 'completion-first stable no-TTV result');
vcw_assert_true($secondBeforeRetry && (string) $secondBeforeRetry->visit_status === 'in_service' && (string) $secondBeforeRetry->request_status === 'Accepted', 'completion-first has no partial mutation');
vcw_assert_true(!empty($recordBResult['ok']), 'completion-first queued TTV writer succeeds');
$retry = (new Home_nakes_m())->update_visit_status(
	$second['request'],
	$second['performer'],
	'completed',
	doclinc_dokter_identity_context($second['performer'], true)
);
$secondAfterRetry = $db->where('request_id', $second['request'])->get('requests')->row();
vcw_assert_true(($retry['status'] ?? null) === 'success' && (string) $secondAfterRetry->visit_status === 'completed', 'explicit completion retry succeeds after TTV');

// Two valid writers are append-only. The request lock serializes both without
// converting the second valid measurement into a duplicate failure.
$third = ttv_concurrency_fixture($db, $base + 200, 'TWO-WRITERS');
$gateC = ttv_concurrency_gate($third, $temp . DIRECTORY_SEPARATOR . 'c');
$recordC1Pipes = array();
$recordC1 = ttv_concurrency_start('vital_signs_worker.php', array(
	'record', $third['request'], $third['performer'], $temp . DIRECTORY_SEPARATOR . 'c-record-1.json',
), $recordC1Pipes);
$recordC1Info = ttv_concurrency_wait_file($temp . DIRECTORY_SEPARATOR . 'c-record-1.json', 'first concurrent TTV writer announced');
ttv_concurrency_wait_graph($password, array(
	array((int) $recordC1Info['connection_id'], $gateC['connection']),
), 'first concurrent writer waits on assignment gate');
$recordC2Pipes = array();
$recordC2 = ttv_concurrency_start('vital_signs_worker.php', array(
	'record', $third['request'], $third['performer'], $temp . DIRECTORY_SEPARATOR . 'c-record-2.json',
), $recordC2Pipes);
$recordC2Info = ttv_concurrency_wait_file($temp . DIRECTORY_SEPARATOR . 'c-record-2.json', 'second concurrent TTV writer announced');
ttv_concurrency_wait_graph($password, array(
	array((int) $recordC1Info['connection_id'], $gateC['connection']),
	array((int) $recordC2Info['connection_id'], (int) $recordC1Info['connection_id']),
), 'concurrent writer deterministic wait graph');
file_put_contents($gateC['release'], 'release', LOCK_EX);
$recordC1Result = ttv_concurrency_collect($recordC1, $recordC1Pipes, 'first concurrent TTV writer');
$recordC2Result = ttv_concurrency_collect($recordC2, $recordC2Pipes, 'second concurrent TTV writer');
ttv_concurrency_collect($gateC['process'], $gateC['pipes'], 'concurrent TTV gate');
$measurements = $db->where('request_id', $third['request'])->order_by('measurement_id', 'ASC')->get('request_vital_sign_measurements')->result();
vcw_assert_true(!empty($recordC1Result['ok']) && !empty($recordC2Result['ok']), 'both concurrent TTV writers succeed');
vcw_assert_same(2, count($measurements), 'two concurrent measurements persisted');
vcw_assert_true((int) $measurements[0]->measurement_id !== (int) $measurements[1]->measurement_id, 'concurrent measurements have distinct ids');
foreach ($measurements as $measurement) {
	vcw_assert_true(
		(int) $measurement->measured_by_user_id === $third['performer']
		&& (int) $measurement->measured_by_staff_id === $third['performer']
		&& (int) $measurement->visit_performer_user_id === $third['performer']
		&& !empty($measurement->measured_at),
		'concurrent measurement has complete server attribution'
	);
}

$files = glob($temp . DIRECTORY_SEPARATOR . '*');
foreach ($files as $file) {
	if (is_file($file)) {
		unlink($file);
	}
}
rmdir($temp);

echo 'TTV_FIRST_GATE_PID=' . $gateA['pid'] . ' TTV_FIRST_GATE_CONNECTION=' . $gateA['connection']
	. ' TTV_FIRST_WRITER_PID=' . $recordAInfo['pid'] . ' TTV_FIRST_WRITER_CONNECTION=' . $recordAInfo['connection_id']
	. ' TTV_FIRST_COMPLETION_PID=' . $completeAInfo['pid'] . ' TTV_FIRST_COMPLETION_CONNECTION=' . $completeAInfo['connection_id'] . "\n";
echo 'COMPLETION_FIRST_GATE_PID=' . $gateB['pid'] . ' COMPLETION_FIRST_GATE_CONNECTION=' . $gateB['connection']
	. ' COMPLETION_FIRST_WORKER_PID=' . $completeBInfo['pid'] . ' COMPLETION_FIRST_WORKER_CONNECTION=' . $completeBInfo['connection_id']
	. ' COMPLETION_FIRST_TTV_PID=' . $recordBInfo['pid'] . ' COMPLETION_FIRST_TTV_CONNECTION=' . $recordBInfo['connection_id'] . "\n";
echo 'CONCURRENT_TTV_GATE_PID=' . $gateC['pid'] . ' CONCURRENT_TTV_GATE_CONNECTION=' . $gateC['connection']
	. ' CONCURRENT_TTV_1_PID=' . $recordC1Info['pid'] . ' CONCURRENT_TTV_1_CONNECTION=' . $recordC1Info['connection_id']
	. ' CONCURRENT_TTV_2_PID=' . $recordC2Info['pid'] . ' CONCURRENT_TTV_2_CONNECTION=' . $recordC2Info['connection_id'] . "\n";
echo "TTV_FIRST_WAIT_GRAPH=PASS\n";
echo "TTV_FIRST_COMPLETION_RACE=PASS\n";
echo "COMPLETION_FIRST_WAIT_GRAPH=PASS\n";
echo "COMPLETION_FIRST_TTV_RACE=PASS\n";
echo "COMPLETION_FIRST_NO_PARTIAL_MUTATION=PASS\n";
echo "COMPLETION_RETRY_AFTER_TTV=PASS\n";
echo "CONCURRENT_TTV_WAIT_GRAPH=PASS\n";
echo "CONCURRENT_VALID_TTV_WRITES_SAFE=PASS\n";
