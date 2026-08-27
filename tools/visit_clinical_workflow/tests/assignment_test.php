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
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($id, 'Task5 ' . $id, 'task5-' . $id . '@example.invalid', 'task5-' . $id, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, array('cost' => 4)), 'dokter', 'aktif', 0, $facility));
    if ($staff !== null) {
        $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($staff, $facility, $id, 'Task5 Staff ' . $id, $profession, 'aktif'));
        $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($staff, $facility, '2026-01-01 00:00:00', 'active', $id));
    }
}

function vcw_assignment_fixture($db, $request, $facility, $doctor, $doctorStaff, $performers = array())
{
    static $commandSequence = 0;
    vcw_assert_safe_request_id($request);
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'Task5 Facility', 'aktif'));
    $commandUserId = 40000 + (++$commandSequence);
    vcw_assignment_user($db, $commandUserId, $facility); // canonical command center (no staff)
    vcw_assignment_user($db, $doctor, $facility, $doctorStaff, 'dokter');
    foreach ($performers as $performer) { vcw_assignment_user($db, $performer[0], $facility, $performer[1], $performer[2]); }
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?)', array($request, $doctor, 'synthetic', 'Accepted', $facility, 'Task5 Facility', 'not_started'));
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $doctorStaff, $doctor, $doctor, 'aktif'));
    $disposition = new Visit_disposition_service($db, new Visit_workflow_policy($db, true), true);
    $created = $disposition->create($request, $doctor, array('decision' => 'visit', 'urgency' => 'routine'), 'task5-disposition-' . $request);
    vcw_assert_true(!empty($created['ok']), 'fixture disposition creation failed request=' . $request . ' doctor=' . $doctor . ' code=' . (string) ($created['code'] ?? 'none') . ' identity=' . json_encode(function_exists('doclinc_dokter_identity_context') ? doclinc_dokter_identity_context($doctor, true) : array()) . ' assignment=' . (int) $db->where('request_id', $request)->where('user_id', $doctor)->count_all_results('request_responsible_doctor_assignments'));
    return $commandUserId;
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
$legacyHandling = $cancelRequest + 20;
$legacyFacility = $cancelFacility . '-L';
$legacyDoctor = $legacyHandling + 1;
$legacyStaff = $legacyHandling + 1;
$legacyCommand = vcw_assignment_fixture($db, $legacyHandling, $legacyFacility, $legacyDoctor, $legacyStaff, array(array($legacyHandling + 2, $legacyHandling + 2, 'Perawat')));
$db->where('request_id', $legacyHandling)->update('requests', array('assigned_nakes_user_id' => $legacyDoctor));
$legacyService = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$legacyAssigned = $legacyService->assign($legacyHandling, $legacyCommand, $legacyHandling + 2, 'legacy-assign-' . $legacyHandling);
vcw_assert_true(!empty($legacyAssigned['ok']), 'legacy cancellation fixture assignment failed');
$legacyCanReview = (new Visit_workflow_policy($db, false))->canReview($legacyHandling, $legacyDoctor);
$legacyCanCancel = doclinc_can_cancel_request($legacyHandling, $legacyDoctor, 'dokter');
vcw_assert_same(true, (bool) $legacyCanReview, 'canonical review authority fixture');
vcw_assert_same(true, (bool) $legacyCanCancel, 'legacy cancellation authority fixture');
$broadRequest = $cancelRequest + 30;
$broadFacility = $cancelFacility . '-B';
$broadDoctor = $broadRequest + 1;
$broadCommand = vcw_assignment_fixture($db, $broadRequest, $broadFacility, $broadDoctor, $broadDoctor, array(array($broadRequest + 2, $broadRequest + 2, 'Perawat')));
$broadService = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$broadAssigned = $broadService->assign($broadRequest, $broadCommand, $broadRequest + 2, 'broad-assign-' . $broadRequest);
vcw_assert_true(!empty($broadAssigned['ok']), 'authority broadening fixture assignment failed');
$broadCanReview = (new Visit_workflow_policy($db, false))->canReview($broadRequest, $broadDoctor);
$broadCanCancel = doclinc_can_cancel_request($broadRequest, $broadDoctor, 'dokter');
vcw_assert_same(true, (bool) $broadCanReview, 'broadening fixture review authority');
vcw_assert_same(false, (bool) $broadCanCancel, 'broadening fixture legacy cancellation denial');
$broadCancelled = $broadService->cancelBeforeStart($broadRequest, $broadDoctor, 'should be denied', 'broad-cancel-' . $broadRequest);
vcw_assert_same('CANCELLATION_NOT_AUTHORIZED', $broadCancelled['code'] ?? null, 'review authority must not broaden cancellation');
$broadState = $db->where('request_id', $broadRequest)->get('requests')->row();
$broadAssignment = $db->where('request_id', $broadRequest)->where('status', 'aktif')->get('request_visit_performer_assignments')->row();
vcw_assert_same('Accepted', (string) $broadState->request_status, 'denied cancellation keeps request accepted');
vcw_assert_true($broadAssignment !== null, 'denied cancellation keeps assignment active');
vcw_assert_same($broadRequest + 2, (int) $broadState->visit_performer_user_id, 'denied cancellation keeps projection');
vcw_assert_same(0, (int) $db->where('request_id', $broadRequest)->where('operation_type', 'cancel_before_start')->count_all_results('visit_assignment_operations'), 'denied cancellation has no receipt');
vcw_assert_same(0, (int) $db->where('request_id', $broadRequest)->where('event_type', 'visit_assignment.cancelled')->count_all_results('request_events'), 'denied cancellation has no event');
$db->where('request_id', $cancelRequest)->update('requests', array('assigned_nakes_user_id' => $cancelRequest + 1));
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

