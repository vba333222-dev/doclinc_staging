<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'libraries/Visit_result_service.php';
require_once APPPATH . 'libraries/Clinical_review_service.php';
require_once APPPATH . 'libraries/Nakes_placement_store.php';
require_once APPPATH . 'libraries/Visit_workflow_state_resolver.php';
require_once APPPATH . 'libraries/Visit_workflow_policy.php';
require_once __DIR__ . '/result_submission_support.php';
$db->query("CREATE TABLE IF NOT EXISTS nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,staff_id INT UNSIGNED NOT NULL,facility_code VARCHAR(64) NOT NULL,effective_from DATETIME NOT NULL,effective_until DATETIME NULL,status ENUM('active','ended') NOT NULL DEFAULT 'active',active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED,created_by_user_id INT NOT NULL,ended_by_user_id INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(placement_id),UNIQUE KEY uq_nakes_active_staff(active_staff_key)) ENGINE=InnoDB");
function review8c_doctor_fixture($db, $request, $rd, $performer, $facility)
{
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility,'Review Facility','aktif'));
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($request-1,'Review Command','reviewcmd'.$request.'@invalid','reviewcmd'.$request,'x','dokter','aktif',0,$facility));
    foreach (array($rd, $performer) as $id) {
        $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($id,'Review '.$id,'review'.$id.'@invalid','review'.$id,'x','dokter','aktif',0,$facility));
        $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($id,$facility,$id,'Review Staff '.$id,'dokter','aktif'));
        $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($id,$facility,'2026-01-01','active',$id));
    }
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)', array($request,$rd,'synthetic','Accepted',$facility,'Review Facility','completed','visit',$rd,$performer));
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request,$rd,$rd,$rd,'aktif'));
    $db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', array($request,1,'visit','routine',$rd,'review-disp-'.$request));
    $db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request,$performer,$performer,$rd,'aktif'));
    return (int) $db->insert_id();
}
$base = 28101; $facility = 'REVIEW-8C-' . $base; $request = $base; $command=$base+1; $doctor=$base+2; $performer=$base+3;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility,'Review Facility','aktif'));
foreach (array(array($command,'Perawat',0),array($doctor,'dokter',$doctor),array($performer,'dokter',$performer)) as $u) {
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
$rdCorrection = $resultService->createCorrectionDraft($fixture['request'], $performer, $resultId, 'review-rd-correction-' . $fixture['request']);
vcw_assert_same('success', $rdCorrection['status'] ?? null, 'RD review integrates with Task 7 correction');
vcw_assert_same(2, (int) ($rdCorrection['version_no'] ?? 0), 'RD correction creates v2');
vcw_assert_same($resultId, (int) $db->where('request_id', $fixture['request'])->where('version_no', 2)->get('visit_results')->row()->supersedes_result_id, 'RD correction predecessor');
$reviewCountBeforeReplay=(int)$db->where('visit_result_id',$resultId)->count_all_results('clinical_reviews');
$eventCountBeforeReplay=(int)$db->where('request_id',$fixture['request'])->where('event_type','visit_result.correction_required')->count_all_results('request_events');
$db->query("UPDATE request_visit_performer_assignments SET status='diganti', ended_at=NOW(6), end_reason='historical replay test' WHERE visit_assignment_id=".(int)$fixture['assignment']);
$historicalReplay=$reviewService->review($fixture['request'],$resultId,$fixture['doctor'],'correction_required','Perlu koreksi.','Catatan review','review-key-' . $fixture['request']);
vcw_assert_true(($historicalReplay['idempotent']??false)===true && (int)$historicalReplay['clinical_review_id']===(int)$review['clinical_review_id'],'historical replay returns committed review');
vcw_assert_same($reviewCountBeforeReplay,(int)$db->where('visit_result_id',$resultId)->count_all_results('clinical_reviews'),'historical replay no duplicate review');
vcw_assert_same($eventCountBeforeReplay,(int)$db->where('request_id',$fixture['request'])->where('event_type','visit_result.correction_required')->count_all_results('request_events'),'historical replay no duplicate event');
$historyConflict=$reviewService->review($fixture['request'],$resultId,$fixture['doctor'],'correction_required','Changed reason','Catatan review','review-key-' . $fixture['request']);
vcw_assert_same('REVIEW_KEY_CONFLICT',$historyConflict['safe_error_code']??null,'historical replay context conflict');
$replay = $reviewService->review($fixture['request'], $resultId, $fixture['doctor'], 'correction_required', 'Perlu koreksi.', 'Catatan review', 'review-key-' . $fixture['request']);
vcw_assert_true(($replay['idempotent'] ?? false) === true, 'review idempotent replay');
$approval = $reviewService->review($fixture['request'], $resultId, $fixture['doctor'], 'approved', null, null, 'review-approval-' . $fixture['request']);
vcw_assert_same('ACCESS_DENIED', $approval['safe_error_code'] ?? null, 'second terminal review denied');
vcw_assert_same(1, (int) $db->where('visit_result_id', $resultId)->count_all_results('clinical_reviews'), 'one immutable review per result');

$doctorRequest = 28201; $doctorA = 28202; $doctorB = 28203; $doctorFacility = 'REVIEW-DOCTOR-' . $doctorRequest;
$doctorAssignment = review8c_doctor_fixture($db, $doctorRequest, $doctorA, $doctorB, $doctorFacility);
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES (" . $doctorRequest . "," . $doctorAssignment . ",1," . $doctorA . "," . $doctorA . ",'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6)," . $doctorA . ",'review-doctor-mismatch')");
$mismatchResult = (int) $db->insert_id();
$mismatch = $reviewService->review($doctorRequest, $mismatchResult, $doctorB, 'correction_required', 'Mismatch', null, 'review-doctor-mismatch-key');
vcw_assert_same('REVIEW_RESULT_PERFORMER_MISMATCH', $mismatch['safe_error_code'] ?? null, 'doctor performer cannot review another performer result');
$validRequest = 28301; $validA = 28302; $validB = 28303; $validFacility = 'REVIEW-DOCTOR-' . $validRequest;
$validAssignment = review8c_doctor_fixture($db, $validRequest, $validA, $validB, $validFacility);
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES (" . $validRequest . "," . $validAssignment . ",1," . $validB . "," . $validB . ",'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6)," . $validB . ",'review-doctor-valid')");
$doctorResult = (int) $db->insert_id();
$doctorReview = $reviewService->review($validRequest, $doctorResult, $validB, 'correction_required', 'Perlu koreksi.', null, 'review-doctor-valid-key');
vcw_assert_same('success', $doctorReview['status'] ?? null, 'doctor performer reviews own result');
vcw_assert_true($doctorReview['review']->responsible_assignment_id === null && (int) $doctorReview['review']->reviewer_visit_assignment_id === $validAssignment, 'doctor performer provenance persisted');
$performerCorrection = $resultService->createCorrectionDraft($validRequest, $validB, $doctorResult, 'review-performer-correction-key');
vcw_assert_same('success', $performerCorrection['status'] ?? null, 'performer review integrates with Task 7 correction');
vcw_assert_same(2, (int) ($performerCorrection['version_no'] ?? 0), 'performer correction creates v2');

$approvalRequest=30001; $approvalRd=30002; $approvalPerformer=30003; $approvalFacility='REVIEW-APPROVAL-'.$approvalRequest;
$approvalAssignment=review8c_doctor_fixture($db,$approvalRequest,$approvalRd,$approvalPerformer,$approvalFacility);
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES ($approvalRequest,$approvalAssignment,1,$approvalPerformer,$approvalPerformer,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),$approvalPerformer,'approval-result')");
$approvalResult=(int)$db->insert_id();
$placementStore=new Nakes_placement_store($db);
$approvalBlockersBefore=$placementStore->blockers($approvalPerformer);
vcw_assert_true(count($approvalBlockersBefore)>0,'active approval assignment blocks placement transfer');
$approval=$reviewService->review($approvalRequest,$approvalResult,$approvalRd,'approved',null,null,'approval-key');
vcw_assert_same('success',$approval['status']??null,'approved review succeeds');
$approvalRow=$db->where('clinical_review_id',(int)$approval['clinical_review_id'])->get('clinical_reviews')->row();
$approvalAssignmentRow=$db->where('visit_assignment_id',$approvalAssignment)->get('request_visit_performer_assignments')->row();
vcw_assert_same('approved',(string)($approvalRow->decision??''),'approved decision persisted');
vcw_assert_same('selesai',(string)($approvalAssignmentRow->status??''),'approval completes assignment');
vcw_assert_true(!empty($approvalAssignmentRow->completed_at),'approval sets completed_at');
vcw_assert_same(1,(int)$db->where('request_id',$approvalRequest)->where('event_type','visit_result.approved')->count_all_results('request_events'),'approval event persisted');
$approvalBlockersAfter=$placementStore->blockers($approvalPerformer);
vcw_assert_same(0,count($approvalBlockersAfter),'completed approval assignment clears placement blocker');
$resolver=new Visit_workflow_state_resolver($db,new Visit_workflow_policy($db,false),true);
$resolved=$resolver->resolve($approvalRequest);
vcw_assert_same('WAITING_CLINICAL_FINALIZATION',$resolved['state']??null,'approved result awaits clinical finalization');
$approvalReplay=$reviewService->review($approvalRequest,$approvalResult,$approvalRd,'approved',null,null,'approval-key');
vcw_assert_true(($approvalReplay['idempotent']??false)===true && (int)$approvalReplay['clinical_review_id']===(int)$approval['clinical_review_id'],'approval idempotent replay');
$atomicApprovalRequest=30101; $atomicApprovalRd=30102; $atomicApprovalPerformer=30103; $atomicApprovalFacility='REVIEW-APPROVAL-ATOMIC-'.$atomicApprovalRequest;
$atomicApprovalAssignment=review8c_doctor_fixture($db,$atomicApprovalRequest,$atomicApprovalRd,$atomicApprovalPerformer,$atomicApprovalFacility);
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES ($atomicApprovalRequest,$atomicApprovalAssignment,1,$atomicApprovalPerformer,$atomicApprovalPerformer,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),$atomicApprovalPerformer,'approval-atomic-result')");
$atomicApprovalResult=(int)$db->insert_id();
$db->query("CREATE TRIGGER vcw_approval_event_fail BEFORE INSERT ON request_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced approval event failure'");
$atomicApprovalReply=$reviewService->review($atomicApprovalRequest,$atomicApprovalResult,$atomicApprovalRd,'approved',null,null,'approval-atomic-key');
vcw_assert_same('WRITE_FAILED',$atomicApprovalReply['safe_error_code']??null,'approval event failure is domain-safe');
vcw_assert_same(0,(int)$db->where('visit_result_id',$atomicApprovalResult)->count_all_results('clinical_reviews'),'approval review rolled back');
vcw_assert_same('aktif',(string)$db->where('visit_assignment_id',$atomicApprovalAssignment)->get('request_visit_performer_assignments')->row()->status,'approval assignment rollback');
$db->query('DROP TRIGGER vcw_approval_event_fail');
$doctorApprovalRequest=30201; $doctorApprovalRd=30202; $doctorApprovalPerformer=30203; $doctorApprovalAssignment=review8c_doctor_fixture($db,$doctorApprovalRequest,$doctorApprovalRd,$doctorApprovalPerformer,'REVIEW-DOCTOR-APPROVAL-'.$doctorApprovalRequest);
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES ($doctorApprovalRequest,$doctorApprovalAssignment,1,$doctorApprovalPerformer,$doctorApprovalPerformer,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),$doctorApprovalPerformer,'doctor-approval-result')");
$doctorApprovalResult=(int)$db->insert_id();
$doctorApproval=$reviewService->review($doctorApprovalRequest,$doctorApprovalResult,$doctorApprovalPerformer,'approved',null,null,'doctor-approval-key');
vcw_assert_same('success',$doctorApproval['status']??null,'doctor performer approval succeeds');
vcw_assert_same('selesai',(string)$db->where('visit_assignment_id',$doctorApprovalAssignment)->get('request_visit_performer_assignments')->row()->status,'doctor performer assignment completed');
$assignmentFailRequest=30301; $assignmentFailRd=30302; $assignmentFailPerformer=30303; $assignmentFailAssignment=review8c_doctor_fixture($db,$assignmentFailRequest,$assignmentFailRd,$assignmentFailPerformer,'REVIEW-ASSIGN-FAIL-'.$assignmentFailRequest);
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES ($assignmentFailRequest,$assignmentFailAssignment,1,$assignmentFailPerformer,$assignmentFailPerformer,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),$assignmentFailPerformer,'assignment-fail-result')");
$assignmentFailResult=(int)$db->insert_id();
$db->query("CREATE TRIGGER vcw_assignment_fail BEFORE UPDATE ON request_visit_performer_assignments FOR EACH ROW IF NEW.status='selesai' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced assignment failure'; END IF");
$assignmentFailReply=$reviewService->review($assignmentFailRequest,$assignmentFailResult,$assignmentFailRd,'approved',null,null,'assignment-fail-key');
vcw_assert_same('WRITE_FAILED',$assignmentFailReply['safe_error_code']??null,'assignment failure is domain-safe');
vcw_assert_same(0,(int)$db->where('visit_result_id',$assignmentFailResult)->count_all_results('clinical_reviews'),'assignment failure rolls back review');
vcw_assert_same('aktif',(string)$db->where('visit_assignment_id',$assignmentFailAssignment)->get('request_visit_performer_assignments')->row()->status,'assignment failure preserves active state');
$db->query('DROP TRIGGER vcw_assignment_fail');

