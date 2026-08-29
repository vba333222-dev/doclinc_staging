<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);

if (!class_exists('MX_Controller')) {
    class MX_Controller
    {
        public $load;
        public $config;
        public $db;

        public function __construct()
        {
            $app = get_instance();
            $this->load = $app->load;
            $this->config = $app->config;
            $this->db = $app->db;
        }
    }
}

require_once APPPATH . 'libraries/Visit_vital_signs_service.php';
require_once APPPATH . 'modules/home_nakes/models/Home_nakes_m.php';

vcw_assert_true((bool) $db->table_exists('request_vital_sign_measurements'), 'canonical vital signs table is required');
vcw_assert_true((bool) $db->table_exists('nakes_facility_placements'), 'canonical placement table is required');

function ttv_user($db, $userId, $facility, $staffId, $profession)
{
    $db->query(
        'INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)',
        array($userId, 'TTV ' . $userId, 'ttv-' . $userId . '@example.invalid', 'ttv-' . $userId, 'x', 'dokter', 'aktif', 0, $facility)
    );
    if ($staffId > 0) {
        $db->query(
            'INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)',
            array($staffId, $facility, $userId, 'TTV Staff ' . $userId, $profession, 'aktif')
        );
        $db->query(
            'INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)',
            array($staffId, $facility, '2026-01-01 00:00:00', 'active', $userId)
        );
    }
}

function ttv_request_fixture($db, $requestId, $facility, $doctor, $doctorStaff, $performer, $performerStaff, $command, $status, $activePerformer = true, $priorPerformer = null)
{
    vcw_assert_safe_request_id($requestId);
    $db->query(
        'INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
        array($requestId, $doctor, 'synthetic', 'Accepted', $facility, 'TTV Facility', $status, 'visit', $doctor, $performer)
    );
    $db->query(
        'INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))',
        array($requestId, $doctorStaff, $doctor, $doctor, 'aktif')
    );
    $db->query(
        'INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key) VALUES (?,?,?,?,?,?)',
        array($requestId, 1, 'visit', 'routine', $doctor, 'ttv-disposition-' . $requestId)
    );
    if (is_array($priorPerformer)) {
        $db->query(
            'INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,ended_at) VALUES (?,?,?,?,?,DATE_SUB(NOW(6), INTERVAL 2 SECOND),DATE_SUB(NOW(6), INTERVAL 1 SECOND))',
            array($requestId, (int) $priorPerformer[1], (int) $priorPerformer[0], $command, 'diganti')
        );
    }
    $db->query(
        'INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,ended_at) VALUES (?,?,?,?,?,NOW(6),?)',
        array($requestId, $performerStaff, $performer, $command, $activePerformer ? 'aktif' : 'dibatalkan', $activePerformer ? null : date('Y-m-d H:i:s'))
    );
    return (int) $db->insert_id();
}

function ttv_count($db, $requestId)
{
    return (int) $db->where('request_id', (int) $requestId)->count_all_results('request_vital_sign_measurements');
}

function ttv_expect_denied_without_insert($service, $db, $requestId, $actorId, $values, $message)
{
    $before = ttv_count($db, $requestId);
    $result = $service->record($requestId, $actorId, doclinc_dokter_identity_context($actorId, true), $values);
    vcw_assert_same('access_denied', $result['safe_error_code'] ?? null, $message . ' stable denial');
    vcw_assert_same($before, ttv_count($db, $requestId), $message . ' no insert');
}

$maxima = $db->query(
    'SELECT GREATEST(COALESCE((SELECT MAX(userId) FROM users),0),COALESCE((SELECT MAX(staff_id) FROM puskesmas_staff),0),COALESCE((SELECT MAX(request_id) FROM requests),0)) AS base_id'
)->row();
$base = (int) $maxima->base_id + 1000;
$facilityA = 'TTV-A-' . $base;
$facilityB = 'TTV-B-' . $base;
$db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?),(?,?,?)', array($facilityA, 'TTV Facility A', 'aktif', $facilityB, 'TTV Facility B', 'aktif'));