// Closure A1: compare every cancellation decision with the pre-existing helper.
$parityCases = array(
    array('legacy_handled_doctor', 'doctor', true),
    array('responsible_only', 'doctor', false),
    array('performer_only', 'performer', false),
    array('same_responsible_performer', 'same', false),
    array('command_center', 'command', false),
    array('unrelated_doctor', 'doctor', false),
    array('unrelated_nakes', 'nakes', false),
    array('admin', 'admin', false),
    array('super_admin', 'super_admin', false),
);
$parityPass = true;
foreach ($parityCases as $index => $case) {
    $id = 71000 + $suffix * 20 + $index * 17;
    vcw_assert_safe_request_id($id);
    $facilityId = 'T5P-' . $suffix . '-' . $index;
    $doctorId = $id + 1;
    $doctorStaff = $id + 1;
    $performerId = $id + 2;
    $performerStaff = $id + 2;
    $commandId = vcw_assignment_fixture($db, $id, $facilityId, $doctorId, $doctorStaff, array(array($performerId, $performerStaff, 'Perawat')));
    $actorId = $doctorId;
    if ($case[1] === 'performer') $actorId = $performerId;
    if ($case[1] === 'same') $actorId = $doctorId;
    if ($case[1] === 'command') $actorId = $commandId;
    if ($case[1] === 'nakes') $actorId = $performerId;
    if ($case[1] === 'admin' || $case[1] === 'super_admin') {
        $actorId = $id + 5;
        $role = $case[1] === 'admin' ? 'admin' : 'super-admin';
        $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($actorId, 'Task5 ' . $role . ' ' . $id, 'task5-' . $role . '-' . $id . '@example.invalid', 'task5-' . $role . '-' . $id, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, array('cost' => 4)), $role, 'aktif', 0, $facilityId));
    }
    $assigned = $service->assign($id, $commandId, $performerId, 'parity-assign-' . $id);
    vcw_assert_true(!empty($assigned['ok']), 'parity assignment setup failed ' . $case[0]);
    if ($case[1] === 'nakes') $db->where('userId', $actorId)->update('users', array('role' => 'warga'));
    if ($case[2]) $db->where('request_id', $id)->update('requests', array('assigned_nakes_user_id' => $actorId));
    $helper = doclinc_can_cancel_request($id, $actorId, $case[1] === 'nakes' ? 'warga' : ($case[1] === 'admin' || $case[1] === 'super_admin' ? $role : 'dokter'));
    $result = $service->cancelBeforeStart($id, $actorId, 'parity', 'parity-cancel-' . $id);
    $serviceAllowed = !empty($result['ok']);
    vcw_assert_same((bool) $helper, $serviceAllowed, 'cancellation parity ' . $case[0]);
    if ($helper) {
        vcw_assert_same('Cancelled', (string) $db->where('request_id', $id)->get('requests')->row()->request_status, 'authorized parity request');
    } else {
        $state = $db->where('request_id', $id)->get('requests')->row();
        $assignmentState = $db->where('request_id', $id)->where('status', 'aktif')->get('request_visit_performer_assignments')->row();
        vcw_assert_same('Accepted', (string) $state->request_status, 'denied parity request');
        vcw_assert_true($assignmentState !== null, 'denied parity assignment');
        vcw_assert_same($performerId, (int) $state->visit_performer_user_id, 'denied parity projection');
        vcw_assert_same(0, (int) $db->where('request_id', $id)->where('operation_type', 'cancel_before_start')->count_all_results('visit_assignment_operations'), 'denied parity receipt');
        vcw_assert_same(0, (int) $db->where('request_id', $id)->where('event_type', 'visit_assignment.cancelled')->count_all_results('request_events'), 'denied parity event');
    }
}

