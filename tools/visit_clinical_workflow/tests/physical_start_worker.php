<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$app->config->set('care_team_workflow_enabled', true);
if (!class_exists('MX_Controller')) {
    class MX_Controller {
        public $load;
        public $config;
        public $db;
        public function __construct() {
            $app = get_instance();
            $this->load = $app->load;
            $this->config = $app->config;
            $this->db = $app->db;
        }
    }
}
require_once APPPATH . 'modules/home_nakes/models/Home_nakes_m.php';

$requestId = (int) ($argv[1] ?? 0);
$actorUserId = (int) ($argv[2] ?? 0);
$targetStatus = (string) ($argv[3] ?? 'en_route');
$announceFile = (string) ($argv[4] ?? '');
if ($requestId < 1 || $actorUserId < 1 || $targetStatus !== 'en_route') {
    fwrite(STDERR, "invalid_worker_input\n");
    exit(2);
}

$connection = $app->db->query('SELECT CONNECTION_ID() AS id')->row();
if ($announceFile !== '') { file_put_contents($announceFile, json_encode(array('pid'=>getmypid(),'connection_id'=>$connection ? (int)$connection->id : 0)), LOCK_EX); }
$identity = doclinc_dokter_identity_context($actorUserId, true);
$result = (new Home_nakes_m())->update_visit_status($requestId, $actorUserId, $targetStatus, $identity);
$payload = array(
    'ok' => isset($result['status']) && $result['status'] === 'success',
    'code' => $result['safe_error_code'] ?? ($result['code'] ?? null),
    'request_id' => $requestId,
    'target_status' => $targetStatus,
    'connection_id' => $connection ? (int) $connection->id : 0,
    'pid' => getmypid(),
    'result' => $result,
);
echo json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
