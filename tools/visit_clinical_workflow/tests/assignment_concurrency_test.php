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
