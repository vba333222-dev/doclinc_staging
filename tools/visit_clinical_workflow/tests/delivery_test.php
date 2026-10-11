<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
$app->config->set('realtime_requests_enabled', true);
$app->config->set('realtime_client_enabled', true);
$app->config->set('realtime_notifications_enabled', true);

require_once APPPATH . 'helpers/request_realtime_helper.php';
require_once APPPATH . 'libraries/Visit_disposition_service.php';
require_once APPPATH . 'libraries/Visit_assignment_service.php';
require_once APPPATH . 'libraries/Clinical_review_service.php';
require_once APPPATH . 'libraries/Clinical_finalization_service.php';
require_once APPPATH . 'libraries/Clinical_amendment_service.php';
require_once APPPATH . 'libraries/Clinical_closure_service.php';

if ((int) $db->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nakes_facility_placements'")->row()->c !== 1) {
    $db->query("CREATE TABLE nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, staff_id INT UNSIGNED NOT NULL, facility_code VARCHAR(64) NOT NULL, effective_from DATETIME NOT NULL, effective_until DATETIME NULL, status ENUM('active','ended') NOT NULL DEFAULT 'active', active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED, created_by_user_id INT NOT NULL, ended_by_user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (placement_id), UNIQUE KEY uq_nakes_active_staff (active_staff_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function delivery_totals($db)
{
    return array(
        'request_events' => (int) $db->count_all_results('request_events'),
        'notifications' => (int) $db->count_all_results('notifications'),
        'realtime_outbox' => (int) $db->count_all_results('realtime_outbox'),
    );
}

function delivery_assert_replay($db, $label, array $before)
{
    $after = delivery_totals($db);
    foreach ($before as $table => $count) {
        vcw_assert_same($count, $after[$table], 'replay must not add rows to ' . $table . ' for ' . $label);
    }
    echo 'TASK12_REPLAY_' . strtoupper($label) . '=PASS' . "\n";
}

function delivery_event_key_count($db, $key)
{
    return (int) $db->where('domain_event_key', $key)->count_all_results('request_events');
}

function delivery_user($db, $id, $facility, $staff = null, $profession = 'Perawat')
{
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($id, 'Task12 ' . $id, 'task12-' . $id . '@example.invalid', 'task12-' . $id, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, array('cost' => 4)), 'dokter', 'aktif', 0, $facility));
    if ($staff !== null) {
        $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($staff, $facility, $id, 'Task12 Staff ' . $id, $profession, 'aktif'));
        $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($staff, $facility, '2026-01-01 00:00:00', 'active', $id));
    }
}


$base = 170000 + (int) (microtime(true) * 100) % 10000;
$requestA = $base;
$facilityA = 'T12A-' . $base;
$commandA = $requestA - 1;
$doctorA = $requestA + 1;
$performerA = $requestA + 2;
vcw_assert_safe_request_id($requestA);

$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facilityA, 'Task12 A', 'aktif'));
delivery_user($db, $commandA, $facilityA);
delivery_user($db, $doctorA, $facilityA, $doctorA, 'dokter');
delivery_user($db, $performerA, $facilityA, $performerA, 'dokter');
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?)', array($requestA, $doctorA, 'synthetic', 'Accepted', $facilityA, 'Task12 A', 'not_started'));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($requestA, $doctorA, $doctorA, $doctorA, 'aktif'));

$disposition = new Visit_disposition_service($db, new Visit_workflow_policy($db, true), true);
$assignment = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));

$dispositionKey = 'task12-disposition-' . $requestA;
$dispositionInput = array('decision' => 'visit', 'urgency' => 'routine', 'required_profession' => 'dokter');
$created = $disposition->create($requestA, $doctorA, $dispositionInput, $dispositionKey);
vcw_assert_true(!empty($created['ok']), 'disposition create first call code=' . (string) ($created['code'] ?? 'none'));
$mid = delivery_totals($db);
$createdReplay = $disposition->create($requestA, $doctorA, $dispositionInput, $dispositionKey);
vcw_assert_true(!empty($createdReplay['ok']), 'disposition create replay');
delivery_assert_replay($db, 'disposition_create', $mid);
vcw_assert_same(1, delivery_event_key_count($db, 'visit-disposition:' . (int) $created['disposition_id'] . ':created'), 'disposition create single event');
vcw_assert_same(1, (int) $db->where('request_id', $requestA)->count_all_results('visit_dispositions'), 'disposition create single row');

