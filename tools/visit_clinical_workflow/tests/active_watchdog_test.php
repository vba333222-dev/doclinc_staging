<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'helpers/request_event_helper.php';
require_once APPPATH . 'helpers/notification_helper.php';
require_once APPPATH . 'libraries/Active_request_watchdog.php';

$passed = 0;
$failed = 0;
function aw_assert($condition, $label)
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL {$label}\n";
}

$base = random_int(48200, 48800);
$request = $base;
$patient = $base + 1;
$doctor = $base + 2;
$facility = 'WATCH-' . $base;
$db->db_debug = false;
aw_assert((int) $db->query('SELECT COUNT(*) c FROM requests WHERE request_id=?', array($request))->row()->c === 0, 'fixture_request_id_unused');

$db->trans_begin();
try {
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'Watchdog Facility', 'aktif'));
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($patient, 'Watchdog Patient', 'watch-' . $patient . '@invalid', 'watch-' . $patient, 'x', 'user', 'aktif', 0, $facility));
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', array($doctor, 'Watchdog Doctor', 'watch-' . $doctor . '@invalid', 'watch-' . $doctor, 'x', 'dokter', 'aktif', 0, $facility));
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,created_at) VALUES (?,?,?,?,?,?,?,DATE_SUB(NOW(), INTERVAL 90 MINUTE))', array($request, $patient, 'synthetic', 'Accepted', $facility, 'Watchdog Facility', 'not_started'));

    $watchdog = new Active_request_watchdog($db, true);
    $exceptions = $watchdog->exceptionsFor($facility);
    aw_assert(count($exceptions) === 1 && $exceptions[0]['type'] === 'no_responsible_doctor', 'flags_missing_responsible_doctor');
    aw_assert($exceptions[0]['severity'] === 'overdue', 'marks_overdue_after_threshold');

    $first = $watchdog->sweep($facility);
    aw_assert($first['flagged'] >= 1 && $first['notified'] >= 1, 'sweep_writes_and_notifies');
    $eventKey = 'request:' . $request . ':clinical-stall:no_responsible_doctor:overdue';
    $eventCount = (int) $db->query('SELECT COUNT(*) c FROM request_events WHERE domain_event_key=?', array($eventKey))->row()->c;
    aw_assert($eventCount === 1, 'stall_event_written_once');

    $second = $watchdog->sweep($facility);
    aw_assert((int) $second['notified'] === 0, 'sweep_is_idempotent');

    $db->query('UPDATE requests SET responsible_doctor_user_id=? WHERE request_id=?', array($doctor, $request));
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($request, $doctor, $doctor, $doctor, 'aktif'));
    $exceptions = $watchdog->exceptionsFor($facility);
    aw_assert(count($exceptions) === 0, 'disposition_is_optional_not_a_stall');

    $db->query("INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))", array($request, 1, 'visit', 'routine', $doctor, 'watch-disp-' . $base));
    $exceptions = $watchdog->exceptionsFor($facility);
    aw_assert(count($exceptions) === 1 && $exceptions[0]['type'] === 'no_performer', 'flags_missing_performer');
} catch (Throwable $exception) {
    aw_assert(false, 'watchdog_exception:' . $exception->getMessage());
}
$db->trans_rollback();
aw_assert((int) $db->query('SELECT COUNT(*) c FROM requests WHERE request_id=?', array($request))->row()->c === 0, 'watchdog_rolled_back');

echo 'ACTIVE_WATCHDOG=PASSED=' . $passed . ', FAILED=' . $failed . "\n";
exit($failed > 0 ? 1 : 0);
