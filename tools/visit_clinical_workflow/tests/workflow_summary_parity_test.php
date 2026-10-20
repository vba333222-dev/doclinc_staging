<?php
/**
 * Parity ringkasan workflow dengan state machine.
 *
 * Test ini tidak memakai database: ia membangun ringkasan yang sama dengan yang
 * dihasilkan Home_nakes_m lalu memastikan precedence state, langkah Clinical
 * Thread, dan label empat aktor tetap konsisten. Kasus disusun mengikuti urutan
 * yang dipakai Visit_workflow_state_resolver: request selesai, belum enroll,
 * non-visit, hasil/review, kunjungan, lalu penugasan.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }

require_once APPPATH . 'libraries/Clinical_workflow_summary.php';
require_once APPPATH . 'libraries/Visit_workflow_presenter.php';

$passed = 0;
$failed = 0;
function parity_check($condition, $label)
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ok   - {$label}\n";
        return true;
    }
    $failed++;
    echo "  FAIL - {$label}\n";
    return false;
}

function parity_summary(array $overrides = array())
{
    $base = array(
        'request' => array(
            'request_id' => 501,
            'request_status' => 'Accepted',
            'consultation_mode' => 'visit',
            'visit_status' => 'not_started',
        ),
        'disposition' => array('decision' => 'visit', 'version_no' => 1),
        'performer' => array('status' => 'aktif', 'display_name' => 'Nakes Satu'),
        'result' => null,
        'review' => null,
        'record' => null,
        'closure' => null,
    );
    foreach ($overrides as $key => $value) {
        $base[$key] = $value;
    }
    return $base;
}

$summary = new Clinical_workflow_summary(true);
$closedSummary = new Clinical_workflow_summary(false);
$result = array('visit_result_id' => 9, 'version_no' => 1, 'status' => 'submitted');
$approved = array('decision' => 'approved');
$correction = array('decision' => 'correction_required');
$finalized = array('record_id' => 3, 'clinical_finalized_at' => '2026-10-18 10:00:00');
$nonVisit = array('request' => array('request_id' => 501, 'request_status' => 'Accepted', 'consultation_mode' => 'non_visit', 'visit_status' => ''), 'disposition' => array('decision' => 'non_visit', 'version_no' => 1));

$cases = array(
    'belum_enroll_flag_mati' => array($closedSummary, array('disposition' => null), null),
    'belum_enroll_flag_hidup' => array($summary, array('disposition' => null), 'WAITING_DOCTOR_DISPOSITION'),
    'tanpa_nakes' => array($summary, array('performer' => null), 'WAITING_PERFORMER_ASSIGNMENT'),
    'nakes_selesai_tanpa_hasil' => array($summary, array('performer' => array('status' => 'selesai')), 'WAITING_PERFORMER_ASSIGNMENT'),
    'kunjungan_belum_mulai' => array($summary, array(), 'VISIT_NOT_STARTED'),
    'kunjungan_perjalanan' => array($summary, array('request' => array('request_id' => 501, 'request_status' => 'Accepted', 'consultation_mode' => 'visit', 'visit_status' => 'en_route')), 'VISIT_EN_ROUTE'),
    'kunjungan_tiba' => array($summary, array('request' => array('request_id' => 501, 'request_status' => 'Accepted', 'consultation_mode' => 'visit', 'visit_status' => 'arrived')), 'VISIT_ARRIVED'),
    'kunjungan_pemeriksaan' => array($summary, array('request' => array('request_id' => 501, 'request_status' => 'Accepted', 'consultation_mode' => 'visit', 'visit_status' => 'in_service')), 'VISIT_IN_SERVICE'),
    'kunjungan_selesai_tanpa_hasil' => array($summary, array('request' => array('request_id' => 501, 'request_status' => 'Accepted', 'consultation_mode' => 'visit', 'visit_status' => 'completed')), 'WAITING_VISIT_RESULT'),
    'hasil_belum_review' => array($summary, array('result' => $result), 'WAITING_DOCTOR_REVIEW'),
    'review_minta_perbaikan' => array($summary, array('result' => $result, 'review' => $correction), 'CORRECTION_REQUIRED'),
    'review_setuju_belum_final' => array($summary, array('result' => $result, 'review' => $approved), 'WAITING_CLINICAL_FINALIZATION'),
    'review_setuju_sudah_final' => array($summary, array('result' => $result, 'review' => $approved, 'record' => $finalized), 'READY_FOR_CLOSURE'),
    'non_visit_belum_final' => array($summary, $nonVisit, 'WAITING_CLINICAL_FINALIZATION'),
    'non_visit_sudah_final' => array($summary, array_merge($nonVisit, array('record' => $finalized)), 'READY_FOR_CLOSURE'),
    'request_completed' => array($summary, array('request' => array('request_id' => 501, 'request_status' => 'Completed', 'consultation_mode' => 'visit', 'visit_status' => 'completed')), 'COMPLETED'),
    'request_completed_lama_flag_mati' => array($closedSummary, array('request' => array('request_id' => 501, 'request_status' => 'Completed', 'consultation_mode' => 'visit', 'visit_status' => 'completed'), 'disposition' => null), null),
);

foreach ($cases as $label => $case) {
    $actual = $case[0]->stateFrom(parity_summary($case[1]));
    parity_check($actual === $case[2], $label . ' => ' . var_export($case[2], true) . ' (dapat ' . var_export($actual, true) . ')');
}

$steps = $summary->steps(parity_summary(), 'VISIT_NOT_STARTED');
parity_check(count($steps) === 7, 'tujuh_langkah_thread');
parity_check($steps[0]['state'] === 'done' && $steps[2]['state'] === 'current', 'langkah_sebelum_dan_saat_ini');
$nonVisitSteps = $summary->steps(parity_summary($nonVisit), 'WAITING_CLINICAL_FINALIZATION');
parity_check($nonVisitSteps[1]['state'] === 'skipped' && $nonVisitSteps[3]['state'] === 'skipped', 'langkah_kunjungan_dilewati_non_visit');
parity_check($nonVisitSteps[5]['state'] === 'current', 'non_visit_berhenti_di_rekam_medis');
$optionalDisposition = array_merge($nonVisit, array('disposition' => null));
$optionalSteps = $summary->steps(parity_summary($optionalDisposition), 'WAITING_DOCTOR_DISPOSITION');
parity_check($optionalSteps[0]['state'] === 'skipped' && $optionalSteps[0]['meta'] === 'Dilewati', 'disposisi_opsional_dilewati');
$correctionSteps = $summary->steps(parity_summary(array('result' => $result, 'review' => $correction)), 'CORRECTION_REQUIRED');
parity_check($correctionSteps[3]['state'] === 'current' && $correctionSteps[4]['state'] === 'blocked', 'perbaikan_kembali_ke_hasil');

$map = $summary->map(array(
    501 => parity_summary(),
    502 => parity_summary(array('request' => array('request_id' => 502, 'request_status' => 'Completed', 'consultation_mode' => 'non_visit', 'visit_status' => ''), 'disposition' => array('decision' => 'non_visit'), 'closure' => array('closed_at' => date('Y-m-d H:i:s')))),
    503 => parity_summary(array('disposition' => null)),
));
parity_check(count($map) === 3 && $map[502]['state'] === 'COMPLETED', 'map_menyimpan_semua_state');
$counts = $summary->actionCounts($map);
parity_check($counts['needs_action'] === 2 && $counts['completed'] === 1 && $counts['completed_today'] === 1, 'hitungan_aksi_dan_selesai');
$doctorCounts = $summary->actionCounts($map, 'responsible_doctor');
$performerCounts = $summary->actionCounts($map, 'performer');
parity_check($doctorCounts['needs_action'] === 1, 'hitungan_aksi_dokter');
parity_check($performerCounts['needs_action'] === 1, 'hitungan_aksi_nakes');

$surfaceUi = $summary->forSurface(array(
    'request' => parity_summary()['request'],
    'disposition_summary' => parity_summary()['disposition'],
    'performer' => parity_summary()['performer'],
));
parity_check($surfaceUi['state'] === 'VISIT_NOT_STARTED' && count($surfaceUi['steps']) === 7, 'for_surface_memakai_alias_kolom');
parity_check($surfaceUi['step_index'] === 3, 'for_surface_indeks_langkah');
$unmanagedUi = $closedSummary->forSurface(array('request' => parity_summary()['request'], 'disposition_summary' => null));
parity_check($unmanagedUi['state'] === null && $unmanagedUi['step_index'] === 0 && $unmanagedUi['steps'][0]['state'] === 'pending', 'surface_tidak_terkelola_tanpa_langkah_aktif');

$presenter = new Visit_workflow_presenter();
$states = array('WAITING_DOCTOR_DISPOSITION', 'WAITING_PERFORMER_ASSIGNMENT', 'VISIT_NOT_STARTED', 'VISIT_EN_ROUTE', 'VISIT_ARRIVED', 'VISIT_IN_SERVICE', 'WAITING_VISIT_RESULT', 'WAITING_DOCTOR_REVIEW', 'CORRECTION_REQUIRED', 'WAITING_CLINICAL_FINALIZATION', 'READY_FOR_CLOSURE', 'COMPLETED');
$actors = array('warga', 'performer', 'responsible_doctor', 'command_center');
foreach ($states as $state) {
    foreach ($actors as $actor) {
        $ui = $presenter->present(array('request_id' => 501, 'state' => $state), $actor);
        parity_check($ui['label'] !== $state && $ui['next_action'] !== '' && $ui['next_action_url'] !== '' && $ui['step_key'] !== '', 'presenter_lengkap_' . $state . '_' . $actor);
    }
}
$wargaCorrection = $presenter->present(array('request_id' => 501, 'state' => 'CORRECTION_REQUIRED'), 'warga', array('correction_reason' => 'Rahasia'));
parity_check($wargaCorrection['state'] === 'WAITING_DOCTOR_REVIEW' && $wargaCorrection['correction_reason'] === null, 'warga_tidak_melihat_detail_perbaikan');
$optionalDoctor = $presenter->present(array('request_id' => 501, 'state' => 'WAITING_DOCTOR_DISPOSITION'), 'responsible_doctor', array('consultation_mode' => 'non_visit'));
parity_check($optionalDoctor['label'] === 'Lanjutkan konsultasi'
    && $optionalDoctor['next_action'] === 'Selesaikan konsultasi'
    && $optionalDoctor['next_action_url'] === '#form_konsul_nakes', 'dokter_melanjutkan_tanpa_disposisi');
$doctorCorrection = $presenter->present(array('request_id' => 501, 'state' => 'CORRECTION_REQUIRED'), 'responsible_doctor', array('correction_reason' => 'Lengkapi hasil'));
parity_check($doctorCorrection['label'] === 'Menunggu perbaikan Nakes' && $doctorCorrection['correction_reason'] === 'Lengkapi hasil', 'dokter_melihat_alasan_perbaikan');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

