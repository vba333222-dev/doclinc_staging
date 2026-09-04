<?php

require_once __DIR__ . '/assert.php';
$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
require_once APPPATH . 'libraries/Visit_workflow_state_resolver.php';

function ownership_query($db, $sql, array $args = array())
{
    $query = $db->query($sql, $args);
    if (!$query) {
        $error = $db->error();
        throw new RuntimeException($error['message'] ?? 'ownership_query_failed');
    }
    return $query;
}

$user = ownership_query($db, 'SELECT userId FROM users ORDER BY userId LIMIT 1')->row_array();
vcw_assert_true($user !== null, 'ownership fixture user exists');
$owner = (int) $user['userId'];
$base = (int) ownership_query($db, 'SELECT COALESCE(MAX(request_id), 940000000) + 10000 AS next_id FROM requests')->row()->next_id;

$cases = array(
    'A_managed_pre_disposition' => array('Accepted', 'visit', true, false, true),
    'B_enrolled_flag_on' => array('Accepted', 'visit', true, true, true),
    'C_enrolled_flag_off' => array('Accepted', 'visit', false, true, true),
    'D_legacy_flag_off' => array('Pending', '', false, false, false),
    'E_historical_completed_flag_on' => array('Completed', '', true, false, false),
    'F_new_flag_off' => array('Accepted', 'visit', false, false, false),
    'G_legacy_not_eligible_flag_on' => array('Pending', '', true, false, false),
    'H_eligible_zero_disposition_flag_on' => array('Accepted', 'visit', true, false, true),
    'I_enrolled_completed' => array('Completed', 'visit', false, true, true),
    'J_historical_completed_no_enrollment' => array('Completed', '', true, false, false),
);

$observed = array();
$index = 0;
foreach ($cases as $name => $case) {
    list($status, $mode, $enabled, $enrolled, $expectedManaged) = $case;
    $requestId = $base + $index++;
    ownership_query($db, 'INSERT INTO requests (request_id,user_id,location,request_status,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode) VALUES (?,?,?,?,?,?,?,?)', array(
        $requestId, $owner, 'ownership-policy', $status, 'OWNERSHIP-' . $requestId, 'Ownership Policy', 'not_started', $mode,
    ));
    if ($enrolled) {
        ownership_query($db, 'INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,idempotency_key,created_at) VALUES (?,?,?,?,?,?,NOW(6))', array(
            $requestId, 1, 'visit', 'routine', $owner, 'ownership-policy-' . $requestId,
        ));
    }

    $policy = new Visit_workflow_policy($db, $enabled);
    $observed[$name] = array(
        'isEnrolled' => $policy->isEnrolled($requestId),
        'mayEnrollNewRequest' => $policy->mayEnrollNewRequest($requestId),
        'isManagedWorkflowRequest' => $policy->isManagedWorkflowRequest($requestId),
        'expectedManaged' => $expectedManaged,
        'requestId' => $requestId,
    );
    vcw_assert_same($expectedManaged, $observed[$name]['isManagedWorkflowRequest'], $name . ' managed ownership');
}

vcw_assert_same(false, $observed['E_historical_completed_flag_on']['isManagedWorkflowRequest'], 'historical Completed remains legacy');
vcw_assert_same(false, $observed['G_legacy_not_eligible_flag_on']['isManagedWorkflowRequest'], 'flag-on ineligible legacy remains legacy');
vcw_assert_same(true, $observed['A_managed_pre_disposition']['isManagedWorkflowRequest'], 'pre-disposition enrollment candidate is managed');
vcw_assert_same(true, $observed['C_enrolled_flag_off']['isManagedWorkflowRequest'], 'enrolled request remains managed after flag off');
vcw_assert_same(true, $observed['I_enrolled_completed']['isManagedWorkflowRequest'], 'enrolled Completed remains managed');
$historicalId = (int) $observed['J_historical_completed_no_enrollment']['requestId'];
$beforeHistoricalWrites = (int) ownership_query($db, 'SELECT COUNT(*) FROM visit_dispositions WHERE request_id = ?', array($historicalId))->row()->{'COUNT(*)'};
$historicalPolicy = new Visit_workflow_policy($db, true);
$historicalResolver = new Visit_workflow_state_resolver($db, $historicalPolicy, true);
$historicalResolved = $historicalResolver->resolve($historicalId);
$afterHistoricalWrites = (int) ownership_query($db, 'SELECT COUNT(*) FROM visit_dispositions WHERE request_id = ?', array($historicalId))->row()->{'COUNT(*)'};
vcw_assert_same(null, $historicalResolved['state'], 'historical Completed resolver remains unmanaged');
vcw_assert_same($beforeHistoricalWrites, $afterHistoricalWrites, 'historical read does not persist workflow rows');

echo "OWNERSHIP_TRUTH_TABLE_EXPANDED=PASS\n";
echo "HISTORICAL_COMPLETED_IS_MANAGED=NO\n";
echo "HISTORICAL_COMPLETED_SYNTHETIC_BACKFILL=NO\n";
echo "FLAG_ON_INELIGIBLE_LEGACY_IS_MANAGED=NO\n";
echo "HISTORICAL_COMPLETED_READ_WRITES=" . ($afterHistoricalWrites - $beforeHistoricalWrites) . "\n";
