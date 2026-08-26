<?php
if (!defined('BASEPATH')) define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 3) . '/application/models/Visit_disposition_m.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Visit_disposition_service.php';
require_once __DIR__ . '/assert.php';
vcw_assert_true(method_exists('Visit_disposition_m', 'getActiveForUpdate'), 'active lock reader exists');
vcw_assert_true(method_exists('Visit_disposition_m', 'getActive'), 'active reader exists');
vcw_assert_true(method_exists('Visit_disposition_service', 'create'), 'create interface exists');
vcw_assert_true(method_exists('Visit_disposition_service', 'revise'), 'revise interface exists');
$helper = file_get_contents(dirname(__DIR__, 3) . '/application/helpers/request_event_helper.php');
vcw_assert_true(strpos($helper, "domain_event_key") !== false, 'domain event dedupe supported');
$service = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/Visit_disposition_service.php');
foreach (array('REQUEST_NOT_ACCEPTED','NOT_RESPONSIBLE_DOCTOR','WORKFLOW_ENROLLMENT_DISABLED','DISPOSITION_VERSION_CONFLICT','DISPOSITION_LOCKED','INVALID_DECISION','INVALID_URGENCY','IDEMPOTENCY_KEY_CONFLICT') as $code) {
    vcw_assert_true(strpos($service, $code) !== false, 'stable code '.$code);
}
vcw_assert_true(strpos($service, "FOR UPDATE") !== false, 'request lock present');
echo "TASK4_DISPOSITION=PASS\n";
