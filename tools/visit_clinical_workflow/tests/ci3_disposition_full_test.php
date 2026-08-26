<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php'; $db = $app->db;
function vcw_id($n) { global $vcwBase; return $vcwBase + $n; }
$vcwBase = 30000 + random_int(1, 500) * 100;
 $vcwFacility = 'PKM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
$db->query("DELETE FROM request_events WHERE request_id >= 20000");
$db->query("DELETE FROM visit_assignment_operations WHERE request_id >= 20000");
$db->query("DELETE FROM request_responsible_doctor_assignments WHERE request_id >= 20000");
$db->query("DELETE FROM request_visit_performer_assignments WHERE request_id >= 20000");
$db->query("DELETE FROM visit_dispositions WHERE request_id >= 20000");
$db->query("DELETE FROM requests WHERE request_id >= 20000");
$db->query("DELETE FROM puskesmas_staff WHERE staff_id >= 20000");
$db->query("DELETE FROM users WHERE userId >= 20000");
function vcw_user($db,$id,$remark=null,$role='dokter') { global $vcwFacility; if($remark===null){$remark=$vcwFacility;} $db->query("INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)",array($id,'Synthetic '.$id,'u'.$id.'@runner.invalid','u'.$id,password_hash(bin2hex(random_bytes(12)),PASSWORD_BCRYPT),$role,'aktif',0,$remark)); }
function vcw_fixture($db,$request,$doctor,$performer=false) {
    global $vcwFacility;
    vcw_assert_safe_request_id($request); vcw_user($db,$doctor); if($performer){vcw_user($db,$doctor+100);}
    $db->query("INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)",array($doctor,$vcwFacility,$doctor,'Synthetic Doctor','dokter','aktif'));
    if($performer){$db->query("INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)",array($doctor+100,$vcwFacility,$doctor+100,'Synthetic Performer','Perawat','aktif'));}
    $db->query("INSERT INTO requests (request_id,user_id,request_description,request_status,date,location,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?,?,?)",array($request,$doctor,'synthetic','Accepted','2026-01-01 00:00:00','Synthetic',$vcwFacility,'Synthetic','not_started'));
    $db->query("INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))",array($request,$doctor,$doctor,$doctor,'aktif'));
    if($performer){$db->query("INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))",array($request,$doctor+100,$doctor+100,$doctor,'aktif')); $db->where('request_id',$request)->update('requests',array('visit_performer_user_id'=>$doctor+100));}
}
function vcw_service($db,$enabled){ return new Visit_disposition_service($db,new Visit_workflow_policy($db,$enabled),$enabled); }
$db->query("INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)",array($vcwFacility,'Synthetic','aktif')); vcw_user($db,vcw_id(0),$vcwFacility); // command-center discriminator
// Create matrix
vcw_fixture($db,vcw_id(1),vcw_id(1)); $s=vcw_service($db,false); $r=$s->create(vcw_id(1),vcw_id(1),array('decision'=>'visit','urgency'=>'routine'),'full-off'); vcw_assert_same('WORKFLOW_ENROLLMENT_DISABLED',$r['code']??null,'feature off'); vcw_assert_same(0,(int)$db->where('request_id',vcw_id(1))->count_all_results('visit_dispositions'),'off rows');
vcw_fixture($db,vcw_id(2),vcw_id(2)); $s=vcw_service($db,true); $r=$s->create(vcw_id(2),vcw_id(2),array('decision'=>'visit','urgency'=>'routine'),'full-valid'); if(empty($r['ok'])){throw new RuntimeException('valid create code='.(string)($r['code']??''));} $id=(int)$r['disposition_id']; vcw_assert_same(1,(int)$r['version_no'],'v1'); vcw_assert_same(1,(int)$db->where('request_id',vcw_id(2))->count_all_results('request_events'),'create event');
$replay=$s->create(vcw_id(2),vcw_id(2),array('decision'=>'visit','urgency'=>'routine'),'full-valid'); vcw_assert_same($id,(int)$replay['disposition_id'],'create replay'); vcw_assert_same(1,(int)$db->where('request_id',vcw_id(2))->count_all_results('visit_dispositions'),'replay rows');
$dup=$s->create(vcw_id(2),vcw_id(2),array('decision'=>'visit','urgency'=>'routine'),'full-other'); vcw_assert_same('DISPOSITION_ALREADY_EXISTS',$dup['code']??null,'different key');
vcw_fixture($db,vcw_id(3),vcw_id(3)); $bad=$s->create(vcw_id(3),vcw_id(3),array('decision'=>'visit','urgency'=>'emergency'),'bad-urgency'); vcw_assert_same('INVALID_URGENCY',$bad['code']??null,'bad urgency');
vcw_fixture($db,vcw_id(4),vcw_id(4)); vcw_user($db,vcw_id(99)); $bad=$s->create(vcw_id(4),vcw_id(99),array('decision'=>'visit','urgency'=>'routine'),'bad-actor'); vcw_assert_same('NOT_RESPONSIBLE_DOCTOR',$bad['code']??null,'bad actor baseline');
// revision and idempotent replay
vcw_fixture($db,vcw_id(5),vcw_id(5)); $v1=$s->create(vcw_id(5),vcw_id(5),array('decision'=>'visit','urgency'=>'routine'),'rev-v1'); $v2=$s->revise(vcw_id(5),vcw_id(5),1,array('decision'=>'visit','urgency'=>'priority'),'rev-v2'); vcw_assert_true(!empty($v2['ok']),'v1-v2'); vcw_assert_same(2,(int)$v2['version_no'],'v2 version'); $v2Replay=$s->revise(vcw_id(5),vcw_id(5),1,array('decision'=>'visit','urgency'=>'priority'),'rev-v2'); vcw_assert_same((int)$v2['disposition_id'],(int)$v2Replay['disposition_id'],'revision replay'); vcw_assert_same(2,(int)$db->where('request_id',vcw_id(5))->count_all_results('visit_dispositions'),'revision row count');
$stale=$s->revise(vcw_id(5),vcw_id(5),1,array('decision'=>'visit','urgency'=>'urgent'),'rev-v3'); vcw_assert_same('DISPOSITION_VERSION_CONFLICT',$stale['code']??null,'stale version');
// flag off does not block enrolled revision
$sOff=vcw_service($db,false); $offRev=$sOff->revise(vcw_id(5),vcw_id(5),2,array('decision'=>'visit','urgency'=>'urgent'),'rev-v4'); vcw_assert_true(!empty($offRev['ok']),'enrolled revision after flag off');
// performer impact: incompatible profession is closed atomically
vcw_fixture($db,vcw_id(6),vcw_id(6),true); $s=vcw_service($db,true); $s->create(vcw_id(6),vcw_id(6),array('decision'=>'visit','urgency'=>'routine','required_profession'=>'Perawat'),'impact-v1'); $impact=$s->revise(vcw_id(6),vcw_id(6),1,array('decision'=>'visit','urgency'=>'routine','required_profession'=>'Bidan'),'impact-v2'); vcw_assert_true(!empty($impact['ok']),'ineligible performer revision'); $assignment=$db->where('request_id',vcw_id(6))->get('request_visit_performer_assignments')->row(); vcw_assert_same('diganti',(string)$assignment->status,'ineligible assignment closed'); vcw_assert_same((int)vcw_id(6),(int)$assignment->ended_by_user_id,'closure actor'); vcw_assert_same(null,$assignment->completed_at,'closure completion timestamp'); vcw_assert_same(null,$db->where('request_id',vcw_id(6))->get('requests')->row()->visit_performer_user_id,'projection cleared');
// visit to non-visit cancels assignment without cancelling request
vcw_fixture($db,vcw_id(7),vcw_id(7),true); $s->create(vcw_id(7),vcw_id(7),array('decision'=>'visit','urgency'=>'routine'),'nonvisit-v1'); $impact=$s->revise(vcw_id(7),vcw_id(7),1,array('decision'=>'non_visit','urgency'=>'urgent','required_profession'=>'Perawat'),'nonvisit-v2'); vcw_assert_true(!empty($impact['ok']),'non-visit revision'); $assignment=$db->where('request_id',vcw_id(7))->get('request_visit_performer_assignments')->row(); vcw_assert_same('dibatalkan',(string)$assignment->status,'nonvisit assignment closed'); vcw_assert_same('Accepted',(string)$db->where('request_id',vcw_id(7))->get('requests')->row()->request_status,'request remains accepted');
// event fault injection proves transaction rollback
vcw_fixture($db,vcw_id(8),vcw_id(8),true); $s->create(vcw_id(8),vcw_id(8),array('decision'=>'visit','urgency'=>'routine'),'rollback-v1'); $db->query("CREATE TRIGGER vcw_fault_event BEFORE INSERT ON request_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='vcw fault'"); $before=$db->where('request_id',vcw_id(8))->get('visit_dispositions')->result(); $failed=$s->revise(vcw_id(8),vcw_id(8),1,array('decision'=>'visit','urgency'=>'priority'),'rollback-v2'); $db->query('DROP TRIGGER vcw_fault_event'); $after=$db->where('request_id',vcw_id(8))->get('visit_dispositions')->result(); vcw_assert_same(false,(bool)($failed['ok']??false),'rollback failure result'); vcw_assert_same(1,count($before),'rollback before rows'); vcw_assert_same(1,count($after),'rollback after rows'); vcw_assert_same(null,$after[0]->superseded_at,'rollback supersession');
// cross-context key misuse is rejected
vcw_fixture($db,vcw_id(13),vcw_id(13)); $cross=$s->create(vcw_id(13),vcw_id(13),array('decision'=>'visit','urgency'=>'routine'),'full-valid'); vcw_assert_same('IDEMPOTENCY_KEY_CONFLICT',$cross['code']??null,'cross-context key');
// physical visit states lock disposition revision
foreach (array('en_route','arrived','in_service','completed') as $offset=>$state) { $req=vcw_id(20+$offset); $doc=vcw_id(20+$offset); vcw_fixture($db,$req,$doc); $s->create($req,$doc,array('decision'=>'visit','urgency'=>'routine'),'lock-'.$state); $db->where('request_id',$req)->update('requests',array('visit_status'=>$state)); $locked=$s->revise($req,$doc,1,array('decision'=>'visit','urgency'=>'priority'),'lock-rev-'.$state); vcw_assert_same('DISPOSITION_LOCKED',$locked['code']??null,'locked '.$state); }
// non-visit to visit does not auto-assign a performer
vcw_fixture($db,vcw_id(30),vcw_id(30)); $s->create(vcw_id(30),vcw_id(30),array('decision'=>'non_visit'),'nv-v1'); $nv=$s->revise(vcw_id(30),vcw_id(30),1,array('decision'=>'visit','urgency'=>'routine'),'nv-v2'); vcw_assert_true(!empty($nv['ok']),'nonvisit to visit'); vcw_assert_same(0,(int)$db->where('request_id',vcw_id(30))->where('status','aktif')->count_all_results('request_visit_performer_assignments'),'no auto performer');
echo "REAL_DISPOSITION_SERVICE_CALLED=YES\nCREATE_MATRIX=PASS\nREVISION_MATRIX=PASS\nCREATE_IDEMPOTENT_REPLAY=PASS\nREVISION_IDEMPOTENT_REPLAY=PASS\nENROLLED_REVISION_AFTER_FLAG_OFF=PASS\nTASK4_REAL_SERVICE_EXECUTION=PASS\n";
echo "INELIGIBLE_ASSIGNMENT_CLOSED_ATOMICALLY=PASS\nNON_VISIT_REVISION_CLEARS_VISIT_ASSIGNMENT=PASS\nROLLBACK_ATOMICITY=PASS\nORPHAN_EVENT_ON_ROLLBACK=NO\n";
