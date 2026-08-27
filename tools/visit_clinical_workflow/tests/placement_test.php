<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
require_once APPPATH . 'libraries/Nakes_placement_store.php';
require_once APPPATH . 'libraries/Nakes_placement_policy.php';
require_once APPPATH . 'libraries/Nakes_placement_service.php';

if (!$db->table_exists('nakes_facility_placements')) {
    $db->query("CREATE TABLE nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, staff_id INT UNSIGNED NOT NULL, facility_code VARCHAR(64) NOT NULL, effective_from DATETIME NOT NULL, effective_until DATETIME NULL, status ENUM('active','ended') NOT NULL DEFAULT 'active', active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED, created_by_user_id INT NOT NULL, ended_by_user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (placement_id), UNIQUE KEY uq_nakes_active_staff (active_staff_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if (!$db->table_exists('nakes_facility_transfers')) {
    $db->query("CREATE TABLE nakes_facility_transfers (transfer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, staff_id INT UNSIGNED NOT NULL, from_placement_id BIGINT UNSIGNED NOT NULL, destination_facility_code VARCHAR(64) NOT NULL, effective_at DATETIME NOT NULL, reason VARCHAR(500) NOT NULL, status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled', scheduled_staff_key INT UNSIGNED AS (CASE WHEN status='scheduled' THEN staff_id ELSE NULL END) STORED, requested_by_user_id INT NOT NULL, requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, completed_by_user_id INT NULL, completed_at DATETIME NULL, cancelled_by_user_id INT NULL, cancelled_at DATETIME NULL, last_block_reason VARCHAR(500) NULL, PRIMARY KEY (transfer_id), UNIQUE KEY uq_nakes_scheduled_transfer (scheduled_staff_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$base = random_int(81000, 81900);
$facility = 'A4-' . $base;
$doctor = $base + 1;
$staff = $base + 1;
$performer = $base + 2;
$performerStaff = $base + 2;
$request = $base + 100;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'A4 Facility', 'aktif'));
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array('A4-DEST-' . $base, 'A4 Destination', 'aktif'));
foreach (array(array($doctor, $staff, 'dokter'), array($performer, $performerStaff, 'Perawat')) as $identity) {
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,remark) VALUES (?,?,?,?,?,?,?,?)', array($identity[0], 'A4 ' . $identity[0], 'a4-' . $identity[0] . '@example.invalid', 'a4-' . $identity[0], password_hash('fixture', PASSWORD_BCRYPT, array('cost' => 4)), 'dokter', 'aktif', $facility));
    $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($identity[1], $facility, $identity[0], 'A4 Staff ' . $identity[0], $identity[2], 'aktif'));
    $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($identity[1], $facility, '2026-01-01 00:00:00', 'active', $identity[0]));
}
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_performer_user_id,consultation_mode,visit_status) VALUES (?,?,?,?,?,?,?,?,?)', array($request, $doctor, 'synthetic', 'Accepted', $facility, 'A4 Facility', $performer, 'visit', 'not_started'));
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key) VALUES (?,?,?,?,?,?)', array($request, 1, 'visit', 'routine', $doctor, 'a4-disposition-' . $request));
$db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $performerStaff, $performer, $doctor, 'aktif'));

$store = new Nakes_placement_store($db);
$db->where('request_id', $request)->update('request_visit_performer_assignments', array('status' => 'aktif'));
$transferService = new Nakes_placement_service($store, true, array('user_id' => $doctor, 'role' => 'admin', 'status' => 'aktif'));
$blockedSchedule = $transferService->schedule(array('staff_id' => $performerStaff, 'destination_facility' => 'A4-DEST-' . $base, 'effective_at' => '2026-09-01 00:00:00', 'reason' => 'A4 blocker smoke'));
vcw_assert_same(false, $blockedSchedule, 'active canonical assignment must block transfer service');
foreach (array('aktif' => false, 'diganti' => true, 'dibatalkan' => true, 'selesai' => true) as $status => $expectedNonBlocking) {
    $db->where('request_id', $request)->update('request_visit_performer_assignments', array('status' => $status));
    $blockers = $store->blockers($performerStaff);
    $policyAllows = Nakes_placement_policy::transferBlockers($blockers);
    vcw_assert_same($expectedNonBlocking, $policyAllows, 'canonical assignment blocker status ' . $status . ' blockers=' . json_encode($blockers));
}
echo "TRANSFER_BLOCKER_STATUS_MATRIX=PASS\n";
echo "REAL_TRANSFER_PATH_USES_BLOCKER=PASS\n";