// Dedicated same-actor dual-authority fixture: RD provenance must win.
$dualRequest=28401; $dualUser=28402; $dualFacility='REVIEW-DUAL-'.$dualRequest;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($dualFacility,'Dual Facility','aktif'));
$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array(28400,'Dual Command','dual28400@invalid','dual28400','x','dokter','aktif',0,$dualFacility));
$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($dualUser,'Dual Doctor','dual'.$dualUser.'@invalid','dual'.$dualUser,'x','dokter','aktif',0,$dualFacility));
$db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($dualUser,$dualFacility,$dualUser,'Dual Staff','dokter','aktif'));
$db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($dualUser,$dualFacility,'2026-01-01','active',$dualUser));
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)', array($dualRequest,$dualUser,'synthetic','Accepted',$dualFacility,'Dual Facility','completed','visit',$dualUser,$dualUser));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($dualRequest,$dualUser,$dualUser,$dualUser,'aktif'));
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', array($dualRequest,1,'visit','routine',$dualUser,'dual-disp-'.$dualRequest));
$db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($dualRequest,$dualUser,$dualUser,$dualUser,'aktif'));
$dualAssignment=(int)$db->insert_id();
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES ($dualRequest,$dualAssignment,1,$dualUser,$dualUser,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),$dualUser,'dual-result')");
$dualResult=(int)$db->insert_id();
$dualReview=$reviewService->review($dualRequest,$dualResult,$dualUser,'correction_required','Dual authority',null,'dual-review-key');
vcw_assert_same('success',$dualReview['status']??null,'dual-authority review allowed');
$dualRow=$db->where('clinical_review_id',(int)$dualReview['clinical_review_id'])->get('clinical_reviews')->row();
vcw_assert_true($dualRow && $dualRow->responsible_assignment_id !== null && $dualRow->reviewer_visit_assignment_id === null,'RD precedence for dual authority');
function review8c_negative($db,$base,$mode)
{
    global $reviewService;
    $request=$base; $rd=$base+1; $performer=$base+2; $actor=$mode==='command_center'?$base-1:$base+3; $facility='REVIEW-NEG-'.$request; $actorFacility=$mode==='cross_facility'?'REVIEW-OTHER-'.$request:$facility;
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)',array($facility,'Negative Facility','aktif'));
    if($actorFacility!==$facility) $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)',array($actorFacility,'Other Facility','aktif'));
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)',array($rd,'Neg RD','neg'.$rd.'@invalid','neg'.$rd,'x','dokter','aktif',0,$facility));
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)',array($performer,'Neg Performer','neg'.$performer.'@invalid','neg'.$performer,'x','dokter','aktif',0,$facility));
    $actorRole=$mode==='warga'?'warga':($mode==='admin'?'admin':'dokter'); $actorStatus=$mode==='inactive_user'?'nonaktif':'aktif';
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)',array($actor,'Neg Actor','neg'.$actor.'@invalid','neg'.$actor,'x',$actorRole,$actorStatus,0,$actorFacility));
    $performerProfession=$mode==='nondoctor_performer'?'Perawat':'dokter';
    $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)',array($performer,$facility,$performer,'Neg Performer Staff',$performerProfession,'aktif'));
    $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)',array($performer,$facility,'2026-01-01','active',$rd));
    if(in_array($mode,array('unrelated_nakes','replaced','terminal'),true)) {
        $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)',array($actor,$actorFacility,$actor,'Neg Actor Staff','Perawat','aktif'));
        $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)',array($actor,$actorFacility,'2026-01-01','active',$actor));
    }
    if($mode==='inactive_staff') $db->query("UPDATE puskesmas_staff SET status='nonaktif' WHERE staff_id=$performer");
    if($mode==='invalid_placement') $db->query("UPDATE nakes_facility_placements SET status='ended' WHERE staff_id=$performer");
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',array($request,$rd,'synthetic','Accepted',$facility,'Negative Facility','completed','visit',$rd,$performer));
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))',array($request,$rd,$rd,$rd,'aktif'));
    $db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))',array($request,1,'visit','routine',$rd,'neg-disp-'.$request));
    $db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))',array($request,$performer,$performer,$rd,'aktif'));
    if($mode==='replaced' || $mode==='terminal') $db->query('INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,ended_at,end_reason) VALUES (?,?,?,?,?,NOW(6),NOW(6),?)',array($request,$actor,$actor,$rd,'diganti','historical'));
    $assignment=(int)$db->insert_id();
    $db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES ($request,$assignment,1,$performer,$performer,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),$performer,'neg-result-$request')");
    $result=(int)$db->insert_id();
    $before=(int)$db->where('request_id',$request)->count_all_results('request_events');
    $reply=$reviewService->review($request,$result,$actor,'correction_required','Denied',null,'neg-key-'.$request);
    vcw_assert_true(($reply['status']??null)==='error','negative actor denied '.$mode);
    vcw_assert_same(0,(int)$db->where('visit_result_id',$result)->count_all_results('clinical_reviews'),'no review row '.$mode);
    vcw_assert_same($before,(int)$db->where('request_id',$request)->count_all_results('request_events'),'no event '.$mode);
}
foreach(array(28501=>'nondoctor_performer',28601=>'unrelated_doctor',28701=>'unrelated_nakes',28801=>'command_center',28901=>'facility_identity',29001=>'inactive_user',29101=>'inactive_staff',29201=>'invalid_placement',29301=>'cross_facility',29401=>'replaced',29501=>'terminal',29601=>'compatibility_only',29701=>'admin',29801=>'warga') as $baseNeg=>$modeNeg){ review8c_negative($db,$baseNeg,$modeNeg); }
$atomicRequest=29901; $atomicRd=29902; $atomicPerformer=29903; $atomicAssignment=review8c_doctor_fixture($db,$atomicRequest,$atomicRd,$atomicPerformer,'REVIEW-ATOMIC-'.$atomicRequest);
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES ($atomicRequest,$atomicAssignment,1,$atomicPerformer,$atomicPerformer,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),$atomicPerformer,'atomic-result')");
$atomicResult=(int)$db->insert_id();
$db->query("CREATE TRIGGER vcw_review_event_fail BEFORE INSERT ON request_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced review event failure'");
$atomicReply=$reviewService->review($atomicRequest,$atomicResult,$atomicRd,'correction_required','Atomicity',null,'atomic-review-key');
vcw_assert_same('WRITE_FAILED',$atomicReply['safe_error_code']??null,'review event failure is domain-safe');
vcw_assert_same(0,(int)$db->where('visit_result_id',$atomicResult)->count_all_results('clinical_reviews'),'review insert rolled back on event failure');
$db->query('DROP TRIGGER vcw_review_event_fail');

