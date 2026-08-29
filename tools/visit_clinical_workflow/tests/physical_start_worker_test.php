<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$app->config->set('care_team_workflow_enabled', true);
function pswt_user($db, $id, $facility, $staffId, $profession) {
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($id, 'H1a ' . $id, 'h1a' . $id . '@invalid', 'h1a' . $id, 'x', 'dokter', 'aktif', 0, $facility));
    if ($staffId > 0) {
        $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($staffId, $facility, $id, 'H1a staff', $profession, 'aktif'));
        $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($staffId, $facility, '2026-01-01', 'active', $id));
    }
}
if ((int) $app->db->query("SELECT COUNT(*) c FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nakes_facility_placements'")->row()->c === 0) {
    $app->db->query("CREATE TABLE nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,staff_id INT UNSIGNED NOT NULL,facility_code VARCHAR(64) NOT NULL,effective_from DATETIME NOT NULL,effective_until DATETIME NULL,status ENUM('active','ended') NOT NULL DEFAULT 'active',active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED,created_by_user_id INT NOT NULL,PRIMARY KEY(placement_id),UNIQUE KEY uq_nakes_active_staff(active_staff_key)) ENGINE=InnoDB");
}
$maximum = $app->db->query(
    'SELECT GREATEST(COALESCE((SELECT MAX(userId) FROM users),0),COALESCE((SELECT MAX(staff_id) FROM puskesmas_staff),0),COALESCE((SELECT MAX(request_id) FROM requests),0)) AS base_id'
)->row();
$base = (int) $maximum->base_id + 100;
$facility = 'H1A-' . $base; $request = $base; $doctor = $base + 1; $performer = $base + 2; $other = $base + 3; $command = $base - 1;
$db = $app->db;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'H1a Facility', 'aktif'));
pswt_user($db, $doctor, $facility, $doctor, 'dokter'); pswt_user($db, $performer, $facility, $performer, 'Perawat'); pswt_user($db, $other, $facility, $other, 'Perawat'); pswt_user($db, $command, $facility, 0, 'Perawat');
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode) VALUES (?,?,?,?,?,?,?,?)', array($request, $doctor, 'synthetic', 'Accepted', $facility, 'H1a Facility', 'not_started', 'visit'));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $doctor, $doctor, $doctor, 'aktif'));
$disposition = new Visit_disposition_service($db, new Visit_workflow_policy($db, true), true);
$created = $disposition->create($request, $doctor, array('decision' => 'visit', 'urgency' => 'routine'), 'h1a-disposition-' . $request); vcw_assert_true(!empty($created['ok']), 'fixture disposition');
$db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $performer, $performer, $command, 'aktif')); $db->where('request_id', $request)->update('requests', array('visit_performer_user_id' => $performer));
function pswt_run($request, $actor) {
    $spec = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/physical_start_worker.php') . ' ' . (int) $request . ' ' . (int) $actor . ' en_route';
    $pipes = array(); $parentPid = getmypid(); $process = proc_open($cmd, $spec, $pipes); vcw_assert_true(is_resource($process), 'worker process started');
    $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    $payload = json_decode(trim($stdout), true); vcw_assert_true(is_array($payload), 'worker JSON output stderr=' . trim($stderr) . ' stdout=' . trim($stdout));
    $payload['parent_pid'] = $parentPid; $payload['parent_connection_id'] = (int) get_instance()->db->query('SELECT CONNECTION_ID() AS id')->row()->id; return $payload;
}
$success = pswt_run($request, $performer); vcw_assert_true($success['ok'] === true, 'authorized worker success ' . json_encode($success)); vcw_assert_true((int)$success['connection_id'] !== (int)$success['parent_connection_id'], 'independent connection');
$state = $db->where('request_id', $request)->get('requests')->row(); vcw_assert_same('en_route', (string)$state->visit_status, 'worker mutation');
$request2 = $request + 10; $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode) VALUES (?,?,?,?,?,?,?,?)', array($request2, $doctor, 'synthetic', 'Accepted', $facility, 'H1a Facility', 'not_started', 'visit')); $db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key) VALUES (?,?,?,?,?,?)', array($request2,1,'visit','routine',$doctor,'h1a-disposition-'.$request2)); $db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request2,$performer,$performer,$command,'aktif')); $db->where('request_id',$request2)->update('requests',array('visit_performer_user_id'=>$performer));
$denied = pswt_run($request2, $other); vcw_assert_true($denied['ok'] === false, 'unauthorized worker denied'); vcw_assert_same('not_started', (string)$db->where('request_id',$request2)->get('requests')->row()->visit_status, 'denial no mutation');
echo "PHYSICAL_START_WORKER_INDEPENDENT_PROCESS=PASS\nPHYSICAL_START_WORKER_INDEPENDENT_DB_CONNECTION=PASS\nPHYSICAL_START_WORKER_CONNECTION_ID_VALID=PASS\nPHYSICAL_START_WORKER_SUCCESS=PASS\nPHYSICAL_START_WORKER_REAL_MUTATION=PASS\nPHYSICAL_START_WORKER_AUTH_DENIAL=PASS\n";
