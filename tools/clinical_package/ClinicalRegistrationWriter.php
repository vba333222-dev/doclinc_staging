<?php

class ClinicalRegistrationWriter
{
	const EXPECTED_ENTITY_COUNT = 890;

	private $repository;
	private $planner;
	private $pins;
	private $failureInjector;

	public function __construct(ClinicalRegistrationWriteRepository $repository, ClinicalRegistrationPlanner $planner, array $pins, $failureInjector = null)
	{
		if ($failureInjector !== null && !is_callable($failureInjector)) throw new InvalidArgumentException('failure injector must be callable');
		$this->repository = $repository;
		$this->planner = $planner;
		$this->pins = $pins;
		$this->failureInjector = $failureInjector;
	}

	public static function assertPreConnectionApplyGates(array $model, array $options, array $config)
	{
		if (empty($options['apply'])) return;
		$enabledName = $config['metadata_write_enabled_environment'];
		if (getenv($enabledName) !== 'true') throw new ClinicalPackageException('metadata_write_disabled', 'Metadata registration writes are disabled.');
		foreach (array('confirm_database','confirm_package_checksum','confirm_package_snapshot','confirm_metadata_entity_count','registration_reference') as $field) {
			if (!isset($options[$field]) || $options[$field] === '') throw new ClinicalPackageException('apply_confirmation_missing', 'All metadata registration apply confirmations are required.');
		}
		$databaseName = getenv($config['write_database_environment_variables']['database']);
		if ($databaseName === false || $databaseName === '' || !hash_equals((string) $databaseName, (string) $options['confirm_database'])) {
			throw new ClinicalPackageException('database_confirmation_mismatch', 'Database confirmation does not match the configured target.');
		}
		if (!hash_equals($model['package_checksum'], (string) $options['confirm_package_checksum'])) {
			throw new ClinicalPackageException('package_checksum_confirmation_mismatch', 'Package checksum confirmation does not match.');
		}
		if (!hash_equals($model['package_snapshot_sha256'], (string) $options['confirm_package_snapshot'])) {
			throw new ClinicalPackageException('package_snapshot_confirmation_mismatch', 'Package snapshot confirmation does not match.');
		}
		if (!is_string($options['confirm_metadata_entity_count']) || $options['confirm_metadata_entity_count'] !== (string) self::EXPECTED_ENTITY_COUNT) {
			throw new ClinicalPackageException('metadata_entity_count_confirmation_mismatch', 'Metadata entity-count confirmation does not match.');
		}
		self::assertRegistrationReference($options['registration_reference']);
	}