// Task 8D closure proofs: current assignment completion, correction blocker, and old-result approval guard.
$exactRequest=30401; $exactRd=30402; $exactPerformer=30403; $exactFacility='REVIEW-EXACT-'.$exactRequest;
$exactAssignment=review8c_doctor_fixture($db,$exactRequest,$exactRd,$exactPerformer,$exactFacility);
$db->query("INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,ended_at,end_reason) VALUES ($exactRequest,30404,30404,$exactRd,'diganti',NOW(6),NOW(6),'reassigned')");
$historicalAssignment=(int)$db->insert_id();
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES ($exactRequest,$exactAssignment,1,$exactPerformer,$exactPerformer,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),$exactPerformer,'exact-result')");
$exactResult=(int)$db->insert_id();
$exactApproval=$reviewService->review($exactRequest,$exactResult,$exactRd,'approved',null,null,'exact-approval-key');
vcw_assert_same('success',$exactApproval['status']??null,'exact current assignment approval');
vcw_assert_same('selesai',(string)$db->where('visit_assignment_id',$exactAssignment)->get('request_visit_performer_assignments')->row()->status,'current assignment completed');
vcw_assert_same('diganti',(string)$db->where('visit_assignment_id',$historicalAssignment)->get('request_visit_performer_assignments')->row()->status,'historical assignment unchanged');

