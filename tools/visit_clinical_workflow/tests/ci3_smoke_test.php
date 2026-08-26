<?php
require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php'; $policy = new Visit_workflow_policy($app->db, false); $row = $app->db->query("SELECT MIN(r.request_id) AS request_id FROM requests r LEFT JOIN visit_dispositions d ON d.request_id=r.request_id WHERE d.request_id IS NULL AND r.request_id NOT IN (47,52)")->row(); $id = $row ? (int)$row->request_id : 1; vcw_assert_safe_request_id($id);
vcw_assert_same(false, $policy->isEnrolled($id), 'enrollment query'); vcw_assert_same(false, $policy->mayEnrollNewRequest($id), 'feature off');
require_once dirname(__DIR__, 3) . '/application/libraries/Visit_workflow_state_resolver.php';
$resolverOff = new Visit_workflow_state_resolver($app->db, $policy, false);
vcw_assert_same(null, $resolverOff->resolve($id)['state'], 'legacy state with feature off');
$resolverOn = new Visit_workflow_state_resolver($app->db, new Visit_workflow_policy($app->db, true), true);
vcw_assert_same('WAITING_DOCTOR_DISPOSITION', $resolverOn->resolve($id)['state'], 'enrollment candidate state');
$request = $app->db->where('request_id', $id)->get('requests')->row();
vcw_assert_true($request !== null && (int) $request->user_id > 0, 'synthetic policy request owner missing');
$app->db->query("INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key) VALUES (?,?,?,?,?,?)", array($id, 1, 'visit', 'routine', (int) $request->user_id, 'ci3-policy-smoke-'.$id));
$enrolledPolicy = new Visit_workflow_policy($app->db, false);
vcw_assert_same(true, $enrolledPolicy->isEnrolled($id), 'enrolled query after disposition');
vcw_assert_same(false, $enrolledPolicy->mayEnrollNewRequest($id), 'enrolled flag off remains no new enrollment');
echo "CI3_BOOTSTRAP=PASS\nREAL_POLICY_EXECUTION=PASS\nREAL_STATE_RESOLVER_EXECUTION=PASS\nDISPOSABLE_DB_CONNECTION=PASS\nPOLICY_IS_ENROLLED=false\nPOLICY_MAY_ENROLL=false\n";
echo "POLICY_IS_ENROLLED_AFTER_DISPOSITION=true\nPOLICY_MAY_ENROLL_AFTER_DISPOSITION=false\n";