// Closure A1: an authorized actor is still locked out after physical start.
foreach (array('en_route', 'arrived', 'in_service', 'completed') as $offset => $visitStatus) {
    $id = 73000 + $suffix * 20 + $offset * 23;
    vcw_assert_safe_request_id($id);
    $facilityId = 'T5S-' . $suffix . '-' . $offset;
    $doctorId = $id + 1;
    $commandId = vcw_assignment_fixture($db, $id, $facilityId, $doctorId, $doctorId, array(array($id + 2, $id + 2, 'Perawat')));
    $db->where('request_id', $id)->update('requests', array('assigned_nakes_user_id' => $doctorId));
    $service->assign($id, $commandId, $id + 2, 'start-assign-' . $id);
    $db->where('request_id', $id)->update('requests', array('visit_status' => $visitStatus));
    $before = $db->where('request_id', $id)->get('requests')->row();
    $result = $service->cancelBeforeStart($id, $doctorId, 'post-start', 'post-start-' . $id);
    vcw_assert_same('VISIT_ALREADY_STARTED', $result['code'] ?? null, 'post-start cancellation ' . $visitStatus);
    $after = $db->where('request_id', $id)->get('requests')->row();
    vcw_assert_same((string) $before->request_status, (string) $after->request_status, 'post-start request unchanged');
    vcw_assert_same($visitStatus, (string) $after->visit_status, 'post-start status unchanged');
    vcw_assert_true($db->where('request_id', $id)->where('status', 'aktif')->get('request_visit_performer_assignments')->row() !== null, 'post-start assignment active');
    vcw_assert_same(0, (int) $db->where('request_id', $id)->where('operation_type', 'cancel_before_start')->count_all_results('visit_assignment_operations'), 'post-start receipt zero');
    vcw_assert_same(0, (int) $db->where('request_id', $id)->where('event_type', 'visit_assignment.cancelled')->count_all_results('request_events'), 'post-start event zero');
}

echo "CANCELLATION_AUTHORITY_PARITY=PASS\n";
echo "POSTSTART_CANCELLATION_REJECTED=PASS\n";

// Closure A2: doctor-performer identity and sequential reassignment guards.
$doctorRequest = 76000 + $suffix;
$doctorFacility = 'T5D-' . $suffix;
$doctorUser = $doctorRequest + 1;
$doctorCommand = vcw_assignment_fixture($db, $doctorRequest, $doctorFacility, $doctorUser, $doctorUser, array());
$doctorAssign = $service->assign($doctorRequest, $doctorCommand, $doctorUser, 'doctor-performer-' . $doctorRequest);
vcw_assert_true(!empty($doctorAssign['ok']), 'eligible doctor performer rejected');
echo "DOCTOR_PERFORMER_ALLOWED=PASS\n";

