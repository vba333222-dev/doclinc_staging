'use strict';

/**
 * Fase 1 - memastikan panel disposisi benar-benar tersambung.
 *
 * Sebelum ini endpoint `clinical/disposition` ada tanpa satu pun pemanggil.
 * Test ini mengunci: panel dimuat di halaman konsultasi, formulir memuat field
 * yang diwajibkan service, kartu/histori memakai ringkasan disposisi dan
 * menyaring Nakes berdasarkan profesi wajib, serta tombol legacy disembunyikan
 * untuk permintaan yang sudah masuk alur klinis.
 */
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
let passed = 0;
let failed = 0;
function expect(condition, name) {
  if (condition) { passed += 1; process.stdout.write(`PASS ${name}\n`); }
  else { failed += 1; process.stdout.write(`FAIL ${name}\n`); }
}

const konsultasiView = read('application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');
const panel = read('application/modules/konsultasi_nakes/views/partials/clinical_disposition_v.php');
const form = read('application/modules/konsultasi_nakes/views/partials/clinical_disposition_form_v.php');
const summary = read('application/modules/home_nakes/views/partials/nakes_disposition_summary_v.php');
const taskCard = read('application/modules/home_nakes/views/partials/nakes_active_task_card_v.php');
const history = read('application/modules/home_nakes/views/partials/nakes_history_v.php');
const controller = read('application/modules/home_nakes/controllers/Home_nakes.php');
const konsultasiController = read('application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
const careService = read('application/libraries/Care_team_service.php');
const resultPanel = read('application/modules/konsultasi_nakes/views/partials/clinical_result_v.php');
const reviewPanel = read('application/modules/konsultasi_nakes/views/partials/clinical_review_v.php');
const closurePanel = read('application/modules/konsultasi_nakes/views/partials/clinical_closure_v.php');
const recordService = read('application/libraries/Clinical_record_service.php');
const resultService = read('application/libraries/Visit_result_service.php');
const workflowSurface = read('application/libraries/Clinical_workflow_surface.php');
const script = read('assets/js/doclinc-clinical-workflow.js');
const workspaceCss = read('assets/css/doclinc-clinical-workspace.css');
const workspaceScript = read('assets/js/doclinc-clinical-workspace.js');
const workflowSummary = read('application/libraries/Clinical_workflow_summary.php');
const homeModel = read('application/modules/home_nakes/models/Home_nakes_m.php');
const homeView = read('application/modules/home_nakes/views/home_nakes_v.php');
const historyView = read('application/modules/home_nakes/views/partials/nakes_history_v.php');
const appbarView = read('application/modules/home_nakes/views/partials/nakes_appbar_v.php');
const clinicalStats = read('application/modules/home_nakes/views/partials/clinical_stats_v.php');
const clinicalActions = read('application/modules/home_nakes/views/partials/clinical_action_list_v.php');
const clinicalChip = read('application/modules/home_nakes/views/partials/clinical_state_chip_v.php');
const clinicalThreadMini = read('application/modules/home_nakes/views/partials/clinical_thread_mini_v.php');
const amendmentModel = read('application/models/Clinical_amendment_m.php');
const amendmentService = read('application/libraries/Clinical_amendment_service.php');
const bottomNav = read('application/modules/home_nakes/views/partials/nakes_bottom_nav_v.php');
const workspaceHeader = read('application/modules/konsultasi_nakes/views/partials/clinical_workspace_header_v.php');
const workspaceContext = read('application/modules/konsultasi_nakes/views/partials/clinical_context_v.php');
const workspaceThread = read('application/modules/konsultasi_nakes/views/partials/clinical_thread_v.php');
const workspaceDock = read('application/modules/konsultasi_nakes/views/partials/clinical_action_dock_v.php');
const commandDeck = read('application/modules/home_nakes/views/partials/puskesmas_command_deck_v.php');
const commandCss = read('assets/css/doclinc-command-center.css');

expect(konsultasiView.includes("partials/clinical_disposition_v"), 'panel_disposisi_dimuat_di_halaman_konsultasi');
expect(konsultasiView.includes('doclinc-clinical-workflow.js'), 'skrip_klinis_dimuat');
expect(konsultasiView.includes('legacy_completion_available'), 'tombol_lama_digerbangi_status_enrolled');

expect(panel.includes("'clinical/disposition'"), 'panel_memanggil_endpoint_create');
expect(panel.includes("'clinical/disposition/revise'"), 'panel_memanggil_endpoint_revise');
expect(panel.includes('partials/clinical_disposition_form_v'), 'panel_memakai_formulir_bersama');
expect(panel.includes('visit_disposition_locked'), 'panel_menghormati_kunci_kunjungan');

['decision', 'urgency', 'required_profession', 'instructions', 'rationale', 'idempotency_key'].forEach(field => {
  expect(form.includes(`name="${field}"`), `form_memuat_${field}`);
});
expect(form.includes('name="staff_id"') && form.includes('data-disposition-staff'), 'form_memuat_pilihan_nakes_langsung');
expect(panel.includes('data-skip-disposition') && panel.includes('Opsional'), 'disposisi_opsional_dan_bisa_dilewati');
expect(controller.includes('assignVisitPerformer(') && controller.includes('disposition_selected_staff('), 'dokter_menugaskan_nakes_yang_dipilih');
expect(form.includes('data-clinical-endpoint'), 'form_membawa_endpoint');
expect(form.includes('data-clinical-idempotency'), 'form_membawa_kunci_idempotensi');

const cards = [taskCard, history];
expect(cards.every(source => source.includes('partials/nakes_disposition_summary_v')), 'kartu_dan_histori_menampilkan_ringkasan_disposisi');
expect(cards.every(source => source.includes('$primary_required_profession') || source.includes('$history_required_profession')), 'kartu_dan_histori_memfilter_profesi_wajib');
expect(cards.every(source => source.includes('Tetapkan disposisi')), 'kartu_dan_histori_menawarkan_langkah_disposisi');
expect(taskCard.includes('$primary_workflow_enabled'), 'kartu_aktif_memakai_flag_alur_klinis');
expect(history.includes('$history_workflow_enabled'), 'histori_memakai_flag_alur_klinis');

expect(controller.includes('notify_disposition('), 'controller_mengirim_notifikasi_disposisi');
expect(careService.includes("'responsible_doctor.assigned'") && careService.includes("'visit_performer.assigned'") && careService.includes("'service_mode.selected'"),
  'event_penunjukan_ditulis_service');
expect(controller.includes('get_active_dispositions_by_request_ids'), 'controller_memuat_peta_disposisi');

['visit_disposition', 'visit_clinical_workflow_enabled', 'staff_professions', 'legacy_completion_available'].forEach(key => {
  expect(konsultasiController.includes(`'${key}'`), `konsultasi_mengirim_${key}`);
});

expect(script.includes('data-clinical-endpoint'), 'skrip_membaca_endpoint_dari_form');
expect(script.includes('credentials: \'same-origin\''), 'skrip_mengirim_kredensial');
expect(script.includes('data-clinical-idempotency'), 'skrip_mengisi_kunci_idempotensi');
expect(script.includes('URLSearchParams'), 'skrip_mengirim_form_urlencoded');

// Fase 2: hasil kunjungan, review, rekam medis, dan penutupan.
expect(konsultasiView.includes("partials/clinical_result_v"), 'panel_hasil_dimuat');
expect(konsultasiView.includes("partials/clinical_review_v"), 'panel_review_dimuat');
expect(konsultasiView.includes("partials/clinical_closure_v"), 'panel_penutupan_dimuat');
expect(konsultasiView.includes('empty($visit_workflow_enrolled)'), 'form_lama_disembunyikan_untuk_enrolled');
expect(resultPanel.includes('visit_result_draft') && resultPanel.includes('visit_result_submit') && resultPanel.includes('visit_result_correction'),
  'panel_hasil_memanggil_semua_endpoint');
expect(resultPanel.includes('data-result-list=') && resultPanel.includes("'findings'") && resultPanel.includes("'actions'"),
  'panel_hasil_mengubah_baris_menjadi_temuan_tindakan');
expect(resultPanel.includes('data-cw-repeater') && resultPanel.includes('data-cw-result-label'), 'panel_hasil_memakai_repeater');
expect(resultPanel.includes('draft_status') && resultPanel.includes('result_correction_reason'), 'panel_hasil_menampilkan_draf_dan_alasan_koreksi');
expect(resultPanel.includes('#vital-signs') && resultPanel.includes('resultSubmitReason'), 'panel_hasil_menautkan_tanda_vital_dan_alasan_disabled');
expect(script.includes('[data-cw-result-list="'), 'skrip_hasil_membaca_repeater_terstruktur');
expect(script.includes('DoclincClinicalWorkspace.confirm') && script.includes('Kirim hasil kunjungan?'), 'kirim_hasil_memakai_konfirmasi');
expect(reviewPanel.includes("'clinical/review'") && reviewPanel.includes('name="visit_result_id"') && reviewPanel.includes('correction_reason'),
  'panel_review_memuat_keputusan_dan_alasan');
expect(reviewPanel.includes('data-review-decision') && reviewPanel.includes('cw-choice-grid'), 'review_memakai_kartu_keputusan');
expect(reviewPanel.includes('review_history') && reviewPanel.includes('Riwayat review'), 'review_menampilkan_riwayat');
expect(reviewPanel.includes('Tanda vital') && reviewPanel.includes('submitted_at'), 'review_menampilkan_vital_dan_waktu_kirim');
expect(script.includes('[data-review-decision]:checked'), 'skrip_review_membaca_radio_terpilih');
expect(closurePanel.includes("'clinical/record'") && closurePanel.includes("'clinical/finalize'") && closurePanel.includes("'clinical/amend'") && closurePanel.includes("'clinical/close'"),
  'panel_penutupan_memanggil_rekam_finalisasi_amend_close');
expect(closurePanel.includes('data-cw-copy-result') && closurePanel.includes('Checklist finalisasi'), 'penutupan_memuat_salinan_dan_checklist');
expect(closurePanel.includes('data-cw-confirm-title') && closurePanel.includes('Riwayat amendemen'), 'penutupan_memuat_konfirmasi_dan_riwayat');
expect(amendmentModel.includes('historyByRecord') && amendmentService.includes('function history'), 'amendemen_menyediakan_riwayat');
expect(konsultasiController.includes('amendment_history') && konsultasiController.includes('record_effective_values'), 'konsultasi_memuat_riwayat_amendemen');
expect(recordService.includes('medicalrecords') && recordService.includes('canReview') && recordService.includes('Clinical_workflow_requirements'),
  'service_rekam_medis_menulis_dan_memeriksa_akses');
expect(workflowSurface.includes('availableActions'), 'surface_menghitung_aksi_tersedia');
expect(careService.includes("'visit_performer.assigned'") && recordService.includes("'clinical_record.drafted'"),
  'audit_rekam_medis_dan_penugasan');

// Fase 3: penempatan awal dan watchdog tahap klinis.
const watchdog = read('application/libraries/Active_request_watchdog.php');
const dashboard = read('application/modules/home_nakes/views/partials/nakes_dashboard_v.php');
const operationsService = read('application/libraries/Puskesmas_operations_service.php');
const adminStaffController = read('admin_menu/application/modules/kelola_staff_puskesmas/controllers/Kelola_staff_puskesmas.php');
const placementModal = read('admin_menu/application/modules/kelola_staff_puskesmas/views/partials/staff_placement_modal_v.php');
expect(watchdog.includes('request.clinical_stall') && watchdog.includes('exceptionsFor'), 'watchdog_menandai_tahap_macet');
expect(controller.includes("load->library('Active_request_watchdog')") && controller.includes("'clinical_stalled'"),
  'dashboard_menjalankan_watchdog');
expect(dashboard.includes('data-puskesmas-clinical-stall'), 'dashboard_menampilkan_konsultasi_macet');
expect(operationsService.includes('Active_request_watchdog') && operationsService.includes('clinicalExceptions'),
  'papan_operasional_menggabungkan_pengecualian_klinis');
expect(adminStaffController.includes('function create_placement') && placementModal.includes('kelola_staff_puskesmas/create_placement'),
  'admin_membuat_penempatan_awal');
expect(placementModal.includes('kelola_staff_puskesmas/schedule_transfer') && placementModal.includes('_provisioning_csrf_token'),
  'modal_penempatan_mendukung_transfer_dan_csrf');

// Perbaikan P0 dan kontrak anamnesis/terapi Nakes.
expect(konsultasiController.indexOf("$x['latest_vital_signs']") !== -1
  && konsultasiController.indexOf("$x['latest_vital_signs']") < konsultasiController.indexOf("$x['latest_measurement_id']"),
  'tanda_vital_dimuat_sebelum_measurement_id');
expect(konsultasiView.indexOf('partials/clinical_context_v') !== -1
  && konsultasiView.indexOf('<div class="nakes-work-pane">') > konsultasiView.indexOf('partials/clinical_context_v'),
  'work_pane_dibuka_di_luar_kondisi');
expect(resultService.includes("'anamnesis'") && resultService.includes("'therapy'"), 'service_hasil_menyimpan_anamnesis_terapi');
expect(resultPanel.includes('name="anamnesis"') && resultPanel.includes('name="therapy"'), 'form_hasil_memuat_anamnesis_terapi');
expect(reviewPanel.includes('Anamnesis (Nakes)') && reviewPanel.includes('Terapi / tindakan (Nakes)'), 'review_menampilkan_catatan_nakes');
const requestRow = read('application/modules/home_nakes/views/partials/puskesmas_request_row_v.php');
const consultationDeskCss = read('assets/css/doclinc-consultation-desk.css');
expect(requestRow.includes('cq-request') && requestRow.includes('start-chat') && requestRow.includes('id="terimaKonsul'), 'request_row_memakai_kontrak_aksi_lama');
expect(homeView.includes('doclinc-consultation-desk.css') && homeView.includes('!empty($nakes_is_command_center)'), 'css_meja_konsultasi_dimuat_untuk_puskesmas');
expect(consultationDeskCss.includes('.cq-request') && consultationDeskCss.includes('.cq-case') && consultationDeskCss.includes('.cq-next-action'), 'meja_konsultasi_memuat_triage_dan_case_ledger');
expect(consultationDeskCss.includes('@media (max-width: 767.98px)') && !consultationDeskCss.includes('font-size: 11px'), 'meja_konsultasi_responsif_tanpa_label_11px');
expect(consultationDeskCss.includes('--cq-action: #2d7d56')
  && consultationDeskCss.includes('--cq-action-edge: #a9cabc')
  && consultationDeskCss.includes('border-radius: 9px !important')
  && consultationDeskCss.includes('#beranda .cc-action')
  && consultationDeskCss.includes('#riwayat_konsul .dl-history-primary-action'),
  'tombol_puskesmas_satu_konsep_dengan_warga');

// Fase 2-3: fondasi visual dan ringkasan workflow bersama.
expect(konsultasiView.includes('doclinc-clinical-workspace.css'), 'css_workspace_dimuat');
expect(konsultasiView.includes('dl-clinical-workspace'), 'root_workspace_ditandai');
expect(workspaceCss.includes('--cw-tap: 44px') && workspaceCss.includes('.cw-thread') && workspaceCss.includes('.cw-choice'), 'workspace_css_memuat_token_dan_komponen');
expect(workspaceScript.includes('DoclincClinicalWorkspace') && workspaceScript.includes('data-cw-confirm'), 'workspace_script_memuat_konfirmasi');
expect(workflowSummary.includes('function stateFrom') && workflowSummary.includes('function steps') && workflowSummary.includes('function actionCounts'), 'ringkasan_workflow_memuat_state_langkah_aksi');
expect(workflowSummary.includes('CORRECTION_REQUIRED') && workflowSummary.includes('READY_FOR_CLOSURE'), 'ringkasan_workflow_memuat_state_akhir');
expect(homeModel.includes('workflow_summaries_by_request_ids'), 'model_menyediakan_ringkasan_batch');
expect(controller.includes('clinical_action_counts'), 'controller_memakai_hitungan_aksi');
expect(controller.includes("load->library('visit_workflow_presenter')") && controller.includes('array_merge($clinical_entry, $clinical_presentation)'), 'controller_menyajikan_label_per_aktor');
expect(homeModel.includes('responsible_doctor_user_id') && homeModel.includes('visit_performer_user_id'), 'batch_memuat_aktor_request');
expect(workflowSurface.includes('workflow_ui'), 'surface_proyeksi_workflow_ui');

// Fase 4: dashboard, daftar tugas, history, dan navigasi dinamis.
expect(homeView.includes("' dl-clinical-workspace'") || homeView.includes('dl-clinical-workspace'), 'body_dashboard_memakai_scope_workspace');
expect(homeView.includes('doclinc-clinical-workspace.css'), 'css_workspace_dimuat_di_dashboard');
expect(clinicalStats.includes('Perlu tindakan') && clinicalStats.includes('completed_today'), 'stat_cards_klinis_role_aware');
expect(clinicalActions.includes('partials/clinical_state_chip_v') && clinicalActions.includes('next_action'), 'daftar_perlu_tindakan_memakai_state_dan_aksi');
expect(clinicalChip.includes('data-clinical-state') && clinicalChip.includes('cw-state'), 'state_chip_dari_map');
expect(clinicalThreadMini.includes('cw-thread--mini') && clinicalThreadMini.includes('aria-current'), 'thread_mini_di_kartu');
expect(historyView.includes('clinical_state_chip_v') && historyView.includes('clinical_thread_mini_v'), 'history_memakai_state_dan_thread');
expect(historyView.includes('$history_clinical_anchor'), 'history_cta_memakai_anchor_aksi');
expect(appbarView.includes('$appbar_initial_section') && appbarView.includes('data-nakes-nav'), 'appbar_memakai_aria_current_dinamis');
expect(homeView.includes('data-nakes-nav') && homeView.includes('desktopTarget.setAttribute'), 'navigasi_desktop_disinkronkan_saat_tab_berubah');

// Command center Puskesmas: ruang kendali yang menggantikan tiga kartu angka.
expect(dashboard.includes('puskesmas_command_deck_v') && dashboard.includes('!empty($nakes_is_command_center)'), 'dashboard_memuat_command_deck_hanya_untuk_puskesmas');
expect(homeView.includes('doclinc-command-center.css') && homeView.includes('!empty($nakes_is_command_center)'), 'css_command_center_dimuat_untuk_puskesmas');
expect(commandDeck.includes('cc-deck') && commandDeck.includes('aria-labelledby="ccDeckTitle"'), 'command_deck_memakai_landmark_dan_judul');
expect(commandDeck.includes('cc-tally') && commandDeck.includes('role="group"'), 'command_deck_memuat_ringkasan_angka_aksesibel');
expect(commandDeck.includes('cc-attention-list') && commandDeck.includes('puskesmas_operation_exceptions'), 'command_deck_memakai_pengecualian_operasional');
expect(commandDeck.includes('<time datetime='), 'command_deck_memakai_elemen_time');
expect(commandCss.includes('--cc-forest') && commandCss.includes('font-variant-numeric: tabular-nums'), 'command_css_memuat_token_dan_angka_tabular');
expect(commandCss.includes('grid-column: 1 / -1') && commandCss.includes('@media (max-width: 767.98px)'), 'command_css_membentang_dan_responsif');
expect(commandCss.includes('prefers-reduced-motion') && !commandCss.includes('font-size: 11px'), 'command_css_reduced_motion_tanpa_label_11px');
expect(commandCss.includes('.cc-action:focus-visible'), 'command_css_fokus_terlihat_pada_aksi_utama');
expect(!commandDeck.includes('cc-kicker') && !commandDeck.includes('cc-throughput-bar'), 'command_deck_tanpa_kicker_dan_hero_metrik');

// Fase 5: shell konsultasi dipecah menjadi header, context, thread, dan dock.
expect(konsultasiView.includes('clinical_workspace_header_v') && konsultasiView.includes('clinical_context_v'), 'shell_konsultasi_memakai_header_dan_context');
expect(konsultasiView.includes('clinical_action_dock_v') && konsultasiView.includes('id="main"'), 'shell_konsultasi_memakai_dock_dan_main');
expect(workspaceHeader.includes('cw-skip-link') && workspaceHeader.includes('clinical_thread_v') && workspaceHeader.includes('data-clinical-state'), 'header_klinis_memuat_skip_link_dan_thread');
expect(workspaceContext.includes('Ringkasan Pasien') && workspaceContext.includes('Keluhan Pasien') && workspaceContext.includes('nakes-context-rail'), 'context_memuat_ringkasan_dan_keluhan');
expect(workspaceContext.includes('cw-context-details'), 'context_bisa_dilipat_di_mobile');
expect(workspaceThread.includes('cw-thread') && workspaceThread.includes('aria-current'), 'thread_klinis_memuat_aria_current');
expect(workspaceDock.includes('cw-dock') && workspaceDock.includes("next_action_url"), 'dock_memakai_aksi_berikutnya');
expect(konsultasiView.includes('id="kunjungan"') && read('application/modules/konsultasi_nakes/views/partials/clinical_closure_v.php').includes('id="penutupan"'), 'anchor_aksi_tersedia_di_panel');

// Fase 9: aksesibilitas dan responsif.
expect(workspaceHeader.includes('href="#main"') && konsultasiView.includes('id="main"'), 'skip_link_dan_main_target');
expect(script.includes("setAttribute('aria-invalid', 'true')") && script.includes('node.focus()'), 'fokus_error_dan_aria_invalid');
expect(script.includes("setAttribute('aria-busy', 'true')") && script.includes("removeAttribute('aria-busy')"), 'status_sibuk_form');
expect(reviewPanel.includes('aria-describedby="reviewReasonHelp"') && resultPanel.includes('aria-describedby="resultSubmitReason"'), 'field_help_terhubung');
expect(bottomNav.includes('aria-current="page"') && !bottomNav.includes("aria-current=\"true\""), 'aria_current_bottom_nav_valid');
expect(homeView.includes("setAttribute('aria-current', 'page')"), 'aria_current_dinamis_memakai_page');
expect(workspaceCss.includes('prefers-reduced-motion') && workspaceCss.includes('overflow-x: clip'), 'reduced_motion_dan_overflow');
expect(!workspaceCss.includes('font-size: 11px'), 'tidak_ada_label_11px_di_workspace');

process.stdout.write(`CLINICAL_UI_WIRING_PASSED=${passed}\nCLINICAL_UI_WIRING_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
