const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
let passed = 0;
let failed = 0;

function expect(condition, name) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${name}\n`);
    return;
  }
  failed += 1;
  process.stderr.write(`FAIL ${name}\n`);
}

const service = read('application/libraries/Visit_vital_signs_service.php');
const policy = read('application/libraries/Visit_vital_signs_policy.php');
const controller = read('application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
const model = read('application/modules/konsultasi_nakes/models/Konsultasi_nakes_m.php');
const view = read('application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');
const homeController = read('application/modules/home_nakes/controllers/Home_nakes.php');
const homeModel = read('application/modules/home_nakes/models/Home_nakes_m.php');
const careTeam = read('application/libraries/Care_team_service.php');
const migration = read('application/migrations/20260813000100_phase6_clinical_visit_foundation.php');
const requestAuthz = read('application/helpers/request_authz_helper.php');

expect(service.includes('FOR UPDATE') && service.includes("empty($access['can_visit'])")
  && service.includes("consultation_mode ?? '') !== 'visit'"), 'vital_write_revalidates_canonical_visit_access_under_lock');
expect(service.includes("'measured_by_user_id' => $user_id")
	&& service.includes("'measured_by_staff_id' => $assignment['visit_performer_staff_id']")
	&& service.includes("'responsible_doctor_user_id' => $assignment['responsible_doctor_user_id']")
	&& service.includes("'visit_performer_user_id' => $assignment['visit_performer_user_id']"), 'vital_attribution_is_server_derived');
expect(!service.includes("$input['measured_by_user_id']") && !service.includes("$input['responsible_doctor_user_id']")
  && !service.includes("$input['measured_at']"), 'client_cannot_supply_vital_attribution');
expect(controller.includes('public function save_vital_signs()') && controller.includes("method(TRUE) !== 'POST'")
  && controller.includes("account_type'] ?? '') !== 'personal'"), 'vital_endpoint_is_post_only_and_personal');
expect(model.includes("currentAssignmentMeasurementState($request, true)")
	&& model.includes("'visit_status_incomplete'") && model.includes("'vital_signs_required'")
	&& model.includes("'vital_signs_schema_unavailable'"), 'visit_completion_requires_completed_visit_and_current_measurement');
expect(model.includes("!$this->db->table_exists('medicalrecords')")
	&& model.includes("'clinical_attribution_schema_unavailable'"), 'care_team_clinical_attribution_schema_fails_closed');
expect(model.includes("$record['responsible_doctor_user_id']") && model.includes("$record['recorded_by_user_id']"), 'medicalrecord_keeps_responsible_doctor_and_writer');
expect(homeModel.includes('if ($current_status === $next_status)')
	&& homeModel.includes("'changed' => false"), 'visit_status_repeat_is_idempotent_without_mutation');
expect(homeModel.includes("currentAssignmentMeasurementState($request, true)")
	&& homeModel.includes('Catat tanda vital sebelum menyelesaikan kunjungan.'), 'visit_status_completion_requires_current_measurement');
expect(/if \(!empty\(\$result\['changed'\]\)\) \{\s*if \(function_exists\('doclinc_log_request_event'\)\) \{\s*doclinc_log_request_event\('visit_status_updated'[\s\S]*?\}\s*\$this->notify_visit_status\(/.test(homeController), 'visit_status_generic_event_and_notification_only_on_change');
expect(service.includes("count($responsible) !== 1") && service.includes("count($performer) !== 1")
	&& service.includes("->where('measured_at >=', $assignment['assigned_at'])")
	&& service.includes("->where('measured_by_staff_id', $assignment['visit_performer_staff_id'])"), 'measurement_is_bound_to_current_assignment_cycle');
expect(careTeam.includes("array('changed' => $changed, 'consultation_mode' => $mode)")
  && homeController.includes('notify_service_mode('), 'service_mode_notification_only_on_change');
expect(homeController.includes('doclinc_notify_user($patient_id') && homeController.includes('doclinc_notify_puskesmas($puskesmas_code'), 'status_notifications_use_server_resolved_recipients');
expect(requestAuthz.includes("$result['can_visit'] = $result['is_visit_performer']")
  || careTeam.includes("$result['can_visit'] = $result['is_visit_performer']"), 'visit_authority_remains_canonical_performer_only');
expect(view.includes('id="visitVitalSignsForm"') && view.includes('save_vital_signs')
  && view.includes('can_record_vital_signs'), 'visit_performer_has_vital_signs_surface');
expect(!view.includes('<span class="summary-label">User ID</span>') && !view.includes('Akun Personal'), 'nakes_clinical_view_hides_internal_identity_labels');
expect(migration.includes('request_vital_sign_measurements') && migration.includes('responsible_doctor_user_id')
	&& migration.includes('recorded_by_user_id') && !/\b(?:DROP|TRUNCATE)\b/i.test(migration), 'phase6_migration_is_additive');
expect(migration.includes('ALGORITHM=INPLACE,LOCK=NONE'), 'medicalrecord_columns_request_online_ddl');
expect(migration.includes('DATABASE_CONNECTION_OPENED=false') && migration.includes("if (!array_key_exists('apply', $options))"), 'phase6_migration_defaults_to_offline_plan');
expect(policy.includes("'systolic'") && policy.includes("'diastolic'") && policy.includes("'pulse'")
  && policy.includes("'respiratory_rate'") && policy.includes("'temperature_c'")
  && policy.includes("'oxygen_saturation'"), 'required_vital_signs_are_structured');

process.stdout.write(`PHASE6_SOURCE_ASSERTIONS=${passed + failed}\nPHASE6_SOURCE_PASS=${passed}\nPHASE6_SOURCE_FAIL=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
