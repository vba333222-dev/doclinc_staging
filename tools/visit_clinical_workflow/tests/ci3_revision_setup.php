<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$request = 29001; $doctor = 29001; $facility = 'PKM-RACE';
vcw_assert_safe_request_id($request);
foreach (array(
    'request_events' => 'request_id >= 28000',
    'request_responsible_doctor_assignments' => 'request_id >= 28000',
    'request_visit_performer_assignments' => 'request_id >= 28000',
    'visit_dispositions' => 'request_id >= 28000',
    'requests' => 'request_id >= 28000',
    'puskesmas_staff' => 'staff_id >= 28000',
    'users' => 'userId >= 28000',
) as $table => $where) { $db->query("DELETE FROM {$table} WHERE {$where}"); }
$db->query('DELETE FROM m_puskesmas WHERE kode_pkm=?', array($facility));
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility,'Race Facility','aktif'));
foreach (array($doctor, $doctor + 100) as $id) {
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($id,'Race '.$id,'race'.$id.'@runner.invalid','race'.$id,password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT),'dokter','aktif',0,$facility));
}
$db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array(28900,'Race Command Center','race28900@runner.invalid','race28900',password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT),'dokter','aktif',0,$facility));
$db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($doctor,$facility,$doctor,'Race Doctor','dokter','aktif'));
$db->query('INSERT INTO requests (request_id,user_id,request_description,request_status,date,location,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?,?,?)', array($request,$doctor,'race','Accepted','2026-01-01 00:00:00','Race',$facility,'Race','not_started'));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request,$doctor,$doctor,$doctor,'aktif'));
$service = new Visit_disposition_service($db, new Visit_workflow_policy($db, true), true);
$result = $service->create($request,$doctor,array('decision'=>'visit','urgency'=>'routine'),'race-create');
if (empty($result['ok'])) { throw new RuntimeException('race setup failed '.json_encode($result)); }
echo "REVISION_SETUP=PASS\n";
