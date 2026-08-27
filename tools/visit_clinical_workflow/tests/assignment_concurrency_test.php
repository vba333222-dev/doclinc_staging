<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
require_once APPPATH . 'libraries/Visit_assignment_service.php';

if ((int) $db->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nakes_facility_placements'")->row()->c !== 1) {
    $db->query("CREATE TABLE nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, staff_id INT UNSIGNED NOT NULL, facility_code VARCHAR(64) NOT NULL, effective_from DATETIME NOT NULL, effective_until DATETIME NULL, status ENUM('active','ended') NOT NULL DEFAULT 'active', active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED, created_by_user_id INT NOT NULL, ended_by_user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (placement_id), UNIQUE KEY uq_nakes_active_staff (active_staff_key), KEY idx_nakes_placement_staff_status (staff_id,status,placement_id), KEY idx_nakes_placement_facility_status (facility_code,status,staff_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function vcw_concurrency_user($db, $id, $facility, $staff, $profession)
{
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($id, 'Task5C ' . $id, 'task5c-' . $id . '@example.invalid', 'task5c-' . $id, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), 'dokter', 'aktif', 0, $facility));
    $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($staff, $facility, $id, 'Task5C Staff ' . $id, $profession, 'aktif'));
    $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($staff, $facility, '2026-01-01 00:00:00', 'active', $id));
}

$suffix = random_int(1, 500);
$request = 70000 + $suffix * 10;
$facility = 'T5C-' . $suffix;
$command = 50000 + ($request % 100);
$doctor = $request + 1;
$performerA = $request + 2;
$performerB = $request + 3;
vcw_assert_safe_request_id($request);
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'Task5C Facility', 'aktif'));
// Command Center is identified by the canonical no-staff identity.
$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($command, 'Task5C Command', 'task5c-command-' . $suffix . '@example.invalid', 'task5c-command-' . $suffix, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), 'dokter', 'aktif', 0, $facility));
vcw_concurrency_user($db, $doctor, $facility, $doctor, 'dokter');
vcw_concurrency_user($db, $performerA, $facility, $performerA, 'Perawat');
vcw_concurrency_user($db, $performerB, $facility, $performerB, 'Perawat');
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?)', array($request, $doctor, 'synthetic', 'Accepted', $facility, 'Task5C Facility', 'not_started'));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $doctor, $doctor, $doctor, 'aktif'));
$disposition = new Visit_disposition_service($db, new Visit_workflow_policy($db, true), true);
$created = $disposition->create($request, $doctor, array('decision' => 'visit', 'urgency' => 'routine'), 'task5c-disposition-' . $request);
vcw_assert_true(!empty($created['ok']), 'concurrency fixture disposition failed');

$barrier = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'visit-assignment-barrier-' . bin2hex(random_bytes(5));
mkdir($barrier);
$worker = __DIR__ . DIRECTORY_SEPARATOR . 'assignment_worker.php';
$commands = array(
    array($performerA, 'concurrent-a-' . $request),
    array($performerB, 'concurrent-b-' . $request),
);
$processes = array();
foreach ($commands as $commandData) {
    $out = $barrier . DIRECTORY_SEPARATOR . 'out-' . $commandData[0] . '.txt';
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker) . ' ' . (int) $request . ' ' . (int) $command . ' ' . (int) $commandData[0] . ' ' . escapeshellarg($commandData[1]) . ' ' . escapeshellarg($barrier);
    $processes[] = array('proc' => proc_open($cmd, array(1 => array('file', $out, 'w'), 2 => array('file', $out . '.err', 'w')), $pipes), 'out' => $out);
}
$deadline = microtime(true) + 30;
while (count(glob($barrier . DIRECTORY_SEPARATOR . 'ready-*')) < 2) {
    if (microtime(true) > $deadline) throw new RuntimeException('concurrency workers did not become ready');
    usleep(10000);
}
file_put_contents($barrier . DIRECTORY_SEPARATOR . 'release', 'go');
$results = array();
foreach ($processes as $process) {
    $code = proc_close($process['proc']);
    vcw_assert_same(0, $code, 'concurrency worker exit');
    $results[] = trim((string) file_get_contents($process['out']));
}
$winners = 0;
$connections = array();
foreach ($results as $result) {
    if (strpos($result, '"ok":true') !== false) $winners++;
    if (preg_match('/CONNECTION_ID=(\d+)/', $result, $match)) $connections[] = $match[1];
}
vcw_assert_same(1, $winners, 'exactly one assignment winner');
vcw_assert_same(2, count(array_unique($connections)), 'independent database connections');
vcw_assert_same(1, (int) $db->where('request_id', $request)->where('status', 'aktif')->count_all_results('request_visit_performer_assignments'), 'one active assignment after race');
vcw_assert_same(1, (int) $db->where('request_id', $request)->count_all_results('visit_assignment_operations'), 'one operation receipt after race');
echo "TWO_ASSIGNMENT_WORKER_RACE=PASS\n";
echo "ASSIGNMENT_WORKERS_READY=PASS\n";
echo "ASSIGNMENT_INDEPENDENT_CONNECTIONS=PASS\n";
echo "ASSIGNMENT_RACE_WINNERS=1\n";
foreach ($results as $result) echo $result . "\n";