$sameRequest = $doctorRequest + 100;
$sameFacility = $doctorFacility . '-S';
$sameDoctor = $sameRequest + 1;
$sameCommand = vcw_assignment_fixture($db, $sameRequest, $sameFacility, $sameDoctor, $sameDoctor, array());
$sameBefore = $db->where('request_id', $sameRequest)->where('user_id', $sameDoctor)->where('status', 'aktif')->get('request_responsible_doctor_assignments')->row();
$sameRequestBefore = $db->where('request_id', $sameRequest)->get('requests')->row();
$sameResult = $service->assign($sameRequest, $sameCommand, $sameDoctor, 'same-doctor-' . $sameRequest);
vcw_assert_true(!empty($sameResult['ok']), 'same responsible/performer rejected');
$sameAfter = $db->where('request_id', $sameRequest)->where('user_id', $sameDoctor)->where('status', 'aktif')->get('request_responsible_doctor_assignments')->row();
    vcw_assert_same((int) $sameBefore->responsible_assignment_id, (int) $sameAfter->responsible_assignment_id, 'responsible assignment changed');
vcw_assert_same((int) $sameRequestBefore->responsible_doctor_user_id, (int) $db->where('request_id', $sameRequest)->get('requests')->row()->responsible_doctor_user_id, 'responsible projection changed');
vcw_assert_same('Accepted', (string) $db->where('request_id', $sameRequest)->get('requests')->row()->request_status, 'same-user request status');
vcw_assert_same(0, (int) $db->where('request_id', $sameRequest)->count_all_results('clinical_reviews'), 'assignment created review');
echo "SAME_USER_RESPONSIBLE_AND_PERFORMER_ALLOWED=PASS\nRESPONSIBLE_DOCTOR_ASSIGNMENT_PRESERVED=PASS\n";

$offRequest = $sameRequest + 100;
$offFacility = $sameFacility . '-O';
$offDoctor = $offRequest + 1;
$offCommand = vcw_assignment_fixture($db, $offRequest, $offFacility, $offDoctor, $offDoctor, array(array($offRequest + 2, $offRequest + 2, 'Perawat')));
$offService = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$offResult = $offService->assign($offRequest, $offCommand, $offRequest + 2, 'enrolled-off-' . $offRequest);
vcw_assert_true(!empty($offResult['ok']), 'enrolled assignment blocked by feature flag off');
echo "ENROLLED_ASSIGNMENT_AFTER_FLAG_OFF=PASS\n";

$reassignRequest = $offRequest + 100;
$reassignFacility = $offFacility . '-R';
$reassignDoctor = $reassignRequest + 1;
$reassignCommand = vcw_assignment_fixture($db, $reassignRequest, $reassignFacility, $reassignDoctor, $reassignDoctor, array(array($reassignRequest + 2, $reassignRequest + 2, 'Perawat'), array($reassignRequest + 3, $reassignRequest + 3, 'dokter')));
$reassignService = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$aResult = $reassignService->assign($reassignRequest, $reassignCommand, $reassignRequest + 2, 'a-' . $reassignRequest);
$bResult = $reassignService->reassign($reassignRequest, $reassignCommand, $reassignRequest + 3, 'doctor replacement', 'b-' . $reassignRequest);
vcw_assert_true(!empty($aResult['ok']) && !empty($bResult['ok']), 'doctor replacement reassign failed');
vcw_assert_same('diganti', (string) $db->where('visit_assignment_id', (int) $aResult['assignment_id'])->get('request_visit_performer_assignments')->row()->status, 'reassign old status');
vcw_assert_same($reassignRequest + 3, (int) $db->where('request_id', $reassignRequest)->get('requests')->row()->visit_performer_user_id, 'reassign projection');
echo "REASSIGN_HAPPY_PATH=PASS\nRESPONSIBLE_DOCTOR_AS_REPLACEMENT_ALLOWED=PASS\n";

