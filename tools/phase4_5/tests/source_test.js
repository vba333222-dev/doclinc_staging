const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
let passed = 0;
let failed = 0;

function source(file) {
  return fs.readFileSync(path.join(root, file), 'utf8');
}

function expect(condition, name) {
  if (condition) { passed++; process.stdout.write('PASS ' + name + '\n'); return; }
  failed++; process.stderr.write('FAIL ' + name + '\n');
}

const publicConfig = source('application/config/config.php');
const adminConfig = source('admin_menu/application/config/config.php');
const publicLogin = source('application/modules/login/controllers/Login.php');
const adminLogin = source('admin_menu/application/modules/login/controllers/Login.php');
const publicHooks = source('application/config/hooks.php');
const adminHooks = source('admin_menu/application/config/hooks.php');
const resetModel = source('admin_menu/application/modules/kelola_staff_puskesmas/models/Kelola_staff_puskesmas_m.php');
const presence = source('application/modules/home_nakes/controllers/Home_nakes.php');
const service = source('application/libraries/Session_binding_service.php');
const sessionPolicy = source('application/libraries/Session_binding_policy.php');
const care = source('application/libraries/Care_team_service.php');
const carePolicy = source('application/libraries/Care_team_policy.php');
const requestAuthz = source('application/helpers/request_authz_helper.php');
const notificationHelper = source('application/helpers/notification_helper.php');
const requestOrchestrator = source('application/libraries/Request_transition_orchestrator.php');
const wargaHomeModel = source('application/modules/home/models/Home_m.php');
const migration = source('application/migrations/20260811000100_session_care_team_foundation.php');
const activationModel = source('application/modules/login/models/Login_m.php');
const publicGate = source('application/hooks/Session_binding_gate.php');
const ciSession = source('system/libraries/Session/Session.php');
const ciFileSession = source('system/libraries/Session/drivers/Session_files_driver.php');
const passwordMask = source('assets/js/doclinc-password-mask.js');
const adminRecoveryModel = source('admin_menu/application/modules/login/models/Login_m.php');
const adminHome = source('admin_menu/application/modules/home/controllers/Home.php');
const commandCenterModel = source('admin_menu/application/modules/kelola_dokter_nakes/models/Kelola_dokter_nakes_m.php');
const homeNakesController = source('application/modules/home_nakes/controllers/Home_nakes.php');
const homeNakesModel = source('application/modules/home_nakes/models/Home_nakes_m.php');
const facilityJoinModels = [
  'admin_menu/application/modules/home/models/Home_m.php',
  'admin_menu/application/modules/konsultasi_kesehatan/models/Konsultasi_kesehatan_m.php',
  'admin_menu/application/modules/laporan/models/Laporan_m.php',
  'admin_menu/application/modules/notifikasi_admin/models/Notifikasi_admin_m.php',
  'admin_menu/application/modules/rekam_medis/models/Rekam_medis_m.php'
].map(source);
const consultationController = source('application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
const consultationModel = source('application/modules/konsultasi_nakes/models/Konsultasi_nakes_m.php');
const consultationView = source('application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');
const activeTask = source('application/modules/home_nakes/views/partials/nakes_active_task_card_v.php');
const history = source('application/modules/home_nakes/views/partials/nakes_history_v.php');
const staffAdminView = source('admin_menu/application/modules/kelola_staff_puskesmas/views/kelola_staff_puskesmas_v.php');
const commandCenterAdminView = source('admin_menu/application/modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php');
const passwordViews = [
  'application/modules/login/views/login_v.php',
  'application/modules/login/views/activate_account_v.php',
  'application/modules/login/views/change_password_v.php',
  'application/modules/sign_up/views/sign_up_v.php',
  'admin_menu/application/modules/login/views/login_v.php',
  'admin_menu/application/views/commons/footer.php'
].map(source);
const legacyAssignBody = homeNakesController.slice(homeNakesController.indexOf('public function assign_staff()'), homeNakesController.indexOf('public function assign_responsible_doctor()'));
const responsibleDoctorBody = homeNakesController.slice(homeNakesController.indexOf('public function assign_responsible_doctor()'), homeNakesController.indexOf('public function assign_visit_performer()'));
const visitPerformerBody = homeNakesController.slice(homeNakesController.indexOf('public function assign_visit_performer()'), homeNakesController.indexOf('public function clear_staff_assignment()'));
const legacyClearBody = homeNakesController.slice(homeNakesController.indexOf('public function clear_staff_assignment()'), homeNakesController.indexOf('public function tes_save_lokasi()'));
const legacyAssignmentGate = homeNakesController.slice(homeNakesController.indexOf('private function reject_legacy_staff_assignment_when_care_team_enabled()'), homeNakesController.indexOf('private function respond_staff_assignment_json'));

expect(publicConfig.includes('DOCLINC_SINGLE_ACTIVE_SESSION_ENABLED') && publicConfig.includes('Doclinc_feature_flags::resolve'), 'session_feature_default_off_resolver');
expect(publicConfig.includes("$config['sess_time_to_update'] = 300;")
  && publicConfig.includes("$config['sess_regenerate_destroy'] = FALSE;"), 'public_session_regeneration_retains_old_file_for_parallel_requests');
expect(ciSession.includes("$this->sess_regenerate((bool) config_item('sess_regenerate_destroy'))")
  && ciSession.includes('session_regenerate_id($destroy)')
  && ciFileSession.includes("unlink($this->_file_path.$session_id)"), 'ci3_file_session_destroy_flag_controls_old_file_removal');
expect(publicLogin.includes('sess_regenerate(TRUE)') && publicLogin.includes('sess_destroy()'), 'explicit_login_rotation_and_logout_destruction_remain_enabled');
expect(publicConfig.includes('DOCLINC_CARE_TEAM_WORKFLOW_ENABLED'), 'care_team_feature_default_off_resolver');
expect(adminConfig.includes('DOCLINC_SINGLE_ACTIVE_SESSION_ENABLED'), 'admin_uses_shared_session_feature');
expect(publicHooks.includes("'class' => 'Session_binding_gate'") && adminHooks.includes("'class' => 'Session_binding_gate'"), 'protected_apps_use_session_gate');
expect(publicLogin.includes('normal_session_token') && publicLogin.includes('session_binding_service->issue') && publicLogin.includes('$login_password_hash'), 'public_login_issues_password_bound_binding');
expect(adminLogin.includes('normal_session_token') && adminLogin.includes('Session_binding_service') && adminLogin.includes('$login_password_hash'), 'admin_login_issues_password_bound_binding');
expect(publicLogin.includes('revokeCurrent') && adminLogin.includes('revokeCurrent'), 'logout_revokes_current_binding');
expect(publicLogin.includes("'normal_session_token'" ) && publicLogin.includes("'credential_activation_user_id'"), 'activation_context_drops_normal_binding');
expect(activationModel.includes('revokeLocked') && activationModel.includes('complete_required_password_change'), 'activation_completion_revokes_old_binding');
expect(resetModel.includes('revokeLocked') && resetModel.indexOf('revokeLocked') < resetModel.indexOf('trans_commit()', resetModel.indexOf('revokeLocked')), 'admin_reset_revokes_inside_transaction');
expect(adminRecoveryModel.includes('revokeLocked') && adminRecoveryModel.includes('FOR UPDATE'), 'admin_password_recovery_revokes_inside_transaction');
expect(adminHome.includes('normal_session_token') && adminHome.includes('Session_binding_service') && adminHome.includes('->rotatePassword('), 'admin_password_change_rotates_binding_atomically');
expect(publicLogin.includes("$context['mode'] === 'enforcement'") && publicLogin.includes("set_userdata('normal_session_token', $completion)")
  && activationModel.includes('rotateLocked') && activationModel.indexOf('rotateLocked') < activationModel.indexOf('trans_commit()', activationModel.indexOf('rotateLocked')), 'public_enforced_password_change_rotates_binding_atomically');
expect(commandCenterModel.includes('session_binding_service') && commandCenterModel.includes('revokeLocked'), 'command_center_reset_and_deactivation_revoke_binding');
expect(sessionPolicy.includes('random_bytes(32)') && sessionPolicy.includes("hash('sha256', $token)") && sessionPolicy.includes('hash_equals'), 'binding_is_random_hashed_and_constant_time');
expect(service.includes('ON DUPLICATE KEY UPDATE') && service.includes('FOR UPDATE'), 'concurrent_login_serialized');
expect(service.includes('rotatePassword') && service.includes("->where('password', $expected_password_hash)")
  && service.includes("->where('token_hash', $current_hash)"), 'password_and_binding_rotation_use_locked_cas');
expect(service.includes('validateLocked') && care.includes('activeSession') && care.includes('validateLocked'), 'care_team_revalidates_binding_inside_transaction');
expect(presence.includes("'session_binding_valid'") && source('application/libraries/Nakes_presence_policy.php').includes('session_binding_valid'), 'presence_requires_effective_binding');
expect(publicGate.includes('set_status_header(401)') && publicGate.includes('sess_destroy()') && publicGate.includes('session_expired'), 'stale_json_session_is_destroyed_with_401');
expect(publicGate.indexOf("single_active_session_enabled') !== true") < publicGate.indexOf('new Session_binding_service'), 'feature_off_skips_session_schema');
expect(homeNakesModel.includes("care_team_workflow_enabled') === true\n\t\t\t&& $this->db->field_exists('responsible_doctor_user_id'")
  && consultationModel.includes("care_team_workflow_enabled') === true\n\t\t\t&& $this->db->field_exists('responsible_doctor_user_id'"), 'care_team_feature_off_preserves_legacy_ownership_queries');
expect(care.includes('responsible_doctor_user_id') && care.includes('request_responsible_doctor_assignments'), 'responsible_doctor_has_canonical_history');
expect(care.includes('request_visit_performer_assignments') && care.includes('visit_performer_user_id')
  && !care.includes('request_staff_assignments'), 'visit_performer_has_distinct_canonical_history');
expect(care.includes('Notification_delivery_service') && care.includes('createWithinTransaction')
  && care.includes("'responsible_doctor_assigned'") && care.includes("'visit_performer_assigned'"), 'care_assignments_create_notifications_inside_owned_transaction');
expect(care.indexOf('return $this->success(\'Dokter penanggung jawab tetap sama.\');') < care.indexOf("'responsible_doctor_assigned'")
  && care.indexOf('return $this->success(\'Nakes kunjungan tetap sama.\');') < care.indexOf("'visit_performer_assigned'"), 'idempotent_assignments_do_not_create_notifications');
expect(requestAuthz.includes("config->item('care_team_workflow_enabled') === true")
  && requestAuthz.includes('request->responsible_doctor_user_id === $user_id')
  && requestAuthz.includes('request->visit_performer_user_id === $user_id')
  && notificationHelper.includes('notification_request.responsible_doctor_user_id')
  && notificationHelper.includes('notification_request.visit_performer_user_id'), 'care_team_notification_visibility_uses_canonical_owners');
expect(!care.includes('nakes_presence') && !source('application/libraries/Care_team_policy.php').includes('nakes_presence'), 'presence_is_not_care_team_authorization');
expect(homeNakesModel.includes('AS is_online') && homeNakesModel.includes("nakes_presence_enabled') === true") && homeNakesModel.includes("select('NULL AS is_online'") && activeTask.includes("'Online' : 'Offline'") && history.includes("'Online' : 'Offline'"), 'presence_is_optional_selection_information');
expect(homeNakesModel.includes('staff_presence.puskesmas_code COLLATE utf8mb4_unicode_ci = CONVERT(puskesmas_staff.kode_pkm USING utf8mb4) COLLATE utf8mb4_unicode_ci'), 'staff_options_normalize_presence_join_collation');
expect(homeNakesModel.includes('m_puskesmas.kode_pkm COLLATE utf8mb4_general_ci = CONVERT(users.remark USING utf8mb4) COLLATE utf8mb4_general_ci')
  && commandCenterModel.includes('m_puskesmas.kode_pkm COLLATE utf8mb4_general_ci = CONVERT(users.remark USING utf8mb4) COLLATE utf8mb4_general_ci'), 'legacy_user_facility_joins_normalize_charset');
expect(facilityJoinModels.every(model => model.includes('CONVERT(NULLIF(TRIM(requests.assigned_puskesmas_code)') || model.includes('CONVERT(NULLIF(TRIM($request_alias.assigned_puskesmas_code)')), 'legacy_request_facility_joins_normalize_charset');
expect(care.includes("account_type'] !== 'command_center") === false && care.includes('commandCenterEligible'), 'command_center_checked_by_policy');
expect(care.includes("consultation_mode !== Care_team_policy::VISIT") && care.includes('responsible_doctor_user_id'), 'visit_assignment_requires_decision_and_responsible_doctor');
expect(care.includes('visitPerformerEligible($performer, $puskesmas_code)')
  && care.includes('visitPerformerEligible($identity, $request->assigned_puskesmas_code)')
  && carePolicy.includes('public function visitPerformerEligible')
  && carePolicy.includes('!$this->doctorProfession($profession)'), 'visit_performer_is_personal_non_doctor');
expect(homeNakesModel.includes('responsible_doctor_eligible') && homeNakesModel.includes('visit_performer_eligible')
  && activeTask.includes('empty($staff_option->visit_performer_eligible)')
  && history.includes('empty($staff_option->visit_performer_eligible)'), 'care_team_candidate_lists_use_server_policy');
expect(care.includes("'Tugas kunjungan baru'") && care.includes("'Ditugaskan oleh ' . $doctor_name . '.'")
  && care.includes('doctorDisplayName($actor)') && !homeNakesController.includes('doctor_name')
  && !care.includes("input->post('doctor") && !care.includes("input->post('dokter"), 'visit_notification_doctor_identity_is_server_resolved');
expect(requestOrchestrator.includes('doclinc_notify_puskesmas(')
  && wargaHomeModel.includes('care_team_command_center_user_id($puskesmas_code)')
  && wargaHomeModel.includes("if (!$care_team_enabled) {")
  && requestAuthz.includes("foreach (array('responsible_doctor_user_id', 'visit_performer_user_id')"), 'pending_cancellation_uses_operational_recipient_without_personal_legacy_fallback');
expect(homeNakesController.includes("method(TRUE) !== 'POST'") && homeNakesController.includes('assign_responsible_doctor')
  && homeNakesController.includes('choose_service_mode') && homeNakesController.includes('assign_visit_performer'), 'care_team_mutations_are_post_only');
expect(legacyAssignBody.indexOf('require_dokter_session') < legacyAssignBody.indexOf("method(TRUE) !== 'POST'")
  && legacyAssignBody.indexOf("method(TRUE) !== 'POST'") < legacyAssignBody.indexOf('reject_legacy_staff_assignment_when_care_team_enabled')
  && legacyAssignBody.indexOf('reject_legacy_staff_assignment_when_care_team_enabled') < legacyAssignBody.indexOf('Home_nakes_m->assign_staff_to_request')
  && legacyClearBody.indexOf('require_dokter_session') < legacyClearBody.indexOf("method(TRUE) !== 'POST'")
  && legacyClearBody.indexOf("method(TRUE) !== 'POST'") < legacyClearBody.indexOf('reject_legacy_staff_assignment_when_care_team_enabled')
  && legacyClearBody.indexOf('reject_legacy_staff_assignment_when_care_team_enabled') < legacyClearBody.indexOf('Home_nakes_m->clear_staff_assignment'), 'care_team_gate_precedes_legacy_staff_mutations');
expect(legacyAssignmentGate.includes("care_team_workflow_enabled') !== true")
  && legacyAssignmentGate.includes('respond_staff_assignment_json(403')
  && legacyAssignmentGate.includes('Pengaturan ini sudah tidak tersedia.')
  && legacyAssignmentGate.includes("redirect('home_nakes#riwayat_konsul')"), 'legacy_staff_gate_has_safe_json_and_form_responses');
expect(activeTask.includes("$care_team_enabled ? 'home_nakes/assign_responsible_doctor' : 'home_nakes/assign_staff'")
  && activeTask.includes("!$care_team_enabled && $primary_pic_assignment ? '' : 'hidden'")
  && history.includes("!empty($care_team_workflow_enabled) ? 'home_nakes/assign_responsible_doctor' : 'home_nakes/assign_staff'")
  && history.includes("empty($care_team_workflow_enabled) && $pic_assignment ? '' : 'hidden'")
  && history.includes("home_nakes/assign_visit_performer"), 'care_team_ui_uses_new_endpoints_and_hides_legacy_clear');
expect(responsibleDoctorBody.includes('care_team_post_ready') && responsibleDoctorBody.includes('assignResponsibleDoctor')
  && visitPerformerBody.includes('care_team_post_ready') && visitPerformerBody.includes('assignVisitPerformer')
  && !responsibleDoctorBody.includes('reject_legacy_staff_assignment_when_care_team_enabled')
  && !visitPerformerBody.includes('reject_legacy_staff_assignment_when_care_team_enabled'), 'care_team_assignment_endpoints_remain_available');
expect(consultationController.includes("['can_assess']") && homeNakesController.includes("['can_visit']")
  && consultationModel.includes("['can_assess']") && homeNakesModel.includes("['can_visit']"), 'responsible_and_performer_authority_remain_distinct');
expect(consultationController.indexOf("? !empty($access_context['can_assess'])") < consultationController.indexOf('Konsultasi_nakes_m->save_konsultasi_nakes'), 'command_center_clinical_write_denied_before_model_mutation');
expect(consultationController.includes("['can_open_patient_chat']")
  && consultationController.includes("account_type'] ?? '') === 'personal'")
  && consultationController.includes('doclinc_can_view_chat')
  && consultationView.includes('if (!empty($can_open_patient_chat))'), 'consultation_chat_cta_uses_server_personal_access');
expect(consultationController.includes('request->consultation_mode') && consultationView.includes('if (!$care_team_workflow_enabled)')
  && consultationModel.includes('service_mode_mismatch'), 'clinical_completion_uses_server_service_decision');
expect(migration.includes('DATABASE_CONNECTION_OPENED=false') && migration.includes("array_key_exists('apply'"), 'migration_plan_opens_no_database');
expect(migration.includes('backup_confirmation_mismatch') && migration.includes("hash_file('sha256'"), 'staging_apply_requires_backup_sha');
expect(migration.includes('partial_schema_detected') && migration.includes('target_schema_mismatch'), 'migration_fails_closed_on_partial_or_incompatible_schema');
expect(migration.includes("session_care_column_contract('bigint(20) unsigned', 'no', null, 'auto_increment')")
  && migration.includes("session_care_column_contract('char(64)', 'yes', 'null', '', 'ascii', 'ascii_bin')")
  && migration.includes("session_care_assert_table_exact"), 'migration_validates_exact_material_schema');
expect(!migration.includes('DROP TABLE') && !migration.includes('DROP COLUMN') && !migration.includes('TRUNCATE'), 'migration_has_no_destructive_ddl');
expect(!publicConfig.includes("$config['single_active_session_enabled'] = true") && !publicConfig.includes("$config['care_team_workflow_enabled'] = true"), 'features_not_hard_enabled');
expect(publicGate.indexOf("single_active_session_enabled') !== true") < publicGate.indexOf('Session_binding_service')
  && resetModel.indexOf("single_active_session_enabled') === true") < resetModel.indexOf('Session_binding_service'), 'feature_off_avoids_phase4_schema_dependency');
expect(passwordViews.every(view => view.includes('doclinc-password-mask.js')), 'all_password_surfaces_load_shared_mask');
expect(staffAdminView.includes('type="password"') && commandCenterAdminView.includes('type="password"')
  && passwordViews[5].includes('doclinc-password-mask.js'), 'admin_create_and_reset_password_surfaces_are_masked');
expect(passwordMask.includes("input.type !== 'password'") && !passwordMask.includes("input.type = 'text'"), 'password_mask_never_reveals_whole_field');
expect(passwordMask.includes("event.inputType === 'insertText'") && passwordMask.includes("input.addEventListener('paste', mask)"), 'password_mask_only_reveals_single_typed_character');

process.stdout.write('PHASE45_SOURCE_PASSED=' + passed + '\nPHASE45_SOURCE_FAILED=' + failed + '\n');
process.exit(failed === 0 ? 0 : 1);
