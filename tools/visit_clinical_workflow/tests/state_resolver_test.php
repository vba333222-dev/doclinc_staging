<?php
if (!defined('BASEPATH')) define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 3) . '/application/libraries/Visit_workflow_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Visit_workflow_state_resolver.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Visit_workflow_presenter.php';
require_once __DIR__ . '/assert.php';
$requests = array(
  1=>array('request_status'=>'Accepted','visit_status'=>'not_started'),2=>array('request_status'=>'Accepted','visit_status'=>'not_started'),
  3=>array('request_status'=>'Accepted','visit_status'=>'not_started'),4=>array('request_status'=>'Accepted','visit_status'=>'not_started'),
  5=>array('request_status'=>'Accepted','visit_status'=>'not_started'),6=>array('request_status'=>'Accepted','visit_status'=>'en_route'),
  7=>array('request_status'=>'Accepted','visit_status'=>'arrived'),8=>array('request_status'=>'Accepted','visit_status'=>'in_service'),
  9=>array('request_status'=>'Accepted','visit_status'=>'completed'),10=>array('request_status'=>'Accepted','visit_status'=>'completed'),
  11=>array('request_status'=>'Accepted','visit_status'=>'completed'),12=>array('request_status'=>'Accepted','visit_status'=>'completed'),
  13=>array('request_status'=>'Accepted','visit_status'=>'completed'),14=>array('request_status'=>'Accepted','visit_status'=>'completed'),
  15=>array('request_status'=>'Completed','visit_status'=>'completed'));
$dispositions=array(3=>array('decision'=>'non_visit'),4=>array('decision'=>'non_visit'),5=>array('decision'=>'visit'),6=>array('decision'=>'visit'),7=>array('decision'=>'visit'),8=>array('decision'=>'visit'),9=>array('decision'=>'visit'),10=>array('decision'=>'visit'),11=>array('decision'=>'visit'),12=>array('decision'=>'visit'),13=>array('decision'=>'visit'),14=>array('decision'=>'visit'));
$submitted=array(10=>array('visit_result_id'=>110,'status'=>'submitted'),11=>array('visit_result_id'=>111,'status'=>'submitted'),12=>array('visit_result_id'=>112,'status'=>'submitted'),13=>array('visit_result_id'=>113,'status'=>'submitted'),14=>array('visit_result_id'=>114,'status'=>'submitted'));
$reviews=array(11=>array('decision'=>'correction_required'),12=>array('decision'=>'approved'),13=>array('decision'=>'approved'),14=>array('decision'=>'approved'));
$finalized=array(4=>true,13=>true,14=>true); $active=array(6=>true,7=>true,8=>true);
$reader=function($sql,$id)use(&$requests,&$dispositions,&$submitted,&$reviews,&$finalized,&$active){
 if(strpos($sql,'FROM requests')!==false)return $requests[$id]??null;
 if(strpos($sql,'FROM visit_dispositions')!==false)return $dispositions[$id]??null;
 if(strpos($sql,'FROM visit_results')!==false)return $submitted[$id]??null;
 if(strpos($sql,'FROM clinical_reviews')!==false){$map=array(111=>11,112=>12,113=>13,114=>14);$r=$reviews[$map[$id]??$id]??null;return $r;}
 if(strpos($sql,'FROM medicalrecords')!==false)return !empty($finalized[$id])?array('clinical_finalized_at'=>'2026-08-26 10:00:00'):null;
 if(strpos($sql,'FROM request_visit_performer_assignments')!==false)return !empty($active[$id])?array('visit_assignment_id'=>500+$id):null;
 return null;};
$policy=new Visit_workflow_policy(null,true,function($actor){return array('valid'=>true,'account_type'=>'personal','user_id'=>$actor,'staff_id'=>$actor,'user_status'=>'aktif','staff_status'=>'aktif','staff_profesi'=>'dokter','puskesmas_code'=>'PKM-1');},function($id)use(&$requests){return $requests[$id]??null;},function($kind,$id)use(&$dispositions){return $kind==='enrolled' ? isset($dispositions[$id]) : false;});
$resolver=new Visit_workflow_state_resolver(null,$policy,true,$reader);
$cases=array(1=>'WAITING_DOCTOR_DISPOSITION',2=>'WAITING_DOCTOR_DISPOSITION',3=>'WAITING_CLINICAL_FINALIZATION',4=>'READY_FOR_CLOSURE',5=>'WAITING_PERFORMER_ASSIGNMENT',6=>'VISIT_EN_ROUTE',7=>'VISIT_ARRIVED',8=>'VISIT_IN_SERVICE',9=>'WAITING_VISIT_RESULT',10=>'WAITING_DOCTOR_REVIEW',11=>'CORRECTION_REQUIRED',12=>'WAITING_CLINICAL_FINALIZATION',13=>'READY_FOR_CLOSURE',14=>'READY_FOR_CLOSURE',15=>'COMPLETED');
foreach($cases as $id=>$expected)vcw_assert_same($expected,$resolver->resolve($id)['state'],'state '.$id);
$off=new Visit_workflow_state_resolver(null,$policy,false,$reader); vcw_assert_same(null,$off->resolve(2)['state'],'legacy flag off remains unmanaged');
$presenter=new Visit_workflow_presenter(); vcw_assert_same('WAITING_DOCTOR_REVIEW',$presenter->present(array('request_id'=>11,'state'=>'CORRECTION_REQUIRED'),'warga')['state'],'warga state masked'); vcw_assert_same('Hasil kunjungan sedang ditinjau',$presenter->present(array('request_id'=>11,'state'=>'CORRECTION_REQUIRED'),'warga')['label'],'warga label masked'); vcw_assert_same('Menunggu perbaikan hasil kunjungan',$presenter->present(array('request_id'=>11,'state'=>'CORRECTION_REQUIRED'),'responsible_doctor')['label'],'doctor correction label');
echo "TASK3_STATE_RESOLVER=PASS\n";