$invalidRequest = $reassignRequest + 100;
$invalidFacility = $reassignFacility . '-I';
$invalidDoctor = $invalidRequest + 1;
$invalidCommand = vcw_assignment_fixture($db, $invalidRequest, $invalidFacility, $invalidDoctor, $invalidDoctor, array(array($invalidRequest + 2, $invalidRequest + 2, 'Perawat'), array($invalidRequest + 3, $invalidRequest + 3, 'Perawat'), array($invalidRequest + 4, $invalidRequest + 4, 'Perawat'), array($invalidRequest + 5, $invalidRequest + 5, 'Perawat')));
$invalidService = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$invalidFirst = $invalidService->assign($invalidRequest, $invalidCommand, $invalidRequest + 2, 'invalid-base-' . $invalidRequest);
vcw_assert_true(!empty($invalidFirst['ok']), 'invalid-target setup failed');
$invalidTargets = array($invalidRequest + 3, $invalidRequest + 4, $invalidRequest + 5, $invalidCommand);
foreach ($invalidTargets as $targetIndex => $target) {
    if ($targetIndex === 0) $db->where('userId', $target)->update('users', array('status' => 'nonaktif'));
    if ($targetIndex === 1) $db->where('user_id', $target)->update('puskesmas_staff', array('status' => 'nonaktif'));
    if ($targetIndex === 2) $db->where('staff_id', $target)->update('nakes_facility_placements', array('facility_code' => 'OTHER-FACILITY'));
    $blocked = $invalidService->reassign($invalidRequest, $invalidCommand, $target, 'invalid-target', 'invalid-target-' . $invalidRequest . '-' . $targetIndex);
    vcw_assert_same('PERFORMER_NOT_ELIGIBLE', $blocked['code'] ?? null, 'invalid reassignment target');
    vcw_assert_true($db->where('request_id', $invalidRequest)->where('status', 'aktif')->where('user_id', $invalidRequest + 2)->get('request_visit_performer_assignments')->row() !== null, 'invalid target closed old assignment');
    vcw_assert_same($invalidRequest + 2, (int) $db->where('request_id', $invalidRequest)->get('requests')->row()->visit_performer_user_id, 'invalid target changed projection');
}
$db->where('request_id', $invalidRequest)->update('requests', array('visit_performer_user_id' => $invalidRequest + 2));
$db->where('request_id', $invalidRequest)->update('visit_dispositions', array('required_profession' => 'dokter'));
$db->where('userId', $invalidRequest + 3)->update('users', array('status' => 'aktif'));
$db->where('user_id', $invalidRequest + 3)->update('puskesmas_staff', array('status' => 'aktif'));
$mismatch = $invalidService->reassign($invalidRequest, $invalidCommand, $invalidRequest + 3, 'profession mismatch', 'invalid-target-mismatch-' . $invalidRequest);
vcw_assert_same('PERFORMER_NOT_ELIGIBLE', $mismatch['code'] ?? null, 'profession mismatch guard');
$samePerformer = $invalidService->reassign($invalidRequest, $invalidCommand, $invalidRequest + 2, 'same performer', 'same-performer-' . $invalidRequest);
vcw_assert_same('SAME_PERFORMER', $samePerformer['code'] ?? null, 'same performer guard');
echo "REASSIGN_INVALID_TARGET_ZERO_MUTATION=PASS\nSAME_PERFORMER_ZERO_MUTATION=PASS\n";

