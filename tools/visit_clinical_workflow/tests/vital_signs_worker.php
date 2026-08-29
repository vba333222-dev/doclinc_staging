<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
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

require_once APPPATH . 'libraries/Visit_vital_signs_service.php';
require_once APPPATH . 'modules/home_nakes/models/Home_nakes_m.php';

$mode = (string) ($argv[1] ?? '');
$request_id = (int) ($argv[2] ?? 0);
$actor_user_id = (int) ($argv[3] ?? 0);
$announce_file = (string) ($argv[4] ?? '');
if (!in_array($mode, array('record', 'complete'), true)
	|| $request_id < 1
	|| $actor_user_id < 1
	|| $announce_file === '') {
	fwrite(STDERR, "invalid_worker_input\n");
	exit(2);
}

$connection = $app->db->query('SELECT CONNECTION_ID() AS id')->row();
$connection_id = $connection ? (int) $connection->id : 0;
$announce = array(
	'pid' => getmypid(),
	'connection_id' => $connection_id,
	'mode' => $mode,
);
$tmp = $announce_file . '.' . getmypid() . '.tmp';
file_put_contents($tmp, json_encode($announce, JSON_UNESCAPED_SLASHES), LOCK_EX);
rename($tmp, $announce_file);

$identity = doclinc_dokter_identity_context($actor_user_id, true);
if ($mode === 'record') {
	$result = (new Visit_vital_signs_service($app->db))->record(
		$request_id,
		$actor_user_id,
		$identity,
		array(
			'systolic' => 120,
			'diastolic' => 80,
			'pulse' => 72,
			'respiratory_rate' => 18,
			'temperature_c' => 36.7,
			'oxygen_saturation' => 98,
			'notes' => 'Synthetic Task 6B concurrency measurement',
		)
	);
} else {
	$result = (new Home_nakes_m())->update_visit_status(
		$request_id,
		$actor_user_id,
		'completed',
		$identity
	);
}

$payload = array(
	'ok' => isset($result['status']) && $result['status'] === 'success',
	'code' => $result['safe_error_code'] ?? ($result['code'] ?? null),
	'mode' => $mode,
	'request_id' => $request_id,
	'connection_id' => $connection_id,
	'pid' => getmypid(),
	'result' => $result,
);
echo json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
