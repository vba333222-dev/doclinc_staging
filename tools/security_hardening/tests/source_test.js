'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
let passed = 0;
let failed = 0;

function source(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function expect(condition, label) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${label}\n`);
    return;
  }
  failed += 1;
  process.stdout.write(`FAIL ${label}\n`);
}

function methodBody(contents, methodName) {
  const marker = `public function ${methodName}(`;
  const start = contents.indexOf(marker);
  if (start < 0) return '';
  const open = contents.indexOf('{', start);
  if (open < 0) return '';
  let depth = 0;
  for (let index = open; index < contents.length; index += 1) {
    if (contents[index] === '{') depth += 1;
    if (contents[index] === '}') {
      depth -= 1;
      if (depth === 0) return contents.slice(open + 1, index);
    }
  }
  return '';
}

const config = source('application/config/config.php');
const autoload = source('application/config/autoload.php');
const hooks = source('application/config/hooks.php');
const csrfSecurity = source('application/core/MY_Security.php');
const csrfHelper = source('application/helpers/csrf_bootstrap_helper.php');
const csrfClient = source('assets/js/doclinc-csrf.js');
const sameSiteGate = source('application/hooks/Cookie_samesite_gate.php');
const login = source('application/modules/login/controllers/Login.php');
const signup = source('application/modules/sign_up/controllers/Sign_up.php');
const signupView = source('application/modules/sign_up/views/sign_up_v.php');
const passwordPolicy = source('application/libraries/Password_strength_policy.php');
const sessionGate = source('application/hooks/Password_change_gate.php');
const gatePolicy = source('application/libraries/First_login_gate_policy.php');
const home = source('application/modules/home/controllers/Home.php');
const nakes = source('application/modules/home_nakes/controllers/Home_nakes.php');
const chat = source('application/modules/chat/controllers/Chat.php');
const chatModel = source('application/modules/chat/models/Chat_m.php');
const chatThread = source('application/modules/chat/views/thread_v.php');
const requestAuthz = source('application/helpers/request_authz_helper.php');
const chatStorage = source('application/libraries/Chat_attachment_storage.php');
const routes = source('application/config/routes.php');
const legacyChatUpload = source('application/modules/chat/controllers/upload.php');
const notifications = source('application/modules/notifikasi/controllers/Notifikasi.php');
const callModel = source('application/modules/chat/models/Call_session_m.php');
const nakesView = source('application/modules/home_nakes/views/home_nakes_v.php');
const homeLegacyNotification = source('application/modules/home/controllers/Notification.php');
const consultationLegacyNotification = source('application/modules/konsultasi/controllers/Notification.php');
const consultation = source('application/modules/konsultasi/controllers/Konsultasi.php');
const nakesConsultation = source('application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
const ciSession = source('system/libraries/Session/Session.php');
const ciFileSession = source('system/libraries/Session/drivers/Session_files_driver.php');

expect(config.includes("$config['sess_time_to_update'] = 300;")
  && config.includes("$config['sess_regenerate_destroy'] = FALSE;"), 'automatic_session_rotation_preserves_parallel_request_compatibility');
expect(ciSession.includes("$this->sess_regenerate((bool) config_item('sess_regenerate_destroy'))")
  && ciSession.includes('session_regenerate_id($destroy)')
  && ciFileSession.includes("unlink($this->_file_path.$session_id)"), 'ci3_file_session_regeneration_contract_verified');
expect(login.includes('sess_regenerate(TRUE)') && methodBody(login, 'logout').includes('sess_destroy()'), 'login_fixation_rotation_and_logout_destruction_preserved');
expect(config.includes("$config['cookie_httponly'] = TRUE;"), 'session_cookie_httponly');
expect(config.includes("getenv('DOCLINC_COOKIE_SECURE')") && config.includes("parse_url((string) $config['base_url'], PHP_URL_SCHEME)"), 'session_cookie_secure_https_default');
expect(config.includes("$config['csrf_protection'] = TRUE;"), 'csrf_global_protection_enabled');
expect(config.includes("$config['csrf_regenerate'] = FALSE;"), 'csrf_token_stable_for_concurrent_ajax');
expect(config.includes("$config['csrf_exclude_uris'] = array();"), 'csrf_has_no_route_exemptions');
expect(config.includes("$config['cookie_samesite'] = 'Lax';"), 'cookie_samesite_defaults_to_lax');
expect(autoload.includes("'csrf_bootstrap'"), 'csrf_bootstrap_helper_autoloaded');
expect(hooks.includes("$hook['post_controller'][]") && hooks.includes("'Cookie_samesite_gate'"), 'cookie_samesite_response_hook_registered');
expect(sameSiteGate.includes("header_remove('Set-Cookie')") && sameSiteGate.includes("'; SameSite=' . $policy"), 'session_and_application_cookies_receive_samesite');
expect(sameSiteGate.includes("$policy === 'None'") && sameSiteGate.includes("cookie_secure") && sameSiteGate.includes("$policy = 'Lax'"), 'samesite_none_requires_secure_cookie');
expect(csrfSecurity.includes("HTTP_X_CSRF_TOKEN") && csrfSecurity.includes("parent::csrf_verify()"), 'csrf_header_is_verified_by_framework_security');
expect(csrfSecurity.includes("'samesite' =>") && csrfSecurity.includes("cookie_samesite"), 'csrf_cookie_receives_samesite_before_controller');
expect(csrfSecurity.includes("'safe_error_code' => 'csrf_validation_failed'"), 'csrf_ajax_failure_has_stable_safe_code');
expect(csrfHelper.includes('get_csrf_token_name()') && csrfHelper.includes('get_csrf_hash()'), 'csrf_bootstrap_uses_framework_token');
expect(csrfClient.includes("headers.set('X-CSRF-TOKEN', token)") && csrfClient.includes('HTMLFormElement.prototype.submit'), 'csrf_client_covers_ajax_and_native_forms');

for (const viewPath of [
  'application/modules/login/views/login_v.php',
  'application/modules/login/views/change_password_v.php',
  'application/modules/sign_up/views/sign_up_v.php',
  'application/modules/home/views/home_v.php',
  'application/modules/home_nakes/views/home_nakes_v.php',
  'application/modules/konsultasi/views/konsultasi_v.php',
  'application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php',
  'application/modules/chat/views/thread_v.php',
]) {
  expect(source(viewPath).includes('doclinc_csrf_bootstrap_markup()'), `csrf_bootstrap_present_${path.basename(viewPath)}`);
}

expect(login.includes("$actor_is_allowed = $user && (string) ($user->status ?? '') === 'aktif';"), 'all_roles_require_active_status_at_login');
expect(!login.includes('$dokter_is_allowed'), 'doctor_only_status_gate_removed');

expect(sessionGate.includes("(string) $user->status !== 'aktif'"), 'active_session_revalidates_database_status');
expect(sessionGate.includes("(string) $user->role !== $session_role"), 'active_session_revalidates_database_role');
expect(sessionGate.includes("'safe_error_code' => $code"), 'session_gate_returns_safe_error_code');
expect(sessionGate.includes("$code === 'session_not_allowed' ? 401 : 403"), 'invalid_session_uses_401');

for (const [label, contents] of [
  ['home', homeLegacyNotification],
  ['consultation', consultationLegacyNotification],
]) {
  expect(['index', 'test', 'send', 'service_worker'].every((name) => methodBody(contents, name).includes('show_404();')), `${label}_legacy_notification_routes_are_tombstones`);
  expect(!contents.includes('Access-Control-Allow-Origin') && !contents.includes('Factory::class'), `${label}_legacy_notification_sender_removed`);
}
expect(methodBody(consultation, 'send').includes('show_404();'), 'consultation_token_sender_retired');
expect(!methodBody(consultation, 'send').includes("input->get('token'"), 'consultation_sender_accepts_no_device_token');
expect(!gatePolicy.includes("'notification' =>") && !gatePolicy.includes("array('save_konsultasi', 'send')"), 'first_login_policy_does_not_allow_retired_sender');

expect(methodBody(login, 'auth').includes("method(TRUE) !== 'POST'"), 'login_auth_post_only');
expect(methodBody(login, 'logout').includes("method(TRUE) !== 'POST'") && methodBody(login, 'logout').includes('show_404();'), 'logout_post_only');
expect(methodBody(signup, 'save_user').includes("method(TRUE) !== 'POST'"), 'signup_post_only');
expect(methodBody(signup, 'save_user').includes('Password_strength_policy') && signup.includes('required|min_length[8]|max_length[72]'), 'signup_uses_authoritative_password_policy');
expect(signupView.includes('minlength="8" maxlength="72"') && signupView.includes('huruf besar, huruf kecil, angka, dan karakter khusus'), 'signup_explains_password_contract');
expect(passwordPolicy.includes("preg_match('/[A-Z]/'") && passwordPolicy.includes("preg_match('/[a-z]/'") && passwordPolicy.includes("preg_match('/[0-9]/'") && passwordPolicy.includes("preg_match('/[^A-Za-z0-9\\s]/'"), 'password_complexity_is_server_enforced');
expect(methodBody(chat, 'messages').includes("method(TRUE) !== 'GET'"), 'chat_messages_get_only');
expect(methodBody(chat, 'send').includes("method(TRUE) !== 'POST'"), 'chat_send_post_only');
expect(requestAuthz.includes("account_type'] ?? '') !== 'personal'")
  && methodBody(chat, 'send').includes('chat_read_only')
  && chat.includes('private function authorize_upload_request') && (chat.match(/chat_read_only/g) || []).length >= 2
  && chatThread.includes('Akun Puskesmas hanya dapat memantau percakapan'), 'command_center_chat_is_server_and_ui_read_only');
expect(methodBody(chat, 'mark_read').includes("method(TRUE) !== 'POST'"), 'chat_mark_read_post_only');
expect(methodBody(chat, 'foto').includes('authorize_upload_request()'), 'chat_upload_uses_post_authorization_gate');
expect(methodBody(chat, 'attachment').includes("method(TRUE) !== 'GET'") && methodBody(chat, 'attachment').includes('get_attachment_for_user'), 'chat_attachment_download_is_get_and_authorized');
expect(methodBody(chat, 'attachment').includes('Cache-Control: private, no-store') && methodBody(chat, 'attachment').includes('X-Content-Type-Options: nosniff') && methodBody(chat, 'attachment').includes('Cross-Origin-Resource-Policy: same-origin'), 'chat_attachment_download_uses_private_safe_headers');
expect(chatModel.includes("'upload_path' => $upload_path") && !chatModel.includes("'upload_path' => './uploads/chat_images/'"), 'chat_upload_targets_private_storage');
expect(chatModel.includes('$actual_mime = $storage->allowed_mime($stored_path)') && chatModel.includes('$stored_size > $max_bytes'), 'chat_upload_revalidates_file_before_database_insert');
expect(chatModel.includes("base_url('chat/attachment/'") && !chatModel.includes("base_url($attachment_path)"), 'chat_message_exposes_authorized_download_url_only');
expect(!chatModel.includes("'attachment_path' => $attachment_path"), 'chat_message_api_hides_storage_key');
expect(chatStorage.includes("const PRIVATE_PREFIX = 'chat-images/'") && chatStorage.includes('path_is_within($this->storage_root, $this->public_root)'), 'chat_storage_rejects_public_web_root');
expect(chatThread.includes('/^\\/chat\\/attachment\\/[1-9][0-9]*\\/?$/') && !chatThread.includes("parsed.pathname.indexOf('/uploads/chat_images/')"), 'chat_client_accepts_only_authorized_attachment_route');
expect(routes.includes("$route['chat/attachment/(:num)'] = 'chat/chat/attachment/$1';"), 'chat_attachment_route_explicit');
expect(['index', 'foto', 'video'].every((name) => methodBody(legacyChatUpload, name).includes('show_404();')), 'legacy_chat_upload_routes_retired');
expect(methodBody(notifications, 'list_json').includes("method(TRUE) !== 'GET'"), 'notification_list_get_only');
expect(methodBody(notifications, 'snapshot').includes("method(TRUE) !== 'GET'"), 'notification_snapshot_get_only');
expect(methodBody(notifications, 'mark_read').includes("method(TRUE) !== 'POST'"), 'notification_mark_read_post_only');
expect(methodBody(home, 'livekit_incoming_call').includes("method(TRUE) !== 'GET'"), 'incoming_call_poll_get_only');
expect(methodBody(nakesConsultation, 'get_terapi').includes("method(TRUE) !== 'GET'"), 'medicine_suggestion_get_only');
expect(methodBody(nakesConsultation, 'getICD_json').includes('show_404();'), 'legacy_diagnosis_suggestion_retired');
expect(methodBody(home, 'save_konsultasi').includes('show_404();'), 'duplicate_warga_consultation_route_retired');
expect(methodBody(nakes, 'tes_save_lokasi').includes('show_404();') && methodBody(nakes, 'save_location').includes('show_404();'), 'legacy_location_write_routes_retired');
expect(methodBody(nakes, 'get_location_user').includes('show_404();'), 'legacy_location_read_route_retired');

for (const name of ['livekit_token', 'answer_livekit_call', 'reject_livekit_call', 'livekit_call_status']) {
  expect(methodBody(home, name).includes('require_livekit_post()'), `warga_${name}_post_only`);
}
for (const name of ['livekit_token', 'start_livekit_call', 'end_livekit_call', 'livekit_call_status']) {
  expect(methodBody(nakes, name).includes('require_livekit_post()'), `nakes_${name}_post_only`);
}
const incoming = methodBody(home, 'livekit_incoming_call');
expect(!incoming.includes('expire_stale_ringing_calls'), 'incoming_get_does_not_bulk_mutate_calls');
expect(incoming.includes('get_latest_incoming_for_warga($user_id, $request_id, false)'), 'incoming_get_uses_read_only_lookup');
expect(callModel.includes('if ($expire_stale && $call && $this->is_call_expired($call))'), 'call_lookup_has_explicit_mutation_switch');

expect(nakesView.includes('<html lang="id">'), 'nakes_document_language_is_indonesian');
expect(nakesView.includes("value=\"<?= html_escape((string) $this->session->userdata('nama')); ?>\""), 'profile_name_attribute_escaped');
expect(nakesView.includes("<?= html_escape((string) ($profile['alamat'] ?? '')); ?>"), 'profile_address_escaped');
expect(nakesView.includes('JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT'), 'profile_values_serialized_safely_for_javascript');
expect((nakesView.match(/id="modalProfilLabel"/g) || []).length === 1, 'profile_modal_label_id_unique');
expect(nakesView.includes('id="chatModalLabel"'), 'chat_modal_has_independent_accessible_label');

const profileUpdate = methodBody(nakes, 'updateprofile');
expect(profileUpdate.includes("userdata('role') !== 'dokter'"), 'profile_update_requires_dokter_role');
expect(profileUpdate.includes("'safe_error_code' => 'profile_validation_failed'"), 'profile_update_has_stable_validation_error');
expect(nakes.includes("preg_match('/^\\+?[0-9]{8,20}$/', $phone)"), 'profile_phone_validated');
expect(nakes.includes("in_array($gender, ['Laki-laki', 'Perempuan'], true)"), 'profile_gender_allowlisted');
expect(nakes.includes("$birthdate_value > new DateTime('today')"), 'profile_birthdate_rejects_future');
expect(nakes.includes("@unlink($uploaded_profile_path)"), 'failed_profile_update_cleans_new_upload');

process.stdout.write(`SECURITY_HARDENING_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`SECURITY_HARDENING_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
