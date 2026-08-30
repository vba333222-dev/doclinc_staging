<?php

function r7c_user($db, $userId, $facility, $staffId, $profession)
{
    $db->query(
        'INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)',
        array($userId, 'Submission ' . $userId, 'submission-' . $userId . '@example.invalid', 'submission-' . $userId, 'x', 'dokter', 'aktif', 0, $facility)
    );
    if ($staffId > 0) {
        $db->query(
            'INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)',
            array($staffId, $facility, $userId, 'Submission Staff ' . $userId, $profession, 'aktif')
        );
        $db->query(
            'INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)',
            array($staffId, $facility, '2026-01-01 00:00:00', 'active', $userId)
        );
    }
}

function r7c_fixture($db, $base, $suffix, array $options = array())
{
    $facility = 'RESULT-SUBMIT-' . $suffix . '-' . $base;
    $request = $base;
    $command = $base + 1;
    $doctor = $base + 2;
    $performer = $base + 3;
    $replacement = $base + 4;
    $unrelated = $base + 5;
    $cross = $base + 6;
    $doctorPerformer = $base + 7;
    vcw_assert_safe_request_id($request);
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($facility, 'Submission Facility', 'aktif'));
    $crossFacility = $facility . '-OTHER';
    $db->query('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($crossFacility, 'Other Facility', 'aktif'));
    r7c_user($db, $command, $facility, 0, 'Perawat');
    r7c_user($db, $doctor, $facility, $doctor, 'dokter');
    r7c_user($db, $performer, $facility, $performer, 'Perawat');
    r7c_user($db, $replacement, $facility, $replacement, 'Bidan');
    r7c_user($db, $unrelated, $facility, $unrelated, 'Perawat');
    r7c_user($db, $cross, $crossFacility, $cross, 'Perawat');
    r7c_user($db, $doctorPerformer, $facility, $doctorPerformer, 'dokter');

    if (!empty($options['doctor_performer'])) {
        $doctor = $doctorPerformer;
        $performer = $doctorPerformer;
    }
    $status = $options['visit_status'] ?? 'completed';
    $projection = $options['projection'] ?? $performer;
    $requestStatus = $options['request_status'] ?? 'Accepted';
    $db->query(
        'INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id,visit_performer_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
        array($request, $doctor, 'synthetic', $requestStatus, $facility, 'Submission Facility', $status, 'visit', $doctor, $projection)
    );
    $db->query(
        'INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,DATE_SUB(NOW(6),INTERVAL 30 SECOND))',
        array($request, $doctor, $doctor, $doctor, 'aktif')
    );
    $db->query(
        'INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,DATE_SUB(NOW(6),INTERVAL 30 SECOND))',
        array($request, 1, 'visit', 'routine', $doctor, 'result-submit-disposition-' . $request)
    );
    $disposition = (int) $db->insert_id();
    $assignmentStatus = $options['assignment_status'] ?? 'aktif';
    $endedAt = $assignmentStatus === 'aktif' ? null : date('Y-m-d H:i:s');
    $db->query(
        'INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at,ended_at) VALUES (?,?,?,?,?,DATE_SUB(NOW(6),INTERVAL 20 SECOND),?)',
        array($request, $performer, $performer, $command, $assignmentStatus, $endedAt)
    );
    $assignment = (int) $db->insert_id();

    return compact('facility', 'crossFacility', 'request', 'command', 'doctor', 'performer', 'replacement', 'unrelated', 'cross', 'doctorPerformer', 'disposition', 'assignment');
}

function r7c_payload($suffix = 'valid')
{
    return array(
        'observation_summary' => 'Observasi ' . $suffix,
        'findings' => array(array('key' => 'skin_color', 'label' => 'Warna kulit', 'value' => 'Normal')),
        'actions' => array(array('key' => 'positioning', 'label' => 'Posisi', 'value' => 'Nyaman')),
        'performer_notes' => 'Catatan ' . $suffix,
    );
}

function r7c_draft($db, Visit_result_service $service, array $fixture, $suffix = 'valid')
{
    $draft = $service->getOrCreateDraft($fixture['request'], $fixture['performer']);
    vcw_assert_same('success', $draft['status'] ?? null, 'Task 7C fixture creates draft');
    $saved = $service->saveDraft((int) $draft['visit_result_id'], $fixture['performer'], 0, r7c_payload($suffix));
    vcw_assert_same('success', $saved['status'] ?? null, 'Task 7C fixture saves factual draft');
    return (int) $draft['visit_result_id'];
}

function r7c_measurement($db, array $fixture, array $overrides = array())
{
    $row = array_merge(array(
        'request_id' => $fixture['request'],
        'measured_by_user_id' => $fixture['performer'],
        'measured_by_staff_id' => $fixture['performer'],
        'responsible_doctor_user_id' => $fixture['doctor'],
        'visit_performer_user_id' => $fixture['performer'],
        'systolic' => 120,
        'diastolic' => 80,
        'pulse' => 72,
        'respiratory_rate' => 18,
        'temperature_c' => 36.7,
        'oxygen_saturation' => 98,
        'notes' => 'Synthetic Task 7C',
        'measured_at' => date('Y-m-d H:i:s.u'),
    ), $overrides);
    vcw_assert_true($db->insert('request_vital_sign_measurements', $row), 'Task 7C measurement fixture inserted');
    return (int) $db->insert_id();
}

function r7c_expect_code(array $result, $code, $message)
{
    vcw_assert_same($code, $result['safe_error_code'] ?? null, $message);
}

function r7c_event_count($db, $requestId)
{
    return (int) $db->where('request_id', (int) $requestId)->where('event_type', 'visit_result.submitted')->count_all_results('request_events');
}

function r7c_link_ids($db, $resultId)
{
    $rows = $db->select('measurement_id')->where('visit_result_id', (int) $resultId)->order_by('measurement_id', 'ASC')->get('visit_result_vital_sign_measurements')->result();
    return array_map(function ($row) { return (int) $row->measurement_id; }, $rows);
}
