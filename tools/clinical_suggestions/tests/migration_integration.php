<?php

$root = dirname(__DIR__, 3);
$migration = $root . '/application/migrations/20260726000100_clinical_suggestions_uat_foundation.php';
$database = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_NAME') ?: '';
$testAllowed = filter_var(getenv('DOCLINC_CLINICAL_SUGGESTION_DISPOSABLE_TEST') ?: false, FILTER_VALIDATE_BOOLEAN);
if (!$testAllowed || preg_match('/(?:^|[_-])test(?:$|[_-])/', strtolower($database)) !== 1) {
	fwrite(STDERR, "MIGRATION_INTEGRATION=FAIL\nSAFE_ERROR_CODE=disposable_test_database_required\n");
	exit(2);
}

function run_migration_process($migration, array $arguments)
{
	$command = array_merge(array(PHP_BINARY, $migration), $arguments);
	$process = proc_open(
		$command,
		array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
		$pipes,
		null,
		null,
		array('bypass_shell' => true)
	);
	if (!is_resource($process)) return array(255, '', 'process_start_failed');
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return array(proc_close($process), $stdout, $stderr);
}

function foundation_table_count(mysqli $db)
{
	$result = $db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('clinical_suggestion_import_batches','clinical_suggestion_terms','clinical_suggestion_aliases')")->fetch_assoc();
	return (int) $result['total'];
}

function remove_foundation_tables(mysqli $db)
{
	$db->query('DROP TABLE IF EXISTS clinical_suggestion_aliases');
	$db->query('DROP TABLE IF EXISTS clinical_suggestion_terms');
	$db->query('DROP TABLE IF EXISTS clinical_suggestion_import_batches');
}

$stage = 'connection';
try {
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	$db = new mysqli(
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_HOST') ?: 'localhost',
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_USER'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD'),
		$database,
		(int) (getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PORT') ?: 3306)
	);
	$db->set_charset('utf8mb4');
	$stage = 'disposable_cleanup';
	remove_foundation_tables($db);

	$stage = 'plan';
	$beforePlan = foundation_table_count($db);
	list($planCode, $planOut) = run_migration_process($migration, array());
	$afterPlan = foundation_table_count($db);
	if ($planCode !== 0 || strpos($planOut, 'EXECUTION_MODE=PLAN') === false || strpos($planOut, 'DDL_EXECUTED=false') === false || $beforePlan !== $afterPlan) {
		throw new RuntimeException('plan_not_zero_write');
	}

	$applyArguments = array('--apply', '--confirm-database=' . $database, '--environment=uat', '--backup-reference=disposable-test-backup');
	$stage = 'apply';
	list($applyCode, $applyOut, $applyErr) = run_migration_process($migration, $applyArguments);
	if ($applyCode !== 0 || strpos($applyOut, 'DDL_EXECUTED=true') === false || foundation_table_count($db) !== 3) {
		throw new RuntimeException('apply_failed');
	}

	$stage = 'idempotency';
	list($secondCode, $secondOut) = run_migration_process($migration, $applyArguments);
	if ($secondCode !== 0 || strpos($secondOut, 'ALREADY_APPLIED=true') === false || strpos($secondOut, 'DDL_EXECUTED=false') === false) {
		throw new RuntimeException('idempotency_failed');
	}

	$stage = 'partial_schema';
	$db->query('DROP TABLE clinical_suggestion_aliases');
	$db->query('DROP TABLE clinical_suggestion_terms');
	list($partialCode, $partialOut, $partialErr) = run_migration_process($migration, $applyArguments);
	if ($partialCode === 0 || strpos($partialErr, 'SAFE_ERROR_CODE=partial_schema_collision') === false || foundation_table_count($db) !== 1) {
		throw new RuntimeException('partial_schema_not_closed');
	}

	$stage = 'restore';
	remove_foundation_tables($db);
	list($restoreCode, $restoreOut) = run_migration_process($migration, $applyArguments);
	if ($restoreCode !== 0 || foundation_table_count($db) !== 3) throw new RuntimeException('restore_failed');

	echo "MIGRATION_INTEGRATION=PASS\nMIGRATION_PLAN_ZERO_WRITE=PASS\nMIGRATION_APPLY_EXPLICIT=PASS\nMIGRATION_IDEMPOTENCY=PASS\nPARTIAL_SCHEMA_NEGATIVE=PASS\n";
} catch (Throwable $exception) {
	fwrite(STDERR, "MIGRATION_INTEGRATION=FAIL\nSAFE_ERROR_CODE=migration_" . $stage . "_failed\n");
	exit(1);
} finally {
	if (isset($db) && $db instanceof mysqli) $db->close();
}
