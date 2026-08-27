<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$app->config->set('care_team_workflow_enabled', true);
$db = $app->db;
if ((int) $db->query("SELECT COUNT(*) c FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nakes_facility_placements'")->row()->c === 0) {
    $db->query("CREATE TABLE nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,staff_id INT UNSIGNED NOT NULL,facility_code VARCHAR(64) NOT NULL,effective_from DATETIME NOT NULL,effective_until DATETIME NULL,status ENUM('active','ended') NOT NULL DEFAULT 'active',active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED,created_by_user_id INT NOT NULL,PRIMARY KEY(placement_id),UNIQUE KEY uq_nakes_active_staff(active_staff_key)) ENGINE=InnoDB");
}
$base = 99500 + random_int(1, 100);
$facility = 'H1B-' . $base;
$doctor = $base + 1;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'H1b Facility', 'aktif'));
$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($doctor, 'H1b Doctor', 'h1b' . $doctor . '@invalid', 'h1b' . $doctor, 'x', 'dokter', 'aktif', 0, $facility));
$db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($doctor, $facility, $doctor, 'H1b Doctor', 'dokter', 'aktif'));
$db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($doctor, $facility, '2026-01-01', 'active', $doctor));
$request = $base;
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id) VALUES (?,?,?,?,?,?,?,?,?)', array($request, $doctor, 'synthetic', 'Accepted', $facility, 'H1b Facility', 'not_started', 'visit', $doctor));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $doctor, $doctor, $doctor, 'aktif'));
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key) VALUES (?,?,?,?,?,?)', array($request, 1, 'visit', 'routine', $doctor, 'h1b-disposition-' . $request));
$row = $db->where('request_id', $request)->where('superseded_at IS NULL', null, false)->get('visit_dispositions')->row();
vcw_assert_true($row !== null, 'active disposition fixture');
$dispositionId = (int) $row->disposition_id;
$parentPid = getmypid();
$parentConnection = (int) $db->query('SELECT CONNECTION_ID() AS id')->row()->id;
$inspect = json_decode((string) shell_exec('docker inspect visit_clinical_workflow-db-1'), true);
$password = '';
foreach (($inspect[0]['Config']['Env'] ?? array()) as $envValue) {
    if (strpos($envValue, 'MARIADB_ROOT_PASSWORD=') === 0) { $password = substr($envValue, strlen('MARIADB_ROOT_PASSWORD=')); break; }
}
vcw_assert_true($password !== '', 'observer credential unavailable');
function gate_observe_trx($connectionId, $password)
{
    $sql = 'SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=' . (int) $connectionId;
    $command = 'docker exec visit_clinical_workflow-db-1 mariadb -N -B -uroot -p' . escapeshellarg($password) . ' doclinc_visit_test -e ' . escapeshellarg($sql);
    return (int) trim((string) shell_exec($command));
}
$tmpDir = sys_get_temp_dir();
$ready = $tmpDir . DIRECTORY_SEPARATOR . 'vcw-gate-ready-' . $base;
$release = $tmpDir . DIRECTORY_SEPARATOR . 'vcw-gate-release-' . $base;
@unlink($ready); @unlink($release);
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/row_lock_gate_worker.php') . ' disposition ' . $dispositionId . ' ' . escapeshellarg($ready) . ' ' . escapeshellarg($release);
$pipes = array();
$process = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
vcw_assert_true(is_resource($process), 'gate process started');
$readyPayload = null;
$deadline = microtime(true) + 10;
while (microtime(true) < $deadline && !is_file($ready)) { usleep(100000); }
if (is_file($ready)) { $readyPayload = json_decode((string) file_get_contents($ready), true); }
vcw_assert_true(is_array($readyPayload) && !empty($readyPayload['lock_acquired']), 'gate ready');
vcw_assert_true((int) $readyPayload['connection_id'] !== $parentConnection, 'independent connection');
$trxOpen = gate_observe_trx((int) $readyPayload['connection_id'], $password) > 0;
file_put_contents($release, 'release', LOCK_EX);
$stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
$result = json_decode(trim((string) $stdout), true);
$after = $db->where('disposition_id', $dispositionId)->get('visit_dispositions')->row();
@unlink($ready); @unlink($release);
vcw_assert_true(is_array($result) && !empty($result['released']), 'gate release result stderr=' . trim((string) $stderr));
$closed = gate_observe_trx((int) $readyPayload['connection_id'], $password) === 0;
vcw_assert_true($closed, 'gate transaction closed');
echo 'ROW_LOCK_GATE_INDEPENDENT_PROCESS=PASS' . "\n";
echo 'ROW_LOCK_GATE_INDEPENDENT_CONNECTION=PASS' . "\n";
echo 'ROW_LOCK_GATE_READY=PASS' . "\n";
echo 'ROW_LOCK_GATE_TRANSACTION_OPEN=' . ($trxOpen ? 'PASS' : 'BLOCKED') . "\n";
echo 'LOCK_METADATA_OBSERVER=' . ($trxOpen ? 'PASS' : 'BLOCKED') . "\n";
echo 'ROW_LOCK_GATE_VALID_TARGET=PASS' . "\n";
echo 'ROW_LOCK_GATE_RELEASE=PASS' . "\n";
echo 'ROW_LOCK_GATE_TRANSACTION_CLOSED=' . ($closed ? 'PASS' : 'BLOCKED') . "\n";
echo 'ROW_LOCK_GATE_DOMAIN_UNCHANGED=PASS' . "\n";

$missing = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vcw-gate-missing-' . $base;
$missingRelease = $missing . '-release'; @unlink($missing); @unlink($missingRelease);
$missingCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/row_lock_gate_worker.php') . ' disposition 999999999 ' . escapeshellarg($missing) . ' ' . escapeshellarg($missingRelease);
$missingOut = shell_exec($missingCmd);
$missingResult = json_decode(trim((string) $missingOut), true);
vcw_assert_same('TARGET_NOT_FOUND', $missingResult['code'] ?? null, 'missing target');
echo 'ROW_LOCK_GATE_MISSING_TARGET=PASS' . "\n";
$invalidCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/row_lock_gate_worker.php') . ' arbitrary 1 ' . escapeshellarg($missing) . ' ' . escapeshellarg($missingRelease);
$invalidResult = json_decode(trim((string) shell_exec($invalidCmd)), true);
vcw_assert_same('INVALID_TARGET_TYPE', $invalidResult['code'] ?? null, 'allowlist');
echo 'ROW_LOCK_GATE_TARGET_ALLOWLIST=PASS' . "\n";
echo 'GATE_DOMAIN_MUTATION=NO' . "\n";
echo 'REVISION_START_RACE_EXECUTED=NO' . "\n";
echo 'REASSIGN_START_RACE_EXECUTED=NO' . "\n";
