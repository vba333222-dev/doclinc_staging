<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$app->config->set('care_team_workflow_enabled', true);
require_once APPPATH . 'libraries/Clinical_finalization_service.php';
require_once APPPATH . 'libraries/Clinical_amendment_service.php';

$db->query("CREATE TABLE IF NOT EXISTS nakes_facility_placements (placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,staff_id INT UNSIGNED NOT NULL,facility_code VARCHAR(64) NOT NULL,effective_from DATETIME NOT NULL,effective_until DATETIME NULL,status ENUM('active','ended') NOT NULL DEFAULT 'active',active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED,created_by_user_id INT NOT NULL,ended_by_user_id INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(placement_id),UNIQUE KEY uq_nakes_active_staff(active_staff_key)) ENGINE=InnoDB");

function t9_clock_fixture($db, $id, $finalized)
{
    $actor = $id + 1; $facility = 'T9CLOCK-' . $id;
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', [$facility, 'Task 9 Clock', 'aktif']);
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', [$id, 'Task9 Clock Command', 'clockcmd'.$id.'@invalid', 'clockcmd'.$id, 'x', 'dokter', 'aktif', 0, $facility]);
    $db->query('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', [$actor, 'Task9 Clock Doctor', 'clock'.$id.'@invalid', 'clock'.$id, 'x', 'dokter', 'aktif', 0, $facility]);
    $db->query('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', [$actor, $facility, $actor, 'Clock Doctor', 'dokter', 'aktif']);
    $db->query('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', [$actor, $facility, '2026-01-01', 'active', $actor]);
    $db->query('INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,consultation_mode,responsible_doctor_user_id) VALUES (?,?,?,?,?,?,?,?)', [$id, $actor, 'clock', 'Accepted', $facility, 'Task 9 Clock', 'non_visit', $actor]);
    $db->query('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', [$id, $actor, $actor, $actor, 'aktif']);
    $at = $finalized ? 'NOW(6)' : 'NULL';
    $db->query("INSERT INTO medicalrecords (request_id,diagnosis,treatment,recommendations,anamnesis,responsible_doctor_user_id,recorded_by_user_id,clinical_finalized_at,clinical_finalized_by_user_id) VALUES (?,?,?,?,?,?,?,$at,?)", [$id, 'initial', 'initial', 'initial', 'history', $actor, $actor, $finalized ? $actor : null]);
    return [$id, (int) $db->insert_id(), $actor];
}

function t9_clock_delta($a, $b)
{
    return abs(strtotime((string) $a) - strtotime((string) $b));
}

$previousTimezone = date_default_timezone_get();
$clockFailures = [];
try {
    date_default_timezone_set('Europe/Berlin');
    [$request, $record, $actor] = t9_clock_fixture($db, 610000 + (int) (microtime(true) * 100) % 10000, false);
    $before = $db->query('SELECT CURRENT_TIMESTAMP(6) AS now')->row()->now;
    $reply = (new Clinical_finalization_service($db))->finalize($request, $actor, 'clock-finalize-' . $request);
    vcw_assert_same('success', $reply['status'] ?? null, 'clock finalization succeeds');
    $stored = $db->where('record_id', $record)->get('medicalrecords')->row()->clinical_finalized_at;
    $event = $db->where('request_id', $request)->where('event_type', 'clinical_record.finalized')->get('request_events')->row();
    vcw_assert_true($event && t9_clock_delta($stored, $event->created_at) <= 30, 'finalization event shares DB clock basis');
    $after = $db->query('SELECT CURRENT_TIMESTAMP(6) AS now')->row()->now;
    echo "FINALIZATION_CLOCK_VALUES=stored:$stored before:$before after:$after\n";
    if (!(t9_clock_delta($stored, $before) <= 30 && t9_clock_delta($stored, $after) <= 30)) { $clockFailures[] = 'finalization'; echo "TASK9_FINALIZATION_DB_CLOCK_RED_OBSERVED=YES\n"; } else { echo "TASK9_FINALIZATION_DB_CLOCK=PASS\n"; }

    [$request2, $record2, $actor2] = t9_clock_fixture($db, $request + 100, true);
    $before2 = $db->query('SELECT CURRENT_TIMESTAMP(6) AS now')->row()->now;
    $amend = (new Clinical_amendment_service($db))->amend($request2, $record2, $actor2, 'clock', [['field_key' => 'diagnosis', 'corrected_value' => 'changed']], 'clock-amend-' . $request2);
    vcw_assert_same('success', $amend['status'] ?? null, 'clock amendment succeeds');
    $stored2 = $db->where('amendment_id', (int) $amend['amendment_id'])->get('clinical_amendments')->row()->created_at;
    $event2 = $db->where('request_id', $request2)->where('event_type', 'clinical_record.amended')->get('request_events')->row();
    vcw_assert_true($event2 && t9_clock_delta($stored2, $event2->created_at) <= 30, 'amendment event shares DB clock basis');
    $after2 = $db->query('SELECT CURRENT_TIMESTAMP(6) AS now')->row()->now;
    echo "AMENDMENT_CLOCK_VALUES=stored:$stored2 before:$before2 after:$after2\n";
    if (!(t9_clock_delta($stored2, $before2) <= 30 && t9_clock_delta($stored2, $after2) <= 30)) { $clockFailures[] = 'amendment'; echo "TASK9_AMENDMENT_DB_CLOCK_RED_OBSERVED=YES\n"; } else { echo "TASK9_AMENDMENT_DB_CLOCK=PASS\n"; }
} finally {
    date_default_timezone_set($previousTimezone);
    vcw_assert_same($previousTimezone, date_default_timezone_get(), 'PHP timezone restored');
    echo "TASK9_PHP_TIMEZONE_TEST_ISOLATION=PASS\n";
}
if ($clockFailures) { throw new RuntimeException('DB_CLOCK_RED:' . implode(',', $clockFailures)); }
echo "TASK9_MIXED_CLOCK_RISK_RESOLVED=PASS\n";
