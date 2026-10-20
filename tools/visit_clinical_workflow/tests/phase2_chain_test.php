<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
$app->config->set('visit_clinical_workflow_enabled', true);
require_once APPPATH . 'libraries/Visit_disposition_service.php';
require_once APPPATH . 'libraries/Visit_result_service.php';
require_once APPPATH . 'libraries/Clinical_record_service.php';
require_once APPPATH . 'libraries/Clinical_review_service.php';
require_once APPPATH . 'libraries/Clinical_finalization_service.php';
require_once APPPATH . 'libraries/Clinical_closure_service.php';

$passed = 0;
$failed = 0;
function p2_assert($condition, $label)
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL {$label}\n";
}

$base = random_int(49200, 49800);
$request = $base;
$command = $base + 1;
$doctor = $base + 2;
$performer = $base + 3;
$facility = 'P2-CHAIN-' . $base;
$db->db_debug = false;
p2_assert((int) $db->query('SELECT COUNT(*) c FROM requests WHERE request_id=?', array($request))->row()->c === 0, 'fixture_request_id_unused');

$db->trans_begin();
try {
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'Phase 2 Facility', 'aktif'));
    foreach (array(array($command, 'Perawat', 0), array($doctor, 'dokter', $doctor), array($performer, 'Perawat', $performer)) as $u) {
        $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($u[0], 'Phase2 ' . $u[0], 'p2-' . $u[0] . '-' . $base . '@invalid', 'p2-' . $u[0] . '-' . $base, 'x', 'dokter', 'aktif', 0, $facility));
        if ($u[2] > 0) {
            $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($u[2], $facility, $u[0], 'Phase2 Staff ' . $u[0], $u[1], 'aktif'));
            $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($u[2], $facility, '2026-01-01 00:00:00', 'active', $u[0]));
        }
    }
    $now = date('Y-m-d H:i:s');
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)', array($request, $command, 'synthetic', 'Accepted', $facility, 'Phase 2 Facility', 'arrived', 'visit', $doctor, $performer));
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,?)', array($request, $doctor, $doctor, $doctor, 'aktif', $now));
    $db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,?)', array($request, 1, 'visit', 'routine', $doctor, 'p2-chain-disp-' . $base, $now));
    $db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,?)', array($request, $performer, $performer, $doctor, 'aktif', $now));

    $results = new Visit_result_service($db);
    $draft = $results->getOrCreateDraft($request, $performer);
    p2_assert(!empty($draft['status']) && $draft['status'] === 'success', 'draft_created');
    $resultId = (int) ($draft['visit_result_id'] ?? 0);
    $saved = $results->saveDraft($resultId, $performer, 0, array(
        'observation_summary' => 'TD 150/90',
        'findings' => array(array('key' => 'td', 'label' => 'Tekanan darah', 'value' => '150/90')),
        'actions' => array(array('key' => 'edukasi', 'label' => 'Edukasi', 'value' => 'Diet rendah garam')),
        'performer_notes' => 'Kontrol 3 hari',
        'anamnesis' => 'Pusing sejak 3 hari',
        'therapy' => 'Amlodipine 5 mg',
    ));
    p2_assert(!empty($saved['status']) && $saved['status'] === 'success', 'draft_saved');
    $savedNotes = $db->query('SELECT anamnesis,therapy FROM visit_results WHERE visit_result_id=?', array($resultId))->row();
    p2_assert($savedNotes && $savedNotes->anamnesis === 'Pusing sejak 3 hari' && $savedNotes->therapy === 'Amlodipine 5 mg', 'draft_notes_saved');

    $db->query('INSERT INTO request_vital_sign_measurements (request_id,measured_by_user_id,measured_by_staff_id,responsible_doctor_user_id,visit_performer_user_id,systolic,diastolic,pulse,respiratory_rate,temperature_c,oxygen_saturation,notes,measured_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array($request, $performer, $performer, $doctor, $performer, 150, 90, 88, 18, 36.6, 98, 'Phase 2', $now, $now));
    $measurementId = (int) $db->insert_id();
    $db->query('UPDATE requests SET visit_status=? WHERE request_id=?', array('completed', $request));
    $submitted = $results->submit($resultId, $performer, array($measurementId), 'p2-chain-submit-' . $base);
    p2_assert(!empty($submitted['status']) && $submitted['status'] === 'success', 'result_submitted');

    $records = new Clinical_record_service($db);
    $record = $records->saveDraft($request, $doctor, array(
        'diagnosis' => 'Hipertensi esensial', 'treatment' => 'Amlodipine 5 mg',
        'recommendations' => 'Kontrol TD tiap hari', 'anamnesis' => 'Pusing sejak 3 hari',
    ), 'p2-chain-record-' . $base);
    p2_assert(!empty($record['status']) && $record['status'] === 'success', 'clinical_record_saved');

    $reviews = new Clinical_review_service($db);
    $review = $reviews->review($request, $resultId, $doctor, 'approved', '', 'Sesuai', 'p2-chain-review-' . $base);
    p2_assert(!empty($review['status']) && $review['status'] === 'success', 'review_approved');

    $finalization = new Clinical_finalization_service($db);
    $finalized = $finalization->finalize($request, $doctor, 'p2-chain-final-' . $base);
    p2_assert(!empty($finalized['status']) && $finalized['status'] === 'success', 'record_finalized');

    $closure = new Clinical_closure_service($db);
    $closed = $closure->closeClinicalVisit($request, $doctor, 'p2-chain-close-' . $base);
    p2_assert(!empty($closed['status']) && $closed['status'] === 'success', 'visit_closed');

    $row = $db->query('SELECT request_status FROM requests WHERE request_id=?', array($request))->row();
    p2_assert($row && (string) $row->request_status === 'Completed', 'request_completed');
    $closureRow = $db->query('SELECT closure_mode FROM clinical_closure_operations WHERE request_id=?', array($request))->row();
    p2_assert($closureRow && (string) $closureRow->closure_mode === 'visit', 'closure_mode_visit');
    $recordRow = $db->query('SELECT clinical_finalized_at FROM medicalrecords WHERE request_id=? ORDER BY record_id DESC LIMIT 1', array($request))->row();
    p2_assert($recordRow && $recordRow->clinical_finalized_at !== null, 'record_finalized_at_set');
} catch (Throwable $exception) {
    p2_assert(false, 'chain_exception:' . $exception->getMessage());
}
$db->trans_rollback();

$left = (int) $db->query('SELECT COUNT(*) c FROM requests WHERE request_id=?', array($request))->row()->c;
p2_assert($left === 0, 'chain_rolled_back');
echo 'PHASE2_CHAIN=PASSED=' . $passed . ', FAILED=' . $failed . "\n";
exit($failed > 0 ? 1 : 0);
