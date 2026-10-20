<?php
/**
 * Clinical JSON-401 contract regression test.
 *
 * Home_nakes serves both HTML pages and JSON endpoints. When the session is
 * missing, an AJAX caller must receive a JSON 401 rather than an HTML login
 * redirect, otherwise the browser client parses a redirect page as JSON and
 * reports a meaningless error. The controller keeps an explicit allow-list of
 * endpoints that get the JSON 401.
 *
 * The six clinical endpoints (clinical_workflow, clinical_review,
 * clinical_disposition, clinical_finalize, clinical_amend, clinical_close) all
 * respond with JSON via clinical_json_result() or an explicit json_encode, but
 * they were missing from the list. An unauthenticated call to any of them fell
 * through to redirect('login') and returned HTML instead.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }

$passed = 0;
$failed = 0;

function clinical_json_check($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "  ok   - " . $label . "\n";
		return true;
	}
	$failed++;
	echo "  FAIL - " . $label . "\n";
	return false;
}

$source = file_get_contents(APPPATH . 'modules/home_nakes/controllers/Home_nakes.php');

// Pull the allow-list out of the constructor so the test follows the code.
$list = '';
if (preg_match('/fetch_method\(\)\s*,\s*array\((.*?)\)\s*,\s*true\)/s', $source, $m) === 1) {
	$list = $m[1];
}

echo "== the JSON-401 allow-list must be discoverable ==\n";
clinical_json_check($list !== '', 'the allow-list is present in the constructor');

$declared = array();
if ($list !== '') {
	foreach (explode(',', $list) as $entry) {
		$name = trim($entry, " \t\n\r'\"");
		if ($name !== '') { $declared[] = $name; }
	}
}

echo "\n== every clinical endpoint must be in the allow-list ==\n";

$clinical = array('clinical_workflow', 'clinical_review', 'clinical_disposition', 'clinical_disposition_revise', 'clinical_record', 'clinical_finalize', 'clinical_amend', 'clinical_close');
foreach ($clinical as $method) {
	clinical_json_check(in_array($method, $declared, true), 'allow-listed: ' . $method);
}

echo "\n== each allow-listed clinical endpoint really returns JSON ==\n";

foreach ($clinical as $method) {
	// Each of these either calls clinical_json_result() or writes a JSON body itself.
	$pattern = '/public function ' . preg_quote($method, '/') . '\s*\([^)]*\)\s*\{(.{0,4000}?)\n\t\}/s';
	$body = preg_match($pattern, $source, $bm) === 1 ? $bm[1] : '';
	$isJson = $body !== '' && (
		strpos($body, 'clinical_json_result(') !== false ||
		strpos($body, 'json_encode(') !== false
	);
	clinical_json_check($body !== '', 'method body found: ' . $method);
	clinical_json_check($isJson, 'responds with JSON: ' . $method);
}

echo "\n== the original allow-list entries must survive ==\n";

$original = array('visit_location', 'update_visit_location', 'update_visit_status', 'presence_heartbeat', 'presence_snapshot', 'livekit_token', 'start_livekit_call', 'end_livekit_call', 'livekit_call_status', 'assign_staff', 'clear_staff_assignment', 'assign_responsible_doctor', 'choose_service_mode', 'assign_visit_performer');
foreach ($original as $method) {
	clinical_json_check(in_array($method, $declared, true), 'still allow-listed: ' . $method);
}

echo "\n== the non-JSON fallback must remain ==\n";
clinical_json_check(strpos($source, "redirect('login')") !== false, 'unlisted methods still redirect to login');

echo "\nCLINICAL_JSON_401=PASSED=$passed, FAILED=$failed\n";
exit($failed === 0 ? 0 : 1);
