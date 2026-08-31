<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
if (!class_exists('MX_Controller')) { class MX_Controller {} }
require_once APPPATH . 'modules/konsultasi_nakes/models/Konsultasi_nakes_m.php';
class Task10LegacyModel extends Konsultasi_nakes_m { public $db; public $config; public $session; public function __construct() {} }

$base = 180000 + (int) (microtime(true) * 100) % 10000;
$facility = 'T10L-' . $base; $actor = $base + 1; $requestId = $base + 10;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'Task 10 Legacy', 'aktif'));
foreach (array($base - 1 => 'Command', $actor => 'Doctor') as $uid => $label) {
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($uid, 'T10 ' . $label, 't10l' . $uid . '@invalid', 't10l' . $uid, 'x', 'dokter', 'aktif', 0, $facility));
}
$db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($actor, $facility, $actor, 'T10 Legacy Doctor', 'dokter', 'aktif'));
$db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($actor, $facility, '2026-01-01', 'active', $actor));
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,consultation_mode,responsible_doctor_user_id,assigned_nakes_user_id) VALUES (?,?,?,?,?,?,?,?,?)', array($requestId, $actor, 'synthetic', 'Accepted', $facility, 'Task 10 Legacy', 'non_visit', $actor, $actor));
$db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($requestId, $actor, $actor, $actor, 'aktif'));
$db->query('INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', array($requestId, 1, 'non_visit', null, $actor, 't10l-disp-' . $requestId));
$identity = doclinc_dokter_identity_context($actor, true);
$beforeMedical = (int) $db->where('request_id', $requestId)->count_all_results('medicalrecords');
$beforeKonsul = (int) $db->where('request_id', $requestId)->count_all_results('konsultasi');
$beforeTerapi = 0;
$beforeCompletedOutbox = (int) $db->where('event_type', 'request.completed')->where('aggregate_id', (string) $requestId)->count_all_results('realtime_outbox');
$ref = new ReflectionClass('Task10LegacyModel');
$model = $ref->newInstanceWithoutConstructor();
$model->db = $db; $model->config = $app->config; $model->session = $app->session;
$invoke = function ($enabled) use ($model, $app, $db, $requestId, $actor, $identity) {
    $app->config->set('care_team_workflow_enabled', $enabled);
    $result = $model->save_konsultasi_nakes($requestId, 'd', 's', '0', '', '', array(), $actor, $identity);
    $row = $db->where('request_id', $requestId)->get('requests')->row();
    return array($result, (string) $model->last_failure_code(), $row ? (string) $row->request_status : 'missing');
};
list($onResult, $onCode, $onStatus) = $invoke(true);
vcw_assert_true($onResult === false, 'enrolled legacy completion rejected with feature enabled');
vcw_assert_same('clinical_closure_required', $onCode, 'enrolled rejection code');
vcw_assert_same('Accepted', $onStatus, 'enrolled request remains accepted');
list($offResult, $offCode, $offStatus) = $invoke(false);
vcw_assert_true($offResult === false, 'enrolled legacy completion rejected with feature disabled');
vcw_assert_same('clinical_closure_required', $offCode, 'feature-independent rejection code');
vcw_assert_same('Accepted', $offStatus, 'feature-disabled enrolled request remains accepted');
vcw_assert_same($beforeMedical, (int) $db->where('request_id', $requestId)->count_all_results('medicalrecords'), 'no enrolled medicalrecord write');
vcw_assert_same($beforeKonsul, (int) $db->where('request_id', $requestId)->count_all_results('konsultasi'), 'no enrolled consultation write');
vcw_assert_same($beforeTerapi, 0, 'no enrolled therapy write');
vcw_assert_same($beforeCompletedOutbox, (int) $db->where('event_type', 'request.completed')->where('aggregate_id', (string) $requestId)->count_all_results('realtime_outbox'), 'no enrolled completion outbox');

$nonEnrolled = $requestId + 1;
$db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,consultation_mode,assigned_nakes_user_id) VALUES (?,?,?,?,?,?,?,?)', array($nonEnrolled, $actor, 'synthetic', 'Accepted', $facility, 'Task 10 Legacy', 'non_visit', $actor));
$app->config->set('care_team_workflow_enabled', false);
$legacyResult = $model->save_konsultasi_nakes($nonEnrolled, 'd', 's', '0', '', '', array(), $actor, $identity);
vcw_assert_true($legacyResult !== false, 'non-enrolled legacy behavior preserved');
$legacyRow = $db->where('request_id', $nonEnrolled)->get('requests')->row();
vcw_assert_same('Completed', (string) $legacyRow->request_status, 'non-enrolled legacy request completes');
echo "CLINICAL_CLOSURE_LEGACY_BYPASS=PASS\n";
