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
    process.stdout.write(`FAIL ${label}\n`);
  }
}

function methodBody(source, name) {
  const match = new RegExp(`(?:public|private) function\\s+${name}\\s*\\(`).exec(source);
  if (!match) return '';
  const open = source.indexOf('{', match.index);
  let depth = 0;
  for (let index = open; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}' && --depth === 0) return source.slice(open + 1, index);
  }
  return '';
}

const config = read('application/config/config.php');
const routes = read('application/config/routes.php');
const firstLogin = read('application/libraries/First_login_gate_policy.php');
const service = read('application/libraries/Role_prerequisite_service.php');
const helper = read('application/helpers/role_prerequisite_helper.php');
const statusController = read('application/controllers/Profile_requirements.php');
const storage = read('application/libraries/Profile_image_storage.php');
const warga = read('application/modules/konsultasi/controllers/Konsultasi.php');
const home = read('application/modules/home/controllers/Home.php');
const nakes = read('application/modules/home_nakes/controllers/Home_nakes.php');
const completion = read('application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
const wargaView = read('application/modules/home/views/home_v.php');
const nakesDashboard = read('application/modules/home_nakes/views/partials/nakes_dashboard_v.php');
const nakesView = read('application/modules/home_nakes/views/home_nakes_v.php');
const homeModel = read('application/modules/home/models/Home_m.php');
const nakesModel = read('application/modules/home_nakes/models/Home_nakes_m.php');
const nakesProfileForm = nakesView.slice(nakesView.indexOf('id="formEditProfile"'), nakesView.indexOf('id="offcanvasNotif"'));

expect(config.includes('DOCLINC_ROLE_PREREQUISITES_ENABLED') && config.includes("Doclinc_feature_flags::resolve("), 'feature_flag_is_environment_bound');
expect(config.includes("$config['role_prerequisites_enabled']"), 'feature_flag_defaults_through_shared_resolver');
expect(routes.includes("$route['profile/requirements'] = 'profile_requirements/status';"), 'explicit_status_route');
expect(routes.includes("$route['profile/update'] = 'home/update_profile';"), 'explicit_warga_profile_update_route');
expect(firstLogin.includes("'profile_requirements' => array('status')"), 'first_login_json_contract');
expect(methodBody(statusController, 'status').includes("method(true) !== 'GET'"), 'status_is_get_only');
expect(statusController.includes('Cache-Control: private, no-store'), 'status_is_private_no_store');
expect(statusController.includes("array('warga', 'dokter')"), 'status_excludes_admin');
expect(service.includes("array('warga', 'dokter')") && service.includes("status !== 'aktif'") && service.includes('must_change_password'), 'actor_state_fail_closed');
expect(service.includes("array('personal', 'command_center')") && service.includes("$base['safe_error_code'] = 'actor_denied'"), 'canonical_nakes_identity_required_even_flag_off');
expect(service.includes("'puskesmas_staff'") && service.includes("'nomor_sip'"), 'personal_staff_requirements');
expect(service.includes("'m_puskesmas'") && service.includes("'nama_puskesmas'") && service.includes("'facility_address'") && service.includes("'facility_latitude'") && service.includes("'facility_longitude'"), 'assigned_facility_requirements');
expect(methodBody(service, 'facility_requirements').includes("array('kode_pkm', 'nama_puskesmas', 'alamat', 'latitude', 'longitude', 'status')"), 'facility_contract_matches_discovered_schema');
expect(service.includes("$allowed = !$enforced || $complete"), 'missing_profile_compatibility_mode');
expect(service.includes("'self_service_fields'") && service.includes("'managed_fields'") && service.includes("'remediation_mode'"), 'remediation_ownership_is_explicit');
expect(helper.includes('Profile_image_storage') && helper.includes('allowed_mime'), 'photo_is_content_validated');
expect(methodBody(storage, 'resolve_stored_file').includes('existing_storage_directory') && !methodBody(storage, 'resolve_stored_file').includes('ensure_storage_directory'), 'status_photo_read_has_no_directory_creation');

const createBody = methodBody(warga, 'save_konsultasi');
const acceptBody = methodBody(nakes, 'accept_request');
const assignBody = methodBody(nakes, 'assign_staff');
const clearBody = methodBody(nakes, 'clear_staff_assignment');
const startCallBody = methodBody(nakes, 'start_livekit_call');
const tokenBody = methodBody(nakes, 'livekit_token');
const heartbeatBody = methodBody(nakes, 'presence_heartbeat');
const visitLocationBody = methodBody(nakes, 'update_visit_location');
const visitBody = methodBody(nakes, 'update_visit_status');
const completeBody = methodBody(completion, 'save_konsultasi_nakes');
expect(createBody.indexOf('doclinc_role_prerequisite_state') < createBody.indexOf('doclinc_active_consultation_request'), 'warga_gate_precedes_domain_work');
expect(acceptBody.indexOf('require_role_prerequisites') < acceptBody.indexOf('Home_nakes_m->accept_request'), 'accept_gate_precedes_mutation');
expect(assignBody.indexOf('require_role_prerequisites') < assignBody.indexOf('Home_nakes_m->assign_staff_to_request'), 'assign_gate_precedes_mutation');
expect(clearBody.indexOf('require_role_prerequisites') < clearBody.indexOf('Home_nakes_m->clear_staff_assignment'), 'clear_gate_precedes_mutation');
expect(startCallBody.indexOf('require_role_prerequisites') < startCallBody.indexOf('Call_session_m->start_or_reuse'), 'call_gate_precedes_session_creation');
expect(tokenBody.indexOf('require_role_prerequisites') < tokenBody.indexOf('doclinc_livekit_token_payload'), 'token_gate_precedes_token_issuance');
expect(heartbeatBody.indexOf('require_role_prerequisites') < heartbeatBody.indexOf('service->touch'), 'presence_gate_precedes_write');
expect(visitLocationBody.indexOf('require_role_prerequisites') < visitLocationBody.indexOf('Home_nakes_m->update_visit_location'), 'location_gate_precedes_mutation');
expect(visitBody.indexOf('require_role_prerequisites') < visitBody.indexOf('Home_nakes_m->update_visit_status'), 'visit_gate_precedes_mutation');
expect(completeBody.indexOf('doclinc_role_prerequisite_state') < completeBody.indexOf('Konsultasi_nakes_m->save_konsultasi_nakes'), 'completion_gate_precedes_mutation');
expect(!methodBody(nakes, 'cancel_request').includes('require_role_prerequisites'), 'command_center_cancel_remains_available');
expect(!methodBody(home, 'cancel_request').includes('role_prerequisite'), 'warga_cancel_remains_available');
expect(wargaView.includes('data-role-prerequisite-alert') && wargaView.includes("showContent('profile')"), 'warga_banner_has_profile_cta');
expect(nakesDashboard.includes('data-role-prerequisite-alert') && nakesDashboard.includes("showContent('profile')") && nakesDashboard.includes('Hubungi pengelola DocLink.'), 'nakes_banner_distinguishes_self_service_and_managed_remediation');
expect(methodBody(home, 'update_profile').includes('require_post_json') && methodBody(home, 'require_post_json').includes("method(TRUE) === 'POST'") && methodBody(home, 'update_profile').includes('email_available_for_user') && methodBody(home, 'update_profile').includes('Home_m->update_profile'), 'warga_profile_update_is_validated_post');
expect(methodBody(homeModel, 'update_profile').includes("array('nama', 'email', 'no_hp', 'tgl', 'gender', 'alamat')") && methodBody(homeModel, 'update_profile').includes("where('role', 'warga')"), 'warga_profile_model_has_exact_field_and_role_scope');
expect(methodBody(homeModel, 'email_available_for_user').includes("$query !== false") && methodBody(nakesModel, 'email_available_for_user').includes("$query !== false"), 'profile_email_check_fails_closed_on_database_error');
expect(wargaView.includes('id="wargaProfileForm"') && wargaView.includes('name="email"') && wargaView.includes('name="alamat"') && !wargaView.includes('Muhammad Bani Husni'), 'warga_profile_form_uses_persisted_values');
expect(methodBody(nakes, 'validated_profile_input').includes("$account_type === 'command_center'") && methodBody(nakes, 'updateprofile').includes('email_available_for_user'), 'nakes_profile_validation_is_identity_aware');
expect(methodBody(nakesModel, 'update_profile').includes("array('nama', 'email', 'no_hp', 'tgl', 'gender', 'alamat', 'foto')") && methodBody(nakesModel, 'update_profile').includes("where('role', 'dokter')"), 'nakes_profile_model_has_exact_field_and_role_scope');
expect(nakesProfileForm.includes('name="email"') && nakesProfileForm.includes('name="no_hp"') && nakesProfileForm.indexOf('name="no_hp"') < nakesProfileForm.indexOf("if (!empty($nakes_is_personal))"), 'command_center_can_remediate_shared_profile_fields');
expect(helper.includes("'safe_error_code'") && helper.includes("'missing_fields'") && helper.includes("'cta_url'"), 'stable_json_error_contract');
expect(helper.includes("!empty($state['cta_url'])") && !helper.includes("base_url(isset($state['cta_url'])"), 'managed_gap_does_not_fall_back_to_profile_cta');
expect(helper.includes("$code === 'actor_denied' ? 403") && helper.includes("$code === 'profile_schema_unavailable' ? 503"), 'http_status_matches_error_class');

process.stdout.write(`ROLE_PREREQUISITE_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`ROLE_PREREQUISITE_SOURCE_FAILED=${failed}\n`);
process.exit(failed > 0 ? 1 : 0);