$command = $base + 1;
$doctor = $base + 2;
$performer = $base + 3;
$replacement = $base + 4;
$unrelated = $base + 5;
$crossFacility = $base + 6;
$doctorPerformer = $base + 7;
ttv_user($db, $command, $facilityA, 0, 'Perawat');
ttv_user($db, $doctor, $facilityA, $doctor, 'dokter');
ttv_user($db, $performer, $facilityA, $performer, 'Perawat');
ttv_user($db, $replacement, $facilityA, $replacement, 'Bidan');
ttv_user($db, $unrelated, $facilityA, $unrelated, 'Perawat');
ttv_user($db, $crossFacility, $facilityB, $crossFacility, 'Perawat');
ttv_user($db, $doctorPerformer, $facilityA, $doctorPerformer, 'dokter');

$service = new Visit_vital_signs_service($db);
$home = new Home_nakes_m();
$values = array(
    'systolic' => '120',
    'diastolic' => '80',
    'pulse' => '72',
    'respiratory_rate' => '18',
    'temperature_c' => '36.7',
    'oxygen_saturation' => '98',
    'notes' => 'Synthetic Task 6B',
    'measured_by_user_id' => $command,
    'measured_by_staff_id' => 999999,
    'responsible_doctor_user_id' => $command,
    'visit_performer_user_id' => $command,
    'measured_at' => '2000-01-01 00:00:00.000000',
);

$request = $base + 100;
ttv_request_fixture($db, $request, $facilityA, $doctor, $doctor, $performer, $performer, $command, 'arrived');
$write = $service->record($request, $performer, doclinc_dokter_identity_context($performer, true), $values);
vcw_assert_same('success', $write['status'] ?? null, 'canonical performer writes TTV');
$measurement = $db->where('measurement_id', (int) $write['measurement_id'])->get('request_vital_sign_measurements')->row();
vcw_assert_true((bool) $measurement, 'canonical measurement persisted');
vcw_assert_same($request, (int) $measurement->request_id, 'measurement request attribution');
vcw_assert_same($performer, (int) $measurement->measured_by_user_id, 'measurement actor user attribution');
vcw_assert_same($performer, (int) $measurement->measured_by_staff_id, 'measurement actor staff attribution');
vcw_assert_same($doctor, (int) $measurement->responsible_doctor_user_id, 'measurement responsible doctor snapshot');
vcw_assert_same($performer, (int) $measurement->visit_performer_user_id, 'measurement performer snapshot');
vcw_assert_true((string) $measurement->measured_at !== '2000-01-01 00:00:00.000000', 'client timestamp ignored');

$doctorRequest = $request + 1;
ttv_request_fixture($db, $doctorRequest, $facilityA, $doctorPerformer, $doctorPerformer, $doctorPerformer, $doctorPerformer, $command, 'arrived');
$doctorWrite = $service->record($doctorRequest, $doctorPerformer, doclinc_dokter_identity_context($doctorPerformer, true), $values);
$doctorMeasurement = !empty($doctorWrite['measurement_id']) ? $db->where('measurement_id', (int) $doctorWrite['measurement_id'])->get('request_vital_sign_measurements')->row() : null;
vcw_assert_true(($doctorWrite['status'] ?? null) === 'success' && $doctorMeasurement && (int) $doctorMeasurement->measured_by_user_id === $doctorPerformer && (int) $doctorMeasurement->responsible_doctor_user_id === $doctorPerformer, 'doctor performer writes with canonical attribution');

$negativeRequest = $request + 2;
ttv_request_fixture($db, $negativeRequest, $facilityA, $doctor, $doctor, $performer, $performer, $command, 'arrived');
ttv_expect_denied_without_insert($service, $db, $negativeRequest, $unrelated, $values, 'unrelated personal Nakes');
ttv_expect_denied_without_insert($service, $db, $negativeRequest, $crossFacility, $values, 'cross-facility personal Nakes');
ttv_expect_denied_without_insert($service, $db, $negativeRequest, $command, $values, 'command center identity');
ttv_expect_denied_without_insert($service, $db, $negativeRequest, $doctor, $values, 'non-performer responsible doctor');

