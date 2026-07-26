<?php

$report = array('result' => 'FAIL', 'write_executed' => false, 'transaction_committed' => false, 'runtime_enabled' => false, 'clinical_metadata_registration_executed' => false, 'full_clinical_package_imported' => false);
$importer = null;
try {
	if (PHP_SAPI !== 'cli') throw new RuntimeException('cli_only');
	require_once __DIR__ . '/ClinicalSuggestionPackage.php';
	require_once __DIR__ . '/ClinicalSuggestionImporter.php';
	$command = $argv[1] ?? '';
	if (!in_array($command, array('import', 'rollback'), true)) throw new ClinicalSuggestionPackageException('invalid_command');
	$options = array('apply' => false);
	foreach (array_slice($argv, 2) as $argument) {
		if ($argument === '--apply') {
			if ($options['apply']) throw new ClinicalSuggestionPackageException('duplicate_option');
			$options['apply'] = true;
			continue;
		}
		if (preg_match('/^--([a-z][a-z0-9-]*)=(.+)$/', $argument, $matches) !== 1) throw new ClinicalSuggestionPackageException('malformed_option');
		$key = str_replace('-', '_', $matches[1]);
		if (isset($options[$key])) throw new ClinicalSuggestionPackageException('duplicate_option');
		$options[$key] = $matches[2];
	}
	$allowed = array('apply', 'package_root', 'environment', 'batch_reference', 'confirm_database', 'confirm_package_checksum', 'confirm_package_snapshot');
	foreach ($options as $key => $unused) if (!in_array($key, $allowed, true)) throw new ClinicalSuggestionPackageException('unknown_option');
	$environment = strtolower(trim((string) ($options['environment'] ?? '')));
	$batchReference = trim((string) ($options['batch_reference'] ?? ''));
	if (!in_array($environment, array('staging', 'uat'), true)) throw new ClinicalSuggestionPackageException('environment_not_allowed');
	if ($batchReference === '' || strlen($batchReference) > 128
		|| preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8,32}$/', $batchReference) !== 1) {
		throw new ClinicalSuggestionPackageException('unique_batch_reference_required');
	}
	$database = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_NAME') ?: '';
	if (!empty($options['apply']) && ($database === '' || !isset($options['confirm_database']) || !hash_equals($database, (string) $options['confirm_database']))) {
		throw new ClinicalSuggestionPackageException('database_confirmation_mismatch');
	}

	$importer = ClinicalSuggestionImporter::connectFromEnvironment(!empty($options['apply']));
	if ($command === 'rollback') {
		$rollbackChecksum = null;
		$rollbackSnapshot = null;
		if (!empty($options['apply'])) {
			if (!isset($options['package_root'])) throw new ClinicalSuggestionPackageException('package_root_required');
			$rollbackPackage = (new ClinicalSuggestionPackage())->load($options['package_root']);
			if (!isset($options['confirm_package_checksum']) || !hash_equals($rollbackPackage['package_checksum'], (string) $options['confirm_package_checksum'])) throw new ClinicalSuggestionPackageException('package_checksum_confirmation_mismatch');
			if (!isset($options['confirm_package_snapshot']) || !hash_equals($rollbackPackage['package_snapshot_sha256'], (string) $options['confirm_package_snapshot'])) throw new ClinicalSuggestionPackageException('package_snapshot_confirmation_mismatch');
			$freshRollbackPackage = (new ClinicalSuggestionPackage())->load($options['package_root']);
			if (!hash_equals($rollbackPackage['package_checksum'], $freshRollbackPackage['package_checksum'])
				|| !hash_equals($rollbackPackage['package_snapshot_sha256'], $freshRollbackPackage['package_snapshot_sha256'])) {
				throw new ClinicalSuggestionPackageException('package_changed_during_validation');
			}
			$rollbackPackage = $freshRollbackPackage;
			$rollbackChecksum = $rollbackPackage['package_checksum'];
			$rollbackSnapshot = $rollbackPackage['package_snapshot_sha256'];
		}
		$result = $importer->rollback($batchReference, $environment, !empty($options['apply']), $rollbackChecksum, $rollbackSnapshot);
		$report = array_merge($report, $result, array('command' => 'rollback', 'execution_mode' => !empty($options['apply']) ? 'apply' : 'dry-run', 'batch_reference' => $batchReference, 'result' => 'PASS'));
	} else {
		if (!isset($options['package_root'])) throw new ClinicalSuggestionPackageException('package_root_required');
		$package = (new ClinicalSuggestionPackage())->load($options['package_root']);
		if (!empty($options['apply'])) {
			if (!isset($options['confirm_package_checksum']) || !hash_equals($package['package_checksum'], (string) $options['confirm_package_checksum'])) throw new ClinicalSuggestionPackageException('package_checksum_confirmation_mismatch');
			if (!isset($options['confirm_package_snapshot']) || !hash_equals($package['package_snapshot_sha256'], (string) $options['confirm_package_snapshot'])) throw new ClinicalSuggestionPackageException('package_snapshot_confirmation_mismatch');
			$freshPackage = (new ClinicalSuggestionPackage())->load($options['package_root']);
			if (!hash_equals($package['package_checksum'], $freshPackage['package_checksum'])
				|| !hash_equals($package['package_snapshot_sha256'], $freshPackage['package_snapshot_sha256'])) {
				throw new ClinicalSuggestionPackageException('package_changed_during_validation');
			}
			$package = $freshPackage;
			$result = $importer->apply($package, $environment, $batchReference);
		} else {
			$plan = $importer->plan($package, $environment, $batchReference);
			$result = array('counts' => $plan['counts'], 'write_executed' => false, 'transaction_committed' => false, 'idempotent' => $plan['idempotent']);
		}
		$report = array_merge($report, $result, array(
			'command' => 'import',
			'execution_mode' => !empty($options['apply']) ? 'apply' : 'dry-run',
			'batch_reference' => $batchReference,
			'package_checksum' => $package['package_checksum'],
			'package_snapshot_sha256' => $package['package_snapshot_sha256'],
			'datasets' => $package['datasets'],
			'source_counts' => $package['counts'],
			'result' => 'PASS',
		));
	}
} catch (Throwable $exception) {
	$code = $exception instanceof ClinicalSuggestionPackageException ? $exception->getSafeCode() : 'internal_execution_failure';
	$report['safe_error_code'] = $code;
} finally {
	if ($importer instanceof ClinicalSuggestionImporter) $importer->close();
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($report['result'] === 'PASS' ? 0 : 1);