foreach (array('en_route', 'arrived', 'in_service', 'completed') as $offset => $state) {
    $id = $reassignRequest + 200 + $offset * 23;
    $facilityId = $reassignFacility . '-P' . $offset;
    $doc = $id + 1;
    $cmd = vcw_assignment_fixture($db, $id, $facilityId, $doc, $doc, array(array($id + 2, $id + 2, 'Perawat'), array($id + 3, $id + 3, 'Perawat')));
    $svc = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
    $first = $svc->assign($id, $cmd, $id + 2, 'post-reassign-a-' . $id);
    vcw_assert_true(!empty($first['ok']), 'post-start setup failed');
    $db->where('request_id', $id)->update('requests', array('visit_status' => $state));
    $blocked = $svc->reassign($id, $cmd, $id + 3, 'post-start', 'post-reassign-b-' . $id);
    vcw_assert_same('VISIT_ALREADY_STARTED', $blocked['code'] ?? null, 'post-start reassign ' . $state);
    vcw_assert_true($db->where('request_id', $id)->where('status', 'aktif')->get('request_visit_performer_assignments')->row() !== null, 'post-start active assignment');
}
echo "POSTSTART_REASSIGN_REJECTED=PASS\nSAME_PERFORMER_RULING=SAME_PERFORMER\n";

// Closure A3: enrolled physical authority must use the canonical performer row.
require_once APPPATH . 'libraries/Care_team_service.php';
$careTeam = new Care_team_service($db);
$app->config->set('care_team_workflow_enabled', true);
$canonicalContext = $careTeam->requestContext($doctorRequest, $doctorUser);
vcw_assert_true(!empty($canonicalContext['is_visit_performer']), 'active canonical performer must authorize physical visit');
vcw_assert_true(doclinc_can_update_visit_status($doctorRequest, $doctorUser, 'dokter'), 'physical authorization helper rejected canonical performer');

$compatRequest = $doctorRequest + 1000;
$compatFacility = $doctorFacility . '-C';
$compatDoctor = $compatRequest + 1;
$compatPerformer = $compatRequest + 2;
$compatCommand = vcw_assignment_fixture($db, $compatRequest, $compatFacility, $compatDoctor, $compatDoctor, array(array($compatPerformer, $compatPerformer, 'Perawat')));
$db->where('request_id', $compatRequest)->update('requests', array('visit_performer_user_id' => $compatPerformer));
$compatContext = $careTeam->requestContext($compatRequest, $compatPerformer);
vcw_assert_true(empty($compatContext['is_visit_performer']), 'compatibility-only performer must not authorize enrolled physical visit');
vcw_assert_true(!doclinc_can_update_visit_status($compatRequest, $compatPerformer, 'dokter'), 'physical authorization helper accepted compatibility-only performer');
echo "ENROLLED_CANONICAL_PERFORMER_REQUIRED=PASS\nCOMPATIBILITY_ONLY_PERFORMER_DENIED=PASS\n";
echo "REAL_PHYSICAL_PATH_USES_CANONICAL_AUTH=PASS\n";