// B2b: two simultaneous retries of the exact same assign operation.
$sameRequest = 75000 + $suffix * 10;
$sameFacility = 'T5K-' . $suffix;
$sameCommand = 51000 + $suffix;
$sameDoctor = $sameRequest + 1;
$samePerformer = $sameRequest + 2;
vcw_assert_safe_request_id($sameRequest);
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($sameFacility, 'Task5 Same Key Facility', 'aktif'));
$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($sameCommand, 'Task5K Command', 'task5k-command-' . $suffix . '@example.invalid', 'task5k-command-' . $suffix, password_hash('x', PASSWORD_BCRYPT), 'dokter', 'aktif', 0, $sameFacility));
vcw_concurrency_user($db, $sameDoctor, $sameFacility, $sameDoctor, 'dokter');
vcw_concurrency_user($db, $samePerformer, $sameFacility, $samePerformer, 'Perawat');
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?)', array($sameRequest, $sameDoctor, 'synthetic', 'Accepted', $sameFacility, 'Task5 Same Key Facility', 'not_started'));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($sameRequest, $sameDoctor, $sameDoctor, $sameDoctor, 'aktif'));
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (?,?,?,?,?,?,?)', array($sameRequest, 1, 'visit', 'routine', $sameDoctor, date('Y-m-d H:i:s.u'), 'task5k-disposition-' . $sameRequest));
$sameBarrier = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'visit-same-key-barrier-' . bin2hex(random_bytes(5));
mkdir($sameBarrier); $sameProcesses = array(); $sameKey = 'same-key-' . $sameRequest;
foreach (array('a', 'b') as $workerName) {
    $out = $sameBarrier . DIRECTORY_SEPARATOR . 'out-' . $workerName . '.txt';
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker) . ' ' . (int) $sameRequest . ' ' . (int) $sameCommand . ' ' . (int) $samePerformer . ' ' . escapeshellarg($sameKey) . ' ' . escapeshellarg($sameBarrier);
    $sameProcesses[] = array('proc' => proc_open($cmd, array(1 => array('file', $out, 'w'), 2 => array('file', $out . '.err', 'w')), $pipes), 'out' => $out);
}
$deadline = microtime(true) + 30;
while (count(glob($sameBarrier . DIRECTORY_SEPARATOR . 'ready-*')) < 2) { if (microtime(true) > $deadline) throw new RuntimeException('same-key workers did not become ready'); usleep(10000); }
file_put_contents($sameBarrier . DIRECTORY_SEPARATOR . 'release', 'go');
$sameResults = array(); foreach ($sameProcesses as $process) { $code = proc_close($process['proc']); vcw_assert_same(0, $code, 'same-key worker exit'); $sameResults[] = trim((string) file_get_contents($process['out'])); }
$sameIds = array();
foreach ($sameResults as $sameResult) {
    vcw_assert_true(strpos($sameResult, '"ok":true') !== false, 'same-key retry must succeed');
    if (preg_match('/"assignment_id":(\d+)/', $sameResult, $m)) $sameIds[] = (int) $m[1];
}
vcw_assert_same(2, count($sameIds), 'same-key result IDs present');
vcw_assert_same(1, count(array_unique($sameIds)), 'same-key replay returns same assignment');
vcw_assert_same(1, (int) $db->where('request_id', $sameRequest)->count_all_results('request_visit_performer_assignments'), 'same-key assignment dedupe');
vcw_assert_same(1, (int) $db->where('idempotency_key', $sameKey)->count_all_results('visit_assignment_operations'), 'same-key receipt dedupe');
vcw_assert_same(1, (int) $db->where('request_id', $sameRequest)->where('event_type', 'visit_assignment.assigned')->count_all_results('request_events'), 'same-key event dedupe');
echo "TWO_SAME_KEY_ASSIGN_WORKERS_EXECUTED=YES\nINDEPENDENT_SAME_KEY_CONNECTIONS=YES\nGENUINE_SAME_KEY_OVERLAP=YES\nSAME_KEY_CONCURRENT_ASSIGN_REPLAY=PASS\n";
foreach ($sameResults as $sameResult) echo $sameResult . "\n";