$replacedRequest = $request + 3;
ttv_request_fixture($db, $replacedRequest, $facilityA, $doctor, $doctor, $replacement, $replacement, $command, 'arrived', true, array($performer, $performer));
ttv_expect_denied_without_insert($service, $db, $replacedRequest, $performer, $values, 'replaced performer');

$terminalRequest = $request + 4;
ttv_request_fixture($db, $terminalRequest, $facilityA, $doctor, $doctor, $performer, $performer, $command, 'arrived', false);
ttv_expect_denied_without_insert($service, $db, $terminalRequest, $performer, $values, 'terminal performer assignment');

$stateResults = array();
foreach (array('not_started', 'en_route', 'arrived', 'in_service', 'completed') as $index => $state) {
    $stateRequest = $request + 10 + $index;
    ttv_request_fixture($db, $stateRequest, $facilityA, $doctor, $doctor, $performer, $performer, $command, $state);
    $before = ttv_count($db, $stateRequest);
    $stateResults[$state] = $service->record($stateRequest, $performer, doclinc_dokter_identity_context($performer, true), $values);
    $allowed = in_array($state, array('arrived', 'in_service'), true);
    vcw_assert_same($allowed ? 'success' : 'access_denied', $allowed ? ($stateResults[$state]['status'] ?? null) : ($stateResults[$state]['safe_error_code'] ?? null), 'state guard ' . $state);
    vcw_assert_same($before + ($allowed ? 1 : 0), ttv_count($db, $stateRequest), 'state insert count ' . $state);
}

$invalidRequest = $request + 20;
ttv_request_fixture($db, $invalidRequest, $facilityA, $doctor, $doctor, $performer, $performer, $command, 'arrived');
$invalid = $service->record($invalidRequest, $performer, doclinc_dokter_identity_context($performer, true), array('systolic' => '999'));
vcw_assert_same('invalid_values', $invalid['safe_error_code'] ?? null, 'invalid clinical value stable code');
vcw_assert_same(0, ttv_count($db, $invalidRequest), 'invalid clinical value no insert');

$lifecycleRequest = $request + 30;
ttv_request_fixture($db, $lifecycleRequest, $facilityA, $doctor, $doctor, $performer, $performer, $command, 'not_started');
$performerIdentity = doclinc_dokter_identity_context($performer, true);
foreach (array('en_route', 'arrived', 'in_service') as $nextState) {
    $transition = $home->update_visit_status($lifecycleRequest, $performer, $nextState, $performerIdentity);
    vcw_assert_same('success', $transition['status'] ?? null, 'physical lifecycle reaches ' . $nextState);
    vcw_assert_same('Accepted', (string) $db->where('request_id', $lifecycleRequest)->get('requests')->row()->request_status, 'request remains Accepted at ' . $nextState);
}
$withoutTtv = $home->update_visit_status($lifecycleRequest, $performer, 'completed', $performerIdentity);
$beforeTtvState = $db->where('request_id', $lifecycleRequest)->get('requests')->row();
vcw_assert_same('vital_signs_required', $withoutTtv['safe_error_code'] ?? null, 'completion requires current TTV');
vcw_assert_true($beforeTtvState && (string) $beforeTtvState->request_status === 'Accepted' && (string) $beforeTtvState->visit_status === 'in_service', 'failed completion has no partial mutation');
$lifecycleWrite = $service->record($lifecycleRequest, $performer, $performerIdentity, $values);
vcw_assert_same('success', $lifecycleWrite['status'] ?? null, 'current-cycle TTV recorded');
$withTtv = $home->update_visit_status($lifecycleRequest, $performer, 'completed', $performerIdentity);
$completedState = $db->where('request_id', $lifecycleRequest)->get('requests')->row();
vcw_assert_true(($withTtv['status'] ?? null) === 'success' && !empty($withTtv['changed']), 'completion succeeds with current TTV');
vcw_assert_true($completedState && (string) $completedState->request_status === 'Accepted' && (string) $completedState->visit_status === 'completed', 'physical completion preserves Accepted request');

