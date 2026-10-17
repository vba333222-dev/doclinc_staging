<?php
/**
 * Parity runner verdict-classification regression test.
 *
 * The runner decides PASS / FAIL / UNVERIFIED_SKIP / UNVERIFIED_ENV from a
 * suite's own output. It used to enumerate the failure tokens
 * (TEST_RESULT / RESULT / INTEGRATION), so a suite that declared its failure with
 * any other token was mis-reported as UNVERIFIED_SKIP while it was actually
 * failing. Three suites were affected:
 *
 *   nakes_import/unit.php                              NAKES_IMPORT_UNIT=FAIL
 *   clinical_suggestions/migration_integration.php      MIGRATION_INTEGRATION=FAIL
 *   clinical_suggestions/anamnesis_migration_integration.php  ANAMESIS_INTEGRATION=FAIL
 *
 * All three pair their FAIL verdict with SAFE_ERROR_CODE=..., and the runner
 * treated any SAFE_ERROR_CODE= as a skip. A skip must never mask a failure, so
 * the failure pattern is now general: any uppercase verdict ending in =FAIL.
 *
 * This test reads the live regexes out of run_all_tests.ps1 so it cannot drift
 * from the runner, then replays real suite output through them.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }

$passed = 0;
$failed = 0;

function runner_verdict_check($condition, $label)
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

$runnerPath = __DIR__ . '/../run_all_tests.ps1';
$runner = is_file($runnerPath) ? file_get_contents($runnerPath) : '';

echo "== the runner regexes must be discoverable ==\n";
runner_verdict_check($runner !== '', 'run_all_tests.ps1 is readable');

$skipPattern = '';
$failPattern = '';
if (preg_match('/\$suiteSkip\s*=\s*\$text\s*-match\s*\'([^\']+)\'/', $runner, $m) === 1) { $skipPattern = $m[1]; }
if (preg_match('/\$explicitFail\s*=\s*\$text\s*-match\s*\'([^\']+)\'/', $runner, $m) === 1) { $failPattern = $m[1]; }

runner_verdict_check($skipPattern !== '', 'suiteSkip regex found in the runner');
runner_verdict_check($failPattern !== '', 'explicitFail regex found in the runner');

/** Replays the runner's decision order: explicitFail, then suiteSkip, else PASS. */
function runner_verdict($text, $failPattern, $skipPattern)
{
	if ($failPattern !== '' && preg_match('/' . $failPattern . '/', $text) === 1) { return 'FAIL'; }
	if ($skipPattern !== '' && preg_match('/' . $skipPattern . '/', $text) === 1) { return 'UNVERIFIED_SKIP'; }
	return 'PASS';
}

echo "\n== a self-declared failure must win over the skip heuristic ==\n";

$cases = array(
	array("NAKES_IMPORT_UNIT=FAIL\nSAFE_ERROR_CODE=test_source_required", 'FAIL', 'nakes_import missing-source FAIL is not a skip'),
	array("NAKES_IMPORT_UNIT=FAIL\nSAFE_ERROR_CODE=unit_test_failed", 'FAIL', 'nakes_import assertion FAIL is not a skip'),
	array("MIGRATION_INTEGRATION=FAIL\nSAFE_ERROR_CODE=disposable_test_database_required", 'FAIL', 'clinical_suggestions migration FAIL is not a skip'),
	array("ANAMNESIS_INTEGRATION=FAIL\nSAFE_ERROR_CODE=disposable_test_database_required", 'FAIL', 'clinical_suggestions anamnesis FAIL is not a skip'),
	array('TEST_RESULT=FAIL', 'FAIL', 'TEST_RESULT=FAIL still detected'),
	array('RESULT=FAIL', 'FAIL', 'RESULT=FAIL still detected'),
	array('INTEGRATION=FAIL', 'FAIL', 'INTEGRATION=FAIL still detected'),
	array('SOMETHING_FAILED=2', 'FAIL', 'a counted failure still detected'),
);
foreach ($cases as $case) {
	runner_verdict_check(runner_verdict($case[0], $failPattern, $skipPattern) === $case[1], $case[2]);
}

echo "\n== genuine skips must still be reported as skips ==\n";

$skipCases = array(
	array('SAFE_ERROR_CODE=disposable_database_password_missing', 'UNVERIFIED_SKIP', 'a skip with no failure token is still a skip'),
	array('SUITE=SKIP', 'UNVERIFIED_SKIP', 'SUITE=SKIP still a skip'),
	array('CASE_SKIPPED=3', 'UNVERIFIED_SKIP', 'a counted skip still a skip'),
);
foreach ($skipCases as $case) {
	runner_verdict_check(runner_verdict($case[0], $failPattern, $skipPattern) === $case[1], $case[2]);
}

echo "\n== passing output must not be misclassified ==\n";

$passCases = array(
	array("TASK3_AUTHORIZATION=PASS\nPASSED=12, FAILED=0", 'PASS', 'a clean pass is PASS'),
	array('MIGRATION_RESULT=PASS', 'PASS', 'MIGRATION_RESULT=PASS is PASS'),
	array('DDL_EXECUTED=false', 'PASS', 'a boolean false value is not read as a failure'),
	array('EXPECTED_FAIL=1', 'PASS', 'an EXPECTED_FAIL counter is not a self-declared failure'),
	array("  ok   - TEST_RESULT=FAIL still detected", 'PASS', 'a mid-line mention of =FAIL is not a suite verdict'),
	array("  ok   - RESULT=FAIL still detected", 'PASS', 'a mid-line RESULT=FAIL mention is not a suite verdict'),
	array("RUNNER_VERDICT=PASSED=18, FAILED=0\n  ok   - INTEGRATION=FAIL still detected", 'PASS', 'a passing suite that documents =FAIL tokens is PASS'),
);
foreach ($passCases as $case) {
	runner_verdict_check(runner_verdict($case[0], $failPattern, $skipPattern) === $case[1], $case[2]);
}

echo "\nRUNNER_VERDICT=PASSED=$passed, FAILED=$failed\n";
exit($failed === 0 ? 0 : 1);