$corrRequest=30501; $corrRd=30502; $corrPerformer=30503; $corrFacility='REVIEW-CORR-'.$corrRequest;
$corrAssignment=review8c_doctor_fixture($db,$corrRequest,$corrRd,$corrPerformer,$corrFacility);
$db->query("INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES ($corrRequest,$corrAssignment,1,$corrPerformer,$corrPerformer,'submitted',0,'[]','[]',NOW(6),NOW(6),NOW(6),$corrPerformer,'corr-result')");
$corrResult=(int)$db->insert_id();
$corrStore=new Nakes_placement_store($db); $corrBefore=count($corrStore->blockers($corrPerformer));
$corrReview=$reviewService->review($corrRequest,$corrResult,$corrRd,'correction_required','Needs correction',null,'corr-key');
vcw_assert_same('success',$corrReview['status']??null,'correction review for blocker proof');
vcw_assert_same('aktif',(string)$db->where('visit_assignment_id',$corrAssignment)->get('request_visit_performer_assignments')->row()->status,'correction leaves assignment active');
vcw_assert_true($corrBefore>0 && count($corrStore->blockers($corrPerformer))>0,'correction blocker remains');

$historicApproval=$reviewService->review($fixture['request'],$resultId,$fixture['doctor'],'approved',null,null,'new-approval-on-corrected-v1');
vcw_assert_true(($historicApproval['status']??null)==='error','historic corrected result cannot be newly approved');
echo "TASK8C_REVIEW_API_PRESENT=PASS\nTASK8C_CORRECTION_REVIEW=PASS\nTASK8C_IDEMPOTENCY=PASS\nTASK8C_APPROVAL_GUARD=PASS\nTASK8C_SEPARATE_DOCTOR_AUTHORITIES=PASS\nTASK8C_DUAL_AUTHORITY_RD_PRECEDENCE=PASS\nTASK8C_AUTHORITY_NEGATIVE_MATRIX=PASS\nTASK8C_PERFORMER_OWN_RESULT_GUARD=PASS\n";