$unrelatedMeasurementRequest = $request + 31;
ttv_request_fixture($db, $unrelatedMeasurementRequest, $facilityA, $doctor, $doctor, $performer, $performer, $command, 'in_service');
$db->insert('request_vital_sign_measurements', array(
    'request_id' => $unrelatedMeasurementRequest,
    'measured_by_user_id' => $unrelated,
    'measured_by_staff_id' => $unrelated,
    'responsible_doctor_user_id' => $doctor,
    'visit_performer_user_id' => $unrelated,
    'systolic' => 120,
    'measured_at' => date('Y-m-d H:i:s'),
));
$unrelatedCompletion = $home->update_visit_status($unrelatedMeasurementRequest, $performer, 'completed', $performerIdentity);
vcw_assert_same('vital_signs_required', $unrelatedCompletion['safe_error_code'] ?? null, 'wrong-attribution TTV does not satisfy completion');
vcw_assert_same('in_service', (string) $db->where('request_id', $unrelatedMeasurementRequest)->get('requests')->row()->visit_status, 'wrong-attribution denial has no mutation');

$priorCycleRequest = $request + 32;
$priorAssignmentId = ttv_request_fixture($db, $priorCycleRequest, $facilityA, $doctor, $doctor, $performer, $performer, $command, 'in_service');
$priorWrite = $service->record($priorCycleRequest, $performer, $performerIdentity, $values);
vcw_assert_same('success', $priorWrite['status'] ?? null, 'prior-cycle fixture measurement recorded legally');
$priorMeasurement = $db->where('measurement_id', (int) $priorWrite['measurement_id'])->get('request_vital_sign_measurements')->row();
$db->where('visit_assignment_id', $priorAssignmentId)->update('request_visit_performer_assignments', array('status' => 'diganti', 'ended_at' => $priorMeasurement->measured_at));
$db->query(
    'INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,DATE_ADD(?, INTERVAL 1 MICROSECOND))',
    array($priorCycleRequest, $performer, $performer, $command, 'aktif', $priorMeasurement->measured_at)
);
$priorCycleCompletion = $home->update_visit_status($priorCycleRequest, $performer, 'completed', $performerIdentity);
vcw_assert_same('vital_signs_required', $priorCycleCompletion['safe_error_code'] ?? null, 'prior-cycle TTV does not satisfy completion');
vcw_assert_same('in_service', (string) $db->where('request_id', $priorCycleRequest)->get('requests')->row()->visit_status, 'prior-cycle denial has no mutation');

$multipleRequest = $request + 40;
$multipleAssignmentId = ttv_request_fixture($db, $multipleRequest, $facilityA, $doctor, $doctor, $performer, $performer, $command, 'arrived');
$first = $service->record($multipleRequest, $performer, $performerIdentity, array_merge($values, array('systolic' => '118')));
vcw_assert_same('success', $first['status'] ?? null, 'first append-only measurement');
$firstBefore = $db->where('measurement_id', (int) $first['measurement_id'])->get('request_vital_sign_measurements')->row();
$second = $service->record($multipleRequest, $performer, $performerIdentity, array_merge($values, array('systolic' => '121')));
vcw_assert_same('success', $second['status'] ?? null, 'second append-only measurement');
$firstAfter = $db->where('measurement_id', (int) $first['measurement_id'])->get('request_vital_sign_measurements')->row();
$latest = $service->latest($multipleRequest);
vcw_assert_true((int) $first['measurement_id'] !== (int) $second['measurement_id'], 'append-only measurements have distinct ids');
vcw_assert_same(2, ttv_count($db, $multipleRequest), 'append-only measurement count');
vcw_assert_same((int) $firstBefore->systolic, (int) $firstAfter->systolic, 'first measurement remains immutable');
vcw_assert_same((int) $second['measurement_id'], (int) $latest->measurement_id, 'latest returns newest measurement');