$staffOnlyRequest = $doctorRequest + 1050;
$staffOnlyFacility = $doctorFacility . '-ST';
$staffOnlyDoctor = $staffOnlyRequest + 1;
$staffOnlyPerformer = $staffOnlyRequest + 2;
vcw_assignment_fixture($db, $staffOnlyRequest, $staffOnlyFacility, $staffOnlyDoctor, $staffOnlyDoctor, array(array($staffOnlyPerformer, $staffOnlyPerformer, 'Perawat')));
$db->query('INSERT INTO request_staff_assignments (request_id,staff_id,kode_pkm,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($staffOnlyRequest, $staffOnlyPerformer, $staffOnlyFacility, $staffOnlyDoctor, 'aktif'));
$staffOnlyContext = $careTeam->requestContext($staffOnlyRequest, $staffOnlyPerformer);
vcw_assert_true(empty($staffOnlyContext['is_visit_performer']), 'legacy staff assignment must not authorize enrolled physical visit');

$wrongRequest = $doctorRequest + 1075;
$wrongFacility = $doctorFacility . '-W';
$wrongDoctor = $wrongRequest + 1;
$wrongPerformer = $wrongRequest + 2;
$wrongOther = $wrongRequest + 3;
$wrongCommand = vcw_assignment_fixture($db, $wrongRequest, $wrongFacility, $wrongDoctor, $wrongDoctor, array(array($wrongPerformer, $wrongPerformer, 'Perawat'), array($wrongOther, $wrongOther, 'Perawat')));
$wrongService = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$wrongAssigned = $wrongService->assign($wrongRequest, $wrongCommand, $wrongPerformer, 'wrong-owner-' . $wrongRequest);
vcw_assert_true(!empty($wrongAssigned['ok']), 'wrong-owner setup failed');
$wrongContext = $careTeam->requestContext($wrongRequest, $wrongOther);
vcw_assert_true(empty($wrongContext['is_visit_performer']), 'wrong canonical performer must not authorize physical visit');

$responsibleOnlyRequest = $doctorRequest + 1090;
$responsibleOnlyFacility = $doctorFacility . '-R';
$responsibleOnlyDoctor = $responsibleOnlyRequest + 1;
vcw_assignment_fixture($db, $responsibleOnlyRequest, $responsibleOnlyFacility, $responsibleOnlyDoctor, $responsibleOnlyDoctor, array());
$responsibleOnlyContext = $careTeam->requestContext($responsibleOnlyRequest, $responsibleOnlyDoctor);
vcw_assert_true(empty($responsibleOnlyContext['is_visit_performer']), 'responsible doctor alone gained physical authority context=' . json_encode($responsibleOnlyContext));
echo "REQUEST_STAFF_ASSIGNMENT_ONLY_DENIED_FOR_ENROLLED=PASS\nWRONG_CANONICAL_PERFORMER_DENIED=PASS\nRESPONSIBLE_DOCTOR_ALONE_NOT_PHYSICAL_PERFORMER=PASS\n";

$terminalRequest = $doctorRequest + 1100;
$terminalFacility = $doctorFacility . '-T';
$terminalDoctor = $terminalRequest + 1;
$terminalPerformer = $terminalRequest + 2;
$terminalCommand = vcw_assignment_fixture($db, $terminalRequest, $terminalFacility, $terminalDoctor, $terminalDoctor, array(array($terminalPerformer, $terminalPerformer, 'Perawat')));
$terminalService = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$terminalAssigned = $terminalService->assign($terminalRequest, $terminalCommand, $terminalPerformer, 'terminal-' . $terminalRequest);
vcw_assert_true(!empty($terminalAssigned['ok']), 'terminal assignment setup failed');
$db->where('request_id', $terminalRequest)->where('status', 'aktif')->update('request_visit_performer_assignments', array('status' => 'selesai'));
$terminalContext = $careTeam->requestContext($terminalRequest, $terminalPerformer);
vcw_assert_true(empty($terminalContext['is_visit_performer']), 'terminal canonical performer must not authorize physical visit');
foreach (array('diganti', 'dibatalkan') as $terminalStatus) {
    $statusRequest = $terminalRequest + ($terminalStatus === 'diganti' ? 25 : 50);
    $statusFacility = $terminalFacility . '-' . ($terminalStatus === 'diganti' ? 'DG' : 'DB');
    $statusDoctor = $statusRequest + 1;
    $statusPerformer = $statusRequest + 2;
    $statusCommand = vcw_assignment_fixture($db, $statusRequest, $statusFacility, $statusDoctor, $statusDoctor, array(array($statusPerformer, $statusPerformer, 'Perawat')));
    $statusService = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
    $statusAssigned = $statusService->assign($statusRequest, $statusCommand, $statusPerformer, 'status-' . $statusRequest);
    vcw_assert_true(!empty($statusAssigned['ok']), 'status setup failed');
    $db->where('visit_assignment_id', (int) $statusAssigned['assignment_id'])->update('request_visit_performer_assignments', array('status' => $terminalStatus));
    $statusContext = $careTeam->requestContext($statusRequest, $statusPerformer);
    vcw_assert_true(empty($statusContext['is_visit_performer']), 'terminal status authorized physical visit: ' . $terminalStatus);
}
echo "TERMINAL_CANONICAL_ASSIGNMENT_DENIED=PASS\n";
