<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
function html_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function base_url($path = '') { return 'https://example.test/' . ltrim((string) $path, '/'); }
function site_url($path = '') { return 'https://example.test/' . ltrim((string) $path, '/'); }
function doclinc_ui_asset($name) { return ''; }
function formatComplaintText($text) { return '<p>' . html_escape($text) . '</p>'; }
class ClinicalRenderLoader {
    public function view($path, $vars = array()) {
        extract($vars, EXTR_SKIP);
        include APPPATH . 'modules/konsultasi_nakes/views/' . $path . '.php';
    }
}
class ClinicalRenderView {
    public $load;
    public function __construct() { $this->load = new ClinicalRenderLoader(); }
    public function render($path, $vars) {
        extract($vars, EXTR_SKIP);
        ob_start();
        include APPPATH . 'modules/konsultasi_nakes/views/partials/' . $path . '.php';
        return ob_get_clean();
    }
    public function renderHome($path, $vars) {
        extract($vars, EXTR_SKIP);
        ob_start();
        include APPPATH . 'modules/home_nakes/views/partials/' . $path . '.php';
        return ob_get_clean();
    }
}
$view = new ClinicalRenderView();
$clinical_ui = array(
    'state' => 'WAITING_DOCTOR_REVIEW',
    'label' => 'Tinjau hasil kunjungan',
    'tone' => 'warning',
    'next_action' => 'Tinjau hasil kunjungan',
    'next_action_url' => '#review-hasil',
    'steps' => array(
        array('key' => 'disposition', 'label' => 'Disposisi', 'state' => 'done', 'meta' => 'Kunjungan'),
        array('key' => 'performer', 'label' => 'Nakes', 'state' => 'done', 'meta' => 'Nakes Satu'),
        array('key' => 'visit', 'label' => 'Kunjungan', 'state' => 'done', 'meta' => 'Selesai'),
        array('key' => 'result', 'label' => 'Hasil', 'state' => 'done', 'meta' => 'Versi 1'),
        array('key' => 'review', 'label' => 'Review', 'state' => 'current', 'meta' => 'Menunggu dokter'),
        array('key' => 'record', 'label' => 'Rekam medis', 'state' => 'pending', 'meta' => ''),
        array('key' => 'closure', 'label' => 'Penutupan', 'state' => 'pending', 'meta' => ''),
    ),
);
$shared = array(
    'clinical_ui' => $clinical_ui,
    'monitor_hide_form' => false,
    'queue_label' => 'A-01',
    'return_url' => '#kembali',
    'nama_pasien' => 'Pasien Uji',
    'umur' => 31,
    'kriteria' => 'Kunjungan Nakes',
    'care_team_workflow_enabled' => true,
    'consultation_next_action' => 'Tinjau hasil kunjungan',
    'consultation_role_label' => 'Dokter penanggung jawab',
    'is_visit_performer' => false,
    'can_open_patient_chat' => true,
    'auto_visit_location' => false,
    'request_id' => 501,
    'keluhan_pasien' => 'Pusing sejak tiga hari',
);
$header = $view->render('clinical_workspace_header_v', $shared);
$context = $view->render('clinical_context_v', $shared);
$dock = $view->render('clinical_action_dock_v', $shared);
$draft = (object) array(
    'visit_result_id' => 9001,
    'draft_revision' => 2,
    'updated_at' => '2026-10-19 09:15:00',
    'observation_summary' => 'TD 150/90',
    'anamnesis' => 'Pusing tiga hari',
    'therapy' => 'Amlodipine 5 mg',
    'performer_notes' => 'Kontrol 3 hari',
    'findings' => array(array('label' => 'Tekanan darah', 'value' => '150/90')),
    'actions' => array(array('label' => 'Edukasi', 'value' => 'Diet rendah garam')),
);
$vitals = (object) array('systolic' => 150, 'diastolic' => 90, 'pulse' => 88, 'respiratory_rate' => 18, 'temperature_c' => 36.6, 'oxygen_saturation' => 98);
$resultHtml = $view->render('clinical_result_v', array_merge($shared, array(
    'visit_clinical_workflow_enabled' => true,
    'visit_workflow_enrolled' => true,
    'is_visit_performer' => true,
    'visit_status' => 'arrived',
    'latest_measurement_id' => 77,
    'latest_vital_signs' => $vitals,
    'visit_result_draft' => $draft,
    'visit_result_submitted' => null,
    'workflow_actions' => array('submit_result' => true, 'correct_result' => true),
    'visit_workflow' => array('review_summary' => array('correction_reason' => 'Lengkapi temuan')),
)));
$submitted = (object) array(
    'visit_result_id' => 9002,
    'version_no' => 1,
    'submitted_at' => '2026-10-19 09:30:00',
    'observation_summary' => 'TD 150/90',
    'anamnesis' => 'Pusing tiga hari',
    'therapy' => 'Amlodipine 5 mg',
    'performer_notes' => 'Kontrol 3 hari',
    'findings' => array(array('label' => 'Tekanan darah', 'value' => '150/90')),
    'actions' => array(array('label' => 'Edukasi', 'value' => 'Diet rendah garam')),
);
$reviewHtml = $view->render('clinical_review_v', array_merge($shared, array(
    'visit_clinical_workflow_enabled' => true,
    'visit_workflow_enrolled' => true,
    'is_responsible_doctor' => true,
    'latest_vital_signs' => $vitals,
    'visit_result_submitted' => $submitted,
    'workflow_actions' => array('review' => true),
    'visit_workflow' => array(),
    'review_history' => array(array(
        'decision' => 'correction_required',
        'correction_reason' => 'Lengkapi temuan',
        'reviewed_at' => '2026-10-19 09:45:00',
        'version_no' => 1,
        'reviewer_name' => 'dr. Uji',
    )),
)));
$record = (object) array(
    'record_id' => 7001,
    'diagnosis' => 'Hipertensi',
    'treatment' => 'Amlodipine 5 mg',
    'recommendations' => 'Kontrol 3 hari',
    'anamnesis' => 'Pusing tiga hari',
    'clinical_finalized_at' => null,
    'clinical_finalized_by_user_id' => null,
);
$closureBase = array_merge($shared, array(
    'visit_clinical_workflow_enabled' => true,
    'visit_workflow_enrolled' => true,
    'is_responsible_doctor' => true,
    'visit_disposition' => (object) array('decision' => 'visit'),
    'visit_result_submitted' => $submitted,
    'clinical_record' => $record,
    'record_effective_values' => array('diagnosis' => 'Hipertensi', 'treatment' => 'Amlodipine 5 mg', 'recommendations' => 'Kontrol 3 hari', 'anamnesis' => 'Pusing tiga hari'),
    'visit_workflow' => array('state' => 'READY_FOR_CLOSURE', 'review_summary' => array('decision' => 'approved')),
));
$closureDraftHtml = $view->render('clinical_closure_v', array_merge($closureBase, array(
    'workflow_actions' => array('record' => true, 'finalize' => true),
)));
$finalRecord = clone $record;
$finalRecord->clinical_finalized_at = '2026-10-19 10:00:00';
$finalRecord->clinical_finalized_by_user_id = 1;
$closureFinalHtml = $view->render('clinical_closure_v', array_merge($closureBase, array(
    'clinical_record' => $finalRecord,
    'clinical_record_finalized_by_name' => 'dr. Uji',
    'workflow_actions' => array('amend' => true, 'close' => true),
    'amendment_history' => array(array(
        'amendment_id' => 1, 'sequence_no' => 1, 'reason' => 'Koreksi dosis',
        'created_at' => '2026-10-19 10:15:00', 'created_by_name' => 'dr. Uji',
        'field_key' => 'treatment', 'previous_value' => 'Amlodipine 5 mg', 'corrected_value' => 'Amlodipine 10 mg',
    )),
)));
$html = $header . $context . $dock . $resultHtml . $reviewHtml . $closureDraftHtml . $closureFinalHtml;
$commandHtml = $view->renderHome('puskesmas_command_deck_v', array(
    'nakes_pending_count' => 9,
    'nakes_active_count' => 3,
    'nakes_completed_count' => 12,
    'clinical_action_counts' => array('needs_action' => 4, 'waiting_review' => 2, 'ready_to_close' => 1),
    'puskesmas_staff_list' => array_fill(0, 8, (object) array('nama' => 'Staf')),
    'nakes_team_duty_counts' => array('on_duty' => 3, 'idle' => 5),
    'puskesmas_operation_exceptions' => array('pending_requests' => 9, 'accepted_without_pic' => 2, 'clinical_stalled' => 1, 'staff_data_attention' => 0),
    'puskesmas_operations_enabled' => true,
));
$commandEmptyHtml = $view->renderHome('puskesmas_command_deck_v', array(
    'nakes_pending_count' => 0,
    'nakes_active_count' => 0,
    'nakes_completed_count' => 0,
    'clinical_action_counts' => array('needs_action' => 0, 'waiting_review' => 0, 'ready_to_close' => 0),
    'puskesmas_staff_list' => array(),
    'nakes_team_duty_counts' => array('on_duty' => 0, 'idle' => 0),
    'puskesmas_operation_exceptions' => array(),
    'puskesmas_operations_enabled' => false,
));
$commandHtml .= $commandEmptyHtml;
$html .= $commandHtml;
$checks = array(
    'skip link' => strpos($html, 'cw-skip-link') !== false,
    'state chip' => strpos($html, 'Tinjau hasil kunjungan') !== false,
    'thread' => strpos($html, 'aria-current="step"') !== false,
    'context' => strpos($html, 'Keluhan Pasien') !== false,
    'details' => strpos($html, 'cw-context-details') !== false,
    'dock' => strpos($html, '#review-hasil') !== false,
    'repeater' => strpos($resultHtml, 'data-cw-repeater') !== false && strpos($resultHtml, 'data-result-list="findings"') !== false,
    'draft status' => strpos($resultHtml, 'Draf revisi 2') !== false,
    'correction' => strpos($resultHtml, 'Lengkapi temuan') !== false,
    'vital link' => strpos($resultHtml, '#vital-signs') !== false,
    'review radios' => strpos($reviewHtml, 'data-review-decision') !== false && strpos($reviewHtml, 'Minta perbaikan') !== false,
    'review history' => strpos($reviewHtml, 'Riwayat review') !== false,
    'review vitals' => strpos($reviewHtml, 'Tanda vital') !== false && strpos($reviewHtml, 'dikirim 19 Oct 2026') !== false,
    'closure copy' => strpos($closureDraftHtml, 'data-cw-copy-result') !== false,
    'closure checklist' => strpos($closureDraftHtml, 'Checklist finalisasi') !== false,
    'closure finalize confirm' => strpos($closureDraftHtml, 'data-cw-confirm-title="Finalisasi rekam medis?"') !== false,
    'closure signed' => strpos($closureFinalHtml, 'oleh dr. Uji') !== false,
    'closure amend history' => strpos($closureFinalHtml, 'Riwayat amendemen') !== false && strpos($closureFinalHtml, 'Amlodipine 10 mg') !== false,
    'closure close confirm' => strpos($closureFinalHtml, 'data-cw-confirm-title="Tutup konsultasi kunjungan?"') !== false,
    'command deck' => strpos($commandHtml, 'cc-deck') !== false && strpos($commandHtml, '09') !== false,
    'command attention' => strpos($commandHtml, 'Perlu perhatian') !== false && strpos($commandHtml, 'Kunjungan tanpa penanggung jawab') !== false,
    'command tally' => strpos($commandHtml, 'cc-tally') !== false && strpos($commandHtml, 'Perlu tindakan') !== false && strpos($commandHtml, 'Selesai') !== false,
    'command operations link' => strpos($commandHtml, 'puskesmas/operations') !== false,
    'command empty queue' => strpos($commandEmptyHtml, 'cc-tally') !== false && strpos($commandEmptyHtml, 'tidak ada') !== false,
    'command empty attention' => strpos($commandEmptyHtml, 'Tidak ada yang tertahan') !== false,
);
$failed = 0;
foreach ($checks as $label => $ok) { echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n"; if (!$ok) { $failed++; } }
exit($failed === 0 ? 0 : 1);