$reviseKey = 'task12-disposition-revise-' . $requestA;
$reviseInput = array('decision' => 'visit', 'urgency' => 'priority', 'required_profession' => 'dokter');
$revised = $disposition->revise($requestA, $doctorA, (int) $created['version_no'], $reviseInput, $reviseKey);
vcw_assert_true(!empty($revised['ok']), 'disposition revise first call code=' . (string) ($revised['code'] ?? 'none'));
$mid = delivery_totals($db);
$revisedReplay = $disposition->revise($requestA, $doctorA, (int) $created['version_no'], $reviseInput, $reviseKey);
vcw_assert_true(!empty($revisedReplay['ok']), 'disposition revise replay');
delivery_assert_replay($db, 'disposition_revise', $mid);
vcw_assert_same(1, delivery_event_key_count($db, 'visit-disposition:' . (int) $revised['disposition_id'] . ':created'), 'disposition revise single event');
vcw_assert_same(1, delivery_event_key_count($db, 'visit-disposition:' . (int) $created['disposition_id'] . ':superseded'), 'disposition supersede single event');

$assignmentKey = 'task12-assignment-' . $requestA;
$assigned = $assignment->assign($requestA, $commandA, $performerA, $assignmentKey);
vcw_assert_true(!empty($assigned['ok']), 'assignment first call code=' . (string) ($assigned['safe_error_code'] ?? ($assigned['code'] ?? 'none')));
$mid = delivery_totals($db);
$assignedReplay = $assignment->assign($requestA, $commandA, $performerA, $assignmentKey);
vcw_assert_true(!empty($assignedReplay['ok']), 'assignment replay');
delivery_assert_replay($db, 'assignment', $mid);
vcw_assert_same(1, (int) $db->where('request_id', $requestA)->count_all_results('request_visit_performer_assignments'), 'assignment single row');
vcw_assert_same(1, delivery_event_key_count($db, 'visit-assignment:' . (int) $assigned['assignment_id'] . ':assigned'), 'assignment single event');

$requestB = $base + 100;
$facilityB = 'T12B-' . $base;
$doctorB = $requestB + 1;
$performerB = $requestB + 2;
vcw_assert_safe_request_id($requestB);

$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facilityB, 'Task12 B', 'aktif'));
delivery_user($db, $requestB - 1, $facilityB);
delivery_user($db, $doctorB, $facilityB, $doctorB, 'dokter');
delivery_user($db, $performerB, $facilityB, $performerB, 'dokter');
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)', array($requestB, $doctorB, 'synthetic', 'Accepted', $facilityB, 'Task12 B', 'completed', 'visit', $doctorB, $performerB));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($requestB, $doctorB, $doctorB, $doctorB, 'aktif'));
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', array($requestB, 1, 'visit', 'routine', $doctorB, 't12b-disp-' . $requestB));
$db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($requestB, $performerB, $performerB, $doctorB, 'aktif'));
$assignmentB = (int) $db->insert_id();
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES (?,?,?,?,?,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),?,?)", array($requestB, $assignmentB, 1, $performerB, $performerB, $performerB, 't12b-result-' . $requestB));
$resultB = (int) $db->insert_id();
$db->query('INSERT INTO medicalrecords (request_id,diagnosis,treatment,recommendations,anamnesis,responsible_doctor_user_id,recorded_by_user_id) VALUES (?,?,?,?,?,?,?)', array($requestB, 'd', 't', 'r', 'a', $doctorB, $performerB));
$recordB = (int) $db->insert_id();

