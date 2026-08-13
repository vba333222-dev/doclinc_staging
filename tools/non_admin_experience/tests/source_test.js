'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
let passed = 0;
let failed = 0;
function expect(condition, label) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${label}\n`);
  } else {
    failed += 1;
    process.stderr.write(`FAIL ${label}\n`);
  }
}

const prerequisites = read('application/libraries/Role_prerequisite_service.php');
const nakesController = read('application/modules/home_nakes/controllers/Home_nakes.php');
const nakesModel = read('application/modules/home_nakes/models/Home_nakes_m.php');
const nakesShell = read('application/modules/home_nakes/views/home_nakes_v.php');
const nakesDashboard = read('application/modules/home_nakes/views/partials/nakes_dashboard_v.php');
const nakesProfile = read('application/modules/home_nakes/views/partials/nakes_profile_v.php');
const nakesAppbar = read('application/modules/home_nakes/views/partials/nakes_appbar_v.php');
const completionView = read('application/views/profile_completion_v.php');
const presence = read('assets/js/doclinc-nakes-presence.js');
const activeVisitLocation = read('assets/js/doclinc-active-visit-location.js');
const authz = read('application/helpers/request_authz_helper.php');
const chatController = read('application/modules/chat/controllers/Chat.php');
const chatModel = read('application/modules/chat/models/Chat_m.php');
const chatView = read('application/modules/chat/views/thread_v.php');
const nakesHistory = read('application/modules/home_nakes/views/partials/nakes_history_v.php');
const homeView = read('application/modules/home/views/home_v.php');
const homeModel = read('application/modules/home/models/Home_m.php');
const homeController = read('application/modules/home/controllers/Home.php');
const style = read('assets/css/style.css');
const notifications = read('assets/js/doclinc-notifications.js');
const nakesConsultation = read('application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
const nakesConsultationView = read('application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');

expect(prerequisites.includes('facility_requirements($identity, $missing, $labels, $schema_gaps, false)')
  && prerequisites.includes('Command-center accounts represent a health facility')
  && !nakesController.match(/\$account_type === 'command_center'[\s\S]{0,350}'nama'/), 'puskesmas_profile_uses_facility_not_personal_identity');
expect(nakesProfile.includes('Identitas dan kontak unit') && nakesProfile.includes('Kode Puskesmas')
  && nakesProfile.includes('Alamat layanan'), 'puskesmas_profile_shows_natural_facility_fields');
expect(nakesShell.includes('Nama, alamat, dan identitas unit Puskesmas dikelola')
  && nakesShell.includes("if (!empty($nakes_is_personal))")
  && completionView.includes('Akun Puskesmas tidak memerlukan foto pribadi'), 'puskesmas_editor_excludes_personal_demographics_and_photo');
expect(!nakesDashboard.includes("'section_title' => 'Status Nakes'")
  && (nakesDashboard.match(/'section_title' => 'Staf Puskesmas'/g) || []).length === 1
  && nakesDashboard.includes('data-presence-roster="true"'), 'staff_and_presence_are_one_dashboard_roster');
expect(nakesModel.includes('users.foto AS profile_photo')
  && nakesDashboard.includes('data-presence-user-id')
  && presence.includes('renderRoster'), 'staff_roster_has_photo_and_online_state');
expect(nakesAppbar.includes("'avatar_photo' => !empty($nakes_is_personal) ? $nakes_photo : ''")
  && homeView.includes('doclinc_profile_image_src((int) $this->session->userdata')
  && chatModel.includes("'sender_photo_url' => base_url('profile/photo/'")
  && chatView.includes('chat-message-avatar'), 'personal_photos_render_on_dashboards_and_chat');
expect(style.includes('.dl-hero .history-empty-text') && style.includes('color: #ffffff'), 'patient_active_consultation_copy_has_high_contrast');
expect(homeController.includes("request_status !== 'Pending'") && homeModel.includes('FOR UPDATE')
  && homeModel.includes("->where('user_id', $user_id)"), 'warga_pending_edit_revalidates_owner_under_lock');
expect(homeModel.includes('responsible_doctor_user_id') && homeModel.includes('visit_performer_user_id')
  && homeModel.includes('request_responsible_doctor_assignments'), 'warga_edit_closes_after_care_team_assignment');
expect(homeModel.includes("return 'Menunggu Puskesmas'") && homeModel.includes("return 'Konsultasi selesai'"), 'warga_status_uses_natural_labels');
expect(homeView.includes('Dokter penanggung jawab:') && homeView.includes('Petugas kunjungan:'), 'warga_history_uses_care_team_names');
expect(!nakesAppbar.includes('Akun personal') && !nakesAppbar.includes('Akun Personal'), 'nakes_header_has_no_account_classification');
expect(!nakesHistory.includes('Lacak kunjungan') && !nakesHistory.includes('start-nakes-visit-tracking'), 'manual_visit_tracking_action_absent');
expect(nakesShell.includes('DoclincActiveVisitLocation.createManager')
  && nakesShell.includes('revalidateOnStart: true')
  && activeVisitLocation.includes("permissions.query({ name: 'geolocation' })")
  && activeVisitLocation.includes("permission.state === 'granted'"), 'automatic_location_respects_existing_browser_permission');
expect(chatModel.includes('chat_clinician_name')
  && chatModel.includes("doclinc_dokter_identity_context($user_id, true)")
  && chatView.includes('request->chat_clinician_name'), 'warga_chat_uses_personal_clinician_name');
expect(authz.includes("identity['account_type'] !== 'personal'")
  && chatController.includes('doclinc_chat_actor_is_command_center()')
  && chatController.includes('doclinc_chat_actor_is_command_center($user_id)')
  && chatController.includes("'safe_error_code' => $read_only ? 'chat_read_only'")
  && !chatView.includes('Akun Puskesmas hanya dapat memantau percakapan')
  && (nakesHistory.match(/if \(!\$nakes_is_command_center\)/g) || []).length >= 2
  && nakesConsultation.includes("account_type'] ?? '') === 'personal'")
  && nakesConsultationView.includes('if (!empty($can_open_patient_chat))'), 'puskesmas_patient_chat_is_hidden_and_denied');
expect(notifications.includes('this.soundEnabled = true')
  && !notifications.includes('doclincNotificationSoundToggle')
  && !notifications.includes('Aktifkan suara'), 'notification_sound_defaults_on_without_toggle_ui');

process.stdout.write(`NON_ADMIN_EXPERIENCE_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`NON_ADMIN_EXPERIENCE_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
