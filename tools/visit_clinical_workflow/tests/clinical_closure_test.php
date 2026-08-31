<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
$app->config->set('realtime_requests_enabled', true);
$app->config->set('realtime_client_enabled', true);
$app->config->set('realtime_notifications_enabled', true);
require_once APPPATH . 'libraries/Clinical_closure_service.php';
$db->query("CREATE TABLE IF NOT EXISTS nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,staff_id INT UNSIGNED NOT NULL,facility_code VARCHAR(64) NOT NULL,effective_from DATETIME NOT NULL,effective_until DATETIME NULL,status ENUM('active','ended') NOT NULL DEFAULT 'active',active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED,created_by_user_id INT NOT NULL,ended_by_user_id INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(placement_id),UNIQUE KEY uq_nakes_active_staff(active_staff_key)) ENGINE=InnoDB");

$base = 150000 + (int) (microtime(true) * 100) % 10000;
$requestId = $base;
$actor = $base + 1;
$facility = 'T10-' . $base;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'Task 10 Facility', 'aktif'));
$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($base - 1, 'Task 10 Command', 't10cmd' . $base . '@invalid', 't10cmd' . $base, 'x', 'dokter', 'aktif', 0, $facility));
$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($actor, 'Task 10 Doctor', 't10' . $actor . '@invalid', 't10' . $actor, 'x', 'dokter', 'aktif', 0, $facility));
$db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($actor, $facility, $actor, 'Task 10 Doctor', 'dokter', 'aktif'));
$db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($actor, $facility, '2026-01-01', 'active', $actor));
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,consultation_mode,responsible_doctor_user_id) VALUES (?,?,?,?,?,?,?,?)', array($requestId, $actor, 'synthetic', 'Accepted', $facility, 'Task 10 Facility', 'non_visit', $actor));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($requestId, $actor, $actor, $actor, 'aktif'));
$rdAssignment = (int) $db->insert_id();
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', array($requestId, 1, 'non_visit', null, $actor, 't10-disp-' . $requestId));
$db->query('INSERT INTO medicalrecords (request_id,diagnosis,treatment,recommendations,anamnesis,responsible_doctor_user_id,recorded_by_user_id,clinical_finalized_at,clinical_finalized_by_user_id) VALUES (?,?,?,?,?,?,?,NOW(6),?)', array($requestId, 'd', 't', 'r', 'a', $actor, $actor, $actor));
$recordId = (int) $db->insert_id();

$service = new Clinical_closure_service($db);
$wrongMode = $service->closeClinicalVisit($requestId, $actor, 't10-wrong-mode-' . $requestId);
vcw_assert_same('CLINICAL_CLOSURE_MODE_MISMATCH', $wrongMode['safe_error_code'] ?? null, 'closure mode matches request mode');
$result = $service->closeClinicalConsultation($requestId, $actor, 't10-closure-' . $requestId);
vcw_assert_same('success', $result['status'] ?? null, 'non-Visit responsible doctor closure');
vcw_assert_same($requestId, (int) ($result['request_id'] ?? 0), 'closure request identity');
vcw_assert_true(!empty($result['closure_operation_id']), 'closure receipt identity');
$request = $db->where('request_id', $requestId)->get('requests')->row();
vcw_assert_same('Completed', (string) $request->request_status, 'closure completes request');
$receipt = $db->where('request_id', $requestId)->get('clinical_closure_operations')->row();
vcw_assert_same('non_visit', (string) $receipt->closure_mode, 'closure mode');
vcw_assert_same('responsible_doctor', (string) $receipt->authority_source, 'closure provenance');
$eventCount = (int) $db->where('request_id', $requestId)->where('domain_event_key', 'request:' . $requestId . ':clinical-closed')->count_all_results('request_events');
vcw_assert_same(1, $eventCount, 'closure event');
$notificationCount = (int) $db->where('entity_type', 'request')->where('entity_id', (string) $requestId)->where('event_type', 'consultation_completed')->count_all_results('notifications');
$outboxCount = (int) $db->where('event_type', 'request.completed')->where('aggregate_id', (string) $requestId)->count_all_results('realtime_outbox');
vcw_assert_same(1, $notificationCount, 'closure notification persisted once');
vcw_assert_true($outboxCount >= 1, 'closure request outbox persisted');

