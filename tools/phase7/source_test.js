'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../..');
let passed = 0;
let failed = 0;

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function expect(condition, name) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${name}\n`);
    return;
  }
  failed += 1;
  process.stdout.write(`FAIL ${name}\n`);
}

const home = read('application/modules/home_nakes/views/home_nakes_v.php');
const history = read('application/modules/home_nakes/views/partials/nakes_history_v.php');
const activeTask = read('application/modules/home_nakes/views/partials/nakes_active_task_card_v.php');
const consultationController = read('application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
const consultation = read('application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');
const wargaHomeModel = read('application/modules/home/models/Home_m.php');
const wargaHomeView = read('application/modules/home/views/home_v.php');
const wargaVisitProgress = read('application/modules/home/views/partials/warga_visit_progress_v.php');
const chatController = read('application/modules/chat/controllers/Chat.php');
const chatModel = read('application/modules/chat/models/Chat_m.php');
const chatView = read('application/modules/chat/views/thread_v.php');
const locationClient = read('assets/js/doclinc-active-visit-location.js');
const chatParticipant = read('assets/js/doclinc-chat-participant.js');
const formGuard = read('assets/js/doclinc-clinical-form-guard.js');

expect(history.includes('$auto_location_enabled = $history_is_performer')
  && history.includes('(int) $x->request_id === $primary_active_request_id')
  && history.includes("array('en_route', 'arrived', 'in_service')"), 'auto_location_is_current_active_visit_performer_only');
expect(home.includes('DoclincActiveVisitLocation.create')
  && consultation.includes('DoclincActiveVisitLocation.create')
  && chatView.includes('DoclincActiveVisitLocation.create'), 'auto_location_reaches_all_personal_work_surfaces');
expect(consultationController.includes("$x['request_status']")
  && consultationController.includes("$x['visit_status']")
  && consultationController.includes("$x['is_visit_performer']"), 'location_eligibility_is_server_derived');
expect(!/Lacak Kunjungan/i.test(home + history + activeTask + consultation + chatView), 'manual_tracking_cta_is_absent');
expect(!/fake.?gps|trust.?score|device.?fingerprint|attestation/i.test(locationClient), 'phase9_location_integrity_is_not_added');

expect(chatModel.includes("'name' => 'Tenaga kesehatan'")
  && chatModel.includes("doclinc_dokter_identity_context($user_id, true)")
  && chatModel.includes("['account_type'] ?? '') !== 'personal'"), 'chat_identity_is_server_resolved_personal_identity');
expect(chatModel.includes("where_in('sender_user_id', array_keys($candidates))")
  && chatModel.includes("group_by('sender_user_id')")
  && chatModel.includes('count($senders) > 1'), 'chat_identity_uses_actual_conversation_context');
expect(chatView.includes('request->chat_clinician_name')
  && !chatView.includes('request->responsible_doctor_name) && $request->responsible_doctor_name'), 'chat_heading_does_not_permanently_prefer_responsible_doctor');
expect(chatController.includes("$response['participant'] = $this->Chat_m->get_chat_participant_payload($request_id)")
  && chatView.includes('chatParticipant.update(data.participant)')
  && chatParticipant.includes('fallbackName'), 'chat_polling_updates_server_derived_participant_context');
expect(chatParticipant.includes('createPoller')
  && chatParticipant.includes('sequence !== latestIssuedSequence')
  && chatView.includes('chatMessagePoller.poll()'), 'chat_polling_uses_single_flight_monotonic_response_guard');
expect(chatModel.includes("config->item('care_team_workflow_enabled') === true")
  && wargaHomeModel.includes("config->item('care_team_workflow_enabled') === true"), 'installed_schema_does_not_enable_care_team_identity');

expect(wargaHomeView.includes("'is_visit' => $is_visit")
  && wargaVisitProgress.includes("(string) $request_status === 'Accepted'")
  && wargaVisitProgress.includes('!empty($is_visit) && !empty($visit_location_available)')
  && wargaVisitProgress.includes('Buka chat'), 'warga_visit_render_is_canonical_and_active_only');

expect(activeTask.includes('data-primary-next-action')
  && history.includes('data-next-action-role')
  && consultation.includes('data-clinical-next-action'), 'nakes_next_action_hierarchy_is_present');
expect(history.includes("$history_is_responsible ? 'responsible_doctor' : 'visit_performer'")
  && history.includes("if (!$visit_monitor_only)"), 'next_actions_follow_server_authorization');
expect(!history.includes('data-visit-status="en_route" <?= $visit_next_status[$visit_status]')
  && history.includes('$next_visit_status'), 'visit_workspace_renders_only_current_next_transition');

expect(consultation.includes('clinicalFormGuard.beginSubmission()')
  && consultation.includes('clinicalFormGuard.submissionSucceeded()')
  && consultation.includes('clinicalFormGuard.submissionFailed()'), 'clinical_save_integrates_dirty_and_inflight_guard');
expect(formGuard.includes("addEventListener('beforeunload'")
  && formGuard.includes('event.returnValue ='), 'beforeunload_protection_is_installed');
expect(consultation.includes('timeout: 30000')
  && consultation.includes('restoreCompletionButton()'), 'failed_save_restores_controls_and_keeps_dom_values');
expect(!/localStorage|sessionStorage|indexedDB|document\.cookie|location\.search/i.test(formGuard + consultation), 'clinical_data_is_not_persisted_in_browser_storage_or_url');
expect(locationClient.includes("addEventListener('pageshow'")
  && locationClient.includes('event.persisted === true')
  && locationClient.includes('expectedGeneration === generation')
  && home.includes("eligibilityUrl: <?= json_encode(base_url('home_nakes/visit_location'))")
  && consultation.includes("eligibilityUrl: <?= json_encode(base_url('home_nakes/visit_location'))")
  && chatView.includes("eligibilityUrl: <?= json_encode(base_url('home_nakes/visit_location'))"), 'location_bfcache_resume_revalidates_server_eligibility_and_guards_async_results');
expect(home.includes('DoclincActiveVisitLocation.createManager')
  && home.includes('revalidateOnStart: true')
  && locationClient.includes('expectedGeneration !== undefined')
  && locationClient.includes('expectedGeneration')
  && home.includes('ns.stopNakesVisitTracking(requestId, trackingGeneration)')
  && home.includes("$(window).on('pagehide'")
  && !home.includes('navigator.geolocation.watchPosition')
  && !home.includes('postVisitLocation')
  && !home.includes('saveLocationToFirebase'), 'dashboard_uses_single_guarded_location_lifecycle');

process.stdout.write(`PHASE7_SOURCE_PASS=${passed}\n`);
process.stdout.write(`PHASE7_SOURCE_FAIL=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
