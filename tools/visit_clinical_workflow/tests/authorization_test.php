<?php
if (!defined('BASEPATH')) define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 3) . '/application/libraries/Visit_workflow_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Care_team_policy.php';
require_once __DIR__ . '/assert.php';
$requests = array(1=>array('request_status'=>'Accepted','assigned_puskesmas_code'=>'PKM'));
$identities = array(10=>array('valid'=>true,'account_type'=>'personal','user_id'=>10,'staff_id'=>10,'user_status'=>'aktif','staff_status'=>'aktif','staff_profesi'=>'dokter','puskesmas_code'=>'PKM'), 20=>array('valid'=>true,'account_type'=>'command_center','is_command_center'=>true,'user_id'=>20,'user_status'=>'aktif','staff_status'=>'','puskesmas_code'=>'PKM'), 30=>array('valid'=>true,'account_type'=>'personal','user_id'=>30,'staff_id'=>30,'user_status'=>'nonaktif','staff_status'=>'aktif','staff_profesi'=>'dokter','puskesmas_code'=>'PKM'));
$assignments = function($kind,$request,$actor = 0) use (&$requests) { if ($kind === 'enrolled') return false; return $kind === 'responsible' && $request === 1 && $actor === 10; };
$policy = new Visit_workflow_policy(null, true, function($id) use (&$identities){ return $identities[$id] ?? array(); }, function($id) use (&$requests){ return $requests[$id] ?? null; }, $assignments);
vcw_assert_true($policy->canCreateDisposition(1,10), 'canonical responsible doctor should enroll');
vcw_assert_true(!$policy->canCreateDisposition(1,20), 'command center cannot enroll');
vcw_assert_true(!$policy->canCreateDisposition(1,30), 'inactive account cannot enroll');
$requests[1]['enrolled'] = true;
vcw_assert_true(!$policy->canReview(1,20), 'command center cannot review');
vcw_assert_true($policy->isEnrolled(1) === false, 'fixture remains unenrolled');
$care = new Care_team_policy();
vcw_assert_true($care->visitPerformerEligible(array('valid'=>true,'account_type'=>'personal','user_id'=>10,'staff_id'=>10,'user_status'=>'aktif','staff_status'=>'aktif','puskesmas_code'=>'PKM','staff_profesi'=>'dokter'),'PKM'), 'doctor may be performer');
vcw_assert_true(!$care->visitPerformerEligible(array('valid'=>true,'account_type'=>'command_center','user_id'=>20,'puskesmas_code'=>'PKM','staff_profesi'=>'dokter'),'PKM'), 'command center rejected');
$requests[2] = array('request_status' => 'Accepted', 'assigned_puskesmas_code' => 'PKM');
$identities[40] = array('valid' => true, 'account_type' => 'personal', 'user_id' => 40, 'staff_id' => 40, 'user_status' => 'aktif', 'staff_status' => 'aktif', 'staff_profesi' => 'Perawat', 'puskesmas_code' => 'PKM');
$identities[50] = array('valid' => true, 'account_type' => 'personal', 'user_id' => 50, 'staff_id' => 50, 'user_status' => 'aktif', 'staff_status' => 'aktif', 'staff_profesi' => 'dokter', 'puskesmas_code' => 'OTHER');
$identities[60] = array('valid' => true, 'account_type' => 'admin', 'user_id' => 60, 'user_status' => 'aktif', 'staff_status' => '', 'puskesmas_code' => 'PKM');
$identities[70] = array('valid' => true, 'account_type' => 'warga', 'user_id' => 70, 'user_status' => 'aktif', 'staff_status' => '', 'puskesmas_code' => 'PKM');

$matrixAssignments = function ($kind, $request, $actor = 0) use (&$requests) {
    if ($kind === 'enrolled') { return !empty($requests[$request]['enrolled']); }
    if ($kind !== 'responsible') { return false; }
    return (int) $request === 1 && (int) $actor === 10;
};
$matrix = new Visit_workflow_policy(null, true, function ($id) use (&$identities) { return $identities[$id] ?? array(); }, function ($id) use (&$requests) { return $requests[$id] ?? null; }, $matrixAssignments);
$careMatrix = new Care_team_policy();

vcw_assert_true(!$matrix->canCreateDisposition(1, 20), 'command_center_cannot_act_as_responsible_doctor');
vcw_assert_true(!$matrix->canCreateDisposition(1, 40), 'non_doctor_cannot_enroll_as_responsible_doctor');
vcw_assert_true(!$matrix->canCreateDisposition(1, 50), 'cross_facility_doctor_cannot_enroll');
vcw_assert_true(!$matrix->canCreateDisposition(1, 60), 'admin_governance_authority_cannot_enroll');
vcw_assert_true(!$matrix->canCreateDisposition(1, 70), 'warga_cannot_enroll');
vcw_assert_true($matrix->canReview(1, 10), 'canonical_responsible_doctor_can_review');
vcw_assert_true(!$matrix->canReview(1, 20), 'command_center_cannot_review_enrolled');
vcw_assert_true(!$matrix->canReview(1, 50), 'unrelated_doctor_cannot_review');
vcw_assert_true(!$matrix->canReview(1, 60), 'admin_governance_authority_cannot_review');
vcw_assert_true(!$matrix->canReview(1, 70), 'warga_cannot_review');

vcw_assert_true($careMatrix->doctorProfession('dokter'), 'doctor_profession_is_clinical_authority');
vcw_assert_true(!$careMatrix->doctorProfession('Perawat'), 'non_doctor_profession_has_no_clinical_authority');
vcw_assert_true($careMatrix->visitPerformerEligible($identities[10], 'PKM'), 'personal_doctor_performer_is_allowed');
vcw_assert_true(!($careMatrix->visitPerformerEligible($identities[40], 'PKM') && $careMatrix->doctorProfession($identities[40]['staff_profesi'])), 'non_doctor_performer_has_no_clinical_authority');
vcw_assert_true(!$careMatrix->visitPerformerEligible($identities[60], 'PKM'), 'admin_governance_authority_cannot_act_as_performer');
vcw_assert_true(!$careMatrix->visitPerformerEligible($identities[70], 'PKM'), 'warga_cannot_act_as_performer');

$policySource = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/Visit_workflow_policy.php');
vcw_assert_true(strpos($policySource, 'request_responsible_doctor_assignments') !== false && strpos($policySource, 'request_visit_performer_assignments') !== false, 'canonical_assignments_are_the_enrolled_authority_source');
vcw_assert_true(strpos($policySource, 'accepted_by_user_id') === false && strpos($policySource, '->dokter_id') === false && strpos($policySource, "'dokter_id'") === false && strpos($policySource, 'profesi') === false, 'compatibility_columns_do_not_grant_enrolled_authority');

echo "TASK3_AUTHORIZATION=PASS\n";
echo "TASK13_AUTHORIZATION_MATRIX=PASS\n";