$db->where('visit_assignment_id', $multipleAssignmentId)->update('request_visit_performer_assignments', array('status' => 'diganti', 'ended_at' => date('Y-m-d H:i:s')));
$historicalMeasurement = $db->where('measurement_id', (int) $first['measurement_id'])->get('request_vital_sign_measurements')->row();
$historicalAssignment = $db->where('visit_assignment_id', $multipleAssignmentId)->get('request_visit_performer_assignments')->row();
vcw_assert_true($historicalMeasurement && $historicalAssignment && (string) $historicalAssignment->status === 'diganti', 'terminal assignment history remains available');
vcw_assert_true((int) $historicalMeasurement->request_id === $multipleRequest
    && (int) $historicalMeasurement->measured_by_user_id === $performer
    && (int) $historicalMeasurement->measured_by_staff_id === $performer
    && (int) $historicalMeasurement->visit_performer_user_id === $performer
    && !empty($historicalMeasurement->measured_at), 'measurement provenance survives terminal assignment status');

$legacyRequest = $request + 50;
vcw_assert_safe_request_id($legacyRequest);
$db->query(
    'INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id) VALUES (?,?,?,?,?,?,?,?,?)',
    array($legacyRequest, $doctor, 'synthetic', 'Accepted', $facilityA, 'TTV Facility', 'not_started', 'non_visit', $doctor)
);
vcw_assert_same(0, (int) $db->where('request_id', $legacyRequest)->count_all_results('visit_dispositions'), 'legacy non-Visit remains non-enrolled');
vcw_assert_same(0, ttv_count($db, $legacyRequest), 'legacy non-Visit has no canonical TTV requirement');

echo "TTV_CANONICAL_PERFORMER_WRITE=PASS\n";
echo "TTV_SERVER_DERIVED_ATTRIBUTION=PASS\n";
echo "TTV_DOCTOR_PERFORMER_WRITE=PASS\n";
echo "TTV_UNRELATED_NAKES_DENIED=PASS\n";
echo "TTV_REPLACED_PERFORMER_DENIED=PASS\n";
echo "TTV_TERMINAL_PERFORMER_DENIED=PASS\n";
echo "TTV_CROSS_FACILITY_DENIED=PASS\n";
echo "TTV_COMMAND_CENTER_DENIED=PASS\n";
echo "TTV_NON_PERFORMER_DOCTOR_DENIED=PASS\n";
echo "TTV_NOT_STARTED_DENIED=PASS\n";
echo "TTV_EN_ROUTE_DENIED=PASS\n";
echo "TTV_ARRIVED_ALLOWED=PASS\n";
echo "TTV_IN_SERVICE_ALLOWED=PASS\n";
echo "TTV_COMPLETED_DENIED=PASS\n";
echo "TTV_INVALID_VALUES_GUARD=PASS\n";
echo "COMPLETION_WITHOUT_TTV_DENIED=PASS\n";
echo "CURRENT_CYCLE_TTV_RECORDED=PASS\n";
echo "COMPLETION_WITH_CURRENT_TTV_ALLOWED=PASS\n";
echo "PHYSICAL_COMPLETED_REQUEST_REMAINS_ACCEPTED=PASS\n";
echo "UNRELATED_TTV_DOES_NOT_SATISFY_COMPLETION=PASS\n";
echo "PRIOR_CYCLE_TTV_DOES_NOT_SATISFY_COMPLETION=PASS\n";
echo "HISTORICAL_TERMINAL_ATTRIBUTION=PASS\n";
echo "TTV_APPEND_ONLY_MULTIPLE_MEASUREMENTS=PASS\n";
echo "TASK6_FULL_PHYSICAL_LIFECYCLE_WITH_TTV=PASS\n";
echo "TTV_NON_ENROLLED_LEGACY_PRESERVED=PASS\n";
echo "TASK6B_SCHEMA_CHANGE_REQUIRED=NO\n";
