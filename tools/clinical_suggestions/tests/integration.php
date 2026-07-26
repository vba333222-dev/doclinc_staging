<?php

$root = dirname(__DIR__, 3);
require_once $root . '/tools/clinical_suggestions/ClinicalSuggestionPackage.php';
require_once $root . '/tools/clinical_suggestions/ClinicalSuggestionImporter.php';
$packageRoot = null;
foreach (array_slice($argv, 1) as $argument) if (strpos($argument, '--package-root=') === 0) $packageRoot = substr($argument, 15);
if ($packageRoot === null) {
	fwrite(STDERR, "PACKAGE_ROOT_REQUIRED\n");
	exit(2);
}

$batch = 'uat-integration-' . gmdate('Ymd\\THis\\Z') . '-' . bin2hex(random_bytes(6));
$importer = null;
$stage = 'bootstrap';
try {
	$stage = 'package';
	$package = (new ClinicalSuggestionPackage())->load($packageRoot);
	$stage = 'connection';
	$importer = ClinicalSuggestionImporter::connectFromEnvironment(true);
	$stage = 'dry_run';
	$stateDb = new mysqli(
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_HOST') ?: 'localhost',
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_USER'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_NAME'),
		(int) (getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PORT') ?: 3306)
	);
	$stateDb->set_charset('utf8mb4');
	$beforeDryRun = $stateDb->query('SELECT (SELECT COUNT(*) FROM clinical_suggestion_import_batches) AS batches,(SELECT COUNT(*) FROM clinical_suggestion_terms) AS terms,(SELECT COUNT(*) FROM clinical_suggestion_aliases) AS aliases')->fetch_assoc();
	$plan = $importer->plan($package, 'uat', $batch);
	if ($plan['counts']['inserted'] !== 11450 || $plan['counts']['aliases_inserted'] !== 701) throw new RuntimeException('dry_run_count_mismatch');
	$afterDryRun = $stateDb->query('SELECT (SELECT COUNT(*) FROM clinical_suggestion_import_batches) AS batches,(SELECT COUNT(*) FROM clinical_suggestion_terms) AS terms,(SELECT COUNT(*) FROM clinical_suggestion_aliases) AS aliases')->fetch_assoc();
	if ($beforeDryRun !== $afterDryRun) throw new RuntimeException('dry_run_wrote_rows');
	$stage = 'failed_apply_rollback';
	$badPackage = $package;
	$badPackage['terms'][0]['source_dataset'] = str_repeat('x', 129);
	$badBatch = 'uat-failed-apply-' . gmdate('Ymd\\THis\\Z') . '-' . bin2hex(random_bytes(6));
	$failedApplyRolledBack = false;
	try {
		$importer->apply($badPackage, 'uat', $badBatch);
	} catch (ClinicalSuggestionPackageException $exception) {
		$failedApplyRolledBack = $exception->getSafeCode() === 'import_transaction_rolled_back';
	}
	$afterFailedApply = $stateDb->query('SELECT (SELECT COUNT(*) FROM clinical_suggestion_import_batches) AS batches,(SELECT COUNT(*) FROM clinical_suggestion_terms) AS terms,(SELECT COUNT(*) FROM clinical_suggestion_aliases) AS aliases')->fetch_assoc();
	if (!$failedApplyRolledBack || $beforeDryRun !== $afterFailedApply) throw new RuntimeException('failed_apply_left_partial_rows');
	$stateDb->close();
	$stage = 'apply';
	$applied = $importer->apply($package, 'uat', $batch);
	if (!$applied['transaction_committed'] || $applied['counts']['inserted'] !== 11450) throw new RuntimeException('apply_failed');
	$stage = 'search';
	$verifyDb = new mysqli(
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_HOST') ?: 'localhost',
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_USER'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_NAME'),
		(int) (getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PORT') ?: 3306)
	);
	$verifyDb->set_charset('utf8mb4');
	$typeCounts = array();
	foreach ($verifyDb->query("SELECT term_type,COUNT(*) AS total FROM clinical_suggestion_terms WHERE environment_scope='uat' AND active_state=1 GROUP BY term_type")->fetch_all(MYSQLI_ASSOC) as $row) $typeCounts[$row['term_type']] = (int) $row['total'];
	if ($typeCounts !== array('complaint' => 35, 'diagnosis' => 10658, 'medicine' => 663, 'symptom' => 94)) throw new RuntimeException('type_distribution_mismatch');
	$aliasHit = $verifyDb->query("SELECT t.suggestion_term_id FROM clinical_suggestion_terms t INNER JOIN clinical_suggestion_aliases a ON a.suggestion_term_id=t.suggestion_term_id WHERE t.term_type='diagnosis' AND a.normalized_alias LIKE 'gastro%' AND a.active_state=1 LIMIT 1")->fetch_assoc();
	$medicineHit = $verifyDb->query("SELECT suggestion_term_id FROM clinical_suggestion_terms WHERE term_type='medicine' AND normalized_label LIKE 'aba%' AND active_state=1 LIMIT 1")->fetch_assoc();
	if (!$aliasHit || !$medicineHit) throw new RuntimeException('search_fixture_missing');
	$verifyDb->close();
	$stage = 'idempotency';
	$idempotent = $importer->apply($package, 'uat', $batch);
	if (!$idempotent['idempotent'] || $idempotent['write_executed']) throw new RuntimeException('idempotency_failed');
	$newBatchRejected = false;
	try {
		$importer->plan($package, 'uat', 'uat-integration-' . gmdate('Ymd\\THis\\Z') . '-' . bin2hex(random_bytes(6)));
	} catch (ClinicalSuggestionPackageException $exception) {
		$newBatchRejected = $exception->getSafeCode() === 'existing_term_owned_by_other_batch';
	}
	if (!$newBatchRejected) throw new RuntimeException('other_batch_ownership_not_rejected');
	$stage = 'rollback_plan';
	$rollbackPlan = $importer->rollback($batch, 'uat', false);
	if ($rollbackPlan['terms'] !== 11450 || $rollbackPlan['write_executed']) throw new RuntimeException('rollback_plan_failed');
	$stage = 'rollback_isolation_fixture';
	$isolationDb = new mysqli(
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_HOST') ?: 'localhost',
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_USER'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_NAME'),
		(int) (getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PORT') ?: 3306)
	);
	$isolationDb->set_charset('utf8mb4');
	$otherBatch = 'uat-isolation-' . gmdate('Ymd\\THis\\Z') . '-' . bin2hex(random_bytes(6));
	$stmt = $isolationDb->prepare("INSERT INTO clinical_suggestion_import_batches (batch_reference,package_id,package_version,package_checksum,package_snapshot_sha256,environment_scope,batch_state,term_count,alias_count) VALUES (?,?,?,?,?,'uat','applied',1,0)");
	$stmt->bind_param('sssss', $otherBatch, $package['package_id'], $package['package_version'], $package['package_checksum'], $package['package_snapshot_sha256']);
	$stmt->execute();
	$otherBatchId = (int) $isolationDb->insert_id;
	$otherKey = 'integration-only-' . bin2hex(random_bytes(6));
	$otherLabel = 'Integration isolation term';
	$otherNormalized = ClinicalSuggestionPackage::normalize($otherLabel);
	$sourceName = 'Disposable integration fixture';
	$sourceVersion = 'test';
	$sourceDataset = 'integration_fixture';
	$sourceGovernance = 'disposable_test_only';
	$stmt = $isolationDb->prepare("INSERT INTO clinical_suggestion_terms (suggestion_import_batch_id,term_type,reference_key,term_code,preferred_label,normalized_label,source_name,source_version,source_dataset,source_governance_status,environment_scope,active_state) VALUES (?,'complaint',?,'',?,?,?,?,?,?,'uat',1)");
	$stmt->bind_param('isssssss', $otherBatchId, $otherKey, $otherLabel, $otherNormalized, $sourceName, $sourceVersion, $sourceDataset, $sourceGovernance);
	$stmt->execute();
	$stage = 'rollback_apply';
	$rolledBack = $importer->rollback($batch, 'uat', true, $package['package_checksum'], $package['package_snapshot_sha256']);
	if (!$rolledBack['transaction_committed']) throw new RuntimeException('rollback_failed');
	$otherStillPresent = $isolationDb->query('SELECT COUNT(*) AS total FROM clinical_suggestion_terms WHERE suggestion_import_batch_id=' . $otherBatchId)->fetch_assoc();
	$otherStillApplied = $isolationDb->query('SELECT batch_state FROM clinical_suggestion_import_batches WHERE suggestion_import_batch_id=' . $otherBatchId)->fetch_assoc();
	if ((int) $otherStillPresent['total'] !== 1 || !$otherStillApplied || $otherStillApplied['batch_state'] !== 'applied') throw new RuntimeException('rollback_deleted_other_batch');
	$importer->rollback($otherBatch, 'uat', true, $package['package_checksum'], $package['package_snapshot_sha256']);
	$isolationDb->close();
	$stage = 'natural_key_conflict';
	$conflictDb = new mysqli(
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_HOST') ?: 'localhost',
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_USER'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_NAME'),
		(int) (getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PORT') ?: 3306)
	);
	$conflictDb->set_charset('utf8mb4');
	$conflictReference = $batch . '-conflict';
	$stmt = $conflictDb->prepare("INSERT INTO clinical_suggestion_import_batches (batch_reference,package_id,package_version,package_checksum,package_snapshot_sha256,environment_scope,batch_state,term_count,alias_count) VALUES (?,?,?,?,?,'uat','applied',1,0)");
	$stmt->bind_param('sssss', $conflictReference, $package['package_id'], $package['package_version'], $package['package_checksum'], $package['package_snapshot_sha256']);
	$stmt->execute();
	$conflictBatchId = (int) $conflictDb->insert_id;
	$first = $package['terms'][0];
	$conflictLabel = $first['label'] . ' conflict';
	$conflictNormalized = ClinicalSuggestionPackage::normalize($conflictLabel);
	$stmt = $conflictDb->prepare("INSERT INTO clinical_suggestion_terms (suggestion_import_batch_id,term_type,reference_key,term_code,preferred_label,normalized_label,source_name,source_version,source_dataset,source_governance_status,environment_scope,active_state) VALUES (?,?,?,?,?,?,?,?,?,?,'uat',1)");
	$stmt->bind_param('isssssssss', $conflictBatchId, $first['type'], $first['reference_key'], $first['code'], $conflictLabel, $conflictNormalized, $first['source_name'], $first['source_version'], $first['source_dataset'], $first['source_governance_status']);
	$stmt->execute();
	$conflictRejected = false;
	try {
		$importer->plan($package, 'uat', $batch . '-new');
	} catch (ClinicalSuggestionPackageException $exception) {
		$conflictRejected = in_array($exception->getSafeCode(), array('existing_term_conflict', 'existing_term_owned_by_other_batch'), true);
	}
	$conflictDb->query('DELETE FROM clinical_suggestion_terms WHERE suggestion_import_batch_id=' . $conflictBatchId);
	$conflictDb->query("UPDATE clinical_suggestion_import_batches SET batch_state='rolled_back',rolled_back_at=CURRENT_TIMESTAMP(6) WHERE suggestion_import_batch_id=" . $conflictBatchId);
	$conflictDb->close();
	if (!$conflictRejected) throw new RuntimeException('natural_key_conflict_not_rejected');
	echo "DISPOSABLE_INTEGRATION=PASS\nREFERENCE_SEARCH=PASS\nIMPORTER_DRY_RUN_ZERO_WRITE=PASS\nFAILED_APPLY_ZERO_PARTIAL_ROWS=PASS\nIMPORTER_IDEMPOTENCY=PASS\nIMPORTER_ROLLBACK=PASS\nROLLBACK_OTHER_BATCH_ISOLATION=PASS\nNATURAL_KEY_CONFLICT_NEGATIVE=PASS\n";
} catch (Throwable $exception) {
	fwrite(STDERR, "DISPOSABLE_INTEGRATION=FAIL\nSAFE_ERROR_CODE=integration_" . $stage . "_failed\n");
	exit(1);
} finally {
	if ($importer instanceof ClinicalSuggestionImporter) $importer->close();
}
