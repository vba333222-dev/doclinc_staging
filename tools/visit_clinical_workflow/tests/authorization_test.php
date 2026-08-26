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
echo "TASK3_AUTHORIZATION=PASS\n";