$replay = $service->closeClinicalConsultation($requestId, $actor, 't10-closure-' . $requestId);
vcw_assert_same('success', $replay['status'] ?? null, 'historical closure replay');
vcw_assert_true(!empty($replay['idempotent']), 'historical replay marker');
vcw_assert_same((int) $receipt->closure_operation_id, (int) ($replay['closure_operation_id'] ?? 0), 'historical receipt identity');

$conflict = $service->closeClinicalConsultation($requestId, $actor + 10, 't10-closure-' . $requestId);
vcw_assert_same('CLOSURE_KEY_CONFLICT', $conflict['safe_error_code'] ?? null, 'same key conflicting actor');

$newKey = $service->closeClinicalConsultation($requestId, $actor, 't10-new-key-' . $requestId);
vcw_assert_same('CLINICAL_ALREADY_CLOSED', $newKey['safe_error_code'] ?? null, 'new key after closure');

// Visit closure: exact approved-result assignment authorizes the performer,
// even though the historical assignment is already in its post-review state.
require_once APPPATH . 'libraries/Clinical_finalization_service.php';
$v = $base + 1000; $vrd = $v + 1; $vperf = $v + 2; $vfac = 'T10V-' . $v;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($vfac, 'Task 10 Visit', 'aktif'));
foreach (array($v - 1 => 'dokter', $vrd => 'dokter', $vperf => 'dokter') as $uid => $role) {
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($uid, 'T10 Visit ' . $uid, 't10v' . $uid . '@invalid', 't10v' . $uid, 'x', $role, 'aktif', 0, $vfac));
}
foreach (array($vrd, $vperf) as $uid) {
    $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($uid, $vfac, $uid, 'T10 Visit Doctor', 'dokter', 'aktif'));
    $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($uid, $vfac, '2026-01-01', 'active', $uid));
}
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)', array($v, $vrd, 'synthetic', 'Accepted', $vfac, 'Task 10 Visit', 'completed', 'visit', $vrd, $vperf));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($v, $vrd, $vrd, $vrd, 'aktif'));
$vra = (int) $db->insert_id();
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', array($v, 1, 'visit', 'routine', $vrd, 't10v-disp-' . $v));
$db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,completed_at) VALUES (?,?,?,?,?,NOW(6),NOW(6))', array($v, $vperf, $vperf, $vrd, 'selesai'));
$vva = (int) $db->insert_id();
$db->query('INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES (?,?,?,?,?,"submitted",0,"[]","[]",NOW(6),NOW(6),NOW(6),?,?)', array($v, $vva, $vperf, $vperf, $vperf, $vperf, 't10v-result-' . $v));
$vresult = (int) $db->insert_id();
$db->query('INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,reviewer_visit_assignment_id,decision,reviewed_at,idempotency_key) VALUES (?,?,?,?,?,?,NOW(6),?)', array($v, $vresult, $vrd, $vra, null, 'approved', 't10v-review-' . $v));
$db->query('INSERT INTO medicalrecords (request_id,diagnosis,treatment,recommendations,anamnesis,responsible_doctor_user_id,recorded_by_user_id) VALUES (?,?,?,?,?,?,?)', array($v, 'd', 't', 'r', 'a', $vrd, $vperf));
$finalized = (new Clinical_finalization_service($db))->finalize($v, $vrd, 't10v-finalize-' . $v);
vcw_assert_same('success', $finalized['status'] ?? null, 'visit prerequisite finalization');
$visitResult = $service->closeClinicalVisit($v, $vperf, 't10v-close-' . $v);
vcw_assert_same('success', $visitResult['status'] ?? null, 'doctor performer visit closure');
vcw_assert_same('doctor_visit_performer', $visitResult['authority_source'] ?? null, 'performer provenance');
$visitReceipt = $db->where('request_id', $v)->get('clinical_closure_operations')->row();
vcw_assert_same($vva, (int) $visitReceipt->visit_assignment_id, 'closure binds result assignment');
vcw_assert_same('selesai', (string) $db->where('visit_assignment_id', $vva)->get('request_visit_performer_assignments')->row()->status, 'performer assignment terminal status accepted');
echo "CLINICAL_CLOSURE=PASS\nCLINICAL_CLOSURE_EVENT=PASS\nCLINICAL_CLOSURE_IDEMPOTENCY=PASS\n";
echo "CLINICAL_CLOSURE_VISIT_PERFORMER=PASS\n";
