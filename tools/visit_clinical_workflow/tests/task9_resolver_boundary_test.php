<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
require_once APPPATH . 'libraries/Visit_workflow_state_resolver.php';
require_once APPPATH . 'libraries/Clinical_finalization_service.php';
require_once APPPATH . 'libraries/Clinical_amendment_service.php';
$db->query("CREATE TABLE IF NOT EXISTS nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,staff_id INT UNSIGNED NOT NULL,facility_code VARCHAR(64) NOT NULL,effective_from DATETIME NOT NULL,effective_until DATETIME NULL,status ENUM('active','ended') NOT NULL DEFAULT 'active',active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED,created_by_user_id INT NOT NULL,ended_by_user_id INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(placement_id),UNIQUE KEY uq_nakes_active_staff(active_staff_key)) ENGINE=InnoDB");

function t9rb_fixture($db, $id, $mode, $finalized = false)
{
    $id = (int) $id; $rd = $id + 1; $performer = $id + 2; $facility = 'T9RB-' . $id;
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', [$facility, 'Task9 resolver', 'aktif']);
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', [$id, 'T9RB command', 't9rbcmd' . $id . '@invalid', 't9rbcmd' . $id, 'x', 'dokter', 'aktif', 0, $facility]);
    foreach ([$rd, $performer] as $u) {
        $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', [$u, 'T9RB ' . $u, 't9rb' . $u . '@invalid', 't9rb' . $u, 'x', 'dokter', 'aktif', 0, $facility]);
        $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', [$u, $facility, $u, 'T9RB staff', 'dokter', 'aktif']);
        $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', [$u, $facility, '2026-01-01', 'active', $u]);
    }
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)', [$id, $rd, 'synthetic', 'Accepted', $facility, 'Task9 resolver', 'completed', $mode, $rd, $performer]);
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', [$id, $rd, $rd, $rd, 'aktif']);
    $db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', [$id, 1, $mode === 'non_visit' ? 'non_visit' : 'visit', $mode === 'non_visit' ? null : 'routine', $rd, 't9rb-d-' . $id]);
    $db->query('INSERT INTO medicalrecords (request_id,diagnosis,treatment,recommendations,anamnesis,responsible_doctor_user_id,recorded_by_user_id,clinical_finalized_at,clinical_finalized_by_user_id) VALUES (?,?,?,?,?,?,?, ?, ?)', [$id, 'initial', 'initial', 'initial', 'history', $rd, $rd, $finalized ? date('Y-m-d H:i:s.u') : null, $finalized ? $rd : null]);
    $record = (int) $db->insert_id();
    if ($mode === 'visit') {
        $db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,completed_at) VALUES (?,?,?,?,?,NOW(6),NOW(6))', [$id, $performer, $performer, $rd, 'selesai']);
        $assignment = (int) $db->insert_id();
        $db->query('INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES (?,?,?,?,?,"submitted",0,"[]","[]",NOW(6),NOW(6),NOW(6),?,?)', [$id, $assignment, $performer, $performer, $performer, $performer, 't9rb-r-' . $id]);
        $result = (int) $db->insert_id();
        $responsibleAssignment = (int) $db->where('request_id', $id)->get('request_responsible_doctor_assignments')->row()->responsible_assignment_id;
        $db->query('INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,reviewed_at,idempotency_key) VALUES (?,?,?,?,?,NOW(6),?)', [$id, $result, $rd, $responsibleAssignment, 'approved', 't9rb-review-' . $id]);
    }
    return [$id, $record];
}

$resolver = new Visit_workflow_state_resolver($db, null, true);
$finalizer = new Clinical_finalization_service($db);
$amender = new Clinical_amendment_service($db);
[$visit, $visitRecord] = t9rb_fixture($db, 47001, 'visit', false);
vcw_assert_same('WAITING_CLINICAL_FINALIZATION', $resolver->resolve($visit)['state'] ?? null, 'Visit unfinalized resolver');
$visitFinalization = $finalizer->finalize($visit, 47002, 't9rb-final-' . $visit);
vcw_assert_same('success', $visitFinalization['status'] ?? null, 'Visit finalization succeeds');
vcw_assert_same('READY_FOR_CLOSURE', $resolver->resolve($visit)['state'] ?? null, 'Visit finalized resolver');
$visitAmendment = $amender->amend($visit, $visitRecord, 47002, 'resolver amendment', [['field_key' => 'diagnosis', 'corrected_value' => 'amended']], 't9rb-amend-' . $visit);
vcw_assert_same('success', $visitAmendment['status'] ?? null, 'Visit amendment succeeds');
vcw_assert_same('READY_FOR_CLOSURE', $resolver->resolve($visit)['state'] ?? null, 'Visit amendment remains closure');

[$nonVisit, $nonVisitRecord] = t9rb_fixture($db, 47101, 'non_visit', false);
vcw_assert_same('WAITING_CLINICAL_FINALIZATION', $resolver->resolve($nonVisit)['state'] ?? null, 'Non-visit unfinalized resolver');
$nonVisitFinalization = $finalizer->finalize($nonVisit, 47102, 't9rb-final-' . $nonVisit);
vcw_assert_same('success', $nonVisitFinalization['status'] ?? null, 'Non-visit finalization succeeds');
vcw_assert_same('READY_FOR_CLOSURE', $resolver->resolve($nonVisit)['state'] ?? null, 'Non-visit finalized resolver');
$boundaryRows = $db->query('SELECT request_id, request_status FROM requests WHERE request_id IN (?,?)', [$visit, $nonVisit])->result_array();
foreach ($boundaryRows as $row) vcw_assert_same('Accepted', $row['request_status'], 'Task 9 leaves request Accepted');
$closureEvents = (int) $db->where_in('request_id', [$visit, $nonVisit])->where_in('event_type', ['clinical_visit.closed', 'clinical_consultation.closed', 'request.completed'])->count_all_results('request_events');
vcw_assert_same(0, $closureEvents, 'Task 9 emits no closure/completion event');
echo "TASK9_VISIT_RESOLVER=PASS\nTASK9_NONVISIT_RESOLVER=PASS\nTASK9_AMENDMENT_RESOLVER_STABLE=PASS\nTASK9_RESOLVER=PASS\nTASK9_REQUEST_COMPLETION_NOT_STARTED=PASS\nTASK9_CLINICAL_CLOSURE_NOT_STARTED=PASS\n";
