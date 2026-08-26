<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
require_once APPPATH . 'libraries/Visit_assignment_service.php';

if ((int) $db->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nakes_facility_placements'")->row()->c !== 1) {
    // Source-derived disposable fixture DDL; production migration remains guarded
    // by its separate allowlist and is not executed by this harness.
    $db->query("CREATE TABLE nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, staff_id INT UNSIGNED NOT NULL, facility_code VARCHAR(64) NOT NULL, effective_from DATETIME NOT NULL, effective_until DATETIME NULL, status ENUM('active','ended') NOT NULL DEFAULT 'active', active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED, created_by_user_id INT NOT NULL, ended_by_user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (placement_id), UNIQUE KEY uq_nakes_active_staff (active_staff_key), KEY idx_nakes_placement_staff_status (staff_id,status,placement_id), KEY idx_nakes_placement_facility_status (facility_code,status,staff_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function vcw_assignment_user($db, $id, $facility, $staff = null, $profession = 'Perawat')
{
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($id, 'Task5 ' . $id, 'task5-' . $id . '@example.invalid', 'task5-' . $id, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), 'dokter', 'aktif', 0, $facility));
    if ($staff !== null) {
        $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($staff, $facility, $id, 'Task5 Staff ' . $id, $profession, 'aktif'));
        $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($staff, $facility, '2026-01-01 00:00:00', 'active', $id));
    }
}

function vcw_assignment_fixture($db, $request, $facility, $doctor, $doctorStaff, $performers = array())
{
    vcw_assert_safe_request_id($request);
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'Task5 Facility', 'aktif'));
    vcw_assignment_user($db, 50000 + $request % 100, $facility); // canonical command center (no staff)
    vcw_assignment_user($db, $doctor, $facility, $doctorStaff, 'dokter');
    foreach ($performers as $performer) { vcw_assignment_user($db, $performer[0], $facility, $performer[1], $performer[2]); }
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?)', array($request, $doctor, 'synthetic', 'Accepted', $facility, 'Task5 Facility', 'not_started'));
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $doctorStaff, $doctor, $doctor, 'aktif'));
    $disposition = new Visit_disposition_service($db, new Visit_workflow_policy($db, true), true);
    $created = $disposition->create($request, $doctor, array('decision' => 'visit', 'urgency' => 'routine'), 'task5-disposition-' . $request);
    vcw_assert_true(!empty($created['ok']), 'fixture disposition creation failed code=' . (string) ($created['code'] ?? 'none'));
    return 50000 + $request % 100;
}

$suffix = random_int(1, 500);
$request = 60000 + $suffix * 10;
$facility = 'T5-' . $suffix;
$command = vcw_assignment_fixture($db, $request, $facility, $request + 1, $request + 1, array(array($request + 2, $request + 2, 'Perawat'), array($request + 3, $request + 3, 'dokter')));
$service = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$performer = $request + 2;
$result = $service->assign($request, $command, $performer, 'assign-' . $request);
vcw_assert_true(!empty($result['ok']), 'eligible assignment must succeed code=' . (string) ($result['code'] ?? 'none'));
$assignmentId = (int) $result['assignment_id'];
vcw_assert_same(1, (int) $db->where('request_id', $request)->count_all_results('request_visit_performer_assignments'), 'one assignment');
$replay = $service->assign($request, $command, $performer, 'assign-' . $request);
vcw_assert_same($assignmentId, (int) $replay['assignment_id'], 'assign replay result');
vcw_assert_same(1, (int) $db->where('request_id', $request)->where('event_type', 'visit_assignment.assigned')->count_all_results('request_events'), 'assign event dedupe');
$different = $service->assign($request, $command, $request + 3, 'assign-' . $request . '-different');
vcw_assert_same('ACTIVE_PERFORMER_EXISTS', $different['code'] ?? null, 'different assign key conflict');
$badActor = $service->assign($request, $request + 1, $request + 3, 'assign-bad-actor');
vcw_assert_same('NOT_COMMAND_CENTER', $badActor['code'] ?? null, 'non command center rejected');
$reassigned = $service->reassign($request, $command, $request + 3, 'switch performer', 'reassign-' . $request);
vcw_assert_true(!empty($reassigned['ok']), 'reassign must succeed');
$old = $db->where('visit_assignment_id', $assignmentId)->get('request_visit_performer_assignments')->row();
vcw_assert_same('diganti', (string) $old->status, 'old assignment closed');
$reassignReplay = $service->reassign($request, $command, $request + 3, 'switch performer', 'reassign-' . $request);
vcw_assert_same((int) $reassigned['assignment_id'], (int) $reassignReplay['assignment_id'], 'reassign replay');

$cancelRequest = $request + 10;
$cancelFacility = $facility . '-C';
$cancelCommand = vcw_assignment_fixture($db, $cancelRequest, $cancelFacility, $cancelRequest + 1, $cancelRequest + 1, array(array($cancelRequest + 2, $cancelRequest + 2, 'dokter')));
$crossContext = $service->assign($cancelRequest, $cancelCommand, $cancelRequest + 2, 'assign-' . $request);
vcw_assert_same('IDEMPOTENCY_KEY_CONFLICT', $crossContext['code'] ?? null, 'cross-context key reuse rejected');
$cancelAssigned = $service->assign($cancelRequest, $cancelCommand, $cancelRequest + 2, 'cancel-assign-' . $cancelRequest);
$cancelled = $service->cancelBeforeStart($cancelRequest, $cancelRequest + 1, 'warga membatalkan', 'cancel-' . $cancelRequest);
vcw_assert_true(!empty($cancelled['ok']), 'cancel must succeed');
$cancelRow = $db->where('request_id', $cancelRequest)->get('request_visit_performer_assignments')->row();
vcw_assert_same('dibatalkan', (string) $cancelRow->status, 'cancel status');
vcw_assert_same('Cancelled', (string) $db->where('request_id', $cancelRequest)->get('requests')->row()->request_status, 'cancel request status');
$cancelReplay = $service->cancelBeforeStart($cancelRequest, $cancelRequest + 1, 'warga membatalkan', 'cancel-' . $cancelRequest);
vcw_assert_same((int) $cancelled['source_assignment_id'], (int) $cancelReplay['source_assignment_id'], 'cancel replay');

$nonEnrolledRequest = $request + 20;
$nonFacility = $facility . '-N';
vcw_assert_safe_request_id($nonEnrolledRequest);
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($nonFacility, 'Task5 Facility', 'aktif'));
vcw_assignment_user($db, 51000 + $nonEnrolledRequest % 100, $nonFacility);
vcw_assignment_user($db, $nonEnrolledRequest + 1, $nonFacility, $nonEnrolledRequest + 1, 'Perawat');
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?)', array($nonEnrolledRequest, $nonEnrolledRequest + 1, 'synthetic', 'Accepted', $nonFacility, 'Task5 Facility', 'not_started'));
$notEnrolled = $service->assign($nonEnrolledRequest, 51000 + $nonEnrolledRequest % 100, $nonEnrolledRequest + 1, 'not-enrolled');
vcw_assert_same('WORKFLOW_NOT_ENROLLED', $notEnrolled['code'] ?? null, 'non-enrolled rejected');

echo "TASK5_ASSIGNMENT_MATRIX=PASS\n";
echo "ASSIGN_IDEMPOTENT_REPLAY=PASS\n";
echo "REASSIGN_IDEMPOTENT_REPLAY=PASS\n";
echo "CANCELLATION_IDEMPOTENT_REPLAY=PASS\n";
echo "TASK5_REAL_SERVICE_EXECUTION=PASS\n";
