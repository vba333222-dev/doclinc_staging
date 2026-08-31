<?php
require_once __DIR__.'/assert.php';
$app=require __DIR__.'/ci3_bootstrap.php'; $db=$app->db;
require_once APPPATH.'libraries/Clinical_finalization_service.php';
require_once APPPATH.'libraries/Clinical_amendment_service.php';

function t9a_fixture($db,$n,$finalized=true){
    $facility='T9F-'.$n; $cmd=$n-1; $rd=$n+1; $perf=$n+2;
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)',[$facility,'Task 9 atomicity','aktif']);
    foreach([[$cmd,'dokter'],[$rd,'dokter'],[$perf,'dokter']] as $u){$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)',[$u[0],'T9A '.$u[0],'t9a'.$u[0].'@invalid','t9a'.$u[0],'x','dokter','aktif',0,$facility]);}
    foreach([$rd,$perf] as $u){$db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)',[$u,$facility,$u,'T9A staff','dokter','aktif']);$db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)',[$u,$facility,'2026-01-01','active',$u]);}
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',[$n,$cmd,'test','Accepted',$facility,'Task 9 atomicity','completed','visit',$rd,$perf]);
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))',[$n,$rd,$rd,$rd,'aktif']); $rda=(int)$db->insert_id();
    $db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))',[$n,1,'visit','routine',$rd,'t9a-d-'.$n]);
    $db->query("INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,completed_at) VALUES (?,?,?,?,?,NOW(6),NOW(6))",[$n,$perf,$perf,$rd,'selesai']); $va=(int)$db->insert_id();
    $db->query('INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,draft_revision,findings_json,actions_json,created_at,updated_at,submitted_at,submitted_by_user_id,submission_key) VALUES (?,?,?,?,?,"submitted",0,"[]","[]",NOW(6),NOW(6),NOW(6),?,?)',[$n,$va,$perf,$perf,$perf,$perf,'t9a-r-'.$n]); $vr=(int)$db->insert_id();
    $db->query('INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,reviewed_at,idempotency_key) VALUES (?,?,?,?,?,NOW(6),?)',[$n,$vr,$rd,$rda,'approved','t9a-v-'.$n]);
    $values=$finalized?'NOW(6),'.$rd:'NULL,NULL';
    $db->query("INSERT INTO medicalrecords (request_id,diagnosis,treatment,recommendations,anamnesis,responsible_doctor_user_id,recorded_by_user_id,clinical_finalized_at,clinical_finalized_by_user_id) VALUES (?,?,?,?,?,?,?,$values)",[$n,'initial','initial','initial','history',$rd,$perf]);
    return ['request'=>$n,'record'=>(int)$db->insert_id(),'user'=>$rd,'facility'=>$facility];
}
function t9a_drop($db,$names){foreach($names as $x)$db->query('DROP TRIGGER IF EXISTS '.$x);}
function t9a_trigger($db,$name,$body){t9a_drop($db,[$name]);vcw_assert_true($db->query("CREATE TRIGGER $name BEFORE $body"),'fault trigger created');}
function t9a_signal($message){$q=chr(39);return 'SIGNAL SQLSTATE '.$q.'45000'.$q.' SET MESSAGE_TEXT='.$q.$message.$q;}
function t9a_safe($r){$s=json_encode($r);foreach(['SQLSTATE','trigger','duplicate','deadlock','clinical_amendments','request_events'] as $x)if(stripos($s,$x)!==false)return false;return true;}
$names=['t9a_marker_fail','t9a_final_event_fail','t9a_header_fail','t9a_item_fail','t9a_amend_event_fail']; t9a_drop($db,$names);
$base=760000+random_int(0,5000);
try {
    $f=t9a_fixture($db,$base,false); $svc=new Clinical_finalization_service($db);
    t9a_trigger($db,'t9a_marker_fail',"UPDATE ON medicalrecords FOR EACH ROW BEGIN IF NEW.record_id=".$f['record']." AND NEW.clinical_finalized_at IS NOT NULL THEN ".t9a_signal('marker fault')."; END IF; END");
    $r=$svc->finalize($f['request'],$f['user'],'t9a-marker-'.$base);$row=$db->where('record_id',$f['record'])->get('medicalrecords')->row();
    vcw_assert_true(($r['status']??'')==='error'&&$row->clinical_finalized_at===null&&$row->clinical_finalized_by_user_id===null&&t9a_safe($r),'finalization marker rollback');
    t9a_drop($db,['t9a_marker_fail']);
    $f=t9a_fixture($db,$base+10,false);
    t9a_trigger($db,'t9a_final_event_fail',"INSERT ON request_events FOR EACH ROW BEGIN IF NEW.request_id=".$f['request']." AND NEW.event_type='clinical_record.finalized' THEN ".t9a_signal('event fault')."; END IF; END");
    $r=$svc->finalize($f['request'],$f['user'],'t9a-event-'.$base);$row=$db->where('record_id',$f['record'])->get('medicalrecords')->row();$ec=(int)$db->where('request_id',$f['request'])->where('event_type','clinical_record.finalized')->count_all_results('request_events');
    vcw_assert_true(($r['status']??'')==='error'&&$row->clinical_finalized_at===null&&$row->clinical_finalized_by_user_id===null&&$ec===0&&t9a_safe($r),'finalization event rollback'); t9a_drop($db,['t9a_final_event_fail']);
    $f=t9a_fixture($db,$base+20,true);$am=new Clinical_amendment_service($db);
    t9a_trigger($db,'t9a_header_fail',"INSERT ON clinical_amendments FOR EACH ROW BEGIN IF NEW.record_id=".$f['record']." THEN ".t9a_signal('header fault')."; END IF; END");
    $r=$am->amend($f['request'],$f['record'],$f['user'],'header fault',[['field_key'=>'diagnosis','corrected_value'=>'x']],'t9a-head-'.$base);$hc=(int)$db->where('record_id',$f['record'])->count_all_results('clinical_amendments');$ec=(int)$db->where('request_id',$f['request'])->where('event_type','clinical_record.amended')->count_all_results('request_events');vcw_assert_true(($r['status']??'')==='error'&&$hc===0&&$ec===0&&t9a_safe($r),'amendment header rollback');t9a_drop($db,['t9a_header_fail']);
    $f=t9a_fixture($db,$base+30,true);
    t9a_trigger($db,'t9a_item_fail',"INSERT ON clinical_amendment_items FOR EACH ROW BEGIN IF NEW.item_order=2 THEN ".t9a_signal('item fault')."; END IF; END");
    $r=$am->amend($f['request'],$f['record'],$f['user'],'item fault',[['field_key'=>'diagnosis','corrected_value'=>'x'],['field_key'=>'treatment','corrected_value'=>'y']],'t9a-item-'.$base);$hc=(int)$db->where('record_id',$f['record'])->count_all_results('clinical_amendments');$ic=(int)$db->query('SELECT COUNT(*) c FROM clinical_amendment_items i JOIN clinical_amendments a ON a.amendment_id=i.amendment_id WHERE a.record_id=?',[$f['record']])->row()->c;$ec=(int)$db->where('request_id',$f['request'])->where('event_type','clinical_record.amended')->count_all_results('request_events');vcw_assert_true(($r['status']??'')==='error'&&$hc===0&&$ic===0&&$ec===0&&t9a_safe($r),'amendment item rollback');t9a_drop($db,['t9a_item_fail']);
    $f=t9a_fixture($db,$base+40,true);
    t9a_trigger($db,'t9a_amend_event_fail',"INSERT ON request_events FOR EACH ROW BEGIN IF NEW.request_id=".$f['request']." AND NEW.event_type='clinical_record.amended' THEN ".t9a_signal('amend event fault')."; END IF; END");
    $r=$am->amend($f['request'],$f['record'],$f['user'],'event fault',[['field_key'=>'diagnosis','corrected_value'=>'x']],'t9a-aevent-'.$base);$hc=(int)$db->where('record_id',$f['record'])->count_all_results('clinical_amendments');$ic=(int)$db->query('SELECT COUNT(*) c FROM clinical_amendment_items i JOIN clinical_amendments a ON a.amendment_id=i.amendment_id WHERE a.record_id=?',[$f['record']])->row()->c;$ec=(int)$db->where('request_id',$f['request'])->where('event_type','clinical_record.amended')->count_all_results('request_events');vcw_assert_true(($r['status']??'')==='error'&&$hc===0&&$ic===0&&$ec===0&&t9a_safe($r),'amendment event rollback');
    echo "TASK9_FINALIZATION_MARKER_FAILURE_ATOMICITY=PASS\nTASK9_FINALIZATION_EVENT_FAILURE_ATOMICITY=PASS\nTASK9_AMENDMENT_HEADER_FAILURE_ATOMICITY=PASS\nTASK9_AMENDMENT_ITEM_FAILURE_ATOMICITY=PASS\nTASK9_AMENDMENT_EVENT_FAILURE_ATOMICITY=PASS\nTASK9_ATOMICITY_ERROR_HYGIENE=PASS\nTASK9_ATOMICITY=PASS\n";
} finally { t9a_drop($db,$names); }