$reviewService = new Clinical_review_service($db);
$reviewKey = 'task12-review-' . $requestB;
$reviewed = $reviewService->review($requestB, $resultB, $doctorB, 'approved', null, null, $reviewKey);
vcw_assert_true(($reviewed['status'] ?? '') === 'success' || !empty($reviewed['ok']), 'review first call result=' . json_encode($reviewed));
$mid = delivery_totals($db);
$reviewedReplay = $reviewService->review($requestB, $resultB, $doctorB, 'approved', null, null, $reviewKey);
vcw_assert_true(($reviewedReplay['status'] ?? '') === 'success' || !empty($reviewedReplay['ok']), 'review replay result=' . json_encode($reviewedReplay));
delivery_assert_replay($db, 'review', $mid);
vcw_assert_same(1, (int) $db->where('request_id', $requestB)->count_all_results('clinical_reviews'), 'review single row');

$finalizationService = new Clinical_finalization_service($db);
$finalizeKey = 'task12-finalize-' . $requestB;
$finalized = $finalizationService->finalize($requestB, $doctorB, $finalizeKey);
vcw_assert_true(($finalized['status'] ?? '') === 'success', 'finalization first call result=' . json_encode($finalized));
$mid = delivery_totals($db);
$finalizedReplay = $finalizationService->finalize($requestB, $doctorB, $finalizeKey);
vcw_assert_true(($finalizedReplay['status'] ?? '') === 'success' && !empty($finalizedReplay['idempotent']), 'finalization replay result=' . json_encode($finalizedReplay));
delivery_assert_replay($db, 'finalization', $mid);
vcw_assert_same(1, delivery_event_key_count($db, 'clinical-finalization:' . $finalizeKey), 'finalization single event');

$closureService = new Clinical_closure_service($db);
$closureKey = 'task12-closure-' . $requestB;
$closed = $closureService->closeClinicalVisit($requestB, $doctorB, $closureKey);
vcw_assert_true(($closed['status'] ?? '') === 'success', 'closure first call result=' . json_encode($closed));
$mid = delivery_totals($db);
$closedReplay = $closureService->closeClinicalVisit($requestB, $doctorB, $closureKey);
vcw_assert_true(($closedReplay['status'] ?? '') === 'success' && !empty($closedReplay['idempotent']), 'closure replay result=' . json_encode($closedReplay));
delivery_assert_replay($db, 'closure', $mid);
vcw_assert_same(1, (int) $db->where('request_id', $requestB)->count_all_results('clinical_closure_operations'), 'closure single receipt');
vcw_assert_same(1, delivery_event_key_count($db, 'request:' . $requestB . ':clinical-closed'), 'closure single event');
vcw_assert_same('Completed', (string) $db->where('request_id', $requestB)->get('requests')->row()->request_status, 'closure completed request');

$amendmentService = new Clinical_amendment_service($db);
$amendKey = 'task12-amend-' . $requestB;
$amendItems = array(array('field_key' => 'diagnosis', 'corrected_value' => 'revised'));
$amended = $amendmentService->amend($requestB, $recordB, $doctorB, 'Task 12 correction', $amendItems, $amendKey);
vcw_assert_true(($amended['status'] ?? '') === 'success', 'amendment first call result=' . json_encode($amended));
$mid = delivery_totals($db);
$amendedReplay = $amendmentService->amend($requestB, $recordB, $doctorB, 'Task 12 correction', $amendItems, $amendKey);
vcw_assert_true(($amendedReplay['status'] ?? '') === 'success', 'amendment replay result=' . json_encode($amendedReplay));
delivery_assert_replay($db, 'amendment', $mid);
vcw_assert_same(1, (int) $db->where('record_id', $recordB)->count_all_results('clinical_amendments'), 'amendment single row');
vcw_assert_same(1, delivery_event_key_count($db, 'clinical-amendment:' . (int) $amended['amendment_id']), 'amendment single event');

echo "TASK12_EXACTLY_ONCE=PASS\n";
echo "TASK12_EVENT_DEDUPE=PASS\n";
echo "TASK12_NOTIFICATION_DEDUPE=PASS\n";
echo "TASK12_OUTBOX_DEDUPE=PASS\n";