// B2a: two independent reassign() calls contend for the same active assignment.
$rr = 80000 + $suffix * 10;
$rf = 'T5R-' . $suffix;
$rd = $rr + 1; $rc = $rr; $ra = $rr + 2; $rb = $rr + 3; $rcandidate = $rr + 4;
vcw_assert_safe_request_id($rr);
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($rf, 'Task5 Reassign Facility', 'aktif'));
$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($rc, 'Task5R Command', 'task5r-command-' . $suffix . '@example.invalid', 'task5r-command-' . $suffix, password_hash('x', PASSWORD_BCRYPT), 'dokter', 'aktif', 0, $rf));
vcw_concurrency_user($db, $rd, $rf, $rd, 'dokter');
vcw_concurrency_user($db, $ra, $rf, $ra, 'Perawat');
vcw_concurrency_user($db, $rb, $rf, $rb, 'Perawat');
vcw_concurrency_user($db, $rcandidate, $rf, $rcandidate, 'Perawat');
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?)', array($rr, $rd, 'synthetic', 'Accepted', $rf, 'Task5 Reassign Facility', 'not_started'));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($rr, $rd, $rd, $rd, 'aktif'));
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (?,?,?,?,?,?,?)', array($rr, 1, 'visit', 'routine', $rd, date('Y-m-d H:i:s.u'), 'task5r-disposition-' . $rr));
$rrService = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$seed = $rrService->assign($rr, $rc, $ra, 'task5r-seed-' . $rr);
vcw_assert_true(!empty($seed['ok']), 'reassign seed assignment');
$sourceId = (int) $seed['assignment_id'];
$rbarrier = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'visit-reassign-barrier-' . bin2hex(random_bytes(5));
mkdir($rbarrier); $rprocs = array();
foreach (array(array($rb, 'task5r-b-' . $rr), array($rcandidate, 'task5r-c-' . $rr)) as $item) {
    $out = $rbarrier . DIRECTORY_SEPARATOR . 'out-' . $item[0] . '.txt';
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker) . ' ' . (int) $rr . ' ' . (int) $rc . ' ' . (int) $item[0] . ' ' . escapeshellarg($item[1]) . ' ' . escapeshellarg($rbarrier) . ' reassign ' . escapeshellarg('Concurrent reassignment') . ' ' . (int) $sourceId;
    $rprocs[] = array('proc' => proc_open($cmd, array(1 => array('file', $out, 'w'), 2 => array('file', $out . '.err', 'w')), $pipes), 'out' => $out);
}
$deadline = microtime(true) + 30;
while (count(glob($rbarrier . DIRECTORY_SEPARATOR . 'ready-*')) < 2) { if (microtime(true) > $deadline) throw new RuntimeException('reassign workers did not become ready'); usleep(10000); }
file_put_contents($rbarrier . DIRECTORY_SEPARATOR . 'release', 'go');
$rresults = array(); foreach ($rprocs as $process) { $code = proc_close($process['proc']); vcw_assert_same(0, $code, 'reassign worker exit'); $rresults[] = trim((string) file_get_contents($process['out'])); }
$rwins = 0; $rstale = 0; $rconnections = array(); foreach ($rresults as $rresult) { if (strpos($rresult, '"ok":true') !== false) $rwins++; if (strpos($rresult, 'PERFORMER_ASSIGNMENT_STALE') !== false) $rstale++; if (preg_match('/CONNECTION_ID=(\d+)/', $rresult, $m)) $rconnections[] = $m[1]; }
vcw_assert_same(1, $rwins, 'one reassign winner');
vcw_assert_same(1, $rstale, 'one stale reassign loser');
vcw_assert_same(2, count(array_unique($rconnections)), 'reassign independent connections');
vcw_assert_same('diganti', (string) $db->where('visit_assignment_id', $sourceId)->get('request_visit_performer_assignments')->row()->status, 'source closed once');
vcw_assert_same(1, (int) $db->where('request_id', $rr)->where('status', 'aktif')->count_all_results('request_visit_performer_assignments'), 'one active replacement');
vcw_assert_same(1, (int) $db->where('request_id', $rr)->where('operation_type', 'reassign')->count_all_results('visit_assignment_operations'), 'one reassign receipt');
echo "TWO_REASSIGNMENT_RACE_EXECUTED=YES\nINDEPENDENT_REASSIGNMENT_CONNECTIONS=YES\nGENUINE_REASSIGNMENT_OVERLAP=YES\nCONCURRENT_REASSIGNMENT=PASS\n";
foreach ($rresults as $rresult) echo $rresult . "\n";