	public function execute(array $initialModel, $revalidate, array $options, $lockTimeoutSeconds)
	{
		if (!is_callable($revalidate)) throw new InvalidArgumentException('revalidation callback must be callable');
		$this->assertModel($initialModel);
		$freshModel = call_user_func($revalidate);
		$this->assertModel($freshModel);
		$this->assertSameModel($initialModel, $freshModel);
		if (empty($options['apply'])) {
			$initialComparison = $this->planner->compare($freshModel, $this->repository);
			return $this->dryRunResult($initialComparison, $this->existingRows($freshModel));
		}
		if (!hash_equals($this->repository->databaseName(), (string) $options['confirm_database'])) {
			throw new ClinicalPackageException('database_confirmation_mismatch', 'Database confirmation does not match the authenticated target.');
		}
		self::assertRegistrationReference($options['registration_reference']);
		$timeout = filter_var($lockTimeoutSeconds, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 60)));
		if ($timeout === false) throw new ClinicalPackageException('registration_lock_timeout_invalid', 'Metadata registration lock timeout is invalid.');
		$lockName = 'doclink:clinical:' . substr(hash('sha256', $this->repository->databaseName() . "\0" . $freshModel['package']['persisted']['package_key'] . "\0" . $freshModel['package']['persisted']['package_version'] . "\0" . $freshModel['package_checksum']), 0, 47);
		$lockHeld = false;
		$result = null;
		$primaryFailure = null;
		$cleanupStatus = null;
		try {
			$this->repository->acquireLock($lockName, (int) $timeout);
			$lockHeld = true;
			$result = $this->executeLocked($initialModel, $revalidate, $options);
		} catch (Throwable $failure) {
			$primaryFailure = $failure;
		}
		if ($lockHeld) {
			try {
				$this->repository->releaseLock($lockName);
				$cleanupStatus = 'released';
			} catch (Throwable $releaseFailure) {
				$cleanupStatus = 'connection_closed';
				try {
					$this->repository->close();
				} catch (Throwable $closeFailure) {
					$cleanupStatus = 'connection_close_unconfirmed';
				}
				if ($result !== null) {
					$result['lock_cleanup_status'] = $cleanupStatus;
					$result['cleanup_warning'] = 'registration_lock_release_unconfirmed';
				} elseif ($primaryFailure !== null) {
					$primaryFailure = $this->withCleanupContext($primaryFailure, $cleanupStatus);
				}
			}
		}
		if ($primaryFailure !== null) throw $primaryFailure;
		if ($result === null) throw new ClinicalPackageException('registration_transaction_failed', 'Metadata registration outcome is unavailable.');
		if (!isset($result['lock_cleanup_status'])) $result['lock_cleanup_status'] = $cleanupStatus;
		return $result;
	}

	private function executeLocked(array $initialModel, $revalidate, array $options)
	{
		$transactionStarted = false;
		$stateBefore = null;
		$existingRows = null;
		$conflictCount = 0;
		try {
			$this->repository->begin();
			$transactionStarted = true;
			$model = call_user_func($revalidate);
			$this->assertModel($model);
			$this->assertSameModel($initialModel, $model);
			$comparison = $this->planner->compare($model, $this->repository);
			$stateBefore = $comparison['package_state'];
			$conflictCount = $comparison['conflict_entity_count'];
			$existingRows = $this->existingRows($model);
			if ($stateBefore === 'conflict') {
				throw new ClinicalPackageException('registration_state_conflict', 'Existing metadata conflicts with the registration plan.');
			}
			if ($stateBefore === 'exact_match') {
				$finalModel = call_user_func($revalidate);
				try {
					$this->assertModel($finalModel);
					$this->assertSameModel($initialModel, $finalModel);
				} catch (ClinicalPackageException $exception) {
					throw new ClinicalPackageException('package_changed_during_registration', 'Package content changed during metadata registration.');
				}
				$this->repository->insertAudit($this->auditRow($model, $options['registration_reference'], 'exact_match', 'exact_match', 'idempotent_noop', 0, 1, null));
				$this->repository->commit();
				$transactionStarted = false;
				return $this->result('idempotent_noop', 0, 1, true, $existingRows, 0);
			}
			if ($stateBefore !== 'new') throw new ClinicalPackageException('registration_state_conflict', 'Registration state is not writable.');
			$written = $this->insertGraph($model);
			if ($written !== self::EXPECTED_ENTITY_COUNT) throw new ClinicalPackageException('metadata_row_count_mismatch', 'Metadata registration row count did not match the controlled plan.');
			$this->inject('before_postwrite_verification');
			$postComparison = $this->planner->compare($model, $this->repository);
			if ($postComparison['package_state'] !== 'exact_match' || $postComparison['exact_match_entity_count'] !== self::EXPECTED_ENTITY_COUNT || $postComparison['conflict_entity_count'] !== 0) {
				throw new ClinicalPackageException('registration_postwrite_verification_failed', 'Metadata registration verification failed.');
			}
			$finalModel = call_user_func($revalidate);
			try {
				$this->assertModel($finalModel);
				$this->assertSameModel($initialModel, $finalModel);
			} catch (ClinicalPackageException $exception) {
				throw new ClinicalPackageException('package_changed_during_registration', 'Package content changed during metadata registration.');
			}
			$this->repository->insertAudit($this->auditRow($model, $options['registration_reference'], 'new', 'exact_match', 'committed', self::EXPECTED_ENTITY_COUNT, 1, null));
			$this->inject('before_commit');
			$this->repository->commit();
			$transactionStarted = false;
			return $this->result('committed', self::EXPECTED_ENTITY_COUNT, 1, true, $existingRows, 0);
		} catch (Throwable $failure) {
			$primary = $failure instanceof ClinicalPackageException ? $failure : new ClinicalPackageException('registration_transaction_failed', 'Metadata registration transaction failed.');
			if (!$transactionStarted) throw $primary;
			try {
				$this->repository->rollback();
				$transactionStarted = false;
			} catch (Throwable $rollbackFailure) {
				throw new ClinicalPackageException($primary->getSafeCode(), 'Metadata registration failed and rollback could not be confirmed.', $this->failureContext(
					$primary, 'failed', $existingRows, $conflictCount, 0, false, 'registration_rollback_failed'
				));
			}
			$result = $primary->getSafeCode() === 'registration_state_conflict' ? 'rejected' : 'rolled_back';
			$before = $stateBefore;
			$after = $result === 'rejected' ? 'conflict' : null;
			$auditWritten = 0;
			try {
				$this->repository->begin();
				$this->repository->insertAudit($this->auditRow($initialModel, $options['registration_reference'], $before, $after, $result, 0, 0, $primary->getSafeCode()));
				$this->repository->commit();
				$auditWritten = 1;
			} catch (Throwable $auditFailure) {
				try { $this->repository->rollback(); } catch (Throwable $ignored) {}
				throw new ClinicalPackageException($primary->getSafeCode(), 'Metadata registration failed and audit evidence could not be persisted.', array(
					'secondary_error' => 'metadata_rollback_audit_failed',
					'registration_result' => $result,
					'planned_metadata_rows' => self::EXPECTED_ENTITY_COUNT,
					'existing_metadata_rows' => $existingRows,
					'conflict_count' => $conflictCount,
					'metadata_rows_written' => 0,
					'audit_rows_written' => 0,
					'transaction_committed' => false,
					'write_executed' => false,
				));
			}
			throw new ClinicalPackageException($primary->getSafeCode(), 'Metadata registration did not commit.', array(
				'registration_result' => $result,
				'planned_metadata_rows' => self::EXPECTED_ENTITY_COUNT,
				'existing_metadata_rows' => $existingRows,
				'conflict_count' => $conflictCount,
				'metadata_rows_written' => 0,
				'audit_rows_written' => $auditWritten,
				'transaction_committed' => false,
				'write_executed' => $auditWritten === 1,
			));
		}
	}

	private function failureContext(ClinicalPackageException $primary, $registrationResult, $existingRows, $conflictCount, $auditRows, $writeExecuted, $secondaryError = null)
	{
		$context = $primary->getSafeContext();
		$context += array(
			'registration_result' => $registrationResult,
			'planned_metadata_rows' => self::EXPECTED_ENTITY_COUNT,
			'existing_metadata_rows' => $existingRows,
			'conflict_count' => $conflictCount,
			'metadata_rows_written' => 0,
			'audit_rows_written' => $auditRows,
			'transaction_committed' => false,
			'write_executed' => $writeExecuted,
		);
		if ($secondaryError !== null) $context['secondary_error'] = $secondaryError;
		return $context;
	}

	private function withCleanupContext(Throwable $failure, $cleanupStatus)
	{
		$primary = $failure instanceof ClinicalPackageException
			? $failure
			: new ClinicalPackageException('registration_transaction_failed', 'Metadata registration transaction failed.');
		$context = $primary->getSafeContext();
		if (!isset($context['secondary_error'])) $context['secondary_error'] = 'registration_lock_release_unconfirmed';
		else $context['cleanup_warning'] = 'registration_lock_release_unconfirmed';
		$context['lock_cleanup_status'] = $cleanupStatus;
		return new ClinicalPackageException($primary->getSafeCode(), $primary->getMessage(), $context);
	}

	private function insertGraph(array $model)
	{
		$observedAt = gmdate('Y-m-d H:i:s') . '.000000';
		$packageId = $this->repository->insertPackage($model['package']['persisted'], $observedAt);
		$written = 1;
		$this->inject('after_package');
		foreach ($model['package_capabilities'] as $row) {
			$this->repository->insertPackageCapability($packageId, $row['persisted']);
			$written++;
		}
		$this->inject('after_package_capabilities');
		$datasetIds = array();
		$datasetCount = count($model['datasets']);
		foreach ($model['datasets'] as $index => $dataset) {
			$key = $dataset['persisted']['dataset_key'];
			$datasetIds[$key] = $this->repository->insertDataset($packageId, $dataset['persisted']);
			$written++;
			if ($index === 0) $this->inject('after_first_dataset');
			if ($index + 1 === (int) ceil($datasetCount / 2)) $this->inject('dataset_midpoint');
		}
		$this->inject('after_datasets');
		$capabilityPosition = 0;
		$capabilityTotal = $model['counts']['dataset_capabilities'];
		foreach ($model['datasets'] as $dataset) {
			$datasetId = $datasetIds[$dataset['persisted']['dataset_key']];
			foreach ($dataset['capabilities'] as $row) {
				$this->repository->insertDatasetCapability($datasetId, $row['persisted']);
				$written++;
				$capabilityPosition++;
				if ($capabilityPosition === 1) $this->inject('after_first_dataset_capability');
				if ($capabilityPosition === (int) ceil($capabilityTotal / 2)) $this->inject('dataset_capability_midpoint');
			}
		}
		$this->inject('after_dataset_capabilities');
		$fieldPosition = 0;
		$fieldTotal = $model['counts']['field_contracts'];
		foreach ($model['datasets'] as $dataset) {
			$datasetId = $datasetIds[$dataset['persisted']['dataset_key']];
			foreach ($dataset['field_contracts'] as $row) {
				$this->repository->insertFieldContract($datasetId, $row['persisted']);
				$written++;
				$fieldPosition++;
				if ($fieldPosition === 1) $this->inject('after_first_field_contract');
				if ($fieldPosition === (int) ceil($fieldTotal / 2)) $this->inject('field_contract_midpoint');
			}
		}
		$this->inject('after_field_contracts');
		return $written;
	}

	private function assertModel(array $model)
	{
		$expectedTopLevel = array('package','package_capabilities','datasets','audit_plan','counts','package_checksum_profile','package_checksum','package_file_count','package_snapshot_sha256');
		if (array_keys($model) !== $expectedTopLevel) throw new ClinicalPackageException('metadata_plan_shape_invalid', 'Metadata registration plan shape is not supported.');
		$packageFields = array('package_key','package_version','manifest_checksum','manifest_schema_version','source_status','governance_status','production_ready','source_runtime_enabled','license_disposition','declared_dataset_count','observed_dataset_count','declared_record_count','observed_record_count','source_reference','package_checksum','package_checksum_profile');
		$datasetFields = array('dataset_key','source_file','dataset_version','dataset_checksum','domain_key','entity_key','source_governance_status','governance_status','source_runtime_enabled','license_disposition','declared_record_count','observed_record_count','seed_order','natural_key_contract','hierarchy_mode');
		$capabilityFields = array('capability','decision_status','decision_reason','decision_actor','decided_at');
		$fieldFields = array('source_field','source_json_type','cardinality','required_flag','nullable_flag','identity_role','target_domain','target_entity','target_attribute','transform_policy','contract_status','review_reason','decision_actor','decided_at');
		if (!isset($model['package']['natural_key'], $model['package']['persisted'])
			|| array_keys($model['package']) !== array('natural_key','persisted')
			|| array_keys($model['package']['persisted']) !== $packageFields) {
			throw new ClinicalPackageException('metadata_plan_shape_invalid', 'Metadata registration package shape is not supported.');
		}
		$expectedCounts = array('package_rows'=>1,'dataset_rows'=>47,'package_capabilities'=>8,'dataset_capabilities'=>376,'field_contracts'=>458);
		if ($model['counts'] !== $expectedCounts || array_sum($model['counts']) !== self::EXPECTED_ENTITY_COUNT
			|| count($model['package_capabilities']) !== 8 || count($model['datasets']) !== 47) {
			throw new ClinicalPackageException('metadata_entity_count_invalid', 'Metadata registration plan does not contain the controlled entity total.');
		}
		$fieldCount = 0;
		$capabilityCount = 0;
		$previousDataset = null;
		$expectedCapabilities = array('audit_import','canonical_storage','clinical_selection','decision_support','prescribing','production_deployment','redistribution','runtime_reference');
		$actualPackageCapabilities = array();
		foreach ($model['package_capabilities'] as $row) {
			if (array_keys($row) !== array('natural_key','persisted') || array_keys($row['persisted']) !== $capabilityFields) {
				throw new ClinicalPackageException('metadata_plan_shape_invalid', 'Metadata registration package-capability shape is not supported.');
			}
			$actualPackageCapabilities[] = $row['persisted']['capability'];
		}
		if ($actualPackageCapabilities !== $expectedCapabilities) throw new ClinicalPackageException('metadata_plan_order_invalid', 'Metadata registration package-capability order is invalid.');
		foreach ($model['datasets'] as $dataset) {
			if (array_keys($dataset) !== array('natural_key','persisted','source_evidence','capabilities','field_contracts')
				|| array_keys($dataset['persisted']) !== $datasetFields) {
				throw new ClinicalPackageException('metadata_plan_shape_invalid', 'Metadata registration dataset shape is not supported.');
			}
			if ($previousDataset !== null && strcmp($previousDataset, $dataset['persisted']['dataset_key']) >= 0) {
				throw new ClinicalPackageException('metadata_plan_order_invalid', 'Metadata registration dataset order is invalid.');
			}
			$previousDataset = $dataset['persisted']['dataset_key'];
			$datasetCapabilities = array();
			foreach ($dataset['capabilities'] as $row) {
				if (array_keys($row) !== array('natural_key','persisted') || array_keys($row['persisted']) !== $capabilityFields) {
					throw new ClinicalPackageException('metadata_plan_shape_invalid', 'Metadata registration dataset-capability shape is not supported.');
				}
				$datasetCapabilities[] = $row['persisted']['capability'];
			}
			if ($datasetCapabilities !== $expectedCapabilities) throw new ClinicalPackageException('metadata_plan_order_invalid', 'Metadata registration dataset-capability order is invalid.');
			foreach ($dataset['field_contracts'] as $row) {
				if (array_keys($row) !== array('natural_key','persisted','observation') || array_keys($row['persisted']) !== $fieldFields) {
					throw new ClinicalPackageException('metadata_plan_shape_invalid', 'Metadata registration field-contract shape is not supported.');
				}
			}
			$capabilityCount += count($dataset['capabilities']);
			$fieldCount += count($dataset['field_contracts']);
		}
		if ($capabilityCount !== 376 || $fieldCount !== 458) throw new ClinicalPackageException('metadata_entity_count_invalid', 'Metadata registration child entity count is invalid.');
		$package = $model['package']['persisted'];
		$pinValues = array(
			'package_key' => $package['package_key'],
			'package_version' => $package['package_version'],
			'manifest_checksum' => $package['manifest_checksum'],
			'manifest_schema_version' => $package['manifest_schema_version'],
			'package_checksum' => $model['package_checksum'],
			'package_checksum_profile' => $model['package_checksum_profile'],
			'package_file_count' => $model['package_file_count'],
			'package_snapshot_sha256' => $model['package_snapshot_sha256'],
			'declared_dataset_count' => $package['declared_dataset_count'],
			'observed_dataset_count' => $package['observed_dataset_count'],
			'declared_record_count' => $package['declared_record_count'],
			'observed_record_count' => $package['observed_record_count'],
		);
		foreach ($this->pins as $key => $expected) {
			if (!array_key_exists($key, $pinValues) || $pinValues[$key] !== $expected) throw new ClinicalPackageException('metadata_package_pin_mismatch', 'Metadata registration package pin does not match.');
		}
		if ($package['production_ready'] !== 0 || $package['source_runtime_enabled'] !== 0
			|| $model['audit_plan']['canonical_record_plan_count'] !== 0 || $model['audit_plan']['import_item_row_plan_count'] !== 0) {
			throw new ClinicalPackageException('metadata_runtime_policy_rejected', 'Metadata registration cannot import records or enable runtime behavior.');
		}
	}

	private function assertSameModel(array $expected, array $actual)
	{
		if (!hash_equals(hash('sha256', serialize($expected)), hash('sha256', serialize($actual)))) {
			throw new ClinicalPackageException('package_changed_during_registration', 'Package content changed during metadata registration.');
		}
	}

	private static function assertRegistrationReference($reference)
	{
		if (!is_string($reference) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$/D', $reference) !== 1) {
			throw new ClinicalPackageException('registration_reference_invalid', 'Registration reference is invalid.');
		}
	}

	private function existingRows(array $model)
	{
		$graph = $this->repository->fetchGraph($model['package']['persisted']['package_key'], $model['package']['persisted']['package_version'], $model['package_checksum']);
		$packageIds = array();
		foreach (array_merge($graph['package_identity_rows'], $graph['package_checksum_rows']) as $row) {
			if (isset($row['clinical_master_package_id'])) $packageIds[(string) $row['clinical_master_package_id']] = true;
		}
		return count($packageIds) + count($graph['datasets']) + count($graph['package_capabilities']) + count($graph['dataset_capabilities']) + count($graph['field_contracts']);
	}

	private function dryRunResult(array $comparison, $existingRows)
	{
		return array(
			'registration_result' => $comparison['package_state'],
			'dry_run' => true,
			'planned_metadata_rows' => self::EXPECTED_ENTITY_COUNT,
			'existing_metadata_rows' => $existingRows,
			'conflict_count' => $comparison['conflict_entity_count'],
			'metadata_rows_written' => 0,
			'audit_rows_written' => 0,
			'transaction_committed' => false,
			'write_executed' => false,
		);
	}

	private function result($registrationResult, $metadataRows, $auditRows, $committed, $existingRows, $conflictCount)
	{
		return array(
			'registration_result' => $registrationResult,
			'dry_run' => false,
			'planned_metadata_rows' => self::EXPECTED_ENTITY_COUNT,
			'existing_metadata_rows' => $existingRows,
			'conflict_count' => $conflictCount,
			'metadata_rows_written' => $metadataRows,
			'audit_rows_written' => $auditRows,
			'transaction_committed' => $committed,
			'write_executed' => true,
		);
	}

	private function auditRow(array $model, $reference, $before, $after, $result, $rowsInserted, $committed, $failureCode)
	{
		return array(
			'operation_type' => 'metadata_registration',
			'execution_mode' => 'apply',
			'registration_reference' => $reference,
			'package_key' => $model['package']['persisted']['package_key'],
			'package_version' => $model['package']['persisted']['package_version'],
			'package_checksum' => $model['package_checksum'],
			'package_checksum_profile' => $model['package_checksum_profile'],
			'package_snapshot_sha256' => $model['package_snapshot_sha256'],
			'metadata_entity_count' => self::EXPECTED_ENTITY_COUNT,
			'metadata_rows_planned' => self::EXPECTED_ENTITY_COUNT,
			'metadata_rows_inserted' => $rowsInserted,
			'state_before' => $before,
			'state_after' => $after,
			'result' => $result,
			'idempotent_noop' => $result === 'idempotent_noop' ? 1 : 0,
			'metadata_transaction_committed' => $committed,
			'safe_failure_code' => $failureCode,
		);
	}

	private function inject($point)
	{
		if ($this->failureInjector !== null) call_user_func($this->failureInjector, $point);
	}
}
