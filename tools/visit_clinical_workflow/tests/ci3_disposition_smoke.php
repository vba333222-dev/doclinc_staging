<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$requestId=19901; $actor=19950; vcw_assert_safe_request_id($requestId);
$db->query("INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)",array('PKM-SOURCE','Synthetic Source','aktif'));
$db->query("INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)",array(19949,'Synthetic Command Center','task4-19949@runner.invalid','task4-19949@runner.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_BCRYPT),'dokter','aktif',0,'PKM-SOURCE'));
$db->query("INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)",array($actor,'Synthetic Doctor','task4-19950@runner.invalid','task4-19950@runner.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_BCRYPT),'dokter','aktif',0,'PKM-SOURCE'));
$db->query("INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)",array($actor,'PKM-SOURCE',$actor,'Synthetic Doctor','dokter','aktif'));
$db->query("INSERT INTO requests (request_id,user_id,request_description,request_status,date,location,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?,?,?)",array($requestId,$actor,'synthetic task4 request','Accepted','2026-01-01 00:00:00','Synthetic location','PKM-SOURCE','Synthetic Source','not_started'));
$candidate=$db->where('request_id',$requestId)->get('requests')->row(); vcw_assert_true($candidate !== null, 'synthetic eligible request missing');
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) SELECT ?,staff_id,user_id,?,\'aktif\',NOW(6) FROM puskesmas_staff WHERE user_id=? AND status=\'aktif\' LIMIT 1', array($requestId,$actor,$actor));
$beforeMode=(string)$candidate->consultation_mode; $beforeEvents=(int)$db->where('request_id',$requestId)->like('event_type','visit_disposition.','after')->count_all_results('request_events');
$off = new Visit_disposition_service($db, new Visit_workflow_policy($db,false), false); $offResult=$off->create($requestId,$actor,array('decision'=>'visit','urgency'=>'routine'),'task4-off-'.$requestId);
echo "DISPOSITION_FEATURE_OFF_RESULT_SAFE=".json_encode(array('ok'=>(bool)($offResult['ok']??false),'code'=>(string)($offResult['code']??'')))."\n";
vcw_assert_same('WORKFLOW_ENROLLMENT_DISABLED',$offResult['code']??null,'feature off result');
$offRows=(int)$db->where('request_id',$requestId)->count_all_results('visit_dispositions'); $offEvents=(int)$db->where('request_id',$requestId)->like('event_type','visit_disposition.','after')->count_all_results('request_events');
$on = new Visit_disposition_service($db, new Visit_workflow_policy($db,true), true); $onResult=$on->create($requestId,$actor,array('decision'=>'visit','urgency'=>'routine'),'task4-on-'.$requestId);
echo "DISPOSITION_FEATURE_ON_RESULT_SAFE=".json_encode(array('ok'=>(bool)($onResult['ok']??false),'code'=>(string)($onResult['code']??'')))."\n";
if (empty($onResult['ok'])) { throw new RuntimeException('feature on create failed: '.json_encode(array('code'=>(string)($onResult['code']??''),'message'=>(string)($onResult['message']??'')))); }
vcw_assert_true(!empty($onResult['ok']),'feature on create result'); $row=$db->where('disposition_id',(int)$onResult['disposition_id'])->get('visit_dispositions')->row();
$onRows=(int)$db->where('request_id',$requestId)->count_all_results('visit_dispositions'); $onEvents=(int)$db->where('request_id',$requestId)->like('event_type','visit_disposition.','after')->count_all_results('request_events'); $afterMode=(string)$db->where('request_id',$requestId)->get('requests')->row()->consultation_mode;
echo "REAL_DISPOSITION_SERVICE_CALLED=YES\nDISPOSITION_FEATURE_OFF_RESULT=".($offResult['code']??'')."\nDISPOSITION_FEATURE_OFF_ROWS=$offRows\nDISPOSITION_FEATURE_OFF_EVENTS=".($offEvents-$beforeEvents)."\nDISPOSITION_FEATURE_ON_ID=".(int)$onResult['disposition_id']."\nDISPOSITION_FEATURE_ON_VERSION=".(int)$onResult['version_no']."\nDISPOSITION_FEATURE_ON_ROWS=$onRows\nDISPOSITION_FEATURE_ON_EVENTS=".($onEvents-$beforeEvents)."\nCONSULTATION_MODE_BEFORE=$beforeMode\nCONSULTATION_MODE_AFTER=$afterMode\nDISPOSITION_CREATE_MUTATION_EXECUTED=PASS\n";
