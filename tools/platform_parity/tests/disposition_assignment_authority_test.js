'use strict';

/**
 * Locks the disposition-assignment authority contract.
 *
 * The parity audit found the workflow state resolver emitting
 * WAITING_DOCTOR_DISPOSITION before WAITING_PERFORMER_ASSIGNMENT while the live
 * assignment path ignored the disposition entirely: the responsible doctor could
 * record "this visit requires a Dokter Umum" and then assign a Perawat.
 *
 * The fix keeps the responsible doctor as the assignment actor - which the
 * platform's own HTTP acceptance matrix documents at
 * clinical_http_actor_matrix_test.php:263 and exercises at lines 61/100/146/264/
 * 310 - and makes the disposition authoritative inside Care_team_service, the
 * class that owns this authority. Visit_assignment_service::assign() stays the
 * command-centre path and must not be wired to the RD endpoint, because it
 * rejects any non command-centre actor with NOT_COMMAND_CENTER.
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

const homeNakes = read('application/modules/home_nakes/controllers/Home_nakes.php');
const care = read('application/libraries/Care_team_service.php');
const assignment = read('application/libraries/Visit_assignment_service.php');
const historyView = read('application/modules/home_nakes/views/partials/nakes_history_v.php');
const taskCardView = read('application/modules/home_nakes/views/partials/nakes_active_task_card_v.php');

const assignBody = homeNakes.slice(
  homeNakes.indexOf('public function assign_visit_performer()'),
  homeNakes.indexOf('private function care_team_post_ready')
);

// The responsible doctor remains the assignment actor.
expect(assignBody.includes('assignVisitPerformer(') && assignBody.includes('$this->care_team_service'),
  'responsible_doctor_still_assigns_through_care_team_service');
expect(!assignBody.includes('Visit_assignment_service') && !assignBody.includes('->assign('),
  'rd_endpoint_not_routed_to_command_centre_service');

// The canonical service stays command-centre gated, which is exactly why it
// cannot serve the RD endpoint.
expect(assignment.includes("commandCenterEligible") && assignment.includes("'NOT_COMMAND_CENTER'"),
  'canonical_service_remains_command_centre_only');
expect(assignment.includes("$this->fail('WORKFLOW_NOT_ENROLLED')"),
  'canonical_service_still_requires_enrollment');

// The fail-closed managed guard exists and is actually reached from the live path.
expect(care.includes('$managed_failure = $this->managedAssignmentFailure(')
  && care.includes('private function managedAssignmentFailure('),
  'care_team_service_has_managed_guard');
expect(care.indexOf('$managed_failure = $this->managedAssignmentFailure(') > care.indexOf('public function assignVisitPerformer(')
  && care.indexOf('$managed_failure = $this->managedAssignmentFailure(') < care.indexOf('public function requestContext('),
  'guard_runs_inside_assign_visit_performer');

// The guard enforces each disposition rule.
const guard = care.slice(care.indexOf('private function managedAssignmentFailure('), care.indexOf('private function managedWorkflowRequest('));
expect(guard.includes('managedWorkflowRequest('), 'guard_is_scoped_to_managed_requests');
expect(guard.includes('activeVisitDisposition('), 'guard_requires_an_active_disposition');
expect(guard.includes('required_profession') && guard.includes('strcasecmp'),
  'guard_enforces_required_profession');
expect(guard.includes('activeFacilityPlacement('), 'guard_requires_an_active_placement');

// Requests outside the managed workflow keep the legacy behaviour.
expect(guard.includes('if (!$this->managedWorkflowRequest($request_id)) {') && guard.includes('return null;'),
  'unmanaged_requests_keep_legacy_behaviour');
expect(care.includes("$this->db->table_exists('visit_dispositions')")
  && care.includes("$this->db->table_exists('nakes_facility_placements')"),
  'guard_checks_tables_before_querying');

// The live RD forms cannot satisfy the canonical service's idempotency contract,
// which is further evidence the RD endpoint must keep the Care_team_service path.
expect(!historyView.includes('idempotency_key') && !taskCardView.includes('idempotency_key'),
  'rd_forms_post_no_idempotency_key');
expect(assignment.includes("$key === ''") && assignment.includes('ASSIGNMENT_CONFLICT'),
  'canonical_service_requires_idempotency_key');

process.stdout.write(`PLATFORM_PARITY_DISPOSITION_AUTHORITY_PASSED=${passed}\nPLATFORM_PARITY_DISPOSITION_AUTHORITY_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
