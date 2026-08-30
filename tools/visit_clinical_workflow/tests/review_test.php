<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'libraries/Visit_result_service.php';
require_once APPPATH . 'libraries/Clinical_review_service.php';
require_once __DIR__ . '/result_submission_support.php';
if (!$db->table_exists('nakes_facility_placements')) {
    $db->query("CREATE TABLE nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,staff_id INT UNSIGNED NOT NULL,facility_code VARCHAR(64) NOT NULL,effective_from DATETIME NOT NULL,effective_until DATETIME NULL,status ENUM('active','ended') NOT NULL DEFAULT 'active',active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED,created_by_user_id INT NOT NULL,ended_by_user_id INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(placement_id),UNIQUE KEY uq_nakes_active_staff(active_staff_key)) ENGINE=InnoDB");
}
$base = 28101; $facility = 'REVIEW-8C-' . $base; $request = $base; $command=$base+1; $doctor=$base+2; $performer=$base+3;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility,'Review Facility','aktif'));
foreach (array(array($command,'Perawat',0),array($doctor,'dokter',$doctor),array($performer,'Perawat',$performer)) as $u) {
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($u[0],'Review '.$u[0],'review'.$u[0].'@invalid','review'.$u[0],'x','dokter','aktif',0,$facility));
    if ($u[2]) { $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($u[2],$facility,$u[0],'Review Staff '.$u[0],$u[1],'aktif')); $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($u[2],$facility,'2026-01-01','active',$u[0])); }
}
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)', array($request,$doctor,'synthetic','Accepted',$facility,'Review Facility','completed','visit',$doctor,$performer));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request,$doctor,$doctor,$doctor,'aktif'));
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', array($request,1,'visit','routine',$doctor,'review-disp-'.$request));
$db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request,$performer,$performer,$command,'aktif'));
$fixture = array('request'=>$request,'doctor'=>$doctor,'performer'=>$performer,'facility'=>$facility,'assignment'=>(int)$db->insert_id());
$resultService = new Visit_result_service($db);
$reviewService = new Clinical_review_service($db);
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES (" . $request . "," . $fixture['assignment'] . ",1," . $performer . "," . $performer . ",'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6)," . $performer . ",'review-submit-" . $request . "')");
$resultId = (int) $db->insert_id();
$review = $reviewService->review($fixture['request'], $resultId, $fixture['doctor'], 'correction_required', 'Perlu koreksi.', 'Catatan review', 'review-key-' . $fixture['request']);
vcw_assert_same('success', $review['status'] ?? null, 'canonical Responsible Doctor review');
$row = $db->where('clinical_review_id', (int) $review['clinical_review_id'])->get('clinical_reviews')->row();
vcw_assert_true($row && $row->responsible_assignment_id !== null && $row->reviewer_visit_assignment_id === null, 'Responsible Doctor provenance precedence');
$replay = $reviewService->review($fixture['request'], $resultId, $fixture['doctor'], 'correction_required', 'Perlu koreksi.', 'Catatan review', 'review-key-' . $fixture['request']);
vcw_assert_true(($replay['idempotent'] ?? false) === true, 'review idempotent replay');
$approval = $reviewService->review($fixture['request'], $resultId, $fixture['doctor'], 'approved', null, null, 'review-approval-' . $fixture['request']);
vcw_assert_same('REVIEW_APPROVAL_REQUIRES_COMPLETION', $approval['safe_error_code'] ?? null, 'approval requires Task 8D completion');
vcw_assert_same(1, (int) $db->where('visit_result_id', $resultId)->count_all_results('clinical_reviews'), 'one immutable review per result');
echo "TASK8C_REVIEW_API_PRESENT=PASS\nTASK8C_CORRECTION_REVIEW=PASS\nTASK8C_IDEMPOTENCY=PASS\nTASK8C_APPROVAL_GUARD=PASS\n";
