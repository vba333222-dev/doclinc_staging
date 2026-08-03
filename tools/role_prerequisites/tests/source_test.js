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
const completionController = read('application/controllers/Profile_completion.php');
const prerequisiteGate = read('application/hooks/Role_prerequisite_gate.php');
const prerequisiteGatePolicy = read('application/libraries/Role_prerequisite_gate_policy.php');
const hooks = read('application/config/hooks.php');
const identityPolicy = read('application/libraries/Role_identity_policy.php');
const identityMigration = read('application/migrations/20260802000100_role_identity_foundation.php');
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
const puskesmasReadiness = read('application/libraries/Puskesmas_data_readiness.php');
const nakesProfileForm = nakesView.slice(nakesView.indexOf('id="formEditProfile"'), nakesView.indexOf('id="offcanvasNotif"'));

expect(config.includes('DOCLINC_ROLE_PREREQUISITES_ENABLED') && config.includes("Doclinc_feature_flags::resolve("), 'feature_flag_is_environment_bound');
expect(config.includes("$config['role_prerequisites_enabled']"), 'feature_flag_defaults_through_shared_resolver');
expect(routes.includes("$route['profile/requirements'] = 'profile_requirements/status';"), 'explicit_status_route');
expect(routes.includes("$route['profile/update'] = 'home/update_profile';"), 'explicit_warga_profile_update_route');
expect(routes.includes("$route['profile/complete'] = 'profile_completion/index';"), 'explicit_profile_completion_route');
expect(firstLogin.includes("'profile_requirements' => array('status')"), 'first_login_json_contract');
expect(methodBody(statusController, 'status').includes("method(true) !== 'GET'"), 'status_is_get_only');
expect(statusController.includes('Cache-Control: private, no-store'), 'status_is_private_no_store');
expect(statusController.includes("array('warga', 'dokter')"), 'status_excludes_admin');
expect(methodBody(completionController, 'index').includes("method(true) !== 'GET'") && completionController.includes("array('warga', 'dokter')"), 'completion_page_is_get_only_and_non_admin');
expect(hooks.indexOf("'class' => 'Password_change_gate'") < hooks.indexOf("'class' => 'Role_prerequisite_gate'"), 'password_gate_precedes_profile_gate');
expect(prerequisiteGate.includes("role_prerequisites_enabled') !== true") && prerequisiteGate.includes("redirect('profile/complete')"), 'global_profile_gate_is_feature_bound');
expect(prerequisiteGate.includes("array('warga', 'dokter')"), 'global_profile_gate_excludes_admin');
expect(prerequisiteGatePolicy.includes("$class === 'profile_completion'") && prerequisiteGatePolicy.includes("$class === 'profile_requirements'") && !prerequisiteGatePolicy.includes("$class === 'home' && $method === 'index'"), 'only_remediation_routes_bypass_gate');
expect(prerequisiteGatePolicy.includes('(int) $resource_user_id === (int) $actor_user_id'), 'blocked_actor_can_only_read_own_profile_photo');
expect(service.includes("array('warga', 'dokter')") && service.includes("status !== 'aktif'") && service.includes('must_change_password'), 'actor_state_fail_closed');
expect(service.includes("array('personal', 'command_center')") && service.includes("$base['safe_error_code'] = 'actor_denied'"), 'canonical_nakes_identity_required_even_flag_off');
expect(service.includes("'puskesmas_staff'") && service.includes("'nomor_sip'"), 'personal_staff_requirements');
expect(service.includes("'nik'") && service.includes("'nomor_kk'") && service.includes("'nomor_bpjs_kis'") && service.includes("'nip'"), 'role_identity_requirements_are_enforced');
expect(identityPolicy.includes("^[0-9]{16}$") && identityPolicy.includes("^[0-9]{13}$") && identityPolicy.includes("^[0-9]{18}$"), 'canonical_identity_lengths_are_explicit');
expect(identityMigration.includes('ADD COLUMN `nik`') && identityMigration.includes('ADD COLUMN `nomor_kk`') && identityMigration.includes('ADD COLUMN `nomor_bpjs_kis`') && identityMigration.includes('ADD COLUMN `nip`'), 'identity_migration_adds_only_required_nullable_columns');
expect(identityMigration.includes('uq_users_nik') && identityMigration.includes('uq_users_nomor_bpjs_kis') && identityMigration.includes('uq_puskesmas_staff_nip'), 'identity_uniqueness_is_schema_enforced');
expect(service.includes("'m_puskesmas'") && service.includes("'nama_puskesmas'") && service.includes("'facility_address'") && service.includes("'facility_latitude'") && service.includes("'facility_longitude'"), 'assigned_facility_requirements');
expect(methodBody(service, 'facility_requirements').includes("array('kode_pkm', 'nama_puskesmas', 'alamat', 'latitude', 'longitude', 'status')"), 'facility_contract_matches_discovered_schema');
expect(service.includes("$allowed = !$enforced || $complete"), 'missing_profile_compatibility_mode');
expect(service.includes("'self_service_fields'") && service.includes("'managed_fields'") && service.includes("'remediation_mode'"), 'remediation_ownership_is_explicit');
expect(!service.includes('$labels = array_values(array_unique($labels))'), 'missing_field_labels_preserve_positional_alignment');
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
expect(!methodBody(nakes, 'cancel_request').includes('require_role_prerequisites') && prerequisiteGatePolicy.includes("'cancel_request'"), 'command_center_cancel_is_owned_by_global_gate');
expect(!methodBody(home, 'cancel_request').includes('role_prerequisite') && prerequisiteGatePolicy.includes("'home' => array") && prerequisiteGatePolicy.includes("'cancel_request'"), 'warga_cancel_is_owned_by_global_gate');
expect(wargaView.includes('data-role-prerequisite-alert') && wargaView.includes("showContent('profile')"), 'warga_banner_has_profile_cta');
expect(nakesDashboard.includes('data-role-prerequisite-alert') && nakesDashboard.includes("showContent('profile')") && nakesDashboard.includes('Hubungi pengelola DocLink.'), 'nakes_banner_distinguishes_self_service_and_managed_remediation');
expect(wargaView.includes('$role_prerequisite_incomplete') && wargaView.includes('data-enforced=') && wargaView.includes('tahap penyiapan data'), 'warga_incomplete_profile_is_visible_before_enforcement');
expect(nakesDashboard.includes('$role_prerequisite_incomplete') && nakesDashboard.includes('data-puskesmas-readiness') && nakesDashboard.includes('SIP belum dilengkapi'), 'nakes_readiness_is_visible_before_enforcement');
expect(puskesmasReadiness.includes("'facility_complete'") && puskesmasReadiness.includes("'staff_missing_sip'") && puskesmasReadiness.includes("'staff_invalid_account'"), 'puskesmas_readiness_has_exact_safe_counts');
expect(puskesmasReadiness.includes("'staff_missing_nip'") && nakesDashboard.includes('data-puskesmas-exception-board') && nakesDashboard.includes('Diterima tanpa PIC'), 'puskesmas_has_advanced_safe_exception_board');
expect(methodBody(nakes, 'index').includes("$d['puskesmas_staff_options']") && methodBody(nakes, 'index').includes("$d['puskesmas_data_readiness']"), 'command_center_readiness_uses_tenant_staff_projection');
expect(methodBody(home, 'update_profile').includes('require_post_json') && methodBody(home, 'require_post_json').includes("method(TRUE) === 'POST'") && methodBody(home, 'update_profile').includes('email_available_for_user') && methodBody(home, 'update_profile').includes('Home_m->update_profile'), 'warga_profile_update_is_validated_post');
expect(methodBody(home, 'validated_warga_profile_input').includes('Role_identity_policy') && methodBody(home, 'update_profile').includes('identity_value_available'), 'warga_identity_is_canonically_validated_and_unique');
expect(methodBody(home, 'update_profile').includes('array_intersect_key') && !methodBody(home, 'update_profile').includes('set_userdata($data)'), 'sensitive_identity_is_not_copied_to_session');
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
